<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceShopMaintenanceMode
{
    /**
     * Schützt das öffentliche Shop-Frontend im aktiven Wartungsmodus (HTTP 503),
     * während eingeloggte Administratoren, Login/Backend, Livewire sowie alle API-,
     * Webhook- und Funkenwerk-Schnittstellen zu 100 % erreichbar bleiben.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN)) {
            return $next($request);
        }

        // 1. Eingeloggte Administratoren sehen den gesamten Shop uneingeschränkt
        if (Auth::guard('admin')->check() || $request->user('admin') instanceof Admin) {
            return $next($request);
        }

        // 2. Aussperr-Schutz: Login, Admin-Portal, Livewire, APIs, Webhooks & gesetzliche Pflichtseiten
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

        return response()->view('errors.maintenance', [
            'companyName' => (string) shop_setting('company_name', 'Mein Seelenfunke'),
            'proprietor' => (string) shop_setting('owner_proprietor', 'Alina Steinhauer'),
            'street' => trim((string) shop_setting('company_street', 'Carl-Goerdeler-Ring') . ' ' . (string) shop_setting('company_street_number', '26')),
            'zipCity' => trim((string) shop_setting('company_zip', '38518') . ' ' . (string) shop_setting('company_city', 'Gifhorn')),
            'email' => (string) shop_setting('company_email', 'kontakt@mein-seelenfunke.de'),
            'phone' => (string) shop_setting('company_phone', '+49 1590 1966864'),
            'ustId' => (string) shop_setting('owner_ust_id', ''),
        ], 503, $headers);
    }
}
