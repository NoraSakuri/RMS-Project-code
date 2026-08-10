<?php
require_once "../../back-end/middleware/auth.php";
allowRoles(["ADMIN", "MANAGER"]);

$pageTitle = "Category Management";
$roleName = $_SESSION["role"];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Category Management</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/categories.css">
</head>

<body>
<div class="wrapper">
    <?php include("../components/sidebar.php"); ?>

    <main class="main">
        <?php include("../components/topbar.php"); ?>

        <section class="content">

            <div class="page-header">
                <div>
                    <h2>Categories</h2>
                    <p>Manage menu categories for food and drinks.</p>
                </div>

                <button class="primary-btn" onclick="openCategoryModal()">
                    + Add Category
                </button>
            </div>

            <div class="category-list" id="categoryList"></div>

        </section>
    </main>
</div>

<div class="modal-overlay" id="categoryModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Add Category</h2>
            <button onclick="closeCategoryModal()">×</button>
        </div>

        <form id="categoryForm">
            <input type="hidden" id="categoryId" name="category_id">

            <div class="form-group">
                <label>Category Name</label>
                <input type="text" id="categoryName" name="category_name" required>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select id="categoryStatus" name="is_active">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>

            <button type="submit" class="primary-btn full">Save Category</button>
        </form>
    </div>
</div>

<script src="../assets/js/categories.js"></script>
</body>
</html>