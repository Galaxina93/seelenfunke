<?php

namespace Database\Factories\Accounting;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\AccountingInvoice>
 */
class AccountingInvoiceFactory extends Factory
{
    protected $model = \App\Models\Accounting\AccountingInvoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'invoice_number' => 'INV-' . $this->faker->unique()->numberBetween(10000, 99999),
            'type' => 'invoice',
            'status' => 'draft',
            'invoice_date' => now(),
            'due_date' => now()->addDays(14),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total' => 11900,
            'billing_address' => [
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'address' => $this->faker->streetAddress(),
                'postal_code' => $this->faker->postcode(),
                'city' => $this->faker->city(),
                'country' => 'DE'
            ],
            'customer_id' => null,
            'order_id' => null,
            'is_e_invoice' => false,
        ];
    }
}
