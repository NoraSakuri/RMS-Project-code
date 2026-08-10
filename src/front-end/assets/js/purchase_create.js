'use strict';

const purchaseCreateForm =
    document.getElementById('purchaseCreateForm');

const purchaseSupplier =
    document.getElementById('purchaseSupplier');

const purchaseDate =
    document.getElementById('purchaseDate');

const invoiceNumber =
    document.getElementById('invoiceNumber');

const purchaseStatus =
    document.getElementById('purchaseStatus');

const purchaseNote =
    document.getElementById('purchaseNote');

const purchaseItemsBody =
    document.getElementById('purchaseItemsBody');

const purchaseItemsEmpty =
    document.getElementById('purchaseItemsEmpty');

const addPurchaseItemButton =
    document.getElementById('addPurchaseItemButton');

const purchaseTax =
    document.getElementById('purchaseTax');

const purchaseDiscount =
    document.getElementById('purchaseDiscount');

const purchasePaidAmount =
    document.getElementById('purchasePaidAmount');

const purchaseSubtotal =
    document.getElementById('purchaseSubtotal');

const purchaseTotal =
    document.getElementById('purchaseTotal');

const purchaseBalance =
    document.getElementById('purchaseBalance');

const purchasePaymentStatus =
    document.getElementById('purchasePaymentStatus');

const savePurchaseButton =
    document.getElementById('savePurchaseButton');

let suppliers = [];
let inventoryItems = [];
let purchaseRowCounter = 0;

function escapeHtml(value) {
    const div = document.createElement('div');

    div.textContent = String(value ?? '');

    return div.innerHTML;
}

function formatMoney(value) {
    return `${Number(value || 0).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }
    )} MMK`;
}

function setDefaultDate() {
    const today = new Date();

    const year = today.getFullYear();

    const month = String(
        today.getMonth() + 1
    ).padStart(2, '0');

    const day = String(
        today.getDate()
    ).padStart(2, '0');

    purchaseDate.value =
        `${year}-${month}-${day}`;
}

function renderSupplierOptions() {
    purchaseSupplier.innerHTML = `
        <option value="">
            Select Supplier
        </option>
    `;

    suppliers.forEach(supplier => {
        const contactInformation = [
            supplier.contact_person,
            supplier.phone
        ]
            .filter(Boolean)
            .join(' - ');

        const option = document.createElement(
            'option'
        );

        option.value =
            supplier.supplier_id;

        option.textContent =
            contactInformation
                ? `${supplier.supplier_name} (${contactInformation})`
                : supplier.supplier_name;

        purchaseSupplier.appendChild(option);
    });
}

function getSelectedInventoryIds(
    ignoredRow = null
) {
    return Array.from(
        purchaseItemsBody.querySelectorAll(
            '.purchase-item-row'
        )
    )
        .filter(row => row !== ignoredRow)
        .map(row => {
            const select = row.querySelector(
                '.purchase-item-select'
            );

            return Number(select.value);
        })
        .filter(Boolean);
}

function createInventoryOptions(
    currentValue = '',
    currentRow = null
) {
    const selectedIds =
        getSelectedInventoryIds(currentRow);

    const options = [
        `
            <option value="">
                Select Item
            </option>
        `
    ];

    inventoryItems.forEach(item => {
        const itemId = Number(
            item.inventory_id
        );

        const isAlreadySelected =
            selectedIds.includes(itemId)
            && Number(currentValue) !== itemId;

        if (isAlreadySelected) {
            return;
        }

        const selected =
            Number(currentValue) === itemId
                ? 'selected'
                : '';

        options.push(`
            <option
                value="${itemId}"
                ${selected}
            >
                ${escapeHtml(item.item_name)}
            </option>
        `);
    });

    return options.join('');
}

function refreshInventoryOptions() {
    const rows = Array.from(
        purchaseItemsBody.querySelectorAll(
            '.purchase-item-row'
        )
    );

    rows.forEach(row => {
        const select = row.querySelector(
            '.purchase-item-select'
        );

        const currentValue = select.value;

        select.innerHTML =
            createInventoryOptions(
                currentValue,
                row
            );

        select.value = currentValue;
    });
}

