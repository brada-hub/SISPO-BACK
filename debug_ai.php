<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Services\Ai\AiAnalysisService::class);
$result = $service->analyzePostulacion(397); // Using 397 because I saw it in the logs

if ($result) {
    echo "SUCCESS: " . json_encode($result);
} else {
    echo "FAILED: Result is null. Check logs.";
    
    // Let's print the latest AiCvAnalysis status
    $analysis = \App\Models\AiCvAnalysis::where('postulacion_id', 397)->first();
    echo "\nStatus in DB: " . ($analysis ? $analysis->status : 'Not found');
    echo "\nError message: " . ($analysis ? $analysis->error_message : '');
}
