<?php
// admin/sql_data_api.php
// Data management backend for SQL Explorer (browse, insert, update, delete)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

// ADMIN ONLY
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$table  = $_GET['table']  ?? '';
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

if ($table === '') {
    echo json_encode(['ok'=>false,'error'=>'Missing table']);
    exit;
}

/* -----------------------------------------
   Helper to escape identifiers
----------------------------------------- */
function ident($x) {
    return "`" . str_replace("`","``",$x) . "`";
}

/* -----------------------------------------
  1) LIST ROWS (pagination)
----------------------------------------- */
if ($action === 'browse') {
    try {
        $countRes = $conn->query("SELECT COUNT(*) AS c FROM " . ident($table));
        $total = $countRes->fetch_assoc()['c'];
        $countRes->free();

        $res = $conn->query("SELECT * FROM " . ident($table) . " LIMIT $limit OFFSET $offset");

        $rows = [];
        $cols = [];

        if ($res instanceof mysqli_result) {
            $fields = $res->fetch_fields();
            foreach ($fields as $f) $cols[] = $f->name;
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $res->free();
        }

        echo json_encode([
            'ok'=>true,
            'table'=>$table,
            'cols'=>$cols,
            'rows'=>$rows,
            'page'=>$page,
            'total'=>$total,
            'limit'=>$limit,
            'pages'=>ceil($total / $limit)
        ]);
    }
    catch (Throwable $e) {
        echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
    }
    exit;
}

/* -----------------------------------------
  2) FETCH SINGLE ROW (for editor popup)
----------------------------------------- */
if ($action === 'row') {
    $pk = $_GET['pk'] ?? '';
    if ($pk === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing PRIMARY KEY value']);
        exit;
    }

    // Primary Key assumed as the first column
    $pkColumn = null;
    $res = $conn->query("SHOW KEYS FROM " . ident($table) . " WHERE Key_name='PRIMARY'");
    if ($r = $res->fetch_assoc()) {
        $pkColumn = $r['Column_name'];
    }
    $res->free();

    if (!$pkColumn) {
        echo json_encode(['ok'=>false,'error'=>'No PRIMARY KEY in this table']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM " . ident($table) . " WHERE " . ident($pkColumn) . " = ?");
    $stmt->bind_param("s", $pk);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?? null;
    $stmt->close();

    echo json_encode(['ok'=>true,'row'=>$row,'pk'=>$pkColumn]);
    exit;
}

/* -----------------------------------------
  3) UPDATE ROW
----------------------------------------- */
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true) ?: [];

    $pkColumn = $data['pkColumn'] ?? '';
    $pkValue  = $data['pkValue'] ?? '';
    $changes  = $data['data'] ?? [];

    if (!$pkColumn || !$pkValue || !is_array($changes)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid update payload']);
        exit;
    }

    // Build SET clause
    $sets = [];
    $vals = [];
    foreach ($changes as $col=>$val) {
        $sets[] = ident($col) . " = ?";
        $vals[] = $val;
    }
    $vals[] = $pkValue; // WHERE

    $types = str_repeat("s", count($vals));

    $sql = "UPDATE " . ident($table) . " SET " . implode(",", $sets) . " WHERE " . ident($pkColumn) . " = ?";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['ok'=>true,'affected'=>$affected]);
    }
    catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* -----------------------------------------
  4) INSERT NEW ROW
----------------------------------------- */
if ($action === 'insert' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true) ?: [];
    if (!is_array($data) || empty($data)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid insert payload']);
        exit;
    }

    $cols = array_keys($data);
    $vals = array_values($data);

    $sql = "INSERT INTO " . ident($table) . " (" .
        implode(",", array_map("ident",$cols)) .
        ") VALUES (" .
        implode(",", array_fill(0,count($vals), "?")) .
        ")";

    $types = str_repeat("s", count($vals));

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['ok'=>true,'insert_id'=>$id]);
    }
    catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* -----------------------------------------
  5) DELETE ROW
----------------------------------------- */
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true) ?: [];
    $pkValue = $data['pkValue'] ?? '';

    if ($pkValue === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing pkValue']);
        exit;
    }

    // fetch PK column
    $res = $conn->query("SHOW KEYS FROM " . ident($table) . " WHERE Key_name='PRIMARY'");
    if ($r = $res->fetch_assoc()) {
        $pkColumn = $r['Column_name'];
    } else {
        echo json_encode(['ok'=>false,'error'=>'No PRIMARY KEY in this table']);
        exit;
    }
    $res->free();

    try {
        $stmt = $conn->prepare("DELETE FROM " . ident($table) . " WHERE " . ident($pkColumn) . " = ?");
        $stmt->bind_param("s", $pkValue);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        echo json_encode(['ok'=>true, 'affected'=>$affected]);
    }
    catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* -----------------------------------------
  Unknown action
----------------------------------------- */
echo json_encode(['ok'=>false,'error'=>'Unknown action']);
exit;
