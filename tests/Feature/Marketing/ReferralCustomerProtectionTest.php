<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Marketing\MarketingReferral;
use App\Models\Marketing\MarketingVoucher;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ReferralCustomerProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_customer_with_different_email_cannot_earn_referrer_reward()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'old_email@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Customer has an older paid order under their account
        OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-2026-PRIOR',
            'customer_id' => $customer->id,
            'email' => 'old_email@example.com',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 3000,
            'tax_amount' => 570,
            'shipping_price' => 0,
            'total_price' => 3000,
        ]);

        // Referrer setup
        $voucher = MarketingVoucher::create([
            'code' => 'RENE10',
            'title' => 'Freunde 10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        $referral = MarketingReferral::create([
            'referrer_email' => 'referrer@example.com',
            'code' => 'RENE10',
            'voucher_id' => $voucher->id,
            'discount_percent' => 10,
            'reward_cents' => 500,
            'is_active' => true,
        ]);

        // Same customer places a new order with a new email address but same customer_id
        $newOrder = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-2026-NEW',
            'customer_id' => $customer->id,
            'email' => 'new_email@example.com',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'coupon_code' => 'RENE10',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'shipping_price' => 0,
            'total_price' => 4000,
        ]);

        $service = app(ReferralService::class);
        $reward = $service->processOrderReferralReward($newOrder);

        // Since the customer is not a new customer (has previous paid order under customer_id), reward must be denied
        $this->assertNull($reward);
    }
}
