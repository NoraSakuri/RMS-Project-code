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

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['staff_id'])) {
    respond(
        false,
        'Your session has expired. Please log in again.',
        [],
        401
    );
}

$role = strtoupper((string) ($_SESSION['role'] ?? ''));

if (!in_array(
    $role,
    ['ADMIN', 'CHEF', 'MANAGER'],
    true
)) {
    respond(
        false,
        'You do not have permission to update kitchen orders.',
        [],
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(
        false,
        'Invalid request method.',
        [],
        405
    );
}

/*
|--------------------------------------------------------------------------
| Read request
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    respond(
        false,
        'Invalid request data.',
        [],
        422
    );
}

$orderId = filter_var(
    $input['order_id'] ?? null,
    FILTER_VALIDATE_INT
);

$newStatus = strtoupper(
    trim((string) ($input['status'] ?? ''))
);

if (!$orderId || $orderId < 1) {
    respond(
        false,
        'Invalid order ID.',
        [],
        422
    );
}

$allowedStatuses = [
    'CONFIRMED',
    'PREPARING',
    'READY'
];

if (!in_array($newStatus, $allowedStatuses, true)) {
    respond(
        false,
        'Invalid kitchen order status.',
        [],
        422
    );
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Lock order
    |--------------------------------------------------------------------------
    */

    $orderStatement = $pdo->prepare("
        SELECT
            order_id,
            table_id,
            status
        FROM orders
        WHERE order_id = :order_id
        FOR UPDATE
    ");

    $orderStatement->execute([
        ':order_id' => $orderId
    ]);

    $order = $orderStatement->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException(
            'Order was not found.'
        );
    }

    $currentStatus = strtoupper(
        (string) $order['status']
    );

    /*
    |--------------------------------------------------------------------------
    | Validate status transition
    |--------------------------------------------------------------------------
    */

    $allowedTransitions = [
        'PENDING' => 'CONFIRMED',
        'CONFIRMED' => 'PREPARING',
        'PREPARING' => 'READY'
    ];

    if (!isset($allowedTransitions[$currentStatus])) {
        throw new RuntimeException(
            'This order can no longer be updated by the kitchen.'
        );
    }

    if ($allowedTransitions[$currentStatus] !== $newStatus) {
        throw new RuntimeException(
            'Invalid status change from '
            . $currentStatus
            . ' to '
            . $newStatus
            . '.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update order
    |--------------------------------------------------------------------------
    */

    $chefActionMap = [
    'CONFIRMED' => 'ACCEPTED',
    'PREPARING' => 'PREPARING',
    'READY' => 'READY'
];

$chefAction = $chefActionMap[$newStatus];

$updateStatement = $pdo->prepare("
    UPDATE orders
    SET
        status = :status,
        chef_action = :chef_action
    WHERE order_id = :order_id
");

$updateStatement->execute([
    ':status' => $newStatus,
    ':chef_action' => $chefAction,
    ':order_id' => $orderId
]);

    /*
    |--------------------------------------------------------------------------
    | Keep table occupied
    |--------------------------------------------------------------------------
    */

    $tableStatement = $pdo->prepare("
        UPDATE rms_tables
        SET table_status = 'OCCUPIED'
        WHERE table_id = :table_id
    ");

    $tableStatement->execute([
        ':table_id' => $order['table_id']
    ]);

    $pdo->commit();

    respond(
        true,
        'Order status updated successfully.',
        [
            'order_id' => $orderId,
            'old_status' => $currentStatus,
            'new_status' => $newStatus
        ]
    );
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond(
        false,
        $exception->getMessage(),
        [],
        422
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Kitchen status update error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to update kitchen order.',
        [],
        500
    );
}