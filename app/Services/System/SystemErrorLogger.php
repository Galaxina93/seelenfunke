<?php

namespace App\Services\System;

use App\Models\System\SystemLog;
use Illuminate\Support\Str;

class SystemErrorLogger
{
    /**
     * Verhindert Endlosschleifen, falls während des Schreibens in system_logs selbst ein Log-Event ausgelöst wird.
     */
    protected static bool $isRecording = false;
    protected static bool $fatalHandlerRegistered = false;

    public const DEAD_LETTER_SPOOL_FILE = 'app/private/sentinel_deadletter.jsonl';

    /**
     * Speichert bereits protokollierte Exception-Objekt-IDs innerhalb desselben Request-Zyklus,
     * um doppelte Einträge durch ExceptionHandler + Log::error Listener zu verhindern.
     *
     * @var array<int, bool>
     */
    protected static array $loggedExceptionIds = [];

    /**
     * Speichert detaillierte Fehlermeldungen von Artisan-Befehlen innerhalb desselben CLI-Prozesses,
     * damit der CommandFinished-Listener sofort die exakte Ursache protokolliert.
     *
     * @var array<string, array<int, string>>
     */
    protected static array $commandFailureDetails = [];

    /**
     * Gecachte Liste aller echten Projekt-Tabellennamen aus database/migrations/*.php.
     *
     * @var array<string, bool>|null
     */
    protected static ?array $knownMigrationTables = null;

    /**
     * Hinterlegt ein Diagnose-Detail für einen laufenden Artisan-Befehl (z. B. crm:fetch-mails),
     * damit recordCommandFailure() bei Exit != 0 den exakten Fehlergrund mitschreibt.
     */
    public static function rememberCommandFailureDetail(string $command, string $detail): void
    {
        $key = strtolower(trim(explode(' ', trim($command))[0]));
        $clean = trim(self::sanitizeSensitiveText($detail));
        if ($key === '' || $clean === '') {
            return;
        }
        self::$commandFailureDetails[$key] ??= [];
        if (count(self::$commandFailureDetails[$key]) < 15) {
            self::$commandFailureDetails[$key][] = $clean;
        }
    }

    /**
     * Liefert die gesammelten Diagnose-Details eines Artisan-Befehls als kompakten String.
     */
    public static function getCommandFailureDetail(string $command): string
    {
        $key = strtolower(trim(explode(' ', trim($command))[0]));
        if ($key === '' || empty(self::$commandFailureDetails[$key])) {
            return '';
        }

        return implode(' | ', array_unique(self::$commandFailureDetails[$key]));
    }

