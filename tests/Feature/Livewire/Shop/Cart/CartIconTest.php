<?php

namespace Tests\Feature\Livewire\Shop\Cart;

use App\Livewire\Shop\Cart\CartIcon;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartIconTest extends TestCase
{
    use RefreshDatabase;

    private $mockTotals = [
        'subtotal_gross' => 1000,
        'tax' => 190,
        'taxes_breakdown' => ['19' => 190],
        'discount_amount' => 0,
        'shipping' => 500,
        'total' => 1500,
        'item_count' => 3
    ];

    #[Test]
    public function it_renders_the_cart_icon_component()
    {
        $this->mock(CartService::class, function ($mock) {
            $mock->shouldReceive('getTotals')->once()->andReturn($this->mockTotals);
        });

        Livewire::test(CartIcon::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.shop.cart.cart-icon')
            ->assertSet('totals.item_count', 3)
            ->assertSet('totals.total', 1500)
            ->assertSet('totals.shipping', 500);
    }

    #[Test]
    public function it_updates_totals_when_cart_updated_event_is_dispatched()
    {
        $newTotals = array_merge($this->mockTotals, [
            'item_count' => 5,
            'total' => 2500
        ]);

        $this->mock(CartService::class, function ($mock) use ($newTotals) {
            // Evaluated during initial Livewire Mount phase
            $mock->shouldReceive('getTotals')->once()->andReturn($this->mockTotals);
            
            // Evaluated when the #[On('cart-updated')] browser listener catches the dispatch
            $mock->shouldReceive('getTotals')->once()->andReturn($newTotals);
        });

        Livewire::test(CartIcon::class)
            ->assertSet('totals.item_count', 3)
            ->dispatch('cart-updated')
            ->assertSet('totals.item_count', 5)
            ->assertSet('totals.total', 2500);
    }
}
