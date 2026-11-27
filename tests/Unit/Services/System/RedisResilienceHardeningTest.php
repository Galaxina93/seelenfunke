<?php

namespace Tests\Unit\Services\System;

use App\Http\Middleware\UserLastActivity;
use App\Services\System\FunkiraPhoneConciergeService;
use App\Services\System\RedisResilienceService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Predis\Connection\Resource\Exception\StreamInitException;
use Tests\TestCase;

class RedisResilienceHardeningTest extends TestCase
{
    public function test_uses_redis_driver_detects_limiter_session_store_broadcast_reverb_and_custom_queue(): void
    {
        Config::set('cache.default', 'file');
        Config::set('cache.limiter', 'file');
        Config::set('schedule.cache_store', 'file');
        Config::set('session.driver', 'file');
        Config::set('session.store', null);
        Config::set('queue.default', 'database');
        Config::set('broadcasting.default', 'null');
        Config::set('reverb.servers.reverb.scaling.enabled', false);

        $this->assertFalse(RedisResilienceService::usesRedisDriver());

        Config::set('cache.limiter', 'redis');
        $this->assertTrue(RedisResilienceService::usesRedisDriver());

        Config::set('cache.limiter', 'file');
        Config::set('session.store', 'redis');
        $this->assertTrue(RedisResilienceService::usesRedisDriver());

        Config::set('session.store', null);
        Config::set('broadcasting.default', 'redis');
        $this->assertTrue(RedisResilienceService::usesRedisDriver());

        Config::set('broadcasting.default', 'null');
        Config::set('reverb.servers.reverb.scaling.enabled', true);
        $this->assertTrue(RedisResilienceService::usesRedisDriver());

        Config::set('reverb.servers.reverb.scaling.enabled', false);
        Config::set('queue.connections.custom_redis', ['driver' => 'redis']);
        Config::set('queue.default', 'custom_redis');
        $this->assertTrue(RedisResilienceService::usesRedisDriver());
    }

    public function test_ensure_healthy_or_fallback_catches_unresolvable_redis_url_and_redirects_redis_store(): void
    {
        Config::set('cache.default', 'file');
        Config::set('cache.limiter', 'redis');
        Config::set('schedule.cache_store', 'redis');
        Config::set('session.driver', 'redis');
        Config::set('session.store', 'redis');
        Config::set('queue.default', 'redis');
        Config::set('broadcasting.default', 'redis');
        Config::set('reverb.servers.reverb.scaling.enabled', true);

        // Simulate REDIS_URL set while default.host remains 127.0.0.1
        Config::set('database.redis.default.host', '127.0.0.1');
        Config::set('database.redis.default.url', 'redis://default:secret@redis-t4tm3u.pg-s-pis5pf.db.project.host.invalid:6379/0');
        Config::set('database.redis.cache.host', '127.0.0.1');
        Config::set('database.redis.cache.url', 'redis://default:secret@redis-t4tm3u.pg-s-pis5pf.db.project.host.invalid:6379/1');

        RedisResilienceService::ensureHealthyOrFallback();

        $this->assertSame('file', Config::get('cache.limiter'));
        $this->assertSame('file', Config::get('schedule.cache_store'));
        $this->assertSame('file', Config::get('session.driver'));
        $this->assertSame('file', Config::get('session.store'));
        $this->assertContains(Config::get('queue.default'), ['database', 'sync']);
        $this->assertSame('null', Config::get('broadcasting.default'));
        $this->assertFalse((bool) Config::get('reverb.servers.reverb.scaling.enabled'));
        $this->assertSame('file', Config::get('cache.stores.redis.driver'));
        $this->assertFalse($this->app->resolved('redis'));

        // Even explicit Cache::store('redis') calls now operate safely on file cache
        $probeKey = 'redis_fallback_probe_' . uniqid();
        Cache::store('redis')->put($probeKey, 'ok', 10);
        $this->assertSame('ok', Cache::store('redis')->get($probeKey));
        Cache::store('redis')->forget($probeKey);
    }

    public function test_trip_circuit_breaker_preserves_rate_limiters_and_updates_schedule_mutex_stores(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('cache.limiter', 'redis');
        Config::set('schedule.cache_store', 'redis');

        RateLimiter::for('phone_concierge_test', fn () => \Illuminate\Cache\RateLimiting\Limit::perMinute(180));

        $schedule = app(Schedule::class)->useCache('redis');
        $this->app->instance(Schedule::class, $schedule);

        $streamEx = new StreamInitException(
            'php_network_getaddresses: getaddrinfo for redis-t4tm3u.pg-s-pis5pf.db.project.host failed: Temporary failure in name resolution [tcp://redis-t4tm3u.pg-s-pis5pf.db.project.host:6379]',
            0
        );

        $this->assertTrue(RedisResilienceService::isRedisConnectivityException($streamEx));
        $this->assertTrue(RedisResilienceService::isRedisConnectivityException(new \RuntimeException('Wrapper', 0, $streamEx)));
        $this->assertFalse(RedisResilienceService::isRedisConnectivityException(new \InvalidArgumentException('Regular input error')));

        RedisResilienceService::tripCircuitBreakerAndFallback($streamEx);

        $this->assertSame('file', Config::get('cache.default'));
        $this->assertSame('file', Config::get('cache.limiter'));
        $this->assertSame('file', Config::get('schedule.cache_store'));
        $this->assertNotNull(RateLimiter::limiter('phone_concierge_test'));

        $eventProp = new \ReflectionProperty(Schedule::class, 'eventMutex');
        $eventProp->setAccessible(true);
        $schedProp = new \ReflectionProperty(Schedule::class, 'schedulingMutex');
        $schedProp->setAccessible(true);
        $this->assertSame('file', $eventProp->getValue($schedule)->store);
        $this->assertSame('file', $schedProp->getValue($schedule)->store);
    }

