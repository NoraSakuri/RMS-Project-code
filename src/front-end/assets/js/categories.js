const categoryList = document.getElementById("categoryList");
const categoryModal = document.getElementById("categoryModal");
const categoryForm = document.getElementById("categoryForm");

const categoryId = document.getElementById("categoryId");
const categoryName = document.getElementById("categoryName");
const categoryStatus = document.getElementById("categoryStatus");

let categories = [];

function openCategoryModal() {
    categoryForm.reset();
    categoryId.value = "";
    document.querySelector(".modal-header h2").innerText = "Add Category";
    categoryModal.classList.add("show");
}

function closeCategoryModal() {
    categoryModal.classList.remove("show");
}

function loadCategories() {
    fetch("../../back-end/api/categories/read.php")
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                categories = data.data;
                renderCategories(categories);
            }
        })
        .catch(error => console.log(error));
}

function renderCategories(items) {
    categoryList.innerHTML = "";

    if (items.length === 0) {
        categoryList.innerHTML = `<p>No categories found.</p>`;
        return;
    }

    items.forEach(item => {
        const active = item.is_active == 1;
        const statusText = active ? "Active" : "Inactive";
        const statusClass = active ? "active" : "inactive";

        categoryList.innerHTML += `
            <div class="category-card">
                <h3>${item.category_name}</h3>
                <span class="status ${statusClass}">${statusText}</span>

                <div class="card-actions">
                    <button class="edit-btn" onclick="editCategory(${item.category_id})">Edit</button>
                    <button class="delete-btn" onclick="deleteCategory(${item.category_id})">Delete</button>
                </div>
            </div>
        `;
    });
}

categoryForm.addEventListener("submit", function(e) {
    e.preventDefault();

    const formData = new FormData(categoryForm);

    const apiUrl = categoryId.value
        ? "../../back-end/api/categories/update.php"
        : "../../back-end/api/categories/create.php";

    fetch(apiUrl, {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);

        if (data.success) {
            closeCategoryModal();
            loadCategories();
        }
    })
    .catch(error => console.log(error));
});

function editCategory(id) {
    const item = categories.find(category => category.category_id == id);

    if (!item) return;

    categoryId.value = item.category_id;
    categoryName.value = item.category_name;
    categoryStatus.value = item.is_active;

    document.querySelector(".modal-header h2").innerText = "Edit Category";
    categoryModal.classList.add("show");
}

function deleteCategory(id) {
    if (!confirm("Are you sure you want to delete this category?")) {
        return;
    }

    const formData = new FormData();
    formData.append("category_id", id);

    fetch("../../back-end/api/categories/delete.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);

        if (data.success) {
            loadCategories();
        }
    })
    .catch(error => console.log(error));
}

loadCategories();