<?php

require_once "../../back-end/middleware/auth.php";

allowRoles(["CASHIER", "ADMIN", "MANAGER"]);

$pageTitle = "Payment History";
$roleName = $_SESSION["role"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Payment History</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/billing.css"
    >
</head>

<body>

<div class="wrapper">

    <?php include "../components/sidebar.php"; ?>

    <main class="main">

        <?php include "../components/topbar.php"; ?>

        <section class="content">

            <div class="billing-header">

                <div>
                    <h2>Payment History</h2>

                    <p>
                        View paid invoices and completed transactions.
                    </p>
                </div>

                <button
                    type="button"
                    class="print-btn"
                    id="refreshPaymentsButton"
                >
                    Refresh
                </button>

            </div>

            <div class="billing-stats">

                <div class="bill-stat">
                    <h4>Total Revenue</h4>
                    <h2 id="totalRevenue">0 MMK</h2>
                </div>

                <div class="bill-stat">
                    <h4>Today's Revenue</h4>
                    <h2 id="todayRevenue">0 MMK</h2>
                </div>

                <div class="bill-stat">
                    <h4>Total Payments</h4>
                    <h2 id="totalPayments">0</h2>
                </div>

                <div class="bill-stat">
                    <h4>Paid Today</h4>
                    <h2 id="paidToday">0</h2>
                </div>

            </div>

            <div class="invoice-list payment-history-panel">

                <div class="section-title">

                    <h3>Paid Invoices</h3>

                    <input
                        type="text"
                        id="searchPayment"
                        placeholder="Search invoice, order or table..."
                        autocomplete="off"
                    >

                </div>

                <div class="payment-table-wrapper">

                    <table class="payment-history-table">

                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Order</th>
                                <th>Table</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="paymentHistoryBody">

                            <tr>
                                <td colspan="8">
                                    Loading payment history...
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>

</div>

<script src="../assets/js/payments.js"></script>

</body>

</html>