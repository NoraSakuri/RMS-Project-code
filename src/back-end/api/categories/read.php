<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../controllers/CategoryController.php";

$controller = new CategoryController($pdo);

echo json_encode([
    "success" => true,
    "data" => $controller->getCategories()
]);