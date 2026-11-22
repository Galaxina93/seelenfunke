<?php

namespace App\Console\Commands;

use App\Models\Admin\Admin;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateTaxExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-tax-export {--month=} {--quarter=} {--year=} {--force}';

    /**
     * Aliases so both generate-tax-export and funki:generate-tax-export work.
     *
     * @var array<int, string>
     */
    protected $aliases = ['funki:generate-tax-export'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generiert vollautomatisch das vollständige Steuer- & DATEV-Archiv (EXTF CSV, Anlagenspiegel, UStVA PDF & Belege) für den Tresor.';

    /**
     * Execute the console command.
     */
    public function handle(FinancialService $financialService): int
    {
        $this->info('Starte automatisierte Steuer- & DATEV-Auswertung...');

        $targetDate = Carbon::now()->subMonth();
        $year = (int) ($this->option('year') ?: $targetDate->year);
        $quarterOpt = $this->option('quarter');

        if ($year < 2000 || $year > 2100) {
            $this->error('Ungültiges Jahr angegeben.');
            return Command::FAILURE;
        }

        if ($quarterOpt !== null && $quarterOpt !== '') {
            $quarter = (int) $quarterOpt;
            if ($quarter < 1 || $quarter > 4) {
                $this->error('Ungültiges Quartal (1-4) angegeben.');
                return Command::FAILURE;
            }
            $periodStart = Carbon::createFromDate($year, ($quarter - 1) * 3 + 1, 1)->startOfMonth();
            $mode = 'quarter';
            $periodNum = $quarter;
        } else {
            $month = (int) ($this->option('month') ?: $targetDate->month);
            if ($month < 1 || $month > 12) {
                $this->error('Ungültiger Monat (1-12) angegeben.');
                return Command::FAILURE;
            }
            $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $mode = 'month';
            $periodNum = $month;
        }

        if ($periodStart->isFuture() && !$this->option('force')) {
            $this->error('Abbruch: Der angegebene Zeitraum liegt in der Zukunft. Nutze --force um dies zu umgehen.');
            return Command::FAILURE;
        }

        $adminId = $financialService->resolveMandantAdminId() ?: Admin::value('id');
        if (!$adminId) {
            $this->error('Kein Administrator im System gefunden. Abbruch.');
            return Command::FAILURE;
        }

        $data = $financialService->getUstvaPeriodData($year, $periodNum, $mode, $adminId);
        $this->line("Zeitraum: {$data['month_name']} {$year}");

        if (($data['missing_receipts_count'] ?? 0) > 0 && !$this->option('force')) {
            $this->warn("Hinweis: Es fehlen {$data['missing_receipts_count']} gewerbliche Belege für diesen Zeitraum.");
        }

        $result = $financialService->generateDatevExportZip($data);

        $this->info("Erfolgreich! Das Steuer-Archiv {$result['zip_name']} liegt nun im Tresor.");
        return Command::SUCCESS;
    }

    /**
     * Delegiert den Aufbau des DATEV-Buchungsstapels inklusive CSV-Formula-Injection-Schutz
     * zentral an den FinancialService.
     */
    private function buildDatevCsv($data): string
    {
        return app(FinancialService::class)->buildDatevCsv((array) $data);
    }
}
