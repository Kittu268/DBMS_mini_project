<?php
include 'header.php';
include 'db.php';
session_start();

// 🔒 Ensure login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['user'];

// Fetch Customer Name
$userQ = $conn->prepare("SELECT name FROM users WHERE email = ?");
$userQ->bind_param("s", $email);
$userQ->execute();
$userData = $userQ->get_result()->fetch_assoc();
$customer_name = $userData ? $userData['name'] : "Guest";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $flight_number = $_POST['flight_number'];
    $leg_no = intval($_POST['leg_no']);
    $date = $_POST['date'];
    $airplane_id = $_POST['airplane_id'];
    $seat_no = $_POST['seat_no'];
    $cphone = $_POST['cphone'];

    // 1️⃣ Check seat already booked
    $check = $conn->prepare("
        SELECT reservation_id 
        FROM reservation
        WHERE Flight_number = ? 
          AND Leg_no = ? 
          AND Date = ?
          AND Airplane_id = ?
          AND Seat_no = ?
    ");
    $check->bind_param("sisss", $flight_number, $leg_no, $date, $airplane_id, $seat_no);
    $check->execute();
    $exists = $check->get_result();

    if ($exists->num_rows > 0) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Seat Already Reserved!',
                text: 'Seat $seat_no is already booked on this flight.',
                confirmButtonText: 'OK'
            }).then(() => window.location.href='make_reservation.php');
        </script>";
        exit();
    }

    // 2️⃣ Fetch Fare from flight_price
    $fare = 0;
    $fareQ = $conn->prepare("SELECT price FROM flight_price WHERE Flight_number = ?");
    $fareQ->bind_param("s", $flight_number);
    $fareQ->execute();
    $resultFare = $fareQ->get_result();
    if ($resultFare->num_rows > 0) {
        $fare = $resultFare->fetch_assoc()['price'];
    }

    // 3️⃣ Insert new reservation
    $stmt = $conn->prepare("
        INSERT INTO reservation 
        (Flight_number, Leg_no, Date, Airplane_id, Seat_no, Customer_name, Cphone, Email, fare, payment_status, cancellation_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Active')
    ");

    $stmt->bind_param(
        "sissssssd",
        $flight_number,
        $leg_no,
        $date,
        $airplane_id,
        $seat_no,
        $customer_name,
        $cphone,
        $email,
        $fare
    );

    if ($stmt->execute()) {
        $reservation_id = $stmt->insert_id;

        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Reservation Successful!',
                text: 'Redirecting to payment page...',
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                window.location.href = 'payment.php?reservation_id=$reservation_id';
            });
        </script>";
        exit();
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Reservation Failed',
                text: 'Please try again.',
                confirmButtonText: 'Retry'
            }).then(() => window.location.href='make_reservation.php');
        </script>";
        exit();
    }
}
?>
