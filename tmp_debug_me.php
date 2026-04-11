<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::with(['roles.permissions','sede','persona'])->first();
if (!$user) { echo "NO_USER\n"; exit; }
$request = Illuminate\Http\Request::create('/api/me', 'GET');
$request->setUserResolver(fn() => $user);
app()->instance('request', $request);
try {
  $response = app(App\Http\Controllers\Api\AuthController::class)->me($request);
  echo get_class($response) . "\n";
  echo substr($response->getContent(), 0, 1200) . "\n";
} catch (Throwable $e) {
  echo 'ERROR: ' . $e->getMessage() . "\n";
  echo $e->getTraceAsString();
}
