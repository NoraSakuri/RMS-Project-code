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


// Check login
if (empty($_SESSION['staff_id'])) {

    respond(
        false,
        'Your session has expired.',
        [],
        401
    );
}


try {

    $statement = $pdo->prepare("
        SELECT
            inventory_id,
            item_name,
            unit_type
        FROM inventory_items
        ORDER BY item_name ASC
    ");

    $statement->execute();


    $items = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );


    respond(
        true,
        'Inventory loaded successfully.',
        $items
    );


} catch (Throwable $exception) {

    error_log(
        'Inventory read error: '
        . $exception->getMessage()
    );


    respond(
        false,
        'Unable to load inventory.',
        [],
        500
    );
}