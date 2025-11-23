<?php
session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

/*
 * Build metadata for all tables:
 * - columns (Field, Type, Key, Extra)
 * - sample rows (for using real values in WHERE/INSERT)
 */
$tables = [];
$dbMeta = [];

$tablesRes = $conn->query("SHOW TABLES");
if ($tablesRes) {
    while ($row = $tablesRes->fetch_array()) {
        $t = $row[0];
        $tables[] = $t;

        // Columns
        $cols = [];
        $cRes = $conn->query("SHOW COLUMNS FROM `$t`");
        if ($cRes) {
            while ($c = $cRes->fetch_assoc()) {
                $cols[] = [
                    'name'  => $c['Field'],
                    'type'  => $c['Type'],
                    'key'   => $c['Key'],
                    'extra' => $c['Extra'],
                ];
            }
        }

        // Sample rows (for valid values)
        $samples = [];
        $rRes = $conn->query("SELECT * FROM `$t` LIMIT 5");
        if ($rRes) {
            while ($r = $rRes->fetch_assoc()) {
                $samples[] = $r;
            }
        }

        $dbMeta[$t] = [
            'columns' => $cols,
            'samples' => $samples,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Help Guide</title>
<link rel="stylesheet" href="../assets/bootstrap.min.css">
<link rel="stylesheet" href="assets/admin.css">
<link rel="stylesheet" href="assets/sql.css">

<style>
/* Main layout fix */
.main {
    margin-left: 240px !important;  /* same as sidebar width */
    padding: 25px;
}

/* Help buttons */
.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px,1fr));
    gap: 15px;
}

.help-btn {
    background: #f1f5ff;
    border-radius: 10px;
    padding: 18px;
    cursor: pointer;
    border: 1px solid #d0d7ff;
    transition: 0.2s;
}
.help-btn:hover {
    background: #e4ebff;
    transform: translateY(-2px);
}

.help-btn .icon {
    font-size: 28px;
    margin-bottom: 10px;
}

/* Search */
.search-input {
    padding: 10px 15px;
    width: 100%;
    border-radius: 8px;
    border: 1px solid #ccc;
    margin-bottom: 20px;
}

/* Tutorial steps */
.tutorial-step {
    background: #fff;
    padding: 15px;
    border-left: 4px solid #4f46e5;
    margin-bottom: 12px;
    border-radius: 6px;
}
.tutorial-btn {
    margin-top: 10px;
}

/* Quick SQL Generator */
.quick-sql-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 20px rgba(15,23,42,0.06);
}
.quick-sql-card h5 {
    margin-bottom: 8px;
}
.quick-sql-card pre {
    background: #0f172a;
    color: #e5e7eb;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 12px;
    max-height: 260px;
    overflow:auto;
}
.small-muted {
    font-size: 12px;
    color: #6b7280;
}
</style>

