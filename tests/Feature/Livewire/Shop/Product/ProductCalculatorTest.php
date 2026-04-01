<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator as Calculator;
use App\Models\Product\Product;
use App\Models\Product\ProductTierPrice;
use App\Models\Order\OrderQuoteRequest;
use App\Mail\NewCalcMailToAdmin;
use App\Mail\NewCalcMailToCustomer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        
        // Populate standard tax rates for test
        DB::table('tax_rates')->insertOrIgnore([
            ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function it_can_calculate_multiple_products_and_volumes()
    {
        // 1. Create a base product
        $product = Product::create([
            'name' => 'Test Calculator Product',
            'slug' => 'test-calc-prod',
            'status' => 'active',
            'type' => 'physical',
            'price' => 1000, // 10,00 EUR
            'weight' => 0.5
        ]);

        // 2. Add Tier Pricing
        ProductTierPrice::create([
            'product_id' => $product->id,
            'qty' => 10,
            'percent' => 10, // 10% discount for 10+
        ]);

        ProductTierPrice::create([
            'product_id' => $product->id,
            'qty' => 50,
            'percent' => 20, // 20% discount for 50+
        ]);

        $component = Livewire::test(Calculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->assertSet('step', 1);

        // Add 5 items (No discount, expected 5 * 10 = 50 EUR gross)
        $component->dispatch('calculator-save', [
            'product_id' => $product->id,
            'qty' => 5,
        ]);
        
        $component->assertSet('gesamtKosten', 50.00)
                  ->assertSet('volumeDiscount', 0);

        // Update item to 10 items (10% discount -> 10 * 9 EUR = 90 EUR)
        $component->call('editItem', 0)
                  ->assertSet('step', 2);
                  
        $component->dispatch('calculator-save', [
            'product_id' => $product->id,
            'qty' => 10,
        ]);

        $component->assertSet('gesamtKosten', 90.00)
                  ->assertSet('volumeDiscount', 10.00); // 100 - 90 = 10 discount
    }

    #[Test]
    public function it_handles_express_delivery_and_validation()
    {
        $product = Product::create([
            'name' => 'Express Prod', 'slug' => 'ex-prod', 
            'status' => 'active', 'type' => 'physical', 'price' => 5000, 
            'weight' => 1
        ]);

        $component = Livewire::test(Calculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->dispatch('calculator-save', [
                'product_id' => $product->id,
                'qty' => 1,
            ]);

        $component->assertSet('gesamtKosten', 50.00);

        // Enable Express
        $component->set('isExpress', true)
                  ->assertSet('gesamtKosten', 75.00); // 50 + 25 (default express_surcharge)

        // Try to proceed without deadline
        $component->call('goNext')
                  ->assertHasErrors(['deadline' => 'required'])
                  ->assertSet('step', 1);

        // Provide valid future deadline
        $component->set('deadline', now()->addDays(5)->format('Y-m-d'))
                  ->call('goNext')
                  ->assertHasNoErrors()
                  ->assertSet('step', 3);
    }

    #[Test]
    public function it_submits_quote_and_sends_mails()
    {
        Mail::fake();

        $product = Product::create([
            'name' => 'Mail Prod', 'slug' => 'mail-prod', 
            'status' => 'active', 'type' => 'physical', 'price' => 2000, 
            'weight' => 2
        ]);

        $component = Livewire::test(Calculator::class)
            ->set('agb_accepted', true)
            ->call('startCalculator')
            ->dispatch('calculator-save', [
                'product_id' => $product->id,
                'qty' => 2,
            ])
            ->set('form.vorname', 'Max')
            ->set('form.nachname', 'Mustermann')
            ->set('form.email', 'max@example.com')
            ->set('form.country', 'DE')
            ->set('form.telefon', '0123456789')
            ->call('submit');

        $component->assertSet('step', 4)
                  ->assertSee('erfolgreich');

        // Verify Database Record
        $this->assertDatabaseHas('order_quote_requests', [
            'email' => 'max@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'gross_total' => 4490, // 2 * 20 EUR = 40 EUR + 4.90 Shipping (because < 50 EUR DE) -> 44.90 * 100
        ]);

        $quote = OrderQuoteRequest::where('email', 'max@example.com')->first();
        $this->assertDatabaseHas('order_quote_request_items', [
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Verify Mails
        Mail::assertQueued(NewCalcMailToCustomer::class, function ($mail) {
            return $mail->hasTo('max@example.com');
        });

        Mail::assertQueued(NewCalcMailToAdmin::class, function ($mail) {
            $owner_mail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
            return $mail->hasTo($owner_mail);
        });
    }
}
