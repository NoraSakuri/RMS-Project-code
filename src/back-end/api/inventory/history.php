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

if (!in_array($role, ['ADMIN', 'MANAGER'], true)) {
    respond(
        false,
        'You do not have permission to view inventory history.',
        [],
        403
    );
}

try {
    $statement = $pdo->prepare("
        SELECT
            it.transaction_id,
            it.inventory_id,
            it.order_id,
            it.transaction_type,
            it.quantity,
            it.unit_type,
            it.note,
            it.created_at,

            i.item_name,

            o.status AS order_status,
            o.table_id,

            t.table_number

        FROM inventory_transactions it

        INNER JOIN inventory_items i
            ON i.inventory_id = it.inventory_id

        LEFT JOIN orders o
            ON o.order_id = it.order_id

        LEFT JOIN rms_tables t
            ON t.table_id = o.table_id

        ORDER BY it.created_at DESC,
                 it.transaction_id DESC
    ");

    $statement->execute();

    $transactions = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $statisticsStatement = $pdo->query("
        SELECT
            COUNT(*) AS total_transactions,

            SUM(
                CASE
                    WHEN transaction_type = 'STOCK_OUT'
                    THEN 1
                    ELSE 0
                END
            ) AS stock_out_count,

            SUM(
                CASE
                    WHEN transaction_type = 'STOCK_IN'
                    THEN 1
                    ELSE 0
                END
            ) AS stock_in_count,

            SUM(
                CASE
                    WHEN transaction_type = 'RETURN'
                    THEN 1
                    ELSE 0
                END
            ) AS return_count

        FROM inventory_transactions
    ");

    $statistics = $statisticsStatement->fetch(
        PDO::FETCH_ASSOC
    );

    respond(
        true,
        'Inventory history loaded successfully.',
        [
            'transactions' => $transactions,

            'statistics' => [
                'total_transactions' =>
                    (int) ($statistics['total_transactions'] ?? 0),

                'stock_out_count' =>
                    (int) ($statistics['stock_out_count'] ?? 0),

                'stock_in_count' =>
                    (int) ($statistics['stock_in_count'] ?? 0),

                'return_count' =>
                    (int) ($statistics['return_count'] ?? 0)
            ]
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'Inventory history error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load inventory history.',
        [],
        500
    );
}