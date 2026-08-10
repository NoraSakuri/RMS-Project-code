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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(false, "Invalid request method.", [], 405);
}

$customerName = trim(
    (string) ($_POST["customer_name"] ?? "")
);

$tableId = filter_var(
    $_POST["table_id"] ?? null,
    FILTER_VALIDATE_INT
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
    (string) ($_POST["reservation_status"] ?? "PENDING")
));

$staffId = (int) ($_SESSION["staff_id"] ?? 0);

$allowedStatuses = [
    "PENDING",
    "CONFIRMED"
];

if (
    $customerName === "" ||
    !$tableId ||
    !$numberOfGuests ||
    $reservationDate === "" ||
    $reservationTime === "" ||
    $staffId <= 0
) {
    respond(
        false,
        "Please complete all required fields.",
        [],
        422
    );
}

if ($numberOfGuests < 1) {
    respond(
        false,
        "Number of guests must be at least 1.",
        [],
        422
    );
}

if (!in_array($status, $allowedStatuses, true)) {
    respond(false, "Invalid reservation status.", [], 422);
}

$dateTimeString = $reservationDate . " " . $reservationTime;

$dateTime = DateTime::createFromFormat(
    "Y-m-d H:i",
    $dateTimeString
);

if (!$dateTime) {
    respond(
        false,
        "Invalid reservation date or time.",
        [],
        422
    );
}

if ($dateTime->getTimestamp() < time()) {
    respond(
        false,
        "Reservation date and time cannot be in the past.",
        [],
        422
    );
}

$formattedDateTime = $dateTime->format("Y-m-d H:i:s");

try {
    /*
    |--------------------------------------------------------------------------
    | Check table and capacity
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    $tableStatement = $pdo->prepare("
        SELECT
            table_id,
            table_number,
            capacity
        FROM rms_tables
        WHERE table_id = :table_id
        LIMIT 1
    ");

    $tableStatement->execute([
        "table_id" => $tableId
    ]);

    $table = $tableStatement->fetch(PDO::FETCH_ASSOC);

    if (!$table) {
        respond(false, "Selected table was not found.", [], 404);
    }

    if ($numberOfGuests > (int) $table["capacity"]) {
        respond(
            false,
            "Guest count exceeds the selected table capacity.",
            [],
            422
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate reservation
    |--------------------------------------------------------------------------
    */

    $duplicateStatement = $pdo->prepare("
        SELECT reservation_id
        FROM reservations
        WHERE table_id = :table_id
          AND reservation_date = :reservation_date
          AND reservation_status IN ('PENDING', 'CONFIRMED')
        LIMIT 1
    ");

    $duplicateStatement->execute([
        "table_id" => $tableId,
        "reservation_date" => $formattedDateTime
    ]);

    if ($duplicateStatement->fetch(PDO::FETCH_ASSOC)) {
        respond(
            false,
            "This table is already reserved for that date and time.",
            [],
            409
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Insert reservation
    |--------------------------------------------------------------------------
    */

    $insertStatement = $pdo->prepare("
        INSERT INTO reservations
        (
            table_id,
            staff_id,
            customer_name,
            number_of_guests,
            reservation_date,
            reservation_status
        )
        VALUES
        (
            :table_id,
            :staff_id,
            :customer_name,
            :number_of_guests,
            :reservation_date,
            :reservation_status
        )
    ");

    $insertStatement->execute([
        "table_id" => $tableId,
        "staff_id" => $staffId,
        "customer_name" => $customerName,
        "number_of_guests" => $numberOfGuests,
        "reservation_date" => $formattedDateTime,
        "reservation_status" => $status
    ]);

    if ($status === "CONFIRMED") {
        $updateTableStatement = $pdo->prepare("
        UPDATE rms_tables
        SET table_status = 'RESERVED'
        WHERE table_id = :table_id
    ");

        $updateTableStatement->execute([
            "table_id" => $tableId
        ]);
    }

    $pdo->commit();

    respond(
        true,
        "Reservation created successfully.",
        [
            "reservation_id" =>
            (int) $pdo->lastInsertId()
        ],
        201
    );
} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "Create reservation error: " .
            $exception->getMessage()
    );

    respond(
        false,
        "Unable to create reservation.",
        [],
        500
    );
}
