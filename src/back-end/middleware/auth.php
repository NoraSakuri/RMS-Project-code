<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin()
{
    if (!isset($_SESSION["staff_id"])) {
        header("Location: ../../front-end/index.php");
        exit;
    }
}

function allowRoles($roles)
{
    requireLogin();

    $userRole = $_SESSION["role"] ?? "";

    if (!in_array($userRole, $roles)) {
        http_response_code(403);
        echo "403 Access Denied";
        exit;
    }
}