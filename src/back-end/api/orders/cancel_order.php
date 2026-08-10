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

$role = strtoupper(
    (string) ($_SESSION['role'] ?? '')
);

if (!in_array(
    $role,
    ['ADMIN', 'MANAGER', 'WAITER'],
    true
)) {
    respond(
        false,
        'You do not have permission to cancel orders.',
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
        'Invalid request data.',
        [],
        422
    );
}

$orderId = filter_var(
    $input['order_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$orderId || $orderId < 1) {
    respond(
        false,
        'Invalid order ID.',
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
            status
        FROM orders
        WHERE order_id = :order_id
        FOR UPDATE
    ");

    $orderStatement->execute([
        ':order_id' => $orderId
    ]);

    $order = $orderStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$order) {
        throw new RuntimeException(
            'Order was not found.'
        );
    }

    if ($order['status'] === 'CANCELLED') {
        throw new RuntimeException(
            'This order has already been cancelled.'
        );
    }

    if ($order['status'] === 'COMPLETED') {
        throw new RuntimeException(
            'Completed orders cannot be cancelled.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Find previous stock-out transactions
    |--------------------------------------------------------------------------
    */

    $transactionStatement = $pdo->prepare("
        SELECT
            inventory_id,
            quantity,
            unit_type
        FROM inventory_transactions
        WHERE order_id = :order_id
          AND transaction_type = 'STOCK_OUT'
        FOR UPDATE
    ");

    $transactionStatement->execute([
        ':order_id' => $orderId
    ]);

    $stockTransactions =
        $transactionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate stock return
    |--------------------------------------------------------------------------
    */

    $returnCheckStatement = $pdo->prepare("
        SELECT COUNT(*)
        FROM inventory_transactions
        WHERE order_id = :order_id
          AND transaction_type = 'RETURN'
    ");

    $returnCheckStatement->execute([
        ':order_id' => $orderId
    ]);

    if (
        (int) $returnCheckStatement->fetchColumn() > 0
    ) {
        throw new RuntimeException(
            'Inventory has already been returned for this order.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Return stock
    |--------------------------------------------------------------------------
    */

    $restoreStockStatement = $pdo->prepare("
        UPDATE inventory_items
        SET quantity = quantity + :quantity
        WHERE inventory_id = :inventory_id
    ");

    $returnTransactionStatement = $pdo->prepare("
        INSERT INTO inventory_transactions (
            inventory_id,
            order_id,
            transaction_type,
            quantity,
            unit_type,
            note
        ) VALUES (
            :inventory_id,
            :order_id,
            'RETURN',
            :quantity,
            :unit_type,
            :note
        )
    ");

    foreach ($stockTransactions as $transaction) {
        $restoreStockStatement->execute([
            ':quantity' =>
                $transaction['quantity'],

            ':inventory_id' =>
                $transaction['inventory_id']
        ]);

        $returnTransactionStatement->execute([
            ':inventory_id' =>
                $transaction['inventory_id'],

            ':order_id' =>
                $orderId,

            ':quantity' =>
                $transaction['quantity'],

            ':unit_type' =>
                $transaction['unit_type'],

            ':note' =>
                'Stock returned after order cancellation.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel order
    |--------------------------------------------------------------------------
    */

    $cancelStatement = $pdo->prepare("
        UPDATE orders
        SET
            status = 'CANCELLED',
            chef_action = 'CANCELLED'
        WHERE order_id = :order_id
    ");

    $cancelStatement->execute([
        ':order_id' => $orderId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Release table
    |--------------------------------------------------------------------------
    */

    $tableStatement = $pdo->prepare("
        UPDATE rms_tables
        SET table_status = 'AVAILABLE'
        WHERE table_id = :table_id
    ");

    $tableStatement->execute([
        ':table_id' => $order['table_id']
    ]);

    $pdo->commit();

    respond(
        true,
        'Order cancelled and inventory returned successfully.'
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
        'Cancel order error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to cancel order.',
        [],
        500
    );
}