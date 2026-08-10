<?php

declare(strict_types=1);

header(
    'Content-Type: application/json; charset=utf-8'
);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__
    . '/../../../../config/database.php';

function respond(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

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

if ($role !== 'CASHIER') {
    respond(
        false,
        'You do not have permission to view this dashboard.',
        [],
        403
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(
        false,
        'Invalid request method.',
        [],
        405
    );
}

try {
    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    $summaryStatement = $pdo->query("
        SELECT
            (
                SELECT COALESCE(SUM(amount_paid), 0)
                FROM payments
                WHERE UPPER(payment_status) = 'PAID'
                  AND DATE(payment_date) = CURDATE()
            ) AS today_revenue,

            (
                SELECT COUNT(*)
                FROM invoices i
                INNER JOIN payments p
                    ON p.payment_id = i.payment_id
                WHERE UPPER(p.payment_status) = 'PAID'
                  AND DATE(i.invoice_date) = CURDATE()
            ) AS paid_invoices,

            (
                SELECT COUNT(*)
                FROM orders o
                WHERE UPPER(o.status) = 'READY'
                  AND DATE(o.order_date) = CURDATE()
                  AND NOT EXISTS (
                      SELECT 1
                      FROM invoices i
                      WHERE i.order_id = o.order_id
                  )
            ) AS pending_bills,

            (
                SELECT COUNT(*)
                FROM payments
                WHERE UPPER(payment_status) = 'PAID'
                  AND UPPER(payment_method) IN (
                      'CARD',
                      'CREDIT CARD',
                      'CREDIT_CARD'
                  )
                  AND DATE(payment_date) = CURDATE()
            ) AS card_payments
    ");

    $summary = $summaryStatement->fetch(
        PDO::FETCH_ASSOC
    ) ?: [];

    /*
    |--------------------------------------------------------------------------
    | Recent payments
    |--------------------------------------------------------------------------
    */

    $recentPaymentStatement = $pdo->query("
        SELECT
            i.invoice_id,
            i.order_id,
            p.amount_paid,
            p.payment_method,
            p.payment_date,
            t.table_number

        FROM payments p

        INNER JOIN invoices i
            ON i.payment_id = p.payment_id

        INNER JOIN orders o
            ON o.order_id = i.order_id

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        WHERE UPPER(p.payment_status) = 'PAID'
          AND DATE(p.payment_date) = CURDATE()

        ORDER BY p.payment_date DESC

        LIMIT 8
    ");

    $recentPayments = $recentPaymentStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($recentPayments as &$payment) {
        $payment['invoice_id'] =
            (int) $payment['invoice_id'];

        $payment['order_id'] =
            (int) $payment['order_id'];

        $payment['table_number'] =
            (int) $payment['table_number'];

        $payment['amount_paid'] =
            (float) $payment['amount_paid'];
    }

    unset($payment);

    /*
    |--------------------------------------------------------------------------
    | Payment method summary
    |--------------------------------------------------------------------------
    */

    $paymentMethodStatement = $pdo->query("
        SELECT
            payment_method,
            COUNT(*) AS payment_count,
            COALESCE(SUM(amount_paid), 0)
                AS total_amount

        FROM payments

        WHERE UPPER(payment_status) = 'PAID'
          AND DATE(payment_date) = CURDATE()

        GROUP BY payment_method

        ORDER BY total_amount DESC
    ");

    $paymentMethods = $paymentMethodStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($paymentMethods as &$method) {
        $method['payment_count'] =
            (int) $method['payment_count'];

        $method['total_amount'] =
            (float) $method['total_amount'];
    }

    unset($method);

    /*
    |--------------------------------------------------------------------------
    | Ready orders waiting for billing
    |--------------------------------------------------------------------------
    */

    $pendingOrderStatement = $pdo->query("
        SELECT
            o.order_id,
            o.order_date,
            o.total_amount,
            t.table_number

        FROM orders o

        INNER JOIN rms_tables t
            ON t.table_id = o.table_id

        WHERE UPPER(o.status) = 'READY'
          AND DATE(o.order_date) = CURDATE()

          AND NOT EXISTS (
              SELECT 1
              FROM invoices i
              WHERE i.order_id = o.order_id
          )

        ORDER BY o.order_date ASC
    ");

    $pendingOrders = $pendingOrderStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($pendingOrders as &$order) {
        $order['order_id'] =
            (int) $order['order_id'];

        $order['table_number'] =
            (int) $order['table_number'];

        $order['total_amount'] =
            (float) $order['total_amount'];
    }

    unset($order);

    respond(
        true,
        'Cashier dashboard loaded successfully.',
        [
            'summary' => [
                'today_revenue' =>
                    (float) (
                        $summary['today_revenue'] ?? 0
                    ),

                'paid_invoices' =>
                    (int) (
                        $summary['paid_invoices'] ?? 0
                    ),

                'pending_bills' =>
                    (int) (
                        $summary['pending_bills'] ?? 0
                    ),

                'card_payments' =>
                    (int) (
                        $summary['card_payments'] ?? 0
                    )
            ],

            'recent_payments' => $recentPayments,
            'payment_methods' => $paymentMethods,
            'pending_orders' => $pendingOrders
        ]
    );

} catch (Throwable $exception) {
    error_log(
        'Cashier dashboard error: '
        . $exception->getMessage()
    );

    respond(
        false,
        'Unable to load cashier dashboard.',
        [],
        500
    );
}