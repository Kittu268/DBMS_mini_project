<?php
// admin/partials/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();

$active = basename($_SERVER['SCRIPT_NAME']);
?>

<aside class="sidebar <?= isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed'] ? 'collapsed' : '' ?>">

    <!-- Logo -->
    <div class="logo-area">
        <div class="logo">✈️</div>
        <div class="logo-text">Airline Admin</div>
    </div>

    <!-- Navigation -->
    <nav class="side-nav">

        <a class="<?= $active == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
            🏠 <span>Dashboard</span>
        </a>

        <a class="<?= $active == 'users.php' ? 'active' : '' ?>" href="users.php">
            👥 <span>Users</span>
        </a>

        <a class="<?= $active == 'flights.php' ? 'active' : '' ?>" href="flights.php">
            🛫 <span>Flights</span>
        </a>

        <a class="<?= $active == 'reservations.php' ? 'active' : '' ?>" href="reservations.php">
            🎟️ <span>Reservations</span>
        </a>

        <a class="<?= $active == 'analytics.php' ? 'active' : '' ?>" href="analytics.php">
            📊 <span>Analytics</span>
        </a>

        <a class="<?= $active == 'sql.php' ? 'active' : '' ?>" href="sql.php">
            🛠 <span>SQL Explorer</span>
        </a>
        <li>
    <a href="help.php">
        <i class="bi bi-info-circle"></i> Help & Docs
    </a>
       </li>

        
        <a href="logout.php">
            🚪 <span>Logout</span>
        </a>

    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <small>Version 1.0 • <?= date('Y') ?></small>
    </div>

</aside>
