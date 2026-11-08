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
use App\Models\Management\ManagementTask as TaskModel;
use App\Models\Management\ManagementTaskList as TaskListModel;
use App\Models\System\SystemLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use ZipArchive;

#[Layout('components.layouts.backend_layout', ['guard' => 'employee'])]
class TaxAdvisorDashboard extends Component
{
    use WithFileUploads;

    public int $selectedYear;
    public int $selectedMonth;
    public bool $isNet = false;

    // Filter & Ansicht-Steuerung
    public string $scopeFilter = 'all'; // all, business, private
    public string $periodMode = 'month'; // month, year
    public string $receiptStatusFilter = 'all'; // all, with_receipt, missing_receipt
    public string $invoiceTypeFilter = ''; // '', invoice, cancellation, credit_note, influencer_payout
    public string $invoiceSearch = '';
    public string $expenseSearch = '';

    public array $archivedExports = [];

    // Kanzlei-Aufgaben & Rückfragen
    #[Locked]
    public ?string $taxAdvisorListId = null;
    public string $taskStatusFilter = 'open'; // open, completed, all
    public bool $showNewTaskForm = false;
    public string $newTaskTitle = '';
    public string $newTaskPriority = 'niedrig'; // niedrig, hoch
    public ?string $newTaskRelevantFrom = null;
    public string $newTaskNote = '';
    public $newTaskUploads = [];

    // Antwort / Thread für bestehende Aufgabe
    public ?string $activeResponseTaskId = null;
    public string $taskResponseText = '';
    public $taskResponseUploads = [];

