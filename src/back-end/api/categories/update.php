<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../controllers/CategoryController.php";

$controller = new CategoryController($pdo);

$id = $_POST["category_id"] ?? 0;

$data = [
    "category_name" => trim($_POST["category_name"] ?? ""),
    "is_active" => $_POST["is_active"] ?? 1
];

if ($id == 0 || $data["category_name"] === "") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid category data."
    ]);
    exit;
}

$result = $controller->updateCategory($id, $data);

echo json_encode([
    "success" => $result,
    "message" => $result ? "Category updated successfully." : "Failed to update category."
]);