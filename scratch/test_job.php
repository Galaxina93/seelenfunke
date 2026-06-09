<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Models\Order\OrderOrder;

echo "Fetching first order...\n";
$order = OrderOrder::first();

if (!$order) {
    echo "No order found in database!\n";
    exit(1);
}

echo "Order found: " . $order->order_number . " (ID: " . $order->id . ")\n";
echo "Dispatching ProcessOrderDocumentsAndMails job synchronously...\n";

try {
    ProcessOrderDocumentsAndMails::dispatchSync($order);
    echo "Job executed successfully!\n";
} catch (\Exception $e) {
    echo "Job failed with error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
