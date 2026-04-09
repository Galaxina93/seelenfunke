<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\System\SystemAiHostingPlan;

class SystemInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Mittwald Starter',
                'token_limit' => 5000000,
                'price_monthly' => 9.00,
                'is_active' => false,
            ],
            [
                'name' => 'Mittwald Pro',
                'token_limit' => 75000000,
                'price_monthly' => 39.00,
                'is_active' => true,
            ],
            [
                'name' => 'Mittwald Business',
                'token_limit' => 300000000,
                'price_monthly' => 149.00,
                'is_active' => false,
            ],
            [
                'name' => 'Mittwald Dedicated',
                'token_limit' => 10000000000, // Milliarden
                'price_monthly' => 999.00,
                'is_active' => false,
            ],
            [
                'name' => 'Lokal gehostet',
                'token_limit' => 999000000,
                'price_monthly' => 0.00,
                'is_active' => false,
            ]
        ];

        foreach ($plans as $plan) {
            SystemAiHostingPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
