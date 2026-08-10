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

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["staff_id"])) {

    respond(
        false,
        "Your session has expired.",
        [],
        401
    );

}

$role = strtoupper($_SESSION["role"] ?? "");

if (!in_array($role, ["ADMIN", "MANAGER"], true)) {

    respond(
        false,
        "Permission denied.",
        [],
        403
    );

}

/*
|--------------------------------------------------------------------------
| Method
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    respond(
        false,
        "Invalid request method.",
        [],
        405
    );

}

$input = json_decode(
    file_get_contents("php://input"),
    true
);

$tableId = filter_var(
    $input["table_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$tableId) {

    respond(
        false,
        "Invalid table ID.",
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

    $stmt = $pdo->prepare("
        SELECT *
        FROM rms_tables
        WHERE table_id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $tableId
    ]);

    $table = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$table) {

        respond(
            false,
            "Table not found.",
            [],
            404
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Don't delete occupied table
    |--------------------------------------------------------------------------
    */

    if ($table["table_status"] === "OCCUPIED") {

        respond(
            false,
            "Cannot delete an occupied table.",
            [],
            409
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    $delete = $pdo->prepare("
        DELETE
        FROM rms_tables
        WHERE table_id = :id
    ");

    $delete->execute([
        ":id" => $tableId
    ]);

    respond(
        true,
        "Table deleted successfully."
    );

} catch (Throwable $e) {

    error_log($e->getMessage());

    respond(
        false,
        "Unable to delete table.",
        [],
        500
    );

}