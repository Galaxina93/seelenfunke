<?php

namespace App\Livewire\Shop\Product\ProductConfigurator;

use App\Models\Product\Product;
use App\Services\CartService;
use App\Livewire\Shop\Product\ProductConfigurator\Traits\HandlesConfiguratorLogic;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\ConfiguratorService;
use App\Services\ProductTrackingService;
use Livewire\Attributes\Computed; // <-- NEU
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use App\Models\System\SystemLog;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Illuminate\Validation\ValidationException;

class ProductConfigurator extends Component
{
    use WithFileUploads, HandlesConfiguratorLogic, WithRateLimiting;

    #[Locked]
    public $product;
    #[Locked]
    public $cartItem = null;
    #[Locked]
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
    #[Locked]
    public $uploaded_files = [];
    public $notes = '';

    public $configSettings = [];
    public $currentPrice = 0;
    public $totalPrice = 0;
    public $config_confirmed = false;
    public $is_express = false;
    #[Locked]
    public $snapshot_path = [];

    public array $fonts = [];
    public array $vectors = [];
    public array $backgroundThemes = [];
    public string $backgroundTheme = 'standard';
    public string $selectedCategory = 'Allgemein';
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
        $this->backgroundThemes = $configService->getBackgroundThemes();
        
        if (!empty($this->vectors)) {
            $this->selectedCategory = array_key_first($this->vectors);
        }

        $this->product = ($product instanceof Product) ? $product : Product::findOrFail($product);
        if (!in_array($context, ['template_admin', 'preview'], true) && !auth()->guard('admin')->check() && !auth()->guard('employee')->check() && $this->product->status !== 'active') {
            abort(404);
        }
        $this->configSettings = $configService->mergeWithDefaults($this->product->configurator_settings ?? []);
        $this->context = $context;
        $this->cartItem = $cartItem;

        // IDOR-Schutz: Kunden dürfen nur eigene Bestellpositionen im Modus 'order_edit' öffnen
        if ($this->context === 'order_edit' && $this->cartItem) {
            $orderItem = \App\Models\Order\OrderOrderItem::with('order')->find($this->cartItem->id ?? null);
            if ($orderItem && $orderItem->order) {
                $isAdmin = auth()->guard('admin')->check();
                $customerId = auth()->guard('customer')->id();
                $isOwner = $customerId && $orderItem->order->customer_id === $customerId;
                if (!$isAdmin && !$isOwner) {
                    abort(403, 'Zugriff auf fremde Bestellposition verweigert.');
                }
            }
        }

        if ($this->context === 'calculator') {
            $this->config_confirmed = true;
        }

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

        $defaultTheme = $this->configSettings['default_background_theme'] ?? 'standard';
        $autoTheme = $configService->getCurrentActiveTheme()['theme_key'] ?? 'standard';
        $effectiveDefault = ($defaultTheme === 'standard' || $defaultTheme === 'auto') ? $autoTheme : $defaultTheme;
        $initialTheme = $source['background_theme'] ?? $source['holiday'] ?? null;
        $this->backgroundTheme = ($initialTheme && $initialTheme !== 'standard')
            ? $configService->resolveThemeKey($initialTheme, $effectiveDefault)
            : $effectiveDefault;

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

        // Wenn noch keine Variante gewählt ist, aber es welche gibt, nimm die erste (außer im Vorlagen-Admin, wo leer = Alle Varianten gilt)!
        $variants = $this->activeVariants;
        if (!$this->variantId && !empty($variants) && $this->context !== 'template_admin') {
            $firstKey = array_key_first($variants);
            $this->variantId = $variants[$firstKey]['id'];
            $this->variantName = $variants[$firstKey]['name'];
        }

