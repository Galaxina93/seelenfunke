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
        if (class_exists(\App\Services\System\SystemHealthPulseService::class)) {
            \App\Services\System\SystemHealthPulseService::recordSchedulerHeartbeat();
        }
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
                $daemonCommands = [
                    'reverb:start', 'reverb:twilio-bridge', 'queue:work', 'queue:listen', 'websockets:serve',
                    'migrate:fresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'tinker', 'key:generate', 'down', 'system:prepare-live',
                ];
                
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
                    if (class_exists(\App\Services\System\SystemAlertTicketService::class)) {
                        \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                            alertKey: 'cronjob_blocked:' . ($commandBase !== '' ? $commandBase : $job->id),
                            title: 'Gesperrter Cronjob automatisch deaktiviert: ' . $job->name,
                            markdownBody: "Der eingetragene Cronjob-Befehl `{$job->command}` ist als Daemon- oder Destruktiv-Befehl für den internen Scheduler gesperrt und wurde zum Schutz vor Server-Blockaden automatisch pausiert.",
                            departmentName: 'System',
                            agentName: 'Systemi',
                            ticketType: 'scheduler_security',
                            priority: 'high'
                        );
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
                    if ($commandClean === 'gamification:award-loyalty-sparks') {
                        $loyaltySparksRegistered = true;
                    }
                    if ($commandClean === 'shop:send-abandoned-cart-reminders') {
                        $abandonedCartReminderRegistered = true;
                    }
                    if ($commandBase === 'system:logs-cleanup') {
                        $logsCleanupRegistered = true;
                    }
                    if ($commandBase === 'system:sentinel-triage') {
                        $sentinelTriageRegistered = true;
                    }
                    if ($commandBase === 'system:sentinel-audit') {
                        $sentinelAuditRegistered = true;
                    }
                    $event->withoutOverlapping();
                    if (!isset($_GET['sync_schedule']) && getenv('SYNC_SCHEDULE') !== '1') {
                        $event->runInBackground();
                    }
                }

                $outputLogFile = storage_path('logs/cron-' . md5($commandBase) . '.log');
                if ($commandClean !== 'system:heartbeat') {
                    $event->sendOutputTo($outputLogFile);
                }

                $event->onSuccess(function () use ($job, $commandBase, $outputLogFile) {
                    $job->update([
                        'last_run_at' => now(),
                        'status' => 'success'
                    ]);

                    if (class_exists(\App\Services\System\SystemAlertTicketService::class)) {
                        \App\Services\System\SystemAlertTicketService::autoResolveByAlertPrefix(
                            'cronjob_failed:' . $commandBase,
                            "Geplanter Lauf von `{$job->command}` ({$job->name}) wurde wieder fehlerfrei abgeschlossen."
                        );
                    }

                    // Temporäre cron-*.log nach fehlerfreiem Lauf konsistent auf 0 Bytes halten (echte Fehlerzeilen bleiben für Sentinel erhalten)
                    if (is_string($outputLogFile)
                        && $outputLogFile !== ''
                        && is_file($outputLogFile)
                        && !is_link($outputLogFile)
                        && class_exists(\App\Services\System\LogArchiveService::class)
                        && !\App\Services\System\LogArchiveService::fileHasErrorLines($outputLogFile)) {
                        @file_put_contents($outputLogFile, '');
                    }
                })->onFailure(function () use ($job, $commandBase, $outputLogFile) {
                    $job->update([
                        'last_run_at' => now(),
                        'status' => 'error'
                    ]);

                    $outputExcerpt = '';
                    try {
                        if (is_file($outputLogFile) && is_readable($outputLogFile)) {
                            $rawOut = trim((string) @file_get_contents($outputLogFile));
                            if ($rawOut !== '') {
                                $outputExcerpt = mb_substr($rawOut, -1500);
                            }
                        }
                    } catch (\Throwable $ignore) {
                    }

                    if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                        \App\Services\System\SystemErrorLogger::recordCommandFailure(
                            $job->command,
                            1,
                            $outputExcerpt,
                            [
                                'cronjob_id' => $job->id,
                                'cronjob_name' => $job->name,
                                'schedule' => $job->schedule,
                                'trigger' => 'scheduler_on_failure',
                            ]
                        );
                    } elseif (class_exists(SystemLog::class)) {
                        SystemLog::create([
                            'type' => 'scheduler',
                            'action_id' => 'scheduler:cronjob_failed:' . $commandBase,
                            'title' => 'Cronjob fehlgeschlagen: ' . $job->name,
                            'message' => 'Der Cronjob (' . $job->command . ') konnte nicht erfolgreich ausgeführt werden.'
                                . ($outputExcerpt !== '' ? ' Ausgabe: ' . $outputExcerpt : ''),
                            'status' => 'error',
                            'started_at' => now(),
                            'finished_at' => now(),
                        ]);
                    }

                    if (class_exists(\App\Services\System\SystemAlertTicketService::class)) {
                        \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                            alertKey: 'cronjob_failed:' . $commandBase,
                            title: 'Cronjob fehlgeschlagen: ' . $job->name,
                            markdownBody: "Der geplante Cronjob `{$job->command}` (`{$job->schedule}`) ist fehlgeschlagen.\n\n"
                                . ($outputExcerpt !== '' ? "**Ausgabe-Auszug:**\n```\n" . $outputExcerpt . "\n```" : 'Keine Konsolenausgabe vorhanden.'),
                            departmentName: 'System',
                            agentName: 'Systemi',
                            ticketType: 'cronjob_failure',
                            priority: 'high',
                            assignToAntigravity: true,
                            extraPayload: [
                                'cronjob_id' => $job->id,
                                'command' => $job->command,
                                'schedule' => $job->schedule,
                            ]
                        );
                    }
                });
            } catch (\Throwable $jobEx) {
                // Logge Registrierungsfehler für diesen einzelnen Job sofort ins SystemLog (/admin/global-logs)
                try {
                    if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                        \App\Services\System\SystemErrorLogger::recordException(
                            $jobEx,
                            'scheduler',
                            'scheduler:registration_failed:' . ($job->command ?? 'unknown'),
                            'Cronjob-Registrierung fehlgeschlagen: ' . ($job->name ?? $job->command),
                            ['cronjob_id' => $job->id ?? null, 'command' => $job->command ?? null]
                        );
                    } else {
                        \Illuminate\Support\Facades\Log::error("Fehler beim Registrieren von Job '{$job->name}' ({$job->command}): " . $jobEx->getMessage());
                    }

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
    // Ausfallsicherer Fallback für DHL Sendungsstatus & Auto-Zustellung (nur falls noch nicht in system_cronjobs hinterlegt)
    if ((!isset($dhlDeliveryStatusRegistered) || !$dhlDeliveryStatusRegistered)
        && (!Schema::hasTable('system_cronjobs') || !SystemCronjob::where('command', 'dhl:check-delivery-status')->exists())) {
        Schedule::command('dhl:check-delivery-status')
            ->cron('0 8-20/2 * * *')
            ->withoutOverlapping()
            ->runInBackground();
    }

    // Ausfallsicherer Fallback für Seelenfunken-Geburtstags- & Jahrestags-Bonus (nur falls noch nicht in system_cronjobs hinterlegt)
    if ((!isset($loyaltySparksRegistered) || !$loyaltySparksRegistered)
        && (!Schema::hasTable('system_cronjobs') || !SystemCronjob::where('command', 'gamification:award-loyalty-sparks')->exists())) {
        Schedule::command('gamification:award-loyalty-sparks')
            ->dailyAt('08:15')
            ->withoutOverlapping();
    }

    // Ausfallsicherer Fallback für freundliche Warenkorb-Erinnerungen (nur falls noch nicht in system_cronjobs hinterlegt)
    if ((!isset($abandonedCartReminderRegistered) || !$abandonedCartReminderRegistered)
        && (!Schema::hasTable('system_cronjobs') || !SystemCronjob::where('command', 'shop:send-abandoned-cart-reminders')->exists())) {
        Schedule::command('shop:send-abandoned-cart-reminders')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    // Ausfallsicherer Fallback für die nächtliche 7-Tage-SystemLog-Selbstbereinigung (nur falls noch nicht in system_cronjobs hinterlegt)
    if ((!isset($logsCleanupRegistered) || !$logsCleanupRegistered)
        && (!Schema::hasTable('system_cronjobs') || !SystemCronjob::where('command', 'system:logs-cleanup')->exists())) {
        Schedule::command('system:logs-cleanup', ['--prune'])
            ->dailyAt('04:15')
            ->withoutOverlapping();
    }

    // Ausfallsicherer Fallback für den 5-Minuten Funkira-Selbstheilungs-Wächter & Antigravity-Pool
    if ((!isset($sentinelTriageRegistered) || !$sentinelTriageRegistered)
        && (!Schema::hasTable('system_cronjobs') || !SystemCronjob::where('command', 'system:sentinel-triage')->exists())) {
        Schedule::command('system:sentinel-triage', ['--probe-bridges'])
            ->everyFiveMinutes()
            ->withoutOverlapping();
    }

    // Ausfallsicherer Fallback für das regelmäßige Selbstheilungs- & Log-Hygiene-Audit
    if ((!isset($sentinelAuditRegistered) || !$sentinelAuditRegistered)
        && (!Schema::hasTable('system_cronjobs') || !SystemCronjob::where('command', 'system:sentinel-audit')->exists())) {
        Schedule::command('system:sentinel-audit')
            ->everyFifteenMinutes()
            ->withoutOverlapping();
    }
} catch (\Throwable $e) {
    // Fange DB-Verbindungsfehler oder ähnliche globale Ausnahmen ab (ignoriere lokale Host-CLI Aufrufe außerhalb des Docker-Netzwerks)
    try {
        $isHostDockerDnsMismatch = class_exists(\App\Services\System\SystemErrorLogger::class)
            && \App\Services\System\SystemErrorLogger::isTransientLocalDatabaseColdBoot($e);

        if (!$isHostDockerDnsMismatch && in_array('schedule:run', $argv ?? [], true)) {
            \Illuminate\Support\Facades\Cache::put('scheduler_last_exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => now()->toDateTimeString(),
            ], now()->addMinutes(60));

            if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                \App\Services\System\SystemErrorLogger::recordException(
                    $e,
                    'scheduler',
                    'scheduler:boot_exception',
                    'Scheduler Boot-Fehler'
                );
            } else {
                \Illuminate\Support\Facades\Log::error("Scheduler globaler Boot-Fehler: " . $e->getMessage());
            }
        }
    } catch (\Throwable $innerEx) {
        // Falls DB/Cache blockiert
    }
}

