<?php
// admin/sql_ai_assistant.php
// AI SQL Engine v4.0 — All-in-one (MIXED MODE)
// - Auto schema loader (MySQLi $conn from db.php)
// - Mixed-model auto-selection (fast small models + fallback large models)
// - Ollama + HF support (if enabled)
// - Strict schema validation & post-processing/fixes for SQL
// - Test-data generator (generates valid INSERT statements only — does NOT execute)
// - Full DB seeder (GENERATE-ONLY mode)
// - Admin-only guard (requires includes/auth_check.php)
//
// Install notes:
// - Place this file at admin/sql_ai_assistant.php
// - Requires ollama listening at http://localhost:11434 for model generation
// - db.php must provide $conn (mysqli) connection
// - This file will NOT auto-execute generated INSERTs unless you modify it

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php'; // expects $conn (mysqli)

header('Content-Type: application/json; charset=utf-8');

// Admin-only guard
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

/* ---------------------------
   INPUT
--------------------------- */
$body  = json_decode(file_get_contents("php://input"), true) ?: [];
$task  = $body['task'] ?? 'sql';
$query = trim($body['query'] ?? '');
$requested_model = preg_replace('/[^a-zA-Z0-9._:-]/', '', $body['model'] ?? ''); // optional override

/* ---------------------------
   AI BACKEND CONFIG
--------------------------- */
$USE_OLLAMA = true;
$USE_HF = false;
$HF_MODEL = "mistralai/Mixtral-8x7B-Instruct";
$HF_API_KEY = "";

/* ---------------------------
   MODEL DETECTION & MIXED POLICY
--------------------------- */
function installed_models_list() {
    $out = shell_exec("ollama list 2>&1");
    return $out ?: "";
}

function detect_models() {
    $installed = installed_models_list();
    $small_candidates = ["qwen2.5-coder:1.5b","qwen2.5:1.5b","mistral:7b","llama3.2:3b"];
    $large_candidates = ["llama3.2:latest","mistral:mixtral-8x7b","mixtral-8x7b:latest"];
    $found_small = null; $found_large = null;
    foreach ($small_candidates as $m) if (strpos($installed,$m)!==false) { $found_small = $m; break; }
    foreach ($large_candidates as $m) if (strpos($installed,$m)!==false) { $found_large = $m; break; }
    // fallback to llama3.2 if nothing else
    if (!$found_small) $found_small = (strpos($installed,"llama3.2:3b")!==false ? "llama3.2:3b" : "llama3.2:latest");
    if (!$found_large) $found_large = "llama3.2:latest";
    return ['small'=>$found_small,'large'=>$found_large];
}

$models = detect_models();

/**
 * Choose model based on task & query complexity
 * - short/simple tasks (insert_template, insert_example, generate_test_data): small model
 * - complex SQL generation (joins, aggregates, multi-table updates): large model
 * - user override with $requested_model allowed if installed (very advanced)
 */
function choose_model($task, $query, $requested_model, $models) {
    if ($requested_model) {
        // verify installed
        $installed = installed_models_list();
        if (strpos($installed, $requested_model) !== false) return $requested_model;
    }
    $task = strtolower($task);
    if (in_array($task, ['insert_template','insert_example','generate_test_data','seed_full_database','test_model','top3'])) {
        return $models['small'];
    }
    // heuristics to detect complexity
    $complex_keywords = ['join','group by','union','having','with','update','delete','insert into','create','alter','drop','subquery','having'];
    $lower = strtolower($query);
    $count_complex = 0;
    foreach ($complex_keywords as $k) if (strpos($lower,$k)!==false) $count_complex++;
    if ($count_complex >= 1 || strlen($query) > 200) return $models['large'];
    return $models['small'];
}

/* ---------------------------
   AUTO-LOAD DATABASE SCHEMA
--------------------------- */
function load_schema($conn) {
    $schema = [];
    $r = $conn->query("SHOW TABLES");
    if (!$r) return $schema;
    while ($row = $r->fetch_row()) {
        $table = $row[0];
        $colsR = $conn->query("SHOW COLUMNS FROM `{$table}`");
        $cols = $colsR ? $colsR->fetch_all(MYSQLI_ASSOC) : [];
        $schema[$table] = ['columns' => $cols];
    }
    return $schema;
}
$schema = load_schema($conn);

