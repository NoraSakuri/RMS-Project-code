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
        'You do not have permission to view tables.',
        [],
        403
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Load Tables
    |--------------------------------------------------------------------------
    */

    $tableStatement = $pdo->query("
        SELECT
            table_id,
            table_number,
            capacity,
            table_status,
            created_at,
            updated_at
        FROM rms_tables
        ORDER BY table_number ASC
    ");

    $tables = $tableStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($tables as &$table) {
        $table['table_id'] =
            (int) $table['table_id'];

        $table['table_number'] =
            (int) $table['table_number'];

        $table['capacity'] =
            (int) $table['capacity'];
    }

    unset($table);

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    $statisticsStatement = $pdo->query("
        SELECT
            COUNT(*) AS total_tables,

            SUM(
                CASE
                    WHEN table_status = 'AVAILABLE'
                    THEN 1
                    ELSE 0
                END
            ) AS available_count,

            SUM(
                CASE
                    WHEN table_status = 'OCCUPIED'
                    THEN 1
                    ELSE 0
                END
            ) AS occupied_count,

            SUM(
                CASE
                    WHEN table_status = 'RESERVED'
                    THEN 1
                    ELSE 0
                END
            ) AS reserved_count,

            SUM(
                CASE
                    WHEN table_status = 'CLEANING'
                    THEN 1
                    ELSE 0
                END
            ) AS cleaning_count,

            COALESCE(
                SUM(capacity),
                0
            ) AS total_capacity

        FROM rms_tables
    ");

    $statistics = $statisticsStatement->fetch(
        PDO::FETCH_ASSOC
    );

    respond(
        true,
        'Tables loaded successfully.',
        [
            'tables' => $tables,

            'statistics' => [
                'total_tables' =>
                    (int) (
                        $statistics['total_tables']
                        ?? 0
                    ),

                'available_count' =>
                    (int) (
                        $statistics['available_count']
                        ?? 0
                    ),

                'occupied_count' =>
                    (int) (
                        $statistics['occupied_count']
                        ?? 0
                    ),

                'reserved_count' =>
                    (int) (
                        $statistics['reserved_count']
                        ?? 0
                    ),

                'cleaning_count' =>
                    (int) (
                        $statistics['cleaning_count']
                        ?? 0
                    ),

                'total_capacity' =>
                    (int) (
                        $statistics['total_capacity']
                        ?? 0
                    )
            ]
        ]
    );

} catch (Throwable $exception) {
    error_log(
        'Table read error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load tables.',
        [],
        500
    );
}