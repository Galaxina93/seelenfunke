<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\System\SystemShopConfig;
use App\Livewire\Shop\System\SystemUserManagement;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemLog;
use App\Models\System\SystemSetting;
use App\Services\Gamification\LobbyInstanceService;
use App\Services\Shop\ShowroomChatGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LobbyMultiplayerSwitchAndAutoSanctionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::create(2026, 11, 18, 18, 0, 0, 'Europe/Berlin'));

        $this->admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'kontakt@mein-seelenfunke.de',
            'password' => Hash::make('SecretAdmin123!'),
        ]);

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'showroom_multiplayer_enabled'], ['value' => '1']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_multiplayer_killswitch_switches_hall_of_heroes_to_solo_flushes_ws_and_blocks_chat_and_switch(): void
    {
        Http::fake([
            '127.0.0.1:8089/*' => Http::response(['ok' => true, 'rooms_cleared' => 1, 'sockets_closed' => 2], 200),
        ]);

        $alice = $this->createHeroReadyCustomer('Alice', 'Morgen', 'alice@example.com', 'Sternenlicht', 6);
        $bob = $this->createHeroReadyCustomer('Bob', 'Glanz', 'bob@example.com', 'Kometenflug', 5);

        // 1. showroom_multiplayer_enabled = true -> regulaerer Multiplayer + ws_relay aktiv
        $joinAlice = $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'x' => 0.0, 'z' => -24.0]);
        $joinAlice->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('multiplayer_disabled', false)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', 1);
        $this->assertIsArray($joinAlice->json('ws_relay'));
        $this->assertNotEmpty($joinAlice->json('ws_relay.ticket'));

        $joinBob = $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'x' => 2.0, 'z' => -25.0]);
        $joinBob->assertOk()
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('instance_id', 1);
        $this->assertCount(1, $joinBob->json('peers'));

        $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Willkommen in der Halle!'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // 2. Admin deaktiviert 3D-Multiplayer in SystemShopConfig -> Clean-UI + WS-Flush + Solo-Modus
        $configComponent = Livewire::actingAs($this->admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSee('3D-Multiplayer (Halle & Chat)')
            ->assertSeeHtml('3D-Multiplayer (Halle &amp; Chat)')
            ->assertSee('Aktiv (Instanzen)')
            ->call('toggleShowroomMultiplayer')
            ->assertSet('settings.showroom_multiplayer_enabled', false)
            ->assertSee('Solo-Modus');

        // Null-Gedankenstrich-Pruefung gezielt auf der Multiplayer-Karte (Datumsbereiche im Jahreszeiten-Tab sind typografische Bis-Striche)
        $configHtml = $configComponent->html();
        $mpStart = strpos($configHtml, '3D-SHOWROOM MULTI-USER') ?: strpos($configHtml, '3D-Multiplayer (Halle');
        $mpCard = $mpStart !== false ? substr($configHtml, max(0, $mpStart - 200), 4000) : $configHtml;
        $this->assertStringNotContainsString(" \u{2013} ", $mpCard);
        $this->assertStringNotContainsString(" \u{2013} ", (string) (new SystemShopConfig())->infoTexts['showroom_multiplayer_enabled']);
        $this->assertFalse(LobbyInstanceService::isMultiplayerEnabled());

        Http::assertSent(function (HttpClientRequest $request): bool {
            return $request->url() === 'http://127.0.0.1:8089/internal/lobby-ws/flush'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Lobby-Admin-Sig');
        });

        // 3. join & sync in 'heroes' liefern solo_mode: true, multiplayer_disabled: true, ws_relay: null, peers: []
        $soloJoin = $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'x' => 0.0, 'z' => -24.0]);
        $soloJoin->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('multiplayer_disabled', true)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', null)
            ->assertJsonPath('ws_relay', null)
            ->assertJsonPath('peers', [])
            ->assertJsonPath('messages', [])
            ->assertJsonPath('instances', [])
            ->assertJsonPath('player.display_name', 'Sternenlicht');

        $soloSync = $this->actingAs($bob, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['zone' => 'heroes', 'x' => 1.5, 'z' => -26.0]);
        $soloSync->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('solo_mode', true)
            ->assertJsonPath('multiplayer_disabled', true)
            ->assertJsonPath('zone', 'heroes')
            ->assertJsonPath('instance_id', null)
            ->assertJsonPath('ws_relay', null)
            ->assertJsonPath('peers', [])
            ->assertJsonPath('messages', [])
            ->assertJsonPath('instances', []);

        // 4. chat, switch & report liefern 403 multiplayer_disabled
        $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Hallo im Solo-Modus'])
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'multiplayer_disabled');

        $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/switch', ['target_instance' => 2])
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'multiplayer_disabled');

        $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/report', ['peer_id' => (string) $joinBob->json('player.peer_id')])
            ->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'multiplayer_disabled');

        // 5. Re-Aktivierung schaltet Multiplayer und ws_relay sofort wieder frei
        $configComponent->call('toggleShowroomMultiplayer')
            ->assertSet('settings.showroom_multiplayer_enabled', true)
            ->assertSee('Aktiv (Instanzen)');

        $this->assertTrue(LobbyInstanceService::isMultiplayerEnabled());

        $rejoined = $this->actingAs($alice, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'x' => 0.0, 'z' => -24.0]);
        $rejoined->assertOk()
            ->assertJsonPath('solo_mode', false)
            ->assertJsonPath('multiplayer_disabled', false)
            ->assertJsonPath('instance_id', 1);
        $this->assertNotNull($rejoined->json('ws_relay.ticket'));
    }

    public function test_deep_word_stem_shield_blocks_german_and_english_insults_plurals_and_compounds_while_allowing_safe_sentences(): void
    {
        /** @var ShowroomChatGuardService $guard */
        $guard = app(ShowroomChatGuardService::class);

        $blockedMessages = [
            'Du bist dumm',
            'Arschlöcher',
            'Ihr Arschloecher hier',
            'Hurensöhne',
            'Hurensoehne',
            'Vollpfosten',
            'halt die Klappe',
            'Halts Maul jetzt',
            'Halt dein Maul',
            'Halt die Fresse',
            'Dreckskerl',
            'So ein Mistkerl und Miststück',
            'Du Dummkopf und Schwachkopf',
            'Ihr Vollidioten und Spastis',
            'Du Opfer und Lappen',
            'Verpiss dich du Pisser',
            'Geh sterben',
            'So ein Kackvogel und Affenarsch',
            'You stupid moron',
            'Shut the fuck up dumbass',
            'Total bullshit assholes',
        ];

        $startedAt = hrtime(true);
        foreach ($blockedMessages as $index => $insult) {
            $res = $guard->inspectAndSanitize('stem-block-' . $index, $insult, false);
            $this->assertFalse(
                $res['allowed'],
                "Beleidigung wurde nicht blockiert: [{$insult}]"
            );
            $this->assertSame('blocked_language', $res['code']);
        }
        $avgMs = ((hrtime(true) - $startedAt) / 1_000_000) / count($blockedMessages);
        $this->assertLessThan(1.0, $avgMs, 'Wortfilter sollte im Schnitt deutlich unter 1 ms pro Nachricht laufen.');

        $safeGermanMessages = [
            'Was für ein schönes Schmuckstück in der Halle der Helden!',
            'Bitte schick mir den Klassiker',
            'Das dicke Kristallglas funkelt herrschaftlich in der Nachbarschaft.',
            'Herzlichen Glückwunsch zur Meisterschaft und viel Glück!',
            'Gib mir bitte Bescheid, welcher Geschenkgutschein dir besser gefällt.',
            'Ein frischer Barsch am Tisch und eine wunderbare Freundschaft im Atelier.',
            'Wir drücken dir die Daumen für deine Auswahl bei Mein-Seelenfunke!',
        ];

        foreach ($safeGermanMessages as $index => $safeText) {
            $res = $guard->inspectAndSanitize('stem-safe-' . $index, $safeText, false);
            $this->assertTrue(
                $res['allowed'],
                "Harmloser deutscher Satz wurde faelschlich blockiert: [{$safeText}] (Code: " . ($res['code'] ?? 'none') . ')'
            );
        }
    }

    public function test_three_stage_auto_sanction_escalation_persistence_and_admin_reset_in_user_management(): void
    {
        $offender = $this->createHeroReadyCustomer('Timo', 'Test', 'timo@example.com', 'Sturmreiter', 4);

        $initialJoin = $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/join', ['zone' => 'heroes', 'x' => 0.0, 'z' => -24.0]);
        $initialJoin->assertOk()
            ->assertJsonPath('chat_moderation.strikes', 0)
            ->assertJsonPath('chat_moderation.max_strikes', 3)
            ->assertJsonPath('chat_moderation.is_muted', false)
            ->assertJsonPath('chat_moderation.permanent_mute', false);

        // 1. Verstoss -> Verwarnung 1/3 (auto_warning_1, noch keine Sperre)
        $strike1 = $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Du Vollpfosten']);
        $strike1->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'auto_warning_1')
            ->assertJsonPath('strike', 1)
            ->assertJsonPath('max_strikes', 3)
            ->assertJsonPath('muted_until_ts', null)
            ->assertJsonPath('remaining_seconds', 0)
            ->assertJsonPath('permanent_mute', false);
        $this->assertStringContainsString('Verwarnung 1/3', (string) $strike1->json('message'));
        $this->assertStringNotContainsString(" \u{2013} ", (string) $strike1->json('message'));

        $this->assertTrue(
            SystemLog::where('type', 'security')
                ->where('action_id', 'security:3d_chat_warning_1')
                ->where('status', 'warning')
                ->exists()
        );

        // Nach Stufe 1 ist eine normale Nachricht weiterhin erlaubt
        $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Entschuldigung, das Schmuckstück ist schön!'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        // 2. Verstoss -> Abmahnung 2/3 + 15 Minuten Sperre (auto_mute_15m)
        $this->travel(3)->seconds();
        $strike2 = $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Halt die Klappe du Dreckskerl']);
        $strike2->assertStatus(422)
            ->assertJsonPath('code', 'auto_mute_15m')
            ->assertJsonPath('strike', 2)
            ->assertJsonPath('max_strikes', 3)
            ->assertJsonPath('remaining_seconds', 900)
            ->assertJsonPath('permanent_mute', false);
        $this->assertNotNull($strike2->json('muted_until_ts'));
        $this->assertStringContainsString('Abmahnung 2/3', (string) $strike2->json('message'));

        $this->assertTrue(
            SystemLog::where('type', 'security')
                ->where('action_id', 'security:3d_chat_auto_mute_15m')
                ->where('status', 'warning')
                ->exists()
        );

        // Waehrend der 15-Minuten-Sperre wird selbst ein harmloses "Hallo" sofort mit 422 chat_muted abgelehnt
        $mutedHello = $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Hallo']);
        $mutedHello->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'chat_muted')
            ->assertJsonPath('strike', 2)
            ->assertJsonPath('max_strikes', 3)
            ->assertJsonPath('permanent_mute', false);
        $this->assertStringContainsString('Min. gesperrt', (string) $mutedHello->json('message'));
        $this->assertStringNotContainsString(" \u{2013} ", (string) $mutedHello->json('message'));

        // Sync uebergibt aktiven Sperrstatus ans Frontend
        $syncWhileMuted = $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/sync', ['zone' => 'heroes', 'x' => 1.0, 'z' => -24.0]);
        $syncWhileMuted->assertOk()
            ->assertJsonPath('chat_moderation.strikes', 2)
            ->assertJsonPath('chat_moderation.is_muted', true);

        // Nach Ablauf der 15 Minuten + Cache-Verlust bleiben Strikes persistent aus titles_progress['chat_moderation'] erhalten!
        $this->travel(16)->minutes();
        Cache::forget('sf_lobby_sanction_v1_' . $offender->id);

        $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Hallo nach der Pause'])
            ->assertOk()
            ->assertJsonPath('chat_moderation.strikes', 2)
            ->assertJsonPath('chat_moderation.is_muted', false);

        // 3. Verstoss -> Abmahnung 3/3 + 24 Stunden Sperre (auto_mute_24h)
        $this->travel(3)->seconds();
        $strike3 = $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Du bist dumm']);
        $strike3->assertStatus(422)
            ->assertJsonPath('code', 'auto_mute_24h')
            ->assertJsonPath('strike', 3)
            ->assertJsonPath('max_strikes', 3)
            ->assertJsonPath('remaining_seconds', 86400)
            ->assertJsonPath('permanent_mute', false);
        $this->assertStringContainsString('Abmahnung 3/3', (string) $strike3->json('message'));

        $this->assertTrue(
            SystemLog::where('type', 'security')
                ->where('action_id', 'security:3d_chat_auto_mute_24h')
                ->where('status', 'error')
                ->exists()
        );

        $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Hallo'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'chat_muted')
            ->assertJsonPath('strike', 3);

        // Nach Ablauf der 24h fuehrt der 4. Verstoss zur dauerhaften Chat-Sperre (auto_mute_permanent)
        $this->travel(25)->hours();
        $strike4 = $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Ihr Hurensöhne']);
        $strike4->assertStatus(422)
            ->assertJsonPath('code', 'auto_mute_permanent')
            ->assertJsonPath('strike', 4)
            ->assertJsonPath('max_strikes', 3)
            ->assertJsonPath('muted_until_ts', null)
            ->assertJsonPath('permanent_mute', true);

        $logPermanent = SystemLog::where('type', 'security')
            ->where('action_id', 'security:3d_chat_permanent_mute')
            ->where('status', 'error')
            ->latest('id')
            ->first();
        $this->assertNotNull($logPermanent);
        $this->assertSame(4, $logPermanent->payload['strike'] ?? null);
        $this->assertSame('blocked_language', $logPermanent->payload['filter_code'] ?? null);
        $this->assertNotEmpty($logPermanent->payload['excerpt'] ?? null);

        // Selbst 7 Tage spaeter bleibt der dauerhafte Mute ohne Admin-Freigabe bestehen
        $this->travel(7)->days();
        $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Hallo'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'chat_muted')
            ->assertJsonPath('permanent_mute', true);

        // Admin oeffnet Kunden in SystemUserManagement -> sieht Sperre + Verwarnungen 4/3 und fuehrt 1-Klick-Reset aus
        $userMgmt = Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', (string) $offender->id, 'customer')
            ->assertSet('editingCustomerChatMuted', true)
            ->assertSet('editingCustomerChatStrikes', 4)
            ->assertSee('3D-Chat: Stummgeschaltet')
            ->assertSee('Verwarnungen: 4/3')
            ->assertSee('Freischalten')
            ->call('resetCustomer3dChatMute')
            ->assertSet('editingCustomerChatMuted', false)
            ->assertSet('editingCustomerChatStrikes', 0)
            ->assertSee('3D-Chat: Frei')
            ->assertDontSee('3D-Chat: Stummgeschaltet')
            ->assertDontSee('Verwarnungen: 4/3');

        $this->assertStringNotContainsString(" \u{2013} ", $userMgmt->html());

        $freshGamification = CustomerGamification::where('customer_id', $offender->id)->firstOrFail();
        $this->assertSame(0, (int) ($freshGamification->titles_progress['chat_moderation']['strikes'] ?? -1));
        $this->assertFalse((bool) ($freshGamification->titles_progress['chat_moderation']['permanent_mute'] ?? true));
        $this->assertNull($freshGamification->titles_progress['chat_moderation']['muted_until_ts'] ?? null);

        // Nach dem Admin-Reset kann der Kunde sofort wieder normal schreiben
        $this->actingAs($offender, 'customer')
            ->postJson('/funkenwerk-welt/lobby/chat', ['message' => 'Vielen Dank für die zweite Chance!'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('chat_moderation.strikes', 0)
            ->assertJsonPath('chat_moderation.is_muted', false);
    }

    private function createHeroReadyCustomer(
        string $firstName,
        string $lastName,
        string $email,
        string $playerName,
        int $level
    ): Customer {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make('Secret123!'),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => $playerName,
            'level' => $level,
            'funken_balance' => 100,
            'funken_total_earned' => 500,
        ]);

        OrderOrder::create([
            'order_number' => 'ORD-MP-' . Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => ['first_name' => $firstName, 'last_name' => $lastName],
            'shipping_address' => ['first_name' => $firstName, 'last_name' => $lastName],
        ]);

        return $customer;
    }
}
