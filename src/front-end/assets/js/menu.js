'use strict';

const menuGrid = document.getElementById('menuGrid');
const searchMenu = document.getElementById('searchMenu');
const categoryFilter = document.getElementById('categoryFilter');

const menuModal = document.getElementById('menuModal');
const menuForm = document.getElementById('menuForm');

const itemId = document.getElementById('itemId');
const itemName = document.getElementById('itemName');
const itemCategory = document.getElementById('itemCategory');
const itemDescription = document.getElementById(
    'itemDescription'
);
const itemPrice = document.getElementById('itemPrice');
const itemAvailability = document.getElementById(
    'itemAvailability'
);

let menuItems = [];

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function getCategoryIcon(categoryName) {
    const category = String(categoryName).toLowerCase();

    if (category === 'rice') return '🍚';
    if (category === 'noodle') return '🍜';
    if (category === 'curry') return '🍛';
    if (category === 'drink') return '🥤';
    if (category === 'dessert') return '🍰';

    return '🍽️';
}

function openMenuModal() {
    menuForm.reset();
    itemId.value = '';

    document.querySelector(
        '#menuModal .modal-header h2'
    ).textContent = 'Add Menu Item';

    menuModal.classList.add('show');
}

function closeMenuModal() {
    menuModal.classList.remove('show');
}

async function loadMenuItems() {
    try {
        const response = await fetch(
            '../../back-end/api/menu/read.php'
        );

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || 'Unable to load menu items.'
            );
        }

        menuItems = result.data;
        filterMenu();
    } catch (error) {
        console.error(error);

        menuGrid.innerHTML = `
            <p>Unable to load menu items.</p>
        `;
    }
}

function renderMenu(items) {
    menuGrid.innerHTML = '';

    if (items.length === 0) {
        menuGrid.innerHTML = `
            <p>No menu items found.</p>
        `;

        return;
    }

    items.forEach((item) => {
        const isAvailable = Number(item.availability) === 1;

        const statusText = isAvailable
            ? 'Available'
            : 'Unavailable';

        const statusClass = isAvailable
            ? 'available'
            : 'unavailable';

        menuGrid.innerHTML += `
            <div class="menu-card">

                <div class="food-icon">
                    ${getCategoryIcon(item.category_name)}
                </div>

                <h3>
                    ${escapeHtml(item.name)}
                </h3>

                <span class="menu-category">
                    ${escapeHtml(item.category_name)}
                </span>

                <p>
                    ${escapeHtml(
                        item.description || 'No description.'
                    )}
                </p>

                <div class="card-bottom">

                    <span class="price">
                        ${Number(item.price).toLocaleString()}
                        MMK
                    </span>

                    <span class="status ${statusClass}">
                        ${statusText}
                    </span>

                </div>

                <div class="card-actions">

                    <button
                        type="button"
                        class="edit-btn"
                        onclick="editMenu(${Number(item.item_id)})"
                    >
                        Edit
                    </button>

                    <button
                        type="button"
                        class="delete-btn"
                        onclick="deleteMenu(${Number(item.item_id)})"
                    >
                        Delete
                    </button>

                </div>

            </div>
        `;
    });
}

function filterMenu() {
    const searchValue =
        searchMenu.value.trim().toLowerCase();

    const selectedCategory =
        categoryFilter.value;

    const filteredItems = menuItems.filter((item) => {
        const matchesSearch = item.name
            .toLowerCase()
            .includes(searchValue);

        const matchesCategory =
            selectedCategory === 'all'
            || String(item.category_id)
                === String(selectedCategory);

        return matchesSearch && matchesCategory;
    });

    renderMenu(filteredItems);
}

menuForm.addEventListener('submit', async function (event) {
    event.preventDefault();

    const apiUrl = itemId.value
        ? '../../back-end/api/menu/update.php'
        : '../../back-end/api/menu/create.php';

    const formData = new FormData(menuForm);

    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        alert(result.message);

        if (result.success) {
            closeMenuModal();
            await loadMenuItems();
        }
    } catch (error) {
        console.error(error);
        alert('Unable to save menu item.');
    }
});

function editMenu(id) {
    const item = menuItems.find(
        menu => Number(menu.item_id) === Number(id)
    );

    if (!item) {
        return;
    }

    itemId.value = item.item_id;
    itemName.value = item.name;
    itemCategory.value = item.category_id;
    itemDescription.value = item.description || '';
    itemPrice.value = item.price;
    itemAvailability.value = Number(
        item.availability
    );

    document.querySelector(
        '#menuModal .modal-header h2'
    ).textContent = 'Edit Menu Item';

    menuModal.classList.add('show');
}

async function deleteMenu(id) {
    const confirmed = confirm(
        'Are you sure you want to delete this menu item?'
    );

    if (!confirmed) {
        return;
    }

    const formData = new FormData();
    formData.append('item_id', id);

    try {
        const response = await fetch(
            '../../back-end/api/menu/delete.php',
            {
                method: 'POST',
                body: formData
            }
        );

        const result = await response.json();

        alert(result.message);

        if (result.success) {
            await loadMenuItems();
        }
    } catch (error) {
        console.error(error);
        alert('Unable to delete menu item.');
    }
}

searchMenu.addEventListener('input', filterMenu);
categoryFilter.addEventListener('change', filterMenu);

loadMenuItems();