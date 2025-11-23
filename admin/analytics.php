<?php
// admin/analytics.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

// Safe HTML escape
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ----------------------------------------------------
   0) BASIC TOTALS
---------------------------------------------------- */
$usersCount   = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$flightsCount = (int)$conn->query("SELECT COUNT(*) AS c FROM flight")->fetch_assoc()['c'];
$resCount     = (int)$conn->query("SELECT COUNT(*) AS c FROM reservation")->fetch_assoc()['c'];

/* ----------------------------------------------------
   1) Reservations per day — last 30 days
---------------------------------------------------- */
$resPerDayStmt = $conn->prepare("
    SELECT DATE(reservation_created_at) AS d, COUNT(*) AS c
    FROM reservation
    WHERE reservation_created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(reservation_created_at)
    ORDER BY DATE(reservation_created_at)
");
$resPerDayStmt->execute();
$resPerDayResult = $resPerDayStmt->get_result();

// Build 30-day map (Google Charts must have all dates)
$datesMap = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $datesMap[$d] = 0;
}
while ($r = $resPerDayResult->fetch_assoc()) {
    $datesMap[$r['d']] = (int)$r['c'];
}
$resPerDayStmt->close();

$resPerDayRows = [];
foreach ($datesMap as $d => $count) {
    $y   = (int)substr($d, 0, 4);
    $m   = (int)substr($d, 5, 2) - 1; // JS month (0–11)
    $day = (int)substr($d, 8, 2);
    $resPerDayRows[] = "[new Date($y,$m,$day),$count]";
}

/* ----------------------------------------------------
   2) Top Routes
---------------------------------------------------- */
$routesRows = [];
$routesSql = "
    SELECT li.Departure_airport_code AS dep, li.Arrival_airport_code AS arr,
           COUNT(r.reservation_id) AS bookings
    FROM reservation r
    LEFT JOIN leg_instance li
        ON r.Flight_number = li.Flight_number
       AND r.Leg_no       = li.Leg_no
       AND r.Date         = li.Date
    WHERE li.Departure_airport_code IS NOT NULL
      AND li.Arrival_airport_code   IS NOT NULL
    GROUP BY dep, arr
    ORDER BY bookings DESC
    LIMIT 10
";
$rq = $conn->query($routesSql);

while ($rr = $rq->fetch_assoc()) {
    $routesRows[] = "['".esc($rr['dep']." → ".$rr['arr'])."',".$rr['bookings']."]";
}

