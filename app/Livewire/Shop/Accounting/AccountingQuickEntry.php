<?php

namespace App\Livewire\Shop\Accounting;

use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Services\AI\ReceiptAiScannerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithDepartmentTheming;

class AccountingQuickEntry extends Component
{
    use WithFileUploads, WithDepartmentTheming;

    public string $themingDepartment = 'Buchhaltung';

    // Forms: Schnellerfassung Sonderausgaben
    #[Rule('required', message: 'Bitte geben Sie einen Titel an.')]
    public $specialTitle = '';

    public $specialCategory = '';

    #[Rule('required', message: 'Bitte geben Sie einen gültigen Betrag an.')]
    public $specialAmount = '';

    #[Rule('required', message: 'Bitte wählen Sie ein Datum.')]
    public $specialDate;

    public $specialLocation = '';
    public $specialIsBusiness = false;

    // Business Felder Schnellerfassung
    public $specialTaxRate = 19;
    public $specialInvoiceNumber = '';

    // Uploads Schnellerfassung
    #[Rule(['specialFiles.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,zip,docx,txt,xml|max:15360'])]
    public $specialFiles = [];

    // Foto-Erfassung & KI-Scan
    #[Rule('nullable|file|mimes:pdf,jpg,jpeg,png,webp,zip,docx,txt,xml|max:15360')]
    public $specialPhoto;

    public bool $autoSaveAfterScan = false;
    public string $aiScanSuccessMessage = '';
    public bool $isAiScanning = false;

