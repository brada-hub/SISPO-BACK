<?php
/**
 * Test different Gemini models to find one that works with this API key.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = config('services.gemini.api_key');
echo "API Key: " . substr($apiKey, 0, 12) . "...\n\n";

$modelsToTest = [
    'gemini-2.0-flash',
    'gemini-2.0-flash-lite',
    'gemini-2.5-flash',
    'gemini-2.5-flash-lite',
];

$payload = [
    'contents' => [
        ['parts' => [['text' => 'Respond: {"ok":true}']]]
    ],
    'generationConfig' => [
        'temperature' => 0.1,
        'maxOutputTokens' => 50,
        'responseMimeType' => 'application/json',
    ],
];

foreach ($modelsToTest as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    echo "Testing {$model}... ";

    try {
        $response = \Illuminate\Support\Facades\Http::timeout(30)
            ->withoutVerifying()
            ->post($url, $payload);

        $status = $response->status();
        echo "HTTP {$status}";

        if ($response->successful()) {
            $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '?';
            echo " ✅ Response: {$text}";
        } else {
            $error = $response->json()['error']['message'] ?? '?';
            echo " ❌ " . substr($error, 0, 120);
        }
    } catch (\Throwable $e) {
        echo "❌ Exception: " . $e->getMessage();
    }

    echo "\n";
    sleep(2); // Don't hit rate limits
}

echo "\nDone.\n";
