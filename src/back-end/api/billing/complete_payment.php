<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";

function respond(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (empty($_SESSION['staff_id'])) {
    respond(
        false,
        'Your session has expired.',
        [],
        401
    );
}

$role = strtoupper((string) ($_SESSION['role'] ?? ''));

if (!in_array(
    $role,
    ['CASHIER', 'ADMIN', 'MANAGER'],
    true
)) {
    respond(
        false,
        'You do not have permission to process payments.',
        [],
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(
        false,
        'Invalid request method.',
        [],
        405
    );
}

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    respond(
        false,
        'Invalid payment data.',
        [],
        422
    );
}

$orderId = filter_var(
    $input['order_id'] ?? null,
    FILTER_VALIDATE_INT
);

$paymentMethod = strtoupper(
    trim((string) ($input['payment_method'] ?? ''))
);

$discountAmount = filter_var(
    $input['discount_amount'] ?? 0,
    FILTER_VALIDATE_FLOAT
);

if ($discountAmount === false) {
    $discountAmount = 0.0;
}

$discountAmount = (float) $discountAmount;

if (!$orderId || $orderId < 1) {
    respond(
        false,
        'Invalid order ID.',
        [],
        422
    );
}

if (!in_array(
    $paymentMethod,
    ['CASH', 'CREDIT_CARD'],
    true
)) {
    respond(
        false,
        'Invalid payment method.',
        [],
        422
    );
}

if ($discountAmount < 0) {
    respond(
        false,
        'Discount cannot be negative.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Lock and validate order
    |--------------------------------------------------------------------------
    */

    $orderStatement = $pdo->prepare("
        SELECT
            order_id,
            table_id,
            status,
            subtotal,
            tax_amount,
            discount_amount,
            total_amount
        FROM orders
        WHERE order_id = :order_id
        FOR UPDATE
    ");

    $orderStatement->execute([
        ':order_id' => $orderId
    ]);

    $order = $orderStatement->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException(
            'Order was not found.'
        );
    }

    if ($order['status'] !== 'READY') {
        throw new RuntimeException(
            'Only ready orders can be paid.'
        );
    }

    $subtotal = (float) $order['subtotal'];
    $taxAmount = (float) $order['tax_amount'];

    if ($discountAmount > $subtotal) {
        throw new RuntimeException(
            'Discount cannot exceed subtotal.'
        );
    }

    $totalAmount = $subtotal
        + $taxAmount
        - $discountAmount;

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate payment
    |--------------------------------------------------------------------------
    */

    $existingPaymentStatement = $pdo->prepare("
        SELECT payment_id
        FROM payments
        WHERE order_id = :order_id
          AND payment_status = 'PAID'
        LIMIT 1
    ");

    $existingPaymentStatement->execute([
        ':order_id' => $orderId
    ]);

    if ($existingPaymentStatement->fetchColumn()) {
        throw new RuntimeException(
            'This order has already been paid.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Insert payment
    |--------------------------------------------------------------------------
    */

    $paymentStatement = $pdo->prepare("
        INSERT INTO payments (
            order_id,
            payment_method,
            amount_paid,
            payment_status
        ) VALUES (
            :order_id,
            :payment_method,
            :amount_paid,
            'PAID'
        )
    ");

    $paymentStatement->execute([
        ':order_id' => $orderId,
        ':payment_method' => $paymentMethod,
        ':amount_paid' => $totalAmount
    ]);

    $paymentId = (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Insert invoice
    |--------------------------------------------------------------------------
    */

    $invoiceStatement = $pdo->prepare("
        INSERT INTO invoices (
            order_id,
            payment_id,
            subtotal,
            tax_amount,
            discount_amount,
            final_total
        ) VALUES (
            :order_id,
            :payment_id,
            :subtotal,
            :tax_amount,
            :discount_amount,
            :final_total
        )
    ");

    $invoiceStatement->execute([
        ':order_id' => $orderId,
        ':payment_id' => $paymentId,
        ':subtotal' => $subtotal,
        ':tax_amount' => $taxAmount,
        ':discount_amount' => $discountAmount,
        ':final_total' => $totalAmount
    ]);

    $invoiceId = (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Complete order
    |--------------------------------------------------------------------------
    */

    $completeOrderStatement = $pdo->prepare("
    UPDATE orders
    SET
        discount_amount = :discount_amount,
        total_amount = :total_amount,
        status = 'COMPLETED',
        chef_action = 'COMPLETED'
    WHERE order_id = :order_id
    ");

    $completeOrderStatement->execute([
        ':discount_amount' => $discountAmount,
        ':total_amount' => $totalAmount,
        ':order_id' => $orderId
    ]);

    $updateTable = $pdo->prepare("
    UPDATE rms_tables
    SET table_status='AVAILABLE'
    WHERE table_id=(
        SELECT table_id
        FROM orders
        WHERE order_id=:order_id
    )
");

    $updateTable->execute([
        ":order_id" => $orderId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Release table only when no other active order exists
    |--------------------------------------------------------------------------
    */

    $activeOrderStatement = $pdo->prepare("
        SELECT COUNT(*)
        FROM orders
        WHERE table_id = :table_id
          AND status NOT IN ('COMPLETED', 'CANCELLED')
    ");

    $activeOrderStatement->execute([
        ':table_id' => $order['table_id']
    ]);

    $activeOrders = (int) $activeOrderStatement->fetchColumn();

    if ($activeOrders === 0) {
        $tableStatement = $pdo->prepare("
            UPDATE rms_tables
            SET table_status = 'AVAILABLE'
            WHERE table_id = :table_id
        ");

        $tableStatement->execute([
            ':table_id' => $order['table_id']
        ]);
    }

    $pdo->commit();

    $invoiceNumber = 'INV-' . str_pad(
        (string) $invoiceId,
        5,
        '0',
        STR_PAD_LEFT
    );

    respond(
        true,
        'Payment completed successfully.',
        [
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'order_id' => $orderId,
            'amount_paid' => $totalAmount,
            'discount_amount' => $discountAmount,
            'payment_method' => $paymentMethod
        ],
        201
    );
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond(
        false,
        $exception->getMessage(),
        [],
        422
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Payment error: '
            . $exception->getMessage()
    );

    respond(
        false,
        'Unable to complete payment.',
        [],
        500
    );
}
