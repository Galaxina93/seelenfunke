<?php

namespace Tests\Feature\System;

use App\Models\System\SystemLog;
use App\Providers\AppServiceProvider;
use App\Services\AI\Mails\AiAgentMessageMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StageServerResilienceAndPhp85ConfigTest extends TestCase
{
    public function test_database_config_uses_pdo_mysql_attr_ssl_ca_without_deprecation(): void
    {
        $config = require base_path('config/database.php');

        $this->assertArrayHasKey('mysql', $config['connections']);
        $this->assertIsArray($config['connections']['mysql']['options']);

        $expectedConstant = defined('Pdo\Mysql::ATTR_SSL_CA')
            ? \Pdo\Mysql::ATTR_SSL_CA
            : \PDO::MYSQL_ATTR_SSL_CA;

        $this->assertIsInt($expectedConstant);

        $databaseFileContent = file_get_contents(base_path('config/database.php'));
        $this->assertStringContainsString("defined('Pdo\\Mysql::ATTR_SSL_CA')", $databaseFileContent);
    }

    public function test_app_service_provider_falls_back_to_file_when_redis_dns_fails(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('session.driver', 'redis');
        Config::set('queue.default', 'redis');
        Config::set('database.redis.default.host', 'redis-t4tm3u.pg-s-pis5pf.db.project.host.invalid');

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->assertSame('file', Config::get('cache.default'));
        $this->assertSame('file', Config::get('session.driver'));
        $this->assertSame('database', Config::get('queue.default'));
    }

    public function test_server_twilio_bridge_normalizes_live_model_and_audio_format(): void
    {
        $bridgeJs = file_get_contents(base_path('server-twilio.js'));

        $this->assertStringContainsString("const DEFAULT_LIVE_MODEL = 'models/gemini-3.1-flash-live-preview';", $bridgeJs);
        $this->assertStringContainsString('data.realtimeInput.audio =', $bridgeJs);
        $this->assertStringContainsString('isWhitespaceOnlyInterrupt', $bridgeJs);
        $this->assertStringContainsString('function diagnoseWsIssue(', $bridgeJs);
        $this->assertStringContainsString('function buildSessionDiagnostics(', $bridgeJs);
        $this->assertStringContainsString("req.url === '/health'", $bridgeJs);
        $this->assertStringNotContainsString('v1alpha.GenerativeService.BidiGenerateContent', $bridgeJs);
    }

    public function test_websocket_log_endpoint_stores_warning_status_and_sends_diagnostic_email(): void
    {
        Mail::fake();
        Cache::flush();

        $actionId = 'websocket:disconnect:google:test_' . uniqid();

        try {
            $response = $this->postJson('/api/log/websocket', [
                'type' => 'warning',
                'action_id' => $actionId,
                'message' => 'Google Live API Verbindung geschlossen. Code: 1008. Reason: Requested entity was not found.',
                'payload' => [
                    'code' => 1008,
                    'reason' => 'Requested entity was not found.',
                    'bridge_version' => 'v1.2-diag',
                    'diagnosis' => [
                        'category' => 'model_or_protocol_entity_not_found',
                        'probable_cause' => 'Google Live API lehnte Modellnamen oder Turn-Frame ab.',
                        'recommended_action' => 'Prüfe setup.model und recent_events.',
                    ],
                    'session_telemetry' => [
                        'agent_name' => 'Funkira',
                        'user_id' => 1,
                        'client_type' => 'Android App (OkHttp)',
                        'requested_model' => 'models/gemini-2.5-flash-native-audio-preview-12-2025',
                        'effective_model' => 'models/gemini-3.1-flash-live-preview',
                        'is_google_setup_complete' => true,
                        'session_duration_sec' => 14,
                        'client_audio_packets_sent' => 42,
                        'google_messages_received' => 18,
                        'google_audio_chunks_received' => 12,
                        'normalizations_applied' => [
                            'model_rewritten' => true,
                            'media_chunks_converted' => 42,
                            'empty_interrupts_dropped' => 1,
                        ],
                    ],
                    'recent_events' => [
                        ['t_ms' => 5, 'event' => 'proxy_init', 'detail' => 'agent=Funkira'],
                        ['t_ms' => 120, 'event' => 'model_normalized', 'detail' => '2.5 -> 3.1'],
                    ],
                ],
            ]);

            $response->assertOk()->assertJson(['status' => 'ok']);

            $log = SystemLog::where('action_id', $actionId)->latest('id')->first();
            $this->assertNotNull($log);
            $this->assertSame('warning', $log->status);
            $this->assertSame('websocket', $log->type);
            $this->assertArrayHasKey('server_environment', $log->payload);
            $this->assertSame('model_or_protocol_entity_not_found', $log->payload['diagnosis']['category']);

            Mail::assertSent(AiAgentMessageMail::class, function (AiAgentMessageMail $mail) {
                return str_contains($mail->messageSubject, 'Code 1008')
                    && str_contains($mail->messageContent, 'Automatische Ursachen-Analyse (Diagnose)')
                    && str_contains($mail->messageContent, 'Sitzungs- & Client-Telemetrie')
                    && str_contains($mail->messageContent, 'Letzte Ereignisse vor dem Ausfall');
            });
        } finally {
            SystemLog::where('action_id', $actionId)->delete();
        }
    }

    public function test_bootstrap_app_uses_system_log_instead_of_non_existent_global_log(): void
    {
        $bootstrapContent = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString('use App\Models\System\SystemLog;', $bootstrapContent);
        $this->assertStringNotContainsString('App\Models\Global\GlobalLog', $bootstrapContent);
        $this->assertStringContainsString('system:exception:redis', $bootstrapContent);
    }

    public function test_every_log_error_and_exception_lands_detailed_in_global_logs(): void
    {
        $uniqueMarker = 'DiagTest_' . uniqid();

        try {
            // 1. Abgefangener Log::error Aufruf (landet über MessageLogged Listener in SystemLog)
            \Illuminate\Support\Facades\Log::error("Redis connection timeout during {$uniqueMarker}", [
                'component' => 'Checkout',
            ]);

            $logEntry = SystemLog::where('message', 'like', "%{$uniqueMarker}%")->latest('id')->first();
            $this->assertNotNull($logEntry, 'Log::error entry must be written to system_logs (/admin/global-logs)');
            $this->assertSame('error', $logEntry->status);
            $this->assertSame('redis_connectivity_or_dns', $logEntry->payload['diagnosis']['category'] ?? null);
            $this->assertArrayHasKey('execution_context', $logEntry->payload);
            $this->assertArrayHasKey('server_environment', $logEntry->payload);

            // 2. Direkte Exception-Erfassung inkl. Stack-Trace & Deduplizierung
            $ex = new \RuntimeException("Database SQLSTATE error {$uniqueMarker}", 500);
            $exLog = \App\Services\System\SystemErrorLogger::recordException($ex);
            $this->assertNotNull($exLog);
            $this->assertSame('database_query_or_connection', $exLog->payload['diagnosis']['category'] ?? null);
            $this->assertNotEmpty($exLog->payload['trace_excerpt'] ?? []);
            $this->assertSame(__FILE__, $exLog->payload['file'] ?? null);

            // Dieselbe Exception-Instanz darf nicht doppelt protokolliert werden
            $duplicate = \App\Services\System\SystemErrorLogger::recordException($ex);
            $this->assertNull($duplicate);

            // 3. Frontend JS / Livewire Error Endpunkt
            $feRes = $this->postJson('/api/log/frontend-error', [
                'message' => "Uncaught TypeError in {$uniqueMarker}",
                'source' => 'https://stage.mein-seelenfunke.de/build/assets/app.js',
                'lineno' => 142,
                'colno' => 18,
                'url' => 'https://stage.mein-seelenfunke.de/admin/dashboard',
                'type' => 'js_error',
                'stack' => "TypeError: Cannot read properties of null\n    at app.js:142:18",
            ]);
            $feRes->assertOk();

            $feLog = SystemLog::where('message', 'like', "%Uncaught TypeError in {$uniqueMarker}%")->latest('id')->first();
            $this->assertNotNull($feLog);
            $this->assertSame('frontend:js_error', $feLog->action_id);
            $this->assertSame('frontend_js_error', $feLog->payload['diagnosis']['category'] ?? null);
            $this->assertSame(142, $feLog->payload['line'] ?? null);
        } finally {
            SystemLog::where('message', 'like', "%{$uniqueMarker}%")->delete();
        }
    }

    public function test_live_ai_widget_bridge_ping_endpoint_and_status_dot_markup(): void
    {
        $admin = \App\Models\Admin\Admin::first();
        if ($admin) {
            $res = $this->actingAs($admin, 'admin')->getJson('/api/ai/bridge-ping');
            $res->assertOk()
                ->assertJsonStructure([
                    'status',
                    'online',
                    'bridge_ping_ms',
                    'bridge_version',
                    'checked_at',
                ]);
        }

        $part1 = file_get_contents(resource_path('views/livewire/shop/ai/ai-widget-part1.blade.php'));
        $part2 = file_get_contents(resource_path('views/livewire/shop/ai/ai-widget-part2.blade.php'));

        $this->assertStringContainsString('pingTooltipOpen', $part1);
        $this->assertStringContainsString('getConnectionLabel()', $part1);
        $this->assertStringContainsString('checkBridgePing(true)', $part1);
        $this->assertStringContainsString("data.type === 'pong'", $part2);
        $this->assertStringContainsString('sendWsPing()', $part2);
    }
}
