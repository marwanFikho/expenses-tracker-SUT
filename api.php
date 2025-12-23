<?php

// Endpoints:
// POST /api.php?path=auth/register { email, password }
// POST /api.php?path=auth/login    { email, password }
// POST /api.php?path=auth/logout   (requires token)
// GET  /api.php?path=state        (requires token)
// POST /api.php?path=expense      { amount, merchant, beneficial, ts? } (requires token)

header('Content-Type: application/json');

// Config
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root';
$db_name = 'expense_tracker';
$jwt_secret = 'root';

// DB Connection
$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($db->connect_error) {
    fail(500, "Database connection failed: " . $db->connect_error);
}
$db->set_charset('utf8mb4');

// Router
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    switch ($path) {
        case 'auth/register':
            handle_register($db, $jwt_secret);
            break;
        case 'auth/login':
            handle_login($db, $jwt_secret);
            break;
        case 'auth/logout':
            handle_logout();
            break;
        default:
            $token = get_token();
            $user_id = verify_token($token, $jwt_secret);
            if (!$user_id) fail(401, 'Unauthorized');

            switch ($path) {
                case 'state':
                    if ($method !== 'GET') fail(405, 'Method not allowed');
                    respond(get_state($db, $user_id));
                    break;
                case 'expense':
                    handle_expense($db, $user_id, $method);
                    break;
                case 'income':
                    handle_income($db, $user_id, $method);
                    break;
                case 'caps':
                    handle_caps($db, $user_id, $method);
                    break;
                case 'prefs':
                    handle_prefs($db, $user_id, $method);
                    break;
                default:
                    fail(404, 'Unknown path');
            }
    }
} catch (Exception $e) {
    fail(500, $e->getMessage());
}

// Auth Handlers
function handle_register(mysqli $db, string $jwt_secret): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail(400, 'Invalid email');
    if (strlen($password) < 6) fail(400, 'Password must be at least 6 characters');

    // Check if user exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) fail(400, 'Email already registered');

    // Create user
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users(email, password_hash) VALUES (?, ?)');
    $stmt->bind_param('ss', $email, $hash);
    if (!$stmt->execute()) fail(500, 'Failed to create user');

    $user_id = $db->insert_id;

    // Initialize wallet, caps, prefs
    $stmt = $db->prepare('INSERT INTO wallet(user_id, balance) VALUES (?, 0)');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $stmt = $db->prepare('INSERT INTO caps(user_id, day, week, month) VALUES (?, 0, 0, 0)');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $stmt = $db->prepare('INSERT INTO prefs(user_id, ai_enabled) VALUES (?, 1)');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    $token = create_token($user_id, $jwt_secret);
    respond(['ok' => true, 'token' => $token, 'user_id' => $user_id]);
}

function handle_login(mysqli $db, string $jwt_secret): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) fail(400, 'Email and password required');

    $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) fail(401, 'Invalid email or password');

    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password_hash'])) fail(401, 'Invalid email or password');

    $token = create_token($user['id'], $jwt_secret);
    respond(['ok' => true, 'token' => $token, 'user_id' => $user['id']]);
}

function handle_logout(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed');
    respond(['ok' => true]);
}

// Protected Handlers
function handle_expense(mysqli $db, int $user_id, string $method): void {
    if ($method === 'POST') {
        $body = read_json();
        $amount = floatval($body['amount'] ?? 0);
        $merchant = trim($body['merchant'] ?? '');
        $beneficial = intval($body['beneficial'] ?? 0);
        $ts = intval($body['ts'] ?? time()*1000);
        if ($amount <= 0 || $merchant === '') fail(400, 'Amount>0 and merchant required');

        $db->begin_transaction();
        try {
            $stmt = $db->prepare('INSERT INTO expenses(user_id, amount, merchant, beneficial, ts) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('idsii', $user_id, $amount, $merchant, $beneficial, $ts);
            $stmt->execute();
            $expense_id = $db->insert_id;

            adjust_wallet($db, $user_id, -$amount);
            $db->commit();

            respond(['ok' => true, 'id' => $expense_id]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    } elseif ($method === 'PUT') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) fail(400, 'Expense id required');
        $body = read_json();
        $amount = floatval($body['amount'] ?? 0);
        $merchant = trim($body['merchant'] ?? '');
        $beneficial = intval($body['beneficial'] ?? 0);
        $ts = intval($body['ts'] ?? time()*1000);
        if ($amount <= 0 || $merchant === '') fail(400, 'Amount>0 and merchant required');

        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT amount FROM expenses WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) { $db->rollback(); fail(404, 'Expense not found'); }
            $old = $result->fetch_assoc();

            $delta = $old['amount'] - $amount;
            $stmt = $db->prepare('UPDATE expenses SET amount=?, merchant=?, beneficial=?, ts=? WHERE id=? AND user_id=?');
            $stmt->bind_param('dssiii', $amount, $merchant, $beneficial, $ts, $id, $user_id);
            $stmt->execute();
            adjust_wallet($db, $user_id, $delta);
            $db->commit();

            respond(['ok' => true]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    } elseif ($method === 'DELETE') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) fail(400, 'Expense id required');
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('SELECT amount FROM expenses WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) { $db->rollback(); fail(404, 'Expense not found'); }
            $old = $result->fetch_assoc();

            $stmt = $db->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $id, $user_id);
            $stmt->execute();
            adjust_wallet($db, $user_id, $old['amount']);
            $db->commit();

            respond(['ok' => true]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    } else {
        fail(405, 'Method not allowed');
    }
}

