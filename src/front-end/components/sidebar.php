<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION["role"] ?? "";

$dashboardLinks = [
    "ADMIN" => "../admin/dashboard.php",
    "MANAGER" => "../manager/dashboard.php",
    "WAITER" => "../waiter/dashboard.php",
    "CHEF" => "../chef/dashboard.php",
    "CASHIER" => "../cashier/dashboard.php"
];

$dashboardLink = $dashboardLinks[$role] ?? "../index.php";
?>

<div class="sidebar">
    <div class="brand">
        <img src="../assets/images/logo.png" alt="RMS Logo" class="logo">
        <div class="brand-text">
            <h2>RMS</h2>
            <span>Restaurant Management</span>
        </div>
    </div>

    <nav>
        <a href="<?= $dashboardLink ?>">📊 Dashboard</a>

        <?php if (in_array($role, ["ADMIN", "MANAGER"])): ?>
            <a href="../admin/menu.php">🍽 Menu</a>
        <?php endif; ?>

        <?php if (in_array($role, ["ADMIN", "MANAGER"])): ?>
            <a href="../admin/categories.php">🏷 Categories</a>
        <?php endif; ?>

        <?php if (in_array($role, ["ADMIN", "MANAGER", "WAITER"])): ?>
            <a href="../waiter/orders.php">🧾 Orders</a>
        <?php endif; ?>

        <?php if (in_array($role, ["ADMIN", "MANAGER", "CHEF"])): ?>
            <a href="../chef/kitchen.php">👨‍🍳 Kitchen</a>
        <?php endif; ?>

        <?php if (in_array($role, ["ADMIN", "MANAGER", "WAITER"])): ?>
            <a href="../admin/tables.php">🪑 Tables</a>
        <?php endif; ?>

        <?php if (in_array($role, ["ADMIN", "MANAGER", "WAITER"])): ?>
            <a href="../admin/reservation.php">📅 Reservation</a>
        <?php endif; ?>

        <?php if (in_array($role, ["ADMIN", "MANAGER"])): ?>
            <a href="../admin/inventory.php">📦 Inventory</a>
        <?php endif; ?>

        

        <?php if (in_array($role, ["ADMIN", "MANAGER", "CASHIER"])): ?>
            <a href="../cashier/billing.php">💳 Billing</a>
        <?php endif; ?>

        <?php if (in_array($role, ['CASHIER', 'ADMIN', 'MANAGER'])): ?>
            <a href="../cashier/payments.php" class="sidebar-link">💳 Payment History</a>
        <?php endif; ?>

        <?php if ($role === "ADMIN"): ?>
            <a href="../admin/users.php">👥 User Management</a>
        <?php endif; ?>

        <?php if (in_array($role, ["ADMIN", "MANAGER"])): ?>
            <a href="../admin/reports.php">📈 Reports</a>
        <?php endif; ?>

        <a class="logout" href="../../back-end/api/auth/logout.php">🚪 Logout</a>
    </nav>
</div>