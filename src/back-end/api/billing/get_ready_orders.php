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

$role = strtoupper((string) ($_SESSION['role'] ?? ''));

if (!in_array(
    $role,
    ['CASHIER', 'ADMIN', 'MANAGER'],
    true
)) {
    respond(
        false,
        'You do not have permission to access billing.',
        [],
        403
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Ready and unpaid orders
    |--------------------------------------------------------------------------
    */

    $orderStatement = $pdo->prepare("
        SELECT
            o.order_id,
            o.table_id,
            o.staff_id,
            o.customer_note,
            o.order_date,
            o.status,
            o.subtotal,
            o.tax_amount,
            o.discount_amount,
            o.total_amount,
            t.table_number,
            s.name AS waiter_name
        FROM orders o
        INNER JOIN rms_tables t
            ON t.table_id = o.table_id
        LEFT JOIN staff s
            ON s.staff_id = o.staff_id
        LEFT JOIN payments p
            ON p.order_id = o.order_id
           AND p.payment_status = 'PAID'
        WHERE o.status = 'READY'
          AND p.payment_id IS NULL
        ORDER BY o.order_date ASC
    ");

    $orderStatement->execute();

    $orders = $orderStatement->fetchAll(PDO::FETCH_ASSOC);

    $detailStatement = $pdo->prepare("
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

    foreach ($orders as &$order) {
        $detailStatement->execute([
            ':order_id' => $order['order_id']
        ]);

        $order['items'] = $detailStatement->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    unset($order);

    /*
    |--------------------------------------------------------------------------
    | Billing statistics
    |--------------------------------------------------------------------------
    */

    $statisticsStatement = $pdo->query("
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN payment_status = 'PAID'
                     AND DATE(payment_date) = CURDATE()
                    THEN amount_paid
                    ELSE 0
                END
            ), 0) AS today_revenue,

            SUM(
                CASE
                    WHEN payment_status = 'PAID'
                     AND DATE(payment_date) = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ) AS paid_today
        FROM payments
    ");

    $statistics = $statisticsStatement->fetch(
        PDO::FETCH_ASSOC
    );

    $invoiceCountStatement = $pdo->query("
        SELECT COUNT(*)
        FROM invoices
        WHERE DATE(invoice_date) = CURDATE()
    ");

    $invoiceCount = (int) $invoiceCountStatement->fetchColumn();

    respond(
        true,
        'Billing orders loaded successfully.',
        [
            'orders' => $orders,
            'statistics' => [
                'today_revenue' =>
                    (float) ($statistics['today_revenue'] ?? 0),

                'paid_today' =>
                    (int) ($statistics['paid_today'] ?? 0),

                'invoice_count' => $invoiceCount,

                'pending_payment' => count($orders)
            ]
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'Billing orders error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load billing orders.',
        [],
        500
    );
}