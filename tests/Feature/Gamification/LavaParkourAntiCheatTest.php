<?php

namespace Tests\Feature\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
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
        Carbon::setTestNow(Carbon::create(2026, 11, 17, 14, 0, 0, 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_honest_lava_parkour_run_awards_5_sparks_updates_level_and_writes_audit_log(): void
    {
        $customer = $this->makeActiveCustomer(1, 20, 148, 1);

        $startResp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start');

        $startResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('claimed_today', false)
            ->assertJsonPath('daily_reward', 5);

        $runId = (string) $startResp->json('run_id');
        $this->assertSame(40, strlen($runId));

        // Nach 3.0s (>= 2.8s): Checkpoint 1
        $this->travel(3000)->milliseconds();
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

        // Weitere 3.1s (>= 2.8s): Checkpoint 2
        $this->travel(3100)->milliseconds();
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

        // Weitere 2.9s (>= 2.4s seit CP2, Gesamtlaufzeit = 9.0s >= 8.5s): Schatztruhe oeffnen
        $this->travel(2900)->milliseconds();
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
            ->assertJsonPath('rewarded_sparks', 5)
            ->assertJsonPath('new_balance', 25)
            ->assertJsonPath('new_level', 2)
            ->assertJsonPath('claimed_today', true)
            ->assertJsonPath('message', '+5 Funken erhalten!');

        $gam = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(25, (int) $gam->funken_balance);
        $this->assertSame(153, (int) $gam->funken_total_earned);
        $this->assertSame(2, (int) $gam->level);
        $this->assertSame('2026-11-17', $gam->titles_progress['_lava_chest_last_claim_date'] ?? null);
        $this->assertSame(1, (int) ($gam->titles_progress['_lava_chest_total_claims'] ?? 0));

        $log = CustomerGamificationLog::where('customer_id', $customer->id)
            ->where('event_type', 'lava_parkour_chest')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('parkour', $log->category);
        $this->assertSame(5, (int) $log->sparks_delta);
        $this->assertSame(25, (int) $log->balance_after);
        $this->assertSame(9000, (int) ($log->metadata['run_duration_ms'] ?? 0));
        $this->assertSame('2026-11-17', $log->metadata['date'] ?? null);
    }

    public function test_double_claim_same_day_returns_409_and_next_berlin_day_allows_new_claim(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 17, 23, 35, 0, 'Europe/Berlin'));
        $customer = $this->makeActiveCustomer(2, 10, 10, 1);

        $firstClaim = $this->performValidRunAndClaim($customer);
        $firstClaim->assertOk()->assertJsonPath('new_balance', 15);

        // Zweiter vollstaendiger Lauf am selben Kalendertag (23:36 Europe/Berlin) -> HTTP 409
        $secondStart = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start');
        $secondStart->assertOk()->assertJsonPath('claimed_today', true);

        $secondClaim = $this->performValidRunAndClaim($customer);
        $secondClaim->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('already_claimed', true)
            ->assertJsonPath('claimed_today', true);

        $this->assertSame(15, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));

        // Naechster Tag nach Berliner Mitternacht (2026-11-18 00:05 Europe/Berlin, obwohl UTC noch 23:05 am Vortag ist!)
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 0, 5, 0, 'Europe/Berlin'));

        $nextDayStart = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start');
        $nextDayStart->assertOk()->assertJsonPath('claimed_today', false);

        $nextDayClaim = $this->performValidRunAndClaim($customer);
        $nextDayClaim->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('rewarded_sparks', 5)
            ->assertJsonPath('new_balance', 20);

        $gam = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('2026-11-18', $gam->titles_progress['_lava_chest_last_claim_date'] ?? null);
        $this->assertSame(2, (int) ($gam->titles_progress['_lava_chest_total_claims'] ?? 0));
    }

    public function test_direct_claim_without_checkpoints_is_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(3, 0, 0, 1);

        // 1. Komplett ohne Start-Session
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

        // 2. Mit echter Start-Session, aber ohne Checkpoints (DevTools-Teleport direkt zur Truhe)
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

    public function test_skipping_checkpoint_1_is_rejected_with_422_and_invalidates_run(): void
    {
        $customer = $this->makeActiveCustomer(4, 0, 0, 1);

        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(4)->seconds();

        // Versuch, direkt Stage 2 zu melden ohne Stage 1
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.0,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Der kompromittierte Lauf wurde im Cache geloescht -> auch Stage 1 geht mit dieser run_id nicht mehr
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

    public function test_speedhack_under_8_point_5_seconds_is_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(5, 0, 0, 1);

        // Fall A: Teleport zu CP1 nach 1.2s (< 2.8s)
        $startFastCp1 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $this->travel(1200)->milliseconds();

        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $startFastCp1->json('run_id'),
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.0,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Fall B: Segmente knapp >= Mindestsplit (2.8s + 2.8s + 2.4s = 8.0s), aber Gesamtlaufzeit < 8.5s!
        $startTotalSpeedhack = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $startTotalSpeedhack->json('run_id');

        $this->travel(2800)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.0,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(2800)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.0,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(2400)->milliseconds(); // Summe = 8000ms (< 8500ms!)
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $cp2->json('claim_token'),
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_wrong_coordinates_at_checkpoint_or_chest_are_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(6, 0, 0, 1);

        // Falsche Position bei CP1
        $s1 = $this->actingAs($customer, 'customer')->postJson('/funkenwerk-welt/lava-parkour/start')->assertOk();
        $this->travel(3500)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $s1->json('run_id'),
                'stage' => 1,
                'x' => 0.0,
                'y' => 0.0,
                'z' => 8.5,
            ])
            ->assertStatus(422);

        // Ehrliche Checkpoints 1 & 2, aber falsche Truhen-Koordinaten beim Claim (z. B. y in der Lava oder falsches x)
        $s2 = $this->actingAs($customer, 'customer')->postJson('/funkenwerk-welt/lava-parkour/start')->assertOk();
        $runId = (string) $s2->json('run_id');

        $this->travel(3200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.0,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3200)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.2,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3000)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $cp2->json('claim_token'),
                'x' => 40.0,
                'y' => 0.5,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_token_replay_attack_is_rejected_with_422(): void
    {
        $customer = $this->makeActiveCustomer(7, 10, 10, 1);

        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(3200)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.0,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3200)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.2,
                'z' => -2.0,
            ])
            ->assertOk();
        $claimToken = (string) $cp2->json('claim_token');

        $this->travel(3000)->milliseconds();
        $payload = [
            'run_id' => $runId,
            'claim_token' => $claimToken,
            'x' => 66.5,
            'y' => 2.8,
            'z' => -2.0,
        ];

        // Erster Claim: Erfolg (+5)
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $payload)
            ->assertOk()
            ->assertJsonPath('rewarded_sparks', 5);

        // Sofortiger Replay desselben Tokens (selbst am naechsten Tag!) -> 422, weil Single-Use-Run geloescht ist
        $this->travel(1)->days();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(15, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_manipulated_amount_parameter_is_strictly_ignored_and_credits_only_5_sparks(): void
    {
        $customer = $this->makeActiveCustomer(8, 0, 0, 1);

        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(3100)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.0,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3100)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.0,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3000)->milliseconds();
        $claimResp = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $cp2->json('claim_token'),
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
                'amount' => 99999,
                'sparks' => 99999,
                'rewarded_sparks' => 99999,
            ]);

        $claimResp->assertOk()
            ->assertJsonPath('rewarded_sparks', 5)
            ->assertJsonPath('new_balance', 5);

        $this->assertSame(5, (int) CustomerGamification::where('customer_id', $customer->id)->value('funken_balance'));
    }

    public function test_guest_receives_401_and_inactive_gamification_profile_receives_403(): void
    {
        Auth::guard('customer')->logout();

        $this->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('guest', true);

        $this->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
            'run_id' => Str::random(40),
            'stage' => 1,
            'x' => 31.5,
            'y' => 1.0,
            'z' => -2.0,
        ])
            ->assertStatus(401)
            ->assertJsonPath('guest', true);

        $this->postJson('/funkenwerk-welt/lava-parkour/claim', [
            'run_id' => Str::random(40),
            'claim_token' => Str::random(64),
            'x' => 66.5,
            'y' => 2.8,
            'z' => -2.0,
        ])
            ->assertStatus(401)
            ->assertJsonPath('guest', true);

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

        $this->actingAs($inactiveCustomer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_showroom_data_and_lobby_sync_expose_funki_model_urls_lava_parkour_and_combo_state(): void
    {
        // 1. Gast auf /shop/showroom-data erhaelt Level 2 Funki-GLB + alle 10 level_models + lava_parkour
        Auth::guard('customer')->logout();
        $guestShowroom = $this->getJson('/shop/showroom-data');
        $guestShowroom->assertOk()
            ->assertJsonPath('player_funki.is_guest', true)
            ->assertJsonPath('player_funki.level', 2)
            ->assertJsonPath('player_funki.model_url', '/shop/customer/gamification/models/funki_lvl_2_basic.glb')
            ->assertJsonPath('lava_parkour.enabled', true)
            ->assertJsonPath('lava_parkour.daily_reward', 5)
            ->assertJsonPath('lava_parkour.is_authenticated', false)
            ->assertJsonPath('lava_parkour.claimed_today', false)
            ->assertJsonPath('lava_parkour.chest_pos.x', 66.5);

        $levelModels = $guestShowroom->json('player_funki.level_models');
        $this->assertIsArray($levelModels);
        $this->assertCount(10, $levelModels);
        $this->assertSame('/shop/customer/gamification/models/funki_lvl_1_rags.glb', $levelModels[1] ?? $levelModels['1'] ?? null);
        $this->assertSame('/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb', $levelModels[10] ?? $levelModels['10'] ?? null);

        // 2. Eingeloggter Kunde (Level 7) + Lobby-Sync mit y, zone='lava', combo_seq & combo_stage
        $hero7 = $this->makeActiveCustomer(10, 50, 2200, 7);
        $peer = $this->makeActiveCustomer(11, 20, 400, 3);

        $authShowroom = $this->actingAs($hero7, 'customer')->getJson('/shop/showroom-data');
        $authShowroom->assertOk()
            ->assertJsonPath('player_funki.is_guest', false)
            ->assertJsonPath('player_funki.level', 7)
            ->assertJsonPath('player_funki.model_url', '/shop/customer/gamification/models/funki_lvl_7_artisan.glb')
            ->assertJsonPath('lava_parkour.is_authenticated', true)
            ->assertJsonPath('lava_parkour.claimed_today', false);

        // Solo-Zone 'lava' liefert ungestörten Parkour-Modus (keine störenden Peers auf Sprungplatten)
        $this->actingAs($hero7, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', [
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
                'zone' => 'lava',
                'combo_seq' => 12,
                'combo_stage' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('solo_mode', true)
            ->assertJsonCount(0, 'peers');

        // Multiplayer-Zone 'heroes' synchronisiert y, combo_seq & combo_stage live zwischen Mitspielern
        $this->actingAs($hero7, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', [
                'x' => 4.5,
                'y' => 1.8,
                'z' => -48.0,
                'zone' => 'heroes',
                'combo_seq' => 12,
                'combo_stage' => 3,
            ])
            ->assertOk();

        $peerSync = $this->actingAs($peer, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', [
                'x' => -3.5,
                'y' => 1.2,
                'z' => -46.0,
                'zone' => 'heroes',
            ]);

        $peerSync->assertOk();
        $peers = $peerSync->json('peers');
        $this->assertCount(1, $peers);
        $this->assertEquals(4.5, $peers[0]['x']);
        $this->assertEquals(1.8, $peers[0]['y']);
        $this->assertSame('heroes', $peers[0]['zone']);
        $this->assertSame(12, $peers[0]['combo_seq']);
        $this->assertSame(3, $peers[0]['combo_stage']);
    }

    private function performValidRunAndClaim(Customer $customer): \Illuminate\Testing\TestResponse
    {
        $start = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk();
        $runId = (string) $start->json('run_id');

        $this->travel(3100)->milliseconds();
        $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3100)->milliseconds();
        $cp2 = $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runId,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.2,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(2900)->milliseconds();

        return $this->actingAs($customer, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runId,
                'claim_token' => $cp2->json('claim_token'),
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
            ]);
    }

    private function makeActiveCustomer(int $idx, int $balance, int $lifetimeSparks, int $level): Customer
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Spieler' . $idx,
            'last_name' => 'Geheim' . $idx,
            'email' => sprintf('lava.spieler.%d@example.de', $idx),
            'password' => bcrypt('SecretPass!' . $idx),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => sprintf('LavaHeld%02d', $idx),
            'level' => $level,
            'funken_balance' => $balance,
            'funken_total_earned' => $lifetimeSparks,
        ]);

        return $customer;
    }
}
