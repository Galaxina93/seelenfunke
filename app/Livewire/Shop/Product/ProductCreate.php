<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Services\ConfiguratorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductCreate extends Component
{
    use WithFileUploads;

    public $viewMode = 'list';
    public Product $product;

    public $currentStep = 1;
    public $totalSteps = 4;

    // --- SCHRITT 1: Basis, Preis & SEO ---
    public $name = '';
    public $type = 'physical'; // NEU: Standardwert
    public $short_description = '';
    public $description = '';

    // Preis & Steuer
    public $price_input = '';
    public $compare_price_input = '';

    // Staffelpreise (Array für Livewire Binding)
    public $tiers = [];

    // Identifikatoren
    public $sku = '';
    public $barcode = '';
    public $brand = '';

    // Status (Enum)
    public $status = 'draft';

    // --- Versanddaten (Nur bei Type = physical)
    public $weight = 0; // Gramm
    public $height = 0; // mm
    public $width = 0;  // mm
    public $length = 0; // mm

    // SEO
    public $seo_title = '';
    public $seo_description = '';
    public $slug_input = '';

    // --- Suche & Medien ---
    public $search = '';
    public $new_media = [];
    public $new_video;
    public $new_preview_image;

    // NEU: Für den 3D Upload
    public $new_3d_model;
    public $new_3d_background;

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
            'slug_input' => 'nullable', // Slug wird automatisch generiert, muss hier nicht strict sein
            'type' => 'required|in:physical,digital,service',
        ];
    }

    public function mount(ConfiguratorService $configService) {
        // Wenn es ein neues Produkt ist oder ein bestehendes geladen wird:
        $this->configSettings = $configService->mergeWithDefaults($this->product->config_settings ?? []);
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
            $this->height = 0;
            $this->width = 0;
            $this->length = 0;
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

        // Versanddaten laden
        $this->weight = $this->product->weight;
        $this->height = $this->product->height;
        $this->width = $this->product->width;
        $this->length = $this->product->length;

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
        $folder = 'products-secure/' . ($this->product->slug ?? 'draft');

        // Original-Dateiname speichern
        $originalName = $this->new_digital_file->getClientOriginalName();

        // Datei speichern (privat)
        $path = $this->new_digital_file->storeAs($folder, time() . '_' . $originalName); // Default disk ist meist 'local' (storage/app)

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
            // Datei vom Server löschen
            Storage::delete($this->product->digital_download_path);

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
            return !empty($this->name) && $price > 0 && !empty($this->sku) && !empty($this->slug_input) && !empty($this->type);
        }
        if ($this->currentStep === 2) {
            // Mindestens ein Bild prüfen
            $hasImage = false;
            foreach($this->product->media_gallery ?? [] as $media) {
                if(isset($media['type']) && $media['type'] === 'image') {
                    $hasImage = true; break;
                }
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

        // 5. Lager
        $this->product->track_quantity = (bool) $this->track_quantity;
        $this->product->quantity = empty($this->quantity) ? 0 : (int) $this->quantity;
        $this->product->continue_selling_when_out_of_stock = (bool) $this->continue_selling;

        // 6. Preis (Cent-Konvertierung)
        $this->product->price = empty($this->price_input) ? 0 : (int) round((float)$this->price_input * 100);
        if($this->compare_price_input) {
            $this->product->compare_at_price = (int) round((float)$this->compare_price_input * 100);
        } else {
            $this->product->compare_at_price = null;
        }

        // 7. Versanddaten & physische Maße (Nur speichern wenn Typ physical ist)
        if ($this->type === 'physical') {
            $this->product->weight = (int) $this->weight;
            $this->product->height = (int) $this->height;
            $this->product->width = (int) $this->width;
            $this->product->length = (int) $this->length;
        } else {
            // Bereinigung falls Typ geändert wurde
            $this->product->weight = null;
            $this->product->height = null;
            $this->product->width = null;
            $this->product->length = null;
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

        $folder = 'products/' . ($this->product->slug ?? 'draft') . '/medien';
        $gallery = $this->product->media_gallery ?? [];

        foreach ($this->new_media as $file) {
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.jpg';
            $fullPath = $folder . '/' . $filename;
            $this->resizeAndSaveImage($file->getRealPath(), $fullPath);
            $gallery[] = ['type' => 'image', 'path' => $fullPath];
        }

        $this->product->media_gallery = $gallery;
        $this->product->save();
        $this->new_media = [];
        session()->flash('success', 'Bilder hochgeladen!');
    }

    public function updatedNewVideo()
    {
        $this->validate(['new_video' => 'file|mimes:mp4,mov,qt|max:51200']);
        $folder = 'products/' . ($this->product->slug ?? 'draft') . '/medien';
        $path = $this->new_video->store($folder, 'public');
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
        $folder = 'products/' . ($this->product->slug ?? 'draft') . '/configurator';
        $path = $this->new_preview_image->store($folder, 'public');
        $this->product->preview_image_path = $path;
        $this->product->save();
        $this->new_preview_image = null;
    }

    public function removePreviewImage()
    {
        if($this->product->preview_image_path) {
            Storage::disk('public')->delete($this->product->preview_image_path);
            $this->product->preview_image_path = null;
            $this->product->save();
        }
    }

    public function updatedNew3dModel()
    {
        // Max 50MB
        $this->validate(['new_3d_model' => 'file|max:51200']);

        $folder = 'products/' . ($this->product->slug ?? 'draft') . '/configurator';

        // Lösche altes Modell, falls vorhanden
        if ($this->product->three_d_model_path) {
            Storage::disk('public')->delete($this->product->three_d_model_path);
        }

        $path = $this->new_3d_model->storeAs($folder, time() . '_' . Str::slug($this->product->name) . '.glb', 'public');

        $this->product->three_d_model_path = $path;
        $this->product->save();
        $this->new_3d_model = null;

        session()->flash('success', '3D-Modell erfolgreich hochgeladen!');
    }

    public function remove3dModel()
    {
        if($this->product->three_d_model_path) {
            Storage::disk('public')->delete($this->product->three_d_model_path);
            $this->product->three_d_model_path = null;
            $this->product->save();
            session()->flash('success', '3D-Modell entfernt.');
        }
    }

    // NEU: Hintergrund Upload Logik
    public function updatedNew3dBackground()
    {
        $this->validate(['new_3d_background' => 'image|max:10240']);
        $folder = 'products/' . ($this->product->slug ?? 'draft') . '/configurator';
        if ($this->product->three_d_background_path) Storage::disk('public')->delete($this->product->three_d_background_path);
        $path = $this->new_3d_background->storeAs($folder, time() . '_bg_' . Str::slug($this->product->name) . '.' . $this->new_3d_background->getClientOriginalExtension(), 'public');
        $this->product->three_d_background_path = $path;
        $this->product->save();
        $this->new_3d_background = null;
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

    public function render()
    {
        $query = Product::query();
        if($this->search) $query->where('name', 'like', '%'.$this->search.'%');

        $products = ($this->viewMode === 'list') ? $query->latest()->get() : [];

        return view('livewire.shop.product.product-create', [
            'products' => $products,
            'canProceed' => $this->canProceed()
        ]);
    }
}
