<?php

namespace Tests\Feature\Ai;

use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Services\AI\Functions\AiMarketingVoucherFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiMarketingVoucherFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_voucher_create_validates_code_format_and_discount_bounds()
    {
        // Invalid code characters
        $resInvalidCode = AiMarketingVoucherFuncs::marketing_voucher_create([
            'code' => 'INVALID CODE <SCRIPT>',
            'type' => 'percent',
            'value' => 20,
        ]);
        $this->assertEquals('error', $resInvalidCode['status']);

        // Invalid discount type
        $resInvalidType = AiMarketingVoucherFuncs::marketing_voucher_create([
            'code' => 'SUMMER2026',
            'type' => 'unlimited_money',
            'value' => 20,
        ]);
        $this->assertEquals('error', $resInvalidType['status']);

        // Percent > 100
        $resExcessPercent = AiMarketingVoucherFuncs::marketing_voucher_create([
            'code' => 'SUMMER200',
            'type' => 'percent',
            'value' => 150,
        ]);
        $this->assertEquals('error', $resExcessPercent['status']);

        // Valid voucher creation
        $resValid = AiMarketingVoucherFuncs::marketing_voucher_create([
            'code' => 'SUMMER25',
            'type' => 'percent',
            'value' => 25,
        ]);
        $this->assertEquals('success', $resValid['status']);
        $this->assertDatabaseHas('marketing_vouchers', [
            'code' => 'SUMMER25',
            'type' => 'percent',
        ]);
    }

    public function test_voucher_analytics_query_uses_total_price_without_crashing()
    {
        $voucher = MarketingVoucher::create([
            'code' => 'TESTVOUCHER',
            'title' => 'Test Voucher',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-VOUCH-1',
            'coupon_code' => 'TESTVOUCHER',
            'email' => 'buyer@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 5000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 4500,
            'billing_address' => ['first_name' => 'Anna', 'last_name' => 'Schmidt'],
        ]);

        // This call previously crashed with SQL 1054 Unknown column 'total'
        $res = AiMarketingVoucherFuncs::marketing_voucher_get_analytics([]);

        $this->assertEquals('success', $res['status']);
        $this->assertCount(1, $res['top_performing_coupons']);
        $this->assertEquals('TESTVOUCHER', $res['top_performing_coupons'][0]->coupon_code);
        $this->assertEquals(1, $res['top_performing_coupons'][0]->total_uses);
        $this->assertEquals('45,00', $res['top_performing_coupons'][0]->generated_revenue_euro);
    }
}
