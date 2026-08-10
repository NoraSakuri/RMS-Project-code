<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['staff_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /src/front-end/index.php");
        exit;
    }
}

function currentRole() {
    return $_SESSION['role'] ?? null;
}

function allowRoles($roles) {
    if (!isLoggedIn() || !in_array(currentRole(), $roles)) {
        http_response_code(403);
        echo "Access Denied";
        exit;
    }
}
?>