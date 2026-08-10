<?php

declare(strict_types=1);

header(
    'Content-Type: application/json; charset=utf-8'
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__
    . '/../../../../config/database.php';

function respond(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

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

if ($role !== 'MANAGER') {
    respond(
        false,
        'You do not have permission to view this dashboard.',
        [],
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(
        false,
        'Invalid request method.',
        [],
        405
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Summary cards
    |--------------------------------------------------------------------------
    */

    $summaryStatement = $pdo->query("
        SELECT
            (
                SELECT COALESCE(SUM(amount_paid), 0)
                FROM payments
                WHERE UPPER(payment_status) = 'PAID'
                  AND DATE(payment_date) = CURDATE()
            ) AS today_revenue,

            (
                SELECT COUNT(*)
                FROM orders
                WHERE DATE(order_date) = CURDATE()
                  AND UPPER(status) <> 'CANCELLED'
            ) AS today_orders,

            (
                SELECT COUNT(*)
                FROM inventory_items
                WHERE quantity <= minimum_stock
            ) AS low_stock_items,

            (
                SELECT COUNT(*)
                FROM reservations
                WHERE DATE(reservation_date) = CURDATE()
                  AND UPPER(status) <> 'CANCELLED'
            ) AS today_reservations
    ");

    $summary = $summaryStatement->fetch(
        PDO::FETCH_ASSOC
    ) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Recent orders today
    |--------------------------------------------------------------------------
    */

    $recentOrdersStatement = $pdo->query("
        SELECT
            o.order_id,
            o.status,
            o.total_amount,
            o.order_date,
            t.table_number

        FROM orders o

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        WHERE DATE(o.order_date) = CURDATE()
          AND UPPER(o.status) <> 'CANCELLED'

        ORDER BY o.order_date DESC

        LIMIT 8
    ");

    $recentOrders = $recentOrdersStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($recentOrders as &$order) {
        $order['order_id'] =
            (int) $order['order_id'];

        $order['table_number'] =
            (int) $order['table_number'];

        $order['total_amount'] =
            (float) $order['total_amount'];
    }

    unset($order);

    /*
    |--------------------------------------------------------------------------
    | Low stock inventory
    |--------------------------------------------------------------------------
    */

    $lowStockStatement = $pdo->query("
        SELECT
            inventory_id,
            item_name,
            quantity,
            minimum_stock,
            unit_type

        FROM inventory_items

        WHERE quantity <= minimum_stock

        ORDER BY
            quantity ASC,
            item_name ASC

        LIMIT 8
    ");

    $lowStockItems = $lowStockStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($lowStockItems as &$item) {
        $item['inventory_id'] =
            (int) $item['inventory_id'];

        $item['quantity'] =
            (float) $item['quantity'];

        $item['minimum_stock'] =
            (float) $item['minimum_stock'];
    }

    unset($item);

    respond(
        true,
        'Manager dashboard loaded successfully.',
        [
            'summary' => [
                'today_revenue' =>
                    (float) (
                        $summary['today_revenue'] ?? 0
                    ),

                'today_orders' =>
                    (int) (
                        $summary['today_orders'] ?? 0
                    ),

                'low_stock_items' =>
                    (int) (
                        $summary['low_stock_items'] ?? 0
                    ),

                'today_reservations' =>
                    (int) (
                        $summary['today_reservations'] ?? 0
                    )
            ],

            'recent_orders' => $recentOrders,
            'low_stock_items' => $lowStockItems
        ]
    );

} catch (Throwable $exception) {
    error_log(
        'Manager dashboard error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load manager dashboard.',
        [],
        500
    );
}