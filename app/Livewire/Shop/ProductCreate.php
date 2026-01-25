<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;

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
    public $tax_included = true;
    public $tax_class = 'standard';
    public $tax_rate = 19.00;

    // SEO
    public $seo_title = '';
    public $seo_description = '';
    public $slug_input = '';

    // Identifikatoren
    public $sku = '';
    public $barcode = '';
    public $brand = '';

    // --- Suche & Medien ---
    public $search = '';
    public $new_media = [];
    public $new_video;
    public $new_preview_image;

    // --- Pricing Tiers ---
    public $tiers = [];

    // --- SCHRITT 3: Attribute & Lager ---
    public $productAttributes = [
        'Material' => '',
        'Druck' => '',
        'Technik' => '', // HIER
        'Größe' => '',
        'Gewicht' => '',
        'Verpackung' => '',
        'Lieferzeit' => '3-5 Werktage',
        'Farbe' => ''
    ];

    public $track_quantity = true;
    public $quantity = 0;
    public $continue_selling = false;

    // --- Info Texte ---
    public $infoTexts = [
        'name' => 'Der offizielle Produktname. Er wird im Shop, auf Rechnungen, Lieferscheinen und Bestellbestätigungen angezeigt.',
        'price' => 'Der tatsächliche Verkaufspreis, den der Kunde bezahlt.',
        'compare_price' => 'Ein optionaler Vergleichspreis, z. B. die UVP.',
        'tax' => 'Legt fest, ob der Preis Brutto oder Netto ist.',
        'tax_class' => 'Die Steuerklasse bestimmt den anzuwendenden Mehrwertsteuersatz.',
        'sku' => 'SKU (Stock Keeping Unit) - Interne Artikelnummer.',
        'barcode' => 'Globale Produktkennzeichnung wie GTIN oder EAN.',
        'brand' => 'Marke oder Hersteller.',
        'seo' => 'Daten für Suchmaschinen wie Google.',
        'slug' => 'Der URL-Teil der Adresse.',
        'Lager' => 'Automatische Bestandsverwaltung aktivieren.',

        // Attribute Hilfetexte
        'Material' => 'Hauptmaterial des Produkts.',
        'Größe' => 'Abmessungen oder Konfektionsgröße.',
        'Gewicht' => 'Gesamtgewicht inkl. Verpackung in Gramm.',
        'Farbe' => 'Primäre Farbe.',

        // NEU: Technik Hilfetext
        'Technik' => 'Das verwendete Verfahren zur Herstellung oder Veredelung (z.B. 3D-Druck, Lasergravur, Siebdruck, Handarbeit).',
        'Druck' => 'Art des Aufdrucks, falls vorhanden (z.B. Digitaldruck, UV-Druck).'
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
        'allowed_align' => ['left', 'center', 'right']
    ];

    public $positions = [
        'top-left' => 'Oben Links', 'top-center' => 'Oben Mittig', 'top-right' => 'Oben Rechts',
        'center-left' => 'Mitte Links', 'center-center' => 'Mitte Zentriert', 'center-right' => 'Mitte Rechts',
        'bottom-left' => 'Unten Links', 'bottom-center' => 'Unten Mittig', 'bottom-right' => 'Unten Rechts',
    ];

    protected $rules = [
        'name' => 'required|min:3',
        'sku' => 'required|min:3',
        'price_input' => 'required|numeric|min:0',
        'slug_input' => 'required|alpha_dash|min:3',
    ];

    public function createDraft()
    {
        $draft = Product::create([
            'name' => 'Neues Produkt ' . date('H:i'),
            'slug' => 'draft-' . Str::uuid(),
            'status' => 'draft',
            'price' => 0,
            'tax_rate' => 19.00,
            'tax_class' => 'standard',
            'tax_included' => true,
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

        // SEO & Slug
        $this->seo_title = $this->product->seo_title;
        $this->seo_description = $this->product->seo_description;
        $this->slug_input = str_starts_with($this->product->slug, 'draft-') ? '' : $this->product->slug;

        // Steuern
        $this->tax_included = (bool) $this->product->tax_included;
        $this->tax_class = $this->product->tax_class ?? 'standard';
        $this->tax_rate = (float) $this->product->tax_rate;

        // Identifikatoren
        $this->sku = $this->product->sku;
        $this->barcode = $this->product->barcode;
        $this->brand = $this->product->brand;

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

        $this->tiers = $this->product->tier_pricing ?? [];

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

    public function updatedTaxClass($value)
    {
        $this->tax_rate = match($value) {
            'reduced' => 7.00,
            'zero' => 0.00,
            default => 19.00
        };
    }

    public function updatedSlugInput($value)
    {
        $this->slug_input = Str::slug($value);
    }

    // --- NAVIGATION ---

    public function updateStatus($id, $newStatus)
    {
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
        if ($this->currentStep === 3) {
            return true;
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

    // --- SAVE ---

    public function save($notify = true)
    {
        // 1. Basisdaten
        $this->product->name = $this->name;
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

        // 3. Steuern
        $this->product->tax_included = (bool) $this->tax_included;
        $this->product->tax_class = $this->tax_class;
        $this->product->tax_rate = $this->tax_rate;

        // 4. SEO
        $this->product->seo_title = $this->seo_title;
        $this->product->seo_description = $this->seo_description;

        // 5. Identifikatoren
        $this->product->sku = $this->sku;
        $this->product->barcode = $this->barcode;
        $this->product->brand = $this->brand;

        // 6. Lager
        $this->product->track_quantity = (bool) $this->track_quantity;
        $this->product->quantity = empty($this->quantity) ? 0 : (int) $this->quantity;
        $this->product->continue_selling_when_out_of_stock = (bool) $this->continue_selling;

        // 7. Preis
        $this->product->price_euro = empty($this->price_input) ? '0' : $this->price_input;
        if($this->compare_price_input) {
            $this->product->compare_at_price = (int) round((float)$this->compare_price_input * 100);
        } else {
            $this->product->compare_at_price = null;
        }

        // 8. Arrays
        $this->product->attributes = $this->productAttributes;
        $this->product->configurator_settings = $this->configSettings;

        // 9. Fortschritt speichern
        if($this->currentStep > $this->product->completion_step && $this->canProceed()) {
            $this->product->completion_step = $this->currentStep;
        }

        $this->product->save();

        if($notify) session()->flash('success', 'Produkt gespeichert.');
    }

    // --- QUICK ACTIONS (Lagerbestand in Liste ändern) ---
    public function updateStock($id, $newQty)
    {
        $product = Product::find($id);

        if ($product) {
            // Validierung: Mindestens 0
            $qty = max(0, (int) $newQty);

            $product->quantity = $qty;
            $product->save();

            // Optional: Feedback-Nachricht, die schnell wieder verschwindet
            session()->flash('success', 'Lagerbestand für "' . $product->name . '" aktualisiert.');
        }
    }

    public function render()
    {
        $query = Product::query();
        if($this->search) $query->where('name', 'like', '%'.$this->search.'%');
        $products = ($this->viewMode === 'list') ? $query->latest()->get() : [];

        return view('livewire.shop.product-create', [
            'products' => $products,
            'canProceed' => $this->canProceed()
        ]);
    }
}
