<?php

namespace Tests\Feature\System;

use App\Livewire\Backend\System\SystemNeuralAnalysisIndex;
use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Management\ManagementPlaylist;
use App\Models\Management\ManagementTrack;
use App\Models\Marketing\MarketingBlogPost;
use App\Models\Marketing\MarketingInfluencer;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemLog;
use App\Models\System\SystemNeuralNode;
use App\Services\CartFileCleanupService;
use App\Services\System\SystemBrainDiagnosticsService;
use App\Support\SafeHtmlAndSvgSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportRedirects\Redirector as LivewireRedirector;
use Livewire\Livewire;
use Tests\TestCase;

class FullRouteAndBrainHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function resetLivewireRedirectorAndGuards(): void
    {
        if ($this->app->bound('redirect') && $this->app->make('redirect') instanceof LivewireRedirector) {
            $this->app->forgetInstance('redirect');
        }
        Auth::forgetGuards();
    }

    public function test_all_frontend_routes_including_dynamic_product_blog_and_landingpage_respond_cleanly(): void
    {
        $admin = Admin::factory()->create();

        MarketingLandingPage::query()->delete();
        MarketingBlogPost::withTrashed()->forceDelete();
        Product::withTrashed()->forceDelete();

        $product = Product::create([
            'name' => 'Manufaktur Kristallblock',
            'slug' => 'manufaktur-kristallblock',
            'sku' => 'SF-KRISTALL-01',
            'type' => 'physical',
            'status' => 'active',
            'price' => 4990,
            'short_description' => 'Handveredelt in Deutschland (Manufaktur Gifhorn)',
            'description' => 'Kristallglas mit feiner UV-Lasergravur.',
        ]);

        MarketingLandingPage::create([
            'product_id' => $product->id,
            'slug' => 'kristallblock-geschenk',
            'title' => 'Kristallblock Geschenkidee',
            'headline' => 'Einzigartige Erinnerung in Kristallglas',
            'sales_copy' => 'Handveredelt in unserer Manufaktur in Gifhorn.',
            'cta_text' => 'Jetzt gestalten',
            'status' => 'active',
        ]);

        MarketingBlogPost::create([
            'user_id' => $admin->id,
            'author_name' => 'Alina Steinhauer',
            'title' => 'Einblick in unsere Laser-Manufaktur',
            'slug' => 'einblick-laser-manufaktur',
            'excerpt' => 'Präzision mit UV-Lasergravur.',
            'content' => '<p>Handveredelt in Gifhorn.</p>',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $this->resetLivewireRedirectorAndGuards();

        $diagnostics = app(SystemBrainDiagnosticsService::class);
        $frontendRoutes = $diagnostics->collectFrontendCheckRoutes();

        $uris = array_column($frontendRoutes, 'uri');
        $this->assertContains('/produkt/manufaktur-kristallblock', $uris);
        $this->assertContains('/l/kristallblock-geschenk', $uris);
        $this->assertContains('/blog/einblick-laser-manufaktur', $uris);
        $this->assertGreaterThanOrEqual(30, count($frontendRoutes));

        foreach ($frontendRoutes as $routeItem) {
            $uri = $routeItem['uri'];
            if ($this->app->bound('redirect') && $this->app->make('redirect') instanceof LivewireRedirector) {
                $this->app->forgetInstance('redirect');
            }

            $response = $this->get($uri);
            $status = $response->getStatusCode();

            $this->assertTrue(
                in_array($status, [200, 301, 302], true),
                "Frontend-Route [{$uri}] lieferte unerwarteten HTTP-Status {$status}."
            );
        }
    }

    public function test_all_backend_navigation_and_admin_routes_enforce_guards_and_render_for_admin(): void
    {
        $diagnostics = app(SystemBrainDiagnosticsService::class);
        $backendRoutes = $diagnostics->collectBackendCheckRoutes();

        $this->assertGreaterThanOrEqual(50, count($backendRoutes));

        // 1. Unauthentifizierte Gäste müssen auf JEDER Admin-Route strikt abgewiesen werden (302/401/403, niemals 200 oder 500)
        $this->resetLivewireRedirectorAndGuards();
        foreach ($backendRoutes as $routeItem) {
            $uri = $routeItem['uri'];
            $guestResponse = $this->get($uri);
            $guestStatus = $guestResponse->getStatusCode();

            $this->assertTrue(
                in_array($guestStatus, [302, 401, 403], true),
                "Unauthentifizierter Gast durfte Admin-Route [{$uri}] mit Status {$guestStatus} aufrufen!"
            );
        }

        // 2. Externe Rollen (Influencer & Steuerberater) dürfen keinen Zugriff auf das Admin-Backend erhalten
        $influencer = Employee::create([
            'first_name' => 'Partner',
            'last_name' => 'Creator',
            'email' => 'influencer-isolation@seelenfunke.test',
            'role' => 'influencer',
            'password' => bcrypt('SecretPass123!'),
            'is_active' => true,
        ]);

        $taxAdvisor = Employee::create([
            'first_name' => 'Steuer',
            'last_name' => 'Kanzlei',
            'email' => 'tax-isolation@seelenfunke.test',
            'role' => 'tax_advisor',
            'password' => bcrypt('SecretPass123!'),
            'is_active' => true,
        ]);

        $sampleAdminUris = array_slice(array_column($backendRoutes, 'uri'), 0, 6);
        foreach ($sampleAdminUris as $sensitiveUri) {
            $this->resetLivewireRedirectorAndGuards();
            $infStatus = $this->actingAs($influencer, 'employee')->get($sensitiveUri)->getStatusCode();
            $this->assertTrue(in_array($infStatus, [302, 401, 403], true), "Influencer erhielt Status {$infStatus} auf {$sensitiveUri}");

            $this->resetLivewireRedirectorAndGuards();
            $taxStatus = $this->actingAs($taxAdvisor, 'employee')->get($sensitiveUri)->getStatusCode();
            $this->assertTrue(in_array($taxStatus, [302, 401, 403], true), "Steuerberater erhielt Status {$taxStatus} auf {$sensitiveUri}");
        }

        // 3. Authentifizierter Admin muss JEDE einzelne Seite der Backend-Navigation fehlerfrei laden können
        $admin = Admin::factory()->create();
        foreach ($backendRoutes as $routeItem) {
            $uri = $routeItem['uri'];
            if ($this->app->bound('redirect') && $this->app->make('redirect') instanceof LivewireRedirector) {
                $this->app->forgetInstance('redirect');
            }

            $adminResponse = $this->actingAs($admin, 'admin')->get($uri);
            $adminStatus = $adminResponse->getStatusCode();

            $this->assertTrue(
                in_array($adminStatus, [200, 302], true),
                "Admin-Route [{$uri}] ({$routeItem['label']}) schlug fehl mit HTTP-Status {$adminStatus}."
            );
        }
    }

    public function test_all_role_portals_enforce_isolation_and_render_for_authorized_roles(): void
    {
        $diagnostics = app(SystemBrainDiagnosticsService::class);
        $portalRoutes = $diagnostics->collectPortalCheckRoutes();
        $this->assertCount(8, $portalRoutes);

        // 1. Gäste werden von allen 8 Portalen abgewiesen
        $this->resetLivewireRedirectorAndGuards();
        foreach ($portalRoutes as $portal) {
            $status = $this->get($portal['uri'])->getStatusCode();
            $this->assertTrue(
                in_array($status, [302, 401, 403], true),
                "Gast wurde auf Portal [{$portal['uri']}] nicht abgewiesen (Status {$status})."
            );
        }

        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $internalEmployee = Employee::create([
            'first_name' => 'Intern',
            'last_name' => 'Produktion',
            'email' => 'intern@seelenfunke.test',
            'role' => 'internal',
            'password' => bcrypt('SecretPass123!'),
            'is_active' => true,
        ]);

        $influencer = Employee::create([
            'first_name' => 'Creator',
            'last_name' => 'Partner',
            'email' => 'creator@seelenfunke.test',
            'role' => 'influencer',
            'password' => bcrypt('SecretPass123!'),
            'is_active' => true,
        ]);

        MarketingInfluencer::create([
            'employee_id' => $influencer->id,
            'handle' => '@creator_seelenfunke',
            'slug' => 'creator-seelenfunke',
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $taxAdvisor = Employee::create([
            'first_name' => 'Steuer',
            'last_name' => 'Prüfer',
            'email' => 'steuer@seelenfunke.test',
            'role' => 'tax_advisor',
            'password' => bcrypt('SecretPass123!'),
            'is_active' => true,
        ]);

        // 2. Autorisierte Zugriffe müssen 200/302 liefern
        foreach ($portalRoutes as $portal) {
            $this->resetLivewireRedirectorAndGuards();

            $actor = match ($portal['guard']) {
                'customer' => $customer,
                'employee_internal' => $internalEmployee,
                'influencer' => $influencer,
                'tax_advisor' => $taxAdvisor,
            };
            $guardName = $portal['guard'] === 'customer' ? 'customer' : 'employee';

            $resp = $this->actingAs($actor, $guardName)->get($portal['uri']);
            $this->assertTrue(
                in_array($resp->getStatusCode(), [200, 302], true),
                "Berechtigter Portal-Aufruf [{$portal['uri']}] für Rolle [{$portal['guard']}] lieferte Status {$resp->getStatusCode()}."
            );
        }

        // 3. Strikte Quer-Isolation zwischen internen Mitarbeitern, Influencern und Steuerberatern (EnsureEmployeeRole + EmployeeRoleIsolationHook)
        $this->resetLivewireRedirectorAndGuards();
        $this->actingAs($influencer, 'employee')->get('/employee/dashboard')
            ->assertRedirect(route('employee.influencer.dashboard'));

        $this->resetLivewireRedirectorAndGuards();
        $this->actingAs($influencer, 'employee')->get('/employee/tax-advisor/dashboard')
            ->assertRedirect(route('employee.influencer.dashboard'));

        $this->resetLivewireRedirectorAndGuards();
        $this->actingAs($taxAdvisor, 'employee')->get('/employee/dashboard')
            ->assertRedirect(route('employee.tax-advisor.dashboard'));

        $this->resetLivewireRedirectorAndGuards();
        $this->actingAs($taxAdvisor, 'employee')->get('/employee/influencer/dashboard')
            ->assertRedirect(route('employee.tax-advisor.dashboard'));

        $this->resetLivewireRedirectorAndGuards();
        $this->actingAs($internalEmployee, 'employee')->get('/employee/influencer/dashboard')
            ->assertRedirect(route('employee.dashboard'));

        $this->resetLivewireRedirectorAndGuards();
        $this->actingAs($internalEmployee, 'employee')->get('/employee/tax-advisor/dashboard')
            ->assertRedirect(route('employee.dashboard'));

        // Direkter Livewire-Mount-Versuch fremder Portale wird hart mit 403 blockiert
        Livewire::actingAs($influencer, 'employee')
            ->test(\App\Livewire\Employee\EmployeeDashboard::class)
            ->assertStatus(403);
        Livewire::actingAs($influencer, 'employee')
            ->test(\App\Livewire\Employee\TaxAdvisor\TaxAdvisorDashboard::class)
            ->assertStatus(403);
        Livewire::actingAs($taxAdvisor, 'employee')
            ->test(\App\Livewire\Employee\Influencer\InfluencerDashboard::class)
            ->assertStatus(403);
    }

    public function test_projekt_gehirn_couples_system_log_errors_in_red_with_connected_files_and_system_check_button(): void
    {
        $admin = Admin::factory()->create();

        // Sauberer Ausgangszustand ohne offene Logs
        SystemLog::query()->delete();
        $laravelLogPath = storage_path('logs/laravel.log');
        $originalLaravelLog = File::exists($laravelLogPath) ? File::get($laravelLogPath) : null;
        if (File::exists($laravelLogPath)) {
            File::put($laravelLogPath, '');
        }

        try {
            // 1. Architektur-Knoten mit bidirektionalen Nachbarn rund um CartService anlegen (group_id int: 8=Service, 4=Livewire, 2=Model)
            SystemNeuralNode::create([
                'file_path' => 'app/Services/CartService.php',
                'name' => 'CartService.php',
                'group_id' => 8,
                'dependencies' => ['app/Models/Cart/Cart.php', 'app/Models/Product/Product.php'],
                'methods' => ['addItem', 'sanitizeConfiguration'],
                'properties' => [
                    'incoming_dependents' => ['app/Livewire/Shop/Cart/Cart.php', 'app/Livewire/Shop/Order/OrderCheckout/OrderCheckout.php'],
                    'outgoing_dependencies' => ['app/Models/Cart/Cart.php', 'app/Models/Product/Product.php'],
                    'incoming_count' => 2,
                    'outgoing_count' => 2,
                ],
                'content_hash' => sha1('cart-service-node'),
            ]);

            SystemNeuralNode::create([
                'file_path' => 'app/Livewire/Shop/Cart/Cart.php',
                'name' => 'Cart.php',
                'group_id' => 4,
                'dependencies' => ['app/Services/CartService.php'],
                'methods' => ['render'],
                'properties' => [
                    'incoming_dependents' => [],
                    'outgoing_dependencies' => ['app/Services/CartService.php'],
                    'incoming_count' => 0,
                    'outgoing_count' => 1,
                ],
                'content_hash' => sha1('cart-livewire-node'),
            ]);

            SystemNeuralNode::create([
                'file_path' => 'app/Models/Cart/Cart.php',
                'name' => 'Cart.php',
                'group_id' => 2,
                'dependencies' => [],
                'methods' => ['items'],
                'properties' => [
                    'incoming_dependents' => ['app/Services/CartService.php'],
                    'outgoing_dependencies' => [],
                    'incoming_count' => 1,
                    'outgoing_count' => 0,
                ],
                'content_hash' => sha1('cart-model-node'),
            ]);

            // 2. Fehler im SystemLog erzeugen -> muss sofort als roter Fehlerkern inkl. Nachbardateien erscheinen
            $errorLog = SystemLog::create([
                'title' => 'Kritischer Preisberechnungsfehler im Warenkorb',
                'type' => 'system',
                'status' => 'error',
                'action_id' => 'exception:cart_calc',
                'message' => 'DivisionByZeroError in /var/www/html/app/Services/CartService.php:142',
                'payload' => [
                    'file' => '/var/www/html/app/Services/CartService.php',
                    'line' => 142,
                    'occurrences_count' => 4,
                    'execution_context' => ['route' => '/warenkorb'],
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            $diagnostics = app(SystemBrainDiagnosticsService::class);
            $overlay = $diagnostics->getActiveErrorOverlay();

            $this->assertTrue($overlay['has_errors']);
            $this->assertTrue($overlay['has_critical_errors']);
            $this->assertSame(1, $overlay['open_errors_count']);
            $this->assertArrayHasKey('app/Services/CartService.php', $overlay['error_cores']);

            $core = $overlay['error_cores']['app/Services/CartService.php'];
            $this->assertTrue($core['is_error']);
            $this->assertSame('error', $core['severity']);
            $this->assertSame(142, $core['line']);
            $this->assertSame(4, $core['occurrences_total']);

            $incomingPaths = array_column($core['incoming_files'], 'file_path');
            $outgoingPaths = array_column($core['outgoing_files'], 'file_path');
            $connectedPaths = array_column($core['connected_files'], 'file_path');

            $this->assertContains('app/Livewire/Shop/Cart/Cart.php', $incomingPaths);
            $this->assertContains('app/Models/Cart/Cart.php', $outgoingPaths);
            $this->assertContains('app/Livewire/Shop/Cart/Cart.php', $connectedPaths);
            $this->assertContains('app/Models/Cart/Cart.php', $connectedPaths);

            // 3. Backend Livewire "Projekt Gehirn" (SystemNeuralAnalysisIndex) zeigt roten Fehlerkern & prüft System
            $component = Livewire::actingAs($admin, 'admin')
                ->test(SystemNeuralAnalysisIndex::class)
                ->assertStatus(200)
                ->assertSee('CartService.php')
                ->assertSee('Kritischer Preisberechnungsfehler im Warenkorb')
                ->call('runSystemCheck');

            $reportWithError = $component->get('systemCheckReport');
            $this->assertIsArray($reportWithError);
            $this->assertFalse($reportWithError['passed'], 'Systemprüfung muss fehlschlagen, solange ein offener Fehler im SystemLog liegt.');
            $this->assertCount(6, $reportWithError['sections']);

            $logSection = collect($reportWithError['sections'])->firstWhere('key', 'system_logs');
            $this->assertNotNull($logSection);
            $this->assertFalse($logSection['passed']);

            $component->call('focusFile', 'app/Services/CartService.php')
                ->assertSet('search', 'app/Services/CartService.php')
                ->assertSet('expandedNodePath', 'app/Services/CartService.php');

            // 4. 3D Projekt-Gehirn API-Endpunkte: Unauthentifiziert gesperrt, für Admin live rot angereichert
            $this->resetLivewireRedirectorAndGuards();
            $this->getJson('/api/ai/system-brain-map')->assertStatus(401);
            $this->getJson('/api/ai/system-brain-errors')->assertStatus(401);
            $this->postJson('/api/ai/system-brain-check')->assertStatus(401);

            $mapResp = $this->actingAs($admin, 'admin')->getJson('/api/ai/system-brain-map');
            $mapResp->assertOk();
            $mapJson = $mapResp->json();
            $this->assertTrue($mapJson['error_summary']['has_errors'] ?? false);

            $cartNodeInGraph = collect($mapJson['nodes'] ?? [])->firstWhere('id', 'app/Services/CartService.php');
            $this->assertNotNull($cartNodeInGraph);
            $this->assertTrue($cartNodeInGraph['isError'] ?? false);
            $this->assertSame('error', $cartNodeInGraph['severity'] ?? null);
            $this->assertContains('app/Livewire/Shop/Cart/Cart.php', $cartNodeInGraph['connectedFiles'] ?? []);

            $errorsResp = $this->actingAs($admin, 'admin')->getJson('/api/ai/system-brain-errors');
            $errorsResp->assertOk()
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('has_errors', true)
                ->assertJsonPath('open_errors_count', 1);

            $checkApiResp = $this->actingAs($admin, 'admin')->postJson('/api/ai/system-brain-check');
            $checkApiResp->assertOk()
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('report.passed', false);

            // 5. Fehler beheben/löschen -> Projekt-Gehirn und CLI-Schnelltest wechseln sofort auf 100% GRÜN
            $errorLog->delete();

            $component->call('runSystemCheck');
            $greenReport = $component->get('systemCheckReport');
            $this->assertTrue($greenReport['passed'], 'Nach Behebung des SystemLog-Fehlers muss "System prüfen" 100% GRÜN melden.');
            $this->assertSame(0, $greenReport['overlay']['error_core_count']);

            $exitCode = Artisan::call('system:check-routes', ['--json' => true]);
            $cliJson = json_decode(trim(Artisan::output()), true);

            $this->assertSame(0, $exitCode, 'Artisan system:check-routes muss Exit-Code 0 liefern.');
            $this->assertIsArray($cliJson);
            $this->assertTrue($cliJson['passed'] ?? false);
            $this->assertTrue($cliJson['blueprints_compliant'] ?? false);
            $this->assertSame(0, $cliJson['missing_tables'] ?? -1);
            $this->assertSame(0, $cliJson['overlay']['error_core_count'] ?? -1);
        } finally {
            if ($originalLaravelLog !== null) {
                File::put($laravelLogPath, $originalLaravelLog);
            }
        }
    }

    public function test_security_and_data_exchange_hardening_regressions(): void
    {
        // 1. DOM-basierter HTML- & SVG-Sanitizer wehrt XSS, Entity-Obfuskation und XXE restlos ab
        $dirtyHtml = '<p onclick="evil()">Hallo</p><img/src=x/onerror=alert(1)><a href="&#106;&#97;vascript:alert(1)">Klick</a><script>alert(2)</script>';
        $cleanHtml = SafeHtmlAndSvgSanitizer::sanitizeHtml($dirtyHtml);

        $this->assertStringContainsString('<p>Hallo</p>', $cleanHtml);
        $this->assertStringNotContainsString('onclick', strtolower($cleanHtml));
        $this->assertStringNotContainsString('onerror', strtolower($cleanHtml));
        $this->assertStringNotContainsString('javascript:', strtolower($cleanHtml));
        $this->assertStringNotContainsString('<script', strtolower($cleanHtml));

        $dirtySvg = <<<'SVG'
<?xml version="1.0"?>
<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)" viewBox="0 0 100 100">
    <script>alert('xss')</script>
    <foreignObject width="100" height="100"><div>XSS</div></foreignObject>
    <use xlink:href="https://evil.example/payload.svg#icon" />
    <circle cx="50" cy="50" r="40" fill="#C5A059" onclick="steal()" />
</svg>
SVG;
        $cleanSvg = SafeHtmlAndSvgSanitizer::sanitizeSvg($dirtySvg);

        $this->assertNotSame('', $cleanSvg);
        $this->assertStringNotContainsString('<!ENTITY', $cleanSvg);
        $this->assertStringNotContainsString('<!DOCTYPE', $cleanSvg);
        $this->assertStringNotContainsString('<script', strtolower($cleanSvg));
        $this->assertStringNotContainsString('foreignobject', strtolower($cleanSvg));
        $this->assertStringNotContainsString('onload', strtolower($cleanSvg));
        $this->assertStringNotContainsString('onclick', strtolower($cleanSvg));
        $this->assertStringNotContainsString('evil.example', strtolower($cleanSvg));
        $this->assertStringContainsString('<circle', $cleanSvg);

        // 2. Stripe Webhook ohne Signatur-Header liefert kontrolliert HTTP 400 statt TypeError/500
        $this->resetLivewireRedirectorAndGuards();
        $logCountBefore = SystemLog::where('status', 'error')->count();
        $stripeResp = $this->postJson('/stripe/webhook', ['type' => 'payment_intent.succeeded']);
        $stripeResp->assertStatus(400);
        $this->assertSame($logCountBefore, SystemLog::where('status', 'error')->count());

        // 3. Musik-Stream Path-Traversal-Schutz blockiert Ausbruch aus storage/app/private/music
        $admin = Admin::factory()->create();
        $playlist = ManagementPlaylist::create([
            'name' => 'Manufaktur Fokus',
            'description' => 'Test-Playlist',
        ]);
        $traversalTrack = ManagementTrack::create([
            'playlist_id' => $playlist->id,
            'title' => 'Traversal Versuch',
            'file_path' => '../../.env',
            'status' => 'ready',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/music/stream/' . $traversalTrack->id)
            ->assertStatus(404);

        // 4. ProductCalculator übernimmt den konfigurierten Variantenpreis exakt (34,90 € statt Basispreis 29,90 €)
        $calcProduct = Product::create([
            'name' => 'Schieferplatte Gravur',
            'slug' => 'schieferplatte-gravur-test',
            'sku' => 'SF-SCHIEFER-XL',
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'is_personalizable' => true,
            'variants_data' => [
                ['id' => 'var_xl', 'name' => 'Format XL', 'price' => '34,90'],
            ],
        ]);

        session([
            'calc_cart' => [
                [
                    'product_id' => $calcProduct->id,
                    'qty' => 1,
                    'configuration' => ['variant_id' => 'var_xl'],
                ],
            ],
        ]);

        $calcTest = Livewire::test(ProductCalculator::class);
        $items = $calcTest->get('cartItems');
        $this->assertNotEmpty($items);
        $this->assertEqualsWithDelta(34.90, (float) ($items[0]['calculated_single_price'] ?? 0), 0.01);

        // 5. CartFileCleanupService schützt Dateien, die in aktiven Produkt-Vorlagen (ProductTemplate) referenziert sind
        Storage::fake('public');
        $disk = Storage::disk('public');

        $protectedRelPath = 'bestellungen/cart-uploads/vorlage-motiv-geschuetzt.png';
        $orphanRelPath = 'bestellungen/cart-uploads/verwaister-upload-alt.png';

        $disk->put($protectedRelPath, 'PNG-TEMPLATE-DATA');
        $disk->put($orphanRelPath, 'PNG-ORPHAN-DATA');

        @touch($disk->path($protectedRelPath), now()->subHours(72)->timestamp);
        @touch($disk->path($orphanRelPath), now()->subHours(72)->timestamp);

        ProductTemplate::create([
            'product_id' => $calcProduct->id,
            'name' => 'Hochzeits-Vorlage',
            'is_active' => true,
            'configuration' => [
                'uploaded_image' => $protectedRelPath,
            ],
        ]);

        $cleanupResult = app(CartFileCleanupService::class)->cleanupOrphanedFiles(48, false);

        $disk->assertExists($protectedRelPath);
        $disk->assertMissing($orphanRelPath);
        $this->assertGreaterThanOrEqual(1, $cleanupResult['deleted'] ?? 0);
    }

    public function test_projekt_gehirn_file_relationships_render_mobile_scrollable_in_3d_widget_and_2d_cockpit(): void
    {
        $admin = Admin::factory()->create();

        $incomingMany = [];
        $outgoingMany = [];
        for ($i = 1; $i <= 14; $i++) {
            $incomingMany[] = sprintf('app/Livewire/Shop/Order/CallerComponent%02d.php', $i);
            $outgoingMany[] = sprintf('app/Services/OrderHelperService%02d.php', $i);
        }

        SystemNeuralNode::create([
            'file_path' => 'app/Services/OrderHubService.php',
            'name' => 'OrderHubService.php',
            'group_id' => 8,
            'dependencies' => array_merge($incomingMany, $outgoingMany),
            'methods' => ['handle'],
            'properties' => [
                'incoming_dependents' => $incomingMany,
                'outgoing_dependencies' => $outgoingMany,
                'incoming_count' => count($incomingMany),
                'outgoing_count' => count($outgoingMany),
            ],
            'content_hash' => sha1('order-hub-mobile-scroll-test'),
        ]);

        // 1. 2D Backend-Cockpit: Alle 14 eingehenden und 14 ausgehenden Dateien sind ohne Abschneiden in scrollbaren Containern enthalten
        Livewire::actingAs($admin, 'admin')
            ->test(SystemNeuralAnalysisIndex::class)
            ->call('toggleNodeNeighborhood', 'app/Services/OrderHubService.php')
            ->assertSee('Datei-Zusammenhänge (28)')
            ->assertSee('CallerComponent01.php')
            ->assertSee('CallerComponent14.php')
            ->assertSee('OrderHelperService01.php')
            ->assertSee('OrderHelperService14.php')
            ->assertSeeHtml('touch-pan-y')
            ->assertSeeHtml('overscroll-contain');

        // 2. 3D Projekt-Gehirn im KI-Widget: Mobile Tabs, eigene Scrollhöhe (kein flex-1 Höhenkollaps) & Touch-Isolation vorhanden
        Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Ai\AiWidget::class)
            ->assertSeeHtml('id="brain-dependencies-list"')
            ->assertSeeHtml('id="brain-selected-node-mobile-name"')
            ->assertSeeHtml('brain-touch-scroll')
            ->assertSeeHtml('window.closeNeuralNodeTile');
    }
}
