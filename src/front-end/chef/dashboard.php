<?php

declare(strict_types=1);

require_once "../../back-end/middleware/auth.php";

allowRoles([
    "CHEF"
]);

$pageTitle = "Chef Dashboard";
$roleName = $_SESSION["role"] ?? "CHEF";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Chef Dashboard</title>

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css?v=4"
    >
</head>

<body>

<div class="wrapper">

    <?php include("../components/sidebar.php"); ?>

    <main class="main">

        <?php include("../components/topbar.php"); ?>

        <section class="content">

            <div class="hero">
                <div>
                    <h2>Kitchen Orders 👨‍🍳</h2>

                    <p>
                        Monitor today’s orders from pending
                        until they are ready to serve.
                    </p>
                </div>
            </div>

            <div class="cards">

                <div class="card">
                    <div class="card-icon">⏳</div>

                    <h3>Pending</h3>

                    <p id="chefPendingCount">
                        0
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">📥</div>

                    <h3>Accepted</h3>

                    <p id="chefAcceptedCount">
                        0
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">🔥</div>

                    <h3>Preparing</h3>

                    <p id="chefPreparingCount">
                        0
                    </p>
                </div>

                <div class="card">
                    <div class="card-icon">✅</div>

                    <h3>Ready</h3>

                    <p id="chefReadyCount">
                        0
                    </p>
                </div>

            </div>

            <div class="dashboard-panel">

                <div class="panel-header">

                    <div>
                        <h2>Today’s Kitchen Queue</h2>

                        <p>
                            Current active orders and their preparation status.
                        </p>
                    </div>

                    <button
                        type="button"
                        id="refreshChefDashboard"
                        class="refresh-btn"
                    >
                        Refresh
                    </button>

                </div>

                <div class="table-responsive">

                    <table class="dashboard-table">

                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Table</th>
                                <th>Time</th>
                                <th>Items</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody id="chefRecentOrders">
                            <tr>
                                <td colspan="5">
                                    Loading kitchen orders...
                                </td>
                            </tr>
                        </tbody>

                    </table>

                </div>

            </div>

        </section>

    </main>

</div>

<script src="../assets/js/chef_dashboard.js?v=1"></script>

</body>
</html>