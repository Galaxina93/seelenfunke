<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ProductCalculatorCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->product = Product::create([
            'name' => 'Einhorn Funken',
            'slug' => 'einhorn-funken',
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ]);
    }

    #[Test]
    public function it_calculates_express_surcharge_when_capacity_is_normal_or_level_1()
    {
        Cache::put('shop_capacity_level', 1);

        $cartItems = [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 1,
                'configuration' => ['is_express' => true],
            ]
        ];

        Livewire::test(ProductCalculator::class)
            // session mocking is generally complex in livewire. The calculator uses a local variable that reads from session
            ->set('cartItems', $cartItems)
            ->set('isExpress', true)
            ->call('calculateTotal')
            ->assertSet('isExpress', true); // Express should stay enabled
    }

    #[Test]
    public function it_automatically_disables_express_surcharge_when_capacity_reaches_level_3()
    {
        Cache::put('shop_capacity_level', 3);

        $cartItems = [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 1,
                'configuration' => ['is_express' => true],
            ]
        ];

        Livewire::test(ProductCalculator::class)
            ->set('cartItems', $cartItems)
            ->set('isExpress', true) // User explicitly sets it
            ->call('calculateTotal')
            ->assertSet('isExpress', false); // The system forces it off!
    }

    #[Test]
    public function it_allows_proceeding_to_the_next_step_even_if_capacity_is_level_4()
    {
        Cache::put('shop_capacity_level', 4);

        $cartItems = [
            [
                'product_id' => $this->product->id,
                'name' => $this->product->name,
                'qty' => 1,
                'configuration' => ['is_express' => true],
            ]
        ];

        Livewire::test(ProductCalculator::class)
            // session mocking is generally complex in livewire. The calculator uses a local variable that reads from session
            ->set('cartItems', $cartItems)
            ->call('goNext')
            ->assertHasNoErrors() // No capacity block anymore!
            ->assertSet('step', 3); // Proceeds successfully to the next step
    }
}
