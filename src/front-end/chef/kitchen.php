<?php

require_once "../../back-end/middleware/auth.php";
require_once "../../../config/database.php";

allowRoles(["ADMIN", "CHEF", "MANAGER"]);

$pageTitle = "Kitchen Display";
$roleName = $_SESSION["role"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Kitchen Display</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/kitchen.css">
</head>

<body>

    <div class="wrapper">

        <?php include("../components/sidebar.php"); ?>

        <main class="main">

            <?php include("../components/topbar.php"); ?>

            <section class="content">

                <div class="kitchen-header">
                    <div>
                        <h2>Kitchen Display</h2>
                        <p>Track orders from pending to ready.</p>
                    </div>

                    <button
                        type="button"
                        class="refresh-btn"
                        id="refreshOrdersButton">
                        Refresh Orders
                    </button>
                </div>

                <div class="kitchen-stats">
                    <div class="kitchen-stat">
                        <span>Today Orders</span>
                        <strong id="todayOrderCount">0</strong>
                    </div>

                    <div class="kitchen-stat pending">
                        <span>Pending</span>
                        <strong id="pendingCount">0</strong>
                    </div>

                    <div class="kitchen-stat preparing">
                        <span>Preparing</span>
                        <strong id="preparingCount">0</strong>
                    </div>

                    <div class="kitchen-stat ready">
                        <span>Ready</span>
                        <strong id="readyCount">0</strong>
                    </div>
                </div>

                <div class="kitchen-board">

                    <div class="kitchen-column">
                        <div class="column-title pending-title">
                            <h3>Pending</h3>
                            <span>New orders</span>
                        </div>

                        <div
                            class="kitchen-order-list"
                            id="pendingOrders">
                            <p class="empty-column">No pending orders.</p>
                        </div>
                    </div>

                    <div class="kitchen-column">
                        <div class="column-title accepted-title">
                            <h3>Accepted</h3>
                            <span>Ready to cook</span>
                        </div>

                        <div
                            class="kitchen-order-list"
                            id="acceptedOrders">
                            <p class="empty-column">No accepted orders.</p>
                        </div>
                    </div>

                    <div class="kitchen-column">
                        <div class="column-title preparing-title">
                            <h3>Preparing</h3>
                            <span>Cooking now</span>
                        </div>

                        <div
                            class="kitchen-order-list"
                            id="preparingOrders">
                            <p class="empty-column">No preparing orders.</p>
                        </div>
                    </div>

                    <div class="kitchen-column">
                        <div class="column-title ready-title">
                            <h3>Ready</h3>
                            <span>Serve now</span>
                        </div>

                        <div
                            class="kitchen-order-list"
                            id="readyOrders">
                            <p class="empty-column">No ready orders.</p>
                        </div>
                    </div>

                </div>

            </section>

        </main>

    </div>

    <script src="../assets/js/kitchen.js"></script>

</body>

</html>