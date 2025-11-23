<?php
session_start();

// If admin logged in -> dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

// Else go to admin login
header("Location: login.php");
exit;
