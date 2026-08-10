'use strict';

/*
|--------------------------------------------------------------------------
| Order Management
|--------------------------------------------------------------------------
*/

let order = [];

/*
|--------------------------------------------------------------------------
| Main Elements
|--------------------------------------------------------------------------
*/

const orderItems =
    document.getElementById('orderItems');

const subtotalText =
    document.getElementById('subtotal');

const taxText =
    document.getElementById('tax');

const grandTotalText =
    document.getElementById('grandTotal');

const searchFood =
    document.getElementById('searchFood');

const foodCards =
    document.querySelectorAll('.food-card');

const categoryButtons =
    document.querySelectorAll('.category-btn');

const tableSelect =
    document.getElementById('tableSelect');

const selectedTableText =
    document.getElementById('selectedTableText');

const orderNote =
    document.getElementById('orderNote');

const sendButton =
    document.querySelector('.send-btn');

/*
|--------------------------------------------------------------------------
| Recent Orders Elements
|--------------------------------------------------------------------------
*/

const recentOrdersGrid =
    document.getElementById('recentOrdersGrid');

const refreshOrdersButton =
    document.getElementById('refreshOrdersButton');

const activeOrderCount =
    document.getElementById('activeOrderCount');

/*
|--------------------------------------------------------------------------
| Notification Audio
|--------------------------------------------------------------------------
|
| File location:
| src/front-end/assets/audio/notification.mp3
|
*/

const notificationAudio = new Audio(
    '../assets/audio/notification.mp3'
);

notificationAudio.preload = 'auto';
notificationAudio.volume = 1;

let audioUnlocked = false;
let firstRecentOrderLoad = true;
let previousReadyOrderIds = new Set();

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function formatMoney(amount) {
    return Number(amount).toLocaleString('en-US') +
        ' MMK';
}

function escapeHtml(value) {
    const element = document.createElement('div');

    element.textContent = String(value ?? '');

    return element.innerHTML;
}

