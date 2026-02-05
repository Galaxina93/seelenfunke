<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product;
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
    public $short_description = '';
    public $description = '';

    // Preis & Steuer
    public $price_input = '';
    public $compare_price_input = '';

    // Identifikatoren
    public $sku = '';
    public $barcode = '';
    public $brand = '';

    // Status (Enum)
    public $status = 'draft';

    // --- Versanddaten
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

    // --- SCHRITT 3: Attribute & Lager ---
    public $productAttributes = [
        'Material' => '',
        'Druck' => '',
        'Technik' => '',
        'Größe' => '',
        'Gewicht' => '', // Nur als Attribut für Frontend-Anzeige, logistik-Gewicht ist $weight
        'Verpackung' => '',
        'Lieferzeit' => '3-5 Werktage',
        'Farbe' => ''
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

    // --- Konfigurator Settings ---
    public $configSettings = [
        'allow_text_pos' => true,
        'allow_logo' => true,
        'allow_logo_pos' => true,
        'default_text_pos' => 'center-center',
        'default_logo_pos' => 'top-center',
        'default_text_align' => 'center',
        'area_top' => 10,
        'area_left' => 10,
        'area_width' => 80,
        'area_height' => 80,
        'area_shape' => 'rect',
        'allowed_align' => ['left', 'center', 'right']
    ];

    protected $rules = [
        'name' => 'required|min:3',
        'sku' => 'required|min:3', // SKU ist jetzt wichtiger
        'price_input' => 'required|numeric|min:0',
        'slug_input' => 'required|alpha_dash|min:3',
    ];

    #[On('product-updated')]
    public function refreshProduct()
    {
        // Lädt das Model neu aus der DB, damit die Vorschau die neuen Steuerdaten hat
        $this->product->refresh();
    }

    public function createDraft()
    {
        $draft = Product::create([
            'name' => 'Neues Produkt ' . date('H:i'),
            'slug' => 'draft-' . Str::uuid(),
            'status' => 'draft',
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
        $this->description = $this->product->description;
        $this->short_description = $this->product->short_description;
        $this->status = $this->product->status;

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

        // --- NEU: Staffelpreise laden (aus der neuen Relation) ---
        // Wir mappen es in ein Array, damit Livewire es bearbeiten kann
        $this->tiers = $this->product->tierPrices->map(function($tier) {
            return [
                'id' => Str::uuid()->toString(), // <--- NEU: Eindeutige ID
                'qty' => $tier->qty,
                'percent' => $tier->percent
            ];
        })->toArray();

        // Arrays Mergen
        $this->productAttributes = array_merge($this->productAttributes, $this->product->attributes ?? []);
        $savedConfig = $this->product->configurator_settings ?? [];
        $this->configSettings = array_merge($this->configSettings, $savedConfig);

        $this->viewMode = 'edit';
        $this->currentStep = ($this->product->completion_step > 0) ? $this->product->completion_step : 1;
        if($this->product->completion_step >= 4) $this->currentStep = 4;
    }

    // --- UPDATE LOGIC ---
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

    // --- NAVIGATION & STATUS ---
    public function updateStatus($id, $newStatus)
    {
        // Validierung gegen Enum-Werte
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
        if ($step > $this->product->completion_step + 1 && $this->product->completion_step < 4) return;
        if ($step > $this->currentStep) {
            if (!$this->canProceed()) return;
            $this->save(false);
        }
        $this->currentStep = $step;
    }
    public function nextStep()
    {
        if(!$this->canProceed()) return;
        if($this->currentStep >= $this->product->completion_step) {
            $this->product->completion_step = $this->currentStep;
        }
        $this->save(false);
        if($this->currentStep < $this->totalSteps) $this->currentStep++;
    }
    public function prevStep()
    {
        if($this->currentStep > 1) $this->currentStep--;
    }
    public function canProceed()
    {
        if ($this->currentStep === 1) {
            $price = (float) $this->price_input;
            // SKU ist jetzt wichtiger
            return !empty($this->name) && $price > 0 && !empty($this->sku) && !empty($this->slug_input);
        }
        if ($this->currentStep === 2) {
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
        $this->product->update(['status' => 'active', 'completion_step' => 4]);
        session()->flash('success', 'Produkt veröffentlicht!');
        $this->backToList();
    }

    // --- MEDIA ---

    // (Hier bleibt die Logik weitgehend gleich, da JSON für Medien vorerst ok ist für MVP)
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
        $this->validate(['new_video' => 'mimetypes:video/mp4,video/quicktime|max:51200']);
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

    // --- SAVE (KERNLOGIK) ---

    public function save($notify = true)
    {
        // 1. Basisdaten
        $this->product->name = $this->name;
        $this->product->description = $this->description;
        $this->product->short_description = $this->short_description; // $this->product->short_description = $this->product->short_description;

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

        // 7. Versanddaten
        $this->product->weight = (int) $this->weight;
        $this->product->height = (int) $this->height;
        $this->product->width = (int) $this->width;
        $this->product->length = (int) $this->length;

        // 8. JSON Arrays
        $this->product->attributes = $this->productAttributes;
        $this->product->configurator_settings = $this->configSettings;

        // 9. Fortschritt
        if($this->currentStep > $this->product->completion_step && $this->canProceed()) {
            $this->product->completion_step = $this->currentStep;
        }

        $this->product->save();

        if($notify) session()->flash('success', 'Produkt gespeichert.');
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

        // Performance: Eager Loading der Felder ist bei Eloquent Standard, aber falls
        // Tax Rates irgendwann relational geladen werden, hier ->with() einfügen.
        $products = ($this->viewMode === 'list') ? $query->latest()->get() : [];

        return view('livewire.shop.product.product-create', [
            'products' => $products,
            'canProceed' => $this->canProceed()
        ]);
    }
}
