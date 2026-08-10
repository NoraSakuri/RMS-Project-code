<?php
require_once "../../back-end/middleware/auth.php";
allowRoles(["ADMIN"]);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include("../components/sidebar.php"); ?>

        <main class="main">
            <?php include("../components/topbar.php"); ?>

            <section class="content">
                <div class="hero">
                    <div>
                        <h2>Welcome back, Admin 👋</h2>
                        <p>Control staff, menu, orders, reports and restaurant operations.</p>
                    </div>
                </div>

                <div class="cards">
                    <div class="card">
                        <div class="card-icon">👥</div>
                        <h3>Total Staff</h3>
                        <p id="totalStaff">0</p>
                    </div>
                    <div class="card">
                        <div class="card-icon">🧾</div>
                        <h3>Total Orders</h3>
                        <p id="totalOrders">0</p>
                    </div>
                    <div class="card">
                        <div class="card-icon">💰</div>
                        <h3>Revenue</h3>
                        <p id="totalRevenue">0 MMK</p>
                    </div>
                    <div class="card">
                        <div class="card-icon">🍽</div>
                        <h3>Tables</h3>
                        <p id="totalTables">0</p>
                    </div>
                </div>

                <div class="section">
                    <h2>Recent Orders</h2>

                    <div class="chart-container">

                        <div class="chart-card">
                            <h2>Revenue Trend</h2>
                            <canvas id="revenueChart"></canvas>
                        </div>


                        <div class="chart-card">
                            <h2>Payment Methods</h2>
                            <canvas id="paymentChart"></canvas>
                        </div>

                    </div>


                    <div class="chart-card">
                        <h2>Top Selling Items</h2>
                        <canvas id="sellingChart"></canvas>
                    </div>

                    <table class="table">

                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Table</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>

                        

                        <tbody id="recentOrders">
                            <tr>
                                <td colspan="4">
                                    Loading orders...
                                </td>
                            </tr>
                        </tbody>

                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        console.log("dashboard php loaded");
    </script>

    <script src="../assets/js/dashboard.js"></script>
</body>

</html>