    public function boot(): void
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee || !$employee->isTaxAdvisor() || (method_exists($employee, 'isAccountActive') && !$employee->isAccountActive())) {
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
        $this->taxAdvisorListId = $this->getTaxAdvisorList()->id;
    }

    public function getTaxAdvisorList(): TaskListModel
    {
        return TaskListModel::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            [
                'icon' => 'calculator',
                'color' => '#C5A059',
                'position' => 10,
                'is_archived' => false,
            ]
        );
    }

    public function toggleNewTaskForm(): void
    {
        $this->showNewTaskForm = !$this->showNewTaskForm;
        if (!$this->showNewTaskForm) {
            $this->reset(['newTaskTitle', 'newTaskPriority', 'newTaskRelevantFrom', 'newTaskNote', 'newTaskUploads']);
            $this->newTaskPriority = 'niedrig';
        }
    }

    public function createTaxTask(): void
    {
        $this->validate([
            'newTaskTitle' => 'required|string|min:3|max:255',
            'newTaskPriority' => 'required|in:niedrig,hoch',
            'newTaskRelevantFrom' => 'nullable|date',
            'newTaskNote' => 'nullable|string|max:3000',
            'newTaskUploads.*' => 'nullable|file|max:20480|mimes:pdf,jpg,jpeg,png,webp,txt,csv,xlsx,zip',
        ], [
            'newTaskTitle.required' => 'Bitte gib einen Betreff für die Rückfrage ein.',
            'newTaskTitle.min' => 'Der Betreff muss mindestens 3 Zeichen lang sein.',
        ]);

        $list = $this->getTaxAdvisorList();
        $employee = Auth::guard('employee')->user();
        $authorName = trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? '')) ?: 'Steuerberater';

        $filePaths = [];
        if (!empty($this->newTaskUploads)) {
            foreach ($this->newTaskUploads as $file) {
                $path = $file->store('leitung/tasks/attachments', 'local');
                $filePaths[] = $path;
            }
        }

        $formattedNote = null;
        if (!empty(trim($this->newTaskNote))) {
            $timestamp = now()->format('d.m.Y H:i');
            $formattedNote = "[$timestamp - Kanzlei ($authorName)]:\n" . trim($this->newTaskNote);
        }

        $highestPos = (int) TaskModel::where('task_list_id', $list->id)->max('position');

        TaskModel::create([
            'task_list_id' => $list->id,
            'title' => trim($this->newTaskTitle),
            'priority' => $this->newTaskPriority,
            'relevant_from' => !empty($this->newTaskRelevantFrom) ? Carbon::parse($this->newTaskRelevantFrom) : null,
            'ai_plan' => $formattedNote,
            'file_paths' => !empty($filePaths) ? $filePaths : null,
            'is_completed' => false,
            'position' => $highestPos + 1,
        ]);

        $this->reset(['newTaskTitle', 'newTaskPriority', 'newTaskRelevantFrom', 'newTaskNote', 'newTaskUploads', 'showNewTaskForm']);
        $this->newTaskPriority = 'niedrig';

        session()->flash('tax_success', 'Rückfrage / Aufgabe erfolgreich an Mandanten übermittelt.');
    }

    public function toggleTaskComplete(string $taskId): void
    {
        $list = $this->getTaxAdvisorList();
        $task = TaskModel::where('task_list_id', $list->id)->find($taskId);
        if (!$task) {
            abort(403);
        }

        $task->update([
            'is_completed' => !$task->is_completed,
        ]);

        $statusText = $task->is_completed ? 'als erledigt markiert' : 'wiedereröffnet';
        session()->flash('tax_success', "Aufgabe '{$task->title}' {$statusText}.");
    }

    public function openTaskResponse(string $taskId): void
    {
        if ($this->activeResponseTaskId === $taskId) {
            $this->activeResponseTaskId = null;
            $this->reset(['taskResponseText', 'taskResponseUploads']);
        } else {
            $this->activeResponseTaskId = $taskId;
            $this->reset(['taskResponseText', 'taskResponseUploads']);
        }
    }

    public function closeTaskResponse(): void
    {
        $this->activeResponseTaskId = null;
        $this->reset(['taskResponseText', 'taskResponseUploads']);
    }

    public function submitTaskResponse(string $taskId): void
    {
        $list = $this->getTaxAdvisorList();
        $task = TaskModel::where('task_list_id', $list->id)->find($taskId);
        if (!$task) {
            abort(403);
        }

        $this->validate([
            'taskResponseText' => 'nullable|string|max:3000',
            'taskResponseUploads.*' => 'nullable|file|max:20480|mimes:pdf,jpg,jpeg,png,webp,txt,csv,xlsx,zip',
        ]);

        if (empty(trim($this->taskResponseText)) && empty($this->taskResponseUploads)) {
            session()->flash('tax_error', 'Bitte gib eine Nachricht ein oder wähle eine Datei aus.');
            return;
        }

        $employee = Auth::guard('employee')->user();
        $authorName = trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? '')) ?: 'Steuerberater';
        $timestamp = now()->format('d.m.Y H:i');

        if (!empty(trim($this->taskResponseText))) {
            $entry = "[$timestamp - Kanzlei ($authorName)]:\n" . trim($this->taskResponseText);
            $existing = trim((string) $task->ai_plan);
            $updatedNote = $existing !== '' ? $existing . "\n\n" . $entry : $entry;
            $task->ai_plan = $updatedNote;
        }

        if (!empty($this->taskResponseUploads)) {
            $currentFiles = is_array($task->file_paths) ? $task->file_paths : (json_decode($task->file_paths ?? '[]', true) ?: []);
            foreach ($this->taskResponseUploads as $file) {
                $path = $file->store('leitung/tasks/attachments', 'local');
                $currentFiles[] = $path;
            }
            $task->file_paths = array_values($currentFiles);
        }

        $task->save();

        $this->reset(['taskResponseText', 'taskResponseUploads', 'activeResponseTaskId']);
        session()->flash('tax_success', 'Antwort / Dokumente erfolgreich gespeichert.');
    }

    public function downloadTaskFile(string $taskId, string $filePath)
    {
        $list = $this->getTaxAdvisorList();
        $task = TaskModel::where('task_list_id', $list->id)->find($taskId);
        if (!$task) {
            abort(403);
        }

        $files = is_array($task->file_paths) ? $task->file_paths : (json_decode($task->file_paths ?? '[]', true) ?: []);
        $cleanPath = (string) $filePath;

        if (str_contains($cleanPath, '..') || !in_array($cleanPath, $files, true) || !Storage::disk('local')->exists($cleanPath)) {
            abort(403);
        }

        $this->logTaxAdvisorAccess('task_attachment', $cleanPath, ['task_id' => $taskId]);

        return response()->download(Storage::disk('local')->path($cleanPath), basename($cleanPath));
    }

    public function prefillNewTaskForMissingReceipt(string $title, string $amount, string $date): void
    {
        $this->showNewTaskForm = true;
        $this->newTaskTitle = 'Beleg fehlt: ' . trim($title);
        $this->newTaskPriority = 'hoch';
        $this->newTaskNote = "Für die steuerliche Erfassung und den Vorsteuerabzug fehlt der Beleg für:\n\n• Posten: " . trim($title) . "\n• Betrag: " . trim($amount) . " €\n• Datum: " . trim($date) . "\n\nBitte die Rechnung oder Quittung hier als PDF oder Foto hochladen.";
        $this->dispatch('switch-tab', tab: 'tasks');
        session()->flash('tax_success', 'Rückfrage-Formular vorbefüllt. Bitte prüfen und absenden.');
    }

    public function setReceiptStatusFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'with_receipt', 'missing_receipt'], true)) {
            $this->receiptStatusFilter = $filter;
        }
    }

    protected function logTaxAdvisorAccess(string $action, string $target, array $meta = []): void
    {
        try {
            $employee = Auth::guard('employee')->user();
            if (!$employee) {
                return;
            }

            SystemLog::create([
                'type' => 'gobd_audit',
                'action_id' => 'tax_' . Str::random(12),
                'title' => 'Steuerberater Export Zugriff',
                'message' => "Steuerberater {$employee->first_name} {$employee->last_name} ({$employee->email}) hat '{$target}' abgerufen.",
                'status' => 'success',
                'payload' => array_merge([
                    'employee_id' => $employee->id,
                    'email' => $employee->email,
                    'action' => $action,
                    'target' => $target,
                    'period' => "{$this->selectedYear}-" . str_pad((string) $this->selectedMonth, 2, '0', STR_PAD_LEFT),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ], $meta),
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit-Logging blockiert nicht den eigentlichen Mandanten-Download
        }
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

        $this->logTaxAdvisorAccess('datev_package_zip', $result['zip_name']);
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
        $this->logTaxAdvisorAccess('datev_csv', $filename);

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
        $this->logTaxAdvisorAccess('ustva_pdf', $filename);

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
        $this->logTaxAdvisorAccess('financial_report_zip', $downloadName);

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
            ->where('status', '!=', 'cancelled')
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

        $this->logTaxAdvisorAccess('monthly_invoices_zip', $zipName);
        return response()->download($zipPath, $zipName);
    }

    /**
     * 5b. Alle Personal- & Lohnunterlagen (Arbeitsverträge, Personalfragebögen, Stundenzettel & Lohnabrechnungen) als Sammel-ZIP
     */
    public function downloadMonthlyHrPackageZip(\App\Services\Employee\EmployeeHrService $hrService)
    {
        $month = max(1, min(12, $this->selectedMonth));
        $year = max(2000, min(2100, $this->selectedYear));
        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $periodLabel = $this->periodMode === 'month' ? "{$year}_{$monthStr}" : "{$year}_Gesamtjahr";

        $contracts = \Illuminate\Support\Facades\Schema::hasTable('employee_contracts')
            ? \App\Models\Employee\EmployeeContract::with(['employee.profile', 'payslips' => fn ($q) => $q->where('period_year', $year)])
                ->whereNotNull('invitation_accepted_at')
                ->orderBy('is_archived')
                ->orderByDesc('signed_at')
                ->get()
            : collect();

        $exportDir = storage_path('app/buchhaltung/exports');
        if (!File::exists($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        $zipName = "Personal_und_Lohn_{$periodLabel}.zip";
        $zipPath = "{$exportDir}/{$zipName}";

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $addedCount = 0;

            foreach ($contracts as $contract) {
                $safeNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $contract->contract_number);
                $empFolder = "Personalakten/{$safeNo}_" . (\Illuminate\Support\Str::slug($contract->display_name) ?: 'Mitarbeiter');

                // 1. Arbeitsvertrag PDF (§ 2 NachwG)
                if (empty($contract->contract_pdf_path) || !Storage::disk('local')->exists((string) $contract->contract_pdf_path)) {
                    $hrService->generateAndStoreContractPdf($contract);
                    $contract->refresh();
                }
                $cPath = (string) $contract->contract_pdf_path;
                if ($cPath !== '' && !str_contains($cPath, '..') && str_starts_with($cPath, 'buchhaltung/contracts/') && Storage::disk('local')->exists($cPath) && !is_link(Storage::disk('local')->path($cPath))) {
                    $zip->addFile(Storage::disk('local')->path($cPath), "{$empFolder}/Arbeitsvertrag_{$safeNo}.pdf");
                    $addedCount++;
                }

                // 2. Personalfragebogen PDF (ELStAM / SV)
                if (empty($contract->questionnaire_pdf_path) || !Storage::disk('local')->exists((string) $contract->questionnaire_pdf_path)) {
                    $hrService->generateAndStoreQuestionnairePdf($contract);
                    $contract->refresh();
                }
                $qPath = (string) $contract->questionnaire_pdf_path;
                if ($qPath !== '' && !str_contains($qPath, '..') && str_starts_with($qPath, 'buchhaltung/contracts/') && Storage::disk('local')->exists($qPath) && !is_link(Storage::disk('local')->path($qPath))) {
                    $zip->addFile(Storage::disk('local')->path($qPath), "{$empFolder}/Personalbogen_{$safeNo}.pdf");
                    $addedCount++;
                }

                // 3. Stundenzettel & Lohnabrechnungen (Monat oder Gesamtjahr)
                $monthsToExport = $this->periodMode === 'month' ? [$month] : range(1, 12);
                foreach ($monthsToExport as $m) {
                    $mStr = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
                    $tPath = $hrService->generateAndStoreTimesheetPdf($contract, $year, $m);
                    if ($tPath && !str_contains($tPath, '..') && str_starts_with($tPath, 'buchhaltung/receipts/') && Storage::disk('local')->exists($tPath) && !is_link(Storage::disk('local')->path($tPath))) {
                        $zip->addFile(Storage::disk('local')->path($tPath), "{$empFolder}/Stundenzettel_{$safeNo}_{$year}_{$mStr}.pdf");
                        $addedCount++;
                    }

                    $payslip = $contract->payslips->firstWhere('period_month', $m);
                    if ($payslip) {
                        $pPath = (string) $payslip->effective_pdf_path;
                        if ($pPath === '' || !Storage::disk('local')->exists($pPath)) {
                            $hrService->generateAndStorePayslipPdf($payslip);
                            $payslip->refresh();
                            $pPath = (string) $payslip->effective_pdf_path;
                        }
                        if ($pPath !== '' && !str_contains($pPath, '..') && str_starts_with($pPath, 'buchhaltung/receipts/') && Storage::disk('local')->exists($pPath) && !is_link(Storage::disk('local')->path($pPath))) {
                            $safePayslipNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $payslip->payslip_number);
                            $zip->addFile(Storage::disk('local')->path($pPath), "{$empFolder}/Lohnabrechnung_{$safePayslipNo}_{$year}_{$mStr}.pdf");
                            $addedCount++;
                        }
                    }
                }
            }

            if ($addedCount === 0) {
                $zip->addFromString('HINWEIS.txt', "Für den gewählten Zeitraum ({$periodLabel}) liegen noch keine unterzeichneten Personalakten vor.");
            }

            $zip->close();
        }

        $this->logTaxAdvisorAccess('monthly_hr_package_zip', $zipName);
        return response()->download($zipPath, $zipName);
    }

    /**
     * 6. Archivierter Steuer-Export aus dem Tresor herunterladen
     */
    public function downloadArchivedTaxExport(string $filename, FileDownloadService $exportService)
    {
        $this->logTaxAdvisorAccess('archived_tax_export', basename($filename));
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
                && Storage::disk('local')->exists($storagePath)
                && !is_link(Storage::disk('local')->path($storagePath))
            ) {
                $this->logTaxAdvisorAccess('invoice_pdf', $filename, ['invoice_id' => $invoiceId]);
                return Storage::disk('local')->download($storagePath, $filename);
            }
        }

        $pdf = $invoiceService->generatePdf($invoice);
        $this->logTaxAdvisorAccess('invoice_pdf', $filename, ['invoice_id' => $invoiceId]);
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

        $this->logTaxAdvisorAccess('invoice_xml', "ERechnung_{$safeInvoiceNumber}.xml", ['invoice_id' => $invoiceId]);
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
        $this->logTaxAdvisorAccess('receipt_or_contract', $cleanFilename, ['path' => $normalized]);

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

        $isSmallBusinessYear = app(\App\Services\Tax\TaxSettingService::class)->isSmallBusinessForYear((int) $this->selectedYear);

        foreach ($groups as $group) {
            $isIncomeGroup = ($group->type === 'income');
            foreach ($group->items as $item) {
                $isBusiness = (bool) $item->is_business;
                $rate = $isSmallBusinessYear ? 0.0 : (float) ($item->tax_rate ?? ($isBusiness ? shop_setting('default_tax_rate', 19.0) : 0));
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

                // Beleg-Statusfilter
                $hasReceipt = !empty($item->contract_file_path);
                if ($this->receiptStatusFilter === 'with_receipt' && !$hasReceipt) {
                    continue;
                }
                if ($this->receiptStatusFilter === 'missing_receipt' && ($hasReceipt || !$isBusiness)) {
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
                    'has_receipt' => $hasReceipt,
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
            $rate = $isSmallBusinessYear ? 0.0 : (float) ($special->tax_rate ?? ($isBusiness ? shop_setting('default_tax_rate', 19.0) : 0));
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

            // Beleg-Statusfilter
            $hasReceipt = !empty($files);
            if ($this->receiptStatusFilter === 'with_receipt' && !$hasReceipt) {
                continue;
            }
            if ($this->receiptStatusFilter === 'missing_receipt' && ($hasReceipt || !$isBusiness)) {
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
                'has_receipt' => $hasReceipt,
                'is_uncategorized' => false,
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

            // Beleg-Statusfilter
            $hasReceipt = !empty($files);
            if ($this->receiptStatusFilter === 'with_receipt' && !$hasReceipt) {
                continue;
            }
            if ($this->receiptStatusFilter === 'missing_receipt' && ($hasReceipt || !$isBusiness)) {
                continue;
            }

            $txTitle = $tx->counterpart_name ?: ($tx->purpose ?: 'Bankbuchung');
            $txCat = $tx->financeCategory?->name ?: ($tx->accounting_category_id ? 'Banktransaktion' : 'Unkategorisiert / Offen');

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
                'location' => $tx->account?->name ?: 'Bankkonto',
                'is_business' => $isBusiness,
                'tax_rate' => 0.0,
                'signed_gross' => $signedGross,
                'abs_gross' => $absGross,
                'abs_net' => $absGross,
                'abs_vat' => 0.0,
                'file_paths' => $files,
                'has_receipt' => $hasReceipt,
                'is_uncategorized' => empty($tx->accounting_category_id),
            ]);
        }

        $variableItemsDetailed = $variableItemsDetailed->sortByDesc('sort_date')->values();

        // 4. Shop-Kosten, Ausgangsrechnungen, Stornos, Gutschriften & Creator-Gutschriften
        $invoicesQuery = AccountingInvoice::with(['order', 'customer'])
            ->where('status', '!=', 'draft')
            ->whereYear('invoice_date', $this->selectedYear);

        $ordersPeriodQuery = OrderOrder::whereYear('created_at', $this->selectedYear)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded']);

        $payoutsQuery = MarketingInfluencerPayout::with('influencer.employee.profile')
            ->where('status', '!=', 'cancelled')
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

        $employeeContracts = \Illuminate\Support\Facades\Schema::hasTable('employee_contracts')
            ? \App\Models\Employee\EmployeeContract::with([
                'employee.profile',
                'payslips' => fn ($q) => $q->where('period_year', (int) $this->selectedYear),
            ])
                ->whereNotNull('invitation_accepted_at')
                ->orderBy('is_archived')
                ->orderByDesc('signed_at')
                ->get()
            : collect();

        if ($employeeContracts->isNotEmpty()) {
            $hrService = app(\App\Services\Employee\EmployeeHrService::class);
            foreach ($employeeContracts as $empContract) {
                $empStats = $hrService->getMonthlyStats($empContract, (int) $this->selectedYear, (int) $this->selectedMonth);
                $empPayslip = $empContract->payslips->firstWhere('period_month', (int) $this->selectedMonth);
                $empContract->setAttribute('selected_month_stats', $empStats);
                $empContract->setAttribute('selected_month_payslip', $empPayslip);
            }
        }

        // Kanzlei & Steuern Aufgaben
        $taxList = $this->getTaxAdvisorList();
        $taxTasksQuery = TaskModel::where('task_list_id', $taxList->id)
            ->whereNull('parent_id')
            ->where('is_archived', false);

        if ($this->taskStatusFilter === 'open') {
            $taxTasksQuery->where('is_completed', false);
        } elseif ($this->taskStatusFilter === 'completed') {
            $taxTasksQuery->where('is_completed', true);
        }

        $taxTasks = $taxTasksQuery->orderBy('is_completed')
            ->orderByRaw("CASE WHEN priority = 'hoch' THEN 0 ELSE 1 END")
            ->orderBy('relevant_from')
            ->orderByDesc('created_at')
            ->get();

        $openTaxTasksCount = TaskModel::where('task_list_id', $taxList->id)
            ->whereNull('parent_id')
            ->where('is_archived', false)
            ->where('is_completed', false)
            ->count();

        $completedTaxTasksCount = TaskModel::where('task_list_id', $taxList->id)
            ->whereNull('parent_id')
            ->where('is_archived', false)
            ->where('is_completed', true)
            ->count();

        // Fristen-Radar für den Abrechnungsmonat berechnen (§ 18 UStG / § 108 AO)
        $baseDeadline = Carbon::create($this->selectedYear, $this->selectedMonth, 10)->addMonth();
        if ($baseDeadline->isSaturday()) {
            $baseDeadline->addDays(2);
        } elseif ($baseDeadline->isSunday()) {
            $baseDeadline->addDay();
        }

        $now = now()->startOfDay();
        $deadlineDay = $baseDeadline->copy()->startOfDay();
        $daysUntilDeadline = (int) $now->diffInDays($deadlineDay, false);

        $deadlineStatus = [
            'date_formatted' => $baseDeadline->format('d.m.Y'),
            'is_past' => $deadlineDay->isPast(),
            'is_today' => $deadlineDay->isToday(),
            'days_remaining' => $daysUntilDeadline,
        ];

        // Lohnsummen für den gewählten Monat berechnen (HR)
        $monthlyGrossSalariesCents = 0;
        if ($employeeContracts->isNotEmpty()) {
            foreach ($employeeContracts as $emp) {
                $mPayslip = $emp->selected_month_payslip ?? null;
                $mStats = $emp->selected_month_stats ?? null;
                $monthlyGrossSalariesCents += $mPayslip ? (int) $mPayslip->gross_amount_cents : (int) ($mStats['estimated_gross_cents'] ?? 0);
            }
        }

        return view('livewire.employee.tax-advisor.tax-advisor-dashboard', [
            'employee' => $employee,
            'companyMeta' => $financialService->getCompanyTaxMeta(),
            'monthsNav' => $monthsNav,
            'activeMonthData' => $activeMonthData,
            'deadlineStatus' => $deadlineStatus,
            'monthlyGrossSalariesCents' => $monthlyGrossSalariesCents,
            'receiptStatusFilter' => $this->receiptStatusFilter,
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
            'employeeContracts' => $employeeContracts,
            'defaultTaxRate' => (float) shop_setting('default_tax_rate', 19.0),
            'taxTasks' => $taxTasks,
            'openTaxTasksCount' => $openTaxTasksCount,
            'completedTaxTasksCount' => $completedTaxTasksCount,
        ]);
    }
}
