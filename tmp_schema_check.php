<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$schema = Illuminate\Support\Facades\Schema::connection('core');
echo 'user_systems=' . ($schema->hasTable('user_systems') ? 'yes' : 'no') . PHP_EOL;
echo 'application_user=' . ($schema->hasTable('application_user') ? 'yes' : 'no') . PHP_EOL;
echo 'sistemas=' . ($schema->hasTable('sistemas') ? 'yes' : 'no') . PHP_EOL;
if ($schema->hasTable('sistemas')) {
  print_r(Illuminate\Support\Facades\DB::connection('core')->select('SHOW COLUMNS FROM sistemas'));
}
