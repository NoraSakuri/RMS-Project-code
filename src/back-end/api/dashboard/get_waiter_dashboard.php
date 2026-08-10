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

if ($role !== 'WAITER') {
    respond(
        false,
        'You do not have permission to view this dashboard.',
        [],
        403
    );
}

$staffId = (int) $_SESSION['staff_id'];

try {
    $availableTablesStatement = $pdo->query("
        SELECT COUNT(*)
        FROM rms_tables
        WHERE UPPER(table_status) = 'AVAILABLE'
    ");

    $availableTables = (int) (
        $availableTablesStatement->fetchColumn() ?: 0
    );

    $summaryStatement = $pdo->prepare("
        SELECT
            COUNT(*) AS my_orders,

            SUM(
                CASE
                    WHEN UPPER(status) IN (
                        'PENDING',
                        'ACCEPTED',
                        'CONFIRMED',
                        'PREPARING',
                        'READY'
                    )
                    THEN 1
                    ELSE 0
                END
            ) AS active_orders,

            SUM(
                CASE
                    WHEN UPPER(status) = 'COMPLETED'
                    THEN 1
                    ELSE 0
                END
            ) AS completed_orders

        FROM orders

        WHERE staff_id = :staff_id
          AND DATE(order_date) = CURDATE()
          AND UPPER(status) <> 'CANCELLED'
    ");

    $summaryStatement->execute([
        ':staff_id' => $staffId
    ]);

    $summary = $summaryStatement->fetch(
        PDO::FETCH_ASSOC
    ) ?: [];

    $recentOrdersStatement = $pdo->prepare("
        SELECT
            o.order_id,
            o.status,
            o.chef_action,
            o.total_amount,
            o.order_date,
            t.table_number

        FROM orders o

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        WHERE o.staff_id = :staff_id
          AND DATE(o.order_date) = CURDATE()
          AND UPPER(o.status) <> 'CANCELLED'
          AND UPPER(o.chef_action) <> 'CANCELLED'

        ORDER BY o.order_date DESC

        LIMIT 10
    ");

    $recentOrdersStatement->execute([
        ':staff_id' => $staffId
    ]);

    $recentOrders = $recentOrdersStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    respond(
        true,
        'Waiter dashboard loaded successfully.',
        [
            'summary' => [
                'available_tables' =>
                    $availableTables,

                'my_orders' =>
                    (int) (
                        $summary['my_orders'] ?? 0
                    ),

                'active_orders' =>
                    (int) (
                        $summary['active_orders'] ?? 0
                    ),

                'completed_orders' =>
                    (int) (
                        $summary['completed_orders'] ?? 0
                    )
            ],

            'recent_orders' => $recentOrders
        ]
    );

} catch (Throwable $exception) {
    error_log(
        'Waiter dashboard error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load waiter dashboard.',
        [],
        500
    );
}