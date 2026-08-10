<?php

declare(strict_types=1);

header(
    "Content-Type: application/json; charset=utf-8"
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../middleware/auth.php";

allowRoles([
    "ADMIN"
]);

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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(
        false,
        "Invalid request method.",
        405
    );
}

$staffId = filter_var(
    $_POST["staff_id"] ?? null,
    FILTER_VALIDATE_INT
);

$name = trim(
    (string) ($_POST["name"] ?? "")
);

$username = trim(
    (string) ($_POST["username"] ?? "")
);

$password = (string) (
    $_POST["password"] ?? ""
);

$role = strtoupper(
    trim(
        (string) ($_POST["role"] ?? "")
    )
);

$status = strtolower(
    trim(
        (string) ($_POST["status"] ?? "inactive")
    )
);

$allowedRoles = [
    "ADMIN",
    "MANAGER",
    "WAITER",
    "CHEF",
    "CASHIER"
];

if (!$staffId) {
    respond(
        false,
        "Invalid staff ID.",
        422
    );
}

if (
    $name === "" ||
    $username === "" ||
    $role === ""
) {
    respond(
        false,
        "Please complete all required fields.",
        422
    );
}

if (
    !in_array(
        $role,
        $allowedRoles,
        true
    )
) {
    respond(
        false,
        "Invalid staff role.",
        422
    );
}

if (
    $password !== "" &&
    strlen($password) < 6
) {
    respond(
        false,
        "New password must be at least 6 characters.",
        422
    );
}

$isActive =
    $status === "active"
        ? 1
        : 0;

$currentStaffId = (int) (
    $_SESSION["staff_id"] ?? 0
);

/*
|--------------------------------------------------------------------------
| Prevent the logged-in admin from disabling own account
|--------------------------------------------------------------------------
*/

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

try {
    /*
    |--------------------------------------------------------------------------
    | Check whether username is used by another staff
    |--------------------------------------------------------------------------
    */

    $checkStatement = $pdo->prepare(
        "
        SELECT staff_id
        FROM staff
        WHERE LOWER(username) =
              LOWER(:username)
          AND staff_id <> :staff_id
        LIMIT 1
        "
    );

    $checkStatement->execute([
        "username" => $username,
        "staff_id" => $staffId
    ]);

    if (
        $checkStatement->fetch(
            PDO::FETCH_ASSOC
        )
    ) {
        respond(
            false,
            "Username already exists.",
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update with or without a new password
    |--------------------------------------------------------------------------
    */

    if ($password !== "") {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $updateStatement = $pdo->prepare(
            "
            UPDATE staff
            SET
                name = :name,
                username = :username,
                password = :password,
                role = :role,
                is_active = :is_active
            WHERE staff_id = :staff_id
            "
        );

        $updateStatement->execute([
            "name" => $name,
            "username" => $username,
            "password" => $passwordHash,
            "role" => $role,
            "is_active" => $isActive,
            "staff_id" => $staffId
        ]);
    } else {
        $updateStatement = $pdo->prepare(
            "
            UPDATE staff
            SET
                name = :name,
                username = :username,
                role = :role,
                is_active = :is_active
            WHERE staff_id = :staff_id
            "
        );

        $updateStatement->execute([
            "name" => $name,
            "username" => $username,
            "role" => $role,
            "is_active" => $isActive,
            "staff_id" => $staffId
        ]);
    }

    respond(
        true,
        "Staff account updated successfully."
    );

} catch (Throwable $exception) {
    error_log(
        "Update staff error: "
        . $exception->getMessage()
    );

    respond(
        false,
        "Unable to update staff account.",
        500
    );
}