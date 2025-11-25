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
   1) Fetch reservation details + departure_time from leg_instance
--------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT r.reservation_id, r.fare, r.cancellation_status, r.Airplane_id,
           li.Departure_time
    FROM reservation r
    JOIN leg_instance li
        ON li.Flight_number = r.Flight_number
       AND li.Leg_no = r.Leg_no
       AND li.Date = r.Date
    WHERE r.Flight_number=? AND r.Leg_no=? AND r.Date=? AND r.Seat_no=?
    LIMIT 1
");
$stmt->bind_param("siss", $flight, $leg, $date, $seat);
$stmt->execute();

$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Reservation not found.");
}

$row  = $res->fetch_assoc();
$resId       = $row['reservation_id'];
$fare        = (float)$row['fare'];
$status      = $row['cancellation_status'];
$airplaneId  = $row['Airplane_id'];
$depTime     = $row['Departure_time']; // NOW WE HAVE IT

if ($status === "cancelled") {
    header("Location: reservations.php?msg=already_cancelled");
    exit;
}

/* ---------------------------------------------------------
   2) Calculate refund based on HOURS 
--------------------------------------------------------- */
$current = new DateTime();  
$travelDateTime = new DateTime("$date $depTime");

$diffHours = (int) round(($travelDateTime->getTimestamp() - $current->getTimestamp()) / 3600);
if ($diffHours < 0) $diffHours = 0;

if ($diffHours >= 48)       $refundPct = 100;
else if ($diffHours >= 12) $refundPct = 50;
else if ($diffHours >= 3)  $refundPct = 20;
else                       $refundPct = 0;

$refundAmount = ($fare * $refundPct) / 100;

/* ---------------------------------------------------------
   3) Mark reservation as CANCELLED
--------------------------------------------------------- */
$stmt2 = $conn->prepare("
    UPDATE reservation
    SET cancellation_status='cancelled'
    WHERE reservation_id=?
");
$stmt2->bind_param("i", $resId);
$stmt2->execute();

/* ---------------------------------------------------------
   4) Increase seat availability back in leg_instance
--------------------------------------------------------- */
$stmt3 = $conn->prepare("
    UPDATE leg_instance
    SET Number_of_available_seats = Number_of_available_seats + 1
    WHERE Flight_number=? AND Leg_no=? AND Date=?
");
$stmt3->bind_param("sis", $flight, $leg, $date);
$stmt3->execute();

/* ---------------------------------------------------------
   5) Log cancellation
--------------------------------------------------------- */
$stmt4 = $conn->prepare("
    INSERT INTO cancellations (reservation_id, refund_amount, refund_pct, hours_left)
    VALUES (?, ?, ?, ?)
");
$stmt4->bind_param("idii", $resId, $refundAmount, $refundPct, $diffHours);
$stmt4->execute();

/* ---------------------------------------------------------
   6) Revenue log
--------------------------------------------------------- */
$stmt5 = $conn->prepare("
    INSERT INTO revenue_log (reservation_id, amount, type)
    VALUES (?, ?, 'refund')
");
$stmt5->bind_param("id", $resId, $refundAmount);
$stmt5->execute();

/* ---------------------------------------------------------
   7) Redirect
--------------------------------------------------------- */
header("Location: reservations.php?msg=cancel_success");
exit;
?>
