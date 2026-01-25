<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // 2. Füge die Morph Map hinzu
        Relation::enforceMorphMap([
            'admin'    => 'App\Models\Admin',
            'customer' => 'App\Models\Customer',
            'employee' => 'App\Models\Employee',
            'permission' => 'App\Models\Permission',
            'directory' => 'App\Models\Directory',
            'role' => 'App\Models\Role',
            // Füge hier bei Bedarf weitere Models hinzu
        ]);

        // Dein Code zum Laden der Migrationen kann hier bleiben
        $migrationsPath = database_path('migrations');
        $directories    = glob($migrationsPath.'/*', GLOB_ONLYDIR);
        $paths          = array_merge([$migrationsPath], $directories);

        $this->loadMigrationsFrom($paths);
    }
}
