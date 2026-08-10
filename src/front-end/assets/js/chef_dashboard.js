'use strict';

const pendingCountElement =
    document.getElementById('chefPendingCount');

const acceptedCountElement =
    document.getElementById('chefAcceptedCount');

const preparingCountElement =
    document.getElementById('chefPreparingCount');

const readyCountElement =
    document.getElementById('chefReadyCount');

const recentOrdersTable =
    document.getElementById('chefRecentOrders');

const refreshButton =
    document.getElementById('refreshChefDashboard');

let isLoadingChefDashboard = false;

function escapeHtml(value) {
    const element = document.createElement('div');

    element.textContent = String(value ?? '');

    return element.innerHTML;
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
        case 'READY':
            return 'success';

        case 'PREPARING':
            return 'warning';

        case 'ACCEPTED':
        case 'CONFIRMED':
            return 'info';

        default:
            return 'pending';
    }
}

function renderChefOrders(orders) {
    if (!recentOrdersTable) {
        return;
    }

    if (!Array.isArray(orders) || orders.length === 0) {
        recentOrdersTable.innerHTML = `
            <tr>
                <td colspan="5">
                    No active kitchen orders for today.
                </td>
            </tr>
        `;

        return;
    }

    recentOrdersTable.innerHTML = orders
        .map(order => {
            const items = Array.isArray(order.items)
                ? order.items
                    .map(item => {
                        return `
                            ${escapeHtml(item.name)}
                            ×${Number(item.quantity)}
                        `;
                    })
                    .join(', ')
                : '-';

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

                    <td class="items-cell">
                        ${items}
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

async function loadChefDashboard(showFeedback = false) {
    if (isLoadingChefDashboard) {
        return;
    }

    isLoadingChefDashboard = true;

    if (refreshButton) {
        refreshButton.disabled = true;
        refreshButton.textContent = 'Loading...';
    }

    try {
        const response = await fetch(
            '../../back-end/api/dashboard/get_chef_dashboard.php'
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
                'Raw chef dashboard response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message ||
                'Unable to load chef dashboard.'
            );
        }

        const summary = result.data?.summary || {};

        pendingCountElement.textContent =
            Number(summary.pending || 0);

        acceptedCountElement.textContent =
            Number(summary.accepted || 0);

        preparingCountElement.textContent =
            Number(summary.preparing || 0);

        readyCountElement.textContent =
            Number(summary.ready || 0);

        renderChefOrders(
            result.data?.recent_orders || []
        );

        if (showFeedback && refreshButton) {
            refreshButton.textContent = 'Refreshed ✓';

            setTimeout(() => {
                refreshButton.textContent = 'Refresh';
            }, 900);
        }

    } catch (error) {
        console.error(
            'Chef dashboard error:',
            error
        );

        if (recentOrdersTable) {
            recentOrdersTable.innerHTML = `
                <tr>
                    <td colspan="5">
                        ${escapeHtml(
                            error.message ||
                            'Unable to load chef dashboard.'
                        )}
                    </td>
                </tr>
            `;
        }

    } finally {
        isLoadingChefDashboard = false;

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
            loadChefDashboard(true);
        }
    );
}

loadChefDashboard();

const chefRefreshInterval = setInterval(
    function () {
        loadChefDashboard(false);
    },
    15000
);

window.addEventListener(
    'beforeunload',
    function () {
        clearInterval(chefRefreshInterval);
    }
);