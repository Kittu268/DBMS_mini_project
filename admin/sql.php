<?php
// admin/sql.php — SQL WORKBENCH UI (Frontend)
// Requires backend: sql_execute.php, sql_schema.php, sql_format.php, sql_ai_assistant.php, sql_data_api.php, sql_structure_api.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin — SQL Workbench</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- CodeMirror -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/material-darker.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/hint/show-hint.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/addon/hint/sql-hint.min.js"></script>
<!-- Bootstrap Icons -->
<link href="assets/sql.css" rel="stylesheet">
<link href="assets/admin.css" rel="stylesheet">

<style>
    body { background:#f3f4f9; }
</style>
</head>
<body>

<?php include 'partials/topnav.php'; ?>

<div class="d-flex">
<?php include 'partials/sidebar.php'; ?>

<main class="admin-main p-4 w-100">

    <h3 class="mb-2">SQL Workbench</h3>
    <div class="small-muted mb-3">
        Logged in as <strong><?php echo $_SESSION['user']['username'] ?? 'admin'; ?></strong>
        — Full SQL access enabled (multi-statement, destructive).
    </div>

    <div class="sql-wrap">

        <!-- =======================================================================
             LEFT SIDEBAR — SCHEMA + ERD
             ======================================================================= -->
        <aside class="schema-box" id="schema">

    <div class="d-flex justify-content-between align-items-center mb-2">
        <strong>Database Schema</strong>
        <button id="refreshSchema" class="btn btn-sm btn-outline-secondary">Refresh</button>
    </div>

    <div class="small-muted mb-1">Click a table to insert query</div>
    <div id="schemaList">Loading schema...</div>

    <hr>

<strong>ERD Diagram</strong>
<div class="small-muted mb-2">
    Auto-generated (zoom, drag, mini-map, fullscreen)
</div>

<div id="erd">
    <div id="erdWrapper" class="erd-wrapper">
        <!-- Main ERD canvas -->
        <canvas id="erdCanvas" width="2400" height="1800"></canvas>

        <!-- Zoom / fullscreen controls -->
        <div id="erdControls" class="erd-controls">
            <button class="erd-btn" id="erdZoomIn">+</button>
            <button class="erd-btn" id="erdZoomOut">–</button>
            <button class="erd-btn" id="erdFullscreen">⛶</button>
        </div>

        <!-- Mini-map -->
        <canvas id="erdMiniMap" width="220" height="140" class="erd-minimap"></canvas>
    </div>
</div>


</aside>   <!-- ✅ Correct closing tag -->


<!-- =======================================================================
     RIGHT — TABS + EDITORS + RESULTS + HISTORY + AI ASSISTANT
     ======================================================================= -->
<section>

    <!-- Tabs -->
    <div id="tabsBar" class="tabs-bar"></div>

    <!-- Editors (dynamic injected) -->
    <div id="editorsContainer"></div>

            <!-- Buttons -->
            <div class="mt-2 d-flex gap-2">
                <button id="runBtn" class="btn btn-primary">Run (Ctrl+Enter)</button>
                <button id="beautifyBtn" class="btn btn-outline-secondary">Beautify SQL</button>
                <button id="explainBtn" class="btn btn-outline-secondary">Explain SQL</button>

                <div class="ms-auto small-muted d-flex align-items-center gap-3">
    SQL Status: <span id="execStatus">Ready</span>

    <span id="aiStatus" 
          class="badge bg-secondary"
          style="font-size: 12px; padding:6px 10px;">
          Checking AI...
    </span>
    <select id="aiModelSelector"
        class="form-select form-select-sm"
        style="width: 160px; margin-left: 12px;">
    <option value="llama3.2:latest">llama3.2</option>
    <option value="llama3:latest">llama3</option>
    <option value="mistral:latest">mistral</option>
    <option value="qwen2:latest">qwen2</option>
    <option value="phi3:latest">phi3</option>
    <option value="gemma:latest">gemma</option>
</select>

</div>
            </div>

            <!-- Results -->
            <div id="results" class="results-box mt-3">

                <div class="d-flex mb-2 gap-2">
                    <button class="btn btn-sm btn-outline-secondary" onclick="exportCSV()">Export CSV</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="exportJSON()">Export JSON</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="copyResults()">Copy</button>

                    <div class="ms-auto small-muted">
                        Multi-Resultsets supported
                    </div>
                </div>

                <div id="resultsTableWrap" style="max-height:60vh; overflow:auto"></div>
            </div>

            <!-- AI ASSISTANT -->
            <div class="ai-box mt-4" id="ai">

                <strong>AI SQL Assistant (Ollama / HuggingFace)</strong>
                <div class="small-muted mb-2">Describe your query in English</div>

                <textarea id="aiInput" placeholder="Example: find the flight with lowest fare between Mumbai and Delhi"></textarea>

                <button id="aiBtn" class="btn btn-success mt-2">Generate SQL</button>
            </div>

            <!-- History -->
            <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>Query History</strong>
                    <button id="clearHistory" class="btn btn-sm btn-outline-danger">Clear</button>
                </div>
                <div id="historyList" style="max-height:200px; overflow:auto"></div>
            </div>

        </section>

    </div> <!-- sql-wrap -->

</main>
</div>

<!-- Workbench JS -->
<script src="assets/sql.js"></script>
<script src="assets/admin.js"></script>
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

</body>
</html>
