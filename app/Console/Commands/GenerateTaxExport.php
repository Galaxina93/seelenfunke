<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Order\Order;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Financial\FinanceGroup;
use App\Models\Admin\Admin;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateTaxExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'funki:generate-tax-export {--month=} {--year=} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generiert vollautomatisch die UStVA (DATEV CSV, Belege & PDF Report) für den Tresor.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Funki startet die automatisierte Umsatzsteuer-Auswertung...');

        // Wenn keine Parameter übergeben wurden, nehmen wir standardmäßig den Vormonat
        $targetDate = Carbon::now()->subMonth();
        $month = $this->option('month') ?: $targetDate->month;
        $year = $this->option('year') ?: $targetDate->year;

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $deadline = Carbon::createFromDate($year, $month, 10)->addMonth();

        $this->line("Zeitraum: {$startDate->locale('de')->monthName} {$year}");

        if ($startDate->isFuture() && !$this->option('force')) {
            $this->error('Abbruch: Der angegebene Monat liegt in der Zukunft. Nutze --force um dies zu umgehen.');
            return Command::FAILURE;
        }

        // Da Cronjobs keinen eingeloggten User haben, holen wir den Haupt-Admin (Shop-Besitzer)
        $admin = Admin::first();
        if (!$admin) {
            $this->error('Kein Administrator im System gefunden. Abbruch.');
            return Command::FAILURE;
        }

        $adminId = $admin->id;

        // 1. UMSÄTZE (Einnahmen)
        $orders = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
            ->get();

        $revenueGross = $orders->sum('total_price') / 100;
        $vatCollected = $orders->sum('tax_amount') / 100;
        $revenueNet = $revenueGross - $vatCollected;

        // 2. AUSGABEN (Gewerblich)
        $businessSpecials = FinanceSpecialIssue::where('admin_id', $adminId)
            ->where('is_business', true)
            ->whereBetween('execution_date', [$startDate, $endDate])
            ->get();

        $businessFixed = collect();
        $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            foreach ($group->items->where('is_business', true) as $item) {
                $startMonth = $item->first_payment_date->month;
                $interval = $item->interval_months ?: 1;

                $diff = ($month - $startMonth) + (($year - $item->first_payment_date->year) * 12);
                if ($diff >= 0 && ($diff % $interval) === 0) {
                    $businessFixed->push($item);
                }
            }
        }

        $totalExpensesGross = abs($businessSpecials->sum('amount')) + abs($businessFixed->sum('amount'));

        // Vorsteuer & Netto berechnen
        $vatPaid = 0;
        $expensesNet = 0;

        foreach ($businessSpecials as $s) {
            $rate = $s->tax_rate ?? 19;
            $amt = abs($s->amount);
            $net = $amt / (1 + ($rate / 100));
            $vatPaid += $amt - $net;
            $expensesNet += $net;
        }

        foreach ($businessFixed as $f) {
            $amt = abs($f->amount);
            $net = $amt / 1.19;
            $vatPaid += $amt - $net;
            $expensesNet += $net;
        }

        $profit = $revenueNet - $expensesNet;
        $zahllast = $vatCollected - $vatPaid;

        // 3. BELEGE PRÜFEN
        $missingReceipts = 0;
        foreach ($businessSpecials as $s) {
            if (empty($s->file_paths)) $missingReceipts++;
        }
        foreach ($businessFixed as $f) {
            if (empty($f->contract_file_path)) $missingReceipts++;
        }

        if ($missingReceipts > 0 && !$this->option('force')) {
            $this->warn("Achtung: Es fehlen {$missingReceipts} gewerbliche Belege für diesen Monat!");
            $this->warn("Das Archiv wird trotzdem erstellt, aber die Dokumentation ist unvollständig.");
        }

        $data = [
            'month_name' => $startDate->locale('de')->monthName,
            'month_number' => str_pad($month, 2, '0', STR_PAD_LEFT),
            'year' => $year,
            'revenue_gross' => $revenueGross,
            'revenue_net' => $revenueNet,
            'vat_collected' => $vatCollected,
            'expenses_gross' => $totalExpensesGross,
            'expenses_net' => $expensesNet,
            'vat_paid' => $vatPaid,
            'zahllast' => $zahllast,
            'profit' => $profit,
            'raw_orders' => $orders,
            'raw_specials' => $businessSpecials,
            'raw_fixed' => $businessFixed,
            'deadline' => $deadline
        ];

        // 4. EXPORT GENERIEREN
        $this->info("Erstelle Dateien für den Datentresor...");

        $monthStr = $data['month_number'];
        $tempDir = storage_path("app/temp_export_{$monthStr}_{$year}");
        if (!File::exists($tempDir)) File::makeDirectory($tempDir, 0755, true);

        // CSV
        $csvPath = $tempDir . "/EXTF_Buchungsstapel_{$monthStr}_{$year}.csv";
        $csvContent = $this->buildDatevCsv($data);
        File::put($csvPath, mb_convert_encoding($csvContent, 'Windows-1252', 'UTF-8'));

        // PDF
        $pdfPath = $tempDir . "/UStVA_Report_{$monthStr}_{$year}.pdf";
        $pdf = Pdf::loadView('global.pdf.ustva_report', ['data' => $data]);
        $pdf->save($pdfPath);

        // Belege
        $receiptsDir = $tempDir . "/Belege";
        File::makeDirectory($receiptsDir, 0755, true);

        foreach ($businessSpecials as $special) {
            if (!empty($special->file_paths)) {
                foreach ($special->file_paths as $idx => $path) {
                    $source = storage_path('app/public/' . $path);
                    if (File::exists($source)) {
                        $ext = pathinfo($source, PATHINFO_EXTENSION);
                        $filename = "Ausgabe_" . date('Y-m-d', strtotime($special->execution_date)) . "_" . Str::slug($special->title) . "_{$idx}.{$ext}";
                        File::copy($source, $receiptsDir . '/' . $filename);
                    }
                }
            }
        }

        foreach ($businessFixed as $fixed) {
            if (!empty($fixed->contract_file_path)) {
                $source = storage_path('app/public/' . $fixed->contract_file_path);
                if (File::exists($source)) {
                    $ext = pathinfo($source, PATHINFO_EXTENSION);
                    $filename = "Fixkosten_" . Str::slug($fixed->name) . ".{$ext}";
                    File::copy($source, $receiptsDir . '/' . $filename);
                }
            }
        }

        // Zippen
        Storage::disk('local')->makeDirectory('tax_exports');
        $zipName = "TaxExport_{$year}_{$monthStr}.zip";
        $zipPath = storage_path("app/tax_exports/{$zipName}");

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFile($csvPath, basename($csvPath));
            $zip->addFile($pdfPath, basename($pdfPath));

            $files = File::files($receiptsDir);
            foreach ($files as $file) {
                $zip->addFile($file->getPathname(), 'Belege/' . $file->getFilename());
            }
            $zip->close();
        }

        File::deleteDirectory($tempDir);

        $this->info("✅ Erfolgreich! Das UStVA Archiv {$zipName} liegt nun sicher im Tresor.");
        return Command::SUCCESS;
    }

    private function buildDatevCsv($data)
    {
        $csv = '"EXTF";700;21;"Buchungsstapel";9;"";"";"";"";"";"1";"";"";"";"";"";"";"";"";"";"";"";"";"";""' . "\n";
        $csv .= '"Umsatz (ohne Soll/Haben-Kz)";"Soll/Haben-Kennzeichen";"Konto";"Gegenkonto (ohne BU-Schlüssel)";"Belegdatum";"Belegfeld 1";"Buchungstext"' . "\n";

        foreach ($data['raw_orders'] as $order) {
            $amount = number_format($order->total_price / 100, 2, ',', '');
            $date = $order->created_at->format('dm');
            $text = "Bestellung " . $order->order_number;
            $csv .= '"'.$amount.'";"S";"1200";"8400";"'.$date.'";"'.$order->order_number.'";"'.$text.'"' . "\n";
        }

        foreach ($data['raw_specials'] as $special) {
            $amount = number_format(abs($special->amount), 2, ',', '');
            $date = Carbon::parse($special->execution_date)->format('dm');
            $text = str_replace('"', '""', $special->title);
            $beleg = $special->invoice_number ?? 'Beleg';
            $csv .= '"'.$amount.'";"H";"4900";"1200";"'.$date.'";"'.$beleg.'";"'.$text.'"' . "\n";
        }

        foreach ($data['raw_fixed'] as $fixed) {
            $amount = number_format(abs($fixed->amount), 2, ',', '');
            $date = "28" . str_pad($data['month_number'], 2, '0', STR_PAD_LEFT);
            $text = str_replace('"', '""', $fixed->name);
            $csv .= '"'.$amount.'";"H";"4900";"1200";"'.$date.'";"Fixkosten";"'.$text.'"' . "\n";
        }

        return $csv;
    }
}
