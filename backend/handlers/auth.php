<?php

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
