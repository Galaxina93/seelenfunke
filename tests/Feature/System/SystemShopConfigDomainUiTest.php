<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        SystemSetting::updateOrCreate(['key' => 'digital_domain_is_live'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'showroom_multiplayer_enabled'], ['value' => 'true']);
    }

    public function test_system_shop_config_renders_clean_domain_and_live_status_architecture(): void
    {
        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Livewire\Shop\System\SystemShopConfig::class);

        $html = $component->html();

        // 1. Klare Trennung: Shop-Domains & Livegang-Status als eigene Karte
        $this->assertStringContainsString('Shop-Domains &amp; Livegang-Status', $html);
        $this->assertStringContainsString('2-Kanal-Architektur', $html);

        // 2. Personalisierter Shop auf mein-seelenfunke.de
        $this->assertStringContainsString('mein-seelenfunke.de', $html);
        $this->assertStringContainsString('Personalisierter Shop (Live-Freigabe)', $html);

        // 3. Digitaler Shop auf digital.mein-seelenfunke.de
        $this->assertStringContainsString('digital.mein-seelenfunke.de', $html);
        $this->assertStringContainsString('Digitaler Shop (Live-Freigabe)', $html);

        // 4. Globaler Wartungsmodus (Alle Domains)
        $this->assertStringContainsString('Alle Domains', $html);
        $this->assertStringContainsString('Wartungsmodus (HTTP 503)', $html);

        // 5. 3D-Showroom Mehrspielermodus
        $this->assertStringContainsString('3D-Welt', $html);
        $this->assertStringContainsString('3D-Multiplayer (12er-Räume &amp; Chat)', $html);

        // 6. Getrennte Karte für Steuern & Finanzen
        $this->assertStringContainsString('Steuern &amp; Finanzen (§ 19 UStG &amp; MwSt-Sätze)', $html);
        $this->assertStringContainsString('Kleinunternehmerregelung', $html);
    }

    public function test_toggling_switches_updates_state_without_errors(): void
    {
        Livewire::actingAs($this->admin, 'admin')
            ->test(\App\Livewire\Shop\System\SystemShopConfig::class)
            ->call('toggleShopIsLive')
            ->assertSet('shop_is_live', true)
            ->call('toggleDigitalDomainIsLive')
            ->assertSet('digital_domain_is_live', true)
            ->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', true);

        $this->assertEquals('true', SystemSetting::where('key', 'shop_is_live')->value('value'));
        $this->assertEquals('true', SystemSetting::where('key', 'digital_domain_is_live')->value('value'));
        $this->assertEquals('true', SystemSetting::where('key', 'maintenance_mode')->value('value'));
    }
}
