<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../../../config/database.php";

function respond(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

if (empty($_SESSION['staff_id'])) {
    respond(
        false,
        'Your session has expired.',
        [],
        401
    );
}

$role = strtoupper(
    (string) ($_SESSION['role'] ?? '')
);

if (!in_array(
    $role,
    ['CASHIER', 'ADMIN', 'MANAGER'],
    true
)) {
    respond(
        false,
        'You do not have permission to view payments.',
        [],
        403
    );
}

try {
    $statement = $pdo->prepare("
        SELECT
            p.payment_id,
            p.order_id,
            p.payment_method,
            p.amount_paid,
            p.payment_status,
            p.payment_date,

            i.invoice_id,
            i.invoice_date,
            i.subtotal,
            i.tax_amount,
            i.discount_amount,
            i.final_total,

            o.table_id,
            o.status AS order_status,

            t.table_number

        FROM payments p

        INNER JOIN invoices i
            ON i.payment_id = p.payment_id

        INNER JOIN orders o
            ON o.order_id = p.order_id

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        WHERE p.payment_status = 'PAID'

        ORDER BY p.payment_date DESC
    ");

    $statement->execute();

    $payments = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $statisticsStatement = $pdo->query("
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN payment_status = 'PAID'
                    THEN amount_paid
                    ELSE 0
                END
            ), 0) AS total_revenue,

            COALESCE(SUM(
                CASE
                    WHEN payment_status = 'PAID'
                     AND DATE(payment_date) = CURDATE()
                    THEN amount_paid
                    ELSE 0
                END
            ), 0) AS today_revenue,

            SUM(
                CASE
                    WHEN payment_status = 'PAID'
                    THEN 1
                    ELSE 0
                END
            ) AS total_payments,

            SUM(
                CASE
                    WHEN payment_status = 'PAID'
                     AND DATE(payment_date) = CURDATE()
                    THEN 1
                    ELSE 0
                END
            ) AS paid_today

        FROM payments
    ");

    $statistics = $statisticsStatement->fetch(
        PDO::FETCH_ASSOC
    );

    respond(
        true,
        'Payment history loaded successfully.',
        [
            'payments' => $payments,

            'statistics' => [
                'total_revenue' =>
                    (float) ($statistics['total_revenue'] ?? 0),

                'today_revenue' =>
                    (float) ($statistics['today_revenue'] ?? 0),

                'total_payments' =>
                    (int) ($statistics['total_payments'] ?? 0),

                'paid_today' =>
                    (int) ($statistics['paid_today'] ?? 0)
            ]
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'Payment history error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load payment history.',
        [],
        500
    );
}