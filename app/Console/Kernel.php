<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use App\Models\Funki\FunkiLog;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 1. Herzschlag für das System-Monitoring (jede Minute)
        // Zeigt dem Dashboard, dass Cronjobs auf dem Server generell laufen
        $schedule->call(function () {
            Cache::put('scheduler_last_run', now());
        })->everyMinute();

        // 2. Das echte Datenbank-Backup (z.B. über Spatie Laravel Backup)
        $schedule->command('backup:run --only-db')
            ->dailyAt('03:00')
            ->onSuccess(function () {
                // Ampel wird nur grün, wenn das Backup fehlerfrei durchlief
                Cache::put('backup_last_run', now());
            })
            ->onFailure(function () {
                // Bei Absturz: Sofort einen Error-Log aufs Dashboard feuern
                if (class_exists(FunkiLog::class)) {
                    FunkiLog::create([
                        'type' => 'system',
                        'action_id' => 'system:backup_failed',
                        'title' => 'Backup fehlgeschlagen',
                        'message' => 'Das nächtliche Datenbank-Backup konnte nicht erfolgreich erstellt werden. Bitte Log prüfen!',
                        'status' => 'error',
                        'started_at' => now(),
                        'finished_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
