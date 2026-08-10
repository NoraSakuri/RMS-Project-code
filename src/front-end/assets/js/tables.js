"use strict";

/* =========================================
   STATE
========================================= */

let tables = [];
let activeStatusFilter = "ALL";
let editingTableId = null;

const userRole = String(
    window.TABLE_USER_ROLE || ""
).toUpperCase();

const canManageTables = [
    "ADMIN",
    "MANAGER"
].includes(userRole);

const canChangeStatus = [
    "ADMIN",
    "MANAGER",
    "WAITER"
].includes(userRole);

/* =========================================
   ELEMENTS
========================================= */

const tableFloorLayout =
    document.getElementById("tableFloorLayout");

const tableSearchInput =
    document.getElementById("tableSearchInput");

const refreshTablesButton =
    document.getElementById("refreshTablesButton");

const addTableButton =
    document.getElementById("addTableButton");

const filterButtons =
    document.querySelectorAll(".table-filter-btn");

const totalTableCount =
    document.getElementById("totalTableCount");

const availableTableCount =
    document.getElementById("availableTableCount");

const occupiedTableCount =
    document.getElementById("occupiedTableCount");

const reservedTableCount =
    document.getElementById("reservedTableCount");

const cleaningTableCount =
    document.getElementById("cleaningTableCount");

const totalCapacityCount =
    document.getElementById("totalCapacityCount");

/* =========================================
   FORM MODAL ELEMENTS
========================================= */

const tableFormModal =
    document.getElementById("tableFormModal");

const tableForm =
    document.getElementById("tableForm");

const tableModalTitle =
    document.getElementById("tableModalTitle");

const tableModalDescription =
    document.getElementById("tableModalDescription");

const tableIdInput =
    document.getElementById("tableId");

const tableNumberInput =
    document.getElementById("tableNumber");

const tableCapacityInput =
    document.getElementById("tableCapacity");

const tableStatusInput =
    document.getElementById("tableStatus");

const closeTableModalButton =
    document.getElementById("closeTableModalButton");

const cancelTableButton =
    document.getElementById("cancelTableButton");

const saveTableButton =
    document.getElementById("saveTableButton");

/* =========================================
   STATUS MODAL ELEMENTS
========================================= */

const tableStatusModal =
    document.getElementById("tableStatusModal");

const tableStatusForm =
    document.getElementById("tableStatusForm");

const statusTableIdInput =
    document.getElementById("statusTableId");

const statusModalTableName =
    document.getElementById("statusModalTableName");

const newTableStatusInput =
    document.getElementById("newTableStatus");

const closeStatusModalButton =
    document.getElementById("closeStatusModalButton");

const cancelStatusButton =
    document.getElementById("cancelStatusButton");

/* =========================================
   HELPERS
========================================= */

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function normalizeStatus(status) {
    return String(status || "AVAILABLE")
        .trim()
        .toUpperCase();
}

function getStatusLabel(status) {
    const normalized = normalizeStatus(status);

    const labels = {
        AVAILABLE: "Available",
        OCCUPIED: "Occupied",
        RESERVED: "Reserved",
        CLEANING: "Cleaning"
    };

    return labels[normalized] || normalized;
}

function setLoadingState(message) {
    tableFloorLayout.innerHTML = `
        <div class="table-loading-state">
            ${escapeHtml(message)}
        </div>
    `;
}

function setButtonLoading(
    button,
    isLoading,
    loadingText,
    normalText
) {
    if (!button) {
        return;
    }

    button.disabled = isLoading;
    button.textContent = isLoading
        ? loadingText
        : normalText;
}

/* =========================================
   LOAD TABLES
========================================= */

async function loadTables() {
    try {
        setLoadingState(
            "Loading restaurant tables..."
        );

        if (refreshTablesButton) {
            setButtonLoading(
                refreshTablesButton,
                true,
                "Loading...",
                "Refresh"
            );
        }

        const response = await fetch(
            "../../back-end/api/tables/read.php",
            {
                method: "GET",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json"
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
                "Raw table response:",
                responseText
            );

            throw new Error(
                "Server returned an invalid response."
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || "Unable to load tables."
            );
        }

        tables = Array.isArray(
            result.data?.tables
        )
            ? result.data.tables
            : [];

        updateStatistics(
            result.data?.statistics || {}
        );

        renderTables();

    } catch (error) {
        console.error(error);

        setLoadingState(
            error.message
            || "Unable to load restaurant tables."
        );

        alert(
            error.message
            || "Unable to load restaurant tables."
        );
    } finally {
        if (refreshTablesButton) {
            setButtonLoading(
                refreshTablesButton,
                false,
                "Loading...",
                "Refresh"
            );
        }
    }
}

