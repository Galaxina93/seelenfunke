<?php

namespace Tests\Feature\Security;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
use App\Models\System\SystemLog;
use App\Models\System\SystemSetting;
use App\Services\Gamification\LobbyInstanceService;
use App\Services\Shop\ShowroomMultiplayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class LobbyAntiManipulationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::create(2026, 11, 17, 15, 0, 0, 'Europe/Berlin'));

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'showroom_multiplayer_enabled'], ['value' => '1']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_forged_identity_level_sparks_and_weapon_are_never_trusted_across_lobby_and_showroom(): void
    {
        $attacker = $this->makeCustomer(1, 'KleinerFunki', 1, 0, 0);
        $observer = $this->makeCustomer(2, 'WachsameEule', 3, 50, 350);

        // 1a. Explizite Funken-/Admin-Injection auf /lobby/join & /lobby/sync wird mit 422 geblockt
        foreach (['/funkenwerk-welt/lobby/join', '/funkenwerk-welt/lobby/sync'] as $lobbyEndpoint) {
            $this->actingAs($attacker, 'customer')
                ->postJson($lobbyEndpoint, [
                    'zone' => 'heroes',
                    'x' => 0.0,
                    'z' => -25.0,
                    'level' => 10,
                    'sparks' => 999999,
                    'display_name' => 'HACKER',
                    'is_admin' => true,
                ])
                ->assertStatus(422)
                ->assertJsonPath('ok', false);
        }

        // 1b. Gefaelschte Profil-Felder (level, display_name, player_name, ship_class, weapon, customer_id, peer_id)
        // werden auf /lobby/join & /lobby/sync zu 100 % ignoriert -> Server liefert echtes DB-Level 1
        $spoofLobbyPayload = [
            'x' => 1.5,
            'y' => 0.0,
            'z' => -25.0,
            'rot_y' => 1.0,
            'zone' => 'heroes',
            'level' => 10,
            'display_name' => 'HACKER',
            'player_name' => 'HACKER',
            'ship_class' => 'KLASSE S',
            'customer_id' => (string) $observer->id,
            'peer_id' => 'deadbeef0000',
            'weapon' => [
                'key' => 'hacked_god_weapon',
                'name' => '<script>alert(1)</script>',
                'rank_class' => 'S',
                'class_label' => 'KLASSE S',
                'effect_type' => 'cosmos_blade',
            ],
        ];

        $joinResp = $this->actingAs($attacker, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', $spoofLobbyPayload);

        $joinResp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('player.level', 1)
            ->assertJsonPath('player.display_name', 'KleinerFunki')
            ->assertJsonPath('player.ship_class', 'KLASSE C');

        $this->actingAs($attacker, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', $spoofLobbyPayload)
            ->assertOk();

        $observerLobby = $this->actingAs($observer, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['x' => 0.0, 'z' => -24.0, 'zone' => 'heroes']);

        $observerLobby->assertOk();
        $lobbyPeers = $observerLobby->json('peers');
        $this->assertCount(1, $lobbyPeers);
        $this->assertSame(1, $lobbyPeers[0]['level']);
        $this->assertSame('KleinerFunki', $lobbyPeers[0]['display_name']);
        $this->assertSame('KLASSE C', $lobbyPeers[0]['ship_class']);
        $this->assertNotSame('deadbeef0000', $lobbyPeers[0]['peer_id']);

        // 2. /shop/showroom-sync ignoriert level: 10, sparks: 999999, display_name: 'HACKER', weapon & is_admin zu 100 %
        $showroomAttacker = $this->actingAs($attacker, 'customer')
            ->postJson('/shop/showroom-sync', array_merge($spoofLobbyPayload, [
                'z' => 6.0,
                'sparks' => 999999,
                'is_admin' => true,
                'reward' => 99999,
            ]));

        $showroomAttacker->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('level', 1)
            ->assertJsonPath('display_name', 'KleinerFunki');

        $showroomObserver = $this->actingAs($observer, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => -1.0, 'y' => 0.0, 'z' => 6.0, 'soloMode' => false]);

        $showroomObserver->assertOk();
        $showroomPeers = $showroomObserver->json('peers');
        $this->assertCount(1, $showroomPeers);
        $this->assertSame(1, $showroomPeers[0]['level']);
        $this->assertSame('KleinerFunki', $showroomPeers[0]['display_name']);
        $this->assertSame('KLASSE C', $showroomPeers[0]['weapon']['class_label']);
        $this->assertNotSame('<script>alert(1)</script>', $showroomPeers[0]['weapon']['name']);

        // 3. DB bleibt unangetastet, Security-Log hat den Manipulationsversuch festgehalten
        $dbGam = CustomerGamification::where('customer_id', $attacker->id)->firstOrFail();
        $this->assertSame(1, (int) $dbGam->level);
        $this->assertSame(0, (int) $dbGam->funken_balance);

        $this->assertTrue(
            SystemLog::where('type', 'security')
                ->where('action_id', 'security:lobby_tamper_blocked')
                ->exists()
        );
    }

    public function test_explicit_console_cheat_commands_are_rejected_with_422_and_throttled_in_security_log(): void
    {
        $attacker = $this->makeCustomer(3, 'CheatTester', 2, 10, 180);

        $this->actingAs($attacker, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['god_mode' => true, 'x' => 0, 'z' => -20, 'zone' => 'heroes'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->actingAs($attacker, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['noclip' => 1, 'set_level' => 10, 'zone' => 'heroes'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->actingAs($attacker, 'customer')
            ->postJson('/shop/showroom-sync', ['give_sparks' => 50000, 'admin_override' => true])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // 15 identische Cheat-Requests in Serie duerfen dank Drosselung keinen DB-Log-Flood erzeugen
        $logsBeforeLoop = SystemLog::where('action_id', 'security:lobby_tamper_blocked')->count();
        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($attacker, 'customer')
                ->postJson('/funkenwerk-welt/lava-parkour/start', ['cheat' => 'instant_win'])
                ->assertStatus(422);
        }
        $logsAfterLoop = SystemLog::where('action_id', 'security:lobby_tamper_blocked')->count();

        $this->assertSame($logsBeforeLoop + 1, $logsAfterLoop);
        $this->assertSame(10, (int) CustomerGamification::where('customer_id', $attacker->id)->value('funken_balance'));
    }

    public function test_nan_extreme_coordinates_speedhack_and_unauthorized_teleport_are_clamped_or_blocked(): void
    {
        $runner = $this->makeCustomer(4, 'SprintPilot', 5, 40, 1100);
        $watcher = $this->makeCustomer(5, 'RadarFunki', 4, 30, 700);

        // Start in der Halle der Helden bei (0.0, 0.0, -24.0)
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', [
                'x' => 0.0,
                'y' => 0.0,
                'z' => -24.0,
                'speed' => 0.0,
                'zone' => 'heroes',
            ])
            ->assertOk();

        $this->actingAs($watcher, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', [
                'x' => 1.0,
                'y' => 0.0,
                'z' => -24.0,
                'zone' => 'heroes',
            ])
            ->assertOk();

        // 1. NaN / Infinity auf /lobby/sync wird sofort mit 422 abgewiesen, Position bleibt unveraendert (0, 0, -24)
        $this->travel(200)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', [
                'x' => 'NaN',
                'y' => 'Infinity',
                'z' => '-INF',
                'speed' => 'NaN',
                'zone' => 'heroes',
            ])
            ->assertStatus(422);

        // 2. Konsolen-Teleport (x: 999999, z: -999999) + Speedhack (speed: 999) in 200ms -> hart geclampt!
        $this->travel(200)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', [
                'x' => 999999,
                'y' => 99999,
                'z' => -999999,
                'speed' => 999,
                'zone' => 'heroes',
            ])
            ->assertOk();

        $checkClamp = $this->actingAs($watcher, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => 1.0, 'z' => -24.0, 'zone' => 'heroes']);
        $peerAfterTeleport = $checkClamp->json('peers.0');

        $this->assertLessThanOrEqual(LobbyInstanceService::MAX_SPEED, (float) $peerAfterTeleport['speed']);
        $stepDistance = hypot((float) $peerAfterTeleport['x'] - 0.0, (float) $peerAfterTeleport['z'] - (-24.0));
        $this->assertLessThanOrEqual(LobbyInstanceService::MIN_SYNC_TELEPORT_STEP + 0.05, $stepDistance);
        $this->assertLessThanOrEqual(6.5, (float) $peerAfterTeleport['y']);

        // 3. Zonenwechsel in Solo-Zonen ('gallery', 'lava') funktioniert reibungslos und gibt Slot frei
        $this->travel(300)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', [
                'x' => 15.8,
                'y' => 0.0,
                'z' => -2.0,
                'speed' => 5.2,
                'zone' => 'lava',
            ])
            ->assertOk()
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('zone', 'lava');

        // 4. /shop/showroom-sync verwirft NaN sicher und clampt Speedhack (<= 9.5) + Konsolen-Teleport hart
        $this->actingAs($runner, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 0.0, 'y' => 0.0, 'z' => 8.0, 'speed' => 3.0])
            ->assertOk();

        $this->travel(150)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 'NaN', 'y' => 'INF', 'z' => 'NaN', 'speed' => 'Infinity'])
            ->assertOk();

        $this->travel(150)->milliseconds();
        $this->actingAs($runner, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 999999, 'y' => 999, 'z' => -999999, 'speed' => 999])
            ->assertOk();

        $showroomWatch = $this->actingAs($watcher, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 0.0, 'y' => 0.0, 'z' => 7.0]);
        $srPeer = $showroomWatch->json('peers.0');
        $this->assertEquals(ShowroomMultiplayerService::MAX_SPEED, $srPeer['speed']);
        $this->assertGreaterThan(-10.0, (float) $srPeer['z']);
    }

    public function test_lava_parkour_console_exploits_teleports_forged_tokens_and_replays_credit_zero_sparks(): void
    {
        $cheater = $this->makeCustomer(6, 'LavaCheater', 1, 0, 0);

        // 1. Direkter /claim ohne gestarteten Lauf
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => Str::random(40),
                'claim_token' => hash('sha256', 'console-forged-token'),
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // 2. Instant-Teleport in 10ms zu CP1 -> 422 & Lauf sofort vernichtet
        $runFast = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk()
            ->json('run_id');

        $this->travel(10)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runFast,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
            ])
            ->assertStatus(422);

        $this->travel(4000)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runFast,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
            ])
            ->assertStatus(422);

        // 3. Ueberspringen von CP1 direkt zu CP2 -> 422
        $runSkip = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk()
            ->json('run_id');

        $this->travel(3500)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runSkip,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.1,
                'z' => -2.0,
            ])
            ->assertStatus(422);

        // 4. Ehrliche Checkpoints 1 & 2, aber Instant-Teleport in 10ms zur Truhe -> 422 & 0 Funken
        $runInstantChest = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk()
            ->json('run_id');

        $this->travel(3100)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runInstantChest,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3100)->milliseconds();
        $cp2Fast = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runInstantChest,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.1,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(10)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runInstantChest,
                'claim_token' => $cp2Fast->json('claim_token'),
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
            ])
            ->assertStatus(422);

        // 5. Ehrliche Checkpoints 1 & 2, richtige Zeit, aber um 1 Zeichen manipuliertes Token -> 422 & 0 Funken
        $runForgedTok = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk()
            ->json('run_id');

        $this->travel(3100)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runForgedTok,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3100)->milliseconds();
        $cp2Valid = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $runForgedTok,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.1,
                'z' => -2.0,
            ])
            ->assertOk();

        $realToken = (string) $cp2Valid->json('claim_token');
        $tamperedToken = ($realToken[0] === 'a' ? 'b' : 'a') . substr($realToken, 1);

        $this->travel(3000)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', [
                'run_id' => $runForgedTok,
                'claim_token' => $tamperedToken,
                'x' => 66.5,
                'y' => 2.8,
                'z' => -2.0,
            ])
            ->assertStatus(422);

        // Nach allen 5 Exploit-Versuchen steht das Konto exakt bei 0 Funken und 0 Log-Gutschriften!
        $this->assertSame(0, (int) CustomerGamification::where('customer_id', $cheater->id)->value('funken_balance'));
        $this->assertSame(
            0,
            CustomerGamificationLog::where('customer_id', $cheater->id)->where('event_type', 'lava_parkour_chest')->count()
        );

        // 6. Ein ehrlicher Lauf danach (2 Min. spaeter am selben Kalendertag) + Replay-Angriff desselben Tokens -> exakt 1x +5 Funken!
        $this->travel(2)->minutes();
        $honestRun = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/start')
            ->assertOk()
            ->json('run_id');

        $this->travel(3200)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $honestRun,
                'stage' => 1,
                'x' => 31.5,
                'y' => 1.2,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3200)->milliseconds();
        $honestCp2 = $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
                'run_id' => $honestRun,
                'stage' => 2,
                'x' => 51.0,
                'y' => 2.1,
                'z' => -2.0,
            ])
            ->assertOk();

        $this->travel(3000)->milliseconds();
        $claimBody = [
            'run_id' => $honestRun,
            'claim_token' => $honestCp2->json('claim_token'),
            'x' => 66.5,
            'y' => 2.8,
            'z' => -2.0,
        ];

        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $claimBody)
            ->assertOk()
            ->assertJsonPath('rewarded_sparks', 5);

        // Sofortiger Replay-Versuch (z. B. Promise.all Zweitrequest) -> 422 Single-Use-Tombstone
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/lava-parkour/claim', $claimBody)
            ->assertStatus(422);

        $this->assertSame(5, (int) CustomerGamification::where('customer_id', $cheater->id)->value('funken_balance'));
        $this->assertSame(
            1,
            CustomerGamificationLog::where('customer_id', $cheater->id)->where('event_type', 'lava_parkour_chest')->count()
        );
    }

    public function test_showroom_chat_and_emotes_block_html_xss_and_console_flood_loops(): void
    {
        $sender = $this->makeCustomer(7, 'SpamTester', 3, 20, 400);
        $listener = $this->makeCustomer(8, 'StillerGast', 4, 30, 650);

        $xssPayloads = [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(document.cookie)>Hallo',
            '&#60;svg onload=alert(1)&#62;',
            'Klick javascript:alert(1)',
        ];

        foreach ($xssPayloads as $xss) {
            $this->actingAs($sender, 'customer')
                ->postJson('/shop/showroom-chat', ['message' => $xss])
                ->assertStatus(422)
                ->assertJsonPath('code', 'blocked_html');
        }

        // Ungewhitelistetes / XSS-Emote wird verworfen, erlaubtes Emote 'wave' geht durch und wird danach gedrosselt
        $this->actingAs($sender, 'customer')
            ->postJson('/shop/showroom-sync', [
                'x' => 0.0,
                'y' => 0.0,
                'z' => 6.0,
                'emote' => '<script>evil()</script>',
            ])
            ->assertOk();

        $checkBadEmote = $this->actingAs($listener, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 1.0, 'y' => 0.0, 'z' => 6.0]);
        $this->assertNull($checkBadEmote->json('peers.0.emote'));
        $this->assertCount(0, $checkBadEmote->json('messages'));

        $this->actingAs($sender, 'customer')
            ->postJson('/shop/showroom-sync', [
                'x' => 0.0,
                'y' => 0.0,
                'z' => 6.0,
                'emote' => 'wave',
            ])
            ->assertOk();

        $checkGoodEmote = $this->actingAs($listener, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 1.0, 'y' => 0.0, 'z' => 6.0]);
        $this->assertSame('wave', $checkGoodEmote->json('peers.0.emote'));

        // Sofortiges zweites Emote innerhalb des Cooldowns wird unterdrueckt
        $this->actingAs($sender, 'customer')
            ->postJson('/shop/showroom-sync', [
                'x' => 0.0,
                'y' => 0.0,
                'z' => 6.0,
                'emote' => 'cheer',
            ])
            ->assertOk();

        $checkThrottledEmote = $this->actingAs($listener, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 1.0, 'y' => 0.0, 'z' => 6.0]);
        $this->assertNull($checkThrottledEmote->json('peers.0.emote'));

        // Konsolen-Flooding-Schleife auf /shop/showroom-chat greift mit HTTP 429 durch
        $flooder = $this->makeCustomer(9, 'LoopBot', 2, 0, 160);
        $got429 = false;
        for ($i = 0; $i < 12; $i++) {
            $resp = $this->actingAs($flooder, 'customer')
                ->postJson('/shop/showroom-chat', ['message' => 'Hallo Loop ' . $i]);
            if ($resp->getStatusCode() === 429) {
                $got429 = true;
                break;
            }
        }
        $this->assertTrue($got429, 'Erwartetes HTTP 429 Rate-Limiting bei Konsolen-Chat-Schleife trat nicht ein.');
    }

    public function test_unauthenticated_guests_cannot_claim_sparks_manipulate_instances_or_flood_chat(): void
    {
        Auth::guard('customer')->logout();
        Auth::guard('admin')->logout();
        Auth::forgetGuards();

        // Lava-Parkour strikt 401
        $this->postJson('/funkenwerk-welt/lava-parkour/start')->assertStatus(401);
        $this->postJson('/funkenwerk-welt/lava-parkour/checkpoint', [
            'run_id' => Str::random(40),
            'stage' => 1,
            'x' => 31.5,
            'y' => 1.2,
            'z' => -2.0,
        ])->assertStatus(401);
        $this->postJson('/funkenwerk-welt/lava-parkour/claim', [
            'run_id' => Str::random(40),
            'claim_token' => Str::random(64),
            'x' => 66.5,
            'y' => 2.8,
            'z' => -2.0,
        ])->assertStatus(401);

        // Lobby: Gast kann weder per Join/Sync eine Instanz belegen noch per Switch wechseln
        $this->postJson('/funkenwerk-welt/lobby/join', [
            'preferred_instance' => 1,
            'level' => 10,
            'display_name' => 'GHOST_HACKER',
            'x' => 5.0,
            'z' => -25.0,
            'zone' => 'heroes',
        ])
            ->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('instance_id', null)
            ->assertJsonPath('player', null);

        $this->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 1])
            ->assertStatus(401);

        // Ein echter Kunde betritt Instanz 1 in 'heroes' und sieht 0 Geister-Peers des Gastes
        $realPlayer = $this->makeCustomer(10, 'EchterSpieler', 3, 10, 350);
        $realJoin = $this->actingAs($realPlayer, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['x' => 0.0, 'z' => -25.0, 'zone' => 'heroes']);
        $realJoin->assertOk()->assertJsonPath('instance_id', 1);
        $this->assertCount(0, $realJoin->json('peers'));

        Auth::guard('customer')->logout();
        Auth::forgetGuards();

        // Showroom Sync, Chat & Report strikt 403 fuer Gaeste
        $this->postJson('/shop/showroom-sync', ['x' => 0, 'y' => 0, 'z' => 5])->assertStatus(403);
        $this->postJson('/shop/showroom-chat', ['message' => 'Gast-Spam'])->assertStatus(403);
        $this->postJson('/shop/showroom-report', ['target_player_id' => 'p_1234567890abcdef'])->assertStatus(403);
    }

    private function makeCustomer(
        int $idx,
        string $playerName,
        int $level,
        int $balance,
        int $lifetimeSparks
    ): Customer {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Vorname' . $idx,
            'last_name' => 'GeheimNachname' . $idx,
            'email' => sprintf('sec.lobby.%d@example.de', $idx),
            'password' => bcrypt('StrongPass!' . $idx),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => $playerName,
            'level' => $level,
            'funken_balance' => $balance,
            'funken_total_earned' => $lifetimeSparks,
        ]);

        return $customer;
    }
}
