<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorChallenge
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('2fa_user_id')) {
            return $next($request);
        }

        return redirect()->route('login')->with('error', 'Sie müssen den Zwei-Faktor-Authentifizierungsprozess durchlaufen, um auf diese Seite zugreifen zu können.');
    }
}
