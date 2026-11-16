<?php

namespace App\Services\System;

use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\System\SystemLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemHealthPulseService
{
    public const UPTIME_LEDGER_FILE = 'app/private/system_uptime_90d.json';
    public const WATCHTOWER_NODES_FILE = 'app/private/watchtower_nodes.json';
    public const NAS_BACKUP_STATE_FILE = 'app/private/watchtower_nas_backup.json';

    public const SSL_CACHE_KEY = 'system_health_pulse_ssl_days_v1';
    public const SSL_HOST_CACHE_PREFIX = 'system_health_ssl_host_v1_';
    public const LIVE_DOMAIN_CACHE_KEY = 'system_health_pulse_live_domain_v1';
    public const LIVE_DOMAIN_CACHE_TTL = 45;
    public const LIVE_PRIMARY_HOST = 'mein-seelenfunke.de';
    public const LIVE_WWW_HOST = 'www.mein-seelenfunke.de';
    public const DIGITAL_SUBDOMAIN_HOST = 'digital.mein-seelenfunke.de';
    public const STAGE_SUBDOMAIN_HOST = 'stage.mein-seelenfunke.de';
    public const WS_SUBDOMAIN_HOST = 'ws.mein-seelenfunke.de';
    public const LIVE_BRIDGE_SUBDOMAIN_HOST = 'api-live-bridge.mein-seelenfunke.de';

    public const GEMINI_BRIDGE_CACHE_KEY = 'system_health_pulse_gemini_bridge_v1';
    public const GEMINI_BRIDGE_CACHE_TTL = 60;
    public const GEMINI_BRIDGE_PUSH_LOCK_KEY = 'gemini_bridge_outage_push_lock';
    public const GEMINI_BRIDGE_PUSH_COOLDOWN_SECONDS = 900;
    public const GEMINI_BRIDGE_DEFAULT_HEALTH_URL = 'https://api-live-bridge.mein-seelenfunke.de/health';
    public const TWILIO_BRIDGE_DEFAULT_HEALTH_URL = 'https://ws.mein-seelenfunke.de/twilio-health';
    public const GEMINI_BRIDGE_LOG_ACTION_ID = 'watchtower:health_fail:gemini_bridge';
    public const GEMINI_BRIDGE_TASK_TITLE = '⚠️ Gemini Audio Bridge auf Stage ausgefallen';
    public const GEMINI_BRIDGE_RESTART_CMD = 'ssh kontakt@mein-seelenfunke.de@a-yeq7el@ssh.altgemeinde.project.host "mittnitectl job restart"';

    public const MAX_LEDGER_DAYS = 90;

    protected static ?\Closure $liveDomainProbeResolver = null;
    protected static ?\Closure $geminiBridgeProbeResolver = null;
    protected static ?\Closure $dnsResolver = null;

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

    public static function setDnsResolver(?\Closure $resolver): void
    {
        self::$dnsResolver = $resolver;
    }

    public function hasCustomDnsResolver(): bool
    {
        return self::$dnsResolver !== null;
    }

    public function hasActiveTestDomainMocks(): bool
    {
        return self::$liveDomainProbeResolver !== null
            || self::$dnsResolver !== null
            || (app()->runningUnitTests() && $this->isHttpClientFaked());
    }

    public static function setGeminiBridgeProbeResolver(?\Closure $resolver): void
    {
        self::$geminiBridgeProbeResolver = $resolver;
    }

    public function hasCustomGeminiBridgeProbeResolver(): bool
    {
        return self::$geminiBridgeProbeResolver !== null;
    }

    /**
     * Zentraler 4-Säulen Health-Puls (Shop & SSL, Cronjobs, Queue-Worker, Backups)
     * inklusive Mittwald Live-Domain-Puls, Gemini Audio-Bridge-Prüfung, 90-Tage-Uptime-Historie und Außen-Wächter-Registrierung.
     */
    public function evaluate(
        ?string $source = null,
        bool $strict = false,
        bool $recordSample = true,
        bool $syncLogs = false,
        ?int $overrideSslDays = null,
        ?array $overrideLiveDomain = null,
        ?array $overrideGeminiBridge = null
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
        $geminiBridge = $this->evaluateGeminiBridge($effectiveStrict, $overrideGeminiBridge);

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

        $bridgeSt = (string) ($geminiBridge['status'] ?? 'ok');
        if ($bridgeSt === 'critical') {
            $overallStatus = 'critical';
            $criticalPillars[] = 'gemini_bridge';
        } elseif ($bridgeSt === 'warning') {
            if ($overallStatus === 'ok') {
                $overallStatus = 'warning';
            }
            $warningPillars[] = 'gemini_bridge';
        }

        $uptimeSummary = $recordSample
            ? $this->recordAndSummarizeUptime($overallStatus, $shopPillar['status'] === 'ok', $now)
            : $this->summarizeUptime($now);

        $logSync = ['logged' => 0, 'healed' => 0];
        if ($syncLogs) {
            $syncTargets = $pillars;
            if (!empty($liveDomain['probed'])) {
                $syncTargets['live_domain'] = $liveDomain;
            }
            if (!empty($geminiBridge['probed'])) {
                $syncTargets['gemini_bridge'] = $geminiBridge;
            }
            $logSync = $this->syncWithSystemLogs($syncTargets, $now);
        }

        $liveDomain = $this->enrichLiveDomainWithSubdomains($liveDomain, $geminiBridge);

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
            'subdomains' => $liveDomain['subdomains'] ?? [],
            'digital_subdomain' => $liveDomain['digital_subdomain'] ?? [],
            'gemini_bridge' => $geminiBridge,
            'watchtower' => $watchtowerSummary,
            'log_sync' => $logSync,
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
        foreach ([
            self::LIVE_PRIMARY_HOST,
            self::LIVE_WWW_HOST,
            self::DIGITAL_SUBDOMAIN_HOST,
            self::STAGE_SUBDOMAIN_HOST,
            self::WS_SUBDOMAIN_HOST,
            self::LIVE_BRIDGE_SUBDOMAIN_HOST,
        ] as $monitoredHost) {
            Cache::forget(self::SSL_HOST_CACHE_PREFIX . md5($monitoredHost));
        }
        Cache::forget(self::LIVE_DOMAIN_CACHE_KEY);
        Cache::forget(self::GEMINI_BRIDGE_CACHE_KEY);
        Cache::forget(self::GEMINI_BRIDGE_PUSH_LOCK_KEY);
        self::$liveDomainProbeResolver = null;
        self::$geminiBridgeProbeResolver = null;
        self::$dnsResolver = null;
    }

    /**
     * Liefert gecachte oder frisch gelesene Live-Domain- und Subdomain-Messdaten (45s TTL via Cache::remember).
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
     * Paralleler Read-Only DNS+HTTPS-Puls (< 400ms Gesamtlatenz, 0 Schreibzugriffe) für:
     * - digital.mein-seelenfunke.de (inkl. sofortigem DNS-Vorabcheck ohne Timeout bei fehlendem DNS)
     * - mein-seelenfunke.de & www.mein-seelenfunke.de
     * - stage.mein-seelenfunke.de
     * - ws.mein-seelenfunke.de (/twilio-health)
     * - api-live-bridge.mein-seelenfunke.de (/health)
     */
    public function probeLiveDomains(): array
    {
        $checkedAt = now()->toIso8601String();
        $targets = $this->monitoredSubdomainDefinitions();

        $dnsMap = [];
        foreach ($targets as $key => $cfg) {
            $ips = $this->resolveHostDnsIps($cfg['host']);
            $dnsMap[$key] = [
                'configured' => count($ips) > 0,
                'ips' => $ips,
            ];
        }

        $poolTimings = [];
        $responses = [];
        $hasAnyResolvedHost = collect($dnsMap)->contains(fn (array $d): bool => $d['configured'] === true);

        if ($hasAnyResolvedHost) {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($targets, $dnsMap, &$poolTimings) {
                $reqs = [];
                foreach ($targets as $key => $cfg) {
                    if (empty($dnsMap[$key]['configured'])) {
                        continue;
                    }
                    $poolTimings[$key] = microtime(true);
                    $pending = $pool->as($key)
                        ->connectTimeout(1.8)
                        ->timeout(2.5)
                        ->withOptions([
                            'verify' => true,
                            'http_errors' => false,
                        ])
                        ->withUserAgent('Seelenfunke-Watchtower/1.1')
                        ->withHeaders([
                            'Accept' => 'text/html,application/json,application/xhtml+xml,*/*',
                        ]);

                    if (empty($cfg['follow_redirects'])) {
                        $pending = $pending->withoutRedirecting();
                    } else {
                        $pending = $pending->maxRedirects(2);
                    }

                    $reqs[$key] = $pending->get($cfg['probe_url']);
                }

                return $reqs;
            });
        }

        $probedEntries = [];
        foreach ($targets as $key => $cfg) {
            $dnsConfigured = (bool) ($dnsMap[$key]['configured'] ?? false);
            $dnsIps = $dnsMap[$key]['ips'] ?? [];
            $reachable = false;
            $httpStatus = 0;
            $latencyMs = 0.0;
            $bodyBytes = 0;
            $hasShopMarkers = false;

            if ($dnsConfigured && array_key_exists($key, $responses)) {
                $resp = $responses[$key];
                $fallbackLatency = isset($poolTimings[$key])
                    ? round((microtime(true) - $poolTimings[$key]) * 1000, 2)
                    : 0.0;

                if ($resp instanceof \Illuminate\Http\Client\Response) {
                    $httpStatus = (int) $resp->status();
                    $reachable = $httpStatus > 0;
                    $statsTime = $resp->transferStats?->getTransferTime();
                    $latencyMs = $statsTime !== null && $statsTime > 0
                        ? round(((float) $statsTime) * 1000, 2)
                        : $fallbackLatency;

                    $rawBody = (string) $resp->body();
                    $bodyBytes = strlen($rawBody);
                    $sampleLower = strtolower(substr($rawBody, 0, 32768));
                    $cookieHeader = strtolower(implode(' ', $resp->header('Set-Cookie') ? (array) $resp->headers()['Set-Cookie'] ?? [$resp->header('Set-Cookie')] : []));
                    $hasShopMarkers = str_contains($sampleLower, 'livewire')
                        || str_contains($sampleLower, 'csrf-token')
                        || str_contains($cookieHeader, 'mein_seelenfunke_session')
                        || str_contains($cookieHeader, 'seelenfunke_session');
                } else {
                    $latencyMs = $fallbackLatency;
                }
            }

            $sslInfo = ['ssl_days_remaining' => null, 'ssl_valid_until' => null];
            if ($dnsConfigured && $reachable && !empty($cfg['check_ssl'])) {
                $sslInfo = $this->resolveHostSslCertificateInfo($cfg['host'], true);
            }

            $probedEntries[$key] = [
                'dns_configured' => $dnsConfigured,
                'dns_ips' => $dnsIps,
                'reachable' => $reachable,
                'http_status' => $httpStatus,
                'latency_ms' => $latencyMs,
                'body_bytes' => $bodyBytes,
                'has_shop_markers' => $hasShopMarkers,
                'ssl_days_remaining' => $sslInfo['ssl_days_remaining'],
                'ssl_valid_until' => $sslInfo['ssl_valid_until'],
            ];
        }

        $primary = $probedEntries['main'];
        $www = $probedEntries['www'];

        $mode = $this->classifyLiveDomainMode(
            (bool) $primary['reachable'],
            (int) $primary['http_status'],
            (int) $primary['body_bytes'],
            (bool) $primary['has_shop_markers']
        );

        $wwwIngressActive = $www['reachable']
            && $www['http_status'] > 0
            && ($www['http_status'] < 500 || $www['http_status'] === 503);

        $digitalReadiness = $this->inspectDigitalShopReadiness();
        $subdomains = $this->buildSubdomainsMatrix($probedEntries, $digitalReadiness, $checkedAt);

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
            'subdomains' => $subdomains,
            'digital_subdomain' => $subdomains['digital'],
            'checked_at' => $checkedAt,
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
        } elseif ($this->hasActiveTestDomainMocks()) {
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
            'probed' => (bool) ($raw['probed'] ?? true),
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
            'subdomains' => is_array($raw['subdomains'] ?? null) ? $raw['subdomains'] : null,
            'digital_subdomain' => is_array($raw['digital_subdomain'] ?? null) ? $raw['digital_subdomain'] : null,
            'checked_at' => $raw['checked_at'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Liefert die überwachten Domains & Subdomains in priorisierter Reihenfolge.
     */
    public function monitoredSubdomainDefinitions(): array
    {
        return [
            'digital' => [
                'key' => 'digital',
                'host' => self::DIGITAL_SUBDOMAIN_HOST,
                'public_url' => 'https://' . self::DIGITAL_SUBDOMAIN_HOST,
                'probe_url' => 'https://' . self::DIGITAL_SUBDOMAIN_HOST . '/',
                'role_label' => 'Digital-Shop',
                'follow_redirects' => true,
                'check_ssl' => true,
            ],
            'main' => [
                'key' => 'main',
                'host' => self::LIVE_PRIMARY_HOST,
                'public_url' => 'https://' . self::LIVE_PRIMARY_HOST,
                'probe_url' => 'https://' . self::LIVE_PRIMARY_HOST . '/',
                'role_label' => 'Haupt-Shop',
                'follow_redirects' => true,
                'check_ssl' => true,
            ],
            'stage' => [
                'key' => 'stage',
                'host' => self::STAGE_SUBDOMAIN_HOST,
                'public_url' => 'https://' . self::STAGE_SUBDOMAIN_HOST,
                'probe_url' => 'https://' . self::STAGE_SUBDOMAIN_HOST . '/',
                'role_label' => 'Stage-Portal',
                'follow_redirects' => true,
                'check_ssl' => true,
            ],
            'ws' => [
                'key' => 'ws',
                'host' => self::WS_SUBDOMAIN_HOST,
                'public_url' => 'https://' . self::WS_SUBDOMAIN_HOST,
                'probe_url' => self::TWILIO_BRIDGE_DEFAULT_HEALTH_URL,
                'role_label' => 'WebSocket & Telefonie',
                'follow_redirects' => true,
                'check_ssl' => false,
            ],
            'api_live_bridge' => [
                'key' => 'api_live_bridge',
                'host' => self::LIVE_BRIDGE_SUBDOMAIN_HOST,
                'public_url' => 'https://' . self::LIVE_BRIDGE_SUBDOMAIN_HOST,
                'probe_url' => self::GEMINI_BRIDGE_DEFAULT_HEALTH_URL,
                'role_label' => 'Gemini Audio-Bridge',
                'follow_redirects' => true,
                'check_ssl' => false,
            ],
            'www' => [
                'key' => 'www',
                'host' => self::LIVE_WWW_HOST,
                'public_url' => 'https://' . self::LIVE_WWW_HOST,
                'probe_url' => 'https://' . self::LIVE_WWW_HOST . '/',
                'role_label' => 'WWW-Ingress',
                'follow_redirects' => false,
                'check_ssl' => false,
            ],
        ];
    }

    /**
     * Schneller DNS-Vorabcheck (< 5ms bei NXDOMAIN), damit vorbereitete Subdomains ohne DNS-Eintrag
     * niemals in einen 3-Sekunden-HTTP-Timeout laufen.
     */
    public function resolveHostDnsIps(string $host): array
    {
        $cleanHost = strtolower(trim($host));
        if ($cleanHost === '') {
            return [];
        }

        if (self::$dnsResolver !== null) {
            $custom = (self::$dnsResolver)($cleanHost);
            if (!is_array($custom)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(
                static fn ($ip): string => trim((string) $ip),
                $custom
            ), static fn (string $ip): bool => $ip !== '')));
        }

        if (app()->runningUnitTests()) {
            return $this->isHttpClientFaked() ? ['198.51.100.10'] : [];
        }

        $ips = [];
        try {
            $v4List = @gethostbynamel($cleanHost);
            if (is_array($v4List)) {
                foreach ($v4List as $candidate) {
                    $ip = trim((string) $candidate);
                    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = $ip;
                    }
                }
            }

            if (empty($ips) && function_exists('dns_get_record')) {
                $records = @dns_get_record($cleanHost, DNS_A + DNS_AAAA);
                if (is_array($records)) {
                    foreach ($records as $rec) {
                        $ip = trim((string) ($rec['ip'] ?? $rec['ipv6'] ?? ''));
                        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                            $ips[] = $ip;
                        }
                    }
                }
            }
        } catch (\Throwable $ignore) {
        }

        return array_values(array_unique($ips));
    }

    /**
     * Liest in Echtzeit den DB-Schalter digital_domain_is_live sowie die Anzahl aktiver
     * und vorbereiteter (draft) Digital-Produkte für digital.mein-seelenfunke.de aus.
     */
    public function inspectDigitalShopReadiness(): array
    {
        $isLive = false;
        try {
            $rawSetting = null;
            if (Schema::hasTable('shop_settings')) {
                $rawSetting = DB::table('shop_settings')
                    ->where('key', 'digital_domain_is_live')
                    ->value('value');
            } elseif (Schema::hasTable('system_settings')) {
                $rawSetting = DB::table('system_settings')
                    ->where('key', 'digital_domain_is_live')
                    ->value('value');
            }
            if ($rawSetting === null && function_exists('shop_setting')) {
                $rawSetting = shop_setting('digital_domain_is_live', false);
            }
            if (is_bool($rawSetting)) {
                $isLive = $rawSetting;
            } elseif ($rawSetting !== null) {
                $isLive = in_array(strtolower(trim((string) $rawSetting)), ['1', 'true', 'yes', 'on'], true);
            }
        } catch (\Throwable $ignore) {
        }

        $activeDigital = 0;
        $draftDigital = 0;

        try {
            if (Schema::hasTable('products')) {
                $hasCollectionLine = Schema::hasColumn('products', 'collection_line');
                $hasDeletedAt = Schema::hasColumn('products', 'deleted_at');

                $baseQuery = DB::table('products')->where(function ($q) use ($hasCollectionLine) {
                    if ($hasCollectionLine) {
                        $q->where('collection_line', 'digital_blueprint')
                            ->orWhere(function ($q2) {
                                $q2->where('type', 'digital')->where('slug', '!=', 'geschenkgutschein');
                            });
                    } else {
                        $q->where('type', 'digital')->where('slug', '!=', 'geschenkgutschein');
                    }
                });

                if ($hasDeletedAt) {
                    $baseQuery->whereNull('deleted_at');
                }

                $activeDigital = (int) (clone $baseQuery)->where('status', 'active')->count();
                $draftDigital = (int) (clone $baseQuery)->where('status', 'draft')->count();
            }
        } catch (\Throwable $ignore) {
        }

        return [
            'digital_domain_is_live' => $isLive,
            'active_digital_products' => $activeDigital,
            'prepared_draft_products' => $draftDigital,
            'total_digital_products' => $activeDigital + $draftDigital,
        ];
    }

    /**
     * Baut die vollständige Subdomain-Statusmatrix für Dashboard, Falcone und Funkira.
     */
    protected function buildSubdomainsMatrix(array $probedEntries, array $digitalReadiness, string $checkedAt): array
    {
        $defs = $this->monitoredSubdomainDefinitions();
        $matrix = [];

        foreach ($defs as $key => $cfg) {
            $entry = $probedEntries[$key] ?? [
                'dns_configured' => false,
                'dns_ips' => [],
                'reachable' => false,
                'http_status' => 0,
                'latency_ms' => 0.0,
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ];

            $dnsConfigured = (bool) ($entry['dns_configured'] ?? false);
            $reachable = (bool) ($entry['reachable'] ?? false);
            $httpStatus = (int) ($entry['http_status'] ?? 0);
            $latencyMs = round((float) ($entry['latency_ms'] ?? 0.0), 2);
            $sslDays = array_key_exists('ssl_days_remaining', $entry) && $entry['ssl_days_remaining'] !== null
                ? (int) $entry['ssl_days_remaining']
                : null;
            $inMaintenance = $reachable && $httpStatus === 503;

            if (!$dnsConfigured) {
                $status = 'prepared';
                $badgeLabel = 'Vorbereitet · DNS offen';
                $stateLabel = 'Vorbereitet (DNS/Ingress noch nicht aufgeschaltet)';
            } elseif ($inMaintenance) {
                $status = 'maintenance';
                $badgeLabel = 'Wartung 503';
                $stateLabel = 'Wartungsmodus aktiv (HTTP 503 · geschützt)';
            } elseif ($reachable && $httpStatus >= 200 && $httpStatus < 400) {
                $status = 'ok';
                $badgeLabel = ($key === 'www' && in_array($httpStatus, [301, 302, 307, 308], true))
                    ? 'Weiterleitung ' . $httpStatus
                    : 'Live';
                $stateLabel = ($key === 'www' && in_array($httpStatus, [301, 302, 307, 308], true))
                    ? "Weiterleitung aktiv (HTTP {$httpStatus})"
                    : ($latencyMs > 0 ? "Online (HTTP {$httpStatus} · {$latencyMs} ms)" : "Online (HTTP {$httpStatus})");
            } else {
                $status = 'error';
                $badgeLabel = 'Störung';
                $stateLabel = $httpStatus > 0 ? "Störung (HTTP {$httpStatus})" : 'Nicht erreichbar (Timeout/TLS)';
            }

            $item = [
                'key' => $key,
                'host' => $cfg['host'],
                'url' => $cfg['public_url'],
                'probe_url' => $cfg['probe_url'],
                'role_label' => $cfg['role_label'],
                'dns_configured' => $dnsConfigured,
                'dns_ips' => array_values($entry['dns_ips'] ?? []),
                'reachable' => $reachable,
                'http_status' => $httpStatus,
                'in_maintenance' => $inMaintenance,
                'latency_ms' => $latencyMs,
                'ssl_days_remaining' => $sslDays,
                'status' => $status,
                'badge_label' => $badgeLabel,
                'state_label' => $stateLabel,
                'checked_at' => $checkedAt,
            ];

            if ($key === 'digital') {
                $item['digital_domain_is_live'] = (bool) $digitalReadiness['digital_domain_is_live'];
                $item['active_digital_products'] = (int) $digitalReadiness['active_digital_products'];
                $item['prepared_draft_products'] = (int) $digitalReadiness['prepared_draft_products'];
                $item['total_digital_products'] = (int) $digitalReadiness['total_digital_products'];
                if ($status === 'ok' && !$item['digital_domain_is_live']) {
                    $item['state_label'] = "Erreichbar (HTTP {$httpStatus} · Schalter AUS)";
                }
                $item['spoken_summary'] = $this->buildDigitalSpokenSummary($item);
            }

            $matrix[$key] = $item;
        }

        return $matrix;
    }

    /**
     * Erzeugt einen sofort sprechfertigen Klartext-Status zu digital.mein-seelenfunke.de ohne Rückfragen.
     */
    public function buildDigitalSpokenSummary(array $digitalSubdomain): string
    {
        $dnsConfigured = (bool) ($digitalSubdomain['dns_configured'] ?? false);
        $reachable = (bool) ($digitalSubdomain['reachable'] ?? false);
        $httpStatus = (int) ($digitalSubdomain['http_status'] ?? 0);
        $inMaintenance = (bool) ($digitalSubdomain['in_maintenance'] ?? false);
        $switchOn = (bool) ($digitalSubdomain['digital_domain_is_live'] ?? false);
        $activeCount = (int) ($digitalSubdomain['active_digital_products'] ?? 0);
        $draftCount = (int) ($digitalSubdomain['prepared_draft_products'] ?? 0);
        $sslDays = $digitalSubdomain['ssl_days_remaining'] ?? null;

        $switchText = $switchOn ? 'AN' : 'AUS';
        $activeLabel = $activeCount === 1 ? '1 aktives digitales Produkt' : "{$activeCount} aktive digitale Produkte";
        $draftLabel = $draftCount === 1 ? '1 vorbereiteter Entwurf' : "{$draftCount} vorbereitete Entwürfe";

        if (!$dnsConfigured) {
            return "Die Subdomain digital.mein-seelenfunke.de ist technisch vorbereitet, DNS und Ingress bei Mittwald sind aktuell noch nicht aufgeschaltet. Der Schalter digital_domain_is_live steht auf {$switchText}, im Katalog stehen {$activeLabel} und {$draftLabel} bereit.";
        }

        $sslPart = ($sslDays !== null) ? " Das SSL-Zertifikat ist noch {$sslDays} Tage gültig." : '';

        if ($inMaintenance) {
            return "Die Subdomain digital.mein-seelenfunke.de ist per DNS aufgeschaltet und geschützt im Wartungsmodus mit HTTP 503 aktiv. Der Schalter digital_domain_is_live steht auf {$switchText}, im Katalog stehen {$activeLabel} und {$draftLabel} bereit.{$sslPart}";
        }

        if ($reachable && $httpStatus >= 200 && $httpStatus < 400) {
            return "Die Subdomain digital.mein-seelenfunke.de ist live erreichbar mit HTTP {$httpStatus}. Der Schalter digital_domain_is_live steht auf {$switchText}, im Katalog stehen {$activeLabel} und {$draftLabel} bereit.{$sslPart}";
        }

        $errDetail = $httpStatus > 0 ? "HTTP {$httpStatus}" : 'keine Verbindung';

        return "Bei der Subdomain digital.mein-seelenfunke.de ist DNS hinterlegt, aber der Abruf meldet aktuell eine Störung ({$errDetail}). Der Schalter digital_domain_is_live steht auf {$switchText}, im Katalog stehen {$activeLabel} und {$draftLabel} bereit.";
    }

    /**
     * Stellt sicher, dass live_domain immer aktuelle Subdomain- und Digital-Shop-Stammdaten enthält.
     */
    protected function enrichLiveDomainWithSubdomains(array $liveDomain, array $geminiBridge = []): array
    {
        $checkedAt = (string) ($liveDomain['checked_at'] ?? now()->toIso8601String());
        $digitalReadiness = $this->inspectDigitalShopReadiness();

        if (is_array($liveDomain['subdomains'] ?? null) && isset($liveDomain['subdomains']['digital'])) {
            $subdomains = $liveDomain['subdomains'];
            $dig = $subdomains['digital'];
            $dig['digital_domain_is_live'] = (bool) $digitalReadiness['digital_domain_is_live'];
            $dig['active_digital_products'] = (int) $digitalReadiness['active_digital_products'];
            $dig['prepared_draft_products'] = (int) $digitalReadiness['prepared_draft_products'];
            $dig['total_digital_products'] = (int) $digitalReadiness['total_digital_products'];
            if (($dig['status'] ?? '') === 'ok' && !$dig['digital_domain_is_live']) {
                $dig['state_label'] = 'Erreichbar (HTTP ' . ((int) ($dig['http_status'] ?? 200)) . ' · Schalter AUS)';
            } elseif (($dig['status'] ?? '') === 'ok' && $dig['digital_domain_is_live']) {
                $lat = (float) ($dig['latency_ms'] ?? 0.0);
                $http = (int) ($dig['http_status'] ?? 200);
                $dig['state_label'] = $lat > 0 ? "Online (HTTP {$http} · {$lat} ms)" : "Online (HTTP {$http})";
            }
            $dig['spoken_summary'] = $this->buildDigitalSpokenSummary($dig);
            $subdomains['digital'] = $dig;

            $liveDomain['subdomains'] = $subdomains;
            $liveDomain['digital_subdomain'] = $dig;

            return $liveDomain;
        }

        $digitalIps = $this->resolveHostDnsIps(self::DIGITAL_SUBDOMAIN_HOST);
        $mainReachable = (bool) ($liveDomain['reachable'] ?? false);
        $mainHttp = (int) ($liveDomain['http_status'] ?? 0);
        $wwwActive = (bool) ($liveDomain['www_ingress_active'] ?? false);
        $wwwHttp = (int) ($liveDomain['www_http_status'] ?? 0);
        $bridgeOk = (bool) ($geminiBridge['live_bridge_ok'] ?? true);
        $bridgeHttp = (int) ($geminiBridge['live_bridge_http_status'] ?? ($bridgeOk ? 200 : 0));
        $wsProbed = (bool) ($geminiBridge['twilio_bridge_probed'] ?? false);
        $wsOk = $wsProbed ? (bool) ($geminiBridge['twilio_bridge_ok'] ?? true) : true;
        $wsHttp = $wsProbed ? (int) ($geminiBridge['twilio_bridge_http_status'] ?? ($wsOk ? 200 : 0)) : 200;

        $syntheticEntries = [
            'digital' => [
                'dns_configured' => count($digitalIps) > 0,
                'dns_ips' => $digitalIps,
                'reachable' => false,
                'http_status' => 0,
                'latency_ms' => 0.0,
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ],
            'main' => [
                'dns_configured' => $mainReachable || $mainHttp > 0,
                'dns_ips' => ($mainReachable || $mainHttp > 0) ? ['resolved'] : [],
                'reachable' => $mainReachable,
                'http_status' => $mainHttp,
                'latency_ms' => (float) ($liveDomain['latency_ms'] ?? 0.0),
                'ssl_days_remaining' => $liveDomain['ssl_days_remaining'] ?? null,
                'ssl_valid_until' => $liveDomain['ssl_valid_until'] ?? null,
            ],
            'stage' => [
                'dns_configured' => true,
                'dns_ips' => ['resolved'],
                'reachable' => true,
                'http_status' => 200,
                'latency_ms' => 0.0,
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ],
            'ws' => [
                'dns_configured' => true,
                'dns_ips' => ['resolved'],
                'reachable' => $wsOk && $wsHttp > 0,
                'http_status' => $wsHttp,
                'latency_ms' => 0.0,
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ],
            'api_live_bridge' => [
                'dns_configured' => true,
                'dns_ips' => ['resolved'],
                'reachable' => $bridgeOk && $bridgeHttp > 0,
                'http_status' => $bridgeHttp,
                'latency_ms' => (float) ($geminiBridge['latency_ms'] ?? 0.0),
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ],
            'www' => [
                'dns_configured' => $wwwActive || $wwwHttp > 0,
                'dns_ips' => ($wwwActive || $wwwHttp > 0) ? ['resolved'] : [],
                'reachable' => $wwwActive,
                'http_status' => $wwwHttp,
                'latency_ms' => 0.0,
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ],
        ];

        $subdomains = $this->buildSubdomainsMatrix($syntheticEntries, $digitalReadiness, $checkedAt);
        $liveDomain['subdomains'] = $subdomains;
        $liveDomain['digital_subdomain'] = $subdomains['digital'];

        return $liveDomain;
    }

    /**
     * Liefert die Subdomain-Übersicht für das Admin-Dashboard ohne blockierenden Netzwerk-Call beim Initial-Render.
     */
    public function getDashboardSubdomainsSnapshot(?array $pulseReport = null): array
    {
        if (is_array($pulseReport['subdomains'] ?? null) && !empty($pulseReport['subdomains'])) {
            $enriched = $this->enrichLiveDomainWithSubdomains([
                'subdomains' => $pulseReport['subdomains'],
                'checked_at' => $pulseReport['checked_at'] ?? now()->toIso8601String(),
            ]);

            return $enriched['subdomains'] ?? [];
        }

        if (Cache::has(self::LIVE_DOMAIN_CACHE_KEY)) {
            $cached = Cache::get(self::LIVE_DOMAIN_CACHE_KEY);
            if (is_array($cached)) {
                $enriched = $this->enrichLiveDomainWithSubdomains($cached);

                return $enriched['subdomains'] ?? [];
            }
        }

        if ($this->hasActiveTestDomainMocks()) {
            $probed = $this->getCachedOrProbeLiveDomains(false);
            $enriched = $this->enrichLiveDomainWithSubdomains($probed);

            return $enriched['subdomains'] ?? [];
        }

        $fallbackLive = $this->evaluateLiveDomain(false, null);
        $enriched = $this->enrichLiveDomainWithSubdomains($fallbackLive);

        return $enriched['subdomains'] ?? [];
    }

    public function isHttpClientFaked(): bool
    {
        try {
            $factory = Http::getFacadeRoot();
            if (!is_object($factory)) {
                return false;
            }
            $ref = new \ReflectionObject($factory);
            if ($ref->hasProperty('recording')) {
                $prop = $ref->getProperty('recording');
                $prop->setAccessible(true);

                return (bool) $prop->getValue($factory);
            }
        } catch (\Throwable $ignore) {
        }

        return false;
    }

    protected function resolveHostSslCertificateInfo(string $host, bool $dnsConfigured): array
    {
        if (!$dnsConfigured || app()->runningUnitTests()) {
            return [
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ];
        }

        $cacheKey = self::SSL_HOST_CACHE_PREFIX . md5(strtolower(trim($host)));

        return Cache::remember($cacheKey, 21600, function () use ($host): array {
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
                    1.2,
                    STREAM_CLIENT_CONNECT,
                    $ctx
                );
                if (!$client) {
                    return ['ssl_days_remaining' => null, 'ssl_valid_until' => null];
                }
                $params = @stream_context_get_params($client);
                @fclose($client);

                $certResource = $params['options']['ssl']['peer_certificate'] ?? null;
                if ($certResource) {
                    $parsed = @openssl_x509_parse($certResource);
                    $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
                    if ($validTo > 0) {
                        return [
                            'ssl_days_remaining' => (int) floor(($validTo - time()) / 86400),
                            'ssl_valid_until' => Carbon::createFromTimestamp($validTo)->toIso8601String(),
                        ];
                    }
                }
            } catch (\Throwable $ignore) {
            }

            return [
                'ssl_days_remaining' => null,
                'ssl_valid_until' => null,
            ];
        });
    }

    /**
     * Liefert die Health-URLs für die Node.js Gemini Live Audio-Bridge (a-yeq7el)
     * sowie optional für die Twilio-Reverb-Bridge (a-iurgq8, sofern ws.mein-seelenfunke.de aktiv ist).
     */
    public function resolveGeminiBridgeHealthUrls(): array
    {
        $rawLiveUrl = trim((string) env('GEMINI_LIVE_BRIDGE_URL', ''));
        if ($rawLiveUrl === '') {
            $rawLiveUrl = trim((string) config('services.gemini.proxy_ws_url', ''));
        }

        $liveBridgeHealthUrl = $this->deriveHealthUrlFromBridgeConfig(
            $rawLiveUrl,
            '/health',
            self::GEMINI_BRIDGE_DEFAULT_HEALTH_URL
        );

        $twilioWss = strtolower(trim((string) config('services.twilio.wss_url', '')));
        $twilioBridgeHealthUrl = ($twilioWss !== '' && str_contains($twilioWss, 'ws.mein-seelenfunke.de'))
            ? self::TWILIO_BRIDGE_DEFAULT_HEALTH_URL
            : null;

        return [
            'live_bridge' => $liveBridgeHealthUrl,
            'reverb_twilio' => $twilioBridgeHealthUrl,
        ];
    }

    protected function deriveHealthUrlFromBridgeConfig(string $rawUrl, string $healthPath, string $defaultUrl): string
    {
        if ($rawUrl === '') {
            return $defaultUrl;
        }

        $normalized = preg_replace(['#^wss://#i', '#^ws://#i'], ['https://', 'http://'], $rawUrl) ?? $rawUrl;
        if (!str_contains($normalized, '://')) {
            $normalized = 'https://' . ltrim($normalized, '/');
        }

        $parsed = @parse_url($normalized);
        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return $defaultUrl;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? 'https'));
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $portPart = ($port !== null && $port > 0 && $port !== $defaultPort) ? ':' . $port : '';

        return $scheme . '://' . $host . $portPart . '/' . ltrim($healthPath, '/');
    }

    /**
     * Liefert kurzzeitig gecachte (60s TTL NUR bei gesundem Status!) oder frisch geprüfte Gemini-Bridge-Messdaten.
     * Ein Fehlerzustand wird niemals im Cache gehalten, damit eine Erholung sofort erkannt wird.
     */
    public function getCachedOrProbeGeminiBridge(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::GEMINI_BRIDGE_CACHE_KEY);
        } else {
            $cached = Cache::get(self::GEMINI_BRIDGE_CACHE_KEY);
            if (is_array($cached) && !empty($cached['healthy'])) {
                return $cached;
            }
        }

        if (self::$geminiBridgeProbeResolver !== null) {
            try {
                $custom = (self::$geminiBridgeProbeResolver)();
                $raw = is_array($custom) ? $custom : ['healthy' => false, 'live_bridge_ok' => false, 'live_bridge_http_status' => 0];
            } catch (\Throwable $e) {
                $raw = [
                    'healthy' => false,
                    'reachable' => false,
                    'live_bridge_ok' => false,
                    'live_bridge_http_status' => 0,
                    'error' => SystemErrorLogger::sanitizeSensitiveText($e->getMessage()),
                ];
            }
        } else {
            $raw = $this->probeGeminiBridge();
        }

        $normalized = $this->normalizeGeminiBridgeProbePayload($raw);
        if (!empty($normalized['healthy'])) {
            Cache::put(self::GEMINI_BRIDGE_CACHE_KEY, $normalized, self::GEMINI_BRIDGE_CACHE_TTL);
        } else {
            Cache::forget(self::GEMINI_BRIDGE_CACHE_KEY);
        }

        return $normalized;
    }

    /**
     * Erzwingt eine ungecachte Echtzeit-Prüfung und Bewertung der Gemini Audio Bridge (z. B. für SentinelTriageService).
     */
    public function probeAndEvaluateGeminiBridgeFresh(): array
    {
        $raw = $this->getCachedOrProbeGeminiBridge(true);

        return $this->evaluateGeminiBridge(true, $raw);
    }

    /**
     * Aktiver HTTP-Health-Check (3s Timeout + 1 Retry nach 300ms bei Fehler) für die Gemini Live Audio-Bridge
     * sowie optional für die Twilio-Reverb-Bridge.
     */
    public function probeGeminiBridge(): array
    {
        $urls = $this->resolveGeminiBridgeHealthUrls();
        $liveProbe = $this->probeSingleBridgeHealthEndpoint($urls['live_bridge'], 3.0);

        $twilioProbe = null;
        if ($urls['reverb_twilio'] !== null) {
            $twilioProbe = $this->probeSingleBridgeHealthEndpoint($urls['reverb_twilio'], 3.0);
        }

        $twilioOk = $twilioProbe === null ? true : (bool) $twilioProbe['ok'];
        $healthy = (bool) $liveProbe['ok'] && $twilioOk;
        $totalLatency = round(
            (float) $liveProbe['latency_ms'] + (float) ($twilioProbe['latency_ms'] ?? 0.0),
            2
        );

        return [
            'probed' => true,
            'healthy' => $healthy,
            'live_bridge_url' => $urls['live_bridge'],
            'live_bridge_ok' => (bool) $liveProbe['ok'],
            'live_bridge_reachable' => (bool) $liveProbe['reachable'],
            'live_bridge_http_status' => (int) $liveProbe['http_status'],
            'live_bridge_json_status' => $liveProbe['json_status'],
            'live_bridge_version' => $liveProbe['bridge_version'],
            'live_bridge_error' => $liveProbe['error'],
            'twilio_bridge_url' => $urls['reverb_twilio'],
            'twilio_bridge_probed' => $twilioProbe !== null,
            'twilio_bridge_ok' => $twilioOk,
            'twilio_bridge_http_status' => $twilioProbe !== null ? (int) $twilioProbe['http_status'] : null,
            'twilio_bridge_json_status' => $twilioProbe['json_status'] ?? null,
            'twilio_bridge_error' => $twilioProbe['error'] ?? null,
            'latency_ms' => $totalLatency,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    protected function probeSingleBridgeHealthEndpoint(string $url, float $timeoutSeconds = 3.0): array
    {
        $t0 = microtime(true);
        $lastHttpStatus = 0;
        $lastJsonStatus = null;
        $lastBridgeVersion = null;
        $lastReachable = false;
        $lastError = null;
        $attemptsUsed = 0;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $attemptsUsed = $attempt;
            if ($attempt === 2 && !app()->runningUnitTests()) {
                usleep(300_000);
            }

            try {
                $response = Http::withOptions([
                    'force_ip_resolve' => 'v4',
                    'connect_timeout' => $timeoutSeconds,
                    'timeout' => $timeoutSeconds,
                ])->acceptJson()->get($url);

                $lastHttpStatus = $response->status();
                $lastReachable = $lastHttpStatus > 0;
                $decoded = $response->json();
                $lastJsonStatus = is_array($decoded) && isset($decoded['status']) ? (string) $decoded['status'] : null;
                $lastBridgeVersion = is_array($decoded) && isset($decoded['bridge_version']) ? (string) $decoded['bridge_version'] : null;

                if ($lastHttpStatus === 200 && $lastJsonStatus === 'ok') {
                    return [
                        'url' => $url,
                        'reachable' => true,
                        'ok' => true,
                        'http_status' => 200,
                        'json_status' => 'ok',
                        'bridge_version' => $lastBridgeVersion,
                        'latency_ms' => round((microtime(true) - $t0) * 1000, 2),
                        'attempts' => $attemptsUsed,
                        'error' => null,
                    ];
                }

                $lastError = $lastHttpStatus === 200
                    ? 'Unerwarteter Health-Payload (status=' . ($lastJsonStatus ?? 'null') . ')'
                    : 'HTTP ' . $lastHttpStatus;
            } catch (\Throwable $e) {
                $lastReachable = false;
                $lastHttpStatus = 0;
                $lastError = SystemErrorLogger::sanitizeSensitiveText($e->getMessage());
            }
        }

        return [
            'url' => $url,
            'reachable' => $lastReachable,
            'ok' => false,
            'http_status' => $lastHttpStatus,
            'json_status' => $lastJsonStatus,
            'bridge_version' => $lastBridgeVersion,
            'latency_ms' => round((microtime(true) - $t0) * 1000, 2),
            'attempts' => $attemptsUsed,
            'error' => $lastError,
        ];
    }

    protected function normalizeGeminiBridgeProbePayload(array $raw): array
    {
        $urls = $this->resolveGeminiBridgeHealthUrls();
        $liveUrl = (string) ($raw['live_bridge_url'] ?? $urls['live_bridge']);
        $twilioUrl = array_key_exists('twilio_bridge_url', $raw) ? $raw['twilio_bridge_url'] : $urls['reverb_twilio'];

        if (array_key_exists('live_bridge_ok', $raw)) {
            $liveOk = (bool) $raw['live_bridge_ok'];
            $liveHttpStatus = (int) ($raw['live_bridge_http_status'] ?? ($liveOk ? 200 : 503));
            $liveReachable = array_key_exists('live_bridge_reachable', $raw)
                ? (bool) $raw['live_bridge_reachable']
                : ($liveHttpStatus > 0);
            $liveJsonStatus = $raw['live_bridge_json_status'] ?? ($liveOk ? 'ok' : null);
        } else {
            $liveReachable = array_key_exists('reachable', $raw)
                ? (bool) $raw['reachable']
                : (((int) ($raw['http_status'] ?? 200)) > 0);
            $liveHttpStatus = (int) ($raw['http_status'] ?? ($liveReachable ? 200 : 0));
            $bodyStatus = is_array($raw['body'] ?? null) ? ($raw['body']['status'] ?? null) : null;
            $liveJsonStatus = $raw['json_status'] ?? $bodyStatus ?? ($raw['status'] ?? ($liveHttpStatus === 200 && $liveReachable ? 'ok' : null));
            $liveOk = $liveReachable && $liveHttpStatus === 200 && $liveJsonStatus === 'ok';
            if (array_key_exists('healthy', $raw) && $raw['healthy'] === false) {
                $liveOk = false;
            }
        }

        $twilioProbed = array_key_exists('twilio_bridge_probed', $raw)
            ? (bool) $raw['twilio_bridge_probed']
            : array_key_exists('twilio_bridge_ok', $raw);
        $twilioOk = $twilioProbed ? (bool) ($raw['twilio_bridge_ok'] ?? true) : true;
        $twilioHttpStatus = $twilioProbed
            ? (int) ($raw['twilio_bridge_http_status'] ?? ($twilioOk ? 200 : 503))
            : null;

        $healthy = $liveOk && $twilioOk;

        return [
            'probed' => (bool) ($raw['probed'] ?? true),
            'healthy' => $healthy,
            'live_bridge_url' => $liveUrl,
            'live_bridge_ok' => $liveOk,
            'live_bridge_reachable' => $liveReachable,
            'live_bridge_http_status' => $liveHttpStatus,
            'live_bridge_json_status' => $liveJsonStatus,
            'live_bridge_version' => $raw['live_bridge_version'] ?? ($raw['bridge_version'] ?? null),
            'live_bridge_error' => $raw['live_bridge_error'] ?? ($raw['error'] ?? null),
            'twilio_bridge_url' => $twilioUrl,
            'twilio_bridge_probed' => $twilioProbed,
            'twilio_bridge_ok' => $twilioOk,
            'twilio_bridge_http_status' => $twilioHttpStatus,
            'twilio_bridge_json_status' => $raw['twilio_bridge_json_status'] ?? ($twilioOk && $twilioProbed ? 'ok' : null),
            'twilio_bridge_error' => $raw['twilio_bridge_error'] ?? null,
            'latency_ms' => round((float) ($raw['latency_ms'] ?? 0.0), 2),
            'checked_at' => $raw['checked_at'] ?? now()->toIso8601String(),
        ];
    }

    public function evaluateGeminiBridge(bool $strict, ?array $overrideGeminiBridge = null): array
    {
        $urls = $this->resolveGeminiBridgeHealthUrls();

        if ($overrideGeminiBridge !== null) {
            $raw = $this->normalizeGeminiBridgeProbePayload($overrideGeminiBridge);
            $enforceStrictThresholds = true;
        } elseif (self::$geminiBridgeProbeResolver !== null) {
            $raw = $this->getCachedOrProbeGeminiBridge(false);
            $enforceStrictThresholds = true;
        } elseif (app()->runningUnitTests()) {
            // In isolierten Unit-Tests ohne Custom-Resolver keine echten Netzwerk-Calls auslösen
            return [
                'probed' => false,
                'status' => 'ok',
                'label' => 'Übersprungen (Testumgebung)',
                'issues' => [],
                'live_bridge_url' => $urls['live_bridge'],
                'live_bridge_ok' => true,
                'live_bridge_http_status' => 200,
                'twilio_bridge_url' => $urls['reverb_twilio'],
                'twilio_bridge_probed' => false,
                'twilio_bridge_ok' => true,
                'twilio_bridge_http_status' => null,
                'latency_ms' => 0.0,
                'checked_at' => now()->toIso8601String(),
            ];
        } elseif (!app()->environment('production', 'staging') && !$strict && !Cache::has(self::GEMINI_BRIDGE_CACHE_KEY)) {
            return [
                'probed' => false,
                'status' => 'ok',
                'label' => 'Bereit (Auf Abruf lokal)',
                'issues' => [],
                'live_bridge_url' => $urls['live_bridge'],
                'live_bridge_ok' => true,
                'live_bridge_http_status' => 200,
                'twilio_bridge_url' => $urls['reverb_twilio'],
                'twilio_bridge_probed' => false,
                'twilio_bridge_ok' => true,
                'twilio_bridge_http_status' => null,
                'latency_ms' => 0.0,
                'checked_at' => now()->toIso8601String(),
            ];
        } else {
            $raw = $this->getCachedOrProbeGeminiBridge(false);
            $enforceStrictThresholds = $strict;
        }

        $liveOk = (bool) ($raw['live_bridge_ok'] ?? false);
        $liveHttp = (int) ($raw['live_bridge_http_status'] ?? 0);
        $twilioProbed = (bool) ($raw['twilio_bridge_probed'] ?? false);
        $twilioOk = (bool) ($raw['twilio_bridge_ok'] ?? true);
        $twilioHttp = $raw['twilio_bridge_http_status'] ?? null;
        $latencyMs = round((float) ($raw['latency_ms'] ?? 0.0), 2);

        $status = 'ok';
        $issues = [];

        if (!$liveOk) {
            $status = $this->escalateStatus($status, $enforceStrictThresholds ? 'critical' : 'warning');
            $liveHost = (string) (parse_url((string) $raw['live_bridge_url'], PHP_URL_HOST) ?: 'api-live-bridge.mein-seelenfunke.de');
            $detail = $liveHttp > 0
                ? "HTTP {$liveHttp}"
                : ((string) ($raw['live_bridge_error'] ?? '') ?: 'Timeout / Nicht erreichbar');
            $issues[] = "Gemini Live Audio-Bridge ({$liveHost}) gestört ({$detail})";
        }

        if ($twilioProbed && !$twilioOk) {
            $status = $this->escalateStatus($status, $enforceStrictThresholds ? 'critical' : 'warning');
            $twDetail = ($twilioHttp !== null && (int) $twilioHttp > 0)
                ? "HTTP {$twilioHttp}"
                : ((string) ($raw['twilio_bridge_error'] ?? '') ?: 'Timeout / Nicht erreichbar');
            $issues[] = "Twilio Reverb Audio-Bridge (ws.mein-seelenfunke.de) gestört ({$twDetail})";
        }

        $label = match ($status) {
            'critical', 'warning' => $issues[0] ?? 'Gemini Audio Bridge gestört',
            default => $latencyMs > 0 ? "Online ({$latencyMs} ms)" : 'Online',
        };

        return [
            'probed' => true,
            'status' => $status,
            'label' => $label,
            'issues' => $issues,
            'live_bridge_url' => $raw['live_bridge_url'],
            'live_bridge_ok' => $liveOk,
            'live_bridge_http_status' => $liveHttp,
            'live_bridge_version' => $raw['live_bridge_version'] ?? null,
            'twilio_bridge_url' => $raw['twilio_bridge_url'],
            'twilio_bridge_probed' => $twilioProbed,
            'twilio_bridge_ok' => $twilioOk,
            'twilio_bridge_http_status' => $twilioHttp,
            'latency_ms' => $latencyMs,
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
            $isGeminiBridge = ((string) $pillarKey === 'gemini_bridge');
            $actionId = $isGeminiBridge
                ? self::GEMINI_BRIDGE_LOG_ACTION_ID
                : ('watchtower:pillar:' . $pillarKey);
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
                    $p['level'] = 'ERROR';
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
                            'level' => 'ERROR',
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

                if ($isGeminiBridge) {
                    $this->notifyGeminiBridgeOutage($pillar, $issueText, $now);
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

                if ($isGeminiBridge) {
                    $this->resolveGeminiBridgeOutage($now);
                }
            }
        }

        return ['logged' => $logged, 'healed' => $healed];
    }

    /**
     * Erzeugt bei einem Ausfall der Gemini Audio Bridge idempotent eine dringende Aufgabe
     * auf /admin/dashboard (Baustein 6) und sendet gedrosselt (max. 1x alle 15 Min) eine FCM-Push-Warnung an Admin-Geräte.
     */
    public function notifyGeminiBridgeOutage(array $bridgeReport, ?string $issueText = null, ?Carbon $now = null): ?ManagementTask
    {
        $now = $now ?? now();
        $cleanIssue = $issueText ?: (implode(' | ', $bridgeReport['issues'] ?? []) ?: 'Gemini Audio Bridge antwortet nicht mit HTTP 200 (status=ok)');
        $task = null;

        try {
            if (class_exists(ManagementTask::class) && Schema::hasTable('management_tasks') && Schema::hasTable('management_task_lists')) {
                $task = ManagementTask::query()
                    ->whereNull('parent_id')
                    ->where('is_completed', false)
                    ->where('is_archived', false)
                    ->where('title', self::GEMINI_BRIDGE_TASK_TITLE)
                    ->first();

                $planLines = [
                    'Automatische Watchtower-Warnung (' . $now->format('d.m.Y H:i:s') . ')',
                    'Befund: ' . $cleanIssue,
                    'Notfall-Neustart Node.js Gemini Live-Bridge (a-yeq7el):',
                    self::GEMINI_BRIDGE_RESTART_CMD,
                    'Notfall-Neustart Twilio Reverb-Bridge (a-iurgq8, falls betroffen):',
                    'ssh kontakt@mein-seelenfunke.de@a-iurgq8@ssh.altgemeinde.project.host "mittnitectl job restart"',
                    'Hinweis: Sobald der Health-Check wieder HTTP 200 (status=ok) meldet, wird diese Aufgabe automatisch erledigt.',
                ];

                if (!$task) {
                    $defaultList = ManagementTaskList::where('is_archived', false)
                        ->orderBy('position', 'asc')
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if (!$defaultList) {
                        $defaultList = ManagementTaskList::create([
                            'name' => 'Allgemein',
                            'icon' => 'clipboard-document-list',
                            'color' => '#C5A059',
                            'position' => 0,
                        ]);
                    }

                    $task = ManagementTask::create([
                        'task_list_id' => $defaultList->id,
                        'title' => self::GEMINI_BRIDGE_TASK_TITLE,
                        'is_completed' => false,
                        'is_archived' => false,
                        'position' => 0,
                        'priority' => 'hoch',
                        'ai_plan' => implode("\n", $planLines),
                    ]);
                } else {
                    $task->update([
                        'priority' => 'hoch',
                        'ai_plan' => implode("\n", $planLines),
                    ]);
                }
            }
        } catch (\Throwable $ignore) {
        }

        try {
            if (Cache::add(self::GEMINI_BRIDGE_PUSH_LOCK_KEY, $now->toIso8601String(), self::GEMINI_BRIDGE_PUSH_COOLDOWN_SECONDS)) {
                if (class_exists(\App\Services\FirebaseService::class)) {
                    $firebase = app(\App\Services\FirebaseService::class);
                    $credentialsPath = (string) config('services.firebase.credentials_file', storage_path('app/firebase-service-account.json'));
                    $isCustomInstance = get_class($firebase) !== \App\Services\FirebaseService::class;

                    if ($isCustomInstance || ($credentialsPath !== '' && is_file($credentialsPath))) {
                        $firebase->sendToAdmins(
                            '⚠️ Gemini Audio Bridge ausgefallen',
                            Str::limit($cleanIssue . ' · Bitte Dienst auf Stage neustarten.', 220, '...'),
                            [
                                'type' => 'gemini_bridge_outage',
                                'navigate_to' => 'organizer',
                                'open_subtab' => '7',
                                'task_id' => (string) ($task?->id ?? ''),
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $ignore) {
        }

        return $task;
    }

    /**
     * Hakt eine offene automatische Gemini-Bridge-Warnaufgabe auf /admin/dashboard als erledigt ab
     * und gibt den Push-Rate-Limit-Lock nach bestätigter Erholung wieder frei.
     */
    public function resolveGeminiBridgeOutage(?Carbon $now = null): int
    {
        $resolvedTasks = 0;
        Cache::forget(self::GEMINI_BRIDGE_PUSH_LOCK_KEY);

        try {
            if (class_exists(ManagementTask::class) && Schema::hasTable('management_tasks')) {
                $openTasks = ManagementTask::query()
                    ->whereNull('parent_id')
                    ->where('is_completed', false)
                    ->where('title', self::GEMINI_BRIDGE_TASK_TITLE)
                    ->get();

                foreach ($openTasks as $openTask) {
                    $openTask->is_completed = true;
                    $openTask->save();
                    $resolvedTasks++;
                }
            }
        } catch (\Throwable $ignore) {
        }

        return $resolvedTasks;
    }

    protected function pillarLabel(string $key): string
    {
        return match ($key) {
            'shop' => 'Shop & SSL',
            'cronjobs' => 'Cronjobs',
            'queues' => 'Queue-Worker',
            'backups' => 'Backups',
            'live_domain' => 'Live-Domain',
            'gemini_bridge' => 'Gemini Audio Bridge',
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
