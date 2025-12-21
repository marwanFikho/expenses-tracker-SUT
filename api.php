<?php
// Minimal PHP + SQLite API for the expense tracker.
// Endpoints (all JSON):
// GET  /api.php?path=state
// POST /api.php?path=expense        { amount, merchant, beneficial (0|1), ts? }
// PUT  /api.php?path=expense&id=ID   { amount, merchant, beneficial, ts? }
// DELETE /api.php?path=expense&id=ID
// POST /api.php?path=income         { amount, source, ts? }
// POST /api.php?path=caps           { day, week, month }
// POST /api.php?path=prefs          { aiEnabled }
// Utility: set header("Access-Control-Allow-Origin: *") if serving from another origin.

header('Content-Type: application/json');

// Load .env if present (simple parser)
function load_dotenv(string $path = null): array {
    $path = $path ?? __DIR__ . '/.env';
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = preg_split('/\r?\n/', file_get_contents($path));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // remove surrounding quotes
        if ((strpos($v, '"') === 0 && strrpos($v, '"') === strlen($v)-1) || (strpos($v, "'") === 0 && strrpos($v, "'") === strlen($v)-1)) {
            $v = substr($v, 1, -1);
        }
        $vars[$k] = $v;
        // populate PHP environment so getenv() works
        putenv("$k=$v");
        $_ENV[$k] = $v;
    }
    return $vars;
}
load_dotenv();