    /**
     * Analysiert eine Fehlermeldung / Exception und liefert eine strukturierte deutsche Ursachen-Diagnose.
     */
    public static function diagnose(string $message, ?string $exceptionClass = null, ?string $file = null): array
    {
        $msgLower = strtolower($message);
        $classLower = strtolower((string) $exceptionClass);
        $fileLower = strtolower((string) $file);

        if (
            str_contains($fileLower, 'standard input code')
            || str_contains($fileLower, 'command line code')
            || str_contains($msgLower, 'at standard input code:')
            || str_contains($msgLower, 'in command line code')
        ) {
            return [
                'category' => 'cli_stdin_eval_error',
                'probable_cause' => 'Einmaliger manueller CLI-/Tinker-Testaufruf über Standard-Input (php -r / stdin), kein regulärer Laufzeitfehler im Anwendungscode.',
                'recommended_action' => 'Kein Eingriff im Produktivcode erforderlich; Eintrag kann als manueller CLI-Testlauf archiviert werden.',
            ];
        }

        if (str_contains($msgLower, 'allowed memory size')
            || str_contains($msgLower, 'maximum execution time')
            || str_contains($msgLower, 'out of memory')
            || str_contains($classLower, 'fatalerror')
            || str_contains($classLower, 'parseerror')) {
            return [
                'category' => 'php_fatal_or_oom',
                'probable_cause' => 'Harter PHP-Prozessabbruch (Speicherlimit erschöpft, Zeitüberschreitung oder Fatal/Parse-Error).',
                'recommended_action' => 'Prüfe die exakte Datei und Zeilennummer im Antigravity-Dossier (z. B. unpaginierte Abfrage, Endlosschleife oder Syntax-/Typfehler).',
            ];
        }

        if (str_contains($msgLower, 'auto-firewall')
            || str_contains($msgLower, 'scanner-ip')
            || str_contains($msgLower, 'exploit_path_ban')
            || str_contains($msgLower, 'auto_ip_ban')) {
            return [
                'category' => 'security_auto_firewall_defense',
                'probable_cause' => 'Erfolgreiche automatische Firewall-Abwehr: Ein externer Bot/Exploit-Scanner hat einen verbotenen Pfad (Honeypot) aufgerufen und wurde automatisch für 24 Stunden gesperrt.',
                'recommended_action' => 'Kein Anwendungsfehler. Der Angriff wurde bereits abgewehrt; gesperrte IPs sind unter /admin/settings (Firewall) einsehbar.',
            ];
        }

        if (str_contains($msgLower, 'system-schranke')
            || str_contains($msgLower, 'sicherheitsprüfung: verwundbarkeiten')
            || str_contains($msgLower, 'package_audit')) {
            return [
                'category' => 'security_package_or_guardrail_audit',
                'probable_cause' => 'Der tägliche Sicherheits- & Architektur-Audit (system:security-audit) hat eine verletzte System-Schranke oder ein Paket-Advisory gemeldet.',
                'recommended_action' => 'Prüfe die Sektion guardrails / failed_guardrails unter Weitere Details (z. B. unbekannte Datei in public/ oder Composer/NPM Advisory).',
            ];
        }

        if (str_contains($msgLower, 'address already in use')
            && (str_contains($msgLower, '6001') || str_contains($msgLower, 'reverb') || str_contains((string) $file, 'Reverb'))) {
            return [
                'category' => 'reverb_port_already_bound',
                'probable_cause' => 'Ein zweiter Reverb-Startversuch traf auf den bereits belegten WebSocket-Port 6001 (der primäre Reverb-Server läuft bereits).',
                'recommended_action' => 'Unkritisch, solange der primäre Reverb-Daemon aktiv ist. Bei Port-Hängern: bestehenden Prozess auf Port 6001 sauber neu starten.',
            ];
        }

        if (str_contains($msgLower, 'infrastruktur-ausfall: backup')) {
            return [
                'category' => 'backup_overdue_warning',
                'probable_cause' => 'Das letzte Datenbank-Backup im überwachten Backup-Verzeichnis ist älter als 48 Stunden (in Lokal-Umgebungen ohne aktiven Backup-Cron normal).',
                'recommended_action' => 'Auf Stage/Live Backup-Cronjob (backup:run) unter /admin/backups prüfen; lokal ist kein täglicher Backup-Zwang erforderlich.',
            ];
        }

        if (str_contains($msgLower, 'uncaught referenceerror:')
            || str_contains($msgLower, 'frontend js error')) {
            return [
                'category' => 'frontend_alpine_or_js_reference',
                'probable_cause' => 'Ein Alpine.js- oder Browser-Skript referenzierte eine Variable außerhalb ihres gültigen x-data-Scopes (häufig beim Livewire wire:navigate Seitenwechsel).',
                'recommended_action' => 'Prüfe die Unterseite, den Alpine-Ausdruck und die Zeilennummer im Payload und stelle sicher, dass x-data-Properties sauber initialisiert sind.',
            ];
        }

        if (str_contains($msgLower, 'influencer')
            || str_contains($msgLower, 'creator-cockpit')
            || str_contains($msgLower, 'pr-sample')
            || str_contains((string) $file, 'Influencer')) {
            return [
                'category' => 'influencer_workflow_error',
                'probable_cause' => 'Fehler im Influencer- & Creator-Workflow (Onboarding, PR-Sample-Gutschein, Tracking-Link oder Provisions-/Gutschrifts-Abrechnung).',
                'recommended_action' => 'Prüfe die Unterseite, die Livewire-Methode, die Influencer-ID (@handle) und die Eingabefelder unter Weitere Details.',
            ];
        }

        if (str_contains($msgLower, 'bridge_unhealthy')
            || str_contains($msgLower, 'twilio-health')
            || str_contains($msgLower, 'api-live-bridge')) {
            return [
                'category' => 'bridge_health_unreachable',
                'probable_cause' => 'Eine der Echtzeit-WebSocket-Brücken (Reverb-Worker a-iurgq8 oder Node.js-Bridge a-yeq7el) antwortete nicht rechtzeitig auf den Gesundheits-Puls.',
                'recommended_action' => 'Prüfe Container-Status via mittnitectl job status bzw. führe FunkenwerkMcp.exe --stage-restart-workers aus.',
            ];
        }

        if (str_contains($classLower, 'blueprintsecurityexception') || str_contains($msgLower, 'architektur-blueprint')) {
            return [
                'category' => 'blueprint_guardrail_or_compliance',
                'probable_cause' => 'Blockierter KI-Zugriff auf ein GoBD-/Sicherheits-geschütztes Model oder Verstoß gegen das Architektur-Typenschild (ModelBlueprint).',
                'recommended_action' => 'Prüfe die AiAccessPolicy des betroffenen Models im Blueprint sowie den auslösenden KI-Tool-Aufruf im Trace.',
            ];
        }

        if (str_contains($msgLower, 'is deprecated') || str_contains($msgLower, 'deprecated:')) {
            return [
                'category' => 'php_deprecation_notice',
                'probable_cause' => 'PHP 8.4+ Typ- oder Funktions-Deprecation (z. B. Übergabe von null an eine String-Funktion).',
                'recommended_action' => 'Prüfe Datei, Zeile und trace_excerpt im Payload und sichere optionale Werte per Null-Coalescing ((string) ($val ?? \'\')) ab.',
            ];
        }

        if (str_contains($msgLower, 'getaddrinfo') || str_contains($msgLower, 'redis') || str_contains($classLower, 'predis')) {
            return [
                'category' => 'redis_connectivity_or_dns',
                'probable_cause' => 'Redis-Host ist temporär nicht auflösbar (DNS) oder der Redis-Dienst antwortet nicht.',
                'recommended_action' => 'Der automatische Fallback in AppServiceProvider schützt Web-Requests via File-Cache. Prüfe bei dauerhaftem Auftreten den Redis-Host in der .env.',
            ];
        }

        if (str_contains($classLower, 'pdoexception') || str_contains($classLower, 'queryexception') || str_contains($msgLower, 'sqlstate')) {
            return [
                'category' => 'database_query_or_connection',
                'probable_cause' => 'Datenbank-Abfragefehler (fehlende Spalte/Tabelle, Constraint-Verletzung) oder Verbindungsabbruch zu MySQL.',
                'recommended_action' => 'Prüfe SQL-Fehlermeldung, ausstehende Migrationen (php artisan migrate) und Datenbank-Zugangsdaten.',
            ];
        }

        if (str_contains($msgLower, 'resource temporarily unavailable')
            || str_contains($msgLower, 'eai_again')
            || str_contains($msgLower, 'tryagain')
            || str_contains($msgLower, '[11]')) {
            return [
                'category' => 'network_dns_or_socket_eagain',
                'probable_cause' => 'Temporärer DNS- oder Socket-Engpass (errno 11 / EAI_AGAIN: Resource temporarily unavailable), typischerweise direkt nach dem Aufwachen aus dem Energiesparmodus (WLAN/DNS-Warmup) oder bei verzögertem IPv6-Stub-Resolver.',
                'recommended_action' => 'Automatischer IPv4-first DNS-Retry und lokaler Vault-Resilienz-Fallback fangen dies ab. Bei Dauerauftreten Router-DNS (Fritz!Box IPv4/IPv6) und Server-Prozesslimits prüfen.',
            ];
        }

        if (str_contains($msgLower, '4003')
            || str_contains($msgLower, 'verify failed')
            || str_contains($msgLower, 'bridge_verify')) {
            return [
                'category' => 'gemini_bridge_token_verification_failed',
                'probable_cause' => 'Token-Verifizierung zwischen der Node.js Live-Bridge (api-live-bridge) und /api/ai/verify-token fehlgeschlagen (Code 4003, z. B. fehlendes/abweichendes AI_PROXY_SECRET oder abgelaufenes Einmal-Token).',
                'recommended_action' => 'Prüfe AI_PROXY_SECRET in Laravel (.env + config/services.php) und im Node.js-Worker sowie den automatischen Direct-Gemini-Fallback im Payload.',
            ];
        }

        if (str_contains($msgLower, 'phone_concierge')
            || str_contains($msgLower, 'phone_secretary')
            || str_contains($msgLower, 'telecommanager')) {
            return [
                'category' => 'funkira_phone_secretary_error',
                'probable_cause' => 'Verbindungs-, Audio- oder Berechtigungsereignis im Funkira Telefon-Sekretärinnen-Dienst (Smartphone Standalone oder Mini-PC HFP).',
                'recommended_action' => 'Prüfe Telefon-/Mikrofon-Freigaben in der Funkenwerk Android App sowie den Anruf-Kontext (audio_mode, ws_mode, stage) im Payload.',
            ];
        }

        if (str_contains($msgLower, 'generativelanguage') || str_contains($msgLower, 'gemini') || str_contains($msgLower, '1008') || str_contains($msgLower, '1007')) {
            return [
                'category' => 'gemini_ai_or_live_bridge',
                'probable_cause' => 'Fehler bei der Kommunikation mit der Google Gemini API (ungültiges Modell, Protokollfehler, Timeout oder Quota).',
                'recommended_action' => 'Prüfe Modell-Konfiguration (models/gemini-3.1-flash-live-preview), API-Key und den Payload-Trace.',
            ];
        }

        if (str_contains($msgLower, 'smtp') || str_contains($msgLower, 'mailer') || str_contains($msgLower, 'imap') || str_contains($msgLower, 'mail')) {
            return [
                'category' => 'mail_smtp_or_imap',
                'probable_cause' => 'Verbindungs-, Zertifikats- oder Authentifizierungsfehler beim E-Mail-Versand (SMTP) oder -Abruf (IMAP).',
                'recommended_action' => 'Prüfe MAIL_* bzw. IMAP-Zugangsdaten und Server-Erreichbarkeit.',
            ];
        }

        if (str_contains($classLower, 'validationexception')) {
            return [
                'category' => 'validation_failed',
                'probable_cause' => 'Ungültige oder fehlende Eingabedaten bei einem Formular- oder API-Request.',
                'recommended_action' => 'Prüfe die übergebenen Request-Parameter im Payload.',
            ];
        }

        if (str_contains($classLower, 'authenticationexception')
            || str_contains($classLower, 'authorizationexception')
            || str_contains($msgLower, 'unauthenticated')
            || str_contains($msgLower, 'sitzung abgelaufen')
            || str_contains($msgLower, 'status 401')
            || str_contains($msgLower, 'http 401')) {
            return [
                'category' => 'auth_or_permission',
                'probable_cause' => 'Nicht authentifizierter Zugriff oder abgelaufene Portal-Anmeldesitzung (HTTP 401).',
                'recommended_action' => 'Normal bei abgelaufenen Nutzer- oder Endgeräte-Sessions; einmalig neu anmelden bzw. Token-Übergabe prüfen.',
            ];
        }

        if (str_contains($msgLower, 'cronjob') || str_contains($msgLower, 'scheduler') || str_contains($msgLower, 'exit-code') || str_contains((string) $file, 'Console/Commands')) {
            return [
                'category' => 'scheduler_or_cronjob_error',
                'probable_cause' => 'Ein geplanter Server-Cronjob oder Konsolenbefehl ist mit einem Fehler abgebrochen oder hat einen Fehler-Exit-Code zurückgegeben.',
                'recommended_action' => 'Prüfe die Konsolenausgabe im Payload sowie den Status unter /admin/ai/workspace -> Server Cronjobs.',
            ];
        }

        if (str_contains($msgLower, '17701') || str_contains($msgLower, 'vosk') || str_contains($msgLower, 'yunet') || str_contains($msgLower, 'sface') || str_contains($msgLower, 'webcam') || str_contains($msgLower, 'pipewire')) {
            return [
                'category' => 'minipc_sense_or_hardware',
                'probable_cause' => 'Lokaler Mini-PC Sense-Dienst (Wake-Word / Gesichtserkennung) oder USB-Kamera/Mikrofon antwortete kurzzeitig nicht.',
                'recommended_action' => 'Prüfe, ob der Watchdog den Dienst automatisch neu gestartet hat (auto_healed), sowie den USB-Sitz der Logitech C920.',
            ];
        }

        if (str_contains($msgLower, 'nas') || str_contains($msgLower, 'cifs') || str_contains($msgLower, 'smb')) {
            return [
                'category' => 'minipc_nas_backup',
                'probable_cause' => 'Synology-NAS im Heimnetz temporär im Standby/nicht erreichbar oder SMB-Anmeldung fehlgeschlagen.',
                'recommended_action' => 'Der Mini-PC merkt sich das Nachhol-Flag (Catch-Up) automatisch für den nächsten Lauf. Bei Dauerfehler NAS-Erreichbarkeit prüfen.',
            ];
        }

        if (str_contains($msgLower, 'okhttp')
            || str_contains($msgLower, 'retrofit')
            || str_contains($msgLower, 'sockettimeout')
            || str_contains($msgLower, 'httpexception')
            || str_contains($classLower, 'httpexception')
            || str_contains($classLower, 'unknownhostexception')) {
            return [
                'category' => 'client_network_or_api',
                'probable_cause' => 'Temporärer Mobilfunk-/WLAN-Abbruch, Server-Restart oder API-Timeout auf dem Endgerät (Android / Desktop).',
                'recommended_action' => 'Kein Datenverlust bei aktivem Offline-Puffer; bei gehäuftem Auftreten Server-Antwortzeiten und API-Status prüfen.',
            ];
        }

        if (str_contains($msgLower, 'livewire') || str_contains((string) $file, 'Livewire')) {
            return [
                'category' => 'livewire_component_error',
                'probable_cause' => 'Laufzeitfehler innerhalb einer Livewire-Komponente oder beim State-Hydrating.',
                'recommended_action' => 'Prüfe Datei, Zeile und Stack-Trace der betroffenen Livewire-Komponente im Payload.',
            ];
        }

        return [
            'category' => 'application_runtime_error',
            'probable_cause' => 'Unerwarteter Laufzeitfehler in der Anwendung (' . ($exceptionClass ? class_basename($exceptionClass) : 'Error') . ').',
            'recommended_action' => 'Prüfe Datei, Zeilennummer und den Stack-Trace (trace_excerpt) im Payload.',
        ];
    }

