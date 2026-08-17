<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";

function jsonResponse(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (empty($_SESSION["staff_id"])) {
    jsonResponse(
        false,
        "Your session has expired.",
        [],
        401
    );
}

$role = strtoupper(
    (string) ($_SESSION["role"] ?? "")
);

if (!in_array(
    $role,
    ["WAITER", "ADMIN", "MANAGER"],
    true
)) {
    jsonResponse(
        false,
        "You do not have permission to view orders.",
        [],
        403
    );
}

try {
    $statement = $pdo->prepare("
    SELECT
        o.order_id,
        o.status,
        o.chef_action,
        o.order_date,
        t.table_number
    FROM orders o

    INNER JOIN rms_tables t
        ON t.table_id = o.table_id

    WHERE DATE(o.order_date) = CURDATE()

      AND UPPER(o.status) IN (
         'PENDING',
         'CONFIRMED',
         'PREPARING',
         'READY'
  )

      AND UPPER(o.chef_action) NOT IN (
          'CANCELLED',
          'COMPLETED'
      )

    ORDER BY o.order_id DESC

    LIMIT 12
");

    $statement->execute();

    $orders = $statement->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(
        true,
        "Recent orders loaded successfully.",
        $orders
    );
} catch (Throwable $exception) {
    error_log(
        "Recent orders error: " .
            $exception->getMessage()
    );

    jsonResponse(
        false,
        "Unable to load recent orders.",
        [],
        500
    );
}
