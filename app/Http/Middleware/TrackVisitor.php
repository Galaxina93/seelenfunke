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
        } catch (\Exception $e) {
            // Falls die Datenbank mal hängt, soll das Frontend trotzdem weiter funktionieren.
        }
    }
}
