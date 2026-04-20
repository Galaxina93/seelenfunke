<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Carbon\Carbon;

class OrderShoppingCartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('de_DE');

        // Ensure products exist
        $products = Product::where('status', 'active')->get();
        if ($products->isEmpty()) {
            $product = Product::create([
                'id' => Str::uuid(),
                'name' => 'Seelenfunke Premium Holzschild',
                'slug' => 'seelenfunke-premium-holzschild-' . uniqid(),
                'price' => 4990, // 49,90€
                'status' => 'active',
                'type' => 'physical',
                'tax_rate' => 19.00,
                'tax_included' => true,
                'weight' => 500,
            ]);
            $products->push($product);
        }

        $customerIds = Customer::pluck('id')->toArray();
        $customerId = !empty($customerIds) ? $faker->randomElement($customerIds) : null;

        // Delete existing carts to ensure clean run
        CartItem::query()->delete();
        Cart::query()->delete();

        // 1. Gast-Warenkorb
        $guestCart = Cart::create([
            'id' => Str::uuid(),
            'session_id' => Str::random(40),
            'customer_id' => null,
            'created_at' => Carbon::now()->subHours(2),
            'updated_at' => Carbon::now()->subHours(1),
        ]);

        if ($products->count() > 0) {
            $randomProduct = $products->random();
            CartItem::create([
                'id' => Str::uuid(),
                'cart_id' => $guestCart->id,
                'product_id' => $randomProduct->id,
                'quantity' => 1,
                'unit_price' => $randomProduct->price,
                'configuration' => [],
                'created_at' => Carbon::now()->subHours(2),
                'updated_at' => Carbon::now()->subHours(1)
            ]);
        }

        // 2. Kunden-Warenkorb mit ALLEN Artikeln
        $customerCart = Cart::create([
            'id' => Str::uuid(),
            'session_id' => Str::random(40),
            'customer_id' => $customerId, // Falls null, existiert kein Nutzer, dann ist es halt ein 2ter Gast
            'created_at' => Carbon::now()->subHours(24),
            'updated_at' => Carbon::now()->subHours(12),
        ]);

        foreach ($products as $product) {
            CartItem::create([
                'id' => Str::uuid(),
                'cart_id' => $customerCart->id,
                'product_id' => $product->id,
                'quantity' => $faker->numberBetween(1, 3),
                'unit_price' => $product->price,
                'configuration' => [
                    'color' => $faker->randomElement(['Naturholz', 'Schwarz', 'Weiß']),
                    'engraving_text' => $faker->boolean(40) ? $faker->words(2, true) : null
                ],
                'created_at' => Carbon::now()->subHours(24),
                'updated_at' => Carbon::now()->subHours(12)
            ]);
        }


    }
}
