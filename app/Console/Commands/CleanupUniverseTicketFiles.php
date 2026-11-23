<?php

namespace App\Console\Commands;

use App\Models\System\SystemLog;
use App\Services\AI\TicketDeliverableService;
use Illuminate\Console\Command;

class CleanupUniverseTicketFiles extends Command
{
    protected $signature = 'tickets:cleanup-files
        {--archive-days=28 : Aktive Dateien älter als X Tage ins komprimierte Archiv verschieben}
        {--purge-days=180 : Nicht angepinnte Archiv-Dateien älter als X Tage physisch löschen}';

    protected $description = 'Archiviert Ticket-Dateien vollautomatisch nach 4 Wochen (28 Tagen) und bereinigt Altlasten ohne manuellen Aufwand.';

    public function handle(): int
    {
        $archiveDays = max(1, (int) $this->option('archive-days'));
        $purgeDays = max($archiveDays + 1, (int) $this->option('purge-days'));

        $result = TicketDeliverableService::runAutomatedLifecycleCleanup($archiveDays, $purgeDays);

        $summary = sprintf(
            'Ticket-Datei-Autopilot abgeschlossen: %d archiviert (>%dd), %d Alt-Archive gelöscht (>%dd), %d Waisen bereinigt (%s Speicherplatz gewonnen).',
            $result['archived_count'],
            $archiveDays,
            $result['purged_archive_count'],
            $purgeDays,
            $result['orphan_deleted_count'],
            TicketDeliverableService::formatBytes((int) $result['bytes_saved'])
        );

        $this->info($summary);

        if (
            ($result['archived_count'] > 0 || $result['purged_archive_count'] > 0 || $result['orphan_deleted_count'] > 0)
            && class_exists(SystemLog::class)
        ) {
            try {
                SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'tickets:cleanup-files',
                    'title' => 'Ticket-Datei-Autopilot (4-Wochen-Archiv)',
                    'message' => $summary,
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            } catch (\Throwable) {
            }
        }

        return self::SUCCESS;
    }
}
