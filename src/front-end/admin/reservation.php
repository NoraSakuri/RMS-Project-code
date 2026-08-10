<?php

require_once "../../back-end/middleware/auth.php";
require_once __DIR__ . "/../../../config/database.php";

allowRoles(["ADMIN", "MANAGER", "WAITER"]);

$pageTitle = "Reservation Management";
$roleName = $_SESSION["role"];

/* Tables for form */
$tableStatement = $pdo->query("
    SELECT
        table_id,
        table_number,
        capacity
    FROM rms_tables
    ORDER BY table_number ASC
");

$tables = $tableStatement->fetchAll(PDO::FETCH_ASSOC);

/* Reservation statistics */
$todayCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE DATE(reservation_date) = CURDATE()
")->fetchColumn();

$confirmedCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE reservation_status = 'CONFIRMED'
    AND reservation_date>=CURDATE()
")->fetchColumn();

$pendingCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE reservation_status = 'PENDING'
    AND reservation_date>=CURDATE()
")->fetchColumn();

$cancelledCount = (int) $pdo->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE reservation_status = 'CANCELLED'
    AND reservation_date>=CURDATE()
")->fetchColumn();

/* Reservation list */
/* Upcoming reservations */

$upcomingStatement = $pdo->query("
    SELECT
        r.reservation_id,
        r.table_id,
        r.customer_name,
        r.number_of_guests,
        r.reservation_date,
        r.reservation_status,
        t.table_number
    FROM reservations r
    INNER JOIN rms_tables t
        ON t.table_id = r.table_id
    WHERE UPPER(r.reservation_status) IN (
        'PENDING',
        'CONFIRMED'
    )
      AND r.reservation_date >= NOW()
    ORDER BY r.reservation_date ASC
");

$upcomingReservations =
    $upcomingStatement->fetchAll(PDO::FETCH_ASSOC);


/* Reservation history */

$historyStatement = $pdo->query("
    SELECT
        r.reservation_id,
        r.table_id,
        r.customer_name,
        r.number_of_guests,
        r.reservation_date,
        r.reservation_status,
        t.table_number
    FROM reservations r
    INNER JOIN rms_tables t
        ON t.table_id = r.table_id
    WHERE UPPER(r.reservation_status) IN (
        'COMPLETED',
        'CANCELLED',
        'EXPIRED'
    )
       OR r.reservation_date < NOW()
    ORDER BY r.reservation_date DESC
");

$reservationHistory =
    $historyStatement->fetchAll(PDO::FETCH_ASSOC);

/* Today schedule */
$todayScheduleStatement = $pdo->query("
    SELECT
        r.customer_name,
        r.number_of_guests,
        r.reservation_date,
        r.reservation_status,
        t.table_number
    FROM reservations r
    INNER JOIN rms_tables t
        ON t.table_id = r.table_id
    WHERE
DATE(r.reservation_date)=CURDATE()
AND r.reservation_date>=NOW()
AND r.reservation_status IN
('PENDING','CONFIRMED')
    ORDER BY r.reservation_date ASC
");

$todaySchedule = $todayScheduleStatement->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reservation Management</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/reservation.css">
</head>

<body>

    <div class="wrapper">

        <?php include("../components/sidebar.php"); ?>

        <main class="main">

            <?php include("../components/topbar.php"); ?>

            <section class="content">

                <div class="reservation-header">
                    <div>
                        <h2>Reservation Management</h2>
                        <p>Manage customer bookings, table availability and reservation status.</p>
                    </div>

                    <button class="add-reservation-btn" onclick="openReservationModal()">
                        + Add Reservation
                    </button>
                </div>

                <div class="reservation-stats">
                    <div class="reservation-card">
                        <h4>Today</h4>
                        <h2><?= $todayCount ?></h2>
                    </div>

                    <div class="reservation-card confirmed">
                        <h4>Confirmed</h4>
                        <h2><?= $confirmedCount ?></h2>
                    </div>

                    <div class="reservation-card pending">
                        <h4>Pending</h4>
                        <h2><?= $pendingCount ?></h2>
                    </div>

                    <div class="reservation-card cancelled">
                        <h4>Cancelled</h4>
                        <h2><?= $cancelledCount ?></h2>
                    </div>
                </div>

                <div class="reservation-tabs">

                    <button
                        type="button"
                        class="reservation-tab active"
                        data-tab="upcoming">
                        Upcoming Reservations
                    </button>

                    <button
                        type="button"
                        class="reservation-tab"
                        data-tab="history">
                        Reservation History
                    </button>

                </div>

                <div class="reservation-layout">

                    <div class="reservation-list">

                        <div
                            class="reservation-tab-content active"
                            id="upcomingTab">

                            <div class="reservation-toolbar">

                                <input
                                    type="text"
                                    id="searchUpcomingReservation"
                                    placeholder="Search upcoming reservation...">

                                <select id="upcomingStatusFilter">
                                    <option value="ALL">All Status</option>
                                    <option value="CONFIRMED">Confirmed</option>
                                    <option value="PENDING">Pending</option>
                                </select>

                            </div>

                            <div class="reservation-table">

                                <table>

                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Table</th>
                                            <th>Guests</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>

                                    <tbody id="upcomingReservationBody">

                                        <?php if (empty($upcomingReservations)): ?>

                                            <tr>
                                                <td colspan="7" class="empty-cell">
                                                    No upcoming reservations found.
                                                </td>
                                            </tr>

                                        <?php else: ?>

                                            <?php foreach ($upcomingReservations as $reservation): ?>

                                                <?php
                                                $status = strtoupper(
                                                    (string) $reservation["reservation_status"]
                                                );

                                                $statusClass =
                                                    strtolower($status);
                                                ?>

                                                <tr
                                                    class="reservation-row"
                                                    data-id="<?= (int) $reservation["reservation_id"] ?>"
                                                    data-customer="<?= htmlspecialchars(
                                                                        (string) $reservation["customer_name"],
                                                                        ENT_QUOTES,
                                                                        "UTF-8"
                                                                    ) ?>"
                                                    data-table-id="<?= (int) $reservation["table_id"] ?>"
                                                    data-guests="<?= (int) $reservation["number_of_guests"] ?>"
                                                    data-date="<?= date(
                                                                    "Y-m-d",
                                                                    strtotime($reservation["reservation_date"])
                                                                ) ?>"
                                                    data-time="<?= date(
                                                                    "H:i",
                                                                    strtotime($reservation["reservation_date"])
                                                                ) ?>"
                                                    data-status="<?= htmlspecialchars(
                                                                        $status,
                                                                        ENT_QUOTES,
                                                                        "UTF-8"
                                                                    ) ?>">

                                                    <td>
                                                        <?= htmlspecialchars(
                                                            (string) $reservation["customer_name"]
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        Table <?= htmlspecialchars(
                                                                    (string) $reservation["table_number"]
                                                                ) ?>
                                                    </td>

                                                    <td>
                                                        <?= (int) $reservation["number_of_guests"] ?>
                                                    </td>

                                                    <td>
                                                        <?= date(
                                                            "d/m/Y",
                                                            strtotime($reservation["reservation_date"])
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <?= date(
                                                            "g:i A",
                                                            strtotime($reservation["reservation_date"])
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <span class="badge <?= $statusClass ?>">
                                                            <?= ucfirst(strtolower($status)) ?>
                                                        </span>
                                                    </td>

                                                    <td class="reservation-actions">

                                                        <button
                                                            type="button"
                                                            class="edit-btn">
                                                            Edit
                                                        </button>

                                                        <button
                                                            type="button"
                                                            class="complete-btn"
                                                            data-id="<?= (int) $reservation["reservation_id"] ?>">
                                                            Complete
                                                        </button>

                                                        <button
                                                            type="button"
                                                            class="cancel-btn"
                                                            data-id="<?= (int) $reservation["reservation_id"] ?>">
                                                            Cancel
                                                        </button>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>


                        <div
                            class="reservation-tab-content"
                            id="historyTab">

                            <div class="reservation-toolbar">

                                <input
                                    type="text"
                                    id="searchReservationHistory"
                                    placeholder="Search reservation history...">

                                <select id="historyStatusFilter">
                                    <option value="ALL">All Status</option>
                                    <option value="COMPLETED">Completed</option>
                                    <option value="CANCELLED">Cancelled</option>
                                    <option value="EXPIRED">Expired</option>
                                </select>

                            </div>

                            <div class="reservation-table">

                                <table>

                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Table</th>
                                            <th>Guests</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>

                                    <tbody id="reservationHistoryBody">

                                        <?php if (empty($reservationHistory)): ?>

                                            <tr>
                                                <td colspan="6" class="empty-cell">
                                                    No reservation history found.
                                                </td>
                                            </tr>

                                        <?php else: ?>

                                            <?php foreach ($reservationHistory as $reservation): ?>

                                                <?php
                                                $status = strtoupper(
                                                    (string) $reservation["reservation_status"]
                                                );

                                                if (
                                                    !in_array(
                                                        $status,
                                                        ["COMPLETED", "CANCELLED", "EXPIRED"],
                                                        true
                                                    )
                                                ) {
                                                    $status = "EXPIRED";
                                                }

                                                $statusClass =
                                                    strtolower($status);
                                                ?>

                                                <tr
                                                    class="history-row"
                                                    data-customer="<?= htmlspecialchars(
                                                                        (string) $reservation["customer_name"],
                                                                        ENT_QUOTES,
                                                                        "UTF-8"
                                                                    ) ?>"
                                                    data-status="<?= htmlspecialchars(
                                                                        $status,
                                                                        ENT_QUOTES,
                                                                        "UTF-8"
                                                                    ) ?>">

                                                    <td>
                                                        <?= htmlspecialchars(
                                                            (string) $reservation["customer_name"]
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        Table <?= htmlspecialchars(
                                                                    (string) $reservation["table_number"]
                                                                ) ?>
                                                    </td>

                                                    <td>
                                                        <?= (int) $reservation["number_of_guests"] ?>
                                                    </td>

                                                    <td>
                                                        <?= date(
                                                            "d/m/Y",
                                                            strtotime($reservation["reservation_date"])
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <?= date(
                                                            "g:i A",
                                                            strtotime($reservation["reservation_date"])
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <span class="badge <?= $statusClass ?>">
                                                            <?= ucfirst(strtolower($status)) ?>
                                                        </span>
                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        <?php endif; ?>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                    <aside class="calendar-panel">

                        <div class="calendar-header">
                            <h3>Today Schedule</h3>
                            <span><?= date("d M Y") ?></span>
                        </div>

                        <?php if (empty($todaySchedule)): ?>

                            <div class="empty-schedule">
                                No reservations for today.
                            </div>

                        <?php else: ?>

                            <?php foreach ($todaySchedule as $schedule): ?>

                                <?php
                                $scheduleStatus = strtolower(
                                    (string) $schedule["reservation_status"]
                                );
                                ?>

                                <div class="schedule-item <?= $scheduleStatus ?>">

                                    <strong>
                                        <?= date(
                                            "g:i A",
                                            strtotime($schedule["reservation_date"])
                                        ) ?>
                                    </strong>

                                    <div>
                                        <h4>
                                            <?= htmlspecialchars(
                                                (string) $schedule["customer_name"]
                                            ) ?>
                                        </h4>

                                        <p>
                                            Table <?= htmlspecialchars(
                                                        (string) $schedule["table_number"]
                                                    ) ?>
                                            ·
                                            <?= (int) $schedule["number_of_guests"] ?>
                                            Guests
                                        </p>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </aside>

                </div>

            </section>

        </main>

    </div>

    <div class="reservation-modal" id="reservationModal">
        <div class="reservation-modal-content">

            <div class="modal-header">
                <h2 id="reservationModalTitle">Add Reservation</h2>
                <button onclick="closeReservationModal()">✕</button>
            </div>

            <form id="reservationForm">

                <input type="hidden" id="reservationId">

                <div class="form-row">
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" id="customerName" placeholder="Customer name" required>
                    </div>

                    <div class="form-group">
                        <label>Table</label>
                        <select id="tableNumber" required>
                            <option value="">Select table</option>

                            <?php foreach ($tables as $table): ?>
                                <option value="<?= (int) $table["table_id"] ?>">
                                    Table <?= htmlspecialchars((string) $table["table_number"]) ?>
                                    — Capacity <?= (int) $table["capacity"] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Guests</label>
                        <input
                            type="number"
                            id="guestCount"
                            min="1"
                            placeholder="4"
                            required>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select id="reservationStatus" required>
                            <option value="CONFIRMED">Confirmed</option>
                            <option value="PENDING">Pending</option>
                            <option value="CANCELLED">Cancelled</option>
                            <option value="COMPLETED">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="reservationDate" required>
                    </div>

                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" id="reservationTime" required>
                    </div>
                </div>

                <button type="submit" class="save-reservation-btn">
                    Save Reservation
                </button>

            </form>

        </div>
    </div>

    <script src="../assets/js/reservation.js"></script>

</body>

</html>