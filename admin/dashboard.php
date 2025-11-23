<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

/* -------------------------
   Safely declare helper e()
------------------------- */
if (!function_exists('e')) {
    function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* -------------------------
   Dashboard Stats
------------------------- */
$userCount   = (int)($conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'] ?? 0);
$resCount    = (int)($conn->query("SELECT COUNT(*) AS c FROM reservation")->fetch_assoc()['c'] ?? 0);
$flightCount = (int)($conn->query("SELECT COUNT(*) AS c FROM flight")->fetch_assoc()['c'] ?? 0);

$todayReservations = (int)(
    $conn->query("SELECT COUNT(*) AS c FROM reservation WHERE DATE(reservation_created_at)=CURDATE()")
         ->fetch_assoc()['c'] ?? 0
);

/* -------------------------
   Revenue (Sales − Refunds)
------------------------- */
$rev = (float)(
    $conn->query("
        SELECT 
            SUM(CASE WHEN type='sale'   THEN amount ELSE 0 END) -
            SUM(CASE WHEN type='refund' THEN amount ELSE 0 END)
            AS total_revenue
        FROM revenue_log
    ")->fetch_assoc()['total_revenue'] ?? 0
);

$todayRev = (float)(
    $conn->query("
        SELECT 
            SUM(CASE WHEN type='sale'   THEN amount ELSE 0 END) -
            SUM(CASE WHEN type='refund' THEN amount ELSE 0 END)
            AS today_revenue
        FROM revenue_log
        WHERE DATE(created_at)=CURDATE()
    ")->fetch_assoc()['today_revenue'] ?? 0
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Dashboard — Airline System</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link rel="stylesheet" href="assets/admin.css">

<style>
.dashboard-grid {
    display: grid;
    gap: 25px;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}
.stat-card {
    padding: 24px;
    border-radius: 18px;
    background: rgba(255,255,255,0.45);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.25);
    box-shadow: 0 8px 28px rgba(0,0,0,0.08);
    transition: 0.3s;
    position: relative;
}
.stat-card:hover { transform: translateY(-4px); }
.stat-icon { font-size: 46px; }
.stat-value { font-size: 42px; font-weight: 700; }
.stat-title { font-size: 18px; margin-top: 6px; }
.stat-sub { font-size: 14px; color:#555; }

.stat-card::after {
    content: "";
    position: absolute;
    bottom: 0; left: 0;
    height: 6px; width: 100%;
    background: linear-gradient(90deg,#6a11cb,#2575fc);
    border-radius: 0 0 18px 18px;
}

/* TABLE */
.recent-card {
    background: rgba(255,255,255,0.55);
    backdrop-filter: blur(12px);
    border-radius: 18px;
    padding: 25px;
    margin-top: 35px;
}
.table-wrap th { background: linear-gradient(90deg,#6a11cb,#2575fc); color:#fff; }
.btn-sm { padding:4px 8px; font-size:13px; }
</style>
</head>

<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/partials/topnav.php'; ?>

<main class="admin-content">
<h2>📊 Admin Dashboard</h2>

<div class="dashboard-grid">

    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value counter" data-value="<?= $userCount ?>">0</div>
        <div class="stat-title">Users</div>
        <div class="stat-sub">Total registered users</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">🛫</div>
        <div class="stat-value counter" data-value="<?= $flightCount ?>">0</div>
        <div class="stat-title">Flights</div>
        <div class="stat-sub">Active flights</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">🎟️</div>
        <div class="stat-value counter" data-value="<?= $resCount ?>">0</div>
        <div class="stat-title">Reservations</div>
        <div class="stat-sub">Total bookings</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value counter" data-value="<?= $todayReservations ?>">0</div>
        <div class="stat-title">Today</div>
        <div class="stat-sub">Reservations today</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value counter" data-value="<?= $rev ?>">0</div>
        <div class="stat-title">Total Revenue</div>
        <div class="stat-sub">Sales − Refunds</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-value counter" data-value="<?= $todayRev ?>">0</div>
        <div class="stat-title">Today's Revenue</div>
        <div class="stat-sub">Net revenue today</div>
    </div>

</div>

<!-- RECENT BOOKINGS -->
<section class="recent-card">
    <h3>🕒 Recent Reservations</h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Flight</th>
                    <th>Leg</th>
                    <th>Date</th>
                    <th>Seat</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $rs = $conn->query("
                SELECT reservation_id, Flight_number, Leg_no, Date, Seat_no,
                       Customer_name, Cphone, payment_status, fare
                FROM reservation
                ORDER BY reservation_created_at DESC
                LIMIT 12
            ");

            if ($rs->num_rows) {
                while ($row = $rs->fetch_assoc()) {
                    $id = (int)$row['reservation_id'];
                    $fare = (float)$row['fare'];
                    $paid = strtolower($row['payment_status']) === 'paid';

                    echo "<tr>";
                    echo "<td>".e($row['Flight_number'])."</td>";
                    echo "<td>".e($row['Leg_no'])."</td>";
                    echo "<td>".e($row['Date'])."</td>";
                    echo "<td>".e($row['Seat_no'])."</td>";
                    echo "<td>".e($row['Customer_name'])."</td>";
                    echo "<td>".e($row['Cphone'])."</td>";
                    echo "<td>".e($row['payment_status'])."</td>";

                    echo "<td>";
                    if (!$paid) {
                        echo "<button class='btn btn-sm btn-success record-sale'
                                data-id='{$id}' data-amt='{$fare}'>Record Sale</button>";
                    } else {
                        echo "<button class='btn btn-sm btn-warning record-refund'
                                data-id='{$id}' data-amt='{$fare}'>Refund</button>";
                    }
                    echo "</td>";

                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No reservations yet.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</section>

<footer class="admin-footer">
    <small>Airline Admin © <?= date('Y') ?> — Logged in as 
        <strong><?= e($_SESSION['admin_name']) ?></strong>
    </small>
</footer>

</main>
</div>

<script src="assets/admin.js"></script>

<!-- REVENUE ACTIONS -->
<script>
// Sale
document.addEventListener('click', async (e) => {
    const btn = e.target;

    if (btn.classList.contains('record-sale')) {
        const id = btn.dataset.id;
        const amt = btn.dataset.amt;

        if (!confirm("Record sale for Reservation #" + id + "?")) return;

        const sql = `
START TRANSACTION;
UPDATE reservation 
    SET payment_status='Paid'
    WHERE reservation_id=${id};
INSERT INTO revenue_log (reservation_id, amount, type)
    VALUES (${id}, ${amt}, 'sale');
COMMIT;
`;

        await sendRevenueSQL(sql);
    }

    // Refund
    if (btn.classList.contains('record-refund')) {
        const id = btn.dataset.id;
        const amt = btn.dataset.amt;

        if (!confirm("Refund Reservation #" + id + "?")) return;

        const sql = `
START TRANSACTION;
UPDATE reservation 
    SET payment_status='Refunded', cancellation_status='cancelled'
    WHERE reservation_id=${id};
INSERT INTO revenue_log (reservation_id, amount, type)
    VALUES (${id}, ${amt}, 'refund');
COMMIT;
`;

        await sendRevenueSQL(sql);
    }
});

async function sendRevenueSQL(sql) {
    try {
        let res = await fetch("sql_execute.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ sql })
        });

        const j = await res.json();
        if (!j.ok) {
            alert("SQL Error: " + j.error);
            return;
        }

        alert("Success!");
        location.reload();

    } catch (err) {
        alert("Network Error: " + err.message);
    }
}
</script>

</body>
</html>