if (!class_exists('SQLite3')) {
    http_response_code(500);
    echo json_encode(['error' => 'SQLite3 extension is not enabled. Install/enable php-sqlite3 (or pdo_sqlite) and restart PHP.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    $db = get_db();
    ensure_schema($db);
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    switch ($path) {
        case 'state':
            if ($method !== 'GET') fail(405, 'Method not allowed');
            respond(get_state($db));
            break;
        case 'expense':
            handle_expense($db, $method);
            break;
        case 'income':
            handle_income($db, $method);
            break;
        case 'caps':
            handle_caps($db, $method);
            break;
        case 'prefs':
            handle_prefs($db, $method);
            break;
        case 'insights':
            handle_insights($db, $method);
            break;
        case 'debug_env':
            if ($method !== 'GET') fail(405, 'Method not allowed');
            $pathEnv = __DIR__ . '/.env';
            $exists = file_exists($pathEnv);
            $content = null;
            $readable = false;
            $size = null;
            if ($exists) {
                $readable = is_readable($pathEnv);
                $size = $readable ? filesize($pathEnv) : null;
                $content = $readable ? file_get_contents($pathEnv) : null;
            }
            // attempt parse using our loader
            $parsed = [];
            if ($exists) {
                $parsed = [];
                $lines = preg_split('/\r?\n/', $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0) continue;
                    if (!strpos($line, '=')) continue;
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if ((strpos($v, '"') === 0 && strrpos($v, '"') === strlen($v)-1) || (strpos($v, "'") === 0 && strrpos($v, "'") === strlen($v)-1)) {
                        $v = substr($v, 1, -1);
                    }
                    $parsed[$k] = $v;
                }
            }
            respond([
                'env_file_path' => $pathEnv,
                'exists' => $exists,
                'is_readable' => $readable,
                'filesize' => $size,
                'content_preview' => $content ? substr($content, 0, 1024) : null,
                'parsed' => $parsed,
                'getenv_OPENAI_API_KEY' => getenv('OPENAI_API_KEY') ?: null,
                'getenv_OPEN_AI_API_KEY' => getenv('OPEN_AI_API_KEY') ?: null,
                'ENV_OPENAI_API_KEY' => $_ENV['OPENAI_API_KEY'] ?? null,
                'ENV_OPEN_AI_API_KEY' => $_ENV['OPEN_AI_API_KEY'] ?? null,
                'allow_url_fopen' => ini_get('allow_url_fopen'),
                'curl_available' => function_exists('curl_init'),
                'openssl_available' => extension_loaded('openssl'),
            ]);
            break;
        default:
            fail(404, 'Unknown path');
    }
} catch (Exception $e) {
    fail(500, $e->getMessage());
}

// ---------- Handlers ----------
function handle_expense(SQLite3 $db, string $method): void {
    if ($method === 'POST') {
        $body = read_json();
        $amount = floatval($body['amount'] ?? 0);
        $merchant = trim($body['merchant'] ?? '');
        $beneficial = intval($body['beneficial'] ?? 0);
        $ts = intval($body['ts'] ?? time()*1000);
        if ($amount <= 0 || $merchant === '') fail(400, 'Amount>0 and merchant required');

        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT INTO expenses(amount, merchant, beneficial, ts) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $amount, SQLITE3_FLOAT);
        $stmt->bindValue(2, $merchant, SQLITE3_TEXT);
        $stmt->bindValue(3, $beneficial, SQLITE3_INTEGER);
        $stmt->bindValue(4, $ts, SQLITE3_INTEGER);
        $stmt->execute();
        adjust_wallet($db, -$amount);
        $db->exec('COMMIT');

        respond(['ok' => true, 'id' => $db->lastInsertRowID()]);
    } elseif ($method === 'PUT') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) fail(400, 'Expense id required');
        $body = read_json();
        $amount = floatval($body['amount'] ?? 0);
        $merchant = trim($body['merchant'] ?? '');
        $beneficial = intval($body['beneficial'] ?? 0);
        $ts = intval($body['ts'] ?? time()*1000);
        if ($amount <= 0 || $merchant === '') fail(400, 'Amount>0 and merchant required');

        $db->exec('BEGIN');
        $old = fetch_one($db, 'SELECT amount FROM expenses WHERE id = ?', [$id]);
        if (!$old) { $db->exec('ROLLBACK'); fail(404, 'Expense not found'); }
        $delta = $old['amount'] - $amount; // refund old, charge new

        $stmt = $db->prepare('UPDATE expenses SET amount=?, merchant=?, beneficial=?, ts=? WHERE id=?');
        $stmt->bindValue(1, $amount, SQLITE3_FLOAT);
        $stmt->bindValue(2, $merchant, SQLITE3_TEXT);
        $stmt->bindValue(3, $beneficial, SQLITE3_INTEGER);
        $stmt->bindValue(4, $ts, SQLITE3_INTEGER);
        $stmt->bindValue(5, $id, SQLITE3_INTEGER);
        $stmt->execute();
        adjust_wallet($db, $delta);
        $db->exec('COMMIT');

        respond(['ok' => true]);
    } elseif ($method === 'DELETE') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) fail(400, 'Expense id required');
        $db->exec('BEGIN');
        $old = fetch_one($db, 'SELECT amount FROM expenses WHERE id = ?', [$id]);
        if (!$old) { $db->exec('ROLLBACK'); fail(404, 'Expense not found'); }
        $stmt = $db->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        adjust_wallet($db, $old['amount']);
        $db->exec('COMMIT');
        respond(['ok' => true]);
    } else {
        fail(405, 'Method not allowed');
    }
}

function handle_income(SQLite3 $db, string $method): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $amount = floatval($body['amount'] ?? 0);
    $source = trim($body['source'] ?? '');
    $ts = intval($body['ts'] ?? time()*1000);
    if ($amount <= 0 || $source === '') fail(400, 'Amount>0 and source required');

    $db->exec('BEGIN');
    $stmt = $db->prepare('INSERT INTO incomes(amount, source, ts) VALUES (?, ?, ?)');
    $stmt->bindValue(1, $amount, SQLITE3_FLOAT);
    $stmt->bindValue(2, $source, SQLITE3_TEXT);
    $stmt->bindValue(3, $ts, SQLITE3_INTEGER);
    $stmt->execute();
    adjust_wallet($db, $amount);
    $db->exec('COMMIT');

    respond(['ok' => true, 'id' => $db->lastInsertRowID()]);
}

