<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CheckShopMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Ausnahmen definieren:
        // - Admin-Bereich URLs
        // - Eingeloggte Admins
        // - Die Login-Seite selbst (WICHTIG!)
        // - Die Login-Post-Route (damit das Formular abgesendet werden kann)
        if (
            $request->is('admin*') ||
            $request->is('login') ||
            $request->is('livewire/*') || // Wichtig für Livewire Logins
            Auth::guard('admin')->check()
        ) {
            return $next($request);
        }

        // 2. Einstellung laden
        $settings = Cache::rememberForever('global_shop_settings', function () {
            return ShopSetting::pluck('value', 'key')->toArray();
        });

        $isMaintenanceMode = filter_var($settings['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // 3. Wartungsmodus aktiv?
        if ($isMaintenanceMode) {
            return response()->view('global.errors.503', [], 503);
        }

        return $next($request);
    }
}
