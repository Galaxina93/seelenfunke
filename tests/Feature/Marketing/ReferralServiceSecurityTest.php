<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingReferral;
use App\Models\Order\OrderOrder;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReferralService::class);
    }

    public function test_reverse_order_referral_reward_is_idempotent_and_prevents_multiple_deductions()
    {
        $ref = $this->service->getOrCreateReferralForCustomer('werber@example.com', 'Max', 'Mustermann');

        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-REV-100',
            'email' => 'freund@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'coupon_code' => $ref->code,
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Freund', 'last_name' => 'Test'],
        ]);

        $voucher = $this->service->processOrderReferralReward($order);
        $this->assertNotNull($voucher);
        $this->assertEquals(500, $voucher->fresh()->current_balance);
        $this->assertEquals(1, $ref->fresh()->successful_referrals_count);

        // Add 500 cents from another friend's order to test balance retention
        $order2 = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-REV-200',
            'email' => 'freund2@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'coupon_code' => $ref->code,
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Freund2', 'last_name' => 'Test'],
        ]);
        $this->service->processOrderReferralReward($order2);

        $this->assertEquals(1000, $voucher->fresh()->current_balance);
        $this->assertEquals(2, $ref->fresh()->successful_referrals_count);

        // 1. First reverse of order 1 -> should succeed and deduct 500
        $reversed1 = $this->service->reverseOrderReferralReward($order);
        $this->assertTrue($reversed1);
        $this->assertEquals(500, $voucher->fresh()->current_balance);
        $this->assertEquals(1, $ref->fresh()->successful_referrals_count);

        // 2. Second reverse of the SAME order 1 -> should be rejected (idempotent) and NOT deduct again!
        $reversed2 = $this->service->reverseOrderReferralReward($order);
        $this->assertFalse($reversed2);
        $this->assertEquals(500, $voucher->fresh()->current_balance);
        $this->assertEquals(1, $ref->fresh()->successful_referrals_count);
    }
}
