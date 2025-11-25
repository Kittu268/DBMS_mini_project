<?php
// profile.php - Clean fixed profile/dashboard for Airline System
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$disable_background = true; // keep the header background off if header uses it

require_once __DIR__ . '/db.php';
include_once __DIR__ . '/header.php';

// ---------------------- AUTH / SESSION ----------------------
if (!isset($_SESSION['user']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'] ?? '';

// Derive display name from possible session shapes
if (is_array($_SESSION['user'])) {
    $name = $_SESSION['user']['Cname']
        ?? $_SESSION['user']['username']
        ?? $_SESSION['user']['name']
        ?? $email;
} else {
    $name = $_SESSION['user'];
}

// ---------------------- AVATAR UPLOAD ----------------------
$avatar_upload_msg = '';
$avatar_webpath = '';
$avatar_dir = __DIR__ . '/uploads/avatars';
if (!is_dir($avatar_dir)) @mkdir($avatar_dir, 0755, true);

// create a deterministic filename per user
$sanitized_email = preg_replace('/[^a-z0-9_\-\.@]/i', '', $email);
$avatar_filename = 'avatar_' . md5(strtolower($sanitized_email)) . '.jpg';
$avatar_fullpath = $avatar_dir . '/' . $avatar_filename;
$avatar_webpath_default = 'uploads/avatars/' . $avatar_filename;

// Check if DB has avatar column (optional)
$avatar_from_db = null;
$checkColRes = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($checkColRes && $checkColRes->fetch_assoc()) {
    $qav = $conn->prepare("SELECT avatar FROM users WHERE email = ? LIMIT 1");
    $qav->bind_param("s", $email);
    $qav->execute();
    $rav = $qav->get_result()->fetch_assoc();
    if ($rav && !empty($rav['avatar'])) {
        $avatar_from_db = $rav['avatar'];
    }
    $qav->close();
}

// Handle avatar upload form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (!empty($_FILES['avatar_file']['name'])) {
        $f = $_FILES['avatar_file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $avatar_upload_msg = "Upload error (code {$f['error']}).";
        } else {
            // limit 2MB
            if ($f['size'] > 2 * 1024 * 1024) {
                $avatar_upload_msg = "File too large. Max 2MB.";
            } else {
                // use mime_content_type which is available in XAMPP
                $mime = @mime_content_type($f['tmp_name']);
                $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!in_array($mime, $allowed)) {
                    $avatar_upload_msg = "Only JPG/PNG avatars allowed.";
                } else {
                    // If PNG, convert to JPG for consistent storage; otherwise move file
                    if ($mime === 'image/png') {
                        $img = @imagecreatefrompng($f['tmp_name']);
                        if ($img) {
                            @imagejpeg($img, $avatar_fullpath, 85);
                            @imagedestroy($img);
                            $avatar_upload_msg = "Avatar uploaded (converted PNG).";
                        } else {
                            // fallback
                            move_uploaded_file($f['tmp_name'], $avatar_fullpath);
                            $avatar_upload_msg = "Avatar uploaded.";
                        }
                    } else {
                        // For jpeg/jpg
                        move_uploaded_file($f['tmp_name'], $avatar_fullpath);
                        $avatar_upload_msg = "Avatar uploaded.";
                    }

                    // If DB has avatar column, update with webpath
                    if ($checkColRes) {
                        $webpath = $avatar_webpath_default;
                        $upd = $conn->prepare("UPDATE users SET avatar = ? WHERE email = ?");
                        if ($upd) {
                            $upd->bind_param("ss", $webpath, $email);
                            $upd->execute();
                            $upd->close();
                        }
                    }
                }
            }
        }
    } else {
        $avatar_upload_msg = "No file chosen.";
    }
}

// Determine which avatar to show: DB path > uploaded file > fallback initial
$avatar_to_show = null;
if ($avatar_from_db && file_exists(__DIR__ . '/' . $avatar_from_db)) {
    $avatar_to_show = $avatar_from_db;
} elseif (file_exists($avatar_fullpath)) {
    $avatar_to_show = $avatar_webpath_default;
} else {
    $avatar_to_show = null;
}

