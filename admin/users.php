<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin — Users</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- DataTables -->
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <!-- Admin UI CSS -->
  <link href="assets/admin.css" rel="stylesheet">
</head>

<body>

<?php include 'partials/topnav.php'; ?>

<div class="admin-main"> <!-- FIX: wrap everything inside admin-main -->

    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-content">

        <h2>👥 All Users</h2>

        <div class="card p-3">
            <table id="tblUsers" class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Admin</th>
                        <th>Created</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                $res = $conn->query("SELECT id, username, name, email, is_admin, created_at FROM users ORDER BY id DESC");

                while ($r = $res->fetch_assoc()):
                ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['username']) ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['email']) ?></td>
                        <td><?= $r['is_admin'] ? 'Yes' : 'No' ?></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>

            </table>
        </div>

    </main>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<!-- Sidebar toggle script -->
<script src="assets/admin.js"></script>

<script>
$(document).ready(() => {
    $('#tblUsers').DataTable({
        pageLength: 10
    });
});
</script>

</body>
</html>
