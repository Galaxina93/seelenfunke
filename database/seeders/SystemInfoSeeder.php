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
                'name' => 'Mittwald Space',
                'token_limit' => 5000000,
                'price_monthly' => 5.00,
                'is_active' => false,
            ],
            [
                'name' => 'Mittwald Pro',
                'token_limit' => 75000000,
                'price_monthly' => 39.00,
                'is_active' => true,
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
