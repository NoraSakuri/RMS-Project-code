<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../controllers/MenuController.php";

function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', 405);
}

$itemId = filter_var(
    $_POST['item_id'] ?? null,
    FILTER_VALIDATE_INT
);

$name = trim($_POST['name'] ?? '');

$categoryId = filter_var(
    $_POST['category_id'] ?? null,
    FILTER_VALIDATE_INT
);

$description = trim($_POST['description'] ?? '');

$price = filter_var(
    $_POST['price'] ?? null,
    FILTER_VALIDATE_FLOAT
);

$availability = isset($_POST['availability'])
    ? (int) $_POST['availability']
    : 1;

if (!$itemId || $itemId < 1) {
    respond(false, 'Invalid menu item.', 422);
}

if ($name === '') {
    respond(false, 'Menu item name is required.', 422);
}

if (!$categoryId || $categoryId < 1) {
    respond(false, 'Please select a valid category.', 422);
}

if ($price === false || $price < 0) {
    respond(false, 'Please enter a valid price.', 422);
}

$availability = $availability === 1 ? 1 : 0;

$controller = new MenuController($pdo);

if (!$controller->categoryExists($categoryId)) {
    respond(
        false,
        'Selected category does not exist or is inactive.',
        422
    );
}

try {
    $result = $controller->updateMenu(
        $itemId,
        [
            'name' => $name,
            'category_id' => $categoryId,
            'description' => $description,
            'price' => (float) $price,
            'availability' => $availability
        ]
    );

    respond(
        $result,
        $result
            ? 'Menu item updated successfully.'
            : 'Unable to update menu item.'
    );
} catch (PDOException $exception) {
    error_log($exception->getMessage());

    respond(
        false,
        'Unable to update menu item.',
        500
    );
}