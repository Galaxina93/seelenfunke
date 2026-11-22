<?php

namespace App\Services\AI;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FunkiraPresenceContext
{
    public const ENV_LOCAL = 'LOCAL';
    public const ENV_TESTING = 'TESTING';
    public const ENV_STAGE = 'STAGE';
    public const ENV_LIVE = 'LIVE';

    public const HEARTBEAT_ANTIGRAVITY_KEY = 'funkira:heartbeat:antigravity_notebook';
    public const HEARTBEAT_TRESOR_KEY = 'funkira:heartbeat:funkenwerk_tresor';

    /**
     * Ermittelt deterministisch die aktive Systemumgebung (LOCAL, TESTING, STAGE, LIVE).
     */
    public static function resolveEnvironmentTier(?string $explicitOverride = null, ?Request $request = null): string
    {
        if ($explicitOverride !== null && in_array(strtoupper($explicitOverride), [
            self::ENV_LOCAL,
            self::ENV_TESTING,
            self::ENV_STAGE,
            self::ENV_LIVE,
        ], true)) {
            return strtoupper($explicitOverride);
        }

        $appEnv = strtolower((string) config('app.env', 'local'));
        if ($appEnv === 'testing' || (app()->runningUnitTests() && $explicitOverride === null)) {
            return self::ENV_TESTING;
        }

        $req = $request ?? (app()->bound('request') ? request() : null);
        $appUrlLower = strtolower((string) config('app.url', ''));
        $host = strtolower((string) ($req?->getHost() ?: parse_url($appUrlLower, PHP_URL_HOST) ?: '127.0.0.1'));

        if ($appEnv === 'staging' || str_starts_with($host, 'stage.') || str_contains($appUrlLower, 'stage.')) {
            return self::ENV_STAGE;
        }

        if ($appEnv === 'production' && in_array($host, ['mein-seelenfunke.de', 'www.mein-seelenfunke.de'], true)) {
            return self::ENV_LIVE;
        }

        if ($appEnv === 'production') {
            return self::ENV_LIVE;
        }

        return self::ENV_LOCAL;
    }

    public static function markAntigravityNotebookOnline(int $ttlSeconds = 600): void
    {
        Cache::put(self::HEARTBEAT_ANTIGRAVITY_KEY, now()->toIso8601String(), $ttlSeconds);
    }

    public static function isAntigravityNotebookOnline(): bool
    {
        if (Cache::has(self::HEARTBEAT_ANTIGRAVITY_KEY)) {
            return true;
        }

        // Auf lokaler Entwicklungsumgebung läuft das System direkt auf Alinas Notebook
        return self::resolveEnvironmentTier() === self::ENV_LOCAL;
    }

    public static function markTresorWorkerOnline(int $ttlSeconds = 300): void
    {
        Cache::put(self::HEARTBEAT_TRESOR_KEY, now()->toIso8601String(), $ttlSeconds);
    }

    public static function isTresorWorkerOnline(): bool
    {
        return Cache::has(self::HEARTBEAT_TRESOR_KEY);
    }