function handle_caps(SQLite3 $db, string $method): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $day = floatval($body['day'] ?? 0);
    $week = floatval($body['week'] ?? 0);
    $month = floatval($body['month'] ?? 0);

    $stmt = $db->prepare('UPDATE caps SET day=?, week=?, month=? WHERE id=1');
    $stmt->bindValue(1, $day, SQLITE3_FLOAT);
    $stmt->bindValue(2, $week, SQLITE3_FLOAT);
    $stmt->bindValue(3, $month, SQLITE3_FLOAT);
    $stmt->execute();

    respond(['ok' => true]);
}

function handle_prefs(SQLite3 $db, string $method): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $ai = !empty($body['aiEnabled']) ? 1 : 0;
    $stmt = $db->prepare('UPDATE prefs SET ai_enabled=? WHERE id=1');
    $stmt->bindValue(1, $ai, SQLITE3_INTEGER);
    $stmt->execute();
    respond(['ok' => true]);
}

function get_state(SQLite3 $db): array {
    $walletRow = fetch_one($db, 'SELECT balance FROM wallet WHERE id=1', []);
    $capsRow = fetch_one($db, 'SELECT day, week, month FROM caps WHERE id=1', []);
    $expenses = fetch_all($db, 'SELECT id, amount, merchant, beneficial, ts FROM expenses ORDER BY ts DESC');
    $incomes = fetch_all($db, 'SELECT id, amount, source, ts FROM incomes ORDER BY ts DESC');
    $prefs = fetch_one($db, 'SELECT ai_enabled FROM prefs WHERE id=1', []);

    return [
        'wallet' => $walletRow ? floatval($walletRow['balance']) : 0,
        'caps' => $capsRow ?: ['day' => 0, 'week' => 0, 'month' => 0],
        'expenses' => $expenses,
        'incomes' => $incomes,
        'aiEnabled' => $prefs ? (bool)$prefs['ai_enabled'] : true,
    ];
}

function handle_insights(SQLite3 $db, string $method): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = @json_decode(file_get_contents('php://input'), true) ?: [];

    $state = get_state($db);
    if (empty($state['aiEnabled'])) {
        respond(['ok' => false, 'error' => 'AI is disabled in preferences']);
    }

    // Build a concise prompt with recent expenses and caps
    $expenses = array_slice($state['expenses'], 0, 50);
    $rows = [];
    foreach ($expenses as $e) {
        $rows[] = sprintf("%s | %s | %s | %s", date('Y-m-d', intval($e['ts']/1000)), $e['merchant'], $e['amount'], $e['beneficial'] ? 'beneficial' : 'not_beneficial');
    }

    $prompt = "You are a helpful, friendly personal finance assistant.\n";
    $prompt .= "User wallet: " . ($state['wallet'] ?? 0) . "\n";
    $prompt .= "Caps: day=" . ($state['caps']['day'] ?? 0) . ", week=" . ($state['caps']['week'] ?? 0) . ", month=" . ($state['caps']['month'] ?? 0) . "\n";
    $prompt .= "Recent expenses (date | merchant | amount | beneficial):\n" . implode("\n", $rows) . "\n\n";
    $prompt .= "Please provide a short JSON object with keys: summary (1-2 sentences), suggestions (array of short actionable tips), categories (simple breakdown by merchant or type), anomalies (list any unusual large or rare expenses), and one concrete 3-step action plan to improve spending behavior. Keep language concise and friendly.";

    $resp = call_llm($prompt);
    if ($resp === null) {
        $err = $GLOBALS['LLM_LAST_ERROR'] ?? 'LLM request failed or API key not configured';
        fail(500, 'LLM request failed: ' . $err);
    }

    // Try to parse JSON from model, otherwise return raw text
    $json = json_decode($resp, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        respond(['ok' => true, 'insights' => $json]);
    }

    respond(['ok' => true, 'text' => $resp]);
}

