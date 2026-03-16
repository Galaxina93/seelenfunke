<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $api = new \App\Services\BankApiService();
    $adminId = \App\Models\Admin\Admin::first()->id;
    $token = $api->getUserToken($adminId);
    $conns = \Illuminate\Support\Facades\Http::withToken($token)->get($api->baseUrl . '/api/v2/bankConnections')->json();
    echo json_encode($conns, JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo $e->getMessage();
}
