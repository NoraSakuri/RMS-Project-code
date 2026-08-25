<?php

require_once "../../back-end/middleware/auth.php";
require_once "../../../config/database.php";

allowRoles(["ADMIN", "MANAGER"]);

$pageTitle = "Menu Management";
$roleName = $_SESSION["role"];

$categoryStatement = $pdo->prepare("
    SELECT
        category_id,
        category_name
    FROM categories
    WHERE is_active = 1
    ORDER BY category_name ASC
");

$categoryStatement->execute();

$categories = $categoryStatement->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Menu Management</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
</head>

<body>
    <div class="wrapper">
        <?php include("../components/sidebar.php"); ?>

        <main class="main">
            <?php include("../components/topbar.php"); ?>

            <section class="content">
                <div class="page-header">
                    <div>
                        <h2>Menu Items</h2>
                        <p>Manage food and drink items for the restaurant.</p>
                    </div>
                    <button class="primary-btn" onclick="openMenuModal()">+ Add Menu Item</button>
                </div>

                <div class="toolbar">
                    <input type="text" id="searchMenu" placeholder="Search menu item...">

                    <select id="categoryFilter">

                        <option value="all">
                            All Categories
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option value="<?= (int) $category['category_id']; ?>">
                                <?= htmlspecialchars(
                                    $category['category_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="menu-grid" id="menuGrid"></div>
            </section>
        </main>
    </div>

    <div class="modal-overlay" id="menuModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Add Menu Item</h2>
                <button onclick="closeMenuModal()">×</button>
            </div>

            <form id="menuForm">

                <input type="hidden" id="itemId" name="item_id">

                <div class="form-row">
                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" id="itemName" name="name" required>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select
                            id="itemCategory"
                            name="category_id"
                            required>
                            <option value="">
                                Select Category
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option value="<?= (int) $category['category_id']; ?>">
                                    <?= htmlspecialchars(
                                        $category['category_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="itemDescription" name="description" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price</label>
                        <input type="number" id="itemPrice" name="price" required>
                    </div>

                    <div class="form-group">
                        <label>Availability</label>
                        <select id="itemAvailability" name="availability">
                            <option value="1">Available</option>
                            <option value="0">Unavailable</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="primary-btn full">Save Item</button>

            </form>
        </div>
    </div>

    <div class="modal-overlay" id="recipeModal">

    <div class="modal">

        <div class="modal-header">
            <h2>
                Setup Recipe
            </h2>

            <button onclick="closeRecipe()">
                ×
            </button>
        </div>


        <input type="hidden" id="recipeItemId">


        <div id="recipeRows">

        </div>


        <button
            type="button"
            onclick="addRecipeRow()"
            class="primary-btn"
        >
            + Add Ingredient
        </button>


        <button
            type="button"
            onclick="saveRecipe()"
            class="primary-btn full"
        >
            Save Recipe
        </button>

    </div>

</div>

    <script src="../assets/js/menu.js"></script>
</body>

</html>