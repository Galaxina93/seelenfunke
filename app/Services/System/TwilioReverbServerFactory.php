<?php

namespace App\Services\System;

use Laravel\Reverb\Servers\Reverb\Connection as ReverbWsConnection;
use Laravel\Reverb\Servers\Reverb\Factory as BaseReverbFactory;
use Laravel\Reverb\Servers\Reverb\Http\Connection as ReverbHttpConnection;
use Laravel\Reverb\Servers\Reverb\Http\Response as ReverbHttpResponse;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Routing\RouteCollection;

class TwilioReverbServerFactory extends BaseReverbFactory
{
    protected static function pusherRoutes(string $path): RouteCollection
    {
        $routes = parent::pusherRoutes($path);

        $routes->add(
            'twilio_stream',
            Route::get('/twilio-stream', function (RequestInterface $request, ReverbWsConnection $connection): void {
                (new TwilioGeminiLiveBridgeService())->handleTwilioWebSocket($request, $connection);
            })
        );

        $routes->add(
            'twilio_health',
            Route::get('/twilio-health', function (RequestInterface $request, ReverbHttpConnection $connection): ReverbHttpResponse {
                return TwilioGeminiLiveBridgeService::handleHealthHttp($request, $connection);
            })
        );

        return $routes;
    }
}
