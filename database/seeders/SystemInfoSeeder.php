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
                'name' => 'API Studio (Free Tier)',
                'token_limit' => 25000000,
                'price_monthly' => 0.00,
                'is_active' => false,
            ],
            [
                'name' => 'Vertex AI (Pay-as-you-go)',
                'token_limit' => 0, // 0 = unbegrenzt
                'price_monthly' => 0.00,
                'is_active' => true,
            ],
            [
                'name' => 'Kosten-Limit: 100M Tokens',
                'token_limit' => 100000000,
                'price_monthly' => 0.00,
                'is_active' => false,
            ],
            [
                'name' => 'Kosten-Limit: 500M Tokens',
                'token_limit' => 500000000,
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
