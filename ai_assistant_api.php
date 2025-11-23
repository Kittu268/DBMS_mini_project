<?php
// ai_assistant_api.php
header('Content-Type: application/json; charset=UTF-8');
ini_set('log_errors', 1);
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function jsonReply($reply, $extra = []) {
    echo json_encode(array_merge(['reply' => $reply], $extra));
    exit;
}

function safeEchoHtml($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Ensure chat_history folder exists
$history_dir = __DIR__ . '/chat_history';
if (!is_dir($history_dir)) @mkdir($history_dir, 0755, true);

// Basic error handler which returns JSON (so client sees something)
set_error_handler(function($sev, $msg, $file=null, $line=null){
    jsonReply("⚠️ Server error: $msg");
});
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err) jsonReply("⚠️ Fatal error: {$err['message']}");
});

// Read input
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$prompt_raw = trim($data['prompt'] ?? '');
$prompt = mb_strtolower($prompt_raw, 'UTF-8');
$userEmail = $_SESSION['email'] ?? null;
$userName  = is_array($_SESSION['user']) ? ($_SESSION['user']['Cname'] ?? ($_SESSION['user']['username'] ?? null)) : ($_SESSION['user'] ?? null);

// Save chat history functions
function get_history_path($email) {
    global $history_dir;
    return $history_dir . '/' . preg_replace('/[^a-z0-9@\._-]/i','_', $email) . '.json';
}
function append_history($email, $role, $text) {
    $path = get_history_path($email);
    $hist = [];
    if (file_exists($path)) {
        $json = @file_get_contents($path);
        $hist = $json ? json_decode($json, true) : [];
    }
    $hist[] = ['time'=>time(), 'role'=>$role, 'text'=>$text];
    @file_put_contents($path, json_encode($hist, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
function load_history_html($email) {
    $path = get_history_path($email);
    if (!file_exists($path)) return '';
    $json = @file_get_contents($path);
    $hist = $json ? json_decode($json, true) : [];
    $html = "<div class='assistant-memory'>\n";
    foreach ($hist as $item) {
        $cls = $item['role']==='user' ? 'user-message' : 'assistant-message';
        $text = safeEchoHtml($item['text']);
        $time = date('Y-m-d H:i', $item['time']);
        $html .= "<div class='{$cls}' style='margin-bottom:8px'><small style='color:#666'>{$time}</small><div>{$text}</div></div>\n";
    }
    $html .= "</div>\n";
    return $html;
}

// Quick return if no prompt
if (!$prompt_raw) {
    jsonReply("❌ No prompt provided.");
}

// Save user message (if logged-in)
if ($userEmail) append_history($userEmail, 'user', $prompt_raw);

// Helpers for DB queries
function run_select($sql) {
    global $conn;
    $res = $conn->query($sql);
    if ($res === false) return ['error'=>$conn->error];
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    return ['rows'=>$rows, 'fields'=>$res->fetch_fields()];
}

// Spell-correction / fuzzy find flights
function fuzzy_find_flights($q) {
    global $conn;
    $qclean = preg_replace('/[^A-Za-z0-9\s\-]/','', $q);
    // fetch flight numbers + airline names
    $list = [];
    $res = $conn->query("SELECT Flight_number, Airline FROM flight");
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    // score via levenshtein + similar_text
    $candidates = [];
    foreach ($list as $item) {
        $fn = $item['Flight_number'];
        $air = $item['Airline'];
        $scoreLev = levenshtein(strtolower($qclean), strtolower($fn));
        $scoreSim = 0;
        similar_text(strtolower($qclean), strtolower($fn), $scoreSim);
        $scoreAir = 0;
        similar_text(strtolower($qclean), strtolower($air), $scoreAir);
        $score = ($scoreSim) - $scoreLev + $scoreAir/2;
        $candidates[] = ['flight'=>$fn, 'airline'=>$air, 'score'=>$score];
    }
    usort($candidates, function($a,$b){ return $b['score'] <=> $a['score']; });
    return array_slice($candidates,0,6);
}

// Intent parsing (order of checks matters)
// 1) greetings
if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening)\b/i', $prompt)) {
    $reply = "👋 Hello" . ($userName ? " {$userName}" : '') . "! I can show your bookings, available flights, run an SQL SELECT, or help you book flights. Try the quick suggestions below.";
    $extra = ['quick' => ['Show my bookings', 'Available flights', 'Fare lookup', 'Help', 'Run SQL']];
    // include saved chat history html if user logged in
    if ($userEmail) $extra['memory_html'] = load_history_html($userEmail);
    jsonReply($reply, $extra);
}

// 2) show my bookings / my reservations
if (preg_match('/(my bookings|show my flights|my reservations|my reservation|bookings)/i', $prompt)) {
    if (!$userEmail) jsonReply("❌ Please log in to view your reservations.");
    $stmt = $conn->prepare("SELECT r.Flight_number, r.Leg_no, r.Date, r.Seat_no, li.Departure_time, li.Arrival_time FROM reservation r LEFT JOIN leg_instance li ON r.Flight_number=li.Flight_number AND r.Leg_no=li.Leg_no AND r.Date=li.Date WHERE r.Email=? ORDER BY r.Date DESC");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) jsonReply("📭 You currently have no reservations.");
    // format table HTML
    $html = "<table class='table table-sm table-bordered'><thead><tr><th>Flight</th><th>Leg</th><th>Date</th><th>Depart→Arrive</th><th>Seat</th></tr></thead><tbody>";
    while ($r = $res->fetch_assoc()) {
        $html .= "<tr><td>".safeEchoHtml($r['Flight_number'])."</td><td>".safeEchoHtml($r['Leg_no'])."</td><td>".safeEchoHtml($r['Date'])."</td><td>".safeEchoHtml($r['Departure_time'])." → ".safeEchoHtml($r['Arrival_time'])."</td><td>".safeEchoHtml($r['Seat_no'])."</td></tr>";
    }
    $html .= "</tbody></table>";
    append_history($userEmail, 'assistant', "Displayed your reservations.");
    jsonReply($html, ['type'=>'html']);
}