/* =========================================
   STATISTICS
========================================= */

function updateStatistics(statistics) {
    totalTableCount.textContent =
        Number(statistics.total_tables || 0);

    availableTableCount.textContent =
        Number(statistics.available_count || 0);

    occupiedTableCount.textContent =
        Number(statistics.occupied_count || 0);

    reservedTableCount.textContent =
        Number(statistics.reserved_count || 0);

    cleaningTableCount.textContent =
        Number(statistics.cleaning_count || 0);

    totalCapacityCount.textContent =
        Number(statistics.total_capacity || 0);
}

/* =========================================
   FILTER TABLES
========================================= */

function getFilteredTables() {
    const searchText = String(
        tableSearchInput?.value || ""
    )
        .trim()
        .toLowerCase();

    return tables.filter(table => {
        const tableNumber =
            String(table.table_number || "");

        const status =
            normalizeStatus(table.table_status);

        const matchesSearch =
            tableNumber.includes(searchText)
            || `table ${tableNumber}`
                .includes(searchText);

        const matchesStatus =
            activeStatusFilter === "ALL"
            || status === activeStatusFilter;

        return matchesSearch && matchesStatus;
    });
}

/* =========================================
   RENDER TABLE CARDS
========================================= */

function renderTables() {
    const filteredTables =
        getFilteredTables();

    if (filteredTables.length === 0) {
        tableFloorLayout.innerHTML = `
            <div class="table-empty-state">
                <div class="table-empty-icon">
                    🍽️
                </div>

                <h3>No tables found</h3>

                <p>
                    No restaurant table matches
                    your search or selected status.
                </p>
            </div>
        `;

        return;
    }

    tableFloorLayout.innerHTML =
        filteredTables
            .map(table => {
                const tableId =
                    Number(table.table_id);

                const tableNumber =
                    Number(table.table_number);

                const capacity =
                    Number(table.capacity);

                const status =
                    normalizeStatus(
                        table.table_status
                    );

                const editButton = canManageTables
                    ? `
                        <button
                            type="button"
                            class="table-card-action edit"
                            data-action="edit"
                            data-table-id="${tableId}"
                        >
                            Edit
                        </button>
                    `
                    : "";

                const deleteButton = canManageTables
                    ? `
                        <button
                            type="button"
                            class="table-card-action delete"
                            data-action="delete"
                            data-table-id="${tableId}"
                        >
                            Delete
                        </button>
                    `
                    : "";

                const statusButton = canChangeStatus
                    ? `
                        <button
                            type="button"
                            class="table-card-action status"
                            data-action="status"
                            data-table-id="${tableId}"
                        >
                            Change Status
                        </button>
                    `
                    : "";

                return `
                    <article
                        class="restaurant-table-card ${status.toLowerCase()}"
                        data-table-id="${tableId}"
                    >
                        <div class="table-card-top">

                            <div class="table-card-icon">
                                🍽️
                            </div>

                            <span
                                class="table-status-badge ${status.toLowerCase()}"
                            >
                                ${escapeHtml(
                                    getStatusLabel(status)
                                )}
                            </span>

                        </div>

                        <div class="table-card-body">

                            <h3>
                                Table ${tableNumber}
                            </h3>

                            <p>
                                ${capacity}
                                ${capacity === 1
                                    ? "Seat"
                                    : "Seats"}
                            </p>

                        </div>

                        <div class="table-card-actions">
                            ${statusButton}
                            ${editButton}
                            ${deleteButton}
                        </div>

                    </article>
                `;
            })
            .join("");
}

/* =========================================
   OPEN ADD MODAL
========================================= */

function openAddTableModal() {
    if (!canManageTables) {
        return;
    }

    editingTableId = null;

    tableForm.reset();

    tableIdInput.value = "";

    tableModalTitle.textContent =
        "Add Table";

    tableModalDescription.textContent =
        "Create a new restaurant table.";

    saveTableButton.textContent =
        "Save Table";

    tableStatusInput.value =
        "AVAILABLE";

    openModal(tableFormModal);

    window.setTimeout(() => {
        tableNumberInput.focus();
    }, 100);
}

/* =========================================
   OPEN EDIT MODAL
========================================= */

