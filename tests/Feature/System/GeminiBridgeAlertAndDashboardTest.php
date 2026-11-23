<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\System\SystemLog;
use App\Services\AI\Mails\AiAgentMessageMail;
use App\Services\FirebaseService;
use App\Services\System\SentinelTriageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GeminiBridgeAlertAndDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $backupInbox = null;
    private ?string $backupHandoff = null;

    protected function setUp(): void
    {
        parent::setUp();

        $inboxPath = storage_path(SentinelTriageService::INBOX_FILE);
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);

        $this->backupInbox = is_file($inboxPath) ? (string) file_get_contents($inboxPath) : null;
        $this->backupHandoff = is_file($handoffPath) ? (string) file_get_contents($handoffPath) : null;

        Cache::forget('master_analytics_system_health_cache');
        Cache::forget('master_analytics_system_health_fresh');
        Cache::forget('sys_fail_log_telephony');
        Cache::forget(SentinelTriageService::BRIDGE_ALERT_COOLDOWN_PREFIX . 'gemini_live');
        Cache::forget(SentinelTriageService::BRIDGE_ALERT_COOLDOWN_PREFIX . 'twilio_reverb');
    }

    protected function tearDown(): void
    {
        $inboxPath = storage_path(SentinelTriageService::INBOX_FILE);
        $handoffPath = storage_path(SentinelTriageService::HANDOFF_FILE);

        if ($this->backupInbox !== null) {
            File::ensureDirectoryExists(dirname($inboxPath));
            file_put_contents($inboxPath, $this->backupInbox);
        } elseif (is_file($inboxPath)) {
            @unlink($inboxPath);
        }

        if ($this->backupHandoff !== null) {
            File::ensureDirectoryExists(dirname($handoffPath));
            file_put_contents($handoffPath, $this->backupHandoff);
        } elseif (is_file($handoffPath)) {
            @unlink($handoffPath);
        }

        parent::tearDown();
    }

    public function test_healthy_bridges_show_green_in_master_analytics_and_create_zero_sentinel_errors(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        Mail::fake();

        Config::set('services.gemini.proxy_ws_url', 'wss://api-live-bridge.mein-seelenfunke.de/gemini-live');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');

        Http::fake([
            'https://ws.mein-seelenfunke.de/twilio-stream*' => Http::response('WebSocket Upgrade Required', 500),
            'https://ws.mein-seelenfunke.de/twilio-health' => Http::response([
                'status' => 'ok',
                'bridge_version' => 'v2.0-reverb-live-secretary',
            ], 200),
            'https://api-live-bridge.mein-seelenfunke.de/health' => Http::response([
                'status' => 'ok',
                'bridge_version' => 'v1.5-vertex-live-secretary',
            ], 200),
            '*' => Http::response('ok', 200),
        ]);

        $sentinel = app(SentinelTriageService::class);
        $triageResult = $sentinel->runTriageCycle(probeBridges: true, allowAiEnrichment: false);

        $this->assertSame(2, $triageResult['probed_bridges']);
        $this->assertSame(0, SystemLog::openIssues()->count());
        Mail::assertNothingSent();

        $admin = Admin::factory()->create();
        $component = Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class)
            ->call('checkSystemHealth', true);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/twilio-stream'));
        Http::assertSent(fn ($request) => $request->url() === 'https://ws.mein-seelenfunke.de/twilio-health');
        Http::assertSent(fn ($request) => $request->url() === 'https://api-live-bridge.mein-seelenfunke.de/health');

        $systemHealth = $component->get('systemHealth');
        $this->assertSame('connected', $systemHealth['telephony']['status']);
        $this->assertNull($systemHealth['telephony']['error']);

        $alertState = $component->get('geminiBridgeAlert');
        $this->assertFalse($alertState['active']);
        $this->assertArrayNotHasKey('gemini_bridge', $component->get('healthChecks'));

        $component->assertDontSee('Gemini Live-Bridge ausgefallen');
        $this->assertSame(
            0,
            SystemLog::openIssues()
                ->where(function ($q) {
                    $q->where('action_id', 'like', 'sentinel:bridge_%')
                        ->orWhere('title', 'like', '%Telephony%')
                        ->orWhere('title', 'like', '%Bridge%');
                })
                ->count()
        );
    }

    public function test_outage_creates_portal_system_log_throttled_alert_and_red_operative_warning_then_auto_heals_on_recovery(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        Mail::fake();

        Config::set('services.gemini.proxy_ws_url', 'wss://api-live-bridge.mein-seelenfunke.de/gemini-live');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');

        $tmpFirebaseCreds = storage_path('app/private/test_fcm_creds_' . uniqid() . '.json');
        File::ensureDirectoryExists(dirname($tmpFirebaseCreds));
        file_put_contents($tmpFirebaseCreds, json_encode(['type' => 'service_account']));
        Config::set('services.firebase.credentials_file', $tmpFirebaseCreds);

        $firebaseMock = Mockery::mock(FirebaseService::class);
        $firebaseMock->shouldReceive('sendToAdmins')
            ->once()
            ->withArgs(function (string $title, string $body, array $data): bool {
                return $title === 'Gemini-Bridge ausgefallen'
                    && str_contains($body, 'a-yeq7el')
                    && ($data['bridge_key'] ?? '') === 'gemini_live';
            })
            ->andReturn(1);
        $this->app->instance(FirebaseService::class, $firebaseMock);

        try {
            // Phase 1: Gemini Live-Bridge (a-yeq7el) ist down (je 2x 502 inkl. 400ms Retry in Zyklus 1 & 2), danach in Zyklus 3 wieder 200 ok
            Http::fake([
                'https://ws.mein-seelenfunke.de/twilio-health' => Http::response(['status' => 'ok'], 200),
                'https://api-live-bridge.mein-seelenfunke.de/health' => Http::sequence()
                    ->push('Bad Gateway', 502)
                    ->push('Bad Gateway', 502)
                    ->push('Bad Gateway', 502)
                    ->push('Bad Gateway', 502)
                    ->push(['status' => 'ok', 'bridge_version' => 'v1.5-vertex-live-secretary'], 200),
                '*' => Http::response('ok', 200),
            ]);

            $sentinel = app(SentinelTriageService::class);
            $sentinel->runTriageCycle(probeBridges: true, allowAiEnrichment: false);

            // 1. Offener SystemLog-Fehler (platform = portal, action_id = sentinel:bridge_down:gemini_live) + Dossier angelegt
            $outageLog = SystemLog::openIssues()
                ->where('action_id', 'sentinel:bridge_down:gemini_live')
                ->first();

            $this->assertNotNull($outageLog);
            $this->assertSame('error', $outageLog->status);
            $this->assertSame('portal', $outageLog->resolvePlatformKey());
            $this->assertSame('queued_for_antigravity', $outageLog->triageState());
            $this->assertSame('critical', $outageLog->payload['antigravity_dossier']['criticality'] ?? null);
            $this->assertStringContainsString(
                'ssh kontakt@mein-seelenfunke.de@a-yeq7el@ssh.altgemeinde.project.host "mittnitectl job restart"',
                (string) ($outageLog->payload['ssh_restart_cmd'] ?? '')
            );

            // 2. Kein E-Mail-Spam; stattdessen exakt 1 dublettenfreies Universum-Ticket angelegt
            Mail::assertNothingSent();
            $bridgeTicket = \App\Models\Ai\AiUniverseTicket::where('error_fingerprint', 'sys_alert:bridge_outage:gemini_live')->first();
            $this->assertNotNull($bridgeTicket);
            $this->assertStringContainsString('a-yeq7el', (string) $bridgeTicket->title);
            $this->assertStringContainsString('mittnitectl job restart', (string) $bridgeTicket->messages()->first()?->content);

            // Zweiter Triage-Zyklus während derselben Ausfall-Episode: occurrences_count steigt, aber KEIN Mail-/Ticket-Spam!
            $sentinel->runTriageCycle(probeBridges: true, allowAiEnrichment: false);
            $outageLog->refresh();
            $this->assertSame(2, (int) ($outageLog->payload['occurrences_count'] ?? 0));
            Mail::assertNothingSent();
            $this->assertSame(1, \App\Models\Ai\AiUniverseTicket::where('error_fingerprint', 'sys_alert:bridge_outage:gemini_live')->count());

            // 3. MasterAnalytics zeigt sofort die rote Warn-Kachel ganz oben im Operativen Status
            $admin = Admin::factory()->create();
            $dashboard = Livewire::actingAs($admin, 'admin')
                ->test(MasterAnalytics::class);

            $alertState = $dashboard->get('geminiBridgeAlert');
            $this->assertTrue($alertState['active']);
            $this->assertSame('Gemini Live-Bridge ausgefallen', $alertState['title']);

            $healthChecks = $dashboard->get('healthChecks');
            $this->assertSame('gemini_bridge', array_key_first($healthChecks));
            $this->assertSame('error', $healthChecks['gemini_bridge']['status']);

            $dashboard
                ->assertSee('Gemini Live-Bridge ausgefallen')
                ->assertSee('a-yeq7el (HTTP 502)');

            // Phase 2: Bridge antwortet wieder mit HTTP 200 + status: "ok" -> Auto-Heal & Operativer Status zurück auf Grün
            $recoveryResult = $sentinel->runTriageCycle(probeBridges: true, allowAiEnrichment: false);
            $outageLog->refresh();

            $this->assertGreaterThanOrEqual(1, $recoveryResult['auto_healed_count']);
            $this->assertSame('success', $outageLog->status);
            $this->assertSame('auto_healed', $outageLog->triageState());
            $this->assertStringStartsWith('[AUTO-GEHEILT]', (string) $outageLog->title);
            $this->assertSame(0, SystemLog::openIssues()->where('action_id', 'like', 'sentinel:bridge_down:%')->count());
            $this->assertSame(\App\Models\Ai\AiUniverseTicket::STATUS_COMPLETED, $bridgeTicket->fresh()->status);

            $recoveredDashboard = Livewire::actingAs($admin, 'admin')
                ->test(MasterAnalytics::class);

            $this->assertFalse($recoveredDashboard->get('geminiBridgeAlert')['active']);
            $this->assertArrayNotHasKey('gemini_bridge', $recoveredDashboard->get('healthChecks'));
            $recoveredDashboard->assertDontSee('Gemini Live-Bridge ausgefallen');
        } finally {
            @unlink($tmpFirebaseCreds);
        }
    }

    public function test_400ms_retry_absorbs_single_micro_blip_without_false_alarm(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        Mail::fake();

        Config::set('services.gemini.proxy_ws_url', 'wss://api-live-bridge.mein-seelenfunke.de/gemini-live');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');

        // 1 kurzer Netzwerkblip (503) gefolgt von 200 ok beim 400ms-Sofort-Retry -> 0 Fehlerlogs, 0 Mails
        Http::fake([
            'https://ws.mein-seelenfunke.de/twilio-health' => Http::sequence()
                ->push('Gateway Blip', 503)
                ->push(['status' => 'ok'], 200),
            'https://api-live-bridge.mein-seelenfunke.de/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $sentinel = app(SentinelTriageService::class);
        $blipReport = $sentinel->probeGeminiBridges(retryOnBlip: true, persistAndAlert: true);

        $this->assertTrue($blipReport['all_healthy']);
        $this->assertSame(2, $blipReport['bridges']['twilio_reverb']['attempts']);
        $this->assertSame(0, SystemLog::openIssues()->count());
        Mail::assertNothingSent();
    }

    public function test_http_200_with_non_ok_json_status_triggers_bridge_down_alert(): void
    {
        SystemLog::openIssues()->update(['status' => 'success']);
        Mail::fake();

        Config::set('services.gemini.proxy_ws_url', 'wss://api-live-bridge.mein-seelenfunke.de/gemini-live');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');

        // HTTP 200, aber status != "ok" (auch nach 400ms Retry) -> wird als echter Ausfall erkannt
        Http::fake([
            'https://ws.mein-seelenfunke.de/twilio-health' => Http::response(['status' => 'degraded'], 200),
            'https://api-live-bridge.mein-seelenfunke.de/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $sentinel = app(SentinelTriageService::class);
        $degradedReport = $sentinel->probeGeminiBridges(retryOnBlip: true, persistAndAlert: true);
        $this->assertFalse($degradedReport['all_healthy']);

        $twilioLog = SystemLog::openIssues()
            ->whereIn('action_id', ['sentinel:bridge_down:twilio_reverb', 'sentinel:bridge_unhealthy:reverb_twilio'])
            ->first();

        $this->assertNotNull($twilioLog);
        $this->assertSame('error', $twilioLog->status);
        $this->assertStringContainsString(
            'ssh kontakt@mein-seelenfunke.de@a-iurgq8@ssh.altgemeinde.project.host "mittnitectl job restart"',
            (string) ($twilioLog->payload['ssh_restart_cmd'] ?? '')
        );
        Mail::assertNothingSent();
        $this->assertSame(
            1,
            \App\Models\Ai\AiUniverseTicket::where('error_fingerprint', 'sys_alert:bridge_outage:twilio_reverb')->count()
        );
    }
}
