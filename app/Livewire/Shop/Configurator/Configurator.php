<?php

namespace App\Livewire\Shop\Configurator;

use App\Models\Product\Product;
use App\Services\CartService;
use App\Livewire\Shop\Configurator\Traits\HandlesConfiguratorLogic;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\ConfiguratorService;
use Livewire\Attributes\Computed; // <-- NEU

class Configurator extends Component
{
    use WithFileUploads, HandlesConfiguratorLogic;

    public $product;
    public $cartItem = null;
    public $context = 'add';
    public $type = 'physical';
    public $isDigital = false;
    public $qty = 1;
    public $activeSide = 'front';

    public $texts = [];
    public $logos = [];
    public $texts_back = [];
    public $logos_back = [];

    public $new_files = [];
    public $uploaded_files = [];
    public $notes = '';

    public $configSettings = [];
    public $currentPrice = 0;
    public $totalPrice = 0;
    public $config_confirmed = false;

    public array $fonts = [];
    public array $vectors = [];
    public $design = 'light';

    public $alignmentOptions = [
        'left' => 'Links',
        'center' => 'Zentriert',
        'right' => 'Rechts'
    ];

    public $variantId = null;
    public $variantName = null;

    public function mount(ConfiguratorService $configService, $product, $context = 'add', $cartItem = null, $initialData = [], $qty = null)
    {
        $this->fonts = $configService->getFonts();
        $this->vectors = $configService->getStandardVectors();

        $this->product = ($product instanceof Product) ? $product : Product::findOrFail($product);
        $this->configSettings = $configService->mergeWithDefaults($this->product->configurator_settings ?? []);
        $this->context = $context;
        $this->cartItem = $cartItem;

        $this->type = $this->product->type ?? 'physical';
        $this->isDigital = ($this->type !== 'physical');

        $dbSettings = $this->product->configurator_settings ?? [];
        if (is_string($dbSettings)) $dbSettings = json_decode($dbSettings, true);

        $this->configSettings = array_merge([
            'allow_logo' => true,
            'overlay_type' => 'plane',
            'cylinder_radius' => 50,
            'area_top' => 10,
            'area_left' => 10,
            'area_width' => 80,
            'area_height' => 80,
            'area_shape' => 'rect',
            'custom_points' => [['x'=>10,'y'=>10], ['x'=>90,'y'=>10], ['x'=>90,'y'=>90], ['x'=>10,'y'=>90]]
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

        // NEU: Variant-ID laden oder Standard setzen
        $this->variantId = $source['variant_id'] ?? null;
        $this->variantName = $source['variant_name'] ?? null;

        // Wenn noch keine Variante gewählt ist, aber es welche gibt, nimm die erste!
        $variants = $this->activeVariants;
        if (!$this->variantId && !empty($variants)) {
            $firstKey = array_key_first($variants);
            $this->variantId = $variants[$firstKey]['id'];
            $this->variantName = $variants[$firstKey]['name'];
        }

        $this->notes = $source['notes'] ?? '';
        $this->uploaded_files = $source['files'] ?? [];

        // ... (Der restliche bestehende Mount-Code für Texte und Logos bleibt exakt gleich)
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
            if (!$this->isDigital) $this->addText($centerX, $centerY);
        }

        if (isset($source['texts_back']) && is_array($source['texts_back'])) {
            $this->texts_back = $source['texts_back'];
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

        if (isset($source['logos_back']) && is_array($source['logos_back'])) {
            $this->logos_back = $source['logos_back'];
        }

        foreach ($this->logos as &$logo) {
            if (!isset($logo['url']) && isset($logo['value'])) {
                if (Str::startsWith($logo['value'], 'vectors/')) {
                    $logo['url'] = asset('images/configurator/' . $logo['value']);
                } else {
                    $logo['url'] = asset('storage/' . $logo['value']);
                }
            }
        }

        foreach ($this->logos_back as &$logo) {
            if (!isset($logo['url']) && isset($logo['value'])) {
                if (Str::startsWith($logo['value'], 'vectors/')) {
                    $logo['url'] = asset('images/configurator/' . $logo['value']);
                } else {
                    $logo['url'] = asset('storage/' . $logo['value']);
                }
            }
        }

        $this->calculatePrice();
    }

    // NEU: Holt die aktiven Varianten für den Dropdown
    #[Computed]
    public function activeVariants()
    {
        $variants = $this->product->variants_data ?? [];
        return array_filter($variants, function($v) {
            return ($v['is_active'] ?? true) == true;
        });
    }

    // NEU: Reagiert auf die Änderung im Dropdown
    public function updatedVariantId($value)
    {
        $variants = $this->activeVariants;
        $variant = collect($variants)->firstWhere('id', $value);

        if ($variant) {
            $this->variantName = $variant['name'];
        } else {
            $this->variantName = null;
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

        // ... (Restlicher File-Upload Code bleibt gleich)
        if ($propertyName === 'new_files') {
            $currentCount = count($this->uploaded_files);
            $newCount = count($this->new_files);

            if (($currentCount + $newCount) > 10) {
                $this->addError('new_files', 'Limit erreicht: Maximal 10 Dateien erlaubt.');
                $this->reset('new_files');
                return;
            }

            $this->validate([
                'new_files.*' => 'required|max:20480',
            ]);

            $newPaths = [];

            foreach ($this->new_files as $key => $file) {
                $filename = $file->getClientOriginalName();
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $mime = $file->getMimeType();

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf'];
                $allowedMimes = ['application/pdf', 'application/x-pdf', 'application/acrobat', 'applications/vnd.pdf', 'text/pdf', 'text/x-pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

                $isValid = in_array($extension, $allowedExtensions) || in_array($mime, $allowedMimes);

                if (!$isValid) {
                    $this->addError('new_files.' . $key, "Typ nicht erlaubt ($extension). Nur Bilder & PDF.");
                    continue;
                }

                $path = $file->store('cart-uploads', 'public');

                if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $newFileName = $this->applyLaserEffect(Storage::disk('public')->path($path), $extension);
                    if ($newFileName) {
                        $path = dirname($path) . '/' . $newFileName;
                    }
                }

                $this->uploaded_files[] = $path;
                $newPaths[] = $path;
            }

            $this->reset('new_files');
            $this->addFilesToStage($newPaths);
        }
    }

    // ... (applyLaserEffect und save() bleiben identisch)
    protected function applyLaserEffect($filePath, $extension)
    {
        $image = null;
        switch ($extension) {
            case 'jpeg':
            case 'jpg':
                $image = @imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = @imagecreatefrompng($filePath);
                break;
            case 'webp':
                $image = @imagecreatefromwebp($filePath);
                break;
        }

        if (!$image) return null;

        $width = imagesx($image);
        $height = imagesy($image);

        $maxDim = 800;
        if ($width > $maxDim || $height > $maxDim) {
            $ratio = min($maxDim / $width, $maxDim / $height);
            $newW = (int)($width * $ratio);
            $newH = (int)($height * $ratio);
            $resizedImage = imagecreatetruecolor($newW, $newH);
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
            imagefill($resizedImage, 0, 0, $transparent);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
            $width = $newW;
            $height = $newH;
        }

        $laserImage = imagecreatetruecolor($width, $height);
        imagealphablending($laserImage, false);
        imagesavealpha($laserImage, true);

        $transparent = imagecolorallocatealpha($laserImage, 0, 0, 0, 127);
        imagefill($laserImage, 0, 0, $transparent);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $color);

                $alpha = $colors['alpha'];
                if ($alpha == 127) {
                    continue;
                }

                $luminance = ($colors['red'] * 0.299) + ($colors['green'] * 0.587) + ($colors['blue'] * 0.114);
                $engravingIntensity = 255 - $luminance;
                $targetAlpha = 127 - ($engravingIntensity / 2);
                $finalAlpha = max($alpha, $targetAlpha);
                
                // FIX: XCS benötigt "Schwarz" (#000000) für eine saubere Vektorgravur.
                // Weiß (#FFFFFF) wird als "unsichtbar" gewertet.
                $laserColor = imagecolorallocatealpha($laserImage, 0, 0, 0, (int)$finalAlpha);

                imagesetpixel($laserImage, $x, $y, $laserColor);
            }
        }

        unlink($filePath);
        $newPath = preg_replace('/\.(jpg|jpeg|webp)$/i', '.png', $filePath);
        imagepng($laserImage, $newPath, 9);

        imagedestroy($image);
        imagedestroy($laserImage);

        return basename($newPath);
    }

