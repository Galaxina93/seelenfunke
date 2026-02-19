<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceSpecialIssue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class FinancialQuickEntry extends Component
{
    use WithFileUploads;

    // Forms: Schnellerfassung Sonderausgaben
    #[Rule('required', message: 'Bitte geben Sie einen Titel an.')]
    public $specialTitle = '';

    public $specialCategory = '';

    #[Rule('required|numeric', message: 'Bitte geben Sie einen gültigen Betrag an.')]
    public $specialAmount = '';

    #[Rule('required', message: 'Bitte wählen Sie ein Datum.')]
    public $specialDate;

    public $specialLocation = '';
    public $specialIsBusiness = false;

    // Business Felder Schnellerfassung
    public $specialTaxRate = 19;
    public $specialInvoiceNumber = '';

    // Uploads Schnellerfassung
    #[Rule(['specialFiles.*' => 'max:10240'])]
    public $specialFiles = [];

    // UI Feedback für Auto-Erkennung
    public $isAutoFilled = false;

    public function mount()
    {
        $this->specialDate = date('Y-m-d');
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    // --- View Helpers ---
    public function getCategoriesProperty()
    {
        return FinanceCategory::where('admin_id', $this->getAdminId())
            ->orderByDesc('usage_count')
            ->pluck('name');
    }

    // --- E-Rechnungs Parser (Magic Upload) ---

    /**
     * Wird automatisch von Livewire aufgerufen, wenn Dateien hochgeladen wurden.
     */
    public function updatedSpecialFiles()
    {
        $this->isAutoFilled = false;

        foreach ($this->specialFiles as $file) {
            $mime = $file->getMimeType();

            // WICHTIG: Nutzung von getRealPath() für temporäre Livewire Dateien
            $content = file_get_contents($file->getRealPath());

            // 1. Check: Ist es eine XML Datei?
            if (str_contains($mime, 'xml') || str_ends_with($file->getClientOriginalName(), '.xml')) {
                if ($this->parseInvoiceXml($content)) {
                    break;
                }
            }
            // 2. Check: Ist es eine PDF?
            elseif (str_contains($mime, 'pdf')) {
                $xmlContent = $this->extractXmlFromPdf($content);
                if ($xmlContent && $this->parseInvoiceXml($xmlContent)) {
                    break;
                }
            }
        }
    }

    /**
     * Versucht XML-Inhalt zu parsen (Unterstützt ZUGFeRD CII und XRechnung UBL)
     */
    private function parseInvoiceXml($xmlContent)
    {
        try {
            libxml_use_internal_errors(true);

            // TRICK: Wir entfernen alle Namespaces (rsm:, ram:, etc.) aus dem String.
            // Das ist zwar "dirty", aber für das Auslesen von Werten viel robuster,
            // weil wir uns nicht um Namespace-Registrierung kümmern müssen.
            $cleanXml = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlContent);
            $xml = simplexml_load_string($cleanXml);

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
        // (Einfache Logik: Wir setzen Standard 19%, User kann ändern)
        $this->specialTaxRate = 19;
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
        foreach ($this->specialFiles as $file) {
            $path = $file->store('financial/receipts', 'public');
            $filePaths[] = $path;
        }

        // Aufgabe 1: Betragslogik
        $rawAmount = str_replace(',', '.', $this->specialAmount);
        $finalAmount = (float)$rawAmount;
        // Wenn kein Minus davor steht, ist es positiv (Einnahme), sonst negativ (Ausgabe)
        // Aber normalerweise sind Ausgaben negativ.
        // Prompt sagt: "Minus vorschreiben = negativ, nur Zahl = positiv".
        // Also nehmen wir den Wert 1:1, da der User das Vorzeichen selbst setzt.

        FinanceSpecialIssue::create([
            'admin_id' => $this->getAdminId(),
            'title' => $this->specialTitle,
            'category' => $this->specialCategory ?: 'Sonstiges',
            'amount' => $finalAmount,
            'execution_date' => $this->specialDate,
            'location' => $this->specialLocation,
            'is_business' => $this->specialIsBusiness,
            'tax_rate' => $this->specialIsBusiness ? $this->specialTaxRate : null,
            'invoice_number' => $this->specialIsBusiness ? $this->specialInvoiceNumber : null,
            'file_paths' => $filePaths
        ]);

        $this->trackCategoryUsage($this->specialCategory);

        $this->reset(['specialTitle', 'specialAmount', 'specialLocation', 'specialCategory', 'specialIsBusiness', 'specialFiles', 'specialInvoiceNumber', 'specialTaxRate', 'isAutoFilled']);
        $this->specialDate = date('Y-m-d');
        $this->specialIsBusiness = false; // Reset auf aus

        $this->dispatch('special-issue-created');
        session()->flash('success', 'Eintrag erfolgreich erstellt.');
    }

    private function trackCategoryUsage($categoryName)
    {
        if(empty($categoryName)) return;

        $cat = FinanceCategory::withTrashed()
            ->where('admin_id', $this->getAdminId())
            ->where('name', $categoryName)
            ->first();

        if ($cat) {
            if ($cat->trashed()) {
                $cat->restore();
            }
            $cat->increment('usage_count');
        } else {
            FinanceCategory::create([
                'admin_id' => $this->getAdminId(),
                'name' => $categoryName,
                'usage_count' => 1
            ]);
        }
    }

    public function render()
    {
        return view('livewire.shop.financial.financial-quick-entry.financial-quick-entry', [
            'categories' => $this->categories
        ]);
    }
}
