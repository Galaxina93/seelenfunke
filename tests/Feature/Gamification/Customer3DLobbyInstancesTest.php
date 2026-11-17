<?php

namespace Tests\Feature\Gamification;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\LobbyInstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class Customer3DLobbyInstancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_gallery_lava_and_labyrinth_are_strictly_solo_and_evict_player_from_heroes_immediately(): void
    {
        $alice = $this->makeCustomer(1, true, 'SoloAlice', 6);
        $bob = $this->makeCustomer(2, true, 'SoloBob', 7);

        // Produkt-Lobby ('gallery'), 'lava' und 'labyrinth' sind 100 % Solo
        foreach (['gallery', 'lava', 'labyrinth'] as $soloZone) {
            $respA = $this->actingAs($alice, 'customer')
                ->postJson('/funkenwerk-welt/lobby/join', ['x' => 0.0, 'z' => 0.0, 'zone' => $soloZone]);
            $respA->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonPath('authenticated', true)
                ->assertJsonPath('solo_mode', true)
                ->assertJsonPath('zone', $soloZone)
                ->assertJsonPath('instance_id', null)
                ->assertJsonPath('peers', [])
                ->assertJsonPath('instances', []);

            $respB = $this->actingAs($bob, 'customer')
                ->postJson('/funkenwerk-welt/lobby/sync', ['x' => 1.0, 'z' => 1.0, 'zone' => $soloZone]);
            $respB->assertOk()
                ->assertJsonPath('solo_mode', true)
                ->assertJsonPath('zone', $soloZone)
                ->assertJsonPath('instance_id', null)
                ->assertJsonPath('peers', [])
                ->assertJsonPath('instances', []);
        }

        // Wechsel in die Halle der Helden ('heroes') fuehrt beide Spieler in Instanz #1 zusammen
        $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['x' => 0.0, 'z' => -24.0, 'zone' => 'heroes'])
            ->assertOk()
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 1);

        $bobHero = $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['x' => 2.0, 'z' => -26.0, 'zone' => 'heroes']);
        $bobHero->assertOk()
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 1);
        $this->assertCount(1, $bobHero->json('peers'));
        $this->assertSame('SoloAlice', $bobHero->json('peers.0.display_name'));

        // Alice laeuft zurueck in die Produkt-Lobby ('gallery') -> Heldenhallen-Platz ist sofort frei
        $aliceBackToGallery = $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => 0.0, 'z' => -4.0, 'zone' => 'gallery']);
        $aliceBackToGallery->assertOk()
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('zone', 'gallery')
            ->assertJsonPath('instance_id', null)
            ->assertJsonPath('peers', []);

        $bobSyncAfterEvict = $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => 2.0, 'z' => -26.0, 'zone' => 'heroes']);
        $bobSyncAfterEvict->assertOk();
        $this->assertCount(0, $bobSyncAfterEvict->json('peers'));
        $this->assertSame(1, collect($bobSyncAfterEvict->json('instances'))->firstWhere('id', 1)['count']);

        // Instanz-Wechsel mit expliziter Solo-Zone wird sauber mit HTTP 422 abgelehnt
        $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 1, 'zone' => 'gallery'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Instanzen sind nur in der Halle der Helden aktiv.');
    }

    public function test_auto_sharding_places_first_16_heroes_players_in_instance_1_and_17th_in_instance_2(): void
    {
        $customers = [];
        for ($i = 1; $i <= 16; $i++) {
            $customer = $this->makeCustomer($i, true, sprintf('Sternenpilot%02d', $i), 4);
            $customers[$i] = $customer;

            $resp = $this->actingAs($customer, 'customer')
                ->postJson('/funkenwerk-welt/lobby/join', [
                    'x' => (float) $i,
                    'z' => -25.0,
                    'rot_y' => 1.2,
                    'zone' => 'heroes',
                ]);

            $resp->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonPath('authenticated', true)
                ->assertJsonPath('solo_mode', false)
                ->assertJsonPath('zone', 'heroes')
                ->assertJsonPath('instance_id', 1)
                ->assertJsonPath('soft_cap', LobbyInstanceService::SOFT_CAP)
                ->assertJsonPath('hard_cap', LobbyInstanceService::HARD_CAP);
        }

        // 17. eingeloggter Heldenhallen-Besucher wird ab SOFT_CAP = 16 automatisch in Instanz #2 geschaltet
        $customer17 = $this->makeCustomer(17, true, 'Kometenfreund17', 8);
        $resp17 = $this->actingAs($customer17, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', [
                'x' => 3.5,
                'z' => -18.0,
                'rot_y' => 0.5,
                'zone' => 'heroes',
            ]);

        $resp17->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 2)
            ->assertJsonPath('player.display_name', 'Kometenfreund17')
            ->assertJsonPath('player.level', 8)
            ->assertJsonPath('player.ship_class', 'KLASSE A');

        $json17 = $resp17->json();
        $this->assertCount(0, $json17['peers']);

        // UI-Instanzliste enthaelt #1 (16/20), #2 (1/20, is_current) PLUS freie Ausweich-Instanz #3 (0/20)
        $instancesById = collect($json17['instances'])->keyBy('id');
        $this->assertTrue($instancesById->has(1));
        $this->assertTrue($instancesById->has(2));
        $this->assertTrue($instancesById->has(3));
        $this->assertSame(16, $instancesById[1]['count']);
        $this->assertFalse($instancesById[1]['is_full']);
        $this->assertFalse($instancesById[1]['is_current']);
        $this->assertSame(1, $instancesById[2]['count']);
        $this->assertTrue($instancesById[2]['is_current']);
        $this->assertSame(0, $instancesById[3]['count']);
        $this->assertFalse($instancesById[3]['is_full']);

        // Erneuter Join-Aufruf mit zone = 'heroes' haelt Spieler 16 stabil in Instanz #1
        $this->actingAs($customers[16], 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes'])
            ->assertOk()
            ->assertJsonPath('instance_id', 1);

        // Wechselt Spieler 16 zurueck in die Produkt-Lobby ('gallery'), wird der 16. Platz in #1 sofort frei
        $this->actingAs($customers[16], 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['zone' => 'gallery', 'x' => 0.0, 'z' => 0.0])
            ->assertOk()
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('instance_id', null);

        $customer18 = $this->makeCustomer(18, true, 'Nachruecker18', 5);
        $this->actingAs($customer18, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'z' => -22.0])
            ->assertOk()
            ->assertJsonPath('instance_id', 1);
    }

    public function test_manual_instance_switch_allows_friends_to_join_up_to_hard_cap_and_clears_old_instance(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $c = $this->makeCustomer($i, true, sprintf('Freund%02d', $i), 5);
            $this->actingAs($c, 'customer')
                ->postJson('/funkenwerk-welt/lobby/join', ['x' => 2.0, 'z' => -24.0, 'zone' => 'heroes'])
                ->assertOk()
                ->assertJsonPath('instance_id', 1);
        }

        $friend17 = $this->makeCustomer(17, true, 'BesteFreundin17', 9);
        $this->actingAs($friend17, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'z' => -20.0])
            ->assertOk()
            ->assertJsonPath('instance_id', 2);

        // Manueller Wechsel von Instanz #2 nach Instanz #1 (ohne zone -> Default 'heroes', 17/20 <= HARD_CAP)
        $switchResp = $this->actingAs($friend17, 'customer')
            ->postJson('/funkenwerk-welt/lobby/switch', [
                'target_instance' => 1,
            ]);

        $switchResp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 1)
            ->assertJsonPath('player.display_name', 'BesteFreundin17')
            ->assertJsonPath('player.ship_class', 'KLASSE S');

        $switchJson = $switchResp->json();
        $this->assertCount(16, $switchJson['peers']);
        $peerNames = collect($switchJson['peers'])->pluck('display_name')->all();
        $this->assertContains('Freund01', $peerNames);
        $this->assertContains('Freund16', $peerNames);

        // Instanz #2 ist sofort wieder leer (0/20 als freie Ausweich-Instanz), Instanz #1 steht bei 17/20
        $instancesById = collect($switchJson['instances'])->keyBy('id');
        $this->assertSame(17, $instancesById[1]['count']);
        $this->assertTrue($instancesById[1]['is_current']);
        $this->assertFalse($instancesById[1]['is_full']);
        $this->assertSame(0, $instancesById[2]['count']);
        $this->assertFalse($instancesById[2]['is_current']);
    }

    public function test_hard_cap_rejects_21st_manual_switch_with_http_409(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $c = $this->makeCustomer($i, true, sprintf('Stammgast%02d', $i), 3);
            $this->actingAs($c, 'customer')
                ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes'])
                ->assertOk()
                ->assertJsonPath('instance_id', 1);
        }

        // Fuellen der Freundes-Reserve 17..20 per manuellem Wechsel nach Instanz #1
        for ($i = 17; $i <= 20; $i++) {
            $c = $this->makeCustomer($i, true, sprintf('ReserveGast%02d', $i), 6);
            $this->actingAs($c, 'customer')
                ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes'])
                ->assertOk()
                ->assertJsonPath('instance_id', 2);

            $res = $this->actingAs($c, 'customer')
                ->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 1]);
            $res->assertOk()
                ->assertJsonPath('instance_id', 1);
        }

        // 21. Spieler landet per Join in Instanz #2 und wird beim Wechsel nach #1 mit HTTP 409 abgewiesen
        $player21 = $this->makeCustomer(21, true, 'Zuspätkommer21', 7);
        $join21 = $this->actingAs($player21, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes']);
        $join21->assertOk()->assertJsonPath('instance_id', 2);

        $inst1Meta = collect($join21->json('instances'))->firstWhere('id', 1);
        $this->assertNotNull($inst1Meta);
        $this->assertSame(20, $inst1Meta['count']);
        $this->assertTrue($inst1Meta['is_full']);

        $rejectResp = $this->actingAs($player21, 'customer')
            ->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 1]);

        $rejectResp->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Diese Instanz ist voll (20/20).');

        // Ungueltige Instanz-Nummern ausserhalb 1..50 liefern sauber 422
        $this->actingAs($player21, 'customer')
            ->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 99])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        // Spieler 21 ist weiterhin sicher in Instanz #2 eingebucht
        $this->actingAs($player21, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => 0, 'z' => -22, 'zone' => 'heroes'])
            ->assertOk()
            ->assertJsonPath('instance_id', 2);
    }

    public function test_sync_clamps_heroes_bounds_allowlists_emotes_and_25s_heartbeat_ttl_plus_leave_clean_up(): void
    {
        $alice = $this->makeCustomer(31, true, 'Lichtpilotin', 10);
        $bob = $this->makeCustomer(32, true, 'Sternensegler', 5);

        $this->actingAs($alice, 'customer')->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes'])->assertOk();
        $this->actingAs($bob, 'customer')->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes'])->assertOk();

        // Alice sendet Extremwerte ausserhalb des Heldenhallen-Bereichs + unerlaubtes Emote -> hartes Server-Clamping
        $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', [
                'x' => 999.9,
                'y' => 99.0,
                'z' => -500.0,
                'rot_y' => -42.0,
                'speed' => 88.0,
                'combo_stage' => 99,
                'emote' => 'hacked_emote',
                'ability_slot' => 42,
                'zone' => 'heroes',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('instance_id', 1);

        $bobSync = $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', [
                'x' => -10.5,
                'y' => 0.0,
                'z' => -85.0,
                'rot_y' => 3.14,
                'speed' => 4.2,
                'emote' => 'wave',
                'ability_slot' => 2,
                'zone' => 'heroes',
            ]);

        $bobSync->assertOk();
        $peersForBob = $bobSync->json('peers');
        $this->assertCount(1, $peersForBob);
        $this->assertEquals(32.0, $peersForBob[0]['x']);
        $this->assertEquals(8.5, $peersForBob[0]['y']);
        $this->assertEquals(-110.0, $peersForBob[0]['z']);
        $this->assertEquals(-6.3, $peersForBob[0]['rot_y']);
        $this->assertEquals(LobbyInstanceService::MAX_SPEED, $peersForBob[0]['speed']);
        $this->assertSame(4, $peersForBob[0]['combo_stage']);
        $this->assertSame('', $peersForBob[0]['emote']);
        $this->assertSame(4, $peersForBob[0]['ability_slot']);
        $this->assertSame('heroes', $peersForBob[0]['zone']);

        // Nach 26s ohne Puls von Alice verschwindet sie automatisch aus Bobs Peers (HEARTBEAT_TTL_SECONDS = 25)
        $this->travel(15)->seconds();
        $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => -10.5, 'z' => -85.0, 'zone' => 'heroes'])
            ->assertOk();

        $this->travel(12)->seconds();
        $afterTtlSync = $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['x' => -10.5, 'z' => -85.0, 'zone' => 'heroes']);
        $afterTtlSync->assertOk();
        $this->assertCount(0, $afterTtlSync->json('peers'));

        // Expliziter Leave entfernt Bob sofort
        $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/leave')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_zero_pii_guest_isolation_admin_support_and_heroes_data_stats(): void
    {
        // Kunde 1: Opt-In aktiv + Spielername -> erscheint in Heroes & Lobby ohne jegliche PII
        $optInHero = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Leon',
            'last_name' => 'StrengVertraulichRechnung',
            'email' => 'leon.privat@geheim-domain.de',
            'password' => bcrypt('TopSecretPass!99'),
        ]);
        CustomerGamification::create([
            'customer_id' => $optInHero->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => 'GoldenerPhönix',
            'level' => 9,
            'funken_total_earned' => 4250,
            'titles_progress' => [
                'sammler' => 120,
                'schatzhueter' => 4,
            ],
        ]);

        // Kunde 2: KEIN Opt-In, nur Rechnungsdaten -> wird in Peers strikt als "Funki #XXXX" anonymisiert
        $privateCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sabine',
            'last_name' => 'GeheimerNachnameZwei',
            'email' => 'sabine.rechnung@privat.de',
            'password' => bcrypt('AnotherSecret!88'),
        ]);
        CustomerGamification::create([
            'customer_id' => $privateCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => false,
            'use_real_name' => true,
            'player_name' => null,
            'level' => 2,
            'funken_total_earned' => 180,
        ]);

        // 1. Gast-Pruefung (unauthentifiziert): HTTP 200 Solo ohne Instanz/Peers bei join & sync, HTTP 401 bei switch
        $guestJoin = $this->postJson('/funkenwerk-welt/lobby/join');
        $guestJoin->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('instance_id', null)
            ->assertJsonPath('player', null)
            ->assertJsonPath('peers', [])
            ->assertJsonPath('instances', []);

        $this->postJson('/funkenwerk-welt/lobby/sync', ['x' => 1, 'z' => 2])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('peers', [])
            ->assertJsonPath('instances', []);

        $this->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 1])
            ->assertStatus(401)
            ->assertJsonPath('ok', false);

        // 2. Beide Kunden + Admin betreten die Halle der Helden -> strikte Zero-PII Pruefung
        $this->actingAs($optInHero, 'customer')->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes'])->assertOk();
        $this->actingAs($privateCustomer, 'customer')->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes'])->assertOk();

        Auth::guard('customer')->logout();
        Auth::forgetGuards();

        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'SteinhauerAdminNachname',
            'email' => 'alina.admin.secret@mein-seelenfunke.de',
            'password' => bcrypt('AdminSecret!77'),
        ]);

        $adminJoin = $this->actingAs($admin, 'admin')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes']);

        $adminJoin->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 1)
            ->assertJsonPath('player.display_name', 'Alina')
            ->assertJsonPath('player.level', 10)
            ->assertJsonPath('player.ship_class', 'KLASSE S');

        $rawLobbyBody = $adminJoin->getContent();
        $this->assertZeroPiiAndZeroAiDashes($rawLobbyBody, [
            'leon.privat@geheim-domain.de',
            'sabine.rechnung@privat.de',
            'alina.admin.secret@mein-seelenfunke.de',
            'StrengVertraulichRechnung',
            'GeheimerNachnameZwei',
            'SteinhauerAdminNachname',
            (string) $optInHero->id,
            (string) $privateCustomer->id,
            (string) $admin->id,
            (string) $optInHero->password,
            (string) $privateCustomer->password,
        ]);

        $peersForAdmin = collect($adminJoin->json('peers'));
        $this->assertCount(2, $peersForAdmin);
        $peerDisplayNames = $peersForAdmin->pluck('display_name')->all();
        $this->assertContains('GoldenerPhönix', $peerDisplayNames);
        $anonPeer = $peersForAdmin->first(fn (array $p) => $p['display_name'] !== 'GoldenerPhönix');
        $this->assertNotNull($anonPeer);
        $this->assertMatchesRegularExpression('/^Funki #[0-9A-F]{4}$/', $anonPeer['display_name']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $anonPeer['peer_id']);

        // 3. Erweiterter /funkenwerk-welt/heroes-data Vertrag inkl. ship_class, titles_count, sparks_total & Zero-PII
        Auth::guard('admin')->logout();
        Auth::forgetGuards();

        $heroesResp = $this->getJson('/funkenwerk-welt/heroes-data');
        $heroesResp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_heroes', 1)
            ->assertJsonPath('heroes.0.slot', 1)
            ->assertJsonPath('heroes.0.occupied', true)
            ->assertJsonPath('heroes.0.display_name', 'GoldenerPhönix')
            ->assertJsonPath('heroes.0.level', 9)
            ->assertJsonPath('heroes.0.ship_class', 'KLASSE S')
            ->assertJsonPath('heroes.0.sparks_total', 4250)
            ->assertJsonPath('heroes.0.sparks_formatted', '4.250')
            ->assertJsonPath('heroes.1.slot', 2)
            ->assertJsonPath('heroes.1.occupied', false);

        $this->assertGreaterThanOrEqual(9, (int) $heroesResp->json('heroes.0.titles_count'));
        $this->assertCount(10, $heroesResp->json('heroes'));

        $this->assertZeroPiiAndZeroAiDashes($heroesResp->getContent(), [
            'leon.privat@geheim-domain.de',
            'sabine.rechnung@privat.de',
            'StrengVertraulichRechnung',
            'GeheimerNachnameZwei',
            'Sabine',
            (string) $optInHero->id,
            (string) $privateCustomer->id,
        ]);
    }

    private function makeCustomer(int $index, bool $optIn, string $playerName, int $level): Customer
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Vorname' . $index,
            'last_name' => 'RechnungsNachname' . $index,
            'email' => sprintf('kunde.%d.geheim@example.de', $index),
            'password' => bcrypt('SecretPass!' . $index),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'ranking_opt_in' => $optIn,
            'use_real_name' => false,
            'player_name' => $playerName,
            'level' => $level,
            'funken_total_earned' => $level * 500,
        ]);

        return $customer;
    }

    /**
     * @param array<int, string> $forbiddenStrings
     */
    private function assertZeroPiiAndZeroAiDashes(string $rawPayload, array $forbiddenStrings): void
    {
        foreach ($forbiddenStrings as $secret) {
            if ($secret !== '') {
                $this->assertStringNotContainsString($secret, $rawPayload);
            }
        }

        $this->assertStringNotContainsString('@', $rawPayload);
        $this->assertStringNotContainsString('$2y$', $rawPayload);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $rawPayload);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $rawPayload);
    }
}