    public function saveWithSnapshot($snapshotData, CartService $cartService)
    {
        $snapshotPaths = [];
        
        \Illuminate\Support\Facades\Log::info("saveWithSnapshot called. Data empty: " . (empty($snapshotData) ? 'YES' : 'NO'));

        if (!empty($snapshotData)) {
            if (is_array($snapshotData)) {
                foreach ($snapshotData as $side => $base64) {
                    $parts = explode(',', $base64);
                    if (count($parts) === 2) {
                        $decoded = base64_decode($parts[1]);
                        if ($decoded !== false) {
                            $filename = 'snapshots/' . Str::uuid() . '_' . $side . '.jpg';
                            Storage::disk('public')->put($filename, $decoded);
                            $snapshotPaths[$side] = $filename;
                            \Illuminate\Support\Facades\Log::info("Snapshot $side successfully saved to: " . $filename);
                        }
                    }
                }
            } elseif (is_string($snapshotData)) {
                // Fallback für alte Aufrufe mit einzelnem String
                $parts = explode(',', $snapshotData);
                if (count($parts) === 2) {
                    $decoded = base64_decode($parts[1]);
                    if ($decoded !== false) {
                        $filename = 'snapshots/' . Str::uuid() . '.jpg';
                        Storage::disk('public')->put($filename, $decoded);
                        $snapshotPaths['front'] = $filename;
                        \Illuminate\Support\Facades\Log::info("Snapshot successfully saved to: " . $filename);
                    }
                }
            }
        }

        // Ruft die normale save-Funktion auf, reicht das Snapshot-Array weiter
        $this->save($cartService, $snapshotPaths);
    }

