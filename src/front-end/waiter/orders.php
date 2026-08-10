<?php

require_once "../../back-end/middleware/auth.php";
require_once "../../../config/database.php";

allowRoles(["WAITER", "ADMIN", "MANAGER"]);


/*
|--------------------------------------------------------------------------
| Get Tables
|--------------------------------------------------------------------------
*/

$tableStatement = $pdo->prepare("
    SELECT
        table_id,
        table_number,
        capacity,
        table_status
    FROM rms_tables
    WHERE table_status IN ('AVAILABLE','RESERVED')
    ORDER BY table_number ASC
");

$tableStatement->execute();

$tables = $tableStatement->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Get Menu Items
|--------------------------------------------------------------------------
*/

$menuStatement = $pdo->prepare("
    SELECT
        m.item_id,
        m.name,
        m.description,
        m.price,
        m.category_id,
        c.category_name
    FROM menu_items m
    INNER JOIN categories c
        ON c.category_id = m.category_id
    WHERE m.availability = 1
      AND c.is_active = 1
    ORDER BY c.category_name ASC, m.name ASC
");

$menuStatement->execute();

$menuItems = $menuStatement->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Get Recent Orders
|--------------------------------------------------------------------------
*/

$recentOrderStatement = $pdo->prepare("
    SELECT
        o.order_id,
        o.status,
        o.chef_action,
        o.order_date,
        t.table_number
    FROM orders o
    INNER JOIN rms_tables t
        ON t.table_id = o.table_id
    ORDER BY o.order_id DESC
    LIMIT 10
");

$recentOrderStatement->execute();

$recentOrders = $recentOrderStatement->fetchAll(
    PDO::FETCH_ASSOC
);

/*
|--------------------------------------------------------------------------
| Food Icon Function
|--------------------------------------------------------------------------
*/

function getFoodIcon(string $category): string
{
    return match (strtolower($category)) {

        'rice' => '🍚',

        'noodle' => '🍜',

        'curry' => '🍛',

        'drink' => '🥤',

        'dessert' => '🍰',

        default => '🍽️'
    };
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Order Management</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/orders.css">
</head>

<body>

    <div class="wrapper">

        <?php include("../components/sidebar.php"); ?>

        <main class="main">

            <?php include("../components/topbar.php"); ?>

            <section class="content order-page">

                <div class="order-header">
                    <div>
                        <h2>Create Customer Order</h2>
                        <p>Select table, choose menu items and send order to kitchen.</p>
                    </div>

                    <div class="table-select-box">
                        <label>Select Table</label>
                        <select id="tableSelect" required>
                            <option value="">Select table</option>

                            <?php foreach ($tables as $table): ?>
                                <?php
                                $tableStatus = strtoupper(
                                    trim((string) $table["table_status"])
                                );

                                $tableLabel = "Table " . $table["table_number"];

                                if ($tableStatus === "RESERVED") {
                                    $tableLabel .= " — Reserved";
                                }
                                ?>

                                <option
                                    value="<?= (int) $table["table_id"] ?>"
                                    data-status="<?= htmlspecialchars(
                                                        $tableStatus,
                                                        ENT_QUOTES,
                                                        "UTF-8"
                                                    ) ?>">
                                    <?= htmlspecialchars(
                                        $tableLabel,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>
                </div>

                <div class="order-layout">

                    <div class="menu-panel">

                        <div class="menu-toolbar">
                            <input type="text" id="searchFood" placeholder="Search menu item...">
                        </div>

                        <div class="category-tabs">

                            <button
                                type="button"
                                class="category-btn active"
                                data-category="all">
                                All
                            </button>

                            <?php foreach ($categories as $category): ?>

                                <button
                                    type="button"
                                    class="category-btn"
                                    data-category="<?= (int) $category['category_id']; ?>">
                                    <?= htmlspecialchars(
                                        $category['category_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </button>

                            <?php endforeach; ?>

                        </div>

                        <div class="food-grid" id="foodGrid">

                            <?php if (empty($menuItems)): ?>

                                <div class="empty-menu">
                                    <h3>No menu items available</h3>
                                    <p>Add available menu items from Menu Management.</p>
                                </div>

                            <?php else: ?>

                                <?php foreach ($menuItems as $item): ?>

                                    <div
                                        class="food-card"
                                        data-name="<?= htmlspecialchars(
                                                        $item['name'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>"
                                        data-category="<?= (int) $item['category_id']; ?>"
                                        data-price="<?= (float) $item['price']; ?>">

                                        <div class="food-image">
                                            <?= getFoodIcon($item['category_name']); ?>
                                        </div>

                                        <h3>
                                            <?= htmlspecialchars(
                                                $item['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </h3>

                                        <p>
                                            <?= htmlspecialchars(
                                                $item['description'] ?: 'No description.',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </p>

                                        <div class="food-bottom">

                                            <strong>
                                                <?= number_format((float) $item['price']); ?>
                                                MMK
                                            </strong>

                                            <button
                                                type="button"
                                                class="add-menu-btn"
                                                data-item-id="<?= (int) $item['item_id']; ?>"
                                                data-name="<?= htmlspecialchars(
                                                                $item['name'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ); ?>"
                                                data-price="<?= (float) $item['price']; ?>">
                                                Add
                                            </button>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                    <aside class="order-summary">

                        <div class="summary-header">
                            <h2>Current Order</h2>
                            <span id="selectedTableText">No Table</span>
                        </div>

                        <div class="order-items" id="orderItems">
                            <div class="empty-order">
                                <h3>No items selected</h3>
                                <p>Add menu items to create an order.</p>
                            </div>
                        </div>

                        <div class="order-note">
                            <label>Customer Note</label>
                            <textarea id="orderNote" placeholder="Example: Less spicy, no onion..."></textarea>
                        </div>

                        <div class="price-summary">
                            <div>
                                <span>Subtotal</span>
                                <strong id="subtotal">0 MMK</strong>
                            </div>

                            <div>
                                <span>Tax 5%</span>
                                <strong id="tax">0 MMK</strong>
                            </div>

                            <div class="grand-total">
                                <span>Total</span>
                                <strong id="grandTotal">0 MMK</strong>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="send-btn"
                            >Sent To Kitchen</button>

                    </aside>

                </div>

                <section class="recent-orders-section">

                    <div class="recent-orders-header">
                        <div>
                            <h2>
                                Recent Order Status
                                <span id="activeOrderCount">(0)</span>
                            </h2>
                            <p>Track orders updated by the kitchen.</p>
                        </div>

                        <button
                            type="button"
                            id="refreshOrdersButton"
                            class="refresh-orders-btn">
                            Refresh
                        </button>
                    </div>

                    <div class="recent-orders-grid" id="recentOrdersGrid">

                        <?php if (empty($recentOrders)): ?>

                            <div class="empty-recent-orders">
                                No recent orders found.
                            </div>

                        <?php else: ?>

                            <?php foreach ($recentOrders as $recentOrder): ?>

                                <?php
                                $orderStatus = strtoupper(
                                    (string) $recentOrder["status"]
                                );

                                $chefAction = strtoupper(
                                    (string) $recentOrder["chef_action"]
                                );

                                $displayStatus = $chefAction !== "PENDING"
                                    ? $chefAction
                                    : $orderStatus;

                                $statusClass = strtolower($displayStatus);

                                $orderNumber = "ORD-" . str_pad(
                                    (string) $recentOrder["order_id"],
                                    5,
                                    "0",
                                    STR_PAD_LEFT
                                );
                                ?>

                                <article class="recent-order-card">

                                    <div class="recent-order-top">
                                        <div>
                                            <h3>
                                                <?= htmlspecialchars($orderNumber) ?>
                                            </h3>

                                            <p>
                                                Table
                                                <?= htmlspecialchars(
                                                    (string) $recentOrder["table_number"]
                                                ) ?>
                                            </p>
                                        </div>

                                        <span
                                            class="order-status-badge
                            <?= htmlspecialchars($statusClass) ?>">
                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    strtolower($displayStatus)
                                                )
                                            ) ?>
                                        </span>
                                    </div>

                                    <div class="recent-order-time">
                                        <?= date(
                                            "d M Y, g:i A",
                                            strtotime(
                                                (string) $recentOrder["order_date"]
                                            )
                                        ) ?>
                                    </div>

                                </article>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </section>

            </section>

        </main>

    </div>

    <script src="../assets/js/orders.js"></script>

</body>

</html>