<?php
session_start();
include 'db.php';
include 'header.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// =======================================================
// NEW CLEAN QUERY (NO DUPLICATES, MULTI-LEG SUPPORT)
// =======================================================
$sql = "
SELECT
    li.Flight_number,
    MIN(li.Leg_no) AS first_leg,
    MAX(li.Leg_no) AS last_leg,
    li.Date,
    MIN(li.Departure_time) AS Departure_time,
    MAX(li.Arrival_time) AS Arrival_time,

    -- Route Display (BLR→DEL | DEL→AMD)
    GROUP_CONCAT(
        CONCAT(li.Departure_airport_code,'→',li.Arrival_airport_code)
        ORDER BY li.Leg_no
        SEPARATOR ' | '
    ) AS route_display,

    f.Airline,
    li.Airplane_id,

    -- Total seats for airplane
    (SELECT COUNT(*) FROM seat s WHERE s.Airplane_id = li.Airplane_id) AS total_seats,

    -- Seats booked (all legs)
    (SELECT COUNT(*) 
     FROM reservation r
     WHERE r.Flight_number = li.Flight_number
       AND r.Date = li.Date
    ) AS booked_seats,

    -- PRICE (uses first leg's fare)
    (
        SELECT Base_price 
        FROM fare fa
        WHERE fa.Flight_number = li.Flight_number
          AND fa.Leg_no = MIN(li.Leg_no)
          AND fa.Seat_class_id = 1
        LIMIT 1
    ) AS price

FROM leg_instance li
JOIN flight f ON li.Flight_number = f.Flight_number
WHERE 1
";

// ---------------- FILTERS -----------------
$types = "";
$params = [];

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
    $sql .= " HAVING price <= ? ";
    $types .= "i"; $params[] = $_GET['price'];
}

$sql .= "
GROUP BY li.Flight_number, li.Date
ORDER BY li.Date ASC, Departure_time ASC
";

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
            $air = $conn->query("SELECT Airport_code, City FROM airport ORDER BY Airport_code");
            while ($x = $air->fetch_assoc()):
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
            $air->data_seek(0);
            while ($x = $air->fetch_assoc()):
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

    <!-- AIRLINE -->
    <div class="col-md-2">
        <label>Airline</label>
        <select name="airline" class="form-select">
            <option value="">Any</option>
            <?php
            $al = $conn->query("SELECT DISTINCT Airline FROM flight ORDER BY Airline");
            while ($x = $al->fetch_assoc()):
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
            <?php foreach ([2000,3000,4000,5000,6000,10000] as $p): ?>
                <option value="<?= $p ?>" <?= (($_GET['price'] ?? '') == $p) ? 'selected' : '' ?>>
                    ₹<?= number_format($p) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12 text-end mt-2">
        <button class="btn btn-primary">🔍 Search</button>
        <a href="available_flights.php" class="btn btn-secondary">♻ Reset</a>
    </div>

</form>

<table class="table table-bordered table-striped text-center">
<thead class="table-primary">
<tr>
    <th>Flight</th>
    <th>Airline</th>
    <th>Route</th>
    <th>Date</th>
    <th>Dep</th>
    <th>Arr</th>
    <th>Seats</th>
    <th>Remaining</th>
    <th>Price</th>
    <th>Book</th>
</tr>
</thead>

<tbody>
<?php if ($res->num_rows > 0): ?>
<?php while ($row = $res->fetch_assoc()): 

    $remain = max(0, $row['total_seats'] - $row['booked_seats']);
    $price  = intval($row['price']);

?>
<tr>
    <td><?= esc($row['Flight_number']) ?></td>
    <td><?= esc($row['Airline']) ?></td>
    <td><?= esc($row['route_display']) ?></td>
    <td><?= esc($row['Date']) ?></td>
    <td><?= esc($row['Departure_time']) ?></td>
    <td><?= esc($row['Arrival_time']) ?></td>
    <td><?= esc($row['total_seats']) ?></td>
    <td style="color:<?= ($remain<=5?'red':'green') ?>;font-weight:bold;">
        <?= $remain ?>
    </td>
    <td>₹<?= number_format($price) ?></td>

    <td>
        <?php if ($remain > 0): ?>
            <a href="make_reservation.php?flight=<?= urlencode($row['Flight_number']) ?>&leg=<?= urlencode($row['first_leg']) ?>&date=<?= urlencode($row['Date']) ?>" 
               class="btn btn-book btn-sm">Book</a>
        <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled>Full</button>
        <?php endif; ?>
    </td>
</tr>

<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="10" class="text-danger fw-bold">No flights found.</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>
</div>

</body>
</html>