    /**
     * Maskiert sensible Query-Parameter, API-Keys, Tokens und Passwörter in Log-Texten und URLs.
     */
    public static function sanitizeSensitiveText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $clean = (string) preg_replace(
            '/\bBearer\s+[A-Za-z0-9\-._~+|\/]+=*/i',
            'Bearer [REDACTED]',
            $text
        );

        $clean = (string) preg_replace(
            '/\bAIza[A-Za-z0-9\-_]{16,}\b/',
            '[REDACTED_GEMINI_KEY]',
            $clean
        );

        $clean = (string) preg_replace(
            '/\bya29\.[A-Za-z0-9\-._]{16,}\b/',
            '[REDACTED_VERTEX_TOKEN]',
            $clean
        );

        return (string) preg_replace(
            '/((?:api[_-]?key|key|token|access[_-]?token|refresh[_-]?token|password|passwd|secret|signature|pin|authorization)["\']?\s*[=:]\s*["\']?)([^\s&"\'\r\n},;]+)/i',
            '$1[REDACTED]',
            $clean
        );
    }

    /**
     * Bereinigt verschachtelte Arrays rekursiv von Geheimnissen und begrenzt String-Längen.
     */
    public static function sanitizeArrayRecursive(array $input, int $depth = 0): array
    {
        if ($depth > 5) {
            return ['_truncated' => true];
        }

        $out = [];
        $sensitiveKeyPattern = '/^(?:password|passwd|secret|token|access_token|refresh_token|api_key|gemini_api_key|gemini_key|direct_ws_url|authorization|cookie|pin|two_factor_code)$/i';

        foreach (array_slice($input, 0, 40, true) as $k => $v) {
            $keyStr = (string) $k;
            if (preg_match($sensitiveKeyPattern, $keyStr)) {
                $out[$keyStr] = '[REDACTED]';
                continue;
            }

            if (is_string($v)) {
                $out[$keyStr] = Str::limit(self::sanitizeSensitiveText($v), 2500);
            } elseif (is_array($v)) {
                $out[$keyStr] = self::sanitizeArrayRecursive($v, $depth + 1);
            } elseif (is_bool($v) || is_int($v) || is_float($v) || is_null($v)) {
                $out[$keyStr] = $v;
            } else {
                $out[$keyStr] = Str::limit(self::sanitizeSensitiveText((string) json_encode($v)), 1000);
            }
        }

        return $out;
    }

    /**
     * Erstellt ein detailliertes Diagnose-Payload-Array aus einer Throwable-Instanz.
     */
    public static function buildExceptionPayload(\Throwable $e, array $extra = []): array
    {
        $traceExcerpt = array_map(function (array $frame) {
            return ($frame['file'] ?? '[internal]') . ':' . ($frame['line'] ?? '?')
                . ' -> ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
        }, array_slice($e->getTrace(), 0, 10));

        $previous = $e->getPrevious();
        $previousData = $previous ? [
            'exception_class' => get_class($previous),
            'message' => Str::limit(self::sanitizeSensitiveText($previous->getMessage()), 500),
            'file' => $previous->getFile(),
            'line' => $previous->getLine(),
        ] : null;

        return array_merge([
            'exception_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'diagnosis' => self::diagnose($e->getMessage(), get_class($e), $e->getFile()),
            'previous_exception' => $previousData,
            'execution_context' => self::buildExecutionContext(),
            'server_environment' => self::buildServerEnvironment(),
            'trace_excerpt' => $traceExcerpt,
        ], $extra);
    }

    /**
     * Erstellt Informationen über den aktuellen HTTP-Request oder CLI-Befehl inklusive exakter Unterseite und Livewire-Kontext.
     */
    public static function buildExecutionContext(): array
    {
        if (app()->runningInConsole()) {
            $rawCmd = self::sanitizeSensitiveText(implode(' ', $_SERVER['argv'] ?? []));
            $shortCmd = trim((string) preg_replace('/^.*?\bartisan\s+/i', '', $rawCmd));
            return [
                'mode' => 'cli',
                'command' => $rawCmd,
                'subpage_path' => $shortCmd !== '' ? ('CLI: ' . $shortCmd) : 'CLI / Artisan',
            ];
        }

        $req = request();
        $user = null;
        try {
            foreach (['admin', 'customer', 'employee', 'web'] as $guard) {
                if (auth()->guard($guard)->check()) {
                    $u = auth()->guard($guard)->user();
                    $user = [
                        'guard' => $guard,
                        'id' => $u?->getKey(),
                        'email' => $u?->email ?? null,
                    ];
                    break;
                }
            }
        } catch (\Throwable $ignore) {
        }

        $rawPath = '/' . ltrim((string) ($req?->path() ?? ''), '/');
        $referer = self::sanitizeSensitiveText((string) ($req?->headers?->get('Referer') ?? ''));
        $subpagePath = $rawPath;
        $livewireComponent = null;
        $livewireMethod = null;

        try {
            $isLivewireRequest = str_contains($rawPath, 'livewire/update') || str_contains($rawPath, 'livewire/message');
            if ($isLivewireRequest && $req) {
                $components = $req->input('components');
                if (is_array($components) && !empty($components[0])) {
                    $firstComp = $components[0];
                    $snapshotRaw = $firstComp['snapshot'] ?? null;
                    if (is_string($snapshotRaw)) {
                        $snapshot = json_decode($snapshotRaw, true);
                        if (is_array($snapshot)) {
                            $memoPath = trim((string) ($snapshot['memo']['path'] ?? ''));
                            $memoName = trim((string) ($snapshot['memo']['name'] ?? ''));
                            if ($memoPath !== '') {
                                $subpagePath = '/' . ltrim($memoPath, '/');
                            }
                            if ($memoName !== '') {
                                $livewireComponent = $memoName;
                            }
                        }
                    }
                    $calls = $firstComp['calls'] ?? null;
                    if (is_array($calls) && !empty($calls[0]['method'])) {
                        $livewireMethod = (string) $calls[0]['method'];
                    }
                }

                if (($subpagePath === $rawPath || $subpagePath === '/livewire/update') && $referer !== '') {
                    $parsedRefPath = parse_url($referer, PHP_URL_PATH);
                    if (is_string($parsedRefPath) && $parsedRefPath !== '') {
                        $subpagePath = '/' . ltrim($parsedRefPath, '/');
                    }
                }
            }
        } catch (\Throwable $ignore) {
        }

        return [
            'mode' => 'http',
            'method' => $req?->method() ?? 'GET',
            'url' => self::sanitizeSensitiveText($req?->fullUrl() ?? ''),
            'subpage_path' => $subpagePath,
            'referer' => $referer !== '' ? $referer : null,
            'route' => $req?->route()?->getName() ?? $req?->path(),
            'livewire_component' => $livewireComponent,
            'livewire_method' => $livewireMethod,
            'ip' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'authenticated_user' => $user,
            'input_keys' => $req ? array_keys($req->except(['password', 'password_confirmation', 'current_password'])) : [],
        ];
    }

    /**
     * Liefert die aktuelle Server- und Treiber-Umgebung.
     */
    public static function buildServerEnvironment(): array
    {
        $appEnv = strtolower((string) config('app.env', 'local'));
        $appUrl = strtolower((string) config('app.url', ''));
        $resolvedEnv = match (true) {
            str_contains($appUrl, 'stage.') || in_array($appEnv, ['stage', 'staging'], true) => 'stage',
            in_array($appEnv, ['production', 'live', 'prod'], true) => 'live',
            default => $appEnv ?: 'local',
        };

        return [
            'php_version' => PHP_VERSION,
            'app_env' => config('app.env'),
            'resolved_env' => $resolvedEnv,
            'app_url' => config('app.url'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_connection' => config('queue.default'),
        ];
    }

    /**
     * Extrahiert für Log::warning / Log::error Aufrufe ohne Throwable den echten Anwendungs-Aufrufer aus dem Call-Stack.
     */
    protected static function extractCallerTraceExcerpt(): array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 22);
        $skipPatterns = [
            'SystemErrorLogger.php',
            'AppServiceProvider.php',
            '/Illuminate/Log/',
            '\\Illuminate\\Log\\',
            '/Monolog/',
            '\\Monolog\\',
            '/Illuminate/Events/',
            '\\Illuminate\\Events\\',
            '/Illuminate/Foundation/Bootstrap/HandleExceptions',
            '\\Illuminate\\Foundation\\Bootstrap\\HandleExceptions',
            '/Illuminate/Support/Facades/Facade',
            '\\Illuminate\\Support\\Facades\\Facade',
        ];

        $filtered = [];
        $callerFile = null;
        $callerLine = null;

        foreach ($frames as $frame) {
            $file = (string) ($frame['file'] ?? '');
            $class = (string) ($frame['class'] ?? '');
            $haystack = $file . ' ' . $class;

            $skip = false;
            foreach ($skipPatterns as $pattern) {
                if (str_contains($haystack, $pattern)) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            if ($callerFile === null && $file !== '' && !str_contains($file, '/vendor/')) {
                $callerFile = $file;
                $callerLine = isset($frame['line']) ? (int) $frame['line'] : null;
            }

            $filtered[] = ($file !== '' ? $file : '[internal]') . ':' . ($frame['line'] ?? '?')
                . ' -> ' . $class . ($frame['type'] ?? '') . ($frame['function'] ?? '');

            if (count($filtered) >= 10) {
                break;
            }
        }

        if ($callerFile === null && !empty($filtered)) {
            foreach ($frames as $frame) {
                if (!empty($frame['file'])) {
                    $callerFile = (string) $frame['file'];
                    $callerLine = isset($frame['line']) ? (int) $frame['line'] : null;
                    break;
                }
            }
        }

        return [
            'file' => $callerFile,
            'line' => $callerLine,
            'trace_excerpt' => $filtered,
        ];
    }

    /**
     * Prüft, ob es sich um einen harmlosen UI-/Coroutine-Lebenszyklus-Abbruch eines Clients handelt
     * (z. B. Jetpack Compose LeftCompositionCancellationException beim Bildschirmwechsel).
     */
    public static function isBenignClientCancellation(string $message, ?string $errorClass = null, ?string $title = null): bool
    {
        $combined = strtolower($message . ' | ' . ($errorClass ?? '') . ' | ' . ($title ?? ''));
        $benignMarkers = [
            'leftcompositioncancellationexception',
            'mutationinterruptedexception',
            'childcancelledexception',
            'jobcancellationexception',
            'kotlin.coroutines.cancellation.cancellationexception',
            'kotlinx.coroutines.cancellationexception',
            'java.util.concurrent.cancellationexception',
            'standalonecoroutine was cancelled',
            'job was cancelled',
            'composition was disposed',
        ];

        foreach ($benignMarkers as $marker) {
            if (str_contains($combined, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sucht einen identischen offenen SystemLog-Eintrag der letzten 24h anhand des Fingerprints und erhöht occurrences_count.
     */
    protected static function incrementRecentDuplicate(
        string $type,
        string $actionId,
        array $statuses,
        string $fingerprint,
        int $addCount = 1
    ): ?SystemLog {
        try {
            $candidates = SystemLog::where('type', $type)
                ->where('action_id', $actionId)
                ->whereIn('status', $statuses)
                ->where('started_at', '>=', now()->subHours(24))
                ->latest('id')
                ->limit(15)
                ->get();

            foreach ($candidates as $candidate) {
                $p = is_array($candidate->payload) ? $candidate->payload : [];
                if (($p['fingerprint'] ?? null) === $fingerprint) {
                    $p['occurrences_count'] = ((int) ($p['occurrences_count'] ?? 1)) + max(1, $addCount);
                    $p['last_seen_at'] = now()->toIso8601String();
                    $candidate->payload = $p;
                    $candidate->finished_at = now();
                    $candidate->save();
                    return $candidate;
                }
            }
        } catch (\Throwable $ignore) {
        }

        return null;
    }

    /**
     * Alias für isTransientLocalMigrationTableMissing zur konsistenten Aufrufbarkeit.
     */
    public static function isTransientLocalBootstrapTableMissing(\Throwable $e): bool
    {
        return self::isTransientLocalMigrationTableMissing($e);
    }

    /**
     * Prüft anhand von database/migrations/*.php (gecacht pro Prozess), ob ein Tabellenname zu einer echten
     * Seelenfunke-Migration gehört, damit echte Tippfehler-Tabellen niemals unterdrückt werden.
     */
    public static function isKnownProjectMigrationTable(string $tableName): bool
    {
        $normalized = strtolower(trim($tableName));
        if ($normalized === '') {
            return false;
        }

        if (self::$knownMigrationTables === null) {
            $tables = [
                'migrations' => true,
                'personal_access_tokens' => true,
                'jobs' => true,
                'failed_jobs' => true,
                'job_batches' => true,
                'sessions' => true,
                'cache' => true,
                'cache_locks' => true,
                'system_logs' => true,
                'system_settings' => true,
                'shop_settings' => true,
                'admins' => true,
                'admin_profiles' => true,
                'customers' => true,
                'customer_profiles' => true,
                'employees' => true,
                'employee_profiles' => true,
                'users' => true,
                'password_reset_tokens' => true,
            ];

            try {
                $migrationDir = database_path('migrations');
                foreach (glob($migrationDir . '/*.php') ?: [] as $file) {
                    $content = (string) @file_get_contents($file);
                    if ($content !== '' && preg_match_all('/Schema::create\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $content, $matches)) {
                        foreach ($matches[1] as $createdTable) {
                            $tables[strtolower($createdTable)] = true;
                        }
                    }
                }
            } catch (\Throwable $ignore) {
            }

            self::$knownMigrationTables = $tables;
        }

        return isset(self::$knownMigrationTables[$normalized]);
    }

    /**
     * Prüft, ob die lokale Datenbank gerade frisch geleert wurde oder eine Migration unvollständig läuft
     * (migrations-Tabelle fehlt oder Anzahl ausgeführter Migrationen < Anzahl der Dateien in database/migrations/*.php).
     */
    public static function isLocalDatabaseIncompleteOrMigrating(): bool
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('migrations')) {
                return true;
            }

            $appliedCount = (int) \Illuminate\Support\Facades\DB::table('migrations')->count();
            $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
            $expectedCount = count($migrationFiles);

            return $expectedCount > 0 && $appliedCount < $expectedCount;
        } catch (\Throwable $ignore) {
            return true;
        }
    }

    /**
     * Extrahiert den Tabellennamen aus einer MySQL/PDO 42S02 / 1146 Fehlermeldung
     * (z. B. "Table 'seelenfunke.customers' doesn't exist" -> "customers").
     */
    protected static function extractMissingTableNameFromSqlError(string $msgLower): ?string
    {
        if (preg_match("/table\s+[`'\"]?(?:[a-z0-9_-]+[`'\"]?\.[`'\"]?)?([a-z0-9_]+)[`'\"]?\s+doesn't\s+exist/i", $msgLower, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /**
     * Erkennt kurzzeitige SQLSTATE[42S02]-Meldungen ("Base table or view not found") auf Basis- und Projekt-Tabellen
     * während eines lokalen migrate:fresh-Laufs (ausschließlich im local-Environment aktiv, niemals auf Stage/Live!).
     * Echte Tippfehler-Tabellen (nicht in database/migrations/*.php enthalten) werden niemals unterdrückt.
     */
    public static function isTransientLocalMigrationTableMissing(\Throwable $e): bool
    {
        try {
            if (!app()->environment('local')) {
                return false;
            }
        } catch (\Throwable $ignore) {
            return false;
        }

        $coreBootstrapTables = [
            'personal_access_tokens',
            'jobs',
            'failed_jobs',
            'job_batches',
            'sessions',
            'cache',
            'cache_locks',
            'system_logs',
            'system_settings',
            'shop_settings',
            'migrations',
        ];

        $authGuardTables = [
            'admins',
            'admin_profiles',
            'customers',
            'customer_profiles',
            'employees',
            'employee_profiles',
        ];

        $cursor = $e;
        $depth = 0;
        while ($cursor !== null && $depth < 4) {
            $msgLower = strtolower($cursor->getMessage());
            $codeStr = strtolower((string) $cursor->getCode());
            $isMissingTableError = $codeStr === '42s02'
                || str_contains($msgLower, 'sqlstate[42s02]')
                || str_contains($msgLower, 'base table or view not found: 1146');

            if ($isMissingTableError) {
                $missingTable = self::extractMissingTableNameFromSqlError($msgLower);

                if ($missingTable === null) {
                    foreach ($coreBootstrapTables as $tbl) {
                        if (
                            str_contains($msgLower, ".{$tbl}'")
                            || str_contains($msgLower, "`{$tbl}`")
                            || str_contains($msgLower, "'{$tbl}'")
                        ) {
                            return true;
                        }
                    }
                } else {
                    if (in_array($missingTable, $coreBootstrapTables, true)) {
                        return true;
                    }

                    if (!self::isKnownProjectMigrationTable($missingTable)) {
                        return false;
                    }

                    if (self::isLocalDatabaseIncompleteOrMigrating()) {
                        return true;
                    }

                    if (in_array($missingTable, $authGuardTables, true)) {
                        try {
                            if (!\Illuminate\Support\Facades\Schema::hasTable($missingTable)) {
                                return true;
                            }
                        } catch (\Throwable $ignore) {
                            return true;
                        }
                    }
                }
            }

            $cursor = $cursor->getPrevious();
            $depth++;
        }

        return false;
    }

    /**
     * Erkennt kurzzeitige lokale Docker-MySQL-Kaltstarts ("SQLSTATE[HY000] [2002] Connection refused" auf Host "db"),
     * wenn mein_php_server beim Aufwachen des PCs einige Sekunden vor dem db-Container antwortet.
     * Strikt begrenzt auf app()->environment('local') (niemals auf Stage/Production aktiv!).
     */
    public static function isTransientLocalDatabaseColdBoot(\Throwable $e): bool
    {
        try {
            if (!app()->environment('local')) {
                return false;
            }
        } catch (\Throwable $ignore) {
            return false;
        }

        $chainMessages = [];
        $hasCode2002 = false;
        $hasConnectionRefused = false;

        $cursor = $e;
        $depth = 0;
        while ($cursor !== null && $depth < 4) {
            $msgLower = strtolower($cursor->getMessage());
            $codeStr = strtolower((string) $cursor->getCode());
            $chainMessages[] = $msgLower;

            if ($codeStr === '2002' || str_contains($msgLower, '[2002]') || str_contains($msgLower, 'code: 2002')) {
                $hasCode2002 = true;
            }
            if (str_contains($msgLower, 'connection refused')) {
                $hasConnectionRefused = true;
            }

            $cursor = $cursor->getPrevious();
            $depth++;
        }

        if (!$hasCode2002 || !$hasConnectionRefused) {
            return false;
        }

        $combinedMsg = implode(' | ', $chainMessages);
        if (preg_match('/host:\s*([a-z0-9._-]+)/i', $combinedMsg, $hostMatch)) {
            return strtolower($hostMatch[1]) === 'db';
        }

        $configuredHost = '';
        try {
            $configuredHost = strtolower(trim((string) config('database.connections.mysql.host', '')));
        } catch (\Throwable $ignore) {
        }

        return $configuredHost === 'db';
    }

    /**
     * Protokolliert eine unbehandelte oder abgefangene Exception sicher in system_logs.
     */
    public static function recordException(
        \Throwable $e,
        string $type = 'system',
        ?string $actionId = null,
        ?string $titlePrefix = 'System-Fehler',
        array $extraPayload = []
    ): ?SystemLog {
        if (
            self::$isRecording
            || !class_exists(SystemLog::class)
            || self::isTransientLocalMigrationTableMissing($e)
            || self::isTransientLocalDatabaseColdBoot($e)
        ) {
            return null;
        }

        $objId = spl_object_id($e);
        if (isset(self::$loggedExceptionIds[$objId])) {
            return null;
        }

        self::$isRecording = true;
        try {
            self::$loggedExceptionIds[$objId] = true;

            $payload = self::buildExceptionPayload($e, $extraPayload);
            $category = $payload['diagnosis']['category'] ?? 'exception';
            $resolvedActionId = Str::limit($actionId ?: ('system:exception:' . $category), 190, '');

            $rawMessage = $e->getMessage() ?: ('Exception in ' . basename($e->getFile()) . ':' . $e->getLine());
            $sanitizedMessage = self::sanitizeSensitiveText($rawMessage);
            $normalizedSig = preg_replace('/\b\d+\b/', '#', strtolower($sanitizedMessage));
            $fingerprint = hash('sha256', strtolower($type . '|' . $resolvedActionId . '|' . get_class($e) . '|' . $e->getFile() . '|' . $e->getLine() . '|' . mb_substr((string) $normalizedSig, 0, 160)));

            $resolvedFile = self::resolveCompiledBladeViewPath($e->getFile()) ?? $e->getFile();
            if ($resolvedFile !== $e->getFile()) {
                $payload['compiled_view_file'] = $e->getFile();
                $payload['file'] = $resolvedFile;
            }

            try {
                ZeroTokenSelfHealer::handleSystemError(
                    $sanitizedMessage,
                    get_class($e),
                    $resolvedFile,
                    $e->getLine(),
                    is_array($payload['diagnosis'] ?? null) ? $payload['diagnosis'] : null
                );
            } catch (\Throwable $ignoreHealer) {
            }

            $existing = self::incrementRecentDuplicate($type, $resolvedActionId, ['error', 'warning'], $fingerprint, 1);
            if ($existing) {
                return $existing;
            }

            $payload['fingerprint'] = $fingerprint;
            $payload['occurrences_count'] = 1;
            $payload['first_seen_at'] = now()->toIso8601String();
            $payload['last_seen_at'] = now()->toIso8601String();

            $attributes = [
                'type' => $type,
                'action_id' => $resolvedActionId,
                'title' => Str::limit(($titlePrefix ?: 'System-Fehler') . ' (' . class_basename($e) . ')', 190),
                'message' => Str::limit($sanitizedMessage, 2000),
                'status' => 'error',
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ];

            try {
                return SystemLog::create($attributes);
            } catch (\Throwable $dbEx) {
                self::writeDeadLetterFallback($attributes);
                return null;
            }
        } catch (\Throwable $ignore) {
            return null;
        } finally {
            self::$isRecording = false;
        }
    }

    /**
     * Protokolliert einen allgemeinen Log::error / Log::critical Aufruf in system_logs,
     * sofern er nicht bereits als Exception erfasst wurde.
     */
    public static function recordLogEvent(string $level, string $message, array $context = []): ?SystemLog
    {
        if (self::$isRecording || !class_exists(SystemLog::class)) {
            return null;
        }

        $levelLower = strtolower($level);
        $contextFile = is_string($context['file'] ?? null) ? $context['file'] : '';
        if (
            in_array($levelLower, ['warning', 'notice', 'info'], true)
            && (stripos($message, 'deprecated') !== false || stripos($message, 'return type of') !== false)
            && (
                str_contains($message, '/vendor/')
                || str_contains($message, '\\vendor\\')
                || str_contains($contextFile, '/vendor/')
                || str_contains($contextFile, '\\vendor\\')
            )
        ) {
            return null;
        }

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            return self::recordException(
                $context['exception'],
                'system',
                null,
                'System-Fehler [' . strtoupper($level) . ']',
                ['log_message' => Str::limit(self::sanitizeSensitiveText($message), 1000)]
            );
        }

        self::$isRecording = true;
        try {
            $safeContext = [];
            foreach ($context as $k => $v) {
                if ($v instanceof \Throwable) {
                    $safeContext[$k] = [
                        'class' => get_class($v),
                        'message' => self::sanitizeSensitiveText($v->getMessage()),
                        'file' => $v->getFile(),
                        'line' => $v->getLine(),
                    ];
                } elseif (is_string($v)) {
                    $safeContext[$k] = self::sanitizeSensitiveText($v);
                } elseif (is_scalar($v) || is_null($v) || is_array($v)) {
                    $safeContext[$k] = is_array($v) ? self::sanitizeArrayRecursive($v) : $v;
                } else {
                    $safeContext[$k] = (string) json_encode($v);
                }
            }

            $sanitizedMessage = self::sanitizeSensitiveText($message);
            $callerInfo = self::extractCallerTraceExcerpt();
            $diagnosis = self::diagnose($sanitizedMessage, null, $callerInfo['file']);
            $actionId = Str::limit('system:log:' . strtolower($level) . ':' . ($diagnosis['category'] ?? 'general'), 190, '');
            $status = in_array(strtolower($level), ['warning', 'notice'], true) ? 'warning' : 'error';

            $normalizedSig = preg_replace('/\b\d+\b/', '#', strtolower($sanitizedMessage));
            $fingerprint = hash('sha256', strtolower('system|' . $actionId . '|' . ($callerInfo['file'] ?? '') . '|' . mb_substr((string) $normalizedSig, 0, 160)));

            $existing = self::incrementRecentDuplicate('system', $actionId, ['error', 'warning'], $fingerprint, 1);
            if ($existing) {
                return $existing;
            }

            $attributes = [
                'type' => 'system',
                'action_id' => $actionId,
                'title' => 'System-Log ' . strtoupper($level) . ' (' . ($diagnosis['category'] ?? 'general') . ')',
                'message' => Str::limit($sanitizedMessage, 2000),
                'status' => $status,
                'payload' => [
                    'level' => $level,
                    'file' => self::resolveCompiledBladeViewPath($callerInfo['file']) ?? $callerInfo['file'],
                    'line' => $callerInfo['line'],
                    'trace_excerpt' => $callerInfo['trace_excerpt'],
                    'fingerprint' => $fingerprint,
                    'occurrences_count' => 1,
                    'first_seen_at' => now()->toIso8601String(),
                    'last_seen_at' => now()->toIso8601String(),
                    'diagnosis' => $diagnosis,
                    'context' => $safeContext,
                    'execution_context' => self::buildExecutionContext(),
                    'server_environment' => self::buildServerEnvironment(),
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ];

            try {
                return SystemLog::create($attributes);
            } catch (\Throwable $dbEx) {
                self::writeDeadLetterFallback($attributes);
                return null;
            }
        } catch (\Throwable $ignore) {
            return null;
        } finally {
            self::$isRecording = false;
        }
    }

    /**
     * Protokolliert einen fehlgeschlagenen Artisan-Befehl / Server-Cronjob (Exit-Code != 0) sofort in system_logs (/admin/global-logs).
     */
    public static function recordCommandFailure(
        string $command,
        int $exitCode,
        string $output = '',
        array $extra = []
    ): ?SystemLog {
        if (self::$isRecording || !class_exists(SystemLog::class)) {
            return null;
        }

        $commandClean = trim(explode(' ', trim($command))[0]);
        $dedupeKey = 'cmd_fail:' . $commandClean . ':' . $exitCode;
        if (isset(self::$loggedExceptionIds[crc32($dedupeKey)])) {
            return null;
        }

        self::$isRecording = true;
        try {
            self::$loggedExceptionIds[crc32($dedupeKey)] = true;

            $rawOutput = trim($output);
            if ($rawOutput === '') {
                $rawOutput = self::getCommandFailureDetail($commandClean);
            }
            $sanitizedOutput = self::sanitizeSensitiveText($rawOutput);
            $cronjobName = $extra['cronjob_name'] ?? $commandClean;
            $actionId = Str::limit('scheduler:cronjob_failed:' . $commandClean, 190, '');

            $message = "Der Befehl '{$command}' wurde mit Fehler-Exit-Code {$exitCode} beendet.";
            if ($sanitizedOutput !== '') {
                $message .= " Ausgabe: " . Str::limit($sanitizedOutput, 1500);
            }

            $diagnosis = self::diagnose($message, null, 'Console/Commands');
            $fingerprint = hash('sha256', strtolower('scheduler|' . $actionId . '|' . $exitCode));
            $existing = self::incrementRecentDuplicate('scheduler', $actionId, ['error', 'warning'], $fingerprint, 1);
            if ($existing) {
                if ($sanitizedOutput !== '') {
                    $p = is_array($existing->payload) ? $existing->payload : [];
                    if (empty($p['console_output'])) {
                        $p['console_output'] = Str::limit($sanitizedOutput, 3000);
                        $p['diagnosis'] = $diagnosis;
                        $existing->payload = $p;
                        $existing->message = Str::limit($message, 2000);
                        $existing->save();
                    }
                }
                return $existing;
            }

            $attributes = [
                'type' => 'scheduler',
                'action_id' => $actionId,
                'title' => Str::limit('Cronjob fehlgeschlagen: ' . $cronjobName . ' (Exit ' . $exitCode . ')', 190),
                'message' => Str::limit($message, 2000),
                'status' => 'error',
                'payload' => array_merge([
                    'command' => $command,
                    'subpage_path' => 'CLI: ' . $commandClean,
                    'exit_code' => $exitCode,
                    'console_output' => Str::limit($sanitizedOutput, 3000),
                    'fingerprint' => $fingerprint,
                    'occurrences_count' => 1,
                    'first_seen_at' => now()->toIso8601String(),
                    'last_seen_at' => now()->toIso8601String(),
                    'diagnosis' => $diagnosis,
                    'execution_context' => self::buildExecutionContext(),
                    'server_environment' => self::buildServerEnvironment(),
                ], $extra),
                'started_at' => now(),
                'finished_at' => now(),
            ];

            try {
                return SystemLog::create($attributes);
            } catch (\Throwable $dbEx) {
                self::writeDeadLetterFallback($attributes);
                return null;
            }
        } catch (\Throwable $ignore) {
            return null;
        } finally {
            self::$isRecording = false;
        }
    }

    /**
     * Zentraler Ökosystem-Error-Sentinel (Laravel Portal, Android App, Funkenwerk OS Mini-PC & Windows Desktop).
     * Gruppiert identische Fehler über 24h per SHA-256-Fingerprint (occurrences_count) ohne Datenbank-Flutung
     * und dokumentiert erfolgreiche Selbstheilungen transparent als [AUTO-GEHEILT].
     */
    public static function recordCrossPlatformEvent(array $data): ?SystemLog
    {
        if (self::$isRecording || !class_exists(SystemLog::class)) {
            return null;
        }

        self::$isRecording = true;
        try {
            $rawPlatform = strtolower(trim((string) ($data['platform'] ?? 'portal')));
            $allowedPlatforms = [
                'portal' => 'Portal',
                'android' => 'Android App',
                'minipc_linux' => 'Werkstatt Mini-PC',
                'windows_desktop' => 'Funkenwerk Windows',
            ];
            $platform = isset($allowedPlatforms[$rawPlatform]) ? $rawPlatform : 'portal';
            $platformLabel = $allowedPlatforms[$platform];

            $component = trim((string) ($data['component'] ?? 'general'));
            $componentSlug = Str::slug($component, '_') ?: 'general';
            $severity = strtolower(trim((string) ($data['severity'] ?? 'error')));
            $autoHealed = !empty($data['auto_healed']) || $severity === 'auto_healed';
            $autoHealAction = self::sanitizeSensitiveText((string) ($data['auto_heal_action'] ?? $data['heal_summary'] ?? ''));

            $cleanMessage = self::sanitizeSensitiveText(trim((string) ($data['message'] ?? 'Unbekannter Fehler')));
            if ($cleanMessage === '') {
                $cleanMessage = 'Unbekannter Laufzeitfehler';
            }

            $errorClass = self::sanitizeSensitiveText(trim((string) ($data['error_class'] ?? $data['exception_class'] ?? '')));
            $file = self::sanitizeSensitiveText(trim((string) ($data['file'] ?? '')));
            $line = isset($data['line']) && is_numeric($data['line']) ? (int) $data['line'] : null;
            $rawTitle = self::sanitizeSensitiveText(trim((string) ($data['title'] ?? '')));

            if (self::isBenignClientCancellation($cleanMessage, $errorClass ?: null, $rawTitle ?: null)) {
                return null;
            }

            $normalizedSig = preg_replace('/\b\d+\b/', '#', strtolower($cleanMessage));
            $providedFp = preg_replace('/[^a-f0-9]/i', '', (string) ($data['fingerprint'] ?? ''));
            $fingerprint = strlen($providedFp) >= 16
                ? strtolower(substr($providedFp, 0, 64))
                : hash('sha256', strtolower($platform . '|' . $componentSlug . '|' . $errorClass . '|' . $file . '|' . ($line ?? 0) . '|' . mb_substr((string) $normalizedSig, 0, 140)));

            $shortFp = substr($fingerprint, 0, 16);
            $actionId = Str::limit("{$platform}:{$componentSlug}:{$shortFp}", 190, '');

            $mappedStatus = match (true) {
                $autoHealed || in_array($severity, ['warning', 'warn', 'notice'], true) => 'warning',
                in_array($severity, ['info', 'resolved', 'success'], true) => 'success',
                default => 'error',
            };

            $occurrencesAdd = max(1, min(500, (int) ($data['occurrences'] ?? 1)));

            // 24h Fingerprint-Deduplizierung (erhöht Zähler statt neue Zeile zu spamen)
            $existing = SystemLog::where('action_id', $actionId)
                ->whereIn('status', ['error', 'warning'])
                ->where('started_at', '>=', now()->subHours(24))
                ->latest('id')
                ->first();

            $safeContext = is_array($data['context'] ?? null)
                ? self::sanitizeArrayRecursive($data['context'])
                : [];

            $rawStackInput = $data['stack'] ?? $data['stacktrace'] ?? null;
            $stackRaw = is_string($rawStackInput) && trim($rawStackInput) !== ''
                ? Str::limit(self::sanitizeSensitiveText($rawStackInput), 4500)
                : null;

            if ($existing) {
                $existingPayload = is_array($existing->payload) ? $existing->payload : [];
                $existingPayload['occurrences_count'] = ((int) ($existingPayload['occurrences_count'] ?? 1)) + $occurrencesAdd;
                $existingPayload['last_seen_at'] = now()->toIso8601String();
                if ($autoHealed) {
                    $existingPayload['auto_healed'] = true;
                    if ($autoHealAction !== '') {
                        $existingPayload['auto_heal_action'] = $autoHealAction;
                    }
                }
                if (!empty($safeContext)) {
                    $existingPayload['latest_context'] = $safeContext;
                }
                if ($stackRaw) {
                    $existingPayload['stack_excerpt'] = $stackRaw;
                }

                // Wenn ein vorher offener Fehler jetzt erfolgreich selbstgeheilt wurde -> Status auf warning abstufen
                if ($autoHealed && $existing->status === 'error') {
                    $existing->status = 'warning';
                    if (!str_starts_with((string) $existing->title, '[AUTO-GEHEILT]')) {
                        $existing->title = Str::limit('[AUTO-GEHEILT] ' . $existing->title, 190);
                    }
                } elseif ($mappedStatus === 'error' && !$autoHealed) {
                    $existing->status = 'error';
                }

                $existing->payload = $existingPayload;
                $existing->finished_at = now();
                $existing->save();

                return $existing;
            }

            $diagnosis = self::diagnose(trim($rawTitle . ' ' . $cleanMessage . ' ' . $componentSlug), $errorClass ?: null, $file ?: null);
            $baseTitle = $rawTitle !== ''
                ? $rawTitle
                : ($component !== '' ? ucfirst($component) : ($errorClass !== '' ? class_basename($errorClass) : 'Laufzeitfehler'));

            $prefix = $autoHealed ? "[AUTO-GEHEILT] [{$platformLabel}] " : "[{$platformLabel}] ";
            $finalTitle = Str::limit($prefix . $baseTitle, 190);

            $payload = [
                'platform' => $platform,
                'platform_label' => $platformLabel,
                'component' => $component,
                'fingerprint' => $fingerprint,
                'occurrences_count' => $occurrencesAdd,
                'auto_healed' => $autoHealed,
                'auto_heal_action' => $autoHealAction !== '' ? $autoHealAction : null,
                'app_version' => Str::limit(self::sanitizeSensitiveText((string) ($data['app_version'] ?? '')), 64),
                'device' => Str::limit(self::sanitizeSensitiveText((string) ($data['device'] ?? '')), 120),
                'exception_class' => $errorClass !== '' ? $errorClass : null,
                'file' => $file !== '' ? $file : null,
                'line' => $line,
                'stack_excerpt' => $stackRaw,
                'diagnosis' => $diagnosis,
                'context' => $safeContext,
                'first_seen_at' => (string) ($data['occurred_at'] ?? now()->toIso8601String()),
                'last_seen_at' => now()->toIso8601String(),
                'server_environment' => self::buildServerEnvironment(),
            ];

            $attributes = [
                'type' => $platform === 'portal' ? 'system' : $platform,
                'action_id' => $actionId,
                'title' => $finalTitle,
                'message' => Str::limit($cleanMessage, 2000),
                'status' => $mappedStatus,
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ];

            try {
                return SystemLog::create($attributes);
            } catch (\Throwable $dbEx) {
                self::writeDeadLetterFallback($attributes);
                return null;
            }
        } catch (\Throwable $ignore) {
            return null;
        } finally {
            self::$isRecording = false;
        }
    }

    /**
     * Löst kompilierte Laravel-Blade-Views (storage/framework/views/*.php) auf die echte .blade.php-Quelldatei zurück.
     */
    public static function resolveCompiledBladeViewPath(?string $file): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $file);
        if (!str_contains($normalized, '/framework/views/') && !str_contains($normalized, '/sf_views_')) {
            return $file;
        }

        try {
            if (is_file($file) && is_readable($file)) {
                $tail = '';
                $fh = @fopen($file, 'rb');
                if ($fh) {
                    $size = @filesize($file) ?: 0;
                    if ($size > 4096) {
                        @fseek($fh, -4096, SEEK_END);
                    }
                    $tail = (string) @fread($fh, 4096);
                    @fclose($fh);
                }

                if ($tail !== '' && preg_match('/\*\*PATH\s+(.+?\.blade\.php)\s+ENDPATH\*\*/', $tail, $m)) {
                    return trim($m[1]);
                }

                $head = (string) @file_get_contents($file, false, null, 0, 1024);
                if ($head !== '' && preg_match('/\*\*PATH\s+(.+?\.blade\.php)\s+ENDPATH\*\*/', $head, $m2)) {
                    return trim($m2[1]);
                }
            }
        } catch (\Throwable $ignore) {
        }

        return $file;
    }

    /**
     * Lokaler Notfall-Spool (sentinel_deadletter.jsonl), falls während eines Fehlers selbst die DB kurz nicht antwortet.
     * Wird beim nächsten Lauf von SentinelTriageService automatisch in system_logs nachgespült (0 % Datenverlust).
     */
    public static function writeDeadLetterFallback(array $attributes): void
    {
        try {
            $spoolPath = storage_path(self::DEAD_LETTER_SPOOL_FILE);
            $dir = dirname($spoolPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            if (is_file($spoolPath) && (@filesize($spoolPath) ?: 0) > 512 * 1024) {
                $lines = @file($spoolPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $lines = array_slice($lines, -120);
                @file_put_contents($spoolPath, implode("\n", $lines) . "\n", LOCK_EX);
            }

            $safeEntry = [
                'queued_at' => now()->toIso8601String(),
                'type' => (string) ($attributes['type'] ?? 'system'),
                'action_id' => (string) ($attributes['action_id'] ?? 'system:deadletter'),
                'title' => Str::limit(self::sanitizeSensitiveText((string) ($attributes['title'] ?? 'System-Fehler')), 190),
                'message' => Str::limit(self::sanitizeSensitiveText((string) ($attributes['message'] ?? '')), 2000),
                'status' => (string) ($attributes['status'] ?? 'error'),
                'payload' => is_array($attributes['payload'] ?? null)
                    ? self::sanitizeArrayRecursive($attributes['payload'])
                    : [],
            ];

            $jsonLine = json_encode($safeEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($jsonLine) && $jsonLine !== '') {
                @file_put_contents($spoolPath, $jsonLine . "\n", FILE_APPEND | LOCK_EX);
                @chmod($spoolPath, app()->environment('local', 'testing') ? 0666 : 0600);
            }
        } catch (\Throwable $ignore) {
        }
    }

    /**
     * Registriert einen PHP-Shutdown-Wächter, der selbst harte PHP-Abstürze (Out of Memory, Max Execution Time,
     * E_ERROR, E_PARSE, E_COMPILE_ERROR) lückenlos in system_logs bzw. den lokalen Notfall-Spool schreibt.
     */
    public static function registerFatalShutdownHandler(): void
    {
        if (self::$fatalHandlerRegistered) {
            return;
        }
        self::$fatalHandlerRegistered = true;

        register_shutdown_function(function (): void {
            $err = error_get_last();
            if (!is_array($err)) {
                return;
            }
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array((int) ($err['type'] ?? 0), $fatalTypes, true)) {
                return;
            }
            self::recordFatalShutdownError($err);
        });
    }

    /**
     * Verarbeitet einen harten PHP-Shutdown-Fehler (z. B. aus error_get_last()) und sichert ihn in system_logs.
     */
    public static function recordFatalShutdownError(array $error): ?SystemLog
    {
        if (self::$isRecording) {
            return null;
        }

        $rawMessage = trim((string) ($error['message'] ?? 'Fatal PHP Shutdown Error'));
        $rawFile = (string) ($error['file'] ?? '');
        $line = isset($error['line']) ? (int) $error['line'] : null;
        $errType = (int) ($error['type'] ?? E_ERROR);

        // Falls derselbe Fehler bereits durch Laravels ExceptionHandler in diesem Zyklus als FatalError erfasst wurde -> Duplikat vermeiden
        $dedupeSig = 'fatal:' . md5($rawFile . ':' . ($line ?? 0) . ':' . mb_substr($rawMessage, 0, 120));
        if (isset(self::$loggedExceptionIds[crc32($dedupeSig)])) {
            return null;
        }

        self::$isRecording = true;
        try {
            self::$loggedExceptionIds[crc32($dedupeSig)] = true;

            $resolvedFile = self::resolveCompiledBladeViewPath($rawFile) ?? $rawFile;
            $sanitizedMessage = self::sanitizeSensitiveText($rawMessage);
            $diagnosis = self::diagnose($sanitizedMessage, 'FatalShutdownError', $resolvedFile);
            $actionId = 'system:fatal_shutdown:' . ($diagnosis['category'] ?? 'php_fatal_or_oom');

            $normalizedSig = preg_replace('/\b\d+\b/', '#', strtolower($sanitizedMessage));
            $fingerprint = hash('sha256', strtolower('system|' . $actionId . '|' . $resolvedFile . '|' . ($line ?? 0) . '|' . mb_substr((string) $normalizedSig, 0, 160)));

            $existing = self::incrementRecentDuplicate('system', $actionId, ['error', 'warning'], $fingerprint, 1);
            if ($existing) {
                return $existing;
            }

            $payload = [
                'platform' => 'portal',
                'platform_label' => 'Webportal',
                'exception_class' => 'FatalShutdownError(E_' . $errType . ')',
                'file' => $resolvedFile !== '' ? $resolvedFile : null,
                'compiled_view_file' => ($resolvedFile !== $rawFile && $rawFile !== '') ? $rawFile : null,
                'line' => $line,
                'php_error_type' => $errType,
                'fingerprint' => $fingerprint,
                'occurrences_count' => 1,
                'first_seen_at' => now()->toIso8601String(),
                'last_seen_at' => now()->toIso8601String(),
                'diagnosis' => $diagnosis,
                'execution_context' => self::buildExecutionContext(),
                'server_environment' => self::buildServerEnvironment(),
            ];

            $attributes = [
                'type' => 'system',
                'action_id' => $actionId,
                'title' => Str::limit('Kritischer PHP-Abbruch (' . ($diagnosis['category'] ?? 'Fatal') . ')', 190),
                'message' => Str::limit($sanitizedMessage, 2000),
                'status' => 'error',
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ];

            try {
                return SystemLog::create($attributes);
            } catch (\Throwable $dbEx) {
                self::writeDeadLetterFallback($attributes);
                return null;
            }
        } catch (\Throwable $ignore) {
            return null;
        } finally {
            self::$isRecording = false;
        }
    }
}
