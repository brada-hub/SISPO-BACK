<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Tokens in core db:\n";
    $tokens = \App\Models\Sanctum\PersonalAccessToken::all();
    echo "Found " . $tokens->count() . " tokens.\n";
    
    if($tokens->count() > 0) {
        $token = $tokens->last();
        echo "Last Token belongs to user: " . $token->tokenable_id . "\n";
        $user = $token->tokenable;
        if($user) {
            echo "User loaded successfully: " . $user->nombres . "\n";
            echo "Accessing sede: " . ($user->sede ? $user->sede->nombre : 'None') . "\n";
            echo "Attempting to serialize user: " . substr(json_encode($user), 0, 100) . "...\n";
        } else {
            echo "But user could not be loaded!\n";
        }
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
