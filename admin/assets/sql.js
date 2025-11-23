// admin/assets/sql.js
// FULL SQL WORKBENCH FRONTEND (upgraded, cleaned)
// Features: multi-statement SQL, AI assistant, ERD V4 (force layout / zoom / drag / mini-map, dark theme),
// schema browser, beautify, tabs, history, resultsets, AI status, model auto-detect.

// ===========================================================
// ERD V4 — Force layout + zoom + drag + mini-map (DARK THEME)
// ===========================================================

// Camera / transform
let erdZoom    = 1;
let erdOffsetX = 0;
let erdOffsetY = 0;
let erdDragging = false;
let erdLastX = 0;
let erdLastY = 0;

// DOM + drawing
let erdCanvas  = null;
let erdCtx     = null;
let erdWrapper = null;
let erdMiniMap = null;

// Layout / graph
let erdLayout  = {};        // tableName -> {x,y,w,h}
let erdNodes   = {};        // tableName -> {x,y,vx,vy}
let erdLinks   = [];        // {from,to}
let erdHighlightTable = null;
let erdMiniMapState   = null; // {minX, minY, scale}

/** Init ERD interactions */
function initERDInteractions() {
    erdCanvas  = document.getElementById("erdCanvas");
    erdWrapper = document.getElementById("erdWrapper");
    erdMiniMap = document.getElementById("erdMiniMap");

    if (!erdCanvas) return;

    erdCtx = erdCanvas.getContext("2d");
    erdCanvas.style.cursor = "grab";

    // Mouse drag start
    erdCanvas.addEventListener("mousedown", (e) => {
        erdDragging = true;
        erdCanvas.style.cursor = "grabbing";
        erdLastX = e.clientX;
        erdLastY = e.clientY;
    });

    // Mouse drag end
    ["mouseup", "mouseleave"].forEach(ev =>
        erdCanvas.addEventListener(ev, () => {
            erdDragging = false;
            erdCanvas.style.cursor = "grab";
        })
    );

    // Mouse drag move
    erdCanvas.addEventListener("mousemove", (e) => {
        if (!erdDragging) return;

        erdOffsetX += e.clientX - erdLastX;
        erdOffsetY += e.clientY - erdLastY;

        erdLastX = e.clientX;
        erdLastY = e.clientY;

        renderERD();
    });

    // Wheel zoom
    erdCanvas.addEventListener("wheel", (e) => {
        e.preventDefault();

        const oldZoom = erdZoom;
        const zoomIntensity = 0.12;

        erdZoom += (e.deltaY < 0 ? zoomIntensity : -zoomIntensity);
        erdZoom = Math.max(0.35, Math.min(erdZoom, 3));

        const rect = erdCanvas.getBoundingClientRect();
        const mx = e.clientX - rect.left;
        const my = e.clientY - rect.top;

        erdOffsetX -= mx / oldZoom - mx / erdZoom;
        erdOffsetY -= my / oldZoom - my / erdZoom;

        renderERD();
    });

    // Click highlight + center
    erdCanvas.addEventListener("click", (e) => {
        if (!ERDData || !Object.keys(erdLayout).length) return;
        const { x, y } = erdScreenToWorld(e.clientX, e.clientY);
        const tbl = erdTableAt(x, y);
        if (tbl) {
            erdHighlightTable = tbl;
            erdCenterOnTable(tbl);
            renderERD();
        }
    });

    // Mini-map click → center
    if (erdMiniMap) {
        erdMiniMap.addEventListener("click", (e) => {
            if (!erdMiniMapState || !erdCanvas) return;
            const rect = erdMiniMap.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;

            const { minX, minY, scale } = erdMiniMapState;
            const worldX = minX + (mx - 8) / scale;
            const worldY = minY + (my - 8) / scale;

            erdOffsetX = erdCanvas.width / 2 - worldX * erdZoom;
            erdOffsetY = erdCanvas.height / 2 - worldY * erdZoom;
            renderERD();
        });
    }

    // Zoom buttons + fullscreen
    const zoomInBtn  = document.getElementById("erdZoomIn");
    const zoomOutBtn = document.getElementById("erdZoomOut");
    const fullBtn    = document.getElementById("erdFullscreen");

    zoomInBtn  && (zoomInBtn.onclick  = erdZoomIn);
    zoomOutBtn && (zoomOutBtn.onclick = erdZoomOut);
    fullBtn    && (fullBtn.onclick    = erdToggleFullscreen);
}

/** World <-> Screen helpers */
function erdScreenToWorld(sx, sy) {
    if (!erdCanvas) return { x: 0, y: 0 };
    const rect = erdCanvas.getBoundingClientRect();
    const x = (sx - rect.left - erdOffsetX) / erdZoom;
    const y = (sy - rect.top  - erdOffsetY) / erdZoom;
    return { x, y };
}

function erdCenterOnTable(name) {
    if (!erdCanvas) return;
    const b = erdLayout[name];
    if (!b) return;

    const cx = b.x + b.w / 2;
    const cy = b.y + b.h / 2;
    const viewW = erdCanvas.width;
    const viewH = erdCanvas.height;

    erdOffsetX = viewW / 2 - cx * erdZoom;
    erdOffsetY = viewH / 2 - cy * erdZoom;
}

function erdTableAt(x, y) {
    for (const name in erdLayout) {
        const b = erdLayout[name];
        if (x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h) return name;
    }
    return null;
}

