<?php

namespace Tests\Feature\System;

use App\Http\Controllers\GoogleAuthController;
use App\Livewire\Auth\AuthLogin;
use App\Livewire\Auth\AuthRegister;
use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\AccountingXmlInvoiceService;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShopMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(?string $email = null): Admin
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => $email ?? ('alina-maint-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de'),
            'password' => Hash::make('Secret123!'),
        ]);

        AdminProfile::firstOrCreate(
            ['admin_id' => $admin->id],
            ['city' => 'Gifhorn', 'country' => 'DE']
        );

        return $admin;
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

    public function test_both_switches_in_system_shop_config_are_admin_only_and_persist_immediately(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')
            ->get('/admin/settings')
            ->assertRedirect();

        $admin = $this->createAdmin('kontakt@mein-seelenfunke.de');

        $component = Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSet('shop_is_live', true)
            ->assertSet('settings.shop_is_live', true)
            ->assertSet('settings.maintenance_mode', false)
            ->assertSee('Live gehen')
            ->assertSee('Live')
            ->assertSee('Wartungsmodus')
            ->assertSee('Zweiter Hauptschalter vor Verkaufsstart:');

        foreach ([
            resource_path('views/livewire/shop/system/system-shop-config.blade.php'),
            resource_path('views/livewire/shop/system/system-config-partials/general_tab.blade.php'),
            resource_path('views/livewire/shop/system/system-config-partials/system_tab.blade.php'),
        ] as $viewPath) {
            $this->assertStringNotContainsString(' – ', (string) file_get_contents($viewPath));
        }

        // 1. Live gehen per Toggle auf false schalten (Gesperrt)
        $component->call('toggleShopIsLive')
            ->assertSet('shop_is_live', false)
            ->assertSet('settings.shop_is_live', false)
            ->assertSet('settings.maintenance_mode', false)
            ->assertSee('Gesperrt');

        $this->assertSame('false', SystemSetting::where('key', 'shop_is_live')->value('value'));
        $this->assertFalse(filter_var(shop_setting('shop_is_live', true), FILTER_VALIDATE_BOOLEAN));

        // 2. Separat den regulären Wartungsmodus ein- und ausschalten
        $component->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', true)
            ->assertSet('shop_is_live', false);

        $this->assertSame('true', SystemSetting::where('key', 'maintenance_mode')->value('value'));
        $this->assertTrue(filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN));

        // 3. Persistenz via Property-Update und save()
        $component->set('shop_is_live', true)
            ->call('toggleMaintenanceMode')
            ->call('save')
            ->assertSet('shop_is_live', true)
            ->assertSet('settings.shop_is_live', true)
            ->assertSet('settings.maintenance_mode', false);

        $this->assertSame('true', SystemSetting::where('key', 'shop_is_live')->value('value'));
        $this->assertSame('false', SystemSetting::where('key', 'maintenance_mode')->value('value'));
    }

    public function test_live_domain_fails_closed_and_blocks_even_logged_in_admin_on_frontend_when_shop_is_live_is_false(): void
    {
        $product = $this->createActiveProduct();
        $admin = $this->createAdmin('kontakt@mein-seelenfunke.de');

        // 0. Fail-Closed ohne DB-Eintrag: Auf mein-seelenfunke.de & www.mein-seelenfunke.de sofort 503 (auch ohne maintenance_mode!)
        Cache::forget('global_shop_settings');
        $this->get('https://mein-seelenfunke.de/')->assertStatus(503);
        $this->get('https://www.mein-seelenfunke.de/')->assertStatus(503);
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/')->assertStatus(503);
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/shop')->assertStatus(503);

        // 1. Explizit shop_is_live = false und maintenance_mode = false auf Live-Domain
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        foreach (['/', '/shop', '/produkt/' . $product->slug, '/warenkorb', '/checkout', '/links', '/kontakt'] as $frontendUri) {
            // Gast erhält 503 Wartungsseite
            $guestResp = $this->get('https://mein-seelenfunke.de' . $frontendUri);
            $guestResp->assertStatus(503, "Gast muss auf {$frontendUri} HTTP 503 erhalten")
                ->assertHeader('X-Robots-Tag', 'noindex, noarchive')
                ->assertHeader('Retry-After', '3600')
                ->assertDontSee(' – ');

            // SELBST EINGELOGGTER ADMIN erhält auf dem öffentlichen Frontend HTTP 503!
            $adminResp = $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de' . $frontendUri);
            $adminResp->assertStatus(503, "Eingeloggter Admin muss bei shop_is_live=false auf {$frontendUri} HTTP 503 erhalten")
                ->assertSee('Wir bereiten gerade unseren Shop für dich vor');
        }

        // 2. Pflichtseiten & Login bleiben für Gäste auf 200 erreichbar, /admin/settings für Admin auf 200!
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get('https://mein-seelenfunke.de/login')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/impressum')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/datenschutz')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/barrierefreiheit')->assertStatus(200);

        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/admin/settings')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/admin/configuration')->assertStatus(200);

        // Klickt ein bereits eingeloggter Admin auf der Wartungsseite auf /login ("Verwaltung"), landet er im Admin-Cockpit statt in einer 503-Schleife
        $this->actingAs($admin, 'admin')
            ->get('https://mein-seelenfunke.de/login')
            ->assertRedirect('/admin/dashboard');

        // Funkenwerk REST-API bleibt aktiv
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin, ['*']);
        $this->getJson('https://mein-seelenfunke.de/api/system/ag-direct-inbox')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_three_stage_matrix_on_live_domain_for_guest_and_authenticated_admin(): void
    {
        $product = $this->createActiveProduct();
        $admin = $this->createAdmin('kontakt@mein-seelenfunke.de');
        $customer = Customer::factory()->create();

        // Stufe 2: shop_is_live = true + maintenance_mode = true -> Schaufenster-Wartungsmodus (HTTP 200 für Gast, Kunde & Admin)!
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        $this->assertTrue(\App\Http\Middleware\EnforceShopMaintenanceMode::isMaintenanceModeActive());
        $this->get('https://mein-seelenfunke.de/')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/shop')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/produkt/' . $product->slug)->assertStatus(200);
        $this->actingAs($customer, 'customer')->get('https://mein-seelenfunke.de/')->assertStatus(200);

        // Eingeloggte Administratorin sieht ebenfalls regulär HTTP 200
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/shop')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/produkt/' . $product->slug)->assertStatus(200);

        // Stufe 3: shop_is_live = true + maintenance_mode = false -> Öffentlich Live (Gast 200, isMaintenanceModeActive = false)!
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        $this->assertFalse(\App\Http\Middleware\EnforceShopMaintenanceMode::isMaintenanceModeActive());
        $this->get('https://mein-seelenfunke.de/')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/shop')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/produkt/' . $product->slug)->assertStatus(200);
    }

    public function test_login_hides_register_and_google_when_not_live_blocks_server_side_bypass_and_allows_admin_login_with_2fa(): void
    {
        $this->seed(SystemSettingSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        // 1. /login liefert 200, zeigt aber weder Konto erstellen noch Google
        $loginResp = $this->get('/login');
        $loginResp->assertStatus(200)
            ->assertSee('Verwaltung')
            ->assertSee('Anmelden')
            ->assertDontSee('Konto erstellen')
            ->assertDontSee('Jetzt hier registrieren')
            ->assertDontSee('/register')
            ->assertDontSee('Google')
            ->assertDontSee('/auth/')
            ->assertDontSee(' – ');

        // 1b. /register und Google-OAuth-Routen liefern direkt HTTP 503 + noindex, noarchive
        foreach (['/register', route('auth.google', ['guard' => 'customer'], false), route('google.callback', [], false)] as $lockedAuthUri) {
            $this->get($lockedAuthUri)
                ->assertStatus(503)
                ->assertHeader('X-Robots-Tag', 'noindex, noarchive');
        }

        // 1c. /impressum liefert 200 mit reduzierter Manufaktur-Hülle (kein Shop-Header/Warenkorb/Registrierung) und USt-ID 'XXX'
        $this->get('/impressum')
            ->assertStatus(200)
            ->assertSee('XXX')
            ->assertDontSee('DE359662265')
            ->assertDontSee('DE459668247')
            ->assertDontSee('Angebot kalkulieren')
            ->assertDontSee('Registrieren')
            ->assertDontSee(' – ');

        // 2. Harte Server-Sperre auf Livewire AuthRegister und GoogleAuthController
        $initialCustomerCount = Customer::count();
        Livewire::test(AuthRegister::class)->assertStatus(503);
        $this->assertSame($initialCustomerCount, Customer::count());

        $controller = app(GoogleAuthController::class);
        try {
            $controller->redirectToGoogle('customer');
            $this->fail('GoogleAuthController::redirectToGoogle muss HTTP 503 werfen.');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        try {
            $controller->handleGoogleCallback();
            $this->fail('GoogleAuthController::handleGoogleCallback muss HTTP 503 werfen.');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }

        // 3. Admin-Login (E-Mail + Passwort + 2FA) für Alina funktioniert zu 100 %
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $admin = $this->createAdmin('kontakt@mein-seelenfunke.de');
        $admin->profile()->update([
            'two_factor_is_active' => true,
            'two_factor_secret' => $secret,
        ]);

        Livewire::test(AuthLogin::class)
            ->set('email', 'kontakt@mein-seelenfunke.de')
            ->set('password', 'Secret123!')
            ->call('login')
            ->assertSet('activeView', 'twoFactor')
            ->set('code', $google2fa->getCurrentOtp($secret))
            ->call('twoFactorVerify')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_xml_invoice_service_omits_placeholder_xxx_ust_id_from_bt31_tax_registration(): void
    {
        $this->seed(SystemSettingSeeder::class);
        $this->assertSame('XXX', SystemSetting::where('key', 'owner_ust_id')->value('value'));

        $invoice = AccountingInvoice::create([
            'invoice_number' => 'RE-2026-9901',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now()->addDays(14),
            'subtotal' => 4193,
            'tax_amount' => 797,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'total' => 4990,
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Musterweg 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
                'email' => 'max@example.org',
            ],
            'custom_items' => [
                [
                    'product_name' => 'Kristall-Herz',
                    'quantity' => 1,
                    'unit_price' => 41.93,
                    'tax_rate' => 19,
                ],
            ],
        ]);

        $xmlPath = app(AccountingXmlInvoiceService::class)->generate($invoice);
        $xml = (string) \Illuminate\Support\Facades\Storage::disk('local')->get($xmlPath);
        $this->assertStringNotContainsString('>XXX<', $xml);
        $this->assertStringNotContainsString('schemeID="VA"', $xml);
        $this->assertStringContainsString('schemeID="FC"', $xml);
    }
}