/* ---------------------------
   TEST DATA / FAKE GENERATORS
   (generate insertable values; does NOT insert)
--------------------------- */
function td_random($conn, $table, $column) {
    $q = @$conn->query("SELECT `$column` FROM `$table` ORDER BY RAND() LIMIT 1");
    if ($q && ($r = $q->fetch_row())) return $r[0];
    return null;
}
function td_random_or($conn,$table,$column,$fallback){ $v = td_random($conn,$table,$column); return ($v===null? $fallback : $v); }
function td_name(){ $f=["Aarav","Meera","John","Aditi","Michael","Priya","Rohan","Sara","Kiran"]; $l=["Patel","Kumar","Reddy","Shetty","Singh","Sharma","Naidu"]; return $f[array_rand($f)]." ".$l[array_rand($l)]; }
function td_phone(){ return "9".rand(100000000,999999999); }
function td_email(){ return "user".rand(1000,999999)."@example.com"; }
function td_date($minDays=1,$maxDays=365){ return date("Y-m-d", strtotime("+".rand($minDays,$maxDays)." days")); }
function td_time(){ return sprintf("%02d:%02d:00", rand(0,23), rand(0,59)); }
function td_seat(){ return rand(1,60).chr(rand(65,70)); }
function td_money(){ return number_format(rand(2000,15000)+rand(0,99)/100, 2); }

function td_generate_row($conn, $table, $schema) {
    if (!isset($schema[$table])) return null;
    $columns = $schema[$table]['columns'];
    $colNames = []; $values = [];
    foreach ($columns as $c) {
        $name = $c['Field'];
        $type = strtolower($c['Type']);
        $extra = strtolower($c['Extra']);
        $nullAllowed = (strtoupper($c['Null']) === 'YES');

        if (strpos($extra,'auto_increment')!==false) continue;
        $colNames[] = "`{$name}`";

        // int
        if (strpos($type,'int')!==false) {
            if (preg_match('/class_id$/i',$name)) { $values[] = td_random_or($conn,'seat_class','class_id',1); continue; }
            if (preg_match('/leg_no/i',$name)) { $values[] = rand(1,3); continue; }
            $values[] = rand(1,999);
            continue;
        }
        // decimal/float
        if (preg_match('/decimal|float|double/',$type)) { $values[] = td_money(); continue; }
        // enum
        if (preg_match("/enum\((.*)\)/",$type,$m)) {
            $opts = array_map(fn($v)=>trim($v,"' "), explode(',',$m[1]));
            $values[] = "'" . $opts[array_rand($opts)] . "'"; continue;
        }
        // date/time/datetime/timestamp
        if (strpos($type,'date')!==false && !preg_match('/datetime|timestamp/',$type)) { $values[] = "'" . td_date() . "'"; continue; }
        if (strpos($type,'time')!==false) { $values[] = "'" . td_time() . "'"; continue; }
        if (strpos($type,'datetime')!==false || strpos($type,'timestamp')!==false) { $values[] = "CURRENT_TIMESTAMP"; continue; }
        // varchar/char/text
        if (strpos($type,'char')!==false || strpos($type,'text')!==false) {
            $lname = strtolower($name);
            if (strpos($lname,'email')!==false) { $values[] = "'" . td_email() . "'"; continue; }
            if (strpos($lname,'name')!==false) { $values[] = "'" . td_name() . "'"; continue; }
            if (strpos($lname,'phone')!==false || strpos($lname,'cphone')!==false || strpos($lname,'contact')!==false) { $values[] = "'" . td_phone() . "'"; continue; }
            if (strcasecmp($name,'Flight_number')===0) { $v = td_random($conn,'flight','Flight_number') ?: ("FL".rand(100,999)); $values[] = "'".$v."'"; continue; }
            if (strcasecmp($name,'Airplane_id')===0) { $v = td_random($conn,'airplane','Airplane_id') ?: ("AP".rand(100,999)); $values[] = "'".$v."'"; continue; }
            if (in_array($name, ['Departure_airport_code','Arrival_airport_code','Airport_code'])) { $v = td_random($conn,'airport','Airport_code') ?: "BLR"; $values[] = "'".$v."'"; continue; }
            if (strcasecmp($name,'Seat_no')===0) { $values[] = "'" . td_seat() . "'"; continue; }
            $values[] = "'" . $name . "_" . rand(100,999) . "'"; continue;
        }
        // fallback
        if ($nullAllowed) $values[] = "NULL"; else $values[] = "''";
    }
    return "INSERT INTO `{$table}` (" . implode(", ", $colNames) . ") VALUES (" . implode(", ", $values) . ");";
}
function td_generate_multi($conn,$table,$schema,$count=5){ $out=[]; for($i=0;$i<max(1,$count);$i++){ $s=td_generate_row($conn,$table,$schema); if($s) $out[]=$s; } return $out; }

