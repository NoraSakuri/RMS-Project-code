'use strict';

const readyOrderList = document.getElementById(
    'readyOrderList'
);

const searchInvoice = document.getElementById(
    'searchInvoice'
);

const emptyInvoicePreview = document.getElementById(
    'emptyInvoicePreview'
);

const invoiceContent = document.getElementById(
    'invoiceContent'
);

const invoiceNumber = document.getElementById(
    'invoiceNumber'
);

const invoiceTable = document.getElementById(
    'invoiceTable'
);

const invoiceCashier = document.getElementById(
    'invoiceCashier'
);

const invoiceDate = document.getElementById(
    'invoiceDate'
);

const invoiceItems = document.getElementById(
    'invoiceItems'
);

const invoiceSubtotal = document.getElementById(
    'invoiceSubtotal'
);

const discountAmount = document.getElementById(
    'discountAmount'
);

const invoiceTax = document.getElementById(
    'invoiceTax'
);

const invoiceTotal = document.getElementById(
    'invoiceTotal'
);

const todayRevenue = document.getElementById(
    'todayRevenue'
);

const invoiceCount = document.getElementById(
    'invoiceCount'
);

const pendingPaymentCount = document.getElementById(
    'pendingPaymentCount'
);

const paidCount = document.getElementById(
    'paidCount'
);

const completePaymentButton = document.getElementById(
    'completePaymentButton'
);

const clearSelectionButton = document.getElementById(
    'clearSelectionButton'
);

let readyOrders = [];
let selectedOrder = null;
let completedInvoiceNumber = null;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US')
        + ' MMK';
}

function formatOrderNumber(orderId) {
    return `ORD-${String(orderId).padStart(5, '0')}`;
}

function formatDate(dateValue) {
    const date = new Date(
        String(dateValue).replace(' ', 'T')
    );

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString();
}

function renderStatistics(statistics = {}) {
    todayRevenue.textContent = formatMoney(
        statistics.today_revenue
    );

    invoiceCount.textContent =
        Number(statistics.invoice_count || 0);

    pendingPaymentCount.textContent =
        Number(statistics.pending_payment || 0);

    paidCount.textContent =
        Number(statistics.paid_today || 0);
}

function renderOrderList() {
    const searchValue =
        searchInvoice.value.trim().toLowerCase();

    const filteredOrders = readyOrders.filter(order => {
        const orderNumber = formatOrderNumber(
            order.order_id
        ).toLowerCase();

        const tableName =
            `table ${order.table_number}`.toLowerCase();

        return orderNumber.includes(searchValue)
            || tableName.includes(searchValue);
    });

    readyOrderList.innerHTML = '';

    if (filteredOrders.length === 0) {
        readyOrderList.innerHTML = `
            <p class="empty-billing-list">
                No orders ready for payment.
            </p>
        `;

        return;
    }

    filteredOrders.forEach(order => {
        const activeClass =
            selectedOrder
                && Number(selectedOrder.order_id)
                === Number(order.order_id)
                ? 'active'
                : '';

        readyOrderList.insertAdjacentHTML(
            'beforeend',
            `
                <button
                    type="button"
                    class="invoice-card ${activeClass}"
                    data-order-id="${Number(order.order_id)}"
                >
                    <h4>
                        ${formatOrderNumber(order.order_id)}
                    </h4>

                    <span>
                        Table ${Number(order.table_number)}
                    </span>

                    <strong>
                        ${formatMoney(order.total_amount)}
                    </strong>
                </button>
            `
        );
    });
}

function updateBillingTotal() {
    if (!selectedOrder) {
        return;
    }

    const subtotal = Number(
        selectedOrder.subtotal || 0
    );

    const tax = Number(
        selectedOrder.tax_amount || 0
    );

    let discount = Number(
        discountAmount.value || 0
    );

    if (!Number.isFinite(discount) || discount < 0) {
        discount = 0;
    }

    if (discount > subtotal) {
        discount = subtotal;
        discountAmount.value = subtotal;
    }

    const total = subtotal + tax - discount;

    invoiceSubtotal.textContent =
        formatMoney(subtotal);

    invoiceTax.textContent =
        formatMoney(tax);

    invoiceTotal.textContent =
        formatMoney(total);
}

