<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

$cmdArg = isset($_GET['cmd']) ? $_GET['cmd'] : 'about';
// Nur alphanumerische Zeichen, Doppelpunkte, Bindestriche und Leerzeichen erlauben
if (!preg_match('/^[a-zA-Z0-9:\-\s_]+$/', $cmdArg)) {
    die("Invalid command format");
}

$artisan = dirname(__DIR__) . '/artisan';
$cmd = "/usr/bin/php " . escapeshellarg($artisan) . " " . $cmdArg . " 2>&1";

echo "Running command: $cmd\n";

$descriptors = [
    0 => ["pipe", "r"], // stdin
    1 => ["pipe", "w"], // stdout
    2 => ["pipe", "w"]  // stderr
];

$process = proc_open($cmd, $descriptors, $pipes);

if (!is_resource($process)) {
    die("Failed to start process");
}

fclose($pipes[0]);

$stdout = '';
$stderr = '';
$start = time();
$timeout = 15; 

while (true) {
    $status = proc_get_status($process);
    
    $read = [$pipes[1], $pipes[2]];
    $write = null;
    $except = null;
    if (stream_select($read, $write, $except, 1) > 0) {
        foreach ($read as $stream) {
            if ($stream === $pipes[1]) {
                $stdout .= fread($pipes[1], 8192);
            } else if ($stream === $pipes[2]) {
                $stderr .= fread($pipes[2], 8192);
            }
        }
    }
    
    if (!$status['running']) {
        echo "Process completed naturally.\n";
        break;
    }
    
    if (time() - $start >= $timeout) {
        echo "Timeout reached. Killing process...\n";
        proc_terminate($process, 9);
        break;
    }
}

fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);

echo "\n--- STDOUT ---\n$stdout\n";
echo "\n--- STDERR ---\n$stderr\n";
