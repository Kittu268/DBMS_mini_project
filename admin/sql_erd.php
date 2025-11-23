<?php
// admin/sql_erd.php — Clean & Fast ERD backend (Option B)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

header("Content-Type: application/json");

// admin check
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

try {
    // Get current database name
    $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];

    // Fetch list of all tables
    $tables = [];
    $qTables = $conn->query("
        SELECT TABLE_NAME 
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA='$dbName'
        ORDER BY TABLE_NAME
    ");

    while ($t = $qTables->fetch_assoc()) {
        $tables[] = $t['TABLE_NAME'];
    }

    $ERD = [
        "tables" => [],
        "relations" => []
    ];

    foreach ($tables as $table) {

        // ============================
        //  Columns
        // ============================
        $cols = [];
        $qCols = $conn->query("
            SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA='$dbName'
              AND TABLE_NAME='$table'
            ORDER BY ORDINAL_POSITION
        ");

        while ($c = $qCols->fetch_assoc()) {
            $cols[] = [
                'name' => $c['COLUMN_NAME'],
                'type' => $c['COLUMN_TYPE'],
                'pk'   => ($c['COLUMN_KEY'] === 'PRI'),
                'nullable' => ($c['IS_NULLABLE'] === 'YES')
            ];
        }

        // ============================
        // Primary Key
        // ============================
        $primary = [];
        $qPK = $conn->query("
            SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'
        ");
        while ($pk = $qPK->fetch_assoc()) {
            $primary[] = $pk['Column_name'];
        }

        // ============================
// Foreign Keys + Cardinality
// ============================
$fks = [];
$qFK = $conn->query("
    SELECT 
        k.COLUMN_NAME,
        k.REFERENCED_TABLE_NAME,
        k.REFERENCED_COLUMN_NAME,
        k.CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE k
    WHERE 
        k.TABLE_SCHEMA='$dbName'
        AND k.TABLE_NAME='$table'
        AND k.REFERENCED_TABLE_NAME IS NOT NULL
");

while ($fk = $qFK->fetch_assoc()) {

    $col = $fk["COLUMN_NAME"];
    $refTbl = $fk["REFERENCED_TABLE_NAME"];
    $refCol = $fk["REFERENCED_COLUMN_NAME"];

    // ------------------------
    // Cardinality Detection
    // ------------------------

    // Is referencing column part of PRIMARY KEY?
    $isPK_local = false;
    $checkPK = $conn->query("SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'");
    while ($pk = $checkPK->fetch_assoc()) {
        if ($pk['Column_name'] === $col) $isPK_local = true;
    }

    // Is referenced column part of PRIMARY KEY?
    $isPK_remote = false;
    $checkPK2 = $conn->query("SHOW KEYS FROM `$refTbl` WHERE Key_name='PRIMARY'");
    while ($pk2 = $checkPK2->fetch_assoc()) {
        if ($pk2['Column_name'] === $refCol) $isPK_remote = true;
    }

    // Determine cardinality
    $card = "1-M"; 

    if ($isPK_local && $isPK_remote)
        $card = "1-1"; 
    else if ($isPK_local && !$isPK_remote)
        $card = "M-1";   
    else if (!$isPK_local && $isPK_remote)
        $card = "1-M";
    else
        $card = "M-M";   // rarely happens unless mapping table


    $fks[] = [
        "column" => $col,
        "ref_table" => $refTbl,
        "ref_column" => $refCol,
        "cardinality" => $card
    ];

    // Push into relations for ERD frontend
    $ERD["relations"][] = [
        "from_table"  => $table,
        "from_column" => $col,
        "to_table"    => $refTbl,
        "to_column"   => $refCol,
        "cardinality" => $card
    ];
}


        // ============================
        // Push Table Block
        // ============================
        $ERD['tables'][] = [
            "name" => $table,
            "columns" => $cols,
            "primary_key" => $primary,
            "foreign_keys" => $fks
        ];
    }
    // ============================
// Table Classification (colors)
// ============================
foreach ($ERD["tables"] as &$t) {

    $name = $t["name"];
    $cols = $t["columns"];

    $fullyPK = count($t["primary_key"]) >= 2;        // multiple PK columns
    $hasFK   = count($t["foreign_keys"]) > 0;

    // Count how many tables reference this table
    $refCount = 0;
    foreach ($ERD["relations"] as $rel) {
        if ($rel["to_table"] === $name) $refCount++;
    }

    // classify
    if (!$hasFK && $refCount == 0) {
        $t["type"] = "isolated";
        $t["color"] = "#E5E7EB"; // light gray
    }
    else if ($fullyPK && $hasFK) {
        $t["type"] = "mapping";
        $t["color"] = "#22c55e"; // green
    }
    else if ($refCount >= 2) {
        $t["type"] = "master";
        $t["color"] = "#f97316"; // orange
    }
    else if (!$hasFK && $refCount > 0) {
        $t["type"] = "lookup";
        $t["color"] = "#3b82f6"; // blue
    }
    else {
        $t["type"] = "transaction";
        $t["color"] = "#ef4444"; // red
    }
}
unset($t);

    echo json_encode(['ok' => true, 'erd' => $ERD], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
 