function showOrder(order) {
    selectedOrder = order;
    completedInvoiceNumber = null;

    emptyInvoicePreview.style.display = 'none';
    invoiceContent.style.display = 'block';

    invoiceNumber.textContent =
        formatOrderNumber(order.order_id);

    invoiceTable.textContent =
        `Table ${Number(order.table_number)}`;

    invoiceCashier.textContent = 'Cashier: Current User';

    invoiceDate.textContent = formatDate(order.order_date);

    invoiceItems.innerHTML = '';

    const items = Array.isArray(order.items)
        ? order.items
        : [];

    items.forEach(item => {
        invoiceItems.insertAdjacentHTML(
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

    invoiceSubtotal.textContent =
        formatMoney(order.subtotal);

    discountAmount.value = Number(
        order.discount_amount || 0
    );

    updateBillingTotal();

    completePaymentButton.disabled = false;

    renderOrderList();
}

function clearSelection() {
    selectedOrder = null;
    completedInvoiceNumber = null;

    discountAmount.value = 0;

    invoiceContent.style.display = 'none';
    emptyInvoicePreview.style.display = 'block';

    completePaymentButton.disabled = true;

    renderOrderList();
}

async function loadBillingOrders() {
    try {
        const response = await fetch(
            '../../back-end/api/billing/get_ready_orders.php',
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
            console.error('Raw response:', responseText);

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to load billing orders.'
            );
        }

        readyOrders = Array.isArray(result.data?.orders)
            ? result.data.orders
            : [];

        renderStatistics(
            result.data?.statistics || {}
        );

        renderOrderList();

        if (
            selectedOrder
            && !readyOrders.some(
                order => Number(order.order_id)
                    === Number(selectedOrder.order_id)
            )
        ) {
            clearSelection();
        }
    } catch (error) {
        console.error(error);

        alert(
            error.message
            || 'Unable to load billing orders.'
        );
    }
}

async function completePayment() {
    if (!selectedOrder) {
        alert('Please select an order.');
        return;
    }

    const paymentMethodElement = document.querySelector(
        'input[name="payment_method"]:checked'
    );

    if (!paymentMethodElement) {
        alert('Please select a payment method.');
        return;
    }

    const confirmed = confirm(
        `Complete payment of ${formatMoney(
            selectedOrder.total_amount
        )}?`
    );

    if (!confirmed) {
        return;
    }

    const originalText = completePaymentButton.textContent;

    completePaymentButton.disabled = true;
    completePaymentButton.textContent =
        'Processing Payment...';

    try {
        const response = await fetch(
            '../../back-end/api/billing/complete_payment.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                body: JSON.stringify({
                    order_id: Number(selectedOrder.order_id),

                    payment_method:
                        paymentMethodElement.value,

                    discount_amount:
                        Number(discountAmount.value || 0)
                })
            }
        );

        const responseText = await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch {
            console.error('Raw response:', responseText);

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to complete payment.'
            );
        }

        completedInvoiceNumber =
            result.invoice_number;

        invoiceNumber.textContent =
            completedInvoiceNumber;

        alert(
            `${completedInvoiceNumber} payment completed successfully.`
        );

        readyOrders = readyOrders.filter(
            order => Number(order.order_id)
                !== Number(selectedOrder.order_id)
        );

        selectedOrder = null;

        renderOrderList();
        await loadBillingOrders();
    } catch (error) {
        console.error(error);

        alert(
            error.message
            || 'Unable to complete payment.'
        );
    } finally {
        completePaymentButton.textContent =
            originalText;

        completePaymentButton.disabled =
            selectedOrder === null;
    }
}

readyOrderList.addEventListener('click', event => {
    const card = event.target.closest(
        '.invoice-card[data-order-id]'
    );

    if (!card) {
        return;
    }

    const order = readyOrders.find(
        item => Number(item.order_id)
            === Number(card.dataset.orderId)
    );

    if (order) {
        showOrder(order);
    }
});

searchInvoice.addEventListener(
    'input',
    renderOrderList
);

discountAmount.addEventListener(
    'input',
    updateBillingTotal
);

completePaymentButton.addEventListener(
    'click',
    completePayment
);

clearSelectionButton.addEventListener(
    'click',
    clearSelection
);

clearSelection();
loadBillingOrders();