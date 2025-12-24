<?php
require 'ai.php';

$state = [
        'wallet' => 1234.56,
        'caps' => ['day'=>100,'week'=>500,'month'=>2000],
        'expenses' => [
            ['amount'=>50,'merchant'=>'Uber','beneficial'=>0,'ts'=>time()],
            ['amount'=>100,'merchant'=>'Groceries','beneficial'=>1,'ts'=>time()]
        ],
        'incomes' => [
            ['amount'=>2000,'source'=>'Salary','ts'=>time()]
        ]
    ];

$prompt = "You are a financial assistant. The user’s wallet balance is {$state['wallet']}. ".
          "Spending limits: day={$state['caps']['day']}, week={$state['caps']['week']}, month={$state['caps']['month']}. ".
          "Recent expenses: ".json_encode($state['expenses']).". ".
          "Incomes: ".json_encode($state['incomes']).". ".
          "Give practical advice on how they can manage their money better, if not enough data say so.";

$cfg = ai_config();
var_dump($cfg);

$response = call_llm($prompt);

if ($response === null) {
    echo "AI call failed: " . $GLOBALS['LLM_LAST_ERROR'] . "\n";
} else {
    echo "AI response:\n$response\n";
}
