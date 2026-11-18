<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Cart\CartIcon;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartIconHydrationResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');
    }

    #[Test]
    public function it_renders_safely_in_normal_operation_and_exposes_all_default_total_keys(): void
    {
        $component = Livewire::test(CartIcon::class)
            ->assertStatus(200)
            ->assertSet('totals.item_count', 0)
            ->assertSet('totals.subtotal', 0)
            ->assertSet('totals.tax', 0)
            ->assertSet('totals.shipping', 0)
            ->assertSet('totals.discount', 0)
            ->assertSet('totals.discount_amount', 0)
            ->assertSet('totals.total', 0);

        $totals = $component->get('totals');
        foreach (array_keys(CartIcon::DEFAULT_TOTALS) as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $totals);
        }
    }

    #[Test]
    public function it_never_throws_undefined_array_key_when_totals_becomes_empty_during_livewire_update(): void
    {
        // 1. Leerer Warenkorb + manipuliertes leeres $totals = [] im Update-Zyklus
        Livewire::test(CartIcon::class)
            ->assertStatus(200)
            ->set('totals', [])
            ->assertStatus(200)
            ->assertSet('totals.item_count', 0)
            ->assertSet('totals.total', 0);

        // 2. Befüllter Warenkorb + manipuliertes leeres $totals = [] oder Preis-Tampering im Update-Zyklus
        $product = Product::create([
            'name' => 'Seelenfunke Gravur Anhänger',
            'slug' => 'seelenfunke-anhaenger-' . Str::lower(Str::random(6)),
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => false,
            'price' => 2990,
            'track_quantity' => false,
            'quantity' => 50,
        ]);

        app(CartService::class)->addItem($product, 2);
        $expectedTotals = app(CartService::class)->getTotals();

        Livewire::test(CartIcon::class)
            ->assertStatus(200)
            ->assertSet('totals.item_count', 2)
            ->assertSet('totals.total', $expectedTotals['total'])
            ->set('totals', [])
            ->assertStatus(200)
            ->assertSet('totals.item_count', 2)
            ->assertSet('totals.total', $expectedTotals['total'])
            ->set('totals.total', 1)
            ->assertStatus(200)
            ->assertSet('totals.total', $expectedTotals['total'])
            ->set('totals.item_count', 999)
            ->assertStatus(200)
            ->assertSet('totals.item_count', 2);
    }

    #[Test]
    public function it_recovers_when_cart_service_returns_sparse_array_and_blade_view_handles_missing_keys(): void
    {
        $this->mock(CartService::class, function ($mock) {
            $mock->shouldReceive('getTotals')->once()->andReturn([]);
        });

        Livewire::test(CartIcon::class)
            ->assertStatus(200)
            ->assertSet('totals.item_count', 0)
            ->assertSet('totals.subtotal', 0)
            ->assertSet('totals.total', 0);

        // Direkter Defense-in-Depth-Check auf der Blade-View ohne sämtliche Array-Schlüssel
        $emptyHtml = view('livewire.shop.cart.cart-icon', [
            'totals' => [],
            'suppressPreview' => false,
        ])->render();
        $this->assertStringContainsString('data-sf-cart-header-icon', $emptyHtml);

        $sparsePreviewHtml = view('livewire.shop.cart.cart-icon', [
            'totals' => ['item_count' => 2],
            'suppressPreview' => false,
        ])->render();
        $this->assertStringContainsString('Warenkorb Vorschau', $sparsePreviewHtml);
        $this->assertStringContainsString('0,00', $sparsePreviewHtml);
    }
}
