<?php

declare(strict_types=1);

require_once "../../back-end/middleware/auth.php";

allowRoles([
    "MANAGER"
]);

$pageTitle = "Manager Dashboard";
$roleName = $_SESSION["role"] ?? "MANAGER";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manager Dashboard</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css?v=5"
    >
</head>

<body>

<div class="wrapper">

    <?php include("../components/sidebar.php"); ?>

    <main class="main">

        <?php include("../components/topbar.php"); ?>

        <section class="content">

            <div class="hero">
                <div>
                    <h2>Restaurant Overview 📊</h2>

                    <p>
                        Monitor today’s sales, orders,
                        reservations and inventory conditions.
                    </p>
                </div>
            </div>

            <div class="cards">

                <div class="card">
                    <div class="card-icon">💰</div>

                    <h3>Today Revenue</h3>

                    <p id="managerTodayRevenue">
                        0 MMK
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">🧾</div>

                    <h3>Today Orders</h3>

                    <p id="managerTodayOrders">
                        0
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">⚠️</div>

                    <h3>Low Stock Items</h3>

                    <p id="managerLowStock">
                        0
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">📅</div>

                    <h3>Today Reservations</h3>

                    <p id="managerReservations">
                        0
                    </p>
                </div>

            </div>

            <div class="manager-dashboard-grid">

                <div class="dashboard-panel">

                    <div class="panel-header">
                        <div>
                            <h2>Recent Orders</h2>

                            <p>
                                Today’s latest restaurant orders.
                            </p>
                        </div>

                        <button
                            type="button"
                            id="refreshManagerDashboard"
                            class="refresh-btn"
                        >
                            Refresh
                        </button>
                    </div>

                    <div class="table-responsive">

                        <table class="dashboard-table">

                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Table</th>
                                    <th>Time</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody id="managerRecentOrders">
                                <tr>
                                    <td colspan="5">
                                        Loading orders...
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </div>

                <div class="dashboard-panel">

                    <div class="panel-header">
                        <div>
                            <h2>Low Stock Alert</h2>

                            <p>
                                Inventory items requiring attention.
                            </p>
                        </div>
                    </div>

                    <div class="table-responsive">

                        <table class="dashboard-table">

                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Minimum</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody id="managerLowStockItems">
                                <tr>
                                    <td colspan="4">
                                        Loading inventory...
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>

    </main>

</div>

<script src="../assets/js/manager_dashboard.js?v=1"></script>

</body>
</html>