<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../db.php';   // ✔ correct
    

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';

    if ($email === '' || $pw === '') {
        $error = 'Enter email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, email, password, is_admin FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (password_verify($pw, $row['password'])) {
                if ((int)$row['is_admin'] === 1) {
                    // mark admin session
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = (int)$row['id'];
                    $_SESSION['admin_name'] = $row['username'] ?? $row['email'];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'You are not authorized as admin.';
                }
            } else {
                $error = 'Invalid password.';
            }
        } else {
            $error = 'Admin user not found.';
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Login — Airline</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:linear-gradient(180deg,#dbeafe,#f8fafc);height:100vh;display:flex;align-items:center;justify-content:center;font-family:Poppins,Arial}
    .card{width:380px;border-radius:14px;box-shadow:0 10px 30px rgba(2,6,23,0.08)}
    .brand{font-weight:700;color:#374151}
  </style>
</head>
<body>
  <div class="card p-4">
    <div class="text-center mb-3">
      <div class="brand">✈️ Airline — Admin</div>
      <small class="text-muted">Sign in to manage site</small>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-2"><input type="email" name="email" class="form-control" placeholder="Admin email" required></div>
      <div class="mb-3">
        <div class="input-group">
          <input type="password" id="pw" name="password" class="form-control" placeholder="Password" required>
          <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('pw').type = document.getElementById('pw').type === 'password' ? 'text' : 'password'">Show</button>
        </div>
      </div>
      <div class="d-grid"><button class="btn btn-primary">Login</button></div>
    </form>

    <div class="mt-3 text-center"><a href="../login.php">Back to User Login</a></div>
  </div>
</body>
</html>
