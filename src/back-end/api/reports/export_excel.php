<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../vendor/autoload.php";
require_once __DIR__ . "/../../../../config/database.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;


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
| Date range
|--------------------------------------------------------------------------
*/

function resolveExcelReportDates(): array
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
            $startDate =
                DateTimeImmutable::createFromFormat(
                    "!Y-m-d",
                    trim(
                        (string) (
                            $_GET["start_date"] ?? ""
                        )
                    )
                );

            $endDate =
                DateTimeImmutable::createFromFormat(
                    "!Y-m-d",
                    trim(
                        (string) (
                            $_GET["end_date"] ?? ""
                        )
                    )
                );

            if (!$startDate || !$endDate) {
                exit("Invalid custom report dates.");
            }

            if ($startDate > $endDate) {
                exit("Start date cannot be after end date.");
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

function applyHeaderStyle(
    Worksheet $sheet,
    string $range
): void {
    $sheet->getStyle($range)->applyFromArray([
        "font" => [
            "bold" => true,
            "color" => [
                "rgb" => "FFFFFF"
            ]
        ],

        "fill" => [
            "fillType" =>
            Fill::FILL_SOLID,

            "startColor" => [
                "rgb" => "6548EF"
            ]
        ],

        "alignment" => [
            "horizontal" =>
            Alignment::HORIZONTAL_CENTER,

            "vertical" =>
            Alignment::VERTICAL_CENTER
        ],

        "borders" => [
            "allBorders" => [
                "borderStyle" =>
                Border::BORDER_THIN,

                "color" => [
                    "rgb" => "D9DFE9"
                ]
            ]
        ]
    ]);
}

function applyTableBorders(
    Worksheet $sheet,
    string $range
): void {
    $sheet->getStyle($range)->applyFromArray([
        "borders" => [
            "allBorders" => [
                "borderStyle" =>
                Border::BORDER_THIN,

                "color" => [
                    "rgb" => "D9DFE9"
                ]
            ]
        ],

        "alignment" => [
            "vertical" =>
            Alignment::VERTICAL_TOP
        ]
    ]);
}

$range = resolveExcelReportDates();

$startDate = $range["start_date"];
$endDate = $range["end_date"];

try {
    /*
    |--------------------------------------------------------------------------
    | Summary
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
                AS average_order_value

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
    | Transactions
    |--------------------------------------------------------------------------
    */

    $transactionStatement = $pdo->prepare("
        SELECT
            i.invoice_id,
            i.order_id,
            i.invoice_date,
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
            ) AS items,

            i.subtotal,
            i.tax_amount,
            i.discount_amount,
            i.final_total

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
            t.table_number,
            p.payment_method,
            p.payment_status,
            i.subtotal,
            i.tax_amount,
            i.discount_amount,
            i.final_total

        ORDER BY i.invoice_date DESC
    ");

    $transactionStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $transactions =
        $transactionStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | Top menu
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
    ");

    $topMenuStatement->execute([
        "start_date" => $startDate,
        "end_date" => $endDate
    ]);

    $topMenu =
        $topMenuStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

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
        $paymentStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | Workbook
    |--------------------------------------------------------------------------
    */

    $spreadsheet = new Spreadsheet();

    $spreadsheet->getProperties()
        ->setCreator("RMS Restaurant Management")
        ->setTitle("Restaurant Sales Report")
        ->setSubject(
            "Sales report from "
                . $startDate
                . " to "
                . $endDate
        );

    /*
    |--------------------------------------------------------------------------
    | Sheet 1: Summary
    |--------------------------------------------------------------------------
    */

    $summarySheet =
        $spreadsheet->getActiveSheet();

    $summarySheet->setTitle("Summary");

    $summarySheet->mergeCells("A1:D1");

    $summarySheet->setCellValue(
        "A1",
        "Restaurant Management System"
    );

    $summarySheet->mergeCells("A2:D2");

    $summarySheet->setCellValue(
        "A2",
        "Sales Report: "
            . $startDate
            . " to "
            . $endDate
    );

    $summarySheet->setCellValue(
        "A4",
        "Metric"
    );

    $summarySheet->setCellValue(
        "B4",
        "Value"
    );

    applyHeaderStyle(
        $summarySheet,
        "A4:B4"
    );

    $summaryRows = [
        [
            "Net Sales",
            (float) (
                $invoiceSummary["net_sales"] ?? 0
            )
        ],
        [
            "Total Orders",
            (int) (
                $orderSummary["total_orders"] ?? 0
            )
        ],
        [
            "Completed Orders",
            (int) (
                $orderSummary["completed_orders"] ?? 0
            )
        ],
        [
            "Cancelled Orders",
            (int) (
                $orderSummary["cancelled_orders"] ?? 0
            )
        ],
        [
            "Average Order Value",
            (float) (
                $invoiceSummary["average_order_value"] ?? 0
            )
        ],
        [
            "Tax Collected",
            (float) (
                $invoiceSummary["tax_collected"] ?? 0
            )
        ],
        [
            "Discount Given",
            (float) (
                $invoiceSummary["discount_given"] ?? 0
            )
        ],
        [
            "Best Seller",
            $bestSeller["name"] ?? "-"
        ]
    ];

    $summaryRow = 5;

    foreach ($summaryRows as $row) {
        $summarySheet->setCellValue(
            "A" . $summaryRow,
            $row[0]
        );

        $summarySheet->setCellValue(
            "B" . $summaryRow,
            $row[1]
        );

        $summaryRow++;
    }

    $summarySheet->getStyle("B5:B11")
        ->getNumberFormat()
        ->setFormatCode(
            '#,##0.00 "MMK"'
        );

    $summarySheet->getColumnDimension("A")
        ->setWidth(28);

    $summarySheet->getColumnDimension("B")
        ->setWidth(24);

    applyTableBorders(
        $summarySheet,
        "A4:B12"
    );

    $summarySheet->getStyle("A1:D1")
        ->getFont()
        ->setBold(true)
        ->setSize(18);

    $summarySheet->getStyle("A2:D2")
        ->getFont()
        ->setItalic(true);

    /*
    |--------------------------------------------------------------------------
    | Sheet 2: Sales Transactions
    |--------------------------------------------------------------------------
    */

    $transactionSheet =
        $spreadsheet->createSheet();

    $transactionSheet->setTitle(
        "Sales Transactions"
    );

    $transactionHeaders = [
        "Date",
        "Invoice",
        "Order",
        "Table",
        "Items",
        "Payment Method",
        "Payment Status",
        "Subtotal",
        "Tax",
        "Discount",
        "Final Total"
    ];

    $transactionSheet->fromArray(
        $transactionHeaders,
        null,
        "A1"
    );

    applyHeaderStyle(
        $transactionSheet,
        "A1:K1"
    );

    $transactionRow = 2;

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

        $transactionSheet->fromArray(
            [
                $transaction["invoice_date"],
                $invoiceNumber,
                $orderNumber,
                $transaction["table_number"],
                $transaction["items"],
                $transaction["payment_method"],
                $transaction["payment_status"],
                (float) $transaction["subtotal"],
                (float) $transaction["tax_amount"],
                (float) $transaction["discount_amount"],
                (float) $transaction["final_total"]
            ],
            null,
            "A" . $transactionRow
        );

        $transactionRow++;
    }

    if ($transactionRow > 2) {
        $transactionSheet
            ->getStyle(
                "H2:K" . ($transactionRow - 1)
            )
            ->getNumberFormat()
            ->setFormatCode(
                '#,##0.00 "MMK"'
            );

        applyTableBorders(
            $transactionSheet,
            "A1:K" . ($transactionRow - 1)
        );
    }

    $transactionSheet->freezePane("A2");
    $transactionSheet->setAutoFilter("A1:K1");

    $transactionWidths = [
        "A" => 20,
        "B" => 15,
        "C" => 15,
        "D" => 11,
        "E" => 42,
        "F" => 19,
        "G" => 17,
        "H" => 16,
        "I" => 14,
        "J" => 16,
        "K" => 18
    ];

    foreach (
        $transactionWidths as $column => $width
    ) {
        $transactionSheet
            ->getColumnDimension($column)
            ->setWidth($width);
    }

    $transactionSheet->getStyle(
        "E2:E" . max(2, $transactionRow - 1)
    )->getAlignment()->setWrapText(true);

    /*
    |--------------------------------------------------------------------------
    | Sheet 3: Top Selling Menu
    |--------------------------------------------------------------------------
    */

    $menuSheet =
        $spreadsheet->createSheet();

    $menuSheet->setTitle("Top Selling Menu");

    $menuSheet->fromArray(
        [
            "Rank",
            "Menu Item",
            "Category",
            "Quantity Sold",
            "Revenue"
        ],
        null,
        "A1"
    );

    applyHeaderStyle(
        $menuSheet,
        "A1:E1"
    );

    $menuRow = 2;

    foreach ($topMenu as $index => $item) {
        $menuSheet->fromArray(
            [
                $index + 1,
                $item["name"],
                $item["category"] ?? "-",
                (int) $item["quantity"],
                (float) $item["revenue"]
            ],
            null,
            "A" . $menuRow
        );

        $menuRow++;
    }

    if ($menuRow > 2) {
        $menuSheet
            ->getStyle(
                "E2:E" . ($menuRow - 1)
            )
            ->getNumberFormat()
            ->setFormatCode(
                '#,##0.00 "MMK"'
            );

        applyTableBorders(
            $menuSheet,
            "A1:E" . ($menuRow - 1)
        );
    }

    $menuSheet->freezePane("A2");
    $menuSheet->setAutoFilter("A1:E1");

    $menuSheet->getColumnDimension("A")
        ->setWidth(10);

    $menuSheet->getColumnDimension("B")
        ->setWidth(25);

    $menuSheet->getColumnDimension("C")
        ->setWidth(20);

    $menuSheet->getColumnDimension("D")
        ->setWidth(18);

    $menuSheet->getColumnDimension("E")
        ->setWidth(20);

    /*
    |--------------------------------------------------------------------------
    | Sheet 4: Payment Summary
    |--------------------------------------------------------------------------
    */

    $paymentSheet =
        $spreadsheet->createSheet();

    $paymentSheet->setTitle("Payment Summary");

    $paymentSheet->fromArray(
        [
            "Payment Method",
            "Transactions",
            "Paid Amount"
        ],
        null,
        "A1"
    );

    applyHeaderStyle(
        $paymentSheet,
        "A1:C1"
    );

    $paymentRow = 2;
    $totalPaymentAmount = 0.0;
    $totalPaymentTransactions = 0;

    foreach ($payments as $payment) {
        $amount =
            (float) $payment["amount"];

        $transactionsCount =
            (int) $payment["transactions"];

        $paymentSheet->fromArray(
            [
                $payment["payment_method"],
                $transactionsCount,
                $amount
            ],
            null,
            "A" . $paymentRow
        );

        $totalPaymentAmount += $amount;
        $totalPaymentTransactions +=
            $transactionsCount;

        $paymentRow++;
    }

    $paymentSheet->setCellValue(
        "A" . $paymentRow,
        "TOTAL"
    );

    $paymentSheet->setCellValue(
        "B" . $paymentRow,
        $totalPaymentTransactions
    );

    $paymentSheet->setCellValue(
        "C" . $paymentRow,
        $totalPaymentAmount
    );

    $paymentSheet->getStyle(
        "A" . $paymentRow
            . ":C" . $paymentRow
    )->getFont()->setBold(true);

    $paymentSheet->getStyle(
        "C2:C" . $paymentRow
    )->getNumberFormat()->setFormatCode(
        '#,##0.00 "MMK"'
    );

    applyTableBorders(
        $paymentSheet,
        "A1:C" . $paymentRow
    );

    $paymentSheet->getColumnDimension("A")
        ->setWidth(24);

    $paymentSheet->getColumnDimension("B")
        ->setWidth(18);

    $paymentSheet->getColumnDimension("C")
        ->setWidth(22);

    /*
    |--------------------------------------------------------------------------
    | Download
    |--------------------------------------------------------------------------
    */

    $spreadsheet->setActiveSheetIndex(0);

    $filename =
        "restaurant_report_"
        . $startDate
        . "_to_"
        . $endDate
        . ".xlsx";

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header(
        "Content-Type: "
            . "application/vnd.openxmlformats-officedocument."
            . "spreadsheetml.sheet"
    );

    header(
        'Content-Disposition: attachment; filename="'
            . $filename
            . '"'
    );

    header("Cache-Control: max-age=0");
    header("Pragma: public");

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx(
        $spreadsheet
    );

    $writer->save("php://output");

    $spreadsheet->disconnectWorksheets();

    exit;
} catch (Throwable $exception) {
    error_log(
        "Excel export error: "
            . $exception->getMessage()
    );

    http_response_code(500);

    exit("Unable to generate the Excel report: "
        . htmlspecialchars(
            $exception->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        ));
}
