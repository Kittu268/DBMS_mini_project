<?php
// admin/includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) session_start();

// expected session keys set by your admin login script
// If your login uses different keys, adapt accordingly.
if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_name'])) {
    // not logged in -> redirect to admin login
    header("Location: login.php");
    exit();
}
