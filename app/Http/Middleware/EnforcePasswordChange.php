<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforcePasswordChange
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('customer')->check()) {
            $customer = Auth::guard('customer')->user();
            if ($customer->needs_password_change) {
                // Intercept GET requests only to avoid breaking Livewire actions or backend logout logic
                if ($request->isMethod('GET') && !$request->routeIs('customer.password-change-force') && !$request->is('logout') && !$request->routeIs('logout')) {
                    return redirect()->route('customer.password-change-force');
                }
            }
        }

        return $next($request);
    }
}
