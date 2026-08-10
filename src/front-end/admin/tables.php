<?php

require_once "../../back-end/middleware/auth.php";

allowRoles([
    "ADMIN",
    "MANAGER",
    "WAITER"
]);

$pageTitle = "Table Management";

$roleName = strtoupper(
    $_SESSION["role"] ?? ""
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Table Management</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/tables.css"
    >
</head>

<body>

<div class="wrapper">

    <?php include "../components/sidebar.php"; ?>

    <main class="main">

        <?php include "../components/topbar.php"; ?>

        <div class="content table-management-content">

            <!-- =====================================
                 PAGE HEADER
            ====================================== -->

            <div class="table-page-header">

                <div>
                    <h2>Restaurant Tables</h2>

                    <p>
                        Manage table numbers, seating capacity
                        and availability.
                    </p>
                </div>

                <?php if (
                    in_array(
                        $roleName,
                        ["ADMIN", "MANAGER"],
                        true
                    )
                ): ?>

                    <button
                        type="button"
                        class="table-primary-btn"
                        id="addTableButton"
                    >
                        + Add Table
                    </button>

                <?php endif; ?>

            </div>

            <!-- =====================================
                 STATISTICS
            ====================================== -->

            <div class="table-stats-grid">

                <div class="table-stat-card total">

                    <span>Total Tables</span>

                    <strong id="totalTableCount">
                        0
                    </strong>

                </div>

                <div class="table-stat-card available">

                    <span>Available</span>

                    <strong id="availableTableCount">
                        0
                    </strong>

                </div>

                <div class="table-stat-card occupied">

                    <span>Occupied</span>

                    <strong id="occupiedTableCount">
                        0
                    </strong>

                </div>

                <div class="table-stat-card reserved">

                    <span>Reserved</span>

                    <strong id="reservedTableCount">
                        0
                    </strong>

                </div>

                <div class="table-stat-card cleaning">

                    <span>Cleaning</span>

                    <strong id="cleaningTableCount">
                        0
                    </strong>

                </div>

                <div class="table-stat-card capacity">

                    <span>Total Capacity</span>

                    <strong id="totalCapacityCount">
                        0
                    </strong>

                </div>

            </div>

            <!-- =====================================
                 TOOLBAR
            ====================================== -->

            <div class="table-toolbar">

                <div class="table-search-box">

                    <input
                        type="text"
                        id="tableSearchInput"
                        placeholder="Search table number..."
                        autocomplete="off"
                    >

                </div>

                <div class="table-filter-group">

                    <button
                        type="button"
                        class="table-filter-btn active"
                        data-status="ALL"
                    >
                        All
                    </button>

                    <button
                        type="button"
                        class="table-filter-btn"
                        data-status="AVAILABLE"
                    >
                        Available
                    </button>

                    <button
                        type="button"
                        class="table-filter-btn"
                        data-status="OCCUPIED"
                    >
                        Occupied
                    </button>

                    <button
                        type="button"
                        class="table-filter-btn"
                        data-status="RESERVED"
                    >
                        Reserved
                    </button>

                    <button
                        type="button"
                        class="table-filter-btn"
                        data-status="CLEANING"
                    >
                        Cleaning
                    </button>

                </div>

                <button
                    type="button"
                    class="table-refresh-btn"
                    id="refreshTablesButton"
                >
                    Refresh
                </button>

            </div>

            <!-- =====================================
                 TABLE FLOOR
            ====================================== -->

            <div
                class="table-floor-layout"
                id="tableFloorLayout"
            >

                <div class="table-loading-state">

                    Loading restaurant tables...

                </div>

            </div>

        </div>

    </main>

</div>

<!-- =========================================
     ADD / EDIT TABLE MODAL
========================================= -->

<div
    class="table-modal"
    id="tableFormModal"
    aria-hidden="true"
>

    <div class="table-modal-content">

        <div class="table-modal-header">

            <div>
                <h2 id="tableModalTitle">
                    Add Table
                </h2>

                <p id="tableModalDescription">
                    Create a new restaurant table.
                </p>
            </div>

            <button
                type="button"
                class="table-modal-close"
                id="closeTableModalButton"
                aria-label="Close"
            >
                &times;
            </button>

        </div>

        <form id="tableForm">

            <input
                type="hidden"
                id="tableId"
                name="table_id"
            >

            <div class="table-form-body">

                <div class="table-form-group">

                    <label for="tableNumber">
                        Table Number
                    </label>

                    <input
                        type="number"
                        id="tableNumber"
                        name="table_number"
                        min="1"
                        step="1"
                        placeholder="Example: 7"
                        required
                    >

                    <small>
                        Enter numbers only.
                    </small>

                </div>

                <div class="table-form-group">

                    <label for="tableCapacity">
                        Seating Capacity
                    </label>

                    <input
                        type="number"
                        id="tableCapacity"
                        name="capacity"
                        min="1"
                        max="50"
                        step="1"
                        placeholder="Example: 4"
                        required
                    >

                </div>

                <div class="table-form-group">

                    <label for="tableStatus">
                        Table Status
                    </label>

                    <select
                        id="tableStatus"
                        name="table_status"
                        required
                    >

                        <option value="AVAILABLE">
                            Available
                        </option>

                        <option value="OCCUPIED">
                            Occupied
                        </option>

                        <option value="RESERVED">
                            Reserved
                        </option>

                        <option value="CLEANING">
                            Cleaning
                        </option>

                    </select>

                </div>

            </div>

            <div class="table-modal-actions">

                <button
                    type="button"
                    class="table-secondary-btn"
                    id="cancelTableButton"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="table-primary-btn"
                    id="saveTableButton"
                >
                    Save Table
                </button>

            </div>

        </form>

    </div>

</div>

<!-- =========================================
     STATUS MODAL
========================================= -->

<div
    class="table-modal"
    id="tableStatusModal"
    aria-hidden="true"
>

    <div class="table-modal-content table-status-modal-content">

        <div class="table-modal-header">

            <div>
                <h2>Change Table Status</h2>

                <p id="statusModalTableName">
                    Select the new table status.
                </p>
            </div>

            <button
                type="button"
                class="table-modal-close"
                id="closeStatusModalButton"
                aria-label="Close"
            >
                &times;
            </button>

        </div>

        <form id="tableStatusForm">

            <input
                type="hidden"
                id="statusTableId"
            >

            <div class="table-form-body">

                <div class="table-form-group">

                    <label for="newTableStatus">
                        New Status
                    </label>

                    <select
                        id="newTableStatus"
                        required
                    >

                        <option value="AVAILABLE">
                            Available
                        </option>

                        <option value="OCCUPIED">
                            Occupied
                        </option>

                        <option value="RESERVED">
                            Reserved
                        </option>

                        <option value="CLEANING">
                            Cleaning
                        </option>

                    </select>

                </div>

            </div>

            <div class="table-modal-actions">

                <button
                    type="button"
                    class="table-secondary-btn"
                    id="cancelStatusButton"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="table-primary-btn"
                >
                    Update Status
                </button>

            </div>

        </form>

    </div>

</div>

<script>
    window.TABLE_USER_ROLE = <?= json_encode(
        $roleName,
        JSON_UNESCAPED_UNICODE
    ) ?>;
</script>

<script src="../assets/js/tables.js"></script>

</body>

</html>