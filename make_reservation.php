<?php
// make_reservation.php — updated seatmap & insertion (complete file)
// ... (this is the full file; replace your existing make_reservation.php with this)
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
$username = is_array($_SESSION['user']) ? ($_SESSION['user']['Cname'] ?? ($_SESSION['user']['name'] ?? 'Guest')) : $_SESSION['user'];
$email    = $_SESSION['email'];
function esc($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$prefill_flight = $_GET['flight']  ?? '';
$prefill_leg    = $_GET['leg']     ?? '';
$prefill_date   = $_GET['date']    ?? '';

// AJAX endpoints
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $mode = $_GET['ajax'];
    if ($mode === 'load') {
        $flight = trim($_GET['flight'] ?? '');
        $leg    = trim($_GET['leg'] ?? '');
        $date   = trim($_GET['date'] ?? '');
        if ($flight === '' || $leg === '' || $date === '') {
            echo json_encode(['ok'=>false,'error'=>'Missing flight/leg/date']);
            exit;
        }
        $q = $conn->prepare("SELECT Airplane_id FROM leg_instance WHERE Flight_number=? AND Leg_no=? AND Date=? LIMIT 1");
        $q->bind_param("sis", $flight, $leg, $date);
        $q->execute();
        $res = $q->get_result();
        $airRow = $res->fetch_assoc();
        if (!$airRow || empty($airRow['Airplane_id'])) {
            echo json_encode(['ok'=>true,'airplane'=>'','seats'=>[],'booked'=>[],'layout'=>['cols'=>[],'rows'=>[]]]);
            exit;
        }
        $airplane = $airRow['Airplane_id'];
        $s = $conn->prepare("SELECT Seat_no FROM seat WHERE Airplane_id=? ORDER BY Seat_no");
        $s->bind_param("s", $airplane);
        $s->execute();
        $rs = $s->get_result();
        $seatList = [];
        while ($row = $rs->fetch_assoc()) $seatList[] = $row['Seat_no'];

        // build layout: handle A1 or 1A
        $cols = []; $rows = [];
        foreach ($seatList as $sn) {
            if (preg_match('/^([A-Za-z]+)(\d+)$/', $sn, $m)) {
                $c = strtoupper($m[1]); $r = intval($m[2]);
            } elseif (preg_match('/^(\d+)([A-Za-z]+)$/', $sn, $m)) {
                $c = strtoupper($m[2]); $r = intval($m[1]);
            } else {
                continue;
            }
            $cols[$c] = true; $rows[$r] = true;
        }
        $colOrder = array_keys($cols); sort($colOrder, SORT_STRING);
        $rowOrder = array_keys($rows); sort($rowOrder, SORT_NUMERIC);

        $b = $conn->prepare("SELECT Seat_no FROM reservation WHERE Flight_number=? AND Leg_no=? AND Date=?");
        $b->bind_param("sis", $flight, $leg, $date);
        $b->execute();
        $bres = $b->get_result(); $booked = [];
        while ($rr = $bres->fetch_assoc()) $booked[] = $rr['Seat_no'];

        echo json_encode(['ok'=>true,'airplane'=>$airplane,'seats'=>$seatList,'booked'=>$booked,'layout'=>['cols'=>$colOrder,'rows'=>$rowOrder]]);
        exit;
    } elseif ($mode === 'by_airplane') {
        $plane = trim($_GET['airplane'] ?? '');
        if ($plane === '') { echo json_encode(['ok'=>false,'error'=>'No airplane']); exit; }
        $s = $conn->prepare("SELECT Seat_no FROM seat WHERE Airplane_id=? ORDER BY Seat_no");
        $s->bind_param("s", $plane);
        $s->execute();
        $rs = $s->get_result();
        $seatList = [];
        while ($row = $rs->fetch_assoc()) $seatList[] = $row['Seat_no'];
        // build layout
        $cols = []; $rows = [];
        foreach ($seatList as $sn) {
            if (preg_match('/^([A-Za-z]+)(\d+)$/', $sn, $m)) { $c = strtoupper($m[1]); $r = intval($m[2]); }
            elseif (preg_match('/^(\d+)([A-Za-z]+)$/', $sn, $m)) { $c = strtoupper($m[2]); $r = intval($m[1]); }
            else continue;
            $cols[$c]=true; $rows[$r]=true;
        }
        $colOrder = array_keys($cols); sort($colOrder, SORT_STRING);
        $rowOrder = array_keys($rows); sort($rowOrder, SORT_NUMERIC);
        echo json_encode(['ok'=>true,'airplane'=>$plane,'seats'=>$seatList,'booked'=>[],'layout'=>['cols'=>$colOrder,'rows'=>$rowOrder]]);
        exit;
    }
}

