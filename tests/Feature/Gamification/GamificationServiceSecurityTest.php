<?php

namespace Tests\Feature\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GamificationServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected GamificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GamificationService::class);
    }

    public function test_add_funken_increments_balance_and_rejects_negative_amounts()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'gamer@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $this->assertEquals(0, $profile->funken_balance);
        $this->assertEquals(0, $profile->funken_total_earned);

        // 1. Valid positive addition
        $this->service->addFunken($customer, 10, 'purchase');
        $profile->refresh();
        $this->assertEquals(10, $profile->funken_balance);
        $this->assertEquals(10, $profile->funken_total_earned);

        // 2. Negative amount attempt must be rejected
        $this->service->addFunken($customer, -50, 'exploit');
        $profile->refresh();
        $this->assertEquals(10, $profile->funken_balance);
        $this->assertEquals(10, $profile->funken_total_earned);

        // 3. Zero amount attempt must be ignored
        $this->service->addFunken($customer, 0, 'zero');
        $profile->refresh();
        $this->assertEquals(10, $profile->funken_balance);
        $this->assertEquals(10, $profile->funken_total_earned);
    }

    public function test_website_hunt_respects_daily_limit()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'hunter@example.com',
            'first_name' => 'Hunter',
            'last_name' => 'Test',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);

        // Collect up to DAILY_SPARK_LIMIT
        for ($i = 0; $i < GameConfig::DAILY_SPARK_LIMIT; $i++) {
            $this->service->addFunken($customer, 1, 'website_hunt');
        }

        $profile->refresh();
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->sparks_collected_today);
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->funken_balance);

        // Additional hunt attempts beyond daily limit must be rejected
        $this->service->addFunken($customer, 1, 'website_hunt');
        $profile->refresh();
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->sparks_collected_today);
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->funken_balance);
    }
}
