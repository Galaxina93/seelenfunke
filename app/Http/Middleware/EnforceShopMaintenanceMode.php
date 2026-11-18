<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceShopMaintenanceMode
{
    public const LIVE_HOSTS = [
        'mein-seelenfunke.de',
        'www.mein-seelenfunke.de',
    ];

    public static function isLiveHost(Request $request): bool
    {
        return in_array(strtolower($request->getHost()), self::LIVE_HOSTS, true);
    }

    public static function isShopLive(?Request $request = null): bool
    {
        $req = $request ?? request();
        $isLiveHost = self::isLiveHost($req);
        $rawShopIsLive = shop_setting('shop_is_live', null);
        $rawLegacyUnlocked = shop_setting('shop_live_unlocked', null);

        if ($rawShopIsLive !== null && $rawShopIsLive !== '') {
            if (!filter_var($rawShopIsLive, FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }
            if ($rawLegacyUnlocked !== null && $rawLegacyUnlocked !== '' && !filter_var($rawLegacyUnlocked, FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }

            return true;
        }

        if ($rawLegacyUnlocked !== null && $rawLegacyUnlocked !== '') {
            return filter_var($rawLegacyUnlocked, FILTER_VALIDATE_BOOLEAN);
        }

        return !$isLiveHost;
    }

    public static function isQuoteCalculatorEnabled(): bool
    {
        return filter_var(shop_setting('quote_calculator_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function isMaintenanceModeActive(): bool
    {
        return filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Zweistufiges Schutzsystem (2-Schlüssel-Prinzip):
     * - Stufe 1 (!shop_is_live): Harte Go-Live-Sperre (Fail-Closed auf mein-seelenfunke.de).
     *   Das gesamte öffentliche Frontend bleibt ausnahmslos hinter HTTP 503 (errors.maintenance)
     *   verborgen, selbst für eingeloggte Administratoren. Nur Login, Admin-Backend, Technik-APIs
     *   und § 5 DDG Pflichtseiten bleiben erreichbar; Registrierung und OAuth sind gesperrt.
     * - Stufe 2 (shop_is_live && maintenance_mode): Klassischer Wartungsmodus (HTTP 503 für Gäste
     *   und Kunden, während eingeloggte Administratoren das Frontend vorab prüfen können).
     * - Stufe 3 (shop_is_live && !maintenance_mode): Shop öffentlich erreichbar.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shopIsLive = self::isShopLive($request);
        $maintenanceMode = filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);

        // Stufe 3: Öffentlich Live (erst wenn Live gehen = AN und Wartungsmodus = AUS)
        if ($shopIsLive && !$maintenanceMode) {
            return $next($request);
        }

        // Stufe 1: Harte Go-Live-Sperre (!shop_is_live) - gilt im Frontend selbst für eingeloggte Admins
        if (!$shopIsLive) {
            if ($request->is('login') && $request->isMethod('GET') && (Auth::guard('admin')->check() || $request->user('admin') instanceof Admin)) {
                return redirect('/admin/dashboard');
            }

            if ($request->is(
                'login',
                'login/*',
                'logout',
                'password/*',
                'admin',
                'admin/*',
                'livewire',
                'livewire/*',
                'api',
                'api/*',
                'up',
                'system/cronjob/*',
                'system/cache/clear/*',
                'notfall',
                'notfall/*',
                'impressum',
                'datenschutz',
                'datenschutzerklaerung',
                'barrierefreiheit'
            )) {
                return $next($request);
            }

            return $this->maintenanceResponse($request);
        }

        // Stufe 2: Klassischer Wartungsmodus (shop_is_live && maintenance_mode)
        if (Auth::guard('admin')->check() || $request->user('admin') instanceof Admin) {
            return $next($request);
        }

        if ($request->is(
            'login',
            'login/*',
            'logout',
            'auth/*',
            'password/*',
            'admin',
            'admin/*',
            'livewire',
            'livewire/*',
            'api',
            'api/*',
            'stripe',
            'stripe/*',
            'up',
            'gemini-live*',
            'twilio*',
            'system/cronjob/*',
            'system/cache/clear/*',
            'notfall',
            'notfall/*',
            'impressum',
            'datenschutz',
            'datenschutzerklaerung',
            'barrierefreiheit'
        )) {
            return $next($request);
        }

        return $this->maintenanceResponse($request);
    }

    private function maintenanceResponse(Request $request): Response
    {
        $headers = [
            'Retry-After' => '3600',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-Robots-Tag' => 'noindex, noarchive',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Mein Seelenfunke befindet sich aktuell im Wartungsmodus.',
            ], 503, $headers);
        }

        $rawUstId = trim((string) shop_setting('owner_ust_id', ''));
        $cleanUstId = ($rawUstId !== '' && strtoupper($rawUstId) !== 'XXX') ? $rawUstId : '';

        return response()->view('errors.maintenance', [
            'companyName' => (string) shop_setting('company_name', 'Mein Seelenfunke'),
            'proprietor' => (string) shop_setting('owner_proprietor', 'Alina Steinhauer'),
            'street' => trim((string) shop_setting('company_street', 'Carl-Goerdeler-Ring') . ' ' . (string) shop_setting('company_street_number', '26')),
            'zipCity' => trim((string) shop_setting('company_zip', '38518') . ' ' . (string) shop_setting('company_city', 'Gifhorn')),
            'email' => (string) shop_setting('company_email', 'kontakt@mein-seelenfunke.de'),
            'phone' => (string) shop_setting('company_phone', '+49 1590 1966864'),
            'ustId' => $cleanUstId,
        ], 503, $headers);
    }
}
