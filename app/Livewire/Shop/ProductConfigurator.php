<?php

namespace App\Livewire\Shop;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductConfigurator extends Component
{
    use WithFileUploads;

    public Product $product;

    // --- STATE ---
    public $qty = 1;

    // Gravur
    public $engraving_text = '';
    public $engraving_font = 'Arial';
    public $engraving_align = 'center';

    // Koordinaten (Prozent 0-100)
    public $text_x = 50.0;
    public $text_y = 50.0;
    public $text_size = 1.0;

    // Logo
    public $uploaded_logo;
    public $logo_x = 50.0;
    public $logo_y = 30.0;
    public $logo_size = 130; // UPDATED: Standard 130px

    // Sonstiges
    public $notes = '';

    // --- CONFIG & PREISE ---
    public $config = [];
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
        'left' => 'Linksbündig',
        'center' => 'Zentriert',
        'right' => 'Rechtsbündig',
    ];

    public function mount(Product $product)
    {
        $this->product = $product;

        // Settings laden
        $dbSettings = $this->product->configurator_settings ?? [];
        if (is_string($dbSettings)) {
            $dbSettings = json_decode($dbSettings, true);
        }

        // Standardwerte für den Arbeitsbereich
        $defaults = [
            'allow_logo' => true,
            'default_text_align' => 'center',
            'area_top' => 10,
            'area_left' => 10,
            'area_width' => 80,
            'area_height' => 80,
        ];

        $this->config = array_merge($defaults, $dbSettings ?? []);

        // Initiale Position: In die Mitte des definierten Arbeitsbereichs setzen
        $centerX = $this->config['area_left'] + ($this->config['area_width'] / 2);
        $centerY = $this->config['area_top'] + ($this->config['area_height'] / 2);

        $this->text_x = $centerX;
        $this->text_y = $centerY;

        // Logo etwas darüber platzieren
        $this->logo_x = $centerX;
        $this->logo_y = max($this->config['area_top'], $centerY - 20);

        $this->engraving_align = $this->config['default_text_align'];

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

    public function addToCart()
    {
        if (!$this->product->isAvailable()) {
            $this->addError('qty', 'Dieses Produkt ist leider ausverkauft.');
            return;
        }

        $this->validate([
            'qty' => 'required|integer|min:1',
            'engraving_text' => 'nullable|string|max:100',
            'uploaded_logo' => 'nullable|image|max:10240',
        ]);

        $logoPath = null;
        if ($this->config['allow_logo'] && $this->uploaded_logo) {
            $logoPath = $this->uploaded_logo->store('cart-uploads', 'public');
        }

        $sessionId = Session::getId();
        $cart = Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['customer_id' => auth()->id()]
        );

        $cart->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $this->qty,
            'unit_price' => $this->currentPrice,
            'configuration' => [
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

                'notes' => $this->notes
            ]
        ]);

        $this->dispatch('cart-updated');
        $this->dispatch('product-added-to-cart');

        $this->reset(['engraving_text', 'uploaded_logo', 'notes']);
    }

    public function getPreviewImageProperty()
    {
        $path = null;
        if (!empty($this->product->preview_image_path)) {
            $path = $this->product->preview_image_path;
        } elseif (!empty($this->product->media_gallery) && is_array($this->product->media_gallery)) {
            foreach($this->product->media_gallery as $media) {
                if (isset($media['type']) && $media['type'] === 'image' && !empty($media['path'])) {
                    $path = $media['path'];
                    break;
                }
            }
        }
        return $path ? asset('storage/' . $path) : null;
    }

    public function render()
    {
        return view('livewire.shop.product-configurator');
    }
}
