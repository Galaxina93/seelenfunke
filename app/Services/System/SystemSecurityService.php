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
     * Prüft, ob ein interner Bridge-/Webhook-Aufruf von einer vertrauenswürdigen lokalen Quelle
     * oder mit gültigem Bridge-Secret erfolgt.
     */
    public static function isTrustedBridgeRequest(Request $request): bool
    {
        $configuredSecrets = array_filter([
            config('services.gemini.bridge_secret'),
            config('services.twilio.bridge_secret'),
            env('BRIDGE_SECRET'),
            env('AI_PROXY_SECRET'),
        ], fn ($v) => is_string($v) && trim($v) !== '');

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

        $clientIp = (string) $request->ip();
        $serverIp = (string) ($request->server('SERVER_ADDR') ?? '');

        $trustedLoopbackIps = array_filter([
            '127.0.0.1',
            '::1',
            'localhost',
            $serverIp,
        ]);

        if (in_array($clientIp, $trustedLoopbackIps, true)) {
            return true;
        }

        // Wenn ein Bridge-Secret konfiguriert ist, sind nicht-lokale Zugriffe ohne gültiges Secret unzulässig
        if (!empty($configuredSecrets)) {
            return false;
        }

        // Wenn der Request über einen Reverse-Proxy (X-Forwarded-For / X-Real-IP / CF-Connecting-IP) kam,
        // stammt er von extern und darf sich nicht allein über die Docker-Proxy-IP authentifizieren
        if ($request->headers->has('X-Forwarded-For') || $request->headers->has('X-Real-IP') || $request->headers->has('CF-Connecting-IP')) {
            return false;
        }

        // Erlaube private/interne Container- & Server-Subnetze (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
        if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $clientIp)) {
            return true;
        }

        return false;
    }

    /**
     * Prüft, ob ein IMAP/SMTP-Host sicher ist (verhindert SSRF auf Loopback, Metadaten-Endpunkte oder interne Dienste).
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

        if (in_array($cleanHost, $forbiddenHosts, true) || str_ends_with($cleanHost, '.internal') || str_ends_with($cleanHost, '.local') || str_ends_with($cleanHost, '.docker')) {
            return false;
        }

        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            // Blockiere private und reservierte IP-Bereiche (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, 169.254.0.0/16)
            if (!filter_var($cleanHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
            return true;
        }

        // Öffentliche Mail-Hosts müssen immer eine Domain mit mindestens einem Punkt sein (blockiert interne Single-Label Container-Namen)
        if (!str_contains($cleanHost, '.')) {
            return false;
        }

        return true;
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
