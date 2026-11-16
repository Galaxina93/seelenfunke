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
use App\Services\System\BlueprintZeroLeakScanner;
use App\Services\System\CleanRoomBlueprintBuilderService;
use App\Support\DigitalDomainContext;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_system_prepare_live_seeds_pure_production_data_enforces_two_key_lock_and_purges_test_accounts(): void
    {
        // 1. Erstinitialisierung auf frischer Datenbank
        $exitCode = Artisan::call('system:prepare-live');
        $this->assertSame(0, $exitCode, Artisan::output());

        // Beide Schutzschalter stehen sicher auf Live-Sperre (shop_is_live = false, maintenance_mode = true)
        // und überstehen ein erneutes Ausführen von SystemSettingSeeder!
        $this->assertSame('false', SystemSetting::where('key', 'shop_is_live')->value('value'));
        $this->assertFalse(filter_var(shop_setting('shop_is_live', true), FILTER_VALIDATE_BOOLEAN));
        $this->assertSame('true', SystemSetting::where('key', 'maintenance_mode')->value('value'));
        $this->assertTrue(filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN));

        $this->seed(SystemSettingSeeder::class);
        $this->assertSame('false', SystemSetting::where('key', 'shop_is_live')->value('value'));
        $this->assertFalse(filter_var(shop_setting('shop_is_live', true), FILTER_VALIDATE_BOOLEAN));
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

        // Digital-Shop (digital.mein-seelenfunke.de) fail-closed vorbereitet inkl. 5 Clean-Room Blueprint-Entwürfen & Zero-Leak-ZIPs
        $this->assertSame('0', (string) SystemSetting::where('key', 'digital_domain_is_live')->value('value'));
        $this->assertFalse(DigitalDomainContext::isDigitalDomainLive());
        $this->assertSame('digital.mein-seelenfunke.de', SystemSetting::where('key', 'digital_domain_host')->value('value'));

        $blueprintDrafts = Product::where('type', 'digital')
            ->where('collection_line', 'digital_blueprint')
            ->where('status', 'draft')
            ->get();
        $this->assertCount(5, $blueprintDrafts);

        foreach (array_keys(CleanRoomBlueprintBuilderService::getBlueprintDefinitions()) as $bpSlug) {
            $this->assertTrue(Storage::disk('local')->exists(CleanRoomBlueprintBuilderService::STORAGE_REL_DIR . '/' . $bpSlug . '.zip'));
            $this->assertTrue(Storage::disk('local')->exists(SystemPrepareLiveCommand::DIGITAL_BLUEPRINTS_DIR . '/' . $bpSlug . '.zip'));
        }
        $scanner = app(BlueprintZeroLeakScanner::class);
        $this->assertTrue($scanner->scanDirectory(Storage::disk('local')->path(SystemPrepareLiveCommand::DIGITAL_BLUEPRINTS_DIR))['clean']);

        // Gäste sehen auf digital.mein-seelenfunke.de 503, während Alina als Admin sofort HTTP 200 Vorschau (DE & EN) erhält
        $this->get('https://digital.mein-seelenfunke.de/shop')->assertStatus(503);
        $this->actingAs($admin, 'admin')->get('https://digital.mein-seelenfunke.de/shop')->assertStatus(200);
        $this->actingAs($admin, 'admin')->get('https://digital.mein-seelenfunke.de/shop?lang=en')->assertStatus(200);
        Auth::guard('admin')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

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
        $verifyOutput = Artisan::output();
        $this->assertStringContainsString('Digital-Shop (digital.mein-seelenfunke.de)', $verifyOutput);
        $this->assertStringContainsString('digital_domain_is_live = false (Vorab-Schutz 503)', $verifyOutput);
        $this->assertStringContainsString('5 Blueprints (5 Entwürfe)', $verifyOutput);
        $this->assertStringContainsString('Zero-Leak OK', $verifyOutput);
    }

    public function test_live_go_live_switch_and_maintenance_mode_workflow_for_alina_and_visitors(): void
    {
        $this->seed(SystemSettingSeeder::class);
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'false']);
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

        // 1. Stufe 1 (shop_is_live = false): Besucher UND eingeloggte Admin sehen auf https://mein-seelenfunke.de/ strikt 503!
        $this->get('https://mein-seelenfunke.de/')->assertStatus(503)->assertHeader('X-Robots-Tag', 'noindex, noarchive');
        $this->get('https://mein-seelenfunke.de/login')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/impressum')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/datenschutz')->assertStatus(200);
        $this->get('https://mein-seelenfunke.de/barrierefreiheit')->assertStatus(200);

        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/')->assertStatus(503);
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/shop')->assertStatus(503);
        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/admin/settings')->assertStatus(200);

        // 2. Stufe 2: Alina schaltet "Live gehen" ein, lässt "Wartungsmodus" aber noch aktiv -> Admin sieht 200, Besucher weiterhin 503!
        $component = Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSet('shop_is_live', false)
            ->assertSet('settings.maintenance_mode', true)
            ->call('toggleShopIsLive')
            ->assertSet('shop_is_live', true);

        $this->actingAs($admin, 'admin')->get('https://mein-seelenfunke.de/')->assertStatus(200);

        Auth::guard('admin')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get('https://mein-seelenfunke.de/')->assertStatus(503);

        // 3. Stufe 3: Alina schaltet auch den Wartungsmodus aus -> Shop ist öffentlich Live (200 für Besucher)!
        $component = Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertSet('shop_is_live', true)
            ->assertSet('settings.maintenance_mode', true)
            ->call('toggleMaintenanceMode')
            ->assertSet('settings.maintenance_mode', false);

        $this->assertNotNull($component);

        Auth::guard('admin')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get('https://mein-seelenfunke.de/')->assertStatus(200);
    }

    public function test_htaccess_enforces_precise_www_to_apex_301_redirect_without_touching_subdomains_or_webhooks(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        $wsPos = strpos($htaccess, 'RewriteRule ^(.*)$ http://a-iurgq8:6001/$1 [P,L]');
        $canonicalPos = strpos($htaccess, 'RewriteCond %{HTTP_HOST} ^www\.mein-seelenfunke\.de$ [NC]');

        $this->assertNotFalse($wsPos);
        $this->assertNotFalse($canonicalPos);
        $this->assertGreaterThan($wsPos, $canonicalPos);

        $this->assertStringContainsString('RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$ [NC]', $htaccess);
        $this->assertStringContainsString('RewriteCond %{REQUEST_URI} !^/(api(/|$)|stripe(/|$)|twilio|up($|/)|\.well-known(/|$)) [NC]', $htaccess);
        $this->assertStringContainsString('RewriteRule ^ https://mein-seelenfunke.de%{REQUEST_URI} [R=301,L]', $htaccess);
        $this->assertStringNotContainsString('RewriteRule ^ https://www.mein-seelenfunke.de', $htaccess);
        $this->assertStringNotContainsString(' – ', $htaccess);

        $hostPattern = '/^www\.mein-seelenfunke\.de$/i';
        $this->assertSame(1, preg_match($hostPattern, 'www.mein-seelenfunke.de'));
        $this->assertSame(1, preg_match($hostPattern, 'WWW.MEIN-SEELENFUNKE.DE'));
        $this->assertSame(0, preg_match($hostPattern, 'mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, 'digital.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, 'DIGITAL.MEIN-SEELENFUNKE.DE'));
        $this->assertSame(0, preg_match($hostPattern, 'stage.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, 'ws.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, 'api-live-bridge.mein-seelenfunke.de'));
        $this->assertSame(0, preg_match($hostPattern, '127.0.0.1'));
        $this->assertSame(0, preg_match($hostPattern, 'steinhauer-invest.de'));
        $this->assertSame(0, preg_match($hostPattern, 'www.steinhauer-invest.de'));

        $excludedUriPattern = '#^/(api(/|$)|stripe(/|$)|twilio|up($|/)|\.well-known(/|$))#i';
        $this->assertSame(1, preg_match($excludedUriPattern, '/api/system/ag-direct-inbox'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/stripe/webhook'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/twilio-stream'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/up'));
        $this->assertSame(1, preg_match($excludedUriPattern, '/.well-known/acme-challenge/token-xyz'));
        $this->assertSame(0, preg_match($excludedUriPattern, '/'));
        $this->assertSame(0, preg_match($excludedUriPattern, '/login'));
        $this->assertSame(0, preg_match($excludedUriPattern, '/shop'));
        $this->assertSame(0, preg_match($excludedUriPattern, '/produkt/seelen-kristall'));
    }
}