        $this->notes = $source['notes'] ?? '';
        $this->is_express = filter_var($source['is_express'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->uploaded_files = $source['files'] ?? [];
        $rawSnapshot = $source['snapshot_path'] ?? [];
        if (is_array($rawSnapshot)) {
            foreach ($rawSnapshot as $k => $p) {
                if (is_string($p) && str_starts_with($p, 'snapshots/')) {
                    $rawSnapshot[$k] = 'system/' . $p;
                }
            }
        } elseif (is_string($rawSnapshot) && str_starts_with($rawSnapshot, 'snapshots/')) {
            $rawSnapshot = 'system/' . $rawSnapshot;
        }
        $this->snapshot_path = $rawSnapshot;

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

        $vectorService = app(\App\Services\ProductConfiguratorService::class);

        foreach ($this->logos as &$logo) {
            if (isset($logo['value'])) {
                if (Str::startsWith($logo['value'], 'vectors/')) {
                    $logo['url'] = $vectorService->resolveVectorUrl($logo['value']);
                } else {
                    $logo['url'] = asset('storage/' . $logo['value']);
                }
            }
        }

        foreach ($this->logos_back as &$logo) {
            if (isset($logo['value'])) {
                if (Str::startsWith($logo['value'], 'vectors/')) {
                    $logo['url'] = $vectorService->resolveVectorUrl($logo['value']);
                } else {
                    $logo['url'] = asset('storage/' . $logo['value']);
                }
            }
        }

        $this->calculatePrice();
    }

    // NEU: Holt die aktiven (und im Frontend verfügbaren) Varianten für den Dropdown
    #[Computed]
    public function activeVariants()
    {
        $variants = $this->product->getSanitizedVariantsData(true);
        $enforceStock = !in_array($this->context, ['template_admin', 'preview', 'order_edit'], true);

        return array_filter($variants, function ($v) use ($enforceStock) {
            if (!is_array($v)) {
                return false;
            }
            if ($enforceStock) {
                return $this->product->isVariantAvailable($v);
            }
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
            $this->variantId = null;
            $this->variantName = null;
        }

        $this->calculatePrice();

        $variantMeta = $this->variantId
            ? ($this->product->resolveVariantImageMeta($this->variantId) ?? $this->product->resolveMainImageMeta(null, true))
            : $this->product->resolveMainImageMeta(null, true);

        $this->dispatch('variant-selected',
            variantId: $this->variantId,
            variantName: $this->variantName,
            formattedPrice: number_format($this->currentPrice / 100, 2, ',', '.') . ' €',
            imageUrl: $variantMeta['url'] ?? null,
            imageIsAi: (bool) ($variantMeta['is_ai_generated'] ?? false),
            imageLabel: $variantMeta['label'] ?? null
        );
    }

    public function messages()
    {
        return [
            'new_files.*.max' => 'Fehler: Die Datei ist zu groß! Maximal 20 MB sind erlaubt.',
            'new_files.*.mimes' => 'Ungültiges Dateiformat. Erlaubt sind JPG, JPEG, PNG, WEBP, SVG und PDF.',
            'new_files.*.uploaded' => 'Die Datei konnte nicht hochgeladen werden (möglicherweise beschädigt oder größer als 20 MB).',
            'new_files.0' => 'Die Datei konnte nicht hochgeladen werden (möglicherweise beschädigt oder größer als 20 MB).',
            'new_files' => 'Die Datei konnte nicht hochgeladen werden (möglicherweise beschädigt oder größer als 20 MB).',
        ];
    }

    public function _uploadErrored($name, $errorsInJson, $isMultiple)
    {
        $this->dispatch('upload:errored', name: $name)->self();

        $germanMsg = 'Die Datei konnte nicht hochgeladen werden. Möglicherweise ist sie beschädigt oder überschreitet die maximale Dateigröße von 20 MB.';
        ProductTrackingService::trackFileUploadError($this->product->id ?? null, $germanMsg);

        if (is_null($errorsInJson)) {
            throw ValidationException::withMessages([
                $name => $germanMsg,
            ]);
        }

        $decoded = json_decode($errorsInJson, true);
        if (isset($decoded['errors']) && is_array($decoded['errors'])) {
            $customErrors = [];
            foreach ($decoded['errors'] as $key => $messages) {
                $mappedKey = $isMultiple ? str_ireplace('files', $name, $key) : str_ireplace('files.0', $name, $key);
                $customErrors[$mappedKey] = array_map(function ($msg) use ($germanMsg) {
                    if (str_contains($msg, 'failed to upload') || str_contains($msg, 'validation.uploaded') || str_contains($msg, 'nicht hochgeladen werden')) {
                        return $germanMsg;
                    }
                    if (str_contains($msg, 'larger than') || str_contains($msg, 'größer als')) {
                        return 'Die Datei ist zu groß. Maximal 20 MB sind erlaubt.';
                    }
                    return $msg;
                }, (array)$messages);
            }
            throw ValidationException::withMessages($customErrors);
        }

        throw ValidationException::withMessages([
            $name => $germanMsg,
        ]);
    }

    public function updated($propertyName)
    {
        if ($this->context === 'preview') return;

        if ($propertyName === 'qty') {
            if ($this->qty < 1) $this->qty = 1;
            $this->calculatePrice();
        }

        if ($propertyName === 'is_express') {
            $this->calculatePrice();
            ProductTrackingService::trackExpressToggled(
                $this->product->id ?? null,
                filter_var($this->is_express, FILTER_VALIDATE_BOOLEAN),
                (int) round($this->totalPrice ?? 0),
                $this->context === 'calculator' ? 'calculator' : 'configurator'
            );
        }

        // ... (Restlicher File-Upload Code bleibt gleich)
        if ($propertyName === 'new_files') {
            try {
                $this->rateLimit(20, 60);
            } catch (TooManyRequestsException $e) {
                $this->addError('new_files', "Zu viele Anfragen. Bitte warten Sie {$e->secondsUntilAvailable} Sekunden.");
                $this->reset('new_files');
                return;
            }

            $currentCount = count($this->uploaded_files);
            $newCount = is_array($this->new_files) ? count($this->new_files) : 0;

            if (($currentCount + $newCount) > 10) {
                $this->addError('new_files', 'Limit erreicht: Maximal 10 Dateien erlaubt.');
                $this->reset('new_files');
                return;
            }

            $this->validate([
                'new_files' => 'required|array|max:10',
                'new_files.*' => 'required|file|max:20480|mimes:jpg,jpeg,png,webp,svg,pdf',
            ]);

            $newPaths = [];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf'];
            $allowedMimesByExt = [
                'jpg'  => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png'  => ['image/png'],
                'webp' => ['image/webp'],
                'svg'  => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'],
                'pdf'  => ['application/pdf', 'application/x-pdf'],
            ];

            foreach ($this->new_files as $key => $file) {
                $clientFilename = $file->getClientOriginalName();

                // 1. Strict filename check: no null bytes, path traversal, or forbidden dangerous extensions
                if (str_contains($clientFilename, "\0") || str_contains($clientFilename, '..') || str_contains($clientFilename, '/') || str_contains($clientFilename, '\\')) {
                    $this->addError('new_files.' . $key, "Ungültiger Dateiname.");
                    continue;
                }

                if (preg_match('/\.(php[0-9]?|phtml|phar|sh|bash|exe|html|htm|js|cgi|pl|py|asp|aspx|jsp|shtml|svgz)(\.|$)/i', $clientFilename)) {
                    $this->addError('new_files.' . $key, "Potenziell unsichere Dateiendung erkannt.");
                    continue;
                }

                $extension = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    $this->addError('new_files.' . $key, "Typ nicht erlaubt ($extension). Nur Bilder & PDF.");
                    continue;
                }

                // 2. Inspect real server-side MIME type via finfo
                $realPath = $file->getRealPath();
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo ? finfo_file($finfo, $realPath) : $file->getMimeType();
                if ($finfo) finfo_close($finfo);

                $allowedMimes = $allowedMimesByExt[$extension] ?? [];
                if (!in_array($detectedMime, $allowedMimes, true)) {
                    $this->addError('new_files.' . $key, "MIME-Typ ($detectedMime) stimmt nicht mit der Dateiendung ($extension) überein.");
                    continue;
                }

                // 3. Deep content verification & safe storage with UUID
                $safeUuid = (string) Str::uuid();

                if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $imageInfo = @getimagesize($realPath);
                    if (!$imageInfo || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
                        $this->addError('new_files.' . $key, "Die Bilddatei ist beschädigt oder kein gültiges Bild.");
                        continue;
                    }

                    if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
                        $this->addError('new_files.' . $key, "Die Bildabmessungen überschreiten das zulässige Maximum (10.000x10.000 px).");
                        continue;
                    }

                    $tempStoredPath = $file->storeAs('bestellungen/cart-uploads', $safeUuid . '.' . $extension, 'public');
                    $fullTempPath = Storage::disk('public')->path($tempStoredPath);

                    $newFileName = $this->applyLaserEffect($fullTempPath, $extension);
                    if (!$newFileName) {
                        if (Storage::disk('public')->exists($tempStoredPath)) {
                            Storage::disk('public')->delete($tempStoredPath);
                        }
                        $this->addError('new_files.' . $key, "Die Bilddatei konnte nicht verarbeitet werden.");
                        continue;
                    }

                    $finalPath = 'bestellungen/cart-uploads/' . $newFileName;
                    $this->uploaded_files[] = $finalPath;
                    $newPaths[] = $finalPath;
                    ProductTrackingService::trackFileUploaded($this->product->id ?? null, $extension, (int) round(($file->getSize() ?? 0) / 1024));
                } elseif ($extension === 'svg') {
                    $tempStoredPath = $file->storeAs('bestellungen/cart-uploads', $safeUuid . '.svg', 'public');
                    $fullTempPath = Storage::disk('public')->path($tempStoredPath);

                    if (!$this->sanitizeSvg($fullTempPath)) {
                        if (Storage::disk('public')->exists($tempStoredPath)) {
                            Storage::disk('public')->delete($tempStoredPath);
                        }
                        $this->addError('new_files.' . $key, "Die SVG-Datei ist ungültig oder enthält potenziell schädlichen Code.");
                        ProductTrackingService::trackFileUploadError($this->product->id ?? null, 'SVG ungültig oder unsicher');
                        continue;
                    }

                    $this->uploaded_files[] = $tempStoredPath;
                    $newPaths[] = $tempStoredPath;
                    ProductTrackingService::trackFileUploaded($this->product->id ?? null, 'svg', (int) round(($file->getSize() ?? 0) / 1024));
                } elseif ($extension === 'pdf') {
                    if (!$this->validatePdfFile($realPath)) {
                        $this->addError('new_files.' . $key, "Die PDF-Datei ist ungültig oder enthält unzulässige eingebettete Skripte/Aktionen.");
                        ProductTrackingService::trackFileUploadError($this->product->id ?? null, 'PDF ungültig oder unsicher');
                        continue;
                    }

                    $storedPdfPath = $file->storeAs('bestellungen/cart-uploads', $safeUuid . '.pdf', 'public');
                    $this->uploaded_files[] = $storedPdfPath;
                    $newPaths[] = $storedPdfPath;
                    ProductTrackingService::trackFileUploaded($this->product->id ?? null, 'pdf', (int) round(($file->getSize() ?? 0) / 1024));
                }
            }

            $this->reset('new_files');
            if (!empty($newPaths)) {
                $this->addFilesToStage($newPaths);
            }
        }
    }

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

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $maxDim = 800;
        if ($width > $maxDim || $height > $maxDim) {
            $ratio = min($maxDim / $width, $maxDim / $height);
            $newW = max(1, (int)($width * $ratio));
            $newH = max(1, (int)($height * $ratio));
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

        // Pre-allocate Alpha-Lookup-Table (0..127), spart bis zu 640.000 Funktionsaufrufe pro Bild
        $alphaLut = [];
        for ($a = 0; $a <= 127; $a++) {
            $alphaLut[$a] = imagecolorallocatealpha($laserImage, 0, 0, 0, $a);
        }

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);

                $alpha = ($color >> 24) & 0x7F;
                if ($alpha === 127) {
                    continue;
                }

                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;

                $luminance = ($r * 0.299) + ($g * 0.587) + ($b * 0.114);
                $engravingIntensity = 255 - $luminance;
                $targetAlpha = 127 - ($engravingIntensity / 2);
                $finalAlpha = (int) max(0, min(127, max($alpha, $targetAlpha)));

                imagesetpixel($laserImage, $x, $y, $alphaLut[$finalAlpha]);
            }
        }

        $newPath = preg_replace('/\.(jpg|jpeg|webp|png)$/i', '.png', $filePath);
        if ($newPath === $filePath) {
            $newPath = dirname($filePath) . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '_proc.png';
        }
        imagepng($laserImage, $newPath, 9);

        if (file_exists($filePath) && $filePath !== $newPath) {
            @unlink($filePath);
        }

        imagedestroy($image);
        imagedestroy($laserImage);

        return basename($newPath);
    }

    public function saveEditedLogo(string $oldValue, string $base64Data): ?array
    {
        if ($this->context === 'preview') {
            return null;
        }

        try {
            $this->rateLimit(20, 60);
        } catch (TooManyRequestsException $e) {
            return null;
        }

        $oldValue = trim($oldValue);
        if ($oldValue === '' || str_contains($oldValue, '..') || str_contains($oldValue, "\0") || !str_starts_with($oldValue, 'bestellungen/cart-uploads/')) {
            return null;
        }

        if (!in_array($oldValue, $this->uploaded_files ?? [], true)) {
            return null;
        }

        if (strlen($base64Data) > 10 * 1024 * 1024) {
            return null;
        }

        $parts = explode(',', $base64Data, 2);
        $rawBase64 = count($parts) === 2 ? $parts[1] : $parts[0];
        $decoded = base64_decode($rawBase64, true);
        if ($decoded === false || strlen($decoded) === 0) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($decoded);
        if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        if ($imageInfo[0] <= 0 || $imageInfo[1] <= 0 || $imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
            return null;
        }

        $extMap = [
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => 'webp',
        ];
        $ext = $extMap[$imageInfo[2]] ?? 'png';
        $safeUuid = (string) Str::uuid();
        $tempRelPath = 'bestellungen/cart-uploads/' . $safeUuid . '.' . $ext;

        Storage::disk('public')->put($tempRelPath, $decoded);
        $fullTempPath = Storage::disk('public')->path($tempRelPath);

        $newFileName = $this->applyLaserEffect($fullTempPath, $ext);
        if (!$newFileName) {
            if (Storage::disk('public')->exists($tempRelPath)) {
                Storage::disk('public')->delete($tempRelPath);
            }
            return null;
        }

        $finalPath = 'bestellungen/cart-uploads/' . $newFileName;
        $finalUrl = asset('storage/' . $finalPath);

        foreach ($this->uploaded_files as $idx => $uf) {
            if ($uf === $oldValue) {
                $this->uploaded_files[$idx] = $finalPath;
            }
        }

        foreach ($this->logos as &$l) {
            if (($l['value'] ?? '') === $oldValue) {
                $l['value'] = $finalPath;
                $l['url'] = $finalUrl;
            }
        }
        unset($l);

        foreach ($this->logos_back as &$lb) {
            if (($lb['value'] ?? '') === $oldValue) {
                $lb['value'] = $finalPath;
                $lb['url'] = $finalUrl;
            }
        }
        unset($lb);

        if ($oldValue !== $finalPath && Storage::disk('public')->exists($oldValue)) {
            Storage::disk('public')->delete($oldValue);
        }

        return [
            'value' => $finalPath,
            'url'   => $finalUrl,
        ];
    }

    protected function validatePdfFile(string $filePath): bool
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return false;
        }

        // 1. Magic bytes check (%PDF-)
        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 1024);
        fclose($handle);

        if (!str_contains($header, '%PDF-')) {
            return false;
        }

        // 2. Scan for malicious PDF action tags
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $dangerousTokens = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFiles'];
        foreach ($dangerousTokens as $token) {
            if (stripos($content, $token) !== false) {
                return false;
            }
        }

        // 3. Try parsing with Smalot\PdfParser to ensure well-formedness
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $parser->parseFile($filePath);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public function saveWithSnapshot($snapshotData, CartService $cartService)
    {
        $snapshotPaths = [];

        if (!empty($snapshotData)) {
            $dataArray = is_array($snapshotData) ? $snapshotData : ['front' => $snapshotData];

            foreach ($dataArray as $side => $base64) {
                if (!is_string($base64) || !is_string($side) || strlen($base64) > 10 * 1024 * 1024) {
                    continue;
                }

                // Sanitize side name to prevent path traversal
                $cleanSide = preg_replace('/[^a-zA-Z0-9_-]/', '', $side);
                if (empty($cleanSide) || strlen($cleanSide) > 20) {
                    continue;
                }

                $parts = explode(',', $base64);
                if (count($parts) === 2) {
                    $decoded = base64_decode($parts[1], true);
                    if ($decoded !== false && strlen($decoded) > 0) {
                        // Verify it is actually an image (PNG or JPEG)
                        $imageInfo = @getimagesizefromstring($decoded);
                        if ($imageInfo && in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
                            $filename = 'system/snapshots/' . Str::uuid() . '_' . $cleanSide . '.jpg';
                            Storage::disk('public')->put($filename, $decoded);
                            $snapshotPaths[$cleanSide] = $filename;
                        }
                    }
                }
            }
        }

        // Ruft die normale save-Funktion auf, reicht das Snapshot-Array weiter
        $this->save($cartService, $snapshotPaths);
    }

    public function sanitizeLaserText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Emojis & farbige Sonderzeichen entfernen, die vom Laser (z. B. XCS) nicht graviert werden können
        $cleaned = preg_replace('/(\p{Extended_Pictographic}|\p{Emoji_Presentation}|[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FAFF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2300}-\x{23FF}\x{2B00}-\x{2BFF}\x{200C}-\x{200D}\x{20E3}\x{FE00}-\x{FE0F}])/u', '', $text);

        $result = $cleaned ?? $text;
        if (trim($result) === '') {
            return '';
        }

        // Bei mehrzeiligem Text bewusste führende Leerzeichen (Einrückungen) erhalten, nur am Ende trimmen
        return str_contains($result, "\n") ? rtrim($result, " \t\r\n") : trim($result);
    }

    public function save(CartService $cartService, $snapshotPath = null)
    {
        if ($this->context === 'preview') return;

        if ($this->context !== 'calculator' && !$this->config_confirmed) {
            $this->addError('config_confirmed', 'Bitte bestätigen Sie Ihre Angaben.');
            return;
        }

        if ($this->context !== 'template_admin') {
            $this->validate(['qty' => 'required|integer|min:1']);
        }

        try {
            DB::transaction(function () use ($cartService, $snapshotPath) {
                $cleanTexts = array_map(function ($t) {
                    if (is_array($t) && isset($t['text'])) {
                        $t['text'] = $this->sanitizeLaserText($t['text']);
                    }
                    return $t;
                }, $this->texts ?? []);

                $cleanTextsBack = array_map(function ($t) {
                    if (is_array($t) && isset($t['text'])) {
                        $t['text'] = $this->sanitizeLaserText($t['text']);
                    }
                    return $t;
                }, $this->texts_back ?? []);

                $sanitizeLogos = function ($logosList) {
                    if (!is_array($logosList)) {
                        return [];
                    }
                    $cleaned = [];
                    foreach ($logosList as $l) {
                        if (!is_array($l)) {
                            continue;
                        }
                        $val = (string) ($l['value'] ?? '');
                        $url = (string) ($l['url'] ?? '');
                        if (str_contains($val, '..') || str_contains($val, "\0") || str_contains($val, '\\') ||
                            str_contains($url, '..') || str_contains($url, "\0") || str_contains($url, '\\')) {
                            \App\Services\System\SystemSecurityService::logEvent(
                                'security:lfi_attempt_blocked',
                                'Manipulierter Logo-Pfad im Konfigurator blockiert',
                                "Ungültiger Pfad in Konfigurator-Logos verworfen.",
                                'error',
                                ['value' => substr($val, 0, 100)]
                            );
                            continue;
                        }
                        if ($val !== '' && !str_starts_with($val, 'vectors/') && !str_starts_with($val, 'bestellungen/cart-uploads/') && !in_array($val, $this->uploaded_files, true)) {
                            continue;
                        }
                        $cleaned[] = $l;
                    }
                    return $cleaned;
                };

                $this->logos = $sanitizeLogos($this->logos);
                $this->logos_back = $sanitizeLogos($this->logos_back);

                $mainLogo = !empty($this->logos) ? $this->logos[0]['value'] : null;
                $mainText = collect($cleanTexts)->firstWhere('text', '!=', '')['text'] ?? '';

                $finalSnapshotPath = !empty($snapshotPath) ? $snapshotPath : $this->snapshot_path;
                if (is_array($finalSnapshotPath)) {
                    foreach ($finalSnapshotPath as $k => $p) {
                        if (is_string($p) && str_starts_with($p, 'snapshots/')) {
                            $finalSnapshotPath[$k] = 'system/' . $p;
                        }
                    }
                } elseif (is_string($finalSnapshotPath) && str_starts_with($finalSnapshotPath, 'snapshots/')) {
                    $finalSnapshotPath = 'system/' . $finalSnapshotPath;
                }

                $configData = [
                    'texts' => $cleanTexts,
                    'logos' => $this->logos,
                    'texts_back' => $cleanTextsBack,
                    'logos_back' => $this->logos_back,
                    'text' => $mainText,
                    'logo_path' => $mainLogo,
                    'files' => $this->uploaded_files,
                    'notes' => $this->notes,
                    'type' => $this->type,
                    'is_digital' => $this->isDigital,
                    'variant_id' => $this->variantId,
                    'variant_name' => $this->variantName,
                    'snapshot_path' => $finalSnapshotPath,
                    'is_express' => $this->is_express,
                    'background_theme' => $this->backgroundTheme,
                ];

                if ($this->context !== 'template_admin') {
                    $configData['qty'] = $this->qty;
                }

                if ($this->context === 'add') {
                    $cartService->addItem($this->product, $this->qty, $configData);
                    $this->dispatch('cart-updated');
                    $this->dispatch('notify', message: 'In den Warenkorb gelegt!');

                    ProductTrackingService::trackConfiguratorSaved(
                        $this->product->id ?? null,
                        (int) round($this->totalPrice ?? 0),
                        [
                            'qty' => $this->qty,
                            'is_express' => filter_var($this->is_express, FILTER_VALIDATE_BOOLEAN),
                            'symbols_count' => count($this->logos) + count($this->logos_back),
                            'files_count' => count($this->uploaded_files),
                        ]
                    );

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
                    if (!auth()->guard('admin')->check() && !auth()->guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
                        abort(403);
                    }
                    $this->dispatch('save-template-data', configData: $configData, previewImagePath: $this->product->preview_image_path);
                } elseif ($this->context === 'order_edit') {
                    $orderItem = \App\Models\Order\OrderOrderItem::with('order')->find($this->cartItem->id ?? null);

                    if (!$orderItem || !$orderItem->order) {
                        session()->flash('error', 'Bestellposition nicht gefunden.');
                        return;
                    }

                    $isAdmin = auth()->guard('admin')->check();
                    $customerId = auth()->guard('customer')->id();
                    $isOwner = $customerId && $orderItem->order->customer_id === $customerId;

                    if (!$isAdmin && !$isOwner) {
                        session()->flash('error', 'Keine Berechtigung zur Bearbeitung dieser Bestellposition.');
                        return;
                    }

                    if ($orderItem->order->status !== 'pending') {
                        session()->flash('error', 'Die Bestellung wird bereits bearbeitet. Änderungen sind nicht mehr möglich.');
                        return;
                    }

                    $configData = $cartService->sanitizeConfiguration($this->product, $configData) ?? $configData;
                    $existingConfig = is_array($orderItem->configuration) ? $orderItem->configuration : [];

                    if (!$isAdmin) {
                        // Kunden dürfen im Nachbesserungsmodus ('order_edit') ausschließlich Gestaltungselemente ändern,
                        // niemals jedoch preis- oder leistungsrelevante Parameter (Variante, Express-Status, Menge, Produkttyp).
                        $configData['variant_id'] = $existingConfig['variant_id'] ?? null;
                        $configData['variant_name'] = $existingConfig['variant_name'] ?? null;
                        $configData['is_express'] = $existingConfig['is_express'] ?? (bool) ($orderItem->order->is_express ?? false);
                        $configData['qty'] = (int) ($existingConfig['qty'] ?? $orderItem->quantity ?? 1);
                        $configData['type'] = $existingConfig['type'] ?? ($this->product->type ?? 'physical');
                        $configData['is_digital'] = $existingConfig['is_digital'] ?? (($this->product->type ?? 'physical') !== 'physical');
                    }

                    // Alte Snapshots physisch vom Server löschen
                    if (!empty($orderItem->configuration['snapshot_path'])) {
                        $paths = is_array($orderItem->configuration['snapshot_path']) 
                                    ? $orderItem->configuration['snapshot_path'] 
                                    : ['front' => $orderItem->configuration['snapshot_path']];
                        foreach($paths as $path) {
                            $pathStr = (string) $path;
                            $trimmedPath = ltrim($pathStr, '/');
                            if ($pathStr !== '' && !str_contains($pathStr, '..') && !str_contains($pathStr, "\0") && (str_starts_with($trimmedPath, 'snapshots/') || str_starts_with($trimmedPath, 'system/snapshots/')) && \Illuminate\Support\Facades\Storage::disk('public')->exists($pathStr)) {
                                \Illuminate\Support\Facades\Storage::disk('public')->delete($pathStr);
                            }
                        }
                    }

                    // Wenn zuvor ein Foto-Mangel beanstandet wurde, markiere diesen automatisch als nachgebessert!
                    if (!empty($orderItem->configuration['photo_issue'])) {
                        $configData['photo_issue'] = array_merge($orderItem->configuration['photo_issue'], [
                            'resolved' => true,
                            'resolved_at' => now()->toDateTimeString(),
                        ]);

                        // Vermerke die Nachbesserung in den Bestellnotizen
                        if ($orderItem->order) {
                            $orderItem->order->update([
                                'notes' => trim(($orderItem->order->notes ?? '') . "\n[" . now()->format('d.m.Y H:i') . " Kunde]: Foto für " . $orderItem->product_name . " wurde erfolgreich nachgebessert.")
                            ]);
                        }
                    }

                    $orderItem->update([
                        'configuration' => $configData,
                        // Preis- oder Mengenänderungen sind streng verboten in diesem Status.
                    ]);

                    $this->dispatch('notify', message: 'Design erfolgreich aktualisiert!');
                    $this->dispatch('order-item-updated', itemId: $orderItem->id);
                }
            });
        } catch (\Exception $e) {
            SystemLog::create([
                'type' => 'error',
                'agent_id' => null,
                'action_id' => 'configurator_save',
                'message' => 'Fehler beim Speichern der Konfiguration: ' . $e->getMessage(),
                'details' => json_encode([
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'product_id' => $this->product->id ?? null,
                    'context' => $this->context
                ])
            ]);

            session()->flash('error', 'Die Konfiguration konnte nicht gespeichert werden.');
        }
    }

    public function getPreviewImageProperty()
    {
        $pkgKey = $this->configSettings['template_package'] ?? 'custom';
        $packages = app(ConfiguratorService::class)->getTemplatePackages();
        $pkgOverlay = ($pkgKey !== 'custom' && isset($packages[$pkgKey]['preview_image_path']))
            ? $packages[$pkgKey]['preview_image_path']
            : null;

        $path = $this->product->preview_image_path ?? $pkgOverlay ?? collect($this->product->media_gallery)->firstWhere('type', 'image')['path'] ?? null;
        if (!$path) {
            return null;
        }
        $diskPath = storage_path('app/public/' . $path);
        $ver = is_file($diskPath) ? '?v=' . filemtime($diskPath) : '';
        return asset('storage/' . $path) . $ver;
    }

    public function getResolvedBackgroundPathProperty()
    {
        $pkgKey = $this->configSettings['template_package'] ?? 'custom';
        $packages = app(ConfiguratorService::class)->getTemplatePackages();
        $pkgBg = ($pkgKey !== 'custom' && isset($packages[$pkgKey]['three_d_background_path']))
            ? $packages[$pkgKey]['three_d_background_path']
            : null;

        $path = $this->product->three_d_background_path ?? $pkgBg;
        if (!$path) {
            return '';
        }
        $diskPath = storage_path('app/public/' . $path);
        $ver = is_file($diskPath) ? '?v=' . filemtime($diskPath) : '';
        return asset('storage/' . $path) . $ver;
    }

    protected function sanitizeSvg(string $filePath): bool
    {
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return false;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Must contain an <svg tag
        if (!preg_match('/<svg[\s>]/i', $content)) {
            if (file_exists($filePath)) unlink($filePath);
            return false;
        }

        // Strip DOCTYPE and ENTITY declarations to prevent XXE
        $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
        $content = preg_replace('/<!ENTITY[^>]*>/i', '', $content);

        $dom = new \DOMDocument();
        $libxml_flags = LIBXML_NONET | LIBXML_NOENT | LIBXML_NOXMLDECL;
        libxml_use_internal_errors(true);

        if (!$dom->loadXML($content, $libxml_flags)) {
            libxml_clear_errors();
            if (file_exists($filePath)) unlink($filePath);
            return false;
        }
        libxml_clear_errors();

        // Root element MUST be svg
        if (!$dom->documentElement || strtolower($dom->documentElement->tagName) !== 'svg') {
            if (file_exists($filePath)) unlink($filePath);
            return false;
        }

        // 1. Remove dangerous elements (case-insensitive)
        $badElements = [
            'script', 'foreignobject', 'iframe', 'object', 'embed', 'meta', 'link',
            'style', 'handler', 'listener', 'applet', 'form', 'input', 'button',
            'textarea', 'select', 'isindex', 'audio', 'video', 'canvas', 'portal',
            'feimage', 'animate', 'set', 'animatetransform', 'animatemotion', 'base'
        ];

        $xpath = new \DOMXPath($dom);
        $nodesToRemove = [];
        foreach ($xpath->query('//*') as $node) {
            $lowerTag = strtolower($node->localName);
            if (in_array($lowerTag, $badElements, true)) {
                $nodesToRemove[] = $node;
            }
        }
        foreach ($nodesToRemove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        // 2. Remove dangerous attributes across all remaining elements
        $allElements = $xpath->query('//*');

        foreach ($allElements as $element) {
            $attrsToRemove = [];
            foreach ($element->attributes as $attrName => $attrNode) {
                $lowerName = strtolower($attrNode->localName);
                $nodeName = strtolower($attrNode->nodeName);
                $val = trim($attrNode->value);
                $cleanVal = preg_replace('/[\s\x00-\x1F\x7F-\x9F]/u', '', strtolower($val));

                // 2a. Any event handler (on*)
                if (str_starts_with($lowerName, 'on') || str_starts_with($nodeName, 'on')) {
                    $attrsToRemove[] = $attrNode;
                    continue;
                }

                // 2b. Dangerous URI schemes or external references in href/xlink:href/src/action/data
                $isUriAttr = in_array($lowerName, ['href', 'src', 'action', 'formaction', 'data'], true)
                    || str_ends_with($lowerName, ':href')
                    || str_ends_with($nodeName, ':href');

                if ($isUriAttr) {
                    // Only allow local anchor references (e.g. #myGradient, #clipPath)
                    if (!str_starts_with($val, '#')) {
                        $attrsToRemove[] = $attrNode;
                        continue;
                    }
                }

                // 2c. Dangerous style attributes
                if ($lowerName === 'style') {
                    if (preg_match('/(expression|behavior|javascript|vbscript|-moz-binding|@import|url\s*\()/i', $val)) {
                        $attrsToRemove[] = $attrNode;
                        continue;
                    }
                }
            }

            foreach ($attrsToRemove as $node) {
                $element->removeAttributeNode($node);
            }
        }

        $sanitizedXml = $dom->saveXML();
        if (empty($sanitizedXml) || !str_contains($sanitizedXml, '<svg')) {
            if (file_exists($filePath)) unlink($filePath);
            return false;
        }

        file_put_contents($filePath, $sanitizedXml);
        return true;
    }

    public function render()
    {
        return view('livewire.shop.product.product-configurator.product-configurator', [
            'renderedLogos' => $this->renderedLogos
        ]);
    }
}