/* ---------------------------
   FULL DB SEEDER (GENERATE-ONLY)
--------------------------- */
function default_seeder_order() {
    return [
        'airport'=>5,
        'airplane_type'=>3,
        'airplane'=>5,
        'seat_class'=>3,
        'flight'=>5,
        'flight_leg'=>8,
        'leg_instance'=>8,
        'seat'=>50,
        'fare'=>12,
        'dynamic_fare'=>8,
        'users'=>6,
        'reservation'=>30,
        'revenue_log'=>30
    ];
}

/* ---------------------------
   OLLAMA + HF CALLS
--------------------------- */
function call_ollama_api($prompt, $model) {
    $payload = ["model"=>$model,"prompt"=>$prompt,"options"=>["num_ctx"=>4096,"temperature"=>0.12]];
    $ch = curl_init("http://localhost:11434/api/generate");
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_HTTPHEADER=>["Content-Type: application/json"],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>0,
        CURLOPT_CONNECTTIMEOUT=>10
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['ok'=>false,'error'=>"Ollama error: $err"];
    $text = "";
    foreach (explode("\n", trim($res)) as $line) {
        if (!$line) continue;
        $j = json_decode($line, true);
        if (!empty($j['response'])) $text .= $j['response'];
    }
    return ['ok'=>true,'text'=>$text];
}
function call_hf_api($prompt,$model,$apiKey=''){
    $payload=["inputs"=>$prompt,"parameters"=>["max_new_tokens"=>400,"temperature"=>0.12]];
    $headers=["Content-Type: application/json"]; if($apiKey) $headers[]="Authorization: Bearer $apiKey";
    $ch = curl_init("https://api-inference.huggingface.co/models/{$model}");
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) return ['ok'=>false,'error'=>"HF error: $err"];
    $json = json_decode($res,true); if (isset($json['error'])) return ['ok'=>false,'error'=>$json['error']];
    if (is_array($json) && isset($json[0]['generated_text'])) return ['ok'=>true,'text'=>$json[0]['generated_text']];
    if (isset($json['generated_text'])) return ['ok'=>true,'text'=>$json['generated_text']];
    return ['ok'=>false,'error'=>'HF response invalid'];
}

/* ---------------------------
   SQL CLEANUP / POST-PROCESSING
   - remove trailing commas
   - normalize date formats
   - map column names to schema (case-insensitive)
   - ensure required NOT NULL columns get defaults (best-effort)
--------------------------- */
function remove_trailing_commas_before_paren($sql) {
    // pattern: ,\s*\) => )
    return preg_replace('/,\s*\)/', ')', $sql);
}

function normalize_date_literals($sql) {
    // Try to fix common faulty date formats like '223-4-15' to '2023-04-15' only if obviously wrong (best-effort)
    return preg_replace_callback("/'([0-9]{1,4})-([0-9]{1,2})-([0-9]{1,2})'/", function($m){
        $y = intval($m[1]); $mo = intval($m[2]); $d = intval($m[3]);
        if ($y < 100) $y += 2000;
        $mo = max(1,min(12,$mo)); $d = max(1,min(31,$d));
        return "'" . sprintf("%04d-%02d-%02d",$y,$mo,$d) . "'";
    }, $sql);
}

function map_column_names_to_schema($sql, $schema) {
    // Map standalone column tokens to proper casing from schema (best-effort)
    // Build map of lower => exact
    $map = [];
    foreach ($schema as $t => $info) {
        foreach ($info['columns'] as $c) {
            $map[strtolower($c['Field'])] = $c['Field'];
        }
    }
    // Replace tokens that match column names (word boundaries)
    return preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', function($m) use ($map) {
        $tok = $m[1];
        $low = strtolower($tok);
        if (isset($map[$low])) return $map[$low];
        return $tok;
    }, $sql);
}

