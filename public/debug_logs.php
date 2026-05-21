<?php
/**
 * Temporary log viewer for staging debug - Robust Version
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<div style='background: #991b1b; color: #fecaca; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #f87171;'>";
        echo "<h3>FATAL ERROR OCCURRED</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . " on line " . $error['line'] . "</p>";
        echo "</div>";
    }
});

echo "<html><head><title>Staging Logs</title></head><body style='font-family: sans-serif; background: #0f172a; color: #e2e8f0; padding: 20px;'>";
echo "<h1>Staging Log Viewer</h1>";
echo "<p><a href='?t=" . time() . "' style='color: #38bdf8; text-decoration: none; border: 1px solid #38bdf8; padding: 5px 10px; border-radius: 4px;'>Refresh Logs</a> ";
echo "<a href='?laravel=1&t=" . time() . "' style='color: #10b981; text-decoration: none; border: 1px solid #10b981; padding: 5px 10px; border-radius: 4px;'>Load Laravel Cache</a></p>";

$files = [
    'Node Live Log' => dirname(__DIR__) . '/node-live.log',
    'Laravel Log' => dirname(__DIR__) . '/storage/logs/laravel.log'
];

foreach ($files as $name => $path) {
    echo "<h2>$name ($path)</h2>";
    try {
        if (!file_exists($path)) {
            echo "<p style='color: #ef4444;'>File does not exist.</p>";
        } else if (!is_readable($path)) {
            echo "<p style='color: #f59e0b;'>File is not readable. Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "</p>";
        } else {
            $size = filesize($path);
            echo "<p>Size: $size bytes</p>";
            if ($size == 0) {
                echo "<p style='color: #64748b; font-style: italic;'>File is empty.</p>";
            } else {
                $lines = ($name === 'Laravel Log') ? 50 : 100;
                $fileLines = file($path);
                if ($fileLines === false) {
                    echo "<p style='color: #ef4444;'>Failed to read file lines.</p>";
                } else {
                    $data = array_slice($fileLines, -$lines);
                    echo "<pre style='background: #020617; color: #38bdf8; padding: 15px; overflow: auto; max-height: 400px; font-family: monospace; border: 1px solid #334155; border-radius: 8px;'>";
                    foreach ($data as $line) {
                        $cleanLine = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $line);
                        echo htmlspecialchars($cleanLine);
                    }
                    echo "</pre>";
                }
            }
        }
    } catch (\Throwable $e) {
        echo "<p style='color: #ef4444;'>Error reading log: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Auch Cache-Ausnahmen aus console.php auslesen, falls explizit gewünscht
if (isset($_GET['laravel'])) {
    echo "<h2>Loading Laravel Cache...</h2>";
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
        echo "<h3>Scheduler Last Exception in Cache:</h3>";
        if ($lastException) {
            echo "<pre style='background: #020617; color: #f43f5e; padding: 15px; font-family: monospace; border: 1px solid #334155; border-radius: 8px;'>";
            print_r($lastException);
            echo "</pre>";
        } else {
            echo "<p style='color: #10b981;'>No scheduler exception in cache.</p>";
        }

        $jobErrors = \Illuminate\Support\Facades\Cache::get('scheduler_job_errors');
        echo "<h3>Scheduler Job Errors:</h3>";
        if ($jobErrors) {
            echo "<pre style='background: #020617; color: #f43f5e; padding: 15px; font-family: monospace; border: 1px solid #334155; border-radius: 8px;'>";
            print_r($jobErrors);
            echo "</pre>";
        } else {
            echo "<p style='color: #10b981;'>No scheduler job errors in cache.</p>";
        }
    } catch (\Throwable $e) {
        echo "<div style='background: #991b1b; color: #fecaca; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #f87171;'>";
        echo "<p><strong>Error loading Laravel:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre style='font-family: monospace; font-size: 12px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
} else {
    echo "<p style='margin-top: 20px; color: #94a3b8;'>Hinweis: Laravel-Cache-Auslesung ist standardmäßig deaktiviert. <a href='?laravel=1&t=" . time() . "' style='color: #38bdf8; text-decoration: underline;'>Hier klicken, um Laravel-Cache zu laden</a>.</p>";
}

echo "</body></html>";

