<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

// 1. Create a dummy order
$order = OrderOrder::create([
    'order_number' => 'DHL-TEST-' . time(),
    'customer_id' => 1,
    'status' => 'shipped',
    'total_amount' => 1000,
    'payment_method' => 'paypal',
    'billing_address' => [],
    'shipping_address' => [],
]);

// 2. Add a dummy shipment
$shipment = OrderShipment::create([
    'order_id' => $order->id,
    'carrier' => 'dhl',
    'tracking_number' => '1234567890_TEST',
    'status' => 'shipped'
]);

echo "Created Order: {$order->order_number} with status 'shipped'.\n";

// 3. Fake the HTTP response for DHL API to return 'delivered'
Http::fake([
    'https://api-eu.dhl.com/track/shipments*' => Http::response([
        'shipments' => [
            [
                'id' => '1234567890_TEST',
                'status' => [
                    'statusCode' => 'delivered',
                    'status' => 'Successfully delivered'
                ]
            ]
        ]
    ], 200)
]);

echo "Faking DHL API Response to return 'delivered' for tracking '1234567890_TEST'.\n";

// 4. Run the command
echo "\n--- Running Command ---\n";
Artisan::call('dhl:check-delivery-status');
echo Artisan::output();
echo "-----------------------\n\n";

// 5. Check the result
$order->refresh();
$shipment->refresh();

echo "Final Shipment Status: " . $shipment->status . "\n";
echo "Final Order Status: " . $order->status . "\n";

if ($order->status === 'completed' && $shipment->status === 'delivered') {
    echo "\nTEST PASSED: Order was successfully updated to 'completed' based on DHL API response!\n";
} else {
    echo "\nTEST FAILED: Status did not update correctly.\n";
}

// Cleanup
$shipment->delete();
$order->delete();
