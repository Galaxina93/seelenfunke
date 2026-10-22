<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Models\System\SystemLog;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\UserLastActivity::class,
            \App\Http\Middleware\TrackVisitor::class,
            \App\Http\Middleware\MarketingTrackingMiddleware::class,
        ]);

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/ai/*',
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            try {
                // Fange echte System-Fehler ab und schiebe sie inkl. automatischer Ursachen-Diagnose ins SystemLog
                if (class_exists(SystemLog::class)) {
                    $msg = $e->getMessage();
                    $msgLower = strtolower($msg);
                    $exClass = get_class($e);

                    $diagnosis = 'Allgemeiner Laufzeitfehler. Prüfe exception_class, file/line und trace_excerpt.';
                    $actionId = 'system:exception';

                    if (str_contains($msgLower, 'getaddrinfo') || str_contains($msgLower, 'redis') || str_contains(strtolower($exClass), 'predis')) {
                        $diagnosis = 'Redis-Container nicht erreichbar oder DNS-Auflösung fehlgeschlagen. Der Boot-Fallback in AppServiceProvider schützt HTTP-Requests automatisch via File-Cache.';
                        $actionId = 'system:exception:redis';
                    } elseif ($e instanceof \PDOException || str_contains($msgLower, 'sqlstate')) {
                        $diagnosis = 'Datenbank-Verbindungs- oder SQL-Abfragefehler. Prüfe DB-Erreichbarkeit, Migrationen und Spaltendefinitionen.';
                        $actionId = 'system:exception:database';
                    } elseif (str_contains($msgLower, 'generativelanguage') || str_contains($msgLower, 'gemini')) {
                        $diagnosis = 'Fehler bei der Kommunikation mit der Google Gemini API (Timeout, Quota oder ungültiges Modell/Parameter).';
                        $actionId = 'system:exception:gemini_api';
                    } elseif (str_contains($msgLower, 'smtp') || str_contains($msgLower, 'mailer') || str_contains($msgLower, 'imap')) {
                        $diagnosis = 'E-Mail-Server (SMTP/IMAP) Verbindungs- oder Authentifizierungsproblem.';
                        $actionId = 'system:exception:mail';
                    }

                    $traceExcerpt = array_map(function (array $frame) {
                        return ($frame['file'] ?? '[internal]') . ':' . ($frame['line'] ?? '?')
                            . ' -> ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
                    }, array_slice($e->getTrace(), 0, 5));

                    SystemLog::create([
                        'type' => 'system',
                        'action_id' => $actionId,
                        'title' => 'System-Fehler (' . class_basename($e) . ')',
                        'message' => substr($msg, 0, 500),
                        'status' => 'error',
                        'payload' => [
                            'exception_class' => $exClass,
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'code' => $e->getCode(),
                            'diagnosis' => $diagnosis,
                            'execution_context' => app()->runningInConsole()
                                ? ('CLI: ' . implode(' ', $_SERVER['argv'] ?? []))
                                : ('HTTP: ' . (request()?->method() ?? 'GET') . ' ' . (request()?->fullUrl() ?? '')),
                            'trace_excerpt' => $traceExcerpt,
                        ],
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                }
            } catch (\Throwable $loggingException) {
                // Wenn das Loggen selbst fehlschlägt (z.B. Datenbank offline) abfangen.
            }
        });

        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })->create();
