<?php
require_once "../../back-end/middleware/auth.php";
require_once "../../../config/database.php";

allowRoles(["ADMIN"]);

$pageTitle = "User Management";
$roleName = "Admin";

/* ==========================
   Statistics
========================== */

$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();

$totalAdmins = $pdo->query("
SELECT COUNT(*)
FROM staff
WHERE role='ADMIN'
")->fetchColumn();

$totalActive = $pdo->query("
SELECT COUNT(*)
FROM staff
WHERE is_active=1
")->fetchColumn();

$totalInactive = $pdo->query("
SELECT COUNT(*)
FROM staff
WHERE is_active=0
")->fetchColumn();

/* ==========================
   Staff List
========================== */

$stmt = $pdo->query("
SELECT *
FROM staff
ORDER BY staff_id DESC
");

$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Management</title>

    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/users.css">
</head>

<body>

    <div class="wrapper">

        <?php include("../components/sidebar.php"); ?>

        <main class="main">

            <?php include("../components/topbar.php"); ?>

            <section class="content">

                <div class="users-header">
                    <div>
                        <h2>User Management</h2>
                        <p>Manage staff accounts, roles and access status.</p>
                    </div>

                    <button class="add-user-btn" onclick="openUserModal()">
                        + Add Staff
                    </button>
                </div>

                <div class="users-stats">
                    <div class="user-stat">
                        <h4>Total Staff</h4>
                        <h2><?= $totalStaff ?></h2>
                    </div>

                    <div class="user-stat admin">
                        <h4>Admins</h4>
                        <h2><?= $totalAdmins ?></h2>
                    </div>

                    <div class="user-stat active">
                        <h4>Active Users</h4>
                        <h2><?= $totalActive ?></h2>
                    </div>

                    <div class="user-stat inactive">
                        <h4>Inactive Users</h4>
                        <h2><?= $totalInactive ?></h2>
                    </div>
                </div>

                <div class="users-panel">

                    <div class="users-toolbar">
                        <input type="text" id="searchUser" placeholder="Search staff name or username...">

                        <select id="roleFilter">
                            <option>All Roles</option>
                            <option>ADMIN</option>
                            <option>MANAGER</option>
                            <option>WAITER</option>
                            <option>CHEF</option>
                            <option>CASHIER</option>
                        </select>

                        <select id="statusFilter">
                            <option>All Status</option>
                            <option>Active</option>
                            <option>Inactive</option>
                        </select>
                    </div>

                    <div class="users-table">

                        <table>
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($staffs as $staff): ?>

                                    <tr data-id="<?= (int) $staff["staff_id"] ?>">

                                        <td>

                                            <div class="staff-info">

                                                <div class="staff-avatar">

                                                    <?= strtoupper(substr($staff['name'], 0, 1)); ?>

                                                </div>

                                                <div>

                                                    <strong><?= htmlspecialchars($staff['name']) ?></strong>

                                                    <span><?= strtolower($staff['role']) ?></span>

                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <?= htmlspecialchars($staff['username']) ?>

                                        </td>

                                        <td>

                                            <span class="role-badge <?= strtolower($staff['role']) ?>">

                                                <?= $staff['role'] ?>

                                            </span>

                                        </td>

                                        <td>

                                            <?php if ($staff['is_active']) { ?>

                                                <span class="status-badge active">

                                                    Active

                                                </span>

                                            <?php } else { ?>

                                                <span class="status-badge inactive">
                                                    Pending / Inactive
                                                </span>

                                            <?php } ?>

                                        </td>

                                        <td>

                                            <?= date("d M Y", strtotime($staff['created_at'])) ?>

                                        </td>

                                        <td>

                                            <button
                                                type="button"
                                                class="edit-btn"
                                                data-id="<?= (int) $staff['staff_id'] ?>"
                                                data-name="<?= htmlspecialchars(
                                                                $staff['name'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                data-username="<?= htmlspecialchars(
                                                                    $staff['username'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                                data-role="<?= htmlspecialchars(
                                                                $staff['role'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                data-status="<?= (int) $staff['is_active'] ?>">
                                                Edit
                                            </button>

                                            <?php if ($staff['is_active']) { ?>

                                                <button class="disable-btn">

                                                    Disable

                                                </button>

                                            <?php } else { ?>

                                                <button class="enable-btn">
                                                    Approve
                                                </button>

                                            <?php } ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>
                        </table>

                    </div>

                </div>

            </section>

        </main>

    </div>

    <div class="user-modal" id="userModal">
        <div class="user-modal-content">

            <div class="modal-header">
                <h2 id="userModalTitle">
                    Add Staff
                </h2>
                <button onclick="closeUserModal()">✕</button>
            </div>

            <form id="userForm">

                <input
                    type="hidden"
                    id="editStaffId"
                    value="">

                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" id="staffName" placeholder="Enter full name" required>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="username" placeholder="Enter username" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label id="passwordLabel">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            placeholder="Enter password">
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select id="role" required>
                            <option value="">Select role</option>
                            <option>ADMIN</option>
                            <option>MANAGER</option>
                            <option>WAITER</option>
                            <option>CHEF</option>
                            <option>CASHIER</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select id="status">
                        <option>Active</option>
                        <option>Inactive</option>
                    </select>
                </div>

                <button
                    type="submit"
                    id="saveUserButton"
                    class="save-user-btn">
                    Save Staff
                </button>

            </form>

        </div>
    </div>

    <script src="../assets/js/users.js"></script>

</body>

</html>