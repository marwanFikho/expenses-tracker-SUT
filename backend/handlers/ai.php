<?php
// AI handlers - AI advice and chatbot

function handle_ai(mysqli $db, int $user_id, array $cfg): void {
    // 1. Get real user state from DB
    $state = get_state($db, $user_id);

    // Optional: respect user preference
    if (empty($state['aiEnabled'])) {
        respond(['ok' => false, 'error' => 'AI is disabled']);
    }

    // 2. Build prompt from REAL data
    $prompt = "You are a financial assistant. The user's wallet balance is {$state['wallet']} EGP. ".
              "Spending limits: day={$state['caps']['day']}, week={$state['caps']['week']}, month={$state['caps']['month']}. ".
              "Recent expenses: ".json_encode($state['expenses']).". ".
              "Incomes: ".json_encode($state['incomes']).". ".
              "Give practical advice on how they can manage their money better, if not enough data say so.";

    // 3. Call AI
    try {
        $advice = call_llm($prompt, $cfg);
        respond(['ok' => true, 'advice' => $advice]);
    } catch (Throwable $e) {
        fail(500, 'AI call failed: ' . $e->getMessage());
    }
}

function handle_chatbot(string $method, array $cfg): void {
    if ($method !== 'POST') fail(405, 'Method not allowed');
    $body = read_json();
    $userMessage = trim($body['message'] ?? '');
    $amount = floatval($body['amount'] ?? 0);
    $merchant = trim($body['merchant'] ?? '');
    
    if (!$userMessage) fail(400, 'Message required');
    
    // Build system message to convince user not to spend on wants
    $systemMessage = "You are a financial advisor chatbot helping users make wise spending decisions. ";
    $systemMessage .= "The user is about to spend {$amount} EGP at {$merchant}. ";
    $systemMessage .= "This is classified as a 'WANT' (non-essential purchase). ";
    $systemMessage .= "Your goal is to politely and empathetically convince them to reconsider this purchase. ";
    $systemMessage .= "Ask questions about their financial goals, suggest alternatives, or remind them of their savings goals. ";
    $systemMessage .= "Be supportive and not judgmental. Keep responses concise (under 150 words).";
    
    $payload = [
        'model' => $cfg['model'],
        'messages' => [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'max_tokens' => 400,
        'temperature' => 0.7
    ];
    
    // Try using curl first, then fall back to file_get_contents
    $response = null;
    $curlError = null;
    
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
        $curlError = curl_error($ch);
        
        if ($response === false || $httpCode >= 400) {
            // curl failed or got an error status, try fallback
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
    
    // If API response is invalid or contains errors, use fallback
    if (isset($data['error']) || !$data || !isset($data['choices'][0]['message']['content'])) {
        error_log('AI API error or invalid response: ' . substr($response, 0, 500));
        fail(500, 'AI call failed');
        return;
    }
    
    $chatbotMessage = $data['choices'][0]['message']['content'];
    respond(['ok' => true, 'reply' => $chatbotMessage]);
}