function handle_income(mysqli $db, int $user_id, string $method): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $amount = floatval($body['amount'] ?? 0);
    $source = trim($body['source'] ?? '');
    $ts = intval($body['ts'] ?? time()*1000);
    if ($amount <= 0 || $source === '') fail(400, 'Amount>0 and source required');

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('INSERT INTO incomes(user_id, amount, source, ts) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('idsi', $user_id, $amount, $source, $ts);
        $stmt->execute();
        $income_id = $db->insert_id;

        adjust_wallet($db, $user_id, $amount);
        $db->commit();

        respond(['ok' => true, 'id' => $income_id]);
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handle_caps(mysqli $db, int $user_id, string $method): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $day = floatval($body['day'] ?? 0);
    $week = floatval($body['week'] ?? 0);
    $month = floatval($body['month'] ?? 0);

    $stmt = $db->prepare('UPDATE caps SET day=?, week=?, month=? WHERE user_id=?');
    $stmt->bind_param('dddi', $day, $week, $month, $user_id);
    $stmt->execute();

    respond(['ok' => true]);
}

function handle_prefs(mysqli $db, int $user_id, string $method): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $ai = !empty($body['aiEnabled']) ? 1 : 0;

    $stmt = $db->prepare('UPDATE prefs SET ai_enabled=? WHERE user_id=?');
    $stmt->bind_param('ii', $ai, $user_id);
    $stmt->execute();

    respond(['ok' => true]);
}

// DB Helpers
function adjust_wallet(mysqli $db, int $user_id, float $delta): void {
    $stmt = $db->prepare('UPDATE wallet SET balance = balance + ? WHERE user_id=?');
    $stmt->bind_param('di', $delta, $user_id);
    $stmt->execute();
}

function get_state(mysqli $db, int $user_id): array {
    $wallet_stmt = $db->prepare('SELECT balance FROM wallet WHERE user_id=?');
    $wallet_stmt->bind_param('i', $user_id);
    $wallet_stmt->execute();
    $wallet_row = $wallet_stmt->get_result()->fetch_assoc();

    $caps_stmt = $db->prepare('SELECT day, week, month FROM caps WHERE user_id=?');
    $caps_stmt->bind_param('i', $user_id);
    $caps_stmt->execute();
    $caps_row = $caps_stmt->get_result()->fetch_assoc();

    $expenses_stmt = $db->prepare('SELECT id, amount, merchant, beneficial, ts FROM expenses WHERE user_id=? ORDER BY ts DESC');
    $expenses_stmt->bind_param('i', $user_id);
    $expenses_stmt->execute();
    $expenses = [];
    $result = $expenses_stmt->get_result();
    while ($row = $result->fetch_assoc()) { $expenses[] = $row; }

    $incomes_stmt = $db->prepare('SELECT id, amount, source, ts FROM incomes WHERE user_id=? ORDER BY ts DESC');
    $incomes_stmt->bind_param('i', $user_id);
    $incomes_stmt->execute();
    $incomes = [];
    $result = $incomes_stmt->get_result();
    while ($row = $result->fetch_assoc()) { $incomes[] = $row; }

    $prefs_stmt = $db->prepare('SELECT ai_enabled FROM prefs WHERE user_id=?');
    $prefs_stmt->bind_param('i', $user_id);
    $prefs_stmt->execute();
    $prefs_row = $prefs_stmt->get_result()->fetch_assoc();

    return [
        'wallet' => $wallet_row ? floatval($wallet_row['balance']) : 0,
        'caps' => $caps_row ?: ['day' => 0, 'week' => 0, 'month' => 0],
        'expenses' => $expenses,
        'incomes' => $incomes,
        'aiEnabled' => $prefs_row ? (bool)$prefs_row['ai_enabled'] : true,
    ];
}

// JWT Helpers
function create_token(int $user_id, string $secret): string {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = ['user_id' => $user_id, 'exp' => time() + 7*24*3600]; // 7 days
    $header_encoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $payload_encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", $secret, true);
    $signature_encoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    return "$header_encoded.$payload_encoded.$signature_encoded";
}

function verify_token(string $token, string $secret): ?int {
    if (!$token) return null;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    list($header_encoded, $payload_encoded, $signature_encoded) = $parts;

    // Verify signature
    $signature_expected = hash_hmac('sha256', "$header_encoded.$payload_encoded", $secret, true);
    $signature_expected_encoded = rtrim(strtr(base64_encode($signature_expected), '+/', '-_'), '=');
    if (!hash_equals($signature_expected_encoded, $signature_encoded)) return null;

    // Decode payload
    $payload_decoded = json_decode(base64_decode(strtr($payload_encoded, '-_', '+/')), true);
    if (!$payload_decoded) return null;

    // Check expiry
    if (($payload_decoded['exp'] ?? 0) < time()) return null;

    return $payload_decoded['user_id'] ?? null;
}

function get_token(): ?string {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            if (preg_match('/Bearer\s+(.+)/', $value, $m)) {
                return $m[1];
            }
        }
    }
    return $_GET['token'] ?? null;
}

// Utilities
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