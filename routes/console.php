<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

// Täglich um 09:00 Uhr prüfen und Gutscheine versenden
Schedule::command('marketing:send-vouchers')->dailyAt('09:00');

Schedule::command('funki:notify')->everyMinute();

// UStVA Autopilot - Läuft am 5. jeden Monats und generiert den Steuer-Export des Vormonats
Schedule::command('funki:generate-tax-export')->monthlyOn(5, '02:00');


// System-Herzschlag für das Health-Dashboard (jede Minute)
Schedule::call(function () {
    Cache::put('scheduler_last_run', now());
})->everyMinute();

// Dummy für das Backup (Ersetze dies später durch deinen echten Backup-Job)
Schedule::call(function () {
    Cache::put('backup_last_run', now());
})->dailyAt('03:00');
