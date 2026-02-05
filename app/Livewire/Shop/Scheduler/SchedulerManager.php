<?php

namespace App\Livewire\Shop\Scheduler;

use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class SchedulerManager extends Component
{
    public $tasks = [
        [
            'id' => 'cache:clear',
            'name' => 'System-Cache bereinigen',
            'description' => 'Leert den Anwendungs- und Konfigurations-Cache für maximale Performance.',
            'schedule' => 'Täglich, 03:00 Uhr',
            'last_run' => 'Vor 4 Stunden',
            'status' => 'success'
        ],
        [
            'id' => 'orders:cleanup',
            'name' => 'Warenkorb-Bereinigung',
            'description' => 'Löscht unbezahlte, verwaiste Warenkörbe, die älter als 30 Tage sind.',
            'schedule' => 'Jede Stunde',
            'last_run' => 'Vor 12 Minuten',
            'status' => 'success'
        ],
        [
            'id' => 'newsletter:send',
            'name' => 'Newsletter-Versand',
            'description' => 'Versendet die Warteschlange der geplanten Marketing-E-Mails.',
            'schedule' => 'Alle 15 Minuten',
            'last_run' => 'Vor 2 Minuten',
            'status' => 'running'
        ],
        [
            'id' => 'quotes:expire',
            'name' => 'Angebots-Gültigkeit prüfen',
            'description' => 'Markiert abgelaufene Angebote automatisch als "Archiviert".',
            'schedule' => 'Täglich, 00:05 Uhr',
            'last_run' => 'Gestern',
            'status' => 'success'
        ]
    ];

    public function runTask($taskId)
    {
        $taskInfo = collect($this->tasks)->firstWhere('id', $taskId);

        // 1. Log-Eintrag erstellen (Start)
        $log = \App\Models\SchedulerLog::create([
            'task_id' => $taskId,
            'task_name' => $taskInfo['name'],
            'started_at' => now(),
            'status' => 'running'
        ]);

        try {
            // 2. Task ausführen und Output abfangen
            Artisan::call($taskId);
            $output = Artisan::output();

            // 3. Log aktualisieren (Erfolg)
            $log->update([
                'finished_at' => now(),
                'status' => 'success',
                'output' => $output
            ]);

            $this->dispatch('notify', ['type' => 'success', 'message' => "Erfolgreich ausgeführt."]);
        } catch (\Exception $e) {
            // 4. Log aktualisieren (Fehler)
            $log->update([
                'finished_at' => now(),
                'status' => 'error',
                'output' => $e->getMessage()
            ]);

            $this->dispatch('notify', ['type' => 'error', 'message' => "Fehler: " . $e->getMessage()]);
        }
    }

    public function getHistoryProperty()
    {
        return \App\Models\SchedulerLog::latest()->take(10)->get();
    }

    public function render()
    {
        return view('livewire.shop.scheduler.scheduler-manager');
    }
}
