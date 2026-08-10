<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../controllers/CategoryController.php";

$controller = new CategoryController($pdo);

$id = $_POST["category_id"] ?? 0;

if ($id == 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid category."
    ]);
    exit;
}

$result = $controller->deleteCategory($id);

echo json_encode([
    "success" => $result,
    "message" => $result ? "Category deleted successfully." : "Failed to delete category."
]);