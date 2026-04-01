<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderShipment;
use Illuminate\Support\Facades\Http;

class CheckDhlDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_updates_order_to_completed_when_all_dhl_shipments_are_delivered()
    {
        // 1. Arrange: Create order in 'shipped' state
        $order = OrderOrder::factory()->create([
            'status' => 'shipped'
        ]);

        // Create 2 shipments
        $shipment1 = OrderShipment::create([
            'order_id' => $order->id,
            'carrier' => 'dhl',
            'tracking_number' => '1111111111',
            'status' => 'shipped'
        ]);

        $shipment2 = OrderShipment::create([
            'order_id' => $order->id,
            'carrier' => 'dhl',
            'tracking_number' => '2222222222',
            'status' => 'shipped'
        ]);

        // Fake DHL API Response to return delivered for both shipments
        Http::fake([
            'https://api-eu.dhl.com/track/shipments*' => Http::response([
                'shipments' => [
                    [
                        'status' => [
                            'statusCode' => 'delivered'
                        ]
                    ]
                ]
            ], 200)
        ]);

        // 2. Act: Run the DHL Sync Command
        $this->artisan('dhl:check-delivery-status')
             ->assertExitCode(0);

        // 3. Assert: Verify statuses are updated
        $this->assertEquals('delivered', $shipment1->fresh()->status);
        $this->assertEquals('delivered', $shipment2->fresh()->status);
        $this->assertEquals('completed', $order->fresh()->status);
    }
}