// 3) available flights
if (preg_match('/(available flights|show flights|list flights|flights available)/i', $prompt)) {
    $q = "SELECT li.Flight_number, f.Airline, li.Leg_no, li.Date, li.Departure_time, li.Arrival_time FROM leg_instance li JOIN flight f ON li.Flight_number=f.Flight_number ORDER BY li.Date ASC, li.Departure_time ASC LIMIT 200";
    $r = $conn->query($q);
    if ($r === false) jsonReply("❌ DB error: ".$conn->error);
    if ($r->num_rows === 0) jsonReply("No upcoming flights found.");
    $html = "<table class='table table-sm table-bordered'><thead><tr><th>Flight</th><th>Airline</th><th>Leg</th><th>Date</th><th>Depart</th><th>Arrive</th><th>Book</th></tr></thead><tbody>";
    while ($row = $r->fetch_assoc()) {
        $f = safeEchoHtml($row['Flight_number']);
        $l = safeEchoHtml($row['Leg_no']);
        $d = safeEchoHtml($row['Date']);
        $html .= "<tr><td>{$f}</td><td>".safeEchoHtml($row['Airline'])."</td><td>{$l}</td><td>{$d}</td><td>".safeEchoHtml($row['Departure_time'])."</td><td>".safeEchoHtml($row['Arrival_time'])."</td>";
        // instruct client to open reservation page with prefilled params
        $bookUrl = "make_reservation.php?flight=".urlencode($row['Flight_number'])."&leg=".urlencode($row['Leg_no'])."&date=".urlencode($row['Date']);
        $html .= "<td><button class='btn btn-sm btn-primary' onclick=\"window.open('{$bookUrl}','_blank')\">Book</button></td></tr>";
    }
    $html .= "</tbody></table>";
    append_history($userEmail ?? 'anon', 'assistant', "Listed available flights.");
    jsonReply($html, ['type'=>'html']);
}

