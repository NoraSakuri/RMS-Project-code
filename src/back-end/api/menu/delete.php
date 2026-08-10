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
        'success' => $success,
        'message' => $message
    ]);

    exit;
}

$itemId = filter_var(
    $_POST['item_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$itemId || $itemId < 1) {
    respond(false, 'Invalid menu item.', 422);
}

$controller = new MenuController($pdo);

try {
    $result = $controller->deleteMenu($itemId);

    respond(
        $result,
        $result
            ? 'Menu item deleted successfully.'
            : 'Unable to delete menu item.'
    );
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        respond(
            false,
            'This menu item has order records. Set it to unavailable instead.',
            409
        );
    }

    error_log($exception->getMessage());

    respond(
        false,
        'Unable to delete menu item.',
        500
    );
}