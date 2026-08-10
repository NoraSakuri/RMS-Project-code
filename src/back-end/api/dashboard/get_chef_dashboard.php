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

if ($role !== 'CHEF') {
    respond(
        false,
        'You do not have permission to view this dashboard.',
        [],
        403
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Today kitchen order counts
    |--------------------------------------------------------------------------
    */

    $summaryStatement = $pdo->query("
        SELECT
            SUM(
                CASE
                    WHEN UPPER(chef_action) = 'PENDING'
                    THEN 1
                    ELSE 0
                END
            ) AS pending,

            SUM(
                CASE
                    WHEN UPPER(chef_action) IN (
                        'ACCEPTED',
                        'CONFIRMED'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS accepted,

            SUM(
                CASE
                    WHEN UPPER(chef_action) = 'PREPARING'
                    THEN 1
                    ELSE 0
                END
            ) AS preparing,

            SUM(
                CASE
                    WHEN UPPER(chef_action) = 'READY'
                    THEN 1
                    ELSE 0
                END
            ) AS ready

        FROM orders

        WHERE DATE(order_date) = CURDATE()
          AND UPPER(status) NOT IN (
              'CANCELLED',
              'COMPLETED'
          )
          AND UPPER(chef_action) NOT IN (
              'CANCELLED',
              'COMPLETED'
          )
    ");

    $summary = $summaryStatement->fetch(
        PDO::FETCH_ASSOC
    ) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Active kitchen orders today
    |--------------------------------------------------------------------------
    */

    $orderStatement = $pdo->query("
        SELECT
            o.order_id,
            o.order_date,
            o.chef_action AS status,
            t.table_number

        FROM orders o

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        WHERE DATE(o.order_date) = CURDATE()

          AND UPPER(o.status) NOT IN (
              'CANCELLED',
              'COMPLETED'
          )

          AND UPPER(o.chef_action) IN (
              'PENDING',
              'ACCEPTED',
              'CONFIRMED',
              'PREPARING',
              'READY'
          )

        ORDER BY o.order_date ASC
    ");

    $orders = $orderStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $itemStatement = $pdo->prepare("
        SELECT
            mi.name,
            od.quantity

        FROM order_details od

        INNER JOIN menu_items mi
            ON mi.item_id = od.item_id

        WHERE od.order_id = :order_id

        ORDER BY od.order_detail_id ASC
    ");

    foreach ($orders as &$order) {
        $order['order_id'] =
            (int) $order['order_id'];

        $order['table_number'] =
            (int) $order['table_number'];

        $order['status'] = strtoupper(
            (string) $order['status']
        );

        $itemStatement->execute([
            ':order_id' => $order['order_id']
        ]);

        $items = $itemStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

        foreach ($items as &$item) {
            $item['quantity'] =
                (int) $item['quantity'];
        }

        unset($item);

        $order['items'] = $items;
    }

    unset($order);

    respond(
        true,
        'Chef dashboard loaded successfully.',
        [
            'summary' => [
                'pending' =>
                    (int) ($summary['pending'] ?? 0),

                'accepted' =>
                    (int) ($summary['accepted'] ?? 0),

                'preparing' =>
                    (int) ($summary['preparing'] ?? 0),

                'ready' =>
                    (int) ($summary['ready'] ?? 0)
            ],

            'recent_orders' => $orders
        ]
    );

} catch (Throwable $exception) {
    error_log(
        'Chef dashboard error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load chef dashboard.',
        [],
        500
    );
}