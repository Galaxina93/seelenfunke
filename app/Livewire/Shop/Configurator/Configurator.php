<?php

namespace App\Livewire\Shop\Configurator;

use App\Models\Product\Product;
use App\Services\CartService;
use App\Livewire\Shop\Configurator\Traits\HandlesConfiguratorLogic;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Configurator extends Component
{
    use WithFileUploads, HandlesConfiguratorLogic;

    // Komponenteneigenschaften
    public $product;
    public $cartItem = null;
    public $context = 'add';
    public $type = 'physical';
    public $isDigital = false;
    public $qty = 1;
    public $texts = [];
    public $logos = [];
    public $new_files = [];
    public $uploaded_files = []; // Hier landen die Pfade nach dem Temp-Upload
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

        $this->type = $this->product->type ?? 'physical';
        $this->isDigital = ($this->type !== 'physical');

        $dbSettings = $this->product->configurator_settings ?? [];
        if (is_string($dbSettings)) $dbSettings = json_decode($dbSettings, true);

        $this->configSettings = array_merge([
            'allow_logo' => true,
            'area_top' => 10, 'area_left' => 10, 'area_width' => 80, 'area_height' => 80, 'area_shape' => 'rect'
        ], $dbSettings ?? []);

        if ($this->isDigital) {
            $this->configSettings['allow_logo'] = false;
        }

        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $source = !empty($initialData) ? $initialData : ($this->cartItem ? $this->cartItem->configuration : []);

        if ($qty !== null) {
            $this->qty = $qty;
        } elseif ($this->cartItem && empty($initialData)) {
            $this->qty = $this->cartItem->quantity;
        } else {
            $this->qty = $source['qty'] ?? 1;
        }

        $this->notes = $source['notes'] ?? '';
        $this->uploaded_files = $source['files'] ?? [];

        if (isset($source['texts']) && is_array($source['texts'])) {
            $this->texts = $source['texts'];
        } elseif (!empty($source['text'])) {
            $this->texts[] = [
                'id' => Str::uuid()->toString(),
                'text' => $source['text'],
                'font' => $source['font'] ?? 'Arial',
                'align' => $source['align'] ?? 'center',
                'x' => $source['text_x'] ?? $centerX,
                'y' => $source['text_y'] ?? $centerY,
                'size' => $source['text_size'] ?? 1.0,
                'rotation' => 0
            ];
        } else {
            if(!$this->isDigital) $this->addText($centerX, $centerY);
        }

        if (isset($source['logos']) && is_array($source['logos'])) {
            $this->logos = $source['logos'];
        } elseif (!empty($source['logo_path'])) {
            if (!in_array($source['logo_path'], $this->uploaded_files)) $this->uploaded_files[] = $source['logo_path'];
            $this->logos[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'saved',
                'value' => $source['logo_path'],
                'x' => $source['logo_x'] ?? $centerX,
                'y' => $source['logo_y'] ?? $centerY,
                'size' => $source['logo_size'] ?? 130,
                'rotation' => 0
            ];
        }

        foreach ($this->logos as &$logo) {
            if (!isset($logo['url']) && isset($logo['value'])) {
                $logo['url'] = asset('storage/' . $logo['value']);
            }
        }

        $this->calculatePrice();
    }

    public function updated($propertyName)
    {
        if ($this->context === 'preview') return;

        if ($propertyName === 'qty') {
            if ($this->qty < 1) $this->qty = 1;
            $this->calculatePrice();
        }

        // === DATEI UPLOAD LOGIK ===
        if ($propertyName === 'new_files') {

            // 1. LIMIT PRÜFUNG (Max 10 Dateien)
            $currentCount = count($this->uploaded_files);
            $newCount = count($this->new_files);

            if (($currentCount + $newCount) > 10) {
                $this->addError('new_files', 'Limit erreicht: Maximal 10 Dateien erlaubt.');
                $this->reset('new_files'); // Upload abbrechen
                return;
            }

            // 2. Grösse Validierung
            $this->validate([
                'new_files.*' => 'required|max:20480', // Max 20MB
            ]);

            foreach ($this->new_files as $key => $file) {
                // 3. Typ Prüfung (PDF & Bilder)
                $filename = $file->getClientOriginalName();
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $mime = $file->getMimeType();

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf'];
                $allowedMimes = [
                    'application/pdf', 'application/x-pdf', 'application/acrobat', 'applications/vnd.pdf', 'text/pdf', 'text/x-pdf',
                    'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'
                ];

                $isValid = in_array($extension, $allowedExtensions) || in_array($mime, $allowedMimes);

                if (!$isValid) {
                    $this->addError('new_files.' . $key, "Typ nicht erlaubt ($extension). Nur Bilder & PDF.");
                    continue;
                }

                // Speichern
                $path = $file->store('cart-uploads', 'public');
                $this->uploaded_files[] = $path;
            }

            $this->reset('new_files');
            $this->addFilesToStage();
        }
    }

    public function save(CartService $cartService)
    {
        if ($this->context === 'preview') return;
        if (!$this->config_confirmed) {
            $this->addError('config_confirmed', 'Bitte bestätigen Sie Ihre Angaben.');
            return;
        }

        $this->validate(['qty' => 'required|integer|min:1']);

        // Die Logos (Stage-Ebenen) enthalten bereits die Pfade aus uploaded_files
        $mainLogo = !empty($this->logos) ? $this->logos[0]['value'] : null;
        $mainText = collect($this->texts)->firstWhere('text', '!=', '')['text'] ?? '';

        $configData = [
            'texts' => $this->texts,
            'logos' => $this->logos,
            'text' => $mainText,
            'logo_path' => $mainLogo,
            'files' => $this->uploaded_files,
            'notes' => $this->notes,
            'qty' => $this->qty,
            'type' => $this->type,
            'is_digital' => $this->isDigital
        ];

        if ($this->context === 'add') {
            if (!$this->product->isAvailable()) return $this->addError('qty', 'Nicht verfügbar.');
            $cartService->addItem($this->product, $this->qty, $configData);
            $this->dispatch('cart-updated');
            $this->dispatch('notify', message: 'In den Warenkorb gelegt!');
            $this->reset(['uploaded_files', 'logos', 'notes']);
            $this->texts = [];
            if(!$this->isDigital) $this->addText();
            $this->config_confirmed = false;
        } elseif ($this->context === 'edit') {
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
        $path = $this->product->preview_image_path ?? collect($this->product->media_gallery)->firstWhere('type', 'image')['path'] ?? null;
        return $path ? asset('storage/' . $path) : null;
    }

    public function render()
    {
        return view('livewire.shop.configurator.configurator', [
            'renderedLogos' => $this->renderedLogos
        ]);
    }
}
