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
        $guardsConfig = Config::get('auth.guards', []);
        $guards = is_array($guardsConfig) ? array_keys($guardsConfig) : [];

        foreach ($guards as $guard) {
            if (!is_string($guard) || $guard === '') {
                continue;
            }
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                if ($user === null || empty($user->id)) {
                    continue;
                }

                try {
                    $expireTime = Carbon::now()->addMinute(1); // keep online for 1 min
                    \App\Services\System\RedisResilienceService::safeCachePut('is_online' . $user->id, true, $expireTime);
                } catch (\Throwable $e) {
                    if (\App\Services\System\RedisResilienceService::isRedisConnectivityException($e)) {
                        \App\Services\System\RedisResilienceService::tripCircuitBreakerAndFallback($e);
                    } else {
                        \Illuminate\Support\Facades\Log::warning('Cache connection failed during UserLastActivity middleware: ' . $e->getMessage());
                    }
                }

                if ($user->profile) {
                    $user->profile->update([
                        'last_seen' => Carbon::now(),
                        'last_ip' => $request->ip()
                    ]);
                }

                break;
            }
        }

        return $next($request);
    }
}
