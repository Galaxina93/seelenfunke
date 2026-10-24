<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Order\OrderOrder;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Accounting\AccountingGroup;
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
    protected $signature = 'generate-tax-export {--month=} {--year=} {--force}';

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
    protected $description = 'Generiert vollautomatisch die UStVA (DATEV CSV, Belege & PDF Report) für den Tresor.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starte die automatisierte Umsatzsteuer-Auswertung...');

        // Wenn keine Parameter übergeben wurden, nehmen wir standardmäßig den Vormonat
        $targetDate = Carbon::now()->subMonth();
        $month = (int) ($this->option('month') ?: $targetDate->month);
        $year = (int) ($this->option('year') ?: $targetDate->year);

        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            $this->error('Ungültiger Monat oder ungültiges Jahr angegeben.');
            return Command::FAILURE;
        }

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

        // 1. UMSÄTZE (Einnahmen inkl. Gutschriften & Kleinunternehmer-Prüfung)
        $orders = OrderOrder::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
            ->get();

        $creditNotes = \App\Models\Accounting\AccountingInvoice::with('order')
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('type', ['credit_note', 'cancellation'])
            ->whereIn('status', ['paid', 'cancelled'])
            ->get();

        $taxService = app(\App\Services\Tax\TaxSettingService::class);
        $isYearSmallBusiness = $taxService->isSmallBusinessForYear($year);
        $isMidyearSwitch = $taxService->isMidyearSwitch($year);

        $revenueGross = 0;
        $vatCollected = 0;
        $igErwerbTax = 0;
        $paragraph13bTax = 0;

        foreach ($orders as $order) {
            $total = $order->total_price / 100;
            $tax = $order->tax_amount / 100;

            if ($isYearSmallBusiness) {
                $tax = 0;
            } elseif ($tax == 0 && ($isMidyearSwitch || $order->is_small_business)) {
                $rate = (float) shop_setting('default_tax_rate', 19.0);
                $net = $total / (1 + ($rate / 100));
                $tax = $total - $net;
            }

            $revenueGross += $total;

            if (!empty($order->is_reverse_charge)) {
                $paragraph13bTax += $tax;
            } elseif (!empty($order->is_ig_erwerb)) {
                $igErwerbTax += $tax;
            } else {
                $vatCollected += $tax;
            }
        }

        foreach ($creditNotes as $cn) {
            $total = -abs($cn->total / 100);
            $tax = -abs($cn->tax_amount / 100);

            if ($isYearSmallBusiness) {
                $tax = 0;
            } elseif ($tax == 0 && ($isMidyearSwitch || $cn->is_small_business)) {
                $rate = (float) shop_setting('default_tax_rate', 19.0);
                $net = $total / (1 + ($rate / 100));
                $tax = $total - $net;
            }

            $revenueGross += $total;

            if ($cn->order && !empty($cn->order->is_reverse_charge)) {
                $paragraph13bTax += $tax;
            } elseif ($cn->order && !empty($cn->order->is_ig_erwerb)) {
                $igErwerbTax += $tax;
            } else {
                $vatCollected += $tax;
            }
        }

        $totalTax = $vatCollected + $igErwerbTax + $paragraph13bTax;
        $revenueNet = $revenueGross - $totalTax;

        // 2. AUSGABEN (Gewerblich)
        $businessSpecials = AccountingSpecialIssue::where('admin_id', $adminId)
            ->where('is_business', true)
            ->whereBetween('execution_date', [$startDate, $endDate])
            ->get();

        $businessFixed = collect();
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            if ($group->type === 'income') {
                continue;
            }
            foreach ($group->items->where('is_business', true) as $item) {
                $paymentDate = Carbon::parse($item->first_payment_date);
                $startMonth = $paymentDate->month;
                $startYear = $paymentDate->year;
                $interval = max(1, (int) ($item->interval_months ?: 1));

                if ($startDate->lt(Carbon::createFromDate($startYear, $startMonth, 1)->startOfMonth())) {
                    continue;
                }
                if ($item->last_payment_date && $startDate->gt(Carbon::parse($item->last_payment_date)->endOfMonth())) {
                    continue;
                }

                $diff = ($month - $startMonth) + (($year - $startYear) * 12);
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
            $rate = $s->tax_rate ?? (float) shop_setting('default_tax_rate', 19.0);
            $amt = abs($s->amount);
            if ($rate > 0) {
                $net = $amt / (1 + ($rate / 100));
                $vatPaid += $amt - $net;
                $expensesNet += $net;
            } else {
                $expensesNet += $amt;
            }
        }

        foreach ($businessFixed as $f) {
            $rate = $f->tax_rate ?? (float) shop_setting('default_tax_rate', 19.0);
            $amt = abs($f->amount);
            if ($rate > 0) {
                $net = $amt / (1 + ($rate / 100));
                $vatPaid += $amt - $net;
                $expensesNet += $net;
            } else {
                $expensesNet += $amt;
            }
        }

        if ($isYearSmallBusiness) {
            $vatPaid = 0;
            $expensesNet = $totalExpensesGross;
        }

        $profit = $revenueNet - $expensesNet;
        $zahllast = $totalTax - $vatPaid;

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

        $totalBusinessItems = $businessSpecials->count() + $businessFixed->count();
        $taxId = (string) shop_setting('owner_tax_id', '');
        $proprietor = (string) shop_setting('owner_proprietor', '');
        $progress = $totalBusinessItems > 0
            ? (($totalBusinessItems - $missingReceipts) / $totalBusinessItems) * 70
            : 70;
        if ($taxId !== '' && $proprietor !== '') {
            $progress += 30;
        }

        $data = [
            'month_name' => $startDate->locale('de')->monthName,
            'month_number' => str_pad($month, 2, '0', STR_PAD_LEFT),
            'year' => $year,
            'revenue_gross' => $revenueGross,
            'revenue_net' => $revenueNet,
            'vat_collected' => $vatCollected,
            'ig_erwerb_tax' => $igErwerbTax,
            'paragraph_13b_tax' => $paragraph13bTax,
            'total_tax' => $totalTax,
            'is_small_business_year' => $isYearSmallBusiness,
            'expenses_gross' => $totalExpensesGross,
            'expenses_net' => $expensesNet,
            'vat_paid' => $vatPaid,
            'zahllast' => $zahllast,
            'profit' => $profit,
            'progress' => min(100, (int) round($progress)),
            'order_count' => $orders->count(),
            'expense_count' => $totalBusinessItems,
            'submission_type' => 'Erstübermittlung',
            'company' => [
                'name' => shop_setting('company_name', 'Mein Seelenfunke'),
                'owner' => $proprietor ?: shop_setting('owner_name', ''),
                'street' => trim(shop_setting('company_street', '') . ' ' . shop_setting('company_street_number', '')),
                'city' => trim(shop_setting('company_zip', '') . ' ' . shop_setting('company_city', '')),
                'tax_id' => $taxId ?: 'n.a.',
                'ust_id' => shop_setting('owner_ust_id', 'n.a.'),
            ],
            'raw_orders' => $orders,
            'raw_credits' => $creditNotes,
            'raw_specials' => $businessSpecials,
            'raw_fixed' => $businessFixed,
            'deadline' => $deadline
        ];

        // 4. EXPORT GENERIEREN
        $this->info("Erstelle Dateien für den Datentresor...");

        $monthStr = $data['month_number'];
        $tempDir = storage_path("app/system/temp_export_{$monthStr}_{$year}");
        if (File::exists($tempDir)) {
            File::deleteDirectory($tempDir);
        }
        File::makeDirectory($tempDir, 0755, true);

        // CSV
        $csvPath = $tempDir . "/EXTF_Buchungsstapel_{$monthStr}_{$year}.csv";
        $csvContent = $this->buildDatevCsv($data);
        File::put($csvPath, mb_convert_encoding($csvContent, 'Windows-1252', 'UTF-8'));

        // PDF
        $pdfPath = $tempDir . "/UStVA_Report_{$monthStr}_{$year}.pdf";
        $pdf = Pdf::loadView('global.pdf.ustva_report', ['data' => $data])->setOption('isRemoteEnabled', false);
        $pdf->save($pdfPath);

        // Belege
        $receiptsDir = $tempDir . "/Belege";
        File::makeDirectory($receiptsDir, 0755, true);

        foreach ($businessSpecials as $special) {
            if (!empty($special->file_paths)) {
                foreach ($special->file_paths as $idx => $path) {
                    $cleanPath = ltrim(str_replace('\\', '/', (string) $path), '/');
                    if ($cleanPath === '' || str_contains($cleanPath, '..') || str_contains($cleanPath, "\0") || !str_starts_with($cleanPath, 'buchhaltung/')) {
                        continue;
                    }
                    $source = Storage::disk('local')->exists($cleanPath)
                        ? Storage::disk('local')->path($cleanPath)
                        : (Storage::disk('public')->exists($cleanPath) ? Storage::disk('public')->path($cleanPath) : null);
                    if ($source && File::exists($source) && !is_link($source)) {
                        $ext = preg_replace('/[^a-zA-Z0-9]/', '', (string) pathinfo($source, PATHINFO_EXTENSION)) ?: 'pdf';
                        $filename = "Ausgabe_" . date('Y-m-d', strtotime($special->execution_date)) . "_" . (Str::slug($special->title) ?: 'beleg') . "_{$idx}.{$ext}";
                        File::copy($source, $receiptsDir . '/' . $filename);
                    }
                }
            }
        }

        foreach ($businessFixed as $fixed) {
            if (!empty($fixed->contract_file_path)) {
                $cleanPath = ltrim(str_replace('\\', '/', (string) $fixed->contract_file_path), '/');
                if ($cleanPath === '' || str_contains($cleanPath, '..') || str_contains($cleanPath, "\0") || (!str_starts_with($cleanPath, 'buchhaltung/') && !str_starts_with($cleanPath, 'leitung/'))) {
                    continue;
                }
                $source = Storage::disk('local')->exists($cleanPath)
                    ? Storage::disk('local')->path($cleanPath)
                    : (Storage::disk('public')->exists($cleanPath) ? Storage::disk('public')->path($cleanPath) : null);
                if ($source && File::exists($source) && !is_link($source)) {
                    $ext = preg_replace('/[^a-zA-Z0-9]/', '', (string) pathinfo($source, PATHINFO_EXTENSION)) ?: 'pdf';
                    $filename = "Fixkosten_" . (Str::slug($fixed->name) ?: 'vertrag') . ".{$ext}";
                    File::copy($source, $receiptsDir . '/' . $filename);
                }
            }
        }

        // Zippen
        Storage::disk('local')->makeDirectory('buchhaltung/tax_exports');
        $zipName = "TaxExport_{$year}_{$monthStr}.zip";
        $zipPath = storage_path("app/buchhaltung/tax_exports/{$zipName}");

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

        $sanitizeCsvField = static function (?string $val): string {
            $str = str_replace('"', '""', (string) $val);
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $str = "'" . $str;
            }
            return $str;
        };

        foreach ($data['raw_orders'] as $order) {
            $amount = number_format($order->total_price / 100, 2, ',', '');
            $date = $order->created_at->format('dm');
            $orderNum = $sanitizeCsvField((string) $order->order_number);
            $text = $sanitizeCsvField("Bestellung " . $order->order_number);
            $csv .= '"'.$amount.'";"S";"1200";"8400";"'.$date.'";"'.$orderNum.'";"'.$text.'"' . "\n";
        }

        foreach ($data['raw_credits'] ?? [] as $credit) {
            $amount = number_format(abs($credit->total / 100), 2, ',', '');
            $date = ($credit->invoice_date ?? $credit->created_at ?? now())->format('dm');
            $creditNo = $sanitizeCsvField((string) $credit->invoice_number);
            $text = $sanitizeCsvField("Gutschrift / Storno " . $credit->invoice_number);
            $csv .= '"'.$amount.'";"H";"1200";"8400";"'.$date.'";"'.$creditNo.'";"'.$text.'"' . "\n";
        }

        foreach ($data['raw_specials'] as $special) {
            $amount = number_format(abs($special->amount), 2, ',', '');
            $date = Carbon::parse($special->execution_date)->format('dm');
            $text = $sanitizeCsvField((string) $special->title);
            $beleg = $sanitizeCsvField((string) ($special->invoice_number ?? 'Beleg'));
            $csv .= '"'.$amount.'";"H";"4900";"1200";"'.$date.'";"'.$beleg.'";"'.$text.'"' . "\n";
        }

        foreach ($data['raw_fixed'] as $fixed) {
            $amount = number_format(abs($fixed->amount), 2, ',', '');
            $date = "28" . str_pad($data['month_number'], 2, '0', STR_PAD_LEFT);
            $text = $sanitizeCsvField((string) $fixed->name);
            $csv .= '"'.$amount.'";"H";"4900";"1200";"'.$date.'";"Fixkosten";"'.$text.'"' . "\n";
        }

        return $csv;
    }
}
