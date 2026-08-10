"use strict";

/*
|--------------------------------------------------------------------------
| Purchase Edit JavaScript
|--------------------------------------------------------------------------
| File:
| front-end/assets/js/purchase-edit.js
|
| Backend API:
| ../../back-end/api/purchases/get_one.php?id=1
| ../../back-end/api/purchases/update.php
|--------------------------------------------------------------------------
*/

document.addEventListener("DOMContentLoaded", () => {
    /*
    |--------------------------------------------------------------------------
    | API URLS
    |--------------------------------------------------------------------------
    */

    const API = {
        getOne: "../../back-end/api/purchases/get_one.php",
        update: "../../back-end/api/purchases/update.php",
    };

    /*
    |--------------------------------------------------------------------------
    | DOM ELEMENTS
    |--------------------------------------------------------------------------
    */

    const editModal = document.getElementById("editPurchaseModal");
    const editForm = document.getElementById("editPurchaseForm");

    const purchaseIdInput = document.getElementById("editPurchaseId");
    const supplierInput = document.getElementById("editSupplierId");
    const purchaseDateInput = document.getElementById("editPurchaseDate");
    const invoiceNumberInput = document.getElementById("editInvoiceNumber");
    const purchaseStatusInput = document.getElementById("editPurchaseStatus");
    const paymentStatusInput = document.getElementById("editPaymentStatus");
    const taxInput = document.getElementById("editTaxAmount");
    const discountInput = document.getElementById("editDiscountAmount");
    const paidAmountInput = document.getElementById("editPaidAmount");
    const noteInput = document.getElementById("editNote");

    const itemsContainer = document.getElementById("editPurchaseItems");

    const subtotalDisplay = document.getElementById("editSubtotal");
    const totalDisplay = document.getElementById("editTotalAmount");
    const balanceDisplay = document.getElementById("editBalanceAmount");

    const addItemButton = document.getElementById("addEditPurchaseItem");
    const closeButtons = document.querySelectorAll(
        "[data-close-edit-purchase]"
    );

    const submitButton = editForm
        ? editForm.querySelector('button[type="submit"]')
        : null;

    /*
    |--------------------------------------------------------------------------
    | STATE
    |--------------------------------------------------------------------------
    */

    let currentPurchaseId = null;
    let ingredientOptions = [];
    let isSubmitting = false;

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    function toNumber(value) {
        const number = parseFloat(value);

        return Number.isFinite(number) ? number : 0;
    }

    function formatMoney(value) {
        return `${Math.round(toNumber(value)).toLocaleString("en-US")} MMK`;
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function showMessage(message, type = "success") {
        if (typeof window.showToast === "function") {
            window.showToast(message, type);
            return;
        }

        if (typeof window.Swal !== "undefined") {
            window.Swal.fire({
                icon: type === "error" ? "error" : "success",
                title: type === "error" ? "Error" : "Success",
                text: message,
            });

            return;
        }

        alert(message);
    }

    async function parseJsonResponse(response) {
        const responseText = await response.text();

        if (!responseText.trim()) {
            throw new Error("Server returned an empty response.");
        }

        let data;

        try {
            data = JSON.parse(responseText);
        } catch (error) {
            console.error("Invalid server response:", responseText);

            throw new Error(
                "Server response is not valid JSON. Check the PHP API file."
            );
        }

        if (!response.ok) {
            throw new Error(
                data.message ||
                `Request failed with status ${response.status}.`
            );
        }

        return data;
    }

    function setLoading(button, loading, loadingText = "Saving...") {
        if (!button) {
            return;
        }

        if (loading) {
            button.dataset.originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = loadingText;
        } else {
            button.disabled = false;
            button.innerHTML =
                button.dataset.originalText || "Update Purchase";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MODAL
    |--------------------------------------------------------------------------
    */

    function openEditModal() {
        if (!editModal) {
            console.error(
                'Element with id="editPurchaseModal" was not found.'
            );

            return;
        }

        editModal.classList.add("show");
        editModal.classList.add("active");
        editModal.removeAttribute("hidden");
        editModal.setAttribute("aria-hidden", "false");

        document.body.classList.add("modal-open");
    }

    function closeEditModal() {
        if (!editModal) {
            return;
        }

        editModal.classList.remove("show");
        editModal.classList.remove("active");
        editModal.setAttribute("hidden", "");
        editModal.setAttribute("aria-hidden", "true");

        document.body.classList.remove("modal-open");

        resetEditForm();
    }

    function resetEditForm() {
        if (editForm) {
            editForm.reset();
        }

        currentPurchaseId = null;

        if (purchaseIdInput) {
            purchaseIdInput.value = "";
        }

        if (itemsContainer) {
            itemsContainer.innerHTML = "";
        }

        updateTotals();
    }

    /*
    |--------------------------------------------------------------------------
    | INGREDIENT OPTIONS
    |--------------------------------------------------------------------------
    */

    function createIngredientOptions(selectedIngredientId = "") {
        if (!ingredientOptions.length) {
            return `
                <option value="">
                    Select ingredient
                </option>
            `;
        }

        const firstOption = `
            <option value="">
                Select ingredient
            </option>
        `;

        const options = ingredientOptions
            .map((ingredient) => {
                const ingredientId =
                    ingredient.id ??
                    ingredient.ingredient_id ??
                    ingredient.inventory_item_id ??
                    "";

                const ingredientName =
                    ingredient.name ??
                    ingredient.ingredient_name ??
                    ingredient.item_name ??
                    "Unknown Ingredient";

                const unit =
                    ingredient.unit ??
                    ingredient.unit_name ??
                    "";

                const selected =
                    String(ingredientId) ===
                    String(selectedIngredientId)
                        ? "selected"
                        : "";

                return `
                    <option
                        value="${escapeHtml(ingredientId)}"
                        data-unit="${escapeHtml(unit)}"
                        ${selected}
                    >
                        ${escapeHtml(ingredientName)}
                        ${unit ? ` (${escapeHtml(unit)})` : ""}
                    </option>
                `;
            })
            .join("");

        return firstOption + options;
    }

    /*
    |--------------------------------------------------------------------------
    | PURCHASE ITEM ROW
    |--------------------------------------------------------------------------
    */

    function createItemRow(item = {}) {
        if (!itemsContainer) {
            return;
        }

        const purchaseItemId =
            item.id ??
            item.purchase_item_id ??
            item.purchase_detail_id ??
            "";

        const ingredientId =
            item.ingredient_id ??
            item.inventory_item_id ??
            item.item_id ??
            "";

        const ingredientName =
            item.ingredient_name ??
            item.item_name ??
            item.name ??
            "";

        const quantity = toNumber(item.quantity || 1);

        const unitCost = toNumber(
            item.unit_cost ??
            item.cost ??
            item.purchase_price ??
            0
        );

        const itemTotal = quantity * unitCost;

        const row = document.createElement("div");
        row.className = "edit-purchase-item-row purchase-item-row";

        row.innerHTML = `
            <input
                type="hidden"
                class="edit-purchase-item-id"
                value="${escapeHtml(purchaseItemId)}"
            >

            <div class="form-group item-ingredient-group">
                <label>Ingredient</label>

                ${
                    ingredientOptions.length
                        ? `
                            <select
                                class="edit-item-ingredient"
                                required
                            >
                                ${createIngredientOptions(ingredientId)}
                            </select>
                        `
                        : `
                            <input
                                type="hidden"
                                class="edit-item-ingredient"
                                value="${escapeHtml(ingredientId)}"
                            >

                            <input
                                type="text"
                                class="edit-item-ingredient-name"
                                value="${escapeHtml(ingredientName)}"
                                placeholder="Ingredient name"
                                readonly
                            >
                        `
                }
            </div>

            <div class="form-group">
                <label>Quantity</label>

                <input
                    type="number"
                    class="edit-item-quantity"
                    value="${quantity}"
                    min="0.01"
                    step="0.01"
                    required
                >
            </div>

            <div class="form-group">
                <label>Unit Cost</label>

                <input
                    type="number"
                    class="edit-item-unit-cost"
                    value="${unitCost}"
                    min="0"
                    step="0.01"
                    required
                >
            </div>

            <div class="form-group">
                <label>Total</label>

                <input
                    type="text"
                    class="edit-item-total"
                    value="${formatMoney(itemTotal)}"
                    readonly
                >
            </div>

            <div class="form-group item-action-group">
                <label>&nbsp;</label>

                <button
                    type="button"
                    class="remove-edit-purchase-item"
                    title="Remove item"
                >
                    Remove
                </button>
            </div>
        `;

        itemsContainer.appendChild(row);

        bindItemRowEvents(row);
        updateItemRowTotal(row);
        updateTotals();
    }

    function bindItemRowEvents(row) {
        const quantityInput =
            row.querySelector(".edit-item-quantity");

        const unitCostInput =
            row.querySelector(".edit-item-unit-cost");

        const removeButton =
            row.querySelector(".remove-edit-purchase-item");

        quantityInput?.addEventListener("input", () => {
            updateItemRowTotal(row);
            updateTotals();
        });

        unitCostInput?.addEventListener("input", () => {
            updateItemRowTotal(row);
            updateTotals();
        });

        removeButton?.addEventListener("click", () => {
            const totalRows = itemsContainer
                ? itemsContainer.querySelectorAll(
                    ".edit-purchase-item-row"
                ).length
                : 0;

            if (totalRows <= 1) {
                showMessage(
                    "Purchase must contain at least one ingredient.",
                    "error"
                );

                return;
            }

            row.remove();
            updateTotals();
        });
    }

    function updateItemRowTotal(row) {
        const quantity = toNumber(
            row.querySelector(".edit-item-quantity")?.value
        );

        const unitCost = toNumber(
            row.querySelector(".edit-item-unit-cost")?.value
        );

        const itemTotalInput =
            row.querySelector(".edit-item-total");

        const itemTotal = quantity * unitCost;

        if (itemTotalInput) {
            itemTotalInput.value = formatMoney(itemTotal);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL CALCULATION
    |--------------------------------------------------------------------------
    */

    function calculateSubtotal() {
        if (!itemsContainer) {
            return 0;
        }

        let subtotal = 0;

        itemsContainer
            .querySelectorAll(".edit-purchase-item-row")
            .forEach((row) => {
                const quantity = toNumber(
                    row.querySelector(
                        ".edit-item-quantity"
                    )?.value
                );

                const unitCost = toNumber(
                    row.querySelector(
                        ".edit-item-unit-cost"
                    )?.value
                );

                subtotal += quantity * unitCost;
            });

        return subtotal;
    }

    function updateTotals() {
        const subtotal = calculateSubtotal();
        const tax = toNumber(taxInput?.value);
        const discount = toNumber(discountInput?.value);
        const paidAmount = toNumber(paidAmountInput?.value);

        const total = Math.max(
            0,
            subtotal + tax - discount
        );

        const balance = Math.max(
            0,
            total - paidAmount
        );

        if (subtotalDisplay) {
            subtotalDisplay.textContent = formatMoney(subtotal);
        }

        if (totalDisplay) {
            totalDisplay.textContent = formatMoney(total);
        }

        if (balanceDisplay) {
            balanceDisplay.textContent = formatMoney(balance);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD PURCHASE DATA
    |--------------------------------------------------------------------------
    */

    async function loadPurchaseForEdit(purchaseId) {
        if (!purchaseId) {
            showMessage("Purchase ID is missing.", "error");
            return;
        }

        currentPurchaseId = purchaseId;

        try {
            const url =
                `${API.getOne}?id=${encodeURIComponent(purchaseId)}`;

            const response = await fetch(url, {
                method: "GET",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                cache: "no-store",
            });

            const result = await parseJsonResponse(response);

            if (result.success === false) {
                throw new Error(
                    result.message || "Unable to load purchase."
                );
            }

            const purchase =
                result.purchase ??
                result.data?.purchase ??
                result.data ??
                result;

            ingredientOptions =
                result.ingredients ??
                result.data?.ingredients ??
                ingredientOptions;

            fillEditForm(purchase);
            openEditModal();
        } catch (error) {
            console.error("Load purchase error:", error);

            showMessage(
                error.message || "Unable to load purchase.",
                "error"
            );
        }
    }

    function fillEditForm(purchase) {
        if (!purchase) {
            throw new Error("Purchase information was not found.");
        }

        const purchaseId =
            purchase.id ??
            purchase.purchase_id ??
            currentPurchaseId;

        currentPurchaseId = purchaseId;

        if (purchaseIdInput) {
            purchaseIdInput.value = purchaseId;
        }

        if (supplierInput) {
            supplierInput.value =
                purchase.supplier_id ?? "";
        }

        if (purchaseDateInput) {
            purchaseDateInput.value =
                purchase.purchase_date ??
                purchase.date ??
                "";
        }

        if (invoiceNumberInput) {
            invoiceNumberInput.value =
                purchase.invoice_number ??
                purchase.invoice_no ??
                "";
        }

        if (purchaseStatusInput) {
            purchaseStatusInput.value =
                purchase.purchase_status ??
                purchase.status ??
                "ORDERED";
        }

        if (paymentStatusInput) {
            paymentStatusInput.value =
                purchase.payment_status ??
                "UNPAID";
        }

        if (taxInput) {
            taxInput.value = toNumber(
                purchase.tax_amount ??
                purchase.tax ??
                0
            );
        }

        if (discountInput) {
            discountInput.value = toNumber(
                purchase.discount_amount ??
                purchase.discount ??
                0
            );
        }

        if (paidAmountInput) {
            paidAmountInput.value = toNumber(
                purchase.paid_amount ??
                purchase.amount_paid ??
                0
            );
        }

        if (noteInput) {
            noteInput.value =
                purchase.note ??
                purchase.notes ??
                "";
        }

        if (itemsContainer) {
            itemsContainer.innerHTML = "";
        }

        const items =
            purchase.items ??
            purchase.purchase_items ??
            purchase.details ??
            [];

        if (Array.isArray(items) && items.length > 0) {
            items.forEach((item) => {
                createItemRow(item);
            });
        } else {
            createItemRow();
        }

        updateTotals();
    }

    /*
    |--------------------------------------------------------------------------
    | FORM VALIDATION
    |--------------------------------------------------------------------------
    */

    function collectPurchaseItems() {
        if (!itemsContainer) {
            return [];
        }

        const items = [];

        itemsContainer
            .querySelectorAll(".edit-purchase-item-row")
            .forEach((row) => {
                const itemId =
                    row.querySelector(
                        ".edit-purchase-item-id"
                    )?.value || null;

                const ingredientId =
                    row.querySelector(
                        ".edit-item-ingredient"
                    )?.value || "";

                const quantity = toNumber(
                    row.querySelector(
                        ".edit-item-quantity"
                    )?.value
                );

                const unitCost = toNumber(
                    row.querySelector(
                        ".edit-item-unit-cost"
                    )?.value
                );

                items.push({
                    id: itemId || null,
                    ingredient_id: ingredientId,
                    quantity,
                    unit_cost: unitCost,
                    total: quantity * unitCost,
                });
            });

        return items;
    }

    function validatePurchaseData(data) {
        if (!data.purchase_id) {
            throw new Error("Purchase ID is missing.");
        }

        if (!data.supplier_id) {
            throw new Error("Please select a supplier.");
        }

        if (!data.purchase_date) {
            throw new Error("Please select the purchase date.");
        }

        if (!data.items.length) {
            throw new Error(
                "Please add at least one purchase item."
            );
        }

        data.items.forEach((item, index) => {
            const itemNumber = index + 1;

            if (!item.ingredient_id) {
                throw new Error(
                    `Please select ingredient for item ${itemNumber}.`
                );
            }

            if (item.quantity <= 0) {
                throw new Error(
                    `Quantity for item ${itemNumber} must be greater than zero.`
                );
            }

            if (item.unit_cost < 0) {
                throw new Error(
                    `Unit cost for item ${itemNumber} cannot be negative.`
                );
            }
        });

        if (data.tax_amount < 0) {
            throw new Error("Tax amount cannot be negative.");
        }

        if (data.discount_amount < 0) {
            throw new Error(
                "Discount amount cannot be negative."
            );
        }

        if (data.discount_amount > data.subtotal + data.tax_amount) {
            throw new Error(
                "Discount cannot be greater than the purchase amount."
            );
        }

        if (data.paid_amount < 0) {
            throw new Error(
                "Paid amount cannot be negative."
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PURCHASE
    |--------------------------------------------------------------------------
    */

    async function updatePurchase(event) {
        event.preventDefault();

        if (isSubmitting) {
            return;
        }

        const items = collectPurchaseItems();
        const subtotal = calculateSubtotal();
        const taxAmount = toNumber(taxInput?.value);
        const discountAmount =
            toNumber(discountInput?.value);

        const totalAmount = Math.max(
            0,
            subtotal + taxAmount - discountAmount
        );

        const payload = {
            purchase_id:
                purchaseIdInput?.value ||
                currentPurchaseId,

            supplier_id:
                supplierInput?.value || "",

            purchase_date:
                purchaseDateInput?.value || "",

            invoice_number:
                invoiceNumberInput?.value.trim() || "",

            purchase_status:
                purchaseStatusInput?.value || "ORDERED",

            payment_status:
                paymentStatusInput?.value || "UNPAID",

            subtotal,

            tax_amount: taxAmount,

            discount_amount: discountAmount,

            total_amount: totalAmount,

            paid_amount:
                toNumber(paidAmountInput?.value),

            note:
                noteInput?.value.trim() || "",

            items,
        };

        try {
            validatePurchaseData(payload);

            isSubmitting = true;
            setLoading(
                submitButton,
                true,
                "Updating..."
            );

            const response = await fetch(API.update, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                body: JSON.stringify(payload),
            });

            const result = await parseJsonResponse(response);

            if (result.success === false) {
                throw new Error(
                    result.message ||
                    "Unable to update purchase."
                );
            }

            showMessage(
                result.message ||
                "Purchase updated successfully.",
                "success"
            );

            closeEditModal();

            /*
            |--------------------------------------------------------------------------
            | Refresh Purchase List
            |--------------------------------------------------------------------------
            */

            if (typeof window.loadPurchases === "function") {
                await window.loadPurchases();
            } else if (
                typeof window.fetchPurchases === "function"
            ) {
                await window.fetchPurchases();
            } else {
                window.location.reload();
            }
        } catch (error) {
            console.error("Update purchase error:", error);

            showMessage(
                error.message ||
                "Unable to update purchase.",
                "error"
            );
        } finally {
            isSubmitting = false;
            setLoading(submitButton, false);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT LISTENERS
    |--------------------------------------------------------------------------
    */

    editForm?.addEventListener("submit", updatePurchase);

    addItemButton?.addEventListener("click", () => {
        createItemRow({
            quantity: 1,
            unit_cost: 0,
        });
    });

    taxInput?.addEventListener("input", updateTotals);
    discountInput?.addEventListener("input", updateTotals);
    paidAmountInput?.addEventListener("input", updateTotals);

    closeButtons.forEach((button) => {
        button.addEventListener("click", closeEditModal);
    });

    editModal?.addEventListener("click", (event) => {
        if (event.target === editModal) {
            closeEditModal();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (
            event.key === "Escape" &&
            editModal &&
            (
                editModal.classList.contains("show") ||
                editModal.classList.contains("active")
            )
        ) {
            closeEditModal();
        }
    });

    /*
    |--------------------------------------------------------------------------
    | EDIT BUTTON EVENT DELEGATION
    |--------------------------------------------------------------------------
    | Edit button example:
    |
    | <button
    |     type="button"
    |     class="edit-purchase-btn"
    |     data-purchase-id="1"
    | >
    |     Edit
    | </button>
    |--------------------------------------------------------------------------
    */

    document.addEventListener("click", (event) => {
        const editButton = event.target.closest(
            ".edit-purchase-btn, [data-edit-purchase]"
        );

        if (!editButton) {
            return;
        }

        const purchaseId =
            editButton.dataset.purchaseId ||
            editButton.getAttribute(
                "data-edit-purchase"
            );

        loadPurchaseForEdit(purchaseId);
    });

    /*
    |--------------------------------------------------------------------------
    | GLOBAL FUNCTIONS
    |--------------------------------------------------------------------------
    | purchases.js ကနေ ဒီ function ကိုလည်း ခေါ်နိုင်ပါတယ်။
    |
    | window.editPurchase(5);
    |--------------------------------------------------------------------------
    */

    window.editPurchase = loadPurchaseForEdit;
    window.openPurchaseEdit = loadPurchaseForEdit;
    window.closePurchaseEditModal = closeEditModal;
});