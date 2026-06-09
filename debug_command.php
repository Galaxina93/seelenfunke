<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$botService = app(App\Services\AI\AiSupportService::class);
$res = $botService->getUltimateCommand(false);

echo "NOW: " . \Carbon\Carbon::now()->toString() . "\n";
echo "TIMEZONE: " . date_default_timezone_get() . "\n";
echo "Laravel TIMEZONE: " . config('app.timezone') . "\n";
echo "Carbon::now() timezone: " . \Carbon\Carbon::now()->timezone->getName() . "\n";
echo "RESULT FLOW: " . json_encode($res['flow'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "RESULT RECOMMENDATION: " . json_encode($res['recommendation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "TESTING system_get_current_time TOOL:\n";
$toolRes = \App\Services\AI\AIFunctionsRegistry::execute('system_get_current_time', []);
echo json_encode($toolRes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
