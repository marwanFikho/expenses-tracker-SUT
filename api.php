<?php

// Endpoints:
// POST /api.php?path=auth/register
// POST /api.php?path=auth/login   
// POST /api.php?path=auth/logout  
// GET  /api.php?path=state        
// POST /api.php?path=expense      

header('Content-Type: application/json');

require_once __DIR__ . '/backend/utils/http.php';
require_once __DIR__ . '/backend/utils/jwt.php';
require_once __DIR__ . '/backend/utils/db.php';
require_once __DIR__ . '/backend/utils/llm.php';
require_once __DIR__ . '/backend/handlers/auth.php';
require_once __DIR__ . '/backend/handlers/protected.php';
require_once __DIR__ . '/backend/handlers/ai.php';

// Config
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'root';
$db_name = 'expense_tracker';
$jwt_secret = 'root';
$cfg = [
    'url' => "https://router.huggingface.co/v1/chat/completions",
    'key' => "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",
    'model' => "openai/gpt-oss-20b:groq",
];

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
            if (!$token) {
                error_log('Auth failed: Missing token. Headers: ' . json_encode(getallheaders()));
                fail(401, 'Missing authorization token');
            }

            $user_id = verify_token($token, $jwt_secret);
    
            if (!$user_id) {
                error_log('Auth failed: Invalid token: ' . substr($token, 0, 20) . '...');
                fail(401, 'Invalid or expired token');
            }

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
                case 'ai':
                    handle_ai($db, $user_id, $cfg);
                    break;
                case 'chatbot':
                    handle_chatbot($method, $cfg);
                    break;
                default:
                    fail(404, 'Unknown path');
            }
    }
} catch (Exception $e) {
    fail(500, $e->getMessage());
}