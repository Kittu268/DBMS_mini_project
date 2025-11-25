<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password'])) {
     // store entire user row correctly
$_SESSION['user'] = $row;

// store email for admin check
$_SESSION['email'] = $row['email'];

// admin flag
$_SESSION['is_admin'] = ($row['email'] === 'admin@airline.com');


      header("Location: index.php");
      exit();
    } else {
      $error = "❌ Invalid password.";
    }
  } else {
    $error = "❌ Email not found.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>✈️ Airline System Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    html, body {
      height: 100%;
      margin: 0;
      overflow: hidden;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(to bottom, #90caf9, #e3f2fd);
      position: relative;
    }

    /* ☁️ Clouds */
    .cloud {
      position: absolute;
      opacity: 0.85;
      animation: moveClouds linear infinite;
      filter: brightness(1.1);
    }

    .cloud:nth-child(1) { top: 10%; left: -300px; width: 250px; animation-duration: 70s; }
    .cloud:nth-child(2) { top: 40%; left: -400px; width: 280px; animation-duration: 90s; animation-delay: 10s; }
    .cloud:nth-child(3) { top: 70%; left: -350px; width: 230px; animation-duration: 100s; animation-delay: 25s; }

    /* @keyframes moveClouds {
      from { transform: translateX(0); }
      to { transform: translateX(130vw); }
    } */

    /* ✈️ Plane Animation */
    .plane {
      position: absolute;
      width: 2200px;
	height:1000px;
      top: -10%;
      left: -250px;
      opacity: 0.9;
      z-index: 2;
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
      animation: flyInClouds 400s ease-in-out infinite;
    }

    /* @keyframes flyInClouds {
      0% { transform: translate(0, 0) rotate(3deg); opacity: 0.9; }
      50% { transform: translate(110vw, -6vh) rotate(-5deg); opacity: 0.7; }
      100% { transform: translate(220vw, 5vh) rotate(4deg); opacity: 0.9; }
    } */

    /* 🌟 Login Card */
    .login-card {
      position: relative;
      z-index: 5;
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(15px);
      border-radius: 20px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.25);
      padding: 40px;
      max-width: 420px;
      color: #0d47a1;
    }

    .btn-login {
      background: #0d47a1;
      color: #fff;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-login:hover {
      background: #1565c0;
      transform: scale(1.05);
    }
  </style>
</head>
<body>
  <!-- ☁️ Animated Background -->
  <!-- <img src="images/cloud.png" alt="cloud" class="cloud">
  <img src="images/cloud.png" alt="cloud" class="cloud">
  <img src="images/cloud.png" alt="cloud" class="cloud">
  <img src="images/airplane.png" alt="airplane" class="plane"> -->

  <!-- 🌤️ Centered Login Form -->
  <div class="d-flex justify-content-center align-items-center vh-100">
    <div class="login-card text-center">
      <h2 class="mb-4">✈️ Airline System Login</h2>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="email" name="email" class="form-control mb-3" placeholder="Email" required>
        <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
        <button type="submit" class="btn btn-login w-100">Login</button>
      </form>
      <div class="mt-3">
        <a href="signup.php" class="btn btn-outline-primary btn-sm">🆕 Sign Up</a>
      </div>
    </div>
  </div>
</body>
</html>
