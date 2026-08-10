<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentRole = $_SESSION["role"] ?? ($roleName ?? "USER");
$currentName = $_SESSION["name"] ?? "User";

$firstLetter = strtoupper(substr($currentName, 0, 1));
?>

<header class="topbar">

    <div class="topbar-left">
        <h1><?= htmlspecialchars($pageTitle ?? "Dashboard") ?></h1>
        <p>Manage your restaurant operations easily</p>
    </div>

    <div class="profile-box">

        <div class="profile-avatar">
            <?= $firstLetter ?>
        </div>

        <div class="profile-info">
            <h4><?= htmlspecialchars($currentName) ?></h4>
            <span><?= htmlspecialchars($currentRole) ?> • Online</span>
        </div>

    </div>

</header>