<script>
// Expose DB metadata for JS (tables, columns, sample rows)
window.DB_META = <?=
    json_encode($dbMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
?>;
</script>
</head>

<body>
<?php include "partials/topnav.php"; ?>
<?php include "partials/sidebar.php"; ?>

<main class="admin-main">


<h2 class="mb-3">📘 Admin Help & Documentation</h2>

<!-- SEARCH BAR -->
<input id="helpSearch" class="search-input" placeholder="Search help topics...">

<!-- HELP BUTTON GRID -->
<div id="helpGrid" class="help-grid">

    <div class="help-btn" data-title="overview intro basics" onclick="location.href='dashboard.php'">
        <div class="icon">📌</div>
        <strong>Overview</strong>
        <p class="small text-muted">Intro to using the Admin Panel</p>
    </div>

    <div class="help-btn" data-title="sql editor tabs code run autocomplete" onclick="location.href='sql.php'">
        <div class="icon">📝</div>
        <strong>SQL Editor</strong>
        <p class="small text-muted">Tabs, autocomplete, beautify SQL</p>
    </div>

    <div class="help-btn" data-title="ai assistant generate optimize autofix model" onclick="location.href='sql.php#ai'">
        <div class="icon">🤖</div>
        <strong>AI SQL Assistant</strong>
        <p class="small text-muted">Generate SQL using AI</p>
    </div>

    <div class="help-btn" data-title="schema browser tables columns" onclick="location.href='sql.php#schema'">
        <div class="icon">🗄️</div>
        <strong>Schema Browser</strong>
        <p class="small text-muted">Click tables to explore columns</p>
    </div>

    <div class="help-btn" data-title="erd diagram relationships" onclick="location.href='sql.php#erd'">
        <div class="icon">🧩</div>
        <strong>ERD Diagram</strong>
        <p class="small text-muted">View table relationships visually</p>
    </div>

    <div class="help-btn" data-title="export copy csv json" onclick="location.href='sql.php#results'">
        <div class="icon">📤</div>
        <strong>Export Tools</strong>
        <p class="small text-muted">Export CSV, JSON, copy results</p>
    </div>

</div>

<!-- ===========================
     QUICK SQL INSERT GENERATOR
=========================== -->
<div class="mt-5">
    <h4>⚙ Quick SQL Insert Generator</h4>
    <p class="small-muted mb-3">
        Click a <strong>Copy</strong> button to generate a fresh <code>INSERT</code> script
        that matches your <code>airline</code> schema. Then paste it directly into
        <strong>SQL Workbench (sql.php)</strong> and run it.
    </p>

    <div class="row g-3">

        <!-- Flight + Airports + Legs -->
        <div class="col-md-6">
            <div class="quick-sql-card">
                <h5>✈ New Flight + Airports + Legs</h5>
                <p class="small-muted">
                    Creates test <code>airport</code> rows (codes start with X), a <code>flight</code>,
                    and one leg in <code>flight_leg</code>.
                </p>
                <button class="btn btn-sm btn-outline-primary mb-2" id="copyFlightBtn">
                    Copy Flight + Legs INSERT
                </button>
                <pre id="previewFlight"></pre>
            </div>
        </div>

        <!-- Airplane + Type + Seats -->
        <div class="col-md-6">
            <div class="quick-sql-card">
                <h5>🛩 Airplane Type + Airplane + Seats</h5>
                <p class="small-muted">
                    Creates an <code>airplane_type</code>, an <code>airplane</code> and a small seat
                    map in <code>seat</code>. <code>class_id</code> is set to <code>NULL</code>
                    to avoid foreign-key issues if <code>seat_class</code> is empty.
                </p>
                <button class="btn btn-sm btn-outline-primary mb-2" id="copyAirplaneBtn">
                    Copy Airplane + Seats INSERT
                </button>
                <pre id="previewAirplane"></pre>
            </div>
        </div>

        <!-- Schedule (leg_instance) -->
        <div class="col-md-6">
            <div class="quick-sql-card">
                <h5>📆 Full Schedule (leg_instance)</h5>
                <p class="small-muted">
                    Creates airports, an airplane, a flight, one flight leg, and multiple
                    <code>leg_instance</code> rows (different dates).
                </p>
                <button class="btn btn-sm btn-outline-primary mb-2" id="copyScheduleBtn">
                    Copy Schedule INSERT
                </button>
                <pre id="previewSchedule"></pre>
            </div>
        </div>

        <!-- Fare -->
        <div class="col-md-6">
            <div class="quick-sql-card">
                <h5>💰 Fare Setup</h5>
                <p class="small-muted">
                    Ensures a <code>flight</code> exists and adds entries in <code>fare</code>
                    for two <code>seat_class</code> IDs (1 & 2 by default).
                </p>
                <button class="btn btn-sm btn-outline-primary mb-2" id="copyFareBtn">
                    Copy Fare INSERT
                </button>
                <pre id="previewFare"></pre>
            </div>
        </div>

        <!-- ===========================
             NEW: ALL TABLES ACTION CARD
        ============================ -->
        <div class="col-12">
            <div class="quick-sql-card">
                <h5>📦 All Tables — SQL Actions</h5>
                <p class="small-muted">
                    Choose any table, then click an action to generate <code>INSERT</code>,
                    <code>UPDATE</code>, <code>DELETE</code> or <code>ALTER</code> templates.
                    For many tables, real values from existing rows are used to keep
                    foreign keys valid. You can tweak the SQL before running it.
                </p>

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4">
                        <label class="small mb-1">Table</label>
                        <select id="allTableSelect" class="form-select form-select-sm">
                            <option value="">-- Select table --</option>
                            <?php foreach ($tables as $t): ?>
                                <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="small mb-1 d-block">Action</label>
                        <div class="btn-group">
                            <button type="button" id="btnAllInsert"
                                    class="btn btn-sm btn-outline-success">INSERT</button>
                            <button type="button" id="btnAllUpdate"
                                    class="btn btn-sm btn-outline-warning">UPDATE</button>
                            <button type="button" id="btnAllDelete"
                                    class="btn btn-sm btn-outline-danger">DELETE</button>
                            <button type="button" id="btnAllAlter"
                                    class="btn btn-sm btn-outline-secondary">ALTER</button>
                        </div>
                    </div>
                </div>

                <pre id="previewAll"></pre>
            </div>
        </div>

    </div> <!-- row -->
</div> <!-- quick sql -->

<!-- TUTORIAL -->
<div class="mt-4">
    <h4>🎓 Interactive Tutorial</h4>
    <div id="tutorialBox"></div>
    <button id="tutorialBtn" class="btn btn-primary tutorial-btn">Start Tutorial</button>
</div>

<script>
/* ==========================
   SEARCH FILTER
========================== */
document.getElementById("helpSearch").addEventListener("input", function () {
    let q = this.value.toLowerCase();
    document.querySelectorAll(".help-btn").forEach(btn => {
        const keywords = (btn.getAttribute("data-title") || "").toLowerCase();
        btn.style.display = keywords.includes(q) ? "block" : "none";
    });
});

/* ==========================
   TUTORIAL STEPS
========================== */
const steps = [
    { text: "Step 1️⃣: Go to SQL Explorer", page: "sql.php" },
    { text: "Step 2️⃣: Try AI SQL Assistant", page: "sql.php#ai" },
    { text: "Step 3️⃣: Run a Query using ▶ Run", page: "sql.php#editor" },
    { text: "Step 4️⃣: Export results from Results panel", page: "sql.php#results" },
    { text: "🎉 Tutorial completed!", page: "help.php" }
];

let idx = 0;
const box = document.getElementById("tutorialBox");
document.getElementById("tutorialBtn").onclick = () => {
    if (idx >= steps.length) idx = 0;
    box.innerHTML += `<div class='tutorial-step'>${steps[idx].text}</div>`;
    window.location.href = steps[idx].page;
    idx++;
};

/* ==========================
   COMMON HELPERS
========================== */
function randInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function randomLetters(len) {
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    let out = "";
    for (let i = 0; i < len; i++) {
        out += chars[randInt(0, chars.length - 1)];
    }
    return out;
}

function randomFlightNumber() {
    const prefixes = ["6E", "AI", "UK", "SG", "G8", "IX"];
    const p = prefixes[randInt(0, prefixes.length - 1)];
    const num = randInt(100, 999);
    return p + num;
}

function randomAirportCode() {
    // Make "fake" airport codes starting with X to avoid clashing with real ones
    return "X" + randomLetters(2);
}

function randomFutureDate(maxDaysAhead = 30) {
    const d = new Date();
    d.setDate(d.getDate() + randInt(1, maxDaysAhead));
    const y  = d.getFullYear();
    const m  = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${dd}`;
}

function randomAirplaneId() {
    return "AP" + randInt(1000, 9999);
}

function randomTypeName() {
    return "TYPE_" + randomLetters(3) + randInt(10, 99);
}

function sqlEscape(val) {
    if (val === null || val === undefined) return "NULL";
    const s = String(val).replace(/'/g, "''");
    return "'" + s + "'";
}

/* ==========================
   QUICK GENERATORS (CARDS)
========================== */

/* --- 1) Flight + Airports + One Leg --- */
function generateFlightSQL() {
    const flightNo = randomFlightNumber();
    const depCode  = randomAirportCode();
    const arrCode  = randomAirportCode();
    const duration = randInt(60, 180);

    const sql = `
-- Create test airports (INSERT IGNORE to avoid duplicate key errors)
INSERT IGNORE INTO airport (Airport_code, Name, City, State, Country) VALUES
  ('${depCode}', 'Test Airport ${depCode}', 'City ${depCode}', 'State ${depCode}', 'India'),
  ('${arrCode}', 'Test Airport ${arrCode}', 'City ${arrCode}', 'State ${arrCode}', 'India');

-- Create flight
INSERT INTO flight (Flight_number, Airline, Duration)
VALUES ('${flightNo}', 'SampleAir', ${duration});

-- Create a single flight leg
INSERT INTO flight_leg (
  Flight_number, Leg_no,
  Departure_airport_code, Arrival_airport_code,
  Departure_time, Arrival_time
) VALUES
  ('${flightNo}', 1, '${depCode}', '${arrCode}', '09:00:00', '10:30:00');
`.trim();

    return sql;
}

/* --- 2) Airplane Type + Airplane + Seats --- */
function generateAirplaneSQL() {
    const airplaneId = randomAirplaneId();
    const typeName   = randomTypeName();
    const totalSeats = 180;

    const sql = `
-- New airplane type
INSERT INTO airplane_type (Type_name, Max_seats, Company) VALUES
  ('${typeName}', ${totalSeats}, 'Sample Co');

-- New airplane using that type
INSERT INTO airplane (Airplane_id, Total_no_of_seats, Type_name) VALUES
  ('${airplaneId}', ${totalSeats}, '${typeName}');

-- Sample seats for this airplane
-- class_id is set to NULL so it does not break foreign key fk_seat_class
INSERT INTO seat (Airplane_id, Seat_no, class_id) VALUES
  ('${airplaneId}', '1A', NULL),
  ('${airplaneId}', '1B', NULL),
  ('${airplaneId}', '1C', NULL),
  ('${airplaneId}', '2A', NULL),
  ('${airplaneId}', '2B', NULL),
  ('${airplaneId}', '2C', NULL);
`.trim();

    return sql;
}

/* --- 3) Full Schedule with leg_instance --- */
function generateScheduleSQL() {
    const flightNo   = randomFlightNumber();
    const airplaneId = randomAirplaneId();
    const typeName   = randomTypeName();

    const depCode = randomAirportCode();
    const arrCode = randomAirportCode();

    const date1 = randomFutureDate(20);
    const date2 = randomFutureDate(40);

    const seats = 180;

    const sql = `
-- Airports (for leg + leg_instance)
INSERT IGNORE INTO airport (Airport_code, Name, City, State, Country) VALUES
  ('${depCode}', 'Test Airport ${depCode}', 'City ${depCode}', 'State ${depCode}', 'India'),
  ('${arrCode}', 'Test Airport ${arrCode}', 'City ${arrCode}', 'State ${arrCode}', 'India');

-- Airplane type & airplane
INSERT INTO airplane_type (Type_name, Max_seats, Company) VALUES
  ('${typeName}', ${seats}, 'Sample Co');

INSERT INTO airplane (Airplane_id, Total_no_of_seats, Type_name) VALUES
  ('${airplaneId}', ${seats}, '${typeName}');

-- Flight
INSERT INTO flight (Flight_number, Airline, Duration) VALUES
  ('${flightNo}', 'SampleAir', 120);

-- Flight leg definition
INSERT INTO flight_leg (
  Flight_number, Leg_no,
  Departure_airport_code, Arrival_airport_code,
  Departure_time, Arrival_time
) VALUES
  ('${flightNo}', 1, '${depCode}', '${arrCode}', '08:30:00', '10:00:00');

-- Leg instances (different dates)
INSERT INTO leg_instance (
  Flight_number, Leg_no, Date,
  Number_of_available_seats,
  Airplane_id,
  Departure_airport_code, Arrival_airport_code,
  Departure_time, Arrival_time
) VALUES
  ('${flightNo}', 1, '${date1}', ${seats}, '${airplaneId}', '${depCode}', '${arrCode}', '08:30:00', '10:00:00'),
  ('${flightNo}', 1, '${date2}', ${seats}, '${airplaneId}', '${depCode}', '${arrCode}', '08:30:00', '10:00:00');
`.trim();

    return sql;
}

/* --- 4) Fare setup --- */
function generateFareSQL() {
    const flightNo   = randomFlightNumber();
    const duration   = randInt(60, 210);
    const baseEco    = randInt(2500, 4500);
    const baseBus    = baseEco + randInt(2000, 5000);

    const sql = `
-- Ensure flight exists for fare
INSERT INTO flight (Flight_number, Airline, Duration) VALUES
  ('${flightNo}', 'SampleAir', ${duration})
ON DUPLICATE KEY UPDATE Airline = VALUES(Airline), Duration = VALUES(Duration);

-- Fares for different seat classes
-- Change seat_class IDs if your seat_class table uses different ids.
INSERT INTO fare (flight_number, seat_class, base_fare, currency) VALUES
  ('${flightNo}', 1, ${baseEco}.00, 'INR'),
  ('${flightNo}', 2, ${baseBus}.00, 'INR');
`.trim();

    return sql;
}

/* ==========================
   ALL TABLES ACTION GENERATOR
========================== */
function getTableMeta(tableName) {
    if (!window.DB_META) return null;
    return window.DB_META[tableName] || null;
}

function generateAllActionSql(tableName, action) {
    const meta = getTableMeta(tableName);
    if (!meta) {
        return `-- No metadata available for table \`${tableName}\`\nSELECT * FROM \`${tableName}\` LIMIT 10;`;
    }

    const cols = meta.columns || [];
    const samples = meta.samples || [];
    const sample = samples.length
        ? samples[randInt(0, samples.length - 1)]
        : null;

    if (!cols.length) {
        return `-- No columns detected for table \`${tableName}\``;
    }

    // Determine primary key (or first column as fallback)
    let pkCol = cols.find(c => c.key === 'PRI') || cols[0];

    switch (action) {
        case 'insert': {
            const insertCols = cols.filter(c => c.extra !== 'auto_increment');
            const colNames = insertCols.map(c => "`" + c.name + "`").join(", ");

            let values = insertCols.map(c => {
                if (sample && Object.prototype.hasOwnProperty.call(sample, c.name)) {
                    return sqlEscape(sample[c.name]);
                }
                // Fallback placeholder
                return "'VALUE_" + c.name + "'";
            }).join(", ");

            return `
-- INSERT template for \`${tableName}\`
INSERT INTO \`${tableName}\` (${colNames}) VALUES
(${values});
`.trim();
        }

        case 'update': {
            const setCols = cols.filter(c =>
                c.name !== pkCol.name && c.extra !== 'auto_increment'
            );

            let setPart;
            if (setCols.length) {
                setPart = setCols.map(c => {
                    const val = (sample && sample[c.name] !== undefined)
                        ? sqlEscape(sample[c.name])
                        : "'NEW_" + c.name + "'";
                    return "`" + c.name + "` = " + val;
                }).join(", ");
            } else {
                setPart = "`" + cols[0].name + "` = 'NEW_VALUE'";
            }

            const pkVal = (sample && sample[pkCol.name] !== undefined)
                ? sqlEscape(sample[pkCol.name])
                : "'PK_VALUE'";

            return `
-- UPDATE template for \`${tableName}\`
UPDATE \`${tableName}\`
SET ${setPart}
WHERE \`${pkCol.name}\` = ${pkVal}
LIMIT 1;
`.trim();
        }

        case 'delete': {
            const pkVal = (sample && sample[pkCol.name] !== undefined)
                ? sqlEscape(sample[pkCol.name])
                : "'PK_VALUE'";

            return `
-- DELETE template for \`${tableName}\`
DELETE FROM \`${tableName}\`
WHERE \`${pkCol.name}\` = ${pkVal}
LIMIT 1;
`.trim();
        }

        case 'alter': {
            return `
-- ALTER TABLE template for \`${tableName}\` (example operations)

-- 1) Add a new column
ALTER TABLE \`${tableName}\`
ADD COLUMN \`new_column\` VARCHAR(100) NULL;

-- 2) Modify an existing column
-- ALTER TABLE \`${tableName}\`
-- MODIFY COLUMN \`${cols[0].name}\` VARCHAR(255) NOT NULL;

-- 3) Drop a column
-- ALTER TABLE \`${tableName}\`
-- DROP COLUMN \`old_column\`;
`.trim();
        }

        default:
            return `-- Unknown action: ${action}`;
    }
}

