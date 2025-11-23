<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Panel</title>
<link rel="stylesheet" href="assets/admin.css">
<script src="assets/admin.js"></script>
</head>
<body>

<?php include __DIR__ . "/../partials/topnav.php"; ?>
<div class="admin-container">
<?php include __DIR__ . "/../partials/sidebar.php"; ?>
<div class="admin-content">
<!-- Admin content starts here -->