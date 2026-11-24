<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullAccessToken
{
    /**
     * Blockiert eingeschränkte Geräte-Tokens (z.B. monitor:pulse) für das reguläre Portal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('*')) {
            if (!$request->is('api/logout')) {
                abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
            }
        }

        return $next($request);
    }
}
