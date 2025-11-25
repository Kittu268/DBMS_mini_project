<?php
// payment.php — FINAL CLEAN VERSION (correct fare logic + optimized)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v){ return "₹" . number_format((float)$v, 2); }

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];

// Required GET params
$flight = $_GET['flight_number'] ?? null;
$date   = $_GET['date'] ?? null;
$leg    = isset($_GET['leg']) ? (int)$_GET['leg'] : null;
$seat   = $_GET['seat'] ?? null;

if (!$flight) {
    die("Flight not specified.");
}

// -------------------
// Find reservation
// -------------------
$res = null;

if ($flight && $date && $leg && $seat) {
    $q = $conn->prepare("
        SELECT *
        FROM reservation
        WHERE Flight_number=? AND Leg_no=? AND Date=? AND Seat_no=? AND Email=?
        LIMIT 1
    ");
    $q->bind_param("sisss", $flight, $leg, $date, $seat, $email);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
}

// fallback: latest reservation by user for this flight
if (!$res) {
    $q = $conn->prepare("
        SELECT *
        FROM reservation
        WHERE Flight_number=? AND Email=?
        ORDER BY reservation_id DESC
        LIMIT 1
    ");
    $q->bind_param("ss", $flight, $email);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();
}

if (!$res) {
    die("<h2>No reservation found for this flight.</h2>");
}

$res_id     = $res['reservation_id'];
$leg_no     = $res['Leg_no'];
$travel_date= $res['Date'];
$seat_no    = $res['Seat_no'];
$airplane_id= $res['Airplane_id'];

// -----------------------------------------------------------------
// GET SEAT CLASS FOR THIS SPECIFIC SEAT (VERY IMPORTANT)
// -----------------------------------------------------------------
$seat_class_id = 1;  // default
$q = $conn->prepare("
    SELECT Seat_class_id 
    FROM seat 
    WHERE Airplane_id=? AND Seat_no=?
");
$q->bind_param("ss", $airplane_id, $seat_no);
$q->execute();
$r = $q->get_result()->fetch_assoc();
$q->close();

if ($r && $r['Seat_class_id']) {
    $seat_class_id = (int)$r['Seat_class_id'];
}

// -----------------------------------------------------------------
// GET FINAL FARE (dynamic_fare → fare → flight_price → fallback)
// -----------------------------------------------------------------
function get_final_fare($conn, $flight, $date, $leg_no, $seat_class_id) {

    // 1) Dynamic Fare
    $q = $conn->prepare("
        SELECT final_fare, base_fare, demand_factor
        FROM dynamic_fare
        WHERE flight_number=? AND travel_date=?
        LIMIT 1
    ");
    $q->bind_param("ss", $flight, $date);
    $q->execute();
    $dyn = $q->get_result()->fetch_assoc();
    $q->close();

    if ($dyn) {
        $amt = $dyn['final_fare'] ?: $dyn['base_fare'];
        return [
            'amount' => (float)$amt,
            'source' => 'dynamic_fare'
        ];
    }

    // 2) Fare table (CORRECT Base_price)
    $q = $conn->prepare("
        SELECT Base_price
        FROM fare
        WHERE Flight_number=? AND (Leg_no=? OR Leg_no IS NULL) AND Seat_class_id=?
        ORDER BY fare_id DESC
        LIMIT 1
    ");
    $q->bind_param("sii", $flight, $leg_no, $seat_class_id);
    $q->execute();
    $fare = $q->get_result()->fetch_assoc();
    $q->close();

    if ($fare) {
        return [
            'amount' => (float)$fare['Base_price'],
            'source' => 'fare'
        ];
    }

    // 3) flight_price fallback
    $q = $conn->prepare("SELECT price FROM flight_price WHERE Flight_number=? LIMIT 1");
    $q->bind_param("s", $flight);
    $q->execute();
    $fp = $q->get_result()->fetch_assoc();
    $q->close();

    if ($fp) {
        return [
            'amount' => (float)$fp['price'],
            'source' => 'flight_price'
        ];
    }

    // DEFAULT
    return [
        'amount' => 2500.00,
        'source' => 'default'
    ];
}

$fare_info = get_final_fare($conn, $flight, $travel_date, $leg_no, $seat_class_id);

$amount = $fare_info['amount'];
$source = $fare_info['source'];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payment — <?= e($flight) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#eef7ff; padding:40px; font-family:Poppins">

<div class="container" style="max-width:720px;">
<div class="card shadow p-4">

    <h3>💳 Payment — <?= e($flight) ?></h3>

    <p class="text-muted">Travel Date: <b><?= e($travel_date) ?></b></p>
    <p>Seat: <b><?= e($seat_no) ?></b> • Leg <b><?= e($leg_no) ?></b></p>

    <p>Fare Source: <b><?= e($source) ?></b></p>

    <h2 class="mt-3 mb-3"><?= money($amount) ?></h2>

    <!-- Payment form -->
    <form action="verify_payment.php" method="POST">

        <input type="hidden" name="reservation_id" value="<?= e($res_id) ?>">
        <input type="hidden" name="flight_number" value="<?= e($flight) ?>">
        <input type="hidden" name="date" value="<?= e($travel_date) ?>">
        <input type="hidden" name="leg" value="<?= e($leg_no) ?>">
        <input type="hidden" name="seat" value="<?= e($seat_no) ?>">
        <input type="hidden" name="amount" value="<?= number_format($amount,2,'.','') ?>">

        <label class="form-label">Payment Method</label>
        <select name="method" class="form-select mb-3" required>
            <option value="card">Card</option>
            <option value="upi">UPI</option>
        </select>

        <button class="btn btn-primary w-100">Pay Now</button>

    </form>

    <div class="mt-3 small text-muted">
        If the price is incorrect, check: <code>dynamic_fare</code>, <code>fare</code>, <code>flight_price</code>.
    </div>

</div>
</div>

</body>
</html>
