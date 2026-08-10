'use strict';

/*
|--------------------------------------------------------------------------
| Report State
|--------------------------------------------------------------------------
*/

let reportData = null;

let selectedPeriod = 'today';

let salesChartInstance = null;
let paymentChartInstance = null;
let orderStatusChartInstance = null;

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const periodButtons =
    document.querySelectorAll('.report-period-btn');

const startDateInput =
    document.getElementById('reportStartDate');

const endDateInput =
    document.getElementById('reportEndDate');

const applyFilterButton =
    document.getElementById('applyReportFilter');

const resetFilterButton =
    document.getElementById('resetReportFilter');

const selectedRangeText =
    document.getElementById('selectedReportRange');

const exportPdfButton =
    document.getElementById('exportPdfBtn');

const exportExcelButton =
    document.getElementById('exportExcelBtn');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatMoney(value) {
    return Number(value || 0)
        .toLocaleString('en-US') + ' MMK';
}

function escapeHtml(value) {
    const element = document.createElement('div');

    element.textContent = String(value ?? '');

    return element.innerHTML;
}

function formatDisplayDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(
        String(value).replace(' ', 'T')
    );

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

function getQueryParameters() {
    const parameters =
        new URLSearchParams();

    parameters.set(
        'period',
        selectedPeriod
    );

    if (selectedPeriod === 'custom') {
        parameters.set(
            'start_date',
            startDateInput?.value || ''
        );

        parameters.set(
            'end_date',
            endDateInput?.value || ''
        );
    }

    return parameters;
}

/*
|--------------------------------------------------------------------------
| Load Report
|--------------------------------------------------------------------------
*/

async function loadReports() {
    const parameters =
        getQueryParameters();

    try {
        setReportLoading(true);

        const response = await fetch(
            '../../back-end/api/reports/get_reports.php?' +
            parameters.toString(),
            {
                method: 'GET',

                headers: {
                    Accept: 'application/json'
                },

                cache: 'no-store'
            }
        );

        const responseText =
            await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch (error) {
            console.error(
                'Raw report response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message ||
                'Unable to load reports.'
            );
        }

        reportData = result.data;

        renderSummary();
        renderSelectedRange();
        renderSalesChart();
        renderPaymentChart();
        renderOrderStatusChart();
        renderTopMenu();
        renderSalesDetails();

    } catch (error) {
        console.error(
            'Reports error:',
            error
        );

        alert(
            error.message ||
            'Unable to load reports.'
        );

    } finally {
        setReportLoading(false);
    }
}

function setReportLoading(isLoading) {
    if (applyFilterButton) {
        applyFilterButton.disabled =
            isLoading;

        applyFilterButton.textContent =
            isLoading
                ? 'Loading...'
                : 'Apply';
    }
}

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

function renderSummary() {
    const summary =
        reportData?.summary || {};

    document.getElementById(
        'netSales'
    ).textContent =
        formatMoney(summary.net_sales);

    document.getElementById(
        'totalOrders'
    ).textContent =
        Number(summary.total_orders || 0)
            .toLocaleString('en-US');

    document.getElementById(
        'completedOrders'
    ).textContent =
        Number(summary.completed_orders || 0)
            .toLocaleString('en-US');

    document.getElementById(
        'cancelledOrders'
    ).textContent =
        Number(summary.cancelled_orders || 0)
            .toLocaleString('en-US');

    document.getElementById(
        'averageOrderValue'
    ).textContent =
        formatMoney(
            summary.average_order_value
        );

    document.getElementById(
        'taxCollected'
    ).textContent =
        formatMoney(summary.tax_collected);

    document.getElementById(
        'discountGiven'
    ).textContent =
        formatMoney(summary.discount_given);

    document.getElementById(
        'bestSeller'
    ).textContent =
        summary.best_seller || '-';
}

function renderSelectedRange() {
    if (!selectedRangeText) {
        return;
    }

    const range =
        reportData?.range || {};

    selectedRangeText.textContent =
        `Report period: ${range.start_date || '-'} to ` +
        `${range.end_date || '-'}`;
}

/*
|--------------------------------------------------------------------------
| Sales Chart
|--------------------------------------------------------------------------
*/

