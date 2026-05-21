<?php
/**
 * Temporary log viewer for staging debug - Plain Text Version
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== STAGING LOG VIEWER ===\n\n";

$files = [
    'Node Live Log' => dirname(__DIR__) . '/node-live.log',
    'Audio Debug Log' => dirname(__DIR__) . '/audio-debug.log',
    'Crash Log' => dirname(__DIR__) . '/crash.log',
    'Laravel Log' => dirname(__DIR__) . '/storage/logs/laravel.log',
    'Node Live Log (Bridge)' => dirname(dirname(__DIR__)) . '/twilio-bridge/node-live.log',
    'Audio Debug Log (Bridge)' => dirname(dirname(__DIR__)) . '/twilio-bridge/audio-debug.log',
    'Crash Log (Bridge)' => dirname(dirname(__DIR__)) . '/twilio-bridge/crash.log'
];

foreach ($files as $name => $path) {
    echo "--- $name ($path) ---\n";
    try {
        if (!file_exists($path)) {
            echo "File does not exist.\n\n";
        } else if (!is_readable($path)) {
            echo "File is not readable. Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "\n\n";
        } else {
            $size = filesize($path);
            echo "Size: $size bytes\n";
            if ($size == 0) {
                echo "File is empty.\n\n";
            } else {
                $lines = ($name === 'Laravel Log') ? 100 : 50;
                $fileLines = file($path);
                if ($fileLines === false) {
                    echo "Failed to read file lines.\n\n";
                } else {
                    $data = array_slice($fileLines, -$lines);
                    foreach ($data as $line) {
                        echo $line;
                    }
                    echo "\n\n";
                }
            }
        }
    } catch (\Throwable $e) {
        echo "Error reading log: " . $e->getMessage() . "\n\n";
    }
}

// Laravel Cache auslesen
echo "--- Laravel Cache Status ---\n";
try {
    if (!file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
        throw new \Exception("vendor/autoload.php not found");
    }
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    
    if (!file_exists(dirname(__DIR__) . '/bootstrap/app.php')) {
        throw new \Exception("bootstrap/app.php not found");
    }
    $app = require_once dirname(__DIR__) . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $lastException = \Illuminate\Support\Facades\Cache::get('scheduler_last_exception');
    echo "Scheduler Last Exception in Cache:\n";
    if ($lastException) {
        print_r($lastException);
    } else {
        echo "No scheduler exception in cache.\n";
    }
    echo "\n";

    $jobErrors = \Illuminate\Support\Facades\Cache::get('scheduler_job_errors');
    echo "Scheduler Job Errors in Cache:\n";
    if ($jobErrors) {
        print_r($jobErrors);
    } else {
        echo "No scheduler job errors in cache.\n";
    }
    echo "\n";

    $lastRun = \Illuminate\Support\Facades\Cache::get('scheduler_last_run');
    echo "Scheduler Last Run in Cache:\n";
    if ($lastRun) {
        echo $lastRun . "\n";
    } else {
        echo "No scheduler last run in cache.\n";
    }
    echo "\n";

} catch (\Throwable $e) {
    echo "Error loading Laravel Cache: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}


