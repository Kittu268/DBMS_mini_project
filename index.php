<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>✈️ Airline System - Home</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

  <style>

    /* Push content below fixed navbar */
    body {
        margin: 0;
        padding-top: 140px;
        font-family: 'Poppins', sans-serif;
        overflow-x: hidden;
    }

    .glass-card {
        position: relative;
        z-index: 1000;
        background: rgba(255, 255, 255, 0.18);
        padding: 50px 60px;
        border-radius: 20px;
        max-width: 700px;
        margin: 120px auto;
        text-align: center;
        backdrop-filter: blur(14px);
        box-shadow: 0 10px 35px rgba(0,0,0,0.3);
        animation: fadeIn 1.4s ease;
    }

    /* @keyframes fadeIn {
      from { opacity: 0; transform: translateY(40px); }
      to   { opacity: 1; transform: translateY(0); }
    } */

    h1 {
      color: #0d47a1;
      font-weight: 700;
      margin-bottom: 20px;
    }

    p {
      color: #1a237e;
      font-size: 1.15rem;
      margin-bottom: 30px;
    }

    /* Buttons */
    .btn-custom {
      background: linear-gradient(90deg, #1e3c72, #2a5298);
      color: white;
      padding: 12px 30px;
      border-radius: 10px;
      border: none;
      font-weight: 600;
      transition: 0.3s;
    }
    .btn-custom:hover {
      transform: scale(1.05);
      background: linear-gradient(90deg, #2a5298, #1e3c72);
    }

    .btn-outline-primary {
      border: 2px solid #1a237e;
      padding: 12px 30px;
      border-radius: 10px;
      background: rgba(255,255,255,0.3);
      font-weight: 600;
      transition: 0.3s;
      color: #1a237e;
    }
    .btn-outline-primary:hover {
      background: #1a237e;
      color: white;
      transform: scale(1.05);
    }

  </style>

</head>
<body>

<div class="glass-card">
  <h1>Welcome to Airline Reservation System</h1>
  <p>Simplify your travel — Book, View, Cancel, and Manage flight reservations.</p>

  <?php if (!empty($_SESSION['user'])): ?>

      <a href="available_flights.php" class="btn btn-custom me-2">🛫 Available Flights</a>

      <a href="make_reservation.php" class="btn btn-outline-primary me-2">✈ Book Flight</a>

      <a href="view_reservations.php" class="btn btn-outline-primary">📋 View Reservations</a>

  <?php else: ?>

      <a href="login.php" class="btn btn-custom me-2">🔐 Login</a>
      <a href="signup.php" class="btn btn-outline-primary">🆕 Sign Up</a>

  <?php endif; ?>
</div>

</body>
</html>