// Process POST reservation
$errors = []; $success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    $flight_number = trim($_POST['flight_number'] ?? '');
    $leg_no        = intval($_POST['leg_no'] ?? 0);
    $date          = trim($_POST['date'] ?? '');
    $airplane_id   = trim($_POST['airplane_id'] ?? '');
    $seat_no       = trim($_POST['seat_no'] ?? '');
    $cphone        = trim($_POST['cphone'] ?? '');
    if ($flight_number === '') $errors[] = "Flight number required.";
    if ($leg_no <= 0) $errors[] = "Leg number required.";
    if ($date === '') $errors[] = "Date required.";
    if ($seat_no === '') $errors[] = "Please select a seat.";
    if ($cphone === '') $errors[] = "Contact phone required.";

    if (empty($errors)) {
        $chk = $conn->prepare("SELECT Airplane_id FROM leg_instance WHERE Flight_number=? AND Leg_no=? AND Date=? LIMIT 1");
        $chk->bind_param("sis", $flight_number, $leg_no, $date);
        $chk->execute();
        $g = $chk->get_result()->fetch_assoc();
        if (!$g) { $errors[] = "Selected flight/leg/date does not exist (leg_instance missing)."; }
        else {
            if (!empty($g['Airplane_id'])) $airplane_id = $g['Airplane_id'];
            else { if (empty($airplane_id)) $errors[] = "No airplane assigned for this leg — contact admin or choose an airplane."; }
        }
    }

    if (empty($errors)) {
        $s = $conn->prepare("SELECT 1 FROM seat WHERE Airplane_id=? AND Seat_no=? LIMIT 1");
        $s->bind_param("ss", $airplane_id, $seat_no); $s->execute();
        $sr = $s->get_result();
        if ($sr->num_rows == 0) $errors[] = "Selected seat ($seat_no) does not exist on airplane $airplane_id.";
    }

    if (empty($errors)) {
        $ck = $conn->prepare("SELECT 1 FROM reservation WHERE Flight_number=? AND Leg_no=? AND Date=? AND Seat_no=? LIMIT 1");
        $ck->bind_param("siss", $flight_number, $leg_no, $date, $seat_no); $ck->execute();
        $cr = $ck->get_result();
        if ($cr->num_rows > 0) $errors[] = "Seat $seat_no is already booked for this flight/leg/date.";
    }

    if (empty($errors)) {
        $ins = $conn->prepare("INSERT INTO reservation (Flight_number, Leg_no, Date, Airplane_id, Seat_no, Customer_name, Cphone, Email, reservation_created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $ins->bind_param("sissssss", $flight_number, $leg_no, $date, $airplane_id, $seat_no, $username, $cphone, $email);
        if (!$ins->execute()) {
            $dberr = $ins->error;
            if (stripos($dberr, 'foreign key') !== false) $errors[] = "Database constraint failed (possible airplane/seat mismatch). Please re-check selection or contact admin.";
            else $errors[] = "Database error: " . $dberr;
        } else {
            $success = true;
            // fetch inserted id if needed
            $inserted_id = $conn->insert_id;
        }
    }
}

// Load dropdowns
$flights   = $conn->query("SELECT Flight_number FROM flight ORDER BY Flight_number");
$airplanes = $conn->query("SELECT Airplane_id FROM airplane ORDER BY Airplane_id");

