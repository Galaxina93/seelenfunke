<?php

namespace Tests\Feature\Shipping;

use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\ShippingCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingCalculatorSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_calc_normalizes_lowercase_country_code_and_clamps_inputs()
    {
        $service = new ShippingCalculatorService();

        $product = Product::create([
            'name' => 'Wohnzimmer Duftkerze',
            'slug' => 'duftkerze-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2500,
            'quantity' => 10,
            'track_quantity' => true,
            'is_personalizable' => false,
        ]);

        // Set global free shipping threshold to 50,00 Euro (5000 cents)
        SystemSetting::create(['key' => 'shipping_free_threshold', 'value' => '5000']);
        SystemSetting::create(['key' => 'shipping_cost', 'value' => '490']);

        // Case 1: Pass lowercase 'de' with 6000 cents cart value -> MUST qualify for free shipping
        $result = $service->calculateShippingCost([$product], 6000, -2.5, 'de');
        $this->assertTrue($result['is_free']);
        $this->assertEquals(0, $result['cost']);

        // Case 2: Pass lowercase 'de' below threshold
        $resultBelow = $service->calculateShippingCost([$product], 2500, 0.5, 'de');
        $this->assertFalse($resultBelow['is_free']);
        $this->assertEquals(490, $resultBelow['cost']);
        $this->assertEquals(2500, $resultBelow['missing']);
    }

    public function test_digital_products_only_do_not_need_shipping()
    {
        $service = new ShippingCalculatorService();

        SystemSetting::create(['key' => 'skip_shipping_for_digital', 'value' => 'true']);

        $digitalProduct = Product::create([
            'name' => 'E-Book Seelenfrieden',
            'slug' => 'ebook-' . Str::random(6),
            'status' => 'active',
            'type' => 'digital',
            'price' => 990,
            'quantity' => 999,
            'track_quantity' => false,
        ]);

        $result = $service->calculateShippingCost([$digitalProduct], 990, 0.0, 'DE');
        $this->assertTrue($result['is_free']);
        $this->assertEquals(0, $result['cost']);
        $this->assertEquals('non_physical_only', $result['reason']);
    }
}
