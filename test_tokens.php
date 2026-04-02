<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tokens = \Laravel\Sanctum\PersonalAccessToken::latest()->take(5)->get();
foreach ($tokens as $token) {
    echo "ID: {$token->id} | Name: {$token->name} | Type: {$token->tokenable_type} | Token: " . substr($token->token, 0, 10) . "... | Created: {$token->created_at}\n";
}
