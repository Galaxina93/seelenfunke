<?php

namespace Tests\Feature\System;

use App\Console\Commands\ReverbWithTwilioServerCommand;
use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\System\SystemLog;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Reverb\Servers\Reverb\Http\Connection as ReverbHttpConnection;
use Laravel\Reverb\Servers\Reverb\Http\Response as ReverbHttpResponse;
use Livewire\Livewire;
use React\Socket\ConnectionInterface;
use Tests\TestCase;

class GeminiAudioBridgeHealthCheckTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('master_analytics_system_health_cache');
        Cache::forget('master_analytics_system_health_fresh');
        Cache::forget('sys_fail_log_telephony');
    }

    public function test_url_normalization_maps_ws_streams_to_dedicated_http_health_endpoints(): void
    {
        $this->assertSame(
            'https://ws.mein-seelenfunke.de/twilio-health',
            MasterAnalytics::normalizeBridgeHealthUrl('wss://ws.mein-seelenfunke.de/twilio-stream')
        );

        $this->assertSame(
            'https://api-live-bridge.mein-seelenfunke.de/health',
            MasterAnalytics::normalizeBridgeHealthUrl('wss://api-live-bridge.mein-seelenfunke.de/gemini-live')
        );

        $this->assertSame(
            'https://api-live-bridge.mein-seelenfunke.de/health',
            MasterAnalytics::normalizeBridgeHealthUrl('https://api-live-bridge.mein-seelenfunke.de')
        );

        $this->assertSame(
            'https://api-live-bridge.mein-seelenfunke.de/health',
            MasterAnalytics::normalizeBridgeHealthUrl('https://api-live-bridge.mein-seelenfunke.de/health')
        );

        // Auf Non-Local (z. B. Live ohne eigene TWILIO_WSS_URL oder mit altem localhost:8081-Default)
        // darf kein kaputter Loopback-Check erzeugt werden.
        Config::set('services.gemini.live_bridge_url', null);
        Config::set('services.gemini.proxy_ws_url', null);
        Config::set('services.twilio.wss_url', 'wss://localhost:8081');

        $targets = MasterAnalytics::resolveTelephonyBridgeTargets();
        $this->assertArrayHasKey('live_bridge', $targets);
        $this->assertArrayNotHasKey('twilio_bridge', $targets);
        $this->assertSame('https://api-live-bridge.mein-seelenfunke.de/health', $targets['live_bridge']['url']);
    }

    public function test_master_analytics_health_check_probes_both_stage_bridge_health_endpoints_and_never_hits_twilio_stream(): void
    {
        Config::set('services.gemini.proxy_ws_url', 'wss://api-live-bridge.mein-seelenfunke.de/gemini-live');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');

        $staleFailure = SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:health_fail',
            'title' => 'Infrastruktur-Ausfall: Telephony',
            'message' => 'Die KI-Telefonie Audio-Bridge ist down! HTTP Status: 500',
            'status' => 'error',
            'payload' => ['service' => 'telephony'],
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(10),
        ]);

        Http::fake([
            'https://ws.mein-seelenfunke.de/twilio-stream*' => Http::response('WebSocket Upgrade Missing', 500),
            'https://api-live-bridge.mein-seelenfunke.de/health' => Http::response([
                'status' => 'ok',
                'bridge_version' => 'v1.5-vertex-live-secretary',
            ], 200),
            'https://ws.mein-seelenfunke.de/twilio-health' => Http::response([
                'status' => 'ok',
                'bridge_version' => 'v2.0-reverb-live-secretary',
            ], 200),
            '*' => Http::response('ok', 200),
        ]);

        $admin = Admin::factory()->create();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class)
            ->call('checkSystemHealth', true);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/twilio-stream'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api-live-bridge.mein-seelenfunke.de/health');
        Http::assertSent(fn ($request) => $request->url() === 'https://ws.mein-seelenfunke.de/twilio-health');

        $systemHealth = $component->get('systemHealth');
        $this->assertArrayHasKey('telephony', $systemHealth);
        $this->assertSame('connected', $systemHealth['telephony']['status']);
        $this->assertStringStartsWith('BEREIT (', (string) $systemHealth['telephony']['value']);
        $this->assertNull($systemHealth['telephony']['error']);
        $this->assertSame('v1.5-vertex-live-secretary', $systemHealth['telephony']['version']);
        $this->assertSame('v2.0-reverb-live-secretary', $systemHealth['telephony']['twilio_version']);
        $this->assertSame('api-live-bridge.mein-seelenfunke.de', $systemHealth['telephony']['host']);
        $this->assertSame('443', $systemHealth['telephony']['port']);

        $staleFailure->refresh();
        $this->assertSame('success', $staleFailure->status);
        $this->assertSame('[GELÖST] Infrastruktur-Ausfall: Telephony', $staleFailure->title);

        $component
            ->assertSee('api-live-bridge.mein-seelenfunke.de:443')
            ->assertSee('v1.5-vertex-live-secretary')
            ->assertDontSee('localhost:8081')
            ->assertDontSee('git reset --hard');
    }

    public function test_master_analytics_reports_mittwald_ssh_emergency_command_when_a_bridge_actually_fails(): void
    {
        Config::set('services.gemini.proxy_ws_url', 'wss://api-live-bridge.mein-seelenfunke.de/gemini-live');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');

        Http::fake([
            'https://api-live-bridge.mein-seelenfunke.de/health' => Http::response(['status' => 'error'], 503),
            'https://ws.mein-seelenfunke.de/twilio-health' => Http::response([
                'status' => 'ok',
                'bridge_version' => 'v2.0-reverb-live-secretary',
            ], 200),
            '*' => Http::response('ok', 200),
        ]);

        $admin = Admin::factory()->create();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class)
            ->call('checkSystemHealth', true);

        $systemHealth = $component->get('systemHealth');
        $this->assertSame('error', $systemHealth['telephony']['status']);
        $this->assertSame('GESTORBEN (503)', $systemHealth['telephony']['value']);
        $this->assertSame(
            'ssh kontakt@mein-seelenfunke.de@a-yeq7el@ssh.altgemeinde.project.host "mittnitectl job restart"',
            $systemHealth['telephony']['ssh_command']
        );

        $component
            ->assertSee('mittnitectl job restart')
            ->assertSee('FunkenwerkMcp.exe --stage-restart-workers')
            ->assertDontSee('git reset --hard');
    }

    public function test_reverb_twilio_stream_route_returns_426_upgrade_required_instead_of_500_on_plain_http_get(): void
    {
        $router = ReverbWithTwilioServerCommand::makeSafeTwilioRouter();
        $request = new Psr7Request('GET', 'https://ws.mein-seelenfunke.de/twilio-stream');

        $fakeSocket = new class implements ConnectionInterface {
            public int $stream = 1;
            public string $captured = '';

            public function write($data): bool
            {
                $this->captured .= (string) $data;

                return true;
            }

            public function end($data = null): void
            {
                if ($data !== null) {
                    $this->captured .= (string) $data;
                }
            }

            public function close(): void {}
            public function pause(): void {}
            public function resume(): void {}
            public function isReadable(): bool { return true; }
            public function isWritable(): bool { return true; }
            public function pipe(\React\Stream\WritableStreamInterface $dest, array $options = []): \React\Stream\WritableStreamInterface { return $dest; }
            public function on($event, callable $listener) { return $this; }
            public function once($event, callable $listener) { return $this; }
            public function removeListener($event, callable $listener): void {}
            public function removeAllListeners($event = null): void {}
            public function listeners($event = null): array { return []; }
            public function emit($event, array $arguments = []): void {}
            public function getRemoteAddress(): ?string { return '127.0.0.1:12345'; }
            public function getLocalAddress(): ?string { return '127.0.0.1:8080'; }
        };

        $httpConnection = new ReverbHttpConnection($fakeSocket);
        $router->dispatch($request, $httpConnection);

        $this->assertStringContainsString('426 Upgrade Required', $fakeSocket->captured);
        $this->assertStringContainsString('/twilio-health', $fakeSocket->captured);
    }
}
