<?php

namespace Tests\Feature\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
use App\Services\Gamification\MiniLabyrinthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class MiniLabyrinthGagAndSecurityTest extends TestCase
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

    public function test_product_lobby_lava_and_labyrinth_are_100_percent_solo_while_heroes_unites_players(): void
    {
        // 18 Besucher verteilen sich auf die Solo-Zonen gallery, lava und labyrinth -> belegen 0 Heldenhallen-Slots
        $soloZones = ['gallery', 'lava', 'labyrinth'];
        for ($i = 1; $i <= 18; $i++) {
            $soloVisitor = $this->makeActiveCustomer($i, sprintf('SoloGast%02d', $i), 3, 100);
            $zone = $soloZones[$i % 3];

            $resp = $this->actingAs($soloVisitor, 'customer')
                ->postJson('/funkenwerk-welt/lobby/join', [
                    'x' => -20.0,
                    'z' => 0.0,
                    'zone' => $zone,
                ]);

            $resp->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonPath('solo_mode', true)
                ->assertJsonPath('zone', $zone)
                ->assertJsonPath('instance_id', null)
                ->assertJsonPath('peers', [])
                ->assertJsonPath('instances', []);
        }

        // Zwei Heldenhallen-Besucher treffen sich ungestoert in Instanz #1 (Count = 2 statt 20)
        $heroA = $this->makeActiveCustomer(51, 'HeldenPilotA', 8, 3500, true);
        $heroB = $this->makeActiveCustomer(52, 'HeldenPilotB', 9, 4500, true);

        $joinA = $this->actingAs($heroA, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['x' => 0.0, 'z' => -30.0, 'zone' => 'heroes']);
        $joinA->assertOk()
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 1)
            ->assertJsonPath('peers', []);

        $joinB = $this->actingAs($heroB, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['x' => 2.5, 'z' => -32.0, 'zone' => 'heroes']);
        $joinB->assertOk()
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 1);

        $this->assertCount(1, $joinB->json('peers'));
        $this->assertSame('HeldenPilotA', $joinB->json('peers.0.display_name'));
        $this->assertSame(2, collect($joinB->json('instances'))->firstWhere('id', 1)['count']);

        // Wechselt HeroA ins Mini-Labyrinth ('labyrinth'), ist er sofort Solo und aus der Heldenhalle ausgebucht
        $toLabyrinth = $this->actingAs($heroA, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => -16.0, 'z' => 0.0, 'zone' => 'labyrinth']);
        $toLabyrinth->assertOk()
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('zone', 'labyrinth')
            ->assertJsonPath('instance_id', null)
            ->assertJsonPath('peers', []);

        $syncB = $this->actingAs($heroB, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => 2.5, 'z' => -32.0, 'zone' => 'heroes']);
        $syncB->assertOk();
        $this->assertCount(0, $syncB->json('peers'));
        $this->assertSame(1, collect($syncB->json('instances'))->firstWhere('id', 1)['count']);
    }

    public function test_console_payloads_identity_spoofing_and_teleport_coords_in_lobby_are_blocked_or_clamped(): void
    {
        $attacker = $this->makeActiveCustomer(60, 'KleinerFunki', 1, 10, true);
        $observer = $this->makeActiveCustomer(61, 'WachsameEule', 5, 1200, true);

        // 1. Verbotene Cheat- und Command-Payloads -> sofort HTTP 422 forbidden_payload
        $forbiddenPayloads = [
            ['zone' => 'heroes', 'command' => 'give_all'],
            ['zone' => 'heroes', 'cmd' => 'whoami'],
            ['zone' => 'heroes', 'exec' => 'ls'],
            ['zone' => 'heroes', 'eval' => '1+1'],
            ['zone' => 'heroes', 'script' => '<script>alert(1)</script>'],
            ['zone' => 'heroes', 'admin' => true],
            ['zone' => 'heroes', 'is_admin' => 1],
            ['zone' => 'heroes', 'god_mode' => true],
            ['zone' => 'heroes', 'give_sparks' => 99999],
            ['zone' => 'heroes', 'sparks' => 5000],
            ['zone' => 'heroes', 'amount' => 5000],
            ['zone' => 'heroes', 'teleport_all' => true],
            ['zone' => 'heroes', 'set_level' => 10],
            ['zone' => 'heroes', 'nested' => ['give_sparks' => 100]],
            ['zone' => 'heroes', 'x' => '1e500'],
            ['zone' => 'heroes', 'x' => ['array_injection']],
        ];

        foreach ($forbiddenPayloads as $badPayload) {
            $this->actingAs($attacker, 'customer')
                ->postJson('/funkenwerk-welt/lobby/join', $badPayload)
                ->assertStatus(422)
                ->assertJsonPath('ok', false)
                ->assertJsonPath('error', 'forbidden_payload');

            $this->actingAs($attacker, 'customer')
                ->postJson('/funkenwerk-welt/lobby/sync', $badPayload)
                ->assertStatus(422)
                ->assertJsonPath('ok', false)
                ->assertJsonPath('error', 'forbidden_payload');
        }

        $this->actingAs($attacker, 'customer')
            ->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 1, 'is_admin' => true])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'forbidden_payload');

        // 2. Gefaelschte Identitaets-Felder + Teleport-Koordinaten werden zu 100 % ignoriert bzw. geclampt
        $spoofJoin = $this->actingAs($attacker, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', [
                'zone' => 'heroes',
                'level' => 10,
                'display_name' => 'FAKE_ADMIN_GOD',
                'peer_id' => '000000000000',
                'ship_class' => 'KLASSE S',
                'level_title' => 'Gefaelschter Titel',
                'x' => -999.0,
                'y' => -50.0,
                'z' => 250.0,
                'emote' => '<script>xss</script>',
                'ability_slot' => 99,
            ]);

        $spoofJoin->assertOk()
            ->assertJsonPath('player.level', 1)
            ->assertJsonPath('player.display_name', 'KleinerFunki')
            ->assertJsonPath('player.ship_class', 'KLASSE C');
        $this->assertNotSame('000000000000', $spoofJoin->json('player.peer_id'));

        $obsJoin = $this->actingAs($observer, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'x' => 0.0, 'z' => -25.0]);
        $obsJoin->assertOk();

        $attackerPeer = $obsJoin->json('peers.0');
        $this->assertNotNull($attackerPeer);
        $this->assertSame(1, $attackerPeer['level']);
        $this->assertSame('KleinerFunki', $attackerPeer['display_name']);
        $this->assertSame('KLASSE C', $attackerPeer['ship_class']);
        $this->assertEquals(-32.0, $attackerPeer['x']);
        $this->assertEquals(-0.5, $attackerPeer['y']);
        $this->assertEquals(-14.0, $attackerPeer['z']);
        $this->assertSame('', $attackerPeer['emote']);
        $this->assertSame(4, $attackerPeer['ability_slot']);
    }

    public function test_mini_labyrinth_regular_run_awards_3_sparks_and_rotates_solvable_5x5_variants(): void
    {
        /** @var MiniLabyrinthService $service */
        $service = app(MiniLabyrinthService::class);

        // Alle 3 Tages-Varianten (0..2) rotieren an 3 aufeinanderfolgenden Tagen und sind 5x5 loesbar
        $variants = [
            $service->dailyVariant('2026-11-17'),
            $service->dailyVariant('2026-11-18'),
            $service->dailyVariant('2026-11-19'),
        ];
        sort($variants);
        $this->assertSame([0, 1, 2], $variants);

        for ($v = 0; $v <= 2; $v++) {
            $grid = $service->getVariantLayout($v);
            $this->assertCount(5, $grid);
            foreach ($grid as $row) {
                $this->assertCount(5, $row);
            }
            $this->assertTrue($this->hasGridPath($grid, [2, 0], [2, 2]), "Variante {$v}: Eingang (2,0) -> CP1 (2,2) muss erreichbar sein.");
            $this->assertTrue($this->hasGridPath($grid, [2, 2], [2, 4]), "Variante {$v}: CP1 (2,2) -> Ziel (2,4) muss erreichbar sein.");
        }

        $runner = $this->makeActiveCustomer(70, 'LabyrinthFuchs', 2, 10, true);

        // 1. Start
        $startResp = $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start');

        $startResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('daily_reward', 3)
            ->assertJsonPath('claimed_today', false);

        $this->assertContains($startResp->json('variant'), [0, 1, 2]);
        $this->assertEquals(-22.0, $startResp->json('cp1.x'));
        $this->assertEquals(0.0, $startResp->json('cp1.z'));
        $this->assertEquals(-28.5, $startResp->json('goal.x'));
        $this->assertEquals(0.0, $startResp->json('goal.z'));
        $this->assertZeroAiDashes($startResp->getContent());

        $runId = (string) $startResp->json('run_id');
        $this->assertNotEmpty($runId);

        // 2. Nach 2s Zwischen-Gag-Punkt CP1 erreichen (>= 1.6s)
        $this->travel(2)->seconds();

        $cpResp = $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', [
                'run_id' => $runId,
                'x' => -22.0,
                'y' => 0.0,
                'z' => 0.0,
            ]);

        $cpResp->assertOk()
            ->assertJsonPath('success', true);
        $claimToken = (string) $cpResp->json('claim_token');
        $this->assertSame(64, strlen($claimToken));

        // 3. Weitere 2s bis zum Ziel-Pokal (Gesamt 4.0s >= 3.5s) -> exakt +3 Funken!
        $this->travel(2)->seconds();

        $claimResp = $this->actingAs($runner, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $runId,
                'claim_token' => $claimToken,
                'x' => -28.5,
                'y' => 0.0,
                'z' => 0.0,
            ]);

        $claimResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sparks_awarded', 3)
            ->assertJsonPath('new_balance', 13)
            ->assertJsonPath('already_claimed', false)
            ->assertJsonPath('message', '🎉 +3 Funken aus dem Mini-Labyrinth gesichert!');
        $this->assertZeroAiDashes($claimResp->getContent());

        $profile = CustomerGamification::where('customer_id', $runner->id)->firstOrFail();
        $this->assertSame(13, (int) $profile->funken_balance);
        $this->assertSame(13, (int) $profile->funken_total_earned);
        $this->assertSame('2026-11-17', $profile->titles_progress['_mini_labyrinth_last_claim_date'] ?? null);

        $log = CustomerGamificationLog::where('customer_id', $runner->id)
            ->where('event_type', 'mini_labyrinth_gag')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(3, (int) $log->sparks_delta);
        $this->assertSame(13, (int) $log->balance_after);
    }

    public function test_mini_labyrinth_blocks_guests_console_cheats_speedhacks_teleports_forged_tokens_and_double_claims(): void
    {
        // 1. Gast-Aufrufe -> HTTP 401 mit guest = true und Tages-Variante 0..2
        $guestStart = $this->postJson('/funkenwerk-welt/labyrinth/start');
        $guestStart->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('guest', true)
            ->assertJsonPath('message', 'Bitte melde dich an, um täglich 3 Bonus-Funken im Mini-Labyrinth mitzunehmen.');
        $this->assertContains($guestStart->json('variant'), [0, 1, 2]);
        $this->assertZeroAiDashes($guestStart->getContent());

        $this->postJson('/funkenwerk-welt/labyrinth/checkpoint', ['run_id' => 'abc', 'x' => -22, 'y' => 0, 'z' => 0])
            ->assertStatus(401)
            ->assertJsonPath('guest', true);

        $this->postJson('/funkenwerk-welt/labyrinth/claim', ['run_id' => 'abc', 'claim_token' => 'tok', 'x' => -28.5, 'y' => 0, 'z' => 0])
            ->assertStatus(401)
            ->assertJsonPath('guest', true);

        $cheater = $this->makeActiveCustomer(80, 'SpeedCheater', 3, 50, true);
        $otherPlayer = $this->makeActiveCustomer(81, 'AndereSpielerin', 4, 80, true);

        // 2. Konsolen-/Manipulations-Felder (sparks, amount, reward, command, admin, force) -> sofort HTTP 422
        foreach (['sparks' => 999, 'amount' => 999, 'reward' => 999, 'command' => 'claim', 'admin' => 1, 'force' => true] as $badKey => $badVal) {
            $this->actingAs($cheater, 'customer')
                ->postJson('/funkenwerk-welt/labyrinth/start', [$badKey => $badVal])
                ->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error', 'forbidden_payload');
        }

        // 3. Speedhack am Checkpoint (< 1.6s) -> 422 & Run entwertet
        $runFastCp = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->json('run_id');

        $this->travel(500)->milliseconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', [
                'run_id' => $runFastCp,
                'x' => -22.0,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // 4. Speedhack auf Gesamtlaufzeit (< 3.5s trotz gueltigem CP1 bei 1.7s + 1.65s = 3.35s) -> 422
        $runFastTotal = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->json('run_id');

        $this->travel(1700)->milliseconds();
        $tokFastTotal = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', [
                'run_id' => $runFastTotal,
                'x' => -22.0,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertOk()
            ->json('claim_token');

        $this->travel(1650)->milliseconds(); // 3350ms < MIN_TOTAL_RUN_MS (3500ms)
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $runFastTotal,
                'claim_token' => $tokFastTotal,
                'x' => -28.5,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // 5. Teleport-Koordinaten (weit weg vom Ziel oder falsche Hoehe Y) -> 422
        $runTeleport = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->json('run_id');
        $this->travel(2)->seconds();
        $tokTeleport = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', [
                'run_id' => $runTeleport,
                'x' => -22.0,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertOk()
            ->json('claim_token');
        $this->travel(2)->seconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $runTeleport,
                'claim_token' => $tokTeleport,
                'x' => -15.0, // noch am Eingang statt -28.5!
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // 6. Gefaelschter claim_token & fremder Spieler-Token -> 422
        $runForged = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->json('run_id');
        $otherRun = (string) $this->actingAs($otherPlayer, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->json('run_id');
        $this->travel(2)->seconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', ['run_id' => $runForged, 'x' => -22.0, 'y' => 0.0, 'z' => 0.0])
            ->assertOk();
        $otherToken = (string) $this->actingAs($otherPlayer, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', ['run_id' => $otherRun, 'x' => -22.0, 'y' => 0.0, 'z' => 0.0])
            ->assertOk()
            ->json('claim_token');

        $this->travel(2)->seconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $runForged,
                'claim_token' => $otherToken, // Token einer anderen Spielerin!
                'x' => -28.5,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Guthaben des Cheaters ist nach allen Angriffen unveraendert exakt 50!
        $this->assertSame(50, (int) CustomerGamification::where('customer_id', $cheater->id)->value('funken_balance'));

        // 7. Regulaerer Claim gefolgt von Doppel-Claim am selben Tag -> HTTP 409 (erst am Folgetag wieder +3)
        $validRun = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->json('run_id');
        $this->travel(2)->seconds();
        $validToken = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', ['run_id' => $validRun, 'x' => -22.0, 'y' => 0.0, 'z' => 0.0])
            ->assertOk()
            ->json('claim_token');
        $this->travel(2)->seconds();

        // Manipulationsversuch direkt beim Claim entwertet den Run sofort -> 0 Funken
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $validRun,
                'claim_token' => $validToken,
                'x' => -28.5,
                'y' => 0.0,
                'z' => 0.0,
                'sparks' => 9999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'forbidden_payload');
        $this->assertSame(50, (int) CustomerGamification::where('customer_id', $cheater->id)->value('funken_balance'));

        // Sauberer erster Tages-Claim -> 50 + 3 = 53
        $cleanRun1 = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->json('run_id');
        $this->travel(2)->seconds();
        $cleanTok1 = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', ['run_id' => $cleanRun1, 'x' => -22.0, 'y' => 0.0, 'z' => 0.0])
            ->json('claim_token');
        $this->travel(2)->seconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $cleanRun1,
                'claim_token' => $cleanTok1,
                'x' => -28.5,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertOk()
            ->assertJsonPath('sparks_awarded', 3)
            ->assertJsonPath('new_balance', 53);

        // Zweiter Lauf am selben Kalendertag -> HTTP 409 already_claimed = true, Balance bleibt 53!
        $cleanRun2 = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->assertOk()
            ->assertJsonPath('claimed_today', true)
            ->json('run_id');
        $this->travel(2)->seconds();
        $cleanTok2 = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', ['run_id' => $cleanRun2, 'x' => -22.0, 'y' => 0.0, 'z' => 0.0])
            ->json('claim_token');
        $this->travel(2)->seconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $cleanRun2,
                'claim_token' => $cleanTok2,
                'x' => -28.5,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('already_claimed', true);

        $this->assertSame(53, (int) CustomerGamification::where('customer_id', $cheater->id)->value('funken_balance'));

        // Am naechsten Kalendertag (Europe/Berlin) sind wieder +3 Funken moeglich -> 56!
        $this->travel(1)->days();
        $nextDayRun = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/start')
            ->assertOk()
            ->assertJsonPath('claimed_today', false)
            ->json('run_id');
        $this->travel(2)->seconds();
        $nextDayTok = (string) $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/checkpoint', ['run_id' => $nextDayRun, 'x' => -22.0, 'y' => 0.0, 'z' => 0.0])
            ->json('claim_token');
        $this->travel(2)->seconds();
        $this->actingAs($cheater, 'customer')
            ->postJson('/funkenwerk-welt/labyrinth/claim', [
                'run_id' => $nextDayRun,
                'claim_token' => $nextDayTok,
                'x' => -28.5,
                'y' => 0.0,
                'z' => 0.0,
            ])
            ->assertOk()
            ->assertJsonPath('sparks_awarded', 3)
            ->assertJsonPath('new_balance', 56);
    }

    private function makeActiveCustomer(
        int $index,
        string $playerName,
        int $level,
        int $sparks,
        bool $optIn = false
    ): Customer {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'TestVorname' . $index,
            'last_name' => 'GeheimNachname' . $index,
            'email' => sprintf('labyrinth.tester.%d@example.de', $index),
            'password' => bcrypt('StrongPass!' . $index),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'ranking_opt_in' => $optIn,
            'use_real_name' => false,
            'player_name' => $playerName,
            'level' => $level,
            'funken_balance' => $sparks,
            'funken_total_earned' => $sparks,
        ]);

        return $customer;
    }

    /**
     * @param array<int, array<int, int>> $grid
     * @param array{0: int, 1: int} $start
     * @param array{0: int, 1: int} $goal
     */
    private function hasGridPath(array $grid, array $start, array $goal): bool
    {
        if (($grid[$start[0]][$start[1]] ?? 1) !== 0 || ($grid[$goal[0]][$goal[1]] ?? 1) !== 0) {
            return false;
        }

        $queue = [$start];
        $visited = [$start[0] . ':' . $start[1] => true];
        $dirs = [[-1, 0], [1, 0], [0, -1], [0, 1]];

        while (!empty($queue)) {
            [$r, $c] = array_shift($queue);
            if ($r === $goal[0] && $c === $goal[1]) {
                return true;
            }

            foreach ($dirs as [$dr, $dc]) {
                $nr = $r + $dr;
                $nc = $c + $dc;
                $key = $nr . ':' . $nc;
                if ($nr >= 0 && $nr < 5 && $nc >= 0 && $nc < 5 && !isset($visited[$key]) && ($grid[$nr][$nc] ?? 1) === 0) {
                    $visited[$key] = true;
                    $queue[] = [$nr, $nc];
                }
            }
        }

        return false;
    }

    private function assertZeroAiDashes(string $payload): void
    {
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $payload);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $payload);
    }
}
