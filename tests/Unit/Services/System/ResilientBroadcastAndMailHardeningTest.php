<?php

namespace Tests\Unit\Services\System;

use App\Events\Management\MailReceivedEvent;
use App\Models\Management\Mail\MailAccount;
use App\Models\System\SystemLog;
use App\Services\System\RealtimeBroadcastService;
use App\Services\System\ResilientPusherBroadcaster;
use App\Services\System\SystemErrorLogger;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use Pusher\ApiErrorException;
use Pusher\Pusher;
use Tests\TestCase;

class ResilientBroadcastAndMailHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ResilientPusherBroadcaster::resetProcessState();
        RealtimeBroadcastService::resetThrottle();
    }

    public function test_broadcast_manager_resolves_reverb_and_pusher_to_resilient_broadcaster_and_falls_back_on_missing_credentials(): void
    {
        /** @var BroadcastManager $manager */
        $manager = app(BroadcastManager::class);

        Config::set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => '123456',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 6001,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ]);
        $manager->purge('reverb');

        $this->assertInstanceOf(ResilientPusherBroadcaster::class, $manager->connection('reverb'));

        Config::set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => '123456',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 6001,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ]);
        $manager->purge('pusher');

        $this->assertInstanceOf(ResilientPusherBroadcaster::class, $manager->connection('pusher'));

        Config::set('broadcasting.connections.reverb_missing', [
            'driver' => 'reverb',
            'key' => '',
            'secret' => null,
            'app_id' => '',
        ]);
        $manager->purge('reverb_missing');

        $this->assertInstanceOf(NullBroadcaster::class, $manager->connection('reverb_missing'));
    }

    public function test_resilient_pusher_broadcaster_absorbs_curl_port_6001_connection_errors_and_guards_empty_inputs(): void
    {
        $pusher = Mockery::mock(Pusher::class);
        $pusher->shouldReceive('trigger')
            ->once()
            ->andThrow(new ApiErrorException(
                'cURL error 7: Failed to connect to 127.0.0.1 port 6001 after 0 ms: Could not connect to server'
            ));

        $broadcaster = new ResilientPusherBroadcaster($pusher, false, [
            'options' => [
                'scheme' => 'http',
                'host' => '127.0.0.1',
                'port' => 6001,
            ],
        ]);

        // Empty channels / event names must be skipped without calling Pusher::trigger
        $broadcaster->broadcast([], 'MailReceivedEvent', []);
        $broadcaster->broadcast(['private-crm-inbox'], '', []);

        // Offline WebSocket server must NOT throw BroadcastException
        $broadcaster->broadcast(['private-crm-inbox'], 'MailReceivedEvent', ['ok' => true]);

        $this->assertSame(0, SystemLog::whereIn('status', ['error', 'warning'])->count());
    }

    public function test_mail_received_event_does_not_throw_or_create_error_tickets_when_reverb_port_is_unreachable(): void
    {
        Config::set('broadcasting.default', 'reverb');
        Config::set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'seelenfunke-key',
            'secret' => 'seelenfunke-secret',
            'app_id' => '348384',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 65432,
                'scheme' => 'http',
                'useTLS' => false,
            ],
            'client_options' => [
                'connect_timeout' => 1,
                'timeout' => 1,
            ],
        ]);

        /** @var BroadcastManager $manager */
        $manager = app(BroadcastManager::class);
        $manager->purge('reverb');

        $beforeErrors = SystemLog::whereIn('status', ['error', 'warning'])->count();

        // Direct event() call (as used in legacy paths) must succeed safely
        event(new MailReceivedEvent());

        // Unified helper call must also succeed safely without throwing
        $result = RealtimeBroadcastService::dispatch(new MailReceivedEvent(), 'phpunit:offline-reverb');
        $this->assertTrue($result);

        $afterErrors = SystemLog::whereIn('status', ['error', 'warning'])->count();
        $this->assertSame($beforeErrors, $afterErrors);
    }

    public function test_realtime_broadcast_service_guards_null_and_catches_unexpected_event_exceptions(): void
    {
        $this->assertFalse(RealtimeBroadcastService::dispatch(null, 'phpunit:null'));

        Log::shouldReceive('warning')->once();

        $brokenEvent = new class implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow {
            public function broadcastOn(): array
            {
                throw new \RuntimeException('Simulated broken event channel');
            }
        };

        $this->assertFalse(RealtimeBroadcastService::dispatch($brokenEvent, 'phpunit:broken-event'));
    }

    public function test_fetch_mails_command_handles_empty_or_unsafe_imap_hosts_gracefully(): void
    {
        $account = MailAccount::create([
            'name' => 'Unsafe Test Account',
            'email' => 'unsafe-test@example.com',
            'imap_host' => '127.0.0.1',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'unsafe-test@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'password' => 'valid-test-pass-123',
            'status' => 'connected',
        ]);

        $this->artisan('crm:fetch-mails')->assertSuccessful();

        $account->refresh();
        $this->assertSame('error', $account->status);
    }

    public function test_system_error_logger_diagnoses_broadcast_port_6001_errors_explicitly(): void
    {
        $diagnosis = SystemErrorLogger::diagnose(
            'Pusher error: cURL error 7: Failed to connect to 127.0.0.1 port 6001 after 0 ms: Could not connect to server.',
            \Illuminate\Broadcasting\BroadcastException::class,
            '/var/www/html/vendor/laravel/framework/src/Illuminate/Broadcasting/Broadcasters/PusherBroadcaster.php'
        );

        $this->assertSame('broadcast_server_unreachable', $diagnosis['category']);
    }
}
