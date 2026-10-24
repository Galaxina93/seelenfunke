<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use App\Models\System\SystemLog;
use App\Models\System\SystemCronjob;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Zähle jeden Boot-Vorgang des Schedulers (zur Diagnose, ob der Cronjob überhaupt den Webspace triggert)
$argv = $_SERVER['argv'] ?? [];
if (in_array('schedule:run', $argv)) {
    try {
        \Illuminate\Support\Facades\Cache::put('scheduler_bootstrap_last_run', now()->timestamp);
    } catch (\Throwable $e) {
        // Ignorieren bei Cache-Fehlern während des Bootstraps
    }
}

try {
    if (Schema::hasTable('system_cronjobs')) {
        $cronjobs = SystemCronjob::where('is_active', true)->get();

        foreach ($cronjobs as $job) {
            try {
                // Normalisiere den Befehl (entferne 'php artisan' o.ä. Präfixe)
                $commandClean = trim($job->command);
                $commandClean = preg_replace('/^(?:\/usr\/bin\/)?php(?:[0-9.]+)?\s+/', '', $commandClean);
                $commandClean = preg_replace('/^(?:(?:\S+)?artisan)\s+/', '', $commandClean);
                $commandClean = trim($commandClean);

                // SICHERHEITSSPERRE FÜR DAEMONS & DESTRUKTIVE BEFEHLE:
                // Commands wie 'reverb:start' oder 'queue:work' laufen unendlich.
                // Auch mit runInBackground() wartet der Mittwald-Docker-Container auf das Ende ALLER Kind-Prozesse.
                // Wenn diese hier geladen werden, bleibt der gesamte schedule:run hängen!
                $daemonCommands = ['reverb:start', 'queue:work', 'queue:listen', 'websockets:serve', 'migrate:fresh', 'migrate:reset', 'db:wipe', 'tinker', 'key:generate', 'down'];
                
                $commandBase = explode(' ', $commandClean)[0];
                if ($commandBase === '' || !preg_match('/^[a-zA-Z0-9:_-]+$/', $commandBase) || in_array($commandBase, $daemonCommands, true)) {
                    // Diese Befehle dürfen NIEMALS über den Laravel Scheduler laufen.
                    // Sie MÜSSEN als eigenständige Cronjobs im Mittwald Panel angelegt werden.
                    if (class_exists(SystemLog::class)) {
                        SystemLog::create([
                            'type' => 'system',
                            'action_id' => 'system:cronjob_blocked',
                            'title' => 'Daemon/Befehl-Sperre: ' . $job->command,
                            'message' => 'Langlaufende oder gesperrte Prozesse dürfen nicht über den internen Scheduler laufen, da sie sonst den Server blockieren oder gefährden.',
                            'status' => 'warning',
                            'started_at' => now(),
                            'finished_at' => now(),
                        ]);
                    }
                    
                    // Job automatisch deaktivieren, um zukünftige Hänger zu vermeiden
                    $job->update(['is_active' => false, 'status' => 'error']);
                    continue;
                }

                // Sub-Minuten Intervalle in Cronjob-Umgebungen auf jede Minute normalisieren,
                // da sub-minütliche Wiederholungen (repeatEvery) ScheduleRunCommand in eine
                // blockierende 60-Sekunden sleep()-Schleife zwingen und Mittwald Cronjobs überlappen/abbrechen lassen.
                $schedule = trim((string) $job->schedule);
                $subMinuteSchedules = ['everySecond', 'everyTwoSeconds', 'everyFiveSeconds', 'everyTenSeconds', 'everyFifteenSeconds', 'everyTwentySeconds', 'everyThirtySeconds'];
                if (in_array($schedule, $subMinuteSchedules, true)) {
                    $schedule = 'everyMinute';
                }

                $allowedScheduleMethods = [
                    'everyMinute', 'everyTwoMinutes', 'everyThreeMinutes', 'everyFourMinutes', 'everyFiveMinutes',
                    'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes',
                    'hourly', 'everyTwoHours', 'everyThreeHours', 'everyFourHours', 'everySixHours',
                    'daily', 'weekly', 'monthly', 'quarterly', 'yearly',
                ];

                $safeParams = [];
                $blockedOpts = ['--env', '--database', '--path', '--realpath', '--bootstrap'];
                if (!empty($job->parameters)) {
                    foreach (preg_split('/\s+/', trim((string) $job->parameters), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                        if (preg_match('/[;&|`$<>\\\\\x00-\x1F]/', $token)) {
                            continue;
                        }
                        if (str_starts_with($token, '--')) {
                            $optKey = strtolower(explode('=', $token, 2)[0]);
                            if (preg_match('/^--[a-zA-Z0-9_-]+(=[a-zA-Z0-9_:.,\/-]+)?$/', $token) && !in_array($optKey, $blockedOpts, true)) {
                                $safeParams[] = $token;
                            }
                        }
                    }
                }

                $event = Schedule::command($commandBase, $safeParams);

                if (in_array($schedule, $allowedScheduleMethods, true)) {
                    $event->{$schedule}();
                } elseif (preg_match('/^[0-9*\/,\-]+(\s+[0-9*\/,\-]+){4}$/', $schedule)) {
                    $event->cron($schedule);
                } else {
                    continue;
                }
                
                // System Herzschlag (Heartbeat): MUSS synchron im selben PHP-Prozess laufen (KEIN runInBackground)
                // und OHNE Mutex-Sperre (KEIN withoutOverlapping), damit er verlässlich in < 1ms ausgeführt wird
                // und niemals durch verwaiste Mutex-Locks blockiert werden kann!
                if ($commandClean === 'system:heartbeat') {
                    $heartbeatRegistered = true;
                } else {
                    if ($commandClean === 'dhl:check-delivery-status') {
                        $dhlDeliveryStatusRegistered = true;
                    }
                    $event->withoutOverlapping();
                    if (!isset($_GET['sync_schedule']) && getenv('SYNC_SCHEDULE') !== '1') {
                        $event->runInBackground();
                    }
                }

                $event->onSuccess(function () use ($job) {
                    $job->update([
                        'last_run_at' => now(),
                        'status' => 'success'
                    ]);
                })->onFailure(function () use ($job) {
                    $job->update([
                        'last_run_at' => now(),
                        'status' => 'error'
                    ]);

                    if (class_exists(SystemLog::class)) {
                        SystemLog::create([
                            'type' => 'system',
                            'action_id' => 'system:cronjob_failed',
                            'title' => 'Cronjob fehlgeschlagen: ' . $job->name,
                            'message' => 'Der Cronjob (' . $job->command . ') konnte nicht erfolgreich ausgeführt werden.',
                            'status' => 'error',
                            'started_at' => now(),
                            'finished_at' => now(),
                        ]);
                    }
                });
            } catch (\Throwable $jobEx) {
                // Logge Registrierungsfehler für diesen einzelnen Job
                try {
                    \Illuminate\Support\Facades\Log::error("Fehler beim Registrieren von Job '{$job->name}' ({$job->command}): " . $jobEx->getMessage());
                    
                    $jobErrors = \Illuminate\Support\Facades\Cache::get('scheduler_job_errors', []);
                    $jobErrors[$job->id] = [
                        'message' => $jobEx->getMessage(),
                        'timestamp' => now()->toDateTimeString(),
                    ];
                    \Illuminate\Support\Facades\Cache::put('scheduler_job_errors', $jobErrors, now()->addMinutes(60));
                    
                    $job->update(['status' => 'error']);
                } catch (\Throwable $innerEx) {
                    // Falls DB/Cache blockiert
                }
            }
        }
    }
} catch (\Throwable $e) {
    // Fange DB-Verbindungsfehler oder ähnliche globale Ausnahmen ab
    try {
        \Illuminate\Support\Facades\Cache::put('scheduler_last_exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => now()->toDateTimeString(),
        ], now()->addMinutes(60));
        
        \Illuminate\Support\Facades\Log::error("Scheduler globaler Boot-Fehler: " . $e->getMessage());
    } catch (\Throwable $innerEx) {
        // Falls DB/Cache blockiert
    }
}