function renderSalesChart() {
    const canvas =
        document.getElementById('salesChart');

    if (!canvas) {
        return;
    }

    if (salesChartInstance) {
        salesChartInstance.destroy();
    }

    const data =
        reportData?.sales_chart || [];

    salesChartInstance = new Chart(
        canvas,
        {
            type: 'line',

            data: {
                labels: data.map(item => {
                    return item.day;
                }),

                datasets: [
                    {
                        label: 'Net Sales',

                        data: data.map(item => {
                            return Number(
                                item.amount || 0
                            );
                        }),

                        tension: 0.3,

                        fill: false
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: true
                    }
                },

                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        }
    );
}

/*
|--------------------------------------------------------------------------
| Payment Chart
|--------------------------------------------------------------------------
*/

function renderPaymentChart() {
    const canvas =
        document.getElementById('paymentChart');

    if (!canvas) {
        return;
    }

    if (paymentChartInstance) {
        paymentChartInstance.destroy();
    }

    const data =
        reportData?.payment_summary || [];

    paymentChartInstance = new Chart(
        canvas,
        {
            type: 'doughnut',

            data: {
                labels: data.map(item => {
                    return item.method;
                }),

                datasets: [
                    {
                        data: data.map(item => {
                            return Number(
                                item.amount || 0
                            );
                        })
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: false
            }
        }
    );
}

/*
|--------------------------------------------------------------------------
| Order Status Chart
|--------------------------------------------------------------------------
*/

function renderOrderStatusChart() {
    const canvas =
        document.getElementById(
            'orderStatusChart'
        );

    if (!canvas) {
        return;
    }

    if (orderStatusChartInstance) {
        orderStatusChartInstance.destroy();
    }

    const data =
        reportData?.order_status || [];

    orderStatusChartInstance = new Chart(
        canvas,
        {
            type: 'bar',

            data: {
                labels: data.map(item => {
                    return item.status;
                }),

                datasets: [
                    {
                        label: 'Orders',

                        data: data.map(item => {
                            return Number(
                                item.total || 0
                            );
                        })
                    }
                ]
            },

            options: {
                responsive: true,

                maintainAspectRatio: false,

                scales: {
                    y: {
                        beginAtZero: true,

                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        }
    );
}

/*
|--------------------------------------------------------------------------
| Top Menu
|--------------------------------------------------------------------------
*/

function renderTopMenu() {
    const tableBody =
        document.getElementById('topMenu');

    if (!tableBody) {
        return;
    }

    const items =
        reportData?.top_items || [];

    if (items.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5">
                    No menu sales found for this period.
                </td>
            </tr>
        `;

        return;
    }

    tableBody.innerHTML =
        items.map(item => {
            return `
                <tr>
                    <td>
                        ${escapeHtml(item.name)}
                    </td>

                    <td>
                        ${escapeHtml(
                            item.category || '-'
                        )}
                    </td>

                    <td>
                        ${Number(
                            item.quantity || 0
                        ).toLocaleString('en-US')}
                    </td>

                    <td>
                        ${formatMoney(item.revenue)}
                    </td>

                    <td>
                        ${Number(
                            item.sales_share || 0
                        ).toFixed(2)}%
                    </td>
                </tr>
            `;
        }).join('');
}

/*
|--------------------------------------------------------------------------
| Detailed Transactions
|--------------------------------------------------------------------------
*/

function renderSalesDetails() {
    const tableBody =
        document.getElementById(
            'salesDetailTable'
        );

    const countText =
        document.getElementById(
            'transactionCount'
        );

    if (!tableBody) {
        return;
    }

    const transactions =
        reportData?.sales_details || [];

    if (countText) {
        countText.textContent =
            `${transactions.length} records`;
    }

    if (transactions.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="10">
                    No completed sales found for this period.
                </td>
            </tr>
        `;

        return;
    }

    tableBody.innerHTML =
        transactions.map(transaction => {
            return `
                <tr>
                    <td>
                        ${escapeHtml(
                            formatDisplayDate(
                                transaction.invoice_date
                            )
                        )}
                    </td>

                    <td>
                        INV-${String(
                            transaction.invoice_id
                        ).padStart(5, '0')}
                    </td>

                    <td>
                        ORD-${String(
                            transaction.order_id
                        ).padStart(5, '0')}
                    </td>

                    <td>
                        Table ${escapeHtml(
                            transaction.table_number
                        )}
                    </td>

                    <td class="report-items-cell">
                        ${escapeHtml(
                            transaction.items || '-'
                        )}
                    </td>

                    <td>
                        ${escapeHtml(
                            transaction.payment_method
                        )}
                    </td>

                    <td>
                        ${formatMoney(
                            transaction.subtotal
                        )}
                    </td>

                    <td>
                        ${formatMoney(
                            transaction.tax_amount
                        )}
                    </td>

                    <td>
                        ${formatMoney(
                            transaction.discount_amount
                        )}
                    </td>

                    <td>
                        <strong>
                            ${formatMoney(
                                transaction.final_total
                            )}
                        </strong>
                    </td>
                </tr>
            `;
        }).join('');
}

/*
|--------------------------------------------------------------------------
| Period Buttons
|--------------------------------------------------------------------------
*/

periodButtons.forEach(button => {
    button.addEventListener(
        'click',
        function () {
            periodButtons.forEach(
                periodButton => {
                    periodButton.classList.remove(
                        'active'
                    );
                }
            );

            this.classList.add('active');

            selectedPeriod =
                this.dataset.period || 'today';

            if (selectedPeriod !== 'custom') {
                loadReports();
            }
        }
    );
});

applyFilterButton?.addEventListener(
    'click',
    function () {
        if (selectedPeriod === 'custom') {
            if (
                !startDateInput?.value ||
                !endDateInput?.value
            ) {
                alert(
                    'Please select both start and end dates.'
                );

                return;
            }

            if (
                startDateInput.value >
                endDateInput.value
            ) {
                alert(
                    'Start date cannot be after end date.'
                );

                return;
            }
        }

        loadReports();
    }
);

resetFilterButton?.addEventListener(
    'click',
    function () {
        selectedPeriod = 'today';

        if (startDateInput) {
            startDateInput.value = '';
        }

        if (endDateInput) {
            endDateInput.value = '';
        }

        periodButtons.forEach(button => {
            button.classList.toggle(
                'active',
                button.dataset.period === 'today'
            );
        });

        loadReports();
    }
);

/*
|--------------------------------------------------------------------------
| Export
|--------------------------------------------------------------------------
*/

exportPdfButton?.addEventListener(
    'click',
    function () {
        const parameters =
            getQueryParameters();

        window.location.href =
            '../../back-end/api/reports/export_pdf.php?' +
            parameters.toString();
    }
);

exportExcelButton?.addEventListener(
    'click',
    function () {
        const parameters =
            getQueryParameters();

        window.location.href =
            '../../back-end/api/reports/export_excel.php?' +
            parameters.toString();
    }
);

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

loadReports();