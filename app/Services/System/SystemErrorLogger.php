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

    /**
     * Speichert bereits protokollierte Exception-Objekt-IDs innerhalb desselben Request-Zyklus,
     * um doppelte Einträge durch ExceptionHandler + Log::error Listener zu verhindern.
     *
     * @var array<int, bool>
     */
    protected static array $loggedExceptionIds = [];

    /**
     * Analysiert eine Fehlermeldung / Exception und liefert eine strukturierte deutsche Ursachen-Diagnose.
     */
    public static function diagnose(string $message, ?string $exceptionClass = null, ?string $file = null): array
    {
        $msgLower = strtolower($message);
        $classLower = strtolower((string) $exceptionClass);

        if (str_contains($classLower, 'blueprintsecurityexception') || str_contains($msgLower, 'architektur-blueprint')) {
            return [
                'category' => 'blueprint_guardrail_or_compliance',
                'probable_cause' => 'Blockierter KI-Zugriff auf ein GoBD-/Sicherheits-geschütztes Model oder Verstoß gegen das Architektur-Typenschild (ModelBlueprint).',
                'recommended_action' => 'Prüfe die AiAccessPolicy des betroffenen Models im Blueprint sowie den auslösenden KI-Tool-Aufruf im Trace.',
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

        if (str_contains($classLower, 'authenticationexception') || str_contains($classLower, 'authorizationexception') || str_contains($msgLower, 'unauthenticated')) {
            return [
                'category' => 'auth_or_permission',
                'probable_cause' => 'Nicht authentifizierter Zugriff oder fehlende Berechtigung / abgelaufene Session.',
                'recommended_action' => 'Normal bei abgelaufenen Nutzer-Sessions; bei API-Requests Token-Übergabe prüfen.',
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

        if (str_contains($msgLower, 'okhttp') || str_contains($msgLower, 'retrofit') || str_contains($msgLower, 'sockettimeout') || str_contains($classLower, 'unknownhostexception')) {
            return [
                'category' => 'client_network_or_api',
                'probable_cause' => 'Temporärer Mobilfunk-/WLAN-Abbruch oder API-Timeout auf dem Endgerät (Android / Desktop).',
                'recommended_action' => 'Kein Datenverlust bei aktivem Offline-Puffer; bei gehäuftem Auftreten Server-Antwortzeiten prüfen.',
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

        return (string) preg_replace(
            '/((?:api[_-]?key|key|token|access[_-]?token|refresh[_-]?token|password|passwd|secret|signature|pin|authorization)\s*[=:]\s*)([^\s&"\'\r\n]+)/i',
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
        $sensitiveKeyPattern = '/^(?:password|passwd|secret|token|access_token|refresh_token|api_key|gemini_key|authorization|cookie|pin|two_factor_code)$/i';

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
     * Erstellt Informationen über den aktuellen HTTP-Request oder CLI-Befehl.
     */
    public static function buildExecutionContext(): array
    {
        if (app()->runningInConsole()) {
            return [
                'mode' => 'cli',
                'command' => self::sanitizeSensitiveText(implode(' ', $_SERVER['argv'] ?? [])),
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

        return [
            'mode' => 'http',
            'method' => $req?->method() ?? 'GET',
            'url' => self::sanitizeSensitiveText($req?->fullUrl() ?? ''),
            'route' => $req?->route()?->getName() ?? $req?->path(),
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
        return [
            'php_version' => PHP_VERSION,
            'app_env' => config('app.env'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_connection' => config('queue.default'),
        ];
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
        if (self::$isRecording || !class_exists(SystemLog::class)) {
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
            $resolvedActionId = $actionId ?: ('system:exception:' . $category);

            $rawMessage = $e->getMessage() ?: ('Exception in ' . basename($e->getFile()) . ':' . $e->getLine());

            return SystemLog::create([
                'type' => $type,
                'action_id' => Str::limit($resolvedActionId, 190, ''),
                'title' => Str::limit(($titlePrefix ?: 'System-Fehler') . ' (' . class_basename($e) . ')', 190),
                'message' => Str::limit(self::sanitizeSensitiveText($rawMessage), 2000),
                'status' => 'error',
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
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
                    $safeContext[$k] = $v;
                } else {
                    $safeContext[$k] = (string) json_encode($v);
                }
            }

            $sanitizedMessage = self::sanitizeSensitiveText($message);
            $diagnosis = self::diagnose($sanitizedMessage);
            $actionId = 'system:log:' . strtolower($level) . ':' . ($diagnosis['category'] ?? 'general');

            return SystemLog::create([
                'type' => 'system',
                'action_id' => Str::limit($actionId, 190, ''),
                'title' => 'System-Log ' . strtoupper($level) . ' (' . ($diagnosis['category'] ?? 'general') . ')',
                'message' => Str::limit($sanitizedMessage, 2000),
                'status' => in_array(strtolower($level), ['warning', 'notice'], true) ? 'warning' : 'error',
                'payload' => [
                    'level' => $level,
                    'diagnosis' => $diagnosis,
                    'context' => $safeContext,
                    'execution_context' => self::buildExecutionContext(),
                    'server_environment' => self::buildServerEnvironment(),
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
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

            $sanitizedOutput = self::sanitizeSensitiveText(trim($output));
            $cronjobName = $extra['cronjob_name'] ?? $commandClean;

            $message = "Der Befehl '{$command}' wurde mit Fehler-Exit-Code {$exitCode} beendet.";
            if ($sanitizedOutput !== '') {
                $message .= " Ausgabe: " . Str::limit($sanitizedOutput, 1500);
            }

            $diagnosis = self::diagnose($message, null, 'Console/Commands');

            return SystemLog::create([
                'type' => 'scheduler',
                'action_id' => Str::limit('scheduler:cronjob_failed:' . $commandClean, 190, ''),
                'title' => Str::limit('Cronjob fehlgeschlagen: ' . $cronjobName . ' (Exit ' . $exitCode . ')', 190),
                'message' => Str::limit($message, 2000),
                'status' => 'error',
                'payload' => array_merge([
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'console_output' => Str::limit($sanitizedOutput, 3000),
                    'diagnosis' => $diagnosis,
                    'execution_context' => self::buildExecutionContext(),
                    'server_environment' => self::buildServerEnvironment(),
                ], $extra),
                'started_at' => now(),
                'finished_at' => now(),
            ]);
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
            $autoHealAction = self::sanitizeSensitiveText((string) ($data['auto_heal_action'] ?? ''));

            $cleanMessage = self::sanitizeSensitiveText(trim((string) ($data['message'] ?? 'Unbekannter Fehler')));
            if ($cleanMessage === '') {
                $cleanMessage = 'Unbekannter Laufzeitfehler';
            }

            $errorClass = self::sanitizeSensitiveText(trim((string) ($data['error_class'] ?? '')));
            $file = self::sanitizeSensitiveText(trim((string) ($data['file'] ?? '')));
            $line = isset($data['line']) && is_numeric($data['line']) ? (int) $data['line'] : null;
            $rawTitle = self::sanitizeSensitiveText(trim((string) ($data['title'] ?? '')));

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

            $stackRaw = is_string($data['stack'] ?? null)
                ? Str::limit(self::sanitizeSensitiveText($data['stack']), 4500)
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

            $diagnosis = self::diagnose($cleanMessage, $errorClass ?: null, $file ?: null);
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

            return SystemLog::create([
                'type' => $platform === 'portal' ? 'system' : $platform,
                'action_id' => $actionId,
                'title' => $finalTitle,
                'message' => Str::limit($cleanMessage, 2000),
                'status' => $mappedStatus,
                'payload' => $payload,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $ignore) {
            return null;
        } finally {
            self::$isRecording = false;
        }
    }
}
