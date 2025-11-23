<?php
// payment_process.php — API endpoint that records payment & revenue
// Accepts JSON body. Returns JSON { ok:true, transaction_id:..., reservation_id:... }

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'Invalid JSON body']);
    exit;
}

// required fields
$required = ['method','flight','leg','date','seat','amount','currency','currency_symbol'];
foreach ($required as $r) {
    if (!isset($data[$r]) || $data[$r] === '') {
        echo json_encode(['ok'=>false,'message'=>"Missing field: $r"]);
        exit;
    }
}

$method = ($data['method'] === 'card') ? 'card' : 'upi';
$flight = substr($data['flight'],0,50);
$leg    = (int)$data['leg'];
$date   = $data['date'];
$seat   = substr($data['seat'],0,20);
$amount = number_format((float)$data['amount'],2,'.','');
$currency = substr($data['currency'],0,8);
$symbol   = substr($data['currency_symbol'],0,8);
$method_details = $data['method_details'] ?? '';

$email = $_SESSION['email'] ?? ($data['email'] ?? null);

// figure reservation id (authoritative)
$reservation_id = null;
$stmt = $conn->prepare("SELECT reservation_id, Email FROM reservation WHERE Flight_number=? AND Leg_no=? AND Date=? AND Seat_no=? LIMIT 1");
$stmt->bind_param("siss", $flight, $leg, $date, $seat);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if ($row) {
    $reservation_id = (int)$row['reservation_id'];
    // try set email if missing
    if (!$email && !empty($row['Email'])) $email = $row['Email'];
} else {
    // reservation not found — still allow payment record (optional), but better to fail
    echo json_encode(['ok'=>false,'message'=>'Reservation record not found for provided flight/date/seat.']);
    exit;
}

// build transaction id
$tx = bin2hex(random_bytes(10));

// Insert into payments table
$ins = $conn->prepare("INSERT INTO payments
    (transaction_id, reservation_id, flight_number, leg_no, date, seat_no, email, customer_name, amount, currency, currency_symbol, method, method_details, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')
");
if (!$ins) {
    echo json_encode(['ok'=>false,'message'=>'DB prepare error (payments): '.$conn->error]);
    exit;
}

// customer_name: prefer session user name if present
$customer_name = null;
if (!empty($_SESSION['user']['Cname'])) $customer_name = $_SESSION['user']['Cname'];
elseif (!empty($_SESSION['user']['name'])) $customer_name = $_SESSION['user']['name'];
elseif (!empty($_SESSION['user']) && is_string($_SESSION['user'])) $customer_name = $_SESSION['user'];
else $customer_name = ($data['customer_name'] ?? 'Guest');

$ins->bind_param('sisissssddsss',
    $tx,
    $reservation_id,
    $flight,
    $leg,
    $date,
    $seat,
    $email,
    $customer_name,
    $amount,
    $amount,    // double use: amount as decimal and repeated to match types (fix binding)
    $currency,
    $symbol,
    $method,
    $method_details
);

// Note: bind types adjusted below because PHP's mysqli needs exact types.
// The above line may not match expected type signature depending on PHP version.
// To be safe, perform a manual bind using the proper types:

$ins->close();

// safer manual insert with explicit types:
$tx = $tx;
$customer_name = $customer_name;
$amount_num = (float)$amount;

$insert_sql = "INSERT INTO payments
    (transaction_id, reservation_id, flight_number, leg_no, date, seat_no, email, customer_name, amount, currency, currency_symbol, method, method_details, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
if (!$stmt = $conn->prepare($insert_sql)) {
    echo json_encode(['ok'=>false,'message'=>'DB prepare error (payments): '.$conn->error]);
    exit;
}
$stmt->bind_param('sississsdsiss',
    $tx,
    $reservation_id,
    $flight,
    $leg,
    $date,
    $seat,
    $email,
    $customer_name,
    $amount_num,
    $currency,
    $symbol,
    $method,
    $method_details
);
if (!$stmt->execute()) {
    echo json_encode(['ok'=>false,'message'=>'DB execute error (payments): '.$stmt->error]);
    exit;
}
$stmt->close();

// update reservation payment_status -> Paid
$u = $conn->prepare("UPDATE reservation SET payment_status = 'Paid', fare = ? WHERE reservation_id = ? LIMIT 1");
if ($u) {
    $fare_val = $amount_num;
    $u->bind_param('di', $fare_val, $reservation_id);
    $u->execute();
    $u->close();
}

// insert into revenue_log (sale)
$rev = $conn->prepare("INSERT INTO revenue_log (reservation_id, amount, type) VALUES (?, ?, 'sale')");
if ($rev) {
    $rev->bind_param('id', $reservation_id, $amount_num);
    $rev->execute();
    $rev->close();
}

echo json_encode(['ok'=>true,'transaction_id'=>$tx,'reservation_id'=>$reservation_id,'message'=>'Payment recorded.']);
exit;
