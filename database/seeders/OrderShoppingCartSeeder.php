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
        $targetCarts = 30;
        
        $this->command->info("Starte umfangreiches Seeding: Generiere exakt {$targetCarts} verlorene Warenkörbe (mit gestaffeltem Alter) ...");

        // Delete existing carts to ensure clean run
        CartItem::query()->delete();
        Cart::query()->delete();

        for ($i = 0; $i < $targetCarts; $i++) {
            
            // Randomly attach a customer (60% chance)
            $customerId = null;
            if (!empty($customerIds) && $faker->boolean(60)) {
                $customerId = $faker->randomElement($customerIds);
            }

            // Determine Cart Age to fall into different Traffic Light categories:
            // 20% Green (0-2h), 40% Yellow (3-23h), 40% Red (24h+)
            $ageCategory = $faker->randomElement(['green', 'green', 'yellow', 'yellow', 'yellow', 'yellow', 'red', 'red', 'red', 'red']);
            
            if ($ageCategory === 'green') {
                $updatedAt = Carbon::now()->subMinutes($faker->numberBetween(10, 150));
            } elseif ($ageCategory === 'yellow') {
                $updatedAt = Carbon::now()->subHours($faker->numberBetween(4, 22));
            } else {
                $updatedAt = Carbon::now()->subHours($faker->numberBetween(25, 300));
            }
            
            $createdAt = (clone $updatedAt)->subMinutes($faker->numberBetween(5, 60));

            $cart = Cart::create([
                'id' => Str::uuid(),
                'session_id' => Str::random(40),
                'customer_id' => $customerId,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            // Add ultra realistic items
            $numItems = $faker->numberBetween(1, 4);
            for ($k = 0; $k < $numItems; $k++) {
                $product = $products->random();
                $qty = $faker->numberBetween(1, 3);
                $unitPrice = $product->price;

                CartItem::create([
                    'id' => Str::uuid(),
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'configuration' => [
                        'color' => $faker->randomElement(['Naturholz', 'Schwarz', 'Weiß']),
                        'engraving_text' => $faker->boolean(40) ? $faker->words(2, true) : null
                    ],
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ]);
            }
        }
        
        $this->command->info("Perfekt! Es wurden 30 gestaffelte Warenkörbe generiert (Grün, Gelb & Rot).");
    }
}
