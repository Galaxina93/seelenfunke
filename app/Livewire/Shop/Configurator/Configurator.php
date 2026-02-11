<?php

namespace App\Livewire\Shop\Configurator;

use App\Models\Product\Product;
use App\Services\CartService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Configurator extends Component
{
    use WithFileUploads;

    public $product;
    public $cartItem = null;
    public $context = 'add';

    // State: Menge (FIX: Wurde hinzugefügt)
    public $qty = 1;
    // State Texte

    public $texts = [];

    // State Logos
    public $logos = [];

    // Files Management
    public $new_files = [];
    public $uploaded_files = [];

    public $notes = '';

    public $configSettings = [];
    public $currentPrice = 0;
    public $totalPrice = 0;

    public $config_confirmed = false;

    public $fonts = [
        'Arial' => 'Arial, sans-serif',
        'Times New Roman' => 'Times New Roman, serif',
        'Verdana' => 'Verdana, sans-serif',
        'Courier New' => 'Courier New, monospace',
        'Georgia' => 'Georgia, serif',
        'Great Vibes' => '"Great Vibes", cursive',
    ];

    public $alignmentOptions = ['left' => 'Links', 'center' => 'Zentriert', 'right' => 'Rechts'];

    public function mount($product, $context = 'add', $cartItem = null, $initialData = [], $qty = null)
    {
        $this->product = ($product instanceof Product) ? $product : Product::findOrFail($product);
        $this->context = $context;
        $this->cartItem = $cartItem;

        $dbSettings = $this->product->configurator_settings ?? [];
        if (is_string($dbSettings)) $dbSettings = json_decode($dbSettings, true);

        $this->configSettings = array_merge([
            'allow_logo' => true,
            'area_top' => 10, 'area_left' => 10, 'area_width' => 80, 'area_height' => 80, 'area_shape' => 'rect'
        ], $dbSettings ?? []);

        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $source = !empty($initialData) ? $initialData : ($this->cartItem ? $this->cartItem->configuration : []);

        // Menge initialisieren
        if ($qty !== null) {
            $this->qty = $qty;
        } elseif ($this->cartItem && empty($initialData)) {
            $this->qty = $this->cartItem->quantity;
        } else {
            $this->qty = $source['qty'] ?? 1;
        }

        $this->notes = $source['notes'] ?? '';
        $this->uploaded_files = $source['files'] ?? [];

        // 1. TEXTE LADEN (Migration von alt zu neu)
        if (isset($source['texts']) && is_array($source['texts'])) {
            $this->texts = $source['texts'];
        } elseif (!empty($source['text'])) {
            // Fallback: Alten Einzeltext in Array umwandeln
            $this->texts[] = [
                'id' => Str::uuid()->toString(),
                'text' => $source['text'],
                'font' => $source['font'] ?? 'Arial',
                'align' => $source['align'] ?? 'center',
                'x' => $source['text_x'] ?? $centerX,
                'y' => $source['text_y'] ?? $centerY,
                'size' => $source['text_size'] ?? 1.0,
            ];
        } else {
            // Standard: Ein leeres Textfeld
            $this->addText($centerX, $centerY);
        }

        // 2. LOGOS LADEN
        if (isset($source['logos']) && is_array($source['logos'])) {
            $this->logos = $source['logos'];
        } elseif (!empty($source['logo_path'])) {
            if (!in_array($source['logo_path'], $this->uploaded_files)) {
                $this->uploaded_files[] = $source['logo_path'];
            }
            $this->logos[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'saved',
                'value' => $source['logo_path'],
                'x' => $source['logo_x'] ?? $centerX,
                'y' => $source['logo_y'] ?? $centerY,
                'size' => $source['logo_size'] ?? 130
            ];
        }

        $this->calculatePrice();
    }

    public function updated($propertyName)
    {
        if ($this->context === 'preview') return; // Sperre für Preview
        if ($propertyName === 'qty') {
            if ($this->qty < 1) $this->qty = 1;
            $this->calculatePrice();
        }
    }

    // --- TEXT MANAGEMENT ---

    public function addText($x = null, $y = null)
    {
        if ($this->context === 'preview') return; // Sperre für Preview
        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $this->texts[] = [
            'id' => Str::uuid()->toString(),
            'text' => '',
            'font' => 'Arial',
            'align' => 'center',
            'x' => $x ?? $centerX,
            'y' => $y ?? $centerY,
            'size' => 1.0
        ];
    }

    public function removeText($index)
    {
        if ($this->context === 'preview') return; // Sperre für Preview
        unset($this->texts[$index]);
        $this->texts = array_values($this->texts);

        // Wenn alle gelöscht, zumindest einen leeren hinzufügen (UX Entscheidung)
        if(count($this->texts) === 0) {
            $this->addText();
        }
    }

    // --- LOGO MANAGEMENT ---

    public function updatedNewFiles()
    {
        if ($this->context === 'preview') return; // Sperre für Preview
        $this->validate([
            'new_files.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,svg',
        ]);

        foreach(array_reverse($this->new_files, true) as $index => $file) {
            if (in_array(strtolower($file->extension()), ['jpg','jpeg','png','webp'])) {
                $this->toggleLogo('new', $index);
                break;
            }
        }
    }

    public function toggleLogo($type, $value)
    {
        if ($this->context === 'preview') return; // Sperre für Preview
        foreach ($this->logos as $key => $logo) {
            if ($logo['type'] === $type && $logo['value'] == $value) {
                unset($this->logos[$key]);
                $this->logos = array_values($this->logos);
                return;
            }
        }

        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $this->logos[] = [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'value' => $value,
            'x' => $centerX,
            'y' => $centerY,
            'size' => 130
        ];
    }

    public function isLogoActive($type, $value)
    {
        foreach ($this->logos as $logo) {
            if ($logo['type'] === $type && $logo['value'] == $value) {
                return true;
            }
        }
        return false;
    }

    public function getRenderedLogosProperty()
    {
        $rendered = [];
        foreach ($this->logos as $logo) {
            $url = null;
            if ($logo['type'] === 'new' && isset($this->new_files[$logo['value']])) {
                try {
                    $url = $this->new_files[$logo['value']]->temporaryUrl();
                } catch (\Exception $e) { continue; }
            } elseif ($logo['type'] === 'saved') {
                $url = asset('storage/' . $logo['value']);
            }

            if ($url) {
                $rendered[] = array_merge($logo, ['url' => $url]);
            }
        }
        return $rendered;
    }

    public function removeFile($index)
    {
        if ($this->context === 'preview') return; // Sperre für Preview
        if (isset($this->uploaded_files[$index])) {
            $path = $this->uploaded_files[$index];
            $this->toggleLogo('saved', $path);
            unset($this->uploaded_files[$index]);
            $this->uploaded_files = array_values($this->uploaded_files);
        }
    }

    public function removeNewFile($index) {
        if ($this->context === 'preview') return; // Sperre für Preview
        if(isset($this->new_files[$index])) {
            foreach ($this->logos as $key => $logo) {
                if ($logo['type'] === 'new' && $logo['value'] == $index) {
                    unset($this->logos[$key]);
                }
            }
            $this->logos = array_values($this->logos);
            unset($this->new_files[$index]);
            $this->new_files = array_values($this->new_files);
            $this->logos = array_filter($this->logos, fn($l) => $l['type'] !== 'new');
        }
    }

    public function calculatePrice()
    {
        $basePrice = $this->product->price;
        $tierPricing = $this->product->tier_pricing;

        if (!empty($tierPricing) && is_array($tierPricing)) {
            usort($tierPricing, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tierPricing as $tier) {
                if ($this->qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    $basePrice -= $discount;
                    break;
                }
            }
        }

        if ($this->product->tax_included === false) {
            $taxRate = (float) ($this->product->tax_rate ?? 19.0);
            $basePrice = (int) round($basePrice * (1 + ($taxRate / 100)));
        }

        $this->currentPrice = $basePrice;
        $this->totalPrice = $basePrice * $this->qty;
    }

    public function save(CartService $cartService)
    {
        if ($this->context === 'preview') return;

        // --- RECHTSPRÜFUNG ---
        if (!$this->config_confirmed) {
            $this->addError('config_confirmed', 'Bitte bestätigen Sie, dass Sie Ihre Angaben geprüft haben.');
            return;
        }

        $this->validate([
            'qty' => 'required|integer|min:1',
            'texts.*.text' => 'nullable|string|max:100', // Validierung für Array
            'new_files.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,svg',
        ]);

        // 1. Files speichern
        $tempIndexToPermanentPath = [];
        if (!empty($this->new_files)) {
            foreach ($this->new_files as $index => $file) {
                $path = $file->store('cart-uploads', 'public');
                $this->uploaded_files[] = $path;
                $tempIndexToPermanentPath[$index] = $path;
            }
        }

        // 2. Logos aktualisieren
        $finalLogos = [];
        foreach ($this->logos as $logo) {
            if ($logo['type'] === 'new') {
                if (isset($tempIndexToPermanentPath[$logo['value']])) {
                    $logo['type'] = 'saved';
                    $logo['value'] = $tempIndexToPermanentPath[$logo['value']];
                    $finalLogos[] = $logo;
                }
            } else {
                $finalLogos[] = $logo;
            }
        }

        // 3. Fallback Preview Image
        $mainLogoPath = null;
        if (!empty($finalLogos)) {
            $mainLogoPath = $finalLogos[0]['value'];
        } elseif (!empty($this->uploaded_files)) {
            foreach($this->uploaded_files as $f) {
                if(preg_match('/\.(jpg|jpeg|png|webp)$/i', $f)) {
                    $mainLogoPath = $f;
                    break;
                }
            }
        }

        // 4. Legacy Text Support (für Backend Anzeige)
        // Wir nehmen den ersten nicht-leeren Text als "Haupttext"
        $mainText = '';
        $mainFont = 'Arial';
        $mainAlign = 'center';
        $mainTextX = 50;
        $mainTextY = 50;
        $mainTextSize = 1.0;

        foreach($this->texts as $t) {
            if(!empty($t['text'])) {
                $mainText = $t['text'];
                $mainFont = $t['font'];
                $mainAlign = $t['align'];
                $mainTextX = $t['x'];
                $mainTextY = $t['y'];
                $mainTextSize = $t['size'];
                break;
            }
        }

        $configData = [
            // Neu: Alle Texte
            'texts' => $this->texts,
            'logos' => $finalLogos,

            // Legacy / Fallback Keys (damit bestehende Views nicht crashen)
            'text' => $mainText,
            'font' => $mainFont,
            'align' => $mainAlign,
            'text_x' => $mainTextX,
            'text_y' => $mainTextY,
            'text_size' => $mainTextSize,
            'logo_path' => $mainLogoPath,
            'logo_storage_path' => $mainLogoPath,

            'files' => $this->uploaded_files,
            'notes' => $this->notes,
            'qty' => $this->qty
        ];

        if ($this->context === 'add') {
            if (!$this->product->isAvailable()) {
                $this->addError('qty', 'Produkt nicht verfügbar.');
                return;
            }
            $cartService->addItem($this->product, $this->qty, $configData);

            $this->dispatch('cart-updated');
            $this->dispatch('notify', message: 'In den Warenkorb gelegt!');
            // Reset
            $this->reset(['new_files', 'uploaded_files', 'logos', 'notes']);
            // Text Reset: Wieder einen leeren Text herstellen
            $this->texts = [];
            $this->addText();
            $this->config_confirmed = false; // Nach Erfolg zurücksetzen

        } elseif ($this->context === 'edit' && $this->cartItem) {
            $cartService->updateItem($this->cartItem->id, $this->qty, $configData);
            $this->dispatch('cart-updated');
            $this->dispatch('close-modal');
            $this->dispatch('notify', message: 'Änderungen gespeichert!');
        } elseif ($this->context === 'calculator') {
            $configData['product_id'] = $this->product->id;
            $this->dispatch('calculator-save', data: $configData);
        }
    }

    public function getPreviewImageProperty()
    {
        $path = $this->product->preview_image_path;
        if (empty($path) && !empty($this->product->media_gallery)) {
            foreach($this->product->media_gallery as $media) {
                if (($media['type'] ?? '') === 'image') {
                    $path = $media['path'];
                    break;
                }
            }
        }
        return $path ? asset('storage/' . $path) : null;
    }

    public function render()
    {
        return view('livewire.shop.configurator.configurator');
    }
}
