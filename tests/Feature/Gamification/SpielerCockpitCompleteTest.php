<?php

namespace Tests\Feature\Gamification;

use App\Livewire\Customer\Gamification\GameGamesComponent;
use App\Livewire\Customer\Gamification\GameGlobalRankingComponent;
use App\Livewire\Customer\Gamification\GameGlobalStatsHeader;
use App\Livewire\Customer\Gamification\GameProfileComponent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpielerCockpitCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected CustomerGamification $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'first_name' => 'Elena',
            'last_name'  => 'Sternenglanz',
            'email'      => 'elena-' . uniqid() . '@seelenfunke.test',
            'password'   => bcrypt('secret123'),
        ]);

        $this->profile = CustomerGamification::create([
            'customer_id'          => $this->customer->id,
            'is_active'            => false,
            'ranking_opt_in'       => false,
            'energy_balance'       => 5,
            'funken_balance'       => 0,
            'funken_total_earned'  => 0,
            'funkenflug_highscore' => 0,
            'level'                => 1,
        ]);

        $this->actingAs($this->customer, 'customer');
    }

    public function test_opt_in_screen_caps_games_module_when_inactive_and_activates_cleanly(): void
    {
        // 1. Wenn Game-Modus inaktiv ist, wird die Opt-In-Vorschau ("Einkaufen, weit weg vom Standard.") angezeigt
        Livewire::test(GameGamesComponent::class)
            ->assertSet('hasOptedIn', false)
            ->assertSee('Einkaufen,')
            ->assertSee('Standard.')
            ->assertDontSee('Halle der Legenden öffnen');

        // 2. Globaler Header hat den Einstiegs-Link nach /games, aber keine Energie-/Guthaben-Anzeige
        Livewire::test(GameGlobalStatsHeader::class)
            ->assertSet('hasOptedIn', false)
            ->assertSee('Spiele')
            ->assertDontSee('Deine Energie')
            ->assertDontSee('Dein Guthaben');

        // 3. Opt-In aktivieren -> 50 Start-Funken, 5 Energie & Weiterleitung ins Spieler-Cockpit
        Livewire::test(GameGamesComponent::class)
            ->call('optIn')
            ->assertRedirect(route('customer.games'));

        $this->profile->refresh();
        $this->assertTrue((bool) $this->profile->is_active);
        $this->assertEquals(0, $this->profile->funken_balance);
        $this->assertEquals(5, $this->profile->energy_balance);
    }

    public function test_unified_spieler_cockpit_renders_smart_tiles_and_cleaned_header(): void
    {
        $this->profile->update([
            'is_active'      => true,
            'funken_balance' => 300,
            'energy_balance' => 4,
            'level'          => 3,
        ]);

        // Spieler-Cockpit zeigt Dachzeile "Deine Evolution", Hauptüberschrift "Spieler-Cockpit" und die 1-Wort Smart-Kacheln
        Livewire::test(GameGamesComponent::class)
            ->assertSet('hasOptedIn', true)
            ->assertSee('Deine Evolution')
            ->assertSee('Spieler-Cockpit')
            ->assertSee('GUIDE')
            ->assertSee('LEGENDE')
            ->assertSee('PRESTIGE')
            ->assertSee('HANGAR')
            ->assertSee('ENERGIE')
            ->assertSee('GUTHABEN');

        // Obere Navigation enthält keinen Guthaben-/Energie-Ballast mehr, sondern den direkten Spiele-Einstieg
        Livewire::test(GameGlobalStatsHeader::class)
            ->assertSet('hasOptedIn', true)
            ->assertSee('Spiele')
            ->assertDontSee('Deine Energie')
            ->assertDontSee('Dein Guthaben');
    }

    public function test_legacy_subpages_redirect_into_unified_spieler_cockpit(): void
    {
        $this->profile->update(['is_active' => true]);

        Livewire::test(GameProfileComponent::class)
            ->assertRedirect(route('customer.games'));

        Livewire::test(GameGlobalRankingComponent::class)
            ->assertRedirect(route('customer.games') . '?panel=ranking');
    }

    public function test_hangar_ship_skins_auras_and_mottos_can_be_purchased_and_equipped(): void
    {
        $this->profile->update([
            'is_active'      => true,
            'funken_balance' => 2500,
            'level'          => 5,
        ]);

        // 1. Saphir-Kristalljäger (450 Funken) kaufen & ausrüsten
        $comp = Livewire::test(GameGamesComponent::class)
            ->call('buyOrEquipSkin', 'crystal_fighter')
            ->assertDispatched('ship-skin-changed');

        $this->profile->refresh();
        $this->assertEquals('crystal_fighter', $this->profile->active_ship_skin);
        $this->assertContains('crystal_fighter', $this->profile->unlocked_skins ?? []);
        $this->assertEquals(2050, $this->profile->funken_balance);

        // 2. Ruhmes-Aura "Sternensaphir-Schimmer" (500 Funken) kaufen & aktivieren
        $comp->call('buyOrEquipAura', 'starlight_rain');
        $this->profile->refresh();
        $this->assertEquals('starlight_rain', $this->profile->active_hall_aura);
        $this->assertContains('starlight_rain', $this->profile->unlocked_hall_auras ?? []);
        $this->assertEquals(1550, $this->profile->funken_balance);

        // 3. Helden-Motto wechseln
        $comp->call('selectHeroMotto', 'lord');
        $this->profile->refresh();
        $this->assertEquals('lord', $this->profile->hero_motto_key);
    }

    public function test_prestige_progression_beyond_level_10_and_hall_of_legends_integration(): void
    {
        $this->profile->update([
            'is_active'            => true,
            'level'                => 10,
            'funken_balance'       => 5000,
            'funken_total_earned'  => 8500,
            'funkenflug_highscore' => 4600,
        ]);

        $service = app(GamificationService::class);

        // Aufstieg auf Prestige-Level 11
        $res = $service->upgradeLevel($this->customer);
        $this->assertTrue($res['success']);
        $this->profile->refresh();
        $this->assertEquals(11, $this->profile->level);

        // Charakter-Modell bleibt ab Level 10 auf dem höchsten Gott-Modell (funki_lvl_10_god_of_soul)
        $comp = Livewire::test(GameGamesComponent::class);
        $this->assertStringContainsString('funki_lvl_10_god_of_soul.glb', $comp->get('modelPath'));

        // Halle der Legenden Opt-In & Tab-Wechsel im Spieler-Cockpit
        $comp->call('optInRanking')
            ->assertSet('rankingOptIn', true)
            ->call('setRankingTab', 'funkenflug')
            ->assertSet('rankingTab', 'funkenflug');

        $this->profile->refresh();
        $this->assertTrue((bool) $this->profile->ranking_opt_in);

        // Helden-Visitenkarte (Inspector Payload) liefert alle Prestige-Daten
        $instance = $comp->instance();
        $payload = $instance->getInspectorPayload($this->profile, 1);
        $this->assertEquals(1, $payload['rank']);
        $this->assertEquals(11, $payload['level']);
        $this->assertEquals('Seelengott', $payload['prestige_name']);
        $this->assertEquals('4.600', $payload['highscore']);
    }

    public function test_all_twelve_spaceship_skins_have_valid_3d_glb_assets_and_categories(): void
    {
        $skins = GameConfig::getShipSkins();
        $this->assertCount(12, $skins);
        $this->assertCount(6, GameConfig::getFighterSkinKeys());
        $this->assertCount(6, GameConfig::getBattleshipSkinKeys());
        $this->assertCount(3, GameConfig::getSpaceStationsPlaceholder());

        foreach ($skins as $key => $skin) {
            $glbPath = public_path('shop/customer/gamification/images/' . $skin['glb_file']);
            $this->assertFileExists($glbPath, "3D GLB file missing for ship skin [{$key}]");
            $this->assertContains($skin['category'], ['fighter', 'battleship']);
            if ($key !== 'default') {
                $this->assertTrue($skin['is_vertex_colored'], "Custom ship [{$key}] should be marked is_vertex_colored");
            }
        }

        // VIP Elite-Schlachtschiff "Dark Perl" prüfen
        $this->assertArrayHasKey('dark_perl', $skins);
        $darkPerl = $skins['dark_perl'];
        $this->assertEquals('battleship', $darkPerl['category']);
        $this->assertEquals('Dark Perl', $darkPerl['name']);
        $this->assertEquals(7, $darkPerl['max_hearts']);
        $this->assertEquals(2.6, $darkPerl['weapon_power_mult']);
        $this->assertEquals(5, $darkPerl['salvo_streams']);
    }

    public function test_battleship_category_requires_all_three_premises_and_unlocks_dark_perl(): void
    {
        $service = app(GamificationService::class);

        $this->profile->update([
            'is_active'      => true,
            'level'          => 55,
            'funken_balance' => 30000,
            'unlocked_skins' => ['default', 'crystal_fighter', 'slate_phantom'], // Noch nicht alle 6 Jäger!
        ]);

        // Ohne die 3 Prämissen ist der Kauf eines Schlachtschiffs blockiert
        $status0 = $service->resolveBattleshipUnlockStatus($this->profile);
        $this->assertFalse($status0['is_unlocked']);
        $this->assertEquals(0, $status0['completed_premises']);

        $attemptBlocked = $service->buyOrEquipShipSkin($this->customer, 'aegis_titan');
        $this->assertFalse($attemptBlocked['success']);

        // Prämisse 1: Verifizierter Einkauf
        \App\Models\Order\OrderOrder::create([
            'order_number'     => 'SF-TEST-' . uniqid(),
            'customer_id'      => $this->customer->id,
            'email'            => $this->customer->email,
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'billing_address'  => [],
            'shipping_address' => [],
            'subtotal_price'   => 4990,
            'tax_amount'       => 797,
            'shipping_price'   => 0,
            'total_price'      => 4990,
        ]);

        // Prämisse 2: Verifizierte 5-Sterne-Bewertung
        $product = \App\Models\Product\Product::create([
            'name'        => 'Kristallglas Unikat',
            'slug'        => 'kristallglas-unikat-' . uniqid(),
            'description' => 'Handgefertigtes Unikat',
            'price'       => 4990,
            'status'      => 'active',
        ]);
        \App\Models\Product\ProductReview::create([
            'product_id'  => $product->id,
            'customer_id' => $this->customer->id,
            'rating'      => 5,
            'title'       => 'Atemberaubend!',
            'content'     => 'Wunderschöne Gravur.',
            'status'      => 'approved',
        ]);

        // Prämisse 3: Alle 6 Gleiter & Jäger freischalten
        $this->profile->update([
            'unlocked_skins' => GameConfig::getFighterSkinKeys(),
        ]);

        $this->profile->refresh();
        $status3 = $service->resolveBattleshipUnlockStatus($this->profile);
        $this->assertTrue($status3['is_unlocked']);
        $this->assertEquals(3, $status3['completed_premises']);

        // Jetzt kann das VIP-Schlachtschiff "Dark Perl" gekauft und ausgerüstet werden
        $resDarkPerl = $service->buyOrEquipShipSkin($this->customer, 'dark_perl');
        $this->assertTrue($resDarkPerl['success']);

        $this->profile->refresh();
        $this->assertEquals('dark_perl', $this->profile->active_ship_skin);
        $this->assertContains('dark_perl', $this->profile->unlocked_skins ?? []);
    }

    public function test_strict_gamification_separation_blocks_all_data_and_scripts_when_inactive(): void
    {
        // Selbst wenn ein Datensatz existiert (z. B. durch Ticket-Einstellungen) mit Level/Funken, aber is_active = false:
        $this->profile->update([
            'is_active'           => false,
            'level'               => 8,
            'funken_balance'      => 5000,
            'active_ship_skin'    => 'phoenix_cruiser',
            'unlocked_skins'      => ['default', 'phoenix_cruiser'],
            'active_hall_aura'    => 'solar_emperor',
            'unlocked_hall_auras' => ['none', 'solar_emperor'],
            'hero_motto_key'      => 'immortal',
        ]);

        $service = app(GamificationService::class);

        // 1. Service gibt bei inaktivem Spielmodus niemals Rangtitel oder Hangar-Daten preis
        $this->assertNull($service->resolveActiveRankName($this->profile));
        $hangarData = $service->resolveHangarData($this->profile);
        $this->assertEquals('default', $hangarData['active_skin_key']);
        $this->assertEquals(['default'], $hangarData['unlocked_skins']);
        $this->assertEquals('default', $hangarData['active_aura_key']);
        $this->assertEquals('default', $hangarData['active_motto_key']);

        // 2. Service-Mutationen (Level-Up, Hangar-Käufe, Auren & Motto) werden bei inaktivem Spielmodus strikt abgelehnt
        $upgradeRes = $service->upgradeLevel($this->customer);
        $this->assertFalse($upgradeRes['success']);
        $skinRes = $service->buyOrEquipShipSkin($this->customer, 'crystal_fighter');
        $this->assertFalse($skinRes['success']);
        $auraRes = $service->buyOrEquipHallAura($this->customer, 'starlight_rain');
        $this->assertFalse($auraRes['success']);
        $mottoRes = $service->selectHeroMotto($this->customer, 'lord');
        $this->assertFalse($mottoRes['success']);

        // 3. HTML-Output unter /games lädt bei inaktivem Modus KEINE 3D-Spiele-Engines oder Cockpit-Modals
        $comp = Livewire::test(GameGamesComponent::class)
            ->assertSet('hasOptedIn', false)
            ->assertSee('Einkaufen,')
            ->assertDontSee('Spieler-Cockpit')
            ->assertDontSee('FunkenflugEngine')
            ->assertDontSee('open-funki-modal');

        // 4. Sämtliche Aktionen/Mutationen sind bei inaktivem Spielmodus gesperrt
        $comp->call('upgrade')
            ->call('buyOrEquipSkin', 'crystal_fighter')
            ->call('buyOrEquipAura', 'starlight_rain')
            ->call('selectHeroMotto', 'lord');

        $this->profile->refresh();
        $this->assertEquals(8, $this->profile->level);
        $this->assertEquals(5000, $this->profile->funken_balance);
        $this->assertNotContains('crystal_fighter', $this->profile->unlocked_skins ?? []);
    }
}