function openEditTableModal(tableId) {
    if (!canManageTables) {
        return;
    }

    const table = tables.find(item => {
        return Number(item.table_id)
            === Number(tableId);
    });

    if (!table) {
        alert("Table was not found.");
        return;
    }

    editingTableId =
        Number(table.table_id);

    tableIdInput.value =
        String(table.table_id);

    tableNumberInput.value =
        String(table.table_number);

    tableCapacityInput.value =
        String(table.capacity);

    tableStatusInput.value =
        normalizeStatus(table.table_status);

    tableModalTitle.textContent =
        `Edit Table ${table.table_number}`;

    tableModalDescription.textContent =
        "Update table number, capacity and status.";

    saveTableButton.textContent =
        "Update Table";

    openModal(tableFormModal);
}

/* =========================================
   OPEN STATUS MODAL
========================================= */

function openStatusModal(tableId) {
    if (!canChangeStatus) {
        return;
    }

    const table = tables.find(item => {
        return Number(item.table_id)
            === Number(tableId);
    });

    if (!table) {
        alert("Table was not found.");
        return;
    }

    statusTableIdInput.value =
        String(table.table_id);

    statusModalTableName.textContent =
        `Table ${table.table_number} is currently ${getStatusLabel(
            table.table_status
        )}.`;

    newTableStatusInput.value =
        normalizeStatus(table.table_status);

    openModal(tableStatusModal);
}

/* =========================================
   MODAL HELPERS
========================================= */

function openModal(modal) {
    if (!modal) {
        return;
    }

    modal.style.display = "flex";

    modal.setAttribute(
        "aria-hidden",
        "false"
    );

    document.body.style.overflow =
        "hidden";
}

function closeModal(modal) {
    if (!modal) {
        return;
    }

    modal.style.display = "none";

    modal.setAttribute(
        "aria-hidden",
        "true"
    );

    if (
        tableFormModal.style.display !== "flex"
        && tableStatusModal.style.display !== "flex"
    ) {
        document.body.style.overflow = "";
    }
}

/* =========================================
   SAVE TABLE
========================================= */

async function saveTable(event) {
    event.preventDefault();

    if (!canManageTables) {
        return;
    }

    const tableNumber =
        Number(tableNumberInput.value);

    const capacity =
        Number(tableCapacityInput.value);

    const tableStatus =
        normalizeStatus(
            tableStatusInput.value
        );

    if (
        !Number.isInteger(tableNumber)
        || tableNumber < 1
    ) {
        alert(
            "Table number must be a positive whole number."
        );

        tableNumberInput.focus();
        return;
    }

    if (
        !Number.isInteger(capacity)
        || capacity < 1
        || capacity > 50
    ) {
        alert(
            "Capacity must be between 1 and 50."
        );

        tableCapacityInput.focus();
        return;
    }

    const isEditing =
        Number.isInteger(editingTableId)
        && editingTableId > 0;

    const endpoint = isEditing
        ? "../../back-end/api/tables/update.php"
        : "../../back-end/api/tables/create.php";

    const payload = {
        table_number: tableNumber,
        capacity,
        table_status: tableStatus
    };

    if (isEditing) {
        payload.table_id =
            editingTableId;
    }

    try {
        setButtonLoading(
            saveTableButton,
            true,
            isEditing
                ? "Updating..."
                : "Saving...",
            isEditing
                ? "Update Table"
                : "Save Table"
        );

        const response = await fetch(
            endpoint,
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type":
                        "application/json",
                    Accept:
                        "application/json"
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
                "Raw table save response:",
                responseText
            );

            throw new Error(
                "Server returned an invalid response."
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || "Unable to save table."
            );
        }

        alert(result.message);

        closeModal(tableFormModal);

        await loadTables();

    } catch (error) {
        console.error(error);

        alert(
            error.message
            || "Unable to save table."
        );
    } finally {
        setButtonLoading(
            saveTableButton,
            false,
            "Saving...",
            editingTableId
                ? "Update Table"
                : "Save Table"
        );
    }
}

/* =========================================
   UPDATE TABLE STATUS
========================================= */

