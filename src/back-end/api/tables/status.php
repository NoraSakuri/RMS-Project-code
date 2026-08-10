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
        'You do not have permission to change table status.',
        [],
        403
    );
}

/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(
        false,
        'Invalid request method.',
        [],
        405
    );
}

/*
|--------------------------------------------------------------------------
| Read Request Body
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Validate Table ID
|--------------------------------------------------------------------------
*/

$tableId = filter_var(
    $input['table_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$tableId || $tableId < 1) {
    respond(
        false,
        'Invalid table ID.',
        [],
        422
    );
}

/*
|--------------------------------------------------------------------------
| Validate Status
|--------------------------------------------------------------------------
*/

$tableStatus = strtoupper(
    trim(
        (string) (
            $input['table_status']
            ?? ''
        )
    )
);

$allowedStatuses = [
    'AVAILABLE',
    'OCCUPIED',
    'RESERVED',
    'CLEANING'
];

if (!in_array(
    $tableStatus,
    $allowedStatuses,
    true
)) {
    respond(
        false,
        'Invalid table status.',
        [],
        422
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Find Table
    |--------------------------------------------------------------------------
    */

    $tableStatement = $pdo->prepare("
        SELECT
            table_id,
            table_number,
            table_status
        FROM rms_tables
        WHERE table_id = :table_id
        LIMIT 1
    ");

    $tableStatement->execute([
        ':table_id' => $tableId
    ]);

    $table = $tableStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$table) {
        respond(
            false,
            'Table was not found.',
            [],
            404
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stop Unnecessary Update
    |--------------------------------------------------------------------------
    */

    $currentStatus = strtoupper(
        (string) $table['table_status']
    );

    if ($currentStatus === $tableStatus) {
        respond(
            true,
            'Table status is already '
            . ucfirst(
                strtolower($tableStatus)
            )
            . '.',
            [
                'table_id' => $tableId,
                'table_number' =>
                    (int) $table['table_number'],
                'table_status' =>
                    $tableStatus
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Status
    |--------------------------------------------------------------------------
    */

    $updateStatement = $pdo->prepare("
        UPDATE rms_tables
        SET table_status = :table_status
        WHERE table_id = :table_id
    ");

    $updateStatement->execute([
        ':table_status' => $tableStatus,
        ':table_id' => $tableId
    ]);

    respond(
        true,
        'Table '
        . $table['table_number']
        . ' status changed to '
        . ucfirst(
            strtolower($tableStatus)
        )
        . '.',
        [
            'table_id' => $tableId,
            'table_number' =>
                (int) $table['table_number'],
            'old_status' =>
                $currentStatus,
            'table_status' =>
                $tableStatus
        ]
    );

} catch (Throwable $exception) {
    error_log(
        'Table status update error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to update table status.',
        [],
        500
    );
}