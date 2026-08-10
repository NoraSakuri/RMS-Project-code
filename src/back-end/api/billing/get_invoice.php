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
        [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ],
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
    ['CASHIER', 'ADMIN', 'MANAGER'],
    true
)) {
    respond(
        false,
        'You do not have permission to view invoices.',
        [],
        403
    );
}

$invoiceId = filter_input(
    INPUT_GET,
    'invoice_id',
    FILTER_VALIDATE_INT
);

if (!$invoiceId || $invoiceId < 1) {
    respond(
        false,
        'Invalid invoice ID.',
        [],
        422
    );
}

try {
    $invoiceStatement = $pdo->prepare("
        SELECT
            i.invoice_id,
            i.order_id,
            i.payment_id,
            i.invoice_date,
            i.subtotal,
            i.tax_amount,
            i.discount_amount,
            i.final_total,

            p.payment_method,
            p.amount_paid,
            p.payment_status,
            p.payment_date,

            o.table_id,
            o.staff_id,
            o.customer_note,
            o.order_date,
            o.status AS order_status,

            t.table_number,

            waiter.name AS waiter_name

        FROM invoices i

        INNER JOIN payments p
            ON p.payment_id = i.payment_id

        INNER JOIN orders o
            ON o.order_id = i.order_id

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        LEFT JOIN staff waiter
            ON waiter.staff_id = o.staff_id

        WHERE i.invoice_id = :invoice_id

        LIMIT 1
    ");

    $invoiceStatement->execute([
        ':invoice_id' => $invoiceId
    ]);

    $invoice = $invoiceStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$invoice) {
        respond(
            false,
            'Invoice was not found.',
            [],
            404
        );
    }

    $itemStatement = $pdo->prepare("
        SELECT
            od.order_detail_id,
            od.item_id,
            od.quantity,
            od.price,
            od.subtotal,
            m.name
        FROM order_details od

        INNER JOIN menu_items m
            ON m.item_id = od.item_id

        WHERE od.order_id = :order_id

        ORDER BY od.order_detail_id ASC
    ");

    $itemStatement->execute([
        ':order_id' => $invoice['order_id']
    ]);

    $items = $itemStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $invoice['items'] = $items;

    respond(
        true,
        'Invoice loaded successfully.',
        $invoice
    );
} catch (Throwable $exception) {
    error_log(
        'Invoice detail error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load invoice.',
        [],
        500
    );
}