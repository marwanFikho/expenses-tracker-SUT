<?php
$GLOBALS['LLM_LAST_ERROR'] = null;

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function ai_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $url = trim($_ENV['AI_API_URL'] ?? '');
    $key = trim($_ENV['AI_API_KEY'] ?? '');
    $model = trim($_ENV['AI_MODEL'] ?? 'openai/gpt-oss-20b:groq');

    if ($url === '') $url = 'https://router.huggingface.co/v1/chat/completions';

    $cfg = [
        'url' => $url,
        'key' => $key,
        'model' => $model,
    ];

    return $cfg;
}



var_dump(getenv('AI_API_KEY'));
var_dump($_ENV['AI_API_KEY'] ?? null);

function call_llm(string $prompt): ?string
{
    $GLOBALS['LLM_LAST_ERROR'] = null;
    $cfg = ai_config();

    if (empty($cfg['url']) || empty($cfg['key'])) {
        $GLOBALS['LLM_LAST_ERROR'] = 'AI env vars missing';
        error_log('LLM ERROR: missing AI env vars');
        return null;
    }

    $payload = [
        "model" => $cfg['model'],
        "messages" => [
            ["role" => "user", "content" => $prompt]
        ],
        "stream" => false
    ];

    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $cfg['key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($res === false) {
        $GLOBALS['LLM_LAST_ERROR'] = $err;
        return null;
    }

    if ($http >= 400) {
        $GLOBALS['LLM_LAST_ERROR'] = "HTTP $http: $res";
        return null;
    }

    $data = json_decode($res, true);
    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        $GLOBALS['LLM_LAST_ERROR'] = 'Unexpected response structure';
        return null;
    }

    return $data['choices'][0]['message']['content'];
}
