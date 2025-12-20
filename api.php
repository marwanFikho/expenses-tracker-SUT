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

// ---------- DB helpers ----------
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
