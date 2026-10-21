<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use App\Models\Order\OrderOrder;
use App\Models\Marketing\MarketingHerzensprojektTransaction;
use App\Services\HerzensprojektService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class HerzensprojektDonationReversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_is_reversed_on_order_cancellation()
    {
        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-2026-TEST01',
            'email' => 'charity@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
        ]);

        // 1. Initial donation recorded
        $donation = HerzensprojektService::recordDonationForOrder($order, 200);
        $this->assertNotNull($donation);
        $this->assertEquals(200, $donation->amount_cents);

        // 2. Cancellation triggers reversal
        $reversal = HerzensprojektService::reverseDonationForOrder($order, 'Stornierung');
        $this->assertNotNull($reversal);
        $this->assertEquals(-200, $reversal->amount_cents);
        $this->assertEquals('disbursement', $reversal->type);

        // 3. Second call is idempotent (no duplicate negative transaction)
        $secondReversal = HerzensprojektService::reverseDonationForOrder($order, 'Stornierung');
        $this->assertNull($secondReversal);

        $netPot = MarketingHerzensprojektTransaction::where('order_id', $order->id)->sum('amount_cents');
        $this->assertEquals(0, $netPot);
    }
}