function postprocess_generated_sql($sql, $schema) {
    if (!$sql) return $sql;
    $s = $sql;
    $s = remove_trailing_commas_before_paren($s);
    $s = normalize_date_literals($s);
    $s = map_column_names_to_schema($s, $schema);
    // ensure semicolon
    $s = trim($s);
    if ($s && !str_ends_with($s, ';')) $s .= ';';
    return $s;
}

/* ---------------------------
   PROMPT BUILDERS
--------------------------- */
function schema_to_text_block($schema) {
    $txt = "DATABASE SCHEMA (AUTO-LOADED):\n";
    foreach ($schema as $table => $info) {
        $txt .= "- TABLE: {$table}\n";
        if (!empty($info['columns'])) {
            foreach ($info['columns'] as $c) {
                $txt .= "    • {$c['Field']} ({$c['Type']})\n";
            }
        }
    }
    return $txt;
}
function build_base_prompt($schema) {
    return "You are an expert MySQL/MariaDB engineer. Use ONLY names from the schema below. DO NOT invent tables or columns.\n\n" . schema_to_text_block($schema) . "\n\n";
}
function build_generate_prompt($user_text, $schema) {
    $base = build_base_prompt($schema);
    return $base . "Convert the user request into valid MySQL/MariaDB SQL. Return ONLY SQL statement(s) and nothing else.\n\nUser request:\n" . $user_text;
}
function build_autofix_prompt($bad_sql, $schema) {
    $base = build_base_prompt($schema);
    return $base . "The user supplied SQL (which may be syntactically invalid). Fix it to a correct, valid MySQL/MariaDB SQL statement that uses ONLY existing table and column names from the schema. Return ONLY the corrected SQL (no explanation or markdown). Input:\n\n" . $bad_sql . "\n\nReturn corrected SQL only.";
}
function build_optimize_prompt($sql, $schema) {
    $base = build_base_prompt($schema);
    return $base . "Rewrite the SQL for performance and clarity where possible for MySQL/MariaDB. Prefer using indexed columns, avoid SELECT * if possible, add appropriate LIMITs, and produce EXPLAIN-friendly structure. Return ONLY the rewritten SQL and nothing else.\n\nOriginal SQL:\n" . $sql;
}

/* ---------------------------
   SQL VALIDATION
--------------------------- */
function validate_sql_against_schema($sql, $schema) {
    $out = ['ok'=>true,'missing_tables'=>[],'missing_columns'=>[],'used'=>['tables'=>[],'columns'=>[]]];
    preg_match_all('/\bFROM\s+([`]?([a-zA-Z0-9_]+)[`]?)\b/i',$sql,$m_from);
    preg_match_all('/\bJOIN\s+([`]?([a-zA-Z0-9_]+)[`]?)\b/i',$sql,$m_join);
    preg_match_all('/\bINTO\s+([`]?([a-zA-Z0-9_]+)[`]?)\b/i',$sql,$m_into);
    preg_match_all('/\bUPDATE\s+([`]?([a-zA-Z0-9_]+)[`]?)\b/i',$sql,$m_update);
    $tables=[];
    foreach ([$m_from,$m_join,$m_into,$m_update] as $m) { if (!empty($m[2])) foreach($m[2] as $t) $tables[]=$t; }
    $tables = array_values(array_unique($tables));
    $out['used']['tables'] = $tables;
    preg_match_all('/([a-zA-Z0-9_]+)\.([a-zA-Z0-9_]+)/',$sql,$m_cols);
    $cols_by_table = [];
    if (!empty($m_cols[1])) {
        foreach ($m_cols[1] as $i=>$tbl) { $col = $m_cols[2][$i]; $cols_by_table[$tbl][] = $col; }
    }
    foreach ($tables as $t) if (!isset($schema[$t])) $out['missing_tables'][] = $t;
    foreach ($cols_by_table as $t=>$cols) {
        if (!isset($schema[$t])) continue;
        $available = array_map(fn($c)=>$c['Field'],$schema[$t]['columns']);
        foreach (array_unique($cols) as $col) if (!in_array($col,$available,true)) $out['missing_columns'][$t][] = $col;
    }
    $out['ok'] = empty($out['missing_tables']) && empty($out['missing_columns']);
    return $out;
}

