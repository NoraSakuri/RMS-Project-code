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

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['staff_id'])) {
    respond(
        false,
        'Your session has expired. Please log in again.',
        [],
        401
    );
}

$role = strtoupper((string) ($_SESSION['role'] ?? ''));

if (!in_array(
    $role,
    ['ADMIN', 'MANAGER'],
    true
)) {
    respond(
        false,
        'You do not have permission to view dashboard analytics.',
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
    | Dashboard summary cards
    |--------------------------------------------------------------------------
    */

    $summaryStatement = $pdo->query("
        SELECT
            (
                SELECT COUNT(*)
                FROM staff
            ) AS total_staff,

            (
                SELECT COUNT(*)
                FROM orders
                WHERE DATE(order_date) = CURDATE()
            ) AS today_orders,

            (
                SELECT COALESCE(SUM(amount_paid), 0)
                FROM payments
                WHERE payment_status = 'PAID'
                  AND DATE(payment_date) = CURDATE()
            ) AS today_revenue,

            (
                SELECT COUNT(*)
                FROM rms_tables
                WHERE table_status = 'AVAILABLE'
            ) AS available_tables,

            (
                SELECT COUNT(*)
                FROM inventory_items
                WHERE quantity <= minimum_stock
            ) AS low_stock_items
    ");

    $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Revenue trend for the last 7 days
    |--------------------------------------------------------------------------
    */

    $revenueStatement = $pdo->query("
        SELECT
            DATE(payment_date) AS revenue_date,
            COALESCE(SUM(amount_paid), 0) AS revenue
        FROM payments
        WHERE payment_status = 'PAID'
          AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY revenue_date ASC
    ");

    $revenueRows = $revenueStatement->fetchAll(PDO::FETCH_ASSOC);

    $revenueByDate = [];

    foreach ($revenueRows as $row) {
        $revenueByDate[$row['revenue_date']] =
            (float) $row['revenue'];
    }

    $revenueTrend = [];

    for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
        $date = date(
            'Y-m-d',
            strtotime("-{$daysAgo} days")
        );

        $revenueTrend[] = [
            'date' => $date,
            'label' => date('D', strtotime($date)),
            'revenue' => $revenueByDate[$date] ?? 0
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Top 5 selling menu items
    |--------------------------------------------------------------------------
    */

    $topSellingStatement = $pdo->query("
        SELECT
            mi.item_id,
            mi.name,
            COALESCE(SUM(od.quantity), 0) AS quantity_sold,
            COALESCE(SUM(od.subtotal), 0) AS sales_amount
        FROM order_details od
        INNER JOIN orders o
            ON o.order_id = od.order_id
        INNER JOIN menu_items mi
            ON mi.item_id = od.item_id
        WHERE o.status = 'COMPLETED'
        GROUP BY
            mi.item_id,
            mi.name
        ORDER BY quantity_sold DESC
        LIMIT 5
    ");

    $topSellingItems = $topSellingStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($topSellingItems as &$item) {
        $item['item_id'] = (int) $item['item_id'];
        $item['quantity_sold'] =
            (int) $item['quantity_sold'];
        $item['sales_amount'] =
            (float) $item['sales_amount'];
    }

    unset($item);

    /*
    |--------------------------------------------------------------------------
    | Payment method distribution
    |--------------------------------------------------------------------------
    */

    $paymentMethodStatement = $pdo->query("
        SELECT
            payment_method,
            COUNT(*) AS payment_count,
            COALESCE(SUM(amount_paid), 0) AS total_amount
        FROM payments
        WHERE payment_status = 'PAID'
        GROUP BY payment_method
        ORDER BY payment_count DESC
    ");

    $paymentMethods = $paymentMethodStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($paymentMethods as &$method) {
        $method['payment_count'] =
            (int) $method['payment_count'];

        $method['total_amount'] =
            (float) $method['total_amount'];
    }

    unset($method);

    /*
    |--------------------------------------------------------------------------
    | Order status overview
    |--------------------------------------------------------------------------
    */

    $orderStatusStatement = $pdo->query("
        SELECT
            status,
            COUNT(*) AS order_count
        FROM orders
        WHERE DATE(order_date) = CURDATE()
        GROUP BY status
    ");

    $orderStatusRows = $orderStatusStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $defaultStatuses = [
        'PENDING' => 0,
        'CONFIRMED' => 0,
        'PREPARING' => 0,
        'READY' => 0,
        'COMPLETED' => 0
    ];

    foreach ($orderStatusRows as $row) {
        $status = strtoupper((string) $row['status']);

        if (array_key_exists($status, $defaultStatuses)) {
            $defaultStatuses[$status] =
                (int) $row['order_count'];
        }
    }

    $orderStatuses = [];

    foreach ($defaultStatuses as $status => $count) {
        $orderStatuses[] = [
            'status' => $status,
            'count' => $count
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Recent orders
    |--------------------------------------------------------------------------
    */

    $recentOrdersStatement = $pdo->query("
        SELECT
            o.order_id,
            o.status,
            o.total_amount,
            o.order_date,
            t.table_number,
            s.name AS staff_name
        FROM orders o
        INNER JOIN rms_tables t
            ON t.table_id = o.table_id
        LEFT JOIN staff s
            ON s.staff_id = o.staff_id
        ORDER BY o.order_date DESC
        LIMIT 8
    ");

    $recentOrders = $recentOrdersStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($recentOrders as &$order) {
        $order['order_id'] = (int) $order['order_id'];
        $order['table_number'] =
            (int) $order['table_number'];
        $order['total_amount'] =
            (float) $order['total_amount'];
    }

    unset($order);

    respond(
        true,
        'Dashboard analytics loaded successfully.',
        [
            'summary' => [
                'total_staff' =>
                    (int) ($summary['total_staff'] ?? 0),

                'today_orders' =>
                    (int) ($summary['today_orders'] ?? 0),

                'today_revenue' =>
                    (float) ($summary['today_revenue'] ?? 0),

                'available_tables' =>
                    (int) ($summary['available_tables'] ?? 0),

                'low_stock_items' =>
                    (int) ($summary['low_stock_items'] ?? 0)
            ],

            'revenue_trend' => $revenueTrend,
            'top_selling_items' => $topSellingItems,
            'payment_methods' => $paymentMethods,
            'order_statuses' => $orderStatuses,
            'recent_orders' => $recentOrders
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'Dashboard analytics error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load dashboard analytics.',
        [],
        500
    );
}