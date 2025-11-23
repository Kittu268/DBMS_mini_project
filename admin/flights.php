<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin — Flights</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Admin CSS -->
  <link href="assets/admin.css" rel="stylesheet">
</head>

<body>

<?php include 'partials/topnav.php'; ?>

<div class="admin-main"> <!-- FIX: this wrapper required for sidebar collapse -->

    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-content">

      <h2>🛫 Flights & Leg Instances</h2>

      <!-- FLIGHTS TABLE -->
      <div class="card p-3 mb-4">
        <h5>Flights</h5>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Flight</th>
                <th>Airline</th>
                <th>Duration</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $f = $conn->query("SELECT Flight_number, Airline, Duration FROM flight ORDER BY Flight_number");
              while ($r = $f->fetch_assoc()) {
                  echo "<tr>";
                  echo "<td>".e($r['Flight_number'])."</td>";
                  echo "<td>".e($r['Airline'])."</td>";
                  echo "<td>".e($r['Duration'])." min</td>";
                  echo "</tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- LEG INSTANCES TABLE -->
      <div class="card p-3">
        <h5>Leg Instances</h5>
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Flight</th>
                <th>Leg</th>
                <th>Date</th>
                <th>Departure</th>
                <th>Arrival</th>
                <th>Airplane</th>
              </tr>
            </thead>

            <tbody>
              <?php 
              $li = $conn->query("SELECT * FROM leg_instance ORDER BY Date DESC, Flight_number LIMIT 300");
              while ($r = $li->fetch_assoc()) {
                  echo "<tr>";
                  echo "<td>".e($r['Flight_number'])."</td>";
                  echo "<td>".e($r['Leg_no'])."</td>";
                  echo "<td>".e($r['Date'])."</td>";
                  echo "<td>".e($r['Departure_time'])."</td>";
                  echo "<td>".e($r['Arrival_time'])."</td>";
                  echo "<td>".e($r['Airplane_id'])."</td>";
                  echo "</tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>

</div>

<!-- Sidebar collapse logic -->
<script src="assets/admin.js"></script>

</body>
</html>