function call_llm(string $prompt): ?string {
    // reset last error
    $GLOBALS['LLM_LAST_ERROR'] = null;

    // --- Load API keys (support multiple providers) ---
    $openrouter_key = getenv('OPENAI_API_KEY') ?: getenv('OPEN_AI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? $_ENV['OPEN_AI_API_KEY'] ?? null);
    $hf_key = getenv('HUGGINGFACE_API_KEY') ?: $_ENV['HUGGINGFACE_API_KEY'] ?? null;
    if (!$openrouter_key && !$hf_key) {
        $GLOBALS['LLM_LAST_ERROR'] = 'No API key found (set OPENAI_API_KEY/OPEN_AI_API_KEY or HUGGINGFACE_API_KEY in .env)';
        error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
        return null;
    }

    // --- Prefer Hugging Face inference when configured ---
    $hf_key = getenv('HUGGINGFACE_API_KEY') ?: getenv('HF_API_KEY') ?: $_ENV['HUGGINGFACE_API_KEY'] ?? $_ENV['HF_API_KEY'] ?? null;
    $hf_url = getenv('HUGGINGFACE_API_URL') ?: getenv('HUGGINGFACE_API_INFERENCE') ?: $_ENV['HUGGINGFACE_API_URL'] ?? $_ENV['HUGGINGFACE_API_INFERENCE'] ?? null;
    if ($hf_key && $hf_url) {
        $payload = ['inputs' => $prompt, 'options' => ['use_cache' => false]];
        $jsonPayload = json_encode($payload);

        $hasCurl = function_exists('curl_init') && extension_loaded('openssl');
        $hasFopen = ini_get('allow_url_fopen') && extension_loaded('openssl');
        if (!$hasCurl && !$hasFopen) {
            $GLOBALS['LLM_LAST_ERROR'] = 'PHP cannot make HTTPS requests: enable the openssl extension (and curl if available) in php.ini';
            error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
            return null;
        }

        $targetUrl = $hf_url;
        $authHeader = 'Authorization: Bearer ' . $hf_key;

        // If Python helper exists and python is available, prefer it for DeepSeek-style encoding
        $pythonHelper = __DIR__ . '/hf_infer.py';
        $pythonAvailable = false;
        $pyCheck = @shell_exec('python --version 2>&1');
        if ($pyCheck && stripos($pyCheck, 'python') !== false && file_exists($pythonHelper)) {
            $pythonAvailable = true;
        }
        if ($pythonAvailable) {
            // build chat-completions style payload matching HF router example
            $hfModel = 'deepseek-ai/DeepSeek-V3.2:novita';
            $pyPayload = json_encode([
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'model' => $hfModel,
                'stream' => false
            ]);
            $escaped = escapeshellarg($pyPayload);
            $cmd = "python " . escapeshellarg($pythonHelper) . " " . $escaped . " 2>&1";
            $out = shell_exec($cmd);
            if ($out === null) {
                $GLOBALS['LLM_LAST_ERROR'] = 'Python helper failed or returned no output';
            } else {
                return $out;
            }
        }

        if ($hasCurl) {
            $ch = curl_init($targetUrl);
            $curlOpts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    $authHeader
                ],
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_TIMEOUT => 60
            ];
            // In local dev environments on Windows PHP/cURL may fail SSL verification.
            // Relax verification for Hugging Face inference endpoint only (dev only).
            if (strpos($targetUrl, 'huggingface') !== false) {
                $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
                $curlOpts[CURLOPT_SSL_VERIFYHOST] = false;
            }
            curl_setopt_array($ch, $curlOpts);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("LLM CURL RESPONSE (HF): HTTP $httpCode => $res");
            if ($res === false) {
                $GLOBALS['LLM_LAST_ERROR'] = 'cURL error: ' . $err;
                error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
                return null;
            } else {
                $data = json_decode($res, true);
                if (is_array($data)) {
                    // common HF inference outputs: array of {generated_text} or {"generated_text": "..."}
                    if (isset($data['generated_text'])) return $data['generated_text'];
                    if (isset($data[0]['generated_text'])) return $data[0]['generated_text'];
                    // some models return a string directly
                }
                // fallback: return raw string
                return is_string($res) ? $res : json_encode($res);
            }
        } else {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n" . $authHeader . "\r\n",
                    'content' => $jsonPayload,
                    'timeout' => 60
                ]
            ];
            $context = stream_context_create($opts);
            $res = @file_get_contents($targetUrl, false, $context);
            if ($res === false) {
                $hdr = $http_response_header ?? null;
                $msg = 'file_get_contents failed';
                if (is_array($hdr)) $msg .= ' — response headers: ' . implode(' | ', $hdr);
                $GLOBALS['LLM_LAST_ERROR'] = $msg;
                error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
                return null;
            } else {
                $data = json_decode($res, true);
                if (is_array($data)) {
                    if (isset($data['generated_text'])) return $data['generated_text'];
                    if (isset($data[0]['generated_text'])) return $data[0]['generated_text'];
                }
                return is_string($res) ? $res : json_encode($res);
            }
        }
        // If HF path failed, continue to try other providers below (if configured)
    }

    // --- Load primary and fallback models (do not default to an OpenAI model) ---
    $primaryModel = getenv('OPENAI_MODEL') ?: getenv('OPEN_AI_MODEL') ?: ($_ENV['OPENAI_MODEL'] ?? $_ENV['OPEN_AI_MODEL'] ?? null);
    $fallbackModel = getenv('OPENAI_FALLBACK_MODEL') ?: $_ENV['OPENAI_FALLBACK_MODEL'] ?? null;

    $modelsToTry = [];
    if ($primaryModel) $modelsToTry[] = $primaryModel;
    if ($fallbackModel && $fallbackModel !== $primaryModel) $modelsToTry[] = $fallbackModel;

    if (empty($modelsToTry)) {
        $GLOBALS['LLM_LAST_ERROR'] = 'No model specified in environment (OPENAI_MODEL or OPEN_AI_MODEL)';
        error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
        return null;
    }

    // If a fallback model exists but appears to be for a different provider than primary, ignore it.
    if (count($modelsToTry) > 1) {
        $a = $modelsToTry[0];
        $b = $modelsToTry[1];
        $detect = function($m) {
            $m = strtolower($m);
            if (strpos($m, 'google') !== false || strpos($m, 'gemini') !== false) return 'google';
            if (strpos($m, 'openai') !== false || strpos($m, 'gpt-4') !== false || strpos($m, 'gpt-4o') !== false) return 'openai';
            if (strpos($m, 'gpt-oss') !== false || strpos($m, 'oss') !== false) return 'openrouter';
            return 'unknown';
        };
        $pa = $detect($a);
        $pb = $detect($b);
        if ($pa !== 'unknown' && $pb !== 'unknown' && $pa !== $pb) {
            // drop the fallback
            $modelsToTry = [$a];
        }
    }

    // --- Detect provider / base URL ---
    $openrouter_url = getenv('OPENROUTER_API_URL') ?: null;
    $isOpenRouter = ($openrouter_key && strpos($openrouter_key, 'sk-or-') === 0) || $openrouter_url !== null;
    $openrouter_base = $openrouter_url ?: 'https://api.openrouter.ai/v1/chat/completions';
    $isHuggingFace = (bool)$hf_key;
    $huggingface_base = 'https://api.huggingface-apis.com/v1/chat/completions';

    // --- Prepare payload ---
    $payloadBase = [
        'messages' => [
            ['role' => 'system', 'content' => 'You are a concise personal finance assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.6,
        'max_tokens' => 600
    ];

    // --- Try each model ---
    foreach ($modelsToTry as $model) {
        $payload = $payloadBase;
        $payload['model'] = $model;

        $jsonPayload = json_encode($payload);
        $res = null;

        // Check capability: need either curl+openssl, or allow_url_fopen+openssl for HTTPS
        $hasCurl = function_exists('curl_init') && extension_loaded('openssl');
        $hasFopen = ini_get('allow_url_fopen') && extension_loaded('openssl');
        if (!$hasCurl && !$hasFopen) {
            $GLOBALS['LLM_LAST_ERROR'] = 'PHP cannot make HTTPS requests: enable the openssl extension (and curl if available) in php.ini';
            error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
            return null;
        }
        // Choose endpoint and auth depending on provider availability
        if ($isHuggingFace) {
            $targetUrl = $huggingface_base;
            $authHeader = 'Authorization: Bearer ' . $hf_key;
        } else {
            $targetUrl = $openrouter_base;
            $authHeader = 'Authorization: Bearer ' . $openrouter_key;
        }

        if ($hasCurl) {
            $ch = curl_init($targetUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    $authHeader
                ],
                CURLOPT_POSTFIELDS => $jsonPayload,
                CURLOPT_TIMEOUT => 60
            ]);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            error_log("LLM CURL RESPONSE: HTTP $httpCode => $res");

            if ($res === false) {
                $GLOBALS['LLM_LAST_ERROR'] = 'cURL error: ' . $err;
                error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
                curl_close($ch);
                continue;
            }
            curl_close($ch);

            if ($httpCode >= 400) {
                $GLOBALS['LLM_LAST_ERROR'] = "HTTP $httpCode: $res";
                error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
                continue; // try fallback
            }
        } else {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n" . $authHeader . "\r\n",
                    'content' => $jsonPayload,
                    'timeout' => 60
                ]
            ];
            $context = stream_context_create($opts);
            $res = @file_get_contents($targetUrl, false, $context);
            if ($res === false) {
                $hdr = $http_response_header ?? null;
                $msg = 'file_get_contents failed';
                if (is_array($hdr)) $msg .= ' — response headers: ' . implode(' | ', $hdr);
                $GLOBALS['LLM_LAST_ERROR'] = $msg;
                error_log('LLM ERROR: ' . $GLOBALS['LLM_LAST_ERROR']);
                continue; // try fallback
            }
        }

        // --- Parse response ---
        $data = json_decode($res, true);
        if ($data && isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        if ($data && isset($data['choices'][0]['text'])) {
            return $data['choices'][0]['text'];
        }

        // If no usable content, log raw response
        error_log("LLM WARNING: model $model returned unexpected response: $res");
    }

    // If all models fail
    error_log("LLM ERROR: All models failed to return a valid response");
    return null;
}