/** Smooth zoom buttons */
function erdSmoothZoom(targetZoom) {
    if (!erdCanvas) return;
    const start = erdZoom;
    const end   = targetZoom;
    const steps = 10;
    let current = 0;

    const cx = erdCanvas.width / 2;
    const cy = erdCanvas.height / 2;

    const animate = () => {
        current++;
        const t = current / steps;
        erdZoom = start + (end - start) * t;

        // keep centered
        erdOffsetX = cx - (cx * erdZoom);
        erdOffsetY = cy - (cy * erdZoom);

        renderERD();
        if (current < steps) requestAnimationFrame(animate);
    };
    animate();
}

function erdZoomIn() {
    erdSmoothZoom(Math.min(erdZoom + 0.25, 3));
}

function erdZoomOut() {
    erdSmoothZoom(Math.max(erdZoom - 0.25, 0.35));
}

function erdToggleFullscreen() {
    if (!erdWrapper) return;
    if (!document.fullscreenElement) {
        erdWrapper.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen().catch(() => {});
    }
}

/* ===========================================================
   ERD DATA LOAD + FORCE LAYOUT
   =========================================================== */

async function loadERD() {
    try {
        const res = await fetch("sql_erd.php");
        const j = await res.json();
        if (!j.ok) return;
        ERDData = j.erd; // {tables:[...], relations:[...]}

        buildERDGraph();
        runERDLayout();
        renderERD();
    } catch (err) {
        console.warn("ERD load failed:", err);
    }
}

/** Build graph nodes + links from ERDData */
function buildERDGraph() {
    erdNodes = {};
    erdLinks = [];

    if (!ERDData || !Array.isArray(ERDData.tables)) return;

    const tables = ERDData.tables;
    const W = 2000;
    const H = 1400;

    // Create nodes at pseudo-random spread
    tables.forEach((tbl, i) => {
        erdNodes[tbl.name] = {
            x: 300 + (i * 150) % W,
            y: 200 + ((i * 180) % H),
            vx: 0,
            vy: 0
        };
    });

    if (Array.isArray(ERDData.relations)) {
        ERDData.relations.forEach(rel => {
            if (erdNodes[rel.from_table] && erdNodes[rel.to_table]) {
                erdLinks.push({ from: rel.from_table, to: rel.to_table });
            }
        });
    }
}

/** Simple force-directed layout */
function runERDLayout() {
    const nodes = erdNodes;
    const links = erdLinks;
    const names = Object.keys(nodes);
    const ITER = 300;
    const repulsion = 30000;
    const springLen = 260;
    const springK  = 0.02;
    const damping  = 0.85;

    if (!names.length) return;

    for (let it = 0; it < ITER; it++) {
        // Repulsion
        for (let i = 0; i < names.length; i++) {
            const a = nodes[names[i]];
            for (let j = i + 1; j < names.length; j++) {
                const b = nodes[names[j]];
                let dx = a.x - b.x;
                let dy = a.y - b.y;
                let dist = Math.sqrt(dx*dx + dy*dy) + 0.01;
                let force = repulsion / (dist * dist);
                let fx = force * dx / dist;
                let fy = force * dy / dist;

                a.vx += fx;
                a.vy += fy;
                b.vx -= fx;
                b.vy -= fy;
            }
        }

        // Springs
        links.forEach(l => {
            const a = nodes[l.from];
            const b = nodes[l.to];
            if (!a || !b) return;
            let dx = b.x - a.x;
            let dy = b.y - a.y;
            let dist = Math.sqrt(dx*dx + dy*dy) + 0.01;
            let force = springK * (dist - springLen);
            let fx = force * dx / dist;
            let fy = force * dy / dist;

            a.vx += fx;
            a.vy += fy;
            b.vx -= fx;
            b.vy -= fy;
        });

        // Integrate
        names.forEach(name => {
            const n = nodes[name];
            n.vx *= damping;
            n.vy *= damping;
            n.x  += n.vx * 0.1;
            n.y  += n.vy * 0.1;
        });
    }

    // Normalize position to center area
    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    names.forEach(name => {
        const n = nodes[name];
        if (n.x < minX) minX = n.x;
        if (n.x > maxX) maxX = n.x;
        if (n.y < minY) minY = n.y;
        if (n.y > maxY) maxY = n.y;
    });

    const width  = maxX - minX || 1;
    const height = maxY - minY || 1;
    const targetW = 1600;
    const targetH = 1000;

    names.forEach(name => {
        const n = nodes[name];
        n.x = ((n.x - minX) / width)  * targetW + 100;
        n.y = ((n.y - minY) / height) * targetH + 80;
    });
}

/* ===========================================================
   RENDER ERD + MINIMAP (DARK THEME)
   =========================================================== */

