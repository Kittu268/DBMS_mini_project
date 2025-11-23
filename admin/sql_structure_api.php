<?php
// admin/sql_structure_api.php
// Manage table structure: columns, indexes, PK/FK, renames.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

// Admin only
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$table  = $_GET['table'] ?? '';

if ($table === '') {
    echo json_encode(['ok'=>false,'error'=>'Missing table']);
    exit;
}

function ident($x) {
    return "`" . str_replace("`","``",$x) . "`";
}

/* ------------------------------------------
   1) GET STRUCTURE
------------------------------------------ */
if ($action === 'structure') {
    try {
        $columns = [];
        $indexes = [];
        $fks = [];

        // Columns
        $res = $conn->query("SHOW FULL COLUMNS FROM " . ident($table));
        while ($r = $res->fetch_assoc()) {
            $columns[] = $r;
        }
        $res->free();

        // Indexes
        $res = $conn->query("SHOW INDEX FROM " . ident($table));
        while ($r = $res->fetch_assoc()) {
            $indexes[] = $r;
        }
        $res->free();

        // Foreign Keys
        $db = $conn->query("SELECT DATABASE()")->fetch_row()[0];
        $fkRes = $conn->query("
            SELECT
                k.CONSTRAINT_NAME,
                k.COLUMN_NAME,
                k.REFERENCED_TABLE_NAME,
                k.REFERENCED_COLUMN_NAME,
                rc.UPDATE_RULE,
                rc.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
            ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
            WHERE 
                k.TABLE_SCHEMA = '$db'
                AND k.TABLE_NAME = '$table'
                AND k.REFERENCED_TABLE_NAME IS NOT NULL
        ");
        while ($r = $fkRes->fetch_assoc()) {
            $fks[] = $r;
        }
        $fkRes->free();

        echo json_encode([
            'ok'=>true,
            'columns'=>$columns,
            'indexes'=>$indexes,
            'foreign_keys'=>$fks
        ]);

    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   2) ADD COLUMN
------------------------------------------ */
if ($action === 'add_column' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $name = $data['name'] ?? '';
    $type = $data['type'] ?? '';
    $after = $data['after'] ?? '';

    if ($name === '' || $type === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing name/type']);
        exit;
    }

    $sql = "ALTER TABLE " . ident($table) . " ADD " . ident($name) . " $type";

    if ($after !== '') {
        $sql .= " AFTER " . ident($after);
    }

    try {
        $conn->query($sql);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   3) MODIFY COLUMN
------------------------------------------ */
if ($action === 'modify_column' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $old = $data['old'] ?? '';
    $name = $data['name'] ?? '';
    $type = $data['type'] ?? '';

    if ($old === '' || $name === '' || $type === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing values']);
        exit;
    }

    $sql = "ALTER TABLE " . ident($table) .
           " CHANGE " . ident($old) . " " . ident($name) . " $type";

    try {
        $conn->query($sql);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   4) DROP COLUMN
------------------------------------------ */
if ($action === 'drop_column' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $name = $data['name'] ?? '';

    if ($name === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing column']);
        exit;
    }

    try {
        $conn->query("ALTER TABLE " . ident($table) . " DROP " . ident($name));
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   5) ADD INDEX
------------------------------------------ */
if ($action === 'add_index' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $columns = $data['columns'] ?? [];
    $unique = $data['unique'] ?? false;

    if (!$columns) {
        echo json_encode(['ok'=>false,'error'=>'Missing columns for index']);
        exit;
    }

    $colList = implode(",", array_map("ident", $columns));
    $idxName = "idx_" . implode("_", $columns);

    $sql = "ALTER TABLE " . ident($table) .
           " ADD " . ($unique ? "UNIQUE " : "") .
           "INDEX " . ident($idxName) . " ($colList)";

    try {
        $conn->query($sql);
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   6) DROP INDEX
------------------------------------------ */
if ($action === 'drop_index' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_getcontents("php://input"), true);
    $name = $data['name'] ?? '';

    if ($name === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing index name']);
        exit;
    }

    try {
        $conn->query("ALTER TABLE " . ident($table) . " DROP INDEX " . ident($name));
        echo json_encode(['ok'=>true]);
    }
    catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   7) ADD FOREIGN KEY
------------------------------------------ */
if ($action === 'add_fk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $col  = $data['col']  ?? '';
    $refTable = $data['ref_table'] ?? '';
    $refCol   = $data['ref_col']   ?? '';

    if ($col==='' || $refTable==='' || $refCol==='') {
        echo json_encode(['ok'=>false,'error'=>'Missing FK info']);
        exit;
    }

    $fkName = "fk_" . $table . "_" . $col;

    $sql = "ALTER TABLE " . ident($table) .
           " ADD CONSTRAINT " . ident($fkName) .
           " FOREIGN KEY (" . ident($col) . ")" .
           " REFERENCES " . ident($refTable) . " (" . ident($refCol) . ")" .
           " ON UPDATE CASCADE ON DELETE CASCADE";

    try {
        $conn->query($sql);
        echo json_encode(['ok'=>true]);
    } catch(Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   8) DROP FOREIGN KEY
------------------------------------------ */
if ($action === 'drop_fk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $name = $data['name'] ?? '';

    if ($name === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing FK name']);
        exit;
    }

    try {
        $conn->query("ALTER TABLE " . ident($table) . " DROP FOREIGN KEY " . ident($name));
        echo json_encode(['ok'=>true]);
    }
    catch (Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ------------------------------------------
   9) RENAME TABLE
------------------------------------------ */
if ($action === 'rename_table' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $new = $data['new'] ?? '';

    if ($new === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing new table name']);
        exit;
    }

    try {
        $conn->query("RENAME TABLE " . ident($table) . " TO " . ident($new));
        echo json_encode(['ok'=>true]);
    }
    catch(Throwable $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}


/* ------------------------------------------
   DEFAULT
------------------------------------------ */
echo json_encode(['ok'=>false,'error'=>'Unknown action']);
