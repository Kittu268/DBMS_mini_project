<?php
include 'header.php';
include 'db.php';
session_start();

// 🔒 Ensure login
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $flight_number = $_POST['flight_number'];
  $leg_no = $_POST['leg_no'];
  $date = $_POST['date'];
  $airplane_id = $_POST['airplane_id'];
  $seat_no = $_POST['seat_no'];
  $cphone = $_POST['cphone'];
  $email = $_SESSION['user'];

  // ✅ Step 1: Check for duplicate seat reservation
  $check = $conn->prepare("
    SELECT * FROM reservation
    WHERE Flight_number = ? AND Leg_no = ? AND Date = ? AND Seat_no = ?
  ");
  $check->bind_param("siss", $flight_number, $leg_no, $date, $seat_no);
  $check->execute();
  $exists = $check->get_result();

  if ($exists->num_rows > 0) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <script>
            Swal.fire({
              icon: 'error',
              title: 'Seat Already Reserved!',
              text: 'Seat $seat_no is already booked for Flight $flight_number.',
              confirmButtonText: 'Back to Booking'
            }).then(() => window.location.href='make_reservation.php');
          </script>";
    exit();
  }

  // ✅ Step 2: Insert Reservation
  $stmt = $conn->prepare("
    INSERT INTO reservation (Flight_number, Leg_no, Date, Airplane_id, Seat_no, Cphone, Email, payment_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
  ");
  $stmt->bind_param("sisssss", $flight_number, $leg_no, $date, $airplane_id, $seat_no, $cphone, $email);

  if ($stmt->execute()) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <script>
            Swal.fire({
              icon: 'success',
              title: 'Reservation Successful!',
              text: 'Redirecting to payment page...',
              showConfirmButton: false,
              timer: 2000
            }).then(() => {
              window.location.href = 'payment.php?flight_number=$flight_number';
            });
          </script>";
    exit();
  } else {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
          <script>
            Swal.fire({
              icon: 'error',
              title: 'Reservation Failed',
              text: 'Something went wrong. Please try again.',
              confirmButtonText: 'Retry'
            }).then(() => window.location.href='make_reservation.php');
          </script>";
    exit();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>✈️ Make a Reservation</title>
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

  .cloud:nth-child(1) { top: 15%; left: -200px; width: 250px; animation-duration: 60s; }
  .cloud:nth-child(2) { top: 50%; left: -300px; width: 300px; animation-duration: 90s; animation-delay: 5s; }
  .cloud:nth-child(3) { top: 70%; left: -250px; width: 220px; animation-duration: 120s; animation-delay: 15s; }

  @keyframes moveClouds {
    from { transform: translateX(0); }
    to { transform: translateX(120vw); }
  }

  /* ✈️ Airplane flying inside clouds */
  .plane {
    position: absolute;
    width: 2200px;
    top: 30%;
    left: -250px;
    opacity: 0.9;
    z-index: 2;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
    animation: flyInClouds 40s ease-in-out infinite;
  }

  @keyframes flyInClouds {
    0% { transform: translate(0, 0) rotate(3deg); opacity: 0.9; }
    50% { transform: translate(110vw, -6vh) rotate(-5deg); opacity: 0.7; }
    100% { transform: translate(220vw, 5vh) rotate(4deg); opacity: 0.9; }
  }

  /* 🌟 Form Card */
  .form-card {
    position: relative;
    z-index: 5;
    margin-top: 10vh;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    color: #0d47a1;
    padding: 40px;
    max-width: 550px;
  }

  .btn-custom {
    background: #0d47a1;
    color: #fff;
    font-weight: 600;
    transition: all 0.3s ease;
  }

  .btn-custom:hover {
    background: #1565c0;
    transform: scale(1.05);
  }
</style>
</head>
<body>
<!-- ☁️ Background -->
<img src="images/cloud.png" alt="cloud" class="cloud">
<img src="images/cloud.png" alt="cloud" class="cloud">
<img src="images/cloud.png" alt="cloud" class="cloud">

<!-- ✈️ Airplane -->
<img src="images/airplane.png" alt="plane" class="plane">

<div class="d-flex justify-content-center align-items-center vh-100">
  <div class="form-card text-center">
    <h2 class="mb-4">✈️ Book Your Reservation</h2>
    <form action="reservation.php" method="POST">
      <input type="text" name="flight_number" class="form-control mb-3" placeholder="Flight Number" required>
      <input type="number" name="leg_no" class="form-control mb-3" placeholder="Leg Number" required>
      <input type="date" name="date" class="form-control mb-3" required>
      <input type="text" name="airplane_id" class="form-control mb-3" placeholder="Airplane ID" required>
      <input type="text" name="seat_no" class="form-control mb-3" placeholder="Seat Number" required>
      <input type="text" name="cphone" class="form-control mb-3" placeholder="Contact Phone" required>
      <button type="submit" class="btn btn-custom w-100">🛫 Reserve Now</button>
    </form>
    <div class="mt-3">
      <a href="view_reservations.php" class="btn btn-outline-secondary btn-sm">📋 View Reservations</a>
    </div>
  </div>
</div>
</body>
</html>