    // UI Feedback für Auto-Erkennung
    public $isAutoFilled = false;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Beleg-Schnellerfassung.');
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Beleg-Schnellerfassung.');
        }

        $this->specialDate = date('Y-m-d');
        $this->specialTaxRate = (float)shop_setting('default_tax_rate', 19.0);
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    // --- View Helpers ---
    public function getCategoriesProperty()
    {
        return AccountingCategory::where('admin_id', $this->getAdminId())
            ->orderByDesc('usage_count')
            ->pluck('name');
    }

    public function isPhotoPreviewable(): bool
    {
        if (!$this->specialPhoto) {
            return false;
        }

        try {
            if (method_exists($this->specialPhoto, 'isPreviewable')) {
                return (bool) $this->specialPhoto->isPreviewable();
            }

            $ext = strtolower(pathinfo($this->specialPhoto->getClientOriginalName(), PATHINFO_EXTENSION));
            return in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'], true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // --- Beleg- & Foto-Parser (KI-Vision & E-Rechnungen) ---

    /**
     * Wird aufgerufen, wenn über die Kamera oder den Foto-Upload ein Bild gewählt wird.
     */
    public function updatedSpecialPhoto()
    {
        if (!$this->specialPhoto) return;

        $this->validateOnly('specialPhoto');

        // Foto auch zu den Beleg-Dateien hinzufügen, damit es beim Speichern in storage gesichert wird
        if (!in_array($this->specialPhoto, $this->specialFiles, true)) {
            $this->specialFiles[] = $this->specialPhoto;
        }

        $this->processFileWithAiOrXml($this->specialPhoto);
    }

    /**
     * Wird automatisch von Livewire aufgerufen, wenn Dateien über das Standard-Feld hochgeladen wurden.
     */
    public function updatedSpecialFiles()
    {
        if (empty($this->specialFiles)) return;

        $this->validateOnly('specialFiles.*');

        $lastFile = end($this->specialFiles);
        if ($lastFile) {
            $this->processFileWithAiOrXml($lastFile);
        }
    }

    /**
     * Führt zuerst E-Rechnungs-Checks (XML/ZUGFeRD) und anschließend KI-Multimodal-Vision-Scan aus.
     */
    public function processFileWithAiOrXml($file)
    {
        $this->isAutoFilled = false;
        $this->aiScanSuccessMessage = '';
        $this->isAiScanning = true;

        try {
            $mime = $file->getMimeType();
            $origName = $file->getClientOriginalName();
            $content = file_get_contents($file->getRealPath());

            // 1. Check: Ist es eine XML Datei?
            if (str_contains($mime, 'xml') || str_ends_with($origName, '.xml')) {
                if ($this->parseInvoiceXml($content)) {
                    $this->isAiScanning = false;
                    $this->aiScanSuccessMessage = "E-Rechnung (XML) erkannt: {$this->specialTitle} ({$this->specialAmount} €)";
                    if ($this->autoSaveAfterScan) {
                        $this->createSpecial();
                    }
                    return true;
                }
            }

            // 2. Check: Ist es eine PDF mit eingebettetem ZUGFeRD-XML?
            if (str_contains($mime, 'pdf')) {
                $xmlContent = $this->extractXmlFromPdf($content);
                if ($xmlContent && $this->parseInvoiceXml($xmlContent)) {
                    $this->isAiScanning = false;
                    $this->aiScanSuccessMessage = "E-Rechnung (ZUGFeRD PDF) erkannt: {$this->specialTitle} ({$this->specialAmount} €)";
                    if ($this->autoSaveAfterScan) {
                        $this->createSpecial();
                    }
                    return true;
                }
            }

            // 3. KI Multimodal Vision Scan (Fotos, Belege, Quittungen, normale PDFs)
            $categories = $this->categories->toArray();
            $aiResult = ReceiptAiScannerService::analyze($file, $categories);

            if ($aiResult && !empty($aiResult['amount_abs'])) {
                $this->applyAiResult($aiResult);
                $this->isAiScanning = false;
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('Belegscan Fehler in AccountingQuickEntry: ' . $e->getMessage());
        }

        $this->isAiScanning = false;
        return false;
    }

    public function applyAiResult(array $res)
    {
        $this->specialTitle = $res['title'];
        $this->specialAmount = $res['amount_formatted']; // z.B. -29.99
        $this->specialCategory = $res['category'];
        $this->specialDate = $res['date'];
        $this->specialLocation = $res['location'];
        $this->specialIsBusiness = $res['is_business'];
        $this->specialTaxRate = $res['tax_rate'];
        if (!empty($res['invoice_number'])) {
            $this->specialInvoiceNumber = $res['invoice_number'];
        }
        $this->isAutoFilled = true;
        $this->aiScanSuccessMessage = "Beleg erkannt: {$res['title']} ({$res['amount_formatted']} €)";

        if ($this->autoSaveAfterScan) {
            $this->createSpecial();
        }
    }

    /**
     * Versucht XML-Inhalt zu parsen (Unterstützt ZUGFeRD CII und XRechnung UBL)
     */
    private function parseInvoiceXml($xmlContent)
    {
        try {
            if (!is_string($xmlContent) || preg_match('/<!(DOCTYPE|ENTITY)/i', $xmlContent)) {
                return false;
            }

            libxml_use_internal_errors(true);

            // TRICK: Wir entfernen alle Namespaces (rsm:, ram:, etc.) aus dem String.
            // Das ist zwar "dirty", aber für das Auslesen von Werten viel robuster,
            // weil wir uns nicht um Namespace-Registrierung kümmern müssen.
            $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlContent);
            $xml = simplexml_load_string($cleanXml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);

            if (!$xml) return false;

            // --- ZUGFeRD / CrossIndustryInvoice (Namespace-bereinigt) ---
            if (isset($xml->ExchangedDocument) || str_contains($xmlContent, 'CrossIndustryInvoice')) {

                // 1. Rechnungsnummer
                if (isset($xml->ExchangedDocument->ID)) {
                    $this->specialInvoiceNumber = (string)$xml->ExchangedDocument->ID;
                }

                // 2. Datum (Format YYYYMMDD oder YYYY-MM-DDT...)
                if (isset($xml->ExchangedDocument->IssueDateTime->DateTimeString)) {
                    $rawDate = (string)$xml->ExchangedDocument->IssueDateTime->DateTimeString;
                    $this->parseFlexibleDate($rawDate);
                }

                // 3. Summe (Grand Total)
                // Pfad: SupplyChainTradeTransaction -> ApplicableHeaderTradeSettlement -> SpecifiedTradeSettlementHeaderMonetarySummation -> GrandTotalAmount
                if (isset($xml->SupplyChainTradeTransaction->ApplicableHeaderTradeSettlement->SpecifiedTradeSettlementHeaderMonetarySummation->GrandTotalAmount)) {
                    $this->specialAmount = (float)$xml->SupplyChainTradeTransaction->ApplicableHeaderTradeSettlement->SpecifiedTradeSettlementHeaderMonetarySummation->GrandTotalAmount;
                }

                // 4. Verkäufer Name
                // Pfad: SupplyChainTradeTransaction -> ApplicableHeaderTradeAgreement -> SellerTradeParty -> Name
                if (isset($xml->SupplyChainTradeTransaction->ApplicableHeaderTradeAgreement->SellerTradeParty->Name)) {
                    $this->specialTitle = (string)$xml->SupplyChainTradeTransaction->ApplicableHeaderTradeAgreement->SellerTradeParty->Name;

                    // Wir setzen den Verkäufer auch als Kategorie-Vorschlag, falls noch leer
                    if(empty($this->specialCategory)) {
                        $this->specialCategory = 'Einkauf'; // Fallback oder Logik für Mapping
                    }
                }

                $this->finalizeAutoFill();
                return true;
            }

            // --- XRechnung / UBL (Namespace-bereinigt) ---
            if (isset($xml->LegalMonetaryTotal) || str_contains($xmlContent, 'Invoice')) {

                if (isset($xml->ID)) $this->specialInvoiceNumber = (string)$xml->ID;

                if (isset($xml->IssueDate)) {
                    $this->parseFlexibleDate((string)$xml->IssueDate);
                }

                if (isset($xml->LegalMonetaryTotal->TaxInclusiveAmount)) {
                    $this->specialAmount = (float)$xml->LegalMonetaryTotal->TaxInclusiveAmount;
                }

                // Verkäufer Name
                if (isset($xml->AccountingSupplierParty->Party->PartyName->Name)) {
                    $this->specialTitle = (string)$xml->AccountingSupplierParty->Party->PartyName->Name;
                } elseif (isset($xml->AccountingSupplierParty->Party->PartyLegalEntity->RegistrationName)) {
                    $this->specialTitle = (string)$xml->AccountingSupplierParty->Party->PartyLegalEntity->RegistrationName;
                }

                $this->finalizeAutoFill();
                return true;
            }

        } catch (\Exception $e) {
            // Fehler stillschweigend ignorieren, User muss tippen
            return false;
        }

        return false;
    }

    private function parseFlexibleDate($rawDate)
    {
        try {
            // ZUGFeRD Format 102 (YYYYMMDD)
            if (strlen($rawDate) == 8 && is_numeric($rawDate)) {
                $this->specialDate = Carbon::createFromFormat('Ymd', $rawDate)->format('Y-m-d');
            } else {
                // Versuche Standard Carbon Parsing
                $this->specialDate = Carbon::parse($rawDate)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            $this->specialDate = date('Y-m-d'); // Fallback heute
        }
    }

    private function finalizeAutoFill()
    {
        $this->specialIsBusiness = true;
        $this->isAutoFilled = true;

        // Prüfen ob Steuer vorhanden ist für den Tax Rate
        // (Einfache Logik: Wir setzen Standard, User kann ändern)
        $this->specialTaxRate = (float)shop_setting('default_tax_rate', 19.0);
    }

    /**
     * Sucht nach eingebettetem ZUGFeRD XML in einem PDF String.
     * Dies ist eine Regex-Lösung, um keine PDF-Parser-Dependency zu benötigen.
     */
    private function extractXmlFromPdf($pdfContent)
    {
        // ZUGFeRD XML beginnt meist mit <rsm:CrossIndustryInvoice
        // Wir suchen den Start-Tag und den End-Tag

        $pattern = '/<rsm:CrossIndustryInvoice.*?<\/rsm:CrossIndustryInvoice>/s';
        if (preg_match($pattern, $pdfContent, $matches)) {
            return $matches[0];
        }

        // Alternative: Manchmal heißt der Root Node anders oder namespace ist anders gebunden
        // Wir suchen generischer nach typischen ZUGFeRD Headern
        $pattern2 = '/<(?:\w+:)?CrossIndustryInvoice.*?<\/(?:\w+:)?CrossIndustryInvoice>/s';
        if (preg_match($pattern2, $pdfContent, $matches)) {
            return $matches[0];
        }

        return null;
    }


    // --- Actions ---

    public function createSpecial()
    {
        $this->validate();

        $filePaths = [];
        $filesToStore = $this->specialFiles;
        if ($this->specialPhoto && !in_array($this->specialPhoto, $filesToStore, true)) {
            $filesToStore[] = $this->specialPhoto;
        }

        $allowedMap = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'txt' => ['text/plain'],
            'xml' => ['text/xml', 'application/xml', 'text/plain'],
        ];

        foreach ($filesToStore as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $ext = strtolower((string) $file->getClientOriginalExtension());
                $realPath = $file->getRealPath();
                $finfo = $realPath ? new \finfo(FILEINFO_MIME_TYPE) : null;
                $detectedMime = ($finfo && $realPath && is_file($realPath)) ? $finfo->file($realPath) : null;
                if (!isset($allowedMap[$ext]) || !in_array($detectedMime, $allowedMap[$ext], true)) {
                    $this->addError('specialFiles', 'Ungültiger Dateityp.');
                    return;
                }
                $path = $file->storeAs('buchhaltung/receipts', \Illuminate\Support\Str::uuid() . '.' . $ext, 'local');
                $filePaths[] = $path;
            }
        }

        // Aufgabe 1: Betragslogik
        $rawAmount = str_replace(',', '.', (string) $this->specialAmount);
        $finalAmount = (float)$rawAmount;
        $safeTaxRate = max(0.0, min(100.0, (float) $this->specialTaxRate));
        // Wenn kein Minus davor steht, ist es positiv (Einnahme), sonst negativ (Ausgabe)
        // Aber normalerweise sind Ausgaben negativ.
        // Prompt sagt: "Minus vorschreiben = negativ, nur Zahl = positiv".
        // Also nehmen wir den Wert 1:1, da der User das Vorzeichen selbst setzt.

        AccountingSpecialIssue::create([
            'admin_id' => $this->getAdminId(),
            'title' => $this->specialTitle,
            'category' => $this->specialCategory ?: 'Sonstiges',
            'amount' => $finalAmount,
            'execution_date' => $this->specialDate,
            'location' => $this->specialLocation,
            'is_business' => $this->specialIsBusiness,
            'tax_rate' => $this->specialIsBusiness ? $safeTaxRate : null,
            'invoice_number' => $this->specialIsBusiness ? $this->specialInvoiceNumber : null,
            'file_paths' => $filePaths
        ]);

        $this->trackCategoryUsage($this->specialCategory);

        $savedTitle = $this->specialTitle;
        $savedAmount = $this->specialAmount;

        $this->reset([
            'specialTitle', 'specialAmount', 'specialLocation', 'specialCategory',
            'specialIsBusiness', 'specialFiles', 'specialPhoto', 'specialInvoiceNumber',
            'specialTaxRate', 'isAutoFilled', 'aiScanSuccessMessage', 'isAiScanning'
        ]);
        $this->specialDate = date('Y-m-d');
        $this->specialIsBusiness = false;

        $this->dispatch('special-issue-created');
        session()->flash('success', "Eintrag '{$savedTitle}' ({$savedAmount} €) erfolgreich erstellt.");
    }

    private function trackCategoryUsage($categoryName)
    {
        if(empty($categoryName)) return;

        $cat = AccountingCategory::withTrashed()
            ->where('admin_id', $this->getAdminId())
            ->where('name', $categoryName)
            ->first();

        if ($cat) {
            if ($cat->trashed()) {
                $cat->restore();
            }
            $cat->increment('usage_count');
        } else {
            AccountingCategory::create([
                'admin_id' => $this->getAdminId(),
                'name' => $categoryName,
                'usage_count' => 1
            ]);
        }
    }

    public function render()
    {
        return view('livewire.shop.accounting.accounting-quick-entry.accounting-quick-entry', [
            'categories' => $this->categories
        ]);
    }
}
