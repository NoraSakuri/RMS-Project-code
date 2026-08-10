<?php

declare(strict_types=1);

require_once "../../back-end/middleware/auth.php";

allowRoles([
    "WAITER",
    "ADMIN",
    "MANAGER"
]);

$pageTitle = "Waiter Dashboard";
$roleName = $_SESSION["role"] ?? "WAITER";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Waiter Dashboard</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css">
</head>

<body>

    <div class="wrapper">

        <?php include("../components/sidebar.php"); ?>

        <main class="main">

            <?php include("../components/topbar.php"); ?>

            <section class="content">

                <div class="hero">
                    <div>
                        <h2>Take Orders Fast 🧾</h2>

                        <p>
                            Create orders, monitor kitchen progress
                            and manage assigned tables.
                        </p>
                    </div>
                </div>

                <div class="cards">

                    <div class="card">
                        <div class="card-icon">🪑</div>

                        <h3>Available Tables</h3>

                        <p id="availableTables">
                            0
                        </p>
                    </div>

                    <div class="card">
                        <div class="card-icon">🧾</div>

                        <h3>My Orders Today</h3>

                        <p id="myOrders">
                            0
                        </p>
                    </div>

                    <div class="card">
                        <div class="card-icon">⏳</div>

                        <h3>Active Orders</h3>

                        <p id="activeOrders">
                            0
                        </p>
                    </div>

                    <div class="card">
                        <div class="card-icon">✅</div>

                        <h3>Completed Today</h3>

                        <p id="completedOrders">
                            0
                        </p>
                    </div>

                </div>

                <div class="dashboard-panel">

                    <div class="panel-header">
                        <div>
                            <h2>My Recent Orders</h2>

                            <p>
                                Today’s orders and their current kitchen status.
                            </p>
                        </div>

                        <button
                            type="button"
                            id="refreshWaiterDashboard"
                            class="refresh-btn">
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

                            <tbody id="waiterRecentOrders">

                                <tr>
                                    <td colspan="5">
                                        Loading orders...
                                    </td>
                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </section>

        </main>

    </div>

    <script src="../assets/js/waiter_dashboard.js?v=1"></script>

</body>

</html>