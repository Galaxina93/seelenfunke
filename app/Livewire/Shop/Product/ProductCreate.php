<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;

use App\Models\Product\Product;
use App\Services\ConfiguratorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ProductCreate extends Component
{
    use WithFileUploads, WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    public $viewMode = 'list';
    public string $layoutMode = 'large'; // 'large' (Groß), 'medium' (Mittel), 'list' (Listenansicht)
    public $showArchived = false;
    #[\Livewire\Attributes\Locked]
    public ?Product $product = null;

    public $currentStep = 1;
    public $totalSteps = 4;

    // --- SCHRITT 1: Basis, Preis & SEO ---
    public $name = '';
    public $type = 'physical'; // NEU: Standardwert
    public string $collection_line = 'manufaktur'; // 'manufaktur' | 'atelier' | 'voucher' | 'service'
    public $is_personalizable = true; // NEU
    public $three_d_active = true;
    public $short_description = '';
    public $description = '';

    // Preis & Steuer
    public $price_input = '';
    public $compare_price_input = '';
    public $purchase_price_input = ''; // EK Preis in Cent (aber Float-Input)

    // Staffelpreise (Array für Livewire Binding)
    public $tiers = [];

    // Identifikatoren
    public $sku = '';
    public $barcode = '';
    public $brand = '';
    public $supplier_id = null;
    public $reorder_url = '';

    // Status (Enum)
    public $status = 'draft';

    // --- Versanddaten (Nur bei Type = physical)
    public $weight = 0; // Gramm
    public $packaging_weight = 0; // Tara / Verpackung in Gramm
    public $height = 0; // mm
    public $width = 0;  // mm
    public $length = 0; // mm
    public $shipping_class = ''; // NEU hinzugefügt für das Shipping Panel

    // --- Produktion & Kalkulation (Nur bei Type = physical)
    public $laser_runtime_minutes = null;
    public $electricity_wear_factor = 1;
    public $packaging_cost_input = ''; // in Euro
    public $marketing_cost_percent = 15.00; // NEU: Marketing Anteil in Prozent

    // SEO
    public $seo_title = '';
    public $seo_description = '';
    public $slug_input = '';

    // --- Suche, Sortierung & Medien ---
    public $search = '';
    public string $sortBy = 'newest';
    public $new_media = [];
    public $new_video;
    public $new_preview_image;

    // NEU: Für den 3D Upload
    public $new_3d_model;
    public $new_3d_background;
    public $new_background_video;
    public bool $autoOptimize3dOnUpload = true;

    // NEU: Für den Digital-Upload
    public $new_digital_file;

    // NEU: Für optionale digitale Begleitdokumente (z. B. Echtheitszertifikat, Pflegehinweise)
    public $new_digital_documents = [];
    public string $new_digital_document_title = '';
    public ?string $digitalDocumentsSavedText = null;

    // NEU: Für Saison- & Feiertags-Hauptbilder unter "2. Medien"
    public array $seasonal_media_uploads = [];

    // --- SCHRITT 3: Attribute & Lager ---
    public $productAttributes = [
        // Physisch
        'Material' => '',
        'Druck' => '',
        'Technik' => '',
        'Größe' => '',
        'Gewicht' => '',
        'Verpackung' => '',
        'Lieferzeit' => '3-5 Werktage',
        'Farbe' => '',

        // Digital
        'Format' => '',
        'Seiten' => '',
        'Sprache' => 'Deutsch',
        'Auslieferung' => 'Sofort-Download',

        // Service
        'Dauer' => '',
        'Ort' => 'Online',
        'Experte' => ''
    ];

    public $track_quantity = true;
    public $quantity = 0;
    public $continue_selling = false;
    public $out_of_stock_lead_days_min = '';
    public $out_of_stock_lead_days_max = '';

    // --- Info Texte & Kompakte Rechtshinweise (GPSR, LUCID, CE, LFGB, TextilG) ---
    public $infoTexts = [
        'name' => 'Der offizielle Produktname für Shop, Rechnungen und Lieferscheine.',
        'price' => 'Verkaufspreis im Shop (Brutto/Netto gemäß Steuereinstellung).',
        'purchase_price' => 'Dein realer Netto-Einkaufspreis des Rohlings. Wird für Margen- & Unit-Economics-Berechnung verwendet.',
        'legal_type' => 'Produktsicherheit: Typische Deko-Gegenstände dürfen KEIN CE-Zeichen tragen (bei Spielzeug-Optik Hinweis: „Kein Spielzeug“). Bedarfsgegenstände mit Lebensmittelkontakt (z. B. Gläser, Bretter) erfordern Konformitätserklärung & Glas-Gabel-Symbol.',
        'description' => 'Produktbeschreibung im Shop. Pflichtangaben beachten: Bei Textilien Faserzusammensetzung nennen (z. B. „100 % Baumwolle“); bei Deko in Spielzeug-Optik Warnhinweis aufnehmen.',
        'sku' => 'SKU (Stock Keeping Unit) – Eindeutige Artikelnummer (Pflichtfeld).',
        'barcode' => 'EAN / GTIN für Scanner und externe Marktplätze.',
        'brand' => 'Marke / Hersteller. GPSR-Pflicht (seit 13.12.2024): Jedes physische Produkt bzw. dessen Verpackung muss zwingend mit Namen und Postanschrift des Herstellers gekennzeichnet sein.',
        'supplier' => 'Zugeordneter B2B-Lieferant für Einkauf, Lieferzeiten und Nachbestellungen.',
        'reorder_url' => 'Direkter Link zum Rohling im B2B-Shop des Lieferanten. Erkennt bekannte Lieferanten automatisch anhand der Domain.',
        'seo' => 'Meta-Titel (optimal max. 60 Zeichen) & Meta-Description (max. 160 Zeichen) für Google-Suchergebnisse.',
        'slug' => 'Der sprechende URL-Pfad des Produkts unter /produkt/...',
        'categories_help' => 'Ordne das Produkt einer oder mehreren Shop-Kategorien zu, damit Kunden es über die Navigation und Filter im Shop finden.',
        'attributes_help' => 'Technische & optische Eigenschaften (z. B. Material, Größe, Veredelung). Gesetzlicher Hinweis: Bei Textilien exakte Faserzusammensetzung angeben; bei mehreren kommagetrennten Werten können daraus in Unter-Schritt 3 Varianten gebildet werden.',
        'variants_help' => 'Erzeugt aus kommagetrennten Eigenschaften (z. B. Größe oder Farbe) automatisch kaufbare Produktvarianten mit eigener SKU, eigenem Preis und Bestand.',
        'lucid_shipping_overview' => 'Verpackungsgesetz (LUCID) & physische Versanddaten. Jede Versand- und Produktverpackung muss bei einem Dualen System lizenziert sein (Recycling-Symbole sind in DE freiwillig, bei Versand nach Frankreich gilt Triman-Pflicht).',
        'packaging_catalog_pick' => '1-Klick Zuweisung aus deinem Verpackungskatalog: Übernimmt automatisch den Verpackungs-EK, das Tara-Gewicht, die Versandklasse sowie die exakten LUCID-Materialfraktionen (Papier/Pappe, Kunststoff, Verbund).',
        'product_weight_grams' => 'Reines Netto-Eigengewicht des Artikels ohne Versandverpackung in Gramm (wird für die Produktbeschreibung und zusammen mit Tara für das DHL-Versandgewicht genutzt).',
        'packaging_weight_grams' => 'Tara-Gewicht der Verpackung in Gramm. VerpackG-Pflicht (LUCID): Fließt in die Jahresmengenmeldung für dein Duales System ein.',
        'shipping_class' => 'Steuert die Versandkosten-Logik und das Versandformat (z. B. Großbrief / Luftpolsterversandtasche bis 20 mm Höhe vs. DHL Paket).',
        'dimensions_mm' => 'Exakte Produktmaße (Länge × Breite × Höhe in Millimetern). Wichtig für die Maßskizze im Shop, die Konfigurator-Proportionen und die Prüfung auf Großbrief-Tauglichkeit.',
        'inventory_overview' => 'Steuert die Bestandsführung im Lager sowie die Buchbarkeit im Shop.',
        'track_quantity' => 'Wenn aktiv, wird bei jeder Bestellung der Lagerbestand automatisch reduziert und bei niedrigem Bestand eine Nachbestell-Warnung ausgelöst.',
        'stock_quantity' => 'Aktuell physisch im Lager vorhandene Stückzahl der Rohlinge (bei Varianten ohne eigenen Bestand gilt dieser Eltern-Bestand).',
        'continue_selling' => 'Wenn aktiv, bleibt das Produkt im Shop bestellbar, auch wenn der Lagerbestand rechnerisch auf 0 fällt (z. B. bei kurzfristig nachbestellbaren Rohlingen).',
        'out_of_stock_lead_days' => 'Zusätzliche Lieferanten-Vorlaufzeit (in Werktagen), die bei Bestand 0 automatisch auf deine Manufaktur-Lieferzeit addiert wird. Bleibt das Feld leer, greift automatisch die hinterlegte Lieferzeit des verknüpften Lieferanten.',
        'unit_economics_overview' => 'Interne Stückkosten-Kalkulation (Unit Economics) für deine reale Netto-Marge in den „Wahren Zahlen“ und im Angebots-Autopiloten.',
        'laser_runtime_minutes' => 'Durchschnittliche Gravur- bzw. Bearbeitungsdauer pro Stück am Laser in Minuten.',
        'electricity_wear_factor' => 'Maschinenstundensatz inkl. Stromverbrauch, Absaugfilter und Laserquellen-Verschleiß pro Minute (in Cent/Min.).',
        'packaging_cost' => 'Netto-Einkaufspreis der verwendeten Standard-Versandverpackung inkl. Inlay/Polsterung (wird bei Katalog-Auswahl in Unter-Schritt 4 automatisch befüllt).',
        'marketing_cost_percent' => 'Kalkulatorischer Anteil für Ads / Kundenakquise (CAC) in Prozent vom Netto-Verkaufspreis.',
        'stripe_fees' => 'Zahlungsanbieter-Gebühren (Kreditkarte, Klarna, Apple Pay, PayPal) werden anhand deiner globalen Shop-Einstellungen automatisch in der Margen-Analyse berücksichtigt.',
        'tier_pricing' => 'Mengenrabatte im Shop (z. B. ab 3 Stück 5 % Rabatt, ab 5 Stück 10 % Rabatt). Steigert den durchschnittlichen Warenkorbwert (AOV) bei Geschenkartikeln.',
        'configurator_files_help' => 'Lade hier die grafischen Assets für den 2D- und 3D-Live-Konfigurator hoch oder wähle ein fertiges Vorlage-Paket.',
        'template_package_help' => 'Weist mit einem Klick das passende 2D-Overlay (PNG), den 3D-Bühnenhintergrund sowie die atmosphärischen Themen-Videos (z. B. Weihnachten, Valentinstag) zu.',
        'preview_overlay_help' => 'Freigestelltes 2D-Produktbild (.png mit Transparenz). Dient als Zeichenbrett im 2D-Konfigurator und als Grundlage für die Platzierung der Gravurfläche.',
        'three_d_model_help' => 'Interaktives 3D-Modell im .glb-Format. Beim Upload werden Polygone und Texturen auf Wunsch automatisch für schnelle Ladezeiten im Shop optimiert.',
        'three_d_bg_help' => 'Hochauflösender Bühnen-Hintergrund (.jpg/.png oder kurzes .mp4 Loop-Video) für die 3D-Ansicht sowie für realistische Glas-/Metall-Reflexionen (WebGL Environment).',
        'xtool_params_help' => 'Exakte physikalische Millimeter-Maße der Gravurfläche für den maßstabsgetreuen 1:1 SVG-Laser-Export an xTool Creative Space (Flachbett oder Rotary/Zylinder).',
        'xtool_plane_width_help' => 'Tatsächliche Breite des 100%-Gravurbereichs in Millimetern auf dem flachen Werkstück (X-Achse).',
        'xtool_plane_height_help' => 'Tatsächliche Höhe des 100%-Gravurbereichs in Millimetern auf dem flachen Werkstück (Y-Achse).',
        'xtool_rotary_diameter_help' => 'Durchmesser des Glases/Zylinders in Millimetern oben und unten am Beginn bzw. Ende der Gravurzone (für die Mantelflächen-Abwicklung im Rotary-Modus).',
        'xtool_safezone_help' => 'Nicht gravierbare Randbereiche (z. B. Trinkrand oben oder Rollen-Einspannung/ Glasboden unten) in Millimetern.',
        'configurator_workspace_help' => 'Richte hier das grüne Gravur-Zeichenbrett (2D) sowie die Position, Rotation und Projektion (Flach vs. Zylindrisch) auf dem 3D-Modell ein.',
        'working_area_help' => 'Steuert Position (X/Y), Größe (Breite/Höhe) und Form (Eckig, Rund, Polygon) des erlaubten Gravurbereichs auf dem 2D-Overlay.',
        'model_transform_help' => 'Passt Skalierung, Position und Grund-Rotation des 3D-Modells im Kamera-Sichtfeld an.',
        'overlay_transform_help' => 'Steuert, wie die Kunden-Gravur auf die 3D-Oberfläche projiziert wird (Größe, Versatz, Rotation und Material-Effekt wie Glas-Frosting oder Metall-Gravur).',
        'product_templates_help' => 'Fertige Design- und Anlass-Vorlagen (z. B. Muttertag, Weihnachten, Hochzeit), die Kunden im Konfigurator mit 1 Klick laden können und die als automatisches Saison-Hauptbild dienen.',
        'Lager' => 'Bestandsführung aktivieren.',
        'Technik' => 'Herstellungsverfahren (z.B. Lasergravur, 3D-Druck).',
    ];

    public int $step1SubStep = 1;
    public int $step3SubStep = 1;
    public int $step4SubStep = 1;

    // --- Konfigurator Settings (ERWEITERT UM CUSTOM POINTS) ---
    public $configSettings = [];

    // --- Zentrale Produkt-Suite Tabs ('collection', 'packaging', 'suppliers', 'templates') ---
    #[\Livewire\Attributes\Url(as: 'section', except: 'collection')]
    public string $activeSection = 'collection';

    public ?string $templatePreselectProductId = null;
    public ?string $templatePreselectId = null;
    public bool $templateStartCreate = false;
    public int $templateEmbedVersion = 1;

    // --- Inline Schnell-Anlage Lieferant in Schritt 1 ---
    public bool $showQuickSupplierForm = false;
    public string $quickSupplierName = '';
    public string $quickSupplierCountry = 'DE';
    public string $quickSupplierWebsite = '';
    public string $quickSupplierLeadDays = '5';

    // --- 1-Klick KI-Produkt-Generator Modal ---
    public bool $showQuickAiModal = false;
    public string $quickAiPreset = 'auto';
    public string $quickAiCollectionLine = 'auto'; // 'auto', 'manufaktur', 'atelier'
    public string $quickAiPersonalizationMode = 'custom_fields'; // 'custom_fields', 'studio', 'standard'
    public string $quickAiCustomFieldsPreset = 'auto'; // 'auto', 'single_name', 'name_date_symbol', 'family_names_4', 'front_back_engraving', 'pet_tag'
    public string $quickAiName = '';
    public string $quickAiSku = '';
    public string $quickAiPrice = '';
    public string $quickAiPurchasePrice = '';
    public string $quickAiReorderUrl = '';
    public ?string $quickAiSupplierId = null;
    public $quickAiQuantity = '';
    public $quickAiWeight = '';
    public $quickAiWidth = '';
    public $quickAiHeight = '';
    public $quickAiLength = '';
    public $quickAiLeadDaysMin = '';
    public $quickAiLeadDaysMax = '';

    protected function rules()
    {
        return [
            'name' => 'required|min:3',
            // SKU nur validieren, wenn sie nicht leer ist oder wir im Speicher-Modus sind
            'sku' => 'nullable|min:3',
            'price_input' => 'required|numeric|min:0',
            'purchase_price_input' => 'nullable|numeric|min:0',
            'slug_input' => 'nullable', // Slug wird automatisch generiert, muss hier nicht strict sein
            'type' => 'required|in:physical,digital,service',
        ];
    }

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(ConfiguratorService $configService) {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $savedLayout = shop_setting('admin_products_layout_mode', 'large');
        if (in_array($savedLayout, ['large', 'medium', 'list'], true)) {
            $this->layoutMode = $savedLayout;
        }

        // Wenn es ein neues Produkt ist oder ein bestehendes geladen wird:
        $this->configSettings = $configService->mergeWithDefaults($this->product?->configurator_settings ?? []);
    }

    public function setLayoutMode(string $mode): void
    {
        if (!in_array($mode, ['large', 'medium', 'list'], true)) {
            return;
        }

        $this->layoutMode = $mode;

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'admin_products_layout_mode'],
            ['value' => $mode]
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
    }

    public function setActiveSection(string $section): void
    {
        if (!in_array($section, ['collection', 'packaging', 'suppliers', 'templates', 'ideas'], true)) {
            return;
        }

        if ($this->viewMode === 'edit') {
            $this->viewMode = 'list';
        }

        if ($section !== 'templates') {
            $this->templatePreselectProductId = null;
            $this->templatePreselectId = null;
            $this->templateStartCreate = false;
        }

        $this->activeSection = $section;
    }

    public function goToStep1SubStep(int $subStep): void
    {
        $this->step1SubStep = max(1, min(5, $subStep));
    }

    public function getStep3AllowedSubSteps(): array
    {
        if ($this->type === 'physical') {
            return [1, 2, 3, 4, 5, 6];
        }
        if ($this->type === 'service') {
            return [1, 2, 3, 5];
        }
        return [1, 2, 3];
    }

    public function goToStep3SubStep(int $subStep): void
    {
        $allowed = $this->getStep3AllowedSubSteps();
        if (in_array($subStep, $allowed, true)) {
            $this->step3SubStep = $subStep;
        } else {
            $this->step3SubStep = $allowed[0] ?? 1;
        }
    }

    public function goToStep4SubStep(int $subStep): void
    {
        $this->step4SubStep = max(1, min(4, $subStep));
    }

    public function openProductInStep(string $productId, int $step, int $subStep = 1): void
    {
        $this->activeSection = 'collection';
        $this->edit($productId);
        $targetStep = max(1, min($step, $this->totalSteps));
        if ($this->product->completion_step < $targetStep) {
            $this->product->updateQuietly(['completion_step' => $targetStep]);
        }
        $this->currentStep = $targetStep;
        if ($targetStep === 1) {
            $this->step1SubStep = max(1, min(5, $subStep));
        } elseif ($targetStep === 3) {
            $this->goToStep3SubStep($subStep);
        } elseif ($targetStep === 4) {
            $this->goToStep4SubStep($subStep);
        }
    }

    public function updatedReorderUrl($value): void
    {
        $url = trim((string) $value);
        if ($url === '' || !preg_match('#^https?://([^/]+)#i', $url, $m)) {
            return;
        }

        $host = strtolower(preg_replace('/^www\./i', '', $m[1]));

        // Falls noch kein Lieferant gewählt ist: Prüfen, ob ein Lieferant zur Domain passt
        if (empty($this->supplier_id)) {
            foreach (\App\Models\Product\ProductSupplier::all() as $sup) {
                $supSite = strtolower((string) $sup->website);
                $supName = strtolower((string) $sup->name);
                if (($supSite !== '' && str_contains($supSite, $host))
                    || (str_contains($host, 'gluecksfieber') && str_contains($supName, 'glücksfieber'))
                    || (str_contains($host, 'silberketten-store') && str_contains($supName, 'silberketten-store'))
                    || (str_contains($host, 'printequipment') && str_contains($supName, 'print equipment'))
                    || (str_contains($host, 'westpack') && str_contains($supName, 'westpack'))
                    || (str_contains($host, 'karton.eu') && str_contains($supName, 'karton'))
                    || (str_contains($host, 'temu') && str_contains($supName, 'temu'))
                ) {
                    $this->supplier_id = $sup->id;
                    break;
                }
            }
        }

        // Vorbelegung für das Schnell-Lieferanten-Formular
        if ($this->quickSupplierWebsite === '') {
            $this->quickSupplierWebsite = 'https://' . $m[1];
        }
        if ($this->quickSupplierName === '') {
            $domainBase = explode('.', $host)[0] ?? $host;
            $this->quickSupplierName = ucfirst($domainBase);
        }
    }

    public function createQuickSupplier(): void
    {
        $name = trim($this->quickSupplierName);
        if ($name === '') {
            $this->addError('quickSupplierName', 'Bitte Lieferantennamen eingeben.');
            return;
        }

        $website = trim($this->quickSupplierWebsite);
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }

        $countryCode = strtoupper(substr(trim($this->quickSupplierCountry ?: 'DE'), 0, 2));
        $countryMap = [
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'CH' => 'Schweiz',
            'DK' => 'Dänemark',
            'CN' => 'China',
            'NL' => 'Niederlande',
            'PL' => 'Polen',
        ];

        $supplier = \App\Models\Product\ProductSupplier::create([
            'name' => $name,
            'company_name' => $name,
            'country_code' => $countryCode,
            'country' => $countryMap[$countryCode] ?? $countryCode,
            'website' => $website ?: null,
            'lead_time_land_days' => max(1, (int) $this->quickSupplierLeadDays),
            'shipping_method' => $countryCode === 'CN' ? 'air' : 'land',
        ]);

        unset($this->suppliers);
        $this->supplier_id = $supplier->id;
        if (isset($this->product) && $this->product->exists) {
            $this->product->update(['product_supplier_id' => $supplier->id]);
        }

        $this->showQuickSupplierForm = false;
        $this->quickSupplierName = '';
        $this->quickSupplierWebsite = '';
        session()->flash('success', "Lieferant „{$supplier->name}“ angelegt und verknüpft.");
    }

    public function assignCatalogPackaging(int $catalogItemId, string $mode = 'replace'): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $item = \App\Models\Product\ProductPackagingItem::findOrFail($catalogItemId);

        if ($mode === 'replace') {
            $this->product->packagings()->delete();
        }

        $primaryGrams = max(1, (int) ($item->primary_weight_grams ?: $item->weight_grams ?: 10));
        $this->product->packagings()->create([
            'packaging_item_id' => $item->id,
            'label'             => $item->name,
            'material_type'     => $item->primary_material_type ?: 'paper',
            'weight_grams'      => $primaryGrams,
        ]);

        if (!empty($item->secondary_material_type) && (int) $item->secondary_weight_grams > 0) {
            $this->product->packagings()->create([
                'packaging_item_id' => $item->id,
                'label'             => $item->name . ' (Einlage/Polster)',
                'material_type'     => $item->secondary_material_type,
                'weight_grams'      => (int) $item->secondary_weight_grams,
            ]);
        }

        $this->recalculateProductPackagingTotals($item);
        session()->flash('success', "Verpackung „{$item->name}“ inkl. LUCID-Aufteilung zugewiesen.");
    }

    public function removeProductPackagingEntry(int $packagingId): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $this->product->packagings()->where('id', $packagingId)->delete();
        $this->recalculateProductPackagingTotals();
    }

    protected function recalculateProductPackagingTotals(?\App\Models\Product\ProductPackagingItem $latestItem = null): void
    {
        $this->product->load('packagings.packagingItem');
        $totalGrams = (int) $this->product->packagings->sum('weight_grams');
        $uniqueCatalogItems = $this->product->packagings
            ->pluck('packagingItem')
            ->filter()
            ->unique('id');

        $totalCostCents = (int) $uniqueCatalogItems->sum('unit_cost_cents');
        if ($totalCostCents === 0 && $latestItem) {
            $totalCostCents = (int) $latestItem->unit_cost_cents;
        }

        $this->packaging_weight = $totalGrams;
        if ($totalCostCents > 0) {
            $this->packaging_cost_input = number_format($totalCostCents / 100, 2, '.', '');
        }
        if ($latestItem && $latestItem->height_mm > 0 && $latestItem->height_mm <= 20 && empty($this->shipping_class)) {
            $this->shipping_class = 'brief';
        }

        $this->product->update([
            'packaging_weight' => $totalGrams,
            'packaging_cost'   => $totalCostCents > 0 ? $totalCostCents : $this->product->packaging_cost,
            'shipping_class'   => $this->shipping_class ?: $this->product->shipping_class,
        ]);
    }

    public function toggleProductTemplateActive(string $templateId): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $tpl = \App\Models\Product\ProductTemplate::where('product_id', $this->product->id)
            ->where('id', $templateId)
            ->first();

        if ($tpl) {
            $tpl->update(['is_active' => !$tpl->is_active]);
            $this->product->load('templates');
        }
    }

    public function openTemplateEditorForProduct(?string $templateId = null, bool $startCreate = false): void
    {
        if (isset($this->product) && $this->product->exists) {
            $this->save(false);
            $this->templatePreselectProductId = $this->product->id;
        }
        $this->templatePreselectId = $templateId;
        $this->templateStartCreate = $startCreate;
        $this->templateEmbedVersion++;
        $this->viewMode = 'list';
        $this->activeSection = 'templates';
    }

    #[On('product-updated')]
    public function refreshProduct()
    {
        // Lädt das Model neu aus der DB und hält Attribute, Varianten & Addons synchron
        $this->product->refresh();
        $this->productAttributes = array_merge($this->productAttributes, $this->product->attributes ?? []);
        $freshSettings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        foreach (['excluded_variant_attributes', 'included_variant_attributes', 'addons', 'digital_documents'] as $syncKey) {
            if (array_key_exists($syncKey, $freshSettings)) {
                $this->configSettings[$syncKey] = $freshSettings[$syncKey];
            }
        }
    }

    // --- LIVE UPDATES ---

    public function updatedType($value)
    {
        // 1. Validierung nur für dieses Feld, um Crashes zu vermeiden
        $this->validateOnly('type');

        // 2. Steps anpassen
        $this->totalSteps = ($value === 'physical') ? 4 : 3;

        // 3. Current Step korrigieren, falls man von Step 4 (Physical) zurückwechselt
        if ($this->currentStep > $this->totalSteps) {
            $this->currentStep = $this->totalSteps;
        }

        // 4. Reset von physischen Daten, falls nötig (Optional, aber sauber)
        if ($value !== 'physical') {
            $this->weight = 0;
            $this->packaging_weight = 0;
            $this->height = 0;
            $this->width = 0;
            $this->length = 0;
            $this->shipping_class = ''; // Reset auch hier
        }

        // Standardwerte für Attribute je nach Typ setzen (optional)
        if ($value === 'digital') {
            $this->collection_line = 'voucher';
            $this->productAttributes['Auslieferung'] = 'Sofort-Download';
            $this->track_quantity = false; // Bei Digital oft irrelevant
        } elseif ($value === 'service') {
            $this->collection_line = 'service';
            $this->productAttributes['Ort'] = 'Online';
            $this->track_quantity = true; // Bei Service (Termine) oft relevant
        } else {
            if (!in_array($this->collection_line, ['manufaktur', 'atelier'], true)) {
                $this->collection_line = 'manufaktur';
            }
            $this->productAttributes['Lieferzeit'] = '3-5 Werktage';
            $this->track_quantity = true;
        }
    }

    public function updatedName($value)
    {
        if (empty($this->slug_input)) {
            $this->slug_input = Str::slug($value);
        }
    }

    public function updatedSlugInput($value)
    {
        $this->slug_input = Str::slug($value);
    }

    // --- ACTIONS ---

    public function createDraft()
    {
        $defaultConfig = app(ConfiguratorService::class)->getDefaultSettings();
        $defaultAttributes = [
            'Material' => '',
            'Druck' => '',
            'Technik' => 'Präzisions-Lasergravur',
            'Größe' => '',
            'Gewicht' => '',
            'Verpackung' => '',
            'Lieferzeit' => '2-4 Werktage',
            'Farbe' => '',
            'Format' => '',
            'Seiten' => '',
            'Sprache' => 'Deutsch',
            'Auslieferung' => 'Sofort-Download',
            'Dauer' => '',
            'Ort' => 'Online',
            'Experte' => '',
        ];

        $this->configSettings = $defaultConfig;
        $this->productAttributes = $defaultAttributes;

        $draft = Product::create([
            'name' => 'Neues Produkt ' . date('H:i'),
            'slug' => 'draft-' . Str::uuid(),
            'sku' => $this->generateNextCleanSku('MSF-UNIKAT'),
            'brand' => 'Mein-Seelenfunke',
            'status' => 'draft',
            'type' => 'physical', // Standard
            'collection_line' => 'manufaktur',
            'is_personalizable' => true,
            'three_d_active' => true,
            'track_quantity' => true,
            'quantity' => 0,
            'price' => 0,
            'purchase_price' => 0,
            'packaging_cost' => 14,
            'packaging_weight' => 15,
            'shipping_class' => 'brief',
            'laser_runtime_minutes' => 1,
            'electricity_wear_factor' => 5,
            'marketing_cost_percent' => 15.00,
            'media_gallery' => [],
            'tier_pricing' => [],
            'attributes' => $defaultAttributes,
            'configurator_settings' => $defaultConfig,
            'completion_step' => 1
        ]);

        $settings = $draft->configurator_settings ?? [];
        $settings['addons'] = $draft->getEffectiveAddons(false);
        $draft->updateQuietly(['configurator_settings' => $settings]);

        $this->syncDefaultProductPackaging($draft);

        $this->edit($draft->id);
    }

    public function syncDefaultProductPackaging(Product $product): void
    {
        if (!$product->isPhysical()) {
            $product->packagings()->delete();
            return;
        }

        $slug = mb_strtolower((string) $product->slug);
        $name = mb_strtolower((string) $product->name);

        $isBrief = ($product->shipping_class === 'brief')
            || ((int) $product->weight > 0 && (int) $product->weight <= 100 && !str_contains($slug, 'kristall') && !str_contains($slug, 'glas'));

        $pkgWeight = max(15, (int) ($product->packaging_weight ?: ($isBrief ? 15 : 150)));

        $catalogSku = 'PKG-ENV-A1';
        if (str_contains($slug, 'weizenglas') || str_contains($name, 'glas')) {
            $catalogSku = 'PKG-BOX-GLASS';
        } elseif (!$isBrief || str_contains($slug, 'kristall') || str_contains($slug, 'schiefer') || str_contains($name, 'kristall') || str_contains($name, 'schiefer')) {
            $catalogSku = 'PKG-BOX-CRYSTAL';
        }

        $catalogItem = \App\Models\Product\ProductPackagingItem::where('sku', $catalogSku)->first();
        $itemId = $catalogItem?->id;
        $itemLabel = $catalogItem?->name;

        $product->packagings()->delete();
        $product->packagings()->createMany(
            $isBrief
                ? [
                    [
                        'packaging_item_id' => $itemId,
                        'label' => $itemLabel ?? 'Luftpolsterversandtasche DIN A6 / C6 (Gr. A/1)',
                        'material_type' => 'composite',
                        'weight_grams' => max(1, $pkgWeight - 3),
                    ],
                    [
                        'packaging_item_id' => $itemId,
                        'label' => $itemLabel ?? 'Luftpolsterversandtasche DIN A6 / C6 (Gr. A/1)',
                        'material_type' => 'paper',
                        'weight_grams' => 3,
                    ],
                ]
                : [
                    [
                        'packaging_item_id' => $itemId,
                        'label' => $itemLabel ?? 'Versandkarton mit Schutzpolster',
                        'material_type' => 'paper',
                        'weight_grams' => max(10, $pkgWeight - 20),
                    ],
                    [
                        'packaging_item_id' => $itemId,
                        'label' => $itemLabel ?? 'Versandkarton mit Schutzpolster',
                        'material_type' => 'plastic',
                        'weight_grams' => 20,
                    ],
                ]
        );
    }

    public function edit($id)
    {
        $this->product = Product::with(['templates', 'supplier', 'packagings.packagingItem', 'categories'])->findOrFail($id);

        // Basisdaten
        $this->name = $this->product->name;
        $this->type = $this->product->type; // NEU: Typ laden
        $this->collection_line = $this->product->collection_line
            ?: ($this->type === 'digital' ? 'voucher' : ($this->type === 'service' ? 'service' : 'manufaktur'));
        $this->is_personalizable = (bool) $this->product->is_personalizable;
        $this->three_d_active = (bool) ($this->product->three_d_active ?? true);
        $this->description = $this->product->description;
        $this->short_description = $this->product->short_description;
        $this->status = $this->product->status;

        // Steps Logik basierend auf geladenem Typ initialisieren
        $this->totalSteps = ($this->type === 'physical') ? 4 : 3;

        // SEO & Slug
        $this->seo_title = $this->product->seo_title;
        $this->seo_description = $this->product->seo_description;
        $this->slug_input = str_starts_with($this->product->slug, 'draft-') ? '' : $this->product->slug;

        // Identifikatoren
        $this->sku = $this->product->sku;
        $this->barcode = $this->product->barcode;
        $this->brand = $this->product->brand;
        $this->supplier_id = $this->product->product_supplier_id;
        $this->reorder_url = $this->product->reorder_url;

        // Versanddaten laden
        $this->weight = $this->product->weight;
        $this->packaging_weight = $this->product->packaging_weight;
        $this->height = $this->product->height;
        $this->width = $this->product->width;
        $this->length = $this->product->length;
        $this->shipping_class = $this->product->shipping_class; // Laden der Shipping Class

        // Kalkulation & Produktion laden
        $this->laser_runtime_minutes = $this->product->laser_runtime_minutes;
        $this->electricity_wear_factor = $this->product->electricity_wear_factor ?? 1;
        $this->marketing_cost_percent = $this->product->marketing_cost_percent ?? 15.00;
        $this->packaging_cost_input = $this->product->packaging_cost > 0
            ? number_format($this->product->packaging_cost / 100, 2, '.', '')
            : '';

        // Lager
        $this->track_quantity = (bool) $this->product->track_quantity;
        $this->quantity = $this->product->quantity;
        $this->continue_selling = (bool) $this->product->continue_selling_when_out_of_stock;
        $rawSavedSettings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        $this->out_of_stock_lead_days_min = (isset($rawSavedSettings['out_of_stock_lead_days_min']) && $rawSavedSettings['out_of_stock_lead_days_min'] !== '' && $rawSavedSettings['out_of_stock_lead_days_min'] !== null)
            ? (string) (int) $rawSavedSettings['out_of_stock_lead_days_min']
            : '';
        $this->out_of_stock_lead_days_max = (isset($rawSavedSettings['out_of_stock_lead_days_max']) && $rawSavedSettings['out_of_stock_lead_days_max'] !== '' && $rawSavedSettings['out_of_stock_lead_days_max'] !== null)
            ? (string) (int) $rawSavedSettings['out_of_stock_lead_days_max']
            : '';

        // Preise
        $this->price_input = $this->product->price > 0
            ? number_format($this->product->price / 100, 2, '.', '')
            : '';

        $this->compare_price_input = $this->product->compare_at_price
            ? number_format($this->product->compare_at_price / 100, 2, '.', '')
            : '';

        $this->purchase_price_input = $this->product->purchase_price > 0
            ? number_format($this->product->purchase_price / 100, 2, '.', '')
            : '';

        // Staffelpreise laden
        $this->tiers = $this->product->tierPrices->map(function($tier) {
            return [
                'id' => Str::uuid()->toString(),
                'qty' => $tier->qty,
                'percent' => $tier->percent
            ];
        })->toArray();

        // Arrays sauber aus Default-Basis + Produktdaten mergen (kein Leak zwischen verschiedenen Produkten)
        $defaultAttributes = [
            'Material' => '',
            'Druck' => '',
            'Technik' => '',
            'Größe' => '',
            'Gewicht' => '',
            'Verpackung' => '',
            'Lieferzeit' => '3-5 Werktage',
            'Farbe' => '',
            'Format' => '',
            'Seiten' => '',
            'Sprache' => 'Deutsch',
            'Auslieferung' => 'Sofort-Download',
            'Dauer' => '',
            'Ort' => 'Online',
            'Experte' => '',
        ];
        $this->productAttributes = array_merge($defaultAttributes, $this->product->attributes ?? []);

        $defaultConfig = app(ConfiguratorService::class)->getDefaultSettings();
        $savedConfig = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];

        // Sicherstellen, dass custom_points immer existiert (Fallout-Schutz)
        if(!isset($savedConfig['custom_points'])) {
            $savedConfig['custom_points'] = $defaultConfig['custom_points'] ?? [];
        }

        $this->configSettings = array_merge($defaultConfig, $savedConfig);
        if ($this->product->isPhysical()) {
            $this->configSettings['addons'] = $this->product->getEffectiveAddons(false);
        } else {
            unset($this->configSettings['addons']);
        }
        $this->configSettings['auto_seasonal_main_image'] = array_key_exists('auto_seasonal_main_image', $savedConfig)
            ? filter_var($savedConfig['auto_seasonal_main_image'], FILTER_VALIDATE_BOOLEAN)
            : ($this->is_personalizable && $this->type === 'physical');

        // Automatische Erkennung des Vorlage-Pakets für bestehende Standard-Produkte
        $packages = app(ConfiguratorService::class)->getTemplatePackages();
        if (($this->configSettings['template_package'] ?? 'custom') === 'custom' && isset($packages[$this->product->slug])) {
            $this->configSettings['template_package'] = $this->product->slug;
        }

        // Personalisierungs-Modus & dynamische Eingabefelder initialisieren
        $pMode = strtolower(trim((string) ($this->configSettings['personalization_mode'] ?? 'studio')));
        $this->configSettings['personalization_mode'] = in_array($pMode, ['studio', 'custom_fields'], true) ? $pMode : 'studio';
        $this->configSettings['custom_fields'] = $this->product->getCustomFieldsDefinition();
        $this->configSettings['digital_documents'] = $this->product->getDigitalDocuments();
        $this->new_digital_documents = [];
        $this->new_digital_document_title = '';
        $this->digitalDocumentsSavedText = null;

        $this->viewMode = 'edit';

        // Step Berechnung korrigieren (Falls man von Physical auf Digital gewechselt hat und Step auf 4 stand)
        $dbStep = ($this->product->completion_step > 0) ? $this->product->completion_step : 1;
        $this->currentStep = min($dbStep, $this->totalSteps);
        $this->step1SubStep = 1;
    }

    public function updatedNewDigitalFile()
    {
        // Validierung: Erlaube gängige Formate, max 100MB (je nach Server-Config)
        $this->validate([
            'new_media.*' => 'image|max:10240', // Existierende Regel
            'new_digital_file' => 'required|file|mimes:pdf,zip,rar,mp3,wav,mp4,mov,epub,mobi|max:102400', // NEU: 100MB
        ]);

        // WICHTIG: Speichern im 'local' Disk (nicht public!), damit es geschützt ist
        // Ordnerstruktur: products-secure/{slug}/
        $folder = 'produkte/products-secure/' . ($this->product->slug ?? 'draft');

        // Original-Dateiname speichern (bereinigt gegen Path Traversal & gefährliche Erweiterungen)
        $rawOriginalName = basename((string) $this->new_digital_file->getClientOriginalName());
        $ext = strtolower(pathinfo($rawOriginalName, PATHINFO_EXTENSION));
        $allowedDigitalExts = ['pdf', 'zip', 'rar', 'mp3', 'wav', 'mp4', 'mov', 'epub', 'mobi'];
        $safeExt = in_array($ext, $allowedDigitalExts, true) ? $ext : 'bin';
        $safeBase = Str::slug(pathinfo($rawOriginalName, PATHINFO_FILENAME)) ?: 'digital_file';
        $originalName = $safeBase . '.' . $safeExt;

        // Datei speichern (privat)
        $path = $this->new_digital_file->storeAs($folder, time() . '_' . $originalName, 'local'); // Default disk ist meist 'local' (storage/app)

        // Datenbank Update
        $this->product->update([
            'digital_download_path' => $path,
            'digital_filename' => $originalName
        ]);

        $this->new_digital_file = null;
        session()->flash('success', 'Produktdatei sicher hochgeladen!');
    }

    public function removeDigitalFile()
    {
        if ($this->product->digital_download_path) {
            $downloadPath = ltrim(str_replace('\\', '/', (string) $this->product->digital_download_path), '/');
            if (
                $downloadPath !== ''
                && !str_contains($downloadPath, '..')
                && !str_contains($downloadPath, "\0")
                && (str_starts_with($downloadPath, 'produkte/products-secure/') || str_starts_with($downloadPath, 'products-secure/'))
            ) {
                Storage::disk('local')->delete($downloadPath);
            }

            // DB bereinigen
            $this->product->update([
                'digital_download_path' => null,
                'digital_filename' => null
            ]);

            session()->flash('success', 'Produktdatei entfernt.');
        }
    }

    public function setDigitalDocumentPresetTitle(string $title): void
    {
        $this->new_digital_document_title = mb_substr(trim($title), 0, 120);
    }

    public function updatedNewDigitalDocuments()
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        if (!is_array($this->new_digital_documents)) {
            $this->new_digital_documents = $this->new_digital_documents ? [$this->new_digital_documents] : [];
        }

        if (empty($this->new_digital_documents)) {
            return;
        }

        $this->validate([
            'new_digital_documents.*' => 'required|file|mimes:pdf,zip,rar,jpg,jpeg,png,webp|max:51200',
        ]);

        $folder = 'produkte/products-secure/' . ($this->product->slug ?: 'draft') . '/documents';
        $allowedExts = ['pdf', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'webp'];

        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        $existingDocs = $this->product->getDigitalDocuments();
        $customTitle = mb_substr(trim($this->new_digital_document_title), 0, 120);
        $totalNew = count($this->new_digital_documents);

        foreach (array_values($this->new_digital_documents) as $idx => $file) {
            $rawOriginalName = basename((string) $file->getClientOriginalName());
            $ext = strtolower(pathinfo($rawOriginalName, PATHINFO_EXTENSION));
            $safeExt = in_array($ext, $allowedExts, true) ? $ext : 'pdf';
            $rawBase = pathinfo($rawOriginalName, PATHINFO_FILENAME);
            $safeBase = Str::slug($rawBase) ?: 'dokument';
            $cleanFilename = $safeBase . '.' . $safeExt;
            $storedFilename = time() . '_' . Str::lower(Str::random(4)) . '_' . $cleanFilename;

            $storedPath = $file->storeAs($folder, $storedFilename, 'local');

            if ($customTitle !== '') {
                $docTitle = $totalNew > 1 ? ($customTitle . ' (' . ($idx + 1) . ')') : $customTitle;
            } else {
                $humanBase = trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $rawBase)));
                $docTitle = $humanBase !== '' ? $humanBase : 'Begleitdokument';
            }

            $existingDocs[] = [
                'id' => 'doc_' . Str::lower(Str::random(10)),
                'title' => $docTitle,
                'path' => str_replace('\\', '/', $storedPath),
                'filename' => $cleanFilename,
                'extension' => $safeExt,
                'size_bytes' => max(0, (int) $file->getSize()),
                'uploaded_at' => now()->format('d.m.Y H:i'),
            ];
        }

        $settings['digital_documents'] = array_values($existingDocs);
        $this->configSettings['digital_documents'] = $settings['digital_documents'];
        $this->product->configurator_settings = $settings;
        $this->product->save();
        $this->product->refresh();

        $this->new_digital_documents = [];
        $this->new_digital_document_title = '';
        $this->digitalDocumentsSavedText = 'Gespeichert';
        session()->flash('success', 'Digitale Dokumente sicher hinterlegt!');
    }

    public function updateDigitalDocumentTitle(string $docId, string $newTitle): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $cleanTitle = mb_substr(trim($newTitle), 0, 120);
        if ($cleanTitle === '') {
            return;
        }

        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        $docs = $this->product->getDigitalDocuments();
        $updated = false;

        foreach ($docs as $idx => $doc) {
            if (($doc['id'] ?? '') === $docId) {
                $docs[$idx]['title'] = $cleanTitle;
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $settings['digital_documents'] = array_values($docs);
            $this->configSettings['digital_documents'] = $settings['digital_documents'];
            $this->product->configurator_settings = $settings;
            $this->product->save();
            $this->product->refresh();
            $this->digitalDocumentsSavedText = 'Gespeichert';
        }
    }

    public function removeDigitalDocument(string $docId): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        $docs = $this->product->getDigitalDocuments();
        $remaining = [];

        foreach ($docs as $doc) {
            if (($doc['id'] ?? '') === $docId) {
                $downloadPath = ltrim(str_replace('\\', '/', (string) ($doc['path'] ?? '')), '/');
                if (
                    $downloadPath !== ''
                    && !str_contains($downloadPath, '..')
                    && !str_contains($downloadPath, "\0")
                    && (str_starts_with($downloadPath, 'produkte/products-secure/') || str_starts_with($downloadPath, 'products-secure/'))
                ) {
                    Storage::disk('local')->delete($downloadPath);
                }
                continue;
            }
            $remaining[] = $doc;
        }

        $settings['digital_documents'] = array_values($remaining);
        $this->configSettings['digital_documents'] = $settings['digital_documents'];
        $this->product->configurator_settings = $settings;
        $this->product->save();
        $this->product->refresh();
        $this->digitalDocumentsSavedText = 'Gespeichert';

        session()->flash('success', 'Digitales Dokument entfernt.');
    }

    public function downloadDigitalDocumentAdmin(string $docId)
    {
        if (!isset($this->product) || !$this->product->exists) {
            return null;
        }

        foreach ($this->product->getDigitalDocuments() as $doc) {
            if (($doc['id'] ?? '') === $docId) {
                $filePath = ltrim(str_replace('\\', '/', (string) ($doc['path'] ?? '')), '/');
                if (
                    $filePath !== ''
                    && !str_contains($filePath, '..')
                    && !str_contains($filePath, "\0")
                    && (str_starts_with($filePath, 'produkte/products-secure/') || str_starts_with($filePath, 'products-secure/'))
                    && Storage::disk('local')->exists($filePath)
                ) {
                    $ext = strtolower((string) ($doc['extension'] ?? pathinfo($filePath, PATHINFO_EXTENSION) ?: 'pdf'));
                    $downloadName = (Str::slug((string) ($doc['title'] ?? 'dokument')) ?: 'dokument') . '.' . $ext;
                    return Storage::disk('local')->download($filePath, $downloadName, [
                        'X-Content-Type-Options' => 'nosniff',
                    ]);
                }
            }
        }

        session()->flash('error', 'Die Dokumentdatei konnte auf dem Server nicht gefunden werden.');
        return null;
    }

    public function updateStatus($id, $newStatus)
    {
        if (!in_array($newStatus, ['draft', 'active', 'archived'])) {
            return;
        }

        $prod = Product::find($id);
        if($prod) {
            $prod->status = $newStatus;
            $prod->save();
            \App\Models\Marketing\MarketingLandingPage::syncForProduct($prod);
        }
    }

    /**
     * 1-Klick Sortiment-Switch: Schaltet ein Produkt per Knopfdruck zwischen
     * "Im Sortiment (active)" und "Pausiert (draft)" um.
     */
    public function toggleAssortment(string $id): void
    {
        $prod = Product::find($id);
        if (!$prod) {
            return;
        }

        $nextStatus = $prod->status === 'active' ? 'draft' : 'active';
        $prod->status = $nextStatus;
        if ($nextStatus === 'active' && (int) $prod->completion_step < 4) {
            $prod->completion_step = $prod->type === 'physical' ? 4 : 3;
        }
        $prod->save();

        \App\Models\Marketing\MarketingLandingPage::syncForProduct($prod);

        if ($nextStatus === 'active') {
            session()->flash('success', "„{$prod->name}“ ist jetzt live im Sortiment!");
        } else {
            session()->flash('success', "„{$prod->name}“ wurde im Sortiment pausiert.");
        }
    }

    /**
     * 1-Klick Herkunfts-Siegel Switch: Schaltet direkt in der Kachelansicht ("Deine Kollektion")
     * rechtskonform zwischen "Handveredelt in Deutschland" und "Kein Siegel (Aus)" um (§ 127 MarkenG / § 5 UWG).
     */
    public function toggleOriginBadge(string $id): void
    {
        $prod = Product::find($id);
        if (!$prod) {
            return;
        }

        $current = $prod->getOriginBadgeMode();
        $next = ($current === 'handcrafted_in_germany') ? 'none' : 'handcrafted_in_germany';

        $settings = is_array($prod->configurator_settings) ? $prod->configurator_settings : [];
        $settings['origin_badge'] = $next;
        $prod->configurator_settings = $settings;

        $attrs = is_array($prod->attributes) ? $prod->attributes : [];
        if ($next === 'handcrafted_in_germany') {
            $city = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
            $attrs['Herkunft'] = "Handveredelt in Deutschland (Laser-Manufaktur {$city})";
        } else {
            unset($attrs['Herkunft']);
        }
        $prod->attributes = $attrs;
        $prod->save();

        $label = ($next === 'handcrafted_in_germany')
            ? '🇩🇪 Handveredelt in Deutschland'
            : 'Ausgeblendet';

        session()->flash('success', "Herkunfts-Siegel für „{$prod->name}“ auf {$label} gesetzt.");
    }

    public function openQuickAiModal(): void
    {
        $this->resetValidation();
        $this->showQuickAiModal = true;
        $this->quickAiCollectionLine = 'auto';
        $this->quickAiPersonalizationMode = 'custom_fields';
        $this->quickAiCustomFieldsPreset = 'auto';
        $this->applyQuickAiPreset('auto');
    }

    public function closeQuickAiModal(): void
    {
        $this->showQuickAiModal = false;
    }

    public function setQuickAiCollectionLine(string $line): void
    {
        if (!in_array($line, ['auto', 'manufaktur', 'atelier'], true)) {
            $line = 'auto';
        }
        $this->quickAiCollectionLine = $line;
    }

    public function setQuickAiPersonalizationMode(string $mode): void
    {
        if (!in_array($mode, ['custom_fields', 'studio', 'standard'], true)) {
            $mode = 'custom_fields';
        }
        $this->quickAiPersonalizationMode = $mode;
    }

    public function setQuickAiCustomFieldsPreset(string $preset): void
    {
        if (!in_array($preset, ['auto', 'single_name', 'name_and_font', 'name_date_symbol', 'family_names_4', 'front_back_engraving', 'pet_tag'], true)) {
            $preset = 'auto';
        }
        $this->quickAiCustomFieldsPreset = $preset;
    }

    /**
     * Erzeugt die dynamischen Schnell-Personalisierungsfelder (inkl. !-Tooltip) für den 1-Klick KI-Generator.
     */
    public function buildQuickAiCustomFields(string $detectedArchetype = 'seelen-talisman', string $fieldPreset = 'auto'): array
    {
        $resolvedPreset = $fieldPreset;
        if ($resolvedPreset === 'auto') {
            $resolvedPreset = match ($detectedArchetype) {
                'seelen-pfote-hundemarke' => 'pet_tag',
                'goldenes-seelen-herz', 'seelen-anhaenger' => 'name_date_symbol',
                'bambus-pen', 'heavytool-opener' => 'single_name',
                default => 'front_back_engraving',
            };
        }

        $allConfiguratorFonts = array_keys(app(\App\Services\ConfiguratorService::class)->getFonts());
        $allFontsRaw = implode(', ', $allConfiguratorFonts);

        $rawFields = match ($resolvedPreset) {
            'single_name' => [
                [
                    'id' => 'cf_wunschname',
                    'label' => 'Wunschname / Gravurtext',
                    'type' => 'text',
                    'placeholder' => 'z.B. Alina',
                    'options_raw' => '',
                    'required' => true,
                    'max_length' => 20,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Bitte gib hier den gewünschten Namen oder das Wort ein. Wir passen die Schriftgröße für ein harmonisches Gravurbild optimal an.',
                ],
            ],
            'name_and_font' => [
                [
                    'id' => 'cf_wunschname',
                    'label' => '1. Wunschname / Gravurtext',
                    'type' => 'text',
                    'placeholder' => 'z.B. Emilia',
                    'options_raw' => '',
                    'required' => true,
                    'max_length' => 22,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Gib hier deinen gewünschten Namen oder Begriff ein. Wir richten die Gravur millimetergenau mittig auf dem Schmuckstück aus.',
                ],
                [
                    'id' => 'cf_schriftart',
                    'label' => '2. Schriftart wählen',
                    'type' => 'font',
                    'placeholder' => '',
                    'options' => $allConfiguratorFonts,
                    'options_raw' => $allFontsRaw,
                    'required' => false,
                    'max_length' => 60,
                    'surcharge_price' => '0.00',
                    'info_text' => '',
                ],
            ],
            'name_date_symbol' => [
                [
                    'id' => 'cf_wunschname',
                    'label' => 'Wunschname',
                    'type' => 'text',
                    'placeholder' => 'z.B. Mia',
                    'options_raw' => '',
                    'required' => true,
                    'max_length' => 18,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Der Name wird zentriert und abriebfest per Präzisionslaser auf der Vorderseite verewigt.',
                ],
                [
                    'id' => 'cf_wunschdatum',
                    'label' => 'Wunschdatum (Optional)',
                    'type' => 'text',
                    'placeholder' => 'z.B. 14.08.2026',
                    'options_raw' => '',
                    'required' => false,
                    'max_length' => 12,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Optionales Datum unterhalb des Namens. Leer lassen, wenn kein Datum gewünscht ist.',
                ],
                [
                    'id' => 'cf_symbol',
                    'label' => 'Kleines Symbol',
                    'type' => 'select',
                    'placeholder' => '',
                    'options_raw' => 'Kein Symbol, Herz ♥, Unendlichkeit ∞, Stern ★, Pfote 🐾, Schutzengel 👼',
                    'required' => true,
                    'max_length' => 30,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Wähle ein feines Zusatz-Symbol, das wir dezent neben oder unter deine Gravur setzen.',
                ],
            ],
            'family_names_4' => [
                [
                    'id' => 'cf_name_1',
                    'label' => '1. Name (Pflichtfeld)',
                    'type' => 'text',
                    'placeholder' => '1. Name (z.B. Mama)',
                    'options_raw' => '',
                    'required' => true,
                    'max_length' => 15,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Erster Name für dein Schmuckstück.',
                ],
                [
                    'id' => 'cf_name_2',
                    'label' => '2. Name (Optional)',
                    'type' => 'text',
                    'placeholder' => '2. Name (leer lassen falls nicht gewünscht)',
                    'options_raw' => '',
                    'required' => false,
                    'max_length' => 15,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Nur ausfüllen, wenn ein zweiter Name graviert werden soll.',
                ],
                [
                    'id' => 'cf_name_3',
                    'label' => '3. Name (Optional)',
                    'type' => 'text',
                    'placeholder' => '3. Name (optional)',
                    'options_raw' => '',
                    'required' => false,
                    'max_length' => 15,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Nur ausfüllen, wenn ein dritter Name graviert werden soll.',
                ],
                [
                    'id' => 'cf_name_4',
                    'label' => '4. Name (Optional)',
                    'type' => 'text',
                    'placeholder' => '4. Name (optional)',
                    'options_raw' => '',
                    'required' => false,
                    'max_length' => 15,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Nur ausfüllen, wenn ein vierter Name graviert werden soll.',
                ],
            ],
            'pet_tag' => [
                [
                    'id' => 'cf_tiername',
                    'label' => 'Name des Vierbeiners (Vorderseite)',
                    'type' => 'text',
                    'placeholder' => 'z.B. Balu',
                    'options_raw' => '',
                    'required' => true,
                    'max_length' => 16,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Wird groß und gut lesbar auf der Vorderseite der Marke eingraviert.',
                ],
                [
                    'id' => 'cf_notfallnummer',
                    'label' => 'Telefonnummer / Notfall-Info (Rückseite)',
                    'type' => 'text',
                    'placeholder' => 'z.B. +49 170 1234567',
                    'options_raw' => '',
                    'required' => false,
                    'max_length' => 28,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Optional: Damit dein Liebling im Fall der Fälle sofort zu dir zurückfindet, gravieren wir deine Nummer auf die Rückseite.',
                ],
            ],
            default => [
                [
                    'id' => 'cf_vorderseite',
                    'label' => 'Gravurtext Vorderseite',
                    'type' => 'text',
                    'placeholder' => 'z.B. Für immer vereint',
                    'options_raw' => '',
                    'required' => true,
                    'max_length' => 25,
                    'surcharge_price' => '0.00',
                    'info_text' => 'Deine Wunschgravur auf der Vorderseite ist bereits im Grundpreis enthalten.',
                ],
                [
                    'id' => 'cf_rueckseite',
                    'label' => 'Optionale Rückseiten-Gravur (+5,00 €)',
                    'type' => 'text',
                    'placeholder' => 'Leer lassen = keine Rückseiten-Gravur',
                    'options_raw' => '',
                    'required' => false,
                    'max_length' => 25,
                    'surcharge_price' => '5.00',
                    'info_text' => 'Nur ausfüllen, wenn zusätzlich auch die Rückseite graviert werden soll (+5,00 € Aufpreis).',
                ],
            ],
        };

        return $this->normalizeCustomFieldsForStorage($rawFields);
    }

    protected function normalizeCustomFieldsForStorage(array $rawFields): array
    {
        $normalized = [];
        foreach (array_values($rawFields) as $cfIdx => $cfItem) {
            if (!is_array($cfItem)) {
                continue;
            }
            $normCf = Product::normalizeCustomFieldItem($cfItem, $cfIdx);
            if ($normCf !== null) {
                $normalized[] = $normCf;
            }
        }
        return $normalized;
    }

    public function applyQuickAiPreset(string $presetKey): void
    {
        $this->resetValidation();
        $this->quickAiPreset = $presetKey;

        if ($presetKey === 'auto') {
            $this->quickAiName = '';
            $this->quickAiSku = '';
            $this->quickAiPrice = '';
            $this->quickAiPurchasePrice = '';
            $this->quickAiSupplierId = null;
            $this->quickAiQuantity = '';
            $this->quickAiWeight = '';
            $this->quickAiWidth = '';
            $this->quickAiHeight = '';
            $this->quickAiLength = '';
            $this->quickAiLeadDaysMin = '';
            $this->quickAiLeadDaysMax = '';
            return;
        }

        if ($presetKey === 'heavytool-opener') {
            $sup = \App\Models\Product\ProductSupplier::where('name', 'like', '%Heavytool%')->first();
            $this->quickAiName = 'Der Seelen-Öffner (Alu-Schlüsselanhänger)';
            $this->quickAiSku = $this->generateNextCleanSku('MSF-ALU-OEFFNER');
            $this->quickAiPrice = '14.90';
            $this->quickAiPurchasePrice = '0.29';
            if (trim($this->quickAiReorderUrl) === '') {
                $this->quickAiReorderUrl = 'https://www.heavytool-shop.de/';
            }
            $this->quickAiSupplierId = $sup?->id;
            $this->quickAiQuantity = 0;
            $this->quickAiWeight = 15;
            $this->quickAiWidth = 15;
            $this->quickAiHeight = 65;
            $this->quickAiLength = 12.0;
            $supDays = (int) ($sup?->lead_time_land_days ?: 3);
            $this->quickAiLeadDaysMin = (string) $supDays;
            $this->quickAiLeadDaysMax = (string) ($supDays + 2);
        } elseif ($presetKey === 'bambus-pen') {
            $sup = \App\Models\Product\ProductSupplier::where('name', 'like', '%HaoYiZF%')->first();
            $this->quickAiName = 'Der Seelen-Schreiber (Bambus-Kugelschreiber)';
            $this->quickAiSku = $this->generateNextCleanSku('MSF-HOLZ-SCHREIBER');
            $this->quickAiPrice = '12.90';
            $this->quickAiPurchasePrice = '0.30';
            if (trim($this->quickAiReorderUrl) === '') {
                $this->quickAiReorderUrl = 'https://www.temu.com/';
            }
            $this->quickAiSupplierId = $sup?->id;
            $this->quickAiQuantity = 0;
            $this->quickAiWeight = 12;
            $this->quickAiWidth = 11;
            $this->quickAiHeight = 140;
            $this->quickAiLength = 11.0;
            $supDays = (int) ($sup?->lead_time_land_days ?: ($sup?->lead_time_air_days ?: 5));
            $this->quickAiLeadDaysMin = (string) $supDays;
            $this->quickAiLeadDaysMax = (string) ($supDays + 3);
        } elseif ($presetKey === 'seelen-pfote-hundemarke') {
            $sup = \App\Models\Product\ProductSupplier::where('name', 'like', '%CTHOLD%')->orWhere('name', 'like', '%HaoYiZF%')->first();
            $this->quickAiName = 'Die Seelen-Pfote Edition';
            $this->quickAiSku = $this->generateNextCleanSku('MSF-EDELSTAHL-PFOTE');
            $this->quickAiPrice = '29.90';
            $this->quickAiPurchasePrice = '0.98';
            if (trim($this->quickAiReorderUrl) === '') {
                $this->quickAiReorderUrl = 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=601099544143627&parent_order_sn=PO-076-04964841144950774&_oak_order_sn=076-04964799201910774&_oak_goods_num=3&_x_sessn_id=qexm6jf56e&refer_page_name=bgt_order_receipt&refer_page_id=10471_1793672371535_hlekpk8i9o&refer_page_sn=10471&is_back=1';
            }
            $this->quickAiSupplierId = $sup?->id;
            $this->quickAiQuantity = 0;
            $this->quickAiWeight = 12;
            $this->quickAiWidth = 40;
            $this->quickAiHeight = 22;
            $this->quickAiLength = 1.8;
            $supDays = (int) ($sup?->lead_time_land_days ?: ($sup?->lead_time_air_days ?: 5));
            $this->quickAiLeadDaysMin = (string) $supDays;
            $this->quickAiLeadDaysMax = (string) ($supDays + 3);
        } elseif ($presetKey === 'goldenes-seelen-herz') {
            $sup = \App\Models\Product\ProductSupplier::where('name', 'like', '%Glücksfieber%')->first();
            $this->quickAiName = 'Das Goldene Seelen-Herz';
            $this->quickAiSku = $this->generateNextCleanSku('MSF-GOLD-HERZ');
            $this->quickAiPrice = '49.90';
            $this->quickAiPurchasePrice = '4.41';
            if (trim($this->quickAiReorderUrl) === '') {
                $this->quickAiReorderUrl = 'https://www.gluecksfieber.de/shop/laserrohlinge/edelstahl-laserrohling-anhaenger-herz-mit-collierschlaufe-14-karat-vergoldet-25-x-22-x-4-mm-oese-3-x-6-mm-ion-plating-ip.html';
            }
            $this->quickAiSupplierId = $sup?->id;
            $this->quickAiQuantity = 0;
            $this->quickAiWeight = 18;
            $this->quickAiWidth = 22;
            $this->quickAiHeight = 25;
            $this->quickAiLength = 4.0;
            $supDays = (int) ($sup?->lead_time_land_days ?: 3);
            $this->quickAiLeadDaysMin = (string) $supDays;
            $this->quickAiLeadDaysMax = (string) ($supDays + 2);
        } else {
            $sup = \App\Models\Product\ProductSupplier::where('name', 'like', '%HaoYiZF%')->first();
            $this->quickAiName = 'Der Seelen-Talisman Edition';
            $this->quickAiSku = $this->generateNextCleanSku('MSF-EDELSTAHL-TALISMAN');
            $this->quickAiPrice = '36.90';
            $this->quickAiPurchasePrice = '1.16';
            if (trim($this->quickAiReorderUrl) === '') {
                $this->quickAiReorderUrl = 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=606203291790930&sku_id=89857024078818';
            }
            $this->quickAiSupplierId = $sup?->id;
            $this->quickAiQuantity = 0;
            $this->quickAiWeight = 24;
            $this->quickAiWidth = 28;
            $this->quickAiHeight = 50;
            $this->quickAiLength = 2.2;
            $supDays = (int) ($sup?->lead_time_land_days ?: ($sup?->lead_time_air_days ?: 5));
            $this->quickAiLeadDaysMin = (string) $supDays;
            $this->quickAiLeadDaysMax = (string) ($supDays + 3);
        }
    }

    /**
     * Erzeugt eine saubere, fortlaufende und eindeutige Artikelnummer (SKU)
     * im Mein-Seelenfunke-Standardformat MSF-<MATERIAL>-<FORM>-<NNN> (z.B. MSF-EDELSTAHL-PFOTE-001).
     */
    public function generateNextCleanSku(string $prefix = 'MSF-UNIKAT', ?string $ignoreProductId = null): string
    {
        $cleanPrefix = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $prefix), '-'));
        if ($cleanPrefix === '') {
            $cleanPrefix = 'MSF-UNIKAT';
        }

        $query = Product::withTrashed()->where('sku', 'like', $cleanPrefix . '-%');
        if ($ignoreProductId) {
            $query->where('id', '!=', $ignoreProductId);
        }

        $existingSkus = $query->pluck('sku')->map(fn ($s) => strtoupper(trim((string) $s)))->all();
        $usedNumbers = [];
        foreach ($existingSkus as $sku) {
            if (preg_match('/^' . preg_quote($cleanPrefix, '/') . '-(\d{1,4})$/', $sku, $m)) {
                $usedNumbers[(int) $m[1]] = true;
            }
        }

        $nextNum = 1;
        while (isset($usedNumbers[$nextNum])) {
            $nextNum++;
        }

        return $cleanPrefix . '-' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Bereinigt eine eingegebene Artikelnummer auf Großbuchstaben/Bindestriche und stellt Eindeutigkeit sicher.
     */
    protected function normalizeCleanSku(string $rawSku, string $fallbackPrefix = 'MSF-UNIKAT', ?string $ignoreProductId = null): string
    {
        $cleaned = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', trim($rawSku)), '-'));
        $cleaned = (string) preg_replace('/-+/', '-', $cleaned);

        if ($cleaned === '' || strlen($cleaned) < 3) {
            return $this->generateNextCleanSku($fallbackPrefix, $ignoreProductId);
        }

        $existsQuery = Product::withTrashed()->where('sku', $cleaned);
        if ($ignoreProductId) {
            $existsQuery->where('id', '!=', $ignoreProductId);
        }

        if (!$existsQuery->exists()) {
            return $cleaned;
        }

        // Falls exakt diese SKU bereits vergeben ist, Basis-Präfix extrahieren und nächste freie Nummer wählen
        $basePrefix = preg_replace('/-\d{1,4}$/', '', $cleaned) ?: $fallbackPrefix;
        return $this->generateNextCleanSku($basePrefix, $ignoreProductId);
    }

    /**
     * Analysiert den eingegebenen Rohling-Kauflink (URL-Slug, Query-Parameter, top_gallery_url,
     * Temu-Preiscodierung, Gebindegröße und Händler-Domain) und berechnet alle fehlenden Produktwerte logisch.
     */
    protected function inferFromQuickAiUrl(string $url): array
    {
        $cleanUrl = trim($url);
        $lower = mb_strtolower(urldecode($cleanUrl));
        $host = strtolower((string) parse_url($cleanUrl, PHP_URL_HOST));
        $path = (string) parse_url($cleanUrl, PHP_URL_PATH);
        $queryStr = (string) parse_url($cleanUrl, PHP_URL_QUERY);
        parse_str($queryStr, $queryParams);

        $searchKey = mb_strtolower((string) ($queryParams['search_key'] ?? ''));
        $haystack = $lower . ' ' . $searchKey . ' ' . mb_strtolower($path);

        // 1. Gebindegröße (Pack-Size) aus Link erkennen (z.B. "set-mit-10", "50-stueck", "_oak_goods_num")
        $packSize = 1;
        if (preg_match('/(?:set-mit-|set-von-|pack-mit-)(\d{1,3})\b/i', $haystack, $m)) {
            $packSize = max(1, (int) $m[1]);
        } elseif (preg_match('/\b(\d{1,3})\s*(?:stueck|stück|pcs|er-set|teile)\b/i', $haystack, $m)) {
            $packSize = max(1, (int) $m[1]);
        } elseif (!empty($queryParams['_oak_goods_num']) && is_numeric($queryParams['_oak_goods_num'])) {
            $packSize = max(1, (int) $queryParams['_oak_goods_num']);
        }

        // 2. Produkt-Archetyp & Referenz-Geometrie aus Link ableiten
        $detectedPreset = $this->quickAiPreset !== 'auto' ? $this->quickAiPreset : 'seelen-talisman';
        if ($this->quickAiPreset === 'auto') {
            if (preg_match('/hund|pfote|knochen|haustier|tier|dog|paw|bone|katze/', $haystack)) {
                $detectedPreset = 'seelen-pfote-hundemarke';
            } elseif (preg_match('/glas|weizen|bier|trink|becher|kelch|tasse/', $haystack)) {
                $detectedPreset = 'weizenglas-personalisiert';
            } elseif (preg_match('/kristall|pokal|trophae|trophäe|glasblock|3d-glas|award/', $haystack)) {
                $detectedPreset = 'seelen-kristall';
            } elseif (preg_match('/collierschlaufe|14-karat|ion-plating/', $haystack) || (str_contains($host, 'gluecksfieber') && preg_match('/herz|heart/', $haystack))) {
                $detectedPreset = 'goldenes-seelen-herz';
            } elseif (preg_match('/herz|heart|liebe|valentins/', $haystack)) {
                $detectedPreset = 'seelen-anhaenger';
            } elseif (preg_match('/oeffner|öffner|flaschen|bottle|heavytool/', $haystack)) {
                $detectedPreset = 'heavytool-opener';
            } elseif (preg_match('/kugelschreiber|stift|bambus|pen|schreiber/', $haystack)) {
                $detectedPreset = 'bambus-pen';
            } else {
                $detectedPreset = 'seelen-talisman';
            }
        }

        // 3. Basis-Stammdaten je erkanntem Archetyp (Start-Lagerbestand bei Neuanlage immer 0!)
        $defaults = match ($detectedPreset) {
            'seelen-pfote-hundemarke' => [
                'ref_slug' => 'seelen-pfote-hundemarke',
                'name' => 'Die Seelen-Pfote (Edelstahl-Hundemarke)',
                'sku_prefix' => 'MSF-EDELSTAHL-PFOTE',
                'sku' => $this->generateNextCleanSku('MSF-EDELSTAHL-PFOTE'),
                'unit_ek' => 0.98,
                'vk' => 29.90,
                'width' => 40,
                'height' => 22,
                'length' => 1.8,
                'weight' => 12,
                'qty' => 0,
                'material' => 'Hochglanzpolierter Edelstahl (rostfrei)',
            ],
            'weizenglas-personalisiert' => [
                'ref_slug' => 'weizenglas-personalisiert',
                'name' => 'Das Seelen-Glas (Personalisiertes Kristallglas)',
                'sku_prefix' => 'MSF-GLAS-WEIZEN',
                'sku' => $this->generateNextCleanSku('MSF-GLAS-WEIZEN'),
                'unit_ek' => 3.33,
                'vk' => 29.90,
                'width' => 74,
                'height' => 218,
                'length' => 74.0,
                'weight' => 450,
                'qty' => 0,
                'material' => 'Brillantes Kristallglas (Spülmaschinenfest)',
            ],
            'seelen-kristall' => [
                'ref_slug' => 'seelen-kristall',
                'name' => 'Der Seelen-Kristall (Optischer 3D-Kristallblock)',
                'sku_prefix' => 'MSF-KRISTALL-3D',
                'sku' => $this->generateNextCleanSku('MSF-KRISTALL-3D'),
                'unit_ek' => 5.36,
                'vk' => 69.90,
                'width' => 180,
                'height' => 200,
                'length' => 40.0,
                'weight' => 920,
                'qty' => 0,
                'material' => 'Optisches K9-Kristallglas mit Facettenschliff',
            ],
            'goldenes-seelen-herz' => [
                'ref_slug' => 'goldenes-seelen-herz',
                'name' => 'Das Goldene Seelen-Herz (Edelstahl-Herz mit Collierschlaufe)',
                'sku_prefix' => 'MSF-GOLD-HERZ',
                'sku' => $this->generateNextCleanSku('MSF-GOLD-HERZ'),
                'unit_ek' => 4.41,
                'vk' => 49.90,
                'width' => 22,
                'height' => 25,
                'length' => 4.0,
                'weight' => 18,
                'qty' => 0,
                'material' => '14 Karat vergoldeter Edelstahl (Ion-Plating)',
            ],
            'seelen-anhaenger' => [
                'ref_slug' => 'seelen-anhaenger',
                'name' => 'Der Seelen-Anhänger (Herz-Edelstahlmarke)',
                'sku_prefix' => 'MSF-ALU-HERZ',
                'sku' => $this->generateNextCleanSku('MSF-ALU-HERZ'),
                'unit_ek' => 1.36,
                'vk' => 29.90,
                'width' => 45,
                'height' => 40,
                'length' => 2.3,
                'weight' => 19,
                'qty' => 0,
                'material' => 'Eloxiertes Aluminium / Edelstahl',
            ],
            'heavytool-opener' => [
                'ref_slug' => 'seelen-talisman',
                'name' => 'Der Seelen-Öffner (Alu-Schlüsselanhänger)',
                'sku_prefix' => 'MSF-ALU-OEFFNER',
                'sku' => $this->generateNextCleanSku('MSF-ALU-OEFFNER'),
                'unit_ek' => 0.29,
                'vk' => 14.90,
                'width' => 15,
                'height' => 65,
                'length' => 12.0,
                'weight' => 15,
                'qty' => 0,
                'material' => 'Eloxiertes Aluminium mit Gravurfläche',
            ],
            'bambus-pen' => [
                'ref_slug' => 'seelen-talisman',
                'name' => 'Der Seelen-Schreiber (Bambus-Kugelschreiber)',
                'sku_prefix' => 'MSF-HOLZ-SCHREIBER',
                'sku' => $this->generateNextCleanSku('MSF-HOLZ-SCHREIBER'),
                'unit_ek' => 0.30,
                'vk' => 12.90,
                'width' => 11,
                'height' => 140,
                'length' => 11.0,
                'weight' => 12,
                'qty' => 0,
                'material' => 'Natur-Bambusholz mit Metallclip',
            ],
            default => [
                'ref_slug' => 'seelen-talisman',
                'name' => 'Der Seelen-Talisman (Edelstahl-Schlüsselanhänger)',
                'sku_prefix' => 'MSF-EDELSTAHL-TALISMAN',
                'sku' => $this->generateNextCleanSku('MSF-EDELSTAHL-TALISMAN'),
                'unit_ek' => 1.16,
                'vk' => 36.90,
                'width' => 28,
                'height' => 50,
                'length' => 2.2,
                'weight' => 24,
                'qty' => 0,
                'material' => 'Massiver Edelstahl (Spiegelpoliert)',
            ],
        };

        // 4. Falls Temu Base64-Preiscodierung (_oak_rec_ext_1) vorhanden ist, exakten Stück-EK berechnen!
        if (!empty($queryParams['_oak_rec_ext_1'])) {
            $decodedPrice = base64_decode((string) $queryParams['_oak_rec_ext_1'], true);
            if ($decodedPrice !== false && is_numeric($decodedPrice) && (int) $decodedPrice > 0) {
                $totalPackEur = ((int) $decodedPrice) / 100.0;
                $defaults['unit_ek'] = round($totalPackEur / max(1, $packSize), 2);
            }
        }

        // 5. Optimalen VK logisch aus dem Stück-EK ableiten (Ziel: ~88-92% Rohertrag + psychologischer .90 Preis)
        $ek = (float) $defaults['unit_ek'];
        if ($ek > 0 && $defaults['vk'] <= 0) {
            $rawTarget = max(14.90, ceil($ek * 14));
            $defaults['vk'] = floor($rawTarget) + 0.90;
        }

        // 6. Lieferant anhand der Domain automatisch zuordnen
        $supplier = null;
        if (str_contains($host, 'silberketten-store')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Silberketten-Store%')->first();
        } elseif (str_contains($host, 'fbm-chains') || str_contains($host, 'binder')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Friedrich Binder%')->first();
        } elseif (str_contains($host, 'maxfroehlich')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Max Fröhlich%')->first();
        } elseif (str_contains($host, 'kleckow') || str_contains($host, 'schmuckkauf')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Kleckow%')->first();
        } elseif (str_contains($host, 'materia-schmuck')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%MATERIA%')->first();
        } elseif (str_contains($host, 'temu')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%HaoYiZF%')
                ->orWhere('name', 'like', '%Temu%')->first();
        } elseif (str_contains($host, 'heavytool')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Heavytool%')->first();
        } elseif (str_contains($host, 'metro')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%METRO%')->first();
        } elseif (str_contains($host, 'alibaba') || str_contains($host, 'aliexpress')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Pujiang%')->first();
        } elseif (str_contains($host, 'gluecksfieber')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Glücksfieber%')->first();
        } elseif (str_contains($host, 'printequipment')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Print Equipment%')->first();
        } elseif (str_contains($host, 'amazon') || str_contains($host, 'ebay')) {
            $supplier = \App\Models\Product\ProductSupplier::where('name', 'like', '%Thal-Versand%')->first();
        }
        if (!$supplier) {
            $supplier = \App\Models\Product\ProductSupplier::first();
        }

        // 7. Kollektions-Bereich (3-Welten-Weiche: Manufaktur vs. Atelier Echtgold & Silber) automatisch erkennen
        $isAtelierFineJewelry = str_contains($host, 'silberketten-store')
            || str_contains($host, 'fbm-chains')
            || str_contains($host, 'maxfroehlich')
            || str_contains($host, 'kleckow')
            || str_contains($host, 'schmuckkauf')
            || str_contains($host, 'materia-schmuck')
            || str_contains($host, 'orovivo')
            || (bool) preg_match('/\b(585|333|375|750|925)\b|echtgold|gelbgold|weissgold|weißgold|sterling|silberarmband|gravurarmband|goldschmuck|silberschmuck/i', $haystack);

        $defaults['collection_line'] = $isAtelierFineJewelry ? 'atelier' : 'manufaktur';
        $defaults['detected_preset'] = $detectedPreset;
        $defaults['supplier_id'] = $supplier?->id;

        // 8. Lieferanten-Vorlaufzeit (Beschaffungszeit bei Bestand 0) intelligent aus Lieferant oder Domain ableiten
        $supLeadDays = max(0, (int) ($supplier?->lead_time_land_days ?: ($supplier?->lead_time_air_days ?: 0)));
        if ($supLeadDays > 0) {
            $defaults['lead_days_min'] = $supLeadDays;
            $defaults['lead_days_max'] = $supLeadDays + 2;
        } elseif (str_contains($host, 'temu') || str_contains($host, 'alibaba') || str_contains($host, 'aliexpress')) {
            $defaults['lead_days_min'] = 5;
            $defaults['lead_days_max'] = 8;
        } elseif ($isAtelierFineJewelry) {
            $defaults['lead_days_min'] = 4;
            $defaults['lead_days_max'] = 6;
        } else {
            $defaults['lead_days_min'] = 3;
            $defaults['lead_days_max'] = 5;
        }

        $defaults['top_gallery_url'] = !empty($queryParams['top_gallery_url'])
            ? urldecode((string) $queryParams['top_gallery_url'])
            : null;

        return $defaults;
    }

    /**
     * Optionaler Button im Modal: Füllt leere Felder vorab sichtbar mit den aus dem Link berechneten KI-Werten.
     */
    public function analyzeQuickAiUrl(): void
    {
        $this->resetValidation();
        $url = trim($this->quickAiReorderUrl);
        if ($url === '') {
            $this->addError('quickAiReorderUrl', 'Bitte gib zuerst einen Produkt-Link (URL) zum Rohling ein.');
            return;
        }

        $inferred = $this->inferFromQuickAiUrl($url);

        if (trim($this->quickAiName) === '') {
            $this->quickAiName = $inferred['name'];
        }
        if (trim($this->quickAiSku) === '') {
            $this->quickAiSku = $inferred['sku'];
        }
        if (trim((string) $this->quickAiPurchasePrice) === '') {
            $this->quickAiPurchasePrice = number_format((float) $inferred['unit_ek'], 2, '.', '');
        }
        if (trim((string) $this->quickAiPrice) === '') {
            $this->quickAiPrice = number_format((float) $inferred['vk'], 2, '.', '');
        }
        if (empty($this->quickAiSupplierId)) {
            $this->quickAiSupplierId = $inferred['supplier_id'];
        }
        if (trim((string) $this->quickAiQuantity) === '') {
            $this->quickAiQuantity = $inferred['qty'];
        }
        if (trim((string) $this->quickAiWeight) === '') {
            $this->quickAiWeight = $inferred['weight'];
        }
        if (trim((string) $this->quickAiWidth) === '') {
            $this->quickAiWidth = $inferred['width'];
        }
        if (trim((string) $this->quickAiHeight) === '') {
            $this->quickAiHeight = $inferred['height'];
        }
        if (trim((string) $this->quickAiLength) === '') {
            $this->quickAiLength = $inferred['length'];
        }
        if (trim((string) $this->quickAiLeadDaysMin) === '') {
            $this->quickAiLeadDaysMin = (string) ($inferred['lead_days_min'] ?? 4);
        }
        if (trim((string) $this->quickAiLeadDaysMax) === '') {
            $this->quickAiLeadDaysMax = (string) ($inferred['lead_days_max'] ?? 6);
        }
    }

    /**
     * Erstellt per 1-Klick ein komplett konfiguriertes, verkaufsfertiges Produkt
     * allein anhand des Rohling-Links (alle optionalen Felder werden bei Leerfeld logisch von der KI berechnet).
     */
    public function createQuickAiProduct(): void
    {
        $this->resetValidation();
        $url = trim($this->quickAiReorderUrl);

        if ($url === '') {
            $this->addError('quickAiReorderUrl', 'Bitte gib den Kauflink zum Rohling-Produkt an – alle anderen Felder berechnet die KI automatisch.');
            return;
        }

        $inferred = $this->inferFromQuickAiUrl($url);

        $name = trim($this->quickAiName) !== '' ? trim($this->quickAiName) : $inferred['name'];
        $rawSku = trim($this->quickAiSku) !== '' ? trim($this->quickAiSku) : $inferred['sku'];
        $sku = $this->normalizeCleanSku($rawSku, $inferred['sku_prefix'] ?? 'STEEL-BAR');
        $ekFloat = trim((string) $this->quickAiPurchasePrice) !== ''
            ? (float) str_replace(',', '.', (string) $this->quickAiPurchasePrice)
            : (float) $inferred['unit_ek'];

        // Falls VK leer ist, logisch aus EK bzw. Archetyp berechnen
        if (trim((string) $this->quickAiPrice) !== '') {
            $vkFloat = (float) str_replace(',', '.', (string) $this->quickAiPrice);
        } else {
            $vkFloat = $ekFloat > 0 ? max((float) $inferred['vk'], floor(ceil($ekFloat * 12)) + 0.90) : (float) $inferred['vk'];
        }

        $quantity = trim((string) $this->quickAiQuantity) !== '' ? (int) $this->quickAiQuantity : (int) $inferred['qty'];
        $weight = trim((string) $this->quickAiWeight) !== '' ? (int) $this->quickAiWeight : (int) $inferred['weight'];
        $width = trim((string) $this->quickAiWidth) !== '' ? (int) $this->quickAiWidth : (int) $inferred['width'];
        $height = trim((string) $this->quickAiHeight) !== '' ? (int) $this->quickAiHeight : (int) $inferred['height'];
        $length = trim((string) $this->quickAiLength) !== '' ? (float) str_replace(',', '.', (string) $this->quickAiLength) : (float) $inferred['length'];
        $supplierId = !empty($this->quickAiSupplierId) ? $this->quickAiSupplierId : $inferred['supplier_id'];

        $baseSlug = Str::slug($name) ?: ('seelen-unikat-' . rand(100, 999));
        $slug = $baseSlug;
        $counter = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $priceCents = max(100, (int) round($vkFloat * 100));
        $ekCents = max(0, (int) round($ekFloat * 100));

        $refSlug = $inferred['ref_slug'];
        $detectedArchetype = $inferred['detected_preset'] ?? $refSlug;
        $refProduct = Product::where('slug', $refSlug)->first();
        $configSettings = $refProduct && is_array($refProduct->configurator_settings)
            ? $refProduct->configurator_settings
            : app(ConfiguratorService::class)->getDefaultSettings();

        $selectedMode = in_array($this->quickAiPersonalizationMode, ['custom_fields', 'studio', 'standard'], true)
            ? $this->quickAiPersonalizationMode
            : 'custom_fields';

        $isPersonalizable = $selectedMode !== 'standard';
        $threeDActive = $selectedMode === 'studio';

        if ($selectedMode === 'custom_fields') {
            $configSettings['personalization_mode'] = 'custom_fields';
            $configSettings['custom_fields'] = $this->buildQuickAiCustomFields($detectedArchetype, $this->quickAiCustomFieldsPreset);
        } elseif ($selectedMode === 'studio') {
            $configSettings['personalization_mode'] = 'studio';
            $configSettings['custom_fields'] = $configSettings['custom_fields'] ?? [];
        } else {
            $configSettings['personalization_mode'] = 'studio';
            $configSettings['custom_fields'] = [];
        }

        // Lieferanten-Vorlaufzeit bei Bestand 0 in configurator_settings speichern
        $leadMin = (trim((string) $this->quickAiLeadDaysMin) !== '' && (int) $this->quickAiLeadDaysMin > 0)
            ? max(1, (int) $this->quickAiLeadDaysMin)
            : max(1, (int) ($inferred['lead_days_min'] ?? 4));
        $leadMax = (trim((string) $this->quickAiLeadDaysMax) !== '' && (int) $this->quickAiLeadDaysMax >= $leadMin)
            ? (int) $this->quickAiLeadDaysMax
            : max($leadMin + 1, (int) ($inferred['lead_days_max'] ?? ($leadMin + 2)));
        $configSettings['out_of_stock_lead_days_min'] = $leadMin;
        $configSettings['out_of_stock_lead_days_max'] = $leadMax;

        $baseDeliv = \App\Models\Delivery\DeliverySetting::getDeliveryDetails();
        $manufakturMin = max(1, (int) ($baseDeliv['min_days'] ?? 3));
        $manufakturMax = max($manufakturMin, (int) ($baseDeliv['max_days'] ?? 5));
        $effectiveMinDays = ($quantity <= 0) ? ($manufakturMin + $leadMin) : $manufakturMin;
        $effectiveMaxDays = ($quantity <= 0) ? ($manufakturMax + $leadMax) : $manufakturMax;
        $effectiveDeliveryAttr = "{$effectiveMinDays}-{$effectiveMaxDays} Werktage";

        $previewPath = $threeDActive ? ($refProduct?->preview_image_path ?? 'produkte/products/seelen-talisman/overlay.png') : ($refProduct?->preview_image_path ?? null);
        $modelPath = $threeDActive ? ($refProduct?->three_d_model_path ?? 'produkte/products/seelen-talisman/seelen_talisman_3d.glb') : null;
        $bgPath = $threeDActive ? ($refProduct?->three_d_background_path ?? 'produkte/products/seelen-talisman/header_bg.png') : null;

        $dimensionImgMap = [
            'seelen-pfote-hundemarke'   => 'produkte/products/seelen-pfote-hundemarke/seelen-pfote-dimensions.jpg',
            'seelen-talisman'           => 'produkte/products/seelen-talisman/seelen-talisman-dimensions.jpg',
            'seelen-anhaenger'          => 'produkte/products/seelen-anhaenger/seelen-anhaenger-dimensions.jpg',
            'goldenes-seelen-herz'      => 'produkte/products/goldenes-seelen-herz/goldenes-seelen-herz-dimensions.jpg',
            'echtgold-seelen-herz'      => 'produkte/products/echtgold-seelen-herz/echtgold-seelen-herz-dimensions.jpg',
            'seelen-kristall'           => 'produkte/products/seelen-kristall/seelen-kristall-dimensions.jpg',
            'weizenglas-personalisiert' => 'produkte/products/weizenglas-personalisiert/weizenglas-dimensions.jpg',
        ];
        $dimensionImagePath = $dimensionImgMap[$refSlug] ?? 'produkte/products/seelen-talisman/seelen-talisman-dimensions.jpg';

        $mediaGallery = $refProduct && !empty($refProduct->media_gallery)
            ? array_values(array_filter(
                $refProduct->media_gallery,
                fn ($item) => ($item['type'] ?? 'image') !== 'video'
                    && !str_contains((string) ($item['path'] ?? ''), '-giftbox')
            ))
            : [
                ['type' => 'image', 'path' => 'produkte/products/seelen-talisman/seelen-talisman-main.jpg', 'is_main' => true, 'is_ai_generated' => true, 'alt' => $name],
                ['type' => 'image', 'path' => $dimensionImagePath, 'is_main' => false, 'is_ai_generated' => true, 'alt' => $name . ' Technische Maßangaben (' . $width . ' x ' . $height . ' x ' . $length . ' mm)'],
            ];

        // Sicherstellen, dass das Maßblatt-Produktbild auf der letzten Bildposition vorhanden ist
        $hasDimensionSheet = collect($mediaGallery)->contains(fn ($item) => str_contains((string) ($item['path'] ?? ''), '-dimensions.jpg'));
        if (!$hasDimensionSheet) {
            $mediaGallery[] = [
                'type' => 'image',
                'path' => $dimensionImagePath,
                'is_main' => false,
                'is_ai_generated' => true,
                'alt' => "{$name}: Detaillierte Maßangaben ({$width} x {$height} x {$length} mm)",
            ];
        }

        // Kollektions-Bereich (Manufaktur vs. Atelier Echtgold & Silber) bestimmen
        $resolvedCollectionLine = in_array($this->quickAiCollectionLine, ['manufaktur', 'atelier'], true)
            ? $this->quickAiCollectionLine
            : ($inferred['collection_line'] ?? 'manufaktur');

        // Versandart & Verpackung (Luftpolster-Umschlag vs. Westpack-Schmuckbox + versichertes DHL-Paket bei Atelier) automatisch bestimmen
        $isAtelier = ($resolvedCollectionLine === 'atelier');
        $isEnvelope = (!$isAtelier && $weight <= 100 && !in_array($refSlug, ['seelen-kristall', 'weizenglas-personalisiert'], true));
        $shippingClass = $isEnvelope ? 'brief' : null;
        $packagingCostCents = $isAtelier ? 166 : ($isEnvelope ? 14 : 60); // 1,66 € (Westpack Schmuckbox + Karton) | 0,14 € (Luftpolster) | 0,60 € (Karton)
        $packagingWeightGrams = $isAtelier ? 170 : ($isEnvelope ? 15 : ($refSlug === 'weizenglas-personalisiert' ? 120 : 150));
        $wearFactorCents = ($isAtelier || !$isEnvelope) ? 15 : 5;

        if ($selectedMode === 'standard') {
            $descriptionText = "{$name}: Fertig veredeltes Unikat aus unserer deutschen Manufaktur Mein-Seelenfunke. Jedes Exemplar ({$width} x {$height} x {$length} mm, ca. {$weight} g) wird per Hochpräzisions-Laser dauerhaft veredelt, handgeprüft und sicher verpackt an dich geliefert.";
            $shortDescText = $quantity > 0
                ? "Handveredeltes Manufaktur-Unikat ({$width} x {$height} mm) mit präziser Lasergravur, direkt ab Lager verfügbar."
                : "Handveredeltes Manufaktur-Unikat ({$width} x {$height} mm) mit präziser Lasergravur, frisch auf Bestellung in Gifhorn gefertigt.";
            $seoTitleText = "{$name} | Handveredeltes Unikat aus Deutschland | Mein-Seelenfunke";
            $seoDescText = $quantity > 0
                ? "{$name} ({$width} x {$height} mm) fertig laserveredelt aus unserer Manufaktur in Gifhorn. Direkt ab Lager bestellbar."
                : "{$name} ({$width} x {$height} mm) fertig laserveredelt aus unserer Manufaktur in Gifhorn. Auftragsfertigung mit {$effectiveDeliveryAttr} Lieferzeit.";
        } elseif ($selectedMode === 'custom_fields') {
            $descriptionText = "{$name}: Individuell in unserer deutschen Manufaktur Mein-Seelenfunke per Hochpräzisions-Laser für dich veredelt. Gib einfach deinen Wunschnamen oder Wunschtext ein und wir fertigen dein persönliches Unikat ({$width} x {$height} x {$length} mm, ca. {$weight} g) millimetergenau und abriebfest.";
            $shortDescText = "Personalisiertes Unikat ({$width} x {$height} mm) mit individueller Wunschgravur aus unserer Manufaktur Mein-Seelenfunke.";
            $seoTitleText = "{$name} | Mit Wunschnamen gravieren lassen | Mein-Seelenfunke";
            $seoDescText = "{$name} ({$width} x {$height} mm) mit deinem Wunschnamen gravieren lassen. Handveredelt per Präzisionslaser in unserer Manufaktur in Gifhorn.";
        } else {
            $descriptionText = "{$name}: Individuell in unserer Manufaktur Mein-Seelenfunke per Hochpräzisions-Laser für dich veredelt. Jedes Exemplar ({$width} x {$height} x {$length} mm, ca. {$weight} g) wird nach deinen Wünschen im Live-3D-Konfigurator gestaltet, abriebfest graviert und sorgfältig geprüft versendet.";
            $shortDescText = "Personalisiertes Unikat ({$width} x {$height} mm) mit hochpräziser Lasergravur aus der Manufaktur Mein-Seelenfunke.";
            $seoTitleText = "{$name} | Mit eigener Gravur im 3D-Konfigurator gestalten | Mein-Seelenfunke";
            $seoDescText = "{$name} ({$width} x {$height} mm) individuell gravieren lassen im 3D-Live-Konfigurator von Mein-Seelenfunke. Handveredelt in unserer Manufaktur in Gifhorn.";
        }

        $created = Product::create([
            'name' => $name,
            'slug' => $slug,
            'type' => 'physical',
            'collection_line' => $resolvedCollectionLine,
            'status' => 'active',
            'is_personalizable' => $isPersonalizable,
            'three_d_active' => $threeDActive,
            'description' => $descriptionText,
            'short_description' => $shortDescText,
            'price' => $priceCents,
            'compare_at_price' => null,
            'purchase_price' => $ekCents,
            'laser_runtime_minutes' => $isAtelier ? 2 : 1,
            'electricity_wear_factor' => $wearFactorCents,
            'packaging_cost' => $packagingCostCents,
            'marketing_cost_percent' => 10.00,
            'sku' => $sku,
            'product_supplier_id' => $supplierId ?: null,
            'reorder_url' => $url,
            'brand' => $isAtelier ? 'Mein-Seelenfunke Atelier' : 'Mein-Seelenfunke',
            'track_quantity' => true,
            'quantity' => max(0, $quantity),
            'continue_selling_when_out_of_stock' => true,
            'weight' => max(1, $weight),
            'packaging_weight' => $packagingWeightGrams,
            'height' => max(1, $height),
            'width' => max(1, $width),
            'length' => max(0.5, $length),
            'shipping_class' => $shippingClass,
            'preview_image_path' => $previewPath,
            'three_d_model_path' => $modelPath,
            'three_d_background_path' => $bgPath,
            'media_gallery' => $mediaGallery,
            'configurator_settings' => $configSettings,
            'attributes' => [
                'Material' => $inferred['material'] ?? 'Eloxiertes Metall / Edelstahl',
                'Größe' => "{$width} x {$height} x {$length} mm",
                'Gewicht' => "ca. {$weight} g",
                'Veredelung' => 'Präzisions-Lasergravur',
                'Herkunft' => 'Handveredelt in Deutschland (Laser-Manufaktur Gifhorn)',
                'Lieferzeit' => $effectiveDeliveryAttr,
            ],
            'tier_pricing' => [],
            'seo_title' => $seoTitleText,
            'seo_description' => $seoDescText,
            'completion_step' => 4,
        ]);

        // Im Studio-Modus aktive Design-Vorlagen vom Referenzprodukt übernehmen, damit Check #9 sofort auf 100% steht
        if ($selectedMode === 'studio' && $refProduct) {
            foreach ($refProduct->templates()->where('is_active', true)->get() as $refTpl) {
                \App\Models\Product\ProductTemplate::create([
                    'product_id' => $created->id,
                    'name' => $refTpl->name,
                    'holiday' => $refTpl->holiday,
                    'preview_image' => $refTpl->preview_image,
                    'configuration' => $refTpl->configuration,
                    'is_active' => true,
                ]);
            }
        }

        $createdSettings = is_array($created->configurator_settings) ? $created->configurator_settings : [];
        $createdSettings['addons'] = $created->getEffectiveAddons(false);
        $created->updateQuietly(['configurator_settings' => $createdSettings]);

        $catNames = $isAtelier
            ? ['Atelier Echtgold & Silber']
            : ($isEnvelope
                ? ['Metall & Alu', 'Schmuck & Anhänger', 'Bestseller']
                : ['Glas & Kristall', 'Geschenksets', 'Bestseller']);
        $catIds = \App\Models\Product\ProductCategory::whereIn('name', $catNames)->pluck('id');
        $created->categories()->sync($catIds);
        if (!$isAtelier) {
            $created->tierPrices()->createMany([
                ['qty' => 3, 'percent' => 5],
                ['qty' => 5, 'percent' => 10],
                ['qty' => 10, 'percent' => 15],
            ]);
        }

        // Automatische Pflege der Verpackungsseite (/admin/product-packaging & LUCID inkl. Katalog-Verknüpfung)
        $this->syncDefaultProductPackaging($created);

        \App\Models\Marketing\MarketingLandingPage::syncForProduct($created);

        $modeLabel = match ($selectedMode) {
            'custom_fields' => 'Schnell-Personalisierung (' . count($configSettings['custom_fields'] ?? []) . ' Felder)',
            'standard' => 'Fertiges Standard-Produkt',
            default => '2D/3D-Live-Konfigurator',
        };

        $this->showQuickAiModal = false;
        session()->flash('success', "KI-Produkt „{$created->name}“ [{$modeLabel}] ({$width}x{$height} mm, EK " . number_format($ekFloat, 2, ',', '.') . " €, VK " . number_format($vkFloat, 2, ',', '.') . " €) wurde vollautomatisch erstellt!");
    }

    public function backToList()
    {
        $this->viewMode = 'list';
        $this->resetValidation();
        $this->new_media = [];
        $this->search = '';
    }

    // --- WIZARD NAVIGATION ---

    public function goToStep($step)
    {
        // Darf nicht weiter springen als erlaubt (Completion Step + 1)
        if ($step > $this->product->completion_step + 1 && $this->product->completion_step < $this->totalSteps) return;

        // Darf nicht über die maximalen Steps des aktuellen Typs hinaus
        if ($step > $this->totalSteps) return;

        if ($step > $this->currentStep) {
            $canJump = ($this->currentStep === 1) ? $this->canProceedStep1() : $this->canProceed();
            if (!$canJump) return;
            $this->save(false);
        }
        $this->currentStep = $step;
    }

    public function nextSubStep()
    {
        if (!$this->canProceedSubStep()) return;

        if ($this->currentStep === 1 && $this->step1SubStep < 5) {
            $this->save(false);
            $this->step1SubStep++;
            return;
        }

        if ($this->currentStep === 3) {
            $allowed = $this->getStep3AllowedSubSteps();
            $currentIndex = array_search($this->step3SubStep, $allowed, true);
            if ($currentIndex !== false && $currentIndex < count($allowed) - 1) {
                $this->save(false);
                $this->step3SubStep = $allowed[$currentIndex + 1];
                return;
            }
        }

        if ($this->currentStep === 4 && $this->step4SubStep < 4) {
            $this->save(false);
            $this->step4SubStep++;
            return;
        }

        $this->nextStep();
    }

    public function nextStep()
    {
        if(!$this->canProceed()) return;

        // Fortschritt speichern
        if($this->currentStep >= $this->product->completion_step) {
            $this->product->completion_step = $this->currentStep;
        }

        $this->save(false);

        if($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
            if ($this->currentStep === 3) {
                $this->step3SubStep = 1;
            } elseif ($this->currentStep === 4) {
                $this->step4SubStep = 1;
            }
        }
    }

    public function prevStep()
    {
        if ($this->currentStep === 1 && $this->step1SubStep > 1) {
            $this->step1SubStep--;
            return;
        }

        if ($this->currentStep === 3) {
            $allowed = $this->getStep3AllowedSubSteps();
            $currentIndex = array_search($this->step3SubStep, $allowed, true);
            if ($currentIndex !== false && $currentIndex > 0) {
                $this->step3SubStep = $allowed[$currentIndex - 1];
                return;
            }
        }

        if ($this->currentStep === 4 && $this->step4SubStep > 1) {
            $this->step4SubStep--;
            return;
        }

        if($this->currentStep > 1) {
            $this->currentStep--;
            if ($this->currentStep === 1) {
                $this->step1SubStep = 5;
            } elseif ($this->currentStep === 3) {
                $allowed = $this->getStep3AllowedSubSteps();
                $this->step3SubStep = end($allowed) ?: 1;
            } elseif ($this->currentStep === 4) {
                $this->step4SubStep = 4;
            }
        }
    }

    protected function normalizePriceInputs(): void
    {
        foreach (['price_input', 'purchase_price_input', 'compare_price_input', 'packaging_cost_input'] as $field) {
            if ($this->{$field} !== null && $this->{$field} !== '') {
                $this->{$field} = str_replace(',', '.', trim((string) $this->{$field}));
            }
        }
    }

    public function canProceedStep1(): bool
    {
        $this->normalizePriceInputs();
        $price = (float) $this->price_input;
        $purchase = (float) $this->purchase_price_input;

        $validBasic = !empty($this->name) && $price > 0 && !empty($this->sku) && !empty($this->slug_input) && !empty($this->type);

        if ($this->type === 'physical') {
            return $validBasic && $this->purchase_price_input !== '' && $purchase >= 0;
        }

        return $validBasic;
    }

    public function canProceedSubStep(): bool
    {
        $this->normalizePriceInputs();
        if ($this->currentStep === 1) {
            $price = (float) $this->price_input;
            $purchase = (float) $this->purchase_price_input;

            if ($this->step1SubStep === 1) {
                return !empty($this->type);
            }
            if ($this->step1SubStep === 2) {
                $validStep2 = !empty($this->name) && $price > 0;
                return $this->type === 'physical'
                    ? ($validStep2 && $this->purchase_price_input !== '' && $purchase >= 0)
                    : $validStep2;
            }
            if ($this->step1SubStep === 3) {
                return true;
            }
            if ($this->step1SubStep === 4) {
                return !empty($this->slug_input) || !empty($this->name);
            }

            return $this->canProceedStep1();
        }

        return (bool) $this->canProceed();
    }

    public function canProceed()
    {
        $this->normalizePriceInputs();
        if ($this->currentStep === 1) {
            return $this->canProceedStep1();
        }
        if ($this->currentStep === 2) {
            // Mindestens ein Bild prüfen (oder Auto-Saisonbild mit vorhandener Vorlage)
            $hasImage = false;
            foreach($this->product->media_gallery ?? [] as $media) {
                if(isset($media['type']) && $media['type'] === 'image') {
                    $hasImage = true; break;
                }
            }
            if (!$hasImage && filter_var($this->configSettings['auto_seasonal_main_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $hasImage = $this->product->templates()->where('is_active', true)->whereNotNull('preview_image')->where('preview_image', '!=', '')->exists();
            }
            return $hasImage;
        }
        return true;
    }

    public function finish()
    {
        if(!$this->canProceed()) return;

        $this->save(false);

        // Status auf active setzen
        // Completion Step auf Maximum für diesen Typ setzen
        $this->product->update([
            'status' => 'active',
            'completion_step' => $this->totalSteps
        ]);

        $lp = \App\Models\Marketing\MarketingLandingPage::syncForProduct($this->product);
        if ($lp && $lp->status !== 'active') {
            $lp->update(['status' => 'active']);
        }

        session()->flash('success', 'Produkt veröffentlicht!');
        $this->backToList();
    }

    // --- SAVE LOGIK ---

    public function save($notify = true)
    {
        $this->normalizePriceInputs();

        // 1. Basisdaten
        $this->product->name = $this->name;
        $this->product->type = $this->type; // Typ speichern
        $this->product->collection_line = $this->type === 'digital'
            ? 'voucher'
            : ($this->type === 'service'
                ? 'service'
                : (in_array($this->collection_line, ['manufaktur', 'atelier'], true) ? $this->collection_line : 'manufaktur'));
        $this->product->is_personalizable = (bool) $this->is_personalizable;
        $this->product->three_d_active = (bool) $this->three_d_active;
        $this->product->description = $this->description;
        $this->product->short_description = $this->short_description;

        // 2. Slug Logik
        if (!empty($this->slug_input)) {
            $slug = Str::slug($this->slug_input);
            if (Product::where('slug', $slug)->where('id', '!=', $this->product->id)->exists()) {
                $slug .= '-' . time();
            }
            $this->product->slug = $slug;
        }

        // 3. SEO
        $this->product->seo_title = $this->seo_title;
        $this->product->seo_description = $this->seo_description;

        // 4. Identifikatoren (SKU auf sauberes Großbuchstaben-Format <MATERIAL>-<FORM>-<NNN> normalisieren)
        $this->sku = $this->normalizeCleanSku((string) $this->sku, 'MSF-UNIKAT', $this->product->id);
        $this->product->sku = $this->sku;
        $this->product->barcode = $this->barcode;
        $this->product->brand = $this->brand;
        $this->product->product_supplier_id = (!empty($this->supplier_id) && \App\Models\Product\ProductSupplier::where('id', $this->supplier_id)->exists())
            ? $this->supplier_id
            : null;
        $cleanReorderUrl = trim((string) $this->reorder_url);
        $this->product->reorder_url = ($cleanReorderUrl !== '' && preg_match('/^https?:\/\//i', $cleanReorderUrl))
            ? $cleanReorderUrl
            : null;

        // 5. Lager
        $this->product->track_quantity = (bool) $this->track_quantity;
        $this->product->quantity = empty($this->quantity) ? 0 : max(0, (int) $this->quantity);
        $this->product->continue_selling_when_out_of_stock = (bool) $this->continue_selling;

        // 6. Preis (Cent-Konvertierung)
        $this->product->price = empty($this->price_input) ? 0 : max(0, (int) round((float)$this->price_input * 100));
        
        if ($this->type === 'physical') {
            $this->product->purchase_price = $this->purchase_price_input === '' ? 0 : max(0, (int) round((float)$this->purchase_price_input * 100));
        } else {
            $this->product->purchase_price = 0; // Kein EK für Digital/Service
        }
        
        if($this->compare_price_input) {
            $this->product->compare_at_price = max(0, (int) round((float)$this->compare_price_input * 100));
        } else {
            $this->product->compare_at_price = null;
        }

        // 7. Versanddaten & physische Maße (Nur speichern wenn Typ physical ist)
        if ($this->type === 'physical') {
            $this->product->weight = max(0, (int) $this->weight);
            $this->product->packaging_weight = max(0, (int) $this->packaging_weight);
            $this->product->height = max(0, (int) $this->height);
            $this->product->width = max(0, (int) $this->width);
            $this->product->length = max(0, (int) $this->length);
            $this->product->shipping_class = empty($this->shipping_class) ? null : $this->shipping_class; // Speichern!
            
            // Kalkulation & Produktion
            $this->product->laser_runtime_minutes = $this->laser_runtime_minutes === '' ? null : max(0, (int) $this->laser_runtime_minutes);
            $this->product->electricity_wear_factor = $this->electricity_wear_factor === '' ? 1 : max(0, (int) $this->electricity_wear_factor);
            $this->product->marketing_cost_percent = $this->marketing_cost_percent === '' ? 15.00 : max(0, min(100, (float) $this->marketing_cost_percent));
            $this->product->packaging_cost = $this->packaging_cost_input === '' ? 0 : max(0, (int) round((float)$this->packaging_cost_input * 100));
        } else {
            // Bereinigung falls Typ geändert wurde
            $this->product->weight = null;
            $this->product->packaging_weight = null;
            $this->product->height = null;
            $this->product->width = null;
            $this->product->length = null;
            $this->product->shipping_class = null;
            // Kalkulation
            $this->product->laser_runtime_minutes = null;
            $this->product->electricity_wear_factor = 1;
            $this->product->marketing_cost_percent = 15.00;
            $this->product->packaging_cost = null;
        }

        // 8. JSON Arrays (frische Sub-Component-Werte wie Addons & Varianten-Filter aus DB synchronisieren)
        $latestDbSettings = Product::where('id', $this->product->id)->value('configurator_settings');
        if (is_string($latestDbSettings)) {
            $latestDbSettings = json_decode($latestDbSettings, true) ?: [];
        }
        if (is_array($latestDbSettings)) {
            foreach (['excluded_variant_attributes', 'included_variant_attributes', 'addons', 'digital_documents', 'seasonal_images'] as $syncKey) {
                if (array_key_exists($syncKey, $latestDbSettings)) {
                    $this->configSettings[$syncKey] = $latestDbSettings[$syncKey];
                }
            }
        }

        $this->product->attributes = $this->productAttributes;
        $this->product->tier_pricing = $this->tiers;

        // Personalisierungs-Modus & dynamische Eingabefelder sauber normalisieren
        $pMode = strtolower(trim((string) ($this->configSettings['personalization_mode'] ?? 'studio')));
        $this->configSettings['personalization_mode'] = in_array($pMode, ['studio', 'custom_fields'], true) ? $pMode : 'studio';

        $normalizedCustomFields = [];
        $rawCustomFields = isset($this->configSettings['custom_fields']) && is_array($this->configSettings['custom_fields'])
            ? array_values($this->configSettings['custom_fields'])
            : [];
        foreach ($rawCustomFields as $cfIdx => $cfItem) {
            if (!is_array($cfItem)) {
                continue;
            }
            if (trim((string) ($cfItem['label'] ?? '')) === '') {
                $cfItem['label'] = ($cfIdx + 1) . '. Eingabefeld';
            }
            $normCf = Product::normalizeCustomFieldItem($cfItem, $cfIdx);
            if ($normCf !== null) {
                $normalizedCustomFields[] = $normCf;
            }
        }
        $this->configSettings['custom_fields'] = $normalizedCustomFields;

        // Lieferanten-Vorlaufzeit bei Bestand 0 (Überverkauf) sauber speichern oder bereinigen
        $leadMin = ($this->out_of_stock_lead_days_min !== '' && $this->out_of_stock_lead_days_min !== null && (int) $this->out_of_stock_lead_days_min > 0)
            ? max(1, (int) $this->out_of_stock_lead_days_min)
            : null;
        $leadMax = ($this->out_of_stock_lead_days_max !== '' && $this->out_of_stock_lead_days_max !== null && (int) $this->out_of_stock_lead_days_max > 0)
            ? max(1, (int) $this->out_of_stock_lead_days_max)
            : null;
        if ($leadMin !== null || $leadMax !== null) {
            $resolvedMin = $leadMin ?? max(1, $leadMax - 2);
            $resolvedMax = ($leadMax !== null && $leadMax >= $resolvedMin) ? $leadMax : ($resolvedMin + 2);
            $this->configSettings['out_of_stock_lead_days_min'] = $resolvedMin;
            $this->configSettings['out_of_stock_lead_days_max'] = $resolvedMax;
            $this->out_of_stock_lead_days_min = (string) $resolvedMin;
            $this->out_of_stock_lead_days_max = (string) $resolvedMax;
        } else {
            unset($this->configSettings['out_of_stock_lead_days_min'], $this->configSettings['out_of_stock_lead_days_max']);
        }

        $this->product->configurator_settings = $this->configSettings;
        if ($this->type === 'physical') {
            $this->configSettings['addons'] = $this->product->getEffectiveAddons(false);
        } else {
            unset($this->configSettings['addons']);
        }
        $this->product->configurator_settings = $this->configSettings;

        // 9. Fortschritt aktualisieren
        if($this->currentStep > $this->product->completion_step && $this->canProceed()) {
            // Sicherstellen, dass completion_step nicht höher als erlaubt ist
            $this->product->completion_step = min($this->currentStep, $this->totalSteps);
        }

        $this->product->save();

        // Automatische LUCID-Verpackungsanlage (/admin/product-packaging), falls physisches Produkt noch keine Einträge hat
        if ($this->type === 'physical') {
            if ($this->product->packagings()->count() === 0) {
                $this->syncDefaultProductPackaging($this->product);
            }
        } else {
            $this->product->packagings()->delete();
        }

        \App\Models\Marketing\MarketingLandingPage::syncForProduct($this->product);

        if($notify) session()->flash('success', 'Produkt gespeichert.');
    }

    // --- MEDIA HANDLING ---

    public function updatedNewMedia()
    {
        $this->validate(['new_media.*' => 'image|max:10240']);
        foreach($this->new_media as $file) {
            if ($file->getSize() > 3 * 1024 * 1024) { $this->addError('new_media', 'Datei zu groß.'); return; }
        }

        $folder = 'produkte/products/' . ($this->product->slug ?? 'draft') . '/medien';
        $gallery = $this->product->media_gallery ?? [];

        foreach ($this->new_media as $file) {
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.jpg';
            $fullPath = $folder . '/' . $filename;
            $this->resizeAndSaveImage($file->getRealPath(), $fullPath);
            $gallery[] = [
                'type' => 'image',
                'path' => $fullPath,
                'is_main' => count($gallery) === 0,
                'is_hover' => false,
                'is_ai_generated' => true,
            ];
        }

        $this->product->media_gallery = $gallery;
        $this->product->save();
        $this->new_media = [];
        session()->flash('success', 'Bilder hochgeladen!');
    }

    public function updatedNewVideo()
    {
        $this->validate(['new_video' => 'file|mimes:mp4,mov,qt|max:51200']);
        $folder = 'produkte/products/' . ($this->product->slug ?? 'draft') . '/medien';
        $originalName = basename((string) $this->new_video->getClientOriginalName());
        $ext = strtolower((string) $this->new_video->getClientOriginalExtension());
        $safeExt = in_array($ext, ['mp4', 'mov'], true) ? $ext : 'mp4';
        $filename = time() . '_vid_' . (Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'video') . '.' . $safeExt;
        $path = $this->new_video->storeAs($folder, $filename, 'public');
        $gallery = $this->product->media_gallery ?? [];
        $gallery[] = [
            'type' => 'video',
            'path' => $path,
            'is_main' => count($gallery) === 0,
            'is_hover' => false,
            'is_ai_generated' => false,
        ];
        $this->product->media_gallery = $gallery;
        $this->product->save();
        $this->new_video = null;
        session()->flash('success', 'Video hochgeladen!');
    }

    public function setMainImage($index)
    {
        $gallery = is_array($this->product->media_gallery) ? $this->product->media_gallery : [];
        if (isset($gallery[$index]) && is_array($gallery[$index])) {
            $item = $gallery[$index];
            unset($gallery[$index]);
            foreach ($gallery as $k => $g) {
                if (is_array($g)) {
                    $gallery[$k]['is_main'] = false;
                }
            }
            $item['is_main'] = true;
            $item['is_hover'] = false;
            array_unshift($gallery, $item);
            $this->product->media_gallery = array_values($gallery);
            $this->product->save();
            $this->product->refresh();
        }
    }

    public function setMainMedia($index)
    {
        $this->setMainImage($index);
    }

    public function setHoverImage($index)
    {
        $gallery = is_array($this->product->media_gallery) ? $this->product->media_gallery : [];
        if (isset($gallery[$index]) && is_array($gallery[$index])) {
            $currentlyHover = !empty($gallery[$index]['is_hover']);
            foreach ($gallery as $k => $g) {
                if (is_array($g)) {
                    $gallery[$k]['is_hover'] = false;
                }
            }
            if (!$currentlyHover) {
                $gallery[$index]['is_hover'] = true;
            }
            $this->product->media_gallery = array_values($gallery);
            $this->product->save();
            $this->product->refresh();
        }
    }

    public function setHoverMedia($index)
    {
        $this->setHoverImage($index);
    }

    public function setBeginnerImage($index)
    {
        $gallery = is_array($this->product->media_gallery) ? $this->product->media_gallery : [];
        if (isset($gallery[$index]) && is_array($gallery[$index])) {
            $currentlyBeginner = !empty($gallery[$index]['is_beginner']);
            foreach ($gallery as $k => $g) {
                if (is_array($g)) {
                    $gallery[$k]['is_beginner'] = false;
                }
            }
            if (!$currentlyBeginner) {
                $gallery[$index]['is_beginner'] = true;
            }
            $this->product->media_gallery = array_values($gallery);
            $this->product->save();
            $this->product->refresh();
        }
    }

    public function setBeginnerMedia($index)
    {
        $this->setBeginnerImage($index);
    }

    private function resizeAndSaveImage($sourcePath, $destinationPath)
    {
        list($width, $height, $type) = getimagesize($sourcePath);
        $maxWidth = 1920; $maxHeight = 1920;
        $ratio = $width / $height;
        if ($width > $maxWidth || $height > $maxHeight) {
            if ($maxWidth / $maxHeight > $ratio) { $newWidth = $maxHeight * $ratio; $newHeight = $maxHeight; } else { $newHeight = $maxWidth / $ratio; $newWidth = $maxWidth; }
        } else { $newWidth = $width; $newHeight = $height; }

        switch ($type) {
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($sourcePath); break;
            case IMAGETYPE_PNG: $src = imagecreatefrompng($sourcePath); break;
            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($sourcePath); break;
            default: return;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start(); imagejpeg($dst, null, 85); $imageData = ob_get_clean();
        Storage::disk('public')->put($destinationPath, $imageData);
        imagedestroy($src); imagedestroy($dst);
    }

    public function removeMedia($index)
    {
        $gallery = $this->product->media_gallery;
        if(isset($gallery[$index])) Storage::disk('public')->delete($gallery[$index]['path']);
        unset($gallery[$index]);
        $this->product->media_gallery = array_values($gallery);
        $this->product->save();
    }

    // --- NEU: 3D MODEL & VORSCHAU ---

    public function updatedNewPreviewImage()
    {
        $this->validate(['new_preview_image' => 'image|mimes:jpg,jpeg,png,webp|max:10240']);
        $folder = 'produkte/products/' . ($this->product->slug ?? 'draft') . '/configurator';

        if ($this->product->preview_image_path) {
            Storage::disk('public')->delete($this->product->preview_image_path);
        }

        // Originalen Dateinamen verwenden (mit strikter Extension-Whitelist auf public Disk)
        $originalName = basename((string) $this->new_preview_image->getClientOriginalName());
        $ext = strtolower((string) $this->new_preview_image->getClientOriginalExtension());
        $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
        $filename = time() . '_2d_' . (Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'preview') . '.' . $safeExt;

        $path = $this->new_preview_image->storeAs($folder, $filename, 'public');
        $this->product->preview_image_path = $path;
        $this->product->save();
        $this->product->refresh(); // Erzwingt das sofortige Neuladen der Variable im HTML
        $this->new_preview_image = null;

        $this->dispatch('2d-preview-updated', url: asset('storage/' . $path));
        session()->flash('success', '2D Vorschau erfolgreich hochgeladen!');
    }

    public function removePreviewImage()
    {
        if($this->product->preview_image_path) {
            if (str_contains($this->product->preview_image_path, '/configurator/')) {
                Storage::disk('public')->delete($this->product->preview_image_path);
            }
            $this->product->preview_image_path = null;
            $this->configSettings['template_package'] = 'custom';
            $this->product->configurator_settings = $this->configSettings;
            $this->product->save();
            $this->product->refresh();

            $this->dispatch('2d-preview-updated', url: '');
            session()->flash('success', '2D Vorschau entfernt.');
        }
    }

    public function updatedNew3dModel()
    {
        // Max 100MB & strikte .glb Dateiendungs- & Magic-Byte-Prüfung
        $this->validate(['new_3d_model' => 'file|max:102400']);
        $ext = strtolower((string) $this->new_3d_model->getClientOriginalExtension());
        $realPath = $this->new_3d_model->getRealPath();
        $headerBytes = ($realPath && is_file($realPath)) ? @file_get_contents($realPath, false, null, 0, 4) : '';
        if ($ext !== 'glb' || $headerBytes !== 'glTF') {
            $this->addError('new_3d_model', 'Nur gültige binäre .glb 3D-Modelldateien (glTF 2.0) sind erlaubt.');
            $this->new_3d_model = null;
            return;
        }

        $folder = 'produkte/products/' . ($this->product->slug ?? 'draft') . '/configurator';

        // Lösche altes Modell, falls vorhanden
        if ($this->product->three_d_model_path) {
            Storage::disk('public')->delete($this->product->three_d_model_path);
        }

        // Originalen Dateinamen verwenden
        $originalName = $this->new_3d_model->getClientOriginalName();
        $filename = time() . '_3d_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.glb';

        $path = $this->new_3d_model->storeAs($folder, $filename, 'public');

        $optNotice = '';
        if ($this->autoOptimize3dOnUpload) {
            $absPath = Storage::disk('public')->path($path);
            $optimizer = app(\App\Services\Storage\GlbOptimizerService::class);
            $stats = $optimizer->inspectGlb($absPath);
            if (!empty($stats['needs_optimization'])) {
                $optResult = $optimizer->optimizeGlb($absPath);
                if (!empty($optResult['success'])) {
                    $optNotice = " (Automatisch 3D-optimiert: {$optResult['original_size_mb']} MB → {$optResult['new_size_mb']} MB, -{$optResult['saved_percent']}%)";
                }
            }
        }

        $this->product->three_d_model_path = $path;
        $this->product->save();
        $this->product->refresh(); // Erzwingt das sofortige Neuladen der Variable im HTML
        $this->new_3d_model = null;

        $this->dispatch('3d-model-updated', url: asset('storage/' . $path) . '?v=' . time());
        session()->flash('success', '3D-Modell erfolgreich hochgeladen!' . $optNotice);
    }

    public function optimizeCurrent3dModel()
    {
        if (!$this->product->three_d_model_path || !Storage::disk('public')->exists($this->product->three_d_model_path)) {
            session()->flash('error', 'Kein 3D-Modell zum Optimieren gefunden.');
            return;
        }

        $absPath = Storage::disk('public')->path($this->product->three_d_model_path);
        $optimizer = app(\App\Services\Storage\GlbOptimizerService::class);
        $result = $optimizer->optimizeGlb($absPath);

        if (!empty($result['success'])) {
            $this->product->refresh();
            $this->dispatch('3d-model-updated', url: asset('storage/' . $this->product->three_d_model_path) . '?v=' . time());
            session()->flash(
                'success',
                "3D-Modell erfolgreich optimiert: {$result['original_size_mb']} MB → {$result['new_size_mb']} MB (-{$result['saved_percent']}% | " .
                number_format((int) ($result['new_triangles'] ?? 0), 0, ',', '.') . " Polygone)."
            );
        } else {
            session()->flash('error', '3D-Optimierung fehlgeschlagen: ' . ($result['error'] ?? 'Unbekannter Fehler'));
        }
    }

    public function remove3dModel()
    {
        if($this->product->three_d_model_path) {
            Storage::disk('public')->delete($this->product->three_d_model_path);
            $this->product->three_d_model_path = null;
            $this->product->save();
            $this->product->refresh();

            $this->dispatch('3d-model-updated', url: '');
            session()->flash('success', '3D-Modell entfernt.');
        }
    }

    // NEU: Hintergrund Upload Logik (unterstützt Bilder .jpg/.png/.webp UND Videos .mp4/.webm inkl. automatischem Poster-Frame für 3D-Reflexionen)
    public function updatedNew3dBackground()
    {
        $this->validate(['new_3d_background' => 'required|file|mimes:jpg,jpeg,png,webp,mp4,webm|max:20480']);
        $folder = 'produkte/products/' . ($this->product->slug ?? 'draft') . '/configurator';

        $originalName = basename((string) $this->new_3d_background->getClientOriginalName());
        $ext = strtolower((string) $this->new_3d_background->getClientOriginalExtension());
        $isVideo = in_array($ext, ['mp4', 'webm'], true);

        if ($isVideo) {
            if (!empty($this->configSettings['background_video_path']) && str_contains($this->configSettings['background_video_path'], '/configurator/')) {
                Storage::disk('public')->delete($this->configSettings['background_video_path']);
            }

            $safeExt = $ext;
            $baseSlug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'bgvid';
            $filename = time() . '_bgvid_' . $baseSlug . '.' . $safeExt;
            $videoPath = $this->new_3d_background->storeAs($folder, $filename, 'public');

            $this->configSettings['background_video_path'] = $videoPath;
            $this->configSettings['enable_background_video'] = true;

            // Automatisch 1. Frame als hochauflösendes .jpg Poster extrahieren, damit WebGL Environment-Reflexionen & Snapshots ohne Nachteil funktionieren
            $absVideoPath = Storage::disk('public')->path($videoPath);
            $posterRelPath = $folder . '/' . time() . '_bg_' . $baseSlug . '_poster.jpg';
            $absPosterPath = Storage::disk('public')->path($posterRelPath);

            if (is_executable('/usr/bin/ffmpeg')) {
                $cmd = sprintf(
                    '/usr/bin/ffmpeg -y -loglevel error -i %s -frames:v 1 -q:v 3 %s 2>&1',
                    escapeshellarg($absVideoPath),
                    escapeshellarg($absPosterPath)
                );
                @exec($cmd);
            }

            if (is_file($absPosterPath) && filesize($absPosterPath) > 0) {
                if ($this->product->three_d_background_path && str_contains($this->product->three_d_background_path, '/configurator/')) {
                    Storage::disk('public')->delete($this->product->three_d_background_path);
                }
                $this->product->three_d_background_path = $posterRelPath;
            }

            $this->product->configurator_settings = $this->configSettings;
            $this->product->save();
            $this->product->refresh();
            $this->new_3d_background = null;

            $videoUrl = asset('storage/' . $videoPath);
            $bgUrl = $this->product->three_d_background_path ? asset('storage/' . $this->product->three_d_background_path) : '';
            $this->dispatch('3d-bg-updated', url: $bgUrl);
            $this->dispatch('3d-theme-video-updated', videoUrl: $videoUrl, imageUrl: $bgUrl, theme: 'custom');
            session()->flash('success', '3D Hintergrund-Video (inkl. WebGL-Reflexionsbild) erfolgreich hochgeladen!');
            return;
        }

        if ($this->product->three_d_background_path && str_contains($this->product->three_d_background_path, '/configurator/')) {
            Storage::disk('public')->delete($this->product->three_d_background_path);
        }

        $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
        $filename = time() . '_bg_' . (Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'bg') . '.' . $safeExt;

        $path = $this->new_3d_background->storeAs($folder, $filename, 'public');
        $this->product->three_d_background_path = $path;
        $this->product->save();
        $this->product->refresh(); // Erzwingt das sofortige Neuladen der Variable im HTML
        $this->new_3d_background = null;

        $this->dispatch('3d-bg-updated', url: asset('storage/' . $path));
        session()->flash('success', '3D Hintergrund erfolgreich hochgeladen!');
    }

    public function remove3dBackground()
    {
        if($this->product->three_d_background_path) {
            if (str_contains($this->product->three_d_background_path, '/configurator/')) {
                Storage::disk('public')->delete($this->product->three_d_background_path);
            }
            $this->product->three_d_background_path = null;
            $this->configSettings['template_package'] = 'custom';
            $this->product->configurator_settings = $this->configSettings;
            $this->product->save();
            $this->product->refresh();

            $this->dispatch('3d-bg-updated', url: '');
            session()->flash('success', 'Hintergrund entfernt.');
        }
    }

    /**
     * Weist dem Produkt ein Vorlage-Paket zu und setzt automatisch das passende
     * "2D Vorschau Overlay (PNG)", "3D Hintergrund (.jpg/.png)" sowie die Themen-Videos.
     */
    public function applyTemplatePackage(string $packageKey)
    {
        $configService = app(ConfiguratorService::class);
        $packages = $configService->getTemplatePackages();

        if (!isset($packages[$packageKey])) {
            return;
        }

        $pkg = $packages[$packageKey];
        $this->configSettings['template_package'] = $packageKey;

        if ($packageKey !== 'custom') {
            if (!empty($pkg['preview_image_path'])) {
                $this->product->preview_image_path = $pkg['preview_image_path'];
            }
            if (!empty($pkg['three_d_background_path'])) {
                $this->product->three_d_background_path = $pkg['three_d_background_path'];
            }
            $this->configSettings['enable_background_video'] = true;
            $this->configSettings['default_background_theme'] = $pkg['default_background_theme'] ?? 'standard';
        }

        $this->product->configurator_settings = $this->configSettings;
        $this->product->save();
        $this->product->refresh();

        $previewUrl = $this->product->preview_image_path ? asset('storage/' . $this->product->preview_image_path) : '';
        $bgUrl = $this->product->three_d_background_path ? asset('storage/' . $this->product->three_d_background_path) : '';

        $themes = $configService->getBackgroundThemes();
        $activeThemeKey = $this->configSettings['default_background_theme'] ?? 'standard';
        $activeTheme = $themes[$activeThemeKey] ?? $themes['standard'];
        $videoUrl = (!empty($this->configSettings['enable_background_video']) && !empty($activeTheme['video']))
            ? asset('storage/' . $activeTheme['video'])
            : '';

        $this->dispatch('2d-preview-updated', url: $previewUrl);
        $this->dispatch('3d-bg-updated', url: $bgUrl);
        $this->dispatch('3d-theme-video-updated', videoUrl: $videoUrl, imageUrl: $bgUrl, theme: $activeThemeKey);

        session()->flash('success', "Vorlage-Paket „{$pkg['name']}“ erfolgreich zugewiesen!");
    }

    /**
     * Wechselt das aktive Standard-Hintergrundthema (inkl. komprimiertem Themen-Video) in der Vorschau & Produkt-Konfiguration.
     */
    public function selectBackgroundTheme(string $themeKey)
    {
        $configService = app(ConfiguratorService::class);
        $themes = $configService->getBackgroundThemes();

        if (!isset($themes[$themeKey])) {
            return;
        }

        $this->configSettings['default_background_theme'] = $themeKey;
        $this->product->configurator_settings = $this->configSettings;
        $this->product->save();

        $activeTheme = $themes[$themeKey];
        $videoUrl = (!empty($this->configSettings['enable_background_video']) && !empty($activeTheme['video']))
            ? asset('storage/' . $activeTheme['video'])
            : '';
        $imageUrl = !empty($activeTheme['image'])
            ? asset('storage/' . $activeTheme['image'])
            : ($this->product->three_d_background_path ? asset('storage/' . $this->product->three_d_background_path) : '');

        if ($this->product->preview_image_path) {
            $themedOverlayRel = dirname($this->product->preview_image_path) . '/overlay_' . $themeKey . '.png';
            if (Storage::disk('public')->exists($themedOverlayRel)) {
                $this->dispatch('2d-preview-updated', url: asset('storage/' . $themedOverlayRel) . '?v=' . time());
            } else {
                $this->dispatch('2d-preview-updated', url: asset('storage/' . $this->product->preview_image_path));
            }
        }

        $this->dispatch('3d-theme-video-updated', videoUrl: $videoUrl, imageUrl: $imageUrl, theme: $themeKey);
    }

    public function updatedNewBackgroundVideo()
    {
        $this->validate([
            'new_background_video' => 'required|file|mimes:mp4,webm|max:20480',
        ]);

        $folder = 'produkte/products/' . ($this->product->slug ?? 'draft') . '/configurator';

        if (!empty($this->configSettings['background_video_path']) && str_contains($this->configSettings['background_video_path'], '/configurator/')) {
            Storage::disk('public')->delete($this->configSettings['background_video_path']);
        }

        $originalName = basename((string) $this->new_background_video->getClientOriginalName());
        $ext = strtolower((string) $this->new_background_video->getClientOriginalExtension());
        $safeExt = in_array($ext, ['mp4', 'webm'], true) ? $ext : 'mp4';
        $filename = time() . '_bgvid_' . (Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'bgvid') . '.' . $safeExt;

        $path = $this->new_background_video->storeAs($folder, $filename, 'public');

        $this->configSettings['background_video_path'] = $path;
        $this->configSettings['enable_background_video'] = true;
        $this->product->configurator_settings = $this->configSettings;
        $this->product->save();
        $this->product->refresh();
        $this->new_background_video = null;

        $videoUrl = asset('storage/' . $path);
        $bgUrl = $this->product->three_d_background_path ? asset('storage/' . $this->product->three_d_background_path) : '';
        $this->dispatch('3d-theme-video-updated', videoUrl: $videoUrl, imageUrl: $bgUrl, theme: 'custom');
        session()->flash('success', 'Hintergrund-Video erfolgreich hochgeladen!');
    }

    public function removeBackgroundVideo()
    {
        if (!empty($this->configSettings['background_video_path'])) {
            if (str_contains($this->configSettings['background_video_path'], '/configurator/')) {
                Storage::disk('public')->delete($this->configSettings['background_video_path']);
            }
            $this->configSettings['background_video_path'] = null;
            $this->product->configurator_settings = $this->configSettings;
            $this->product->save();
            $this->product->refresh();

            $this->selectBackgroundTheme($this->configSettings['default_background_theme'] ?? 'standard');
            session()->flash('success', 'Eigenes Hintergrund-Video entfernt (Themen-Videos aktiv).');
        }
    }

    // --- QUICK ACTIONS ---

    public function updateStock($id, $newQty)
    {
        $product = Product::find($id);

        if ($product) {
            $qty = max(0, (int) $newQty);
            $product->quantity = $qty;
            $product->save();
            session()->flash('success', 'Lagerbestand aktualisiert.');
        }
    }

    public function archiveProduct($id)
    {
        $prod = Product::find($id);
        if ($prod) {
            $prod->status = 'archived';
            $prod->save();
            $prod->delete(); // Soft delete
            session()->flash('success', 'Produkt ins Archiv verschoben.');
        }
    }

    public function restoreProduct($id)
    {
        $prod = Product::withTrashed()->find($id);
        if ($prod) {
            $prod->restore();
            $prod->status = 'active';
            $prod->save();
            session()->flash('success', 'Produkt aus dem Archiv als aktiv wiederhergestellt.');
        }
    }

    public function toggleArchiveMode()
    {
        $this->showArchived = !$this->showArchived;
        $this->search = ''; // optionally clear search
    }

    public function toggleAutoSeasonalMainImage(): void
    {
        $current = filter_var($this->configSettings['auto_seasonal_main_image'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $next = !$current;
        $this->configSettings['auto_seasonal_main_image'] = $next;

        if (isset($this->product) && $this->product->exists) {
            $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
            $settings['auto_seasonal_main_image'] = $next;
            $this->product->configurator_settings = $settings;
            $this->product->save();
            $this->product->refresh();
        }
    }

    public function updatedSeasonalMediaUploads($file, $holidayKey): void
    {
        if (!isset($this->product) || !$this->product->exists || !$file) {
            return;
        }

        $holidayKey = trim((string) $holidayKey);
        if (!array_key_exists($holidayKey, Product::SEASONAL_HOLIDAY_SLOTS)) {
            return;
        }

        $this->validate([
            "seasonal_media_uploads.{$holidayKey}" => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $slug = $this->product->slug ?: 'draft';
        $holidaySlug = Str::slug($holidayKey);
        $folder = "produkte/products/{$slug}/seasonal";
        $filename = time() . "_{$slug}-{$holidaySlug}.jpg";
        $fullPath = "{$folder}/{$filename}";

        $this->resizeAndSaveImage($file->getRealPath(), $fullPath);

        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        if (!isset($settings['seasonal_images']) || !is_array($settings['seasonal_images'])) {
            $settings['seasonal_images'] = [];
        }

        // Vorherige eigene Datei aufräumen, falls sie im seasonal-Ordner lag
        $prevPath = $settings['seasonal_images'][$holidayKey]['path'] ?? null;
        if (is_string($prevPath) && str_contains($prevPath, '/seasonal/')) {
            Storage::disk('public')->delete($prevPath);
        }

        $settings['seasonal_images'][$holidayKey] = [
            'path' => $fullPath,
            'is_ai_generated' => true,
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->configSettings['seasonal_images'] = $settings['seasonal_images'];
        $this->product->configurator_settings = $settings;
        $this->product->save();

        // Synchronisiere auch das Vorschaubild einer ggf. vorhandenen Produktvorlage für diesen Anlass
        \App\Models\Product\ProductTemplate::where('product_id', $this->product->id)
            ->whereRaw('LOWER(holiday) = ?', [mb_strtolower($holidayKey)])
            ->update([
                'preview_image' => $fullPath,
                'is_ai_generated' => true,
            ]);

        $this->product->refresh();
        unset($this->seasonal_media_uploads[$holidayKey]);
        session()->flash('success', "Saison-Hauptbild für „{$holidayKey}“ gespeichert!");
    }

    public function toggleSeasonalImageAiGenerated(string $holidayKey): void
    {
        if (!isset($this->product) || !$this->product->exists || !array_key_exists($holidayKey, Product::SEASONAL_HOLIDAY_SLOTS)) {
            return;
        }

        $overview = $this->product->getSeasonalMainImagesOverview();
        $slot = $overview[$holidayKey] ?? null;
        if (!$slot || empty($slot['path'])) {
            return;
        }

        $nextAi = !$slot['is_ai_generated'];
        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        if (!isset($settings['seasonal_images']) || !is_array($settings['seasonal_images'])) {
            $settings['seasonal_images'] = [];
        }

        $settings['seasonal_images'][$holidayKey] = [
            'path' => $slot['path'],
            'is_ai_generated' => $nextAi,
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->configSettings['seasonal_images'] = $settings['seasonal_images'];
        $this->product->configurator_settings = $settings;
        $this->product->save();

        \App\Models\Product\ProductTemplate::where('product_id', $this->product->id)
            ->whereRaw('LOWER(holiday) = ?', [mb_strtolower($holidayKey)])
            ->update(['is_ai_generated' => $nextAi]);

        $this->product->refresh();
    }

    public function removeSeasonalMainImage(string $holidayKey): void
    {
        if (!isset($this->product) || !$this->product->exists || !array_key_exists($holidayKey, Product::SEASONAL_HOLIDAY_SLOTS)) {
            return;
        }

        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        $prevPath = $settings['seasonal_images'][$holidayKey]['path'] ?? null;
        if (is_string($prevPath) && str_contains($prevPath, '/seasonal/')) {
            Storage::disk('public')->delete($prevPath);
        }

        unset($settings['seasonal_images'][$holidayKey]);
        $this->configSettings['seasonal_images'] = $settings['seasonal_images'] ?? [];
        $this->product->configurator_settings = $settings;
        $this->product->save();
        $this->product->refresh();

        session()->flash('success', "Eigenes Saisonbild für „{$holidayKey}“ zurückgesetzt.");
    }

    public function toggleMediaAiGenerated(int $index): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $gallery = $this->product->media_gallery ?? [];
        if (isset($gallery[$index]) && is_array($gallery[$index])) {
            $current = filter_var($gallery[$index]['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $gallery[$index]['is_ai_generated'] = !$current;
            $this->product->media_gallery = $gallery;
            $this->product->save();
        }
    }

    public function updateMediaVariantBinding(int $index, string $binding): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $binding = trim($binding);
        if ($binding !== '' && !str_starts_with($binding, 'attr:') && !str_starts_with($binding, 'var:')) {
            return;
        }
        $binding = mb_substr($binding, 0, 160);

        $gallery = $this->product->media_gallery ?? [];
        if (isset($gallery[$index]) && is_array($gallery[$index])) {
            if ($binding === '') {
                unset($gallery[$index]['variant_binding']);
            } else {
                $gallery[$index]['variant_binding'] = $binding;
            }
            $this->product->media_gallery = $gallery;
            $this->product->save();
            $this->dispatch('product-updated');
        }
    }

    public function render()
    {
        $query = Product::with(['templates', 'supplier', 'packagings.packagingItem', 'categories'])
            ->withCount([
                'reviews as approved_reviews_count' => function ($q) {
                    $q->where('status', 'approved');
                },
                'reviews as pending_reviews_count' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->withAvg([
                'reviews as approved_reviews_avg' => function ($q) {
                    $q->where('status', 'approved');
                },
            ], 'rating')
            ->withSum([
                'orderItems as total_sold_qty' => function ($q) {
                    $q->whereHas('order', function ($oq) {
                        $oq->whereNotIn('status', ['cancelled', 'refunded']);
                    });
                },
            ], 'quantity')
            ->withSum([
                'orderItems as total_revenue_cents' => function ($q) {
                    $q->whereHas('order', function ($oq) {
                        $oq->whereNotIn('status', ['cancelled', 'refunded']);
                    });
                },
            ], 'total_price');

        if ($this->showArchived) {
            $query->onlyTrashed();
        }

        if ($this->search) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('name', 'like', '%' . $escapedSearch . '%')
                  ->orWhere('sku', 'like', '%' . $escapedSearch . '%');
            });
        }

        // Schnellfilter-Optionen
        if ($this->sortBy === 'filter_active') {
            $query->where('status', 'active');
        } elseif ($this->sortBy === 'filter_draft') {
            $query->where('status', 'draft');
        } elseif ($this->sortBy === 'filter_low_stock') {
            $lowThreshold = (int) shop_setting('inventory_low_stock_threshold', 5);
            $query->where('track_quantity', true)->where('quantity', '<', $lowThreshold);
        } elseif ($this->sortBy === 'filter_physical') {
            $query->where('type', 'physical');
        } elseif ($this->sortBy === 'filter_manufaktur') {
            $query->where('type', 'physical')->where('collection_line', 'manufaktur');
        } elseif ($this->sortBy === 'filter_atelier') {
            $query->where('type', 'physical')->where('collection_line', 'atelier');
        } elseif ($this->sortBy === 'filter_digital') {
            $query->where('type', 'digital');
        }

        $products = ($this->viewMode === 'list') ? $query->latest()->get() : collect();

        if ($this->viewMode === 'list' && $products->isNotEmpty()) {
            if ($this->sortBy === 'filter_incomplete') {
                $products = $products->filter(fn ($p) => !($p->getReadinessReport()['is_ready'] ?? true))->values();
            }

            $products = match ($this->sortBy) {
                'bestseller' => $products->sortByDesc(fn ($p) => [
                    (int) ($p->total_sold_qty ?? 0),
                    (int) ($p->total_revenue_cents ?? 0),
                    $p->created_at?->timestamp ?? 0,
                ])->values(),
                'least_sold' => $products->sortBy(fn ($p) => [
                    (int) ($p->total_sold_qty ?? 0),
                    (int) ($p->total_revenue_cents ?? 0),
                ])->values(),
                'revenue_desc' => $products->sortByDesc(fn ($p) => [
                    (int) ($p->total_revenue_cents ?? 0),
                    (int) ($p->total_sold_qty ?? 0),
                ])->values(),
                'rating_desc' => $products->sortByDesc(fn ($p) => [
                    (float) ($p->approved_reviews_avg ?? 0),
                    (int) ($p->approved_reviews_count ?? 0),
                ])->values(),
                'reviews_desc' => $products->sortByDesc(fn ($p) => [
                    (int) ($p->approved_reviews_count ?? 0),
                    (float) ($p->approved_reviews_avg ?? 0),
                ])->values(),
                'stock_asc' => $products->sortBy(fn ($p) => [
                    $p->track_quantity ? 0 : 1,
                    (int) $p->quantity,
                ])->values(),
                'stock_desc' => $products->sortByDesc(fn ($p) => [
                    $p->track_quantity ? 1 : 0,
                    (int) $p->quantity,
                ])->values(),
                'price_desc' => $products->sortByDesc(fn ($p) => (int) $p->price)->values(),
                'price_asc' => $products->sortBy(fn ($p) => (int) $p->price)->values(),
                'name_asc' => $products->sortBy(fn ($p) => mb_strtolower((string) $p->name), SORT_NATURAL)->values(),
                default => $products,
            };
        }

        $suiteCounts = [
            'products'  => Product::count(),
            'packaging' => \App\Models\Product\ProductPackagingItem::where('is_active', true)->count(),
            'suppliers' => \App\Models\Product\ProductSupplier::count(),
            'templates' => \App\Models\Product\ProductTemplate::where('is_active', true)->count(),
            'ideas'     => \Illuminate\Support\Facades\Schema::hasTable('product_ideas')
                ? \App\Models\Product\ProductIdea::count()
                : 2,
        ];

        return view('livewire.shop.product.product-create', [
            'products' => $products,
            'canProceed' => $this->canProceed(),
            'suiteCounts' => $suiteCounts,
        ]);
    }

    // --- DYNAMISCHE SCHNELL-PERSONALISIERUNGS-FELDER (MODUS B: custom_fields) ---

    protected function persistCustomFieldsToProduct(): void
    {
        if (!isset($this->product) || !$this->product?->exists) {
            return;
        }

        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        $pMode = strtolower(trim((string) ($this->configSettings['personalization_mode'] ?? 'studio')));
        $settings['personalization_mode'] = in_array($pMode, ['studio', 'custom_fields'], true) ? $pMode : 'studio';

        $rawCustomFields = isset($this->configSettings['custom_fields']) && is_array($this->configSettings['custom_fields'])
            ? array_values($this->configSettings['custom_fields'])
            : [];
        $normalizedForDb = [];
        foreach ($rawCustomFields as $cfIdx => $cfItem) {
            if (!is_array($cfItem)) {
                continue;
            }
            $dbCandidate = $cfItem;
            if (trim((string) ($dbCandidate['label'] ?? '')) === '') {
                $dbCandidate['label'] = ($cfIdx + 1) . '. Eingabefeld';
            }
            $normCf = Product::normalizeCustomFieldItem($dbCandidate, $cfIdx);
            if ($normCf !== null) {
                $normalizedForDb[] = $normCf;
            }
        }

        $settings['custom_fields'] = $normalizedForDb;
        $this->product->configurator_settings = $settings;
        $this->product->saveQuietly();
    }

    public function updatedConfigSettings($value, $key = null): void
    {
        $keyStr = (string) $key;
        if (preg_match('/^custom_fields\.(\d+)\.type$/', $keyStr, $m)) {
            $idx = (int) $m[1];
            if (isset($this->configSettings['custom_fields'][$idx]) && is_array($this->configSettings['custom_fields'][$idx])) {
                $allFonts = array_keys(app(ConfiguratorService::class)->getFonts());
                $currentLabel = trim((string) ($this->configSettings['custom_fields'][$idx]['label'] ?? ''));
                $currentInfo = trim((string) ($this->configSettings['custom_fields'][$idx]['info_text'] ?? ''));
                $isGenericLabel = $currentLabel === ''
                    || str_contains($currentLabel, 'Eingabefeld')
                    || in_array($currentLabel, ['Wunschname / Gravurtext', '1. Wunschname / Gravurtext', 'Schriftart wählen', '2. Schriftart wählen', 'Wunschdatum', 'Auswahl / Symbol'], true);
                $isGenericInfo = $currentInfo === ''
                    || str_contains($currentInfo, 'Gib hier deinen gewünschten Text')
                    || str_contains($currentInfo, 'Gib hier den gewünschten Namen')
                    || str_contains($currentInfo, 'Wähle hier dein gewünschtes Datum')
                    || str_contains($currentInfo, 'Wähle hier deine gewünschte Option');

                if ($value === 'font') {
                    if ($isGenericLabel) {
                        $this->configSettings['custom_fields'][$idx]['label'] = 'Schriftart wählen';
                    }
                    // Für Schriftart-Auswahl machen weder „!“-Hinweistext noch Pflichtfeld-Stern oder Aufpreis Sinn (Standard: Arial)
                    $this->configSettings['custom_fields'][$idx]['info_text'] = '';
                    $this->configSettings['custom_fields'][$idx]['required'] = false;
                    $this->configSettings['custom_fields'][$idx]['placeholder'] = '';
                    $this->configSettings['custom_fields'][$idx]['surcharge_price'] = '0.00';
                    $this->configSettings['custom_fields'][$idx]['surcharge_cents'] = 0;
                    $norm = Product::normalizeCustomFieldItem($this->configSettings['custom_fields'][$idx], $idx);
                    $this->configSettings['custom_fields'][$idx]['options'] = $norm['options'] ?? $allFonts;
                    $this->configSettings['custom_fields'][$idx]['options_raw'] = implode(', ', $this->configSettings['custom_fields'][$idx]['options']);
                    $this->configSettings['custom_fields'][$idx]['max_length'] = 60;
                } elseif ($value === 'date') {
                    if ($isGenericLabel) {
                        $this->configSettings['custom_fields'][$idx]['label'] = 'Wunschdatum';
                    }
                    if ($isGenericInfo) {
                        $this->configSettings['custom_fields'][$idx]['info_text'] = 'Wähle hier dein gewünschtes Datum für die Gravur aus.';
                    }
                    $this->configSettings['custom_fields'][$idx]['placeholder'] = '';
                    $this->configSettings['custom_fields'][$idx]['max_length'] = 20;
                } elseif ($value === 'select') {
                    if ($isGenericLabel) {
                        $this->configSettings['custom_fields'][$idx]['label'] = 'Auswahl / Symbol';
                    }
                    if ($isGenericInfo) {
                        $this->configSettings['custom_fields'][$idx]['info_text'] = 'Wähle hier deine gewünschte Option für die Gravur aus.';
                    }
                    // Falls vorher 'font' aktiv war (oder Optionen leer sind), Standard-Symbole statt der 22 Schriftarten setzen
                    $existingOpts = is_array($this->configSettings['custom_fields'][$idx]['options'] ?? null)
                        ? $this->configSettings['custom_fields'][$idx]['options']
                        : [];
                    if (empty($existingOpts) || count(array_intersect($existingOpts, $allFonts)) >= 3) {
                        $defaultSelectOpts = ['Kein Symbol', '♥ Herz', '∞ Unendlichkeit', '★ Stern', '🐾 Pfote', '⚓ Anker'];
                        $this->configSettings['custom_fields'][$idx]['options'] = $defaultSelectOpts;
                        $this->configSettings['custom_fields'][$idx]['options_raw'] = implode(', ', $defaultSelectOpts);
                    }
                    $this->configSettings['custom_fields'][$idx]['max_length'] = 120;
                    $this->configSettings['custom_fields'][$idx]['required'] = true;
                } else {
                    // 'text' oder 'textarea'
                    if ($isGenericLabel) {
                        $this->configSettings['custom_fields'][$idx]['label'] = 'Wunschname / Gravurtext';
                    }
                    if ($isGenericInfo) {
                        $this->configSettings['custom_fields'][$idx]['info_text'] = 'Gib hier deinen gewünschten Text für die Lasergravur ein.';
                    }
                    if (trim((string) ($this->configSettings['custom_fields'][$idx]['placeholder'] ?? '')) === '') {
                        $this->configSettings['custom_fields'][$idx]['placeholder'] = 'z. B. Emilia';
                    }
                    $curMax = (int) ($this->configSettings['custom_fields'][$idx]['max_length'] ?? 25);
                    if ($value === 'textarea' && $curMax <= 60) {
                        $this->configSettings['custom_fields'][$idx]['max_length'] = 150;
                    } elseif ($value === 'text' && $curMax > 60) {
                        $this->configSettings['custom_fields'][$idx]['max_length'] = 25;
                    }
                    $surchargeFloat = (float) str_replace(',', '.', (string) ($this->configSettings['custom_fields'][$idx]['surcharge_price'] ?? 0));
                    $this->configSettings['custom_fields'][$idx]['required'] = ($surchargeFloat <= 0);
                }
            }
        } elseif (preg_match('/^custom_fields\.(\d+)\.surcharge_price$/', $keyStr, $m)) {
            $idx = (int) $m[1];
            if (isset($this->configSettings['custom_fields'][$idx]) && is_array($this->configSettings['custom_fields'][$idx])) {
                $surchargeFloat = (float) str_replace(',', '.', (string) $value);
                $cfType = (string) ($this->configSettings['custom_fields'][$idx]['type'] ?? 'text');
                if ($surchargeFloat > 0 && in_array($cfType, ['text', 'textarea', 'date'], true)) {
                    $this->configSettings['custom_fields'][$idx]['required'] = false;
                }
            }
        }

        if ($key === null || str_starts_with($keyStr, 'custom_fields') || $keyStr === 'personalization_mode') {
            $this->persistCustomFieldsToProduct();
        }
    }

    public function setPersonalizationMode(string $mode): void
    {
        $cleanMode = in_array($mode, ['studio', 'custom_fields'], true) ? $mode : 'studio';
        $this->configSettings['personalization_mode'] = $cleanMode;

        if ($cleanMode === 'custom_fields' && empty($this->configSettings['custom_fields'])) {
            $this->applyCustomFieldsPreset('single_name');
            return;
        }

        $this->persistCustomFieldsToProduct();
    }

    public function addCustomField(): void
    {
        if (!isset($this->configSettings['custom_fields']) || !is_array($this->configSettings['custom_fields'])) {
            $this->configSettings['custom_fields'] = [];
        }

        $this->configSettings['custom_fields'] = array_values($this->configSettings['custom_fields']);
        $nextIndex = count($this->configSettings['custom_fields']) + 1;
        $this->configSettings['custom_fields'][] = [
            'id'              => 'cf_' . Str::lower(Str::random(6)),
            'label'           => $nextIndex . '. Eingabefeld (z. B. Wunschname)',
            'type'            => 'text',
            'placeholder'     => 'z. B. Emilia',
            'info_text'       => 'Gib hier deinen gewünschten Text für die Lasergravur ein.',
            'required'        => true,
            'max_length'      => 25,
            'options'         => [],
            'options_raw'     => '',
            'surcharge_cents' => 0,
            'surcharge_price' => '0.00',
        ];

        $this->persistCustomFieldsToProduct();
    }

    public function addCustomFontField(): void
    {
        if (!isset($this->configSettings['custom_fields']) || !is_array($this->configSettings['custom_fields'])) {
            $this->configSettings['custom_fields'] = [];
        }

        $allFonts = array_keys(app(ConfiguratorService::class)->getFonts());
        $this->configSettings['custom_fields'] = array_values($this->configSettings['custom_fields']);
        $this->configSettings['custom_fields'][] = [
            'id'              => 'cf_font_' . Str::lower(Str::random(4)),
            'label'           => 'Schriftart wählen',
            'type'            => 'font',
            'placeholder'     => '',
            'info_text'       => '',
            'required'        => false,
            'max_length'      => 60,
            'options'         => $allFonts,
            'options_raw'     => implode(', ', $allFonts),
            'surcharge_cents' => 0,
            'surcharge_price' => '0.00',
        ];

        $this->persistCustomFieldsToProduct();
    }

    public function toggleCustomFieldFont(int $index, string $fontName): void
    {
        if (!isset($this->configSettings['custom_fields'][$index]) || !is_array($this->configSettings['custom_fields'][$index])) {
            return;
        }

        $allFonts = array_keys(app(ConfiguratorService::class)->getFonts());
        if (!in_array($fontName, $allFonts, true)) {
            return;
        }

        $norm = Product::normalizeCustomFieldItem($this->configSettings['custom_fields'][$index], $index);
        $currentFonts = $norm['options'] ?? $allFonts;

        if (in_array($fontName, $currentFonts, true)) {
            if (count($currentFonts) <= 1) {
                return; // Mindestens 1 Schriftart muss aktiv bleiben
            }
            $currentFonts = array_values(array_diff($currentFonts, [$fontName]));
        } else {
            $currentFonts[] = $fontName;
            // In Original-Konfigurator-Reihenfolge sortieren
            $currentFonts = array_values(array_intersect($allFonts, $currentFonts));
        }

        $this->configSettings['custom_fields'][$index]['options'] = $currentFonts;
        $this->configSettings['custom_fields'][$index]['options_raw'] = implode(', ', $currentFonts);
        $this->persistCustomFieldsToProduct();
    }

    public function setCustomFieldFontPreset(int $index, string $preset): void
    {
        if (!isset($this->configSettings['custom_fields'][$index]) || !is_array($this->configSettings['custom_fields'][$index])) {
            return;
        }

        $allFonts = array_keys(app(ConfiguratorService::class)->getFonts());
        $selectedFonts = match ($preset) {
            'jewelry_top6' => ['Arial', 'Great Vibes', 'Dancing Script', 'Alex Brush', 'Cinzel', 'Playfair Display'],
            'clean_modern' => ['Arial', 'Montserrat', 'Cinzel', 'Playfair Display', 'Comfortaa', 'Bebas Neue', 'Georgia'],
            default => $allFonts,
        };

        $selectedFonts = array_values(array_intersect($allFonts, $selectedFonts));
        if (empty($selectedFonts)) {
            $selectedFonts = $allFonts;
        }

        $this->configSettings['custom_fields'][$index]['options'] = $selectedFonts;
        $this->configSettings['custom_fields'][$index]['options_raw'] = implode(', ', $selectedFonts);
        $this->persistCustomFieldsToProduct();
    }

    public function removeCustomField(int $index): void
    {
        if (!isset($this->configSettings['custom_fields'][$index])) {
            return;
        }

        unset($this->configSettings['custom_fields'][$index]);
        $this->configSettings['custom_fields'] = array_values($this->configSettings['custom_fields']);
        $this->persistCustomFieldsToProduct();
    }

    public function moveCustomFieldUp(int $index): void
    {
        if ($index <= 0 || !isset($this->configSettings['custom_fields'][$index])) {
            return;
        }

        $fields = array_values($this->configSettings['custom_fields']);
        $tmp = $fields[$index - 1];
        $fields[$index - 1] = $fields[$index];
        $fields[$index] = $tmp;
        $this->configSettings['custom_fields'] = $fields;
        $this->persistCustomFieldsToProduct();
    }

    public function moveCustomFieldDown(int $index): void
    {
        $fields = isset($this->configSettings['custom_fields']) && is_array($this->configSettings['custom_fields'])
            ? array_values($this->configSettings['custom_fields'])
            : [];

        if ($index < 0 || $index >= count($fields) - 1) {
            return;
        }

        $tmp = $fields[$index + 1];
        $fields[$index + 1] = $fields[$index];
        $fields[$index] = $tmp;
        $this->configSettings['custom_fields'] = $fields;
        $this->persistCustomFieldsToProduct();
    }

    public function applyCustomFieldsPreset(string $presetKey): void
    {
        if (empty($this->configSettings['personalization_mode'])) {
            $this->configSettings['personalization_mode'] = 'custom_fields';
        }

        if ($presetKey === 'symbol_and_text') {
            $allFonts = array_keys(app(ConfiguratorService::class)->getFonts());
            $this->configSettings['custom_fields'] = [
                [
                    'id'              => 'cf_wunschsymbol',
                    'label'           => '1. Wunsch-Symbol (oben mittig)',
                    'type'            => 'select',
                    'placeholder'     => 'Bitte wählen...',
                    'info_text'       => 'Wähle ein feines Motiv-Symbol, das wir wie in der Vorschau mittig über deinem Wunschtext gravieren.',
                    'required'        => true,
                    'max_length'      => 40,
                    'options'         => ['Herz ♥', 'Unendlichkeit ∞', 'Stern ★', 'Schutzengel 👼', 'Pfote 🐾', 'Lebensbaum 🌳', 'Kleeblatt 🍀', 'Kein Symbol'],
                    'options_raw'     => 'Herz ♥, Unendlichkeit ∞, Stern ★, Schutzengel 👼, Pfote 🐾, Lebensbaum 🌳, Kleeblatt 🍀, Kein Symbol',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_wunschtext',
                    'label'           => '2. Dein Wunschtext / Name',
                    'type'            => 'textarea',
                    'placeholder'     => "z. B. Für immer\nim Herzen",
                    'info_text'       => 'Gib hier deinen gewünschten Namen, ein kurzes Datum oder deine Botschaft ein (Zeilenumbruch mit Enter möglich). Wir richten alles millimetergenau mittig für dich aus.',
                    'required'        => true,
                    'max_length'      => 80,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_schriftart',
                    'label'           => '3. Schriftart wählen',
                    'type'            => 'font',
                    'placeholder'     => '',
                    'info_text'       => '',
                    'required'        => false,
                    'max_length'      => 60,
                    'options'         => $allFonts,
                    'options_raw'     => implode(', ', $allFonts),
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
            ];
        } elseif ($presetKey === 'single_name') {
            $this->configSettings['custom_fields'] = [
                [
                    'id'              => 'cf_wunschname',
                    'label'           => 'Wunschname / Gravurtext',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. Emilia',
                    'info_text'       => 'Gib hier den gewünschten Namen oder Begriff ein. Wir richten die Gravur in unserer Manufaktur millimetergenau mittig auf dem Schmuckstück aus.',
                    'required'        => true,
                    'max_length'      => 20,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
            ];
        } elseif ($presetKey === 'name_and_font') {
            $allFonts = array_keys(app(ConfiguratorService::class)->getFonts());
            $this->configSettings['custom_fields'] = [
                [
                    'id'              => 'cf_wunschname',
                    'label'           => '1. Wunschname / Gravurtext',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. Emilia',
                    'info_text'       => 'Gib hier den gewünschten Namen oder Begriff ein. Wir richten die Gravur in unserer Manufaktur millimetergenau mittig auf dem Schmuckstück aus.',
                    'required'        => true,
                    'max_length'      => 22,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_schriftart',
                    'label'           => '2. Schriftart wählen',
                    'type'            => 'font',
                    'placeholder'     => '',
                    'info_text'       => '',
                    'required'        => false,
                    'max_length'      => 60,
                    'options'         => $allFonts,
                    'options_raw'     => implode(', ', $allFonts),
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
            ];
        } elseif ($presetKey === 'name_date_symbol') {
            $this->configSettings['custom_fields'] = [
                [
                    'id'              => 'cf_name',
                    'label'           => '1. Wunschname',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. Emilia & Lukas',
                    'info_text'       => 'Dein Haupt-Gravurtext auf der Vorderseite. Maximal 25 Zeichen für optimale Lesbarkeit.',
                    'required'        => true,
                    'max_length'      => 25,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_datum',
                    'label'           => '2. Wunschdatum (Optional)',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. 14.08.2026',
                    'info_text'       => 'Optionales Datum (z. B. Jahrestag, Geburtstag oder Taufe), das fein unter dem Namen eingraviert wird.',
                    'required'        => false,
                    'max_length'      => 15,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_symbol',
                    'label'           => '3. Kleines Gravur-Symbol',
                    'type'            => 'select',
                    'placeholder'     => 'Bitte wählen...',
                    'info_text'       => 'Wähle auf Wunsch ein feines Laser-Symbol, das wir harmonisch neben oder unter deine Gravur setzen.',
                    'required'        => true,
                    'max_length'      => 40,
                    'options'         => ['Kein Symbol', '♥ Herz', '∞ Unendlichkeit', '★ Stern', '🐾 Pfote', '⚓ Anker'],
                    'options_raw'     => 'Kein Symbol, ♥ Herz, ∞ Unendlichkeit, ★ Stern, 🐾 Pfote, ⚓ Anker',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
            ];
        } elseif ($presetKey === 'family_names_4') {
            $this->configSettings['custom_fields'] = [
                [
                    'id'              => 'cf_name_1',
                    'label'           => '1. Name (Pflichtfeld)',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. Mama / 1. Kind',
                    'info_text'       => 'Erster Name für die Gravur (im Grundpreis enthalten).',
                    'required'        => true,
                    'max_length'      => 18,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_name_2',
                    'label'           => '2. Name (Optional)',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. 2. Name (leer lassen falls nicht gewünscht)',
                    'info_text'       => 'Optionaler zweiter Name auf dem Schmuckstück.',
                    'required'        => false,
                    'max_length'      => 18,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_name_3',
                    'label'           => '3. Name (Optional)',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. 3. Name (leer lassen falls nicht gewünscht)',
                    'info_text'       => 'Optionaler dritter Name auf dem Schmuckstück.',
                    'required'        => false,
                    'max_length'      => 18,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_name_4',
                    'label'           => '4. Name (Optional)',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. 4. Name (leer lassen falls nicht gewünscht)',
                    'info_text'       => 'Optionaler vierter Name auf dem Schmuckstück.',
                    'required'        => false,
                    'max_length'      => 18,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
            ];
        } elseif ($presetKey === 'front_back_engraving') {
            $this->configSettings['custom_fields'] = [
                [
                    'id'              => 'cf_front',
                    'label'           => 'Gravur Vorderseite',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. Balu',
                    'info_text'       => 'Wird gut sichtbar und zentriert auf der Vorderseite eingraviert (inklusive).',
                    'required'        => true,
                    'max_length'      => 22,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 0,
                    'surcharge_price' => '0.00',
                ],
                [
                    'id'              => 'cf_back',
                    'label'           => 'Zusätzliche Gravur Rückseite (Optional)',
                    'type'            => 'text',
                    'placeholder'     => 'z. B. Tel. 0171 1234567 oder Geheime Widmung',
                    'info_text'       => 'Wenn du dieses Feld ausfüllst, gravieren wir zusätzlich die Rückseite für dich (+5,00 € Aufpreis).',
                    'required'        => false,
                    'max_length'      => 30,
                    'options'         => [],
                    'options_raw'     => '',
                    'surcharge_cents' => 500,
                    'surcharge_price' => '5.00',
                ],
            ];
        }

        $this->persistCustomFieldsToProduct();
    }

    #[Computed]
    public function suppliers()
    {
        return \App\Models\Product\ProductSupplier::orderBy('name')->get();
    }

    #[Computed]
    public function packagingCatalogItems()
    {
        return \App\Models\Product\ProductPackagingItem::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }
}