// Ausfallsicherer Heartbeat-Fallback für das Health-Dashboard:
// Stellt sicher, dass das Lebenszeichen immer geschrieben wird, falls in der DB noch kein Job angelegt oder aktiv ist
if (!isset($heartbeatRegistered) || !$heartbeatRegistered) {
    Schedule::command('system:heartbeat')->everyMinute();
}

Artisan::command('workspace:dateitrichter {--all : Verarbeitet alle Dokumente im gesamten Workspace}', function () {
    $this->info('=== STARTE WORKSPACE DATEITRICHTER ===');
    $service = app(\App\Services\AI\Workspace\DateitrichterService::class);
    $all = (bool) $this->option('all');
    $result = $service->processAll($all);
    $this->info($result['message']);
    $this->info("Gefunden: {$result['total_files_found']} | Verarbeitet: {$result['processed_count']} | Verschoben: {$result['moved_count']} | Indiziert: {$result['indexed_count']}");
})->purpose('Analysiert und sortiert Workspace-Dokumente in Berufsleben, Dokumente und Gesundheit ein');

Artisan::command('product:assortment {slug? : Produkt-Slug oder SKU} {--enable : Produkt im Sortiment aktivieren} {--disable : Produkt im Sortiment pausieren}', function (?string $slug = null) {
    if (!$slug) {
        $rows = \App\Models\Product\Product::orderBy('created_at')->get()->map(fn ($p) => [
            $p->sku ?: '-',
            $p->slug,
            $p->name,
            $p->status,
            (int) $p->quantity,
            $p->shipping_class ?: ($p->type === 'physical' ? 'dhl_standard' : $p->type),
        ])->toArray();

        $this->table(['SKU', 'Slug', 'Name', 'Status', 'Bestand', 'Versand/Typ'], $rows);
        return;
    }

    $product = \App\Models\Product\Product::where('slug', $slug)
        ->orWhere('sku', strtoupper(trim($slug)))
        ->first();

    if (!$product) {
        $this->error("Produkt '{$slug}' wurde nicht gefunden.");
        return;
    }

    if ($this->option('enable')) {
        $product->status = 'active';
    } elseif ($this->option('disable')) {
        $product->status = 'draft';
    } else {
        $product->status = $product->status === 'active' ? 'draft' : 'active';
    }

    $product->save();
    \App\Models\Marketing\MarketingLandingPage::syncForProduct($product);
    $this->info("Produkt [{$product->sku}] {$product->name} ({$product->slug}) -> Status: {$product->status}");
})->purpose('Listet alle Produkte samt Artikelnummer (SKU) auf oder schaltet ein Produkt im Sortiment aktiv/pausiert');
