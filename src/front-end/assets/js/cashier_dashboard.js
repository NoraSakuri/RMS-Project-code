'use strict';

const todayRevenueElement =
    document.getElementById('cashierTodayRevenue');

const paidInvoicesElement =
    document.getElementById('cashierPaidInvoices');

const pendingBillsElement =
    document.getElementById('cashierPendingBills');

const cardPaymentsElement =
    document.getElementById('cashierCardPayments');

const recentPaymentsTable =
    document.getElementById('cashierRecentPayments');

const pendingOrdersTable =
    document.getElementById('cashierPendingOrders');

const refreshButton =
    document.getElementById('refreshCashierDashboard');

let paymentChartInstance = null;
let cashierDashboardLoading = false;

function escapeHtml(value) {
    const element = document.createElement('div');

    element.textContent = String(value ?? '');

    return element.innerHTML;
}

function formatMoney(value) {
    return Number(value || 0)
        .toLocaleString('en-US') + ' MMK';
}

function formatInvoiceNumber(invoiceId) {
    return `INV-${String(invoiceId).padStart(5, '0')}`;
}

function formatOrderNumber(orderId) {
    return `ORD-${String(orderId).padStart(5, '0')}`;
}

function formatTime(value) {
    const date = new Date(
        String(value || '').replace(' ', 'T')
    );

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function renderRecentPayments(payments) {
    if (!recentPaymentsTable) {
        return;
    }

    if (!Array.isArray(payments) || payments.length === 0) {
        recentPaymentsTable.innerHTML = `
            <tr>
                <td colspan="6">
                    No payments completed today.
                </td>
            </tr>
        `;

        return;
    }

    recentPaymentsTable.innerHTML = payments
        .map(payment => {
            return `
                <tr>
                    <td>
                        <strong>
                            ${formatInvoiceNumber(payment.invoice_id)}
                        </strong>
                    </td>

                    <td>
                        ${formatOrderNumber(payment.order_id)}
                    </td>

                    <td>
                        Table ${escapeHtml(payment.table_number)}
                    </td>

                    <td>
                        <span class="badge info">
                            ${escapeHtml(payment.payment_method)}
                        </span>
                    </td>

                    <td>
                        <strong>
                            ${formatMoney(payment.amount_paid)}
                        </strong>
                    </td>

                    <td>
                        ${formatTime(payment.payment_date)}
                    </td>
                </tr>
            `;
        })
        .join('');
}

function renderPendingOrders(orders) {
    if (!pendingOrdersTable) {
        return;
    }

    if (!Array.isArray(orders) || orders.length === 0) {
        pendingOrdersTable.innerHTML = `
            <tr>
                <td colspan="5">
                    No orders are waiting for payment.
                </td>
            </tr>
        `;

        return;
    }

    pendingOrdersTable.innerHTML = orders
        .map(order => {
            return `
                <tr>
                    <td>
                        <strong>
                            ${formatOrderNumber(order.order_id)}
                        </strong>
                    </td>

                    <td>
                        Table ${escapeHtml(order.table_number)}
                    </td>

                    <td>
                        ${formatTime(order.order_date)}
                    </td>

                    <td>
                        ${formatMoney(order.total_amount)}
                    </td>

                    <td>
                        <span class="badge success">
                            READY
                        </span>
                    </td>
                </tr>
            `;
        })
        .join('');
}

function renderPaymentChart(methods) {
    const canvas =
        document.getElementById('cashierPaymentChart');

    if (!canvas) {
        return;
    }

    if (paymentChartInstance) {
        paymentChartInstance.destroy();
    }

    const labels = Array.isArray(methods)
        ? methods.map(item => item.payment_method)
        : [];

    const amounts = Array.isArray(methods)
        ? methods.map(item => Number(item.total_amount || 0))
        : [];

    paymentChartInstance = new Chart(
        canvas,
        {
            type: 'doughnut',

            data: {
                labels: labels,

                datasets: [
                    {
                        data: amounts
                    }
                ]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        }
    );
}

async function loadCashierDashboard(showFeedback = false) {
    if (cashierDashboardLoading) {
        return;
    }

    cashierDashboardLoading = true;

    if (refreshButton) {
        refreshButton.disabled = true;
        refreshButton.textContent = 'Loading...';
    }

    try {
        const response = await fetch(
            '../../back-end/api/dashboard/get_cashier_dashboard.php'
                + '?_='
                + Date.now(),
            {
                method: 'GET',

                headers: {
                    'Accept': 'application/json'
                },

                credentials: 'same-origin',
                cache: 'no-store'
            }
        );

        const responseText =
            await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch {
            console.error(
                'Raw cashier dashboard response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message ||
                'Unable to load cashier dashboard.'
            );
        }

        const summary =
            result.data?.summary || {};

        todayRevenueElement.textContent =
            formatMoney(summary.today_revenue);

        paidInvoicesElement.textContent =
            Number(summary.paid_invoices || 0);

        pendingBillsElement.textContent =
            Number(summary.pending_bills || 0);

        cardPaymentsElement.textContent =
            Number(summary.card_payments || 0);

        renderRecentPayments(
            result.data?.recent_payments || []
        );

        renderPendingOrders(
            result.data?.pending_orders || []
        );

        renderPaymentChart(
            result.data?.payment_methods || []
        );

        if (showFeedback && refreshButton) {
            refreshButton.textContent =
                'Refreshed ✓';

            setTimeout(() => {
                refreshButton.textContent =
                    'Refresh';
            }, 900);
        }

    } catch (error) {
        console.error(
            'Cashier dashboard error:',
            error
        );

        if (recentPaymentsTable) {
            recentPaymentsTable.innerHTML = `
                <tr>
                    <td colspan="6">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }

        if (pendingOrdersTable) {
            pendingOrdersTable.innerHTML = `
                <tr>
                    <td colspan="5">
                        Unable to load pending bills.
                    </td>
                </tr>
            `;
        }

    } finally {
        cashierDashboardLoading = false;

        if (refreshButton) {
            refreshButton.disabled = false;

            if (!showFeedback) {
                refreshButton.textContent = 'Refresh';
            }
        }
    }
}

refreshButton?.addEventListener(
    'click',
    function () {
        loadCashierDashboard(true);
    }
);

loadCashierDashboard();

const cashierRefreshInterval = setInterval(
    function () {
        loadCashierDashboard(false);
    },
    20000
);

window.addEventListener(
    'beforeunload',
    function () {
        clearInterval(cashierRefreshInterval);
    }
);