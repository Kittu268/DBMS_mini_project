<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$reservation_id = (int)($_POST["reservation_id"] ?? 0);
$amount         = (float)($_POST["amount"] ?? 0);

if ($reservation_id <= 0) {
    echo "<script>alert('Invalid reservation!'); window.location='view_reservations.php';</script>";
    exit;
}

// Update reservation as PAID
$stmt = $conn->prepare("
    UPDATE reservation
    SET payment_status='Paid', fare=?
    WHERE reservation_id=?
");
$stmt->bind_param("di", $amount, $reservation_id);
$stmt->execute();
$stmt->close();

// Insert revenue log
$stmt = $conn->prepare("
    INSERT INTO revenue_log (reservation_id, amount, type)
    VALUES (?, ?, 'sale')
");
$stmt->bind_param("id", $reservation_id, $amount);
$stmt->execute();
$stmt->close();

// Redirect
header("Location: payment_success.php?id=".$reservation_id);
exit;
?>
