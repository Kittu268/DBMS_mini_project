<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function u($v) { return urlencode($v ?? ''); }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin — Reservations</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>

<body>

<?php include 'partials/topnav.php'; ?>

<div class="admin-main">
<?php include 'partials/sidebar.php'; ?>

<main class="admin-content">

    <h2>🎟️ Reservations</h2>

    <!-- Success/Failure messages -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info">
            <?php
                if ($_GET['msg'] === 'cancel_success') echo "Reservation cancelled successfully.";
                elseif ($_GET['msg'] === 'already_cancelled') echo "Reservation is already cancelled.";
            ?>
        </div>
    <?php endif; ?>

    <div class="card p-3">
        <table id="tblRes" class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Flight</th>
                    <th>Leg</th>
                    <th>Date</th>
                    <th>Seat</th>
                    <th>Fare</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $q = "
                SELECT reservation_id, Flight_number, Leg_no, Date, Seat_no, Customer_name,
                       Email, Cphone, fare, cancellation_status
                FROM reservation
                ORDER BY Date DESC, Flight_number
                LIMIT 500
            ";

            $res = $conn->query($q);
            while ($r = $res->fetch_assoc()) {

                $params = '?flight=' . u($r['Flight_number']) .
                          '&leg=' . u($r['Leg_no']) .
                          '&date=' . u($r['Date']) .
                          '&seat=' . u($r['Seat_no']);

                $isCancelled = ($r['cancellation_status'] === 'cancelled');
            ?>

                <tr>
                    <td>
                        <?php if ($isCancelled): ?>
                            <span class="badge bg-danger">Cancelled</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </td>

                    <td><?= e($r['Flight_number']) ?></td>
                    <td><?= e($r['Leg_no']) ?></td>
                    <td><?= e($r['Date']) ?></td>
                    <td><?= e($r['Seat_no']) ?></td>
                    <td>₹<?= e(number_format($r['fare'], 2)) ?></td>
                    <td><?= e($r['Customer_name']) ?></td>
                    <td><?= e($r['Email']) ?></td>
                    <td><?= e($r['Cphone']) ?></td>

                    <td>
                        <!-- FIXED: CORRECT PATH TO ticket.php -->
                        <a class="btn btn-sm btn-primary me-1" href="../ticket.php<?= $params ?>">Ticket</a>

                        <?php if (!$isCancelled): ?>
                            <button 
                                type="button"
                                class="btn btn-sm btn-danger cancel-btn"
                                data-flight="<?= e($r['Flight_number']) ?>"
                                data-leg="<?= e($r['Leg_no']) ?>"
                                data-date="<?= e($r['Date']) ?>"
                                data-seat="<?= e($r['Seat_no']) ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#cancelModal">
                                Cancel
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>Cancelled</button>
                        <?php endif; ?>
                    </td>
                </tr>

            <?php } ?>
            </tbody>
        </table>
    </div>

</main>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<!-- Required for modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(() => {
    $('#tblRes').DataTable({
        pageLength: 15,
        order: [[2, 'desc']]
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".cancel-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const flight = btn.dataset.flight;
            const leg    = btn.dataset.leg;
            const date   = btn.dataset.date;
            const seat   = btn.dataset.seat;

            document.getElementById("m_flight").innerText = flight;
            document.getElementById("m_leg").innerText    = leg;
            document.getElementById("m_date").innerText   = date;
            document.getElementById("m_seat").innerText   = seat;

            document.getElementById("f_flight").value = flight;
            document.getElementById("f_leg").value    = leg;
            document.getElementById("f_date").value   = date;
            document.getElementById("f_seat").value   = seat;
        });
    });
});
</script>

<!-- CANCEL MODAL -->
<div class="modal fade" id="cancelModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

          <div class="modal-header bg-danger text-white">
              <h5 class="modal-title">Confirm Cancellation</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
              <p class="fw-bold text-danger">
                  ⚠️ Are you sure you want to cancel this reservation?
              </p>
              <p>This will issue a <strong>refund log</strong> and mark the booking as <strong>cancelled</strong>.</p>

              <hr>

              <div class="small">
                  <strong>Flight:</strong> <span id="m_flight"></span><br>
                  <strong>Leg:</strong> <span id="m_leg"></span><br>
                  <strong>Date:</strong> <span id="m_date"></span><br>
                  <strong>Seat:</strong> <span id="m_seat"></span>
              </div>
          </div>

          <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>

              <form id="cancelForm" method="post" action="cancel_reservation.php" class="d-inline">
                <input type="hidden" name="Flight_number" id="f_flight">
                <input type="hidden" name="Leg_no" id="f_leg">
                <input type="hidden" name="Date" id="f_date">
                <input type="hidden" name="Seat_no" id="f_seat">
                <button type="submit" class="btn btn-danger">Yes, Cancel</button>
              </form>
          </div>

      </div>
  </div>
</div>

</body>
</html>
