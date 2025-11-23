<?php
// ======================================
// AVAILABLE FLIGHTS — FINAL CLEAN VERSION
// ======================================

session_start();
include 'db.php';
include 'header.php';

// User login check
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

// Escape helper
function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ---------------------------
// LOAD PRICES
// ---------------------------
$priceMap = [];
$pq = $conn->query("SELECT Flight_number, price FROM flight_price");
while ($r = $pq->fetch_assoc()) {
    $priceMap[$r['Flight_number']] = $r['price'];
}


// ---------------------------
// BASE QUERY
// ---------------------------
$sql = "
    SELECT 
        li.Flight_number,
        li.Leg_no,
        li.Date,
        li.Departure_time,
        li.Arrival_time,
        li.Departure_airport_code,
        li.Arrival_airport_code,
        li.Airplane_id,
        f.Airline,

        (SELECT COUNT(*) FROM seat s WHERE s.Airplane_id = li.Airplane_id) AS total_seats,

        (SELECT COUNT(*) 
         FROM reservation r
         WHERE r.Flight_number = li.Flight_number
           AND r.Leg_no = li.Leg_no
           AND r.Date = li.Date
        ) AS booked_seats

    FROM leg_instance li
    JOIN flight f ON li.Flight_number = f.Flight_number
    WHERE 1
";

$types = "";
$params = [];

// Apply filters
if (!empty($_GET['from'])) {
    $sql .= " AND li.Departure_airport_code = ? ";
    $types .= "s"; $params[] = $_GET['from'];
}
if (!empty($_GET['to'])) {
    $sql .= " AND li.Arrival_airport_code = ? ";
    $types .= "s"; $params[] = $_GET['to'];
}
if (!empty($_GET['date'])) {
    $sql .= " AND li.Date = ? ";
    $types .= "s"; $params[] = $_GET['date'];
}
if (!empty($_GET['airline'])) {
    $sql .= " AND f.Airline = ? ";
    $types .= "s"; $params[] = $_GET['airline'];
}
if (!empty($_GET['price'])) {
    $sql .= " AND li.Flight_number IN 
        (SELECT Flight_number FROM flight_price WHERE price <= ?) ";
    $types .= "i"; $params[] = $_GET['price'];
}

$sql .= " ORDER BY li.Date ASC, li.Departure_time ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>✈️ Available Flights</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    padding-top: 140px;
    background: linear-gradient(to bottom,#a1c4fd,#c2e9fb);
    font-family: Poppins, sans-serif;
}
.glass {
    background: rgba(255,255,255,0.25);
    padding: 25px;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0,0,0,.2);
}
.btn-book {
    background: linear-gradient(90deg,#1e3c72,#2a5298);
    color:white;
}
.btn-book:hover { transform: scale(1.05); }
</style>

</head>
<body>

<div class="container">
<div class="glass">

<h2 class="text-center mb-3">✈️ Available Flights</h2>

<!-- FILTERS -->
<form method="GET" class="row g-3 mb-4">

    <!-- FROM -->
    <div class="col-md-3">
        <label>From</label>
        <select name="from" class="form-select">
            <option value="">Any</option>
            <?php
            $a = $conn->query("SELECT Airport_code, City FROM airport ORDER BY Airport_code");
            while ($x = $a->fetch_assoc()):
                $sel = ($_GET['from'] ?? '') == $x['Airport_code'] ? 'selected' : '';
            ?>
                <option value="<?= esc($x['Airport_code']) ?>" <?= $sel ?>>
                    <?= esc($x['Airport_code']) ?> — <?= esc($x['City']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- TO -->
    <div class="col-md-3">
        <label>To</label>
        <select name="to" class="form-select">
            <option value="">Any</option>
            <?php
            $a->data_seek(0);
            while ($x = $a->fetch_assoc()):
                $sel = ($_GET['to'] ?? '') == $x['Airport_code'] ? 'selected' : '';
            ?>
                <option value="<?= esc($x['Airport_code']) ?>" <?= $sel ?>>
                    <?= esc($x['Airport_code']) ?> — <?= esc($x['City']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- DATE -->
    <div class="col-md-2">
        <label>Date</label>
        <input type="date" name="date" class="form-control"
            value="<?= esc($_GET['date'] ?? '') ?>">
    </div>

    <!-- Airline -->
    <div class="col-md-2">
        <label>Airline</label>
        <select name="airline" class="form-select">
            <option value="">Any</option>
            <?php
            $air = $conn->query("SELECT DISTINCT Airline FROM flight ORDER BY Airline");
            while ($x = $air->fetch_assoc()):
                $sel = ($_GET['airline'] ?? '') == $x['Airline'] ? 'selected' : '';
            ?>
                <option value="<?= esc($x['Airline']) ?>" <?= $sel ?>>
                    <?= esc($x['Airline']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>

    <!-- PRICE -->
    <div class="col-md-2">
        <label>Max Price</label>
        <select name="price" class="form-select">
            <option value="">Any</option>
            <?php
            foreach ([2000,3000,4000,5000,6000,10000] as $p):
                $sel = ($_GET['price'] ?? '') == $p ? 'selected' : '';
            ?>
                <option value="<?= $p ?>" <?= $sel ?>>₹<?= number_format($p) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-12 text-end">
        <button class="btn btn-primary">🔍 Search</button>
        <a href="available_flights.php" class="btn btn-secondary">♻ Reset</a>
    </div>

</form>


<!-- TABLE -->
<table class="table table-bordered table-striped text-center">
    <thead class="table-primary">
    <tr>
        <th>Flight</th>
        <th>Airline</th>
        <th>From</th>
        <th>To</th>
        <th>Date</th>
        <th>Dep</th>
        <th>Arr</th>
        <th>Airplane</th>
        <th>Total</th>
        <th>Booked</th>
        <th>Remaining</th>
        <th>Price</th>
        <th>Book</th>
    </tr>
    </thead>
    <tbody>

<?php if ($res->num_rows > 0): ?>
<?php while ($row = $res->fetch_assoc()): 

    $total   = intval($row['total_seats']);
    $booked  = intval($row['booked_seats']);
    $remain  = max(0, $total - $booked);

    $price   = $priceMap[$row['Flight_number']] ?? 0;

?>
<tr>
    <td><?= esc($row['Flight_number']) ?></td>
    <td><?= esc($row['Airline']) ?></td>
    <td><?= esc($row['Departure_airport_code']) ?></td>
    <td><?= esc($row['Arrival_airport_code']) ?></td>
    <td><?= esc($row['Date']) ?></td>
    <td><?= esc($row['Departure_time']) ?></td>
    <td><?= esc($row['Arrival_time']) ?></td>

    <td><?= esc($row['Airplane_id'] ?: 'N/A') ?></td>

    <td><?= $total ?></td>
    <td><?= $booked ?></td>
    <td style="font-weight:bold;color:<?= ($remain<=5?'red':'green') ?>">
        <?= $remain ?>
    </td>

    <td>₹<?= number_format($price) ?></td>

    <td>
        <?php if ($remain > 0 && $row['Airplane_id']): ?>
            <a href="make_reservation.php?flight=<?= urlencode($row['Flight_number']) ?>&leg=<?= urlencode($row['Leg_no']) ?>&date=<?= urlencode($row['Date']) ?>"
               class="btn btn-book btn-sm">Book</a>
        <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled>Full</button>
        <?php endif; ?>
    </td>
</tr>

<?php endwhile; ?>
<?php else: ?>

<tr><td colspan="13" class="text-danger fw-bold">No flights found.</td></tr>

<?php endif; ?>

</tbody>
</table>

</div>
</div>

</body>
</html>
