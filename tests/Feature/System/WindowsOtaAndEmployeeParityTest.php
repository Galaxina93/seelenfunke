<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Order\OrderOrder;
use App\Services\System\WindowsReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class WindowsOtaAndEmployeeParityTest extends TestCase
{
    use RefreshDatabase;

    private string $tempVaultDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempVaultDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_win_ota_test_' . Str::lower(Str::random(10));
        File::ensureDirectoryExists($this->tempVaultDir);
        $this->app->singleton(WindowsReleaseService::class, fn () => new WindowsReleaseService($this->tempVaultDir));
    }

    protected function tearDown(): void
    {
        if (isset($this->tempVaultDir) && is_dir($this->tempVaultDir)) {
            File::deleteDirectory($this->tempVaultDir);
        }

        parent::tearDown();
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'alina-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
            'password'   => bcrypt('SecretAdmin!123'),
        ]);
    }

    private function createEmployee(
        string $role = 'internal',
        bool $canTasks = true,
        bool $canProduction = false,
        bool $isActive = true
    ): Employee {
        $employee = Employee::create([
            'first_name'        => 'Max',
            'last_name'         => 'Werkstatt',
            'email'             => 'emp-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'role'              => $role,
            'email_verified_at' => now(),
            'password'          => bcrypt('SecretEmp!123'),
        ]);

        if ($role === 'internal') {
            EmployeeContract::create([
                'employee_id'           => $employee->id,
                'job_title'             => 'Manufaktur-Mitarbeiter',
                'department'            => 'Produktion',
                'employment_type'       => 'part_time',
                'compensation_type'     => 'hourly',
                'hourly_rate_cents'     => 1650,
                'weekly_hours'          => 20,
                'vacation_days_per_year'=> 24,
                'start_date'            => now()->subMonth()->toDateString(),
                'is_active'             => $isActive,
                'is_archived'           => false,
                'can_access_tasks'      => $canTasks,
                'can_access_production' => $canProduction,
                'can_access_support'    => false,
                'can_access_inventory'  => false,
            ]);
        }

        return $employee->fresh();
    }

    /**
     * @param array<string, string> $entries
     */
    private function buildTempSuiteZip(array $entries): string
    {
        $zipPath = $this->tempVaultDir . DIRECTORY_SEPARATOR . 'build_' . Str::lower(Str::random(8)) . '.zip';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        foreach ($entries as $index => $entry) {
            if (is_int($index)) {
                continue;
            }
        }
        foreach ($entries as $entryName => $content) {
            $zip->addFromString($entryName, $content);
            $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
        }

        $zip->close();

        return $zipPath;
    }

    #[Test]
    public function windows_release_service_verifies_valid_suite_zip_sha256_and_prevents_rollback(): void
    {
        /** @var WindowsReleaseService $service */
        $service = app(WindowsReleaseService::class);

        $binaryPayload = str_repeat('MZ-FUNKENWERK-SUITE-BINARY-', 900);
        $zipPath = $this->buildTempSuiteZip([
            'Funkenwerk/Funkenwerk.exe'                => $binaryPayload,
            'Module/Funkenwerk PDF/FunkenwerkPdf.exe'  => $binaryPayload,
        ]);
        $expectedSha = strtolower((string) hash_file('sha256', $zipPath));

        $manifest = $service->storeVerifiedRelease(
            sourceTempFile: $zipPath,
            versionCode: 53,
            versionName: '2.7.0',
            expectedSha256: $expectedSha,
            releaseNotes: 'Zentrales Windows OTA Release'
        );

        $this->assertSame(53, $manifest['version_code']);
        $this->assertSame('2.7.0', $manifest['version_name']);
        $this->assertSame($expectedSha, $manifest['sha256']);
        $this->assertContains('Funkenwerk', $manifest['modules']);
        $this->assertContains('FunkenwerkPdf', $manifest['modules']);

        $loaded = $service->getLatestManifest();
        $this->assertNotNull($loaded);
        $this->assertSame($expectedSha, $loaded['sha256']);
        $this->assertSame($loaded['file_size'], $loaded['size_bytes']);

        $this->expectException(RuntimeException::class);
        $service->storeVerifiedRelease(
            sourceTempFile: $zipPath,
            versionCode: 52,
            versionName: '2.6.0'
        );
    }

    #[Test]
    public function windows_release_service_rejects_zip_slip_and_archives_without_funkenwerk_binary(): void
    {
        /** @var WindowsReleaseService $service */
        $service = app(WindowsReleaseService::class);

        $padding = str_repeat('X', 20000);
        $zipSlipPath = $this->buildTempSuiteZip([
            '../evil.exe'               => $padding,
            'Funkenwerk/Funkenwerk.exe' => $padding,
        ]);

        try {
            $service->storeVerifiedRelease($zipSlipPath, 60, '2.8.0');
            $this->fail('Expected Zip-Slip exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Zip-Slip', $e->getMessage());
        }

        $nonFunkenwerkZip = $this->buildTempSuiteZip([
            'readme.txt' => $padding,
        ]);

        $this->expectException(RuntimeException::class);
        $service->storeVerifiedRelease($nonFunkenwerkZip, 61, '2.8.1');
    }

    #[Test]
    public function system_shop_config_renders_central_windows_suite_ota_card_next_to_android(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        Livewire::test(SystemShopConfig::class)
            ->assertStatus(200)
            ->assertSee('Funkenwerk Android App')
            ->assertSee('Funkenwerk Windows Suite')
            ->assertSeeHtml('data-windows-ota-card');
    }

    #[Test]
    public function api_login_returns_exact_web_dashboard_permissions_and_blocks_external_partners(): void
    {
        $admin = $this->createAdmin();
        $empTasksOnly = $this->createEmployee('internal', canTasks: true, canProduction: false);
        $empProdAndTasks = $this->createEmployee('internal', canTasks: true, canProduction: true);
        $influencer = $this->createEmployee('influencer');

        $adminRes = $this->postJson('/api/login', [
            'email'    => $admin->email,
            'password' => 'SecretAdmin!123',
        ]);
        $adminRes->assertOk()
            ->assertJsonPath('user_type', 'admin')
            ->assertJsonPath('permissions.is_admin', true)
            ->assertJsonPath('permissions.allowed_spaces.0', '*')
            ->assertJsonPath('permissions.allowed_modules.0', '*');

        $emp1Res = $this->postJson('/api/login', [
            'email'    => $empTasksOnly->email,
            'password' => 'SecretEmp!123',
        ]);
        $emp1Res->assertOk()
            ->assertJsonPath('user_type', 'employee')
            ->assertJsonPath('permissions.is_admin', false)
            ->assertJsonPath('permissions.can_access_tasks', true)
            ->assertJsonPath('permissions.can_access_production', false);
        $this->assertSame(['launcher', 'todo'], $emp1Res->json('permissions.allowed_spaces'));
        $this->assertSame(['pdf'], $emp1Res->json('permissions.allowed_modules'));

        $emp2Res = $this->postJson('/api/login', [
            'email'    => $empProdAndTasks->email,
            'password' => 'SecretEmp!123',
        ]);
        $emp2Res->assertOk()
            ->assertJsonPath('permissions.can_access_production', true);
        $this->assertSame(['launcher', 'todo', 'orders'], $emp2Res->json('permissions.allowed_spaces'));
        $this->assertSame(['pdf', 'drucker', '3d'], $emp2Res->json('permissions.allowed_modules'));

        $infRes = $this->postJson('/api/login', [
            'email'    => $influencer->email,
            'password' => 'SecretEmp!123',
        ]);
        $infRes->assertStatus(403);
    }

    #[Test]
    public function employee_api_enforces_strict_task_privacy_production_flag_and_ota_read_only_access(): void
    {
        $admin = $this->createAdmin();
        $empNoProd = $this->createEmployee('internal', canTasks: true, canProduction: false);
        $empWithProd = $this->createEmployee('internal', canTasks: true, canProduction: true);

        /** @var WindowsReleaseService $service */
        $service = app(WindowsReleaseService::class);
        $binaryPayload = str_repeat('MZ-FUNKENWERK-OTA-', 1200);
        $zipPath = $this->buildTempSuiteZip(['Funkenwerk/Funkenwerk.exe' => $binaryPayload]);
        $service->storeVerifiedRelease($zipPath, 55, '2.7.2');

        $privateList = ManagementTaskList::create([
            'name'            => 'Alina Privat',
            'icon'            => 'lock',
            'color'           => '#C5A059',
            'is_team_visible' => false,
        ]);
        $teamList = ManagementTaskList::create([
            'name'            => 'Werkstatt Team',
            'icon'            => 'sparkles',
            'color'           => '#10B981',
            'is_team_visible' => true,
        ]);

        $adminSecretTask = ManagementTask::create([
            'task_list_id'  => $privateList->id,
            'title'         => 'Geheime Steuerplanung Alina',
            'priority'      => 'high',
            'status'        => ManagementTask::STATUS_OPEN,
            'assignee_type' => 'admin',
            'assignee_id'   => (string) $admin->id,
        ]);

        $assignedTeamTask = ManagementTask::create([
            'task_list_id'  => $teamList->id,
            'title'         => 'Versandkartons nachfüllen',
            'priority'      => 'medium',
            'status'        => ManagementTask::STATUS_OPEN,
            'assignee_type' => 'employee',
            'assignee_id'   => (string) $empNoProd->id,
        ]);

        OrderOrder::create([
            'order_number'    => 'SF-2026-9901',
            'email'           => 'kunde@example.com',
            'status'          => 'pending',
            'payment_status'  => 'paid',
            'subtotal_price'  => 3490,
            'shipping_price'  => 0,
            'tax_amount'      => 557,
            'total_price'     => 3490,
            'billing_address' => ['first_name' => 'Anna', 'last_name' => 'Muster'],
        ]);

        Sanctum::actingAs($empNoProd, ['*']);

        // 1. Employee CAN check & download Windows OTA updates, CANNOT publish
        $this->getJson('/api/funki/system/windows-update/check')
            ->assertOk()
            ->assertJsonPath('has_release', true)
            ->assertJsonPath('data.version_code', 55);

        $this->get('/api/funki/system/windows-update/download')
            ->assertOk()
            ->assertHeader('X-Release-Version-Code', '55');

        $this->postJson('/api/funki/system/windows-update/publish', [])
            ->assertStatus(403);

        // 2. Employee NEVER accesses Admin-only emails, finance, command ("Nächster Schritt"), or ag-direct-inbox
        $this->getJson('/api/funki/emails/accounts')->assertStatus(403);
        $this->getJson('/api/funki/financials/kpis')->assertStatus(403);
        $this->getJson('/api/funki/command')->assertStatus(403);
        $this->getJson('/api/system/ag-direct-inbox')->assertStatus(403);

        // 3. Employee without production flag gets 403 on orders
        $this->getJson('/api/shop/orders')->assertStatus(403);

        // 4. Employee tasks API strictly hides Admin's private lists & tasks
        $listsRes = $this->getJson('/api/funki/tasks/lists')->assertOk();
        $listNames = collect($listsRes->json('data'))->pluck('name')->all();
        $this->assertContains('Werkstatt Team', $listNames);
        $this->assertNotContains('Alina Privat', $listNames);

        $tasksRes = $this->getJson('/api/funki/tasks')->assertOk();
        $taskTitles = collect($tasksRes->json('data'))->pluck('title')->all();
        $this->assertContains('Versandkartons nachfüllen', $taskTitles);
        $this->assertNotContains('Geheime Steuerplanung Alina', $taskTitles);

        // Direct tampering attempt on Admin's private task returns 403
        $this->putJson("/api/funki/tasks/{$adminSecretTask->id}/status", ['status' => 'completed'])->assertStatus(403);
        $this->putJson("/api/funki/tasks/{$assignedTeamTask->id}/status", ['status' => 'completed'])->assertOk();

        // 5. Employee WITH production contract flag CAN load shop production orders
        Sanctum::actingAs($empWithProd, ['*']);
        $this->getJson('/api/shop/orders')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    #[Test]
    public function dedicated_employee_cockpit_api_mirrors_web_employee_dashboard_1_to_1(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee('internal', canTasks: true, canProduction: true);
        $influencer = $this->createEmployee('influencer');

        // Admin & external influencer are strictly separated from internal Employee Cockpit API
        Sanctum::actingAs($admin, ['*']);
        $this->getJson('/api/employee/cockpit')->assertStatus(403);

        Sanctum::actingAs($influencer, ['*']);
        $this->getJson('/api/employee/cockpit')->assertStatus(403);

        // Internal employee gets clean 1:1 cockpit data
        Sanctum::actingAs($employee, ['*']);

        $cockpit = $this->getJson('/api/employee/cockpit')->assertOk();
        $cockpit->assertJsonPath('success', true)
            ->assertJsonPath('data.has_contract', true)
            ->assertJsonPath('data.contract.job_title', 'Manufaktur-Mitarbeiter')
            ->assertJsonPath('data.contract.can_access_tasks', true)
            ->assertJsonPath('data.contract.can_access_production', true)
            ->assertJsonPath('data.kpis.vacation_days_per_year', 24)
            ->assertJsonPath('data.running_entry', null);

        // Live Clock-In & Clock-Out
        $clockIn = $this->postJson('/api/employee/clock-in', [
            'activity_note' => 'Lasergravur & Versand',
        ])->assertOk();
        $this->assertNotNull($clockIn->json('data.running_entry.id'));

        $clockOut = $this->postJson('/api/employee/clock-out', [
            'activity_note' => 'Lasergravur & Versand abgeschlossen',
        ])->assertOk();
        $this->assertNull($clockOut->json('data.running_entry'));

        // Manual work time entry + delete
        $workDate = now()->subDay()->toDateString();
        $manualRes = $this->postJson('/api/employee/time-entries', [
            'work_date'     => $workDate,
            'start_time'    => '09:00',
            'end_time'      => '13:30',
            'break_minutes' => 30,
            'activity_note' => 'Packtisch Schicht',
        ])->assertOk();

        $entries = collect($manualRes->json('data.time_entries'));
        $createdEntry = $entries->firstWhere('activity_note', 'Packtisch Schicht');
        $this->assertNotNull($createdEntry);
        $this->assertSame('4:00 Std.', $createdEntry['formatted_hours']);

        $this->deleteJson('/api/employee/time-entries/' . $createdEntry['id'])
            ->assertOk();

        // Vacation / Sick leave entry
        $leaveDate = now()->addDays(2)->toDateString();
        $leaveRes = $this->postJson('/api/employee/leave-entries', [
            'entry_type'    => 'vacation',
            'start_date'    => $leaveDate,
            'end_date'      => $leaveDate,
            'activity_note' => 'Erholungsurlaub',
        ])->assertOk();
        $this->assertTrue($leaveRes->json('success'));

        // PDF Document endpoints (Arbeitsvertrag & Stundenzettel)
        $contractPdf = $this->get('/api/employee/documents/contract');
        $contractPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $contractPdf->headers->get('Content-Type'));

        $year = (int) now()->year;
        $month = (int) now()->month;
        $timesheetPdf = $this->get("/api/employee/documents/timesheet/{$year}/{$month}");
        $timesheetPdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $timesheetPdf->headers->get('Content-Type'));
    }
}
