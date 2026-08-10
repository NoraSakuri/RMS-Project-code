<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";

/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/

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
    ['ADMIN', 'MANAGER'],
    true
)) {
    respond(
        false,
        'You do not have permission to create tables.',
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
| Read JSON Input
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
| Validate Table Number
|--------------------------------------------------------------------------
*/

$tableNumber = filter_var(
    $input['table_number'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$tableNumber || $tableNumber < 1) {
    respond(
        false,
        'Table number must be a positive whole number.',
        [],
        422
    );
}

/*
|--------------------------------------------------------------------------
| Validate Capacity
|--------------------------------------------------------------------------
*/

$capacity = filter_var(
    $input['capacity'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    !$capacity
    || $capacity < 1
    || $capacity > 50
) {
    respond(
        false,
        'Capacity must be between 1 and 50.',
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
            ?? 'AVAILABLE'
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
    | Check Duplicate Table Number
    |--------------------------------------------------------------------------
    */

    $duplicateStatement = $pdo->prepare("
        SELECT table_id
        FROM rms_tables
        WHERE table_number = :table_number
        LIMIT 1
    ");

    $duplicateStatement->execute([
        ':table_number' => $tableNumber
    ]);

    if ($duplicateStatement->fetchColumn()) {
        respond(
            false,
            'This table number already exists.',
            [],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Insert Table
    |--------------------------------------------------------------------------
    */

    $insertStatement = $pdo->prepare("
        INSERT INTO rms_tables (
            table_number,
            capacity,
            table_status
        ) VALUES (
            :table_number,
            :capacity,
            :table_status
        )
    ");

    $insertStatement->execute([
        ':table_number' => $tableNumber,
        ':capacity' => $capacity,
        ':table_status' => $tableStatus
    ]);

    $tableId = (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Return Created Table
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Table created successfully.',
        [
            'table' => [
                'table_id' => $tableId,
                'table_number' => $tableNumber,
                'capacity' => $capacity,
                'table_status' => $tableStatus
            ]
        ],
        201
    );

} catch (PDOException $exception) {
    error_log(
        'Table create database error: '
        . $exception->getMessage()
    );

    if (
        (string) $exception->getCode()
        === '23000'
    ) {
        respond(
            false,
            'This table number already exists.',
            [],
            409
        );
    }

    respond(
        false,
        'Unable to create table.',
        [],
        500
    );

} catch (Throwable $exception) {
    error_log(
        'Table create error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to create table.',
        [],
        500
    );
}