<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$order = \App\Models\Order\OrderOrder::where('order_number', 'like', '%V7FJ8A%')->first();
if ($order) {
    echo "Found order: {$order->order_number}\n";
    foreach ($order->items as $item) {
        echo "Item: {$item->product_name} | Type: " . ($item->product ? $item->product->type : 'NULL') . "\n";
    }
} else {
    echo "No order matching V7FJ8A found anywhere.\n";
}
