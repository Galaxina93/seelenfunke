<?php

namespace Tests\Feature\Gamification;

use App\Models\Admin\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use App\Models\System\SystemBlockedIp;
use App\Models\System\SystemSetting;
use App\Services\Gamification\LavaParkourService;
use App\Services\Gamification\LobbyInstanceService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LavaParkourAntiCheatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
        Carbon::setTestNow(Carbon::create(2026, 11, 17, 14, 0, 0, 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_honest_lava_parkour_run_awards_first_clear_sparks_updates_level_and_writes_audit_log(): void
    {
        $customer = $this->makeActiveCustomer(1, 20, 148, 1);

        $startResp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start');

        $startResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('level', 1)
            ->assertJsonPath('hearts', 6)
            ->assertJsonPath('max_hearts', 6)
            ->assertJsonPath('unlocked_level', 1)
            ->assertJsonPath('shield_charges_remaining', 3)
            ->assertJsonPath('claimed_today', false)
            ->assertJsonPath('first_clear_reward', 5)
            ->assertJsonPath('anchors.cp1.x', 18.5)
            ->assertJsonPath('anchors.cp1.y', 0.85)
            ->assertJsonPath('anchors.cp1.z', 7.5)
            ->assertJsonPath('anchors.cp2.x', 37.5)
            ->assertJsonPath('anchors.cp2.y', 1.95)
            ->assertJsonPath('anchors.cp2.z', -8.3)
            ->assertJsonPath('anchors.chest.x', 55.5)
            ->assertJsonPath('anchors.chest.y', 3.2)
            ->assertJsonPath('anchors.chest.z', 3.5);

        $runId = (string) $startResp->json('run_id');
        $lifeNonce = (string) $startResp->json('life_nonce');
        $this->assertSame(40, strlen($runId));
        $this->assertSame(32, strlen($lifeNonce));

        // Nach 2.0s (>= 1.6s): Checkpoint 1 (Weltkoordinaten: ORIGIN (16.5, 0.004, -2.0) + rel_cp1 (18.5, 0.85, 7.5) = (35.0, 0.854, 5.5))
        $this->travel(2000)->milliseconds();
        $cp1Resp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'life_nonce' => $lifeNonce,
                'stage' => 1,
                'x' => 35.0,
                'y' => 1.2,
                'z' => 5.5,
            ]);

        $cp1Resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 1)
            ->assertJsonPath('claim_token', null);

        // Idempotenter Retry auf Stage 1 darf den Lauf nicht zerstoeren
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 35.0,
                'y' => 1.2,
                'z' => 5.5,
            ])
            ->assertOk()
            ->assertJsonPath('stage', 1);

        // Weitere 2.2s (>= 2.0s): Checkpoint 2 (Weltkoordinaten: (16.5 + 37.5, 2.0, -2.0 + -8.3) = (54.0, 2.0, -10.3))
        $this->travel(2200)->milliseconds();
        $cp2Resp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'life_nonce' => $lifeNonce,
                'stage' => 2,
                'x' => 54.0,
                'y' => 2.0,
                'z' => -10.3,
            ]);

        $cp2Resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 2);

        $claimToken = (string) $cp2Resp->json('claim_token');
        $this->assertSame(64, strlen($claimToken));

        // Idempotenter Retry auf Stage 2 liefert denselben Token
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 54.0,
                'y' => 2.0,
                'z' => -10.3,
            ])
            ->assertOk()
            ->assertJsonPath('claim_token', $claimToken);

        // Weitere 1.8s (>= 1.5s seit CP2, Gesamtlaufzeit = 6.0s >= 5.6s): Schatztruhe oeffnen (Weltkoordinaten: (72.0, 3.2, 1.5))
        $this->travel(1800)->milliseconds();
        $claimResp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'life_nonce' => $lifeNonce,
                'claim_token' => $claimToken,
                'x' => 72.0,
                'y' => 3.2,
                'z' => 1.5,
            ]);

        $claimResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('level', 1)
            ->assertJsonPath('sparks_awarded', 5)
            ->assertJsonPath('is_first_clear', true)
            ->assertJsonPath('already_claimed_today', false)
            ->assertJsonPath('funken_balance', 25)
            ->assertJsonPath('funken_total_earned', 153)
            ->assertJsonPath('player_level', 2)
            ->assertJsonPath('model_url', '/shop/customer/gamification/models/funki_lvl_2_basic.glb')
            ->assertJsonPath('master_key_unlocked', false)
            ->assertJsonPath('highest_unlocked_level', 2)
            ->assertJsonPath('unlocked_level', 2)
            ->assertJsonPath('unlocked_portals', [2])
            ->assertJsonPath('first_cleared_levels', [1])
            ->assertJsonPath('daily_cleared_levels', [1])
            ->assertJsonPath('daily_sparks_earned', 0)
            ->assertJsonPath('daily_sparks_cap', 15);

        $gam = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(25, (int) $gam->funken_balance);
        $this->assertSame(153, (int) $gam->funken_total_earned);
        $this->assertSame(2, (int) $gam->level);
        $this->assertSame(2, (int) $gam->magma_unlocked_level);
        $this->assertNull($gam->magma_active_run_id);
        $this->assertFalse((bool) $gam->magma_run_committed);
        $this->assertSame([1], $gam->titles_progress['lava_cleared_levels'] ?? []);

        $log = CustomerGamificationLog::where('customer_id', $customer->id)
            ->where('event_type', 'lava_parkour_chest')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('parkour', $log->category);
        $this->assertSame(5, (int) $log->sparks_delta);
        $this->assertSame(25, (int) $log->balance_after);
        $this->assertSame(1, (int) ($log->metadata['level'] ?? 0));
        $this->assertTrue((bool) ($log->metadata['is_first_clear'] ?? false));
    }

    public function test_ten_levels_progression_master_key_gating_at_level_6_revocation_fallback_and_daily_caps(): void
    {
        $customer = $this->makeActiveCustomer(20, 0, 0, 1, false);

        // 10-EUR-Geschenkgutschein (< 15 EUR) erfuellt zwar sanctum has_completed_order, aber NICHT den Meisterschluessel (>= 15 EUR)!
        $voucherProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'type' => 'digital',
            'status' => 'active',
            'price' => 0,
            'is_personalizable' => true,
        ]);

        $smallVoucherOrder = OrderOrder::create([
            'order_number' => 'ORD-VOUCHER-1000',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 1000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 1000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Test'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Test'],
        ]);
        OrderOrderItem::create([
            'order_id' => $smallVoucherOrder->id,
            'product_id' => $voucherProduct->id,
            'product_name' => 'Geschenkgutschein',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'configuration' => ['is_gift_voucher' => true, 'amount_cents' => 1000],
        ]);

        $this->assertFalse(app(LavaParkourService::class)->hasMasterKey($customer));

        // Versuch, Level 2 vor Abschluss von Level 1 zu starten -> HTTP 403 LEVEL_LOCKED
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 2])
            ->assertStatus(403)
            ->assertJsonPath('code', 'LEVEL_LOCKED');

        // Level 1 bis 5 mit exakten S-Kurven-Koordinaten absolvieren: 5 + 8 + 10 + 12 + 15 = 50 Funken
        $expectedFirstClear1To5 = [1 => 5, 2 => 8, 3 => 10, 4 => 12, 5 => 15];
        $runningTotal = 0;

        foreach ($expectedFirstClear1To5 as $lvl => $expectedSparks) {
            $claim = $this->performRelativeRunAndClaim($customer, $lvl);
            $runningTotal += $expectedSparks;

            $claim->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('level', $lvl)
                ->assertJsonPath('is_first_clear', true)
                ->assertJsonPath('sparks_awarded', $expectedSparks)
                ->assertJsonPath('funken_balance', $runningTotal)
                ->assertJsonPath('master_key_unlocked', false);
        }

        $this->assertSame(50, $runningTotal);

        // Nach Abschluss von Level 5 ohne Meisterschluessel: highest_unlocked_level bleibt 5, Level 6 liefert 403 MASTER_KEY_REQUIRED!
        $stateWithoutKey = app(LavaParkourService::class)->resolveProgressState($customer);
        $this->assertSame(5, $stateWithoutKey['highest_unlocked_level']);
        $this->assertSame([2, 3, 4, 5], $stateWithoutKey['unlocked_portals']);

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 6])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'MASTER_KEY_REQUIRED');

        // Naechster Berliner Kalendertag: Wiederholungs-Truhen Level 1..5 ergeben exakt 2 + 2 + 3 + 4 + 4 = 15 Funken
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 10, 0, 0, 'Europe/Berlin'));
        foreach ([1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 4] as $lvl => $dailySparks) {
            $rep = $this->performRelativeRunAndClaim($customer, $lvl);
            $runningTotal += $dailySparks;
            $rep->assertOk()
                ->assertJsonPath('is_first_clear', false)
                ->assertJsonPath('already_claimed_today', false)
                ->assertJsonPath('sparks_awarded', $dailySparks)
                ->assertJsonPath('daily_sparks_cap', 15);
        }
        $this->assertSame(65, $runningTotal);

        // Jetzt kauft der Kunde einen 15,00 EUR Geschenkgutschein -> Meisterschluessel aktiv, Level 6 sofort offen!
        $qualifyingVoucherOrder = OrderOrder::create([
            'order_number' => 'ORD-VOUCHER-1500',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 1500,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 1500,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Test'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Test'],
        ]);
        OrderOrderItem::create([
            'order_id' => $qualifyingVoucherOrder->id,
            'product_id' => $voucherProduct->id,
            'product_name' => 'Geschenkgutschein',
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
            'configuration' => ['is_gift_voucher' => true, 'amount_cents' => 1500],
        ]);

        $this->assertTrue(app(LavaParkourService::class)->hasMasterKey($customer));
        $stateWithKey = app(LavaParkourService::class)->resolveProgressState($customer);
        $this->assertSame(6, $stateWithKey['highest_unlocked_level']);
        $this->assertSame([2, 3, 4, 5, 6], $stateWithKey['unlocked_portals']);

        // Level 6 bis 10 Erstabschluss: 20 + 25 + 30 + 35 + 50 = 160 Funken
        foreach ([6 => 20, 7 => 25, 8 => 30, 9 => 35, 10 => 50] as $lvl => $expectedSparks) {
            $claim = $this->performRelativeRunAndClaim($customer, $lvl);
            $runningTotal += $expectedSparks;
            $claim->assertOk()
                ->assertJsonPath('level', $lvl)
                ->assertJsonPath('is_first_clear', true)
                ->assertJsonPath('sparks_awarded', $expectedSparks)
                ->assertJsonPath('master_key_unlocked', true)
                ->assertJsonPath('daily_sparks_cap', 30);
        }
        $this->assertSame(225, $runningTotal);

        // Tag 3: Alle 10 Level sind erstabgeschlossen -> Wiederholung deckelt bei exakt 30 Funken/Tag!
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0, 'Europe/Berlin'));
        $expectedRepeatSequence = [
            10 => 6,
            9  => 5,
            8  => 5,
            7  => 5,
            6  => 4,
            5  => 4,
            4  => 1,
            3  => 0,
        ];

        foreach ($expectedRepeatSequence as $lvl => $expectedAward) {
            $rep = $this->performRelativeRunAndClaim($customer, $lvl);
            $runningTotal += $expectedAward;
            $rep->assertOk()
                ->assertJsonPath('is_first_clear', false)
                ->assertJsonPath('already_claimed_today', false)
                ->assertJsonPath('claimed_today', true)
                ->assertJsonPath('chest_locked_today', true)
                ->assertJsonPath('sparks_awarded', $expectedAward)
                ->assertJsonPath('daily_sparks_cap', 30);
        }
        $this->assertSame(255, $runningTotal);

        $secondAttemptOnCapFullChest = $this->performRelativeRunAndClaim($customer, 3);
        $secondAttemptOnCapFullChest->assertOk()
            ->assertJsonPath('already_claimed_today', true)
            ->assertJsonPath('claimed_today', true)
            ->assertJsonPath('chest_locked_today', true)
            ->assertJsonPath('sparks_awarded', 0);

        // Widerruf der einzigen 15-EUR-Bestellung entzieht den Meisterschluessel sofort wieder!
        OrderRevocation::create([
            'name' => 'Max Test',
            'email' => $customer->email,
            'order_number' => 'ORD-VOUCHER-1500',
            'status' => 'processed',
        ]);

        $this->assertFalse(app(LavaParkourService::class)->hasMasterKey($customer));
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 6])
            ->assertStatus(403)
            ->assertJsonPath('code', 'MASTER_KEY_REQUIRED');
    }

    public function test_double_claim_same_day_returns_already_claimed_today_and_next_berlin_day_awards_daily_repeat(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 17, 23, 35, 0, 'Europe/Berlin'));
        $customer = $this->makeActiveCustomer(2, 10, 10, 1);

        $firstClaim = $this->performValidRunAndClaim($customer);
        $firstClaim->assertOk()
            ->assertJsonPath('is_first_clear', true)
            ->assertJsonPath('already_claimed_today', false)
            ->assertJsonPath('sparks_awarded', 5)
            ->assertJsonPath('funken_balance', 15);

        $secondStart = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start');
        $secondStart->assertOk()->assertJsonPath('claimed_today', true);

        $secondClaim = $this->performValidRunAndClaim($customer);
        $secondClaim->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sparks_awarded', 0)
            ->assertJsonPath('is_first_clear', false)
            ->assertJsonPath('already_claimed_today', true)
            ->assertJsonPath('chest_locked_today', true)
            ->assertJsonPath('funken_balance', 15);

        Carbon::setTestNow(Carbon::create(2026, 11, 18, 0, 5, 0, 'Europe/Berlin'));

        $nextDayStart = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start');
        $nextDayStart->assertOk()->assertJsonPath('claimed_today', false);

        $nextDayClaim = $this->performValidRunAndClaim($customer);
        $nextDayClaim->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_first_clear', false)
            ->assertJsonPath('already_claimed_today', false)
            ->assertJsonPath('sparks_awarded', 2)
            ->assertJsonPath('funken_balance', 17)
            ->assertJsonPath('daily_sparks_earned', 2);
    }

    public function test_direct_claim_without_checkpoints_and_skipping_checkpoint_1_are_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(3, 0, 0, 1);

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => Str::random(40),
                'claim_token' => hash('sha256', 'forged'),
                'rel_x' => 55.5,
                'rel_y' => 3.2,
                'rel_z' => 3.5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(4)->seconds();

        // Skipping CP1 directly to CP2 -> 422
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'rel_x' => 37.5,
                'rel_y' => 1.95,
                'rel_z' => -8.3,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('current_stage', 0);

        // Forged claim token before stage 2 -> destroys run
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => hash('sha256', 'forged-skip-token'),
                'rel_x' => 55.5,
                'rel_y' => 3.2,
                'rel_z' => 3.5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('retryable', false);

        $this->assertSame(0, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_speedhack_under_new_s_curve_split_and_total_minimums_is_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(5, 0, 0, 1);

        // Fall A: 1200 ms (< MIN_SPLIT_1_MS = 1600 ms) zu CP1 wird mit 422 blockiert
        $startFastCp1 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();

        $this->travel(1200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $startFastCp1->json('run_id'),
                'stage' => 1,
                'rel_x' => 18.5,
                'rel_y' => 0.85,
                'rel_z' => 7.5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('retryable', true)
            ->assertJsonPath('wait_ms', 400);

        // Fall B: Segmente exakt am Mindestsplit (1600 + 2000 + 1500 = 5100 ms), aber Gesamtlaufzeit < 5600 ms (500 ms Restwartezeit!)
        $startTotalSpeedhack = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $startTotalSpeedhack->json('run_id');

        $this->travel(1600)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'rel_x' => 18.5,
                'rel_y' => 0.85,
                'rel_z' => 7.5,
            ])
            ->assertOk();

        $this->travel(2000)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'rel_x' => 37.5,
                'rel_y' => 1.95,
                'rel_z' => -8.3,
            ])
            ->assertOk();

        $this->travel(1500)->milliseconds(); // Summe = 5100 ms < 5600 ms -> wait_ms = 500
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $cp2->json('claim_token'),
                'rel_x' => 55.5,
                'rel_y' => 3.2,
                'rel_z' => 3.5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('retryable', true)
            ->assertJsonPath('wait_ms', 500);

        $this->assertSame(0, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_wrong_coordinates_token_replay_and_manipulated_amounts_are_guarded(): void
    {
        $customer = $this->makeActiveCustomer(7, 10, 10, 1);

        // 1. Alte geradeaus Koordinaten (z = 0 statt z = 7.5 auf der S-Kurve) werden bei CP1 abgelehnt (dist = 7.56m > 6.5m)
        $s1 = $this->actingAs($customer, 'customer')->postJson('/funkenwerk-welt/lava-parkour/start')->assertOk();
        $this->travel(2000)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $s1->json('run_id'),
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 1.0,
                'rel_z' => 0.0,
            ])
            ->assertStatus(422);

        // 2. Ehrlicher Lauf mit manipulierten amount/sparks Feldern schreibt exakt +5 gut, Replay schlaegt mit 422 fehl
        $s2 = $this->actingAs($customer, 'customer')->postJson('/funkenwerk-welt/lava-parkour/start')->assertOk();
        $runId = (string) $s2->json('run_id');

        $this->travel(2000)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'rel_x' => 18.5,
                'rel_y' => 1.5,
                'rel_z' => 7.5,
            ])
            ->assertOk();

        $this->travel(2200)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'rel_x' => 37.5,
                'rel_y' => 2.2,
                'rel_z' => -8.3,
            ])
            ->assertOk();
        $claimToken = (string) $cp2->json('claim_token');

        $this->travel(2000)->milliseconds();
        $claimPayload = [
            'run_id' => $runId,
            'claim_token' => $claimToken,
            'rel_x' => 55.5,
            'rel_y' => 3.2,
            'rel_z' => 3.5,
            'amount' => 99999,
            'sparks' => 99999,
            'rewarded_sparks' => 99999,
        ];

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $claimPayload)
            ->assertOk()
            ->assertJsonPath('sparks_awarded', 5)
            ->assertJsonPath('funken_balance', 15);

        $this->travel(1)->days();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $claimPayload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(15, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_guest_no_order_inactive_magic_revoked_order_and_unverified_email_across_all_lava_endpoints(): void
    {
        Auth::guard('customer')->logout();
        Auth::forgetGuards();

        foreach (['start', 'commit', 'leave', 'hit', 'checkpoint', 'claim'] as $ep) {
            $this->postJson('/funkenwerk-welt/lava-parkour/' . $ep, ['run_id' => Str::random(40)])
                ->assertStatus(403)
                ->assertJsonPath('guest', true)
                ->assertJsonPath('code', 'login_required');
        }

        // Unbestaetigte E-Mail -> 403 email_verification_required
        $unverified = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Unverifiziert',
            'last_name' => 'Test',
            'email' => 'unverified.lava@example.de',
            'email_verified_at' => null,
            'password' => bcrypt('Secret!123'),
        ]);
        $this->actingAs($unverified, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertStatus(403)
            ->assertJsonPath('code', 'email_verification_required');

        // Eingeloggter Kunde OHNE bezahlte Bestellung -> 403 order_required
        $noOrderCustomer = $this->makeActiveCustomer(91, 10, 10, 1, false);
        $this->actingAs($noOrderCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 1])
            ->assertStatus(403)
            ->assertJsonPath('code', 'order_required');

        // Eingeloggter Kunde MIT bezahlter Bestellung, aber OHNE 'Magie aktivieren' -> 403 magic_activation_required
        $inactiveCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Inaktiv',
            'last_name' => 'Testkunde',
            'email' => 'inaktiv.parkour@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('Secret!123'),
        ]);
        CustomerGamification::create([
            'customer_id' => $inactiveCustomer->id,
            'is_active' => false,
            'level' => 1,
        ]);
        OrderOrder::create([
            'order_number' => 'ORD-MAGIC-REQ-92',
            'customer_id' => $inactiveCustomer->id,
            'email' => $inactiveCustomer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => ['first_name' => 'Inaktiv', 'last_name' => 'Testkunde'],
            'shipping_address' => ['first_name' => 'Inaktiv', 'last_name' => 'Testkunde'],
        ]);

        $this->actingAs($inactiveCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 1])
            ->assertStatus(403)
            ->assertJsonPath('code', 'magic_activation_required');

        // Sobald Bestellung widerrufen wird, faellt auch ein aktiver Spieler sofort auf 403 order_required zurueck!
        CustomerGamification::where('customer_id', $inactiveCustomer->id)->update(['is_active' => true]);
        $this->performRelativeRunAndClaim($inactiveCustomer, 1)->assertOk()->assertJsonPath('sparks_awarded', 5);

        OrderRevocation::create([
            'name' => 'Inaktiv Testkunde',
            'email' => $inactiveCustomer->email,
            'order_number' => 'ORD-MAGIC-REQ-92',
            'status' => 'completed',
        ]);
        $this->actingAs($inactiveCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 1])
            ->assertStatus(403)
            ->assertJsonPath('code', 'order_required');
    }

    public function test_inline_login_enforces_csrf_lockout_2fa_password_change_cart_merge_and_showroom_payload(): void
    {
        Auth::guard('customer')->logout();
        Auth::forgetGuards();

        // 1. Echter Web/CSRF-Middleware-Check auf /funkenwerk-welt/inline-login & /funkenwerk-welt/activate-magic
        $inlineLoginRoute = app('router')->getRoutes()->getByName('gamification.funkenwerk.inline-login');
        $activateMagicRoute = app('router')->getRoutes()->getByName('gamification.funkenwerk.activate-magic');
        $this->assertNotNull($inlineLoginRoute);
        $this->assertNotNull($activateMagicRoute);
        $this->assertContains('web', $inlineLoginRoute->gatherMiddleware());
        $this->assertContains('web', $activateMagicRoute->gatherMiddleware());

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Seelen-Anhänger',
            'slug' => 'seelen-anhaenger-inline',
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'is_personalizable' => false,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Miriam',
            'last_name' => 'Stern',
            'email' => 'miriam.inline@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('Secret!123'),
        ]);
        OrderOrder::create([
            'order_number' => 'ORD-INLINE-01',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => ['first_name' => 'Miriam', 'last_name' => 'Stern'],
            'shipping_address' => ['first_name' => 'Miriam', 'last_name' => 'Stern'],
        ]);

        // 2. 3 Fehlversuche -> Lockout beim 4. Versuch (HTTP 429 locked_out)
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/funkenwerk-welt/inline-login', [
                'email' => $customer->email,
                'password' => 'WrongPass!' . $i,
            ])->assertStatus(422)->assertJsonPath('status', 'invalid_credentials');
        }

        $this->postJson('/funkenwerk-welt/inline-login', [
            'email' => $customer->email,
            'password' => 'Secret!123',
        ])->assertStatus(429)->assertJsonPath('status', 'locked_out');

        $this->travel(11)->seconds();

        // 3. IP-Blockade wird geprueft
        SystemBlockedIp::create([
            'ip_address' => '203.0.113.55',
            'reason' => 'Test Block',
            'blocked_until' => now()->addHour(),
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->postJson('/funkenwerk-welt/inline-login', [
                'email' => $customer->email,
                'password' => 'Secret!123',
            ])
            ->assertStatus(403)
            ->assertJsonPath('status', 'ip_blocked');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        // 4. 2FA aktiv -> ohne Code 200 two_factor_required, mit echtem TOTP-Code 200 authenticated + Gast-Warenkorb-Merge
        $google2fa = new Google2FA();
        $secret2fa = $google2fa->generateSecretKey();
        $customer->profile->update([
            'two_factor_is_active' => true,
            'two_factor_secret' => $secret2fa,
        ]);

        $step1 = $this->postJson('/funkenwerk-welt/inline-login', [
            'email' => $customer->email,
            'password' => 'Secret!123',
        ]);
        $step1->assertOk()
            ->assertJsonPath('status', 'two_factor_required')
            ->assertJsonPath('message', 'Bitte 6-stelligen Sicherheitscode eingeben.');
        $this->assertGuest('customer');

        $guestSessionId = session()->getId();
        $sessionCookieName = (string) config('session.cookie');
        $rawEncryptedSessionCookie = null;
        foreach ($step1->headers->getCookies() as $ck) {
            if ($ck->getName() === $sessionCookieName) {
                $rawEncryptedSessionCookie = $ck->getValue();
                break;
            }
        }
        $this->assertNotNull($rawEncryptedSessionCookie);

        $guestCart = Cart::create(['session_id' => $guestSessionId]);
        CartItem::create([
            'cart_id' => $guestCart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 2990,
            'total_price' => 5980,
        ]);

        $stepBad2fa = $this->withCredentials()
            ->withUnencryptedCookie($sessionCookieName, (string) $rawEncryptedSessionCookie)
            ->postJson('/funkenwerk-welt/inline-login', [
                'email' => $customer->email,
                'password' => 'Secret!123',
                'two_factor_code' => '000000',
            ]);
        $stepBad2fa->assertStatus(422)->assertJsonPath('status', 'invalid_two_factor_code');

        $validOtp = $google2fa->getCurrentOtp($secret2fa);
        $step2 = $this->withCredentials()
            ->withUnencryptedCookie($sessionCookieName, (string) $rawEncryptedSessionCookie)
            ->postJson('/funkenwerk-welt/inline-login', [
                'email' => $customer->email,
                'password' => 'Secret!123',
                'two_factor_code' => $validOtp,
            ]);
        $step2->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('sanctum_access.is_authenticated', true)
            ->assertJsonPath('sanctum_access.has_completed_order', true)
            ->assertJsonPath('sanctum_access.gamification_active', false)
            ->assertJsonPath('sanctum_access.sanctum_block_reason', 'magic_activation_required')
            ->assertJsonPath('player_funki.is_guest', false)
            ->assertJsonPath('lava_parkour.hearts', 6);
        $this->assertNotEmpty($step2->json('csrf_token'));
        $this->assertAuthenticatedAs($customer, 'customer');

        $mergedCart = Cart::where('customer_id', $customer->id)->first();
        $this->assertNotNull($mergedCart);
        $this->assertSame(2, (int) $mergedCart->items()->where('product_id', $product->id)->sum('quantity'));

        // 5. needs_password_change -> bleibt eingeloggt, liefert aber 403 password_change_required + redirect_url
        Auth::guard('customer')->logout();
        $forcePwCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Temp',
            'last_name' => 'Pass',
            'email' => 'forcepw.inline@example.de',
            'email_verified_at' => now(),
            'needs_password_change' => true,
            'password' => bcrypt('TempSecret!123'),
        ]);
        $pwResp = $this->postJson('/funkenwerk-welt/inline-login', [
            'email' => $forcePwCustomer->email,
            'password' => 'TempSecret!123',
        ]);
        $pwResp->assertStatus(403)
            ->assertJsonPath('status', 'password_change_required');
        $this->assertNotEmpty($pwResp->json('redirect_url'));
        $this->assertAuthenticatedAs($forcePwCustomer, 'customer');
    }

    public function test_inline_activate_magic_enforces_terms_verified_order_and_unlocks_sanctums_immediately(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Klara',
            'last_name' => 'Funken',
            'email' => 'klara.magic@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('Secret!123'),
        ]);

        // 1. Ohne accept_terms -> 422 terms_required
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/activate-magic', ['accept_terms' => false])
            ->assertStatus(422)
            ->assertJsonPath('status', 'terms_required');

        // 2. Ohne abgeschlossene Bestellung -> 403 order_required (DB bleibt is_active = false)
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/activate-magic', ['accept_terms' => true])
            ->assertStatus(403)
            ->assertJsonPath('status', 'order_required');
        $this->assertFalse((bool) CustomerGamification::where('customer_id', $customer->id)->value('is_active'));

        // 3. Mit bezahlter Bestellung -> 200 magic_activated + 6 Herzen + can_enter_sanctums = true
        OrderOrder::create([
            'order_number' => 'ORD-INLINE-MAGIC-1',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 3990,
            'tax_amount' => 637,
            'shipping_price' => 0,
            'total_price' => 3990,
            'billing_address' => ['first_name' => 'Klara', 'last_name' => 'Funken'],
            'shipping_address' => ['first_name' => 'Klara', 'last_name' => 'Funken'],
        ]);

        $actResp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/activate-magic', [
                'accept_terms' => true,
                'ranking_opt_in' => true,
            ]);

        $actResp->assertOk()
            ->assertJsonPath('status', 'magic_activated')
            ->assertJsonPath('sanctum_access.can_enter_sanctums', true)
            ->assertJsonPath('lava_parkour.can_enter_sanctums', true)
            ->assertJsonPath('lava_parkour.hearts', 6)
            ->assertJsonPath('lava_parkour.max_hearts', 6)
            ->assertJsonPath('lava_parkour.unlocked_level', 1);

        $gam = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertTrue((bool) $gam->is_active);
        $this->assertTrue((bool) $gam->ranking_opt_in);
        $this->assertSame(6, (int) $gam->magma_hearts);
        $this->assertSame(1, (int) $gam->magma_unlocked_level);
    }

    public function test_six_hearts_escrow_f5_penalty_safe_leave_debounce_shield_and_demotion_without_first_clear_refarming(): void
    {
        $customer = $this->makeActiveCustomer(77, 0, 0, 1);

        // Stufe 1 und Stufe 2 ehrlich abschliessen -> magma_unlocked_level = 3, Erstabschluss-Funken = 5 + 8 = 13
        $this->performRelativeRunAndClaim($customer, 1)->assertOk()->assertJsonPath('sparks_awarded', 5);
        $this->performRelativeRunAndClaim($customer, 2)->assertOk()->assertJsonPath('sparks_awarded', 8);

        $gamAfter2 = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(3, (int) $gamAfter2->magma_unlocked_level);
        $this->assertSame(13, (int) $gamAfter2->funken_balance);
        $this->assertSame(45, LobbyInstanceService::calculateHeroRankingMetrics($gamAfter2)['magma_prestige_points']);

        // 1. Un-committed Lauf auf Stufe 3 starten und sofort auf dem Startbalkon verlassen -> KEIN Herzverlust (6/6)!
        $sSafe = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 3])
            ->assertOk()
            ->assertJsonPath('hearts', 6);
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/leave', ['run_id' => $sSafe->json('run_id')])
            ->assertOk()
            ->assertJsonPath('status', 'left_safely')
            ->assertJsonPath('hearts', 6);

        // 2. Lauf starten, Startbalkon verlassen (/commit), Versuch mitten im Raum ohne Balkon-Rueckkehr zu leaven -> 409!
        $sEscrow = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 3])
            ->assertOk();
        $escrowRunId = (string) $sEscrow->json('run_id');

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/commit', ['run_id' => $escrowRunId])
            ->assertOk()
            ->assertJsonPath('committed', true);

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/leave', ['run_id' => $escrowRunId, 'rel_x' => 22.0])
            ->assertStatus(409)
            ->assertJsonPath('status', 'run_still_committed');

        // Spieler drueckt F5 -> naechster /start zieht sofort 1 Escrow-Herz ab (6 -> 5)!
        $sAfterF5 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 3])
            ->assertOk()
            ->assertJsonPath('escrow_penalty_applied', true)
            ->assertJsonPath('hearts', 5)
            ->assertJsonPath('level', 3);

        $runId = (string) $sAfterF5->json('run_id');
        $nonce1 = (string) $sAfterF5->json('life_nonce');

        // 3. Aegis-Schild rettet beim 1. Einsatz (hearts bleibt 5), aber gefaelschter 2. Schild-Einsatz nach 2s (< 13.5s Cooldown) zieht 1 Herz ab (5 -> 4)!
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', [
                'run_id' => $runId,
                'level' => 3,
                'used_shield' => true,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'shield_saved')
            ->assertJsonPath('hearts', 5)
            ->assertJsonPath('shield_charges_remaining', 2);

        $this->travel(2000)->milliseconds();
        $forgedShieldHit = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', [
                'run_id' => $runId,
                'level' => 3,
                'used_shield' => true,
            ]);
        $forgedShieldHit->assertOk()
            ->assertJsonPath('status', 'heart_lost')
            ->assertJsonPath('hearts', 4)
            ->assertJsonPath('demoted', false);

        $nonce2 = (string) $forgedShieldHit->json('life_nonce');
        $this->assertNotSame($nonce1, $nonce2);

        // Alter life_nonce aus dem vorherigen Versuch wird bei Checkpoint sofort abgelehnt!
        $this->travel(2000)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'life_nonce' => $nonce1,
                'stage' => 1,
                'rel_x' => 19.7,
                'rel_y' => 1.13,
                'rel_z' => 8.6,
            ])
            ->assertStatus(422);

        // 4. 1.0s Server-Debounce: Doppel-Paket innerhalb 200ms zieht NUR 1 Herz ab (4 -> 3)!
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', ['run_id' => $runId, 'level' => 3])
            ->assertOk()
            ->assertJsonPath('hearts', 3);

        $this->travel(200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', ['run_id' => $runId, 'level' => 3])
            ->assertOk()
            ->assertJsonPath('debounced', true)
            ->assertJsonPath('hearts', 3);

        // 5. Restliche 3 Herzen verlieren (3 -> 2 -> 1 -> 0 = Level-Rueckstufung von Stufe 3 auf Stufe 2 + frische 6/6 Herzen!)
        $this->travel(1200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', ['run_id' => $runId, 'level' => 3])
            ->assertOk()
            ->assertJsonPath('hearts', 2);

        $this->travel(1200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', ['run_id' => $runId, 'level' => 3])
            ->assertOk()
            ->assertJsonPath('hearts', 1);

        $this->travel(1200)->milliseconds();
        $demote1 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', ['run_id' => $runId, 'level' => 3]);
        $demote1->assertOk()
            ->assertJsonPath('status', 'level_demoted')
            ->assertJsonPath('demoted', true)
            ->assertJsonPath('hearts', 6)
            ->assertJsonPath('max_hearts', 6)
            ->assertJsonPath('unlocked_level', 2)
            ->assertJsonPath('demoted_to_level', 2);

        $demotedRunId = (string) $demote1->json('run_id');
        $this->assertNotSame($runId, $demotedRunId);

        // Weiterer Abstieg auf Stufe 2 (6 Herzen verlieren -> unlocked_level faellt auf 1 -> magma_prestige_points zaehlt nur noch Raum 1 = 15 statt 45!)
        for ($h = 5; $h >= 1; $h--) {
            $this->travel(1200)->milliseconds();
            $this->actingAs($customer, 'customer')
                ->postJson('/funkenwerk-welt/lava-parkour/hit', ['run_id' => $demotedRunId, 'level' => 2])
                ->assertOk()
                ->assertJsonPath('hearts', $h);
        }
        $this->travel(1200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/hit', ['run_id' => $demotedRunId, 'level' => 2])
            ->assertOk()
            ->assertJsonPath('status', 'level_demoted')
            ->assertJsonPath('unlocked_level', 1)
            ->assertJsonPath('demoted_to_level', 1);

        $gamDemotedTo1 = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(1, (int) $gamDemotedTo1->magma_unlocked_level);
        $this->assertSame([1, 2], $gamDemotedTo1->titles_progress['lava_cleared_levels']);
        $this->assertSame(15, LobbyInstanceService::calculateHeroRankingMetrics($gamDemotedTo1)['magma_prestige_points']);

        // 6. Anti-Farming-Beweis am naechsten Kalendertag: Erneuter Abschluss von Stufe 1 & 2 zahlt NIEMALS nochmal FIRST_CLEAR_REWARDS (5/8) aus, sondern nur Daily-Repeat (2/2)!
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 12, 0, 0, 'Europe/Berlin'));
        $reClear1 = $this->performRelativeRunAndClaim($customer, 1);
        $reClear1->assertOk()
            ->assertJsonPath('is_first_clear', false)
            ->assertJsonPath('sparks_awarded', 2)
            ->assertJsonPath('unlocked_level', 2);

        $reClear2 = $this->performRelativeRunAndClaim($customer, 2);
        $reClear2->assertOk()
            ->assertJsonPath('is_first_clear', false)
            ->assertJsonPath('sparks_awarded', 2)
            ->assertJsonPath('unlocked_level', 3)
            ->assertJsonPath('magma_prestige_points', 45);
    }

    public function test_showroom_data_exposes_s_curve_anchors_for_all_10_levels_and_admin_guard_is_consistent(): void
    {
        Auth::guard('customer')->logout();
        $guestShowroom = $this->getJson('/shop/showroom-data')->assertOk();
        $levels = $guestShowroom->json('lava_parkour.levels');
        $this->assertCount(10, $levels);

        for ($lvl = 1; $lvl <= 10; $lvl++) {
            $row = $levels[$lvl - 1];
            $dir = ($lvl % 2 === 1) ? 1.0 : -1.0;
            $ampZ = round(7.5 + ($lvl - 1) * 0.55, 2);

            $this->assertEquals(round(18.5 + ($lvl - 1) * 0.60, 2), $row['rel_cp1']['x']);
            $this->assertEquals(round(0.85 + ($lvl - 1) * 0.14, 2), $row['rel_cp1']['y']);
            $this->assertEquals(round($dir * $ampZ, 2), $row['rel_cp1']['z']);

            $this->assertEquals(round(37.5 + ($lvl - 1) * 1.15, 2), $row['rel_cp2']['x']);
            $this->assertEquals(round(1.95 + ($lvl - 1) * 0.22, 2), $row['rel_cp2']['y']);
            $this->assertEquals(round(-$dir * ($ampZ + 0.80), 2), $row['rel_cp2']['z']);

            $this->assertEquals(round(55.5 + ($lvl - 1) * 1.65, 2), $row['rel_chest']['x']);
            $this->assertEquals(round(3.20 + ($lvl - 1) * 0.28, 2), $row['rel_chest']['y']);
            $this->assertEquals(round($dir * (2.40 + ($lvl % 3) * 1.10), 2), $row['rel_chest']['z']);
        }

        // Konsistenter Admin-Guard zwischen CustomerLobbyController und LavaParkourController
        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina.lava.admin@mein-seelenfunke.de',
            'password' => bcrypt('AdminPass!99'),
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'lava'])
            ->assertOk()
            ->assertJsonPath('zone', 'lava');

        $this->actingAs($admin, 'admin')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 8])
            ->assertOk()
            ->assertJsonPath('admin_preview', true)
            ->assertJsonPath('level', 8)
            ->assertJsonPath('hearts', 6);
    }

    private function performValidRunAndClaim(Customer $customer): \Illuminate\Testing\TestResponse
    {
        return $this->performRelativeRunAndClaim($customer, 1);
    }

    private function performRelativeRunAndClaim(Customer $customer, int $level): \Illuminate\Testing\TestResponse
    {
        $def = app(LavaParkourService::class)->getLevelDefinition($level);

        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => $level])
            ->assertOk();
        $runId = (string) $start->json('run_id');
        $lifeNonce = (string) $start->json('life_nonce');

        $this->travel(1800)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'life_nonce' => $lifeNonce,
                'level' => $level,
                'stage' => 1,
                'rel_x' => $def['rel_cp1']['x'],
                'rel_y' => $def['rel_cp1']['y'],
                'rel_z' => $def['rel_cp1']['z'],
            ])
            ->assertOk();

        $this->travel(2200)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'life_nonce' => $lifeNonce,
                'level' => $level,
                'stage' => 2,
                'rel_x' => $def['rel_cp2']['x'],
                'rel_y' => $def['rel_cp2']['y'],
                'rel_z' => $def['rel_cp2']['z'],
            ])
            ->assertOk();

        $minRemainingMs = max(1600, LavaParkourService::minTotalRunMs($level) - 3900);
        $this->travel($minRemainingMs)->milliseconds();

        return $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'life_nonce' => $lifeNonce,
                'level' => $level,
                'claim_token' => $cp2->json('claim_token'),
                'rel_x' => $def['rel_chest']['x'],
                'rel_y' => $def['rel_chest']['y'],
                'rel_z' => $def['rel_chest']['z'],
            ]);
    }

    private function makeActiveCustomer(
        int $index,
        int $balance,
        int $totalEarned,
        int $level,
        bool $withPaidEntryOrder = true
    ): Customer {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Parkour' . $index,
            'last_name' => 'Spieler' . $index,
            'email' => "lava.parkour.{$index}@example.de",
            'email_verified_at' => now(),
            'password' => bcrypt('StrongPass!' . $index),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'level' => $level,
            'funken_balance' => $balance,
            'funken_total_earned' => $totalEarned,
        ]);

        if ($withPaidEntryOrder) {
            OrderOrder::create([
                'order_number' => sprintf('ORD-ENTRY-%04d', $index),
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal_price' => 1000,
                'tax_amount' => 0,
                'shipping_price' => 0,
                'total_price' => 1000,
                'billing_address' => ['first_name' => $customer->first_name, 'last_name' => $customer->last_name],
                'shipping_address' => ['first_name' => $customer->first_name, 'last_name' => $customer->last_name],
            ]);
        }

        return $customer;
    }
}
