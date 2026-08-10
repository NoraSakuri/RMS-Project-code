<?php

header("Content-Type: application/json");

require_once "../../../../config/database.php";
require_once "../../controllers/MenuController.php";

$controller = new MenuController($pdo);

echo json_encode([
    "success" => true,
    "data" => $controller->getMenus()
]);