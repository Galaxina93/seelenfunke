<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\Cache;

class CheckShopMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = Cache::rememberForever('global_shop_settings', function () {
            return ShopSetting::pluck('value', 'key')->toArray();
        });

        $isMaintenanceMode = filter_var($settings['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isMaintenanceMode) {
            // Erlaube technische Livewire-Endpunkte IMMER,
            // sonst stürzen Komponenten im Hintergrund ab.
            if ($request->is('livewire/*')) {
                return $next($request);
            }

            // Alles andere wird auf die 503-Seite geleitet
            return response()->view('global.errors.503', [], 503);
        }

        return $next($request);
    }
}
