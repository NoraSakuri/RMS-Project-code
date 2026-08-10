<?php

declare(strict_types=1);

header("Content-Type: application/json");

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../models/Staff.php";

$staff = new Staff($pdo);

$name = trim($_POST["name"] ?? "");
$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";
$role = strtoupper(trim($_POST["role"] ?? ""));

if (
    $name === "" ||
    $username === "" ||
    $password === "" ||
    $confirmPassword === "" ||
    $role === ""
) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill all required fields."
    ]);

    exit;
}

if (strlen($password) < 6) {
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 6 characters."
    ]);

    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode([
        "success" => false,
        "message" => "Passwords do not match."
    ]);

    exit;
}

$allowedRoles = [
    "MANAGER",
    "WAITER",
    "CHEF",
    "CASHIER"
];

if (!in_array($role, $allowedRoles, true)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid role selected."
    ]);

    exit;
}

if ($staff->usernameExists($username)) {
    echo json_encode([
        "success" => false,
        "message" => "Username already exists. Please login instead."
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Public registration creates a pending and inactive staff account
|--------------------------------------------------------------------------
*/

$isActive = 0;

$result = $staff->createStaff(
    $name,
    $username,
    $password,
    $role,
    $isActive
);

echo json_encode([
    "success" => $result,
    "message" => $result
        ? "Registration submitted successfully. Please wait for administrator approval."
        : "Registration failed."
]);

exit;