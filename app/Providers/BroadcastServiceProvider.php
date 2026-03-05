<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // WICHTIG: admin UND customer müssen hier stehen!
        Broadcast::routes(['middleware' => ['web', 'auth:admin,customer']]);

        require base_path('routes/channels.php');
    }
}
