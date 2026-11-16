<?php

namespace Tests\Feature\Shop;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\Shop\ShowroomChatGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class Louvre3DShowroomMultiplayerAndChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'showroom_multiplayer_enabled'], ['value' => '1']);
    }

    public function test_guests_remain_strictly_solo_and_cannot_access_sync_chat_or_report_endpoints(): void
    {
        $boot = $this->getJson('/shop/showroom-data');
        $boot->assertStatus(200);
        $boot->assertJsonPath('player_funki.is_guest', true);
        $boot->assertJsonPath('player_funki.weapon', null);
        $boot->assertJsonPath('multiplayer.enabled', false);
        $boot->assertJsonPath('multiplayer.is_authenticated', false);
        $boot->assertJsonPath('multiplayer.solo_only', true);
        $boot->assertJsonPath('multiplayer.room_id', null);

        $this->postJson('/shop/showroom-sync', ['x' => 1.2, 'y' => 0.0, 'z' => 4.0])->assertStatus(403);
        $this->postJson('/shop/showroom-chat', ['message' => 'Hallo zusammen'])->assertStatus(403);
        $this->postJson('/shop/showroom-report', ['target_player_id' => 'p_1234567890abcdef'])->assertStatus(403);
    }

    public function test_twelve_player_room_sharding_assigns_customer_thirteen_to_second_room_with_zero_pii(): void
    {
        $customers = [];

        for ($i = 1; $i <= 13; $i++) {
            $customer = Customer::create([
                'id' => (string) Str::uuid(),
                'first_name' => 'VornameGeheim' . $i,
                'last_name' => 'NachnamePrivat' . $i,
                'email' => "privat.kunde{$i}@geheim-mail.example",
                'password' => bcrypt('secret123'),
            ]);

            if ($i === 1) {
                // Geprüfter Fantasy-Spielername + eigene KLASSE S Waffe
                CustomerGamification::create([
                    'customer_id' => $customer->id,
                    'is_active' => true,
                    'level' => 9,
                    'funken_balance' => 500,
                    'funken_total_earned' => 4800,
                    'player_name' => 'Sternenwanderer',
                    'use_real_name' => false,
                    'equipped_items' => [
                        'weapon' => [
                            'key' => 'cosmos_blade_custom',
                            'name' => 'Astrale Goldklinge',
                            'rank_class' => 'S',
                            'effect_type' => 'cosmos_blade',
                            'accent_hex' => '#FBBF24',
                        ],
                    ],
                ]);
            } elseif ($i === 2) {
                // Hat use_real_name = true -> MUSS im Showroom trotzdem anonym ("Funki #XXXX") bleiben!
                CustomerGamification::create([
                    'customer_id' => $customer->id,
                    'is_active' => true,
                    'level' => 5,
                    'player_name' => 'VornameGeheim2',
                    'use_real_name' => true,
                ]);
            } elseif ($i === 3) {
                // Hat seinen Nachnamen in player_name eingetragen -> MUSS anonymisiert werden!
                CustomerGamification::create([
                    'customer_id' => $customer->id,
                    'is_active' => true,
                    'level' => 4,
                    'player_name' => 'BaronNachnamePrivat3',
                    'use_real_name' => false,
                ]);
            }

            $customers[$i] = $customer;
        }

        // Kunden 1 bis 12 betreten Raum 1
        for ($i = 1; $i <= 12; $i++) {
            $resp = $this->actingAs($customers[$i], 'customer')->postJson('/shop/showroom-sync', [
                'x' => -3.0 + ($i * 0.4),
                'y' => $i === 1 ? 0.65 : 0.0,
                'z' => 6.0,
                'rotY' => 3.14,
                'jumping' => $i === 1,
                'weaponActionSeq' => $i === 1 ? 2 : 0,
                'soloMode' => false,
            ]);

            $resp->assertStatus(200);
            $resp->assertJsonPath('room_id', 'louvre-room-1');
        }

        // Kunde 13 wird automatisch in Raum 2 geshardet!
        $resp13 = $this->actingAs($customers[13], 'customer')->postJson('/shop/showroom-sync', [
            'x' => 0.0,
            'y' => 0.0,
            'z' => 8.0,
            'rotY' => 3.14,
            'jumping' => false,
            'weaponActionSeq' => 0,
            'soloMode' => false,
        ]);
        $resp13->assertStatus(200);
        $resp13->assertJsonPath('room_id', 'louvre-room-2');
        $this->assertCount(0, $resp13->json('peers'));

        // Kunde 4 synchronisiert Raum 1: sieht exakt 11 Mitspieler und 0 bürgerliche Klarnamen / E-Mails / UUIDs
        $syncRoom1 = $this->actingAs($customers[4], 'customer')->postJson('/shop/showroom-sync', [
            'x' => 1.0,
            'y' => 0.0,
            'z' => 5.0,
            'rotY' => 1.57,
            'jumping' => false,
            'weaponActionSeq' => 0,
            'soloMode' => false,
        ]);
        $syncRoom1->assertStatus(200);
        $rawSyncJson = (string) $syncRoom1->getContent();
        $peers = $syncRoom1->json('peers');
        $this->assertCount(11, $peers);

        for ($i = 1; $i <= 13; $i++) {
            $this->assertStringNotContainsString('VornameGeheim' . $i, $rawSyncJson);
            $this->assertStringNotContainsString('NachnamePrivat' . $i, $rawSyncJson);
            $this->assertStringNotContainsString("privat.kunde{$i}", $rawSyncJson);
            $this->assertStringNotContainsString((string) $customers[$i]->id, $rawSyncJson);
        }

        $peer1 = collect($peers)->firstWhere('display_name', 'Sternenwanderer');
        $this->assertNotNull($peer1);
        $this->assertSame(9, $peer1['level']);
        $this->assertTrue($peer1['jumping']);
        $this->assertSame(2, $peer1['weapon_seq']);
        $this->assertSame('KLASSE S', $peer1['weapon']['class_label']);
        $this->assertSame('cosmos_blade', $peer1['weapon']['effect_type']);

        // Wechselt Kunde 1 in den Solo-Modus, verschwindet er sofort aus der Peer-Liste der anderen
        $soloResp = $this->actingAs($customers[1], 'customer')->postJson('/shop/showroom-sync', [
            'x' => 0.0,
            'y' => 0.0,
            'z' => 6.0,
            'soloMode' => true,
        ]);
        $soloResp->assertStatus(200);
        $soloResp->assertJsonPath('solo_mode', true);
        $this->assertCount(0, $soloResp->json('peers'));

        $syncAfterSolo = $this->actingAs($customers[4], 'customer')->postJson('/shop/showroom-sync', [
            'x' => 1.0,
            'y' => 0.0,
            'z' => 5.0,
            'soloMode' => false,
        ]);
        $this->assertCount(10, $syncAfterSolo->json('peers'));
    }

    public function test_four_stage_tiktok_word_link_and_pii_filter_blocks_evasions_and_allows_friendly_chat(): void
    {
        /** @var ShowroomChatGuardService $guard */
        $guard = app(ShowroomChatGuardService::class);

        $blockedSamples = [
            // DE & EN Beleidigungen / Hassrede
            'Du verdammtes Arschloch',
            'You stupid motherfucker',
            // Anti-Trick-Normalisierung (Punkte, Sternchen, Leerzeichen, Mehrfachbuchstaben, Leetspeak, Kyrillisch)
            'So ein A.r.s.c.h hier',
            'What the f*ck man',
            'Du h u r e im Raum',
            'Du huuuuure',
            'Sche1ss Laden $ch31sse',
            'Du n4z1 Idiot',
            "аrschloch", // kyrillisches 'а'
            // Links, Domains, Messenger, E-Mails, Telefonnummern & Social-Handles
            'Schaut mal auf https://billig-kopie.example vorbei',
            'Geht auf www.fremdshop.de jetzt',
            'Kauft bei fremdshop.com ein',
            'Kommt in t.me/geheimgruppe',
            'Join discord.gg/invite123',
            'Schreib mir an kontakt@fremd-mail.de',
            'Ruf mich an unter 01590 1966864',
            'Meine Nummer ist +49-176-9876543',
            'Folgt mir alle auf @mein_insta_handle',
            // Überlänge (> 100 Zeichen)
            str_repeat('a', 101),
        ];

        foreach ($blockedSamples as $idx => $badText) {
            $result = $guard->inspectAndSanitize('cust-test-' . $idx, $badText, false);
            $this->assertFalse(
                $result['allowed'],
                "Erwartete Blockade für Muster [{$badText}], wurde aber durchgelassen."
            );
        }

        $allowedSamples = [
            'Wie edel der Seelen-Kristall unter der Glaspyramide funkelt!',
            'Ich gestalte gerade das Goldene Seelen-Herz als Geschenk.',
            'Dein Level 8 Funki sieht mega stark aus!',
            'Really love the black and gold Louvre gallery design.',
        ];

        foreach ($allowedSamples as $idx => $goodText) {
            $result = $guard->inspectAndSanitize('cust-good-' . $idx, $goodText, false);
            $this->assertTrue(
                $result['allowed'],
                "Freundliche Nachricht [{$goodText}] wurde fälschlich blockiert: " . ($result['code'] ?? '')
            );
            $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $result['sanitized']);
        }

        // End-to-End Test über POST /shop/showroom-chat & Zustellung im selben 12er-Raum
        $sender = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Anna',
            'last_name' => 'Geheim',
            'email' => 'anna@example.com',
            'password' => bcrypt('secret123'),
        ]);
        CustomerGamification::create([
            'customer_id' => $sender->id,
            'is_active' => true,
            'level' => 6,
            'player_name' => 'Goldfunken',
            'use_real_name' => false,
        ]);

        $listener = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Tom',
            'last_name' => 'Vertraulich',
            'email' => 'tom@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // Böse Nachricht über HTTP-Endpunkt wird mit 422 abgewiesen
        $badHttp = $this->actingAs($sender, 'customer')->postJson('/shop/showroom-chat', [
            'message' => 'Besucht www.spam-seite.de sofort!',
        ]);
        $badHttp->assertStatus(422);

        // Freundliche Nachricht wird akzeptiert und im Raum-Ringpuffer bereitgestellt
        $goodHttp = $this->actingAs($sender, 'customer')->postJson('/shop/showroom-chat', [
            'message' => 'Hallo im Louvre Showroom!',
        ]);
        $goodHttp->assertStatus(200);
        $goodHttp->assertJsonPath('entry.display_name', 'Goldfunken');
        $goodHttp->assertJsonPath('entry.class_label', 'KLASSE A');
        $goodHttp->assertJsonPath('entry.text', 'Hallo im Louvre Showroom!');

        $listenerSync = $this->actingAs($listener, 'customer')->postJson('/shop/showroom-sync', [
            'x' => 0,
            'y' => 0,
            'z' => 7,
            'soloMode' => false,
        ]);
        $listenerSync->assertStatus(200);
        $messages = $listenerSync->json('messages');
        $this->assertCount(1, $messages);
        $this->assertSame('Goldfunken', $messages[0]['display_name']);
        $this->assertSame('Hallo im Louvre Showroom!', $messages[0]['text']);
        $this->assertStringNotContainsString('Anna', (string) $listenerSync->getContent());
    }

    public function test_slow_mode_cooldown_two_strike_shadow_mute_and_admin_killswitch(): void
    {
        $troublemaker = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Max',
            'last_name' => 'Test',
            'email' => 'max@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $reporterOne = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Klara',
            'last_name' => 'Eins',
            'email' => 'klara1@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $reporterTwo = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Klara',
            'last_name' => 'Zwei',
            'email' => 'klara2@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $boot = $this->actingAs($troublemaker, 'customer')->getJson('/shop/showroom-data');
        $boot->assertStatus(200);
        $targetPublicId = (string) $boot->json('multiplayer.player_id');
        $this->assertStringStartsWith('p_', $targetPublicId);

        // 1. Slow-Mode Cooldown (4 Sekunden)
        $this->actingAs($troublemaker, 'customer')
            ->postJson('/shop/showroom-chat', ['message' => 'Erste freundliche Nachricht'])
            ->assertStatus(200);

        $secondImmediate = $this->actingAs($troublemaker, 'customer')
            ->postJson('/shop/showroom-chat', ['message' => 'Zweite Nachricht sofort hinterher']);
        $secondImmediate->assertStatus(422);
        $secondImmediate->assertJsonPath('code', 'cooldown');

        // Selbst-Meldung wird abgewiesen
        $this->actingAs($troublemaker, 'customer')
            ->postJson('/shop/showroom-report', ['target_player_id' => $targetPublicId])
            ->assertStatus(422);

        // 2. 2-Strike-Report -> 24h Shadow-Mute
        $rep1 = $this->actingAs($reporterOne, 'customer')
            ->postJson('/shop/showroom-report', ['target_player_id' => $targetPublicId]);
        $rep1->assertStatus(200);
        $rep1->assertJsonPath('reports_count', 1);
        $rep1->assertJsonPath('shadow_muted', false);

        $rep2 = $this->actingAs($reporterTwo, 'customer')
            ->postJson('/shop/showroom-report', ['target_player_id' => $targetPublicId]);
        $rep2->assertStatus(200);
        $rep2->assertJsonPath('reports_count', 2);
        $rep2->assertJsonPath('shadow_muted', true);

        // Gemeldeter Spieler ist sofort für 24h im Chat gesperrt
        $mutedAttempt = $this->actingAs($troublemaker, 'customer')
            ->postJson('/shop/showroom-chat', ['message' => 'Ich versuche weiter zu schreiben']);
        $mutedAttempt->assertStatus(422);
        $mutedAttempt->assertJsonPath('code', 'muted');

        // 3. Admin-Hauptschalter in /admin/settings (showroom_multiplayer_enabled = '0')
        SystemSetting::updateOrCreate(['key' => 'showroom_multiplayer_enabled'], ['value' => '0']);

        $disabledBoot = $this->actingAs($reporterOne, 'customer')->getJson('/shop/showroom-data');
        $disabledBoot->assertStatus(200);
        $disabledBoot->assertJsonPath('multiplayer.enabled', false);
        $disabledBoot->assertJsonPath('multiplayer.solo_only', true);

        $this->actingAs($reporterOne, 'customer')
            ->postJson('/shop/showroom-sync', ['x' => 0, 'y' => 0, 'z' => 5])
            ->assertStatus(403);
        $this->actingAs($reporterOne, 'customer')
            ->postJson('/shop/showroom-chat', ['message' => 'Test bei deaktiviertem Schalter'])
            ->assertStatus(403);
    }

    public function test_frontend_markup_and_3d_js_contain_jump_weapon_chat_and_focus_protection_hooks(): void
    {
        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'price' => 6990,
            'track_quantity' => true,
            'quantity' => 5,
        ]);

        $resp = $this->get('/shop');
        $resp->assertStatus(200);
        $html = (string) $resp->getContent();

        $this->assertStringContainsString('data-testid="louvre-jump-btn"', $html);
        $this->assertStringContainsString('data-testid="louvre-weapon-btn"', $html);
        $this->assertStringContainsString('data-testid="louvre-chat-toggle-btn"', $html);
        $this->assertStringContainsString('data-testid="louvre-room-chat-panel"', $html);
        $this->assertStringContainsString('data-testid="louvre-live-solo-toggle"', $html);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $html);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $html);

        $js = (string) file_get_contents(public_path('js/seelenfunke-louvre-showroom-3d.js'));
        $this->assertStringContainsString('triggerJump()', $js);
        $this->assertStringContainsString('triggerWeaponAction()', $js);
        $this->assertStringContainsString('updatePeers(', $js);
        $this->assertStringContainsString('showChatBubble(', $js);
        $this->assertStringContainsString('fpsEstimate < 40', $js);
        $this->assertStringContainsString('focusProtected', $js);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $js);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $js);
    }
}
