<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class MarketingTrackingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prüfen, ob der Request UTM Parameter enthält
        if ($request->has('utm_source') || $request->has('utm_campaign') || $request->has('utm_medium')) {
            $source = $request->input('utm_source');
            $campaign = $request->input('utm_campaign');
            $medium = $request->input('utm_medium');

            $data = json_encode([
                'source' => $source,
                'campaign' => $campaign,
                'medium' => $medium,
                'timestamp' => now()->toDateTimeString()
            ]);

            // First-Touch Cookie: Nur setzen wenn es noch nicht existiert
            if (!$request->hasCookie('seelenfunke_first_touch')) {
                // 30 Tage gültig (30 * 24 * 60 = 43200 Minuten)
                Cookie::queue('seelenfunke_first_touch', $data, 43200);
            }

            // Last-Touch Cookie: Wird bei jedem neuen Besuch mit Tags überschrieben
            // 1 Tag gültig (24 * 60 = 1440 Minuten)
            Cookie::queue('seelenfunke_last_touch', $data, 1440);
        }

        return $response;
    }
}
