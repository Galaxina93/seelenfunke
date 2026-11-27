<?php

namespace App\Services\System;

use App\Models\System\SystemLog;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\CacheSchedulingMutex;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Console\Scheduling\SchedulingMutex;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class RedisResilienceService
{
    private const CB_FAILURE_TTL_SECONDS = 45;
    private const CB_SUCCESS_TTL_SECONDS = 15;
    private const DNS_TIMEOUT_PROBE_SECONDS = 1.0;

    /**
     * Prüft, ob mindestens ein aktives Laravel-Subsystem auf den Redis-Treiber konfiguriert ist.
     */
    public static function usesRedisDriver(): bool
    {
        if (
            Config::get('cache.default') === 'redis'
            || Config::get('cache.limiter') === 'redis'
            || Config::get('schedule.cache_store') === 'redis'
            || Config::get('session.driver') === 'redis'
            || Config::get('session.store') === 'redis'
            || Config::get('queue.default') === 'redis'
            || Config::get('broadcasting.default') === 'redis'
            || (bool) Config::get('reverb.servers.reverb.scaling.enabled', false)
        ) {
            return true;
        }

        $queueDefault = Config::get('queue.default');
        if (is_string($queueDefault) && $queueDefault !== '') {
            $queueDriver = Config::get("queue.connections.{$queueDefault}.driver");
            if ($queueDriver === 'redis') {
                return true;
            }
        }

        return false;
    }

    /**
     * Stellt beim Boot sicher, dass konfigurierte Redis-Endpunkte per DNS & Cache-Probe erreichbar sind,
     * oder aktiviert einen idempotenten File-Cache/Session/Database-Queue Fallback mit Circuit-Breaker.
     */
    public static function ensureHealthyOrFallback(): void
    {
        if (!self::usesRedisDriver()) {
            return;
        }

        $endpoints = self::collectConfiguredEndpoints();
        $cbFile = self::resolveCircuitBreakerPath($endpoints);

        if ($cbFile !== null && is_file($cbFile)) {
            $cbAge = time() - (int) @filemtime($cbFile);
            if ($cbAge >= 0 && $cbAge < self::CB_FAILURE_TTL_SECONDS) {
                $cbState = trim((string) @file_get_contents($cbFile));
                if ($cbState === '0') {
                    self::applyFileFallback();
                    return;
                }

                if ($cbState === '1' && $cbAge < self::CB_SUCCESS_TTL_SECONDS) {
                    $resolvedMap = self::resolveEndpointHostsDns($endpoints, throwOnFailure: false);
                    if ($resolvedMap !== null) {
                        foreach ($resolvedMap as $origHost => $ip) {
                            self::bindResolvedIp($origHost, $ip);
                        }
                        return;
                    }
                }
            }
        }

        try {
            $resolvedMap = self::resolveEndpointHostsDns($endpoints, throwOnFailure: true) ?? [];
            foreach ($resolvedMap as $origHost => $ip) {
                self::bindResolvedIp($origHost, $ip);
            }

            // Aktiven TCP-/Treiber-Check gegen den konfigurierten Redis-Cache-Store durchführen
            Cache::store('redis')->get('__redis_health_check__');

            // Falls der default-Endpunkt einen anderen Host/Port als cache nutzt, auch dessen TCP-Erreichbarkeit prüfen
            self::probeDistinctDefaultSocket($endpoints, $resolvedMap);

            if ($cbFile !== null) {
                @file_put_contents($cbFile, '1', LOCK_EX);
                @chmod($cbFile, 0666);
            }
        } catch (\Throwable $redisEx) {
            self::tripCircuitBreakerAndFallback($redisEx, $cbFile, $endpoints);
        }
    }

    /**
     * Schaltet bei einem Redis-DNS-/Verbindungsfehler sofort auf den sicheren File-/Database-Fallback um,
     * setzt den Circuit-Breaker auf "0" und protokolliert das Ereignis gedrosselt (max. 1x pro Stunde).
     */
    public static function tripCircuitBreakerAndFallback(
        ?\Throwable $exception = null,
        ?string $cbFile = null,
        ?array $endpoints = null
    ): void {
        $endpoints ??= self::collectConfiguredEndpoints();
        $cbFile ??= self::resolveCircuitBreakerPath($endpoints);

        if ($cbFile !== null) {
            @file_put_contents($cbFile, '0', LOCK_EX);
            @chmod($cbFile, 0666);
        }

        self::applyFileFallback();

        if ($exception === null) {
            return;
        }

        try {
            $throttleKey = 'redis_fallback_logged_' . date('Ymd_H');
            if (!Cache::store('file')->has($throttleKey)) {
                Cache::store('file')->put($throttleKey, true, 3600);
                $errMessage = (string) $exception->getMessage();
                Log::warning('Redis nicht erreichbar (' . $errMessage . '): Automatischer Fallback auf File-Cache/Session aktiviert.');

                if (!app()->runningInConsole() && class_exists(SystemLog::class) && Schema::hasTable('system_logs')) {
                    $primaryHost = $endpoints[0]['host'] ?? (string) Config::get('database.redis.default.host', '127.0.0.1');
                    SystemLog::create([
                        'type' => 'system',
                        'action_id' => 'system:redis_fallback',
                        'title' => 'Redis-Fallback aktiviert (File-Cache)',
                        'message' => 'Redis-Host nicht erreichbar (' . substr($errMessage, 0, 250) . '). System nutzt automatisch File-Cache & File-Session.',
                        'status' => 'info',
                        'payload' => [
                            'redis_host' => $primaryHost,
                            'error' => $errMessage,
                            'active_cache_driver' => Config::get('cache.default'),
                            'active_session_driver' => Config::get('session.driver'),
                            'active_queue_driver' => Config::get('queue.default'),
                        ],
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Schaltet alle Redis-abhängigen Treiber idempotent auf File-/Database-Backends um,
     * ohne bereits registrierte RateLimiter-Callbacks zu verlieren.
     */
    public static function applyFileFallback(): void
    {
        if (Config::get('cache.default') === 'redis') {
            Config::set('cache.default', 'file');
        }
        if (Config::get('cache.limiter') === 'redis') {
            Config::set('cache.limiter', 'file');
        }
        if (Config::get('schedule.cache_store') === 'redis') {
            Config::set('schedule.cache_store', 'file');
        }
        if (Config::get('session.driver') === 'redis') {
            Config::set('session.driver', 'file');
        }
        if (Config::get('session.store') === 'redis') {
            Config::set('session.store', 'file');
        }

        $queueDefault = (string) Config::get('queue.default', '');
        $queueDriver = $queueDefault !== '' ? Config::get("queue.connections.{$queueDefault}.driver") : null;
        if ($queueDefault === 'redis' || $queueDriver === 'redis') {
            $fallbackQueue = 'database';
            try {
                if (!Schema::hasTable('jobs')) {
                    $fallbackQueue = 'sync';
                }
            } catch (\Throwable) {
                $fallbackQueue = 'database';
            }
            Config::set('queue.default', $fallbackQueue);
        }

        if (Config::get('broadcasting.default') === 'redis') {
            Config::set('broadcasting.default', 'null');
        }
        if ((bool) Config::get('reverb.servers.reverb.scaling.enabled', false)) {
            Config::set('reverb.servers.reverb.scaling.enabled', false);
        }

        // Auch explizite Cache::store('redis') Aufrufe sicher auf den lokalen File-Store umleiten
        $fileStoreConfig = Config::get('cache.stores.file');
        if (!is_array($fileStoreConfig) || ($fileStoreConfig['driver'] ?? null) !== 'file') {
            $fileStoreConfig = [
                'driver' => 'file',
                'path' => storage_path('framework/cache/data'),
                'lock_path' => storage_path('framework/cache/data'),
                'permission' => 0777,
            ];
        }
        Config::set('cache.stores.redis', $fileStoreConfig);

        self::resetResolvedRedisConsumers();
    }

    /**
     * Ersetzt einen aufgelösten Redis-Hostnamen sauber in allen relevanten Config-Keys (inkl. URLs),
     * ohne Teilstrings in Passwörtern oder Pfaden zu verändern.
     */
    public static function bindResolvedIp(string $originalHost, string $resolvedIp): void
    {
        $originalHost = trim($originalHost);
        $resolvedIp = trim($resolvedIp);
        if ($originalHost === '' || $resolvedIp === '' || !filter_var($resolvedIp, FILTER_VALIDATE_IP)) {
            return;
        }

        foreach ([
            'database.redis.default.host',
            'database.redis.cache.host',
            'reverb.servers.reverb.scaling.server.host',
        ] as $hostKey) {
            $current = Config::get($hostKey);
            if (is_string($current) && strcasecmp(self::normalizeHostString($current), $originalHost) === 0) {
                Config::set($hostKey, $resolvedIp);
            }
        }

        foreach ([
            'database.redis.default.url',
            'database.redis.cache.url',
            'reverb.servers.reverb.scaling.server.url',
        ] as $urlKey) {
            $rawUrl = Config::get($urlKey);
            if (is_string($rawUrl) && trim($rawUrl) !== '') {
                $updatedUrl = self::replaceUrlHost($rawUrl, $originalHost, $resolvedIp);
                if ($updatedUrl !== $rawUrl) {
                    Config::set($urlKey, $updatedUrl);
                }
            }
        }

        try {
            if (app()->resolved('redis')) {
                app()->forgetInstance('redis');
                app()->forgetInstance('redis.connection');
                if (app()->resolved('cache')) {
                    app('cache')->forgetDriver('redis');
                }
                if (app()->resolved('session')) {
                    app()->forgetInstance('session.store');
                }
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Erkennt Redis-/Predis-Konnektivitäts- und DNS-Ausnahmen entlang der gesamten Exception-Kette.
     */
    public static function isRedisConnectivityException(\Throwable $e): bool
    {
        $current = $e;
        for ($depth = 0; $depth < 8 && $current !== null; $depth++) {
            $classLower = strtolower(get_class($current));
            $msgLower = strtolower((string) $current->getMessage());

            if (
                str_contains($classLower, 'predis\\')
                || str_contains($classLower, 'redisexception')
                || str_contains($classLower, 'streaminitexception')
            ) {
                return true;
            }

            if (
                str_contains($msgLower, 'getaddrinfo')
                || str_contains($msgLower, 'php_network_getaddresses')
                || str_contains($msgLower, 'redis')
                || str_contains($msgLower, 'read error on connection to')
                || (str_contains($msgLower, 'connection refused') && str_contains($msgLower, '6379'))
            ) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    /**
     * Ausfallsicheres Cache::get() mit automatischem File-Store-Fallback bei Redis-Störungen.
     */
    public static function safeCacheGet(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        try {
            return Cache::get($key, $default);
        } catch (\Throwable $e) {
            if (self::isRedisConnectivityException($e) || self::usesRedisDriver()) {
                self::tripCircuitBreakerAndFallback($e);
            }
            try {
                return Cache::store('file')->get($key, $default);
            } catch (\Throwable) {
                return $default;
            }
        }
    }

    /**
     * Ausfallsicheres Cache::put() mit automatischem File-Store-Fallback bei Redis-Störungen.
     */
    public static function safeCachePut(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        if ($key === '') {
            return false;
        }

        try {
            return (bool) Cache::put($key, $value, $ttl);
        } catch (\Throwable $e) {
            if (self::isRedisConnectivityException($e) || self::usesRedisDriver()) {
                self::tripCircuitBreakerAndFallback($e);
            }
            try {
                return (bool) Cache::store('file')->put($key, $value, $ttl);
            } catch (\Throwable) {
                return false;
            }
        }
    }

    /**
     * Ausfallsicheres Cache::forget() mit automatischem File-Store-Fallback bei Redis-Störungen.
     */
    public static function safeCacheForget(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        try {
            return (bool) Cache::forget($key);
        } catch (\Throwable $e) {
            if (self::isRedisConnectivityException($e) || self::usesRedisDriver()) {
                self::tripCircuitBreakerAndFallback($e);
            }
            try {
                return (bool) Cache::store('file')->forget($key);
            } catch (\Throwable) {
                return false;
            }
        }
    }

    /**
     * Sammelt alle konfigurierten Redis-Verbindungsendpunkte inkl. URLs, Cache-Store und Reverb-Scaling.
     *
     * @return array<int, array{name: string, host: string, port: int, has_url: bool}>
     */
    public static function collectConfiguredEndpoints(): array
    {
        $connections = [
            'default' => Config::get('database.redis.default'),
            'cache' => Config::get('database.redis.cache'),
        ];

        if ((bool) Config::get('reverb.servers.reverb.scaling.enabled', false)) {
            $connections['reverb_scaling'] = Config::get('reverb.servers.reverb.scaling.server');
        }

        $endpoints = [];
        foreach ($connections as $name => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }

            $rawUrl = is_string($cfg['url'] ?? null) ? trim((string) $cfg['url']) : '';
            $rawHost = is_string($cfg['host'] ?? null) ? trim((string) $cfg['host']) : '';
            $rawPort = $cfg['port'] ?? 6379;

            $effectiveHost = '';
            $effectivePort = is_numeric($rawPort) ? (int) $rawPort : 6379;
            $hasUrl = false;

            if ($rawUrl !== '') {
                $hasUrl = true;
                $parsed = @parse_url($rawUrl);
                if (is_array($parsed) && !empty($parsed['host'])) {
                    $effectiveHost = self::normalizeHostString((string) $parsed['host']);
                    if (!empty($parsed['port']) && is_numeric($parsed['port'])) {
                        $effectivePort = (int) $parsed['port'];
                    }
                } else {
                    // Ungültige URL darf nicht stillschweigend ignoriert werden
                    $effectiveHost = '__invalid_redis_url__';
                }
            } else {
                $effectiveHost = self::normalizeHostString($rawHost !== '' ? $rawHost : '127.0.0.1');
            }

            $effectivePort = max(1, min(65535, $effectivePort));

            $endpoints[] = [
                'name' => $name,
                'host' => $effectiveHost,
                'port' => $effectivePort,
                'has_url' => $hasUrl,
            ];
        }

        return $endpoints;
    }

    /**
     * Löst alle nicht-lokalen Hostnamen der Endpunkte per IPv4-DNS auf.
     *
     * @param  array<int, array{name: string, host: string, port: int, has_url: bool}>  $endpoints
     * @return array<string, string>|null  Map von Original-Host -> aufgelöste IPv4, oder null bei Fehler (wenn throwOnFailure=false)
     */
    private static function resolveEndpointHostsDns(array $endpoints, bool $throwOnFailure): ?array
    {
        $resolvedMap = [];
        foreach ($endpoints as $endpoint) {
            $host = (string) ($endpoint['host'] ?? '');
            if ($host === '' || $host === '__invalid_redis_url__') {
                if ($throwOnFailure) {
                    throw new \RuntimeException('Invalid or empty Redis host configuration.');
                }
                return null;
            }

            if (filter_var($host, FILTER_VALIDATE_IP) || strtolower($host) === 'localhost') {
                continue;
            }

            if (isset($resolvedMap[$host])) {
                continue;
            }

            $resolved = @gethostbyname($host);
            if ($resolved === $host || !filter_var($resolved, FILTER_VALIDATE_IP)) {
                if ($throwOnFailure) {
                    throw new \RuntimeException("DNS resolution failed for Redis host: {$host}");
                }
                return null;
            }

            $resolvedMap[$host] = $resolved;
        }

        return $resolvedMap;
    }

    /**
     * Prüft die TCP-Erreichbarkeit des `default`-Endpunkts, falls dessen Host/Port vom `cache`-Endpunkt abweicht.
     *
     * @param  array<int, array{name: string, host: string, port: int, has_url: bool}>  $endpoints
     * @param  array<string, string>  $resolvedMap
     */
    private static function probeDistinctDefaultSocket(array $endpoints, array $resolvedMap): void
    {
        $byName = [];
        foreach ($endpoints as $ep) {
            $byName[$ep['name']] = $ep;
        }

        $default = $byName['default'] ?? null;
        $cache = $byName['cache'] ?? null;
        if ($default === null) {
            return;
        }

        $defaultIp = $resolvedMap[$default['host']] ?? $default['host'];
        $cacheIp = $cache !== null ? ($resolvedMap[$cache['host']] ?? $cache['host']) : null;

        if ($cache !== null && $defaultIp === $cacheIp && $default['port'] === $cache['port']) {
            return;
        }

        $target = filter_var($defaultIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? "tcp://[{$defaultIp}]:{$default['port']}"
            : "tcp://{$defaultIp}:{$default['port']}";

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, self::DNS_TIMEOUT_PROBE_SECONDS);
        if (!is_resource($socket)) {
            throw new \RuntimeException("Redis default endpoint unreachable ({$target}): {$errstr}", (int) $errno);
        }
        @fclose($socket);
    }

    /**
     * Bereinigt bereits aufgelöste Singletons sicher und erhält registrierte RateLimiter-Callbacks.
     */
    private static function resetResolvedRedisConsumers(): void
    {
        try {
            if (app()->resolved('cache')) {
                app('cache')->forgetDriver('redis');
            }
            app()->forgetInstance('cache.store');
            app()->forgetInstance('redis');
            app()->forgetInstance('redis.connection');
            if (app()->resolved('session')) {
                app()->forgetInstance('session.store');
            }

            if (app()->resolved(\Illuminate\Console\Scheduling\Schedule::class)) {
                $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
                if (method_exists($schedule, 'useCache')) {
                    $schedule->useCache('file');
                }
            }
            if (app()->resolved(CacheEventMutex::class)) {
                $eventMutex = app(CacheEventMutex::class);
                if (method_exists($eventMutex, 'useStore')) {
                    $eventMutex->useStore('file');
                }
            }
            app()->forgetInstance(EventMutex::class);

            if (app()->resolved(CacheSchedulingMutex::class)) {
                $schedMutex = app(CacheSchedulingMutex::class);
                if (method_exists($schedMutex, 'useStore')) {
                    $schedMutex->useStore('file');
                }
            }
            app()->forgetInstance(SchedulingMutex::class);

            $savedLimiters = [];
            if (app()->resolved(CacheRateLimiter::class)) {
                try {
                    $existingLimiter = app(CacheRateLimiter::class);
                    $prop = new \ReflectionProperty(CacheRateLimiter::class, 'limiters');
                    $prop->setAccessible(true);
                    $raw = $prop->getValue($existingLimiter);
                    if (is_array($raw)) {
                        $savedLimiters = $raw;
                    }
                } catch (\Throwable) {
                }
            }

            app()->forgetInstance(CacheRateLimiter::class);
            RateLimiter::clearResolvedInstances();

            if (!empty($savedLimiters)) {
                $freshLimiter = app(CacheRateLimiter::class);
                foreach ($savedLimiters as $name => $callback) {
                    if (is_string($name) && is_callable($callback)) {
                        $freshLimiter->for($name, $callback);
                    }
                }
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param  array<int, array{name: string, host: string, port: int, has_url: bool}>  $endpoints
     */
    private static function resolveCircuitBreakerPath(array $endpoints): ?string
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        $fingerprintParts = [];
        foreach ($endpoints as $ep) {
            $fingerprintParts[] = ($ep['name'] ?? '') . ':' . ($ep['host'] ?? '') . ':' . ($ep['port'] ?? 6379);
        }
        $signature = empty($fingerprintParts)
            ? (string) Config::get('database.redis.default.host', '127.0.0.1')
            : implode('|', $fingerprintParts);

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/sf_redis_cb_' . md5($signature) . '.state';
    }

    private static function normalizeHostString(string $host): string
    {
        $clean = trim($host);
        $clean = (string) preg_replace('#^[a-z0-9+.-]+://#i', '', $clean);
        if (str_starts_with($clean, '[')) {
            $clean = trim(explode(']', $clean)[0], '[]');
        } elseif (substr_count($clean, ':') === 1) {
            $clean = explode(':', $clean)[0];
        }

        return rtrim(trim($clean), '.');
    }

    private static function replaceUrlHost(string $rawUrl, string $originalHost, string $resolvedIp): string
    {
        $parsed = @parse_url($rawUrl);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return $rawUrl;
        }

        if (strcasecmp(self::normalizeHostString((string) $parsed['host']), $originalHost) !== 0) {
            return $rawUrl;
        }

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $user = $parsed['user'] ?? '';
        $pass = isset($parsed['pass']) ? ':' . $parsed['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? "{$user}{$pass}@" : '';
        $hostFormatted = filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? "[{$resolvedIp}]" : $resolvedIp;
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return "{$scheme}{$auth}{$hostFormatted}{$port}{$path}{$query}{$fragment}";
    }
}