function addPurchaseItemRow() {
    purchaseRowCounter += 1;

    const row = document.createElement('tr');

    row.className = 'purchase-item-row';

    row.dataset.rowId =
        String(purchaseRowCounter);

    row.innerHTML = `
        <td>
            <select
                class="purchase-item-select"
                required
            >
                ${createInventoryOptions('', row)}
            </select>
        </td>

        <td>
            <div class="current-stock-display">
                <strong class="current-stock-value">
                    0
                </strong>

                <span class="current-stock-unit">
                    -
                </span>
            </div>
        </td>

        <td>
            <input
                type="number"
                class="purchase-item-quantity"
                min="0.001"
                step="0.001"
                value="1"
                required
            >
        </td>

        <td>
            <input
                type="text"
                class="purchase-item-unit"
                readonly
                value="-"
            >
        </td>

        <td>
            <input
                type="number"
                class="purchase-item-unit-cost"
                min="0"
                step="0.01"
                value="0"
                required
            >
        </td>

        <td>
            <strong class="purchase-item-total">
                0 MMK
            </strong>
        </td>

        <td>
            <button
                type="button"
                class="purchase-remove-item-btn"
                title="Remove item"
            >
                ×
            </button>
        </td>
    `;

    purchaseItemsBody.appendChild(row);

    purchaseItemsEmpty.style.display = 'none';

    refreshInventoryOptions();

    const itemSelect = row.querySelector(
        '.purchase-item-select'
    );

    itemSelect.focus();

    calculatePurchaseTotals();
}

function removePurchaseItemRow(row) {
    row.remove();

    refreshInventoryOptions();

    updateEmptyItemState();

    calculatePurchaseTotals();
}

function updateEmptyItemState() {
    const hasRows =
        purchaseItemsBody.querySelector(
            '.purchase-item-row'
        );

    purchaseItemsEmpty.style.display =
        hasRows
            ? 'none'
            : 'block';
}

function getInventoryItem(inventoryId) {
    return inventoryItems.find(item => {
        return Number(item.inventory_id)
            === Number(inventoryId);
    });
}

function updateItemRow(row) {
    const inventorySelect = row.querySelector(
        '.purchase-item-select'
    );

    const currentStockValue = row.querySelector(
        '.current-stock-value'
    );

    const currentStockUnit = row.querySelector(
        '.current-stock-unit'
    );

    const unitInput = row.querySelector(
        '.purchase-item-unit'
    );

    const quantityInput = row.querySelector(
        '.purchase-item-quantity'
    );

    const unitCostInput = row.querySelector(
        '.purchase-item-unit-cost'
    );

    const totalElement = row.querySelector(
        '.purchase-item-total'
    );

    const inventory = getInventoryItem(
        inventorySelect.value
    );

    if (inventory) {
        currentStockValue.textContent =
            Number(
                inventory.quantity || 0
            ).toLocaleString();

        currentStockUnit.textContent =
            inventory.unit_type || '-';

        unitInput.value =
            inventory.unit_type || '-';
    } else {
        currentStockValue.textContent = '0';
        currentStockUnit.textContent = '-';
        unitInput.value = '-';
    }

    const quantity = Math.max(
        Number(quantityInput.value || 0),
        0
    );

    const unitCost = Math.max(
        Number(unitCostInput.value || 0),
        0
    );

    const itemTotal =
        quantity * unitCost;

    totalElement.textContent =
        formatMoney(itemTotal);

    totalElement.dataset.total =
        String(itemTotal);
}

