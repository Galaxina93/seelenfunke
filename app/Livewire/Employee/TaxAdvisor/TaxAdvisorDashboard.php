<?php

namespace App\Livewire\Employee\TaxAdvisor;

use App\Models\Accounting\AccountingBankTransaction;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Marketing\MarketingInfluencerPayout;
use App\Models\Order\OrderOrder;
use App\Services\AccountingXmlInvoiceService;
use App\Services\Export\FileDownloadService;
use App\Services\FinancialService;
use App\Services\InvoiceService;
use App\Services\Marketing\InfluencerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use ZipArchive;

#[Layout('components.layouts.backend_layout', ['guard' => 'employee'])]
class TaxAdvisorDashboard extends Component
{
    public int $selectedYear;
    public int $selectedMonth;
    public bool $isNet = false;

    // Filter & Ansicht-Steuerung
    public string $scopeFilter = 'all'; // all, business, private
    public string $periodMode = 'month'; // month, year
    public string $invoiceTypeFilter = ''; // '', invoice, cancellation, credit_note, influencer_payout
    public string $invoiceSearch = '';
    public string $expenseSearch = '';

    public array $archivedExports = [];

    public function boot(): void
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee || !$employee->isTaxAdvisor()) {
            abort(403, 'Zugriff nur für autorisierte Steuerberater.');
        }
    }

    public function mount(): void
    {
        $this->selectedYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $this->selectedMonth = $currentMonth === 1 ? 12 : ($currentMonth - 1);
        if ($currentMonth === 1) {
            $this->selectedYear--;
        }

        $this->loadArchivedExports();
    }

    public function selectMonth(int $month): void
    {
        $this->selectedMonth = max(1, min(12, $month));
        $this->selectedYear = max(2000, min(2100, (int) ($this->selectedYear ?: date('Y'))));
    }

    public function updatedSelectedYear($value): void
    {
        $this->selectedYear = max(2000, min(2100, (int) $value));
    }

    public function setScopeFilter(string $scope): void
    {
        if (in_array($scope, ['all', 'business', 'private'], true)) {
            $this->scopeFilter = $scope;
        }
    }

    public function setPeriodMode(string $mode): void
    {
        if (in_array($mode, ['month', 'year'], true)) {
            $this->periodMode = $mode;
        }
    }

    public function loadArchivedExports(): void
    {
        Storage::disk('local')->makeDirectory('buchhaltung/tax_exports');
        $files = Storage::disk('local')->files('buchhaltung/tax_exports');

        $this->archivedExports = collect($files)->map(function ($file) {
            return [
                'name' => basename($file),
                'size' => number_format(Storage::disk('local')->size($file) / 1024 / 1024, 2, ',', '.') . ' MB',
                'date' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file))->format('d.m.Y H:i'),
                'timestamp' => Storage::disk('local')->lastModified($file),
                'path' => $file,
            ];
        })->sortByDesc('timestamp')->values()->toArray();
    }

    /**
     * 1. Komplettpaket DATEV & UStVA Export (ZIP mit EXTF CSV + UStVA PDF + allen Belegen)
     */
    public function downloadDatevPackageZip(FinancialService $financialService)
    {
        $adminId = $financialService->resolveMandantAdminId();
        $data = $financialService->getUstvaMonthData($adminId, $this->selectedMonth, $this->selectedYear);
        $result = $financialService->generateDatevExportZip($data);

        $this->loadArchivedExports();

        if (!File::exists($result['zip_path']) || is_link($result['zip_path'])) {
            session()->flash('tax_error', 'Das DATEV-Komplettpaket konnte nicht erstellt werden.');
            return null;
        }

        session()->flash('tax_success', "DATEV- & UStVA-Komplettpaket ({$result['zip_name']}) erfolgreich generiert.");
        return response()->download($result['zip_path'], $result['zip_name']);
    }

    /**
     * 2. Einzelner DATEV EXTF Buchungsstapel (CSV, Windows-1252)
     */
    public function downloadDatevCsv(FinancialService $financialService)
    {
        $adminId = $financialService->resolveMandantAdminId();
        $data = $financialService->getUstvaMonthData($adminId, $this->selectedMonth, $this->selectedYear);
        $csvContent = $financialService->buildDatevCsv($data);
        $encoded = mb_convert_encoding($csvContent, 'Windows-1252', 'UTF-8');

        $filename = "EXTF_Buchungsstapel_{$data['month_number']}_{$data['year']}.csv";

        return response()->streamDownload(function () use ($encoded) {
            echo $encoded;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=Windows-1252',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * 3. Einzelnes UStVA-Prüfprotokoll (PDF)
     */
    public function downloadUstvaPdf(FinancialService $financialService)
    {
        $adminId = $financialService->resolveMandantAdminId();
        $data = $financialService->getUstvaMonthData($adminId, $this->selectedMonth, $this->selectedYear);
        $pdf = $financialService->generateUstvaPdf($data);

        $filename = "UStVA_Auswertung_{$data['month_number']}_{$data['year']}.pdf";

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * 4. Finanz- & Liquiditätsbericht inkl. Transaktionen CSV & Bank-/Sonderbelege (ZIP)
     */
    public function downloadFinancialReportZip(FinancialService $financialService)
    {
        $adminId = $financialService->resolveMandantAdminId();
        $zipPath = $financialService->generateTaxExport($adminId, $this->selectedMonth, $this->selectedYear);

        if (!$zipPath || !File::exists($zipPath) || is_link($zipPath)) {
            session()->flash('tax_error', 'Der Finanz- & Liquiditätsbericht konnte nicht erstellt werden.');
            return null;
        }

        $monthStr = str_pad((string) $this->selectedMonth, 2, '0', STR_PAD_LEFT);
        $downloadName = "Finanzbericht_{$this->selectedYear}_{$monthStr}.zip";

        return response()->download($zipPath, $downloadName);
    }

    /**
     * 5. Alle Ausgangsrechnungen, Stornos, Gutschriften (PDF + E-Rechnung XML) & Creator-Gutschriften als Sammel-ZIP
     */
    public function downloadMonthlyInvoicesZip(InvoiceService $invoiceService, AccountingXmlInvoiceService $xmlService, InfluencerService $influencerService)
    {
        $month = max(1, min(12, $this->selectedMonth));
        $year = max(2000, min(2100, $this->selectedYear));
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $invoiceQuery = AccountingInvoice::with('order.items')
            ->where('status', '!=', 'draft')
            ->whereYear('invoice_date', $year);

        $payoutQuery = MarketingInfluencerPayout::with('influencer.employee.profile')
            ->whereYear('created_at', $year);

        if ($this->periodMode === 'month') {
            $invoiceQuery->whereMonth('invoice_date', $month);
            $payoutQuery->whereMonth('created_at', $month);
            $periodLabel = "{$year}_{$monthStr}";
        } else {
            $periodLabel = "{$year}_Gesamtjahr";
        }

        $invoices = $invoiceQuery->orderBy('invoice_date')->get();
        $payouts = $payoutQuery->orderBy('created_at')->get();

        $exportDir = storage_path('app/buchhaltung/exports');
        if (!File::exists($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $zipName = "Rechnungen_und_Gutschriften_{$periodLabel}.zip";
        $zipPath = "{$exportDir}/{$zipName}";

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $addedCount = 0;

            foreach ($invoices as $inv) {
                $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $inv->invoice_number);
                $prefix = match ($inv->type) {
                    'cancellation' => 'Storno_',
                    'credit_note' => 'Gutschrift_',
                    default => 'Rechnung_',
                };

                // PDF
                $archivedPdfPath = ltrim(str_replace('\\', '/', (string) $inv->pdf_storage_path), '/');
                if (
                    $inv->has_archived_pdf
                    && $archivedPdfPath !== ''
                    && !str_contains($archivedPdfPath, '..')
                    && !is_link(Storage::disk('local')->path($archivedPdfPath))
                ) {
                    $zip->addFile(Storage::disk('local')->path($archivedPdfPath), "Ausgangsrechnungen_PDF/{$prefix}{$safeNumber}.pdf");
                    $addedCount++;
                } else {
                    try {
                        $pdf = $invoiceService->generatePdf($inv);
                        $zip->addFromString("Ausgangsrechnungen_PDF/{$prefix}{$safeNumber}.pdf", $pdf->output());
                        $addedCount++;
                    } catch (\Throwable $e) {
                        // Skip broken PDF in batch
                    }
                }

                // E-Rechnung XML (ZUGFeRD / XRechnung)
                $xmlRelative = "buchhaltung/invoices/xml/{$safeNumber}.xml";
                if (!Storage::disk('local')->exists($xmlRelative)) {
                    try {
                        $xmlService->generate($inv);
                    } catch (\Throwable $e) {
                        // Ignore XML generation failure for non-applicable records
                    }
                }
                if (Storage::disk('local')->exists($xmlRelative) && !is_link(Storage::disk('local')->path($xmlRelative))) {
                    $zip->addFile(Storage::disk('local')->path($xmlRelative), "E-Rechnungen_XML/{$safeNumber}.xml");
                }
            }

            foreach ($payouts as $payout) {
                if (empty($payout->pdf_path) || !Storage::disk('local')->exists((string) $payout->pdf_path)) {
                    try {
                        $influencerService->generateCreditNotePdf($payout);
                        $payout->refresh();
                    } catch (\Throwable $e) {
                        continue;
                    }
                }

                $payoutPath = (string) $payout->pdf_path;
                if (
                    $payoutPath !== ''
                    && !str_contains($payoutPath, '..')
                    && str_starts_with($payoutPath, 'buchhaltung/receipts/')
                    && Storage::disk('local')->exists($payoutPath)
                    && !is_link(Storage::disk('local')->path($payoutPath))
                ) {
                    $safePayoutNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $payout->credit_note_number);
                    $zip->addFile(Storage::disk('local')->path($payoutPath), "Creator_Gutschriften_PDF/Gutschrift_{$safePayoutNo}.pdf");
                    $addedCount++;
                }
            }

            if ($addedCount === 0) {
                $zip->addFromString('HINWEIS.txt', "Für den gewählten Zeitraum ({$periodLabel}) liegen noch keine abgeschlossenen Rechnungen oder Gutschriften vor.");
            }

            $zip->close();
        }

        return response()->download($zipPath, $zipName);
    }

    /**
     * 6. Archivierter Steuer-Export aus dem Tresor herunterladen
     */
    public function downloadArchivedTaxExport(string $filename, FileDownloadService $exportService)
    {
        return $exportService->downloadTaxExport(basename($filename));
    }

    /**
     * 7. Einzelne Rechnung / Storno / Gutschrift als PDF herunterladen
     */
    public function downloadInvoicePdf(string $invoiceId, InvoiceService $invoiceService)
    {
        $invoice = AccountingInvoice::findOrFail($invoiceId);
        if ($invoice->status === 'draft') {
            abort(403, 'Entwürfe sind nicht für den Steuerexport freigegeben.');
        }

        $prefix = match ($invoice->type) {
            'cancellation' => 'Storno_',
            'credit_note' => 'Gutschrift_',
            default => 'Rechnung_',
        };
        $safeNumber = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->invoice_number);
        $filename = $prefix . $safeNumber . '.pdf';

        if ($invoice->has_archived_pdf) {
            $storagePath = ltrim(str_replace('\\', '/', (string) $invoice->pdf_storage_path), '/');
            if (
                $storagePath !== ''
                && !str_contains($storagePath, '..')
                && !str_contains($storagePath, "\0")
                && (str_starts_with($storagePath, 'buchhaltung/invoices/') || str_starts_with($storagePath, 'invoices/'))
                && !is_link(Storage::disk('local')->path($storagePath))
            ) {
                return Storage::disk('local')->download($storagePath, $filename);
            }
        }

        $pdf = $invoiceService->generatePdf($invoice);
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * 8. Einzelne E-Rechnung (ZUGFeRD / XRechnung XML) herunterladen
     */
    public function downloadInvoiceXml(string $invoiceId, AccountingXmlInvoiceService $xmlService)
    {
        $invoice = AccountingInvoice::findOrFail($invoiceId);
        if ($invoice->status === 'draft') {
            abort(403, 'Entwürfe sind nicht für den Steuerexport freigegeben.');
        }

        $safeInvoiceNumber = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $invoice->invoice_number);
        $path = 'buchhaltung/invoices/xml/' . $safeInvoiceNumber . '.xml';

        if (!Storage::disk('local')->exists($path)) {
            $xmlService->generate($invoice);
        }

        if (!Storage::disk('local')->exists($path) || is_link(Storage::disk('local')->path($path))) {
            abort(404, 'XML-Datei konnte nicht bereitgestellt werden.');
        }

        return Storage::disk('local')->download($path, "ERechnung_{$safeInvoiceNumber}.xml");
    }

    /**
     * 9. Einzelnen Ausgaben-Beleg oder Fixkosten-Vertrag sicher herunterladen
     */
    public function downloadReceiptOrContract(string $rawPath)
    {
        $normalized = ltrim(str_replace('\\', '/', trim($rawPath)), '/');

        if (
            $normalized === ''
            || str_contains($normalized, '..')
            || str_contains($normalized, "\0")
        ) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        if (!str_starts_with($normalized, 'buchhaltung/')) {
            $normalized = 'buchhaltung/' . $normalized;
        }

        $allowedPrefixes = [
            'buchhaltung/receipts/',
            'buchhaltung/contracts/',
            'buchhaltung/invoices/',
            'buchhaltung/tax_exports/',
        ];

        $isAllowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            abort(403, 'Zugriff auf diesen Speicherpfad ist nicht freigegeben.');
        }

        $disk = Storage::disk('local')->exists($normalized)
            ? 'local'
            : (Storage::disk('public')->exists($normalized) ? 'public' : null);

        if (!$disk) {
            abort(404, 'Beleg- oder Vertragsdatei nicht gefunden.');
        }

        $fullPath = Storage::disk($disk)->path($normalized);
        $realPath = realpath($fullPath);
        $allowedRoot = realpath(Storage::disk($disk)->path('buchhaltung'));

        if (
            !$realPath
            || is_link($fullPath)
            || ($allowedRoot && !str_starts_with($realPath, $allowedRoot . DIRECTORY_SEPARATOR))
        ) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        $cleanFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($realPath)) ?: 'Beleg.pdf';

        return Storage::disk($disk)->download($normalized, $cleanFilename);
    }

    public function render(FinancialService $financialService)
    {
        $employee = Auth::guard('employee')->user();
        $mandantAdminId = $financialService->resolveMandantAdminId();

        // 1. Monats-Navigationsstatus (1..12) + Aktiver Monat (UStVA & Steuer-Kennzahlen)
        $monthsNav = [];
        for ($m = 1; $m <= 12; $m++) {
            $mData = $financialService->getUstvaMonthData($mandantAdminId, $m, $this->selectedYear);
            $monthsNav[$m] = [
                'name' => mb_substr($mData['month_name'], 0, 3),
                'full_name' => $mData['month_name'],
                'status' => $mData['status'],
                'missing_count' => $mData['missing_receipts_count'],
            ];
        }

        $activeMonthData = $financialService->getUstvaMonthData($mandantAdminId, $this->selectedMonth, $this->selectedYear);
        $monthlyStatsBrutto = $financialService->getMonthlyStats($mandantAdminId, $this->selectedMonth, $this->selectedYear, false);
        $monthlyStatsNetto = $financialService->getMonthlyStats($mandantAdminId, $this->selectedMonth, $this->selectedYear, true);
        $yearlyMatrix = $financialService->getYearlyMatrix($mandantAdminId, $this->selectedYear, $this->isNet);

        // 2. Fixkosten & Fix-Einnahmen (Gewerblich & Privat) detailliert aufbereiten
        $groups = AccountingGroup::with('items')
            ->where('admin_id', $mandantAdminId)
            ->orderBy('position')
            ->get();

        $fixedItemsDetailed = collect();
        $fixedSummary = [
            'business_month_gross' => 0.0,
            'business_month_net' => 0.0,
            'business_month_vat' => 0.0,
            'private_month_gross' => 0.0,
            'income_month_gross' => 0.0,
            'business_year_gross' => 0.0,
            'private_year_gross' => 0.0,
            'income_year_gross' => 0.0,
        ];

        foreach ($groups as $group) {
            $isIncomeGroup = ($group->type === 'income');
            foreach ($group->items as $item) {
                $isBusiness = (bool) $item->is_business;
                $rate = (float) ($item->tax_rate ?? ($isBusiness ? shop_setting('default_tax_rate', 19.0) : 0));
                $gross = abs((float) $item->amount);
                $net = $rate > 0 ? ($gross / (1 + ($rate / 100))) : $gross;
                $vat = $gross - $net;

                $isDueInSelectedMonth = $financialService->isDueInMonth($item, $this->selectedMonth, $this->selectedYear);

                $dueMonthsCount = 0;
                for ($m = 1; $m <= 12; $m++) {
                    if ($financialService->isDueInMonth($item, $m, $this->selectedYear)) {
                        $dueMonthsCount++;
                    }
                }
                $yearGross = $gross * $dueMonthsCount;

                if ($isIncomeGroup) {
                    if ($isDueInSelectedMonth) {
                        $fixedSummary['income_month_gross'] += $gross;
                    }
                    $fixedSummary['income_year_gross'] += $yearGross;
                } elseif ($isBusiness) {
                    if ($isDueInSelectedMonth) {
                        $fixedSummary['business_month_gross'] += $gross;
                        $fixedSummary['business_month_net'] += $net;
                        $fixedSummary['business_month_vat'] += $vat;
                    }
                    $fixedSummary['business_year_gross'] += $yearGross;
                } else {
                    if ($isDueInSelectedMonth) {
                        $fixedSummary['private_month_gross'] += $gross;
                    }
                    $fixedSummary['private_year_gross'] += $yearGross;
                }

                // Scope- & Suchfilter für die Tabelle anwenden
                if ($this->scopeFilter === 'business' && !$isBusiness) {
                    continue;
                }
                if ($this->scopeFilter === 'private' && $isBusiness) {
                    continue;
                }
                if ($this->expenseSearch !== '') {
                    $needle = mb_strtolower(trim($this->expenseSearch));
                    $haystack = mb_strtolower($item->name . ' ' . $group->name . ' ' . ($item->notes ?? ''));
                    if (!str_contains($haystack, $needle)) {
                        continue;
                    }
                }

                $fixedItemsDetailed->push([
                    'id' => $item->id,
                    'name' => $item->name,
                    'group_name' => $group->name,
                    'is_income' => $isIncomeGroup,
                    'is_business' => $isBusiness,
                    'interval_months' => max(1, (int) ($item->interval_months ?: 1)),
                    'first_payment_date' => $item->first_payment_date ? Carbon::parse($item->first_payment_date)->format('d.m.Y') : '—',
                    'last_payment_date' => $item->last_payment_date ? Carbon::parse($item->last_payment_date)->format('d.m.Y') : null,
                    'is_due_in_month' => $isDueInSelectedMonth,
                    'due_months_in_year' => $dueMonthsCount,
                    'tax_rate' => $rate,
                    'gross' => $gross,
                    'net' => $net,
                    'vat' => $vat,
                    'year_gross' => $yearGross,
                    'contract_file_path' => $item->contract_file_path,
                ]);
            }
        }

        // 3. Sonderausgaben / Variable Kosten & Banktransaktionen (Gewerblich & Privat)
        $specialQuery = AccountingSpecialIssue::where('admin_id', $mandantAdminId)
            ->whereYear('execution_date', $this->selectedYear);

        if ($this->periodMode === 'month') {
            $specialQuery->whereMonth('execution_date', $this->selectedMonth);
        }

        $allPeriodSpecials = $specialQuery->orderByDesc('execution_date')->get();

        $bankTxs = class_exists(AccountingBankTransaction::class)
            ? AccountingBankTransaction::with(['account', 'financeCategory'])
                ->whereHas('account', fn ($q) => $q->where('admin_id', $mandantAdminId))
                ->whereNotNull('accounting_category_id')
                ->whereYear('transaction_date', $this->selectedYear)
                ->when($this->periodMode === 'month', fn ($q) => $q->whereMonth('transaction_date', $this->selectedMonth))
                ->orderByDesc('transaction_date')
                ->get()
            : collect();

        $specialSummary = [
            'business_gross' => 0.0,
            'business_net' => 0.0,
            'business_vat' => 0.0,
            'private_gross' => 0.0,
            'receipt_files_count' => 0,
        ];

        $variableItemsDetailed = collect();

        foreach ($allPeriodSpecials as $special) {
            $isBusiness = (bool) $special->is_business;
            $rate = (float) ($special->tax_rate ?? ($isBusiness ? shop_setting('default_tax_rate', 19.0) : 0));
            $signedGross = (float) $special->amount;
            $absGross = abs($signedGross);
            $absNet = $rate > 0 ? ($absGross / (1 + ($rate / 100))) : $absGross;
            $absVat = $absGross - $absNet;
            $files = is_array($special->file_paths) ? array_values(array_filter($special->file_paths)) : [];

            $specialSummary['receipt_files_count'] += count($files);

            if ($isBusiness) {
                $specialSummary['business_gross'] += $signedGross;
                $specialSummary['business_net'] += ($signedGross < 0 ? -$absNet : $absNet);
                $specialSummary['business_vat'] += ($signedGross < 0 ? -$absVat : $absVat);
            } else {
                $specialSummary['private_gross'] += $signedGross;
            }

            if ($this->scopeFilter === 'business' && !$isBusiness) {
                continue;
            }
            if ($this->scopeFilter === 'private' && $isBusiness) {
                continue;
            }
            if ($this->expenseSearch !== '') {
                $needle = mb_strtolower(trim($this->expenseSearch));
                $haystack = mb_strtolower($special->title . ' ' . ($special->category ?? '') . ' ' . ($special->invoice_number ?? '') . ' ' . ($special->location ?? ''));
                if (!str_contains($haystack, $needle)) {
                    continue;
                }
            }

            $variableItemsDetailed->push([
                'id' => $special->id,
                'source' => 'special',
                'date' => $special->execution_date ? Carbon::parse($special->execution_date)->format('d.m.Y') : '—',
                'sort_date' => $special->execution_date ? Carbon::parse($special->execution_date)->timestamp : 0,
                'title' => $special->title,
                'category' => $special->category ?: 'Sonstiges',
                'invoice_number' => $special->invoice_number,
                'location' => $special->location,
                'is_business' => $isBusiness,
                'tax_rate' => $rate,
                'signed_gross' => $signedGross,
                'abs_gross' => $absGross,
                'abs_net' => $absNet,
                'abs_vat' => $absVat,
                'file_paths' => $files,
            ]);
        }

        foreach ($bankTxs as $tx) {
            $isBusiness = (bool) ($tx->is_business ?? ($tx->account ? $tx->account->is_business : false));
            $signedGross = (float) $tx->amount;
            $absGross = abs($signedGross);
            $files = is_array($tx->file_paths) ? array_values(array_filter($tx->file_paths)) : [];
            $specialSummary['receipt_files_count'] += count($files);

            if ($isBusiness) {
                $specialSummary['business_gross'] += $signedGross;
                $specialSummary['business_net'] += $signedGross;
            } else {
                $specialSummary['private_gross'] += $signedGross;
            }

            if ($this->scopeFilter === 'business' && !$isBusiness) {
                continue;
            }
            if ($this->scopeFilter === 'private' && $isBusiness) {
                continue;
            }

            $txTitle = $tx->counterpart_name ?: ($tx->purpose ?: 'Bankbuchung');
            $txCat = $tx->financeCategory?->name ?: 'Banktransaktion';

            if ($this->expenseSearch !== '') {
                $needle = mb_strtolower(trim($this->expenseSearch));
                $haystack = mb_strtolower($txTitle . ' ' . $txCat . ' ' . ($tx->purpose ?? ''));
                if (!str_contains($haystack, $needle)) {
                    continue;
                }
            }

            $variableItemsDetailed->push([
                'id' => $tx->id,
                'source' => 'bank',
                'date' => $tx->transaction_date ? Carbon::parse($tx->transaction_date)->format('d.m.Y') : '—',
                'sort_date' => $tx->transaction_date ? Carbon::parse($tx->transaction_date)->timestamp : 0,
                'title' => $txTitle,
                'category' => $txCat,
                'invoice_number' => null,
                'location' => 'Bankkonto',
                'is_business' => $isBusiness,
                'tax_rate' => 0.0,
                'signed_gross' => $signedGross,
                'abs_gross' => $absGross,
                'abs_net' => $absGross,
                'abs_vat' => 0.0,
                'file_paths' => $files,
            ]);
        }

        $variableItemsDetailed = $variableItemsDetailed->sortByDesc('sort_date')->values();

        // 4. Shop-Kosten, Ausgangsrechnungen, Stornos, Gutschriften & Creator-Gutschriften
        $invoicesQuery = AccountingInvoice::with(['order', 'customer'])
            ->where('status', '!=', 'draft')
            ->whereYear('invoice_date', $this->selectedYear);

        $ordersPeriodQuery = OrderOrder::whereYear('created_at', $this->selectedYear)
            ->where('payment_status', 'paid');

        $payoutsQuery = MarketingInfluencerPayout::with('influencer.employee.profile')
            ->whereYear('created_at', $this->selectedYear);

        if ($this->periodMode === 'month') {
            $invoicesQuery->whereMonth('invoice_date', $this->selectedMonth);
            $ordersPeriodQuery->whereMonth('created_at', $this->selectedMonth);
            $payoutsQuery->whereMonth('created_at', $this->selectedMonth);
        }

        $allPeriodInvoices = $invoicesQuery->orderByDesc('invoice_date')->orderByDesc('created_at')->get();
        $allPeriodOrders = $ordersPeriodQuery->get();
        $allPeriodPayouts = $payoutsQuery->orderByDesc('created_at')->get();

        $shopSummary = [
            'invoices_count' => $allPeriodInvoices->where('type', 'invoice')->count(),
            'cancellations_count' => $allPeriodInvoices->whereIn('type', ['cancellation', 'credit_note'])->count(),
            'payouts_count' => $allPeriodPayouts->count(),
            'discounts_granted_eur' => ($allPeriodOrders->sum('discount_amount') + $allPeriodOrders->sum('volume_discount')) / 100,
            'shipping_collected_eur' => $allPeriodOrders->sum('shipping_price') / 100,
            'cancellations_gross_eur' => $allPeriodInvoices->whereIn('type', ['cancellation', 'credit_note'])->sum(fn ($i) => abs((int) $i->total)) / 100,
            'creator_payouts_gross_eur' => $allPeriodPayouts->sum('gross_amount_cents') / 100,
            'creator_payouts_vat_eur' => $allPeriodPayouts->sum('tax_amount_cents') / 100,
        ];

        $filteredInvoices = $allPeriodInvoices->filter(function (AccountingInvoice $inv) {
            if ($this->invoiceTypeFilter !== '' && $this->invoiceTypeFilter !== 'influencer_payout') {
                if ($this->invoiceTypeFilter === 'cancellation') {
                    if (!in_array($inv->type, ['cancellation', 'credit_note'], true)) {
                        return false;
                    }
                } elseif ($inv->type !== $this->invoiceTypeFilter) {
                    return false;
                }
            } elseif ($this->invoiceTypeFilter === 'influencer_payout') {
                return false;
            }

            if ($this->invoiceSearch !== '') {
                $needle = mb_strtolower(trim($this->invoiceSearch));
                $recipient = trim(($inv->billing_address['first_name'] ?? '') . ' ' . ($inv->billing_address['last_name'] ?? '') . ' ' . ($inv->billing_address['company'] ?? ''));
                $orderNo = $inv->order?->order_number ?? '';
                $haystack = mb_strtolower($inv->invoice_number . ' ' . $recipient . ' ' . $orderNo . ' ' . ($inv->subject ?? ''));
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        })->values();

        $filteredPayouts = $allPeriodPayouts->filter(function (MarketingInfluencerPayout $payout) {
            if ($this->invoiceTypeFilter !== '' && $this->invoiceTypeFilter !== 'influencer_payout') {
                return false;
            }

            if ($this->invoiceSearch !== '') {
                $needle = mb_strtolower(trim($this->invoiceSearch));
                $creatorName = trim(($payout->influencer?->employee?->first_name ?? '') . ' ' . ($payout->influencer?->employee?->last_name ?? '') . ' ' . ($payout->influencer?->handle ?? ''));
                $haystack = mb_strtolower($payout->credit_note_number . ' ' . $creatorName);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        })->values();

        return view('livewire.employee.tax-advisor.tax-advisor-dashboard', [
            'employee' => $employee,
            'companyMeta' => $financialService->getCompanyTaxMeta(),
            'monthsNav' => $monthsNav,
            'activeMonthData' => $activeMonthData,
            'monthlyStatsBrutto' => $monthlyStatsBrutto,
            'monthlyStatsNetto' => $monthlyStatsNetto,
            'yearlyMatrix' => $yearlyMatrix,
            'fixedItemsDetailed' => $fixedItemsDetailed,
            'fixedSummary' => $fixedSummary,
            'variableItemsDetailed' => $variableItemsDetailed,
            'specialSummary' => $specialSummary,
            'shopSummary' => $shopSummary,
            'filteredInvoices' => $filteredInvoices,
            'filteredPayouts' => $filteredPayouts,
            'defaultTaxRate' => (float) shop_setting('default_tax_rate', 19.0),
        ]);
    }
}
