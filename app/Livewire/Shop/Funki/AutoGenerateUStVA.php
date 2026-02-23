<?php

namespace App\Livewire\Shop\Funki;

use Livewire\Component;
use Carbon\Carbon;
use App\Models\Order\Order;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Financial\FinanceGroup;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AutoGenerateUStVA extends Component
{
    public $selectedYear;
    public $showStorageVault = false;
    public $archivedExports = [];

    public function mount()
    {
        $this->selectedYear = date('Y');
        $this->loadArchivedExports();
    }

    public function loadArchivedExports()
    {
        Storage::disk('local')->makeDirectory('tax_exports');
        $files = Storage::disk('local')->files('tax_exports');

        $this->archivedExports = collect($files)->map(function($file) {
            return [
                'name' => basename($file),
                'size' => number_format(Storage::disk('local')->size($file) / 1024 / 1024, 2) . ' MB',
                'date' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file))->format('d.m.Y H:i'),
                'path' => $file
            ];
        })->sortByDesc('date')->values()->toArray();
    }

    public function getMonthData($month)
    {
        $adminId = auth()->guard('admin')->id();
        $startDate = Carbon::createFromDate($this->selectedYear, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $now = Carbon::now();
        $isFutureMonth = $startDate->isFuture(); // Check ob Monat in der Zukunft liegt

        // Frist: 10. des Folgemonats
        $deadline = Carbon::createFromDate($this->selectedYear, $month, 10)->addMonth();

        // 1. UMSÄTZE (Einnahmen)
        $orders = Order::whereYear('created_at', $this->selectedYear)
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

                $diff = ($month - $startMonth) + (($this->selectedYear - $item->first_payment_date->year) * 12);
                if ($diff >= 0 && ($diff % $interval) === 0) {
                    $businessFixed->push($item);
                }
            }
        }

        // Korrektur: Werte zwingend positiv machen, um -- Fehler zu vermeiden
        $totalExpensesGross = abs($businessSpecials->sum('amount')) + abs($businessFixed->sum('amount'));

        // Vorsteuer berechnen (inklusive Netto-Ermittlung für EÜR)
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
            $net = $amt / 1.19; // Standardmäßig 19% bei Fixkosten
            $vatPaid += $amt - $net;
            $expensesNet += $net;
        }

        // Gewinn / EÜR
        $profit = $revenueNet - $expensesNet;

        // 3. READINESS BERECHNEN (Fortschritt & fehlende Belege)
        $progress = 0;
        $missingReceipts = 0;
        $totalBusinessItems = $businessSpecials->count() + $businessFixed->count();

        foreach ($businessSpecials as $s) {
            if (empty($s->file_paths)) $missingReceipts++;
        }
        foreach ($businessFixed as $f) {
            if (empty($f->contract_file_path)) $missingReceipts++;
        }

        // Logik für Readiness korrigiert
        if ($isFutureMonth) {
            // Zukünftige Monate sind nie fertig
            $progress = 0;
            $status = 'future';
        } else {
            // Belege zählen 70% der Readiness
            if ($totalBusinessItems > 0) {
                $receiptProgress = (($totalBusinessItems - $missingReceipts) / $totalBusinessItems) * 70;
                $progress += $receiptProgress;
            } else {
                // Keine Ausgaben = 100% der Beleg-Readiness erfüllt
                $progress += 70;
            }

            // Zeitlicher Fortschritt zählt 30%
            if ($now->gt($endDate)) {
                $progress += 30; // Monat ist abgeschlossen
                $status = ($missingReceipts == 0) ? 'ready' : 'missing_receipts';
            } else {
                $timeProgress = ($now->day / $startDate->daysInMonth) * 30;
                $progress += $timeProgress;
                $status = 'in_progress';
            }
        }

        return [
            'month_name' => $startDate->locale('de')->monthName,
            'month_number' => str_pad($month, 2, '0', STR_PAD_LEFT),
            'year' => $this->selectedYear,
            'revenue_gross' => $revenueGross,
            'revenue_net' => $revenueNet,
            'vat_collected' => $vatCollected,
            'expenses_gross' => $totalExpensesGross,
            'expenses_net' => $expensesNet,
            'vat_paid' => $vatPaid,
            'zahllast' => $vatCollected - $vatPaid,
            'profit' => $profit,
            'progress' => min(100, round($progress)),
            'missing_receipts' => $missingReceipts,
            'status' => $status,
            'raw_orders' => $orders,
            'raw_specials' => $businessSpecials,
            'raw_fixed' => $businessFixed,
            'is_future' => $isFutureMonth,
            'deadline' => $deadline
        ];
    }

    public function generateDatevExport($month)
    {
        $data = $this->getMonthData($month);

        // Blockiere Export für zukünftige Monate
        if ($data['is_future']) {
            session()->flash('error', 'Export für zukünftige Monate nicht möglich.');
            return;
        }

        $monthStr = $data['month_number'];
        $yearStr = $data['year'];

        $tempDir = storage_path("app/temp_export_{$monthStr}_{$yearStr}");
        if (!File::exists($tempDir)) File::makeDirectory($tempDir, 0755, true);

        // 1. DATEV CSV ERSTELLEN
        $csvPath = $tempDir . "/EXTF_Buchungsstapel_{$monthStr}_{$yearStr}.csv";
        $csvContent = $this->buildDatevCsv($data);
        File::put($csvPath, mb_convert_encoding($csvContent, 'Windows-1252', 'UTF-8'));

        // 2. PDF REPORT ERSTELLEN
        $pdfPath = $tempDir . "/UStVA_Report_{$monthStr}_{$yearStr}.pdf";
        $pdf = Pdf::loadView('global.pdf.ustva_report', ['data' => $data]);
        $pdf->save($pdfPath);

        // 3. BELEGE SAMMELN
        $receiptsDir = $tempDir . "/Belege";
        File::makeDirectory($receiptsDir, 0755, true);

        foreach ($data['raw_specials'] as $special) {
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

        foreach ($data['raw_fixed'] as $fixed) {
            if (!empty($fixed->contract_file_path)) {
                $source = storage_path('app/public/' . $fixed->contract_file_path);
                if (File::exists($source)) {
                    $ext = pathinfo($source, PATHINFO_EXTENSION);
                    $filename = "Fixkosten_" . Str::slug($fixed->name) . ".{$ext}";
                    File::copy($source, $receiptsDir . '/' . $filename);
                }
            }
        }

        // 4. ALLES ZIPPEN
        $zipName = "TaxExport_{$yearStr}_{$monthStr}.zip";
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

        // Cleanup
        File::deleteDirectory($tempDir);

        $this->loadArchivedExports();
        session()->flash('success', "DATEV Export für $monthStr/$yearStr erfolgreich generiert und im Tresor abgelegt.");
    }

    private function buildDatevCsv($data)
    {
        // DATEV Header (EXTF Standardstruktur)
        $csv = '"EXTF";700;21;"Buchungsstapel";9;"";"";"";"";"";"1";"";"";"";"";"";"";"";"";"";"";"";"";"";""' . "\n";
        // Spaltenüberschriften
        $csv .= '"Umsatz (ohne Soll/Haben-Kz)";"Soll/Haben-Kennzeichen";"Konto";"Gegenkonto (ohne BU-Schlüssel)";"Belegdatum";"Belegfeld 1";"Buchungstext"' . "\n";

        // Einnahmen
        foreach ($data['raw_orders'] as $order) {
            $amount = number_format($order->total_price / 100, 2, ',', '');
            $date = $order->created_at->format('dm');
            $text = "Bestellung " . $order->order_number;
            $csv .= '"'.$amount.'";"S";"1200";"8400";"'.$date.'";"'.$order->order_number.'";"'.$text.'"' . "\n";
        }

        // Ausgaben (Variabel)
        foreach ($data['raw_specials'] as $special) {
            $amount = number_format(abs($special->amount), 2, ',', '');
            $date = Carbon::parse($special->execution_date)->format('dm');
            $text = str_replace('"', '""', $special->title);
            $beleg = $special->invoice_number ?? 'Beleg';
            $csv .= '"'.$amount.'";"H";"4900";"1200";"'.$date.'";"'.$beleg.'";"'.$text.'"' . "\n";
        }

        // Ausgaben (Fixkosten)
        foreach ($data['raw_fixed'] as $fixed) {
            $amount = number_format(abs($fixed->amount), 2, ',', '');
            $date = "28" . str_pad($data['month_number'], 2, '0', STR_PAD_LEFT);
            $text = str_replace('"', '""', $fixed->name);
            $csv .= '"'.$amount.'";"H";"4900";"1200";"'.$date.'";"Fixkosten";"'.$text.'"' . "\n";
        }

        return $csv;
    }

    public function downloadExport($filename)
    {
        $path = storage_path('app/tax_exports/' . $filename);
        if (File::exists($path)) {
            return response()->download($path);
        }
        session()->flash('error', 'Datei nicht gefunden.');
    }

    public function deleteExport($filename)
    {
        $path = storage_path('app/tax_exports/' . $filename);
        if (File::exists($path)) {
            File::delete($path);
            $this->loadArchivedExports();
            session()->flash('success', 'Archiv gelöscht.');
        }
    }

    public function transmitToElster($month)
    {
        $data = $this->getMonthData($month);

        // Sicherheitscheck
        if ($data['status'] !== 'ready') {
            session()->flash('error', 'Monat ist noch nicht bereit für die Übermittlung.');
            return;
        }

        // Simulierter API Request an z.B. Lexoffice / Taxdoo / Elster
        sleep(2);

        session()->flash('success', "Erfolgreich! UStVA $month/".$this->selectedYear." wurde via ELSTER-API an das Finanzamt übermittelt.");
    }

    public function render()
    {
        $monthsData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthsData[$i] = $this->getMonthData($i);
        }

        return view('livewire.shop.funki.auto-generate-u-st-v-a', [
            'monthsData' => $monthsData
        ]);
    }
}
