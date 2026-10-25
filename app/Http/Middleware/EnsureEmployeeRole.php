<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeRole
{
    /**
     * Isoliert den Zugriff innerhalb des 'employee'-Guards strikt nach der Sub-Rolle
     * (z.B. 'internal', 'influencer', 'tax_advisor').
     */
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $employee = Auth::guard('employee')->user();

        if (!$employee) {
            abort(403, 'Zugriff verweigert.');
        }

        $currentRole = (string) ($employee->role ?: 'internal');

        if (empty($allowedRoles) || in_array($currentRole, $allowedRoles, true)) {
            return $next($request);
        }

        if ($currentRole === 'influencer') {
            return redirect()->route('employee.influencer.dashboard');
        }

        return redirect()->route('employee.dashboard');
    }
}
