<?php

namespace Tests\Feature\System;

use App\Http\Middleware\EnsureAdminApiAccess;
use App\Http\Middleware\EnsureAiEndpointAccess;
use App\Http\Middleware\EnsureFullAccessToken;
use App\Http\Middleware\EnsureFunkenwerkDomainAccess;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Services\System\SystemBrainDiagnosticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteRegistrarAndMiddlewareSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $tempRoutesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoutesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_route_safety_' . Str::lower(Str::random(8));
        File::ensureDirectoryExists($this->tempRoutesDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempRoutesDir) && is_dir($this->tempRoutesDir)) {
            File::deleteDirectory($this->tempRoutesDir);
        }

        parent::tearDown();
    }

    private function createInternalEmployee(bool $canTasks, bool $canProduction): Employee
    {
        $employee = Employee::create([
            'first_name' => 'Werkstatt',
            'last_name' => 'Mitarbeiter',
            'email' => 'emp-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'role' => 'internal',
            'email_verified_at' => now(),
            'password' => bcrypt('SecretEmp!123'),
        ]);

        EmployeeContract::create([
            'employee_id' => $employee->id,
            'job_title' => 'Manufaktur-Kraft',
            'department' => 'Produktion',
            'employment_type' => 'part_time',
            'compensation_type' => 'hourly',
            'hourly_rate_cents' => 1650,
            'weekly_hours' => 20,
            'vacation_days_per_year' => 24,
            'start_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
            'is_archived' => false,
            'can_access_tasks' => $canTasks,
            'can_access_production' => $canProduction,
            'can_access_support' => false,
            'can_access_inventory' => false,
        ]);

        return $employee->fresh();
    }

    #[Test]
    public function all_project_route_files_and_registered_routes_are_free_of_closure_or_variable_middleware(): void
    {
        /** @var SystemBrainDiagnosticsService $diagnostics */
        $diagnostics = app(SystemBrainDiagnosticsService::class);
        $audit = $diagnostics->auditRouteDefinitionsSafety();

        $this->assertTrue(
            $audit['passed'],
            'Route-Middleware Integritätsprüfung fehlgeschlagen: ' . json_encode($audit['violations'], JSON_UNESCAPED_UNICODE)
        );
        $this->assertGreaterThanOrEqual(15, $audit['files_scanned']);
        $this->assertGreaterThanOrEqual(100, $audit['routes_scanned']);
        $this->assertSame(0, $audit['violations_count']);

        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                $this->assertIsString($mw, 'Route [' . $route->uri() . '] darf nur String-/Klassen-Middleware enthalten.');
            }
        }

        $systemOtaSource = (string) file_get_contents(base_path('routes/api/system_ota.php'));
        $this->assertStringNotContainsString('$adminOnlyMiddleware', $systemOtaSource);

        foreach (['api/funki/system/app-update/publish', 'api/funki/system/windows-update/publish'] as $publishUri) {
            $publishRoute = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $publishUri);
            $this->assertNotNull($publishRoute, "Route {$publishUri} muss registriert sein.");
            $this->assertContains(EnsureAdminApiAccess::class, $publishRoute->gatherMiddleware());
        }
    }

    #[Test]
    public function route_safety_auditor_detects_closure_and_variable_middleware_traps_and_allows_valid_class_syntax(): void
    {
        $unsafeFile = $this->tempRoutesDir . DIRECTORY_SEPARATOR . 'unsafe_routes.php';
        $validFile = $this->tempRoutesDir . DIRECTORY_SEPARATOR . 'valid_routes.php';

        file_put_contents($unsafeFile, <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

$adminOnlyMiddleware = function ($request, $next) {
    return $next($request);
};

Route::prefix('trap-a')->middleware(function ($request, $next) {
    return $next($request);
})->group(function () {
    Route::get('/one', fn () => 'ok');
});

Route::prefix('trap-b')->middleware([$adminOnlyMiddleware, 'throttle:60,1'])->group(function () {
    Route::get('/two', fn () => 'ok');
});

Route::middleware(fn ($request, $next) => $next($request))->group(function () {
    Route::get('/three', fn () => 'ok');
});

Route::group(['prefix' => 'trap-d', 'middleware' => [function ($request, $next) {
    return $next($request);
}]], function () {
    Route::get('/four', fn () => 'ok');
});

Route::get('/trap-e', fn () => 'ok')->middleware($adminOnlyMiddleware);

Route::post('/trap-f', fn () => 'ok')->middleware(function ($request, $next) {
    return $next($request);
});

$r = Route::get('/trap-g', fn () => 'ok');
$r->action['middleware'] = [function ($request, $next) {
    return $next($request);
}];

Route::prefix(function () {
    return 'bad-prefix';
})->group(function () {
    Route::get('/trap-h', fn () => 'ok');
});
PHP);

        file_put_contents($validFile, <<<'PHP'
<?php

use App\Http\Middleware\EnsureAdminApiAccess;
use Illuminate\Support\Facades\Route;

$dynamicPrefix = 'safe-dynamic';

Route::prefix($dynamicPrefix)
    ->middleware([EnsureAdminApiAccess::class, 'throttle:60,1'])
    ->group(function () {
        Route::get('/ok', function () {
            return response()->json(['ok' => true]);
        })->middleware('throttle:30,1');
    });

Route::middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->group(function () {
    Route::get('/login-check', fn () => 'ok');
});
PHP);

        /** @var SystemBrainDiagnosticsService $diagnostics */
        $diagnostics = app(SystemBrainDiagnosticsService::class);
        $unsafeOnlyDir = $this->tempRoutesDir . DIRECTORY_SEPARATOR . 'only_valid';
        File::ensureDirectoryExists($unsafeOnlyDir);
        File::copy($validFile, $unsafeOnlyDir . DIRECTORY_SEPARATOR . 'valid_routes.php');

        $validAudit = $diagnostics->auditRouteDefinitionsSafety($unsafeOnlyDir, false);
        $this->assertTrue($validAudit['passed'], 'Valide Routen-Datei darf keinen Fehlalarm auslösen.');
        $this->assertSame(0, $validAudit['violations_count']);

        $fullTempAudit = $diagnostics->auditRouteDefinitionsSafety($this->tempRoutesDir, false);
        $this->assertFalse($fullTempAudit['passed']);
        $this->assertSame(8, $fullTempAudit['violations_count']);

        $rules = array_column($fullTempAudit['violations'], 'rule');
        $this->assertContains('closure_in_route_middleware', $rules);
        $this->assertContains('variable_in_route_middleware', $rules);
    }

    #[Test]
    public function extracted_api_middlewares_enforce_strict_role_domain_and_token_isolation(): void
    {
        $this->assertTrue(class_exists(EnsureAdminApiAccess::class));
        $this->assertTrue(class_exists(EnsureFunkenwerkDomainAccess::class));
        $this->assertTrue(class_exists(EnsureFullAccessToken::class));
        $this->assertTrue(class_exists(EnsureAiEndpointAccess::class));

        $admin = Admin::factory()->create();
        $customer = Customer::factory()->create();
        $empTasksOnly = $this->createInternalEmployee(canTasks: true, canProduction: false);
        $empProdOnly = $this->createInternalEmployee(canTasks: false, canProduction: true);
        $influencer = Employee::create([
            'first_name' => 'Creator',
            'last_name' => 'Partner',
            'email' => 'inf-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'role' => 'influencer',
            'email_verified_at' => now(),
            'password' => bcrypt('SecretInf!123'),
        ]);

        // 1. Unauthenticated -> 401
        $this->getJson('/api/system/ag-direct-inbox')->assertStatus(401);
        $this->getJson('/api/ai/schema')->assertStatus(401);

        // 2. Customer -> 403 across all protected API groups
        Sanctum::actingAs($customer, ['*']);
        $this->getJson('/api/system/ag-direct-inbox')->assertStatus(403);
        $this->getJson('/api/funki/tasks')->assertStatus(403);
        $this->getJson('/api/shop/orders')->assertStatus(403);
        $this->getJson('/api/ai/schema')->assertStatus(403);
        $this->postJson('/api/funki/system/app-update/publish', [])->assertStatus(403);
        $this->postJson('/api/funki/system/windows-update/publish', [])->assertStatus(403);

        // 3. Influencer -> 403 across internal endpoints
        Sanctum::actingAs($influencer, ['*']);
        $this->getJson('/api/funki/tasks')->assertStatus(403);
        $this->getJson('/api/ai/schema')->assertStatus(403);

        // 4. Internal employee domain isolation (tasks vs production)
        Sanctum::actingAs($empTasksOnly, ['*']);
        $this->getJson('/api/funki/tasks')->assertOk();
        $this->getJson('/api/shop/orders')->assertStatus(403);
        $this->getJson('/api/system/ag-direct-inbox')->assertStatus(403);
        $this->getJson('/api/ai/schema')->assertOk();

        Sanctum::actingAs($empProdOnly, ['*']);
        $this->getJson('/api/funki/tasks')->assertStatus(403);
        $this->getJson('/api/shop/orders')->assertOk();
        $this->getJson('/api/shop/workshop-focus')->assertStatus(403);

        // 5. Restricted monitor:pulse token isolation (blocked on portal & workshop-focus, allowed on logout)
        Sanctum::actingAs($admin, ['monitor:pulse']);
        $this->getJson('/api/user')->assertStatus(403);
        $this->getJson('/api/system/ag-direct-inbox')->assertStatus(403);
        $this->getJson('/api/shop/workshop-focus')->assertStatus(403);
        $this->getJson('/api/ai/schema')->assertStatus(403);
        $this->postJson('/api/logout')->assertOk();

        // 6. Mini-PC Funkira token (monitor:pulse + ai:funkira) allowed on ai/schema, blocked on EnsureFullAccessToken-gated endpoints
        Sanctum::actingAs($admin, ['monitor:pulse', 'ai:funkira']);
        $this->getJson('/api/ai/schema')->assertOk();
        $this->getJson('/api/shop/workshop-focus')->assertStatus(403);
        $this->getJson('/api/system/ag-direct-inbox')->assertStatus(403);

        // 7. Full admin token (*) allowed everywhere
        Sanctum::actingAs($admin, ['*']);
        $this->getJson('/api/user')->assertOk();
        $this->getJson('/api/system/ag-direct-inbox')->assertOk();
        $this->getJson('/api/funki/tasks')->assertOk();
        $this->getJson('/api/shop/orders')->assertOk();
        $this->getJson('/api/shop/workshop-focus')->assertOk();
        $this->getJson('/api/ai/schema')->assertOk();

        // 8. Direct ability guard verification on EnsureAdminApiAccess (without outer EnsureFullAccessToken)
        $middleware = new EnsureAdminApiAccess();
        Sanctum::actingAs($admin, ['orders:manage']);
        $reqAllowed = \Illuminate\Http\Request::create('/api/shop/workshop-focus', 'GET');
        $reqAllowed->setUserResolver(fn () => $admin);
        $resp = $middleware->handle($reqAllowed, fn () => response('ok', 200), '*', 'ai:funkira', 'orders:manage');
        $this->assertSame(200, $resp->getStatusCode());

        Sanctum::actingAs($admin, ['monitor:pulse']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $middleware->handle($reqAllowed, fn () => response('ok', 200), '*', 'ai:funkira', 'orders:manage');
    }
}
