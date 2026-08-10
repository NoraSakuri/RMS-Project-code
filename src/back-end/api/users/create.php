<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../middleware/auth.php";

allowRoles(["ADMIN"]);


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
    respond(false, "Invalid request method.", 405);
}

$name = trim((string) ($_POST["name"] ?? ""));
$username = trim((string) ($_POST["username"] ?? ""));
$password = (string) ($_POST["password"] ?? "");
$role = strtoupper(trim((string) ($_POST["role"] ?? "")));
$status = strtolower(trim((string) ($_POST["status"] ?? "active")));

$allowedRoles = [
    "ADMIN",
    "MANAGER",
    "WAITER",
    "CHEF",
    "CASHIER"
];

if ($name === "" || $username === "" || $password === "" || $role === "") {
    respond(false, "Please complete all required fields.", 422);
}

if (!in_array($role, $allowedRoles, true)) {
    respond(false, "Invalid staff role.", 422);
}

if (strlen($password) < 6) {
    respond(false, "Password must be at least 6 characters.", 422);
}

$isActive = $status === "active" ? 1 : 0;

try {
    $checkStatement = $pdo->prepare("
        SELECT staff_id
        FROM staff
        WHERE LOWER(username) = LOWER(:username)
        LIMIT 1
    ");

    $checkStatement->execute([
        "username" => $username
    ]);

    if ($checkStatement->fetch(PDO::FETCH_ASSOC)) {
        respond(false, "Username already exists.", 409);
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $insertStatement = $pdo->prepare("
        INSERT INTO staff
        (
            name,
            username,
            password,
            role,
            is_active
        )
        VALUES
        (
            :name,
            :username,
            :password,
            :role,
            :is_active
        )
    ");

    $insertStatement->execute([
        "name" => $name,
        "username" => $username,
        "password" => $passwordHash,
        "role" => $role,
        "is_active" => $isActive
    ]);

    respond(true, "Staff created successfully.", 201);

} catch (Throwable $exception) {
    error_log(
        "Create staff error: " .
        $exception->getMessage()
    );

    respond(false, "Unable to create staff.", 500);
}