<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Customer\Customer;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use Faker\Factory as Faker;

class OrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('de_DE');

        // Lade oder erzeuge Test-Produkte
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

        // 1. Kapazitätsberechnung für Standardwerte (7x10x5 -> 37 Pakete)
        SystemSetting::updateOrCreate(['key' => 'shop_daily_working_hours'], ['value' => 7]);
        SystemSetting::updateOrCreate(['key' => 'shop_minutes_per_order'], ['value' => 10]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_buffer'], ['value' => 5]);

        \Illuminate\Support\Facades\Cache::forget('shop_daily_working_hours');
        \Illuminate\Support\Facades\Cache::forget('shop_minutes_per_order');
        \Illuminate\Support\Facades\Cache::forget('shop_capacity_buffer');
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $dailyWorkingHours = 7.0;
        $minutesPerOrder = 10;
        $capacityBuffer = 5;

        // Theoretisches Limit und Max Capacity
        $theoreticalLimit = ($dailyWorkingHours * 60) / max(1, $minutesPerOrder);
        $maxCapacity = max(1, (int) round($theoreticalLimit) - $capacityBuffer);
        
        // 95% von 37 Paketen = 35.15 -> 35 Bestellungen = 94.6% (das nächste an 95%)
        $targetOrders = (int) round($maxCapacity * 0.95);

        // Alle bestehenden aktiven Orders löschen, um sauber zu starten
        OrderOrder::whereIn('status', ['pending', 'processing'])->delete();

        $this->command->info("Starte ultra realistisches Seeding: Generiere exakt {$targetOrders} aktive Bestellungen für 95% Auslastung...");

        for ($i = 0; $i < $targetOrders; $i++) {

            // Kunde generieren
            $customerId = null;
            $customerEmail = $faker->unique()->safeEmail;
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;

            if (!empty($customerIds) && $faker->boolean(70)) {
                $customerId = $faker->randomElement($customerIds);
            }

            // Garantiert "processing" oder "pending" für die Auslastung
            $status = $faker->randomElement(['pending', 'processing']);
            $paymentStatus = 'paid';

            $billingAddress = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => $faker->boolean(20) ? $faker->company : null,
                'address' => $faker->streetAddress,
                'postal_code' => $faker->postcode,
                'city' => $faker->city,
                'country' => 'DE',
            ];

            // Ultra realistische Produkte anhängen
            $orderItems = [];
            $subtotal = 0;
            $numItems = $faker->numberBetween(1, 3);
            
            for ($k = 0; $k < $numItems; $k++) {
                $product = $products->random();
                $qty = $faker->numberBetween(1, 2);
                $unitPrice = $product->price;
                $lineTotal = $unitPrice * $qty;
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'id' => Str::uuid(),
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'tax_rate' => $product->tax_rate ?? 19.00,
                    'configuration' => [
                        'color' => $faker->randomElement(['Naturholz', 'Schwarz', 'Weiß']),
                        'engraving_text' => $faker->boolean(50) ? $faker->words(3, true) : null
                    ]
                ];
            }

            $discountAmount = $faker->boolean(20) ? (int)($subtotal * 0.10) : 0;
            $couponCode = $discountAmount > 0 ? 'RABATT10' : null;

            $shippingPrice = $subtotal > 5000 ? 0 : 490;
            $taxAmount = (int) round(($subtotal - $discountAmount + $shippingPrice) - (($subtotal - $discountAmount + $shippingPrice) / 1.19));
            $totalPrice = $subtotal + $shippingPrice - $discountAmount;

            $order = OrderOrder::create([
                'id' => Str::uuid(),
                'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
                'customer_id' => $customerId,
                'status' => $status,
                'is_express' => $faker->boolean(15),
                'deadline' => null, // Express deadlines usually handled separately if needed
                'payment_status' => $paymentStatus,
                'payment_method' => $faker->randomElement(['stripe', 'paypal']),
                'email' => $customerEmail,
                'billing_address' => $billingAddress,
                'shipping_address' => $billingAddress, // keep simple
                'subtotal_price' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_price' => $shippingPrice,
                'total_price' => $totalPrice,
                'discount_amount' => $discountAmount,
                'coupon_code' => $couponCode,
                'created_at' => $faker->dateTimeBetween('-2 days', 'now'),
                'updated_at' => now(),
            ]);

            // Save Items
            foreach ($orderItems as $itemData) {
                OrderOrderItem::create(array_merge($itemData, ['order_id' => $order->id]));
            }

            // Create Ultra Realistic Invoice!
            AccountingInvoice::create([
                'id' => Str::uuid(),
                'invoice_number' => 'RE-' . date('Y') . '-' . mt_rand(100, 999) . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT),
                'type' => 'invoice',
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'status' => 'paid',
                'invoice_date' => $order->created_at,
                'delivery_date' => $order->created_at->addDays(3),
                'due_date' => $order->created_at->addDays(14),
                'paid_at' => $order->created_at->addMinutes(15), // Sofort bezahlt
                'billing_address' => $billingAddress,
                'shipping_address' => $billingAddress,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shippingPrice,
                'discount_amount' => $discountAmount,
                'total' => $totalPrice,
                'header_text' => 'Vielen Dank für Ihre Bestellung!',
                'footer_text' => 'Bitte überweisen Sie den Betrag innerhalb von 14 Tagen. Zahlungsziel: [%ZAHLUNGSZIEL%]',
            ]);
        }

        $this->command->info("Perfekt! Die Shop Auslastung wurde auf exakt 95% gesät (Max: {$maxCapacity}, Aktiv: {$targetOrders}).");
        
        // Triggere die Engine um das Level zu berechnen und den Livewire Cache zu updaten
        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
    }
}
