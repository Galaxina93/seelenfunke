<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Models\Global\GlobalLog;

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
        ]);

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (\Throwable $e) {
            try {
                // Fange echte System-Fehler ab und schiebe sie direkt ins Dashboard
                if (class_exists(GlobalLog::class)) {
                    GlobalLog::create([
                        'type' => 'system',
                        'action_id' => 'system:exception',
                        'title' => 'System-Fehler (Exception)',
                        'message' => substr($e->getMessage(), 0, 200),
                        'status' => 'error',
                        'payload' => [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'code' => $e->getCode()
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
