/* ============================================================
   COLLAPSIBLE SIDEBAR CONTROLLER
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("menu-toggle");
    const sidebar = document.querySelector(".sidebar");
    const main = document.querySelector(".admin-main");

    if (btn && sidebar && main) {
        btn.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");
            main.classList.toggle("collapsed");
        });
    }
});


/* ============================================================
   DASHBOARD COUNTERS (runs only on dashboard.php)
============================================================ */
function animateCounters() {
    const counters = document.querySelectorAll(".counter");
    if (!counters.length) return;

    counters.forEach(counter => {
        const target = parseFloat(counter.dataset.value);
        let start = 0;
        const duration = 900;
        const step = target / (duration / 16);

        function update() {
            start += step;
            if (start >= target) {
                counter.innerText = isNaN(target)
                    ? target
                    : target.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                return;
            }
            counter.innerText = Math.floor(start).toLocaleString();
            requestAnimationFrame(update);
        }
        update();
    });
}


/* ============================================================
   GOOGLE CHART (Dashboard only — revenue last 30 days)
============================================================ */
function drawDashboardChart() {
    if (!document.getElementById("revenue_chart")) return; // Not on dashboard

    if (!window._REVENUE_SERIES) return;

    google.charts.load("current", { packages: ["corechart"] });
    google.charts.setOnLoadCallback(() => {
        const raw = window._REVENUE_SERIES;
        const data = new google.visualization.DataTable();
        data.addColumn("string", "Day");
        data.addColumn("number", "Net Revenue");

        raw.forEach(r => data.addRow([r.label, Number(r.net)]));

        const options = {
            legend: { position: "none" },
            chartArea: { left: 60, right: 16, top: 24, bottom: 64 },
            hAxis: { slantedText: true, slantedTextAngle: 45 },
            vAxis: { minValue: 0 },
            colors: ["#2575fc"],
            height: 320
        };

        const chart = new google.visualization.ColumnChart(
            document.getElementById("revenue_chart")
        );

        chart.draw(data, options);
    });
}


/* ============================================================
   REVENUE SQL ACTIONS (Record Sale / Refund)
============================================================ */
async function sendRevenueSQL(sql) {
    try {
        let res = await fetch("sql_execute.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ sql })
        });

        let j = await res.json();
        if (!j.ok) {
            alert("SQL Error: " + j.error);
            return false;
        }
        return true;
    } catch (err) {
        alert("Network Error: " + err.message);
        return false;
    }
}


document.addEventListener("click", async (e) => {
    const btn = e.target;

    // ---- Record Sale ---- //
    if (btn.classList.contains("record-sale")) {
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

        const ok = await sendRevenueSQL(sql);
        if (ok) {
            alert("Sale recorded!");
            location.reload();
        }
    }

    // ---- Refund ---- //
    if (btn.classList.contains("record-refund")) {
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

        const ok = await sendRevenueSQL(sql);
        if (ok) {
            alert("Refund recorded!");
            location.reload();
        }
    }
});


/* ============================================================
   AUTO-REFRESH DASHBOARD (kept exactly from your logic)
============================================================ */
async function refreshDashboard() {
    const grid = document.querySelector(".dashboard-grid");
    if (!grid) return;

    try {
        const res = await fetch("dashboard.php?ajax=1", {
            cache: "no-store"
        });
        const html = await res.text();

        const parser = new DOMParser();
        const dom = parser.parseFromString(html, "text/html");

        const newGrid = dom.querySelector(".dashboard-grid");
        if (!newGrid) return;

        grid.innerHTML = newGrid.innerHTML;

        animateCounters();
        drawDashboardChart();
    } catch (err) {
        console.warn("Dashboard auto-refresh failed:", err);
    }
}


/* ============================================================
   INITIALIZE
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    animateCounters();
    drawDashboardChart();

    if (document.querySelector(".dashboard-grid")) {
        setInterval(refreshDashboard, 20000);
    }
});


window.addEventListener("resize", drawDashboardChart);
