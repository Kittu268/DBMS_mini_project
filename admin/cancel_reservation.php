<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

function post($k) { return $_POST[$k] ?? null; }

$flight = post("Flight_number");
$leg    = post("Leg_no");
$date   = post("Date");
$seat   = post("Seat_no");

if (!$flight || !$leg || !$date || !$seat) {
    die("Invalid cancellation request (missing parameters)");
}

/* ---------------------------------------------------------
   1) Fetch reservation details
--------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT reservation_id, fare, cancellation_status, Airplane_id
    FROM reservation
    WHERE Flight_number=? AND Leg_no=? AND Date=? AND Seat_no=?
    LIMIT 1
");
$stmt->bind_param("siss", $flight, $leg, $date, $seat);
$stmt->execute();

$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Reservation not found.");
}

$row  = $res->fetch_assoc();
$resId = $row['reservation_id'];
$fare  = $row['fare'] ?? 0;
$status = $row['cancellation_status'];
$airplaneId = $row['Airplane_id'];

if ($status === "cancelled") {
    header("Location: reservations.php?msg=already_cancelled");
    exit;
}

/* ---------------------------------------------------------
   2) Mark reservation as CANCELLED
--------------------------------------------------------- */
$stmt2 = $conn->prepare("
    UPDATE reservation
    SET cancellation_status='cancelled'
    WHERE reservation_id=?
");
$stmt2->bind_param("i", $resId);
$stmt2->execute();

/* ---------------------------------------------------------
   3) Free the seat (correct for your DB structure)
--------------------------------------------------------- */
$stmt3 = $conn->prepare("
    DELETE FROM seat
    WHERE Airplane_id=? AND Seat_no=?
");
$stmt3->bind_param("ss", $airplaneId, $seat);
$stmt3->execute();

/* ---------------------------------------------------------
   4) Add refund transaction
--------------------------------------------------------- */
$stmt4 = $conn->prepare("
    INSERT INTO revenue_log (reservation_id, amount, type)
    VALUES (?, ?, 'refund')
");
$stmt4->bind_param("id", $resId, $fare);
$stmt4->execute();

/* ---------------------------------------------------------
   5) Redirect to admin page
--------------------------------------------------------- */
header("Location: reservations.php?msg=cancel_success");
exit;
?>
