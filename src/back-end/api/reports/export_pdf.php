<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../vendor/autoload.php";
require_once __DIR__ . "/../../../../config/database.php";

use Dompdf\Dompdf;
use Dompdf\Options;

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["staff_id"])) {
    http_response_code(401);
    exit("Please log in again.");
}

$currentRole = strtoupper(
    (string) ($_SESSION["role"] ?? "")
);

if (!in_array(
    $currentRole,
    ["ADMIN", "MANAGER"],
    true
)) {

    http_response_code(403);
    exit("You do not have permission to export reports.");
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function escapeHtml(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function formatMoney(mixed $value): string
{
    return number_format(
        (float) $value,
        2
    ) . " MMK";
}

function resolveReportDates(): array
{
    $period = strtolower(
        trim((string) ($_GET["period"] ?? "today"))
    );

    $today = new DateTimeImmutable("today");

    switch ($period) {
        case "last7":
            $startDate = $today->modify("-6 days");
            $endDate = $today;
            break;

        case "month":
            $startDate = $today->modify(
                "first day of this month"
            );

            $endDate = $today;
            break;

        case "custom":
            $startInput = trim(
                (string) ($_GET["start_date"] ?? "")
            );

            $endInput = trim(
                (string) ($_GET["end_date"] ?? "")
            );

            $startDate =
                DateTimeImmutable::createFromFormat(
                    "!Y-m-d",
                    $startInput
                );

            $endDate =
                DateTimeImmutable::createFromFormat(
                    "!Y-m-d",
                    $endInput
                );

            if (!$startDate || !$endDate) {
                exit("Invalid custom report dates.");
            }

            if ($startDate > $endDate) {
                exit(
                    "Start date cannot be after end date."
                );
            }

            break;

        case "today":
        default:
            $period = "today";
            $startDate = $today;
            $endDate = $today;
            break;
    }

    return [
        "period" => $period,
        "start_date" => $startDate->format("Y-m-d"),
        "end_date" => $endDate->format("Y-m-d")
    ];
}

$range = resolveReportDates();

$startDate = $range["start_date"];
$endDate = $range["end_date"];

try {
    /*
    |--------------------------------------------------------------------------
    | Invoice summary
    |--------------------------------------------------------------------------
    */

    $summaryStatement = $pdo->prepare("
        SELECT
            COALESCE(SUM(i.final_total), 0)
                AS net_sales,

            COALESCE(SUM(i.tax_amount), 0)
                AS tax_collected,

            COALESCE(SUM(i.discount_amount), 0)
                AS discount_given,

            COALESCE(AVG(i.final_total), 0)
                AS average_order_value,

            COUNT(i.invoice_id)
                AS paid_invoice_count

        FROM invoices i

        INNER JOIN payments p
            ON p.payment_id = i.payment_id

        WHERE
            DATE(i.invoice_date)
                BETWEEN :start_date AND :end_date

            AND UPPER(p.payment_status) = 'PAID'
    ");

    $summaryStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $invoiceSummary =
        $summaryStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Order summary
    |--------------------------------------------------------------------------
    */

    $orderStatement = $pdo->prepare("
        SELECT
            COUNT(*) AS total_orders,

            SUM(
                CASE
                    WHEN UPPER(status) = 'COMPLETED'
                    THEN 1
                    ELSE 0
                END
            ) AS completed_orders,

            SUM(
                CASE
                    WHEN UPPER(status) = 'CANCELLED'
                    THEN 1
                    ELSE 0
                END
            ) AS cancelled_orders

        FROM orders

        WHERE
            DATE(order_date)
                BETWEEN :start_date AND :end_date
    ");

    $orderStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $orderSummary =
        $orderStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Best seller
    |--------------------------------------------------------------------------
    */

    $bestSellerStatement = $pdo->prepare("
        SELECT
            m.name,
            SUM(od.quantity) AS quantity

        FROM order_details od

        INNER JOIN menu_items m
            ON m.item_id = od.item_id

        INNER JOIN invoices i
            ON i.order_id = od.order_id

        INNER JOIN payments p
            ON p.payment_id = i.payment_id

        WHERE
            DATE(i.invoice_date)
                BETWEEN :start_date AND :end_date

            AND UPPER(p.payment_status) = 'PAID'

        GROUP BY
            m.item_id,
            m.name

        ORDER BY
            quantity DESC,
            m.name ASC

        LIMIT 1
    ");

    $bestSellerStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $bestSeller =
        $bestSellerStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Payment summary
    |--------------------------------------------------------------------------
    */

    $paymentStatement = $pdo->prepare("
        SELECT
            p.payment_method,
            COUNT(p.payment_id) AS transactions,
            COALESCE(SUM(p.amount_paid), 0)
                AS amount

        FROM payments p

        WHERE
            DATE(p.payment_date)
                BETWEEN :start_date AND :end_date

            AND UPPER(p.payment_status) = 'PAID'

        GROUP BY p.payment_method

        ORDER BY amount DESC
    ");

    $paymentStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $payments =
        $paymentStatement->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Top-selling menu
    |--------------------------------------------------------------------------
    */

    $topMenuStatement = $pdo->prepare("
        SELECT
            m.name,
            m.category,
            SUM(od.quantity) AS quantity,
            SUM(od.subtotal) AS revenue

        FROM order_details od

        INNER JOIN menu_items m
            ON m.item_id = od.item_id

        INNER JOIN invoices i
            ON i.order_id = od.order_id

        INNER JOIN payments p
            ON p.payment_id = i.payment_id

        WHERE
            DATE(i.invoice_date)
                BETWEEN :start_date AND :end_date

            AND UPPER(p.payment_status) = 'PAID'

        GROUP BY
            m.item_id,
            m.name,
            m.category

        ORDER BY
            quantity DESC,
            revenue DESC

        LIMIT 10
    ");

    $topMenuStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $topMenu =
        $topMenuStatement->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Detailed sales
    |--------------------------------------------------------------------------
    */

    $detailStatement = $pdo->prepare("
        SELECT
            i.invoice_id,
            i.order_id,
            i.invoice_date,
            i.subtotal,
            i.tax_amount,
            i.discount_amount,
            i.final_total,

            COALESCE(t.table_number, '-')
                AS table_number,

            p.payment_method,
            p.payment_status,

            GROUP_CONCAT(
                CONCAT(
                    m.name,
                    ' x',
                    od.quantity
                )
                ORDER BY od.order_detail_id ASC
                SEPARATOR ', '
            ) AS items

        FROM invoices i

        INNER JOIN payments p
            ON p.payment_id = i.payment_id

        INNER JOIN orders o
            ON o.order_id = i.order_id

        LEFT JOIN rms_tables t
            ON t.table_id = o.table_id

        INNER JOIN order_details od
            ON od.order_id = i.order_id

        INNER JOIN menu_items m
            ON m.item_id = od.item_id

        WHERE
            DATE(i.invoice_date)
                BETWEEN :start_date AND :end_date

            AND UPPER(p.payment_status) = 'PAID'

        GROUP BY
            i.invoice_id,
            i.order_id,
            i.invoice_date,
            i.subtotal,
            i.tax_amount,
            i.discount_amount,
            i.final_total,
            t.table_number,
            p.payment_method,
            p.payment_status

        ORDER BY i.invoice_date DESC
    ");

    $detailStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $transactions =
        $detailStatement->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | HTML
    |--------------------------------------------------------------------------
    */

    $generatedBy = escapeHtml(
        $_SESSION["name"]
        ?? $_SESSION["username"]
        ?? "Admin"
    );

    $generatedAt = date("d M Y, h:i A");

    $netSales = formatMoney(
        $invoiceSummary["net_sales"] ?? 0
    );

    $averageOrder = formatMoney(
        $invoiceSummary["average_order_value"] ?? 0
    );

    $taxCollected = formatMoney(
        $invoiceSummary["tax_collected"] ?? 0
    );

    $discountGiven = formatMoney(
        $invoiceSummary["discount_given"] ?? 0
    );

    $totalOrders =
        (int) ($orderSummary["total_orders"] ?? 0);

    $completedOrders =
        (int) ($orderSummary["completed_orders"] ?? 0);

    $cancelledOrders =
        (int) ($orderSummary["cancelled_orders"] ?? 0);

    $bestSellerName =
        escapeHtml($bestSeller["name"] ?? "-");

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">

        <style>
            @page {
                margin: 28px;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                color: #172033;
                font-size: 10px;
                line-height: 1.45;
            }

            .header {
                border-bottom: 3px solid #6648ef;
                padding-bottom: 14px;
                margin-bottom: 18px;
            }

            .header-table {
                width: 100%;
                border-collapse: collapse;
            }

            .header-table td {
                border: none;
                padding: 0;
            }

            .title {
                margin: 0;
                font-size: 24px;
                color: #111827;
            }

            .subtitle {
                margin: 4px 0 0;
                color: #64748b;
                font-size: 11px;
            }

            .meta {
                text-align: right;
                color: #475569;
                font-size: 9px;
            }

            .section-title {
                margin: 20px 0 9px;
                padding-bottom: 5px;
                border-bottom: 1px solid #dfe5ee;
                color: #111827;
                font-size: 14px;
            }

            .summary-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 7px;
                margin-left: -7px;
            }

            .summary-table td {
                width: 25%;
                padding: 11px;
                border: 1px solid #e4e8f0;
                border-radius: 7px;
                background: #f8fafc;
                vertical-align: top;
            }

            .summary-label {
                color: #64748b;
                font-size: 8px;
                font-weight: bold;
                text-transform: uppercase;
            }

            .summary-value {
                margin-top: 4px;
                color: #111827;
                font-size: 13px;
                font-weight: bold;
            }

            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 7px;
            }

            .data-table th {
                padding: 7px 6px;
                border: 1px solid #d8dee9;
                background: #6648ef;
                color: #ffffff;
                font-size: 8px;
                text-align: left;
            }

            .data-table td {
                padding: 6px;
                border: 1px solid #dfe5ee;
                font-size: 8px;
                vertical-align: top;
            }

            .data-table tr:nth-child(even) td {
                background: #f8fafc;
            }

            .money {
                text-align: right;
                white-space: nowrap;
            }

            .center {
                text-align: center;
            }

            .items-column {
                width: 150px;
            }

            .empty {
                padding: 18px;
                color: #64748b;
                text-align: center;
            }

            .footer {
                margin-top: 22px;
                padding-top: 9px;
                border-top: 1px solid #dfe5ee;
                color: #64748b;
                font-size: 8px;
                text-align: center;
            }
        </style>
    </head>

    <body>

        <div class="header">

            <table class="header-table">
                <tr>
                    <td>
                        <h1 class="title">
                            Restaurant Management System
                        </h1>

                        <p class="subtitle">
                            Detailed Sales and Performance Report
                        </p>
                    </td>

                    <td class="meta">
                        <strong>Report Period:</strong><br>
                        ' . escapeHtml($startDate) . '
                        to
                        ' . escapeHtml($endDate) . '
                        <br><br>

                        <strong>Generated:</strong><br>
                        ' . escapeHtml($generatedAt) . '
                        <br>

                        <strong>Prepared By:</strong>
                        ' . $generatedBy . '
                    </td>
                </tr>
            </table>

        </div>

        <h2 class="section-title">
            Report Summary
        </h2>

        <table class="summary-table">
            <tr>
                <td>
                    <div class="summary-label">Net Sales</div>
                    <div class="summary-value">' . $netSales . '</div>
                </td>

                <td>
                    <div class="summary-label">Total Orders</div>
                    <div class="summary-value">' . $totalOrders . '</div>
                </td>

                <td>
                    <div class="summary-label">Completed Orders</div>
                    <div class="summary-value">' . $completedOrders . '</div>
                </td>

                <td>
                    <div class="summary-label">Cancelled Orders</div>
                    <div class="summary-value">' . $cancelledOrders . '</div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="summary-label">Average Order</div>
                    <div class="summary-value">' . $averageOrder . '</div>
                </td>

                <td>
                    <div class="summary-label">Tax Collected</div>
                    <div class="summary-value">' . $taxCollected . '</div>
                </td>

                <td>
                    <div class="summary-label">Discount Given</div>
                    <div class="summary-value">' . $discountGiven . '</div>
                </td>

                <td>
                    <div class="summary-label">Best Seller</div>
                    <div class="summary-value">' . $bestSellerName . '</div>
                </td>
            </tr>
        </table>

        <h2 class="section-title">
            Payment Summary
        </h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th>Transactions</th>
                    <th>Paid Amount</th>
                </tr>
            </thead>

            <tbody>';

    if ($payments === []) {
        $html .= '
            <tr>
                <td colspan="3" class="empty">
                    No paid transactions found.
                </td>
            </tr>';
    } else {
        foreach ($payments as $payment) {
            $html .= '
            <tr>
                <td>'
                    . escapeHtml($payment["payment_method"])
                    . '
                </td>

                <td class="center">'
                    . (int) $payment["transactions"]
                    . '
                </td>

                <td class="money">'
                    . formatMoney($payment["amount"])
                    . '
                </td>
            </tr>';
        }
    }

    $html .= '
            </tbody>
        </table>

        <h2 class="section-title">
            Top Selling Menu
        </h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Menu Item</th>
                    <th>Category</th>
                    <th>Quantity Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>

            <tbody>';

    if ($topMenu === []) {
        $html .= '
            <tr>
                <td colspan="5" class="empty">
                    No menu sales found.
                </td>
            </tr>';
    } else {
        foreach ($topMenu as $index => $item) {
            $html .= '
            <tr>
                <td class="center">'
                    . ($index + 1)
                    . '
                </td>

                <td>'
                    . escapeHtml($item["name"])
                    . '
                </td>

                <td>'
                    . escapeHtml($item["category"] ?? "-")
                    . '
                </td>

                <td class="center">'
                    . (int) $item["quantity"]
                    . '
                </td>

                <td class="money">'
                    . formatMoney($item["revenue"])
                    . '
                </td>
            </tr>';
        }
    }

    $html .= '
            </tbody>
        </table>

        <h2 class="section-title">
            Detailed Sales Transactions
        </h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice</th>
                    <th>Order</th>
                    <th>Table</th>
                    <th class="items-column">Items</th>
                    <th>Payment</th>
                    <th>Subtotal</th>
                    <th>Tax</th>
                    <th>Discount</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>';

    if ($transactions === []) {
        $html .= '
            <tr>
                <td colspan="10" class="empty">
                    No completed sales found.
                </td>
            </tr>';
    } else {
        foreach ($transactions as $transaction) {
            $invoiceNumber =
                "INV-" . str_pad(
                    (string) $transaction["invoice_id"],
                    5,
                    "0",
                    STR_PAD_LEFT
                );

            $orderNumber =
                "ORD-" . str_pad(
                    (string) $transaction["order_id"],
                    5,
                    "0",
                    STR_PAD_LEFT
                );

            $formattedDate = date(
                "d M Y H:i",
                strtotime(
                    (string) $transaction["invoice_date"]
                )
            );

            $html .= '
            <tr>
                <td>'
                    . escapeHtml($formattedDate)
                    . '
                </td>

                <td>'
                    . escapeHtml($invoiceNumber)
                    . '
                </td>

                <td>'
                    . escapeHtml($orderNumber)
                    . '
                </td>

                <td class="center">'
                    . escapeHtml($transaction["table_number"])
                    . '
                </td>

                <td>'
                    . escapeHtml($transaction["items"] ?? "-")
                    . '
                </td>

                <td>'
                    . escapeHtml($transaction["payment_method"])
                    . '
                </td>

                <td class="money">'
                    . formatMoney($transaction["subtotal"])
                    . '
                </td>

                <td class="money">'
                    . formatMoney($transaction["tax_amount"])
                    . '
                </td>

                <td class="money">'
                    . formatMoney($transaction["discount_amount"])
                    . '
                </td>

                <td class="money">'
                    . formatMoney($transaction["final_total"])
                    . '
                </td>
            </tr>';
        }
    }

    $html .= '
            </tbody>
        </table>

        <div class="footer">
            RMS Restaurant Management System —
            End of Report
        </div>

    </body>
    </html>';

    /*
    |--------------------------------------------------------------------------
    | Generate PDF
    |--------------------------------------------------------------------------
    */

    $options = new Options();

    $options->set(
        "defaultFont",
        "DejaVu Sans"
    );

    $options->set(
        "isRemoteEnabled",
        true
    );

    $dompdf = new Dompdf($options);

    $dompdf->loadHtml(
        $html,
        "UTF-8"
    );

    $dompdf->setPaper(
        "A4",
        "landscape"
    );

    $dompdf->render();

    $filename =
        "restaurant_report_"
        . $startDate
        . "_to_"
        . $endDate
        . ".pdf";

    $dompdf->stream(
        $filename,
        [
            "Attachment" => true
        ]
    );

    exit;

} catch (Throwable $exception) {
    error_log(
        "PDF export error: "
        . $exception->getMessage()
    );

    http_response_code(500);

    exit(
        "Unable to generate the PDF report: "
        . escapeHtml($exception->getMessage())
    );
}