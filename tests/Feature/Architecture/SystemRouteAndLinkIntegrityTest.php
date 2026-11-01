<?php

namespace Tests\Feature\Architecture;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Customer\CustomerInvoicesComponent;
use App\Livewire\Customer\CustomerOrdersComponent;
use App\Livewire\Customer\CustomerTicketsComponent;
use App\Livewire\Employee\EmployeeDashboard;
use App\Livewire\Employee\Influencer\InfluencerDashboard;
use App\Livewire\Employee\TaxAdvisor\TaxAdvisorDashboard;
use App\Livewire\Shop\Marketing\MarketingLinktree;
use App\Livewire\Shop\System\SystemUserManagement;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SystemRouteAndLinkIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_named_route_calls_in_views_and_app_exist_in_router(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $namedRoutes = $routes->map(fn ($r) => $r->getName())->filter()->flip()->all();

        $missingRoutes = [];
        $scanDirs = [
            base_path('resources/views'),
            base_path('app'),
        ];

        foreach ($scanDirs as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                // Match global route('name') helper calls (excluding Notification::route() or $req->route())
                if (preg_match_all('/(?<!->)(?<!::)\broute\(\s*[\'"]([a-zA-Z0-9_.\-\/]+)[\'"]\s*(?:,|\))/', $content, $matches)) {
                    foreach ($matches[1] as $routeName) {
                        if (!isset($namedRoutes[$routeName])) {
                            $relPath = str_replace(base_path() . '/', '', $file->getPathname());
                            $missingRoutes[$routeName][] = $relPath;
                        }
                    }
                }
            }
        }

        $report = [];
        foreach ($missingRoutes as $name => $files) {
            $report[] = "Route [{$name}] nicht definiert (referenziert in: " . implode(', ', array_unique($files)) . ')';
        }

        $this->assertEmpty(
            $report,
            "Nicht existierende Named-Routes gefunden:\n" . implode("\n", $report)
        );
    }

    public function test_all_hardcoded_internal_href_links_in_blade_views_match_registered_routes(): void
    {
        $router = app('router');
        $deadLinks = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/views')));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if (preg_match_all('/\bhref\s*=\s*[\'"](\/[a-zA-Z0-9_\-\/]*)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $path) {
                    // Ignore static public asset paths
                    if (
                        $path === '//'
                        || str_starts_with($path, '/storage/')
                        || str_starts_with($path, '/shop/')
                        || str_starts_with($path, '/images/')
                        || str_starts_with($path, '/assets/')
                        || str_starts_with($path, '/favicon')
                        || str_starts_with($path, '/build/')
                    ) {
                        continue;
                    }

                    try {
                        $request = \Illuminate\Http\Request::create($path, 'GET');
                        $router->getRoutes()->match($request);
                    } catch (\Throwable $e) {
                        $relPath = str_replace(base_path() . '/', '', $file->getPathname());
                        $deadLinks[$path][] = $relPath;
                    }
                }
            }
        }

        $report = [];
        foreach ($deadLinks as $path => $files) {
            $report[] = "Interner Link [{$path}] hat keine passende GET-Route (in: " . implode(', ', array_unique($files)) . ')';
        }

        $this->assertEmpty(
            $report,
            "Tote interne href-Verlinkungen in Blade-Views gefunden:\n" . implode("\n", $report)
        );
    }

    public function test_all_public_frontend_and_seo_routes_are_reachable(): void
    {
        $publicGetUris = [
            '/',
            '/shop',
            '/manufaktur',
            '/kontakt',
            '/calculator',
            '/warenkorb',
            '/checkout',
            '/checkout/success',
            '/blog',
            '/impressum',
            '/datenschutz',
            '/agb',
            '/widerruf',
            '/widerruf/pdf',
            '/versand',
            '/verhaltenskodex',
            '/barrierefreiheit',
            '/notfall',
            '/links',
            '/newsletter',
            '/login',
            '/register',
            '/sitemap.xml',
            '/llms.txt',
            '/llms-full.txt',
            '/feed/google-shopping.xml',
            '/feed/products.json',
        ];

        foreach ($publicGetUris as $uri) {
            $response = $this->get($uri);
            $status = $response->getStatusCode();
            $this->assertTrue(
                in_array($status, [200, 301, 302], true),
                "Öffentliche Route [{$uri}] lieferte unerwarteten HTTP-Status {$status}."
            );
        }
    }

    public function test_customer_routes_require_authentication_and_render_for_logged_in_customer(): void
    {
        $customerUris = [
            '/customer/dashboard',
            '/customer/profile',
            '/orders',
            '/invoices',
            '/support',
        ];

        // 1. Als Gast müssen alle Kunden-Routen zum Login weiterleiten
        foreach ($customerUris as $uri) {
            $response = $this->get($uri);
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 401, 403], true),
                "Kunden-Route [{$uri}] darf für Gäste nicht ohne Login erreichbar sein (Status: {$response->getStatusCode()})."
            );
        }

        // 2. Als eingeloggter, verifizierter Kunde müssen alle Kunden-Routen sauber mit HTTP 200 laden
        $customer = Customer::create([
            'first_name' => 'Clara',
            'last_name' => 'Kundin',
            'email' => 'clara-route-test@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($customer, 'customer');

        foreach ($customerUris as $uri) {
            $response = $this->get($uri);
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Kunden-Route [{$uri}] konnte für eingeloggten Kunden nicht mit HTTP 200 geladen werden (Status: {$response->getStatusCode()})."
            );
        }

        // Kurzlinks /dashboard und /profile müssen sauber auf /customer/dashboard bzw. /customer/profile weiterleiten
        $this->get('/dashboard')->assertRedirect('/customer/dashboard');
        $this->get('/profile')->assertRedirect('/customer/profile');
    }

    public function test_all_admin_routes_strictly_block_guests_customers_and_external_partners(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $adminGetUris = [];

        foreach ($routes as $route) {
            $uri = '/' . ltrim($route->uri(), '/');
            if (
                str_starts_with($uri, '/admin')
                && in_array('GET', $route->methods(), true)
                && empty($route->parameterNames())
            ) {
                $adminGetUris[] = $uri;
            }
        }

        $this->assertNotEmpty($adminGetUris);

        $customer = Customer::create([
            'first_name' => 'Kunde',
            'last_name' => 'Unbefugt',
            'email' => 'unbefugt-kunde@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        foreach ($adminGetUris as $uri) {
            // Gast
            Auth::guard('customer')->logout();
            Auth::guard('admin')->logout();
            $guestResp = $this->get($uri);
            $this->assertTrue(
                in_array($guestResp->getStatusCode(), [302, 401, 403], true),
                "Admin-Route [{$uri}] ist für unauthentifizierte Gäste zugänglich!"
            );

            // Kunde
            $this->actingAs($customer, 'customer');
            $custResp = $this->get($uri);
            $this->assertTrue(
                in_array($custResp->getStatusCode(), [302, 401, 403], true),
                "Admin-Route [{$uri}] ist für Kunden zugänglich!"
            );
        }
    }

    public function test_public_directory_contains_no_exposed_debug_or_exec_scripts(): void
    {
        $publicDir = public_path();
        $forbiddenFiles = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relPath = str_replace($publicDir . '/', '', $file->getPathname());
            $ext = strtolower($file->getExtension());

            // In public/ darf ausschließlich index.php als ausführbares PHP-Skript liegen
            if ($ext === 'php' && $relPath !== 'index.php') {
                $forbiddenFiles[] = $relPath;
            }

            // Keine Log-, SQL-, Backup-, Shell- oder Env-Dateien im öffentlichen Webroot
            if (in_array($ext, ['log', 'sql', 'bak', 'sh', 'env'], true) || str_starts_with($file->getFilename(), '.env')) {
                $forbiddenFiles[] = $relPath;
            }
        }

        $this->assertEmpty(
            $forbiddenFiles,
            "Sicherheitskritische Dateien im öffentlichen public/-Verzeichnis gefunden:\n" . implode("\n", $forbiddenFiles)
        );
    }

    public function test_livewire_hook_strictly_isolates_protected_backend_employee_and_customer_components(): void
    {
        // 1. Als Gast müssen geschützte Backend-, Mitarbeiter- und Kunden-Komponenten sofort mit 403 blockiert werden
        Auth::guard('admin')->logout();
        Auth::guard('employee')->logout();
        Auth::guard('customer')->logout();

        $protectedForGuests = [
            MarketingLinktree::class,
            SystemUserManagement::class,
            EmployeeDashboard::class,
            InfluencerDashboard::class,
            TaxAdvisorDashboard::class,
            CustomerDashboardComponent::class,
            CustomerOrdersComponent::class,
            CustomerInvoicesComponent::class,
            CustomerTicketsComponent::class,
        ];

        foreach ($protectedForGuests as $componentClass) {
            Livewire::test($componentClass)->assertForbidden();
        }

        // 2. Als eingeloggter Kunde müssen alle Admin-/Backend-/Employee-Komponenten ebenfalls mit 403 blockiert werden
        $customer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Kunde',
            'email' => 'max-isolation@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($customer, 'customer');

        $protectedForCustomers = [
            MarketingLinktree::class,
            SystemUserManagement::class,
            EmployeeDashboard::class,
            InfluencerDashboard::class,
            TaxAdvisorDashboard::class,
        ];

        foreach ($protectedForCustomers as $componentClass) {
            Livewire::test($componentClass)->assertForbidden();
        }
    }
}
