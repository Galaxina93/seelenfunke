<?php

namespace App\Http\Middleware;

use App\Models\Employee\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalEmployeeApiAccess
{
    /**
     * Stellt sicher, dass ausschließlich aktive, interne Mitarbeiter (keine Influencer,
     * Steuerberater, Kunden oder gesperrten Accounts) auf das Mitarbeiter-Cockpit zugreifen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof Employee || !$user->isInternalEmployee() || !$user->isAccountActive()) {
            abort(403, 'Zugriff verweigert. Dieser Bereich ist für interne Mitarbeiter reserviert.');
        }

        return $next($request);
    }
}
