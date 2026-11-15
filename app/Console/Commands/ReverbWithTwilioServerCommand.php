<?php

namespace App\Console\Commands;

use App\Services\System\TwilioGeminiLiveBridgeService;
use App\Services\System\TwilioReverbServerFactory;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer as BaseReverbStartServer;
use React\EventLoop\Loop;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'reverb:start')]
class ReverbWithTwilioServerCommand extends BaseReverbStartServer
{
    public function handle(): void
    {
        if ($this->option('debug')) {
            $this->laravel->instance(Logger::class, new CliLogger($this->output));
        }

        TwilioGeminiLiveBridgeService::bootMetrics();

        $config = $this->laravel['config']['reverb.servers.reverb'];

        $loop = Loop::get();

        $server = TwilioReverbServerFactory::make(
            $host = $this->option('host') ?: $config['host'],
            $port = $this->option('port') ?: $config['port'],
            $path = $this->option('path') ?: $config['path'] ?? '',
            $hostname = $this->option('hostname') ?: $config['hostname'],
            max(32_768, (int) ($config['max_request_size'] ?? 10_000)),
            $config['options'] ?? [],
            loop: $loop
        );

        $this->ensureHorizontalScalability($loop);
        $this->ensureStaleConnectionsAreCleaned($loop);
        $this->ensureRestartCommandIsRespected($server, $loop, $host, $port);
        $this->ensurePulseEventsAreCollected($loop, $config['pulse_ingest_interval']);
        $this->ensureTelescopeEntriesAreCollected($loop, $config['telescope_ingest_interval'] ?? 15);

        $this->components->info(
            'Starting ' . ($server->isSecure() ? 'secure ' : '') . "Reverb + Funkira Live-Bridge server on {$host}:{$port}{$path}"
            . (($hostname && $hostname !== $host) ? " ({$hostname})" : '')
        );

        $server->start();
    }
}
