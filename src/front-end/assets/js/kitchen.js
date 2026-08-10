'use strict';

const pendingOrders = document.getElementById('pendingOrders');
const acceptedOrders = document.getElementById('acceptedOrders');
const preparingOrders = document.getElementById('preparingOrders');
const readyOrders = document.getElementById('readyOrders');

const todayOrderCount = document.getElementById('todayOrderCount');
const pendingCount = document.getElementById('pendingCount');
const preparingCount = document.getElementById('preparingCount');
const readyCount = document.getElementById('readyCount');

const refreshOrdersButton = document.getElementById(
    'refreshOrdersButton'
);

let kitchenOrders = [];

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function formatOrderNumber(orderId) {
    return `#${String(orderId).padStart(5, '0')}`;
}

function formatOrderTime(dateValue) {
    const date = new Date(
        String(dateValue).replace(' ', 'T')
    );

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getColumnElement(status) {
    if (status === 'PENDING') {
        return pendingOrders;
    }

    if (status === 'CONFIRMED') {
        return acceptedOrders;
    }

    if (status === 'PREPARING') {
        return preparingOrders;
    }

    if (status === 'READY') {
        return readyOrders;
    }

    return null;
}

function getNextAction(status) {
    if (status === 'PENDING') {
        return {
            label: 'Accept Order',
            nextStatus: 'CONFIRMED',
            className: 'accept'
        };
    }

    if (status === 'CONFIRMED') {
        return {
            label: 'Start Preparing',
            nextStatus: 'PREPARING',
            className: 'preparing'
        };
    }

    if (status === 'PREPARING') {
        return {
            label: 'Mark Ready',
            nextStatus: 'READY',
            className: 'ready'
        };
    }

    if (status === 'READY') {
        return null;
    }

    return null;
}

function createOrderCard(order) {
    const action = getNextAction(order.status);

    const itemsHtml = Array.isArray(order.items)
        ? order.items.map(item => `
            <p>
                ${escapeHtml(item.name)}
                ×${Number(item.quantity)}
            </p>
        `).join('')
        : '';

    const noteHtml = order.customer_note
        ? `
            <div class="order-note">
                <strong>Note:</strong>
                ${escapeHtml(order.customer_note)}
            </div>
        `
        : '';

    const waiterName = order.waiter_name
        ? escapeHtml(order.waiter_name)
        : 'Unknown';

    return `
        <div
            class="order-card"
            data-order-id="${Number(order.order_id)}"
            data-status="${escapeHtml(order.status)}"
        >
            <div class="order-top">
                <strong>
                    ${formatOrderNumber(order.order_id)}
                </strong>

                <span>
                    Table ${Number(order.table_number)}
                </span>
            </div>

            <div class="order-time">
                ${formatOrderTime(order.order_date)}
            </div>

            <div class="order-waiter">
                Waiter: ${waiterName}
            </div>

            <div class="order-list">
                ${itemsHtml || '<p>No items found.</p>'}
            </div>

            ${noteHtml}

            ${action ? `
                <button
                    type="button"
                    class="action-btn ${action.className}"
                    data-order-id="${Number(order.order_id)}"
                    data-next-status="${action.nextStatus}"
                >
                    ${action.label}
                </button>
            ` : ''}
        </div>
    `;
}

function renderOrders() {
    const columnMap = {
        PENDING: pendingOrders,
        CONFIRMED: acceptedOrders,
        PREPARING: preparingOrders,
        READY: readyOrders
    };

    Object.values(columnMap).forEach(column => {
        if (column) {
            column.innerHTML = '';
        }
    });

    const counts = {
        PENDING: 0,
        CONFIRMED: 0,
        PREPARING: 0,
        READY: 0
    };

    kitchenOrders.forEach(order => {
        const column = getColumnElement(order.status);

        if (!column) {
            return;
        }

        counts[order.status]++;

        column.insertAdjacentHTML(
            'beforeend',
            createOrderCard(order)
        );
    });

    Object.entries(columnMap).forEach(([status, column]) => {
        if (column && counts[status] === 0) {
            column.innerHTML = `
                <p class="empty-column">
                    No ${status.toLowerCase()} orders.
                </p>
            `;
        }
    });

    if (todayOrderCount) {
        todayOrderCount.textContent = kitchenOrders.length;
    }

    if (pendingCount) {
        pendingCount.textContent = counts.PENDING;
    }

    if (preparingCount) {
        preparingCount.textContent = counts.PREPARING;
    }

    if (readyCount) {
        readyCount.textContent = counts.READY;
    }
}

async function loadKitchenOrders() {
    if (refreshOrdersButton) {
        refreshOrdersButton.disabled = true;
        refreshOrdersButton.textContent = 'Loading...';
    }

    try {
        const response = await fetch(
            '../../back-end/api/orders/get_kitchen_orders.php',
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
        } catch (error) {
            console.error('Raw API response:', responseText);

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Unable to load orders.'
            );
        }

        kitchenOrders = Array.isArray(result.data)
            ? result.data
            : [];

        renderOrders();
    } catch (error) {
        console.error(error);

        alert(
            error.message || 'Unable to load kitchen orders.'
        );
    } finally {
        if (refreshOrdersButton) {
            refreshOrdersButton.disabled = false;
            refreshOrdersButton.textContent = 'Refresh Orders';
        }
    }
}

async function updateOrderStatus(orderId, status, button) {
    const originalText = button.textContent;

    button.disabled = true;
    button.textContent = 'Updating...';

    try {
        const response = await fetch(
            '../../back-end/api/orders/update_kitchen_status.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                body: JSON.stringify({
                    order_id: Number(orderId),
                    status: status
                })
            }
        );

        const responseText = await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch (error) {
            console.error('Raw API response:', responseText);

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Unable to update order.'
            );
        }

        await loadKitchenOrders();
    } catch (error) {
        console.error(error);

        alert(
            error.message || 'Unable to update order.'
        );

        button.disabled = false;
        button.textContent = originalText;
    }
}

document.addEventListener('click', event => {
    const button = event.target.closest(
        '.action-btn[data-order-id]'
    );

    if (!button) {
        return;
    }

    updateOrderStatus(
        button.dataset.orderId,
        button.dataset.nextStatus,
        button
    );
});

if (refreshOrdersButton) {
    refreshOrdersButton.addEventListener(
        'click',
        loadKitchenOrders
    );
}

loadKitchenOrders();

const kitchenRefreshInterval = setInterval(() => {
    loadKitchenOrders();
}, 15000);

window.addEventListener('beforeunload', () => {
    clearInterval(kitchenRefreshInterval);
});