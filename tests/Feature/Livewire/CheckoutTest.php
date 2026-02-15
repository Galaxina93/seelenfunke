<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Shop\Checkout\Checkout;
use App\Models\Cart\Cart as CartModel;
use App\Models\Product\Product;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_fields()
    {
        Livewire::test(Checkout::class)
            ->call('validateAndCreateOrder')
            ->assertHasErrors([
                'email' => 'required',
                'first_name' => 'required',
                'address' => 'required'
            ]);
    }
}
