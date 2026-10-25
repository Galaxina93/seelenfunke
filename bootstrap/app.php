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
            'employee_role' => \App\Http\Middleware\EnsureEmployeeRole::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/ai/*',
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            try {
                // Fange echte System-Fehler ab und schiebe sie inkl. automatischer Ursachen-Diagnose ins SystemLog (/admin/global-logs)
                if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                    $msgLower = strtolower($e->getMessage());
                    $actionId = null;
                    if (str_contains($msgLower, 'getaddrinfo') || str_contains($msgLower, 'redis') || str_contains(strtolower(get_class($e)), 'predis')) {
                        $actionId = 'system:exception:redis';
                    }
                    \App\Services\System\SystemErrorLogger::recordException($e, 'system', $actionId, 'System-Fehler');
                } elseif (class_exists(SystemLog::class)) {
                    SystemLog::create([
                        'type' => 'system',
                        'action_id' => 'system:exception',
                        'title' => 'System-Fehler (' . class_basename($e) . ')',
                        'message' => substr($e->getMessage(), 0, 1000),
                        'status' => 'error',
                        'payload' => [
                            'exception_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'code' => $e->getCode(),
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
