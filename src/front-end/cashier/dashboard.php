<?php

declare(strict_types=1);

require_once "../../back-end/middleware/auth.php";

allowRoles([
    "CASHIER"
]);

$pageTitle = "Cashier Dashboard";
$roleName = $_SESSION["role"] ?? "CASHIER";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Cashier Dashboard</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css?v=6"
    >

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
                    <h2>Billing & Payment 💳</h2>

                    <p>
                        Review today’s payments, invoices and
                        orders waiting for billing.
                    </p>
                </div>
            </div>

            <div class="cards">

                <div class="card">
                    <div class="card-icon">💰</div>

                    <h3>Today Revenue</h3>

                    <p id="cashierTodayRevenue">
                        0 MMK
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">🧾</div>

                    <h3>Paid Invoices Today</h3>

                    <p id="cashierPaidInvoices">
                        0
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">⏳</div>

                    <h3>Pending Bills</h3>

                    <p id="cashierPendingBills">
                        0
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">💳</div>

                    <h3>Card Payments Today</h3>

                    <p id="cashierCardPayments">
                        0
                    </p>
                </div>

            </div>

            <div class="cashier-dashboard-grid">

                <div class="dashboard-panel">

                    <div class="panel-header">

                        <div>
                            <h2>Recent Payments</h2>

                            <p>
                                Latest completed payments for today.
                            </p>
                        </div>

                        <button
                            type="button"
                            id="refreshCashierDashboard"
                            class="refresh-btn"
                        >
                            Refresh
                        </button>

                    </div>

                    <div class="table-responsive">

                        <table class="dashboard-table">

                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Order</th>
                                    <th>Table</th>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Time</th>
                                </tr>
                            </thead>

                            <tbody id="cashierRecentPayments">

                                <tr>
                                    <td colspan="6">
                                        Loading payments...
                                    </td>
                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

                <div class="dashboard-panel">

                    <div class="panel-header">
                        <div>
                            <h2>Payment Methods</h2>

                            <p>
                                Today’s paid amount by method.
                            </p>
                        </div>
                    </div>

                    <div class="cashier-chart-container">
                        <canvas id="cashierPaymentChart"></canvas>
                    </div>

                </div>

            </div>

            <div class="dashboard-panel cashier-waiting-panel">

                <div class="panel-header">

                    <div>
                        <h2>Orders Waiting for Payment</h2>

                        <p>
                            Ready orders that still require billing.
                        </p>
                    </div>

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

                        <tbody id="cashierPendingOrders">

                            <tr>
                                <td colspan="5">
                                    Loading pending bills...
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>

</div>

<script src="../assets/js/cashier_dashboard.js?v=1"></script>

</body>
</html>