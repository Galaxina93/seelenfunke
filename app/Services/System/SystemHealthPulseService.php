<?php

namespace App\Services\System;

use App\Models\System\SystemLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemHealthPulseService
{
    public const UPTIME_LEDGER_FILE = 'app/private/system_uptime_90d.json';
    public const WATCHTOWER_NODES_FILE = 'app/private/watchtower_nodes.json';
    public const NAS_BACKUP_STATE_FILE = 'app/private/watchtower_nas_backup.json';

    public const SSL_CACHE_KEY = 'system_health_pulse_ssl_days_v1';
    public const LIVE_DOMAIN_CACHE_KEY = 'system_health_pulse_live_domain_v1';
    public const LIVE_DOMAIN_CACHE_TTL = 300;
    public const LIVE_PRIMARY_HOST = 'mein-seelenfunke.de';
    public const LIVE_WWW_HOST = 'www.mein-seelenfunke.de';
    public const MAX_LEDGER_DAYS = 90;

    protected static ?\Closure $liveDomainProbeResolver = null;

    private const ALLOWED_SOURCES = [
        'synology_nas' => 'Synology NAS',
        'android_watchtower' => 'Smartphone Wächter',
        'minipc_linux' => 'Werkstatt Mini-PC',
        'windows_desktop' => 'Funkenwerk Desktop',
        'cli_scheduler' => 'Interner Scheduler',
        'dashboard' => 'Admin Dashboard',
    ];

    public static function setLiveDomainProbeResolver(?\Closure $resolver): void
    {
        self::$liveDomainProbeResolver = $resolver;
    }

    public function hasCustomLiveProbeResolver(): bool
    {
        return self::$liveDomainProbeResolver !== null;
    }

    /**
     * Zentraler 4-Säulen Health-Puls (Shop & SSL, Cronjobs, Queue-Worker, Backups)
     * inklusive passivem Mittwald Live-Domain-Puls, 90-Tage-Uptime-Historie und Außen-Wächter-Registrierung.
     */
    public function evaluate(
        ?string $source = null,
        bool $strict = false,
        bool $recordSample = true,
        bool $syncLogs = false,
        ?int $overrideSslDays = null,
        ?array $overrideLiveDomain = null
    ): array {
        $startedAt = microtime(true);
        $now = now();
        $effectiveStrict = $strict || app()->environment('production', 'staging');

        if ($source !== null && $source !== '') {
            $this->recordWatchtowerNodePing($source, $now);
        }

        $shopPillar = $this->evaluateShopPillar($effectiveStrict, $overrideSslDays);
        $cronPillar = $this->evaluateCronjobsPillar($effectiveStrict, $now);
        $queuePillar = $this->evaluateQueuesPillar($effectiveStrict, $now);
        $backupPillar = $this->evaluateBackupsPillar($effectiveStrict, $now);
        $liveDomain = $this->evaluateLiveDomain($effectiveStrict, $overrideLiveDomain);

        $pillars = [
            'shop' => $shopPillar,
            'cronjobs' => $cronPillar,
            'queues' => $queuePillar,
            'backups' => $backupPillar,
        ];

        $overallStatus = 'ok';
        $criticalPillars = [];
        $warningPillars = [];

        foreach ($pillars as $key => $pillar) {
            $st = (string) ($pillar['status'] ?? 'ok');
            if ($st === 'critical') {
                $overallStatus = 'critical';
                $criticalPillars[] = $key;
            } elseif ($st === 'warning') {
                if ($overallStatus === 'ok') {
                    $overallStatus = 'warning';
                }
                $warningPillars[] = $key;
            }
        }

        $liveSt = (string) ($liveDomain['status'] ?? 'ok');
        if ($liveSt === 'critical') {
            $overallStatus = 'critical';
            $criticalPillars[] = 'live_domain';
        } elseif ($liveSt === 'warning') {
            if ($overallStatus === 'ok') {
                $overallStatus = 'warning';
            }
            $warningPillars[] = 'live_domain';
        }

        $uptimeSummary = $recordSample
            ? $this->recordAndSummarizeUptime($overallStatus, $shopPillar['status'] === 'ok', $now)
            : $this->summarizeUptime($now);

        if ($syncLogs) {
            $syncTargets = $pillars;
            if (!empty($liveDomain['probed'])) {
                $syncTargets['live_domain'] = $liveDomain;
            }
            $this->syncWithSystemLogs($syncTargets, $now);
        }

        $watchtowerSummary = $this->getWatchtowerNodesSummary($now);
        $latencyMs = round((microtime(true) - $startedAt) * 1000, 2);

        $headline = match ($overallStatus) {
            'critical' => 'Kritische Störung (' . implode(', ', array_map([$this, 'pillarLabel'], $criticalPillars)) . ')',
            'warning' => 'Hinweis aktiv (' . implode(', ', array_map([$this, 'pillarLabel'], $warningPillars)) . ')',
            default => 'Alle 4 Säulen grün',
        };

        return [
            'status' => $overallStatus,
            'healthy' => $overallStatus !== 'critical',
            'headline' => $headline,
            'environment' => (string) app()->environment(),
            'checked_at' => $now->toIso8601String(),
            'latency_ms' => $latencyMs,
            'uptime_90d_percent' => $uptimeSummary['uptime_90d_percent'],
            'strict_healthy_90d_percent' => $uptimeSummary['strict_healthy_90d_percent'],
            'tracked_days_count' => $uptimeSummary['tracked_days_count'],
            'daily_bars' => $uptimeSummary['daily_bars'],
            'pillars' => $pillars,
            'live_domain' => $liveDomain,
            'watchtower' => $watchtowerSummary,
        ];
    }

    /**
     * Nimmt den NAS-Backup-Herzschlag vom Werkstatt-Mini-PC oder der Synology NAS entgegen.
     */
    public function recordNasBackupHeartbeat(array $data): array
    {
        $now = now();
        $rawStatus = strtolower(trim((string) ($data['status'] ?? 'ok')));
        $status = in_array($rawStatus, ['ok', 'warning', 'error'], true) ? $rawStatus : 'ok';
        $source = $this->normalizeSource((string) ($data['source'] ?? 'minipc_linux'));

        $record = [
            'status' => $status,
            'summary' => Str::limit(SystemErrorLogger::sanitizeSensitiveText((string) ($data['summary'] ?? 'NAS-Sicherung gemeldet')), 280),
            'copied_count' => max(0, (int) ($data['copied_count'] ?? 0)),
            'deleted_count' => max(0, (int) ($data['deleted_count'] ?? 0)),
            'nas_host' => Str::limit(preg_replace('/[^0-9A-Za-z._:-]/', '', (string) ($data['nas_host'] ?? '')), 64, ''),
            'source' => $source,
            'reported_at' => $now->toIso8601String(),
            'reported_ts' => $now->timestamp,
        ];

        $this->writeJsonAtomically($this->resolvePrivatePath(self::NAS_BACKUP_STATE_FILE), $record);
        $this->recordWatchtowerNodePing($source, $now);

        return $record;
    }

    /**
     * Setzt die lokalen Watchtower-Zustandsdateien für isolierte PHPUnit-Tests zurück.
     */
    public function resetStateForTesting(): void
    {
        foreach ([self::UPTIME_LEDGER_FILE, self::WATCHTOWER_NODES_FILE, self::NAS_BACKUP_STATE_FILE] as $rel) {
            $path = $this->resolvePrivatePath($rel);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        Cache::forget(self::SSL_CACHE_KEY);
        Cache::forget(self::LIVE_DOMAIN_CACHE_KEY);
        self::$liveDomainProbeResolver = null;
    }

    /**
     * Liefert gecachte oder frisch gelesene Live-Domain-Messdaten (5 Minuten TTL via Cache::remember).
     */
    public function getCachedOrProbeLiveDomains(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::LIVE_DOMAIN_CACHE_KEY);
        }

        return Cache::remember(self::LIVE_DOMAIN_CACHE_KEY, self::LIVE_DOMAIN_CACHE_TTL, function (): array {
            if (self::$liveDomainProbeResolver !== null) {
                $custom = (self::$liveDomainProbeResolver)();
                if (is_array($custom)) {
                    return $custom;
                }
            }

            return $this->probeLiveDomains();
        });
    }

    /**
     * Rein lesender HTTPS-Check (0 Schreibzugriffe) für https://mein-seelenfunke.de (max. 3.0s Timeout)
     * sowie passiver Ingress-Check für https://www.mein-seelenfunke.de (1.5s Timeout).
     */
    public function probeLiveDomains(): array
    {
        $primary = $this->probeHttpsEndpointReadOnly(self::LIVE_PRIMARY_HOST, '/', 2.8);

        // Falls Root einen Redirect (301/302/307/308) liefert, 1 Hop lesend auflösen
        if (
            $primary['reachable']
            && in_array($primary['http_status'], [301, 302, 307, 308], true)
            && !empty($primary['location'])
        ) {
            $locUrl = (string) $primary['location'];
            $locHost = strtolower((string) (parse_url($locUrl, PHP_URL_HOST) ?: self::LIVE_PRIMARY_HOST));
            $locPath = (string) (parse_url($locUrl, PHP_URL_PATH) ?: '/');
            if (in_array($locHost, [self::LIVE_PRIMARY_HOST, self::LIVE_WWW_HOST], true)) {
                $followed = $this->probeHttpsEndpointReadOnly($locHost, $locPath, 2.2);
                if ($followed['reachable']) {
                    $primary['http_status'] = $followed['http_status'];
                    $primary['body_bytes'] = $followed['body_bytes'];
                    $primary['has_shop_markers'] = $followed['has_shop_markers'];
                    $primary['latency_ms'] = round($primary['latency_ms'] + $followed['latency_ms'], 2);
                }
            }
        }

        $mode = $this->classifyLiveDomainMode(
            (bool) $primary['reachable'],
            (int) $primary['http_status'],
            (int) $primary['body_bytes'],
            (bool) $primary['has_shop_markers']
        );

        $www = $this->probeHttpsEndpointReadOnly(self::LIVE_WWW_HOST, '/', 1.5);
        $wwwIngressActive = $www['reachable'] && $www['http_status'] > 0 && ($www['http_status'] < 500 || $www['http_status'] === 503);

        return [
            'probed' => true,
            'domain' => self::LIVE_PRIMARY_HOST,
            'url' => 'https://' . self::LIVE_PRIMARY_HOST,
            'reachable' => (bool) $primary['reachable'],
            'http_status' => (int) $primary['http_status'],
            'latency_ms' => (float) $primary['latency_ms'],
            'ssl_days_remaining' => $primary['ssl_days_remaining'],
            'ssl_valid_until' => $primary['ssl_valid_until'],
            'body_bytes' => (int) $primary['body_bytes'],
            'has_shop_markers' => (bool) $primary['has_shop_markers'],
            'mode' => $mode,
            'www_domain' => self::LIVE_WWW_HOST,
            'www_ingress_active' => $wwwIngressActive,
            'www_http_status' => (int) $www['http_status'],
            'www_status' => $wwwIngressActive ? 'active' : 'pending_ingress',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Klassifiziert den Betriebsmodus von mein-seelenfunke.de:
     * - 'maintenance' bei HTTP 503 (gewollter Vorab-Schutz -> gesund/ok!)
     * - 'placeholder' bei HTTP 200 und Body < 12000 Bytes ohne Laravel/Livewire-Shop-Marker (-> technisch ok!)
     * - 'live_shop' bei HTTP 200 mit aktivem Laravel/Livewire-Shop
     * - 'error' bei Nichterreichbarkeit oder HTTP >= 500 (außer 503)
     */
    public function classifyLiveDomainMode(bool $reachable, int $httpStatus, int $bodyBytes, bool $hasShopMarkers): string
    {
        if (!$reachable || $httpStatus <= 0 || ($httpStatus >= 500 && $httpStatus !== 503)) {
            return 'error';
        }

        if ($httpStatus === 503) {
            return 'maintenance';
        }

        if ($httpStatus === 200) {
            if (!$hasShopMarkers && $bodyBytes < 12000) {
                return 'placeholder';
            }

            return 'live_shop';
        }

        if ($httpStatus >= 200 && $httpStatus < 400) {
            return 'placeholder';
        }

        return 'error';
    }

    protected function evaluateLiveDomain(bool $effectiveStrict, ?array $overrideLiveDomain = null): array
    {
        if ($overrideLiveDomain !== null) {
            $raw = $overrideLiveDomain;
            $enforceStrictThresholds = true;
        } elseif (self::$liveDomainProbeResolver !== null) {
            $raw = $this->getCachedOrProbeLiveDomains(false);
            $enforceStrictThresholds = true;
        } elseif (app()->runningUnitTests()) {
            // In isolierten Unit-Tests ohne Override keine echten Internet-Calls auslösen
            return [
                'probed' => false,
                'status' => 'ok',
                'label' => 'Übersprungen (Testumgebung)',
                'issues' => [],
                'domain' => self::LIVE_PRIMARY_HOST,
                'url' => 'https://' . self::LIVE_PRIMARY_HOST,
                'reachable' => true,
                'http_status' => 200,
                'latency_ms' => 0.0,
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
                'mode' => 'placeholder',
                'www_domain' => self::LIVE_WWW_HOST,
                'www_ingress_active' => false,
                'www_http_status' => 0,
                'www_status' => 'pending_ingress',
            ];
        } elseif (!app()->environment('production', 'staging') && !$effectiveStrict && !Cache::has(self::LIVE_DOMAIN_CACHE_KEY)) {
            // Lokal ohne strict-Flag kein blockierender Netzwerk-Call beim normalen Dashboard-Aufruf
            return [
                'probed' => false,
                'status' => 'ok',
                'label' => 'Bereit (Auf Abruf lokal)',
                'issues' => [],
                'domain' => self::LIVE_PRIMARY_HOST,
                'url' => 'https://' . self::LIVE_PRIMARY_HOST,
                'reachable' => true,
                'http_status' => 200,
                'latency_ms' => 0.0,
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
                'mode' => 'placeholder',
                'www_domain' => self::LIVE_WWW_HOST,
                'www_ingress_active' => false,
                'www_http_status' => 0,
                'www_status' => 'pending_ingress',
            ];
        } else {
            $raw = $this->getCachedOrProbeLiveDomains(false);
            $enforceStrictThresholds = $effectiveStrict;
        }

        $reachable = (bool) ($raw['reachable'] ?? false);
        $httpStatus = (int) ($raw['http_status'] ?? ($reachable ? 200 : 0));
        $latencyMs = round((float) ($raw['latency_ms'] ?? 0.0), 2);
        $sslDays = array_key_exists('ssl_days_remaining', $raw) && $raw['ssl_days_remaining'] !== null
            ? (int) $raw['ssl_days_remaining']
            : null;
        $sslValidUntil = isset($raw['ssl_valid_until']) && $raw['ssl_valid_until'] !== ''
            ? (string) $raw['ssl_valid_until']
            : null;
        $bodyBytes = (int) ($raw['body_bytes'] ?? 0);
        $hasShopMarkers = (bool) ($raw['has_shop_markers'] ?? false);

        $mode = isset($raw['mode']) && in_array($raw['mode'], ['maintenance', 'placeholder', 'live_shop', 'error'], true)
            ? (string) $raw['mode']
            : $this->classifyLiveDomainMode($reachable, $httpStatus, $bodyBytes, $hasShopMarkers);

        $wwwIngressActive = (bool) ($raw['www_ingress_active'] ?? false);
        $wwwHttpStatus = (int) ($raw['www_http_status'] ?? 0);
        $wwwStatus = $wwwIngressActive ? 'active' : 'pending_ingress';

        $status = 'ok';
        $issues = [];

        if ($mode === 'error' || !$reachable || ($httpStatus >= 500 && $httpStatus !== 503)) {
            $mode = 'error';
            $status = $this->escalateStatus($status, $enforceStrictThresholds ? 'critical' : 'warning');
            $issues[] = !$reachable
                ? 'Live-Domain mein-seelenfunke.de nicht erreichbar'
                : "Live-Domain mein-seelenfunke.de meldet HTTP {$httpStatus}";
        }

        if ($sslDays !== null) {
            if ($sslDays < 7) {
                $status = $this->escalateStatus($status, $enforceStrictThresholds ? 'critical' : 'warning');
                $issues[] = "Live-SSL-Zertifikat läuft in {$sslDays} Tagen ab";
            } elseif ($sslDays < 14) {
                $status = $this->escalateStatus($status, 'warning');
                $issues[] = "Live-SSL-Zertifikat läuft in {$sslDays} Tagen ab";
            }
        }

        $label = match ($status) {
            'critical', 'warning' => $issues[0] ?? 'Live-Domain Hinweis',
            default => match ($mode) {
                'maintenance' => 'Wartungsmodus aktiv (HTTP 503 · geschützt)',
                'placeholder' => 'Mittwald-Platzhalter aktiv (HTTP 200)',
                'live_shop' => $latencyMs > 0 ? "Live-Shop online ({$latencyMs} ms)" : 'Live-Shop online',
                default => 'Online',
            },
        };

        return [
            'probed' => true,
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
            'domain' => self::LIVE_PRIMARY_HOST,
            'url' => 'https://' . self::LIVE_PRIMARY_HOST,
            'reachable' => $reachable,
            'http_status' => $httpStatus,
            'latency_ms' => $latencyMs,
            'ssl_days_remaining' => $sslDays,
            'ssl_valid_until' => $sslValidUntil,
            'mode' => $mode,
            'www_domain' => self::LIVE_WWW_HOST,
            'www_ingress_active' => $wwwIngressActive,
            'www_http_status' => $wwwHttpStatus,
            'www_status' => $wwwStatus,
            'checked_at' => $raw['checked_at'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Führt einen strikt lesenden TLS+HTTP/1.1 GET-Check mit engem Timeout aus (0 Schreibzugriffe).
     */
    protected function probeHttpsEndpointReadOnly(string $host, string $path = '/', float $timeoutSeconds = 2.8): array
    {
        $t0 = microtime(true);
        $cleanPath = '/' . ltrim($path, '/');

        try {
            $ctx = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'SNI_enabled' => true,
                    'peer_name' => $host,
                ],
            ]);

            $errno = 0;
            $errstr = '';
            $client = @stream_socket_client(
                "ssl://{$host}:443",
                $errno,
                $errstr,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                $ctx
            );

            if (!$client) {
                return [
                    'reachable' => false,
                    'http_status' => 0,
                    'latency_ms' => round((microtime(true) - $t0) * 1000, 2),
                    'ssl_days_remaining' => null,
                    'ssl_valid_until' => null,
                    'body_bytes' => 0,
                    'has_shop_markers' => false,
                    'location' => null,
                ];
            }

            $sec = max(1, (int) floor($timeoutSeconds));
            $usec = (int) round(($timeoutSeconds - floor($timeoutSeconds)) * 1_000_000);
            @stream_set_timeout($client, $sec, $usec);

            $sslDaysRemaining = null;
            $sslValidUntil = null;
            $params = @stream_context_get_params($client);
            $certResource = $params['options']['ssl']['peer_certificate'] ?? null;
            if ($certResource) {
                $parsed = @openssl_x509_parse($certResource);
                $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
                if ($validTo > 0) {
                    $sslDaysRemaining = (int) floor(($validTo - time()) / 86400);
                    $sslValidUntil = Carbon::createFromTimestamp($validTo)->toIso8601String();
                }
            }

            $req = "GET {$cleanPath} HTTP/1.1\r\n"
                . "Host: {$host}\r\n"
                . "User-Agent: Seelenfunke-Watchtower/1.0\r\n"
                . "Accept: text/html,application/xhtml+xml,*/*\r\n"
                . "Accept-Encoding: identity\r\n"
                . "Connection: close\r\n\r\n";

            @fwrite($client, $req);

            $rawResponse = '';
            $maxReadBytes = 28672;
            while (!feof($client) && strlen($rawResponse) < $maxReadBytes) {
                $chunk = @fread($client, 4096);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $rawResponse .= $chunk;
                $meta = @stream_get_meta_data($client);
                if (!empty($meta['timed_out'])) {
                    break;
                }
            }
            @fclose($client);

            $latencyMs = round((microtime(true) - $t0) * 1000, 2);
            if ($rawResponse === '') {
                return [
                    'reachable' => false,
                    'http_status' => 0,
                    'latency_ms' => $latencyMs,
                    'ssl_days_remaining' => $sslDaysRemaining,
                    'ssl_valid_until' => $sslValidUntil,
                    'body_bytes' => 0,
                    'has_shop_markers' => false,
                    'location' => null,
                ];
            }

            $parts = explode("\r\n\r\n", $rawResponse, 2);
            $headerBlock = $parts[0] ?? '';
            $bodySample = $parts[1] ?? '';

            $httpStatus = 0;
            if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', $headerBlock, $m)) {
                $httpStatus = (int) $m[1];
            }

            $contentLength = null;
            if (preg_match('/^Content-Length:\s*(\d+)/mi', $headerBlock, $clm)) {
                $contentLength = (int) $clm[1];
            }

            $location = null;
            if (preg_match('/^Location:\s*([^\r\n]+)/mi', $headerBlock, $locm)) {
                $location = trim($locm[1]);
            }

            $bodyBytes = $contentLength !== null ? max($contentLength, strlen($bodySample)) : strlen($bodySample);
            $lowerHeader = strtolower($headerBlock);
            $lowerBody = strtolower($bodySample);

            $hasShopMarkers = str_contains($lowerBody, 'livewire')
                || str_contains($lowerBody, 'csrf-token')
                || str_contains($lowerHeader, 'mein_seelenfunke_session')
                || str_contains($lowerHeader, 'seelenfunke_session');

            return [
                'reachable' => $httpStatus > 0,
                'http_status' => $httpStatus,
                'latency_ms' => $latencyMs,
                'ssl_days_remaining' => $sslDaysRemaining,
                'ssl_valid_until' => $sslValidUntil,
                'body_bytes' => $bodyBytes,
                'has_shop_markers' => $hasShopMarkers,
                'location' => $location,
            ];
        } catch (\Throwable $ignore) {
            return [
                'reachable' => false,
                'http_status' => 0,
                'latency_ms' => round((microtime(true) - $t0) * 1000, 2),
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
                'body_bytes' => 0,
                'has_shop_markers' => false,
                'location' => null,
            ];
        }
    }

    protected function evaluateShopPillar(bool $strict, ?int $overrideSslDays = null): array
    {
        $status = 'ok';
        $issues = [];
        $dbConnected = false;
        $dbLatencyMs = null;

        try {
            $t0 = microtime(true);
            DB::select('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $t0) * 1000, 2);
            $dbConnected = true;
            if ($dbLatencyMs > 800) {
                $status = $this->escalateStatus($status, 'warning');
                $issues[] = "Hohe DB-Latenz ({$dbLatencyMs} ms)";
            }
        } catch (\Throwable $e) {
            $status = 'critical';
            $issues[] = 'Datenbank nicht erreichbar';
        }

        $cacheConnected = false;
        try {
            $probeKey = 'health_pulse_probe_' . Str::random(6);
            Cache::put($probeKey, '1', 10);
            $cacheConnected = Cache::get($probeKey) === '1';
            Cache::forget($probeKey);
            if (!$cacheConnected) {
                $status = $this->escalateStatus($status, 'critical');
                $issues[] = 'Cache Lese-/Schreibtest fehlgeschlagen';
            }
        } catch (\Throwable $e) {
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = 'Cache-Dienst gestört';
        }

        $diskFreePercent = null;
        $diskFreeGb = null;
        try {
            $base = base_path();
            $total = @disk_total_space($base);
            $free = @disk_free_space($base);
            if ($total && $total > 0 && $free !== false) {
                $diskFreePercent = (int) round(($free / $total) * 100);
                $diskFreeGb = round($free / 1024 / 1024 / 1024, 1);
                if ($diskFreePercent < 8) {
                    $status = $this->escalateStatus($status, 'critical');
                    $issues[] = "Festplatte kritisch voll ({$diskFreePercent}% frei)";
                } elseif ($diskFreePercent < 15) {
                    $status = $this->escalateStatus($status, 'warning');
                    $issues[] = "Speicherplatz knapp ({$diskFreePercent}% frei)";
                }
            }
        } catch (\Throwable $ignore) {
        }

        $sslDays = $overrideSslDays ?? $this->resolveSslDaysRemaining($strict);
        if ($sslDays !== null) {
            if ($sslDays <= 7) {
                $status = $this->escalateStatus($status, 'critical');
                $issues[] = "SSL-Zertifikat läuft in {$sslDays} Tagen ab";
            } elseif ($sslDays <= 14) {
                $status = $this->escalateStatus($status, 'warning');
                $issues[] = "SSL-Zertifikat läuft in {$sslDays} Tagen ab";
            }
        }

        $label = match ($status) {
            'critical' => $issues[0] ?? 'Shop-Kern gestört',
            'warning' => $issues[0] ?? 'Shop-Hinweis',
            default => $dbLatencyMs !== null ? "Online ({$dbLatencyMs} ms)" : 'Online',
        };

        return [
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
            'db_connected' => $dbConnected,
            'db_latency_ms' => $dbLatencyMs,
            'cache_connected' => $cacheConnected,
            'disk_free_percent' => $diskFreePercent,
            'disk_free_gb' => $diskFreeGb,
            'ssl_days_remaining' => $sslDays,
        ];
    }

    protected function evaluateCronjobsPillar(bool $strict, Carbon $now): array
    {
        $status = 'ok';
        $issues = [];
        $lastRunRaw = Cache::get('scheduler_last_run');
        $lastRunAt = null;
        $secondsAgo = null;

        if ($lastRunRaw !== null && $lastRunRaw !== '') {
            try {
                $lastRun = is_numeric($lastRunRaw)
                    ? Carbon::createFromTimestamp((int) $lastRunRaw)
                    : Carbon::parse((string) $lastRunRaw);
                $lastRunAt = $lastRun->toIso8601String();
                $secondsAgo = max(0, (int) $lastRun->diffInSeconds($now, false));
            } catch (\Throwable $ignore) {
            }
        }

        if ($secondsAgo !== null) {
            if ($secondsAgo > 600) {
                $mins = (int) floor($secondsAgo / 60);
                $status = $this->escalateStatus($status, 'critical');
                $issues[] = "Scheduler seit {$mins} Min inaktiv";
            } elseif ($secondsAgo > 180) {
                $mins = (int) floor($secondsAgo / 60);
                $status = $this->escalateStatus($status, 'warning');
                $issues[] = "Scheduler verzögert ({$mins} Min)";
            }
        } elseif ($strict) {
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = 'Kein Scheduler-Herzschlag vorhanden';
        }

        $failedCronNames = [];
        if (Schema::hasTable('system_cronjobs')) {
            try {
                $query = DB::table('system_cronjobs')->where('is_active', true);
                if (Schema::hasColumn('system_cronjobs', 'last_status')) {
                    $failedRows = (clone $query)->where('last_status', 'error')->limit(10)->get();
                    foreach ($failedRows as $row) {
                        $failedCronNames[] = (string) ($row->name ?? $row->command ?? 'Cronjob');
                    }
                } elseif (Schema::hasColumn('system_cronjobs', 'status')) {
                    $failedRows = (clone $query)->where('status', 'error')->limit(10)->get();
                    foreach ($failedRows as $row) {
                        $failedCronNames[] = (string) ($row->name ?? $row->command ?? 'Cronjob');
                    }
                }
            } catch (\Throwable $ignore) {
            }
        }

        $failedCount = count($failedCronNames);
        if ($failedCount >= 3) {
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = "{$failedCount} aktive Cronjobs fehlerhaft";
        } elseif ($failedCount > 0) {
            $status = $this->escalateStatus($status, 'warning');
            $issues[] = "{$failedCount} Cronjob mit Fehlerstatus";
        }

        $lastException = Cache::get('scheduler_last_exception');
        if (is_array($lastException) && !empty($lastException['message'])) {
            $status = $this->escalateStatus($status, 'warning');
            $issues[] = 'Scheduler-Ausnahme protokolliert';
        }

        $label = match ($status) {
            'critical', 'warning' => $issues[0] ?? 'Cronjob-Prüfung erforderlich',
            default => $secondsAgo !== null
                ? 'Aktiv (vor ' . max(0, (int) floor($secondsAgo / 60)) . ' Min)'
                : 'Bereit (Lokal)',
        };

        return [
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
            'last_run_at' => $lastRunAt,
            'last_run_seconds_ago' => $secondsAgo,
            'failed_cronjobs_count' => $failedCount,
            'failed_cronjob_names' => $failedCronNames,
            'has_boot_exception' => is_array($lastException) && !empty($lastException['message']),
        ];
    }

    protected function evaluateQueuesPillar(bool $strict, Carbon $now): array
    {
        $status = 'ok';
        $issues = [];
        $driver = (string) config('queue.default', 'sync');

        $workerSecondsAgo = null;
        $workerAlive = ($driver === 'sync');

        if ($driver !== 'sync') {
            $hbRaw = Cache::get('ai-worker-heartbeat');
            if ($hbRaw !== null && is_numeric($hbRaw)) {
                $workerSecondsAgo = max(0, $now->timestamp - (int) $hbRaw);
            }

            try {
                if (Storage::disk('local')->exists('system/ai_worker_heartbeat.txt')) {
                    $fileTs = (int) trim((string) Storage::disk('local')->get('system/ai_worker_heartbeat.txt'));
                    if ($fileTs > 0) {
                        $fileAge = max(0, $now->timestamp - $fileTs);
                        if ($workerSecondsAgo === null || $fileAge < $workerSecondsAgo) {
                            $workerSecondsAgo = $fileAge;
                        }
                    }
                }
            } catch (\Throwable $ignore) {
            }

            if ($workerSecondsAgo !== null && $workerSecondsAgo <= 180) {
                $workerAlive = true;
            }
        }

        $pendingCount = 0;
        $oldestPendingSeconds = null;
        if (Schema::hasTable('jobs')) {
            try {
                $pendingCount = (int) DB::table('jobs')->count();
                if ($pendingCount > 0) {
                    $oldestCreated = DB::table('jobs')
                        ->whereNull('reserved_at')
                        ->min('created_at');
                    if (is_numeric($oldestCreated) && (int) $oldestCreated > 0) {
                        $oldestPendingSeconds = max(0, $now->timestamp - (int) $oldestCreated);
                    }
                }
            } catch (\Throwable $ignore) {
            }
        }

        $failedTotal = 0;
        $failed24h = 0;
        if (Schema::hasTable('failed_jobs')) {
            try {
                $failedTotal = (int) DB::table('failed_jobs')->count();
                if ($failedTotal > 0 && Schema::hasColumn('failed_jobs', 'failed_at')) {
                    $failed24h = (int) DB::table('failed_jobs')
                        ->where('failed_at', '>=', $now->copy()->subHours(24))
                        ->count();
                } else {
                    $failed24h = $failedTotal;
                }
            } catch (\Throwable $ignore) {
            }
        }

        if ($oldestPendingSeconds !== null && $oldestPendingSeconds > 600 && !$workerAlive) {
            $mins = (int) floor($oldestPendingSeconds / 60);
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = "Queue-Stau ({$pendingCount} Jobs seit {$mins} Min)";
        } elseif (!$workerAlive && $strict) {
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = 'Queue-Worker ohne Herzschlag';
        } elseif ($oldestPendingSeconds !== null && $oldestPendingSeconds > 900) {
            $mins = (int) floor($oldestPendingSeconds / 60);
            $status = $this->escalateStatus($status, 'warning');
            $issues[] = "Warteschlange verzögert ({$mins} Min)";
        }

        if ($failed24h >= 5) {
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = "{$failed24h} fehlgeschlagene Jobs (24h)";
        } elseif ($failedTotal > 0) {
            $status = $this->escalateStatus($status, 'warning');
            $issues[] = "{$failedTotal} fehlgeschlagene Jobs";
        }

        $label = match ($status) {
            'critical', 'warning' => $issues[0] ?? 'Queue-Prüfung erforderlich',
            default => $pendingCount > 0 ? "Aktiv ({$pendingCount} wartend)" : 'Aktiv (0 wartend)',
        };

        return [
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
            'driver' => $driver,
            'worker_alive' => $workerAlive,
            'worker_heartbeat_seconds_ago' => $workerSecondsAgo,
            'pending_jobs_count' => $pendingCount,
            'oldest_pending_seconds' => $oldestPendingSeconds,
            'failed_jobs_count' => $failedTotal,
            'failed_jobs_24h_count' => $failed24h,
        ];
    }

    protected function evaluateBackupsPillar(bool $strict, Carbon $now): array
    {
        $status = 'ok';
        $issues = [];

        $diskName = (string) config('backup.backup.destination.disks.0', 'local');
        $backupName = (string) config('backup.backup.name', config('app.name') . '-db-backup');

        $shopBackupAt = null;
        $shopHoursAgo = null;
        $shopSizeMb = null;

        try {
            $disk = Storage::disk($diskName);
            if ($disk->exists($backupName)) {
                $files = $disk->files($backupName);
                $latestTime = 0;
                $latestFile = null;
                foreach ($files as $file) {
                    if (str_ends_with(strtolower((string) $file), '.zip')) {
                        $mtime = (int) $disk->lastModified($file);
                        if ($mtime > $latestTime) {
                            $latestTime = $mtime;
                            $latestFile = $file;
                        }
                    }
                }

                if ($latestTime > 0 && $latestFile !== null) {
                    $lastRun = Carbon::createFromTimestamp($latestTime);
                    $shopBackupAt = $lastRun->toIso8601String();
                    $shopHoursAgo = max(0, (int) floor($lastRun->diffInMinutes($now, false) / 60));
                    try {
                        $shopSizeMb = round(((int) $disk->size($latestFile)) / 1024 / 1024, 2);
                    } catch (\Throwable $ignore) {
                    }
                }
            }
        } catch (\Throwable $e) {
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = 'Backup-Speicher nicht lesbar';
        }

        if ($shopHoursAgo !== null) {
            if ($shopHoursAgo > 48) {
                $status = $this->escalateStatus($status, 'critical');
                $issues[] = "Shop-Backup überfällig ({$shopHoursAgo} Std alt)";
            } elseif ($shopHoursAgo > 26) {
                $status = $this->escalateStatus($status, 'warning');
                $issues[] = "Shop-Backup älter als 26 Std ({$shopHoursAgo} Std)";
            }
        } elseif ($strict) {
            $status = $this->escalateStatus($status, 'critical');
            $issues[] = 'Kein Shop-Backup gefunden';
        } else {
            $status = $this->escalateStatus($status, 'warning');
            $issues[] = 'Lokal noch kein ZIP-Backup';
        }

        $nasState = $this->readJsonSafe($this->resolvePrivatePath(self::NAS_BACKUP_STATE_FILE));
        $nasPayload = [
            'status' => 'unknown',
            'reported_at' => null,
            'hours_ago' => null,
            'summary' => null,
            'copied_count' => 0,
            'deleted_count' => 0,
            'nas_host' => null,
        ];

        if (!empty($nasState['reported_ts'])) {
            $nasTs = (int) $nasState['reported_ts'];
            $nasHoursAgo = max(0, (int) floor(($now->timestamp - $nasTs) / 3600));
            $nasStatus = (string) ($nasState['status'] ?? 'ok');

            $nasPayload = [
                'status' => $nasStatus,
                'reported_at' => $nasState['reported_at'] ?? Carbon::createFromTimestamp($nasTs)->toIso8601String(),
                'hours_ago' => $nasHoursAgo,
                'summary' => $nasState['summary'] ?? null,
                'copied_count' => (int) ($nasState['copied_count'] ?? 0),
                'deleted_count' => (int) ($nasState['deleted_count'] ?? 0),
                'nas_host' => $nasState['nas_host'] ?? null,
            ];

            if ($nasStatus === 'error') {
                $status = $this->escalateStatus($status, $nasHoursAgo > 72 ? 'critical' : 'warning');
                $issues[] = 'NAS-Sicherung meldet Fehler';
            } elseif ($nasHoursAgo > 72) {
                $status = $this->escalateStatus($status, 'warning');
                $issues[] = "NAS-Sicherung seit {$nasHoursAgo} Std ohne Meldung";
            }
        }

        $label = match ($status) {
            'critical', 'warning' => $issues[0] ?? 'Backup-Hinweis',
            default => $shopHoursAgo !== null ? "Aktuell (vor {$shopHoursAgo} Std)" : 'Aktuell',
        };

        return [
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
            'shop_backup' => [
                'disk' => $diskName,
                'name' => $backupName,
                'last_backup_at' => $shopBackupAt,
                'hours_ago' => $shopHoursAgo,
                'size_mb' => $shopSizeMb,
            ],
            'nas_backup' => $nasPayload,
        ];
    }

    protected function resolveSslDaysRemaining(bool $strict): ?int
    {
        if (!$strict || app()->runningUnitTests()) {
            return null;
        }

        return Cache::remember(self::SSL_CACHE_KEY, 21600, function () {
            $appUrl = (string) config('app.url', '');
            $host = parse_url($appUrl, PHP_URL_HOST);
            $scheme = strtolower((string) parse_url($appUrl, PHP_URL_SCHEME));
            if (!$host || $scheme !== 'https' || in_array($host, ['localhost', '127.0.0.1'], true)) {
                return null;
            }

            try {
                $ctx = stream_context_create([
                    'ssl' => [
                        'capture_peer_cert' => true,
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'SNI_enabled' => true,
                        'peer_name' => $host,
                    ],
                ]);
                $client = @stream_socket_client(
                    "ssl://{$host}:443",
                    $errno,
                    $errstr,
                    3.0,
                    STREAM_CLIENT_CONNECT,
                    $ctx
                );
                if (!$client) {
                    return null;
                }
                $params = stream_context_get_params($client);
                @fclose($client);
                $certResource = $params['options']['ssl']['peer_certificate'] ?? null;
                if (!$certResource) {
                    return null;
                }
                $parsed = @openssl_x509_parse($certResource);
                $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
                if ($validTo <= 0) {
                    return null;
                }
                return (int) floor(($validTo - time()) / 86400);
            } catch (\Throwable $ignore) {
                return null;
            }
        });
    }

    protected function recordWatchtowerNodePing(string $rawSource, Carbon $now): void
    {
        $source = $this->normalizeSource($rawSource);
        $path = $this->resolvePrivatePath(self::WATCHTOWER_NODES_FILE);
        $nodes = $this->readJsonSafe($path);

        $prev = is_array($nodes[$source] ?? null) ? $nodes[$source] : [];
        $nodes[$source] = [
            'source' => $source,
            'label' => self::ALLOWED_SOURCES[$source] ?? 'Außen-Wächter',
            'last_seen_at' => $now->toIso8601String(),
            'last_seen_ts' => $now->timestamp,
            'pings_count' => ((int) ($prev['pings_count'] ?? 0)) + 1,
        ];

        $this->writeJsonAtomically($path, $nodes);
    }

    public function getWatchtowerNodesSummary(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $nodesRaw = $this->readJsonSafe($this->resolvePrivatePath(self::WATCHTOWER_NODES_FILE));
        $externalSources = ['synology_nas', 'android_watchtower', 'minipc_linux', 'windows_desktop'];

        $nodes = [];
        $activeExternalCount = 0;
        $lastExternalTs = 0;
        $lastExternalAt = null;

        foreach ($externalSources as $srcKey) {
            if (!isset($nodesRaw[$srcKey]) || !is_array($nodesRaw[$srcKey])) {
                continue;
            }
            $entry = $nodesRaw[$srcKey];
            $ts = (int) ($entry['last_seen_ts'] ?? 0);
            $ageSec = $ts > 0 ? max(0, $now->timestamp - $ts) : null;
            // Smartphone JobScheduler ticks every 15m (allow up to 45m), NAS/Mini-PC every 2-5m
            $freshThreshold = $srcKey === 'android_watchtower' ? 2700 : 900;
            $isActive = ($ageSec !== null && $ageSec <= $freshThreshold);

            if ($isActive) {
                $activeExternalCount++;
            }
            if ($ts > $lastExternalTs) {
                $lastExternalTs = $ts;
                $lastExternalAt = $entry['last_seen_at'] ?? Carbon::createFromTimestamp($ts)->toIso8601String();
            }

            $nodes[] = [
                'source' => $srcKey,
                'label' => self::ALLOWED_SOURCES[$srcKey] ?? $srcKey,
                'active' => $isActive,
                'last_seen_at' => $entry['last_seen_at'] ?? null,
                'seconds_ago' => $ageSec,
                'pings_count' => (int) ($entry['pings_count'] ?? 1),
            ];
        }

        return [
            'active_nodes_count' => $activeExternalCount,
            'last_external_ping_at' => $lastExternalAt,
            'nodes' => $nodes,
        ];
    }

    protected function recordAndSummarizeUptime(string $overallStatus, bool $shopCoreUp, Carbon $now): array
    {
        $path = $this->resolvePrivatePath(self::UPTIME_LEDGER_FILE);
        $ledger = $this->readJsonSafe($path);
        $days = is_array($ledger['days'] ?? null) ? $ledger['days'] : [];

        $todayKey = $now->format('Y-m-d');
        $bucket = is_array($days[$todayKey] ?? null) ? $days[$todayKey] : [
            'checks_total' => 0,
            'checks_up' => 0,
            'checks_ok' => 0,
            'checks_warning' => 0,
            'checks_critical' => 0,
            'last_status' => 'ok',
            'updated_at' => $now->toIso8601String(),
        ];

        $bucket['checks_total'] = ((int) ($bucket['checks_total'] ?? 0)) + 1;
        if ($shopCoreUp) {
            $bucket['checks_up'] = ((int) ($bucket['checks_up'] ?? 0)) + 1;
        }
        if ($overallStatus === 'critical') {
            $bucket['checks_critical'] = ((int) ($bucket['checks_critical'] ?? 0)) + 1;
        } elseif ($overallStatus === 'warning') {
            $bucket['checks_warning'] = ((int) ($bucket['checks_warning'] ?? 0)) + 1;
        } else {
            $bucket['checks_ok'] = ((int) ($bucket['checks_ok'] ?? 0)) + 1;
        }
        $bucket['last_status'] = $overallStatus;
        $bucket['updated_at'] = $now->toIso8601String();

        $days[$todayKey] = $bucket;

        // Prune buckets older than 90 days
        $cutoffKey = $now->copy()->subDays(self::MAX_LEDGER_DAYS - 1)->format('Y-m-d');
        foreach (array_keys($days) as $dateKey) {
            if ((string) $dateKey < $cutoffKey) {
                unset($days[$dateKey]);
            }
        }
        ksort($days);

        $this->writeJsonAtomically($path, [
            'updated_at' => $now->toIso8601String(),
            'days' => $days,
        ]);

        return $this->computeUptimeMetricsFromDays($days, $now);
    }

    public function summarizeUptime(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $ledger = $this->readJsonSafe($this->resolvePrivatePath(self::UPTIME_LEDGER_FILE));
        $days = is_array($ledger['days'] ?? null) ? $ledger['days'] : [];

        return $this->computeUptimeMetricsFromDays($days, $now);
    }

    protected function computeUptimeMetricsFromDays(array $days, Carbon $now): array
    {
        $totalChecks = 0;
        $totalUp = 0;
        $totalHealthy = 0;

        $cutoffKey = $now->copy()->subDays(self::MAX_LEDGER_DAYS - 1)->format('Y-m-d');
        foreach ($days as $dateKey => $b) {
            if ((string) $dateKey < $cutoffKey || !is_array($b)) {
                continue;
            }
            $cTot = max(0, (int) ($b['checks_total'] ?? 0));
            $cUp = max(0, (int) ($b['checks_up'] ?? 0));
            $cOk = max(0, (int) ($b['checks_ok'] ?? 0));
            $cWarn = max(0, (int) ($b['checks_warning'] ?? 0));

            $totalChecks += $cTot;
            $totalUp += min($cTot, $cUp);
            $totalHealthy += min($cTot, $cOk + $cWarn);
        }

        $uptimePct = $totalChecks > 0 ? round(($totalUp / $totalChecks) * 100, 2) : 100.0;
        $strictHealthyPct = $totalChecks > 0 ? round(($totalHealthy / $totalChecks) * 100, 2) : 100.0;

        $bars = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i);
            $k = $d->format('Y-m-d');
            if (isset($days[$k]) && is_array($days[$k]) && ((int) ($days[$k]['checks_total'] ?? 0)) > 0) {
                $b = $days[$k];
                $tot = (int) $b['checks_total'];
                $crit = (int) ($b['checks_critical'] ?? 0);
                $warn = (int) ($b['checks_warning'] ?? 0);
                $dayStatus = $crit > 0 ? 'critical' : ($warn > 0 ? 'warning' : 'ok');
                $dayPct = round((max(0, $tot - $crit) / $tot) * 100, 1);
                $bars[] = [
                    'date' => $d->format('d.m.'),
                    'iso_date' => $k,
                    'status' => $dayStatus,
                    'percent' => $dayPct,
                    'checks' => $tot,
                ];
            } else {
                $bars[] = [
                    'date' => $d->format('d.m.'),
                    'iso_date' => $k,
                    'status' => 'nodata',
                    'percent' => null,
                    'checks' => 0,
                ];
            }
        }

        return [
            'uptime_90d_percent' => $uptimePct,
            'strict_healthy_90d_percent' => $strictHealthyPct,
            'tracked_days_count' => count($days),
            'daily_bars' => $bars,
        ];
    }

    /**
     * Synchronisiert kritische Säulen-Ausfälle dedupliziert in SystemLog (für Funkiras 3D-Kuppelhalle)
     * und markiert sie automatisch als [AUTO-GEHEILT], sobald die Säule wieder grün meldet.
     */
    public function syncWithSystemLogs(array $pillars, ?Carbon $now = null): array
    {
        if (!class_exists(SystemLog::class) || !Schema::hasTable('system_logs')) {
            return ['logged' => 0, 'healed' => 0];
        }

        $now = $now ?? now();
        $logged = 0;
        $healed = 0;

        foreach ($pillars as $pillarKey => $pillar) {
            $actionId = 'watchtower:pillar:' . $pillarKey;
            $status = (string) ($pillar['status'] ?? 'ok');
            $titleBase = 'Watchtower: ' . $this->pillarLabel((string) $pillarKey) . ' kritisch';

            if ($status === 'critical') {
                $issueText = implode(' | ', $pillar['issues'] ?? []) ?: (string) ($pillar['label'] ?? 'Kritische Abweichung');
                $fingerprint = hash('sha256', 'watchtower|pillar|' . $pillarKey);

                $existing = SystemLog::openIssues()
                    ->where('action_id', $actionId)
                    ->latest('id')
                    ->first();

                if ($existing) {
                    $p = is_array($existing->payload) ? $existing->payload : [];
                    $p['occurrences_count'] = ((int) ($p['occurrences_count'] ?? 1)) + 1;
                    $p['last_seen_at'] = $now->toIso8601String();
                    $p['pillar_snapshot'] = $pillar;
                    $existing->message = Str::limit($issueText, 2000);
                    $existing->payload = $p;
                    $existing->finished_at = $now;
                    $existing->save();
                } else {
                    SystemLog::create([
                        'type' => 'system',
                        'action_id' => $actionId,
                        'title' => Str::limit($titleBase, 190),
                        'message' => Str::limit($issueText, 2000),
                        'status' => 'error',
                        'payload' => [
                            'platform' => 'portal',
                            'platform_label' => 'Watchtower',
                            'component' => 'watchtower_' . $pillarKey,
                            'pillar' => $pillarKey,
                            'pillar_snapshot' => $pillar,
                            'fingerprint' => $fingerprint,
                            'occurrences_count' => 1,
                            'first_seen_at' => $now->toIso8601String(),
                            'last_seen_at' => $now->toIso8601String(),
                            'diagnosis' => SystemErrorLogger::diagnose($issueText, 'SystemHealthPulseService'),
                        ],
                        'started_at' => $now,
                        'finished_at' => $now,
                    ]);
                    $logged++;
                }
            } elseif ($status === 'ok') {
                $openLogs = SystemLog::openIssues()
                    ->where('action_id', $actionId)
                    ->get();

                foreach ($openLogs as $openLog) {
                    $p = is_array($openLog->payload) ? $openLog->payload : [];
                    $p['auto_healed'] = true;
                    $p['auto_healed_at'] = $now->toIso8601String();
                    $p['auto_heal_playbook'] = 'watchtower_pillar_recovered';
                    $p['auto_heal_summary'] = $this->pillarLabel((string) $pillarKey) . ' meldet wieder regulären Grün-Status (' . ($pillar['label'] ?? 'OK') . ').';

                    $cleanTitle = preg_replace('/^\[AUTO-GEHEILT\]\s*/i', '', (string) $openLog->title);
                    $openLog->title = Str::limit('[AUTO-GEHEILT] ' . $cleanTitle, 190);
                    $openLog->status = 'success';
                    $openLog->payload = $p;
                    $openLog->finished_at = $now;
                    $openLog->save();
                    $healed++;
                }
            }
        }

        return ['logged' => $logged, 'healed' => $healed];
    }

    protected function pillarLabel(string $key): string
    {
        return match ($key) {
            'shop' => 'Shop & SSL',
            'cronjobs' => 'Cronjobs',
            'queues' => 'Queue-Worker',
            'backups' => 'Backups',
            'live_domain' => 'Live-Domain',
            default => ucfirst($key),
        };
    }

    protected function normalizeSource(string $raw): string
    {
        $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $raw)));
        return array_key_exists($clean, self::ALLOWED_SOURCES) ? $clean : 'synology_nas';
    }

    protected function escalateStatus(string $current, string $candidate): string
    {
        $rank = ['ok' => 0, 'warning' => 1, 'critical' => 2];
        return ($rank[$candidate] ?? 0) > ($rank[$current] ?? 0) ? $candidate : $current;
    }

    protected function resolvePrivatePath(string $storageRelative): string
    {
        if (app()->runningUnitTests()) {
            return storage_path($storageRelative . '.testing');
        }
        return storage_path($storageRelative);
    }

    protected function readJsonSafe(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        try {
            $raw = @file_get_contents($path);
            if (!is_string($raw) || $raw === '') {
                return [];
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $ignore) {
            return [];
        }
    }

    protected function writeJsonAtomically(string $path, array $data): void
    {
        try {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            $tmp = $path . '.tmp.' . getmypid() . '.' . Str::random(6);
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                return;
            }
            if (@file_put_contents($tmp, $encoded, LOCK_EX) !== false) {
                @chmod($tmp, 0600);
                @rename($tmp, $path);
                @chmod($path, 0600);
            } else {
                @unlink($tmp);
            }
        } catch (\Throwable $ignore) {
        }
    }
}