function calculatePurchaseTotals() {
    let subtotal = 0;

    const rows = purchaseItemsBody.querySelectorAll(
        '.purchase-item-row'
    );

    rows.forEach(row => {
        updateItemRow(row);

        const totalElement = row.querySelector(
            '.purchase-item-total'
        );

        subtotal += Number(
            totalElement.dataset.total || 0
        );
    });

    const taxAmount = Math.max(
        Number(purchaseTax.value || 0),
        0
    );

    const discountAmount = Math.max(
        Number(purchaseDiscount.value || 0),
        0
    );

    const paidAmount = Math.max(
        Number(purchasePaidAmount.value || 0),
        0
    );

    const totalAmount = Math.max(
        subtotal
        + taxAmount
        - discountAmount,
        0
    );

    const balance = Math.max(
        totalAmount - paidAmount,
        0
    );

    let paymentStatus = 'UNPAID';

    if (
        totalAmount > 0
        && paidAmount >= totalAmount
    ) {
        paymentStatus = 'PAID';
    } else if (paidAmount > 0) {
        paymentStatus = 'PARTIAL';
    }

    purchaseSubtotal.textContent =
        formatMoney(subtotal);

    purchaseTotal.textContent =
        formatMoney(totalAmount);

    purchaseBalance.textContent =
        formatMoney(balance);

    purchasePaymentStatus.textContent =
        paymentStatus;

    purchasePaymentStatus.className =
        `payment-preview ${paymentStatus.toLowerCase()}`;

    purchaseSubtotal.dataset.value =
        String(subtotal);

    purchaseTotal.dataset.value =
        String(totalAmount);
}

function collectPurchaseItems() {
    const rows = Array.from(
        purchaseItemsBody.querySelectorAll(
            '.purchase-item-row'
        )
    );

    return rows.map((row, index) => {
        const inventoryId = Number(
            row.querySelector(
                '.purchase-item-select'
            ).value
        );

        const quantity = Number(
            row.querySelector(
                '.purchase-item-quantity'
            ).value
        );

        const unitCost = Number(
            row.querySelector(
                '.purchase-item-unit-cost'
            ).value
        );

        if (!inventoryId) {
            throw new Error(
                `Please select an inventory item on row ${
                    index + 1
                }.`
            );
        }

        if (
            !Number.isFinite(quantity)
            || quantity <= 0
        ) {
            throw new Error(
                `Quantity must be greater than zero on row ${
                    index + 1
                }.`
            );
        }

        if (
            !Number.isFinite(unitCost)
            || unitCost < 0
        ) {
            throw new Error(
                `Invalid unit cost on row ${
                    index + 1
                }.`
            );
        }

        return {
            inventory_id: inventoryId,
            quantity,
            unit_cost: unitCost
        };
    });
}

