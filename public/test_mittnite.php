<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== MITTNITECTL TEST ===\n\n";

if (!function_exists('exec')) {
    die("exec() function is disabled.\n");
}

$commands = [
    'which mittnitectl',
    'mittnitectl job status',
    'mittnitectl job restart',
    'id',
    'pwd'
];

foreach ($commands as $cmd) {
    echo "Running: $cmd\n";
    $output = [];
    $code = -1;
    @exec("$cmd 2>&1", $output, $code);
    echo "Exit code: $code\n";
    echo "Output:\n" . implode("\n", $output) . "\n\n";
}
