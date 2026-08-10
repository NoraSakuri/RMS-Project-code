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

$mappingId = filter_var(
    $input['menu_item_inventory_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$mappingId || $mappingId < 1) {
    respond(false, 'Invalid recipe mapping.', [], 422);
}

try {
    $statement = $pdo->prepare("
        DELETE FROM menu_item_inventory
        WHERE menu_item_inventory_id = :mapping_id
    ");

    $statement->execute([
        ':mapping_id' => $mappingId
    ]);

    if ($statement->rowCount() === 0) {
        respond(
            false,
            'Recipe ingredient was not found.',
            [],
            404
        );
    }

    respond(
        true,
        'Ingredient removed successfully.'
    );
} catch (Throwable $exception) {
    error_log(
        'Recipe delete error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to remove ingredient.',
        [],
        500
    );
}