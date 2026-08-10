'use strict';

const revenueElement =
    document.getElementById('managerTodayRevenue');

const ordersElement =
    document.getElementById('managerTodayOrders');

const lowStockElement =
    document.getElementById('managerLowStock');

const reservationsElement =
    document.getElementById('managerReservations');

const recentOrdersTable =
    document.getElementById('managerRecentOrders');

const lowStockTable =
    document.getElementById('managerLowStockItems');

const refreshButton =
    document.getElementById('refreshManagerDashboard');

let isLoadingManagerDashboard = false;

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

    return date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusClass(status) {
    switch (status) {
        case 'COMPLETED':
        case 'READY':
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
            const status = String(
                order.status || 'PENDING'
            ).toUpperCase();

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

function renderLowStockItems(items) {
    if (!lowStockTable) {
        return;
    }

    if (!Array.isArray(items) || items.length === 0) {
        lowStockTable.innerHTML = `
            <tr>
                <td colspan="4">
                    No low-stock items.
                </td>
            </tr>
        `;

        return;
    }

    lowStockTable.innerHTML = items
        .map(item => {
            const quantity = Number(item.quantity || 0);
            const minimum = Number(item.minimum_stock || 0);

            const status = quantity <= 0
                ? 'OUT OF STOCK'
                : 'LOW STOCK';

            const statusClass = quantity <= 0
                ? 'danger'
                : 'warning';

            return `
                <tr>
                    <td>
                        <strong>
                            ${escapeHtml(item.item_name)}
                        </strong>
                    </td>

                    <td>
                        ${quantity.toLocaleString()}
                        ${escapeHtml(item.unit_type)}
                    </td>

                    <td>
                        ${minimum.toLocaleString()}
                        ${escapeHtml(item.unit_type)}
                    </td>

                    <td>
                        <span class="badge ${statusClass}">
                            ${status}
                        </span>
                    </td>
                </tr>
            `;
        })
        .join('');
}

async function loadManagerDashboard(showFeedback = false) {
    if (isLoadingManagerDashboard) {
        return;
    }

    isLoadingManagerDashboard = true;

    if (refreshButton) {
        refreshButton.disabled = true;
        refreshButton.textContent = 'Loading...';
    }

    try {
        const response = await fetch(
            '../../back-end/api/dashboard/get_manager_dashboard.php'
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

        const responseText = await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch {
            console.error(
                'Raw manager dashboard response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message ||
                'Unable to load manager dashboard.'
            );
        }

        const summary = result.data?.summary || {};

        revenueElement.textContent =
            formatMoney(summary.today_revenue);

        ordersElement.textContent =
            Number(summary.today_orders || 0);

        lowStockElement.textContent =
            Number(summary.low_stock_items || 0);

        reservationsElement.textContent =
            Number(summary.today_reservations || 0);

        renderRecentOrders(
            result.data?.recent_orders || []
        );

        renderLowStockItems(
            result.data?.low_stock_items || []
        );

        if (showFeedback && refreshButton) {
            refreshButton.textContent = 'Refreshed ✓';

            setTimeout(() => {
                refreshButton.textContent = 'Refresh';
            }, 900);
        }

    } catch (error) {
        console.error(
            'Manager dashboard error:',
            error
        );

        if (recentOrdersTable) {
            recentOrdersTable.innerHTML = `
                <tr>
                    <td colspan="5">
                        ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }

    } finally {
        isLoadingManagerDashboard = false;

        if (refreshButton) {
            refreshButton.disabled = false;

            if (!showFeedback) {
                refreshButton.textContent = 'Refresh';
            }
        }
    }
}

if (refreshButton) {
    refreshButton.addEventListener(
        'click',
        function () {
            loadManagerDashboard(true);
        }
    );
}

loadManagerDashboard();

const managerRefreshInterval = setInterval(
    function () {
        loadManagerDashboard(false);
    },
    30000
);

window.addEventListener(
    'beforeunload',
    function () {
        clearInterval(managerRefreshInterval);
    }
);