// Ausfallsicherer Heartbeat-Fallback für das Health-Dashboard:
// Stellt sicher, dass das Lebenszeichen immer geschrieben wird, falls in der DB noch kein Job angelegt oder aktiv ist
if (!isset($heartbeatRegistered) || !$heartbeatRegistered) {
    Schedule::command('system:heartbeat')->everyMinute();
}

// Ausfallsicherer Fallback für DHL Sendungsstatus & Auto-Zustellung (läuft alle 2 Stunden tagsüber)
if (!isset($dhlDeliveryStatusRegistered) || !$dhlDeliveryStatusRegistered) {
    Schedule::command('dhl:check-delivery-status')
        ->cron('0 8-20/2 * * *')
        ->withoutOverlapping()
        ->runInBackground();
}

Artisan::command('workspace:dateitrichter {--all : Verarbeitet alle Dokumente im gesamten Workspace}', function () {
    $this->info('=== STARTE WORKSPACE DATEITRICHTER ===');
    $service = app(\App\Services\AI\Workspace\DateitrichterService::class);
    $all = (bool) $this->option('all');
    $result = $service->processAll($all);
    $this->info($result['message']);
    $this->info("Gefunden: {$result['total_files_found']} | Verarbeitet: {$result['processed_count']} | Verschoben: {$result['moved_count']} | Indiziert: {$result['indexed_count']}");
})->purpose('Analysiert und sortiert Workspace-Dokumente in Berufsleben, Dokumente und Gesundheit ein');
