<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../controllers/CategoryController.php";

$controller = new CategoryController($pdo);

$data = [
    "category_name" => trim($_POST["category_name"] ?? ""),
    "is_active" => $_POST["is_active"] ?? 1
];

if ($data["category_name"] === "") {
    echo json_encode([
        "success" => false,
        "message" => "Category name is required."
    ]);
    exit;
}

$result = $controller->createCategory($data);

echo json_encode([
    "success" => $result,
    "message" => $result ? "Category added successfully." : "Failed to add category."
]);