function renderERD() {
    const canvas = erdCanvas || document.getElementById("erdCanvas");
    if (!canvas || !ERDData) return;
    const ctx = canvas.getContext("2d");

    // reset transform + dark background
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Dark background like MySQL Workbench dark
    ctx.fillStyle = "#020617";
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // apply camera
    ctx.setTransform(erdZoom, 0, 0, erdZoom, erdOffsetX, erdOffsetY);
    ctx.font = "12px system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    ctx.textBaseline = "top";

    erdLayout = {};

    const tables = ERDData.tables || [];

    // Draw relations first (lines)
    if (Array.isArray(ERDData.relations)) {
        ctx.lineWidth = 1.2 / erdZoom;
        ctx.strokeStyle = "#4b5563"; // soft gray line on dark

        ERDData.relations.forEach(rel => {
            const fromNode = erdNodes[rel.from_table];
            const toNode   = erdNodes[rel.to_table];
            if (!fromNode || !toNode) return;

            const ax = fromNode.x;
            const ay = fromNode.y;
            const bx = toNode.x;
            const by = toNode.y;

            const midX = (ax + bx) / 2;

            ctx.beginPath();
            ctx.moveTo(ax, ay);
            ctx.bezierCurveTo(midX, ay, midX, by, bx, by);
            ctx.stroke();

            // small arrow circle
            ctx.beginPath();
            ctx.arc(bx, by, 3 / erdZoom, 0, Math.PI * 2);
            ctx.fillStyle = "#e5e7eb";
            ctx.fill();
        });
    }

    // Draw table blocks (use backend color if provided)
    tables.forEach(tbl => {
        const cols = tbl.columns || [];
        const node = erdNodes[tbl.name] || { x: 100, y: 100 };

        const w = 260;
        const h = 30 + cols.length * 18 + 10;

        const x = node.x - w / 2;
        const y = node.y - 18;

        erdLayout[tbl.name] = { x, y, w, h };

        const isActive = (tbl.name === erdHighlightTable);

        // Use backend-sent color if available, else previous logic
        const baseColor = (tbl.color || (isActive ? "#1d4ed8" : "#0f172a"));
        const headerColor = (tbl.color ? shadeColor(tbl.color, -18) : (isActive ? "#2563eb" : "#111827"));

        // card background (dark / or colored)
        ctx.fillStyle = baseColor;
        roundRect(ctx, x, y, w, h, 8, true, false);

        // header band
        ctx.fillStyle = headerColor;
        roundRect(ctx, x, y, w, 26, 8, true, false);

        // table name
        ctx.fillStyle = "#e5e7eb";
        ctx.fillText(tbl.name, x + 10, y + 6);

        // columns
        let cy = y + 30;
        cols.forEach(c => {
            const colName = c.name || c.COLUMN_NAME || "?";
            const colType = c.type || c.COLUMN_TYPE || "";
            ctx.fillStyle = "#cbd5f5";
            ctx.fillText("• " + colName + (colType ? " (" + colType + ")" : ""), x + 10, cy);
            cy += 18;
        });
    });

    renderERDMiniMap();
}

/** Shade a hex color lighter/darker by percent (-100..100). Returns hex */
function shadeColor(hex, percent) {
    try {
        let c = hex.replace('#','');
        if (c.length === 3) c = c.split('').map(s=>s+s).join('');
        const num = parseInt(c,16);
        let r = (num >> 16) + percent;
        let g = ((num >> 8) & 0x00FF) + percent;
        let b = (num & 0x0000FF) + percent;
        r = Math.max(Math.min(255, r), 0);
        g = Math.max(Math.min(255, g), 0);
        b = Math.max(Math.min(255, b), 0);
        return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    } catch (e) {
        return hex;
    }
}

/** Mini-map rendering (dark) */
function renderERDMiniMap() {
    if (!erdMiniMap || !Object.keys(erdLayout).length) return;
    const mctx = erdMiniMap.getContext("2d");
    const W = erdMiniMap.width;
    const H = erdMiniMap.height;

    mctx.setTransform(1,0,0,1,0,0);
    mctx.clearRect(0, 0, W, H);

    // Compute world bounds of layout
    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    for (const name in erdLayout) {
        const b = erdLayout[name];
        if (b.x < minX) minX = b.x;
        if (b.y < minY) minY = b.y;
        if (b.x + b.w > maxX) maxX = b.x + b.w;
        if (b.y + b.h > maxY) maxY = b.y + b.h;
    }
    if (!isFinite(minX) || !isFinite(minY)) return;

    const padding = 8;
    const worldW = maxX - minX || 1;
    const worldH = maxY - minY || 1;
    const scale = Math.min(
        (W - 2 * padding) / worldW,
        (H - 2 * padding) / worldH
    );

    erdMiniMapState = { minX, minY, scale };

    function worldToMini(x, y) {
        return {
            mx: padding + (x - minX) * scale,
            my: padding + (y - minY) * scale
        };
    }

    // Background (dark)
    mctx.fillStyle = "#020617";
    mctx.fillRect(0, 0, W, H);
    mctx.strokeStyle = "#4b5563";
    mctx.strokeRect(0.5, 0.5, W - 1, H - 1);

    // Relations
    if (Array.isArray(ERDData.relations)) {
        mctx.strokeStyle = "#6b7280";
        mctx.lineWidth = 1;
        ERDData.relations.forEach(rel => {
            const a = erdLayout[rel.from_table];
            const b = erdLayout[rel.to_table];
            if (!a || !b) return;

            const aCenter = worldToMini(a.x + a.w/2, a.y + a.h/2);
            const bCenter = worldToMini(b.x + b.w/2, b.y + b.h/2);

            mctx.beginPath();
            mctx.moveTo(aCenter.mx, aCenter.my);
            mctx.lineTo(bCenter.mx, bCenter.my);
            mctx.stroke();
        });
    }

    // Tables
    for (const name in erdLayout) {
        const b = erdLayout[name];
        const p = worldToMini(b.x + b.w/2, b.y + 14);
        const isActive = (name === erdHighlightTable);

        mctx.beginPath();
        mctx.arc(p.mx, p.my, isActive ? 4 : 3, 0, Math.PI * 2);
        mctx.fillStyle = isActive ? "#3b82f6" : "#e5e7eb";
        mctx.fill();
    }

    // Viewport rectangle (what main canvas sees)
    if (erdCanvas) {
        const vx1 = -erdOffsetX / erdZoom;
        const vy1 = -erdOffsetY / erdZoom;
        const vx2 = (erdCanvas.width  - erdOffsetX) / erdZoom;
        const vy2 = (erdCanvas.height - erdOffsetY) / erdZoom;

        const p1 = worldToMini(vx1, vy1);
        const p2 = worldToMini(vx2, vy2);

        const rx = Math.min(p1.mx, p2.mx);
        const ry = Math.min(p1.my, p2.my);
        const rw = Math.abs(p2.mx - p1.mx);
        const rh = Math.abs(p2.my - p1.my);

        mctx.strokeStyle = "#3b82f6";
        mctx.lineWidth = 1;
        mctx.strokeRect(rx, ry, rw, rh);
    }
}

