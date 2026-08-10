<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";

function respond(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (empty($_SESSION['staff_id'])) {
    respond(false, 'Your session has expired.', [], 401);
}

$role = strtoupper(
    (string) ($_SESSION['role'] ?? '')
);

if (!in_array(
    $role,
    ['ADMIN', 'MANAGER'],
    true
)) {
    respond(false, 'Access denied.', [], 403);
}

$itemId = filter_input(
    INPUT_GET,
    'item_id',
    FILTER_VALIDATE_INT
);

if (!$itemId || $itemId < 1) {
    respond(false, 'Invalid menu item.', [], 422);
}

try {
    $statement = $pdo->prepare("
        SELECT
            mii.menu_item_inventory_id,
            mii.item_id,
            mii.inventory_id,
            mii.required_quantity,
            mii.unit_type,
            m.name AS menu_name,
            i.item_name
        FROM menu_item_inventory mii
        INNER JOIN menu_items m
            ON m.item_id = mii.item_id
        INNER JOIN inventory_items i
            ON i.inventory_id = mii.inventory_id
        WHERE mii.item_id = :item_id
        ORDER BY i.item_name ASC
    ");

    $statement->execute([
        ':item_id' => $itemId
    ]);

    $recipes = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    respond(
        true,
        'Recipe loaded successfully.',
        $recipes
    );
} catch (Throwable $exception) {
    error_log(
        'Recipe read error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load recipe.',
        [],
        500
    );
}