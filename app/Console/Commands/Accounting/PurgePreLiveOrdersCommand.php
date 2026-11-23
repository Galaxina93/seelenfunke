<?php

namespace App\Console\Commands\Accounting;

use App\Services\Accounting\PreLiveOrderAndInvoiceCleanupService;
use Illuminate\Console\Command;
use RuntimeException;

class PurgePreLiveOrdersCommand extends Command
{
    protected $signature = 'accounting:purge-prelive-orders
                            {--dry-run : Zeigt nur die gefundenen Testbestellungen und Archivrechnungen ohne Löschung an}';

    protected $description = 'Bereinigt vor dem Livegang alle Testbestellungen und Archivrechnungen für einen sauberen Start ab RE-YYYY-1000 (GoBD-geschützt sobald shop_is_live=true in Produktion).';

    public function handle(PreLiveOrderAndInvoiceCleanupService $cleanupService): int
    {
        try {
            $cleanupService->assertPreLiveSafe();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $counts = $cleanupService->inspectCounts();

        if ($this->option('dry-run')) {
            $this->info('Dry-Run Vorab-Prüfung (keine Daten gelöscht):');
            $this->table(
                ['Bereich', 'Aktueller Stand'],
                [
                    ['Bestellungen (inkl. Papierkorb)', (string) $counts['orders_count']],
                    ['Ausgangsrechnungen / Stornos / Gutschriften', (string) $counts['invoices_count']],
                    ['Archivrechnungs-Dateien (buchhaltung/invoices)', (string) $counts['archive_files_count']],
                ]
            );

            return self::SUCCESS;
        }

        $summary = $cleanupService->purge();

        $this->info('Vorab-Bereinigung für sauberen Buchhaltungs-Start erfolgreich abgeschlossen.');
        $this->table(
            ['Kennzahl', 'Ergebnis'],
            [
                ['Gelöschte Testbestellungen', (string) $summary['deleted_orders']],
                ['Gelöschte Bestellpositionen', (string) $summary['deleted_order_items']],
                ['Gelöschte Ausgangsrechnungen (DB)', (string) $summary['deleted_invoices']],
                ['Gelöschte Archivrechnungen (PDF/XML)', (string) $summary['deleted_archive_files']],
                ['Gelöschte Bestell-Artefakte (Snapshots/Labels)', (string) $summary['deleted_order_files']],
                ['Geschützte Sonderausgaben (AccountingSeeder)', (string) $summary['preserved_special_issues'] . ' (unangetastet)'],
                ['Geschützte Fixkosten/Verträge (AccountingSeeder)', (string) $summary['preserved_cost_items'] . ' (unangetastet)'],
                ['Nächste Rechnungsnummer (#1 bei Livegang)', (string) ($summary['next_invoice_number'] ?? 'RE-' . date('Y') . '-1000')],
            ]
        );

        return self::SUCCESS;
    }
}