async function loadPurchaseFormData() {
    addPurchaseItemButton.disabled = true;

    try {
        const response = await fetch(
            '../../back-end/api/purchases/form_data.php',
            {
                headers: {
                    'Accept':
                        'application/json'
                }
            }
        );

        const responseText =
            await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch {
            console.error(
                'Raw purchase form response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (
            !response.ok
            || !result.success
        ) {
            throw new Error(
                result.message
                || 'Unable to load purchase form.'
            );
        }

        suppliers = Array.isArray(
            result.data?.suppliers
        )
            ? result.data.suppliers
            : [];

        inventoryItems = Array.isArray(
            result.data?.inventory_items
        )
            ? result.data.inventory_items
            : [];

        renderSupplierOptions();

        if (suppliers.length === 0) {
            alert(
                'No active suppliers were found. Please create an active supplier first.'
            );
        }

        if (inventoryItems.length === 0) {
            alert(
                'No inventory items were found. Please create inventory items first.'
            );
        } else {
            addPurchaseItemRow();
        }
    } catch (error) {
        console.error(error);

        alert(
            error.message
            || 'Unable to load purchase form.'
        );
    } finally {
        addPurchaseItemButton.disabled = false;
    }
}

purchaseItemsBody.addEventListener(
    'change',
    event => {
        const row = event.target.closest(
            '.purchase-item-row'
        );

        if (!row) {
            return;
        }

        if (
            event.target.classList.contains(
                'purchase-item-select'
            )
        ) {
            refreshInventoryOptions();
        }

        updateItemRow(row);

        calculatePurchaseTotals();
    }
);

purchaseItemsBody.addEventListener(
    'input',
    event => {
        const row = event.target.closest(
            '.purchase-item-row'
        );

        if (!row) {
            return;
        }

        if (
            event.target.classList.contains(
                'purchase-item-quantity'
            )
            || event.target.classList.contains(
                'purchase-item-unit-cost'
            )
        ) {
            updateItemRow(row);

            calculatePurchaseTotals();
        }
    }
);

purchaseItemsBody.addEventListener(
    'click',
    event => {
        const removeButton = event.target.closest(
            '.purchase-remove-item-btn'
        );

        if (!removeButton) {
            return;
        }

        const row = removeButton.closest(
            '.purchase-item-row'
        );

        if (row) {
            removePurchaseItemRow(row);
        }
    }
);

addPurchaseItemButton.addEventListener(
    'click',
    () => {
        if (
            purchaseItemsBody.querySelectorAll(
                '.purchase-item-row'
            ).length >= inventoryItems.length
        ) {
            alert(
                'All available inventory items have already been added.'
            );

            return;
        }

        addPurchaseItemRow();
    }
);

[
    purchaseTax,
    purchaseDiscount,
    purchasePaidAmount
].forEach(input => {
    input.addEventListener(
        'input',
        calculatePurchaseTotals
    );
});

purchaseCreateForm.addEventListener(
    'submit',
    async event => {
        event.preventDefault();

        try {
            const supplierId = Number(
                purchaseSupplier.value
            );

            if (!supplierId) {
                throw new Error(
                    'Please select a supplier.'
                );
            }

            if (!purchaseDate.value) {
                throw new Error(
                    'Purchase date is required.'
                );
            }

            const items = collectPurchaseItems();

            if (items.length === 0) {
                throw new Error(
                    'Please add at least one purchase item.'
                );
            }

            calculatePurchaseTotals();

            const subtotal = Number(
                purchaseSubtotal.dataset.value || 0
            );

            const totalAmount = Number(
                purchaseTotal.dataset.value || 0
            );

            const taxAmount = Number(
                purchaseTax.value || 0
            );

            const discountAmount = Number(
                purchaseDiscount.value || 0
            );

            const paidAmount = Number(
                purchasePaidAmount.value || 0
            );

            if (discountAmount > subtotal + taxAmount) {
                throw new Error(
                    'Discount cannot be greater than subtotal plus tax.'
                );
            }

            if (paidAmount > totalAmount) {
                throw new Error(
                    'Paid amount cannot be greater than total amount.'
                );
            }

            const payload = {
                supplier_id: supplierId,

                purchase_date:
                    purchaseDate.value,

                invoice_number:
                    invoiceNumber.value.trim(),

                purchase_status:
                    purchaseStatus.value,

                tax_amount: taxAmount,

                discount_amount:
                    discountAmount,

                paid_amount: paidAmount,

                note:
                    purchaseNote.value.trim(),

                items
            };

            const confirmed = confirm(
                `Save this purchase as ${purchaseStatus.value}?`
            );

            if (!confirmed) {
                return;
            }

            const originalText =
                savePurchaseButton.textContent;

            savePurchaseButton.disabled = true;

            savePurchaseButton.textContent =
                'Saving...';

            try {
                const response = await fetch(
                    '../../back-end/api/purchases/create.php',
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json'
                        },

                        body: JSON.stringify(payload)
                    }
                );

                const responseText =
                    await response.text();

                let result;

                try {
                    result = JSON.parse(
                        responseText
                    );
                } catch {
                    console.error(
                        'Raw purchase create response:',
                        responseText
                    );

                    throw new Error(
                        'Server returned an invalid response.'
                    );
                }

                if (
                    !response.ok
                    || !result.success
                ) {
                    throw new Error(
                        result.message
                        || 'Unable to create purchase.'
                    );
                }

                alert(
                    `${result.message}\nPurchase Number: ${
                        result.data.purchase_number
                    }`
                );

                window.location.href =
                    './purchases.php';
            } finally {
                savePurchaseButton.disabled =
                    false;

                savePurchaseButton.textContent =
                    originalText;
            }
        } catch (error) {
            console.error(error);

            alert(
                error.message
                || 'Unable to create purchase.'
            );
        }
    }
);

document.addEventListener(
    'DOMContentLoaded',
    () => {
        setDefaultDate();
        calculatePurchaseTotals();
        loadPurchaseFormData();
    }
);