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

    public $product;
    public $cartItem = null;
    public $context = 'add';

    // State
    public $qty = 1;
    public $engraving_text = '';
    public $engraving_font = 'Arial';
    public $engraving_align = 'center';

    public $text_x = 50.0;
    public $text_y = 50.0;
    public $text_size = 1.0;

    public $logo_x = 50.0;
    public $logo_y = 30.0;
    public $logo_size = 130;

    public $new_files = [];
    public $uploaded_files = [];
    public $active_preview = null;

    public $notes = '';

    public $configSettings = [];
    public $currentPrice = 0;
    public $totalPrice = 0;

    public $fonts = [
        'Arial' => 'Arial, sans-serif',
        'Times New Roman' => 'Times New Roman, serif',
        'Verdana' => 'Verdana, sans-serif',
        'Courier New' => 'Courier New, monospace',
        'Georgia' => 'Georgia, serif',
        'Great Vibes' => '"Great Vibes", cursive',
    ];

    public $alignmentOptions = ['left' => 'Links', 'center' => 'Zentriert', 'right' => 'Rechts'];

    // FEHLERBEHEBUNG 1: $qty Parameter hinzugefügt
    public function mount($product, $context = 'add', $cartItem = null, $initialData = [], $qty = null)
    {
        $this->product = ($product instanceof Product) ? $product : Product::findOrFail($product);
        $this->context = $context;
        $this->cartItem = $cartItem;

        $dbSettings = $this->product->configurator_settings ?? [];
        if (is_string($dbSettings)) $dbSettings = json_decode($dbSettings, true);

        $this->configSettings = array_merge([
            'allow_logo' => true,
            'area_top' => 10, 'area_left' => 10, 'area_width' => 80, 'area_height' => 80
        ], $dbSettings ?? []);

        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $source = !empty($initialData) ? $initialData : ($this->cartItem ? $this->cartItem->configuration : []);

        // FEHLERBEHEBUNG 1: Priorisierung der Mengenangabe
        if ($qty !== null) {
            // Wenn explizit übergeben (z.B. aus Order View), nimm diesen Wert
            $this->qty = $qty;
        } elseif ($this->cartItem && empty($initialData)) {
            // Wenn CartItem existiert
            $this->qty = $this->cartItem->quantity;
        } else {
            // Fallback auf gespeicherte Daten oder Standard 1
            $this->qty = $source['qty'] ?? 1;
        }

        $this->engraving_text = $source['text'] ?? '';
        $this->engraving_font = $source['font'] ?? 'Arial';
        $this->engraving_align = $source['align'] ?? 'center';

        $this->text_x = isset($source['text_x']) ? (float)$source['text_x'] : $centerX;
        $this->text_y = isset($source['text_y']) ? (float)$source['text_y'] : $centerY;
        $this->text_size = isset($source['text_size']) ? (float)$source['text_size'] : 1.0;

        $this->logo_x = isset($source['logo_x']) ? (float)$source['logo_x'] : $centerX;
        $this->logo_y = isset($source['logo_y']) ? (float)$source['logo_y'] : max($this->configSettings['area_top'], $centerY - 20);
        $this->logo_size = isset($source['logo_size']) ? (int)$source['logo_size'] : 130;

        $this->notes = $source['notes'] ?? '';
        $this->uploaded_files = $source['files'] ?? [];

        if (!empty($source['logo_path']) && !in_array($source['logo_path'], $this->uploaded_files)) {
            $this->uploaded_files[] = $source['logo_path'];
        }

        if (!empty($source['logo_path'])) {
            $this->active_preview = $source['logo_path'];
        }

        $this->calculatePrice();
    }

    public function updated($propertyName)
    {
        if ($propertyName === 'qty') {
            if ($this->qty < 1) $this->qty = 1;
            $this->calculatePrice();
        }
    }

    public function updatedNewFiles()
    {
        $this->validate([
            'new_files.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,svg',
        ]);

        foreach(array_reverse($this->new_files, true) as $index => $file) {
            if (in_array(strtolower($file->extension()), ['jpg','jpeg','png','webp'])) {
                $this->setPreview('new', $index);
                break;
            }
        }
    }

    public function setPreview($type, $value)
    {
        if ($type === 'new') {
            $this->active_preview = 'new_' . $value;
        } else {
            $this->active_preview = $value;
        }
    }

    public function getPreviewUrlProperty()
    {
        if (!$this->active_preview) return null;

        if (str_starts_with($this->active_preview, 'new_')) {
            $index = (int) str_replace('new_', '', $this->active_preview);
            if (isset($this->new_files[$index])) {
                try {
                    return $this->new_files[$index]->temporaryUrl();
                } catch (\Exception $e) {
                    return null;
                }
            }
        }

        if (is_string($this->active_preview) && !str_starts_with($this->active_preview, 'new_')) {
            return asset('storage/' . $this->active_preview);
        }

        return null;
    }

    public function removeFile($index)
    {
        if (isset($this->uploaded_files[$index])) {
            $path = $this->uploaded_files[$index];
            if ($this->active_preview === $path) {
                $this->active_preview = null;
            }
            unset($this->uploaded_files[$index]);
            $this->uploaded_files = array_values($this->uploaded_files);
        }
    }

    public function removeNewFile($index) {
        if(isset($this->new_files[$index])) {
            if ($this->active_preview === 'new_' . $index) {
                $this->active_preview = null;
            }
            unset($this->new_files[$index]);
            $this->new_files = array_values($this->new_files);

            if (str_starts_with($this->active_preview ?? '', 'new_')) {
                $this->active_preview = null;
            }
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
        if ($this->context === 'preview') {
            return;
        }

        $this->validate([
            'qty' => 'required|integer|min:1',
            'engraving_text' => 'nullable|string|max:100',
            'new_files.*' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,svg',
        ]);

        $activeNewIndex = null;
        if (str_starts_with($this->active_preview ?? '', 'new_')) {
            $activeNewIndex = (int) str_replace('new_', '', $this->active_preview);
        }

        $finalPreviewPath = ($activeNewIndex === null) ? $this->active_preview : null;

        if (!empty($this->new_files)) {
            foreach ($this->new_files as $index => $file) {
                $path = $file->store('cart-uploads', 'public');
                $this->uploaded_files[] = $path;

                if ($index === $activeNewIndex) {
                    $finalPreviewPath = $path;
                }
            }
        }

        if (!$finalPreviewPath && !empty($this->uploaded_files)) {
            foreach($this->uploaded_files as $f) {
                if(preg_match('/\.(jpg|jpeg|png|webp)$/i', $f)) {
                    $finalPreviewPath = $f;
                    break;
                }
            }
        }

        $configData = [
            'text' => $this->engraving_text,
            'font' => $this->engraving_font,
            'align' => $this->engraving_align,
            'text_x' => $this->text_x,
            'text_y' => $this->text_y,
            'text_size' => $this->text_size,
            'files' => $this->uploaded_files,
            'logo_path' => $finalPreviewPath,
            'logo_storage_path' => $finalPreviewPath,
            'logo_x' => $this->logo_x,
            'logo_y' => $this->logo_y,
            'logo_size' => $this->logo_size,
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
            $this->reset(['engraving_text', 'new_files', 'uploaded_files', 'active_preview', 'notes']);

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
        return view('livewire.shop.configurator');
    }
}