/** Rounded rectangle helper */
function roundRect(ctx, x, y, w, h, r, fill, stroke) {
    if (!r) r = 5;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
    if (fill) ctx.fill();
    if (stroke) ctx.stroke();
}

/* ===========================================================
   GLOBAL STATE
   =========================================================== */

let CM = {};                  // CodeMirror instances per tab
let activeTab = 0;
let Tabs = [];
let LatestResults = [];       // for export
let Schema = {};              // loaded from sql_schema.php
let ERDData = null;           // from sql_erd.php
let structureCache = {};      // cache per-table structure UI
let AI_MODELS = [];           // fetched from Ollama
const DEFAULT_MODEL = localStorage.getItem("AI_MODEL") || "llama3.2:latest";

/* ===========================================================
   Dashboard Stats Refresher (NEW)
   - Fetches read-only summary SQL via sql_execute.php and updates
     .stat-value.counter elements (data-value attr used as source)
   =========================================================== */

/*
  Notes:
  - We run a multi-statement SQL query. The server-side sql_execute.php already supports multi-statement.
  - We expect sql_execute.php to return resultsets in j.results[] in order.
  - If your sql_execute.php doesn't allow these queries, change endpoint or adjust SQL.
*/

async function refreshDashboardStats() {
    // Check if dashboard DOM exists
    const statEls = document.querySelectorAll('.stat-value.counter[data-value]');
    if (!statEls || statEls.length === 0) return;

    // Multi-statement SQL: 6 queries, results returned in order
    const sql = `
SELECT COUNT(*) AS value, 'users' AS keyname FROM users;
SELECT COUNT(*) AS value, 'flights' AS keyname FROM flight;
SELECT COUNT(*) AS value, 'reservations' AS keyname FROM reservation;
SELECT COUNT(*) AS value, 'todayReservations' AS keyname FROM reservation WHERE DATE(reservation_created_at)=CURDATE();
SELECT 
    (COALESCE(SUM(CASE WHEN type='sale' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN type='refund' THEN amount ELSE 0 END),0)
    ) AS value,
    'totalRevenue' AS keyname
FROM revenue_log;
SELECT 
    (COALESCE(SUM(CASE WHEN type='sale' THEN amount ELSE 0 END),0) - COALESCE(SUM(CASE WHEN type='refund' THEN amount ELSE 0 END),0)
    ) AS value,
    'todayRevenue' AS keyname
FROM revenue_log WHERE DATE(created_at)=CURDATE();
`;

    try {
        const res = await fetch('sql_execute.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sql })
        });
        const j = await res.json();
        if (!j || !j.ok) {
            // silently ignore but log
            console.warn('refreshDashboardStats: failed', j && j.error);
            return;
        }

        const results = j.results || [];
        // map resultsets -> keyname:value
        const map = {};
        results.forEach(r => {
            if (r.type === 'resultset' || r.type === 'result') {
                const rows = r.rows || [];
                if (rows.length && rows[0].keyname !== undefined) {
                    map[rows[0].keyname] = rows[0].value;
                } else if (rows.length && Object.keys(rows[0]).length === 1) {
                    // fallback: first column
                    const k = Object.keys(rows[0])[0];
                    map[k] = rows[0][k];
                }
            }
        });

        // update DOM: find each .stat-value.counter and update by mapping
        statEls.forEach(el => {
            const current = parseFloat(el.getAttribute('data-value') || '0') || 0;
            let newVal = current;
            const title = el.closest('.stat-card') && el.closest('.stat-card').querySelector('.stat-title');
            const label = title ? (title.textContent || '').trim().toLowerCase() : '';

            // mapping by stat-title text or by known data-key attributes if present
            // Prefer explicit data-key attribute on parent card (optional)
            const parent = el.closest('.stat-card');
            const dataKey = parent && parent.getAttribute('data-key');

            if (dataKey && map[dataKey] !== undefined) {
                newVal = map[dataKey];
            } else {
                // try matching by common labels
                if (label.includes('user')) newVal = map['users'] ?? newVal;
                else if (label.includes('flight')) newVal = map['flights'] ?? newVal;
                else if (label.includes('reservation') && label.includes('today')) newVal = map['todayReservations'] ?? newVal;
                else if (label.includes('reservation')) newVal = map['reservations'] ?? newVal;
                else if (label.includes('total revenue') || label.includes('revenue') && !label.includes("today")) newVal = map['totalRevenue'] ?? newVal;
                else if (label.includes("today's revenue") || label.includes("today revenue") || label.includes('today')) newVal = map['todayRevenue'] ?? newVal;
            }

            // if numeric currency value, format to 2 decimals
            if (typeof newVal === 'number' && (label.includes('revenue') || label.includes('total revenue') || label.includes('today'))) {
                el.setAttribute('data-value', String(newVal));
                animateCounters(el, newVal, { decimals: 2, prefix: '₹' });
            } else {
                el.setAttribute('data-value', String(newVal));
                animateCounters(el, newVal, { decimals: 0, prefix: '' });
            }
        });

    } catch (err) {
        console.error('refreshDashboardStats error', err);
    }
}

