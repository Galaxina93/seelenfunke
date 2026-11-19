<?php

namespace Tests\Feature\System;

use App\Http\Middleware\EnforceShopMaintenanceMode;
use App\Models\Admin\Admin;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class SystemShopConfigDomainUiTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create([
            'email' => 'admin@mein-seelenfunke.de',
        ]);

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'showroom_multiplayer_enabled'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'quote_calculator_enabled'], ['value' => 'true']);
        Cache::forget('global_shop_settings');
    }

    public function test_system_shop_config_renders_clean_domain_and_live_status_architecture(): void
    {
        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Livewire\Shop\System\SystemShopConfig::class);

        $html = $component->html();

        // 1. Klare Trennung: Shop-Domains & Livegang-Status mit 4 Kacheln (grid-cols-1 md:grid-cols-2 xl:grid-cols-4)
        $this->assertStringContainsString('Shop-Domains &amp; Livegang-Status', $html);
        $this->assertStringContainsString('2-Stufen-Schutz', $html);
        $this->assertStringContainsString('grid-cols-1 md:grid-cols-2 xl:grid-cols-4', $html);

        // 2. Kachel 1: Personalisierter Shop auf mein-seelenfunke.de (ohne verworfene Subdomain-Kachel)
        $this->assertStringContainsString('mein-seelenfunke.de', $html);
        $this->assertStringContainsString('Personalisierter Shop (Live)', $html);
        $this->assertStringContainsString('In Vorbereitung (Gesperrt)', $html);
        $this->assertStringNotContainsString('Digitaler Shop (Live-Freigabe)', $html);

        // 3. Kachel 2: Schaufenster-Wartungsmodus (Alle Domains)
        $this->assertStringContainsString('Alle Domains', $html);
        $this->assertStringContainsString('Wartungsmodus (Schaufenster)', $html);
        $this->assertStringContainsString('Inaktiv (Regulär)', $html);
        $this->assertStringNotContainsString('Wartungsmodus (HTTP 503)', $html);

        // 4. Kachel 3: 3D-Showroom Mehrspielermodus
        $this->assertStringContainsString('3D-Welt', $html);
        $this->assertStringContainsString('3D-Multiplayer (Halle &amp; Chat)', $html);

        // 5. Kachel 4: Angebotskalkulator (Frontend)
        $this->assertStringContainsString('/calculator', $html);
        $this->assertStringContainsString('Angebotskalkulator (Frontend)', $html);
        $this->assertStringContainsString('Aktiv (Sichtbar)', $html);

        // 6. Getrennte Karte für Steuern & Finanzen
        $this->assertStringContainsString('Steuern &amp; Finanzen (§ 19 UStG &amp; MwSt-Sätze)', $html);
        $this->assertStringContainsString('Kleinunternehmerregelung', $html);
        foreach ([
            resource_path('views/livewire/shop/system/system-config-partials/general_tab.blade.php'),
            resource_path('views/livewire/shop/system/system-config-partials/system_tab.blade.php'),
            app_path('Livewire/Shop/System/SystemShopConfig.php'),
            app_path('Http/Middleware/EnforceShopMaintenanceMode.php'),
        ] as $checkedFile) {
            $this->assertStringNotContainsString(" \u{2013} ", (string) file_get_contents($checkedFile));
        }
    }

    public function test_toggling_switches_updates_state_without_errors(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Livewire\Shop\System\SystemShopConfig::class)
            ->call('toggleShopIsLive')
            ->assertSet('shop_is_live', true)
            ->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', true)
            ->assertSee('Aktiv (Kauf pausiert)')
            ->call('toggleQuoteCalculator')
            ->assertSet('settings.quote_calculator_enabled', false)
            ->assertSee('Gesperrt (Ausgeblendet)');

        $this->assertEquals('true', SystemSetting::where('key', 'shop_is_live')->value('value'));
        $this->assertEquals('true', SystemSetting::where('key', 'maintenance_mode')->value('value'));
        $this->assertEquals('false', SystemSetting::where('key', 'quote_calculator_enabled')->value('value'));
        $this->assertTrue(EnforceShopMaintenanceMode::isMaintenanceModeActive());
        $this->assertFalse(EnforceShopMaintenanceMode::isQuoteCalculatorEnabled());
    }

    public function test_disabling_quote_calculator_hides_frontend_buttons_blocks_route_404_and_removes_from_seo(): void
    {
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'quote_calculator_enabled'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        // 1. Aktiviert: Navigation, Footer, Startseite, /calculator (200), /sitemap.xml und /llms.txt zeigen den Kalkulator
        $homeActive = $this->get('/');
        $homeActive->assertStatus(200)
            ->assertSee('Angebot kalkulieren')
            ->assertSee('B2B Projekt-Kalkulator')
            ->assertSee('Angebotskalkulator öffnen')
            ->assertSee('Jetzt Serien-Preis berechnen')
            ->assertSee('Jetzt Preis berechnen');

        $this->get('/calculator')->assertStatus(200);
        $this->get('/sitemap.xml')->assertStatus(200)->assertSee('/calculator');
        $this->get('/llms.txt')->assertStatus(200)->assertSee('/calculator');

        // 2. Über Kachel 4 in SystemShopConfig deaktivieren
        Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Livewire\Shop\System\SystemShopConfig::class)
            ->assertSet('settings.quote_calculator_enabled', true)
            ->call('toggleQuoteCalculator')
            ->assertSet('settings.quote_calculator_enabled', false)
            ->assertSee('Gesperrt (Ausgeblendet)');

        // 3. Deaktiviert: Alle Frontend-Buttons verschwinden, /calculator liefert 404, Sitemap & llms.txt bereinigt
        $homeDisabled = $this->get('/');
        $homeDisabled->assertStatus(200)
            ->assertDontSee('Angebot kalkulieren')
            ->assertDontSee('B2B Projekt-Kalkulator')
            ->assertDontSee('Angebotskalkulator öffnen')
            ->assertDontSee('Jetzt Serien-Preis berechnen')
            ->assertDontSee('Jetzt Preis berechnen')
            ->assertDontSee('href="/calculator"', false)
            ->assertDontSee(route('calculator'), false);

        $this->get('/calculator')->assertStatus(404);
        $this->get('/sitemap.xml')->assertStatus(200)->assertDontSee('/calculator');
        $this->get('/llms.txt')->assertStatus(200)->assertDontSee('/calculator');

        // Direkte Angebotsannahme-Route (/angebot/{token}/annehmen) bleibt weiterhin registriert & nicht durch Kalkulator-Schalter gesperrt
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('quote.accept'));
        $this->get('/angebot/test-token-check/annehmen')->assertStatus(200);
    }
}

