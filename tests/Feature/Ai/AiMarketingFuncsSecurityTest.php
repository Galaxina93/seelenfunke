<?php

namespace Tests\Feature\Ai;

use App\Models\Marketing\MarketingVoucher;
use App\Services\AI\Functions\AiMarketingFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMarketingFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_coupon_clamps_values_and_sanitizes_code()
    {
        $dummy = new class {
            use AiMarketingFuncs;
        };

        // 1. Percentage voucher clamped to 100%
        $resPercent = $dummy::executeCreateCoupon([
            'code' => 'sommer-rabatt!%#',
            'type' => 'percent',
            'value' => 500, // Should be clamped to 100
            'min_order_value' => -50, // Should be clamped to 0
            'usage_limit' => -10, // Invalid limit ignored
        ]);

        $this->assertEquals('success', $resPercent['status']);
        $voucher1 = MarketingVoucher::where('code', 'SOMMER-RABATT')->first();
        $this->assertNotNull($voucher1);
        $this->assertEquals(100, $voucher1->value);
        $this->assertEquals(0, $voucher1->min_order_value);
        $this->assertNull($voucher1->usage_limit);

        // 2. Fixed voucher created properly
        $resFixed = $dummy::executeCreateCoupon([
            'code' => 'FIXED10',
            'type' => 'fixed',
            'value' => 10, // 10 Euro -> 1000 Cents
            'min_order_value' => 30, // 30 Euro -> 3000 Cents
            'usage_limit' => 50,
        ]);

        $this->assertEquals('success', $resFixed['status']);
        $voucher2 = MarketingVoucher::where('code', 'FIXED10')->first();
        $this->assertNotNull($voucher2);
        $this->assertEquals(1000, $voucher2->value);
        $this->assertEquals(3000, $voucher2->min_order_value);
        $this->assertEquals(50, $voucher2->usage_limit);
    }

    public function test_update_coupon_prevents_duplicate_code_and_clamps()
    {
        $dummy = new class {
            use AiMarketingFuncs;
        };

        $v1 = MarketingVoucher::create([
            'title' => 'Gutschein 1',
            'code' => 'CODE1',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'mode' => 'manual',
        ]);

        $v2 = MarketingVoucher::create([
            'title' => 'Gutschein 2',
            'code' => 'CODE2',
            'type' => 'percent',
            'value' => 15,
            'is_active' => true,
            'mode' => 'manual',
        ]);

        // Renaming v2 to CODE1 should fail with collision error
        $resCollision = $dummy::executeUpdateCoupon([
            'id' => $v2->id,
            'code' => 'CODE1',
        ]);

        $this->assertEquals('error', $resCollision['status']);
        $this->assertStringContainsString('existiert bereits', $resCollision['message']);

        // Update value with clamping
        $resUpdate = $dummy::executeUpdateCoupon([
            'id' => $v2->id,
            'value' => 150, // Clamps to 100 for percent
            'is_active' => 'false',
        ]);

        $this->assertEquals('success', $resUpdate['status']);
        $v2->refresh();
        $this->assertEquals(100, $v2->value);
        $this->assertFalse($v2->is_active);
    }
}
