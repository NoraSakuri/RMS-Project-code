<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=utf-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";
require_once __DIR__ . "/../../middleware/auth.php";

allowRoles(["ADMIN", "MANAGER", "WAITER"]);

function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.", 405);
}

$reservationId = filter_var(
    $_POST["reservation_id"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$reservationId) {
    respond(false, "Invalid reservation ID.", 422);
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare("
        SELECT table_id
        FROM reservations
        WHERE reservation_id = :reservation_id
        LIMIT 1
        FOR UPDATE
    ");

    $statement->execute([
        "reservation_id" => $reservationId
    ]);

    $reservation = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        throw new RuntimeException("Reservation not found.");
    }

    $pdo->prepare("
        UPDATE reservations
        SET reservation_status = 'CANCELLED'
        WHERE reservation_id = :reservation_id
    ")->execute([
        "reservation_id" => $reservationId
    ]);

    $pdo->prepare("
        UPDATE rms_tables
        SET table_status = 'AVAILABLE'
        WHERE table_id = :table_id
    ")->execute([
        "table_id" => (int) $reservation["table_id"]
    ]);

    $pdo->commit();

    respond(true, "Reservation cancelled successfully.");

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "Cancel reservation error: " .
        $exception->getMessage()
    );

    respond(false, "Unable to cancel reservation.", 500);
}