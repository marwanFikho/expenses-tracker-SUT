<?php
// Database helpers - Wallet, state, and adjustment functions

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