    /**
     * Baut den verifizierten Orts-, Sensor- und Umgebungs-Kontext auf.
     *
     * @param array<string, mixed> $rawMeta
     * @return array<string, mixed>
     */
    public static function build(array $rawMeta = [], ?Request $request = null): array
    {
        $allowedChannels = [
            'web_portal',
            'live_widget',
            'android_app',
            'android_live_call',
            'funkenwerk_desktop',
            'workshop_minipc',
            'system_sentinel',
            'customer_escalation',
        ];

        $channel = strtolower(trim((string) ($rawMeta['channel'] ?? 'web_portal')));
        if (!in_array($channel, $allowedChannels, true)) {
            $channel = 'web_portal';
        }

        if ($channel === 'funkenwerk_desktop') {
            self::markTresorWorkerOnline();
        }

        $envTier = self::resolveEnvironmentTier($rawMeta['environment_override'] ?? null, $request);
        $activeRoute = trim((string) ($rawMeta['active_route'] ?? ($request?->path() ? '/' . ltrim($request->path(), '/') : '')));

        // Kamera- & Stimm-Sensorik auf dem Werkstatt Mini-PC (YuNet + SFace 0-Token lokal)
        $cameraAlinaVerified = array_key_exists('camera_alina_verified', $rawMeta)
            ? (bool) $rawMeta['camera_alina_verified']
            : ($channel !== 'workshop_minipc');
        $voiceAlinaVerified = array_key_exists('voice_alina_verified', $rawMeta)
            ? (bool) $rawMeta['voice_alina_verified']
            : ($channel !== 'workshop_minipc');

        $detectedPersons = [];
        if (!empty($rawMeta['detected_persons']) && is_array($rawMeta['detected_persons'])) {
            foreach (array_slice($rawMeta['detected_persons'], 0, 6) as $person) {
                if (is_string($person) && trim($person) !== '') {
                    $detectedPersons[] = mb_substr(trim($person), 0, 60);
                }
            }
        }

        // Zero-PII Besucher-Sperre am Mini-PC, sobald Alina weder per Kamera noch per Stimme verifiziert ist
        $visitorSandboxLocked = ($channel === 'workshop_minipc') && !$cameraAlinaVerified && !$voiceAlinaVerified;

        return [
            'channel' => $channel,
            'channel_label' => match ($channel) {
                'web_portal' => 'Web-Portal (Admin)',
                'live_widget' => 'Live-AI Seiten-Widget',
                'android_app' => 'Funkenwerk Android-App (Chat)',
                'android_live_call' => 'Funkenwerk Android-App (3D Live-Call)',
                'funkenwerk_desktop' => 'Funkenwerk Windows-Suite (Haupt-PC)',
                'workshop_minipc' => 'Werkstatt Mini-PC am UV-Laser',
                'system_sentinel' => 'System-Wächter',
                'customer_escalation' => 'Shop-Eskalation',
                default => $channel,
            },
            'environment_tier' => $envTier,
            'active_route' => $activeRoute !== '' ? mb_substr($activeRoute, 0, 180) : null,
            'camera_alina_verified' => $cameraAlinaVerified,
            'voice_alina_verified' => $voiceAlinaVerified,
            'detected_persons' => $detectedPersons,
            'visitor_sandbox_locked' => $visitorSandboxLocked,
            'notebook_antigravity_online' => self::isAntigravityNotebookOnline(),
            'tresor_worker_online' => self::isTresorWorkerOnline(),
            'is_voice_mode' => in_array($channel, ['android_live_call', 'workshop_minipc'], true) || !empty($rawMeta['is_voice_mode']),
        ];
    }

    /**
     * Erzeugt den ultrakompakten (~35 Tokens) System-Header für Funkira & Abteilungs-Agenten.
     *
     * @param array<string, mixed> $presence
     */
    public static function toPromptHeader(array $presence): string
    {
        $env = $presence['environment_tier'] ?? self::ENV_LOCAL;
        $channelLabel = $presence['channel_label'] ?? 'Web-Portal';
        $routeInfo = !empty($presence['active_route']) ? " | Aktive Seite: {$presence['active_route']}" : '';
        $agInfo = !empty($presence['notebook_antigravity_online']) ? 'Notebook/Antigravity: ONLINE' : 'Notebook/Antigravity: OFFLINE (Queue aktiv)';
        $tresorInfo = !empty($presence['tresor_worker_online']) ? 'Tresor-Browser: BEREIT' : 'Tresor-Browser: WARTESCHLANGE';

        $sensorInfo = '';
        if (($presence['channel'] ?? '') === 'workshop_minipc') {
            if (!empty($presence['visitor_sandbox_locked'])) {
                $guests = !empty($presence['detected_persons']) ? implode(', ', $presence['detected_persons']) : 'Unbekannte Person';
                $sensorInfo = " | KAMERA-SPERRE AKTIV (Alina nicht im Bild, erkannt: {$guests}): ZERO-PII BESUCHER-MODUS! Gib keinerlei Geschäfts-, Kunden-, Finanz- oder Ticketdaten preis!";
            } else {
                $sensorInfo = ' | Kamera & Stimme: Alina verifiziert';
            }
        }

        $envNote = match ($env) {
            self::ENV_LIVE => 'Produktivbetrieb (echte Daten)',
            self::ENV_STAGE => 'Stage-Prüfserver (Vorab-Umgebung, keine echten Live-Kundenaufträge)',
            self::ENV_TESTING => 'Automatisierte Testumgebung (Sandbox)',
            default => 'Lokale Entwicklungsumgebung (127.0.0.1, keine unbestätigten Echtgeld-Käufe)',
        };

        return "[AKTIVER KONTEXT: Ort={$channelLabel}{$routeInfo} | Umgebung={$env} ({$envNote}) | {$agInfo} | {$tresorInfo}{$sensorInfo}]";
    }
}