    public function test_bind_resolved_ip_rewrites_only_url_host_without_corrupting_credentials(): void
    {
        Config::set('database.redis.default.host', 'redis-t4tm3u.db.host');
        Config::set('database.redis.default.url', 'redis://redis-t4tm3u.db.host:redis-t4tm3u.db.host@redis-t4tm3u.db.host:6379/0');
        Config::set('database.redis.cache.host', 'redis-t4tm3u.db.host');
        Config::set('database.redis.cache.url', 'redis://user:pass@redis-t4tm3u.db.host:6380/1');
        Config::set('reverb.servers.reverb.scaling.server.host', 'redis-t4tm3u.db.host');
        Config::set('reverb.servers.reverb.scaling.server.url', 'redis://redis-t4tm3u.db.host:6379/0');

        RedisResilienceService::bindResolvedIp('redis-t4tm3u.db.host', '10.20.30.40');

        $this->assertSame('10.20.30.40', Config::get('database.redis.default.host'));
        $this->assertSame('redis://redis-t4tm3u.db.host:redis-t4tm3u.db.host@10.20.30.40:6379/0', Config::get('database.redis.default.url'));
        $this->assertSame('10.20.30.40', Config::get('database.redis.cache.host'));
        $this->assertSame('redis://user:pass@10.20.30.40:6380/1', Config::get('database.redis.cache.url'));
        $this->assertSame('10.20.30.40', Config::get('reverb.servers.reverb.scaling.server.host'));
        $this->assertSame('redis://10.20.30.40:6379/0', Config::get('reverb.servers.reverb.scaling.server.url'));

        // Guard invalid inputs: must ignore without mutating config
        RedisResilienceService::bindResolvedIp('10.20.30.40', 'not-an-ip');
        $this->assertSame('10.20.30.40', Config::get('database.redis.default.host'));
    }

    public function test_safe_cache_helpers_and_phone_concierge_poll_recover_from_mid_request_predis_failure(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('database.redis.client', 'predis');
        Config::set('database.redis.cache.host', 'redis-t4tm3u.pg-s-pis5pf.db.project.host.invalid');
        Config::set('database.redis.cache.url', null);
        Config::set('database.redis.default.host', 'redis-t4tm3u.pg-s-pis5pf.db.project.host.invalid');
        Config::set('database.redis.default.url', null);
        app('cache')->forgetDriver('redis');
        app()->forgetInstance('cache.store');

        // Guard empty key handling
        $this->assertSame('fallback', RedisResilienceService::safeCacheGet('', 'fallback'));
        $this->assertFalse(RedisResilienceService::safeCachePut('', 'val', 10));
        $this->assertFalse(RedisResilienceService::safeCacheForget(''));

        // Simulate mid-request pollState() call when Predis DNS fails
        $poll = FunkiraPhoneConciergeService::pollState();

        $this->assertIsArray($poll);
        $this->assertArrayHasKey('enabled', $poll);
        $this->assertArrayHasKey('active_call', $poll);
        $this->assertSame('file', Config::get('cache.default'));

        // Subsequent safe cache operations work seamlessly on file cache
        $key = 'concierge_resilience_' . uniqid();
        $this->assertTrue(RedisResilienceService::safeCachePut($key, ['ok' => true], 30));
        $this->assertSame(['ok' => true], RedisResilienceService::safeCacheGet($key));
        $this->assertTrue(RedisResilienceService::safeCacheForget($key));
    }

    public function test_user_last_activity_middleware_survives_redis_failure_without_throwing(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('database.redis.client', 'predis');
        Config::set('database.redis.cache.host', 'redis-t4tm3u.pg-s-pis5pf.db.project.host.invalid');
        Config::set('database.redis.cache.url', null);
        app('cache')->forgetDriver('redis');
        app()->forgetInstance('cache.store');

        $fakeUser = new \stdClass();
        $fakeUser->id = '00000000-0000-0000-0000-000000000099';
        $fakeUser->profile = null;

        $guardMock = \Mockery::mock();
        $guardMock->shouldReceive('check')->andReturn(true);
        $guardMock->shouldReceive('user')->andReturn($fakeUser);

        Config::set('auth.guards', ['web' => ['driver' => 'session']]);
        Auth::shouldReceive('guard')->with('web')->andReturn($guardMock);

        $middleware = new UserLastActivity();
        $request = Request::create('/test-online-status', 'GET');
        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('file', Config::get('cache.default'));
    }
}
