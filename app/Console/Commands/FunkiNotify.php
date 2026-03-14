<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiSupportService;
use Illuminate\Support\Facades\Cache;

class FunkiNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'funki:notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sendet Push-Benachrichtigungen basierend auf Funkis Empfehlungen';

    /**
     * Execute the console command.
     */
    public function handle(AiSupportService $service)
    {
        $data = $service->getUltimateCommand();

        // 1. FIX: Daten aus dem richtigen Unter-Array 'recommendation' ziehen
        $rec = $data['recommendation'] ?? [];
        $title = $rec['title'] ?? 'Funki Info';
        $message = $rec['message'] ?? 'Es gibt Neuigkeiten.';
        $score = $rec['score'] ?? 0;

        // 2. Caching Logik (Nur bei Änderung oder High Priority)
        $currentHash = md5($title . $message);
        $lastHash = Cache::get('funki_last_push_hash');

        if ($lastHash !== $currentHash || $score >= 500) {

            // Hash aktualisieren, damit es nicht spammt
            Cache::put('funki_last_push_hash', $currentHash, now()->addHours(24));

            // =========================================================================
            // HIER KOMMT DEIN BESTEHENDER CODE REIN (z.B. UserDevice / Firebase Push)
            // =========================================================================

            $this->info("Funki sendet Push: {$title}");

        } else {
            $this->line("Keine neue wichtige Benachrichtigung (Score zu niedrig oder bereits gesendet).");
        }

        return Command::SUCCESS;
    }
}
