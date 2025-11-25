<?php
// ==========================================
// FINAL CANCEL RESERVATION (Refund + Popup)
// ==========================================

if (session_status() === PHP_SESSION_NONE) session_start();
require_once "db.php";
require_once "header.php";

// Ensure login
// FIX: Correct session key — login uses $_SESSION['user']
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['user'];

// -----------------------------------------
// PREFILL WITH reservation_id FROM GET
// -----------------------------------------
$prefill = [
    "reservation_id" => "",
    "Flight_number"  => "",
    "Leg_no"         => "",
    "Date"           => "",
    "Seat_no"        => "",
    "fare"           => 0
];

if (!empty($_GET['reservation_id'])) {
    $id = intval($_GET['reservation_id']);

    $q = $conn->prepare("SELECT * FROM reservation WHERE reservation_id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $res = $q->get_result();

    if ($res->num_rows > 0) {
        $prefill = $res->fetch_assoc();
    }
}

// -----------------------------------------
// HELPERS
// -----------------------------------------
function refund_percentage($hoursLeft) {
    if ($hoursLeft >= 720) return 0.90; // 30 days
    if ($hoursLeft >= 360) return 0.70; // 15 days
    if ($hoursLeft >= 168) return 0.50; // 7 days
    if ($hoursLeft >= 72)  return 0.30; // 3 days
    if ($hoursLeft >= 0)   return 0.10;
    return 0.00;
}

// -----------------------------------------
// PROCESS CANCELLATION
// -----------------------------------------
$status = "";
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $resId = intval($_POST['reservation_id']);

    // Fetch reservation
    $s = $conn->prepare("SELECT * FROM reservation WHERE reservation_id = ?");
    $s->bind_param("i", $resId);
    $s->execute();
    $data = $s->get_result()->fetch_assoc();

    if (!$data) {
        $status = "error";
        $message = "Reservation not found.";
    } else {

        // Calculate hours left
        $flightDT = new DateTime($data['Date'] . " 00:00:00");
        $nowDT    = new DateTime();
        $diff     = $nowDT->diff($flightDT);
        $hoursLeft = ($diff->days * 24) + $diff->h;

        // Refund amount
        $pct = refund_percentage($hoursLeft);
        $refund = round(($data['fare'] * $pct), 2);
        $daysLeft = $diff->days;

        // Insert cancellation record
        $ins = $conn->prepare("
            INSERT INTO cancellations (reservation_id, refund_amount, refund_pct, days_left, hours_left, cancelled_on)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $ins->bind_param("iddii", $resId, $refund, $pct, $daysLeft, $hoursLeft);
        $ins->execute();

        // Revenue log
        $log = $conn->prepare("
            INSERT INTO revenue_log (reservation_id, amount, type)
            VALUES (?, ?, 'refund')
        ");
        $log->bind_param("id", $resId, $refund);
        $log->execute();

        // Update reservation cancellation status
        // FIX: view page matches this format
        $label = "Cancelled (Refund: ₹" . number_format($refund,2) . ")";

        $upd = $conn->prepare("
            UPDATE reservation 
            SET cancellation_status = ?
            WHERE reservation_id = ?
        ");
        $upd->bind_param("si", $label, $resId);
        $upd->execute();

        $status = "success";
        $message = "Refund ₹".number_format($refund,2).". Press OK to return.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>❌ Cancel Reservation</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    margin:0;
    padding-top:120px;
    font-family:Poppins;
    background:linear-gradient(135deg,#8ec5fc,#e0c3fc);
}
.glass {
    max-width:600px;
    margin:auto;
    padding:30px;
    background:rgba(255,255,255,0.18);
    border-radius:20px;
    backdrop-filter:blur(10px);
    box-shadow:0 8px 32px rgba(0,0,0,0.2);
}
</style>
</head>
<body>

<div class="glass">
    <h2 class="text-danger fw-bold">❌ Cancel a Reservation</h2>
    <p>Select a reservation to confirm cancellation.</p>

    <?php if ($status): ?>
        <script>
            setTimeout(() => {
                if (confirm("<?= $message ?>")) {
                    window.location.href = "view_reservations.php";
                }
            }, 300);
        </script>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="reservation_id"
               value="<?= htmlspecialchars($prefill['reservation_id']) ?>">

        <label>Flight Number</label>
        <input class="form-control mb-2" value="<?= htmlspecialchars($prefill['Flight_number']) ?>" readonly>

        <label>Leg No</label>
        <input class="form-control mb-2" value="<?= htmlspecialchars($prefill['Leg_no']) ?>" readonly>

        <label>Date</label>
        <input class="form-control mb-2" value="<?= htmlspecialchars($prefill['Date']) ?>" readonly>

        <label>Seat No</label>
        <input class="form-control mb-3" value="<?= htmlspecialchars($prefill['Seat_no']) ?>" readonly>

        <button class="btn btn-danger w-100">Confirm Cancellation</button>
    </form>

    <a href="view_reservations.php" class="btn btn-outline-secondary mt-3 w-100">⬅ Back</a>
</div>

</body>
</html>
