<?php
// admin/sql_execute.php
// The main SQL execution engine for the Workbench.
// Supports multi-statement SQL, returns multiple resultsets.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

// admin only
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents("php://input"), true) ?: [];
$sql   = trim($input['sql'] ?? '');
$explain = boolval($input['explain'] ?? false);

if ($sql === '') {
    echo json_encode(['ok'=>false, 'error'=>'Empty SQL']);
    exit;
}

/* ============================================================
   CORE: MULTI STATEMENT EXECUTOR
   Supports: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP
============================================================ */
function execute_multi($conn, $sql) {
    $start = microtime(true);

    if (!$conn->multi_query($sql)) {
        return ['ok'=>false, 'error'=>$conn->error];
    }

    $results = [];
    $index = 0;

    do {
        $res = $conn->store_result();
        $warnings = $conn->warning_count;

        $info = $conn->info;
        $affected = $conn->affected_rows;
        $insertId = $conn->insert_id ?: null;

        if ($warnings > 0) {
            $warnRes = $conn->query("SHOW WARNINGS");
            $warningsList = [];
            while ($w = $warnRes->fetch_assoc()) {
                $warningsList[] = $w;
            }
            $warnRes->free();
        } else {
            $warningsList = [];
        }

        if ($res instanceof mysqli_result) {
            // SELECT resultset
            $cols = [];
            $fields = $res->fetch_fields();
            foreach ($fields as $f) $cols[] = $f->name;

            $rows = [];
            $count = 0;
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
                $count++;
                if ($count >= 5000) break; // safety limit
            }
            $res->free();

            $results[] = [
                'type' => 'resultset',
                'cols' => $cols,
                'rows' => $rows,
                'affected' => $affected,
                'insert_id' => $insertId,
                'info' => $info,
                'warnings' => $warningsList,
                'index' => $index++
            ];
        } else {
            // UPDATE, INSERT, DELETE, DDL
            $results[] = [
                'type' => 'ok',
                'affected' => $affected,
                'insert_id' => $insertId,
                'info' => $info,
                'warnings' => $warningsList,
                'index' => $index++
            ];
        }

        if (!$conn->more_results()) break;

    } while ($conn->next_result());

    $end = microtime(true);

    return [
        'ok' => true,
        'results' => $results,
        'time' => round($end - $start, 4)
    ];
}

/* ============================================================
   EXPLAIN FIRST STATEMENT
============================================================ */
if ($explain) {
    $first = preg_split('/;[\s\r\n]*/', $sql, 2)[0];

    if (!$first) {
        echo json_encode(['ok'=>false, 'error'=>'No SQL to explain']);
        exit;
    }

    try {
        $ex = $conn->query("EXPLAIN " . $first);

        if (!$ex) {
            echo json_encode(['ok'=>false, 'error'=>$conn->error]);
            exit;
        }

        $rows = [];
        while ($r = $ex->fetch_assoc()) $rows[] = $r;
        $ex->free();

        echo json_encode([
            'ok'=>true,
            'type'=>'explain',
            'rows'=>$rows
        ]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
        exit;
    }
}

/* ============================================================
   NORMAL EXECUTION (MULTI QUERY)
============================================================ */
try {
    $res = execute_multi($conn, $sql);

    if (!$res['ok']) {
        echo json_encode(['ok'=>false, 'error'=>$res['error']]);
        exit;
    }

    echo json_encode([
        'ok'=>true,
        'time'=>$res['time'],
        'results'=>$res['results']
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
