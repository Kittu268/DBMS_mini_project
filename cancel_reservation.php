<?php
include 'header.php';
include 'db.php';

// Ensure user login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Prefill when coming from view_reservations.php
$prefill = [
    "flight_number" => $_GET['flight_number'] ?? "",
    "leg_no"        => $_GET['leg_no'] ?? "",
    "date"          => $_GET['date'] ?? "",
    "seat_no"       => $_GET['seat_no'] ?? ""
];

// Handle Delete (POST)
$status = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $flight = $_POST['flight_number'];
    $leg    = $_POST['leg_no'];
    $date   = $_POST['date'];
    $seat   = $_POST['seat_no'];

    $stmt = $conn->prepare("
        DELETE FROM reservation
        WHERE Flight_number = ? AND Leg_no = ? AND Date = ? AND Seat_no = ?
    ");
    $stmt->bind_param("siss", $flight, $leg, $date, $seat);

    $status = ($stmt->execute()) ? 'success' : 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>❌ Cancel Reservation</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

<style>
body {
    margin: 0;
    padding-top: 140px; 
    font-family: 'Poppins', sans-serif;
    overflow-x: hidden;
}

.glass-card {
    position: relative;
    z-index: 10;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(15px);
    border-radius: 20px;
    padding: 35px;
    max-width: 600px;
    width: 90%;
    margin: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: fadeIn 1.3s ease;
    color: #0d47a1;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(40px);}
    to { opacity: 1; transform: translateY(0);}
}

h2 {
    text-align: center;
    color: #b71c1c;
    font-weight: 700;
    margin-bottom: 25px;
}

.form-control, .form-select {
    background: rgba(255,255,255,0.85);
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.btn-danger {
    background: linear-gradient(90deg, #e53935, #b71c1c);
    border: none;
    font-weight: 600;
}
.btn-danger:hover {
    transform: scale(1.05);
    background: linear-gradient(90deg, #b71c1c, #e53935);
}

.btn-outline-secondary, .btn-outline-primary {
    border-radius: 10px;
}
</style>
</head>

<body>

<div class="glass-card">

    <h2>❌ Cancel a Reservation</h2>

    <form method="POST">

        <!-- Dropdown -->
        <div class="mb-3">
            <label class="form-label fw-bold">Select Reservation</label>
            <select class="form-select" id="reservationDropdown" onchange="fillForm(this)">
                <option value="">🔽 Choose a reservation</option>

                <?php
                $res = $conn->query("SELECT Flight_number, Leg_no, Date, Seat_no, Customer_name FROM reservation");
                while ($row = $res->fetch_assoc()):
                    $val = htmlspecialchars(json_encode($row));
                ?>
                    <option value="<?= $val ?>">
                        <?= $row['Customer_name'] ?> - <?= $row['Flight_number'] ?> (Seat <?= $row['Seat_no'] ?>)
                    </option>
                <?php endwhile; ?>

            </select>
        </div>

        <!-- Auto-Filled Inputs -->
        <input type="text" name="flight_number" id="flight_number"
               value="<?= $prefill['flight_number'] ?>" 
               class="form-control mb-2" placeholder="Flight Number" required>

        <input type="number" name="leg_no" id="leg_no"
               value="<?= $prefill['leg_no'] ?>"
               class="form-control mb-2" placeholder="Leg No" required>

        <input type="date" name="date" id="date"
               value="<?= $prefill['date'] ?>"
               class="form-control mb-2" required>

        <input type="text" name="seat_no" id="seat_no"
               value="<?= $prefill['seat_no'] ?>"
               class="form-control mb-3" placeholder="Seat No" required>

        <button type="submit" class="btn btn-danger w-100">Cancel Reservation</button>

    </form>

    <div class="mt-4 d-flex justify-content-between">
        <a href="index.php" class="btn btn-outline-secondary">🏠 Home</a>
        <a href="view_reservations.php" class="btn btn-outline-primary">📋 View Reservations</a>
    </div>

</div>

<?php if ($status): ?>
<div class="modal fade show" id="statusModal"
    style="display:block; background:rgba(0,0,0,0.5);" aria-modal="true">

    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">

            <?php if ($status === 'success'): ?>
                <h4 class="text-success">✅ Reservation Cancelled</h4>
                <p>Your reservation was successfully removed.</p>
            <?php else: ?>
                <h4 class="text-danger">❌ Error</h4>
                <p>Something went wrong. Try again.</p>
            <?php endif; ?>

            <button class="btn btn-primary mt-3" onclick="closeModal()">OK</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function fillForm(select) {
    if (!select.value) return;
    const data = JSON.parse(select.value);

    document.getElementById('flight_number').value = data.Flight_number;
    document.getElementById('leg_no').value = data.Leg_no;
    document.getElementById('date').value = data.Date;
    document.getElementById('seat_no').value = data.Seat_no;
}

function closeModal() {
    document.getElementById('statusModal').style.display = 'none';
    window.location.href = "cancel_reservation.php";
}
</script>

</body>
</html>