/** Animate a single counter element from current shown value to new value */
function animateCounters(el, targetValue, opts = {}) {
    const decimals = opts.decimals ?? 0;
    const prefix = opts.prefix ?? '';
    const duration = (opts.durationMs || 900);
    // parse current displayed value
    let start = parseFloat(el.textContent.replace(/[^\d.-]/g,'') || el.getAttribute('data-value') || '0');
    if (!isFinite(start)) start = 0;
    const end = Number(targetValue) || 0;

    const steps = Math.max(8, Math.round(duration / 40));
    let frame = 0;

    const step = () => {
        frame++;
        const t = frame / steps;
        // easeOutQuad
        const eased = 1 - (1 - t) * (1 - t);
        const val = start + (end - start) * eased;
        el.textContent = prefix + Number(val).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        if (frame < steps) requestAnimationFrame(step);
        else {
            el.textContent = prefix + Number(end).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        }
    };
    requestAnimationFrame(step);
}

/* ===========================================================
   INIT
   =========================================================== */

document.addEventListener("DOMContentLoaded", () => {
  initERDInteractions();          // ERD zoom/drag/click
  initEditors();
  loadSchema();
  loadHistory();
  initButtons();
  loadERD();
  checkAIStatusAndPopulateModels();
  setInterval(checkAIStatusAndPopulateModels, 5000); // poll AI status & models

  // Schema refresh button
  const refreshSchemaBtn = document.getElementById("refreshSchema");
  if (refreshSchemaBtn) refreshSchemaBtn.onclick = loadSchema;

  // Auto-refresh dashboard stats (NEW): update on load + every 15s
  refreshDashboardStats();
  setInterval(refreshDashboardStats, 15000);

  // Auto-scroll to hash anchors (for help links, etc.)
  const hash = window.location.hash;
  if (hash) {
    const el = document.querySelector(hash);
    if (el) {
      setTimeout(() => {
        el.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 400);
    }
  }
});
/* ===========================================================
   EDITOR / TABS
   =========================================================== */

function initEditors() {
  createTab("-- Type SQL here...\nSELECT * FROM reservation LIMIT 50;");
  switchTab(0);
}

function createTab(initialSQL) {
  const idx = Tabs.length;
  Tabs.push({ title: "Query " + (idx + 1), sql: initialSQL });

  const wrap = document.createElement("div");
  wrap.className = "sql-editor-wrap";
  wrap.style.height = "260px";
  document.getElementById("editorsContainer").appendChild(wrap);

  const cm = CodeMirror(wrap, {
    value: initialSQL,
    mode: "text/x-sql",
    lineNumbers: true,
    theme: "material-darker",
    extraKeys: {
      "Ctrl-Space": "autocomplete",
      "Ctrl-Enter": runSQL,
      "Ctrl-B": beautifySQL
    }
  });

  // Schema-aware autocomplete
  cm.on("keyup", (inst, ev) => {
    const token = inst.getTokenAt(inst.getCursor());
    const word = token.string || "";
    if (word && !/\s/.test(word) && ![9,13,27].includes(ev.keyCode)) {
      CodeMirror.showHint(inst, schemaHint, { completeSingle: false });
    }
  });

  CM[idx] = cm;
  renderTabs();
  return idx;
}

function renderTabs() {
  const bar = document.getElementById("tabsBar");
  if (!bar) return;
  bar.innerHTML = "";

  Tabs.forEach((t, i) => {
    const btn = document.createElement("button");
    btn.className = "tab " + (i === activeTab ? "active" : "");
    btn.textContent = t.title;

    const close = document.createElement("span");
    close.textContent = " ×";
    close.className = "closeX";
    close.onclick = (ev) => {
      ev.stopPropagation();
      closeTab(i);
    };
    btn.appendChild(close);

    btn.onclick = () => switchTab(i);
    bar.appendChild(btn);
  });

  const plus = document.createElement("button");
  plus.className = "tab add";
  plus.textContent = "+";
  plus.onclick = newTab;
  bar.appendChild(plus);
}

function switchTab(i) {
  if (i === activeTab) return;
  if (CM[activeTab]) Tabs[activeTab].sql = CM[activeTab].getValue();
  activeTab = i;

  document.querySelectorAll(".sql-editor-wrap").forEach((wrap, idx) => {
    wrap.style.display = idx === i ? "block" : "none";
    if (idx === i && CM[idx]) CM[idx].refresh();
  });

  renderTabs();
}

function closeTab(i) {
  if (Tabs.length === 1) {
    CM[0].setValue("");
    Tabs[0].sql = "";
    return;
  }

  Tabs.splice(i, 1);
  if (CM[i]) {
    CM[i].getWrapperElement().remove();
    delete CM[i];
  }

  const oldTabs = [...Tabs];
  Tabs = [];
  CM = {};
  document.getElementById("editorsContainer").innerHTML = "";
  oldTabs.forEach(t => createTab(t.sql));

  if (activeTab >= Tabs.length) activeTab = Tabs.length - 1;
  switchTab(activeTab);
}

function newTab() {
  createTab("-- new query");
  switchTab(Tabs.length - 1);
}

/* ===========================================================
   SCHEMA AUTOCOMPLETE
   =========================================================== */

function schemaHint(cm) {
  const cur = cm.getCursor();
  const token = cm.getTokenAt(cur);
  const word = token.string || "";

  let list = [];

  const parts = word.split(".");
  if (parts.length === 2) {
    const tbl = parts[0];
    if (Schema[tbl]) {
      Schema[tbl].columns.forEach(col => list.push(tbl + "." + col.COLUMN_NAME));
    }
  } else {
    Object.keys(Schema).forEach(tbl => list.push(tbl));
  }

  return {
    list,
    from: CodeMirror.Pos(cur.line, token.start),
    to: CodeMirror.Pos(cur.line, token.end)
  };
}

/* ===========================================================
   SCHEMA LOADER
   =========================================================== */

async function loadSchema() {
  const box = document.getElementById("schemaList");
  if (box) box.textContent = "Loading schema...";

  try {
    const res = await fetch("sql_schema.php", { cache: "no-store" });
    const j = await res.json();
    if (!j.ok) {
      box.textContent = "Schema load error";
      return;
    }
    Schema = j.schema || {};
    renderSchema();
  } catch (err) {
    if (box) box.textContent = "Schema load error";
    console.error("Schema load failed:", err);
  }
}

function renderSchema() {
  const wrap = document.getElementById("schemaList");
  if (!wrap) return;
  wrap.innerHTML = "";

  Object.keys(Schema).forEach(table => {
    const card = document.createElement("div");
    card.className = "schema-item";

    card.innerHTML = `
      <div class='schema-title'>${table}</div>
      <div class='schema-cols small text-muted'>
        ${Schema[table].columns.slice(0, 10).map(c => `<span>${c.COLUMN_NAME}</span>`).join("")}
      </div>
    `;

    card.onclick = () => {
      if (CM[activeTab]) {
        CM[activeTab].setValue(`SELECT * FROM \`${table}\` LIMIT 50;`);
        CM[activeTab].focus();
      }
    };

    wrap.appendChild(card);
  });
}

/* ===========================================================
   RUN SQL
   =========================================================== */

async function runSQL() {
  if (!CM[activeTab]) { alert("Editor not ready"); return; }
  const sql = CM[activeTab].getValue().trim();

  if (!sql) { alert("No SQL to run"); return; }

  Tabs[activeTab].sql = sql;

  const resultDiv = document.getElementById("resultsTableWrap");
  const status = document.getElementById("execStatus");

  status && (status.textContent = "Running...");
  resultDiv && (resultDiv.innerHTML = "");

  try {
    const res = await fetch("sql_execute.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sql })
    });
    const j = await res.json();

    if (!j.ok) {
      status && (status.textContent = "Error");
      resultDiv.innerHTML = `<div class="alert alert-danger">${eHTML(j.error)}</div>`;
      addHistory(sql, false, j.error || "Execution error");
      return;
    }

    LatestResults = j.results || [];
    renderResultsets(j.results || [], j.execution_time_ms || j.time || 0);
    addHistory(sql, true);

    status && (status.textContent = "Done");
  } catch (err) {
    status && (status.textContent = "Network error");
    resultDiv.innerHTML = `<div class="alert alert-danger">Network: ${eHTML(err.message)}</div>`;
    addHistory(sql, false, err.message || "Network error");
  }
}