// extra functions for db
function get_db(): SQLite3 {
    $dbPath = __DIR__ . '/data.sqlite';
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);
    return $db;
}

function ensure_schema(SQLite3 $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS wallet (id INTEGER PRIMARY KEY, balance REAL NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        amount REAL NOT NULL,
        merchant TEXT NOT NULL,
        beneficial INTEGER NOT NULL DEFAULT 0,
        ts INTEGER NOT NULL
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS incomes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        amount REAL NOT NULL,
        source TEXT NOT NULL,
        ts INTEGER NOT NULL
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS caps (
        id INTEGER PRIMARY KEY,
        day REAL NOT NULL DEFAULT 0,
        week REAL NOT NULL DEFAULT 0,
        month REAL NOT NULL DEFAULT 0
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS prefs (
        id INTEGER PRIMARY KEY,
        ai_enabled INTEGER NOT NULL DEFAULT 1
    )');

    // Seed single rows for wallet and caps
    $db->exec('INSERT OR IGNORE INTO wallet(id, balance) VALUES (1, 0)');
    $db->exec('INSERT OR IGNORE INTO caps(id, day, week, month) VALUES (1, 0, 0, 0)');
    $db->exec('INSERT OR IGNORE INTO prefs(id, ai_enabled) VALUES (1, 1)');
}

function adjust_wallet(SQLite3 $db, float $delta): void {
    $stmt = $db->prepare('UPDATE wallet SET balance = balance + ? WHERE id=1');
    $stmt->bindValue(1, $delta, SQLITE3_FLOAT);
    $stmt->execute();
}

function fetch_one(SQLite3 $db, string $sql, array $params): ?array {
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $val) {
        $stmt->bindValue($i+1, $val, is_int($val) ? SQLITE3_INTEGER : (is_float($val) ? SQLITE3_FLOAT : SQLITE3_TEXT));
    }
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function fetch_all(SQLite3 $db, string $sql, array $params = []): array {
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $val) {
        $stmt->bindValue($i+1, $val, is_int($val) ? SQLITE3_INTEGER : (is_float($val) ? SQLITE3_FLOAT : SQLITE3_TEXT));
    }
    $res = $stmt->execute();
    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $rows[] = $row; }
    return $rows;
}

function read_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) fail(400, 'Invalid JSON');
    return $data;
}

function respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}
