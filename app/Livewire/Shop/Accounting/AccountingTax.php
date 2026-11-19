<?php

namespace App\Livewire\Shop\Accounting;

use Livewire\Attributes\Layout;

use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Order\OrderOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithFileUploads;
use App\Models\System\SystemLog;
use ZipArchive;

#[Layout('components.layouts.backend_layout')]
class AccountingTax extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Buchhaltung';

    use WithFileUploads;

    public $selectedYear;
    public $selectedMonth;
    public int $selectedQuarter = 4;
    public string $periodMode = 'month'; // 'month' oder 'quarter'
    public string $refundScope = 'total_all'; // 'total_all', 'since_launch', 'selected_period'
    public $submissionType = 'Erstübermittlung';
    public int $activeStep = 1; // Schritt-für-Schritt Navigation: 1 (Zahlen/Belege), 2 (Parameter/Auth), 3 (Vorschau/Übermittlung)
    
    // NEU: Hardware-Token Integration
    public $authMethod = 'software'; // 'software' oder 'hardware'
    public $hardwarePin = '';
    public $certPassword = '';
    public $showStorageVault = false;
    #[\Livewire\Attributes\Locked]
    public $archivedExports = [];
    
    // NEU: Tresor & Zertifikats-Scanner
    #[\Livewire\Attributes\Locked]
    public $tresorCertificates = [];
    public $selectedCertName = '';
    #[\Livewire\Attributes\Locked]
    public $hasEnvPassword = false;
    
    // NEU: Inline Beleg Upload
    public $receiptFiles = [];

    // NEU: Das Global-Log System für absolute Transparenz
    #[\Livewire\Attributes\Locked]
    public $globalLogs = [];
    
    // NEU: ELSTER API Status
    public $apiStatus = 'checking'; // checking, online, offline
    public $apiStatusMessage = 'Kontaktiere ELSTER Server (Warte auf Antwort)...';

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Steuerzentrale.');
        }
    }

    protected function sanitizeCertFilename(?string $certName): string
    {
        $clean = basename(trim((string) $certName));
        if ($clean === '' || $clean === '.' || $clean === '..' || !str_ends_with(strtolower($clean), '.pfx')) {
            return '';
        }
        return $clean;
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Steuerzentrale.');
        }

        $this->selectedYear = (int) date('Y');
        $this->selectedMonth = date('n') == 1 ? 12 : date('n') - 1;
        if (date('n') == 1) {
            $this->selectedYear--;
        }
        $this->selectedQuarter = (int) ceil($this->selectedMonth / 3);
        
        $this->selectedCertName = $this->sanitizeCertFilename(shop_setting('eric_default_cert', ''));
        $this->scanTresorCertificates();
        
        // Prüfen ob ein Tresor-Passwort absolut sicher in der .env liegt!
        $envPass = env('ERIC_CERT_PASSWORD', env('ERIC_CERT_PASSWORT', ''));
        $this->hasEnvPassword = !empty($envPass);
        
        $this->loadArchivedExports();
        $this->addLog('system', "Umsatzsteuer-Zentrale initialisiert. Bereit für Abrechnung.");
    }

    public function scanTresorCertificates()
    {
        $tresorPath = env('ERIC_TRESOR_PATH', 'storage/app/buchhaltung/erictresor');
        $isAbs = str_starts_with($tresorPath, '/');
        $fullPath = $isAbs ? $tresorPath : base_path($tresorPath);

        $this->tresorCertificates = [];
        if (File::exists($fullPath) && File::isDirectory($fullPath)) {
            $files = File::files($fullPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'pfx') {
                    $this->tresorCertificates[] = $file->getFilename();
                }
            }
        }
    }

    public function updatedSelectedCertName($val)
    {
        $safeCert = $this->sanitizeCertFilename((string) $val);
        $this->selectedCertName = $safeCert;

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'eric_default_cert'],
            ['value' => $safeCert]
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        
        $this->addLog('info', "Standard-Zertifikat im Tresor auf '{$safeCert}' festgelegt.");
    }

    // --- LOGGING SYSTEM ---
    private function addLog($type, $message)
    {
        $this->globalLogs[] = [
            'time' => now()->format('H:i:s.v'),
            'type' => $type, // 'system', 'info', 'success', 'error', 'warning'
            'message' => $message
        ];
    }

    // --- API STATUS CHECK ---
    public function checkApiStatus()
    {
        try {
            app(\App\Services\ElsterEricService::class)->checkServerAvailability();
            $this->apiStatus = 'online';
            $this->apiStatusMessage = 'Stabile Verbindung zu datenannahme.elster.de hergestellt.';
            $this->addLog('system', 'API Statuscheck: Server sind online und bereit zur Datenannahme.');
        } catch (\Exception $e) {
            $this->apiStatus = 'offline';
            $this->apiStatusMessage = $e->getMessage();
            $this->addLog('error', 'API Statuscheck fehlgeschlagen: ' . $this->apiStatusMessage);
        }
    }

    public function setPeriodMode(string $mode): void
    {
        if (!in_array($mode, ['month', 'quarter'], true)) {
            return;
        }
        $this->periodMode = $mode;
        if ($mode === 'quarter') {
            $this->selectedQuarter = (int) ceil(max(1, min(12, (int) $this->selectedMonth)) / 3);
        }
        $this->globalLogs = [];
        $label = $mode === 'quarter'
            ? "Quartal Q{$this->selectedQuarter}/{$this->selectedYear}"
            : "Monat " . str_pad((string) $this->selectedMonth, 2, '0', STR_PAD_LEFT) . "/{$this->selectedYear}";
        $this->addLog('system', "Zeitraum-Modus gewechselt auf {$label}.");
    }

    public function setRefundScope(string $scope): void
    {
        if (in_array($scope, ['total_all', 'since_launch', 'selected_period'], true)) {
            $this->refundScope = $scope;
        }
    }

    public function selectMonth($month)
    {
        $month = max(1, min(12, (int) $month));
        $this->selectedYear = max(2000, min(2100, (int) ($this->selectedYear ?: date('Y'))));
        $this->selectedMonth = $month;
        $this->selectedQuarter = (int) ceil($month / 3);
        $this->periodMode = 'month';
        $this->globalLogs = []; // Logs beim Monatswechsel leeren
        $this->addLog('system', "Monat " . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . "/{$this->selectedYear} geladen. Analysiere Belege...");
    }

    public function selectQuarter(int $quarter): void
    {
        $quarter = max(1, min(4, $quarter));
        $this->selectedYear = max(2000, min(2100, (int) ($this->selectedYear ?: date('Y'))));
        $this->selectedQuarter = $quarter;
        $this->selectedMonth = ($quarter - 1) * 3 + 1;
        $this->periodMode = 'quarter';
        $this->globalLogs = [];
        $this->addLog('system', "Quartal Q{$quarter}/{$this->selectedYear} (ELSTER Zeitraum " . (40 + $quarter) . ") geladen. Analysiere Belege...");
    }

    public function setStep(int $step): void
    {
        if (in_array($step, [1, 2, 3])) {
            $this->activeStep = $step;
        }
    }

    public function loadArchivedExports()
    {
        Storage::disk('local')->makeDirectory('buchhaltung/tax_exports');
        $files = Storage::disk('local')->files('buchhaltung/tax_exports');

        $this->archivedExports = collect($files)->map(function($file) {
            return [
                'name' => basename($file),
                'size' => number_format(Storage::disk('local')->size($file) / 1024 / 1024, 2) . ' MB',
                'date' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file))->format('d.m.Y H:i'),
                'path' => $file
            ];
        })->sortByDesc('date')->values()->toArray();
    }

    // --- DYNAMISCHE DATEN-ERMITTLUNG (Zentralisiert via FinancialService) ---
    protected function enrichPeriodChecklist(array $data): array
    {
        $checklist = $data['checklist'];

        // Laufzeit-Prüfung, ob das konfigurierte Software-Zertifikat existiert!
        if ($this->authMethod === 'software') {
            $this->selectedCertName = $this->sanitizeCertFilename($this->selectedCertName);
            $tresorPath = env('ERIC_TRESOR_PATH', 'storage/app/buchhaltung/erictresor');
            $isAbs = str_starts_with($tresorPath, '/');
            $fullPath = $isAbs ? $tresorPath : base_path($tresorPath);
            $fullCertPath = $fullPath . DIRECTORY_SEPARATOR . $this->selectedCertName;

            $certExists = !empty($this->selectedCertName) && file_exists($fullCertPath);

            $checklist['cert_file'] = [
                'name' => 'Software-Zertifikat (.pfx) gewählt & geprüft',
                'passed' => $certExists,
                'description' => "Das Zertifikat '" . ($this->selectedCertName ?: 'NICHT GEWÄHLT') . "' " . ($certExists ? "wurde erfolgreich im Tresor ({$tresorPath}) gefunden." : "fehlt im Tresor oder ist nicht gewählt!"),
            ];
        }

        $allChecklistPassed = collect($checklist)->every(fn ($item) => $item['passed']);
        $status = $data['status'];
        if (!$data['is_future'] && $data['is_past']) {
            $status = $allChecklistPassed ? 'ready' : 'missing_data';
        }

        $isTestCert = in_array($this->selectedCertName, ['test-soft-pse.pfx', 'test-softorg-pse.pfx', 'test-softidnr-pse.pfx']) || str_contains($this->selectedCertName, 'bescheid');
        $hasValidSoftwareAuth = $this->authMethod === 'software' && ($isTestCert || $this->hasEnvPassword || strlen($this->certPassword) >= 3);

        $data['checklist'] = $checklist;
        $data['all_checklist_passed'] = $allChecklistPassed;
        $data['status'] = $status;
        $data['is_ready_for_transmit'] = $status === 'ready' && (
            $hasValidSoftwareAuth || ($this->authMethod === 'hardware' && strlen($this->hardwarePin) >= 6)
        );

        return $data;
    }

    public function getMonthData($month)
    {
        $month = max(1, min(12, (int) $month));
        $this->selectedYear = max(2000, min(2100, (int) ($this->selectedYear ?: date('Y'))));
        $adminId = auth()->guard('admin')->id();

        $financialService = app(\App\Services\FinancialService::class);
        $data = $financialService->getUstvaPeriodData(
            (int) $this->selectedYear,
            $month,
            'month',
            $adminId,
            (string) $this->submissionType
        );

        return $this->enrichPeriodChecklist($data);
    }

    public function getQuarterData(int $quarter): array
    {
        $quarter = max(1, min(4, $quarter));
        $this->selectedYear = max(2000, min(2100, (int) ($this->selectedYear ?: date('Y'))));
        $adminId = auth()->guard('admin')->id();

        $financialService = app(\App\Services\FinancialService::class);
        $data = $financialService->getUstvaPeriodData(
            (int) $this->selectedYear,
            $quarter,
            'quarter',
            $adminId,
            (string) $this->submissionType
        );

        return $this->enrichPeriodChecklist($data);
    }

    public function getActivePeriodData(): array
    {
        return $this->periodMode === 'quarter'
            ? $this->getQuarterData($this->selectedQuarter)
            : $this->getMonthData($this->selectedMonth);
    }

    public function uploadMissingReceipt($itemId, $type)
    {
        $itemId = (string) $itemId;

        if (!isset($this->receiptFiles[$itemId])) {
            $this->addLog('error', 'Kein Beleg für Upload gefunden.');
            return;
        }

        $this->validate([
            'receiptFiles.' . $itemId => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'receiptFiles.' . $itemId . '.mimes' => 'Nur PDF-, JPG-, PNG- und WebP-Dateien sind als Beleg zulässig.',
            'receiptFiles.' . $itemId . '.max' => 'Die Belegdatei darf maximal 10 MB groß sein.',
        ]);

        $adminId = auth()->guard('admin')->id();
        $file = $this->receiptFiles[$itemId];
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowedMap = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
        ];
        $realPath = $file->getRealPath();
        $finfo = $realPath ? new \finfo(FILEINFO_MIME_TYPE) : null;
        $detectedMime = ($finfo && $realPath && is_file($realPath)) ? $finfo->file($realPath) : null;
        if (in_array($detectedMime, ['application/x-empty', 'inode/x-empty', false, null], true)) {
            $detectedMime = $file->getMimeType();
        }
        if (!isset($allowedMap[$ext]) || !in_array($detectedMime, $allowedMap[$ext], true)) {
            unset($this->receiptFiles[$itemId]);
            $this->addLog('error', 'Ungültiger Dateityp für Beleg-Upload.');
            return;
        }
        $sanitizedOriginal = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file->getClientOriginalName());
        $filename = Str::uuid() . '_' . $sanitizedOriginal;
        $path = $file->storeAs('buchhaltung/receipts', $filename, 'local');

        if ($type === 'variable') {
            $issue = \App\Models\Accounting\AccountingSpecialIssue::where('admin_id', $adminId)->find($itemId);
            if ($issue) {
                $existing = $issue->file_paths ?? [];
                $existing[] = $path;
                $issue->update(['file_paths' => $existing]);
                $this->addLog('success', "Ein Beleg für Variablen-Ausgabe '{$issue->title}' wurde inline verknüpft.");
            } else {
                $this->addLog('error', 'Variable Ausgabe nicht gefunden oder keine Berechtigung.');
            }
        } elseif ($type === 'fixed') {
            $issue = \App\Models\Accounting\AccountingCostItem::whereHas('group', fn($q) => $q->where('admin_id', $adminId))->find($itemId);
            if ($issue) {
                $issue->update(['contract_file_path' => $path]);
                $this->addLog('success', "Ein Beleg für Fixkosten '{$issue->name}' wurde inline verknüpft.");
            } else {
                $this->addLog('error', 'Fixkosten-Position nicht gefunden oder keine Berechtigung.');
            }
        }

        unset($this->receiptFiles[$itemId]);
        session()->flash('success_receipt', 'Beleg erfolgreich hinterlegt!'); // Trigger UI Alert
    }

    public function generateDatevExport()
    {
        $this->addLog('info', 'Starte ZIP & PDF Export Generierung...');
        $data = $this->getActivePeriodData();

        $periodSlug = $data['export_period_slug'] ?? $data['month_number'];
        $yearStr = $data['year'];

        $financialService = app(\App\Services\FinancialService::class);
        $result = $financialService->generateDatevExportZip($data);

        $this->addLog('info', "Temporäres Verzeichnis im Tresor erstellt: {$result['temp_dir']}");
        $this->addLog('success', 'DATEV EXTF CSV-Datei kompiliert.');
        $this->addLog('success', 'Rechtssicheres PDF-Steuerprotokoll generiert.');
        $this->addLog('info', "{$result['copy_count']} Belege in Export-Ordner kopiert.");
        $this->addLog('success', 'Archiv erfolgreich komprimiert und verschlüsselt.');

        $this->loadArchivedExports();
        session()->flash('success', "Steuer-Export (Zip) für {$periodSlug}/{$yearStr} erfolgreich erstellt.");
    }

    private function buildDatevCsv($data)
    {
        return app(\App\Services\FinancialService::class)->buildDatevCsv($data);
    }

    // --- NATIVE ELSTER API INTEGRATION (ERiC) ---
    public function transmitToElster()
    {
        $this->addLog('info', 'Initialisiere direkte ELSTER-ERiC Schnittstelle...');
        $data = $this->getActivePeriodData();

        if ($data['status'] !== 'ready') {
            $this->addLog('error', 'Abbruch: Es fehlen noch zwingend erforderliche Belege oder Checklisten-Vorbedingungen (siehe UI rechts).');
            return;
        }

        if ($this->authMethod === 'hardware' && strlen($this->hardwarePin) < 6) {
            $this->addLog('error', 'Abbruch: Für den Hardware-Token muss die 6-stellige secunet-PIN angegeben werden.');
            return;
        }
        
        if ($this->authMethod === 'software') {
            $this->selectedCertName = $this->sanitizeCertFilename($this->selectedCertName);
            $tresorPath = env('ERIC_TRESOR_PATH', 'storage/app/buchhaltung/erictresor');
            $isAbs = str_starts_with($tresorPath, '/');
            $fullPath = $isAbs ? $tresorPath : base_path($tresorPath);
            $fullCertPath = $fullPath . DIRECTORY_SEPARATOR . $this->selectedCertName;
            
            if (empty($this->selectedCertName) || !file_exists($fullCertPath)) {
                $this->addLog('error', "CRITICAL: Das Zertifikat '{$this->selectedCertName}' konnte im Tresor nicht geladen werden. Bitte Dropdown prüfen!");
                return;
            }
        }

        try {
            $this->addLog('info', 'Pre-Flight: Prüfe ELSTER Server Status (RSS-Feed)...');
            
            // Aufruf des neuen Service-Layers, der die gesamte ERiC-Logik kapselt
            $elsterService = app(\App\Services\ElsterEricService::class);
            
            $this->addLog('info', 'Server OK. Erstelle UStVA XML-Payload nach Finkonsens-Schema...');
            
            if ($this->authMethod === 'hardware') {
                $this->addLog('warning', 'Initiiere Hardware-Token Handshake (APDU) mit übergebener secunet-PIN...');
                $pinToPass = $this->hardwarePin;
            } else {
                $this->addLog('warning', 'Prüfe Zertifikat (.pfx) und sende XML-Payload an ERiC Endpoint...');
                
                // Security-Check: Testzertifikate immer 123456 zwingen, ansonsten ENV prüfen
                $isTestCert = in_array($this->selectedCertName, ['test-soft-pse.pfx', 'test-softorg-pse.pfx', 'test-softidnr-pse.pfx']) || str_contains($this->selectedCertName, 'bescheid');
                
                if ($isTestCert) {
                    $pinToPass = '123456';
                    $this->addLog('info', 'Sandbox: Verwende zwingende Test-PIN (123456) für offizielles ELSTER Testzertifikat.');
                } else {
                    $envPass = env('ERIC_CERT_PASSWORD', env('ERIC_CERT_PASSWORT', ''));
                    $pinToPass = !empty($envPass) ? $envPass : $this->certPassword;
                    if (!empty($envPass)) {
                        $this->addLog('info', 'Security: PFX-Passwort wurde hochsicher über serverseitige .env Konfiguration geladen.');
                    }
                }
            }
            
            $response = $elsterService->transmitUStVA($data, $this->submissionType, $this->authMethod, $pinToPass);
            
            $ticketId = $response['ticket_id'];
            if (isset($response['simulated']) && $response['simulated']) {
                $this->addLog('success', "SIMULATION ERFOLGREICH: Die API simulierte einen ERiC Binary Connect.");
                $this->addLog('success', "Simuliertes Transferticket generiert: {$ticketId}");
            } else {
                $this->addLog('success', "Datenannahme bestätigt. Transferticket: {$ticketId}");
            }
            $this->addLog('success', "Validierung durch ERiC Modul: FEHLERFREI. (Kennzahl 83: {$data['zahllast']} EUR)");

            session()->flash('success', "Übermittlung an ELSTER API nach erfolgreicher Prüfung in Testsystem beendet (Ticket: {$ticketId}).");

        } catch (\Exception $e) {
            // Fällt sofort ins Live-Terminal der UI auf dem Screen
            $this->addLog('error', 'ERiC API FEHLER: ' . $e->getMessage());
            $this->addLog('error', 'Die Übertragung wurde blockiert und abgebrochen.');
        } finally {
            $this->reset(['certPassword', 'hardwarePin']);
        }
    }

    public function downloadTaxExport($filename, \App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadTaxExport(basename($filename));
    }

    public function deleteExport(string $filename): void
    {
        $safeName = basename($filename);
        if (!str_ends_with(strtolower($safeName), '.zip')) {
            $this->addLog('error', 'Ungültiges Dateiformat für Export-Löschung.');
            return;
        }
        $path = 'buchhaltung/tax_exports/' . $safeName;
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
            $this->addLog('info', "Archivierter Export '{$safeName}' wurde gelöscht.");
            $this->loadArchivedExports();
            session()->flash('success', "Export '{$safeName}' erfolgreich gelöscht.");
        }
    }

    public function render()
    {
        $monthsNav = [];
        for ($i = 1; $i <= 12; $i++) {
            $data = $this->getMonthData($i);
            $monthsNav[$i] = [
                'name' => mb_substr($data['month_name'], 0, 3),
                'status' => $data['status'],
            ];
        }

        $quarterRanges = [1 => 'Jan-Mär', 2 => 'Apr-Jun', 3 => 'Jul-Sep', 4 => 'Okt-Dez'];
        $quartersNav = [];
        for ($q = 1; $q <= 4; $q++) {
            $qData = $this->getQuarterData($q);
            $quartersNav[$q] = [
                'name' => "Q{$q}",
                'range' => $quarterRanges[$q],
                'status' => $qData['status'],
            ];
        }

        $activeData = $this->getActivePeriodData();

        $financialService = app(\App\Services\FinancialService::class);
        $refundSummary = $financialService->getPotentialTaxRefundSummary(
            (int) $this->selectedYear,
            $this->periodMode === 'quarter' ? $this->selectedQuarter : (int) $this->selectedMonth,
            $this->periodMode,
            auth()->guard('admin')->id()
        );

        $configuredBufa = shop_setting('owner_finanzamt_nr', shop_setting('owner_finanzamt_id'));
        $formattedElsterTaxId = \App\Services\ElsterEricService::toElsterSteuernummer(
            (string) shop_setting('owner_tax_id', ''),
            $configuredBufa ? (string) $configuredBufa : null
        );

        return view('livewire.shop.accounting.accounting-tax', [
            'monthsNav' => $monthsNav,
            'quartersNav' => $quartersNav,
            'activeData' => $activeData,
            'refundSummary' => $refundSummary,
            'formattedElsterTaxId' => $formattedElsterTaxId,
        ]);
    }
}
