<?php

declare(strict_types=1);

header(
    "Content-Type: application/json; charset=utf-8"
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__
    . "/../../../../config/database.php";

function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(
        false,
        "Invalid request method.",
        405
    );
}

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_SESSION["staff_id"],
        $_SESSION["role"]
    )
) {
    respond(
        false,
        "Your login session has expired. Please log in again.",
        401
    );
}

$currentRole = strtoupper(
    trim(
        (string) $_SESSION["role"]
    )
);

if ($currentRole !== "ADMIN") {
    respond(
        false,
        "Only administrators can update staff status.",
        403
    );
}

/*
|--------------------------------------------------------------------------
| Read JSON Input
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($input)) {
    respond(
        false,
        "Invalid request data.",
        400
    );
}

$staffId = filter_var(
    $input["staff_id"] ?? null,
    FILTER_VALIDATE_INT
);

$isActive = filter_var(
    $input["is_active"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$staffId) {
    respond(
        false,
        "Invalid staff ID.",
        422
    );
}

if (!in_array($isActive, [0, 1], true)) {
    respond(
        false,
        "Invalid account status.",
        422
    );
}

/*
|--------------------------------------------------------------------------
| Prevent Admin from Disabling Own Account
|--------------------------------------------------------------------------
*/

$currentStaffId = (int) $_SESSION["staff_id"];

if (
    $staffId === $currentStaffId &&
    $isActive === 0
) {
    respond(
        false,
        "You cannot disable your own account.",
        422
    );
}

/*
|--------------------------------------------------------------------------
| Update Staff Status
|--------------------------------------------------------------------------
*/

try {
    $accountStatus =
        $isActive === 1
            ? "APPROVED"
            : "REJECTED";

    $statement = $pdo->prepare(
        "
        UPDATE staff
        SET
            is_active = :is_active,
            account_status = :account_status
        WHERE staff_id = :staff_id
        "
    );

    $statement->execute([
        "is_active" => $isActive,
        "account_status" => $accountStatus,
        "staff_id" => $staffId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Confirm Staff Exists
    |--------------------------------------------------------------------------
    */

    if ($statement->rowCount() === 0) {
        $checkStatement = $pdo->prepare(
            "
            SELECT staff_id
            FROM staff
            WHERE staff_id = :staff_id
            LIMIT 1
            "
        );

        $checkStatement->execute([
            "staff_id" => $staffId
        ]);

        if (!$checkStatement->fetch(PDO::FETCH_ASSOC)) {
            respond(
                false,
                "Staff account was not found.",
                404
            );
        }

        respond(
            true,
            "The staff account already has this status."
        );
    }

    respond(
        true,
        $isActive === 1
            ? "Staff account approved and activated."
            : "Staff account rejected and disabled."
    );

} catch (Throwable $exception) {
    error_log(
        "Update staff status error: "
        . $exception->getMessage()
    );

    respond(
        false,
        "Unable to update staff status.",
        500
    );
}