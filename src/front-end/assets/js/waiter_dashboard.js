'use strict';

const availableTablesElement =
    document.getElementById('availableTables');

const myOrdersElement =
    document.getElementById('myOrders');

const activeOrdersElement =
    document.getElementById('activeOrders');

const completedOrdersElement =
    document.getElementById('completedOrders');

const recentOrdersTable =
    document.getElementById('waiterRecentOrders');

const refreshButton =
    document.getElementById('refreshWaiterDashboard');

let isLoading = false;

function escapeHtml(value) {
    const element = document.createElement('div');

    element.textContent = String(value ?? '');

    return element.innerHTML;
}

function formatMoney(value) {
    return Number(value || 0)
        .toLocaleString('en-US') + ' MMK';
}

function formatOrderNumber(orderId) {
    return `ORD-${String(orderId).padStart(5, '0')}`;
}

function formatOrderTime(value) {
    const date = new Date(
        String(value || '').replace(' ', 'T')
    );

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getDisplayedStatus(order) {
    const chefAction = String(
        order.chef_action || ''
    ).toUpperCase();

    const orderStatus = String(
        order.status || ''
    ).toUpperCase();

    if (
        chefAction &&
        chefAction !== 'PENDING'
    ) {
        return chefAction;
    }

    return orderStatus || 'PENDING';
}

function getStatusClass(status) {
    switch (status) {
        case 'READY':
        case 'COMPLETED':
            return 'success';

        case 'PREPARING':
            return 'warning';

        case 'ACCEPTED':
        case 'CONFIRMED':
            return 'info';

        case 'CANCELLED':
            return 'danger';

        default:
            return 'pending';
    }
}

function renderRecentOrders(orders) {
    if (!recentOrdersTable) {
        return;
    }

    if (!Array.isArray(orders) || orders.length === 0) {
        recentOrdersTable.innerHTML = `
            <tr>
                <td colspan="5">
                    No orders found for today.
                </td>
            </tr>
        `;

        return;
    }

    recentOrdersTable.innerHTML = orders
        .map(order => {
            const status =
                getDisplayedStatus(order);

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
                        ${formatOrderTime(order.order_date)}
                    </td>

                    <td>
                        ${formatMoney(order.total_amount)}
                    </td>

                    <td>
                        <span class="badge ${getStatusClass(status)}">
                            ${escapeHtml(status)}
                        </span>
                    </td>
                </tr>
            `;
        })
        .join('');
}

async function loadWaiterDashboard(showFeedback = false) {
    if (isLoading) {
        return;
    }

    isLoading = true;

    if (refreshButton) {
        refreshButton.disabled = true;
        refreshButton.textContent = 'Loading...';
    }

    try {
        const response = await fetch(
            '../../back-end/api/dashboard/get_waiter_dashboard.php'
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
                'Raw waiter dashboard response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message ||
                'Unable to load waiter dashboard.'
            );
        }

        const summary =
            result.data?.summary || {};

        availableTablesElement.textContent =
            Number(summary.available_tables || 0);

        myOrdersElement.textContent =
            Number(summary.my_orders || 0);

        activeOrdersElement.textContent =
            Number(summary.active_orders || 0);

        completedOrdersElement.textContent =
            Number(summary.completed_orders || 0);

        renderRecentOrders(
            result.data?.recent_orders || []
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
            'Waiter dashboard error:',
            error
        );

        if (recentOrdersTable) {
            recentOrdersTable.innerHTML = `
                <tr>
                    <td colspan="5">
                        ${escapeHtml(
                            error.message ||
                            'Unable to load dashboard.'
                        )}
                    </td>
                </tr>
            `;
        }

    } finally {
        isLoading = false;

        if (refreshButton) {
            refreshButton.disabled = false;

            if (!showFeedback) {
                refreshButton.textContent =
                    'Refresh';
            }
        }
    }
}

refreshButton?.addEventListener(
    'click',
    function () {
        loadWaiterDashboard(true);
    }
);

loadWaiterDashboard();

const waiterRefreshInterval = setInterval(
    function () {
        loadWaiterDashboard(false);
    },
    15000
);

window.addEventListener(
    'beforeunload',
    function () {
        clearInterval(waiterRefreshInterval);
    }
);