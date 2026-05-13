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

try {
    if (Schema::hasTable('system_cronjobs')) {
        $cronjobs = SystemCronjob::where('is_active', true)->get();

        foreach ($cronjobs as $job) {
            // SICHERHEITSSPERRE FÜR DAEMONS:
            // Commands wie 'reverb:start' oder 'queue:work' laufen unendlich.
            // Auch mit runInBackground() wartet der Mittwald-Docker-Container auf das Ende ALLER Kind-Prozesse.
            // Wenn diese hier geladen werden, bleibt der gesamte schedule:run hängen!
            $daemonCommands = ['reverb:start', 'queue:work', 'queue:listen', 'websockets:serve'];
            
            $commandBase = explode(' ', $job->command)[0];
            if (in_array($commandBase, $daemonCommands)) {
                // Diese Befehle dürfen NIEMALS über den Laravel Scheduler laufen.
                // Sie MÜSSEN als eigenständige Cronjobs im Mittwald Panel angelegt werden.
                if (class_exists(SystemLog::class)) {
                    SystemLog::create([
                        'type' => 'system',
                        'action_id' => 'system:cronjob_blocked',
                        'title' => 'Daemon-Sperre: ' . $job->command,
                        'message' => 'Langlaufende Prozesse dürfen nicht über den internen Scheduler laufen, da sie sonst den Server blockieren. Bitte als eigenen Mittwald-Cronjob anlegen!',
                        'status' => 'warning',
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                }
                
                // Job automatisch deaktivieren, um zukünftige Hänger zu vermeiden
                $job->update(['is_active' => false, 'status' => 'error']);
                continue;
            }

            $event = Schedule::command($job->command, $job->parameters ? explode(' ', $job->parameters) : []);

            if (method_exists($event, $job->schedule)) {
                $event->{$job->schedule}();
            } else {
                $event->cron($job->schedule);
            }
            
            // Verhindert Überschneidungen bei normalen Jobs
            $event->runInBackground()->withoutOverlapping();

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
        }
    }
} catch (\Throwable $e) {
    // If DB is not available yet, do not crash artisan
}
