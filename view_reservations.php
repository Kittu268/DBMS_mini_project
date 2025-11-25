<?php
// view_reservations.php - list user's reservations with modern UI
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function e($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/* 
-------------------------------------------------------
 FIX 1: Correct session variable
 Old:  $_SESSION['email']
 But login stores: $_SESSION['user']
-------------------------------------------------------
*/
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Auto-detect email format from session
if (is_array($_SESSION['user'])) {

    // If user stored as array with key 'email'
    if (isset($_SESSION['user']['email'])) {
        $userEmail = $_SESSION['user']['email'];

    // If user stored as array with numeric index
    } elseif (isset($_SESSION['user'][0])) {
        $userEmail = $_SESSION['user'][0];

    } else {
        die("Error: Email not found in session.");
    }

} else {
    // If user stored as a plain string (email)
    $userEmail = $_SESSION['user'];
}
   // extract email only
 // FIX 2

// Fetch reservations for logged-in user
$stmt = $conn->prepare("
  SELECT reservation_id, Flight_number, Leg_no, Date, Seat_no, Airplane_id,
         Customer_name, Cphone, Email, created_at, reservation_created_at,
         payment_status, fare, cancellation_status
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
body {
  font-family: Poppins, Arial, sans-serif;
  background: linear-gradient(180deg,#e6f8ff,#fff);
  padding: 60px 20px;
}
.container-card { max-width: 1100px; margin: 0 auto; }
.glass {
  background: rgba(255,255,255,0.36);
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 12px 30px rgba(2,6,23,0.08);
  backdrop-filter: blur(8px);
}
.status-pill {
  padding: 6px 10px;
  border-radius: 999px; font-weight:600; font-size:0.9rem;
}
.status-paid { background:#e6ffef; color:#0f5132; }
.status-pending { background:#fff7e6; color:#7a4b00; }
.status-cancel { background:#fff0f0; color:#7a1f1f; }
.btn-mini { padding:6px 10px; font-size:0.9rem; }
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

                <!-- Correct Fare -->
                <td><?= '₹' . number_format((float)$r['fare'], 2) ?></td>

                <!-- Customer Name -->
                <td><?= e($r['Customer_name']) ?></td>

                <!-- Phone -->
                <td><?= e($r['Cphone']) ?></td>

                <!-- Status -->
                <td>
                  <?php
                    $pay = strtolower($r['payment_status']);
                    $cancel = strtolower($r['cancellation_status']);

                    /*
                    -------------------------------------------------------
                     FIX 3: Cancellation text contains:
                         "Cancelled (Refund: ₹120.00)"
                     so exact match fails.
                    -------------------------------------------------------
                    */
                    if (strpos($cancel, 'cancelled') === 0) {
                      $cls = 'status-cancel';
                      $label = 'Cancelled';
                    }
                    elseif ($pay === 'paid') {
                      $cls = 'status-paid';
                      $label = 'Paid';
                    }
                    else {
                      $cls = 'status-pending';
                      $label = 'Pending';
                    }
                  ?>
                  <span class="status-pill <?= $cls ?>"><?= e($label) ?></span>
                </td>

         <!-- ACTIONS -->
<td>
  <div class="d-flex gap-1">

    <?php
    $isCancelled = strpos(strtolower($r['cancellation_status']), 'cancelled') === 0;
    $isPaid = strtolower($r['payment_status']) === 'paid';

    // Show Download button only when Paid & Not Cancelled
    if ($isPaid && !$isCancelled):
    ?>
        <a class="btn btn-success btn-mini"
           href="ticket.php?flight=<?= urlencode($r['Flight_number']) ?>&leg=<?= urlencode($r['Leg_no']) ?>&date=<?= urlencode($r['Date']) ?>&seat=<?= urlencode($r['Seat_no']) ?>"
           target="_blank">
           Download
        </a>

    <?php elseif ($isCancelled): ?>
        <button class="btn btn-secondary btn-mini" disabled>Cancelled</button>
    <?php endif; ?>


    <!-- Cancel Button (only if not cancelled already) -->
    <?php if (!$isCancelled): ?>
      <a class="btn btn-danger btn-mini"
         href="cancel_reservation.php?reservation_id=<?= urlencode($r['reservation_id']) ?>">
         Cancel
      </a>
    <?php endif; ?>

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
