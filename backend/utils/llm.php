<?php
// LLM helper - AI API integration with fallback

function call_llm(string $prompt, array $cfg): string {
    $payload = [
        'model' => $cfg['model'],
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 400,
        'temperature' => 0.6
    ];

    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $cfg['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['key']
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode >= 400) {
            $response = null;
        }
    }

    if ($response === null) {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $cfg['key']
                ],
                'content' => json_encode($payload),
                'timeout' => 15,
            ]
        ]);

        $response = @file_get_contents($cfg['url'], false, $context);
    }

    $data = json_decode($response, true);

    if (!$data || isset($data['error']) || !isset($data['choices'][0]['message']['content'])) {
        error_log('AI API error or invalid response: ' . substr((string)$response, 0, 500));
        fail(500, 'AI call failed');
    }

    return $data['choices'][0]['message']['content'];
}