function formatOrderDate(value) {
    if (!value) {
        return '';
    }

    const formattedValue = String(value)
        .replace(' ', 'T');

    const date = new Date(formattedValue);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

/*
|--------------------------------------------------------------------------
| Add Menu Item
|--------------------------------------------------------------------------
*/

function addToOrder(itemId, name, price) {
    const numericItemId = Number(itemId);
    const numericPrice = Number(price);

    const existingItem = order.find(item => {
        return item.itemId === numericItemId;
    });

    if (existingItem) {
        existingItem.qty += 1;
    } else {
        order.push({
            itemId: numericItemId,
            name: name,
            price: numericPrice,
            qty: 1
        });
    }

    renderOrder();
}

/*
|--------------------------------------------------------------------------
| Render Current Order
|--------------------------------------------------------------------------
*/

function renderOrder() {
    if (!orderItems) {
        return;
    }

    orderItems.innerHTML = '';

    if (order.length === 0) {
        orderItems.innerHTML = `
            <div class="empty-order">
                <h3>No items selected</h3>

                <p>
                    Add menu items to create an order.
                </p>
            </div>
        `;
    } else {
        order.forEach((item, index) => {
            orderItems.innerHTML += `
                <div class="order-item">

                    <div class="item-info">
                        <h4>
                            ${escapeHtml(item.name)}
                        </h4>

                        <span>
                            ${formatMoney(item.price)}
                        </span>
                    </div>

                    <div class="qty-control">

                        <button
                            type="button"
                            onclick="decreaseQty(${index})"
                        >
                            −
                        </button>

                        <strong>
                            ${item.qty}
                        </strong>

                        <button
                            type="button"
                            onclick="increaseQty(${index})"
                        >
                            +
                        </button>

                    </div>

                </div>
            `;
        });
    }

    calculateTotal();

    if (sendButton) {
        sendButton.disabled =
            order.length === 0;
    }
}

/*
|--------------------------------------------------------------------------
| Quantity Controls
|--------------------------------------------------------------------------
*/

function increaseQty(index) {
    if (!order[index]) {
        return;
    }

    if (order[index].qty >= 99) {
        return;
    }

    order[index].qty += 1;

    renderOrder();
}

function decreaseQty(index) {
    if (!order[index]) {
        return;
    }

    order[index].qty -= 1;

    if (order[index].qty <= 0) {
        order.splice(index, 1);
    }

    renderOrder();
}

/*
|--------------------------------------------------------------------------
| Calculate Total
|--------------------------------------------------------------------------
*/

function calculateTotal() {
    const subtotal = order.reduce(
        (sum, item) => {
            return sum +
                (item.price * item.qty);
        },
        0
    );

    const tax = subtotal * 0.05;
    const total = subtotal + tax;

    if (subtotalText) {
        subtotalText.textContent =
            formatMoney(subtotal);
    }

    if (taxText) {
        taxText.textContent =
            formatMoney(tax);
    }

    if (grandTotalText) {
        grandTotalText.textContent =
            formatMoney(total);
    }
}

/*
|--------------------------------------------------------------------------
| Menu Search and Category Filter
|--------------------------------------------------------------------------
*/

function applyFilters() {
    const searchValue = searchFood
        ? searchFood.value.trim().toLowerCase()
        : '';

    const activeCategoryButton =
        document.querySelector(
            '.category-btn.active'
        );

    const selectedCategory =
        activeCategoryButton?.dataset.category ||
        'all';

    foodCards.forEach(card => {
        const itemName = String(
            card.dataset.name || ''
        ).toLowerCase();

        const itemCategory = String(
            card.dataset.category || ''
        );

        const matchesSearch =
            itemName.includes(searchValue);

        const matchesCategory =
            selectedCategory === 'all' ||
            itemCategory === selectedCategory;

        card.style.display =
            matchesSearch && matchesCategory
                ? 'block'
                : 'none';
    });
}

/*
|--------------------------------------------------------------------------
| Table Selection
|--------------------------------------------------------------------------
*/

function updateSelectedTable() {
    if (!tableSelect || !selectedTableText) {
        return;
    }

    const selectedOption =
        tableSelect.options[
            tableSelect.selectedIndex
        ];

    selectedTableText.textContent =
        selectedOption?.dataset.tableName ||
        selectedOption?.textContent?.trim() ||
        'No Table';
}

/*
|--------------------------------------------------------------------------
| Send Order to Kitchen
|--------------------------------------------------------------------------
*/

async function sendOrder() {
    if (!tableSelect?.value) {
        alert('Please select a table.');
        return;
    }

    if (order.length === 0) {
        alert('Please add at least one item.');
        return;
    }

    const payload = {
        table_id: Number(tableSelect.value),

        customer_note:
            orderNote?.value.trim() || '',

        items: order.map(item => ({
            item_id: item.itemId,
            quantity: item.qty
        }))
    };

    const originalButtonText =
        sendButton?.textContent ||
        'Send To Kitchen';

    if (sendButton) {
        sendButton.disabled = true;
        sendButton.textContent = 'Sending...';
    }

    try {
        const response = await fetch(
            '../../back-end/api/orders/create_order.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    Accept:
                        'application/json'
                },

                body: JSON.stringify(payload)
            }
        );

        const responseText =
            await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error(
                'Create order raw response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message ||
                'Unable to create order.'
            );
        }

        alert(
            `${result.order_number} sent to kitchen successfully.`
        );

        order = [];

        if (orderNote) {
            orderNote.value = '';
        }

        renderOrder();

        // Show the new order immediately.
        await loadRecentOrders(true);

    } catch (error) {
        console.error(
            'Create order error:',
            error
        );

        alert(
            error.message ||
            'Unable to send order.'
        );

    } finally {
        if (sendButton) {
            sendButton.textContent =
                originalButtonText;

            sendButton.disabled =
                order.length === 0;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Unlock Notification Sound
|--------------------------------------------------------------------------
|
| Safari and Chrome require a user click before JavaScript can play audio.
|
*/

async function unlockNotificationSound() {
    if (audioUnlocked) {
        return;
    }

    try {
        notificationAudio.volume = 0;

        await notificationAudio.play();

        notificationAudio.pause();
        notificationAudio.currentTime = 0;
        notificationAudio.volume = 1;

        audioUnlocked = true;

        console.log(
            '✅ Notification sound unlocked'
        );

    } catch (error) {
        notificationAudio.volume = 1;

        console.warn(
            'Notification sound unlock failed:',
            error
        );
    }
}

document.addEventListener(
    'click',
    unlockNotificationSound,
    { once: true }
);

/*
|--------------------------------------------------------------------------
| Play Ready Notification
|--------------------------------------------------------------------------
*/

async function playReadySound() {

    console.log("🔔 Sound Function Called");

    try {

        notificationAudio.pause();

        notificationAudio.currentTime = 0;

        notificationAudio.muted = false;

        notificationAudio.volume = 1;

        await notificationAudio.play();

        console.log("✅ SOUND PLAYED");

    } catch(error){

        console.error(error);

    }

}

/*
|--------------------------------------------------------------------------
| Determine Displayed Status
|--------------------------------------------------------------------------
*/

function getDisplayedOrderStatus(orderData) {
    const chefAction = String(
        orderData.chef_action || ''
    )
        .trim()
        .toUpperCase();

    const orderStatus = String(
        orderData.status || ''
    )
        .trim()
        .toUpperCase();

    if (
        chefAction &&
        chefAction !== 'PENDING'
    ) {
        return chefAction;
    }

    return orderStatus || 'PENDING';
}

/*
|--------------------------------------------------------------------------
| Render Recent Orders
|--------------------------------------------------------------------------
*/

function renderRecentOrders(data) {
    if (!recentOrdersGrid) {
        return;
    }

    const activeOrders = Array.isArray(data)
        ? data.filter(orderData => {
            const displayedStatus =
                getDisplayedOrderStatus(
                    orderData
                );

            return displayedStatus !==
                'COMPLETED';
        })
        : [];

    if (activeOrderCount) {
        activeOrderCount.textContent =
            `(${activeOrders.length})`;
    }

    /*
    |--------------------------------------------------------------------------
    | Detect newly READY orders
    |--------------------------------------------------------------------------
    */

    const currentReadyOrderIds = new Set(
        activeOrders
            .filter(orderData => {
                return getDisplayedOrderStatus(
                    orderData
                ) === 'READY';
            })
            .map(orderData => {
                return Number(
                    orderData.order_id
                );
            })
    );

    let hasNewReadyOrder = false;

    // Do not play sounds for READY orders
    // that already existed when the page opened.
    if (!firstRecentOrderLoad) {
        currentReadyOrderIds.forEach(orderId => {
            if (
                !previousReadyOrderIds.has(
                    orderId
                )
            ) {
                hasNewReadyOrder = true;
            }
        });
    }

    previousReadyOrderIds =
        currentReadyOrderIds;

    firstRecentOrderLoad = false;

    if (hasNewReadyOrder) {
        console.log("NEW READY DETECTED");
        playReadySound();
    }

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    if (activeOrders.length === 0) {
        recentOrdersGrid.innerHTML = `
            <div class="empty-recent-orders">
                No active orders found.
            </div>
        `;

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Order Cards
    |--------------------------------------------------------------------------
    */

    recentOrdersGrid.innerHTML =
        activeOrders
            .map(orderData => {
                const status =
                    getDisplayedOrderStatus(
                        orderData
                    );

                const statusClass =
                    status
                        .toLowerCase()
                        .replaceAll(' ', '-');

                const orderNumber =
                    `ORD-${String(
                        orderData.order_id
                    ).padStart(5, '0')}`;

                const readyClass =
                    status === 'READY'
                        ? ' newly-ready'
                        : '';

                return `
                    <article
                        class="recent-order-card${readyClass}"
                        data-order-id="${Number(
                            orderData.order_id
                        )}"
                    >

                        <div class="recent-order-top">

                            <div>
                                <h3>
                                    ${escapeHtml(
                                        orderNumber
                                    )}
                                </h3>

                                <p>
                                    Table
                                    ${escapeHtml(
                                        orderData.table_number
                                    )}
                                </p>
                            </div>

                            <span
                                class="order-status-badge ${escapeHtml(
                                    statusClass
                                )}"
                            >
                                ${escapeHtml(status)}
                            </span>

                        </div>

                        <div class="recent-order-time">
                            ${escapeHtml(
                                formatOrderDate(
                                    orderData.order_date
                                )
                            )}
                        </div>

                    </article>
                `;
            })
            .join('');
}

/*
|--------------------------------------------------------------------------
| Load Recent Orders
|--------------------------------------------------------------------------
*/

async function loadRecentOrders(
    showLoading = false
) {
    if (!recentOrdersGrid) {
        return;
    }

    if (showLoading) {
        recentOrdersGrid.classList.add(
            'is-refreshing'
        );
    }

    if (refreshOrdersButton) {
        refreshOrdersButton.disabled = true;

        refreshOrdersButton.textContent =
            'Refreshing...';
    }

    try {
        const response = await fetch(
            '../../back-end/api/orders/get_recent_orders.php',
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
        } catch (parseError) {
            console.error(
                'Recent orders raw response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message ||
                'Unable to load recent orders.'
            );
        }

        renderRecentOrders(result.data);

    } catch (error) {
        console.error(
            'Recent orders error:',
            error
        );

        recentOrdersGrid.innerHTML = `
            <div
                class="empty-recent-orders error"
            >
                ${escapeHtml(
                    error.message ||
                    'Unable to load recent orders.'
                )}
            </div>
        `;

    } finally {
        recentOrdersGrid.classList.remove(
            'is-refreshing'
        );

        if (refreshOrdersButton) {
            refreshOrdersButton.disabled =
                false;

            refreshOrdersButton.textContent =
                'Refresh';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Event Listeners
|--------------------------------------------------------------------------
*/

searchFood?.addEventListener(
    'input',
    applyFilters
);

categoryButtons.forEach(button => {
    button.addEventListener(
        'click',
        function () {
            categoryButtons.forEach(
                categoryButton => {
                    categoryButton.classList.remove(
                        'active'
                    );
                }
            );

            this.classList.add('active');

            applyFilters();
        }
    );
});

tableSelect?.addEventListener(
    'change',
    updateSelectedTable
);

document
    .querySelectorAll('.add-menu-btn')
    .forEach(button => {
        button.addEventListener(
            'click',
            function () {
                addToOrder(
                    this.dataset.itemId,
                    this.dataset.name,
                    this.dataset.price
                );
            }
        );
    });

sendButton?.addEventListener(
    'click',
    sendOrder
);

refreshOrdersButton?.addEventListener(
    'click',
    function () {
        loadRecentOrders(true);
    }
);

/*
|--------------------------------------------------------------------------
| Initial Page Setup
|--------------------------------------------------------------------------
*/

renderOrder();
applyFilters();
updateSelectedTable();
loadRecentOrders();

/*
|--------------------------------------------------------------------------
| Auto Refresh Every 10 Seconds
|--------------------------------------------------------------------------
*/

setInterval(
    loadRecentOrders,
    10000
);

/*
|--------------------------------------------------------------------------
| Refresh immediately when returning to Waiter tab
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'visibilitychange',
    function () {
        if (document.visibilityState === 'visible') {
            loadRecentOrders();
        }
    }
);