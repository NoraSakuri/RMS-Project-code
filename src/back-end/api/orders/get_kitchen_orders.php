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
        'You do not have permission to view kitchen orders.',
        [],
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(
        false,
        'Invalid request method.',
        [],
        405
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Read active kitchen orders
    |--------------------------------------------------------------------------
    */

    $orderStatement = $pdo->prepare("
        SELECT
            o.order_id,
            o.table_id,
            o.staff_id,
            o.customer_note,
            o.status,
            o.chef_action,
            o.order_date,
            t.table_number,
            s.name AS waiter_name
        FROM orders o
        INNER JOIN rms_tables t
            ON t.table_id = o.table_id
        LEFT JOIN staff s
            ON s.staff_id = o.staff_id
        WHERE DATE(o.order_date) = CURDATE()
          AND o.status IN (
              'PENDING',
              'CONFIRMED',
              'PREPARING',
              'READY'
          )
        ORDER BY
            CASE o.status
                WHEN 'PENDING' THEN 1
                WHEN 'CONFIRMED' THEN 2
                WHEN 'PREPARING' THEN 3
                WHEN 'READY' THEN 4
                ELSE 5
            END,
            o.order_date ASC
    ");

    $orderStatement->execute();

    $orders = $orderStatement->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Read order items
    |--------------------------------------------------------------------------
    */

    $itemStatement = $pdo->prepare("
        SELECT
            od.item_id,
            od.quantity,
            mi.name
        FROM order_details od
        INNER JOIN menu_items mi
            ON mi.item_id = od.item_id
        WHERE od.order_id = :order_id
        ORDER BY od.order_detail_id ASC
    ");

    foreach ($orders as &$order) {
        $itemStatement->execute([
            ':order_id' => $order['order_id']
        ]);

        $order['items'] = $itemStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

        $order['order_id'] = (int) $order['order_id'];
        $order['table_id'] = (int) $order['table_id'];
        $order['table_number'] = (int) $order['table_number'];

        foreach ($order['items'] as &$item) {
            $item['item_id'] = (int) $item['item_id'];
            $item['quantity'] = (int) $item['quantity'];
        }

        unset($item);
    }

    unset($order);

    respond(
        true,
        'Kitchen orders loaded successfully.',
        $orders
    );
} catch (Throwable $exception) {
    error_log(
        'Kitchen order read error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load kitchen orders.',
        [],
        500
    );
}