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
    respond(false, 'Invalid request data.', [], 422);
}

$inventoryId = filter_var(
    $input['inventory_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$inventoryId || $inventoryId < 1) {
    respond(false, 'Invalid inventory ID.', [], 422);
}

try {
    $checkStatement = $pdo->prepare("
        SELECT inventory_id
        FROM inventory_items
        WHERE inventory_id = :inventory_id
        LIMIT 1
    ");

    $checkStatement->execute([
        ':inventory_id' => $inventoryId
    ]);

    if (!$checkStatement->fetchColumn()) {
        throw new RuntimeException(
            'Inventory item was not found.'
        );
    }

    $usedStatement = $pdo->prepare("
        SELECT COUNT(*)
        FROM menu_item_inventory
        WHERE inventory_id = :inventory_id
    ");

    $usedStatement->execute([
        ':inventory_id' => $inventoryId
    ]);

    if ((int) $usedStatement->fetchColumn() > 0) {
        throw new RuntimeException(
            'This inventory item is linked to a menu recipe and cannot be deleted.'
        );
    }

    $deleteStatement = $pdo->prepare("
        DELETE FROM inventory_items
        WHERE inventory_id = :inventory_id
    ");

    $deleteStatement->execute([
        ':inventory_id' => $inventoryId
    ]);

    respond(
        true,
        'Inventory item deleted successfully.'
    );
} catch (RuntimeException $exception) {
    respond(false, $exception->getMessage(), [], 422);
} catch (Throwable $exception) {
    error_log(
        'Inventory delete error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to delete inventory item.',
        [],
        500
    );
}