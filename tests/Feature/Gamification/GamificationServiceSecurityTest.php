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
        $this->assertEquals(GameConfig::DAILY_SPARK_LIMIT, $profile->titles_progress['sammler'] ?? 0);
    }

    public function test_new_customer_starts_without_rank_until_title_is_unlocked_and_selected()
    {
        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'norank@example.com',
            'first_name' => 'No',
            'last_name' => 'Rank',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $this->assertNull($this->service->resolveActiveRankName($profile));

        // Unlock 'sammler' silber (req >= 50)
        $progress = $profile->titles_progress ?? [];
        $progress['sammler'] = 50;
        $profile->titles_progress = $progress;
        $profile->active_title = 'sammler';
        $profile->save();

        $this->assertEquals('Sucher', $this->service->resolveActiveRankName($profile->fresh()));
    }

    public function test_order_complete_payment_awards_seelenfunken_and_syncs_order_titles()
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\OrderPlaced::class]);
        \Illuminate\Support\Facades\Queue::fake([\App\Jobs\ProcessOrderDocumentsAndMails::class]);

        $customer = Customer::create([
            'id' => Str::uuid(),
            'email' => 'buyer-game@example.com',
            'first_name' => 'Buyer',
            'last_name' => 'Game',
            'password' => bcrypt('secret123'),
        ]);

        $profile = $this->service->getProfile($customer);
        $this->assertEquals(0, $profile->funken_balance);

        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-GAME-REWARD-01',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'payment_status' => 'pending',
            'status' => 'pending',
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'shipping_price' => 0,
            'total_price' => 4000, // 40,00 € => 20 Funken
            'billing_address' => ['first_name' => 'Buyer', 'last_name' => 'Game', 'address' => 'Hauptstr. 1', 'postal_code' => '38518', 'city' => 'Gifhorn'],
            'shipping_address' => ['first_name' => 'Beschenkt', 'last_name' => 'Freund', 'address' => 'Geschenkweg 5', 'postal_code' => '10115', 'city' => 'Berlin'],
        ]);

        $order->completePayment('pi_game_reward_test');

        $profile->refresh();
        $this->assertEquals(20, $profile->funken_balance);
        $this->assertEquals(20, $profile->funken_total_earned);
        $this->assertEquals(1, $profile->titles_progress['freudebringer'] ?? 0);

        // Equip 'freudebringer' (unlocked at Silber req >= 1)
        $profile->update(['active_title' => 'freudebringer']);
        $this->assertEquals('Aufmerksamer Freund', $this->service->resolveActiveRankName($profile->fresh()));

        // Cancel order -> sparks reversed idempotently & active_title reset to null since freudebringer drops to 0
        $prevStatus = $order->status;
        $prevPaymentStatus = $order->payment_status;
        $order->update(['status' => 'cancelled', 'payment_status' => 'refunded']);
        $order->handleCancellation('Test-Storno', $prevStatus, $prevPaymentStatus);
        // Second call must be a no-op (idempotent)
        $order->handleCancellation('Test-Storno Doppelaufruf', 'cancelled', 'refunded');

        $profile->refresh();
        $this->assertEquals(0, $profile->funken_balance);
        $this->assertEquals(0, $profile->funken_total_earned);
        $this->assertEquals(0, $profile->titles_progress['freudebringer'] ?? 0);
        $this->assertNull($profile->active_title);
        $this->assertNull($this->service->resolveActiveRankName($profile));
    }
}
