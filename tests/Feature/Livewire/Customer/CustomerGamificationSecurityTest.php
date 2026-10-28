<?php

namespace Tests\Feature\Livewire\Customer;

use App\Livewire\Customer\Gamification\GameGamesComponent;
use App\Livewire\Customer\Gamification\GameProfileComponent;
use App\Livewire\Customer\Gamification\GameSparkHuntComponent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerGamificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected CustomerGamification $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Gamer',
            'email' => 'gamer-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->profile = CustomerGamification::firstOrCreate(
            ['customer_id' => $this->customer->id],
            [
                'is_active' => true,
                'energy_balance' => 5,
                'funken_balance' => 0,
                'funken_total_earned' => 0,
                'funkenflug_highscore' => 100,
                'level' => 1,
                'active_title' => 'apprentice'
            ]
        );

        $this->actingAs($this->customer, 'customer');
    }

    public function test_cannot_claim_reward_sparks_without_consuming_energy_session()
    {
        session()->forget('active_game_session');

        Livewire::test(GameGamesComponent::class)
            ->call('rewardGameSparks', 50);

        $this->profile->refresh();
        $this->assertEquals(0, $this->profile->funken_balance, 'Sparks must not be credited without active game session');
    }

    public function test_cannot_save_game_record_without_consuming_energy_session()
    {
        session()->forget('active_game_session');

        Livewire::test(GameGamesComponent::class)
            ->call('saveGameRecord', 999999, 500);

        $this->profile->refresh();
        $this->assertEquals(100, $this->profile->funkenflug_highscore, 'Highscore must not be updated without active game session');
        $this->assertEquals(0, $this->profile->funken_balance, 'Sparks must not be awarded without active game session');
    }

    public function test_game_session_cannot_be_reused_multiple_times()
    {
        // 1. Start game properly
        $component = Livewire::test(GameGamesComponent::class)
            ->call('consumeEnergy');

        $this->assertNotEmpty(session('active_game_session'));

        // Manually adjust start time to simulate 10 seconds of gameplay
        $sess = session('active_game_session');
        $sess['started_at'] = now()->subSeconds(10)->timestamp;
        session(['active_game_session' => $sess]);

        // 2. Submit record first time
        $component->call('saveGameRecord', 200, 10);
        $this->profile->refresh();
        $this->assertEquals(200, $this->profile->funkenflug_highscore);
        $this->assertEquals(10, $this->profile->funken_balance);

        // 3. Session should now be cleared
        $this->assertNull(session('active_game_session'));

        // 4. Second submission must be rejected
        $component->call('saveGameRecord', 500, 50);
        $this->profile->refresh();
        $this->assertEquals(200, $this->profile->funkenflug_highscore, 'Second submission with consumed session must be rejected');
        $this->assertEquals(10, $this->profile->funken_balance, 'Additional sparks must not be awarded');
    }

    public function test_game_record_distance_and_sparks_are_clamped_to_physical_bounds()
    {
        Livewire::test(GameGamesComponent::class)
            ->call('consumeEnergy');

        // Simulate 5 seconds elapsed
        $sess = session('active_game_session');
        $sess['started_at'] = now()->subSeconds(5)->timestamp;
        session(['active_game_session' => $sess]);

        // Attempt impossible distance for 5 seconds (e.g. 50,000 meters and 5,000 sparks)
        Livewire::test(GameGamesComponent::class)
            ->call('saveGameRecord', 50000, 5000);

        $this->profile->refresh();
        // 5 seconds * 85 max speed (incl. ship skin distance bonus) = 425 max distance
        $this->assertLessThanOrEqual(425, $this->profile->funkenflug_highscore);
        $this->assertLessThanOrEqual(40, $this->profile->funken_balance);
    }

    public function test_cannot_equip_unearned_title()
    {
        Livewire::test(GameGamesComponent::class)
            ->call('selectTitle', 'seelengott')
            ->assertDispatched('notify');

        $this->profile->refresh();
        $this->assertNotEquals('seelengott', $this->profile->active_title);
    }

    public function test_spark_hunt_is_visible_property_cannot_be_tampered()
    {
        $component = Livewire::test(GameSparkHuntComponent::class);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $component->set('isVisible', true);
    }

    public function test_spark_hunt_collects_visible_spark_and_awards_funke()
    {
        // Mount repeatedly until the 30% spawn triggers (max 50 tries)
        $component = null;
        for ($i = 0; $i < 50; $i++) {
            $candidate = Livewire::test(GameSparkHuntComponent::class);
            if ($candidate->get('isVisible') === true) {
                $component = $candidate;
                break;
            }
        }

        $this->assertNotNull($component, 'Spark should spawn within 50 attempts');
        $this->assertTrue($component->get('isVisible'));

        $component->call('collectSpark')
            ->assertDispatched('notify')
            ->assertDispatched('sparks-awarded')
            ->assertSet('isVisible', false);

        $this->profile->refresh();
        $this->assertEquals(1, $this->profile->funken_balance);
        $this->assertEquals(1, $this->profile->sparks_collected_today);
    }

    public function test_upgrade_level_handles_race_conditions_cleanly()
    {
        $service = app(GamificationService::class);
        $this->profile->update([
            'level' => 2,
            'funken_balance' => 100,
        ]);

        $res = $service->upgradeLevel($this->customer);
        $this->assertTrue($res['success']);
        $this->profile->refresh();
        $this->assertEquals(3, $this->profile->level);

        // Verify voucher was created once for level 3
        $this->assertCount(1, MarketingVoucher::where('title', 'like', '%Level 3 Belohnung%')->get());

        // Calling coupon check again does not create a duplicate
        $res2 = $service->upgradeLevel($this->customer);
        $this->profile->refresh();
        $this->assertCount(1, MarketingVoucher::where('title', 'like', '%Level 3 Belohnung%')->get());
    }
}