async function updateTableStatus(event) {
    event.preventDefault();

    if (!canChangeStatus) {
        return;
    }

    const tableId =
        Number(statusTableIdInput.value);

    const tableStatus =
        normalizeStatus(
            newTableStatusInput.value
        );

    if (
        !Number.isInteger(tableId)
        || tableId < 1
    ) {
        alert("Invalid table ID.");
        return;
    }

    try {
        const submitButton =
            tableStatusForm.querySelector(
                'button[type="submit"]'
            );

        setButtonLoading(
            submitButton,
            true,
            "Updating...",
            "Update Status"
        );

        const response = await fetch(
            "../../back-end/api/tables/status.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type":
                        "application/json",
                    Accept:
                        "application/json"
                },
                body: JSON.stringify({
                    table_id: tableId,
                    table_status: tableStatus
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
                "Raw table status response:",
                responseText
            );

            throw new Error(
                "Server returned an invalid response."
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || "Unable to update table status."
            );
        }

        alert(result.message);

        closeModal(tableStatusModal);

        await loadTables();

    } catch (error) {
        console.error(error);

        alert(
            error.message
            || "Unable to update table status."
        );
    } finally {
        const submitButton =
            tableStatusForm.querySelector(
                'button[type="submit"]'
            );

        setButtonLoading(
            submitButton,
            false,
            "Updating...",
            "Update Status"
        );
    }
}

/* =========================================
   DELETE TABLE
========================================= */

async function deleteTable(tableId) {
    if (!canManageTables) {
        return;
    }

    const table = tables.find(item => {
        return Number(item.table_id)
            === Number(tableId);
    });

    if (!table) {
        alert("Table was not found.");
        return;
    }

    const confirmed = confirm(
        `Delete Table ${table.table_number}?\n\n`
        + "This action cannot be undone."
    );

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch(
            "../../back-end/api/tables/delete.php",
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type":
                        "application/json",
                    Accept:
                        "application/json"
                },
                body: JSON.stringify({
                    table_id:
                        Number(tableId)
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
                "Raw table delete response:",
                responseText
            );

            throw new Error(
                "Server returned an invalid response."
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message
                || "Unable to delete table."
            );
        }

        alert(result.message);

        await loadTables();

    } catch (error) {
        console.error(error);

        alert(
            error.message
            || "Unable to delete table."
        );
    }
}

/* =========================================
   EVENT LISTENERS
========================================= */

if (addTableButton) {
    addTableButton.addEventListener(
        "click",
        openAddTableModal
    );
}

if (refreshTablesButton) {
    refreshTablesButton.addEventListener(
        "click",
        loadTables
    );
}

if (tableSearchInput) {
    tableSearchInput.addEventListener(
        "input",
        renderTables
    );
}

filterButtons.forEach(button => {
    button.addEventListener(
        "click",
        () => {
            filterButtons.forEach(item => {
                item.classList.remove("active");
            });

            button.classList.add("active");

            activeStatusFilter =
                normalizeStatus(
                    button.dataset.status
                );

            if (
                button.dataset.status === "ALL"
            ) {
                activeStatusFilter = "ALL";
            }

            renderTables();
        }
    );
});

if (tableFloorLayout) {
    tableFloorLayout.addEventListener(
        "click",
        event => {
            const actionButton =
                event.target.closest(
                    "[data-action]"
                );

            if (!actionButton) {
                return;
            }

            const tableId =
                Number(
                    actionButton.dataset.tableId
                );

            const action =
                actionButton.dataset.action;

            if (action === "edit") {
                openEditTableModal(tableId);
            }

            if (action === "status") {
                openStatusModal(tableId);
            }

            if (action === "delete") {
                deleteTable(tableId);
            }
        }
    );
}

if (tableForm) {
    tableForm.addEventListener(
        "submit",
        saveTable
    );
}

if (tableStatusForm) {
    tableStatusForm.addEventListener(
        "submit",
        updateTableStatus
    );
}

if (closeTableModalButton) {
    closeTableModalButton.addEventListener(
        "click",
        () => closeModal(tableFormModal)
    );
}

if (cancelTableButton) {
    cancelTableButton.addEventListener(
        "click",
        () => closeModal(tableFormModal)
    );
}

if (closeStatusModalButton) {
    closeStatusModalButton.addEventListener(
        "click",
        () => closeModal(tableStatusModal)
    );
}

if (cancelStatusButton) {
    cancelStatusButton.addEventListener(
        "click",
        () => closeModal(tableStatusModal)
    );
}

[tableFormModal, tableStatusModal]
    .forEach(modal => {
        if (!modal) {
            return;
        }

        modal.addEventListener(
            "click",
            event => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            }
        );
    });

document.addEventListener(
    "keydown",
    event => {
        if (event.key !== "Escape") {
            return;
        }

        closeModal(tableFormModal);
        closeModal(tableStatusModal);
    }
);

/* =========================================
   INITIAL LOAD
========================================= */

document.addEventListener(
    "DOMContentLoaded",
    loadTables
);