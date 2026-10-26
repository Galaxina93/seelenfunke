<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{

    public function handle(Request $request, Closure $next, $permission)
    {
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        $user = Auth::user() ?? Auth::guard('employee')->user();
        if (!$user) {
            abort(403, 'Unauthorized action.');
        }

        if ($user instanceof \App\Models\Employee\Employee && !$user->isInternalEmployee()) {
            abort(403, 'Unauthorized action.');
        }

        $hasPermission = false;

        if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
            $hasPermission = true;
        } elseif (is_object($user->role) && $user->role->permissions && $user->role->permissions->pluck('name')->contains($permission)) {
            $hasPermission = true;
        } elseif ($user->roles && $user->roles->flatMap(fn($r) => $r->permissions ?? collect())->pluck('name')->contains($permission)) {
            $hasPermission = true;
        }

        if (!$hasPermission) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
