<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::find(2);
if (!$user) { echo "NO_USER\n"; exit; }
echo Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
