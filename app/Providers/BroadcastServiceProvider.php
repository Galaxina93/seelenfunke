<?php

namespace App\Providers;

use App\Services\System\ResilientPusherBroadcaster;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $resilientPusherCreator = static function ($app, array $config) {
            if (empty($config['key']) || empty($config['secret']) || empty($config['app_id'])) {
                Log::notice('Broadcast-Verbindung ohne vollständige Credentials (key/secret/app_id) -> NullBroadcaster-Fallback aktiv.');

                return new NullBroadcaster();
            }

            $existingClientOptions = is_array($config['client_options'] ?? null) ? $config['client_options'] : [];
            $config['client_options'] = array_merge([
                'connect_timeout' => 2,
                'timeout' => 5,
            ], $existingClientOptions);

            try {
                /** @var BroadcastManager $manager */
                $manager = $app->make(BroadcastManager::class);

                return new ResilientPusherBroadcaster(
                    $manager->pusher($config),
                    (bool) ($config['jsonp'] ?? false),
                    $config
                );
            } catch (\Throwable $e) {
                Log::notice('Broadcast-Treiber konnte nicht initialisiert werden -> NullBroadcaster-Fallback aktiv: ' . $e->getMessage());

                return new NullBroadcaster();
            }
        };

        Broadcast::extend('reverb', $resilientPusherCreator);
        Broadcast::extend('pusher', $resilientPusherCreator);

        Broadcast::routes(['middleware' => ['web', 'auth:admin,customer,employee']]);

        require base_path('routes/channels.php');
    }
}