/* ==========================
   CLIPBOARD HELPERS
========================== */
async function copyToClipboard(sql) {
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(sql);
            alert("✅ SQL copied to clipboard. Paste into SQL Workbench (sql.php).");
        } else {
            const ta = document.createElement("textarea");
            ta.value = sql;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand("copy");
            document.body.removeChild(ta);
            alert("✅ SQL copied (fallback).");
        }
    } catch (err) {
        console.error("Clipboard copy failed:", err);
        alert("Could not copy automatically. You can manually copy from the preview box.");
    }
}

async function copySqlAndPreview(generatorFn, previewId) {
    const sql = generatorFn();
    const pre = document.getElementById(previewId);
    if (pre) pre.textContent = sql;
    await copyToClipboard(sql);
}

/* --- Bind buttons on load --- */
document.addEventListener("DOMContentLoaded", () => {
    const btnFlight    = document.getElementById("copyFlightBtn");
    const btnAirplane  = document.getElementById("copyAirplaneBtn");
    const btnSchedule  = document.getElementById("copyScheduleBtn");
    const btnFare      = document.getElementById("copyFareBtn");

    if (btnFlight) {
        btnFlight.addEventListener("click", () => {
            copySqlAndPreview(generateFlightSQL, "previewFlight");
        });
        document.getElementById("previewFlight").textContent = generateFlightSQL();
    }

    if (btnAirplane) {
        btnAirplane.addEventListener("click", () => {
            copySqlAndPreview(generateAirplaneSQL, "previewAirplane");
        });
        document.getElementById("previewAirplane").textContent = generateAirplaneSQL();
    }

    if (btnSchedule) {
        btnSchedule.addEventListener("click", () => {
            copySqlAndPreview(generateScheduleSQL, "previewSchedule");
        });
        document.getElementById("previewSchedule").textContent = generateScheduleSQL();
    }

    if (btnFare) {
        btnFare.addEventListener("click", () => {
            copySqlAndPreview(generateFareSQL, "previewFare");
        });
        document.getElementById("previewFare").textContent = generateFareSQL();
    }

    // All Tables action buttons
    const allSelect   = document.getElementById("allTableSelect");
    const previewAll  = document.getElementById("previewAll");
    const btnInsert   = document.getElementById("btnAllInsert");
    const btnUpdate   = document.getElementById("btnAllUpdate");
    const btnDelete   = document.getElementById("btnAllDelete");
    const btnAlter    = document.getElementById("btnAllAlter");

    function runAllAction(action) {
        const table = allSelect.value;
        if (!table) {
            alert("Please select a table first.");
            return;
        }
        const sql = generateAllActionSql(table, action);
        previewAll.textContent = sql;
        copyToClipboard(sql);
    }

    if (btnInsert) btnInsert.addEventListener("click", () => runAllAction("insert"));
    if (btnUpdate) btnUpdate.addEventListener("click", () => runAllAction("update"));
    if (btnDelete) btnDelete.addEventListener("click", () => runAllAction("delete"));
    if (btnAlter)  btnAlter.addEventListener("click",  () => runAllAction("alter"));
});
</script>
<!-- ============================================================
     DYNAMIC SQL GENERATOR FOR AVAILABLE FLIGHTS (USING DB META)
