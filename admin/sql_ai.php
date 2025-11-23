    <?php
// admin/sql_ai.php
// AI assistant for SQL generation — Ollama (local) preferred, fallback to Hugging Face Inference API.
// Requires: session auth (auth_check.php), curl enabled in PHP

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../db.php';

// Ensure only admins can use AI (adjust as per your auth)
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false, 'error'=>'Forbidden: admin only']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Configuration (environment overrides)
$OLLAMA_URL = getenv('OLLAMA_URL') ?: 'http://localhost:11434';
$DEFAULT_OLLAMA_MODEL = getenv('OLLAMA_MODEL') ?: 'llama2'; // change to the model you have locally
$HUGGINGFACE_KEY = getenv('HUGGINGFACE_API_KEY') ?: (isset($config['hf_key']) ? $config['hf_key'] : null);

// Read input
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$prompt = trim($input['prompt'] ?? '');
$max_tokens = intval($input['max_tokens'] ?? 512);
$model = $input['model'] ?? null;

if ($prompt === '') {
    echo json_encode(['ok'=>false, 'error'=>'Empty prompt']);
    exit;
}

// Helper: try curl request and return [ok, response, status_code, error]
function http_post_json($url, $body, $headers = []) {
    $ch = curl_init($url);
    $payload = json_encode($body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $hdrs = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
    // small timeout so UI doesn't hang
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    $err = null;
    if ($resp === false) $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$err === null, $resp, $code, $err];
}