    public function save(CartService $cartService, $snapshotPath = null)
    {
        if ($this->context === 'preview') return;

        if (!$this->config_confirmed) {
            $this->addError('config_confirmed', 'Bitte bestätigen Sie Ihre Angaben.');
            return;
        }

        if ($this->context !== 'template_admin') {
            $this->validate(['qty' => 'required|integer|min:1']);
        }

        $mainLogo = !empty($this->logos) ? $this->logos[0]['value'] : null;
        $mainText = collect($this->texts)->firstWhere('text', '!=', '')['text'] ?? '';

        $configData = [
            'texts' => $this->texts,
            'logos' => $this->logos,
            'texts_back' => $this->texts_back,
            'logos_back' => $this->logos_back,
            'text' => $mainText,
            'logo_path' => $mainLogo,
            'files' => $this->uploaded_files,
            'notes' => $this->notes,
            'type' => $this->type,
            'is_digital' => $this->isDigital,
            'variant_id' => $this->variantId,
            'variant_name' => $this->variantName,
            'snapshot_path' => $snapshotPath,
        ];

        if ($this->context !== 'template_admin') {
            $configData['qty'] = $this->qty;
        }

        if ($this->context === 'add') {
            $cartService->addItem($this->product, $this->qty, $configData);
            $this->dispatch('cart-updated');
            $this->dispatch('notify', message: 'In den Warenkorb gelegt!');

            $this->reset(['uploaded_files', 'logos', 'logos_back', 'notes']);
            $this->texts = [];
            $this->texts_back = [];
            if (!$this->isDigital) $this->addText();

            $this->config_confirmed = false;
        } elseif ($this->context === 'edit') {
            $cartService->updateItem($this->cartItem->id, $this->qty, $configData);
            $this->dispatch('cart-updated');
            $this->dispatch('close-modal');
            $this->dispatch('notify', message: 'Änderungen gespeichert!');
        } elseif ($this->context === 'calculator') {
            $configData['product_id'] = $this->product->id;
            $this->dispatch('calculator-save', data: $configData);
        } elseif ($this->context === 'template_admin') {
            $this->dispatch('save-template-data', configData: $configData, previewImagePath: $this->product->preview_image_path);
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
