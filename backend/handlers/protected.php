<?php
// Protected handlers - Expense, income, caps, preferences

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
