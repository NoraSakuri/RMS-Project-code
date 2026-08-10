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

$tableId = filter_var(
    $_POST["table_id"] ?? null,
    FILTER_VALIDATE_INT
);

$customerName = trim(
    (string) ($_POST["customer_name"] ?? "")
);

$numberOfGuests = filter_var(
    $_POST["number_of_guests"] ?? null,
    FILTER_VALIDATE_INT
);

$reservationDate = trim(
    (string) ($_POST["reservation_date"] ?? "")
);

$reservationTime = trim(
    (string) ($_POST["reservation_time"] ?? "")
);

$status = strtoupper(trim(
    (string) ($_POST["reservation_status"] ?? "")
));

$allowedStatuses = [
    "PENDING",
    "CONFIRMED",
    "CANCELLED",
    "COMPLETED"
];

if (
    !$reservationId ||
    !$tableId ||
    !$numberOfGuests ||
    $customerName === "" ||
    $reservationDate === "" ||
    $reservationTime === ""
) {
    respond(false, "Please complete all required fields.", 422);
}

if (!in_array($status, $allowedStatuses, true)) {
    respond(false, "Invalid reservation status.", 422);
}

$dateTime = DateTime::createFromFormat(
    "Y-m-d H:i",
    $reservationDate . " " . $reservationTime
);

if (!$dateTime) {
    respond(false, "Invalid date or time.", 422);
}

$formattedDateTime = $dateTime->format("Y-m-d H:i:s");

try {
    $pdo->beginTransaction();

    $oldStatement = $pdo->prepare("
        SELECT table_id
        FROM reservations
        WHERE reservation_id = :reservation_id
        LIMIT 1
        FOR UPDATE
    ");

    $oldStatement->execute([
        "reservation_id" => $reservationId
    ]);

    $oldReservation = $oldStatement->fetch(PDO::FETCH_ASSOC);

    if (!$oldReservation) {
        throw new RuntimeException("Reservation not found.");
    }

    $tableStatement = $pdo->prepare("
        SELECT table_id, capacity
        FROM rms_tables
        WHERE table_id = :table_id
        LIMIT 1
    ");

    $tableStatement->execute([
        "table_id" => $tableId
    ]);

    $table = $tableStatement->fetch(PDO::FETCH_ASSOC);

    if (!$table) {
        throw new RuntimeException("Selected table not found.");
    }

    if ($numberOfGuests > (int) $table["capacity"]) {
        throw new RuntimeException(
            "Guest count exceeds table capacity."
        );
    }

    $duplicateStatement = $pdo->prepare("
        SELECT reservation_id
        FROM reservations
        WHERE table_id = :table_id
          AND reservation_date = :reservation_date
          AND reservation_status IN ('PENDING', 'CONFIRMED')
          AND reservation_id <> :reservation_id
        LIMIT 1
    ");

    $duplicateStatement->execute([
        "table_id" => $tableId,
        "reservation_date" => $formattedDateTime,
        "reservation_id" => $reservationId
    ]);

    if ($duplicateStatement->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            "This table is already reserved for that date and time."
        );
    }

    $updateStatement = $pdo->prepare("
        UPDATE reservations
        SET
            table_id = :table_id,
            customer_name = :customer_name,
            number_of_guests = :number_of_guests,
            reservation_date = :reservation_date,
            reservation_status = :reservation_status
        WHERE reservation_id = :reservation_id
    ");

    $updateStatement->execute([
        "table_id" => $tableId,
        "customer_name" => $customerName,
        "number_of_guests" => $numberOfGuests,
        "reservation_date" => $formattedDateTime,
        "reservation_status" => $status,
        "reservation_id" => $reservationId
    ]);

    $oldTableId = (int) $oldReservation["table_id"];

    if ($oldTableId !== $tableId) {
        $pdo->prepare("
            UPDATE rms_tables
            SET table_status = 'AVAILABLE'
            WHERE table_id = :table_id
        ")->execute([
            "table_id" => $oldTableId
        ]);
    }

    $newTableStatus =
        $status === "CONFIRMED"
            ? "RESERVED"
            : "AVAILABLE";

    $pdo->prepare("
        UPDATE rms_tables
        SET table_status = :table_status
        WHERE table_id = :table_id
    ")->execute([
        "table_status" => $newTableStatus,
        "table_id" => $tableId
    ]);

    $pdo->commit();

    respond(true, "Reservation updated successfully.");

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "Update reservation error: " .
        $exception->getMessage()
    );

    respond(false, $exception->getMessage(), 500);
}