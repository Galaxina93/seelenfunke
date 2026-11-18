<?php

namespace Tests\Feature\Livewire\Customer;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Customer\Gamification\GameSparkHuntComponent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderRevocation;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            'email_verified_at' => now(),
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

    public function test_spark_hunt_atomic_cache_lock_blocks_parallel_double_click_requests()
    {
        $component = null;
        for ($i = 0; $i < 120; $i++) {
            Cache::forget('spark_hunt_cooldown_' . $this->customer->id);
            session()->forget(['spark_hunt_token', 'spark_hunt_user_id', 'spark_hunt_spawned_at']);

            $candidate = Livewire::test(GameSparkHuntComponent::class);
            if ($candidate->get('isVisible') === true) {
                $component = $candidate;
                break;
            }
        }

        $this->assertNotNull($component);
        $token = (string) $component->get('sparkToken');
        $this->assertNotEmpty($token);

        // Simulate a parallel Livewire request having just acquired the atomic single-use lock
        $this->assertTrue(Cache::add('spark_claim_lock:' . $token, 1, 60));

        $component->call('collectSpark')
            ->assertSet('isVisible', false)
            ->assertSet('sparkToken', '');

        $this->profile->refresh();
        $this->assertEquals(0, $this->profile->funken_balance, 'Parallel second request must be blocked by spark_claim_lock');
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

        $service->upgradeLevel($this->customer);
        $this->profile->refresh();
        $this->assertCount(1, MarketingVoucher::where('title', 'like', '%Level 3 Belohnung%')->get());
    }

    public function test_dashboard_voucher_redemption_rejects_invalid_tier_and_insufficient_balance()
    {
        $this->profile->update([
            'funken_balance' => 240,
            'funken_total_earned' => 240,
            'funki_level' => 2,
        ]);

        Livewire::test(CustomerDashboardComponent::class)
            ->assertSee('Ins Funkenreich')
            ->assertDontSee('3D-Lobby öffnen')
            ->call('redeemFunkenVoucher', 'invalid_tier')
            ->call('redeemFunkenVoucher', 'voucher_5'); // requires 250

        $this->profile->refresh();
        $this->assertEquals(240, $this->profile->funken_balance);
    }

    public function test_dashboard_activate_seelenfunke_is_free_without_order_and_initializes_hearts()
    {
        $this->profile->update(['is_active' => false]);

        // Kostenlose 1-Klick-Magie-Aktivierung ohne Bestellpflicht (Ehrenpodeste erfordern weiterhin eine Bestellung)
        Livewire::test(CustomerDashboardComponent::class)
            ->call('activateSeelenfunke')
            ->assertSet('isGameModeActive', true);

        $fresh = $this->profile->fresh();
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame(6, (int) $fresh->magma_hearts);
        $this->assertGreaterThanOrEqual(1, (int) $fresh->magma_unlocked_level);

        // Deaktivieren und sofort wieder aktivieren bleibt verlustfrei moeglich
        Livewire::test(CustomerDashboardComponent::class)
            ->call('deactivateSeelenfunke')
            ->assertSet('isGameModeActive', false)
            ->call('activateSeelenfunke')
            ->assertSet('isGameModeActive', true);

        $this->assertTrue((bool) $this->profile->fresh()->is_active);
    }

    public function test_hall_of_fame_pagination_avoids_n_plus_1_write_storm_and_rate_limits_rapid_paging()
    {
        for ($i = 1; $i <= 12; $i++) {
            $c = Customer::create([
                'id' => (string) Str::uuid(),
                'first_name' => 'Held' . $i,
                'last_name' => 'Test' . $i,
                'email' => "held.{$i}@example.de",
                'email_verified_at' => now(),
                'password' => bcrypt('secret123'),
            ]);
            OrderOrder::create([
                'order_number' => 'ORD-HOF-' . $i,
                'customer_id' => $c->id,
                'email' => $c->email,
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal_price' => 2500,
                'tax_amount' => 399,
                'shipping_price' => 0,
                'total_price' => 2500,
                'billing_address' => ['first_name' => $c->first_name, 'last_name' => $c->last_name],
                'shipping_address' => ['first_name' => $c->first_name, 'last_name' => $c->last_name],
            ]);
            CustomerGamification::create([
                'customer_id' => $c->id,
                'is_active' => true,
                'ranking_opt_in' => true,
                'player_name' => sprintf('HeldPilot%02d', $i),
                'level' => 3,
                'funken_total_earned' => $i * 100,
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $paginated = app(GamificationService::class)->getHallOfFamePaginated(1, 10);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(10, $paginated['items']);
        $updateQueries = array_filter($queries, static fn (array $q): bool => str_starts_with(strtolower(trim($q['query'])), 'update '));
        $this->assertCount(0, $updateQueries, 'getHallOfFamePaginated must perform 0 SQL UPDATE queries');

        $comp = Livewire::test(CustomerDashboardComponent::class);
        for ($i = 0; $i < 31; $i++) {
            $comp->call('gotoHallOfFamePage', ($i % 2) + 1);
        }
        $comp->call('gotoHallOfFamePage', 2)
            ->assertDispatched('notify');
    }
}
