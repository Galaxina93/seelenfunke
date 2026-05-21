<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== TWILIO-GEMINI BRIDGE SYNC ===\n\n";

$webAppPath = dirname(__DIR__); // /home/p-g27wim/html/seelenfunke-stage
$bridgePath = dirname($webAppPath) . '/twilio-bridge'; // /home/p-g27wim/html/twilio-bridge

if (!file_exists($bridgePath)) {
    die("Error: Bridge directory does not exist at $bridgePath\n");
}

$filesToSync = [
    'server-twilio.js',
    'package.json',
    'package-lock.json'
];

foreach ($filesToSync as $file) {
    $src = $webAppPath . '/' . $file;
    $dst = $bridgePath . '/' . $file;
    
    echo "Syncing $file...\n";
    echo "  Source: $src (" . (file_exists($src) ? filesize($src) . " bytes" : "NOT FOUND") . ")\n";
    echo "  Dest:   $dst (" . (file_exists($dst) ? filesize($dst) . " bytes" : "NOT FOUND") . ")\n";
    
    if (!file_exists($src)) {
        echo "  Skipping: Source file does not exist.\n\n";
        continue;
    }
    
    // MD5 vergleichen
    $srcMd5 = md5_file($src);
    $dstMd5 = file_exists($dst) ? md5_file($dst) : '';
    
    if ($srcMd5 === $dstMd5) {
        echo "  Result: Files are identical. No sync needed.\n\n";
        continue;
    }
    
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        if (copy($src, $dst)) {
            echo "  Result: SUCCESS (copied successfully)\n\n";
        } else {
            echo "  Result: FAILED to copy\n\n";
        }
    } else {
        echo "  Result: Diff detected. Run with ?execute=1 to apply.\n\n";
    }
}

// Node-Prozesse anzeigen
if (function_exists('exec')) {
    echo "--- RUNNING NODE PROCESSES ---\n";
    $output = [];
    $code = -1;
    @exec("ps aux | grep node 2>&1", $output, $code);
    echo implode("\n", $output) . "\n\n";
}

echo "To copy files, append '?execute=1' to the URL.\n";
