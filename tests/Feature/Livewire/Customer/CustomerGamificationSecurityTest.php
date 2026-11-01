<?php

namespace Tests\Feature\Livewire\Customer;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Customer\Gamification\GameSparkHuntComponent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
            'last_name' => 'Sammler',
            'email' => 'sammler-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->profile = CustomerGamification::firstOrCreate(
            ['customer_id' => $this->customer->id],
            [
                'is_active' => true,
                'funken_balance' => 0,
                'funken_total_earned' => 0,
                'level' => 1,
                'funki_level' => 1,
            ]
        );

        $this->actingAs($this->customer, 'customer');
    }

    public function test_spark_hunt_is_visible_property_cannot_be_tampered()
    {
        $component = Livewire::test(GameSparkHuntComponent::class);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $component->set('isVisible', true);
    }

    public function test_spark_hunt_spark_token_property_cannot_be_tampered()
    {
        $component = Livewire::test(GameSparkHuntComponent::class);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $component->set('sparkToken', 'forged-token');
    }

    public function test_spark_hunt_collects_visible_spark_and_enforces_reload_cooldown()
    {
        $component = null;
        for ($i = 0; $i < 120; $i++) {
            Cache::forget('spark_hunt_cooldown_' . $this->customer->id);
            session()->forget(['spark_hunt_active_token', 'spark_hunt_spawned_at']);

            $candidate = Livewire::test(GameSparkHuntComponent::class);
            if ($candidate->get('isVisible') === true) {
                $component = $candidate;
                break;
            }
        }

        $this->assertNotNull($component, 'Spark should spawn within 120 attempts when cooldown is cleared');
        $this->assertTrue($component->get('isVisible'));
        $this->assertNotEmpty($component->get('sparkToken'));

        $component->call('collectSpark')
            ->assertDispatched('notify')
            ->assertDispatched('sparks-awarded')
            ->assertSet('isVisible', false)
            ->assertSet('sparkToken', '');

        $this->profile->refresh();
        $this->assertEquals(1, $this->profile->funken_balance);
        $this->assertEquals(1, $this->profile->sparks_collected_today);

        // Second call on same component (replay attack) must fail because single-use token was consumed
        $component->call('collectSpark');
        $this->profile->refresh();
        $this->assertEquals(1, $this->profile->funken_balance);

        // Reloading page immediately (F5) must NOT spawn a new spark because server-side 180s cooldown is active
        $this->assertTrue(Cache::has('spark_hunt_cooldown_' . $this->customer->id));
        $reloaded = Livewire::test(GameSparkHuntComponent::class);
        $this->assertFalse($reloaded->get('isVisible'));
    }

    public function test_spark_hunt_rejects_collection_without_valid_session_token()
    {
        $component = null;
        for ($i = 0; $i < 120; $i++) {
            Cache::forget('spark_hunt_cooldown_' . $this->customer->id);
            session()->forget(['spark_hunt_active_token', 'spark_hunt_spawned_at']);

            $candidate = Livewire::test(GameSparkHuntComponent::class);
            if ($candidate->get('isVisible') === true) {
                $component = $candidate;
                break;
            }
        }

        $this->assertNotNull($component);

        // Invalidate server-side session token before calling collectSpark
        session()->forget('spark_hunt_token');

        $component->call('collectSpark')
            ->assertSet('isVisible', false);

        $this->profile->refresh();
        $this->assertEquals(0, $this->profile->funken_balance, 'Spark must not be awarded if server session token is missing/invalid');
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

        $this->assertCount(1, MarketingVoucher::where('title', 'like', '%Level 3 Belohnung%')->get());

        $res2 = $service->upgradeLevel($this->customer);
        $this->profile->refresh();
        $this->assertCount(1, MarketingVoucher::where('title', 'like', '%Level 3 Belohnung%')->get());
    }

    public function test_dashboard_voucher_redemption_rejects_invalid_tier_and_insufficient_balance()
    {
        $this->profile->update([
            'funken_balance' => 200,
            'funken_total_earned' => 200,
            'funki_level' => 2,
        ]);

        Livewire::test(CustomerDashboardComponent::class)
            ->call('redeemFunkenVoucher', 'invalid_tier')
            ->call('redeemFunkenVoucher', 'voucher_5'); // requires 250

        $this->profile->refresh();
        $this->assertEquals(200, $this->profile->funken_balance);
    }
}
