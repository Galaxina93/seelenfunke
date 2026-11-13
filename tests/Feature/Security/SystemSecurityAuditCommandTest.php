<?php

namespace Tests\Feature\Security;

use App\Console\Commands\SystemSecurityAuditCommand;
use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Services\AnalyticsService;
use Database\Seeders\SystemCronjobSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSecurityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalReportContent = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (Storage::disk('local')->exists(SystemSecurityAuditCommand::REPORT_STORAGE_PATH)) {
            $this->originalReportContent = Storage::disk('local')->get(SystemSecurityAuditCommand::REPORT_STORAGE_PATH);
        }
    }

    protected function tearDown(): void
    {
        if ($this->originalReportContent !== null) {
            Storage::disk('local')->put(SystemSecurityAuditCommand::REPORT_STORAGE_PATH, $this->originalReportContent);
        }

        parent::tearDown();
    }

    #[Test]
    public function security_audit_command_creates_structured_report_across_all_four_layers(): void
    {
        $this->artisan('system:security-audit --skip-network --no-alert')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists(SystemSecurityAuditCommand::REPORT_STORAGE_PATH));

        $report = json_decode(Storage::disk('local')->get(SystemSecurityAuditCommand::REPORT_STORAGE_PATH), true);

        $this->assertIsArray($report);
        $this->assertSame('success', $report['status']);
        $this->assertSame(0, $report['cve_count']);
        $this->assertSame(0, $report['guardrail_errors_count']);

        // 1. Composer Layer
        $this->assertGreaterThan(10, $report['composer']['direct_count'] ?? 0);
        $this->assertGreaterThan(50, $report['composer']['locked_count'] ?? 0);

        // 2. NPM Layer
        $this->assertGreaterThan(5, $report['npm']['direct_prod_count'] ?? 0);
        $this->assertGreaterThan(50, $report['npm']['locked_count'] ?? 0);

        // 3. App Ecosystem Layer (Funkenwerk C# & Android)
        $this->assertTrue($report['apps']['funkenwerk']['exists'] ?? false);
        $this->assertSame('net9.0-windows', $report['apps']['funkenwerk']['target_framework'] ?? null);
        $this->assertTrue($report['apps']['android']['exists'] ?? false);
        $this->assertSame(34, $report['apps']['android']['compile_sdk'] ?? null);

        // 4. Guardrails Layer
        $this->assertSame(6, $report['guardrails']['total_count'] ?? 0);
        $this->assertSame(6, $report['guardrails']['passed_count'] ?? 0);
    }

    #[Test]
    public function analytics_service_reports_green_when_zero_cves_and_red_when_cves_detected(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        // 1. Clean state (0 CVEs)
        $this->artisan('system:security-audit --skip-network --no-alert')->assertExitCode(0);

        $service = app(AnalyticsService::class);
        $checks = $service->getHealthChecks();

        $this->assertArrayHasKey('security_audit', $checks);
        $this->assertSame('success', $checks['security_audit']['status']);
        $this->assertSame(0, $checks['security_audit']['count']);

        // 2. Simulated CVE state
        $report = json_decode(Storage::disk('local')->get(SystemSecurityAuditCommand::REPORT_STORAGE_PATH), true);
        $report['cve_count'] = 2;
        $report['status'] = 'error';
        Storage::disk('local')->put(SystemSecurityAuditCommand::REPORT_STORAGE_PATH, json_encode($report));

        $checksWithCve = $service->getHealthChecks();
        $this->assertSame('error', $checksWithCve['security_audit']['status']);
        $this->assertSame(2, $checksWithCve['security_audit']['count']);
        $this->assertStringContainsString('CVE', $checksWithCve['security_audit']['message']);
    }

    #[Test]
    public function master_analytics_can_trigger_security_audit_and_exposes_report_in_defense_stats(): void
    {
        $admin = Admin::factory()->create();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class)
            ->call('runSecurityAudit')
            ->assertHasNoErrors();

        $defStats = $component->get('securityDefenseStats');
        $this->assertArrayHasKey('audit_report', $defStats);
        $this->assertSame('success', $defStats['audit_report']['status'] ?? null);
    }

    #[Test]
    public function daily_security_audit_cronjob_is_registered_in_default_cronjobs(): void
    {
        $jobs = collect(SystemCronjobSeeder::getDefaultCronjobs());
        $securityJob = $jobs->firstWhere('command', 'system:security-audit');

        $this->assertNotNull($securityJob);
        $this->assertSame('30 4 * * *', $securityJob['schedule']);
        $this->assertTrue($securityJob['is_active']);
    }
}