/* ----------------------------------------------------
   3) Seats summary
---------------------------------------------------- */
// total seats from leg_instance
$totalSeats = (int)$conn->query("
    SELECT SUM(COALESCE(Number_of_available_seats,0)) AS t 
    FROM leg_instance
")->fetch_assoc()['t'];

// booked seats (reservation count)
$bookedSeats = (int)$conn->query("
    SELECT COUNT(*) AS c FROM reservation
")->fetch_assoc()['c'];

$availableSeats = max(0, $totalSeats - $bookedSeats);

/* ----------------------------------------------------
   4) Airline Market Share
---------------------------------------------------- */
$airlineRows = [];
$aRes = $conn->query("
    SELECT f.Airline, COUNT(*) AS c
    FROM reservation r
    LEFT JOIN flight f ON r.Flight_number = f.Flight_number
    GROUP BY f.Airline
    ORDER BY c DESC
    LIMIT 12
");
while ($a = $aRes->fetch_assoc()) {
    $airlineRows[] = "['".esc($a['Airline'])."',".$a['c']."]";
}

/* ----------------------------------------------------
   5) Top Flights
---------------------------------------------------- */
$topFlights = $conn->query("
    SELECT Flight_number, COUNT(*) AS c
    FROM reservation
    GROUP BY Flight_number
    ORDER BY c DESC
    LIMIT 10
");

/* ----------------------------------------------------
   6) Revenue Analytics (revenue_log table)
---------------------------------------------------- */

// Lifetime totals
$revRow = $conn->query("
    SELECT 
        SUM(CASE WHEN type='sale'   THEN amount ELSE 0 END) AS total_sales,
        SUM(CASE WHEN type='refund' THEN amount ELSE 0 END) AS total_refunds
    FROM revenue_log
")->fetch_assoc();

$totalSales   = (float)($revRow['total_sales']   ?? 0);
$totalRefunds = (float)($revRow['total_refunds'] ?? 0);
$netRevenue   = $totalSales - $totalRefunds;

// Today's revenue
$todayRevRow = $conn->query("
    SELECT 
        SUM(CASE WHEN type='sale'   THEN amount ELSE 0 END) AS sales_today,
        SUM(CASE WHEN type='refund' THEN amount ELSE 0 END) AS refunds_today
    FROM revenue_log
    WHERE DATE(created_at) = CURDATE()
")->fetch_assoc();

$todaySales   = (float)($todayRevRow['sales_today']   ?? 0);
$todayRefunds = (float)($todayRevRow['refunds_today'] ?? 0);
$todayNet     = $todaySales - $todayRefunds;

// Average sale
$avgRow = $conn->query("
    SELECT AVG(amount) AS avg_sale
    FROM revenue_log
    WHERE type='sale'
")->fetch_assoc();
$avgPerSale = (float)($avgRow['avg_sale'] ?? 0);

// Refund rate
$refundRate = $totalSales > 0 ? ($totalRefunds / $totalSales) * 100 : 0;

// Monthly revenue (12 months)
$monthlyRows = [];
$monthlyRes = $conn->query("
    SELECT 
        DATE_FORMAT(created_at,'%Y-%m') AS ym,
        SUM(CASE WHEN type='sale'   THEN amount ELSE 0 END) AS sales,
        SUM(CASE WHEN type='refund' THEN amount ELSE 0 END) AS refunds
    FROM revenue_log
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY ym
    ORDER BY ym
");

while ($row = $monthlyRes->fetch_assoc()) {
    $ts   = strtotime($row['ym'].'-01');
    $label = date('M Y', $ts); // e.g. Feb 2025
    $sales   = (float)$row['sales'];
    $refunds = (float)$row['refunds'];
    $net     = $sales - $refunds;
    $monthlyRows[] = "['".esc($label)."',$sales,$refunds,$net]";
}

// Pie chart
$revPieRows = [
    "['Sales', $totalSales]",
    "['Refunds', $totalRefunds]"
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Analytics — Airline</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="assets/admin.css">

<script src="https://www.gstatic.com/charts/loader.js"></script>

<style>
.analytics-wrap { max-width:1200px;margin:20px auto;padding:10px; }
.widgets { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.widget { flex:1 1 200px; background:white; padding:14px; border-radius:10px; box-shadow:0 6px 16px rgba(0,0,0,0.07); }
.widget .label { font-size:13px;color:#6b7280;margin-bottom:4px; }
.widget .num { font-size:22px;font-weight:700;color:#0d47a1; }
.widget .sub { font-size:12px;color:#9ca3af;margin-top:2px; }
.grid { display:grid; grid-template-columns:1fr 350px; gap:18px; }
@media(max-width:950px){ .grid{grid-template-columns:1fr;} }
.card { background:white; padding:18px; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.07); }
.card h3 { margin-top:0;font-size:18px;margin-bottom:8px; }
.revenue-table { width:100%; border-collapse:collapse; margin-top:10px; font-size:14px; }
.revenue-table th, .revenue-table td { padding:6px 8px; border-bottom:1px solid #e5e7eb; text-align:right; }
.revenue-table th:first-child, .revenue-table td:first-child { text-align:left; }
.revenue-table tfoot td { font-weight:600; }
</style>

<script>
google.charts.load('current',{packages:['corechart','bar']});
google.charts.setOnLoadCallback(drawCharts);

function drawCharts(){

    /* Reservations per day */
    var d1 = new google.visualization.DataTable();
    d1.addColumn('date','Date');
    d1.addColumn('number','Reservations');
    d1.addRows([<?= implode(",", $resPerDayRows); ?>]);

    new google.visualization.AreaChart(document.getElementById('chart1'))
        .draw(d1,{
            height:300,
            legend:'none',
            colors:['#1e88e5'],
            areaOpacity:0.2
        });

    /* Top Routes */
    var d2 = new google.visualization.DataTable();
    d2.addColumn('string','Route');
    d2.addColumn('number','Bookings');
    d2.addRows([<?= implode(",", $routesRows); ?>]);

    new google.visualization.BarChart(document.getElementById('chart2'))
        .draw(d2,{height:300,legend:'none'});

    /* Seats Summary */
    var d3 = new google.visualization.DataTable();
    d3.addColumn('string','Type');
    d3.addColumn('number','Count');
    d3.addRows([
        ['Booked', <?= $bookedSeats ?>],
        ['Available', <?= $availableSeats ?>]
    ]);

    new google.visualization.PieChart(document.getElementById('chart3'))
        .draw(d3,{pieHole:0.55,height:300});

    /* Airline share */
    var d4 = new google.visualization.DataTable();
    d4.addColumn('string','Airline');
    d4.addColumn('number','Bookings');
    d4.addRows([<?= implode(",", $airlineRows); ?>]);

    new google.visualization.PieChart(document.getElementById('chart4'))
        .draw(d4,{height:300});

    /* Monthly Revenue (Sales vs Refunds vs Net) */
    var d5 = new google.visualization.DataTable();
    d5.addColumn('string','Month');
    d5.addColumn('number','Sales');
    d5.addColumn('number','Refunds');
    d5.addColumn('number','Net');

    d5.addRows([<?= implode(",", $monthlyRows); ?>]);

    new google.visualization.ColumnChart(document.getElementById('chart5'))
        .draw(d5,{
            height:320,
            legend:{ position:'top' },
            isStacked:false
        });

    /* Revenue Pie (Sales vs Refunds) */
    var d6 = new google.visualization.DataTable();
    d6.addColumn('string','Type');
    d6.addColumn('number','Amount');
    d6.addRows([<?= implode(",", $revPieRows); ?>]);

    new google.visualization.PieChart(document.getElementById('chart6'))
        .draw(d6,{
            height:320,
            pieHole:0.45
        });
}

window.addEventListener('resize', drawCharts);
</script>

</head>
<body>

<?php include __DIR__.'/partials/topnav.php'; ?>
<?php include __DIR__.'/partials/sidebar.php'; ?>

<div class="admin-main">
<div class="admin-content">

<h2>📊 Analytics Dashboard</h2>

<div class="analytics-wrap">

<!-- ================= WIDGETS (TOP) ================= -->
<div class="widgets">
    <div class="widget">
        <div class="label">Users</div>
        <div class="num"><?= number_format($usersCount) ?></div>
        <div class="sub">Registered accounts</div>
    </div>
    <div class="widget">
        <div class="label">Flights</div>
        <div class="num"><?= number_format($flightsCount) ?></div>
        <div class="sub">Total scheduled flights</div>
    </div>
    <div class="widget">
        <div class="label">Reservations</div>
        <div class="num"><?= number_format($resCount) ?></div>
        <div class="sub">All-time bookings</div>
    </div>
    <div class="widget">
        <div class="label">Total Seats</div>
        <div class="num"><?= number_format($totalSeats) ?></div>
        <div class="sub">Across all leg instances</div>
    </div>

    <!-- Revenue widgets -->
    <div class="widget">
        <div class="label">Net Revenue</div>
        <div class="num">₹<?= number_format($netRevenue, 2) ?></div>
        <div class="sub">Sales − refunds (lifetime)</div>
    </div>
    <div class="widget">
        <div class="label">Today’s Revenue</div>
        <div class="num">₹<?= number_format($todayNet, 2) ?></div>
        <div class="sub">Today: sales − refunds</div>
    </div>
    <div class="widget">
        <div class="label">Refund Rate</div>
        <div class="num"><?= number_format($refundRate, 1) ?>%</div>
        <div class="sub">Refunds / Sales value</div>
    </div>
</div>

<!-- ============ MAIN GRID: BOOKINGS & SEATS ============ -->
<div class="grid">
    <div class="card">
        <h3>Reservations — Last 30 Days</h3>
        <div id="chart1"></div>
    </div>

    <div class="card">
        <h3>Seats Summary</h3>
        <div id="chart3"></div>
    </div>

    <div class="card">
        <h3>Top Routes</h3>
        <div id="chart2"></div>
    </div>

    <div class="card">
        <h3>Airline Market Share</h3>
        <div id="chart4"></div>
    </div>
</div>

<!-- ============ REVENUE ANALYTICS SECTION ============ -->
<div class="grid" style="margin-top:20px;">
    <div class="card">
        <h3>Monthly Revenue (Last 12 Months)</h3>
        <div id="chart5"></div>

        <table class="revenue-table">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Sales</td>
                    <td><?= number_format($totalSales, 2) ?></td>
                </tr>
                <tr>
                    <td>Total Refunds</td>
                    <td><?= number_format($totalRefunds, 2) ?></td>
                </tr>
                <tr>
                    <td>Net Revenue</td>
                    <td><?= number_format($netRevenue, 2) ?></td>
                </tr>
                <tr>
                    <td>Avg Revenue per Sale</td>
                    <td><?= number_format($avgPerSale, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Revenue Split — Sales vs Refunds</h3>
        <div id="chart6"></div>
    </div>
</div>

<!-- ============ TOP FLIGHTS TABLE ============ -->
<div class="card" style="margin-top:18px;">
    <h3>Top Flights (by Reservations)</h3>
    <table class="revenue-table">
        <thead>
            <tr>
                <th>Flight</th>
                <th>Bookings</th>
            </tr>
        </thead>
        <tbody>
        <?php while($tf = $topFlights->fetch_assoc()): ?>
            <tr>
                <td><?= esc($tf['Flight_number']) ?></td>
                <td><?= number_format($tf['c']) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</div> <!-- .analytics-wrap -->

</div></div> <!-- .admin-content / .admin-main -->

<script src="assets/admin.js"></script>
</body>
</html>
<?php
// -------------- PART 3 — FINAL CLEANUP --------------

// Ensure esc() always exists (prevents header.php error)
if (!function_exists('esc')) {
    function esc($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
?>

</div> <!-- end admin-content -->
</div> <!-- end admin-main -->

<script src="assets/admin.js"></script>

</body>
</html>
