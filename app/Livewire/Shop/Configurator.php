<?php

namespace App\Livewire\Shop;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Livewire\Component;
use Livewire\WithFileUploads;

class Configurator extends Component
{
    use WithFileUploads;

    // --- PARAMETER ---
    public $product;                // Kann Model oder ID (String/Int) sein
    public $cartItem = null;        // Nur bei Edit-Modus im Cart
    public $context = 'add';        // 'add' (Shop), 'edit' (Cart), 'calculator' (Widget)
    public $initialData = [];       // Daten vom Calculator

    // --- STATE ---
    public $qty = 1;
    public $engraving_text = '';
    public $engraving_font = 'Arial';
    public $engraving_align = 'center';

    // Positionierung
    public $text_x = 50.0;
    public $text_y = 50.0;
    public $text_size = 1.0;

    public $logo_x = 50.0;
    public $logo_y = 30.0;
    public $logo_size = 130;

    // Bilder
    public $uploaded_logo;          // Temporäres Livewire Upload Objekt (Neu)
    public $existing_logo_path;     // Pfad aus DB/Session (Bestand)

    public $notes = '';

    // --- CONFIG & PREISE ---
    public $configSettings = [];
    public $currentPrice = 0;
    public $totalPrice = 0;

    // --- OPTIONEN ---
    public $fonts = [
        'Arial' => 'Arial, sans-serif',
        'Times New Roman' => 'Times New Roman, serif',
        'Verdana' => 'Verdana, sans-serif',
        'Courier New' => 'Courier New, monospace',
        'Georgia' => 'Georgia, serif',
        'Great Vibes' => '"Great Vibes", cursive',
    ];

    public $alignmentOptions = [
        'left' => 'Links',
        'center' => 'Zentriert',
        'right' => 'Rechts',
    ];

    public function mount($product, $context = 'add', $cartItem = null, $initialData = [])
    {
        // 1. Produkt laden (ID zu Model), falls nötig
        $this->product = ($product instanceof Product) ? $product : Product::findOrFail($product);

        $this->context = $context;
        $this->cartItem = $cartItem;

        // 2. Settings des Produkts laden
        $dbSettings = $this->product->configurator_settings ?? [];
        if (is_string($dbSettings)) $dbSettings = json_decode($dbSettings, true);

        $defaults = [
            'allow_logo' => true,
            'default_text_align' => 'center',
            'area_top' => 10, 'area_left' => 10, 'area_width' => 80, 'area_height' => 80,
        ];
        $this->configSettings = array_merge($defaults, $dbSettings ?? []);

        // 3. Standardwerte für Positionen berechnen
        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        // 4. Datenquelle bestimmen (Priorität: InitialData > CartItem > Defaults)
        $source = [];

        if (!empty($initialData)) {
            $source = $initialData;
        } elseif ($this->cartItem) {
            $source = $this->cartItem->configuration ?? [];
            $this->qty = $this->cartItem->quantity;
        }

        // 5. Werte zuweisen
        $this->qty = $source['qty'] ?? ($initialData['qty'] ?? 1);

        $this->engraving_text = $source['text'] ?? ($source['engraving_text'] ?? '');
        $this->engraving_font = $source['font'] ?? ($source['engraving_font'] ?? 'Arial');
        $this->engraving_align = $source['align'] ?? ($source['engraving_align'] ?? 'center');

        $this->text_x = isset($source['text_x']) ? (float)$source['text_x'] : $centerX;
        $this->text_y = isset($source['text_y']) ? (float)$source['text_y'] : $centerY;
        $this->text_size = isset($source['text_size']) ? (float)$source['text_size'] : 1.0;

        $this->logo_x = isset($source['logo_x']) ? (float)$source['logo_x'] : $centerX;
        $this->logo_y = isset($source['logo_y']) ? (float)$source['logo_y'] : max($this->configSettings['area_top'], $centerY - 20);
        $this->logo_size = isset($source['logo_size']) ? (int)$source['logo_size'] : 130;

        // WICHTIG: Hier holen wir das bestehende Logo zurück
        $this->existing_logo_path = $source['logo_path'] ?? ($source['existing_logo_path'] ?? null);

        $this->notes = $source['notes'] ?? '';

        $this->calculatePrice();
    }

    public function updated($propertyName)
    {
        if ($propertyName === 'qty') {
            if ($this->qty < 1) $this->qty = 1;
            $this->calculatePrice();
        }
    }

    public function calculatePrice()
    {
        $basePrice = $this->product->price;
        $tierPricing = $this->product->tier_pricing;

        // Staffelpreise
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
        $this->validate([
            'qty' => 'required|integer|min:1',
            'engraving_text' => 'nullable|string|max:100',
            'uploaded_logo' => 'nullable|image|max:10240',
        ]);

        // Logo Logik: Neues Bild > Altes Bild > Null
        $logoPath = $this->existing_logo_path;

        if ($this->configSettings['allow_logo'] && $this->uploaded_logo) {
            $logoPath = $this->uploaded_logo->store('cart-uploads', 'public');
        }

        // Datenpaket
        $configData = [
            'text' => $this->engraving_text,
            'font' => $this->engraving_font,
            'align' => $this->engraving_align,
            'text_x' => $this->text_x,
            'text_y' => $this->text_y,
            'text_size' => $this->text_size,

            'logo_path' => $logoPath,
            'logo_x' => $this->logo_x,
            'logo_y' => $this->logo_y,
            'logo_size' => $this->logo_size,

            'notes' => $this->notes,
            'qty' => $this->qty
        ];

        // --- Context Handling ---

        if ($this->context === 'add') {
            if (!$this->product->isAvailable()) {
                $this->addError('qty', 'Produkt nicht verfügbar.');
                return;
            }
            $cartService->addItem($this->product, $this->qty, $configData);

            $this->dispatch('cart-updated');
            $this->dispatch('notify', message: 'In den Warenkorb gelegt!');
            $this->reset(['engraving_text', 'uploaded_logo', 'notes']);

        } elseif ($this->context === 'edit' && $this->cartItem) {
            $cartService->updateItem($this->cartItem->id, $this->qty, $configData);

            $this->dispatch('cart-updated');
            $this->dispatch('close-modal');
            $this->dispatch('notify', message: 'Änderungen gespeichert!');

        } elseif ($this->context === 'calculator') {
            // ID hinzufügen, damit Calculator weiß, um welches Produkt es geht
            $configData['product_id'] = $this->product->id;

            // Event senden
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
        return view('livewire.shop.configurator');
    }
}
