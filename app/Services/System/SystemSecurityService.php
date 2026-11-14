<?php

namespace App\Services\System;

use App\Models\System\SystemBlockedIp;
use App\Models\System\SystemLog;
use App\Models\System\SystemLoginAttempt;
use Illuminate\Http\Request;

class SystemSecurityService
{
    /**
     * Protokolliert ein Sicherheits- oder Abwehr-Ereignis zentral im SystemLog (Typ: security).
     */
    public static function logEvent(
        string $actionId,
        string $title,
        string $message,
        $status = 'warning',
        $context = [],
        ?string $categoryOverride = null
    ): void {
        try {
            // Unterstützung für alternative Aufrufsignatur: logEvent($actionId, 'WARNING'|'ERROR'|'INFO', $message, $contextArray, $category)
            if (is_array($status)) {
                $altStatus = strtolower($title);
                $categoryFromFifth = is_string($context) ? $context : $categoryOverride;
                $context = $status;
                if ($categoryFromFifth && !isset($context['category'])) {
                    $context['category'] = $categoryFromFifth;
                }
                $status = in_array($altStatus, ['error', 'warning', 'info', 'success'], true) ? $altStatus : 'warning';
                $title = $message;
            } else {
                $status = strtolower((string) $status);
                $context = is_array($context) ? $context : [];
                if ($categoryOverride && !isset($context['category'])) {
                    $context['category'] = $categoryOverride;
                }
            }

            $request = request();
            $ip = $context['ip_address'] ?? ($context['ip'] ?? ($request ? $request->ip() : null));

            $category = $context['category'] ?? self::inferCategory($actionId);

            $payload = array_merge([
                'category' => $category,
                'ip_address' => $ip,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : 'CLI',
                'url' => $request ? $request->fullUrl() : null,
                'timestamp_iso' => now()->toIso8601String(),
            ], $context);

            SystemLog::create([
                'type' => 'security',
                'action_id' => $actionId,
                'title' => $title,
                'message' => $message,
                'status' => in_array($status, ['error', 'warning', 'info', 'success'], true) ? $status : 'warning',
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Niemals den Hauptprozess durch Logging-Fehler unterbrechen
        }
    }

    /**
     * Zeichnet einen Login-Versuch auf und aktiviert bei massiven Brute-Force-Angriffen (>= 10 Fehlversuche / 15 Min)
     * automatisch die IP-Firewall für 24 Stunden.
     */
    public static function recordLoginAttempt(?string $email, $ipOrSuccess, $successOrIp = false, string $channel = 'web'): void
    {
        try {
            if (is_bool($ipOrSuccess)) {
                $success = $ipOrSuccess;
                $ip = is_string($successOrIp) ? $successOrIp : null;
            } else {
                $ip = is_string($ipOrSuccess) ? $ipOrSuccess : null;
                $success = (bool) $successOrIp;
            }

            $ip = $ip ?: request()?->ip();

            SystemLoginAttempt::create([
                'email' => substr((string) $email, 0, 255),
                'ip_address' => $ip,
                'success' => $success,
                'attempted_at' => now(),
            ]);

            if (!$success && !empty($ip)) {
                $recentFailures = SystemLoginAttempt::where('ip_address', $ip)
                    ->where('success', false)
                    ->where('attempted_at', '>=', now()->subMinutes(15))
                    ->count();

                if ($recentFailures >= 10 && !SystemBlockedIp::isBlocked($ip)) {
                    SystemBlockedIp::blockIp(
                        $ip,
                        "Automatische Brute-Force-Abwehr ({$recentFailures} Fehlversuche in 15 Min. via {$channel})",
                        24,
                        'Auto-Defense'
                    );

                    self::logEvent(
                        'security:auto_ip_ban',
                        'Automatische IP-Sperre (Brute-Force)',
                        "IP {$ip} wurde nach {$recentFailures} fehlgeschlagenen Login-Versuchen (Kanal: {$channel}, Letztes Ziel: {$email}) automatisch für 24h gesperrt.",
                        'error',
                        [
                            'category' => 'brute_force',
                            'ip_address' => $ip,
                            'target_email' => $email,
                            'attempts_15m' => $recentFailures,
                            'channel' => $channel,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            // Fail-safe
        }
    }

    /**
     * Optionale Test-Auflösung für DNS-Abfragen in SystemSecurityService::isSafeMailHost().
     * Signatur: fn(string $host): array<int, string>
     */
    public static ?\Closure $dnsResolver = null;

    /**
     * Prüft, ob ein interner Bridge-/Webhook-Aufruf von einer vertrauenswürdigen lokalen Quelle
     * oder mit gültigem Bridge-Secret erfolgt.
     */
    public static function isTrustedBridgeRequest(Request $request): bool
    {
        $configuredSecrets = array_values(array_unique(array_filter([
            config('services.gemini.bridge_secret'),
            config('services.ai.proxy_secret'),
            config('services.twilio.bridge_secret'),
            app()->configurationIsCached() ? null : env('BRIDGE_SECRET'),
            app()->configurationIsCached() ? null : env('AI_PROXY_SECRET'),
        ], fn ($v) => is_string($v) && trim($v) !== '')));

        $providedSecret = $request->header('X-Bridge-Secret')
            ?: $request->header('X-AI-Proxy-Secret')
            ?: $request->input('bridge_secret');

        if (is_string($providedSecret) && trim($providedSecret) !== '') {
            foreach ($configuredSecrets as $secret) {
                if (hash_equals((string) $secret, $providedSecret)) {
                    return true;
                }
            }
            // Ein explizit mitgesendetes, aber falsches Secret darf niemals durch IP-Fallback akzeptiert werden
            return false;
        }

        // Sobald ein Bridge-Secret konfiguriert ist oder die Umgebung production/staging ist,
        // muss IMMER ein gültiges X-Bridge-Secret per hash_equals nachgewiesen werden (auch von 127.0.0.1)
        if (!empty($configuredSecrets) || app()->environment(['production', 'staging'])) {
            return false;
        }

        // Requests mit Reverse-Proxy-Headern ohne gültiges Bridge-Secret immer VOR einer Loopback-Prüfung ablehnen
        if (
            $request->headers->has('X-Forwarded-For')
            || $request->headers->has('X-Real-IP')
            || $request->headers->has('CF-Connecting-IP')
            || $request->headers->has('Forwarded')
        ) {
            return false;
        }

        $clientIp = (string) $request->ip();
        $serverIp = (string) ($request->server('SERVER_ADDR') ?? '');

        $trustedLoopbackIps = array_filter([
            '127.0.0.1',
            '::1',
            'localhost',
            $serverIp,
        ]);

        return in_array($clientIp, $trustedLoopbackIps, true);
    }

    /**
     * Prüft, ob ein IMAP/SMTP-Host sicher ist (verhindert SSRF & DNS-Rebinding auf Loopback, Metadaten-Endpunkte oder interne Dienste).
     */
    public static function isSafeMailHost(string $host): bool
    {
        $cleanHost = strtolower(trim($host));
        if ($cleanHost === '') {
            return false;
        }

        // Entferne evtl. Protokoll-Präfixe wie ssl:// oder tls:// zur Prüfung
        $cleanHost = preg_replace('#^[a-z0-9+.-]+://#i', '', $cleanHost);
        if (str_starts_with($cleanHost, '[')) {
            $cleanHost = trim(explode(']', $cleanHost)[0], '[]');
        } else {
            $cleanHost = explode(':', $cleanHost)[0];
        }
        $cleanHost = rtrim($cleanHost, '.');

        if ($cleanHost === '') {
            return false;
        }

        $forbiddenHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0',
            '169.254.169.254',
            'metadata.google.internal',
            'host.docker.internal',
            'gateway.docker.internal',
            'kubernetes.default',
            'redis',
            'mysql',
            'mariadb',
            'postgres',
            'memcached',
            'db',
            'meine_datenbank',
            'mein_php_server',
            'mailpit',
            'mailhog',
        ];

        $forbiddenSuffixes = [
            '.internal',
            '.local',
            '.docker',
            '.localhost',
            '.nip.io',
            '.sslip.io',
            '.xip.io',
            '.traefik.me',
            '.localtest.me',
            '.lvh.me',
        ];

        if (in_array($cleanHost, $forbiddenHosts, true)) {
            return false;
        }

        foreach ($forbiddenSuffixes as $suffix) {
            if (str_ends_with($cleanHost, $suffix)) {
                return false;
            }
        }

        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            // Blockiere private und reservierte IP-Bereiche (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, 169.254.0.0/16)
            if (!filter_var($cleanHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            return true;
        }

        // Öffentliche Mail-Hosts müssen immer eine gültige Domain mit mindestens einem Punkt sein (blockiert Single-Label Container-Namen)
        if (!str_contains($cleanHost, '.') || !preg_match('/^[a-z0-9.-]+$/', $cleanHost)) {
            return false;
        }

        // DNS-Auflösung prüfen (verhindert DNS-Rebinding / Domains, die auf 127.0.0.1, 169.254.169.254 oder Docker-LAN zeigen)
        $resolvedIps = [];
        $dnsOverrides = config('services.mail_host_dns_override', []);

        if (self::$dnsResolver instanceof \Closure) {
            $resolvedIps = (array) (self::$dnsResolver)($cleanHost);
        } elseif (is_array($dnsOverrides) && array_key_exists($cleanHost, $dnsOverrides)) {
            $resolvedIps = (array) $dnsOverrides[$cleanHost];
        } else {
            $records = @dns_get_record($cleanHost, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $rec) {
                    if (!empty($rec['ip']) && is_string($rec['ip'])) {
                        $resolvedIps[] = $rec['ip'];
                    }
                    if (!empty($rec['ipv6']) && is_string($rec['ipv6'])) {
                        $resolvedIps[] = $rec['ipv6'];
                    }
                }
            }
            if (empty($resolvedIps)) {
                $v4List = @gethostbynamel($cleanHost);
                if (is_array($v4List)) {
                    foreach ($v4List as $ip) {
                        if (is_string($ip) && $ip !== '') {
                            $resolvedIps[] = $ip;
                        }
                    }
                }
            }
        }

        $resolvedIps = array_values(array_unique(array_filter($resolvedIps, fn ($ip) => is_string($ip) && trim($ip) !== '')));

        if (empty($resolvedIps)) {
            // In PHPUnit/Testing ohne aktive Internetverbindung syntaktisch saubere Public-Domains zulassen,
            // sofern kein explizites Override auf [] gesetzt wurde; in Produktion/Stage ablehnen.
            if (
                app()->environment('testing')
                && !(self::$dnsResolver instanceof \Closure)
                && !(is_array($dnsOverrides) && array_key_exists($cleanHost, $dnsOverrides))
            ) {
                return true;
            }
            return false;
        }

        foreach ($resolvedIps as $resolvedIp) {
            if (!filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Erlaubte Standard-Mail-Ports für benutzerdefinierte IMAP/SMTP-Konten.
     */
    public static function isAllowedMailPort(int $port): bool
    {
        return in_array($port, [143, 465, 587, 993, 2525], true);
    }

    /**
     * Verhindert unverschlüsselte Klartext-Mailverbindungen ('none') auf Nicht-Loopback-Hosts.
     */
    public static function isSafeMailEncryption(?string $encryption, string $host): bool
    {
        $enc = strtolower(trim((string) ($encryption ?? 'ssl')));
        if (in_array($enc, ['ssl', 'tls', 'starttls'], true)) {
            return true;
        }

        $cleanHost = strtolower(trim($host));
        $cleanHost = preg_replace('#^[a-z0-9+.-]+://#i', '', $cleanHost);
        $cleanHost = trim(explode(':', $cleanHost)[0], '[]');

        return in_array($cleanHost, ['127.0.0.1', '::1', 'localhost'], true);
    }

    private static function inferCategory(string $actionId): string
    {
        if (str_contains($actionId, '2fa') || str_contains($actionId, 'oauth') || str_contains($actionId, 'emergency_auth') || str_contains($actionId, 'password_reset')) {
            return 'auth_2fa';
        }
        if (str_contains($actionId, 'honeypot') || str_contains($actionId, 'bot') || str_contains($actionId, 'spam') || str_contains($actionId, 'flood') || str_contains($actionId, 'voucher_brute')) {
            return 'bot_defense';
        }
        if (str_contains($actionId, 'rate_limit') || str_contains($actionId, 'brute') || str_contains($actionId, 'ip_ban')) {
            return 'brute_force';
        }
        if (str_contains($actionId, 'ai') || str_contains($actionId, 'bridge') || str_contains($actionId, 'sandbox') || str_contains($actionId, 'api') || str_contains($actionId, 'telegram') || str_contains($actionId, 'webhook') || str_contains($actionId, 'cron_token')) {
            return 'api_ai_sandbox';
        }
        if (str_contains($actionId, 'idor') || str_contains($actionId, 'checkout') || str_contains($actionId, 'path') || str_contains($actionId, 'lfi') || str_contains($actionId, 'ssrf') || str_contains($actionId, 'cheat') || str_contains($actionId, 'revocation') || str_contains($actionId, 'tampering') || str_contains($actionId, 'redirect')) {
            return 'idor_integrity';
        }
        return 'general';
    }
}
