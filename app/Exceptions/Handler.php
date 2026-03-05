<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use App\Models\Funki\FunkiLog;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            try {
                // Fange echte System-Fehler ab und schiebe sie direkt ins Funki-Dashboard
                if (class_exists(FunkiLog::class)) {
                    FunkiLog::create([
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
                // Wenn das Loggen selbst fehlschlägt (z.B. weil die Datenbank offline ist),
                // fangen wir das hier stillschweigend ab, damit das System nicht komplett crasht.
            }
        });
    }
}
