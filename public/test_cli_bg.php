<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

$artisan = dirname(__DIR__) . '/artisan';
$logFile = __DIR__ . '/scheduler_cli.log';

// Logdatei leeren
file_put_contents($logFile, "--- STARTING CLI SCHEDULER TEST ---\nDate: " . date('Y-m-d H:i:s') . "\n");

// Befehl im Hintergrund ausführen (Linux)
$cmd = "/usr/bin/php " . escapeshellarg($artisan) . " schedule:run >> " . escapeshellarg($logFile) . " 2>&1 &";

echo "Running background CLI command: $cmd\n";
exec($cmd);
echo "Command dispatched. Check scheduler_cli.log in a few seconds.\n";
