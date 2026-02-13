<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Order\Order; // Stelle sicher, dass der Namespace stimmt
use App\Models\Customer\Customer;
use Faker\Factory as Faker;

class OrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('de_DE');

        // Wir holen uns existierende Customer IDs, um echte Verknüpfungen zu simulieren
        // Falls keine Kunden existieren, bleibt das Array leer
        $customerIds = [];
        try {
            $customerIds = Customer::pluck('id')->toArray();
        } catch (\Exception $e) {
            // Ignorieren, falls Model nicht existiert oder Tabelle leer
        }

        for ($i = 0; $i < 30; $i++) {

            // 1. Kunde bestimmen (Registriert oder Gast)
            $customerId = null;
            $customerEmail = $faker->unique()->safeEmail;
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;

            // 70% Wahrscheinlichkeit für verknüpften Kunden, wenn Kunden existieren
            if (!empty($customerIds) && $faker->boolean(70)) {
                $customerId = $faker->randomElement($customerIds);
                // Optional: Echte Daten vom Kunden holen, hier simulieren wir es einfachheitshalber
            }

            // 2. Status Logik
            $status = $faker->randomElement(['pending', 'processing', 'shipped', 'completed', 'cancelled']);

            // Wenn versendet/fertig, dann meistens bezahlt. Wenn storniert, dann refunded oder unpaid.
            $paymentStatus = 'unpaid';
            if (in_array($status, ['processing', 'shipped', 'completed'])) {
                $paymentStatus = 'paid';
            } elseif ($status === 'cancelled') {
                $paymentStatus = $faker->randomElement(['refunded', 'unpaid']);
            }

            // 3. Adressen generieren
            $billingAddress = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => $faker->boolean(20) ? $faker->company : null,
                'address' => $faker->streetAddress,
                'postal_code' => $faker->postcode,
                'city' => $faker->city,
                'country' => 'DE',
            ];

            // 30% Wahrscheinlichkeit für abweichende Lieferadresse
            $shippingAddress = null;
            if ($faker->boolean(30)) {
                $shippingAddress = [
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'company' => null,
                    'address' => $faker->streetAddress,
                    'postal_code' => $faker->postcode,
                    'city' => $faker->city,
                    'country' => 'DE',
                ];
            }

            // 4. Preise berechnen (in Cent)
            $subtotal = $faker->numberBetween(1500, 25000); // 15€ bis 250€

            // Versandkosten (frei ab gewissem Wert simulieren oder pauschal)
            $shippingPrice = $subtotal > 10000 ? 0 : 490; // Kostenlos ab 100€, sonst 4,90€

            // Rabatt
            $discountAmount = 0;
            $couponCode = null;
            if ($faker->boolean(20)) { // 20% Chance auf Gutschein
                $discountAmount = intval($subtotal * 0.10); // 10% Rabatt
                $couponCode = 'WELCOME10';
            }

            // Steuer (grob 19% vom Subtotal berechnet für Demo-Zwecke)
            $taxAmount = intval(($subtotal - $discountAmount) * 0.19);

            // Endsumme
            $totalPrice = $subtotal + $shippingPrice - $discountAmount;


            // 5. Erstellen
            Order::create([
                'id' => Str::uuid(),
                'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
                'customer_id' => $customerId,

                'status' => $status,
                'is_express' => $faker->boolean(10), // 10% Express
                'deadline' => $faker->boolean(5) ? $faker->dateTimeBetween('now', '+1 week') : null,

                'payment_status' => $paymentStatus,
                'payment_method' => $faker->randomElement(['stripe', 'paypal', 'invoice']),
                'payment_url' => ($paymentStatus === 'unpaid') ? 'https://checkout.stripe.com/pay/...' : null,
                'stripe_payment_intent_id' => $paymentStatus === 'paid' ? 'pi_' . Str::random(24) : null,

                'email' => $customerEmail,

                'billing_address' => $billingAddress, // Model castet dies automatisch zu JSON
                'shipping_address' => $shippingAddress, // Model castet dies automatisch zu JSON

                'subtotal_price' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_price' => $shippingPrice,
                'total_price' => $totalPrice,

                'volume_discount' => 0,
                'coupon_code' => $couponCode,
                'discount_amount' => $discountAmount,

                'notes' => $faker->boolean(15) ? $faker->sentence : null,
                'cancellation_reason' => $status === 'cancelled' ? $faker->sentence : null,

                // Ein paar Bestellungen in die Vergangenheit datieren für Statistiken
                'created_at' => $faker->dateTimeBetween('-3 months', 'now'),
                'updated_at' => now(),
            ]);
        }
    }
}
