<?php
// view_reservations.php - list user's reservations with modern UI and ticket download
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['email'])) {
    header('Location: login.php');
    exit;
}

$userEmail = $_SESSION['email'];
// fetch reservations for user
$stmt = $conn->prepare("
  SELECT reservation_id, Flight_number, Leg_no, Date, Seat_no, Airplane_id,
         Customer_name, Cphone, Email, created_at, reservation_created_at, payment_status, fare, cancellation_status
  FROM reservation
  WHERE Email = ?
  ORDER BY reservation_created_at DESC, created_at DESC
");
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Your Reservations — Airline</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* glass card style */
body {
  font-family: Poppins, Arial, sans-serif;
  background: linear-gradient(180deg,#e6f8ff,#fff);
  padding: 60px 20px;
}
.container-card {
  max-width: 1100px;
  margin: 0 auto;
}
.glass {
  background: rgba(255,255,255,0.36);
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 12px 30px rgba(2,6,23,0.08);
  backdrop-filter: blur(8px);
}
.status-pill {
  padding: 6px 10px;
  border-radius: 999px;
  font-weight:600;
  font-size:0.9rem;
}
.status-paid { background:#e6ffef; color:#0f5132; border:1px solid rgba(16,185,129,0.08); }
.status-pending { background:#fff7e6; color:#7a4b00; border:1px solid rgba(245,158,11,0.06); }
.status-cancel { background:#fff0f0; color:#7a1f1f; border:1px solid rgba(239,68,68,0.06); }

.table-responsive { overflow:auto; }
.btn-mini { padding:6px 10px; font-size:0.9rem; }
.float-right { float:right; }
.empty-note { color:#6b7280; text-align:center; padding:40px 0; }
</style>
</head>
<body>

<?php include_once __DIR__ . '/header.php'; ?>

<div class="container-card">
  <div class="glass">
    <div class="d-flex align-items-center mb-3">
      <h3 class="mb-0">✈️ Your Reservations</h3>
      <div class="ms-auto">
        <a href="available_flights.php" class="btn btn-outline-primary btn-sm">Browse Flights</a>
      </div>
    </div>

    <?php if (count($rows) === 0): ?>
      <div class="empty-note">
        <p>No reservations found. Try booking a flight from <a href="available_flights.php">Available Flights</a>.</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Flight</th>
              <th>Leg</th>
              <th>Date</th>
              <th>Seat</th>
              <th>Airplane</th>
              <th>Fare</th>
              <th>Customer</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e($r['reservation_id']) ?></td>
                <td><?= e($r['Flight_number']) ?></td>
                <td><?= e($r['Leg_no']) ?></td>
                <td><?= e($r['Date']) ?></td>
                <td><?= e($r['Seat_no']) ?></td>
                <td><?= e($r['Airplane_id']) ?></td>
                <td><?= '₹' . number_format((float)$r['fare'],2) ?></td>
                <td><?= e($r['Customer_name']) ?></td>
                <td><?= e($r['Cphone']) ?></td>
                <td>
                  <?php
                    $ps = strtolower($r['payment_status'] ?? 'Pending');
                    if ($ps === 'paid') $cls='status-paid';
                    elseif ($ps === 'cancelled' || ($r['cancellation_status'] ?? '') === 'Cancelled') $cls='status-cancel';
                    else $cls='status-pending';
                  ?>
                  <span class="status-pill <?= $cls ?>"><?= e($r['payment_status'] ?? 'Pending') ?></span>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <!-- Download ticket only if paid -->
                    <?php if (strtolower($r['payment_status'] ?? '') === 'paid'): ?>
                      <a class="btn btn-success btn-mini" href="ticket.php?flight=<?= urlencode($r['Flight_number']) ?>&leg=<?= urlencode($r['Leg_no']) ?>&date=<?= urlencode($r['Date']) ?>&seat=<?= urlencode($r['Seat_no']) ?>" target="_blank">Download</a>
                    <?php else: ?>
                      <a class="btn btn-outline-secondary btn-mini" href="payment.php?flight_number=<?= urlencode($r['Flight_number']) ?>&leg=<?= urlencode($r['Leg_no']) ?>&date=<?= urlencode($r['Date']) ?>&seat=<?= urlencode($r['Seat_no']) ?>">Pay</a>
                    <?php endif; ?>

                    <form method="post" action="cancel_reservation.php" style="display:inline;">
                      <input type="hidden" name="reservation_id" value="<?= e($r['reservation_id']) ?>">
                      <button class="btn btn-danger btn-mini" type="submit" onclick="return confirm('Cancel reservation <?= e($r['reservation_id']) ?>?')">Cancel</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
