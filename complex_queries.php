<?php include 'db.php'; include 'header.php'; ?>
<!DOCTYPE html>
<html>
<head>
	<link rel="stylesheet" href="style.css">

  <title>Reports - Complex Queries</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
  <h2>📈 Airline Reports & Analytics</h2>

  <?php
  // 1️⃣ JOIN — Flight details with Airplane type
  echo "<h4 class='mt-4 text-primary'>1. Flight and Airplane Type Details (JOIN)</h4>";
  $q1 = "SELECT f.Flight_number, a.Airplane_id, t.Type_name, t.Company
         FROM leg_instance li
         JOIN airplane a ON li.Airplane_id = a.Airplane_id
         JOIN airplane_type t ON a.Type_name = t.Type_name
         JOIN flight f ON li.Flight_number = f.Flight_number
         LIMIT 10";
  $r1 = $conn->query($q1);
  if ($r1->num_rows > 0) {
      echo "<table class='table table-bordered table-striped'><tr>";
      while ($field = $r1->fetch_field()) echo "<th>{$field->name}</th>";
      echo "</tr>";
      while ($row = $r1->fetch_assoc()) {
          echo "<tr>";
          foreach ($row as $val) echo "<td>$val</td>";
          echo "</tr>";
      }
      echo "</table>";
  }

  // 2️⃣ UNION — Flights either from Air India or SpiceJet
  echo "<h4 class='mt-4 text-primary'>2. Flights from Air India or SpiceJet (UNION)</h4>";
  $q2 = "(SELECT Flight_number, Airline FROM flight WHERE Airline='Air India')
         UNION
         (SELECT Flight_number, Airline FROM flight WHERE Airline='SpiceJet')";
  $r2 = $conn->query($q2);
  if ($r2->num_rows > 0) {
      echo "<table class='table table-bordered table-striped'><tr>";
      while ($field = $r2->fetch_field()) echo "<th>{$field->name}</th>";
      echo "</tr>";
      while ($row = $r2->fetch_assoc()) {
          echo "<tr>";
          foreach ($row as $val) echo "<td>$val</td>";
          echo "</tr>";
      }
      echo "</table>";
  }

  // 3️⃣ GROUP BY — Count reservations per customer
  echo "<h4 class='mt-4 text-primary'>3. Reservations per Customer (GROUP BY)</h4>";
  $q3 = "SELECT Customer_name, COUNT(*) AS total_reservations FROM reservation GROUP BY Customer_name";
  $r3 = $conn->query($q3);
  if ($r3->num_rows > 0) {
      echo "<table class='table table-bordered table-striped'><tr>";
      while ($field = $r3->fetch_field()) echo "<th>{$field->name}</th>";
      echo "</tr>";
      while ($row = $r3->fetch_assoc()) {
          echo "<tr>";
          foreach ($row as $val) echo "<td>$val</td>";
          echo "</tr>";
      }
      echo "</table>";
  }

  // 4️⃣ SUBQUERY — Flights with more than one leg
  echo "<h4 class='mt-4 text-primary'>4. Flights with Multiple Legs (SUBQUERY)</h4>";
  $q4 = "SELECT Flight_number FROM flight_leg 
         WHERE Flight_number IN (SELECT Flight_number FROM flight_leg GROUP BY Flight_number HAVING COUNT(*) > 1)";
  $r4 = $conn->query($q4);
  if ($r4->num_rows > 0) {
      echo "<table class='table table-bordered table-striped'><tr><th>Flight_number</th></tr>";
      while ($row = $r4->fetch_assoc()) echo "<tr><td>{$row['Flight_number']}</td></tr>";
      echo "</table>";
  }
  ?>
</body>
</html>
