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
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', [], 405);
}

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    respond(false, 'Invalid recipe data.', [], 422);
}

$itemId = filter_var(
    $input['item_id'] ?? null,
    FILTER_VALIDATE_INT
);

$inventoryId = filter_var(
    $input['inventory_id'] ?? null,
    FILTER_VALIDATE_INT
);

$requiredQuantity = filter_var(
    $input['required_quantity'] ?? null,
    FILTER_VALIDATE_FLOAT
);

$unitType = strtoupper(
    trim((string) ($input['unit_type'] ?? ''))
);

$allowedUnits = [
    'GRAM',
    'KILOGRAM',
    'MILLILITER',
    'LITER',
    'PIECE'
];

if (!$itemId || $itemId < 1) {
    respond(false, 'Invalid menu item.', [], 422);
}

if (!$inventoryId || $inventoryId < 1) {
    respond(false, 'Invalid inventory item.', [], 422);
}

if (
    $requiredQuantity === false
    || $requiredQuantity <= 0
) {
    respond(
        false,
        'Required quantity must be greater than zero.',
        [],
        422
    );
}

if (!in_array($unitType, $allowedUnits, true)) {
    respond(false, 'Invalid unit type.', [], 422);
}

try {
    $statement = $pdo->prepare("
        INSERT INTO menu_item_inventory (
            item_id,
            inventory_id,
            required_quantity,
            unit_type
        ) VALUES (
            :item_id,
            :inventory_id,
            :required_quantity,
            :unit_type
        )
    ");

    $statement->execute([
        ':item_id' => $itemId,
        ':inventory_id' => $inventoryId,
        ':required_quantity' => $requiredQuantity,
        ':unit_type' => $unitType
    ]);

    respond(
        true,
        'Ingredient added to recipe successfully.',
        [
            'menu_item_inventory_id' =>
                (int) $pdo->lastInsertId()
        ],
        201
    );
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        respond(
            false,
            'This ingredient is already linked to the selected menu item.',
            [],
            422
        );
    }

    error_log(
        'Recipe create error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to add ingredient.',
        [],
        500
    );
}