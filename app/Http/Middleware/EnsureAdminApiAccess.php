<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminApiAccess
{
    /**
     * Stellt sicher, dass ausschließlich authentifizierte Administratoren (und optional
     * Tokens mit mindestens einer der geforderten Sanctum-Abilities) Zugriff erhalten.
     */
    public function handle(Request $request, Closure $next, string ...$requiredAbilities): Response
    {
        $user = $request->user();
        if (!$user instanceof Admin) {
            abort(403, 'Zugriff verweigert. Nur Administratoren sind hierfür berechtigt.');
        }

        if (!empty($requiredAbilities) && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $hasAnyAbility = false;
            foreach ($requiredAbilities as $ability) {
                if ($ability !== '' && $user->tokenCan($ability)) {
                    $hasAnyAbility = true;
                    break;
                }
            }

            if (!$hasAnyAbility) {
                abort(403, 'Unzureichende Token-Berechtigung.');
            }
        }

        return $next($request);
    }
}
