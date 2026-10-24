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

        return (string) preg_replace(
            '/((?:api[_-]?key|key|token|access[_-]?token|refresh[_-]?token|password|passwd|secret|signature|pin)\s*[=:]\s*)([^\s&"\'\r\n]+)/i',
            '$1[REDACTED]',
            $text
        );
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
}
