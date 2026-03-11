<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AI\AIFunctionsRegistry;

echo "--- SCHEMA ---" . PHP_EOL;
echo json_encode(AIFunctionsRegistry::getSchema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

echo PHP_EOL . "--- EXECUTE: get_next_order_deadline ---" . PHP_EOL;
echo json_encode(AIFunctionsRegistry::execute('get_next_order_deadline'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

echo PHP_EOL . "--- EXECUTE: get_system_health ---" . PHP_EOL;
echo json_encode(AIFunctionsRegistry::execute('get_system_health'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