function renderResultsets(resultsets, timeMs) {
  const wrap = document.getElementById("resultsTableWrap");
  if (!wrap) return;
  wrap.innerHTML = "";

  const timeDisplay = timeMs ? `${timeMs} ms` : "n/a";
  const statusEl = document.getElementById("execStatus");
  statusEl && (statusEl.textContent = `Done (${timeDisplay})`);

  if (!resultsets.length) {
    wrap.innerHTML = `<div class="small text-muted">Query executed. No resultsets returned.</div>`;
    return;
  }

  resultsets.forEach((r, idx) => {
    const block = document.createElement("div");
    block.className = "result-block";

    const head = document.createElement("div");
    head.className = "result-head";
    head.textContent =
      (r.type === "result" || r.type === "resultset")
        ? `Resultset #${idx + 1}`
        : `Statement #${idx + 1}`;
    block.appendChild(head);

    const meta = document.createElement("div");
    meta.className = "small text-muted";
    const metaParts = [];
    if (r.info) metaParts.push(r.info);
    if (r.affected_rows !== undefined) metaParts.push("Affected: " + r.affected_rows);
    if (r.insert_id) metaParts.push("Insert ID: " + r.insert_id);
    if (r.warnings) metaParts.push("Warnings: " + r.warnings);
    meta.textContent = metaParts.join(" • ");
    block.appendChild(meta);

    if (r.type === "result" || r.type === "resultset") {
      const cols = r.cols || [];
      const rows = r.rows || [];

      const tbl = document.createElement("table");
      tbl.className = "table table-sm table-bordered";

      const thead = document.createElement("thead");
      const thr = document.createElement("tr");

      cols.forEach(c => {
        const th = document.createElement("th");
        th.textContent = c;
        thr.appendChild(th);
      });

      thead.appendChild(thr);
      tbl.appendChild(thead);

      const tbody = document.createElement("tbody");
      rows.forEach(row => {
        const tr = document.createElement("tr");
        cols.forEach(c => {
          const td = document.createElement("td");
          const val = row[c];
          td.textContent = (val === null || val === undefined) ? "" : String(val);
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });

      tbl.appendChild(tbody);
      block.appendChild(tbl);

      if (!window._lastResult && rows.length) {
        window._lastResult = { cols, rows };
      }
    } else {
      const note = document.createElement("div");
      note.className = "small text-muted mt-2";
      note.innerHTML = `Statement completed. ${
        r.affected_rows !== undefined ? ('Affected rows: ' + r.affected_rows) : ''
      } ${r.insert_id ? ('• Insert ID: ' + r.insert_id) : ''}`;
      block.appendChild(note);
    }

    wrap.appendChild(block);
  });

  if (!window._lastResult) window._lastResult = { cols: [], rows: [] };
}

/* ===========================================================
   BEAUTIFY
   =========================================================== */

async function beautifySQL() {
  if (!CM[activeTab]) return;

  const raw = CM[activeTab].getValue().trim();
  if (!raw) return;

  try {
    const res = await fetch("sql_format.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sql: raw })
    });
    const j = await res.json();

    if (!j.ok) throw new Error(j.error || "Format failed");

    CM[activeTab].setValue(j.formatted || raw);
  } catch (err) {
    alert("Beautify error: " + err.message);
  }
}
/* ===========================================================
   AI ASSISTANT — GENERATE SQL
   =========================================================== */

async function aiGenerate() {
  const promptEl = document.getElementById("aiInput");
  if (!promptEl) return alert("AI input not found");

  const prompt = promptEl.value.trim();
  if (!prompt) return alert("Enter a prompt for AI");

  const modelSelect = document.getElementById("aiModelSelector");
  const selectedModel = (modelSelect && modelSelect.value) ? modelSelect.value : DEFAULT_MODEL;

  const aiStatusEl = document.getElementById("aiStatus");
  if (aiStatusEl) {
    aiStatusEl.textContent = "AI Generating...";
    aiStatusEl.classList.add("ai-loading");
    aiStatusEl.classList.remove("ai-online", "ai-offline");
  }

  try {
    const res = await fetch("sql_ai_assistant.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        task: "sql",
        query: prompt,
        schema: Schema,
        model: selectedModel
      })
    });

    const j = await res.json();
    if (!j.ok) {
      if (aiStatusEl) {
        aiStatusEl.textContent = "AI Error";
        aiStatusEl.classList.add("ai-offline");
        aiStatusEl.classList.remove("ai-loading");
      }
      return alert("AI Error: " + j.error);
    }

    if (CM[activeTab]) {
      CM[activeTab].setValue(j.text || "");
      CM[activeTab].focus();
    }

    if (aiStatusEl) {
      aiStatusEl.textContent = "AI Online";
      aiStatusEl.classList.remove("ai-loading");
      aiStatusEl.classList.add("ai-online");
    }

  } catch (err) {
    if (aiStatusEl) {
      aiStatusEl.textContent = "AI Error";
      aiStatusEl.classList.add("ai-offline");
      aiStatusEl.classList.remove("ai-loading");
    }
    alert("AI request failed: " + err.message);
  }
}

