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
            $request->is('admin/*')
        ) {
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
                'user_agent'  => Str::limit($request->userAgent(), 250),
                'referer'     => Str::limit($request->headers->get('referer'), 250),
                'customer_id' => Auth::guard('customer')->id(), // Erfasst, ob es ein eingeloggter Kunde ist
            ]);
        } catch (\Exception $e) {
            // Falls die Datenbank mal hängt, soll das Frontend trotzdem weiter funktionieren.
        }
    }
}