/* ---------------------------
   TASK HANDLERS
--------------------------- */

// test model endpoint
if ($task === 'test_model') {
    $chosen = choose_model('test_model','',$requested_model,$models);
    echo json_encode(['ok'=>true,'selected_model'=>$chosen,'installed'=>installed_models_list()]);
    exit;
}

// generate_test_data: returns array of INSERT statements for a single table
if ($task === 'generate_test_data') {
    $table = trim($body['table'] ?? '');
    $count = intval($body['count'] ?? 3);
    if (!$table || !isset($schema[$table])) { echo json_encode(['ok'=>false,'error'=>'Invalid table']); exit; }
    $rows = td_generate_multi($conn,$table,$schema,$count);
    echo json_encode(['ok'=>true,'table'=>$table,'rows'=>$rows]);
    exit;
}

// seed_full_database: generate-only mode (does NOT execute)
// returns list of INSERT statements in dependency order
if ($task === 'seed_full_database') {
    $order = default_seeder_order();
    $seed_sql = [];
    foreach ($order as $table => $count) {
        if (!isset($schema[$table])) continue;
        $seed_sql = array_merge($seed_sql, td_generate_multi($conn,$table,$schema,$count));
    }
    echo json_encode(['ok'=>true,'mode'=>'generate-only','seed_sql'=>$seed_sql,'tables'=>array_keys($order)]);
    exit;
}

// insert_template: show placeholders for manual editing
if ($task === 'insert_template') {
    $table = trim($body['table'] ?? '');
    if (!$table || !isset($schema[$table])) { echo json_encode(['ok'=>false,'error'=>'Table not found']); exit; }
    $cols = array_column($schema[$table]['columns'],'Field');
    $placeholders = array_map(fn($c)=>"/*{$c}*/",$cols);
    $sql = "INSERT INTO `{$table}` (`".implode("`,`",$cols)."`) VALUES (".implode(", ",$placeholders).");";
    echo json_encode(['ok'=>true,'template'=>$sql]);
    exit;
}

// insert_example: single realistic INSERT (best-effort)
if ($task === 'insert_example') {
    $table = trim($body['table'] ?? '');
    if (!$table || !isset($schema[$table])) { echo json_encode(['ok'=>false,'error'=>'Table not found']); exit; }
    $sql = td_generate_row($conn,$table,$schema);
    echo json_encode(['ok'=>true,'table'=>$table,'example'=>$sql]);
    exit;
}

// top3: call model 3x and return distinct candidates (for user to pick)
if ($task === 'top3') {
    if (!$query) { echo json_encode(['ok'=>false,'error'=>'Empty request']); exit; }
    $candidates=[];
    $model_choice = choose_model('top3',$query,$requested_model,$models);
    for ($i=0;$i<3;$i++) {
        $resp = call_ollama_api(build_generate_prompt($query,$schema), $model_choice);
        if (!$resp['ok']) continue;
        $sql = postprocess_generated_sql($resp['text'],$schema);
        $candidates[] = $sql;
    }
    $candidates = array_values(array_unique($candidates));
    echo json_encode(['ok'=>true,'candidates'=>$candidates,'model'=>$model_choice]);
    exit;
}

// validate: quick clean + schema validate
if ($task === 'validate') {
    if (!$query) { echo json_encode(['ok'=>false,'error'=>'Empty query']); exit; }
    $clean = postprocess_generated_sql($query,$schema);
    $v = validate_sql_against_schema($clean,$schema);
    echo json_encode(['ok'=>true,'clean_sql'=>$clean,'validation'=>$v]);
    exit;
}

