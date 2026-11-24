<?php

namespace App\Http\Middleware;

use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Services\AI\AiAuthHelper;
use App\Services\System\SystemSecurityService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiEndpointAccess
{
    /**
     * Schützt interne KI-Endpunkte für Admins & aktive interne Mitarbeiter inklusive
     * Sanctum-Ability-, Token-Ablauf- sowie Cross-Origin-/CSRF-Prüfung.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?: auth()->guard('admin')->user() ?: auth()->guard('employee')->user();
        $isAdminOrEmployee = ($user instanceof Admin)
            || ($user instanceof Employee && $user->isInternalEmployee() && $user->isAccountActive())
            || AiAuthHelper::isAdmin()
            || AiAuthHelper::isEmployee();

        if (!$isAdminOrEmployee) {
            abort(403, 'Zugriff verweigert. Nur Administratoren und interne Mitarbeiter.');
        }

        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken() && !$user->tokenCan('*') && !$user->tokenCan('ai:funkira')) {
            abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
        }

        if ($request->bearerToken()) {
            $pat = PersonalAccessToken::findToken((string) $request->bearerToken());
            if ($pat && $pat->expires_at && $pat->expires_at->isPast()) {
                abort(401, 'Token abgelaufen.');
            }
            if ($pat && !$pat->can('*') && !$pat->can('ai:funkira')) {
                abort(403, 'Zugriff verweigert: Eingeschränktes Manufaktur-Monitor-Token.');
            }
        }

        // CSRF- & Cross-Origin-Schutz für Cookie-basierte (nicht-Bearer) schreibende Anfragen
        if (!$request->isMethodSafe() && !$request->bearerToken()) {
            $secFetchSite = strtolower((string) $request->header('Sec-Fetch-Site', ''));
            if ($secFetchSite === 'cross-site') {
                SystemSecurityService::logEvent(
                    'security:ai_csrf_blocked',
                    'Cross-Site KI-API Aufruf blockiert',
                    'Cross-Site Request (Sec-Fetch-Site: cross-site) auf ' . $request->path() . ' abgewehrt.',
                    'error'
                );
                abort(403, 'Cross-site request blocked.');
            }

            $origin = (string) $request->header('Origin', '');
            if ($origin !== '') {
                $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
                $requestHost = strtolower((string) $request->getHost());
                if ($originHost === '' || $originHost !== $requestHost) {
                    SystemSecurityService::logEvent(
                        'security:ai_csrf_blocked',
                        'Cross-Origin KI-API Aufruf blockiert',
                        "Abweichender Origin-Header ({$origin}) auf " . $request->path() . ' abgewehrt.',
                        'error'
                    );
                    abort(403, 'Cross-origin request blocked.');
                }
            }

            if (!app()->runningUnitTests() && $request->hasSession()) {
                $sessionToken = (string) $request->session()->token();
                $providedToken = (string) ($request->header('X-CSRF-TOKEN') ?: $request->input('_token', ''));
                if ($sessionToken === '' || $providedToken === '' || !hash_equals($sessionToken, $providedToken)) {
                    abort(419, 'CSRF token mismatch.');
                }
            }
        }

        return $next($request);
    }
}
