<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{

    public function handle(Request $request, Closure $next, $permission)
    {
        $user = Auth::user();

        if (!$user || !$user->role || !$user->role->permissions->pluck('name')->contains($permission)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
