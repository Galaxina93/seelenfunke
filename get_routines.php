<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$routines = App\Models\Management\ManagementDayRoutine::all();
echo json_encode($routines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
