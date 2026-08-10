<?php

require_once "../../back-end/middleware/auth.php";

allowRoles(["CASHIER", "ADMIN", "MANAGER"]);

$invoiceId = filter_input(
    INPUT_GET,
    "invoice_id",
    FILTER_VALIDATE_INT
);

if (!$invoiceId) {
    header("Location: payments.php");
    exit;
}

$pageTitle = "Invoice";
$roleName = $_SESSION["role"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Invoice</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css">

    <link
        rel="stylesheet"
        href="../assets/css/invoice.css">
</head>

<body>

    <div class="wrapper">

        <?php include "../components/sidebar.php"; ?>

        <main class="main">

            <?php include "../components/topbar.php"; ?>

            <section class="content invoice-page">

                <div class="invoice-page-header">

                    <div>
                        <h2>Invoice Detail</h2>

                        <p>
                            View and print completed payment receipt.
                        </p>
                    </div>

                    <div class="invoice-header-actions">

                        <a
                            href="payments.php"
                            class="back-button">
                            Back
                        </a>

                        <button
                            type="button"
                            id="printInvoiceButton"
                            class="print-invoice-button"
                            disabled>
                            🖨 Print Invoice
                        </button>

                    </div>

                </div>

                <div
                    id="invoiceMessage"
                    class="invoice-message">
                    Loading invoice...
                </div>

                <article
                    id="invoiceReceipt"
                    class="invoice-receipt"
                    style="display: none;">

                    <div class="receipt-brand">

                        <div class="receipt-logo">

                            <img
                                src="../assets/images/logo.png"
                                alt="RMS Logo">

                        </div>

                        <div class="receipt-brand-text">
                            <h1>Restaurant Management System</h1>
                            <p>Customer Payment Receipt</p>
                        </div>

                        <div class="receipt-title">
                        <h2>INVOICE</h2>
                        <strong id="receiptInvoiceNumber">
                            INV-00000
                        </strong>
                        </div>

                    </div>

                    <div class="receipt-information">

                        <div>
                            <span>Order</span>
                            <strong id="receiptOrderNumber">-</strong>
                        </div>

                        <div>
                            <span>Table</span>
                            <strong id="receiptTable">-</strong>
                        </div>

                        <div>
                            <span>Waiter</span>
                            <strong id="receiptWaiter">-</strong>
                        </div>

                        <div>
                            <span>Date</span>
                            <strong id="receiptDate">-</strong>
                        </div>

                        <div>
                            <span>Payment Method</span>
                            <strong id="receiptPaymentMethod">-</strong>
                        </div>

                        <div>
                            <span>Payment Status</span>
                            <strong
                                id="receiptPaymentStatus"
                                class="paid-status">
                                PAID
                            </strong>
                        </div>

                    </div>

                    <div class="receipt-table-wrapper">

                        <table class="receipt-table">

                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody id="receiptItems"></tbody>

                        </table>

                    </div>

                    <div class="receipt-summary">

                        <div>
                            <span>Subtotal</span>
                            <strong id="receiptSubtotal">
                                0 MMK
                            </strong>
                        </div>

                        <div>
                            <span>Tax</span>
                            <strong id="receiptTax">
                                0 MMK
                            </strong>
                        </div>

                        <div>
                            <span>Discount</span>
                            <strong id="receiptDiscount">
                                0 MMK
                            </strong>
                        </div>

                        <div class="receipt-total">
                            <span>Total Paid</span>
                            <strong id="receiptTotal">
                                0 MMK
                            </strong>
                        </div>

                    </div>

                    <div class="receipt-footer">

                        <p>Thank you for dining with us.</p>

                        <small>
                            This invoice was generated electronically.
                        </small>

                    </div>

                </article>

            </section>

        </main>

    </div>

    <script>
        const INVOICE_ID = <?= (int) $invoiceId; ?>;
    </script>

    <script>
        'use strict';

        const invoiceMessage = document.getElementById(
            'invoiceMessage'
        );

        const invoiceReceipt = document.getElementById(
            'invoiceReceipt'
        );

        const printInvoiceButton = document.getElementById(
            'printInvoiceButton'
        );

        const receiptInvoiceNumber = document.getElementById(
            'receiptInvoiceNumber'
        );

        const receiptOrderNumber = document.getElementById(
            'receiptOrderNumber'
        );

        const receiptTable = document.getElementById(
            'receiptTable'
        );

        const receiptWaiter = document.getElementById(
            'receiptWaiter'
        );

        const receiptDate = document.getElementById(
            'receiptDate'
        );

        const receiptPaymentMethod = document.getElementById(
            'receiptPaymentMethod'
        );

        const receiptPaymentStatus = document.getElementById(
            'receiptPaymentStatus'
        );

        const receiptItems = document.getElementById(
            'receiptItems'
        );

        const receiptSubtotal = document.getElementById(
            'receiptSubtotal'
        );

        const receiptTax = document.getElementById(
            'receiptTax'
        );

        const receiptDiscount = document.getElementById(
            'receiptDiscount'
        );

        const receiptTotal = document.getElementById(
            'receiptTotal'
        );

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value ?? '');
            return div.innerHTML;
        }

        function formatMoney(value) {
            return Number(value || 0).toLocaleString('en-US') +
                ' MMK';
        }

        function formatInvoiceNumber(id) {
            return `INV-${String(id).padStart(5, '0')}`;
        }

        function formatOrderNumber(id) {
            return `ORD-${String(id).padStart(5, '0')}`;
        }

        function formatDate(value) {
            const date = new Date(
                String(value).replace(' ', 'T')
            );

            if (Number.isNaN(date.getTime())) {
                return '';
            }

            return date.toLocaleString();
        }

        function formatPaymentMethod(value) {
            return String(value || '')
                .replaceAll('_', ' ')
                .toLowerCase()
                .replace(/\b\w/g, letter => letter.toUpperCase());
        }

        async function loadInvoice() {
            try {
                const response = await fetch(
                    `../../back-end/api/billing/get_invoice.php?invoice_id=${INVOICE_ID}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const responseText = await response.text();

                let result;

                try {
                    result = JSON.parse(responseText);
                } catch {
                    console.error(
                        'Raw invoice response:',
                        responseText
                    );

                    throw new Error(
                        'Server returned an invalid response.'
                    );
                }

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message || 'Unable to load invoice.'
                    );
                }

                const invoice = result.data;

                receiptInvoiceNumber.textContent =
                    formatInvoiceNumber(invoice.invoice_id);

                receiptOrderNumber.textContent =
                    formatOrderNumber(invoice.order_id);

                receiptTable.textContent =
                    `Table ${Number(invoice.table_number)}`;

                receiptWaiter.textContent =
                    invoice.waiter_name || 'Unknown';

                receiptDate.textContent =
                    formatDate(invoice.payment_date || invoice.invoice_date);

                receiptPaymentMethod.textContent =
                    formatPaymentMethod(invoice.payment_method);

                receiptPaymentStatus.textContent =
                    invoice.payment_status;

                receiptItems.innerHTML = '';

                const items = Array.isArray(invoice.items) ?
                    invoice.items : [];

                items.forEach(item => {
                    receiptItems.insertAdjacentHTML(
                        'beforeend',
                        `
                    <tr>
                        <td>${escapeHtml(item.name)}</td>
                        <td>${Number(item.quantity)}</td>
                        <td>${formatMoney(item.price)}</td>
                        <td>${formatMoney(item.subtotal)}</td>
                    </tr>
                `
                    );
                });

                receiptSubtotal.textContent =
                    formatMoney(invoice.subtotal);

                receiptTax.textContent =
                    formatMoney(invoice.tax_amount);

                receiptDiscount.textContent =
                    formatMoney(invoice.discount_amount);

                receiptTotal.textContent =
                    formatMoney(invoice.final_total);

                invoiceMessage.style.display = 'none';
                invoiceReceipt.style.display = 'block';
                printInvoiceButton.disabled = false;
            } catch (error) {
                console.error(error);

                invoiceMessage.textContent =
                    error.message || 'Unable to load invoice.';

                invoiceMessage.classList.add('error');
            }
        }

        printInvoiceButton.addEventListener(
            'click',
            () => window.print()
        );

        loadInvoice();
    </script>

</body>

</html>