// 4) SQL SELECT execution (explicit: starts with "select" or message contains "sql:" or "run query")
if (preg_match('/^\s*select\b/i', $prompt_raw) || preg_match('/\bsql\b/i', $prompt)) {
    // try to extract SELECT statement
    if (preg_match('/(select .*?);?$/is', $prompt_raw, $m)) {
        $query = $m[1];
        // only allow SELECT (basic safety)
        if (!preg_match('/^\s*select\b/i', $query)) jsonReply("❌ Only SELECT statements are allowed.");
        $res = $conn->query($query);
        if ($res === false) jsonReply("❌ SQL Error: ".$conn->error);
        if ($res->num_rows === 0) jsonReply("✅ Query ran but returned no rows.");
        // build HTML table
        $html = "<table class='table table-sm table-bordered'><thead><tr>";
        $fields = $res->fetch_fields();
        foreach ($fields as $f) $html .= "<th>".safeEchoHtml($f->name)."</th>";
        $html .= "</tr></thead><tbody>";
        while ($r = $res->fetch_assoc()) {
            $html .= "<tr>";
            foreach ($r as $val) $html .= "<td>".safeEchoHtml($val)."</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
        append_history($userEmail ?? 'anon', 'assistant', "Ran SELECT query.");
        jsonReply($html, ['type'=>'html']);
    } else {
        jsonReply("❌ Please include a SELECT statement in your message (e.g. `SELECT * FROM reservation WHERE ...`).");
    }
}

// 5) booking intent: try to detect phrases like "book AI101 2025-02-10" or "book flight AI101 tomorrow"
if (preg_match('/\b(book|reserve|booking)\b/i', $prompt)) {
    // simple extraction: flight code & date & passengers
    // flight code: pattern like AI101 or alphanumeric
    preg_match('/([A-Za-z]{1,3}\d{1,4}|[A-Za-z0-9\-]{3,8})/i', $prompt_raw, $mfn);
    $flight_guess = $mfn[1] ?? null;
    // date (yyyy-mm-dd or words like tomorrow)
    $date = null;
    if (preg_match('/\b(20\d{2}[-\/]\d{2}[-\/]\d{2})\b/', $prompt_raw, $md)) $date = $md[1];
    elseif (preg_match('/\btomorrow\b/i', $prompt)) $date = date('Y-m-d', strtotime('+1 day'));
    elseif (preg_match('/\btoday\b/i', $prompt)) $date = date('Y-m-d');
    // leg number
    $leg = 1;
    if (preg_match('/\bleg\s*(\d+)/i', $prompt_raw, $ml)) $leg = (int)$ml[1];
    // passengers
    $pax = 1;
    if (preg_match('/(\d+)\s*(passenger|passengers|pax)/i', $prompt_raw, $mp)) $pax = (int)$mp[1];

    // If flight guessed, try to fuzzy match to real flights
    if ($flight_guess) {
        $matches = fuzzy_find_flights($flight_guess);
        if (count($matches) === 0) {
            jsonReply("I couldn't find any flight matching '{$flight_guess}'. Try typing the exact flight number or ask 'available flights'.");
        }
        // pick top candidate
        $top = $matches[0];
        $flightNumber = $top['flight'];
        // check if leg_instance exists for that combination
        $stmt = $conn->prepare("SELECT * FROM leg_instance WHERE Flight_number=? AND Leg_no=? AND Date=? LIMIT 1");
        $stmt->bind_param("sis", $flightNumber, $leg, $date);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            // return structured response instructing client to prefill reservation
            $prefill = [
                'flight_number'=>$flightNumber,
                'leg_no'=>$leg,
                'date'=>$date,
                'pax'=>$pax,
                // url to open
                'url'=> "make_reservation.php?flight=".urlencode($flightNumber)."&leg=".$leg."&date=".urlencode($date)."&pax=".$pax
            ];
            append_history($userEmail ?? 'anon', 'assistant', "Prepared a booking prefill for {$flightNumber} on {$date}.");
            jsonReply("Ready — I found flight {$flightNumber} on {$date}. Click the button to open booking (prefilled).", ['type'=>'action','action'=>'prefill_booking','data'=>$prefill]);
        } else {
            // if no exact leg/date, suggest closest available dates (search by flight)
            $stmt2 = $conn->prepare("SELECT Date, Departure_time, Arrival_time FROM leg_instance WHERE Flight_number=? ORDER BY Date ASC LIMIT 5");
            $stmt2->bind_param("s", $flightNumber);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            $list = [];
            while ($row = $r2->fetch_assoc()) $list[] = $row;
            if (count($list) > 0) {
                $optHtml = "<div>Couldn't find that exact date/leg. Available instances for <strong>".safeEchoHtml($flightNumber)."</strong>:</div><ul>";
                foreach ($list as $it) {
                    $optHtml .= "<li>".safeEchoHtml($it['Date'])." — ".safeEchoHtml($it['Departure_time'])." → ".safeEchoHtml($it['Arrival_time'])." <button class='btn btn-sm btn-primary' onclick=\"window.open('make_reservation.php?flight=".urlencode($flightNumber)."&leg={$leg}&date=".urlencode($it['Date'])."','_blank')\">Book</button></li>";
                }
                $optHtml .= "</ul>";
                append_history($userEmail ?? 'anon', 'assistant', "Suggested alternate dates for {$flightNumber}.");
                jsonReply($optHtml, ['type'=>'html']);
            } else {
                jsonReply("No upcoming instances found for flight {$flightNumber}.");
            }
        }
    } else {
        jsonReply("I detected a booking intent but couldn't find a flight code in your message. Please say: *book AI101 tomorrow* (example), or click 'Available flights'.");
    }
}

// 6) fuzzy lookup (user typed a flight with typos) — explicit
if (preg_match('/(find flight|which flight|did you mean)/i', $prompt) || strlen($prompt_raw) <= 7) {
    $candidates = fuzzy_find_flights($prompt_raw);
    if (count($candidates) === 0) jsonReply("No close flight matches found.");
    $html = "<div>Did you mean:</div><ul>";
    foreach ($candidates as $c) {
        $html .= "<li><strong>".safeEchoHtml($c['flight'])."</strong> — ".safeEchoHtml($c['airline'])." <button class='btn btn-sm btn-outline-primary' onclick=\"window.open('make_reservation.php?flight=".urlencode($c['flight'])."&leg=1','_blank')\">Book</button></li>";
    }
    $html .= "</ul>";
    jsonReply($html, ['type'=>'html']);
}

// 7) fallback: try helpful suggestions
$help = "Sorry, I didn't understand that. Try: <ul><li>'Show my bookings'</li><li>'Available flights'</li><li>'Book AI101 tomorrow'</li><li>Or click one of the quick suggestions.</li></ul>";
append_history($userEmail ?? 'anon', 'assistant', "Asked for clarification.");
jsonReply($help, ['type'=>'html','quick'=>['Show my bookings','Available flights','Book AI101 tomorrow','Help']]);