include 'header.php';
?>
<!doctype html><html><head><meta charset="utf-8"><title>Make Reservation</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* reuse small styles from your UI plus improved seat colors */
body { padding-top:120px; background: linear-gradient(to bottom,#a1c4fd,#c2e9fb); font-family: 'Poppins', sans-serif; }
.container-card { max-width:980px; margin:auto; background: rgba(255,255,255,0.28); backdrop-filter: blur(10px); padding:20px; border-radius:12px; box-shadow: 0 8px 30px rgba(0,0,0,.12); }
.seat { border:1px solid #cbd5e1; padding:6px 8px; margin:4px; border-radius:6px; cursor:pointer; user-select:none; display:inline-block; width:48px; text-align:center; }
.seat.available { background:#ffffff; }
.seat.booked { background:#ef4444; color:#fff; cursor:not-allowed; }
.seat.selected { background:#0ea5a3; color:#fff; }
.seat.header { background:transparent; border:none; cursor:default; font-weight:700; opacity:.9; }
.seat-row { display:flex; align-items:center; margin-bottom:6px; gap:6px; flex-wrap:wrap; }
.small-note { color:#374151; font-size:0.9rem; }
</style>
</head><body>
<div class="container">
  <div class="container-card mt-4">
    <h3 class="text-center mb-3">✈️ Make Reservation</h3>

    <?php if ($success): ?>
      <div class="alert alert-success">Reservation created — redirecting to payment...</div>
      <script>
        setTimeout(() => {
          window.location = "payment.php?flight_number=<?= urlencode($flight_number) ?>&date=<?= urlencode($date) ?>&seat=<?= urlencode($seat_no) ?>&leg=<?= urlencode($leg_no) ?>";
        }, 700);
      </script>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger"><ul><?php foreach($errors as $er) echo "<li>".esc($er)."</li>"; ?></ul></div>
    <?php endif; ?>

    <div class="row">
      <div class="col-md-6">
        <form method="POST" id="reservationForm">
            <label>Flight Number</label>
            <select name="flight_number" id="flight" class="form-select mb-2" required>
                <option value="">Select flight</option>
                <?php while ($r = $flights->fetch_assoc()): ?>
                    <option value="<?= esc($r['Flight_number']) ?>" <?= ($prefill_flight == $r['Flight_number']) ? 'selected' : '' ?>><?= esc($r['Flight_number']) ?></option>
                <?php endwhile; ?>
            </select>

            <label>Leg Number</label>
            <input type="number" id="leg" name="leg_no" class="form-control mb-2" value="<?= esc($prefill_leg) ?>" required>

            <label>Date</label>
            <input type="date" id="date" name="date" class="form-control mb-2" value="<?= esc($prefill_date) ?>" required>

            <label>Airplane (Auto-selected)</label>
            <select id="airplane" name="airplane_id" class="form-select mb-2">
                <option value="">-- select airplane --</option>
                <?php while ($a = $airplanes->fetch_assoc()): ?>
                    <option value="<?= esc($a['Airplane_id']) ?>"><?= esc($a['Airplane_id']) ?></option>
                <?php endwhile; ?>
            </select>

            <label>Seat</label>
            <select id="seatSelect" name="seat_no" class="form-select mb-2" required>
                <option value="">Select seat</option>
            </select>

            <label>Contact Phone</label>
            <input type="text" name="cphone" class="form-control mb-3" required>

            <button class="btn btn-primary w-100" name="reserve" type="submit">🛫 Reserve Now</button>
        </form>
        <div class="small-note mt-2">Airplane is taken from the selected Flight / Leg / Date when available.</div>
      </div>

      <div class="col-md-6">
        <h5>Seat Map — click to pick</h5>
        <div id="seatMap" class="border p-2 rounded" style="min-height:360px; background:white; overflow:auto;">
            <div class="text-muted">Select flight, leg and date to load seats</div>
        </div>
        <div class="mt-2">
            <span class="seat available">Available</span>
            <span class="seat booked">Booked</span>
            <span class="seat selected">Selected</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const $ = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
let seatSet = new Set(), bookedSet = new Set();

async function loadSeats(){
    const flight = $("#flight").value, leg = $("#leg").value, date = $("#date").value;
    if (!flight || !leg || !date) { $("#seatMap").innerHTML = "<div class='text-muted'>Enter flight, leg & date to load seat map</div>"; $("#airplane").value=""; $("#seatSelect").innerHTML="<option value=''>Select seat</option>"; seatSet=new Set(); bookedSet=new Set(); return; }
    try {
        const res = await fetch(`make_reservation.php?ajax=load&flight=${encodeURIComponent(flight)}&leg=${encodeURIComponent(leg)}&date=${encodeURIComponent(date)}`);
        const j = await res.json();
        if (!j.ok) { $("#seatMap").innerHTML = "<div class='text-danger'>Failed to load seat map</div>"; return; }
        if (j.airplane) $("#airplane").value = j.airplane;
        seatSet = new Set(j.seats || []); bookedSet = new Set(j.booked || []);
        const seatSelect = $("#seatSelect"); seatSelect.innerHTML = "<option value=''>Select seat</option>";
        (j.seats || []).forEach(s => { const opt = document.createElement("option"); opt.value = s; opt.textContent = s; if (bookedSet.has(s)) opt.disabled = true; seatSelect.appendChild(opt); });
        renderSeatMap(j.layout, j.seats || [], j.booked || []);
    } catch(e){ console.error(e); $("#seatMap").innerHTML = "<div class='text-danger'>Network error</div>"; }
}

function renderSeatMap(layout, seats, booked){
    const map = $("#seatMap"); map.innerHTML = "";
    if (!layout || !layout.cols || !layout.rows || layout.cols.length===0 || layout.rows.length===0) { map.innerHTML = "<div class='text-muted'>No seat layout available</div>"; return; }
    const cols=layout.cols, rows=layout.rows;
    const header=document.createElement("div"); header.className="seat-row";
    const blank=document.createElement("div"); blank.className="seat header"; blank.style.width="36px"; header.appendChild(blank);
    cols.forEach(c=>{ const h=document.createElement("div"); h.className="seat header"; h.style.width="48px"; h.textContent=c; header.appendChild(h); });
    map.appendChild(header);
    const sset=new Set(seats||[]), bset=new Set(booked||[]);
    rows.forEach(r=>{
        const rowEl=document.createElement("div"); rowEl.className="seat-row";
        const rowLabel=document.createElement("div"); rowLabel.className="seat header"; rowLabel.style.width="36px"; rowLabel.textContent=r; rowEl.appendChild(rowLabel);
        cols.forEach(c=>{
            const name1 = `${c}${r}`; // A1
            const name2 = `${r}${c}`; // 1A
            const exists = sset.has(name1) || sset.has(name2);
            const taken = bset.has(name1) || bset.has(name2);
            const displayName = sset.has(name1) ? name1 : (sset.has(name2) ? name2 : name1);
            const cell = document.createElement("div"); cell.style.width="48px";
            if (!exists) { cell.className="seat header"; cell.style.opacity=0.08; cell.textContent=""; }
            else if (taken) { cell.className="seat booked"; cell.textContent="X"; }
            else {
                cell.className="seat available"; cell.textContent=displayName;
                cell.addEventListener("click", ()=> {
                    $$(".seat.selected").forEach(e=>e.classList.remove("selected"));
                    cell.classList.add("selected");
                    $("#seatSelect").value = displayName;
                });
            }
            rowEl.appendChild(cell);
        });
        map.appendChild(rowEl);
    });
}

$("#flight").addEventListener("change", loadSeats);
$("#leg").addEventListener("input", loadSeats);
$("#date").addEventListener("change", loadSeats);
$("#airplane").addEventListener("change", async ()=>{
    const plane = $("#airplane").value;
    if (!plane) return loadSeats();
    try {
        const res = await fetch(`make_reservation.php?ajax=by_airplane&airplane=${encodeURIComponent(plane)}`);
        const j = await res.json();
        if (!j.ok) { alert("Failed to load seats for selected airplane."); return; }
        seatSet = new Set(j.seats || []); bookedSet = new Set();
        const seatSelect = $("#seatSelect"); seatSelect.innerHTML = "<option value=''>Select seat</option>";
        (j.seats||[]).forEach(s=>{ const opt=document.createElement("option"); opt.value=s; opt.textContent=s; seatSelect.appendChild(opt); });
        renderSeatMap(j.layout, j.seats || [], []);
    } catch(e){ console.error(e); alert("Network error while loading airplane seats."); }
});

$("#seatSelect").addEventListener("change", (e)=>{ const val=e.target.value; if(!val) return; $$(".seat.selected").forEach(el=>el.classList.remove("selected")); $$(".seat.available").forEach(el=>{ if(el.textContent===val) el.classList.add("selected"); }); });

document.getElementById('reservationForm').addEventListener('submit', function(e){
    const seat=$("#seatSelect").value;
    if(!seat){ e.preventDefault(); alert("Please select a seat."); return; }
    if (bookedSet.has(seat)) { e.preventDefault(); alert("That seat is already booked. Choose another."); return; }
});

// auto-load if prefilled
<?php if ($prefill_flight && $prefill_leg && $prefill_date): ?>
setTimeout(loadSeats, 220);
<?php endif; ?>
</script>

</body></html>
