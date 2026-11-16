<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class ShopMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-maint-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    private function createActiveProduct(): Product
    {
        return Product::create([
            'name' => 'Seelen Kristall Wartungstest',
            'slug' => 'seelen-kristall-wartung-' . Str::lower(Str::random(5)),
            'type' => 'physical',
            'status' => 'active',
            'price' => 4990,
            'width' => 80,
            'height' => 80,
        ]);
    }

    public function test_maintenance_switch_in_system_shop_config_is_admin_only_and_persists_immediately(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->get('/admin/configuration')
            ->assertRedirect();

        $admin = $this->createAdmin();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSet('settings.maintenance_mode', false)
            ->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', true);

        $this->assertSame('true', SystemSetting::where('key', 'maintenance_mode')->value('value'));
        $this->assertTrue(filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN));

        $component->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', false);

        $this->assertSame('false', SystemSetting::where('key', 'maintenance_mode')->value('value'));
        $this->assertFalse(filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN));
    }

    public function test_active_maintenance_mode_returns_503_light_mode_page_for_guests_and_customers_with_retry_after(): void
    {
        $product = $this->createActiveProduct();

        // Im Normalzustand (Standard = false) sind Startseite und Produktseite normal erreichbar (200)
        $this->get('/')->assertStatus(200);
        $this->get('/produkt/' . $product->slug)->assertStatus(200);

        // Wartungsmodus aktivieren
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        // 1. Gast erhält auf '/' und Produktseite HTTP 503 + Retry-After + Light-Mode-Wartungsseite (inkl. § 5 DDG Angaben)
        $homeResp = $this->get('/');
        $homeResp->assertStatus(503)
            ->assertHeader('Retry-After', '3600')
            ->assertSee('Wir bereiten gerade unseren Shop für dich vor')
            ->assertSee('Alina Steinhauer')
            ->assertSee('38518 Gifhorn')
            ->assertSee('Impressum')
            ->assertSee('Datenschutz')
            ->assertDontSee(' – ');

        $this->assertStringContainsString('class="light"', $homeResp->getContent());

        $prodResp = $this->get('/produkt/' . $product->slug);
        $prodResp->assertStatus(503)
            ->assertHeader('Retry-After', '3600');

        $this->get('/shop')->assertStatus(503);
        $this->get('/warenkorb')->assertStatus(503);

        // 2. Auch eingeloggte reguläre Kunden (Customer) erhalten während der Wartung HTTP 503 auf Shop-Seiten
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->get('/')
            ->assertStatus(503);

        $this->actingAs($customer, 'customer')
            ->get('/produkt/' . $product->slug)
            ->assertStatus(503);
    }

    public function test_authenticated_admin_bypasses_maintenance_mode_and_login_admin_and_apis_remain_reachable(): void
    {
        $product = $this->createActiveProduct();
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        // 1. Login-Seite & Pflichtseiten bleiben selbst für nicht eingeloggte Besucher erreichbar (kein Aussperren!)
        $this->get('/login')->assertStatus(200);
        $this->get('/impressum')->assertStatus(200);
        $this->get('/datenschutz')->assertStatus(200);

        // 2. Eingeloggte Administratorin sieht den gesamten öffentlichen Shop + Admin-Routen mit HTTP 200
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin')->get('/')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('/shop')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('/produkt/' . $product->slug)->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('/admin/configuration')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('/admin/settings')->assertStatus(200);

        // 3. Funkenwerk / AG-Direct-Inbox REST-API bleibt trotz Wartungsmodus zu 100 % aktiv!
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin, ['*']);
        $this->getJson('/api/system/ag-direct-inbox')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
