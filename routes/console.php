<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Cache;
use App\Models\Funki\FunkiLog;

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

// Täglich um 08:00 Uhr prüfen und Newsletter senden
Schedule::command('marketing:send-newsletters')->dailyAt('08:00');

Schedule::command('funki:notify')->everyMinute();

// UStVA Autopilot - Läuft am 5. jeden Monats und generiert den Steuer-Export des Vormonats
Schedule::command('funki:generate-tax-export')->monthlyOn(5, '02:00');

// System-Herzschlag für das Health-Dashboard (jede Minute)
Schedule::call(function () {
    Cache::put('scheduler_last_run', now());
})->everyMinute();

// Das echte Datenbank-Backup (z.B. über Spatie Laravel Backup)
Schedule::command('backup:run --only-db')
    ->dailyAt('03:00')
    ->onSuccess(function () {
        // Ampel auf dem Dashboard wird grün
        Cache::put('backup_last_run', now());
    })
    ->onFailure(function () {
        // Bei Absturz: Sofort einen Error-Log aufs Dashboard feuern
        if (class_exists(FunkiLog::class)) {
            FunkiLog::create([
                'type' => 'system',
                'action_id' => 'system:backup_failed',
                'title' => 'Backup fehlgeschlagen',
                'message' => 'Das nächtliche Datenbank-Backup konnte nicht erfolgreich erstellt werden. Bitte Server-Logs prüfen!',
                'status' => 'error',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }
    });
