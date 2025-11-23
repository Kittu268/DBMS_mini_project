<?php
// admin/sql_schema.php
// Returns full database schema in a structure expected by sql.js

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

// Only admin users allowed
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Forbidden: admin only'
    ]);
    exit;
}

try {
    // Get active DB
    $dbRow = $conn->query("SELECT DATABASE()")->fetch_row();
    if (!$dbRow) throw new Exception("No active database selected");
    
    $dbName = $conn->real_escape_string($dbRow[0]);

    // Fetch tables
    $tables = $conn->query("
        SELECT TABLE_NAME 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = '$dbName'
        ORDER BY TABLE_NAME
    ");

    $schema = [];

    while ($t = $tables->fetch_assoc()) {
        $tbl = $t['TABLE_NAME'];

        $schema[$tbl] = [
            'columns'      => [],
            'primary_key'  => [],
            'foreign_keys' => []
        ];

        // Fetch columns
        $cols = $conn->query("
            SELECT 
                COLUMN_NAME,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_KEY,
                COLUMN_DEFAULT,
                EXTRA
            FROM information_schema.COLUMNS
            WHERE 
                TABLE_SCHEMA = '$dbName' 
                AND TABLE_NAME = '$tbl'
            ORDER BY ORDINAL_POSITION
        ");

        while ($c = $cols->fetch_assoc()) {
            $schema[$tbl]['columns'][] = $c;

            if ($c['COLUMN_KEY'] === "PRI") {
                $schema[$tbl]['primary_key'][] = $c['COLUMN_NAME'];
            }
        }

        // Foreign Keys
        $fk = $conn->query("
            SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = '$dbName'
                AND TABLE_NAME = '$tbl'
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        while ($f = $fk->fetch_assoc()) {
            $schema[$tbl]['foreign_keys'][] = $f;
        }
    }

    echo json_encode([
        'ok'     => true,
        'schema' => $schema
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage()
    ]);
}
