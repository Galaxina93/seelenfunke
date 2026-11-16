<?php

namespace App\Console\Commands\Ai;

use App\Services\AI\Workspace\AbschlussberichteIndexerService;
use Illuminate\Console\Command;

class SyncAbschlussberichteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:sync-abschlussberichte {--force : Alle Berichte unabhängig vom Hash neu indexieren}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Indexiert alle Markdown-Abschlussberichte aus Abschlussberichte/ blitzschnell in die KI-Wissensdatenbank.';

    /**
     * Execute the console command.
     */
    public function handle(AbschlussberichteIndexerService $indexer): int
    {
        $this->info('Starte Indexierung aller Abschlussberichte in die KI-Wissensdatenbank...');
        $force = (bool) $this->option('force');

        $startTime = microtime(true);

        $result = $indexer->syncAll(
            force: $force,
            progressCallback: function (string $msg) {
                if ($this->output->isVerbose()) {
                    $this->line("  {$msg}");
                }
            }
        );

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->info("Indexierung abgeschlossen in {$durationMs} ms!");
        $this->table(
            ['Metrik', 'Anzahl'],
            [
                ['Gefundene Markdown-Dateien', $result['total']],
                ['Neu indexiert', $result['created']],
                ['Aktualisiert', $result['updated']],
                ['Unverändert (übersprungen)', $result['skipped']],
                ['Kategorien berührt', $result['categories']],
            ]
        );

        return self::SUCCESS;
    }
}
