'use strict';

const paymentHistoryBody = document.getElementById(
    'paymentHistoryBody'
);

const searchPayment = document.getElementById(
    'searchPayment'
);

const refreshPaymentsButton = document.getElementById(
    'refreshPaymentsButton'
);

const totalRevenue = document.getElementById(
    'totalRevenue'
);

const todayRevenue = document.getElementById(
    'todayRevenue'
);

const totalPayments = document.getElementById(
    'totalPayments'
);

const paidToday = document.getElementById(
    'paidToday'
);

let payments = [];

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US')
        + ' MMK';
}

function formatInvoiceNumber(invoiceId) {
    return `INV-${String(invoiceId).padStart(5, '0')}`;
}

function formatOrderNumber(orderId) {
    return `ORD-${String(orderId).padStart(5, '0')}`;
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

function formatPaymentMethod(method) {
    return String(method)
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/\b\w/g, letter => letter.toUpperCase());
}

function renderStatistics(statistics = {}) {
    totalRevenue.textContent = formatMoney(
        statistics.total_revenue
    );

    todayRevenue.textContent = formatMoney(
        statistics.today_revenue
    );

    totalPayments.textContent = Number(
        statistics.total_payments || 0
    );

    paidToday.textContent = Number(
        statistics.paid_today || 0
    );
}

function renderPayments() {
    const searchValue =
        searchPayment.value.trim().toLowerCase();

    const filteredPayments = payments.filter(payment => {
        const invoiceNumber = formatInvoiceNumber(
            payment.invoice_id
        ).toLowerCase();

        const orderNumber = formatOrderNumber(
            payment.order_id
        ).toLowerCase();

        const tableName =
            `table ${payment.table_number}`.toLowerCase();

        const method = String(
            payment.payment_method
        ).toLowerCase();

        return invoiceNumber.includes(searchValue)
            || orderNumber.includes(searchValue)
            || tableName.includes(searchValue)
            || method.includes(searchValue);
    });

    paymentHistoryBody.innerHTML = '';

    if (filteredPayments.length === 0) {
        paymentHistoryBody.innerHTML = `
            <tr>
                <td colspan="8">
                    No paid invoices found.
                </td>
            </tr>
        `;

        return;
    }

    filteredPayments.forEach(payment => {
        paymentHistoryBody.insertAdjacentHTML(
            'beforeend',
            `
                <tr>
                    <td>
                        ${formatInvoiceNumber(
                            payment.invoice_id
                        )}
                    </td>

                    <td>
                        ${formatOrderNumber(
                            payment.order_id
                        )}
                    </td>

                    <td>
                        Table ${Number(payment.table_number)}
                    </td>

                    <td>
                        ${escapeHtml(
                            formatPaymentMethod(
                                payment.payment_method
                            )
                        )}
                    </td>

                    <td>
                        ${formatMoney(payment.amount_paid)}
                    </td>

                    <td>
                        <span class="status available">
                            ${escapeHtml(
                                payment.payment_status
                            )}
                        </span>
                    </td>

                    <td>
                        ${formatDate(payment.payment_date)}
                    </td>

                    <td>
                        <button
                            type="button"
                            class="print-payment-btn"
                            data-invoice-id="${
                                Number(payment.invoice_id)
                            }"
                        >
                            Print
                        </button>
                    </td>
                </tr>
            `
        );
    });
}

async function loadPaymentHistory() {
    refreshPaymentsButton.disabled = true;
    refreshPaymentsButton.textContent = 'Loading...';

    try {
        const response = await fetch(
            '../../back-end/api/billing/get_payment_history.php',
            {
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
                'Raw payment response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to load payment history.'
            );
        }

        payments = Array.isArray(result.data?.payments)
            ? result.data.payments
            : [];

        renderStatistics(
            result.data?.statistics || {}
        );

        renderPayments();
    } catch (error) {
        console.error(error);

        alert(
            error.message
            || 'Unable to load payment history.'
        );
    } finally {

    refreshPaymentsButton.disabled = false;
    refreshPaymentsButton.textContent = 'Refreshed ✓';

    setTimeout(() => {
        refreshPaymentsButton.textContent = 'Refresh';
    }, 800);
}
}

searchPayment.addEventListener(
    'input',
    renderPayments
);

refreshPaymentsButton.addEventListener(
    'click',
    loadPaymentHistory
);

paymentHistoryBody.addEventListener(
    'click',
    event => {
        const button = event.target.closest(
            '.print-payment-btn'
        );

        if (!button) {
            return;
        }

        const invoiceId = Number(
            button.dataset.invoiceId
        );

        if (!invoiceId) {
            alert('Invalid invoice.');
            return;
        }

        window.location.href =
            `invoice.php?invoice_id=${invoiceId}`;
    }
);

loadPaymentHistory();