<?php
// payment.php — show fare & start payment
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php'; // must set $conn (mysqli)

function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmt_currency($amt, $symbol='₹'){ return $symbol . number_format((float)$amt,2); }

// require flight
if (!isset($_GET['flight_number'])) {
    echo "<script>alert('Flight not specified'); window.location='index.php';</script>";
    exit;
}
$flight = $_GET['flight_number'];
$date   = $_GET['date'] ?? null;
$leg    = isset($_GET['leg']) ? intval($_GET['leg']) : null;
$seat   = $_GET['seat'] ?? null;

$email = $_SESSION['email'] ?? null;
if (!$email) {
    echo "<script>alert('Please login to make payment'); window.location='login.php';</script>";
    exit;
}

// Attempt to find matching reservation for this user (strongest match: flight+leg+date+seat+email)
$reservation = null;
if ($flight && $date && $leg && $seat) {
    $q = $conn->prepare("SELECT * FROM reservation WHERE Flight_number=? AND Leg_no=? AND Date=? AND Seat_no=? AND Email=? LIMIT 1");
    $q->bind_param("sisss", $flight, $leg, $date, $seat, $email);
    $q->execute();
    $reservation = $q->get_result()->fetch_assoc();
    $q->close();
}

// If not found, try fallback: last unpaid reservation by this user for that flight
if (!$reservation) {
    $q = $conn->prepare("SELECT * FROM reservation WHERE Flight_number=? AND Email=? ORDER BY reservation_created_at DESC LIMIT 1");
    $q->bind_param("ss", $flight, $email);
    $q->execute();
    $reservation = $q->get_result()->fetch_assoc();
    $q->close();
}

// If still not found, show error and option to go back to booking
if (!$reservation) {
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Payment</title></head><body style='font-family:Inter,Arial,Helvetica'>
        <div style='max-width:760px;margin:60px auto;padding:20px;background:#fff;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.06)'>
        <h3>Invalid reservation</h3>
        <p>No matching reservation was found for your account. Please make a reservation first.</p>
        <a href='make_reservation.php' class='btn' style='display:inline-block;padding:8px 12px;background:#1e88e5;color:#fff;border-radius:6px;text-decoration:none'>Make Reservation</a>
        </div></body></html>";
    exit;
}

// Use reservation values (authoritative)
$res_id = $reservation['reservation_id'];
$leg_no = $reservation['Leg_no'];
$date   = $reservation['Date'];
$seat_no= $reservation['Seat_no'];
$airplane_id = $reservation['Airplane_id'];

// Fare lookup — helper uses dynamic_fare -> fare -> flight_price fallbacks
function get_final_fare_for($conn, $flight, $travel_date) {
    // dynamic_fare
    if ($stmt = $conn->prepare("
        SELECT df.final_fare, df.base_fare, df.demand_factor, sc.class_name
        FROM dynamic_fare df
        LEFT JOIN seat_class sc ON df.seat_class = sc.class_id
        WHERE df.flight_number=? AND df.travel_date=? LIMIT 1
    ")) {
        $stmt->bind_param("ss", $flight, $travel_date);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) {
            $amt = $r['final_fare'] ?: $r['base_fare'];
            return ['amount'=> (float)$amt, 'class'=>($r['class_name'] ?? 'Economy'), 'factor'=>($r['demand_factor'] ?? 1), 'source'=>'dynamic'];
        }
    }
    // fare table
    if ($stmt = $conn->prepare("SELECT base_fare FROM fare WHERE flight_number=? ORDER BY fare_id LIMIT 1")) {
        $stmt->bind_param("s", $flight);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) return ['amount'=> (float)$r['base_fare'], 'class'=>'Economy','factor'=>1,'source'=>'fare'];
    }
    // flight_price
    if ($stmt = $conn->prepare("SELECT price FROM flight_price WHERE Flight_number=? LIMIT 1")) {
        $stmt->bind_param("s", $flight);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) return ['amount'=> (float)$r['price'],'class'=>'Economy','factor'=>1,'source'=>'flight_price'];
    }
    return null;
}

$fare_info = get_final_fare_for($conn, $flight, $date);
$default_amount = 2500.00;
$final_amount = ($fare_info['amount'] ?? $default_amount);
$seat_class = ($fare_info['class'] ?? 'Economy');
$factor = ($fare_info['factor'] ?? 1);
$source = ($fare_info['source'] ?? 'fallback');
$currency_symbol = '₹';

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Payment — <?= e($flight) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:linear-gradient(to bottom,#e6f8ff,#fff);font-family:Inter,Arial;padding:36px;">
<div class="container" style="max-width:720px;">
  <div class="card p-4 shadow-sm">
    <h4>💳 Payment — <?= e($flight) ?></h4>
    <p class="text-muted mb-1">Travel Date: <strong><?= e($date) ?></strong></p>
    <p class="mb-1"><strong>Seat:</strong> <?= e($seat_no) ?> — <strong>Leg:</strong> <?= e($leg_no) ?></p>
    <p class="mb-1"><strong>Seat class:</strong> <?= e($seat_class) ?></p>
    <p class="mb-1"><strong>Fare:</strong> <?= fmt_currency($final_amount, $currency_symbol) ?> <small class="text-muted"><?= e($source) ?></small></p>
    <h3 class="mt-3"><?= fmt_currency($final_amount, $currency_symbol) ?></h3>

    <form method="POST" action="verify_payment.php" style="margin-top:18px;">
      <input type="hidden" name="reservation_id" value="<?= e($res_id) ?>">
      <input type="hidden" name="flight_number" value="<?= e($flight) ?>">
      <input type="hidden" name="leg" value="<?= e($leg_no) ?>">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="seat" value="<?= e($seat_no) ?>">
      <input type="hidden" name="amount" value="<?= number_format($final_amount,2,'.','') ?>">
      <!-- Optional: method selection -->
      <div class="mb-3">
        <label class="form-label">Payment method</label>
        <select name="method" class="form-select" required>
          <option value="card">Card</option>
          <option value="upi">UPI</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary w-100">Pay Now ✈️</button>
    </form>

    <div class="mt-3 text-muted small">
      If price looks wrong: check <code>dynamic_fare</code>, <code>fare</code>, <code>flight_price</code>.
    </div>
  </div>
</div>
</body>
</html>
