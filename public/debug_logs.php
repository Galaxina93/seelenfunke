<?php
/**
 * Temporary log viewer for staging debug
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Staging Logs</title></head><body style='font-family: sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px;'>";
echo "<h1>Staging Log Viewer</h1>";

$files = [
    'Laravel Log' => dirname(__DIR__) . '/storage/logs/laravel.log',
    'Node Live Log' => dirname(__DIR__) . '/node-live.log',
    'Audio Debug Log' => dirname(__DIR__) . '/audio-debug.log',
    'Crash Log' => dirname(__DIR__) . '/crash.log'
];

foreach ($files as $name => $path) {
    echo "<h2>$name ($path)</h2>";
    if (!file_exists($path)) {
        echo "<p style='color: #ef4444;'>File does not exist.</p>";
    } else {
        echo "<p>Size: " . filesize($path) . " bytes</p>";
        $lines = 100;
        $data = array_slice(file($path), -$lines);
        echo "<pre style='background: #020617; color: #38bdf8; padding: 15px; overflow: auto; max-height: 400px; font-family: monospace; border: 1px solid #334155; border-radius: 8px;'>";
        foreach ($data as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>";
    }
}

// Auch Cache-Ausnahmen aus console.php auslesen
try {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $app = require_once dirname(__DIR__) . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $lastException = \Illuminate\Support\Facades\Cache::get('scheduler_last_exception');
    echo "<h2>Scheduler Last Exception in Cache:</h2>";
    echo "<pre style='background: #020617; color: #f43f5e; padding: 15px; font-family: monospace; border: 1px solid #334155; border-radius: 8px;'>";
    print_r($lastException);
    echo "</pre>";

    $jobErrors = \Illuminate\Support\Facades\Cache::get('scheduler_job_errors');
    echo "<h2>Scheduler Job Errors:</h2>";
    echo "<pre style='background: #020617; color: #f43f5e; padding: 15px; font-family: monospace; border: 1px solid #334155; border-radius: 8px;'>";
    print_r($jobErrors);
    echo "</pre>";
} catch (\Throwable $e) {
    echo "<p style='color: #ef4444;'>Error loading Laravel: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