=============================================================== -->
<div class="mt-5">
    <h4>🛫 Dynamic Available Flights — SQL Generator</h4>
    <p class="small-muted mb-2">
        Auto-generates SQL for <b>ADD / UPDATE / DELETE / READ</b>.
        Values change every time based on actual table contents.
        After copying the SQL, you will be redirected to <b>SQL Workbench (sql.php)</b>.
    </p>

    <div class="quick-sql-card">
        <div class="row g-2">
            <div class="col-md-3">
                <button class="btn btn-sm btn-outline-success w-100"
                        onclick="dynamicFlightSQL('add')">ADD</button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-outline-warning w-100"
                        onclick="dynamicFlightSQL('update')">UPDATE</button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-outline-danger w-100"
                        onclick="dynamicFlightSQL('delete')">DELETE</button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-outline-primary w-100"
                        onclick="dynamicFlightSQL('read')">READ</button>
            </div>
        </div>

        <pre id="dynAvailPreview" class="mt-3"></pre>
    </div>
</div>

<script>
// ================================
// Helper: Pick a random row from a table
// ================================
function pickRandom(table) {
    if (!window.DB_META || !window.DB_META[table]) return null;
    const rows = window.DB_META[table].samples || [];
    if (!rows.length) return null;
    return rows[Math.floor(Math.random() * rows.length)];
}

