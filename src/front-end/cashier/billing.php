<?php

require_once "../../back-end/middleware/auth.php";

allowRoles(["CASHIER", "ADMIN", "MANAGER"]);

$pageTitle = "Billing & Payment";
$roleName = $_SESSION["role"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Billing & Payment</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css">

    <link
        rel="stylesheet"
        href="../assets/css/billing.css">
</head>

<body>

    <div class="wrapper">

        <?php include "../components/sidebar.php"; ?>

        <main class="main">

            <?php include "../components/topbar.php"; ?>

            <section class="content">

                <div class="billing-header">

                    <div>
                        <h2>Billing & Payment</h2>

                        <p>
                            Generate invoices and complete customer payments.
                        </p>
                    </div>

                    

                </div>

                <div class="billing-stats">

                    <div class="bill-stat">
                        <h4>Today's Revenue</h4>
                        <h2 id="todayRevenue">0 MMK</h2>
                    </div>

                    <div class="bill-stat">
                        <h4>Invoices</h4>
                        <h2 id="invoiceCount">0</h2>
                    </div>

                    <div class="bill-stat">
                        <h4>Pending Payment</h4>
                        <h2 id="pendingPaymentCount">0</h2>
                    </div>

                    <div class="bill-stat">
                        <h4>Paid Today</h4>
                        <h2 id="paidCount">0</h2>
                    </div>

                </div>

                <div class="billing-layout">

                    <div class="invoice-list">

                        <div class="section-title">

                            <h3>Ready Orders</h3>

                            <input
                                type="text"
                                id="searchInvoice"
                                placeholder="Search order or table..."
                                autocomplete="off">

                        </div>

                        <div id="readyOrderList">

                            <p class="empty-billing-list">
                                No orders ready for payment.
                            </p>

                        </div>

                    </div>

                    <div
                        class="invoice-preview"
                        id="invoicePreview">

                        <div
                            class="empty-invoice-preview"
                            id="emptyInvoicePreview">
                            <h3>Select an order</h3>

                            <p>
                                Select a ready order to view payment details.
                            </p>
                        </div>

                        <div
                            id="invoiceContent"
                            style="display: none;">

                            <div class="invoice-top">

                                <div>
                                    <h2>Invoice</h2>
                                    <p id="invoiceNumber">Not generated</p>
                                </div>

                                <div>
                                    <p id="invoiceTable">Table -</p>
                                    <p id="invoiceCashier">Cashier: -</p>
                                    <p id="invoiceDate">-</p>
                                </div>

                            </div>

                            <table>

                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>

                                <tbody id="invoiceItems"></tbody>

                            </table>

                            <div class="summary">

                                <div>
                                    <span>Subtotal</span>
                                    <strong id="invoiceSubtotal">
                                        0 MMK
                                    </strong>
                                </div>

                                <div class="discount-row">
                                    <span>Discount</span>

                                    <div class="discount-input-box">
                                        <input
                                            type="number"
                                            id="discountAmount"
                                            min="0"
                                            step="100"
                                            value="0"
                                            placeholder="0">

                                        <span>MMK</span>
                                    </div>
                                </div>

                                <div>
                                    <span>Tax (5%)</span>
                                    <strong id="invoiceTax">
                                        0 MMK
                                    </strong>
                                </div>

                                <hr>

                                <div class="grand-total">
                                    <span>Total</span>
                                    <strong id="invoiceTotal">
                                        0 MMK
                                    </strong>
                                </div>

                            </div>

                            <div class="payment-method">

                                <h3>Payment Method</h3>

                                <label>
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="CASH"
                                        checked>

                                    Cash
                                </label>

                                <label>
                                    <input
                                        type="radio"
                                        name="payment_method"
                                        value="CREDIT_CARD">

                                    Credit Card
                                </label>

                            </div>

                            <div class="actions">

                                <button
                                    type="button"
                                    class="cancel-btn"
                                    id="clearSelectionButton">
                                    Clear
                                </button>

                                <button
                                    type="button"
                                    class="pay-btn"
                                    id="completePaymentButton">
                                    Complete Payment
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </section>

        </main>

    </div>

    <script src="../assets/js/billing.js"></script>

</body>

</html>