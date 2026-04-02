<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $sede = \App\Models\Sede::all();
    echo "Sede: Sede OK\n";
    
    $user = \App\Models\User::first();
    echo "User: " . $user->id_user . "\n";
    
    $token = auth('api')->login($user);
    echo "Token generated: OK\n";
    
    print_r($user->getJWTCustomClaims());
    
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
