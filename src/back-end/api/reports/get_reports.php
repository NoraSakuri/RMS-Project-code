<?php

declare(strict_types=1);

header(
    "Content-Type: application/json; charset=utf-8"
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__
    . "/../../../../config/database.php";

function respond(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (empty($_SESSION["staff_id"])) {
    respond(
        false,
        "Your session has expired. Please log in again.",
        [],
        401
    );
}

$role = strtoupper(
    (string) ($_SESSION["role"] ?? "")
);

if (!in_array(
    $role,
    ["ADMIN", "MANAGER"],
    true
)) {

    respond(
        false,
        "You do not have permission to view reports.",
        [],
        403
    );
}

/*
|--------------------------------------------------------------------------
| Date Range
|--------------------------------------------------------------------------
*/

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
        $startInput =
            trim((string) ($_GET["start_date"] ?? ""));

        $endInput =
            trim((string) ($_GET["end_date"] ?? ""));

        $startDate = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $startInput
        );

        $endDate = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $endInput
        );

        if (!$startDate || !$endDate) {
            respond(
                false,
                "Please provide valid start and end dates.",
                [],
                422
            );
        }

        if ($startDate > $endDate) {
            respond(
                false,
                "Start date cannot be after end date.",
                [],
                422
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

$startDateValue =
    $startDate->format("Y-m-d");

$endDateValue =
    $endDate->format("Y-m-d");

try {
    /*
    |--------------------------------------------------------------------------
    | Sales Summary
    |--------------------------------------------------------------------------
    */

    $salesStatement = $pdo->prepare("
        SELECT
            COALESCE(SUM(i.final_total), 0)
                AS net_sales,

            COALESCE(SUM(i.tax_amount), 0)
                AS tax_collected,

            COALESCE(SUM(i.discount_amount), 0)
                AS discount_given,

            COUNT(i.invoice_id)
                AS paid_orders,

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

    $salesStatement->execute([
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $salesSummary =
        $salesStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Order Summary
    |--------------------------------------------------------------------------
    */

    $orderSummaryStatement = $pdo->prepare("
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

    $orderSummaryStatement->execute([
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $orderSummary =
        $orderSummaryStatement->fetch(
            PDO::FETCH_ASSOC
        );

    /*
    |--------------------------------------------------------------------------
    | Best Seller
    |--------------------------------------------------------------------------
    */

    $bestSellerStatement = $pdo->prepare("
        SELECT
            m.name,
            SUM(od.quantity) AS sold_quantity

        FROM order_details od

        INNER JOIN menu_items m
            ON m.item_id = od.item_id

        INNER JOIN orders o
            ON o.order_id = od.order_id

        INNER JOIN invoices i
            ON i.order_id = o.order_id

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
            sold_quantity DESC,
            m.name ASC

        LIMIT 1
    ");

    $bestSellerStatement->execute([
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $bestSeller =
        $bestSellerStatement->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Top Menu Items
    |--------------------------------------------------------------------------
    */

    $topMenuStatement = $pdo->prepare("
        SELECT
            m.item_id,
            m.name,
            m.category,

            SUM(od.quantity)
                AS quantity,

            SUM(od.subtotal)
                AS revenue

        FROM order_details od

        INNER JOIN menu_items m
            ON m.item_id = od.item_id

        INNER JOIN orders o
            ON o.order_id = od.order_id

        INNER JOIN invoices i
            ON i.order_id = o.order_id

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
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $topItems =
        $topMenuStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    $netSales =
        (float) ($salesSummary["net_sales"] ?? 0);

    foreach ($topItems as &$item) {
        $item["quantity"] =
            (int) $item["quantity"];

        $item["revenue"] =
            (float) $item["revenue"];

        $item["sales_share"] =
            $netSales > 0
                ? round(
                    (
                        (float) $item["revenue"]
                        / $netSales
                    ) * 100,
                    2
                )
                : 0;
    }

    unset($item);

    /*
    |--------------------------------------------------------------------------
    | Payment Summary
    |--------------------------------------------------------------------------
    */

    $paymentStatement = $pdo->prepare("
        SELECT
            p.payment_method AS method,

            COUNT(p.payment_id)
                AS transactions,

            COALESCE(SUM(p.amount_paid), 0)
                AS amount

        FROM payments p

        WHERE
            DATE(p.payment_date)
                BETWEEN :start_date AND :end_date

            AND UPPER(p.payment_status) = 'PAID'

        GROUP BY
            p.payment_method

        ORDER BY
            amount DESC
    ");

    $paymentStatement->execute([
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $paymentSummary =
        $paymentStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach ($paymentSummary as &$payment) {
        $payment["transactions"] =
            (int) $payment["transactions"];

        $payment["amount"] =
            (float) $payment["amount"];
    }

    unset($payment);

    /*
    |--------------------------------------------------------------------------
    | Order Status
    |--------------------------------------------------------------------------
    */

    $orderStatusStatement = $pdo->prepare("
        SELECT
            UPPER(
                CASE
                    WHEN chef_action IS NOT NULL
                         AND UPPER(chef_action) <> 'PENDING'
                    THEN chef_action
                    ELSE status
                END
            ) AS status,

            COUNT(*) AS total

        FROM orders

        WHERE
            DATE(order_date)
                BETWEEN :start_date AND :end_date

        GROUP BY
            UPPER(
                CASE
                    WHEN chef_action IS NOT NULL
                         AND UPPER(chef_action) <> 'PENDING'
                    THEN chef_action
                    ELSE status
                END
            )

        ORDER BY status ASC
    ");

    $orderStatusStatement->execute([
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $orderStatus =
        $orderStatusStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach ($orderStatus as &$status) {
        $status["total"] =
            (int) $status["total"];
    }

    unset($status);

    /*
    |--------------------------------------------------------------------------
    | Sales Chart
    |--------------------------------------------------------------------------
    */

    $chartStatement = $pdo->prepare("
        SELECT
            DATE(i.invoice_date) AS day,

            COALESCE(SUM(i.final_total), 0)
                AS amount

        FROM invoices i

        INNER JOIN payments p
            ON p.payment_id = i.payment_id

        WHERE
            DATE(i.invoice_date)
                BETWEEN :start_date AND :end_date

            AND UPPER(p.payment_status) = 'PAID'

        GROUP BY
            DATE(i.invoice_date)

        ORDER BY
            day ASC
    ");

    $chartStatement->execute([
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $salesChart =
        $chartStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach ($salesChart as &$chartItem) {
        $chartItem["amount"] =
            (float) $chartItem["amount"];
    }

    unset($chartItem);

    /*
    |--------------------------------------------------------------------------
    | Detailed Transactions
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

            t.table_number,

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

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        INNER JOIN order_details od
            ON od.order_id = o.order_id

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

        ORDER BY
            i.invoice_date DESC

        LIMIT 200
    ");

    $detailStatement->execute([
        "start_date" => $startDateValue,
        "end_date" => $endDateValue
    ]);

    $salesDetails =
        $detailStatement->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach ($salesDetails as &$detail) {
        $detail["invoice_id"] =
            (int) $detail["invoice_id"];

        $detail["order_id"] =
            (int) $detail["order_id"];

        $detail["subtotal"] =
            (float) $detail["subtotal"];

        $detail["tax_amount"] =
            (float) $detail["tax_amount"];

        $detail["discount_amount"] =
            (float) $detail["discount_amount"];

        $detail["final_total"] =
            (float) $detail["final_total"];
    }

    unset($detail);

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        "Reports loaded successfully.",
        [
            "range" => [
                "period" => $period,
                "start_date" => $startDateValue,
                "end_date" => $endDateValue
            ],

            "summary" => [
                "net_sales" =>
                    (float) (
                        $salesSummary["net_sales"] ?? 0
                    ),

                "total_orders" =>
                    (int) (
                        $orderSummary["total_orders"] ?? 0
                    ),

                "completed_orders" =>
                    (int) (
                        $orderSummary["completed_orders"] ?? 0
                    ),

                "cancelled_orders" =>
                    (int) (
                        $orderSummary["cancelled_orders"] ?? 0
                    ),

                "average_order_value" =>
                    (float) (
                        $salesSummary[
                            "average_order_value"
                        ] ?? 0
                    ),

                "tax_collected" =>
                    (float) (
                        $salesSummary["tax_collected"] ?? 0
                    ),

                "discount_given" =>
                    (float) (
                        $salesSummary["discount_given"] ?? 0
                    ),

                "best_seller" =>
                    $bestSeller["name"] ?? "-"
            ],

            "sales_chart" => $salesChart,
            "payment_summary" => $paymentSummary,
            "order_status" => $orderStatus,
            "top_items" => $topItems,
            "sales_details" => $salesDetails
        ]
    );

} catch (Throwable $exception) {
    error_log(
        "Report API error: "
        . $exception->getMessage()
    );

    respond(
        false,
        "Unable to load report information.",
        [],
        500
    );
}