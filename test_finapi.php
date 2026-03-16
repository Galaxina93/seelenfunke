<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$api = new \App\Services\BankApiService();
$adminId = \App\Models\Auth\Admin::first()->id;
$token = $api->getUserToken($adminId);

$connections = \Illuminate\Support\Facades\Http::withToken($token)->get($api->baseUrl . '/api/v2/bankConnections')->json();
$accounts = $api->getAccounts($token);

echo "--- Bank Connections ---\n";
print_r($connections);
echo "\n--- Accounts ---\n";
print_r($accounts);
