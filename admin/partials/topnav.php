<?php
// admin/partials/topnav.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<header class="topnav">

    <!-- Hamburger -->
    <button id="menu-toggle" class="hamburger-btn">☰</button>

    <!-- Brand -->
    <div class="brand">✈️ Airline Admin</div>

    <!-- Right Section -->
    <div class="topnav-right">
        <span><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
        <a href="../index.php" class="btn-outline">↗ View site</a>
        <a href="logout.php" class="btn-danger">Logout</a>
    </div>

</header>
