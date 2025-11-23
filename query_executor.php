<?php
// query_executor.php
// Run predefined SELECT queries or a manual SELECT (SELECT-only allowed).
include 'db.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>🧠 SQL Query Explorer</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
/* ===== Background / Clouds / Plane (animated) ===== */
html,body{height:100%;margin:0;padding:0;font-family:'Poppins',sans-serif;background:linear-gradient(to bottom,#a1c4fd,#c2e9fb);overflow-x:hidden}
.cloud-layer{position:absolute;width:250%;height:150%;background:url('images/cloud.png') repeat-x;background-size:contain;opacity:.45;top:0;left:-50%;animation:moveClouds linear infinite;z-index:1}
.cloud-layer:nth-child(1){animation-duration:150s;top:5%;opacity:.6}
.cloud-layer:nth-child(2){animation-duration:180s;top:40%;opacity:.5}
.cloud-layer:nth-child(3){animation-duration:210s;top:75%;opacity:.4}
@keyframes moveClouds{from{background-position-x:0}to{background-position-x:10000px}}
.plane{position:absolute;width:1800px;height:auto;top:0;left:-500px;opacity:.85;z-index:0;filter:drop-shadow(0 10px 15px rgba(0,0,0,.25));animation:flyInsideClouds 55s ease-in-out infinite}
@keyframes flyInsideClouds{0%{transform:translate(0,0) rotate(6deg)}50%{transform:translate(110vw,-8vh) rotate(-4deg)}100%{transform:translate(-200px,6vh) rotate(6deg)}}

/* ===== Glass box ===== */
.glass-box{position:relative;z-index:5;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border-radius:18px;box-shadow:0 6px 25px rgba(0,0,0,.2);padding:30px;width:90%;max-width:1200px;margin:110px auto;color:#0d47a1}
h2{font-weight:700;color:#0d47a1}
.table{background:rgba(255,255,255,.95)}
textarea,select{background:rgba(255,255,255,.98);border:1px solid rgba(0,0,0,.08)}
.btn-success{background:#0d47a1;border:none}
.btn-success:hover{background:#1565c0}
.code-block{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", monospace;background:#0b1220;color:#dfe7ff;padding:10px;border-radius:8px;overflow:auto}
.small-muted{font-size:.9rem;color:#456}
</style>
<script>
document.addEventListener("DOMContentLoaded",function(){
  const form=document.getElementById("queryForm");
  const manual=document.getElementById("manual_query");
  manual.addEventListener("keydown",function(e){
    if(e.key==="Enter" && !e.shiftKey){
      e.preventDefault();
      form.submit();
    }
  });
});
</script>
</head>
<body>
<?php include 'header.php'; ?>

<!-- background -->
<div class="cloud-layer"></div>
<div class="cloud-layer"></div>
<div class="cloud-layer"></div>
<img src="images/airplane.png" alt="airplane" class="plane">

<div class="glass-box">
  <h2>🧮 SQL Query Explorer — Predefined & Manual (SELECT-only)</h2>
  <p class="small-muted">Choose a predefined query (examples include joins, UNION, aggregates, nested queries) or enter your own <strong>SELECT</strong> statement. Non-SELECT queries will be rejected.</p>

  <form method="POST" id="queryForm" class="mb-4">
    <label class="form-label fw-semibold">Predefined queries</label>
    <select name="query_choice" class="form-select mb-3">
      <option value="">-- Select Query --</option>
      <option value="show_tables">🗂️ Show All Tables</option>

      <optgroup label="Aggregates & GROUP BY">
        <option value="agg1">📊 Reservations per Flight (GROUP BY)</option>
        <option value="agg2">📊 Seats per Airplane Type (JOIN + SUM)</option>
      </optgroup>

      <optgroup label="Complex JOINs">
        <option value="j1">🔗 Reservation → Flight (simple JOIN)</option>
        <option value="j2">🔗 Flight → Leg_Instance → Airplane → Airplane_Type (multi-join)</option>
        <option value="j3">🔗 Reservation → Leg_Instance → Airports (departure/arrival)</option>
      </optgroup>

      <optgroup label="UNION & Subqueries">
        <option value="u1">🔀 UNION: Flights from two airlines</option>
        <option value="nested1">🧠 Nested: Flights with > average reservations</option>
      </optgroup>

      <optgroup label="Window-like / TopN / Correlated">
        <option value="top_seats">🏆 Top 3 seats by bookings (simulated)</option>
        <option value="corr1">🔎 Correlated subquery: Customers with multiple distinct flights</option>
      </optgroup>
    </select>

    <label for="manual_query" class="form-label fw-semibold mt-3">Or enter your own SQL (SELECT-only)</label>
    <textarea name="manual_query" id="manual_query" class="form-control mb-3" rows="4" placeholder="SELECT * FROM reservation WHERE Flight_number = 'AI301';"></textarea>

    <button type="submit" class="btn btn-success">Run Query</button>
  </form>

<?php
// ---------- Helper: predefined queries ----------
$queries = [

  // 0 show tables
  'show_tables' => "SHOW TABLES;",

  // 1 - aggregate: reservations per flight
  'agg1' => "SELECT r.Flight_number,
                    COUNT(*) AS total_reservations,
                    MAX(r.Date) AS last_booking_date
             FROM reservation r
             GROUP BY r.Flight_number
             ORDER BY total_reservations DESC
             LIMIT 100;",

  // 2 - aggregate join: total seats by airplane type
  'agg2' => "SELECT t.Type_name,
                    t.Company,
                    SUM(a.Total_no_of_seats) AS total_seats,
                    COUNT(a.Airplane_id) AS airplane_count
             FROM airplane a
             JOIN airplane_type t ON a.Type_name = t.Type_name
             GROUP BY t.Type_name, t.Company
             ORDER BY total_seats DESC;",

  // 3 - j1: reservation + flight
  'j1' => "SELECT r.Flight_number,
                  r.Date,
                  r.Seat_no,
                  r.Customer_name,
                  f.Airline,
                  f.Duration
           FROM reservation r
           LEFT JOIN flight f ON r.Flight_number = f.Flight_number
           ORDER BY r.Date DESC
           LIMIT 200;",

  // 4 - j2: flight -> leg_instance -> airplane -> airplane_type
  'j2' => "SELECT f.Flight_number,
                  f.Airline,
                  li.Leg_no,
                  li.Date AS leg_date,
                  li.Airplane_id,
                  a.Total_no_of_seats,
                  t.Type_name,
                  t.Company
           FROM flight f
           JOIN leg_instance li ON f.Flight_number = li.Flight_number
           LEFT JOIN airplane a ON li.Airplane_id = a.Airplane_id
           LEFT JOIN airplane_type t ON a.Type_name = t.Type_name
           ORDER BY li.Date DESC
           LIMIT 200;",

  // 5 - j3: reservation -> leg_instance -> airports (departure/arrival via flight_leg)
  'j3' => "SELECT r.Flight_number,
                  r.Date,
                  r.Seat_no,
                  dep.Name AS departure_airport,
                  dep.City AS departure_city,
                  arr.Name AS arrival_airport,
                  arr.City AS arrival_city
           FROM reservation r
           JOIN leg_instance li ON r.Flight_number = li.Flight_number AND r.Leg_no = li.Leg_no AND r.Date = li.Date
           JOIN flight_leg fl ON li.Flight_number = fl.Flight_number AND li.Leg_no = fl.Leg_no
           LEFT JOIN airport dep ON fl.Departure_airport_code = dep.Airport_code
           LEFT JOIN airport arr ON fl.Arrival_airport_code = arr.Airport_code
           LIMIT 200;",

  // 6 - union example: flights from two airlines
  'u1' => "(SELECT Flight_number, Airline, 'Air India' AS source FROM flight WHERE Airline LIKE '%Air India%')
           UNION
           (SELECT Flight_number, Airline, 'SpiceJet' AS source FROM flight WHERE Airline LIKE '%SpiceJet%')
           ORDER BY Airline, Flight_number
           LIMIT 200;",

  // 7 - nested: flights having reservations greater than average reservations per flight
  'nested1' => "SELECT Flight_number, cnt
                FROM (
                  SELECT Flight_number, COUNT(*) AS cnt
                  FROM reservation
                  GROUP BY Flight_number
                ) AS sub
                WHERE cnt > (SELECT AVG(cnt2) FROM (SELECT COUNT(*) AS cnt2 FROM reservation GROUP BY Flight_number) AS t)
                ORDER BY cnt DESC;",

  // 8 - top seats by bookings (TopN simulation)
  'top_seats' => "SELECT Seat_no, COUNT(*) AS bookings
                  FROM reservation
                  GROUP BY Seat_no
                  ORDER BY bookings DESC
                  LIMIT 3;",

  // 9 - correlated subquery: customers who booked more than 1 distinct flight
  'corr1' => "SELECT DISTINCT r.Customer_name, r.Email,
                     (SELECT COUNT(DISTINCT r2.Flight_number) FROM reservation r2 WHERE r2.Email = r.Email) AS distinct_flights
              FROM reservation r
              WHERE (SELECT COUNT(DISTINCT r3.Flight_number) FROM reservation r3 WHERE r3.Email = r.Email) > 1
              ORDER BY distinct_flights DESC
              LIMIT 200;"
];

// ---------- Determine which query to run ----------
$query = '';
$manual_query = trim($_POST['manual_query'] ?? '');
$choice = $_POST['query_choice'] ?? '';

if ($choice === 'show_tables') {
    $query = $queries['show_tables'];
} elseif ($choice && isset($queries[$choice])) {
    $query = $queries[$choice];
} elseif ($manual_query) {
    // Allow manual queries only if they start with SELECT
    if (preg_match('/^\s*SELECT\b/i', $manual_query)) {
        $query = $manual_query;
    } else {
        echo "<div class='alert alert-danger'>⚠️ Only SELECT queries are allowed for manual input.</div>";
    }
}

// ---------- Execute and display ----------
if ($query) {
    echo "<h4 class='text-primary mt-3'>🧾 Query to run</h4>";
    echo "<div class='code-block mb-3'>" . htmlspecialchars($query) . "</div>";

    // run (we use mysqli->query because these are read-only selects)
    $result = $conn->query($query);
    if ($result === false) {
        echo "<div class='alert alert-danger'>❌ SQL Error: " . htmlspecialchars($conn->error) . "</div>";
    } else {
        if ($result->num_rows === 0) {
            echo "<div class='alert alert-info'>✅ Query executed successfully — no rows returned.</div>";
        } else {
            echo "<div class='table-responsive mt-3'>";
            echo "<table class='table table-bordered table-striped table-sm'>";
            // header
            echo "<thead class='table-light'><tr>";
            // fetch fields safely
            $fields = $result->fetch_fields();
            foreach ($fields as $f) {
                echo "<th>" . htmlspecialchars($f->name) . "</th>";
            }
            echo "</tr></thead><tbody>";
            // rows
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $val) {
                    echo "<td>" . htmlspecialchars($val ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table></div>";
        }
        // free
        $result->free();
    }
}
?>

</div> <!-- glass-box -->
</body>
</html>
