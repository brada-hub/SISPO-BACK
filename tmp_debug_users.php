<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::with(['roles.permissions','sede','persona'])->whereHas('roles.permissions', fn($q) => $q->where('sistema_id', 2))->first();
if (!$user) { echo "NO_USER\n"; exit; }
app('auth')->guard('api')->setUser($user);
$request = Illuminate\Http\Request::create('/api/usuarios', 'GET');
app()->instance('request', $request);
try {
  $response = app(App\Http\Controllers\UserController::class)->index();
  echo get_class($response) . "\n";
  echo substr($response->getContent(), 0, 1200) . "\n";
} catch (Throwable $e) {
  echo 'ERROR: ' . $e->getMessage() . "\n";
  echo $e->getTraceAsString();
}