/* ===========================================================
   AI — EXPLAIN SQL
   =========================================================== */

async function aiExplainSQL() {
  if (!CM[activeTab]) return alert("Editor not ready");

  const sql = CM[activeTab].getValue().trim();
  if (!sql) return alert("No SQL to explain");

  const modelSelect = document.getElementById("aiModelSelector");
  const selectedModel = modelSelect?.value || DEFAULT_MODEL;

  const aiStatusEl = document.getElementById("aiStatus");
  if (aiStatusEl) {
    aiStatusEl.textContent = "AI Explaining...";
    aiStatusEl.classList.add("ai-loading");
    aiStatusEl.classList.remove("ai-online", "ai-offline");
  }

  try {
    const res = await fetch("sql_ai_assistant.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        task: "explain",
        query: sql,
        schema: Schema,
        model: selectedModel
      })
    });

    const j = await res.json();
    if (!j.ok) {
      if (aiStatusEl) {
        aiStatusEl.textContent = "AI Error";
        aiStatusEl.classList.add("ai-offline");
        aiStatusEl.classList.remove("ai-loading");
      }
      return alert("AI Error: " + j.error);
    }

    const box = document.getElementById("aiInput");
    if (box) {
      box.value = j.text || "";
      box.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    if (aiStatusEl) {
      aiStatusEl.textContent = "AI Online";
      aiStatusEl.classList.remove("ai-loading");
      aiStatusEl.classList.add("ai-online");
    }

  } catch (err) {
    if (aiStatusEl) {
      aiStatusEl.textContent = "AI Error";
      aiStatusEl.classList.add("ai-offline");
      aiStatusEl.classList.remove("ai-loading");
    }
    alert("AI explain request failed: " + err.message);
  }
}

/* ===========================================================
   HISTORY
   =========================================================== */

function loadHistory() {
  const list = document.getElementById("historyList");
  if (!list) return;
  list.innerHTML = "";

  const h = JSON.parse(localStorage.getItem("sql_history2") || "[]");

  h.slice().reverse().forEach(item => {
    const div = document.createElement("div");
    div.className = "history-item";

    div.innerHTML = `
      <div class="small text-muted">${item.time}</div>
      <div class="history-sql">${eHTML(item.sql)}</div>
    `;

    div.onclick = () => {
      if (CM[activeTab]) {
        CM[activeTab].setValue(item.sql);
        CM[activeTab].focus();
      }
    };

    list.appendChild(div);
  });
}

function addHistory(sql, ok, err = "") {
  let h = JSON.parse(localStorage.getItem("sql_history2") || "[]");

  h.push({
    sql,
    ok,
    error: err,
    time: new Date().toLocaleString()
  });

  if (h.length > 200) h.shift();

  localStorage.setItem("sql_history2", JSON.stringify(h));
  loadHistory();
}

