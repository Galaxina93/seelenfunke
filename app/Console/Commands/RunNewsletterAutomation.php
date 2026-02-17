<?php

namespace App\Console\Commands;

use App\Models\FunkiLog;
use App\Models\FunkiNewsletter;
use App\Services\FunkiBotService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunNewsletterAutomation extends Command
{
    protected $signature = 'funki:send-newsletters';
    protected $description = 'Prüft auf anstehende Newsletter und versendet diese.';

    public function handle(FunkiBotService $service)
    {
        $today = Carbon::today();
        $year = $today->year;

        $this->info("Prüfe Newsletter für: " . $today->format('d.m.Y'));

        $templates = FunkiNewsletter::where('is_active', true)->get();

        foreach ($templates as $template) {
            // Service nutzen zur Datumsberechnung
            $targetDate = $service->getHolidayDate($template->target_event_key, $year);
            $sendDate = $targetDate->copy()->subDays($template->days_offset);

            if ($sendDate->isSameDay($today)) {
                $this->info("Treffer! Versende: {$template->title}");

                // 1. FunkiLog STARTEN
                $log = FunkiLog::start(
                    'newsletter:send',
                    "Newsletter-Kampagne: {$template->title}",
                    'system'
                );

                try {
                    // 2. Service nutzen (gibt Anzahl zurück)
                    $count = $service->sendBatch($template);

                    // 3. FunkiLog ERFOLGREICH BEENDEN
                    $log->finish(
                        'success',
                        "Der Newsletter wurde erfolgreich an $count Abonnenten versendet.",
                        ['template_id' => $template->id, 'sent_count' => $count]
                    );

                    $this->info("-> $count Mails versendet.");

                } catch (\Exception $e) {
                    // 4. FunkiLog FEHLER
                    $log->finish(
                        'error',
                        'Fehler beim Versand: ' . $e->getMessage()
                    );
                    $this->error($e->getMessage());
                }
            }
        }
    }
}
