<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AI\AIFunctionsRegistry;
use App\Models\Ai\AiAgent;

echo "Before execution:\n";
$agent = AiAgent::first();
if ($agent) {
    echo "Speed: {$agent->tts_speed}, API Url: {$agent->tts_api_url}\n";
} else {
    echo "No agent found.\n";
    exit(1);
}

echo "\nExecuting update_agent_configuration tool...\n";
$args = [
    'setting_key' => 'tts_speed',
    'setting_value' => '1.5'
];

$result = AIFunctionsRegistry::execute('update_agent_configuration', $args);
print_r($result);

echo "\nAfter execution:\n";
$agent->refresh();
echo "Speed: {$agent->tts_speed}\n";

// Reset
$args['setting_value'] = '1.0';
AIFunctionsRegistry::execute('update_agent_configuration', $args);

echo "\nTest Finished.\n";
