<?php

namespace App\Console\Commands\Shop;

use App\Models\System\SystemLog;
use App\Services\CartFileCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOrphanedUploadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:cleanup-orphaned-uploads
                            {--hours=48 : Mindestalter in Stunden fuer verwaiste Uploads und Snapshots}
                            {--cart-days=14 : Mindestalter in Tagen fuer verlassene Warenkoerbe}
                            {--dry-run : Nur simulieren ohne Dateien oder Warenkoerbe zu loeschen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bereinigt verwaiste Uploads, Snapshots und abgelaufene Warenkoerbe ohne Bestellung';

    /**
     * Execute the console command.
     */
    public function handle(CartFileCleanupService $cleanupService): int
    {
        $hours = (int) $this->option('hours');
        $cartDays = (int) $this->option('cart-days');
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("=== BEREINIGUNG VERWAISTER UPLOADS & WARENKÖRBE ===");
        if ($isDryRun) {
            $this->warn("MODUS: DRY-RUN (Simulation - keine Dateien werden physisch gelöscht)");
        }

        // 1. Verlassene Warenkörbe bereinigen
        $this->line("1. Prüfe abgelaufene Warenkörbe (älter als {$cartDays} Tage)...");
        $deletedCarts = 0;
        if (!$isDryRun) {
            $deletedCarts = $cleanupService->cleanupAbandonedCarts($cartDays);
            $this->info("   -> {$deletedCarts} abgelaufene Warenkörbe gelöscht.");
        } else {
            $this->info("   -> [Dry-Run] Überspringe Warenkorb-Löschung.");
        }

        // 2. Verwaiste Dateien & Snapshots scannen und löschen
        $this->line("2. Scanne verwaiste Dateien (älter als {$hours} Stunden)...");
        $fileStats = $cleanupService->cleanupOrphanedFiles($hours, $isDryRun);

        $mbFreed = round($fileStats['bytes_freed'] / (1024 * 1024), 2);

        $this->table(
            ['Metrik', 'Wert'],
            [
                ['Gescannte Dateien', $fileStats['scanned']],
                ['Aktive/Geschützte Dateien (übersprungen)', $fileStats['skipped_active']],
                ['Verwaiste Dateien gelöscht', $fileStats['deleted']],
                ['Freigegebener Speicherplatz', "{$mbFreed} MB ({$fileStats['bytes_freed']} Bytes)"],
                ['Abgelaufene Warenkörbe entfernt', $deletedCarts],
                ['Modus', $isDryRun ? 'DRY-RUN' : 'LIVE'],
            ]
        );

        // 3. System-Log schreiben
        if (class_exists(SystemLog::class) && !$isDryRun) {
            try {
                SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'shop:cleanup_orphaned_uploads',
                    'title' => 'Dateibereinigung: Verwaiste Uploads & Warenkörbe',
                    'message' => "Erfolgreich {$fileStats['deleted']} verwaiste Dateien ({$mbFreed} MB) und {$deletedCarts} alte Warenkörbe bereinigt.",
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('SystemLog konnte für Dateibereinigung nicht erstellt werden: ' . $e->getMessage());
            }
        }

        $this->info("Bereinigung erfolgreich abgeschlossen.");
        return Command::SUCCESS;
    }
}
