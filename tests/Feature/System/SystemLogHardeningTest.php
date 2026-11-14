<?php

namespace Tests\Feature\System;

use App\Http\Middleware\TrackVisitor;
use App\Livewire\Shop\Master\MasterAnalytics;
use App\Livewire\Shop\System\SystemLogs;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\System\SystemLog;
use App\Models\System\SystemSchedulerLog;
use App\Models\Tracking\PageVisit;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\Marketing\HolidayOfferAutopilotService;
use App\Services\System\SystemErrorLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SystemLogHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_track_visitor_handles_missing_referer_without_php84_deprecation(): void
    {
        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED || str_contains(strtolower($errstr), 'deprecated')) {
                $deprecations[] = $errstr;
            }
            return false;
        });

        try {
            $request = Request::create('http://127.0.0.1/', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/157.0.0.0 Safari/537.36',
                'REMOTE_ADDR' => '198.51.100.42',
            ]);
            $request->setLaravelSession($this->app['session']->driver());

            $middleware = new TrackVisitor();
            $middleware->terminate($request, new Response('OK', 200));
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations, 'TrackVisitor::terminate must not emit PHP 8.4 deprecations when Referer is null.');

        $visit = PageVisit::where('url', 'http://127.0.0.1')->latest('id')->first();
        $this->assertNotNull($visit);
        $this->assertNull($visit->referer);
    }

    public function test_system_error_logger_attaches_caller_trace_and_deduplicates_within_24h(): void
    {
        $marker = 'HardeningDedup_' . uniqid();

        // 1. Log event captures caller file/line/trace_excerpt and deduplicates repeated messages
        $log1 = SystemErrorLogger::recordLogEvent('warning', "mb_strwidth(): Passing null is deprecated [{$marker}]");
        $this->assertNotNull($log1);
        $this->assertSame('warning', $log1->status);
        $this->assertSame('php_deprecation_notice', $log1->payload['diagnosis']['category'] ?? null);
        $this->assertNotEmpty($log1->payload['trace_excerpt'] ?? []);
        $this->assertSame(1, $log1->payload['occurrences_count'] ?? 0);

        $log2 = SystemErrorLogger::recordLogEvent('warning', "mb_strwidth(): Passing null is deprecated [{$marker}]");
        $this->assertNotNull($log2);
        $this->assertSame($log1->id, $log2->id);
        $this->assertSame(2, $log2->fresh()->payload['occurrences_count'] ?? 0);

        // 2. Separate Throwable instances from the same line deduplicate into a single row
        $makeEx = fn () => new \RuntimeException("Transient upstream glitch [{$marker}]");
        $ex1 = $makeEx();
        $ex2 = $makeEx();

        $exLog1 = SystemErrorLogger::recordException($ex1);
        $this->assertNotNull($exLog1);
        $this->assertSame(1, $exLog1->payload['occurrences_count'] ?? 0);

        // Exact same instance returns null (in-request guard)
        $this->assertNull(SystemErrorLogger::recordException($ex1));

        // Second distinct instance from same location increments occurrences_count on existing row
        $exLog2 = SystemErrorLogger::recordException($ex2);
        $this->assertNotNull($exLog2);
        $this->assertSame($exLog1->id, $exLog2->id);
        $this->assertSame(2, $exLog2->fresh()->payload['occurrences_count'] ?? 0);
    }

    public function test_cross_platform_sentinel_ignores_benign_ui_cancellation_exceptions(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('AndroidApp', ['*'])->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/system/error-ingest', [
                'platform' => 'android',
                'component' => 'AuthRepo',
                'severity' => 'error',
                'title' => 'AuthRepo: LeftCompositionCancellationException',
                'message' => 'getCurrentUser failed',
                'error_class' => 'androidx.compose.runtime.LeftCompositionCancellationException',
            ]);

        $resp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ingested', 0);

        $this->assertNull(SystemErrorLogger::recordCrossPlatformEvent([
            'platform' => 'android',
            'component' => 'EmailRepo',
            'title' => 'EmailRepo: JobCancellationException',
            'message' => 'StandaloneCoroutine was cancelled',
        ]));
    }

    public function test_system_scheduler_logs_table_and_autopilot_and_funkira_helpers_work(): void
    {
        $this->assertTrue(Schema::hasTable('system_scheduler_logs'));

        $schedLog = SystemSchedulerLog::create([
            'task_id' => 'test-cron-1',
            'task_name' => 'Test Backup',
            'started_at' => now(),
            'finished_at' => now(),
            'status' => 'success',
            'output' => 'OK',
        ]);
        $this->assertDatabaseHas('system_scheduler_logs', ['id' => $schedLog->id, 'status' => 'success']);

        $autopilot = app(HolidayOfferAutopilotService::class);
        $defs = $autopilot->getHolidayEventsDefinition();
        $overview = $autopilot->getAutopilotCalendarOverview();
        $this->assertArrayHasKey('christmas', $defs);
        $this->assertArrayHasKey('holidays_timeline', $overview);
        $this->assertArrayHasKey('season', $overview);

        $this->assertTrue(FunkiraSkillPoolService::isFunkira('Funkira'));
        $this->assertTrue(FunkiraSkillPoolService::isFunkira(['name' => 'funkira']));
        $this->assertTrue(FunkiraSkillPoolService::isFunkira(new AiAgent(['name' => 'Funkira'])));
        $this->assertFalse(FunkiraSkillPoolService::isFunkira(null));
        $this->assertFalse(FunkiraSkillPoolService::isFunkira('Funki'));
    }

    public function test_master_analytics_auto_resolves_healed_infrastructure_logs_and_system_logs_view_has_valid_tbody(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $staleDbAlert = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:health_fail',
            'title' => 'Infrastruktur-Ausfall: Database',
            'message' => 'Die primäre Datenbank ist nicht erreichbar!',
            'status' => 'error',
            'payload' => ['service' => 'database', 'occurrences_count' => 1],
            'started_at' => now()->subMinutes(20),
            'finished_at' => now()->subMinutes(20),
        ]);

        Cache::forget('master_analytics_system_health_fresh');
        Cache::forget('master_analytics_system_health_cache');

        Livewire::test(MasterAnalytics::class)
            ->call('checkSystemHealth', true);

        $staleDbAlert->refresh();
        $this->assertSame('success', $staleDbAlert->status);
        $this->assertStringStartsWith('[GELÖST]', (string) $staleDbAlert->title);

        $html = Livewire::test(SystemLogs::class)->html();
        $this->assertSame(substr_count($html, '<tbody'), substr_count($html, '</tbody>'));
        $this->assertSame(0, preg_match('/<tbody[^>]*>\s*<tbody/i', $html));
    }
}
