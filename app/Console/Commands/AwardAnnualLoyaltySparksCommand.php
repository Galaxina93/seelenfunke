<?php

namespace App\Console\Commands;

use App\Services\Gamification\GamificationService;
use Illuminate\Console\Command;

class AwardAnnualLoyaltySparksCommand extends Command
{
    protected $signature = 'gamification:award-loyalty-sparks';

    protected $description = 'Prüft täglich Geburtstage und Manufaktur-Jahrestage aktiver Seelenfunken-Kunden und schreibt jeweils +50 Treue-Funken inkl. E-Mail gut.';

    public function handle(GamificationService $gameService): int
    {
        try {
            $result = $gameService->processAllAnnualLoyaltySparks(true);
            $bdayCount = (int) ($result['birthday_awarded_count'] ?? 0);
            $annivCount = (int) ($result['anniversary_awarded_count'] ?? 0);

            $this->info(sprintf(
                'Seelenfunken Treue-Lauf abgeschlossen: %d Geburtstags-Boni, %d Jahrestags-Boni vergeben.',
                $bdayCount,
                $annivCount
            ));

            if (($bdayCount > 0 || $annivCount > 0) && class_exists(\App\Models\System\SystemLog::class)) {
                \App\Models\System\SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'gamification:loyalty_sparks_awarded',
                    'title' => 'Seelenfunken Treue-Bonus vergeben',
                    'message' => "Täglicher Treue-Lauf: {$bdayCount} Geburtstags-Boni (+50 ✨) und {$annivCount} Jahrestags-Boni (+50 ✨) gutgeschrieben.",
                    'status' => 'success',
                    'payload' => $result,
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Fehler beim Seelenfunken Treue-Lauf: ' . $e->getMessage());
            if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
                \App\Services\System\SystemErrorLogger::recordException(
                    $e,
                    'scheduler',
                    'scheduler:cronjob_failed:gamification:award-loyalty-sparks',
                    'Cronjob Fehler: Seelenfunken Treue-Bonus'
                );
            }
            return self::FAILURE;
        }
    }
}
