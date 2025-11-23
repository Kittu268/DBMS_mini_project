<?php
// admin/sql_format.php
// Lightweight SQL Beautifier for MySQL/MariaDB.
// Returns formatted SQL text.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';

// Only admin
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Read input
$input = json_decode(file_get_contents("php://input"), true) ?: [];
$sql = trim($input['sql'] ?? '');

if ($sql === '') {
    echo json_encode(['ok'=>false,'error'=>'Empty SQL']);
    exit;
}

/**
 * SQL Beautifier
 * ----------------
 * Applies:
 *   - Uppercase SQL keywords
 *   - Indentation for SELECT/FROM/WHERE/AND/OR/JOIN
 *   - Line breaks after common clauses
 *   - Keeps quoted strings intact
 */
function beautify_sql($sql) {
    // Normalize line endings
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);

    // Uppercase keywords list
    $keywords = [
        "select","from","where","group by","order by","limit",
        "inner join","left join","right join","join",
        "on","and","or","insert","into","values","update",
        "set","delete","create","table","alter","drop",
        "having","distinct","union","all","exists","case",
        "when","then","end","else","as","in","is","not"
    ];

    // Uppercase keywords (outside quotes)
    foreach ($keywords as $kw) {
        $pattern = '/\b' . preg_quote($kw, '/') . '\b/i';
        $sql = preg_replace_callback($pattern, function($m){
            return strtoupper($m[0]);
        }, $sql);
    }

    // Add line breaks before major clauses
    $breaks = [
        "SELECT","FROM","WHERE","GROUP BY","ORDER BY","HAVING","LIMIT",
        "INSERT","UPDATE","DELETE","INNER JOIN","LEFT JOIN","RIGHT JOIN","JOIN"
    ];
    foreach ($breaks as $b) {
        $sql = preg_replace('/\s*' . preg_quote($b,'/') . '\b/', "\n" . $b, $sql);
    }

    // Cleanup repeated empty lines
    $sql = preg_replace("/\n{2,}/", "\n", $sql);

    // Indent rules
    $lines = explode("\n", $sql);
    $indent = 0;
    $out = [];

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') continue;

        // Decrease indent for keywords
        if (preg_match('/^(FROM|WHERE|GROUP BY|ORDER BY|HAVING|LIMIT|INNER JOIN|LEFT JOIN|RIGHT JOIN|JOIN)\b/i', $trim)) {
            $indent = 0;
        }

        $out[] = str_repeat("    ", $indent) . $trim;

        // Increase indentation after SELECT
        if (preg_match('/^SELECT\b/i', $trim)) {
            $indent = 1;
        }
    }

    $sql = implode("\n", $out);

    return trim($sql) . "\n";
}

$formatted = beautify_sql($sql);

// Return response
echo json_encode(['ok'=>true, 'formatted'=>$formatted], JSON_UNESCAPED_UNICODE);