// Build contextual prompt: include schema overview (tables + columns) for better SQL generation
function build_contextual_prompt($pdo_conn, $user_prompt) {
    // using existing $conn (mysqli) to query information_schema
    global $conn;
    $dbName = $conn->real_escape_string($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? '');
    $qt = $conn->prepare("
        SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ");
    $qt->bind_param('s', $dbName);
    $qt->execute();
    $rt = $qt->get_result();
    $tables = [];
    while ($r = $rt->fetch_assoc()) {
        $tables[$r['TABLE_NAME']][] = $r['COLUMN_NAME'] . ' (' . $r['COLUMN_TYPE'] . ')';
    }
    $qt->close();

    $schema_lines = [];
    foreach ($tables as $t => $cols) {
        $schema_lines[] = "$t: " . implode(', ', array_slice($cols,0,25));
    }
    $schema_text = implode("\n", array_slice($schema_lines,0,80));

    $system = "You are an assistant that writes correct MariaDB / MySQL SQL queries. Use the schema provided. If the user's instruction is ambiguous, produce a short explanation and a best-effort SQL query. Return only the SQL in a JSON object with field 'sql'. Do NOT execute anything.";

    $prompt = $system . "\n\nSCHEMA:\n" . $schema_text . "\n\nUser request:\n" . $user_prompt . "\n\nReturn JSON like: {\"sql\":\"<SQL here>\"}";

    return $prompt;
}

// 1) Try Ollama (local) if reachable
$ollama_available = false;
$ollama_resp = null;
$chosen_backend = null;

$full_prompt = build_contextual_prompt($conn, $prompt);

// Try Ollama local server: we'll attempt to POST to /api/generate or /v1/models/{model}/chat depending on availability.
// Many Ollama setups expose: POST {OLLAMA_URL}/api/generate with { model, prompt }
// We'll try /api/generate first.
$ollama_try_body = [
    'model' => $model ?: $DEFAULT_OLLAMA_MODEL,
    'prompt' => $full_prompt,
    'max_tokens' => $max_tokens
];

list($ok, $resp, $code, $err) = http_post_json(rtrim($OLLAMA_URL, '/') . '/api/generate', $ollama_try_body);
if ($ok && $code >= 200 && $code < 300 && $resp) {
    $ollama_available = true;
    $ollama_resp = $resp;
    $chosen_backend = 'ollama_api_generate';
} else {
    // Try alternate Ollama path: /v1/complete or /v1/chat/completions (some local adapters use OpenAI-compatible endpoints)
    list($ok2, $resp2, $code2, $err2) = http_post_json(rtrim($OLLAMA_URL, '/') . '/v1/complete', [
        'model' => $model ?: $DEFAULT_OLLAMA_MODEL,
        'prompt' => $full_prompt,
        'max_tokens' => $max_tokens
    ]);
    if ($ok2 && $code2 >= 200 && $code2 < 300 && $resp2) {
        $ollama_available = true;
        $ollama_resp = $resp2;
        $chosen_backend = 'ollama_v1_complete';
    } else {
        $ollama_available = false;
    }
}

// If Ollama available, attempt to parse result
if ($ollama_available) {
    // many Ollama endpoints return plain text or JSON with 'text' field — pass through raw and attempt to extract JSON
    $raw = $ollama_resp;
    // try parse JSON
    $decoded = json_decode($raw, true);
    if ($decoded && isset($decoded['text'])) {
        $text = $decoded['text'];
    } elseif ($decoded && isset($decoded['output'])) {
        $text = is_string($decoded['output']) ? $decoded['output'] : (json_encode($decoded['output']));
    } else {
        // fallback: raw body as text
        $text = is_string($raw) ? $raw : json_encode($raw);
    }

    // attempt to extract JSON { "sql": "..." }
    if (preg_match('/\{.*"sql".*\}/s', $text, $m)) {
        $j = json_decode($m[0], true);
        if ($j && isset($j['sql'])) {
            echo json_encode(['ok'=>true, 'backend'=>'ollama', 'sql'=>$j['sql']]);
            exit;
        }
    }

    // if not JSON, return raw assistant text
    echo json_encode(['ok'=>true, 'backend'=>'ollama', 'text'=>$text]);
    exit;
}

// 2) Fallback: Hugging Face Inference API (requires HF key)
if (!empty($HUGGINGFACE_KEY)) {
    $hf_model = $model ?: 'google/flan-t5-large'; // example instruction-tuned model; change as you like
    $hf_url = "https://api-inference.huggingface.co/models/" . urlencode($hf_model);
    list($okh, $resph, $codeh, $errh) = http_post_json($hf_url, ['inputs' => $full_prompt, 'parameters' => ['max_new_tokens' => $max_tokens]], ['Authorization: Bearer ' . $HUGGINGFACE_KEY]);
    if ($okh && $codeh >= 200 && $codeh < 300 && $resph) {
        // HF may return JSON array or object with generated_text
        $dec = json_decode($resph, true);
        if (is_array($dec) && isset($dec[0]['generated_text'])) {
            $out_text = $dec[0]['generated_text'];
            // try extract JSON {sql: ...}
            if (preg_match('/\{.*"sql".*\}/s', $out_text, $m)) {
                $j = json_decode($m[0], true);
                if ($j && isset($j['sql'])) {
                    echo json_encode(['ok'=>true, 'backend'=>'huggingface', 'sql'=>$j['sql']]);
                    exit;
                }
            }
            echo json_encode(['ok'=>true, 'backend'=>'huggingface', 'text'=>$out_text]);
            exit;
        } else {
            // some HF models return plain text
            $out_text = is_string($resph) ? $resph : json_encode($resph);
            echo json_encode(['ok'=>true, 'backend'=>'huggingface', 'text'=>$out_text]);
            exit;
        }
    } else {
        // HF failed
        $hf_err = $errh ?: ("HTTP code: $codeh");
    }
}

// If we reached here, no AI backend worked
$help = "AI backend not available. To enable:\n"
    . "- Option A: Run Ollama locally (https://ollama.ai). Start a model and ensure the Ollama daemon listens on http://localhost:11434. Set OLLAMA_URL env if different and OLLAMA_MODEL to the model name.\n"
    . "- Option B: Provide a Hugging Face Inference API key in env HUGGINGFACE_API_KEY and optionally set HUGGINGFACE_MODEL.\n";

echo json_encode(['ok'=>false, 'error'=>'No AI backend available', 'howto'=>$help]);
exit;
