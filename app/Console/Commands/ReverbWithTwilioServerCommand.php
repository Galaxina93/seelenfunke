<?php

namespace App\Console\Commands;

use App\Services\System\TwilioGeminiLiveBridgeService;
use App\Services\System\TwilioReverbServerFactory;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Servers\Reverb\Connection as ReverbWsConnection;
use Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer as BaseReverbStartServer;
use Laravel\Reverb\Servers\Reverb\Http\Connection as ReverbHttpConnection;
use Laravel\Reverb\Servers\Reverb\Http\Response as ReverbHttpResponse;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Laravel\Reverb\Servers\Reverb\Http\Router;
use Laravel\Reverb\Servers\Reverb\Http\Server as HttpServer;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
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

        $server = self::makeSafeTwilioReverbServer(
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

    public static function makeSafeTwilioRouter(string $path = ''): Router
    {
        TwilioReverbServerFactory::makePusherRouter($path);

        return new Router(new UrlMatcher(self::buildSafeRoutes($path), new RequestContext));
    }

    protected static function makeSafeTwilioReverbServer(
        string $host = '0.0.0.0',
        string $port = '8080',
        string $path = '',
        ?string $hostname = null,
        int $maxRequestSize = 32_768,
        array $options = [],
        ?LoopInterface $loop = null
    ): HttpServer {
        $factory = new class extends TwilioReverbServerFactory {
            protected static function pusherRoutes(string $path): RouteCollection
            {
                return ReverbWithTwilioServerCommand::buildSafeRoutes($path, parent::pusherRoutes($path));
            }
        };

        return $factory::make($host, $port, $path, $hostname, $maxRequestSize, $options, 'pusher', $loop);
    }

    public static function buildSafeRoutes(string $path = '', ?RouteCollection $baseRoutes = null): RouteCollection
    {
        if ($baseRoutes === null) {
            $extractor = new class extends TwilioReverbServerFactory {
                public static function exposedPusherRoutes(string $path): RouteCollection
                {
                    return parent::pusherRoutes($path);
                }
            };
            $routes = $extractor::exposedPusherRoutes($path);
        } else {
            $routes = $baseRoutes;
        }

        $routes->add(
            'twilio_stream',
            Route::get('/twilio-stream', function (RequestInterface $request, object $connection): mixed {
                if ($connection instanceof ReverbWsConnection) {
                    (new TwilioGeminiLiveBridgeService())->handleTwilioWebSocket($request, $connection);

                    return null;
                }

                if ($connection instanceof ReverbHttpConnection) {
                    return new ReverbHttpResponse(
                        (object) [
                            'status' => 'upgrade_required',
                            'message' => 'WebSocket Upgrade Required. Use GET /twilio-health for HTTP health checks.',
                            'health_endpoint' => '/twilio-health',
                            'bridge_version' => TwilioGeminiLiveBridgeService::BRIDGE_VERSION,
                        ],
                        426,
                        ['Upgrade' => 'websocket']
                    );
                }

                return null;
            })
        );

        return $routes;
    }
}

