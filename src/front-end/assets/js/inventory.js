'use strict';

const searchInventory = document.getElementById(
    'searchInventory'
);

const categoryFilter = document.getElementById(
    'categoryFilter'
);

const statusFilter = document.getElementById(
    'statusFilter'
);

const inventoryModal = document.getElementById(
    'inventoryModal'
);

const inventoryForm = document.getElementById(
    'inventoryForm'
);

const inventoryModalTitle = document.getElementById(
    'inventoryModalTitle'
);

const inventoryId = document.getElementById(
    'inventoryId'
);

const itemName = document.getElementById(
    'itemName'
);

const category = document.getElementById(
    'category'
);

const quantity = document.getElementById(
    'quantity'
);

const unit = document.getElementById(
    'unit'
);

const minimumStock = document.getElementById(
    'minimumStock'
);

const supplier = document.getElementById(
    'supplier'
);

const addInventoryButton = document.getElementById(
    'addInventoryButton'
);

const closeModalButton = document.getElementById(
    'closeModal'
);

function getRows() {
    return document.querySelectorAll(
        '.inventory-table tbody tr'
    );
}

function filterInventory() {
    const searchValue =
        searchInventory.value.trim().toLowerCase();

    const categoryValue =
        categoryFilter.value;

    const statusValue =
        statusFilter.value;

    getRows().forEach(row => {
        if (row.children.length < 7) {
            return;
        }

        const itemText =
            row.children[0].textContent
                .trim()
                .toLowerCase();

        const categoryText =
            row.children[1].textContent.trim();

        const statusText =
            row.children[6].textContent.trim();

        const matchSearch =
            itemText.includes(searchValue);

        const matchCategory =
            categoryValue === 'all'
            || categoryText === categoryValue;

        const matchStatus =
            statusValue === 'All Status'
            || statusText === statusValue;

        row.style.display =
            matchSearch
            && matchCategory
            && matchStatus
                ? ''
                : 'none';
    });
}

function openAddModal() {
    inventoryForm.reset();
    inventoryId.value = '';

    inventoryModalTitle.textContent =
        'Add Inventory Item';

    inventoryModal.style.display = 'flex';
}

function closeInventoryModal() {
    inventoryModal.style.display = 'none';
    inventoryForm.reset();
    inventoryId.value = '';
}

function openEditModal(button) {
    inventoryId.value =
        button.dataset.id || '';

    itemName.value =
        button.dataset.name || '';

    category.value =
        button.dataset.category || '';

    quantity.value =
        button.dataset.quantity || '';

    unit.value =
        button.dataset.unit || 'KILOGRAM';

    minimumStock.value =
        button.dataset.minimum || '';

    supplier.value =
        button.dataset.supplier || '';

    inventoryModalTitle.textContent =
        'Edit Inventory Item';

    inventoryModal.style.display = 'flex';
}

function closeInventoryModal() {
    inventoryModal.style.display = 'none';
    inventoryForm.reset();
    inventoryId.value = '';
}

searchInventory.addEventListener(
    'input',
    filterInventory
);

categoryFilter.addEventListener(
    'change',
    filterInventory
);

statusFilter.addEventListener(
    'change',
    filterInventory
);

addInventoryButton.addEventListener(
    'click',
    openAddModal
);

closeModalButton.addEventListener(
    'click',
    closeInventoryModal
);

inventoryModal.addEventListener(
    'click',
    event => {
        if (event.target === inventoryModal) {
            closeInventoryModal();
        }
    }
);

document.addEventListener(
    'click',
    event => {
        const editButton = event.target.closest(
            '.edit[data-id]'
        );

        if (editButton) {
            openEditModal(editButton);
            return;
        }

        const deleteButton = event.target.closest(
            '.delete[data-id]'
        );

        if (deleteButton) {
            deleteInventory(
                deleteButton.dataset.id
            );
        }
    }
);

inventoryForm.addEventListener(
    'submit',
    async event => {
        event.preventDefault();

        const currentId =
            inventoryId.value.trim();

        const apiUrl = currentId
            ? '../../back-end/api/inventory/update.php'
            : '../../back-end/api/inventory/create.php';

        const payload = {
            inventory_id:
                currentId
                    ? Number(currentId)
                    : null,

            item_name:
                itemName.value.trim(),

            category_id:
                Number(category.value),

            quantity:
                Number(quantity.value),

            unit_type:
                unit.value,

            minimum_stock:
                Number(minimumStock.value),

            supplier_name:
                supplier.value.trim()
        };

        if (!payload.item_name) {
            alert('Item name is required.');
            return;
        }

        if (!payload.category_id) {
            alert('Please select a category.');
            return;
        }

        if (
            !Number.isFinite(payload.quantity)
            || payload.quantity < 0
        ) {
            alert('Please enter a valid quantity.');
            return;
        }

        if (
            !Number.isFinite(payload.minimum_stock)
            || payload.minimum_stock < 0
        ) {
            alert(
                'Please enter a valid minimum stock.'
            );
            return;
        }

        const saveButton =
            inventoryForm.querySelector(
                '.save-btn'
            );

        const originalText =
            saveButton.textContent;

        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';

        try {
            const response = await fetch(
                apiUrl,
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
                result = JSON.parse(responseText);
            } catch {
                console.error(
                    'Raw response:',
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
                    || 'Unable to save inventory item.'
                );
            }

            alert(result.message);

            closeInventoryModal();
            window.location.reload();
        } catch (error) {
            console.error(error);

            alert(
                error.message
                || 'Unable to save inventory item.'
            );
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = originalText;
        }
    }
);

async function deleteInventory(id) {
    const confirmed = confirm(
        'Are you sure you want to delete this inventory item?'
    );

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch(
            '../../back-end/api/inventory/delete.php',
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    'Accept':
                        'application/json'
                },

                body: JSON.stringify({
                    inventory_id: Number(id)
                })
            }
        );

        const responseText =
            await response.text();

        let result;

        try {
            result = JSON.parse(responseText);
        } catch {
            console.error(
                'Raw delete response:',
                responseText
            );

            throw new Error(
                'Server returned an invalid response.'
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || 'Unable to delete inventory item.'
            );
        }

        alert(result.message);
        window.location.reload();
    } catch (error) {
        console.error(error);

        alert(
            error.message
            || 'Unable to delete inventory item.'
        );
    }
}