function clearHistory() {
  if (!confirm("Clear all SQL history?")) return;
  localStorage.removeItem("sql_history2");
  loadHistory();
  alert("History cleared.");
}

/* ===========================================================
   AI STATUS CHECKER — OLLAMA
   =========================================================== */

async function checkAIStatusAndPopulateModels() {
  const el = document.getElementById("aiStatus");
  const modelSelect = document.getElementById("aiModelSelector");

  if (el) {
    el.textContent = "Checking...";
    el.classList.add("ai-loading");
    el.classList.remove("ai-online", "ai-offline");
  }

  try {
    const res = await fetch("http://localhost:11434/api/tags", {
      method: "GET",
      cache: "no-store"
    });

    if (!res.ok) throw new Error("Ollama offline");

    const j = await res.json();

    // Parse models
    const models = Array.isArray(j.models)
      ? j.models.map(m => m.name)
      : Array.isArray(j)
        ? j.map(m => m.name)
        : [];

    AI_MODELS = models.length ? models : [DEFAULT_MODEL];

    // update UI
    if (modelSelect) {
      const saved = localStorage.getItem("AI_MODEL") || DEFAULT_MODEL;
      modelSelect.innerHTML = "";

      AI_MODELS.forEach(m => {
        const opt = document.createElement("option");
        opt.value = m;
        opt.textContent = m;
        if (m === saved) opt.selected = true;
        modelSelect.appendChild(opt);
      });

      modelSelect.onchange = () => {
        localStorage.setItem("AI_MODEL", modelSelect.value);
      };
    }

    if (el) {
      el.textContent = "AI Online";
      el.classList.remove("ai-loading");
      el.classList.add("ai-online");
    }

  } catch (err) {
    if (el) {
      el.textContent = "AI Offline";
      el.classList.add("ai-offline");
      el.classList.remove("ai-loading", "ai-online");
    }

    if (modelSelect && modelSelect.options.length === 0) {
      const opt = document.createElement("option");
      opt.value = DEFAULT_MODEL;
      opt.textContent = DEFAULT_MODEL + " (offline)";
      modelSelect.appendChild(opt);
      modelSelect.value = DEFAULT_MODEL;
    }
  }
}

/* ===========================================================
   EXPORT / COPY RESULTS
   =========================================================== */

function copyResults() {
  const r = window._lastResult;
  if (!r || !r.rows || !r.rows.length) return alert("No results to copy");

  let txt = r.cols.join("\t") + "\n";

  r.rows.forEach(row => {
    txt += r.cols.map(c => (row[c] ?? "")).join("\t") + "\n";
  });

  navigator.clipboard.writeText(txt)
    .then(() => alert("Copied to clipboard"));
}

function exportCsv() {
  const r = window._lastResult;
  if (!r || !r.rows || !r.rows.length) return alert("No results to export");

  const cols = r.cols;
  let csv = cols.map(c => `"${c.replace(/"/g, '""')}"`).join(",") + "\n";

  r.rows.forEach(row => {
    csv += cols.map(c =>
      `"${String(row[c] ?? '').replace(/"/g, '""')}"`
    ).join(",") + "\n";
  });

  downloadFile(csv, "results.csv", "text/csv;charset=utf-8;");
}

function downloadJson() {
  const r = window._lastResult;
  if (!r || !r.rows) return alert("No results to download");

  downloadFile(
    JSON.stringify(r.rows, null, 2),
    "results.json",
    "application/json;charset=utf-8;"
  );
}

function exportCSV() { exportCsv(); }
function exportJSON() { downloadJson(); }

function downloadFile(data, filename, mime) {
  const blob = new Blob([data], { type: mime });
  const url = URL.createObjectURL(blob);

  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();

  a.remove();
  URL.revokeObjectURL(url);
}

/* ===========================================================
   UTILITIES
   =========================================================== */

function eHTML(s) {
  const d = document.createElement("div");
  d.textContent = s;
  return d.innerHTML;
}

function clearEditor() {
  if (CM[activeTab]) {
    CM[activeTab].setValue("");
    Tabs[activeTab].sql = "";
  }
}

/* ===========================================================
   BUTTON INIT
   =========================================================== */

function initButtons() {
  const runBtn          = document.getElementById("runBtn");
  const beautifyBtn     = document.getElementById("beautifyBtn");
  const aiBtn           = document.getElementById("aiBtn");
  const clearBtn        = document.getElementById("clearBtn");
  const clearHistoryBtn = document.getElementById("clearHistory");
  const explainBtn      = document.getElementById("explainBtn");
  const copyBtn         = document.getElementById("copyBtn");
  const csvBtn          = document.getElementById("csvBtn");
  const jsonBtn         = document.getElementById("jsonBtn");

  runBtn          && (runBtn.onclick          = runSQL);
  beautifyBtn     && (beautifyBtn.onclick     = beautifySQL);
  aiBtn           && (aiBtn.onclick           = aiGenerate);
  clearBtn        && (clearBtn.onclick        = clearEditor);
  clearHistoryBtn && (clearHistoryBtn.onclick = clearHistory);
  explainBtn      && (explainBtn.onclick      = aiExplainSQL);
  copyBtn         && (copyBtn.onclick         = copyResults);
  csvBtn          && (csvBtn.onclick          = exportCsv);
  jsonBtn         && (jsonBtn.onclick         = downloadJson);
}

// END OF FILE
