<?php

namespace App\Livewire\Shop;

use App\Services\CartService;
use App\Models\CartItem;
use Livewire\Component;
use Livewire\WithFileUploads;

class CartItemEditor extends Component
{
    use WithFileUploads;

    public CartItem $item;
    public $product;

    // --- STATE ---
    public $qty = 1;

    // Gravur
    public $engraving_text = '';
    public $engraving_font = 'Arial';
    public $engraving_align = 'center';

    // Koordinaten
    public $text_x = 50.0;
    public $text_y = 50.0;
    public $text_size = 1.0;

    // Logo
    public $uploaded_logo;
    public $existing_logo_path;

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

    public function mount(CartItem $item)
    {
        $this->item = $item;
        $this->product = $item->product;

        $dbSettings = $this->product->configurator_settings ?? [];
        if (is_string($dbSettings)) {
            $dbSettings = json_decode($dbSettings, true);
        }

        $defaults = [
            'allow_logo' => true,
            'default_text_align' => 'center',
            'area_top' => 10,
            'area_left' => 10,
            'area_width' => 80,
            'area_height' => 80,
        ];

        $this->config = array_merge($defaults, $dbSettings ?? []);

        // Load Values
        $savedConfig = $this->item->configuration ?? [];

        $this->qty = $this->item->quantity;

        $this->engraving_text = $savedConfig['text'] ?? '';
        $this->engraving_font = $savedConfig['font'] ?? 'Arial';
        $this->engraving_align = $savedConfig['align'] ?? 'center';

        $centerX = $this->config['area_left'] + ($this->config['area_width'] / 2);
        $centerY = $this->config['area_top'] + ($this->config['area_height'] / 2);

        $this->text_x = $savedConfig['text_x'] ?? $centerX;
        $this->text_y = $savedConfig['text_y'] ?? $centerY;
        $this->text_size = $savedConfig['text_size'] ?? 1.0;

        $this->existing_logo_path = $savedConfig['logo_path'] ?? null;

        $this->logo_x = $savedConfig['logo_x'] ?? $centerX;
        $this->logo_y = $savedConfig['logo_y'] ?? max($this->config['area_top'], $centerY - 20);
        $this->logo_size = $savedConfig['logo_size'] ?? 130; // UPDATED

        $this->notes = $savedConfig['notes'] ?? '';

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
            $taxRate = (float) $this->product->tax_rate;
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

        $logoPath = $this->existing_logo_path;
        if ($this->config['allow_logo'] && $this->uploaded_logo) {
            $logoPath = $this->uploaded_logo->store('cart-uploads', 'public');
        }

        $newConfig = [
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
        ];

        $cartService->updateItem($this->item->id, $this->qty, $newConfig);

        $this->dispatch('cart-updated');
        $this->dispatch('close-modal');
        session()->flash('success', 'Konfiguration aktualisiert!');
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
        return view('livewire.shop.cart-item-editor');
    }
}