// ---------------------- FETCH RESERVATIONS & STATS ----------------------
$stmt = $conn->prepare("
    SELECT r.reservation_id, r.Flight_number, r.Leg_no, r.Date, r.Seat_no,
           r.Customer_name, r.Cphone, r.Airplane_id, r.payment_status, r.fare, r.cancellation_status,
           li.Departure_time, li.Arrival_time
    FROM reservation r
    LEFT JOIN leg_instance li
        ON r.Flight_number = li.Flight_number
       AND r.Leg_no = li.Leg_no
       AND r.Date = li.Date
    WHERE r.Email = ?
    ORDER BY r.Date ASC, li.Departure_time ASC
");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$all = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group upcoming/past/cancelled
$now = new DateTime('now');
$upcoming = $past = $cancelled = [];
foreach ($all as $row) {
    $rowDate = DateTime::createFromFormat('Y-m-d', $row['Date']);
    $isCancelled = stripos($row['cancellation_status'] ?? '', 'cancelled') === 0;
    if ($isCancelled) {
        $cancelled[] = $row;
    } else {
        if ($rowDate && $rowDate >= $now) $upcoming[] = $row;
        else $past[] = $row;
    }
}

// Stats
$totalBookings = count($all);
$totalUpcoming = count($upcoming);
$totalPast = count($past);
$totalCancelled = count($cancelled);

// total spent (sales) and refunds
$spent = 0.00;
$refunds = 0.00;
$q1 = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM revenue_log WHERE type = 'sale' AND reservation_id IN (SELECT reservation_id FROM reservation WHERE Email = ?)");
$q1->bind_param("s", $email);
$q1->execute();
$srow = $q1->get_result()->fetch_assoc();
if ($srow) $spent = (float)$srow['s'];
$q1->close();

$q2 = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS r FROM revenue_log WHERE type = 'refund' AND reservation_id IN (SELECT reservation_id FROM reservation WHERE Email = ?)");
$q2->bind_param("s", $email);
$q2->execute();
$rrow = $q2->get_result()->fetch_assoc();
if ($rrow) $refunds = (float)$rrow['r'];
$q2->close();

$paidCount = 0;
foreach ($all as $r) {
    if (strtolower($r['payment_status'] ?? '') === 'paid' && stripos($r['cancellation_status'] ?? '', 'cancelled') !== 0) $paidCount++;
}

// helper esc
function esc($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// Refund color (#0059ff) selected by user
$refund_color = '#0059ff';
?>
<!-- PAGE CONTENT -->
<div class="container" style="padding-top:140px; font-family:Poppins;">

  <div class="p-4" style="
      max-width:1100px;
      margin:auto;
      background:rgba(255,255,255,0.24);
      backdrop-filter:blur(12px);
      border-radius:16px;
      box-shadow:0 8px 30px rgba(0,0,0,0.12);
  ">

    <!-- TOP ROW: Avatar + Dashboard -->
    <div class="d-flex align-items-center mb-3">
        <div class="d-flex align-items-center">
            <!-- Avatar -->
            <div style="width:88px;height:88px;border-radius:999px;overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;">
                <?php if ($avatar_to_show): ?>
                    <img src="<?= esc($avatar_to_show) ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <div style="font-size:28px;color:#2b6cb0;font-weight:700;">
                        <?= strtoupper(substr(esc($name),0,1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ms-3">
                <h3 class="mb-0"><?= esc($name) ?></h3>
                <div class="text-muted"><?= esc($email) ?></div>

                <div class="mt-2">
                    <!-- Avatar upload form -->
                    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
                        <input type="file" name="avatar_file" accept="image/png,image/jpeg" style="display:inline-block;">
                        <button class="btn btn-sm btn-outline-primary" name="upload_avatar" type="submit">Upload Avatar</button>
                    </form>
                    <?php if ($avatar_upload_msg): ?>
                        <div class="small text-success mt-1"><?= esc($avatar_upload_msg) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Dashboard quick stats (aligned) -->
        <div class="ms-auto d-flex gap-4 text-end" style="align-items:center;">
            <div>
                <div class="small text-muted">Total Bookings</div>
                <div class="fw-bold"><?= $totalBookings ?></div>
            </div>
            <div>
                <div class="small text-muted">Upcoming</div>
                <div class="fw-bold text-primary"><?= $totalUpcoming ?></div>
            </div>
            <div>
                <div class="small text-muted">Completed</div>
                <div class="fw-bold"><?= $totalPast ?></div>
            </div>
            <div>
                <div class="small text-muted">Cancelled</div>
                <div class="fw-bold text-danger"><?= $totalCancelled ?></div>
            </div>
            <div>
                <div class="small text-muted">Amount Spent</div>
                <div class="fw-bold">₹<?= number_format($spent,2) ?></div>
            </div>
            <div>
                <div class="small text-muted">Refunds</div>
                <div class="fw-bold" style="color:<?= esc($refund_color) ?>">₹<?= number_format($refunds,2) ?></div>
            </div>
        </div>
    </div>

    <hr>

    <!-- Upcoming -->
    <div class="mb-4">
        <h5 class="mb-3">✈️ Upcoming Flights</h5>
        <?php if (empty($upcoming)): ?>
            <div class="text-muted">No upcoming flights. Book a new flight from <a href="available_flights.php">Available Flights</a>.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($upcoming as $r):
                    $isCancelled = stripos($r['cancellation_status'] ?? '', 'cancelled') === 0;
                    $isPaid = strtolower($r['payment_status'] ?? '') === 'paid';
                    $qrPayload = json_encode([
                        'res' => $r['reservation_id'] ?? null,
                        'flight' => $r['Flight_number'],
                        'date' => $r['Date'],
                        'seat' => $r['Seat_no'],
                    ]);
                    $qrURL = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode($qrPayload);
                ?>
                <div class="col-md-6">
                    <div class="p-3" style="background:white;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.06);">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="mb-0"><?= esc($r['Flight_number']) ?> <small class="text-muted"> • Leg <?= esc($r['Leg_no']) ?></small></h6>
                                        <div class="small text-muted"><?= esc($r['Date']) ?> • <?= esc($r['Departure_time'] ?? '—') ?> → <?= esc($r['Arrival_time'] ?? '—') ?></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div class="fw-bold">Seat <?= esc($r['Seat_no']) ?></div>
                                        <div class="small text-muted"><?= esc($r['Airplane_id'] ?: '—') ?></div>
                                    </div>
                                </div>

                                <div class="mt-2 d-flex align-items-center gap-2">
                                    <?php if ($isCancelled): ?>
                                        <span style="background:#fff0f0;color:#7a1f1f;padding:6px 10px;border-radius:999px;font-weight:600;">Cancelled</span>
                                    <?php elseif ($isPaid): ?>
                                        <span style="background:#e6ffef;color:#0f5132;padding:6px 10px;border-radius:999px;font-weight:600;">Paid</span>
                                    <?php else: ?>
                                        <span style="background:#fff7e6;color:#7a4b00;padding:6px 10px;border-radius:999px;font-weight:600;">Pending</span>
                                    <?php endif; ?>

                                    <div class="small text-muted ms-3">Phone: <?= esc($r['Cphone']) ?></div>
                                </div>

                                <div class="mt-3 d-flex gap-2">
                                    <?php if ($isPaid && !$isCancelled): ?>
                                        <a class="btn btn-sm btn-success"
                                           href="ticket.php?reservation_id=<?= urlencode($r['reservation_id'] ?? '') ?>&flight=<?= urlencode($r['Flight_number']) ?>&leg=<?= urlencode($r['Leg_no']) ?>&date=<?= urlencode($r['Date']) ?>&seat=<?= urlencode($r['Seat_no']) ?>"
                                           target="_blank">📄 Ticket</a>
                                    <?php elseif ($isCancelled): ?>
                                        <button class="btn btn-sm btn-secondary" disabled>Cancelled</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-warning" disabled>Pending Payment</button>
                                    <?php endif; ?>

                                    <?php if (!$isCancelled): ?>
                                        <a class="btn btn-sm btn-outline-danger" href="cancel_reservation.php?reservation_id=<?= urlencode($r['reservation_id'] ?? '') ?>">❌ Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="width:82px;margin-left:12px;display:flex;align-items:center;">
                                <img src="<?= esc($qrURL) ?>" alt="qr" style="width:72px;height:72px;border-radius:8px;">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Past Flights -->
    <div class="mb-4">
        <h5 class="mb-3">🕒 Past Flights</h5>
        <?php if (empty($past)): ?>
            <div class="text-muted">No past flights.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($past as $r):
                    $isCancelled = stripos($r['cancellation_status'] ?? '', 'cancelled') === 0;
                    $isPaid = strtolower($r['payment_status'] ?? '') === 'paid';
                    $qrPayload = json_encode([
                        'res' => $r['reservation_id'] ?? null,
                        'flight' => $r['Flight_number'],
                        'date' => $r['Date'],
                        'seat' => $r['Seat_no'],
                    ]);
                    $qrURL = "https://chart.googleapis.com/chart?chs=120x120&cht=qr&chl=" . urlencode($qrPayload);
                ?>
                <div class="col-md-6">
                    <div class="p-3" style="background:white;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.06);">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?= esc($r['Flight_number']) ?></strong><br>
                                <small class="text-muted"><?= esc($r['Date']) ?> • <?= esc($r['Departure_time'] ?? '—') ?> → <?= esc($r['Arrival_time'] ?? '—') ?></small>
                                <div>Seat <?= esc($r['Seat_no']) ?> • <?= esc($r['Airplane_id'] ?: '—') ?></div>
                            </div>
                            <div style="text-align:right;">
                                <?php if ($isCancelled): ?>
                                    <span style="background:#fff0f0;color:#7a1f1f;padding:6px 10px;border-radius:999px;font-weight:600;">Cancelled</span>
                                <?php elseif ($isPaid): ?>
                                    <span style="background:#e6ffef;color:#0f5132;padding:6px 10px;border-radius:999px;font-weight:600;">Paid</span>
                                <?php else: ?>
                                    <span style="background:#fff7e6;color:#7a4b00;padding:6px 10px;border-radius:999px;font-weight:600;">Pending</span>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <?php if ($isPaid && !$isCancelled): ?>
                                        <a class="btn btn-sm btn-success" href="ticket.php?reservation_id=<?= urlencode($r['reservation_id'] ?? '') ?>" target="_blank">📄 Ticket</a>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2">
                                    <img src="<?= esc($qrURL) ?>" alt="qr" style="width:64px;height:64px;border-radius:8px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cancelled Flights -->
    <div class="mb-4">
        <h5 class="mb-3">❌ Cancelled Flights</h5>
        <?php if (empty($cancelled)): ?>
            <div class="text-muted">No cancelled flights.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($cancelled as $r):
                    $qrPayload = json_encode([
                        'res' => $r['reservation_id'] ?? null,
                        'flight' => $r['Flight_number'],
                        'date' => $r['Date'],
                        'seat' => $r['Seat_no'],
                    ]);
                    $qrURL = "https://chart.googleapis.com/chart?chs=100x100&cht=qr&chl=" . urlencode($qrPayload);
                ?>
                <div class="col-md-6">
                    <div class="p-3" style="background:white;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.06);border-left:6px solid #ff7b7b;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?= esc($r['Flight_number']) ?></strong>
                                <div class="small text-muted"><?= esc($r['Date']) ?> • Seat <?= esc($r['Seat_no']) ?></div>
                            </div>
                            <div>
                                <img src="<?= esc($qrURL) ?>" alt="qr" style="width:56px;height:56px;border-radius:8px;">
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

  </div>
</div>