// ================================
// SQL Generator (Dynamic from DB)
// ================================
function generateDynamicFlightSQL(type) {

    const li = pickRandom("leg_instance");
    const fl = pickRandom("flight");
    const flg = pickRandom("flight_leg");

    // random fallback
    function futureDate() {
        const d = new Date();
        d.setDate(d.getDate() + Math.floor(Math.random() * 20) + 1);
        return d.toISOString().split("T")[0];
    }

    // Protect nulls
    const F = (v, d) => v ? v : d;

    // ---------------- ADD ----------------
    if (type === "add") {
        return `
INSERT INTO leg_instance (
    Flight_number, Leg_no, Date,
    Number_of_available_seats, Airplane_id,
    Departure_airport_code, Arrival_airport_code,
    Departure_time, Arrival_time
) VALUES (
    '${F(flg?.Flight_number, "SJ" + Math.floor(Math.random()*900+100))}',
    ${F(flg?.Leg_no, 1)},
    '${futureDate()}',
    ${F(li?.Number_of_available_seats, 180)},
    '${F(li?.Airplane_id, "AP" + Math.floor(Math.random()*9000))}',
    '${F(flg?.Departure_airport_code, "BLR")}',
    '${F(flg?.Arrival_airport_code, "MYS")}',
    '${F(flg?.Departure_time, "10:00:00")}',
    '${F(flg?.Arrival_time, "11:00:00")}'
);`;
    }

    // ---------------- UPDATE ----------------
    if (type === "update") {
        return `
UPDATE leg_instance
SET Number_of_available_seats = ${Math.floor(Math.random()*100 + 50)}
WHERE Flight_number='${F(li?.Flight_number, fl?.Flight_number)}'
  AND Leg_no=${F(li?.Leg_no, 1)}
  AND Date='${F(li?.Date, futureDate())}';

UPDATE flight_price
SET price = ${Math.floor(Math.random()*2000 + 1000)}
WHERE Flight_number='${F(fl?.Flight_number, "SJ202")}';`;
    }

    // ---------------- DELETE ----------------
    if (type === "delete") {
        return `
DELETE FROM leg_instance
WHERE Flight_number='${F(li?.Flight_number, fl?.Flight_number)}'
  AND Leg_no=${F(li?.Leg_no, 1)}
  AND Date='${F(li?.Date, futureDate())}'
LIMIT 1;`;
    }

    // ---------------- READ ----------------
    if (type === "read") {
        return `
SELECT
    f.Flight_number, f.Airline,
    fl.Departure_airport_code AS From_airport,
    fl.Arrival_airport_code AS To_airport,
    li.Date, li.Departure_time, li.Arrival_time,
    li.Airplane_id,
    li.Number_of_available_seats,
    (SELECT COUNT(*) FROM reservation r
     WHERE r.Flight_number=li.Flight_number
       AND r.Leg_no=li.Leg_no
       AND r.Date=li.Date) AS booked,
    fp.price
FROM leg_instance li
JOIN flight f ON li.Flight_number=f.Flight_number
JOIN flight_leg fl ON fl.Flight_number=li.Flight_number
    AND fl.Leg_no=li.Leg_no
LEFT JOIN flight_price fp ON fp.Flight_number=li.Flight_number
ORDER BY li.Date, li.Flight_number;`;
    }

    return "-- unknown action";
}

// ================================
// Copy + Redirect
// ================================
async function dynamicFlightSQL(type) {
    const sql = generateDynamicFlightSQL(type);
    document.getElementById("dynAvailPreview").textContent = sql;

    try {
        await navigator.clipboard.writeText(sql);
        alert("SQL copied! Redirecting to Workbench…");
        window.location.href = "sql.php"; // redirect now
    } catch (err) {
        alert("Copy failed. Manually copy from preview.");
    }
}
</script>
</main> 
</body>
</html>
