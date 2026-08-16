<?php

require_once "../../back-end/middleware/auth.php";
require_once __DIR__ . "/../../../config/database.php";

allowRoles(["ADMIN", "MANAGER"]);

$pageTitle = "Inventory Management";
$roleName = $_SESSION["role"];

$inventoryStatement = $pdo->prepare("

SELECT

i.inventory_id,

i.item_name,

i.quantity,

i.minimum_stock,

i.supplier_name,

i.unit_type,

i.category_id,

c.category_name

FROM inventory_items i

LEFT JOIN inventory_categories c

ON c.inventory_category_id=i.category_id

ORDER BY i.item_name

");

$inventoryStatement->execute();
$inventoryItems = $inventoryStatement->fetchAll(PDO::FETCH_ASSOC);

$categoryStatement = $pdo->prepare("
    SELECT
        inventory_category_id,
        category_name
    FROM inventory_categories
    WHERE is_active = 1
    ORDER BY category_name ASC
");

$categoryStatement->execute();
$inventoryCategories = $categoryStatement->fetchAll(
    PDO::FETCH_ASSOC
);

$totalItems = count($inventoryItems);
$lowStock = 0;
$outOfStock = 0;
$supplierNames = [];

foreach ($inventoryItems as $item) {
    $quantity = (float) $item["quantity"];
    $minimum = (float) $item["minimum_stock"];

    if ($quantity <= 0) {
        $outOfStock++;
    } elseif ($quantity <= $minimum) {
        $lowStock++;
    }

    if (!empty($item["supplier_name"])) {
        $supplierNames[] = $item["supplier_name"];
    }
}

$totalSuppliers = count(array_unique($supplierNames));
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Inventory Management</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/inventory.css">

</head>

<body>

    <div class="wrapper">

        <?php include("../components/sidebar.php"); ?>

        <main class="main">

            <?php include("../components/topbar.php"); ?>

            <section class="content">

                <!-- Header -->

                <div class="inventory-header">

                    <div>

                        <h2>Inventory Management</h2>

                        <p>Manage restaurant ingredients and stock levels.</p>

                    </div>

                    <button
                        type="button"
                        class="add-btn"
                        id="addInventoryButton">

                        + Add Inventory

                    </button>

                    <div
                        class="modal"
                        id="inventoryModal">

                        <div class="modal-content">

                            <h2 id="inventoryModalTitle">
                                Add Inventory
                            </h2>

                            <form id="inventoryForm">

                                <input
                                    type="hidden"
                                    id="inventoryId">

                                <label>

                                    Item Name

                                </label>

                                <input
                                    type="text"
                                    id="itemName"
                                    required>

                                <label>

                                    Category

                                </label>

                                <select
                                    id="category"
                                    required>

                                    <?php foreach ($inventoryCategories as $category): ?>

                                        <option
                                            value="<?= $category["inventory_category_id"] ?>">

                                            <?= htmlspecialchars($category["category_name"]) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <label>

                                    Quantity

                                </label>

                                <input
                                    type="number"
                                    id="quantity"
                                    min="0"
                                    step="0.01"
                                    required>

                                <label>

                                    Unit

                                </label>

                                <select id="unit">

                                    <option value="GRAM">GRAM</option>

                                    <option value="KILOGRAM">KILOGRAM</option>

                                    <option value="MILLILITER">MILLILITER</option>

                                    <option value="LITER">LITER</option>

                                    <option value="PIECE">PIECE</option>

                                </select>

                                <label>

                                    Minimum Stock

                                </label>

                                <input
                                    type="number"
                                    id="minimumStock"
                                    min="0"
                                    step="0.01"
                                    required>

                                <label>

                                    Supplier

                                </label>

                                <input
                                    type="text"
                                    id="supplier"
                                    required>

                                <div class="modal-buttons">

                                    <button
                                        type="submit"
                                        class="save-btn">

                                        Save

                                    </button>

                                    <button
                                        type="button"
                                        id="closeModal">

                                        Cancel

                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

                <!-- Statistics -->

                <div class="inventory-stats">

                    <div class="inventory-card">

                        <h4>Total Items</h4>

                        <h2><?= $totalItems ?></h2>

                    </div>

                    <div class="inventory-card warning">

                        <h4>Low Stock</h4>

                        <h2><?= $lowStock ?></h2>

                    </div>

                    <div class="inventory-card danger">

                        <h4>Out of Stock</h4>

                        <h2><?= $outOfStock ?></h2>

                    </div>

                    <div class="inventory-card success">

                        <h4>Suppliers</h4>

                        <h2><?= $totalSuppliers ?></h2>

                    </div>

                </div>

                <!-- Toolbar -->

                <div class="toolbar">

                    <input
                        type="text"
                        placeholder="Search inventory item..."
                        id="searchInventory">

                    <select id="categoryFilter">

                        <option value="all">
                            All Categories
                        </option>

                        <?php foreach ($inventoryCategories as $category): ?>

                            <option value="<?= htmlspecialchars(
                                                $category["category_name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ); ?>">
                                <?= htmlspecialchars(
                                    $category["category_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <select id="statusFilter">

                        <option>All Status</option>
                        <option>Available</option>
                        <option>Low Stock</option>
                        <option>Out of Stock</option>

                    </select>

                </div>

                <!-- Inventory Table -->

                <div class="inventory-table">

                    <table>

                        <thead>

                            <tr>

                                <th>Item</th>

                                <th>Category</th>

                                <th>Quantity</th>

                                <th>Unit</th>

                                <th>Minimum</th>

                                <th>Supplier</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (empty($inventoryItems)): ?>

                                <tr>
                                    <td colspan="8">
                                        No inventory items found.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($inventoryItems as $item): ?>

                                    <?php

                                    $status = "Available";
                                    $class = "available";
                                    $rowClass = "";

                                    if ($item["quantity"] <= 0) {

                                        $status = "Out of Stock";
                                        $class = "danger";
                                        $rowClass = "out-stock";
                                    } elseif ($item["quantity"] <= $item["minimum_stock"]) {

                                        $status = "Low Stock";
                                        $class = "warning";
                                        $rowClass = "low-stock";
                                    }

                                    ?>

                                    <tr class="<?= $rowClass ?>">

                                        <td><?= htmlspecialchars($item["item_name"]) ?></td>

                                        <td>

                                            <?= htmlspecialchars(
                                                $item["category_name"] ?? "Uncategorized"
                                            ) ?>

                                        </td>

                                        <td><?= $item["quantity"] ?></td>

                                        <td><?= $item["unit_type"] ?></td>

                                        <td><?= $item["minimum_stock"] ?></td>

                                        <td><?= htmlspecialchars($item["supplier_name"]) ?></td>

                                        <td>
                                            <span class="badge <?= $class ?>">
                                                <?= $status ?>
                                            </span>
                                        </td>

                                        <td>

                                            <button
                                                type="button"
                                                class="edit"
                                                data-id="<?= (int) $item["inventory_id"]; ?>"
                                                data-name="<?= htmlspecialchars(
                                                                $item["item_name"],
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ); ?>"
                                                data-category="<?= (int) $item["category_id"]; ?>"
                                                data-quantity="<?= (float) $item["quantity"]; ?>"
                                                data-unit="<?= htmlspecialchars(
                                                                $item["unit_type"],
                                                                ENT_QUOTES,
                                                                "UTF-8"
                                                            ); ?>"
                                                data-minimum="<?= (float) $item["minimum_stock"]; ?>"
                                                data-supplier="<?= htmlspecialchars(
                                                                    $item["supplier_name"] ?? "",
                                                                    ENT_QUOTES,
                                                                    "UTF-8"
                                                                ); ?>">
                                                Edit
                                            </button>

                                            <button
                                                class="delete"
                                                data-id="<?= $item["inventory_id"] ?>">
                                                Delete
                                            </button>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

    <script src="../assets/js/inventory.js"></script>

</body>

</html>