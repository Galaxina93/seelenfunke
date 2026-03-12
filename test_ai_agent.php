<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$agent = new \App\Services\AI\MittwaldAgent();
$history = [
    [
        'role' => 'system',
        'content' => 'Du hast Zugriff auf Tools. Nutze get_coupons, wenn der User nach Gutscheinen oder Rabattcodes fragt.'
    ],
    [
        'role' => 'user', 
        'content' => 'zeige mir die Rabattcodes aus der Datenbank'
    ]
];

echo "Querying AI...\n";
$result = $agent->ask($history);

echo "\n--- RESULT DUMP ---\n";
print_r($result);
echo "\n-------------------\n";
