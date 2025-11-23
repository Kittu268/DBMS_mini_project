<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Disable background BEFORE header is loaded
$disable_background = true;

include 'db.php';
include 'header.php';

// Check login
if (!isset($_SESSION['user']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];

// ✅ FIXED: PROFILE NAME DETECTION (works for all login types)
if (is_array($_SESSION['user'])) {
    $name = $_SESSION['user']['Cname']
        ?? $_SESSION['user']['username']
        ?? $_SESSION['user']['name']
        ?? 'User';
} else {
    $name = $_SESSION['user'];
}

// Fetch user reservations
$stmt = $conn->prepare("
    SELECT r.Flight_number, r.Leg_no, r.Date, r.Seat_no, r.Customer_name,
           r.Cphone, r.Airplane_id, 
           li.Departure_time, li.Arrival_time
    FROM reservation r
    LEFT JOIN leg_instance li
        ON r.Flight_number = li.Flight_number
       AND r.Leg_no = li.Leg_no
       AND r.Date = li.Date
    WHERE r.Email = ?
    ORDER BY r.Date DESC, li.Departure_time DESC
");
$stmt->bind_param("s", $email);
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- PAGE CONTENT (no new <html> tag needed) -->
<div class="container" style="padding-top:150px; font-family:Poppins;">

    <div class="p-4" style="
        max-width:1000px;
        margin:auto;
        background:rgba(255,255,255,0.22);
        backdrop-filter:blur(12px);
        border-radius:14px;
        box-shadow:0 8px 30px rgba(0,0,0,0.15);
    ">
        <h2>👤 <?= htmlspecialchars($name) ?></h2>
        <p>Email: <?= htmlspecialchars($email) ?></p>

        <hr>

        <h4>📋 Your Reservations</h4>

        <?php if (empty($reservations)): ?>
            <p class="text-muted">You have no reservations yet.</p>

        <?php else: ?>

        <div class="table-responsive mt-3">
            <table class="table table-striped table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Flight</th>
                        <th>Leg</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Seat</th>
                        <th>Phone</th>
                        <th>Ticket</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['Flight_number']) ?></td>
                        <td><?= htmlspecialchars($r['Leg_no']) ?></td>
                        <td><?= htmlspecialchars($r['Date']) ?></td>
                        <td>
                            <?= htmlspecialchars($r['Departure_time'] ?? '—') ?>
                            →
                            <?= htmlspecialchars($r['Arrival_time'] ?? '—') ?>
                        </td>
                        <td><?= htmlspecialchars($r['Seat_no']) ?></td>
                        <td><?= htmlspecialchars($r['Cphone']) ?></td>

                        <td>
                            <a class="btn btn-sm btn-primary"
                                href="ticket.php?flight=<?= urlencode($r['Flight_number']) ?>
                                &leg=<?= urlencode($r['Leg_no']) ?>
                                &date=<?= urlencode($r['Date']) ?>
                                &seat=<?= urlencode($r['Seat_no']) ?>"
                                target="_blank">
                                📄 Ticket
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

            </table>
        </div>

        <?php endif; ?>

    </div>
</div>
