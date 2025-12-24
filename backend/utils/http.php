<?php
// HTTP utilities - Request/response handling

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