/* ---------------------------
   AI-assisted SQL tasks
   - autofix, autofix_strict, optimize, sql, explain
--------------------------- */
$ai_tasks = ['autofix','autofix_strict','optimize','sql','explain'];
if (in_array($task,$ai_tasks)) {
    if ($task !== 'explain' && !$query) { echo json_encode(['ok'=>false,'error'=>'Empty request']); exit; }

    // choose model based on mixed policy
    $model_choice = choose_model($task,$query,$requested_model,$models);

    // build prompt
    switch ($task) {
        case 'autofix':
        case 'autofix_strict':
            $prompt = build_autofix_prompt($query,$schema); break;
        case 'optimize':
            $prompt = build_optimize_prompt($query,$schema); break;
        case 'sql':
            $prompt = build_generate_prompt($query,$schema); break;
        case 'explain':
            $prompt = "Explain this SQL in simple terms. Do not use markdown.\n\nSQL:\n" . $query; break;
        default:
            $prompt = build_generate_prompt($query,$schema);
    }

    // call model (try chosen -> fallback large if small fails)
    $resp = call_ollama_api($prompt, $model_choice);
    if ((!$resp['ok']) && $USE_HF) $resp = call_hf_api($prompt, $HF_MODEL, $HF_API_KEY);

    // If chosen was small and returned something but validation fails badly, try large fallback once
    $raw_text = $resp['text'] ?? '';
    $clean_sql = postprocess_generated_sql($raw_text,$schema);

    // validate result
    $validation = validate_sql_against_schema($clean_sql,$schema);

    if (!$resp['ok'] || (!$validation['ok'] && $model_choice === $models['small'])) {
        // attempt one large-model fallback
        $fallback = $models['large'];
        $resp2 = call_ollama_api($prompt, $fallback);
        if ((!$resp2['ok']) && $USE_HF) $resp2 = call_hf_api($prompt,$HF_MODEL,$HF_API_KEY);
        if ($resp2['ok']) {
            $raw_text = $resp2['text']; $clean_sql = postprocess_generated_sql($raw_text,$schema);
            $validation = validate_sql_against_schema($clean_sql,$schema);
            $model_choice = $fallback;
        }
    }

    // Special: autofix_strict -> auto-map token typos + produce confidence
    if ($task === 'autofix_strict') {
        $mapped = postprocess_generated_sql($clean_sql,$schema);
        $validation = validate_sql_against_schema($mapped,$schema);
        $score = 100 - (count($validation['missing_tables'])*30) - (array_sum(array_map('count', $validation['missing_columns']))*10);
        if (strlen($mapped) < 10) $score -= 20;
        $score = max(0,min(100,$score));
        echo json_encode(['ok'=>true,'sql'=>$mapped,'raw'=>$raw_text,'validation'=>$validation,'confidence'=>$score,'model'=>$model_choice]);
        exit;
    }

    // Return cleaned SQL + validation info (for sql/optimize/autofix)
    if (in_array($task,['sql','optimize','autofix'])) {
        echo json_encode(['ok'=>true,'text'=>$clean_sql,'raw'=>$raw_text,'validation'=>$validation,'model'=>$model_choice]);
        exit;
    }

    // Explain
    echo json_encode(['ok'=>true,'text'=>$raw_text,'model'=>$model_choice]);
    exit;
}

/* ---------------------------
   Fallback direct sql generation (if user used task 'sql' earlier)
--------------------------- */
if ($task === 'sql') {
    if (!$query) { echo json_encode(['ok'=>false,'error'=>'Empty request']); exit; }
    $model_choice = choose_model('sql',$query,$requested_model,$models);
    $resp = call_ollama_api(build_generate_prompt($query,$schema), $model_choice);
    if ((!$resp['ok']) && $USE_HF) $resp = call_hf_api(build_generate_prompt($query,$schema),$HF_MODEL,$HF_API_KEY);
    if (!$resp['ok']) { echo json_encode(['ok'=>false,'error'=>$resp['error'] ?? 'AI backend failure']); exit; }
    $clean_sql = postprocess_generated_sql($resp['text'],$schema);
    $validation = validate_sql_against_schema($clean_sql,$schema);
    // fallback to large if small failed as above
    if (!$validation['ok'] && $model_choice === $models['small']) {
        $resp2 = call_ollama_api(build_generate_prompt($query,$schema), $models['large']);
        if ($resp2['ok']) { $clean_sql = postprocess_generated_sql($resp2['text'],$schema); $validation = validate_sql_against_schema($clean_sql,$schema); $model_choice = $models['large']; }
    }
    echo json_encode(['ok'=>true,'sql'=>$clean_sql,'raw'=>$resp['text'] ?? '','validation'=>$validation,'model'=>$model_choice]);
    exit;
}

/* ---------------------------
   Unknown task
--------------------------- */
echo json_encode(['ok'=>false,'error'=>'Unknown or unsupported task']);
exit;
