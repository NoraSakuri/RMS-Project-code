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

$role = strtoupper((string) ($_SESSION['role'] ?? ''));

if (!in_array($role, ['ADMIN', 'MANAGER'], true)) {
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
    respond(false, 'Invalid inventory data.', [], 422);
}

$itemName = trim((string) ($input['item_name'] ?? ''));
$categoryId = filter_var(
    $input['category_id'] ?? null,
    FILTER_VALIDATE_INT
);
$quantity = filter_var(
    $input['quantity'] ?? null,
    FILTER_VALIDATE_FLOAT
);
$minimumStock = filter_var(
    $input['minimum_stock'] ?? null,
    FILTER_VALIDATE_FLOAT
);
$supplierName = trim(
    (string) ($input['supplier_name'] ?? '')
);
$unitType = strtoupper(
    trim((string) ($input['unit_type'] ?? ''))
);

if ($itemName === '') {
    respond(false, 'Item name is required.', [], 422);
}

if (!$categoryId || $categoryId < 1) {
    respond(false, 'Valid category is required.', [], 422);
}

if ($quantity === false || $quantity < 0) {
    respond(false, 'Invalid quantity.', [], 422);
}

if ($minimumStock === false || $minimumStock < 0) {
    respond(false, 'Invalid minimum stock.', [], 422);
}

$allowedUnits = [
    'GRAM',
    'KILOGRAM',
    'MILLILITER',
    'LITER',
    'PIECE'
];

if (!in_array($unitType, $allowedUnits, true)) {
    respond(false, 'Invalid unit type.', [], 422);
}

try {
    $duplicateStatement = $pdo->prepare("
        SELECT inventory_id
        FROM inventory_items
        WHERE LOWER(item_name) = LOWER(:item_name)
        LIMIT 1
    ");

    $duplicateStatement->execute([
        ':item_name' => $itemName
    ]);

    if ($duplicateStatement->fetchColumn()) {
        throw new RuntimeException(
            'Inventory item already exists.'
        );
    }

    $statement = $pdo->prepare("
        INSERT INTO inventory_items (
            item_name,
            category_id,
            quantity,
            minimum_stock,
            supplier_name,
            unit_type
        ) VALUES (
            :item_name,
            :category_id,
            :quantity,
            :minimum_stock,
            :supplier_name,
            :unit_type
        )
    ");

    $statement->execute([
        ':item_name' => $itemName,
        ':category_id' => $categoryId,
        ':quantity' => $quantity,
        ':minimum_stock' => $minimumStock,
        ':supplier_name' => $supplierName !== ''
            ? $supplierName
            : null,
        ':unit_type' => $unitType
    ]);

    respond(
        true,
        'Inventory item added successfully.',
        [
            'inventory_id' => (int) $pdo->lastInsertId()
        ],
        201
    );
} catch (RuntimeException $exception) {
    respond(false, $exception->getMessage(), [], 422);
} catch (Throwable $exception) {
    error_log(
        'Inventory create error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to add inventory item.',
        [],
        500
    );
}