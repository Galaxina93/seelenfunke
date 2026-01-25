<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class UserLastActivity
{
    public function handle(Request $request, Closure $next)
    {
        $guards = array_keys(Config::get('auth.guards'));

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                $expireTime = Carbon::now()->addMinute(1); // keep online for 1 min
                Cache::put('is_online' . $user->id, true, $expireTime);

                if ($user->profile) {
                    $user->profile->update(['last_seen' => Carbon::now()]);
                }

                break;
            }
        }

        return $next($request);
    }
}
