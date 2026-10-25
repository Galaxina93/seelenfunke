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
    public Product $product;

    public $currentStep = 1;
    public $totalSteps = 4;

    // --- SCHRITT 1: Basis, Preis & SEO ---
    public $name = '';
    public $type = 'physical'; // NEU: Standardwert
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

    // --- Info Texte ---
    public $infoTexts = [
        'name' => 'Der offizielle Produktname für Shop, Rechnungen und Lieferscheine.',
        'price' => 'Verkaufspreis. Brutto oder Netto je nach Einstellung.',
        'sku' => 'SKU (Stock Keeping Unit) - Eindeutige Artikelnummer (Pflichtfeld).',
        'barcode' => 'EAN / GTIN für Scanner und externe Marktplätze.',
        'brand' => 'Marke oder Hersteller.',
        'seo' => 'Meta-Daten für Suchmaschinen.',
        'slug' => 'Der URL-Pfad des Produkts.',
        'Lager' => 'Bestandsführung aktivieren.',
        'Technik' => 'Herstellungsverfahren (z.B. Lasergravur, 3D-Druck).',
    ];

    // --- Konfigurator Settings (ERWEITERT UM CUSTOM POINTS) ---
    public $configSettings = [];

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
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(ConfiguratorService $configService) {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $savedLayout = \App\Models\System\SystemSetting::where('key', 'admin_products_layout_mode')->value('value');
        if (in_array($savedLayout, ['large', 'medium', 'list'], true)) {
            $this->layoutMode = $savedLayout;
        }

        // Wenn es ein neues Produkt ist oder ein bestehendes geladen wird:
        $this->configSettings = $configService->mergeWithDefaults($this->product->config_settings ?? []);
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
    }

    #[On('product-updated')]
    public function refreshProduct()
    {
        // Lädt das Model neu aus der DB
        $this->product->refresh();
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
            $this->productAttributes['Auslieferung'] = 'Sofort-Download';
            $this->track_quantity = false; // Bei Digital oft irrelevant
        } elseif ($value === 'service') {
            $this->productAttributes['Ort'] = 'Online';
            $this->track_quantity = true; // Bei Service (Termine) oft relevant
        } else {
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
        $draft = Product::create([
            'name' => 'Neues Produkt ' . date('H:i'),
            'slug' => 'draft-' . Str::uuid(),
            'status' => 'draft',
            'type' => 'physical', // Standard
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 0,
            'media_gallery' => [],
            'tier_pricing' => [],
            'attributes' => $this->productAttributes,
            'configurator_settings' => $this->configSettings,
            'completion_step' => 1
        ]);

        $this->edit($draft->id);
    }

    public function edit($id)
    {
        $this->product = Product::findOrFail($id);

        // Basisdaten
        $this->name = $this->product->name;
        $this->type = $this->product->type; // NEU: Typ laden
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

        // Arrays Mergen
        $this->productAttributes = array_merge($this->productAttributes, $this->product->attributes ?? []);

        $savedConfig = $this->product->configurator_settings ?? [];

        // Sicherstellen, dass custom_points immer existiert (Fallout-Schutz)
        if(!isset($savedConfig['custom_points'])) {
            $savedConfig['custom_points'] = $this->configSettings['custom_points'];
        }

        $this->configSettings = array_merge($this->configSettings, $savedConfig);
        $this->configSettings['auto_seasonal_main_image'] = array_key_exists('auto_seasonal_main_image', $savedConfig)
            ? filter_var($savedConfig['auto_seasonal_main_image'], FILTER_VALIDATE_BOOLEAN)
            : ($this->is_personalizable && $this->type === 'physical');

        // Automatische Erkennung des Vorlage-Pakets für bestehende Standard-Produkte
        $packages = app(ConfiguratorService::class)->getTemplatePackages();
        if (($this->configSettings['template_package'] ?? 'custom') === 'custom' && isset($packages[$this->product->slug])) {
            $this->configSettings['template_package'] = $this->product->slug;
        }

        $this->viewMode = 'edit';

        // Step Berechnung korrigieren (Falls man von Physical auf Digital gewechselt hat und Step auf 4 stand)
        $dbStep = ($this->product->completion_step > 0) ? $this->product->completion_step : 1;
        $this->currentStep = min($dbStep, $this->totalSteps);
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

    public function updateStatus($id, $newStatus)
    {
        if (!in_array($newStatus, ['draft', 'active', 'archived'])) {
            return;
        }

        $prod = Product::find($id);
        if($prod) {
            $prod->status = $newStatus;
            $prod->save();
        }
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
            if (!$this->canProceed()) return;
            $this->save(false);
        }
        $this->currentStep = $step;
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
        }
    }

    public function prevStep()
    {
        if($this->currentStep > 1) $this->currentStep--;
    }

    public function canProceed()
    {
        if ($this->currentStep === 1) {
            $price = (float) $this->price_input;
            $purchase = (float) $this->purchase_price_input;
            
            $validBasic = !empty($this->name) && $price > 0 && !empty($this->sku) && !empty($this->slug_input) && !empty($this->type);
            
            if ($this->type === 'physical') {
                return $validBasic && $this->purchase_price_input !== '' && $purchase >= 0;
            }
            
            return $validBasic;
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

        session()->flash('success', 'Produkt veröffentlicht!');
        $this->backToList();
    }

    // --- SAVE LOGIK ---

    public function save($notify = true)
    {
        // 1. Basisdaten
        $this->product->name = $this->name;
        $this->product->type = $this->type; // Typ speichern
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

        // 4. Identifikatoren
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

        // 8. JSON Arrays
        $this->product->configurator_settings = $this->configSettings;

        // 9. Fortschritt aktualisieren
        if($this->currentStep > $this->product->completion_step && $this->canProceed()) {
            // Sicherstellen, dass completion_step nicht höher als erlaubt ist
            $this->product->completion_step = min($this->currentStep, $this->totalSteps);
        }

        $this->product->save();

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
            $gallery[] = ['type' => 'image', 'path' => $fullPath, 'is_ai_generated' => false];
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
        $gallery[] = ['type' => 'video', 'path' => $path];
        $this->product->media_gallery = $gallery;
        $this->product->save();
        $this->new_video = null;
    }

    public function setMainImage($index)
    {
        $gallery = $this->product->media_gallery;
        if(isset($gallery[$index])) {
            $item = $gallery[$index];
            unset($gallery[$index]);
            array_unshift($gallery, $item);
            $this->product->media_gallery = array_values($gallery);
            $this->product->save();
        }
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

    // NEU: Hintergrund Upload Logik
    public function updatedNew3dBackground()
    {
        $this->validate(['new_3d_background' => 'image|mimes:jpg,jpeg,png,webp|max:10240']);
        $folder = 'produkte/products/' . ($this->product->slug ?? 'draft') . '/configurator';

        if ($this->product->three_d_background_path) {
            Storage::disk('public')->delete($this->product->three_d_background_path);
        }

        // Originalen Dateinamen verwenden (mit strikter Extension-Whitelist auf public Disk)
        $originalName = basename((string) $this->new_3d_background->getClientOriginalName());
        $ext = strtolower((string) $this->new_3d_background->getClientOriginalExtension());
        $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
        $filename = time() . '_bg_' . (Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'bg') . '.' . $safeExt;

        $path = $this->new_3d_background->storeAs($folder, $filename, 'public');
        $this->product->three_d_background_path = $path;
        $this->product->save();
        $this->product->refresh(); // Erzwingt das sofortige Neuladen der Variable im HTML
        $this->new_3d_background = null;

        $this->dispatch('3d-bg-updated', url: asset('storage/' . $path));
        session()->flash('success', 'Hintergrund erfolgreich hochgeladen!');
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
        }
    }

    public function toggleMediaAiGenerated(int $index): void
    {
        if (!isset($this->product) || !$this->product->exists) {
            return;
        }

        $gallery = $this->product->media_gallery ?? [];
        if (isset($gallery[$index]) && is_array($gallery[$index])) {
            $current = filter_var($gallery[$index]['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $gallery[$index]['is_ai_generated'] = !$current;
            $this->product->media_gallery = $gallery;
            $this->product->save();
        }
    }

    public function render()
    {
        $query = Product::with('templates')
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
            $lowThreshold = (int) (\App\Models\System\SystemSetting::where('key', 'inventory_low_stock_threshold')->value('value') ?? 5);
            $query->where('track_quantity', true)->where('quantity', '<', $lowThreshold);
        } elseif ($this->sortBy === 'filter_physical') {
            $query->where('type', 'physical');
        } elseif ($this->sortBy === 'filter_digital') {
            $query->where('type', 'digital');
        }

        $products = ($this->viewMode === 'list') ? $query->latest()->get() : collect();

        if ($this->viewMode === 'list' && $products->isNotEmpty()) {
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

        return view('livewire.shop.product.product-create', [
            'products' => $products,
            'canProceed' => $this->canProceed()
        ]);
    }

    #[Computed]
    public function suppliers()
    {
        return \App\Models\Product\ProductSupplier::orderBy('name')->get();
    }
}
