<?php

namespace Tests\Feature\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\Gamification\LavaParkourService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
            ->assertJsonPath('claimed_today', false)
            ->assertJsonPath('first_clear_reward', 5)
            ->assertJsonPath('checkpoints.cp1.rel_x', 19.5)
            ->assertJsonPath('checkpoints.cp2.rel_x', 39)
            ->assertJsonPath('chest_pos.rel_x', 54.5)
            ->assertJsonPath('chest_pos.rel_y', 3.2)
            ->assertJsonPath('chest_pos.rel_z', 0);

        $runId = (string) $startResp->json('run_id');
        $this->assertSame(40, strlen($runId));

        // Nach 2.0s (>= 1.2s): Checkpoint 1 (rueckwaertskompatible Weltkoordinaten x=31.5, z=-2.0)
        $this->travel(2000)->milliseconds();
        $cp1Resp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
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
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
            ])
            ->assertOk()
            ->assertJsonPath('stage', 1);

        // Weitere 2.0s (>= 1.4s): Checkpoint 2
        $this->travel(2000)->milliseconds();
        $cp2Resp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.1,
                'z' => -2.0,
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
                'x' => 51.0,
                'y' => 2.1,
                'z' => -2.0,
            ])
            ->assertOk()
            ->assertJsonPath('claim_token', $claimToken);

        // Weitere 2.0s (>= 1.2s seit CP2, Gesamtlaufzeit = 6.0s >= 4.2s): Schatztruhe oeffnen
        $this->travel(2000)->milliseconds();
        $claimResp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $claimToken,
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
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
            ->assertJsonPath('unlocked_portals', [2])
            ->assertJsonPath('first_cleared_levels', [1])
            ->assertJsonPath('daily_cleared_levels', [1])
            ->assertJsonPath('daily_sparks_earned', 0)
            ->assertJsonPath('daily_sparks_cap', 15);

        $gam = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(25, (int) $gam->funken_balance);
        $this->assertSame(153, (int) $gam->funken_total_earned);
        $this->assertSame(2, (int) $gam->level);
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

        // Unbezahlte Bestellung, stornierte Bestellung und 10-EUR-Geschenkgutschein (< 15 EUR) schalten KEINEN Meisterschluessel frei!
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

        OrderOrder::create([
            'order_number' => 'ORD-UNPAID-99',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_price' => 4990,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Test'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Test'],
        ]);

        $this->assertFalse(app(LavaParkourService::class)->hasMasterKey($customer));

        // Versuch, Level 2 vor Abschluss von Level 1 zu starten -> HTTP 403 LEVEL_LOCKED
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 2])
            ->assertStatus(403)
            ->assertJsonPath('code', 'LEVEL_LOCKED');

        // Level 1 bis 5 kostenlos mit raum-relativen Koordinaten (rel_x, rel_y, rel_z) absolvieren: 5 + 8 + 10 + 12 + 15 = 50 Funken
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

        // Nach Abschluss von Level 5 ohne Meisterschluessel: highest_unlocked_level bleibt 5, Portale [2,3,4,5], Level 6 liefert 403 MASTER_KEY_REQUIRED!
        $stateWithoutKey = app(LavaParkourService::class)->resolveProgressState($customer);
        $this->assertSame(5, $stateWithoutKey['highest_unlocked_level']);
        $this->assertSame([2, 3, 4, 5], $stateWithoutKey['unlocked_portals']);

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 6])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'MASTER_KEY_REQUIRED');

        // Naechster Berliner Kalendertag: Wiederholungs-Truhen Level 1..5 ergeben exakt 2 + 2 + 3 + 4 + 4 = 15 Funken (Daily Cap ohne Key = 15)
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

        // Jetzt kauft der Kunde einen 15,00 EUR Geschenkgutschein -> Meisterschluessel sofort aktiv, Level 6 sofort offen!
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

        // Tag 3: Alle 10 Level sind bereits erstabgeschlossen -> Wiederholung deckelt bei exakt 30 Funken/Tag!
        // Wir spielen Level 10 (6) + 9 (5) + 8 (5) + 7 (5) + 6 (4) + 5 (4) + 4 (4 -> gedeckelt auf +1 = 30!) + 3 (0!)
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0, 'Europe/Berlin'));
        $expectedRepeatSequence = [
            10 => 6,
            9  => 5,
            8  => 5,
            7  => 5,
            6  => 4,
            5  => 4,
            4  => 1, // 29 + 1 = 30 Cap erreicht!
            3  => 0, // Cap bereits voll -> 0 Funken
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

        // Punkt 5a Bugfix-Beweis: Obwohl bei Stufe 3 das Tages-Cap (30) bereits voll war (sparks_awarded = 0),
        // ist Truhe 3 fuer heute fest in daily_claimed_levels gesperrt und kann am selben Tag NICHT nochmals geoeffnet werden!
        $secondAttemptOnCapFullChest = $this->performRelativeRunAndClaim($customer, 3);
        $secondAttemptOnCapFullChest->assertOk()
            ->assertJsonPath('already_claimed_today', true)
            ->assertJsonPath('claimed_today', true)
            ->assertJsonPath('chest_locked_today', true)
            ->assertJsonPath('sparks_awarded', 0)
            ->assertJsonPath('message', 'Diese Truhe wurde heute bereits geöffnet. Morgen ab 00:00 Uhr ist sie wieder bereit!');

        $showroomAfterCap = $this->actingAs($customer, 'customer')->getJson('/shop/showroom-data')->assertOk();
        $this->assertSame([3, 4, 5, 6, 7, 8, 9, 10], $showroomAfterCap->json('lava_parkour.daily_claimed_levels'));
        $this->assertTrue($showroomAfterCap->json('lava_parkour.levels.2.claimed_today'));
        $this->assertFalse($showroomAfterCap->json('lava_parkour.levels.0.claimed_today'));

        // Vollstaendiger Widerruf der einzigen qualifizierenden Bestellung entzieht den Meisterschluessel wieder!
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

        // Erstabschluss Level 1 -> +5 Funken (Balance = 15)
        $firstClaim = $this->performValidRunAndClaim($customer);
        $firstClaim->assertOk()
            ->assertJsonPath('is_first_clear', true)
            ->assertJsonPath('already_claimed_today', false)
            ->assertJsonPath('sparks_awarded', 5)
            ->assertJsonPath('funken_balance', 15);

        // Zweiter Lauf in Raum 1 am selben Kalendertag -> already_claimed_today = true, sparks_awarded = 0, Balance bleibt 15!
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
            ->assertJsonPath('message', 'Diese Truhe wurde heute bereits geöffnet. Morgen ab 00:00 Uhr ist sie wieder bereit!')
            ->assertJsonPath('funken_balance', 15);

        // Naechster Tag nach Berliner Mitternacht (2026-11-18 00:05 Europe/Berlin): Taegliche Wiederholungs-Truhe Level 1 = +2 Funken
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

    public function test_direct_claim_without_checkpoints_is_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(3, 0, 0, 1);

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => Str::random(40),
                'claim_token' => hash('sha256', 'forged'),
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(10)->seconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => hash('sha256', 'forged-token'),
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_skipping_checkpoint_1_is_rejected_with_422_and_invalidates_run_on_forged_token(): void
    {
        $customer = $this->makeActiveCustomer(4, 0, 0, 1);

        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(4)->seconds();

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.0,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('current_stage', 0);

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => hash('sha256', 'forged-skip-token'),
                'x' => 66.5,
                'y' => 3.2,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('retryable', false);

        $this->travel(4)->seconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.0,
                'z' => -2.0,
            ])
            ->assertStatus(422);
    }

    public function test_speedhack_under_split_and_total_minimums_is_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(5, 0, 0, 1);

        // Fall A: 0 ms sowie 700 ms (< 1200 ms) zu CP1 werden mit 422 blockiert
        $startFastCp1 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $startFastCp1->json('run_id'),
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 1.0,
                'rel_z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('retryable', true);

        $this->travel(700)->milliseconds();

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $startFastCp1->json('run_id'),
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 1.0,
                'rel_z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Fall B: Segmente exakt am Mindestsplit (1.2s + 1.4s + 1.2s = 3.8s), aber Gesamtlaufzeit < 4.2s!
        $startTotalSpeedhack = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $startTotalSpeedhack->json('run_id');

        $this->travel(1200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 1.0,
                'rel_z' => 0.0,
            ])
            ->assertOk();

        $this->travel(1400)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'rel_x' => 39.0,
                'rel_y' => 2.0,
                'rel_z' => 0.0,
            ])
            ->assertOk();

        $this->travel(1200)->milliseconds(); // Summe = 3800ms (< 4200ms -> 400ms Restwartezeit!)
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $cp2->json('claim_token'),
                'rel_x' => 54.5,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('retryable', true)
            ->assertJsonPath('wait_ms', 400);

        $this->assertSame(0, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_wrong_coordinates_and_token_replay_and_manipulated_amounts_are_guarded(): void
    {
        $customer = $this->makeActiveCustomer(7, 10, 10, 1);

        // 1. Falsche Position bei CP1
        $s1 = $this->actingAs($customer, 'customer')->postJson('/funkenwerk-welt/lava-parkour/start')->assertOk();
        $this->travel(2000)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $s1->json('run_id'),
                'stage' => 1,
                'rel_x' => 0.0,
                'rel_y' => 0.0,
                'rel_z' => 8.5,
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
                'rel_x' => 19.5,
                'rel_y' => 1.5,
                'rel_z' => 0.0,
            ])
            ->assertOk();

        $this->travel(2000)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'rel_x' => 39.0,
                'rel_y' => 2.2,
                'rel_z' => 0.0,
            ])
            ->assertOk();
        $claimToken = (string) $cp2->json('claim_token');

        $this->travel(2000)->milliseconds();
        $claimPayload = [
            'run_id' => $runId,
            'claim_token' => $claimToken,
            'rel_x' => 54.5,
            'rel_y' => 3.2,
            'rel_z' => 0.0,
            'amount' => 99999,
            'sparks' => 99999,
            'rewarded_sparks' => 99999,
        ];

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $claimPayload)
            ->assertOk()
            ->assertJsonPath('sparks_awarded', 5)
            ->assertJsonPath('funken_balance', 15);

        // Replay desselben Tokens am naechsten Tag -> 422
        $this->travel(1)->days();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $claimPayload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(15, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_guest_no_order_inactive_magic_and_full_access_across_all_lava_parkour_endpoints(): void
    {
        // (a) Gast -> 403 'login_required' auf Start, Checkpoint und Truhe
        Auth::guard('customer')->logout();
        Auth::forgetGuards();

        $this->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('guest', true)
            ->assertJsonPath('code', 'login_required');

        $this->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
            'run_id' => Str::random(40),
            'stage' => 1,
            'rel_x' => 19.5,
            'rel_y' => 1.0,
            'rel_z' => 0.0,
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('guest', true)
            ->assertJsonPath('code', 'login_required');

        $this->postJson('/funkenwerk-welt/lava-parkour/claim', [
            'run_id' => Str::random(40),
            'claim_token' => Str::random(64),
            'rel_x' => 54.5,
            'rel_y' => 3.2,
            'rel_z' => 0.0,
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('guest', true)
            ->assertJsonPath('code', 'login_required');

        // (b) Eingeloggter Kunde OHNE bezahlte Bestellung (selbst bei is_active = true!) -> 403 'order_required' ab Raum 1
        $noOrderCustomer = $this->makeActiveCustomer(91, 10, 10, 1, false);

        foreach ([1, 3, 6, 10] as $lvl) {
            $this->actingAs($noOrderCustomer, 'customer')
                ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => $lvl])
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('code', 'order_required');
        }

        $this->actingAs($noOrderCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => Str::random(40),
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 1.0,
                'rel_z' => 0.0,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'order_required');

        $this->actingAs($noOrderCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => Str::random(40),
                'claim_token' => Str::random(64),
                'rel_x' => 54.5,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'order_required');

        // (c) Eingeloggter Kunde MIT bezahlter Bestellung, aber OHNE 'Magie aktivieren' (is_active = false) -> 403 'magic_activation_required'
        $inactiveCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Inaktiv',
            'last_name' => 'Testkunde',
            'email' => 'inaktiv.parkour@example.de',
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
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'magic_activation_required');

        $this->actingAs($inactiveCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => Str::random(40),
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 1.0,
                'rel_z' => 0.0,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'magic_activation_required');

        $this->actingAs($inactiveCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => Str::random(40),
                'claim_token' => Str::random(64),
                'rel_x' => 54.5,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'magic_activation_required');

        // (d) Kunde aktiviert Magie -> alle 3 Voraussetzungen erfuellt -> 200 OK bei Start, Checkpoint & Truhe!
        CustomerGamification::where('customer_id', $inactiveCustomer->id)->update(['is_active' => true]);

        $claimOk = $this->performRelativeRunAndClaim($inactiveCustomer, 1);
        $claimOk->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sparks_awarded', 5);
    }

    public function test_showroom_data_exposes_environment_seasons_holidays_player_glb_and_10_lava_levels_without_mini_labyrinth(): void
    {
        Auth::guard('customer')->logout();

        // 1. Herbst-Tag (17.11.2026 14:00 Europe/Berlin) als Gast
        $guestShowroom = $this->getJson('/shop/showroom-data');
        $guestShowroom->assertOk()
            ->assertJsonPath('environment.season_key', 'autumn')
            ->assertJsonPath('environment.season_label', 'Herbst')
            ->assertJsonPath('environment.holiday_key', null)
            ->assertJsonPath('environment.server_time', '14:00')
            ->assertJsonPath('player.is_authenticated', false)
            ->assertJsonPath('player.has_completed_order', false)
            ->assertJsonPath('player.gamification_active', false)
            ->assertJsonPath('player.can_enter_sanctums', false)
            ->assertJsonPath('player.sanctum_block_reason', 'login_required')
            ->assertJsonPath('player.login_url', '/login')
            ->assertJsonPath('player.dashboard_url', '/dashboard')
            ->assertJsonPath('player.level', 2)
            ->assertJsonPath('player.model_url', '/shop/customer/gamification/models/funki_lvl_2_basic.glb')
            ->assertJsonPath('player.master_key_unlocked', false)
            ->assertJsonPath('player.gift_voucher_url', '/produkt/geschenkgutschein')
            ->assertJsonPath('lava_parkour.can_enter_sanctums', false)
            ->assertJsonPath('lava_parkour.sanctum_block_reason', 'login_required')
            ->assertJsonPath('lava_parkour.master_key_unlocked', false)
            ->assertJsonPath('lava_parkour.highest_unlocked_level', 1)
            ->assertJsonPath('lava_parkour.daily_sparks_cap', 15);

        $this->assertArrayNotHasKey('mini_labyrinth', $guestShowroom->json());
        $this->assertCount(10, $guestShowroom->json('lava_parkour.levels'));
        $this->assertSame('Funkensteg', $guestShowroom->json('lava_parkour.levels.0.title'));
        $this->assertFalse($guestShowroom->json('lava_parkour.levels.4.requires_master_key'));
        $this->assertTrue($guestShowroom->json('lava_parkour.levels.5.requires_master_key'));
        $this->assertSame(50, $guestShowroom->json('lava_parkour.levels.9.first_clear_reward'));

        // 2. Weihnachten im Winter (24.12.2026) mit eingeloggtem Stammkunden (Level 7 + bezahlte Bestellung)
        Carbon::setTestNow(Carbon::create(2026, 12, 24, 18, 30, 0, 'Europe/Berlin'));
        $hero7 = $this->makeActiveCustomer(10, 320, 2200, 7);
        OrderOrder::create([
            'order_number' => 'ORD-XMAS-777',
            'customer_id' => $hero7->id,
            'email' => $hero7->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 4990,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
            'billing_address' => ['first_name' => 'Held', 'last_name' => 'Sieben'],
            'shipping_address' => ['first_name' => 'Held', 'last_name' => 'Sieben'],
        ]);

        $authShowroom = $this->actingAs($hero7, 'customer')->getJson('/shop/showroom-data');
        $authShowroom->assertOk()
            ->assertJsonPath('environment.season_key', 'winter')
            ->assertJsonPath('environment.season_label', 'Winter')
            ->assertJsonPath('environment.holiday_key', 'weihnachten')
            ->assertJsonPath('environment.holiday_label', 'Weihnachten')
            ->assertJsonPath('environment.server_time', '18:30')
            ->assertJsonPath('player.is_authenticated', true)
            ->assertJsonPath('player.has_completed_order', true)
            ->assertJsonPath('player.gamification_active', true)
            ->assertJsonPath('player.can_enter_sanctums', true)
            ->assertJsonPath('player.sanctum_block_reason', null)
            ->assertJsonPath('player.login_url', '/login')
            ->assertJsonPath('player.dashboard_url', '/dashboard')
            ->assertJsonPath('player.level', 7)
            ->assertJsonPath('player.model_url', '/shop/customer/gamification/models/funki_lvl_7_artisan.glb')
            ->assertJsonPath('player.funken_balance', 320)
            ->assertJsonPath('player.funken_total_earned', 2200)
            ->assertJsonPath('player.master_key_unlocked', true)
            ->assertJsonPath('lava_parkour.can_enter_sanctums', true)
            ->assertJsonPath('lava_parkour.sanctum_block_reason', null)
            ->assertJsonPath('lava_parkour.master_key_unlocked', true)
            ->assertJsonPath('lava_parkour.daily_sparks_cap', 30);
    }

    public function test_premature_chest_click_or_slightly_far_coord_does_not_destroy_run_and_succeeds_after_wait(): void
    {
        $runner = $this->makeActiveCustomer(13, 5, 50, 1);

        $start = $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(1300)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 2.0,
                'rel_z' => 0.0,
            ])
            ->assertOk();

        $this->travel(1500)->milliseconds(); // Summe = 2800ms
        $cp2 = $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'rel_x' => 39.0,
                'rel_y' => 2.6,
                'rel_z' => 0.0,
            ])
            ->assertOk();
        $claimToken = (string) $cp2->json('claim_token');

        // 1. Leeres claim_token -> 422 retryable
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => null,
                'rel_x' => 54.5,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('retryable', true);

        // 2. Verfruehter Klick (900ms seit CP2 < 1200ms, Gesamt 3700ms < 4200ms -> wait_ms = 500)
        $this->travel(900)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $claimToken,
                'rel_x' => 54.5,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('retryable', true)
            ->assertJsonPath('wait_ms', 500);

        // 3. Zu weit weg von der Truhe (dist = 6.7m > 6.0m) -> 422 retryable, Lauf bleibt erhalten
        $this->travel(550)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $claimToken,
                'rel_x' => 47.8,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('retryable', true);

        // 4. Direkter Folge-Klick vor der Truhe schreibt +5 Funken gut
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $claimToken,
                'rel_x' => 54.5,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sparks_awarded', 5)
            ->assertJsonPath('funken_balance', 10);
    }

    public function test_elevated_pillar_and_updraft_jump_heights_at_checkpoints_succeed_while_chest_coords_stay_exact(): void
    {
        $jumper = $this->makeActiveCustomer(15, 0, 50, 1);

        $start = $this->actingAs($jumper, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => 1])
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(1500)->milliseconds();
        $this->actingAs($jumper, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'level' => 1,
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 12.8,
                'rel_z' => 0.0,
            ])
            ->assertOk()
            ->assertJsonPath('stage', 1);

        $this->travel(1600)->milliseconds();
        $cp2 = $this->actingAs($jumper, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'level' => 1,
                'stage' => 2,
                'rel_x' => 39.0,
                'rel_y' => 13.6,
                'rel_z' => 0.0,
            ])
            ->assertOk()
            ->assertJsonPath('stage', 2);

        $this->travel(1500)->milliseconds();
        $this->actingAs($jumper, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'level' => 1,
                'claim_token' => $cp2->json('claim_token'),
                'rel_x' => LavaParkourService::REL_CHEST_X,
                'rel_y' => LavaParkourService::REL_CHEST_Y,
                'rel_z' => LavaParkourService::REL_CHEST_Z,
            ])
            ->assertOk()
            ->assertJsonPath('sparks_awarded', 5)
            ->assertJsonPath('hero_score', 70)
            ->assertJsonPath('magma_prestige_points', 15);
    }

    private function performValidRunAndClaim(Customer $customer): \Illuminate\Testing\TestResponse
    {
        return $this->performRelativeRunAndClaim($customer, 1);
    }

    private function performRelativeRunAndClaim(Customer $customer, int $level): \Illuminate\Testing\TestResponse
    {
        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start', ['level' => $level])
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(1600)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'level' => $level,
                'stage' => 1,
                'rel_x' => 19.5,
                'rel_y' => 1.5,
                'rel_z' => 0.0,
            ])
            ->assertOk();

        $this->travel(1600)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'level' => $level,
                'stage' => 2,
                'rel_x' => 39.0,
                'rel_y' => 2.2,
                'rel_z' => 0.0,
            ])
            ->assertOk();

        $this->travel(1500)->milliseconds();

        return $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'level' => $level,
                'claim_token' => $cp2->json('claim_token'),
                'rel_x' => 54.5,
                'rel_y' => 3.2,
                'rel_z' => 0.0,
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
