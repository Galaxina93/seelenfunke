<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// Boot Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\System\SystemCronjob;

try {
    $cronjobs = SystemCronjob::all();
    $bootstrapLastRun = \Illuminate\Support\Facades\Cache::get('scheduler_bootstrap_last_run');
    $heartbeatLastRun = \Illuminate\Support\Facades\Cache::get('scheduler_last_run');
    
    echo json_encode([
        'cronjobs' => $cronjobs,
        'scheduler_bootstrap_last_run' => $bootstrapLastRun,
        'scheduler_bootstrap_last_run_date' => $bootstrapLastRun ? date('Y-m-d H:i:s', $bootstrapLastRun) : null,
        'scheduler_last_run' => $heartbeatLastRun,
        'scheduler_last_run_date' => $heartbeatLastRun ? (\Carbon\Carbon::parse($heartbeatLastRun)->toDateTimeString()) : null,
        'current_time' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
