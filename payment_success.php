<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$tx = $_GET['tx'] ?? '';
$payment = null;

if ($tx) {
    $s = $conn->prepare("SELECT * FROM payments WHERE transaction_id=? LIMIT 1");
    $s->bind_param("s", $tx);
    $s->execute();
    $payment = $s->get_result()->fetch_assoc();
    $s->close();
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Payment Successful</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="font-family:Poppins,Arial;background:#e6f7ff;padding:40px;">

<script>
// Auto redirect to reservations after 1.8 seconds
setTimeout(() => {
    window.location.href = "view_reservations.php";
}, 1800);
</script>

<div class="container" style="max-width:720px;">
  <div class="card p-4 shadow-sm text-center">

    <div style="font-size:56px;width:120px;height:120px;border-radius:60px;
    background:linear-gradient(135deg,#4caf50,#66bb6a);color:#fff;margin:0 auto;
    display:flex;align-items:center;justify-content:center">
        ✓
    </div>

    <h3 class="mt-3">Payment Successful</h3>

    <?php if ($payment): ?>
      <p class="text-muted small">Transaction ID: <strong><?= e($payment['transaction_id']) ?></strong></p>
      <p><strong><?= e($payment['currency_symbol'].' '.number_format($payment['amount'],2)) ?></strong>
        received via <strong><?= e(strtoupper($payment['method'])) ?></strong></p>
      <p class="mb-1">Flight: <strong><?= e($payment['flight_number']) ?></strong> — Seat: <strong><?= e($payment['seat_no']) ?></strong></p>
      <p class="mb-1">Date: <?= e($payment['date']) ?> — Reservation: <?= e($payment['reservation_id']) ?></p>
    <?php else: ?>
      <p class="text-muted">Payment record not found.</p>
    <?php endif; ?>

    <div class="mt-3">
      <a href="view_reservations.php" class="btn btn-primary">View Reservations</a>
      <a href="index.php" class="btn btn-outline-secondary">Home</a>
    </div>

  </div>
</div>

</body>
</html>
