<?php

namespace App\Console\Commands;

use App\Services\AI\KnowledgeReportSyncService;
use Illuminate\Console\Command;

class AiSyncKnowledgeReportsCommand extends Command
{
    protected $signature = 'ai:sync-knowledge-reports
        {--force : Erzwingt die Neu-Indexierung aller Berichte unabhängig vom SHA-256-Hash}
        {--path= : Optionales Quellverzeichnis statt base_path("Abschlussberichte")}';

    protected $description = 'Synchronisiert alle Markdown-Berichte aus Abschlussberichte/ per SHA-256-Delta-Check inklusive Kategorien und Auto-Tagging in AiKnowledgeBase.';

    public function handle(KnowledgeReportSyncService $syncService): int
    {
        $customPath = $this->option('path');
        $force = (bool) $this->option('force');

        $stats = $syncService->sync(
            is_string($customPath) && trim($customPath) !== '' ? $customPath : null,
            $force
        );

        $this->info(sprintf(
            'Abschlussberichte-Sync abgeschlossen (%s ms): %d gescannt | %d neu | %d aktualisiert | %d unverändert übersprungen | %d veraltet entfernt.',
            number_format($stats['elapsed_ms'], 2, '.', ''),
            $stats['scanned'],
            $stats['created'],
            $stats['updated'],
            $stats['skipped_unchanged'],
            $stats['deleted_stale']
        ));

        return self::SUCCESS;
    }
}
