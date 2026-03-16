<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $api = new \App\Services\BankApiService();
    $adminId = \App\Models\Admin\Admin::first()->id;
    $token = $api->getUserToken($adminId);
    
    // First, get an account to test
    $accounts = $api->getAccounts($token);
    if(count($accounts) > 0) {
        $firstAccount = $accounts[0]['id'];
        echo "Testing Account: " . $firstAccount . "\n";
        
        $txs = $api->getTransactions($token, $firstAccount, 5);
        if(count($txs) > 0) {
            echo "Found " . count($txs) . " transactions!\nSample:\n";
            echo json_encode($txs[0], JSON_PRETTY_PRINT);
        } else {
            echo "0 transactions returned by API.\n";
        }
    } else {
        echo "0 accounts found.\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
