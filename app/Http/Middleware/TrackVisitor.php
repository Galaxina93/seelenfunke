<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tracking\PageVisit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TrackVisitor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Terminate wird aufgerufen, NACHDEM die Response an den Browser geschickt wurde.
     * So wird die Ladezeit der Seite durch das Tracking nicht um eine Millisekunde verlängert!
     */
    public function terminate($request, $response)
    {
        // Wir tracken nur normale GET Aufrufe. Keine Livewire-Hintergrund-Updates, keine Admin-Klicks.
        if (
            !$request->isMethod('GET') ||
            $request->ajax() ||
            $request->is('livewire/*') ||
            $request->is('admin/*') ||
            $request->wantsJson() ||
            !$response->isSuccessful() // Ignoriere 404 Seiten (oft fehlende .map, .jpg, etc)
        ) {
            return;
        }

        // Ignoriere typische Asset-Muster, falls sie das Routing System erreichen
        if (preg_match('/\.(js|css|map|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/i', $request->path())) {
            return;
        }

        // Bots und Crawler ignorieren (Google, Bing, Uptime-Checks)
        $agent = strtolower($request->userAgent() ?? '');
        $isBot = preg_match('/(bot|crawl|slurp|spider|mediapartners|apis-google|yandex|bing|http|monitor|inspect|health)/i', $agent);

        if (empty($agent) || $isBot) {
            return;
        }

        // DSGVO/TDDDG-Konformität: Wir speichern niemals die echte IP,
        // sondern generieren einen anonymen Hash zusammen mit dem App-Key.
        $hashedIp = hash('sha256', $request->ip() . config('app.key'));

        try {
            PageVisit::create([
                'session_id'  => session()->getId(),
                'ip_hash'     => $hashedIp,
                'url'         => $request->fullUrl(),
                'path'        => $request->path(),
                'method'      => $request->method(),
                'user_agent'  => \Illuminate\Support\Str::limit($request->userAgent(), 250),
                'referer'     => \Illuminate\Support\Str::limit($request->headers->get('referer'), 250),
                'customer_id' => \Illuminate\Support\Facades\Auth::guard('customer')->id(), // Erfasst, ob es ein eingeloggter Kunde ist
            ]);

            // Falls der Besucher direkt über einen UTM-/Tracking-Link einsteigt (ohne vorherige /t/{slug} Weiterleitung),
            // ordnen wir den Aufruf ebenfalls dem entsprechenden MarketingTrackingLink zu.
            $alreadyAttributedId = session()->pull('attributed_tracking_link_id');
            if (!$alreadyAttributedId && ($request->filled('tid') || $request->filled('utm_source'))) {
                $rawTid = $request->query('tid', '');
                $rawUtmSource = $request->query('utm_source', '');
                $rawUtmCampaign = $request->query('utm_campaign', '');

                $tid = is_string($rawTid) ? substr(trim($rawTid), 0, 100) : '';
                $utmSource = is_string($rawUtmSource) ? substr(strtolower(trim($rawUtmSource)), 0, 100) : '';
                $utmCampaign = is_string($rawUtmCampaign) ? substr(trim($rawUtmCampaign), 0, 100) : '';

                if (\Illuminate\Support\Facades\Schema::hasTable('marketing_tracking_links')) {
                    $trkQuery = \App\Models\Marketing\MarketingTrackingLink::where('is_active', true);
                    $trk = null;
                    if ($tid !== '') {
                        $trk = (clone $trkQuery)->where('slug', $tid)->first();
                    }
                    if (!$trk && $utmSource !== '' && $utmCampaign !== '') {
                        $trk = (clone $trkQuery)
                            ->whereRaw('LOWER(utm_source) = ?', [$utmSource])
                            ->where('utm_campaign', $utmCampaign)
                            ->first();
                    }
                    if ($trk) {
                        $trk->recordHit($request->ip(), $request->userAgent(), $request->headers->get('referer'));
                    }
                }
            }
        } catch (\Exception $e) {
            // Falls die Datenbank mal hängt, soll das Frontend trotzdem weiter funktionieren.
        }
    }
}
