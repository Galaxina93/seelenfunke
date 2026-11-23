<?php

namespace App\Http\Middleware;

use App\Services\AI\AiAuthHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminExecutiveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AiAuthHelper::isAdmin() && !auth()->guard('admin')->check()) {
            abort(403, 'TOP SECRET: Zugriff ausschließlich für die Geschäftsführung (Admin).');
        }

        return $next($request);
    }
}
