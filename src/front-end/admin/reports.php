<?php
declare(strict_types=1);

require_once "../../back-end/middleware/auth.php";

allowRoles([
    "ADMIN",
    "MANAGER"
]);

$pageTitle = "Reports";

$roleName = strtoupper(
    $_SESSION["role"] ?? ""
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reports Dashboard</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/reports.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<div class="wrapper">

    <?php include "../components/sidebar.php"; ?>

    <main class="main">

        <?php include "../components/topbar.php"; ?>

        <section class="content report-content">

            <!-- Header -->

            <div class="report-page-header">

                <div>
                    <h1>Reports Dashboard</h1>

                    <p>
                        Review restaurant sales, orders,
                        payments and menu performance.
                    </p>
                </div>

            </div>

            <!-- Date Filter -->

            <div class="report-filter-card">

                <div class="quick-filter-buttons">

                    <button
                        type="button"
                        class="report-period-btn active"
                        data-period="today"
                    >
                        Today
                    </button>

                    <button
                        type="button"
                        class="report-period-btn"
                        data-period="last7"
                    >
                        Last 7 Days
                    </button>

                    <button
                        type="button"
                        class="report-period-btn"
                        data-period="month"
                    >
                        This Month
                    </button>

                    <button
                        type="button"
                        class="report-period-btn"
                        data-period="custom"
                    >
                        Custom
                    </button>

                </div>

                <div class="custom-date-filter">

                    <div>
                        <label for="reportStartDate">
                            Start Date
                        </label>

                        <input
                            type="date"
                            id="reportStartDate"
                        >
                    </div>

                    <div>
                        <label for="reportEndDate">
                            End Date
                        </label>

                        <input
                            type="date"
                            id="reportEndDate"
                        >
                    </div>

                    <button
                        type="button"
                        id="applyReportFilter"
                        class="apply-report-btn"
                    >
                        Apply
                    </button>

                    <button
                        type="button"
                        id="resetReportFilter"
                        class="reset-report-btn"
                    >
                        Reset
                    </button>

                </div>

                <p
                    id="selectedReportRange"
                    class="selected-report-range"
                >
                    Loading report period...
                </p>

            </div>

            <!-- Summary Cards -->

            <div class="cards report-summary-cards">

                <div class="report-card">
                    <h3>Net Sales</h3>

                    <p id="netSales">
                        0 MMK
                    </p>
                </div>

                <div class="report-card">
                    <h3>Total Orders</h3>

                    <p id="totalOrders">
                        0
                    </p>
                </div>

                <div class="report-card">
                    <h3>Completed Orders</h3>

                    <p id="completedOrders">
                        0
                    </p>
                </div>

                <div class="report-card">
                    <h3>Cancelled Orders</h3>

                    <p id="cancelledOrders">
                        0
                    </p>
                </div>

                <div class="report-card">
                    <h3>Average Order</h3>

                    <p id="averageOrderValue">
                        0 MMK
                    </p>
                </div>

                <div class="report-card">
                    <h3>Tax Collected</h3>

                    <p id="taxCollected">
                        0 MMK
                    </p>
                </div>

                <div class="report-card">
                    <h3>Discount Given</h3>

                    <p id="discountGiven">
                        0 MMK
                    </p>
                </div>

                <div class="report-card">
                    <h3>Best Seller</h3>

                    <p id="bestSeller">
                        -
                    </p>
                </div>

            </div>

            <!-- Sales Chart -->

            <div class="report-box report-chart-box">

                <div class="report-box-header">
                    <div>
                        <h2>Sales Overview</h2>

                        <p>
                            Revenue trend for the selected period.
                        </p>
                    </div>
                </div>

                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>

            </div>

            <!-- Charts Grid -->

            <div class="grid report-chart-grid">

                <div class="report-box">

                    <div class="report-box-header">
                        <div>
                            <h2>Payment Summary</h2>

                            <p>
                                Paid amount grouped by payment method.
                            </p>
                        </div>
                    </div>

                    <div class="small-chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>

                </div>

                <div class="report-box">

                    <div class="report-box-header">
                        <div>
                            <h2>Order Status</h2>

                            <p>
                                Orders grouped by current status.
                            </p>
                        </div>
                    </div>

                    <div class="small-chart-container">
                        <canvas id="orderStatusChart"></canvas>
                    </div>

                </div>

            </div>

            <!-- Top Menu -->

            <div class="report-box">

                <div class="report-box-header">

                    <div>
                        <h2>Top Selling Menu</h2>

                        <p>
                            Best-performing menu items for the
                            selected period.
                        </p>
                    </div>

                </div>

                <div class="report-table-wrapper">

                    <table class="report-table">

                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                                <th>Sales Share</th>
                            </tr>
                        </thead>

                        <tbody id="topMenu">

                            <tr>
                                <td colspan="5">
                                    Loading menu performance...
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- Detailed Sales -->

            <div class="report-box">

                <div class="report-box-header">

                    <div>
                        <h2>Detailed Sales Transactions</h2>

                        <p>
                            Completed invoices and payment details.
                        </p>
                    </div>

                    <span
                        id="transactionCount"
                        class="report-count"
                    >
                        0 records
                    </span>

                </div>

                <div class="report-table-wrapper">

                    <table class="report-table detailed-report-table">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Order</th>
                                <th>Table</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Subtotal</th>
                                <th>Tax</th>
                                <th>Discount</th>
                                <th>Total</th>
                            </tr>
                        </thead>

                        <tbody id="salesDetailTable">

                            <tr>
                                <td colspan="10">
                                    Loading transaction details...
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- Export -->

            <div class="export report-export">

                <button
                    type="button"
                    id="exportPdfBtn"
                >
                    Export PDF
                </button>

                <button
                    type="button"
                    id="exportExcelBtn"
                >
                    Export Excel
                </button>

            </div>

        </section>

    </main>

</div>

<script src="../assets/js/reports.js"></script>

</body>
</html>