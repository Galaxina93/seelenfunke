<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFunkenwerkDomainAccess
{
    /**
     * Prüft den Zugriff für Administratoren sowie aktive interne Mitarbeiter
     * (optional eingegrenzt auf eine konkrete Funkenwerk-Domäne wie 'tasks' oder 'production').
     */
    public function handle(Request $request, Closure $next, ?string $domain = null): Response
    {
        $user = $request->user();
        $isAdmin = $user instanceof Admin;

        if ($domain === null || $domain === '') {
            $isInternalEmployee = $user instanceof Employee
                && $user->isInternalEmployee()
                && $user->isAccountActive();

            if (!$isAdmin && !$isInternalEmployee) {
                abort(403, 'Zugriff verweigert. Nur für Administratoren und freigeschaltete interne Mitarbeiter zugänglich.');
            }

            return $next($request);
        }

        $allowed = $isAdmin
            || ($user instanceof Employee && $user->canAccessFunkenwerkDomain($domain));

        if (!$allowed) {
            abort(403, 'Keine Berechtigung für diesen Funkenwerk-Bereich.');
        }

        return $next($request);
    }
}
