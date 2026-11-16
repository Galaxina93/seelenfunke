<?php

namespace Tests\Feature\System;

use App\Console\Commands\SystemPrepareLiveCommand;
use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Logistics\LogisticsShippingZone;
use App\Models\Marketing\MarketingInfluencer;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemSetting;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class LivePreparationMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $hadImplicitCommit = false;
        try {
            $hadImplicitCommit = !DB::connection()->getPdo()->inTransaction();
        } catch (\Throwable) {
        }

        parent::tearDown();

        if ($hadImplicitCommit) {
            RefreshDatabaseState::$migrated = false;
        }
    }

    public function test_system_prepare_live_seeds_pure_production_data_enforces_maintenance_mode_and_purges_test_accounts(): void
    {
        // 1. Erstinitialisierung auf frischer Datenbank
        $exitCode = Artisan::call('system:prepare-live');
        $this->assertSame(0, $exitCode, Artisan::output());

        // Wartungsmodus ist hart aktiv und übersteht erneutes Ausführen von SystemSettingSeeder
        $this->assertSame('true', SystemSetting::where('key', 'maintenance_mode')->value('value'));
        $this->assertTrue(filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN));

        $this->seed(SystemSettingSeeder::class);
        $this->assertSame('true', SystemSetting::where('key', 'maintenance_mode')->value('value'));
        $this->assertTrue(filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN));

        // Alina als einziger Admin mit korrekter UUID & Gifhorn-Profil angelegt
        $admin = Admin::where('email', SystemPrepareLiveCommand::ALINA_ADMIN_EMAIL)->first();
        $this->assertNotNull($admin);
        $this->assertSame(SystemPrepareLiveCommand::ALINA_ADMIN_ID, $admin->id);
        $this->assertSame(1, Admin::count());
        $this->assertSame('Gifhorn', $admin->profile?->city);

        // Echte Produktions-Stammdaten vorhanden
        $this->assertGreaterThan(0, Product::count());
        $this->assertGreaterThan(0, ProductCategory::count());
        $this->assertGreaterThan(0, ProductTemplate::count());
        $this->assertGreaterThan(0, LogisticsShippingZone::where('is_active', true)->count());
        $this->assertGreaterThan(0, DB::table('tax_rates')->count());

        // 0 Test-Accounts, 0 LARA10, 0 Fake-Bestellungen
        $this->assertSame(0, Employee::withTrashed()->whereIn('email', SystemPrepareLiveCommand::FORBIDDEN_EMPLOYEE_EMAILS)->count());
        $this->assertSame(0, MarketingInfluencer::where('slug', 'lara-living')->count());
        $this->assertSame(0, MarketingVoucher::where('code', 'LARA10')->count());
        $this->assertSame(0, Customer::withTrashed()->where('first_name', 'Sarah')->where('last_name', 'Sonnenschein')->count());
        $this->assertSame(0, OrderOrder::count());

        // 2. Schutz übernommener Stage-Credentials (Passwort-Hash + 2FA) & Bereinigung eingeschleuster Test-Artefakte
        $stagePasswordHash = Hash::make('AlinaStageLiveSecret!2026');
        $admin->forceFill(['password' => $stagePasswordHash])->save();
        $admin->profile()->update([
            'two_factor_is_active' => true,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $testEmp = Employee::create([
            'first_name' => 'Lara',
            'last_name' => 'Living',
            'email' => 'influencer@mein-seelenfunke.de',
            'role' => 'influencer',
            'password' => Hash::make('Test1234!'),
        ]);
        MarketingInfluencer::create([
            'employee_id' => $testEmp->id,
            'handle' => '@lara.living',
            'slug' => 'lara-living',
            'primary_channel' => 'instagram',
            'is_active' => true,
        ]);
        MarketingVoucher::create([
            'code' => 'LARA10',
            'title' => 'Lara Test',
            'type' => 'percent',
            'value' => 10,
            'mode' => 'manual',
            'is_active' => true,
        ]);
        Customer::create([
            'first_name' => 'Sarah',
            'last_name' => 'Sonnenschein',
            'email' => 'alina.stone@t-online.de',
            'password' => Hash::make('Test1234!'),
        ]);

        // --verify-only erkennt die verbotenen Test-Einträge ohne Schreibzugriff (Exit-Code 1)
        $this->assertSame(1, Artisan::call('system:prepare-live', ['--verify-only' => true]));

        // Idempotenter Zweitlauf bereinigt alle Test-Artefakte und lässt Alinas Passwort & 2FA unangetastet
        $this->assertSame(0, Artisan::call('system:prepare-live'));

        $freshAdmin = Admin::where('email', SystemPrepareLiveCommand::ALINA_ADMIN_EMAIL)->firstOrFail();
        $freshProfile = AdminProfile::where('admin_id', $freshAdmin->id)->firstOrFail();
        $this->assertSame($stagePasswordHash, $freshAdmin->password);
        $this->assertTrue((bool) $freshProfile->two_factor_is_active);
        $this->assertSame('JBSWY3DPEHPK3PXP', $freshProfile->two_factor_secret);

        $this->assertSame(0, Employee::withTrashed()->whereIn('email', SystemPrepareLiveCommand::FORBIDDEN_EMPLOYEE_EMAILS)->count());
        $this->assertSame(0, MarketingInfluencer::where('slug', 'lara-living')->count());
        $this->assertSame(0, MarketingVoucher::where('code', 'LARA10')->count());
        $this->assertSame(0, Customer::withTrashed()->where('first_name', 'Sarah')->where('last_name', 'Sonnenschein')->count());

        $this->assertSame(0, Artisan::call('system:prepare-live', ['--verify-only' => true]));
    }

    public function test_public_visitor_receives_503_noindex_maintenance_page_and_lockout_safe_routes_return_200(): void
    {
        $this->seed(SystemSettingSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        // Öffentlicher Besucher erhält auf / HTTP 503 + X-Robots-Tag + Wartungstext im Light-Mode
        $response = $this->get('/');
        $response->assertStatus(503)
            ->assertHeader('X-Robots-Tag', 'noindex, noarchive')
            ->assertHeader('Retry-After', '3600')
            ->assertSee('Wir bereiten gerade unseren Shop für dich vor')
            ->assertSee('Alina Steinhauer')
            ->assertDontSee(' – ');

        $this->assertStringContainsString('class="light"', $response->getContent());

        // Aussperr-Schutz: Login & gesetzliche Pflichtseiten bleiben trotz Wartungsmodus auf 200
        $this->get('/login')->assertStatus(200);
        $this->get('/impressum')->assertStatus(200);
        $this->get('/datenschutz')->assertStatus(200);
    }

    public function test_authenticated_alina_admin_receives_200_and_can_toggle_maintenance_mode_for_visitors(): void
    {
        $this->seed(SystemSettingSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        $admin = Admin::withoutEvents(function (): Admin {
            $created = new Admin();
            $created->forceFill([
                'id' => SystemPrepareLiveCommand::ALINA_ADMIN_ID,
                'first_name' => 'Alina',
                'last_name' => 'Steinhauer',
                'email' => SystemPrepareLiveCommand::ALINA_ADMIN_EMAIL,
                'password' => Hash::make('SecretLive!2026'),
            ]);
            $created->save();

            return $created;
        });
        AdminProfile::firstOrCreate(['admin_id' => $admin->id], ['city' => 'Gifhorn', 'country' => 'DE']);

        // Besucher sieht zunächst 503
        $this->get('/')->assertStatus(503);

        // Eingeloggte Admin erhält auf / und /admin/settings HTTP 200
        $this->actingAs($admin, 'admin')->get('/')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('/admin/settings')->assertStatus(200);

        // Admin schaltet in SystemShopConfig per toggleMaintenanceMode den Wartungsmodus aus -> 200 für Besucher
        $component = Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSet('settings.maintenance_mode', true)
            ->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', false);

        Auth::guard('admin')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get('/')->assertStatus(200);

        // Admin schaltet den Wartungsmodus wieder ein -> sofort 503 für Besucher
        $component = Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSet('settings.maintenance_mode', false)
            ->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', true);

        $this->assertNotNull($component);

        Auth::guard('admin')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('X-Robots-Tag', 'noindex, noarchive')
            ->assertSee('Wir bereiten gerade unseren Shop für dich vor');
    }

    public function test_htaccess_enforces_precise_apex_to_www_301_redirect_without_touching_subdomains_or_webhooks(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        $wsPos = strpos($htaccess, 'RewriteRule ^(.*)$ http://a-iurgq8:6001/$1 [P,L]');
        $canonicalPos = strpos($htaccess, 'RewriteCond %{HTTP_HOST} ^mein-seelenfunke\.de$ [NC]');

        $this->assertNotFalse($wsPos);
        $this->assertNotFalse($canonicalPos);
        $this->assertGreaterThan($wsPos, $canonicalPos);

        $this->assertStringContainsString('RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$ [NC]', $htaccess);
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/(api(/|$)|stripe(/|$)|twilio|up($|/)|\.well-known(/|$)) [NC]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^ https://www.mein-seelenfunke.de%{REQUEST_URI} [R=301,L]', $htaccess);
        $this->assertStringNotContainsString(' – ', $htaccess);

        // Host-Regex darf ausschließlich die exakte Apex-Domain mein-seelenfunke.de matchen
        $hostPattern = '/^mein-seelenfunke\.de$/i';
        $this->assertSame(1, preg_match($hostPattern, 'mein-seelenfunke.de'));
        $this->assertSame(1, preg_match($hostPattern, 'MEIN-SEELENFUNKE.DE'));
        $this->assertSame(0, preg_match($hostPattern, 'www.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, 'stage.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, 'ws.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, 'api-live-bridge.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, '127.0.0.1'));
        $this->assertSame(0, preg_match($hostPattern, 'steinhauer-invest.de'));

        // Pfad-Ausnahmen schützen APIs, Webhooks, Healthcheck und Let's Encrypt ACME-Challenges
        $excludedUriPattern = '#^/(api(/|$)|stripe(/|$)|twilio|up($|/)|\.well-known(/|$))#i';
        $this->assertSame(1, preg_match($excludedUriPattern, '/api/system/ag-direct-inbox'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/stripe/webhook'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/twilio-stream'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/up'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/.well-known/acme-challenge/token-xyz'));
        $this->assertSame(0, preg_match($excludedUriPattern, '/'));
        $this->assertSame(0, preg_match($excludedUriPattern, '/shop'));
        $this->assertSame(0, preg_match($excludedUriPattern, '/produkt/seelen-kristall'));
    }
}
