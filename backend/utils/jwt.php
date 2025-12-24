<?php
// JWT Helpers - Token creation and verification

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
    $auth = $_SERVER['HTTP_AUTHORIZATION'] 
         ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
         ?? null;

    if ($auth && preg_match('/Bearer\s+(.+)/', $auth, $m)) {
        return $m[1];
    }

    return $_GET['token'] ?? null;
}