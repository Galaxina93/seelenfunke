<?php

namespace Tests\Feature\Order;

use Tests\TestCase;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingGiftVoucherLog;
use App\Models\Marketing\MarketingReferral;
use App\Models\Marketing\MarketingVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class OrderCancellationComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_order_restores_used_gift_voucher_balance()
    {
        // 1. Existing gift voucher with 20 €
        $voucher = MarketingGiftVoucher::create([
            'id' => Str::uuid(),
            'code' => 'GIFT-RESTORE-1',
            'initial_value' => 5000,
            'current_balance' => 3000, // 20 € were used
            'recipient_name' => 'Max',
            'is_active' => true,
        ]);

        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-2026-VOUCHER',
            'email' => 'customer@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'coupon_code' => 'GIFT-RESTORE-1',
            'discount_amount' => 2000, // 20.00 € used
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 0,
            'total_price' => 3000,
        ]);

        MarketingGiftVoucherLog::create([
            'gift_voucher_id' => $voucher->id,
            'order_id' => $order->id,
            'amount' => 2000,
            'remaining_balance' => 3000,
        ]);

        // Cancel order
        $order->handleCancellation('Kundenwunsch');

        $voucher->refresh();
        $this->assertEquals(5000, $voucher->current_balance);
        $this->assertTrue((bool)$voucher->is_active);

        // Verify reversal log exists
        $reversalLog = MarketingGiftVoucherLog::where('order_id', $order->id)->where('amount', -2000)->first();
        $this->assertNotNull($reversalLog);
        $this->assertEquals(5000, $reversalLog->remaining_balance);
    }

    public function test_cancelling_order_deactivates_purchased_gift_vouchers()
    {
        $voucherProduct = Product::create([
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'price' => 5000,
            'quantity' => 100,
            'track_quantity' => false,
            'is_personalizable' => false,
            'type' => 'digital',
            'status' => 'active',
        ]);

        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-2026-PURCHASED-VOUCHER',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 0,
            'total_price' => 5000,
        ]);

        $orderItem = OrderOrderItem::create([
            'id' => Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $voucherProduct->id,
            'product_name' => 'Geschenkgutschein',
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
            'configuration' => ['is_gift_voucher' => true, 'amount_cents' => 5000],
        ]);

        $purchasedVoucher = MarketingGiftVoucher::create([
            'id' => Str::uuid(),
            'code' => 'PURCHASED-VOUCHER-99',
            'order_item_id' => $orderItem->id,
            'initial_value' => 5000,
            'current_balance' => 5000,
            'recipient_name' => 'Recipient',
            'is_active' => true,
        ]);

        $this->assertTrue((bool)$purchasedVoucher->is_active);

        // Cancel the order
        $order->handleCancellation('Kunde storniert');

        $purchasedVoucher->refresh();
        $this->assertFalse((bool)$purchasedVoucher->is_active);
        $this->assertEquals(0, $purchasedVoucher->current_balance);
    }

    public function test_cancelling_order_reverses_referral_reward()
    {
        $voucher = MarketingVoucher::create([
            'code' => 'ALINA10',
            'title' => 'Freunde 10',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        $referral = MarketingReferral::create([
            'referrer_email' => 'referrer@example.com',
            'code' => 'ALINA10',
            'voucher_id' => $voucher->id,
            'discount_percent' => 10,
            'reward_cents' => 500,
            'successful_referrals_count' => 1,
            'is_active' => true,
        ]);

        $referrerVoucher = MarketingGiftVoucher::create([
            'id' => Str::uuid(),
            'code' => 'DANKE-ALIN-1234',
            'recipient_email' => 'referrer@example.com',
            'recipient_name' => 'Referrer',
            'initial_value' => 500,
            'current_balance' => 500,
            'personal_message' => '5 € Belohnung für erfolgreiche Empfehlung durch Bestellung ORD-2026-REF!',
            'is_active' => true,
        ]);

        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-2026-REF',
            'email' => 'buyer@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'coupon_code' => 'ALINA10',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 0,
            'total_price' => 4500,
        ]);

        // Cancel order
        $order->handleCancellation('Stornierung');

        $referrerVoucher->refresh();
        $referral->refresh();

        $this->assertEquals(0, $referrerVoucher->current_balance);
        $this->assertFalse((bool)$referrerVoucher->is_active);
        $this->assertEquals(0, $referral->successful_referrals_count);
    }
}
