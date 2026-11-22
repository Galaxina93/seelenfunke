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
    #[Locked]
    public array $raw_files = [];
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
    public array $quotes = [];
    public array $recommendedQuotes = [];
    public string $quoteOccasionLabel = '';
    public int $totalQuotesCount = 0;
    public array $backgroundThemes = [];
    public string $backgroundTheme = 'standard';
    public string $selectedCategory = 'Allgemein';
    public string $selectedQuoteCategory = '';
    public $design = 'light';

    public $alignmentOptions = [
        'left' => 'Links',
        'center' => 'Zentriert',
        'right' => 'Rechts'
    ];

    public $variantId = null;
    public $variantName = null;
    public array $selectedAddons = [];
    public string $giftWrapMode = 'wrapped'; // 'wrapped' = Fertig verpackt | 'loose' = Papier nur mitliefern
    public array $customFieldValues = [];
    public array $customFieldSides = [];
    #[Locked]
    public array $savedCustomFieldsSnapshot = [];
    public string $effectivePersonalizationMode = 'studio';

    public function mount(ConfiguratorService $configService, $product, $context = 'add', $cartItem = null, $initialData = [], $qty = null)
    {
        $this->fonts = $configService->getFonts();
        $this->vectors = $configService->getStandardVectors();
        $this->backgroundThemes = $configService->getBackgroundThemes();

        if (!empty($this->vectors)) {
            $this->selectedCategory = array_key_first($this->vectors);
        }

        $isStaff = auth()->guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();

        if ($context === 'template_admin' && !$isStaff) {
            abort(403, 'Zugriff auf den Vorlagen-Editor verweigert.');
        }

        if ($context === 'preview' && !$isStaff && !auth()->guard('customer')->check()) {
            abort(403, 'Zugriff auf den Vorschaumodus verweigert.');
        }

        $this->product = ($product instanceof Product) ? $product : Product::findOrFail($product);
        if (!in_array($context, ['template_admin', 'preview'], true) && !$isStaff && $this->product->status !== 'active') {
            abort(404);
        }

        $quoteBundle = $configService->getStorefrontQuotesBundle($this->product);
        $this->quotes = $quoteBundle['grouped'] ?? [];
        $this->recommendedQuotes = $quoteBundle['recommended'] ?? [];
        $this->quoteOccasionLabel = (string) ($quoteBundle['occasion_label'] ?? '');
        $this->totalQuotesCount = (int) ($quoteBundle['total_count'] ?? 0);
        if (!empty($this->quotes)) {
            $this->selectedQuoteCategory = array_key_first($this->quotes);
        }
        $this->configSettings = $configService->mergeWithDefaults($this->product->configurator_settings ?? []);
        $this->context = $context;
        $this->cartItem = $cartItem;

        // IDOR-Schutz: Kunden dürfen nur eigene Warenkorbpositionen im Modus 'edit' öffnen
        if ($this->context !== 'order_edit' && $this->cartItem instanceof \App\Models\Cart\CartItem) {
            $activeCart = app(\App\Services\CartService::class)->getCart(false);
            if (!$isStaff && (!$activeCart || (string) $this->cartItem->cart_id !== (string) $activeCart->id)) {
                abort(403, 'Zugriff auf fremde Warenkorbposition verweigert.');
            }
        }

        // IDOR-Schutz: Kunden dürfen nur eigene Bestellpositionen im Modus 'order_edit' öffnen
        if ($this->context === 'order_edit' && $this->cartItem) {
            if ($isStaff) {
                $this->config_confirmed = true;
            }
            $orderItem = \App\Models\Order\OrderOrderItem::with('order')->find($this->cartItem->id ?? null);
            if ($orderItem && $orderItem->order) {
                $customerId = auth()->guard('customer')->id();
                $isOwner = $customerId && (string) $orderItem->order->customer_id === (string) $customerId;
                if (!$isStaff && !$isOwner) {
                    abort(403, 'Zugriff auf fremde Bestellposition verweigert.');
                }
            } elseif (!$isStaff) {
                abort(403, 'Zugriff auf Bestellposition verweigert.');
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
        unset($this->configSettings['seasonal_images']);

        if ($this->isDigital) {
            $this->configSettings['allow_logo'] = false;
        }

        $coords = $this->getBeginnerPositionCoordinates($this->configSettings);
        $centerX = $coords['center_x'] ?? ($this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2));
        $centerY = $coords['center_y'] ?? ($this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2));
        $studioTextSize = $coords['studio_text_size'] ?? 0.72;

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

        // Dynamische Eingabefelder (Schnell-Personalisierung) initialisieren
        $this->customFieldValues = [];
        $this->customFieldSides = [];
        $this->savedCustomFieldsSnapshot = isset($source['custom_fields_data']) && is_array($source['custom_fields_data'])
            ? array_values($source['custom_fields_data'])
            : [];
        $savedFieldSides = isset($source['custom_field_sides']) && is_array($source['custom_field_sides'])
            ? $source['custom_field_sides']
            : [];

        $hasStudioPayload = !empty($source['texts']) || !empty($source['logos']) || !empty($source['texts_back']) || !empty($source['logos_back']) || !empty($source['snapshot_path']);
        $sourceMode = $source['personalization_mode'] ?? null;

        if ($sourceMode === 'custom_fields' || !empty($this->savedCustomFieldsSnapshot)) {
            $this->effectivePersonalizationMode = 'custom_fields';
        } elseif ($sourceMode === 'studio' || $hasStudioPayload) {
            $this->effectivePersonalizationMode = 'studio';
        } else {
            $this->effectivePersonalizationMode = $this->product->getPersonalizationMode();
        }

        $savedCustomFieldsById = collect($this->savedCustomFieldsSnapshot)->keyBy('id');
        $savedCustomFieldsByLabel = collect($this->savedCustomFieldsSnapshot)->keyBy(fn($r) => mb_strtolower(trim((string) ($r['label'] ?? ''))));
        $customFieldsDef = $this->product->getCustomFieldsDefinition($this->isUsingCustomFieldsMode());

        $legacyTextAssigned = false;
        foreach ($customFieldsDef as $cfIdx => $cfDef) {
            $cfId = $cfDef['id'];
            $cfType = $cfDef['type'] ?? 'text';
            $cfLabelKey = mb_strtolower(trim((string) ($cfDef['label'] ?? '')));
            if ($savedCustomFieldsById->has($cfId)) {
                $val = (string) ($savedCustomFieldsById->get($cfId)['value'] ?? '');
            } elseif ($cfLabelKey !== '' && $savedCustomFieldsByLabel->has($cfLabelKey)) {
                $val = (string) ($savedCustomFieldsByLabel->get($cfLabelKey)['value'] ?? '');
            } elseif (isset($this->savedCustomFieldsSnapshot[$cfIdx]['value'])) {
                $val = (string) $this->savedCustomFieldsSnapshot[$cfIdx]['value'];
            } elseif ($cfType === 'font' && !empty($cfDef['options'])) {
                $val = in_array('Arial', $cfDef['options'], true)
                    ? 'Arial'
                    : (string) ($cfDef['options'][0] ?? 'Arial');
            } elseif (!$legacyTextAssigned && in_array($cfType, ['text', 'textarea'], true) && empty($this->savedCustomFieldsSnapshot) && !empty($source['text'])) {
                $val = (string) $source['text'];
                $legacyTextAssigned = true;
            } elseif ($cfType === 'select' && !empty($cfDef['options']) && !empty($cfDef['required'])) {
                $val = (string) ($cfDef['options'][0] ?? '');
            } else {
                $val = '';
            }

            // Für HTML5 <input type="date"> ein gespeichertes deutsches Datum (TT.MM.JJJJ) zurück in YYYY-MM-DD wandeln
            if ($cfType === 'date' && $this->context !== 'preview' && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($val), $dm)) {
                $val = "{$dm[3]}-{$dm[2]}-{$dm[1]}";
            }

            $this->customFieldValues[$cfId] = $val;

            $hasBackCfg = !empty($this->configSettings['has_back_side']);
            if (!$hasBackCfg || $cfType === 'font') {
                $this->customFieldSides[$cfId] = 'front';
            } elseif (($cfDef['target_side'] ?? 'front') === 'choice') {
                $savedRowForSide = $savedCustomFieldsById->get($cfId)
                    ?? ($cfLabelKey !== '' ? $savedCustomFieldsByLabel->get($cfLabelKey) : null);
                $rawSavedSide = strtolower(trim((string) ($savedRowForSide['side'] ?? ($savedFieldSides[$cfId] ?? ($cfDef['default_side'] ?? 'back')))));
                $this->customFieldSides[$cfId] = ($rawSavedSide === 'front') ? 'front' : 'back';
            } else {
                $this->customFieldSides[$cfId] = (($cfDef['target_side'] ?? 'front') === 'back') ? 'back' : 'front';
            }
        }

        if (isset($source['selected_addons']) && is_array($source['selected_addons'])) {
            $validAddonIds = array_column($this->availableAddons, 'id');
            $this->selectedAddons = array_values(array_intersect(array_map('strval', $source['selected_addons']), $validAddonIds));
        } else {
            $this->selectedAddons = [];
        }

        $restoredWrapMode = (string) ($source['gift_wrap_mode'] ?? '');
        if ($restoredWrapMode === '' && !empty($source['addons_summary']) && is_array($source['addons_summary'])) {
            foreach ($source['addons_summary'] as $savedAddon) {
                if (is_array($savedAddon) && \App\Models\Product\Product::isGiftWrapAddon($savedAddon)) {
                    if (($savedAddon['wrap_mode'] ?? '') === 'loose' || str_contains(mb_strtolower((string) ($savedAddon['name'] ?? '')), 'mitliefern')) {
                        $restoredWrapMode = 'loose';
                    }
                    break;
                }
            }
        }
        $this->giftWrapMode = ($restoredWrapMode === 'loose') ? 'loose' : 'wrapped';
        $this->uploaded_files = is_array($source['files'] ?? null) ? $source['files'] : [];
        $this->raw_files = [];
        if (isset($source['raw_files']) && is_array($source['raw_files'])) {
            foreach ($source['raw_files'] as $procKey => $rawPath) {
                if (!is_string($procKey) || !is_string($rawPath)) {
                    continue;
                }
                $cleanKey = trim($procKey);
                $cleanRaw = trim($rawPath);
                $baseKey = str_ends_with($cleanKey, ':initial') ? substr($cleanKey, 0, -8) : $cleanKey;
                if (
                    $baseKey !== ''
                    && $cleanRaw !== ''
                    && str_starts_with($baseKey, 'bestellungen/cart-uploads/')
                    && str_starts_with($cleanRaw, 'bestellungen/cart-uploads/')
                    && !str_contains($cleanKey, '..')
                    && !str_contains($cleanKey, "\0")
                    && !str_contains($cleanKey, '\\')
                    && !str_contains($cleanRaw, '..')
                    && !str_contains($cleanRaw, "\0")
                    && !str_contains($cleanRaw, '\\')
                ) {
                    $this->raw_files[$cleanKey] = $cleanRaw;
                }
            }
        }
        // Nur für eigene Warenkorb-Positionen (nicht bei Produkt-Vorlagen aus $initialData!) als Session-Eigentum registrieren
        if ((!empty($this->uploaded_files) || !empty($this->raw_files)) && $this->cartItem && empty($initialData)) {
            $this->registerSessionOwnedUploads(array_merge(
                $this->uploaded_files,
                array_values($this->raw_files)
            ));
        }
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
                'size' => $source['text_size'] ?? $studioTextSize,
                'rotation' => 0
            ];
        } else {
            if (!$this->isDigital && $this->context !== 'preview' && empty($source['logos']) && empty($source['logo_path'])) {
                $this->addText($centerX, $centerY, $studioTextSize);
            }
        }

        if (isset($source['texts_back']) && is_array($source['texts_back'])) {
            $this->texts_back = $source['texts_back'];
        }

        if ($this->context === 'preview') {
            $this->texts = array_values(array_filter(
                is_array($this->texts) ? $this->texts : [],
                fn($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== ''
            ));
            $this->texts_back = array_values(array_filter(
                is_array($this->texts_back) ? $this->texts_back : [],
                fn($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== ''
            ));
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
            if (isset($logo['value']) && is_string($logo['value'])) {
                if (Str::startsWith($logo['value'], 'vectors/')) {
                    $logo['url'] = $vectorService->resolveVectorUrl($logo['value']);
                    unset($logo['raw_value']);
                } else {
                    $logo['url'] = asset('storage/' . $logo['value']);
                    if (!empty($logo['raw_value']) && is_string($logo['raw_value']) && str_starts_with($logo['raw_value'], 'bestellungen/cart-uploads/') && !str_contains($logo['raw_value'], '..') && !str_contains($logo['raw_value'], "\0") && !str_contains($logo['raw_value'], '\\')) {
                        if (!isset($this->raw_files[$logo['value']]) && str_starts_with($logo['value'], 'bestellungen/cart-uploads/') && !str_contains($logo['value'], '..')) {
                            $this->raw_files[$logo['value']] = $logo['raw_value'];
                        }
                    } elseif (isset($this->raw_files[$logo['value']])) {
                        $logo['raw_value'] = $this->raw_files[$logo['value']];
                    }
                }
            }
        }
        unset($logo);

        foreach ($this->logos_back as &$logo) {
            if (isset($logo['value']) && is_string($logo['value'])) {
                if (Str::startsWith($logo['value'], 'vectors/')) {
                    $logo['url'] = $vectorService->resolveVectorUrl($logo['value']);
                    unset($logo['raw_value']);
                } else {
                    $logo['url'] = asset('storage/' . $logo['value']);
                    if (!empty($logo['raw_value']) && is_string($logo['raw_value']) && str_starts_with($logo['raw_value'], 'bestellungen/cart-uploads/') && !str_contains($logo['raw_value'], '..') && !str_contains($logo['raw_value'], "\0") && !str_contains($logo['raw_value'], '\\')) {
                        if (!isset($this->raw_files[$logo['value']]) && str_starts_with($logo['value'], 'bestellungen/cart-uploads/') && !str_contains($logo['value'], '..')) {
                            $this->raw_files[$logo['value']] = $logo['raw_value'];
                        }
                    } elseif (isset($this->raw_files[$logo['value']])) {
                        $logo['raw_value'] = $this->raw_files[$logo['value']];
                    }
                }
            }
        }
        unset($logo);

        if (!empty($this->raw_files) && $this->cartItem && empty($initialData)) {
            $this->registerSessionOwnedUploads(array_values($this->raw_files));
        }

        $this->texts = $this->normalizeStageItemsList($this->texts, 'txt');
        $this->texts_back = $this->normalizeStageItemsList($this->texts_back, 'txt');
        $this->logos = $this->normalizeStageItemsList($this->logos, 'logo');
        $this->logos_back = $this->normalizeStageItemsList($this->logos_back, 'logo');

        if ($this->isUsingCustomFieldsMode() && $this->product->usesStudioConfigurator()) {
            $this->syncBeginnerPositionToCanvas($this->context === 'preview');
        }

        $this->calculatePrice();
    }

    public function hydrate(): void
    {
        $this->texts = $this->normalizeStageItemsList($this->texts, 'txt');
        $this->texts_back = $this->normalizeStageItemsList($this->texts_back, 'txt');
        $this->logos = $this->normalizeStageItemsList($this->logos, 'logo');
        $this->logos_back = $this->normalizeStageItemsList($this->logos_back, 'logo');
    }

    protected function normalizeStageItemsList($items, string $prefix = 'item'): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        $seenIds = [];
        foreach (array_values($items) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $candidateId = (isset($entry['id']) && is_scalar($entry['id'])) ? trim((string) $entry['id']) : '';
            if ($candidateId === '' || isset($seenIds[$candidateId])) {
                $candidateId = $prefix . '_' . Str::uuid()->toString();
            }
            $seenIds[$candidateId] = true;
            $entry['id'] = $candidateId;
            $normalized[] = $entry;
        }

        return $normalized;
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

    #[Computed]
    public function availableAddons(): array
    {
        if (!$this->product->isPhysical()) {
            return [];
        }

        return array_map(fn(array $addon) => [
            'id'          => (string) $addon['id'],
            'name'        => (string) $addon['name'],
            'description' => (string) ($addon['description'] ?? ''),
            'price_cents' => (int) ($addon['price_cents'] ?? 0),
            'image_path'  => $addon['image_path'] ?? \App\Models\Product\Product::resolveAddonImagePath($addon),
            'image_url'   => $addon['image_url'] ?? \App\Models\Product\Product::resolveAddonImageUrl($addon),
        ], $this->product->getEffectiveAddons(true));
    }

    public function isUsingCustomFieldsMode(): bool
    {
        return ($this->effectivePersonalizationMode ?: $this->product->getPersonalizationMode()) === 'custom_fields';
    }

    #[Computed]
    public function customFieldsDefinition(): array
    {
        return $this->product->getCustomFieldsDefinition($this->isUsingCustomFieldsMode());
    }

    public function resolveEffectiveCustomFieldSide(array $cfDef): string
    {
        if (empty($this->configSettings['has_back_side']) || ($cfDef['type'] ?? 'text') === 'font') {
            return 'front';
        }

        $targetSide = (string) ($cfDef['target_side'] ?? 'front');
        if ($targetSide === 'choice') {
            $cfId = (string) ($cfDef['id'] ?? '');
            $chosen = strtolower(trim((string) ($this->customFieldSides[$cfId] ?? ($cfDef['default_side'] ?? 'back'))));
            return ($chosen === 'front') ? 'front' : 'back';
        }

        return ($targetSide === 'back') ? 'back' : 'front';
    }

    public function setCustomFieldSide(string $cfId, string $side): void
    {
        $cleanId = trim($cfId);
        if ($cleanId === '') {
            return;
        }

        $cleanSide = ($side === 'back' && !empty($this->configSettings['has_back_side'])) ? 'back' : 'front';
        $this->customFieldSides[$cleanId] = $cleanSide;
        $this->activeSide = $cleanSide;

        if ($this->isUsingCustomFieldsMode() && $this->product->usesStudioConfigurator()) {
            $this->syncBeginnerPositionToCanvas();
        }
    }

    protected function buildCustomFieldsData(bool $validateRequired = true): array
    {
        $definitions = $this->product->getCustomFieldsDefinition($this->isUsingCustomFieldsMode());
        $result = [];
        $hasErrors = false;

        $isOrderEditCustomer = ($this->context === 'order_edit')
            && !auth()->guard('admin')->check()
            && !\App\Services\AI\AiAuthHelper::isAdmin()
            && !\App\Services\AI\AiAuthHelper::isEmployee();

        $savedById = collect($this->savedCustomFieldsSnapshot)->keyBy('id');
        $savedByLabel = collect($this->savedCustomFieldsSnapshot)->keyBy(fn($r) => mb_strtolower(trim((string) ($r['label'] ?? ''))));

        foreach ($definitions as $cfDef) {
            $cfId = $cfDef['id'];
            $fieldType = $cfDef['type'] ?? 'text';
            $rawVal = (string) ($this->customFieldValues[$cfId] ?? '');

            if ($fieldType === 'font' && !empty($cfDef['options'])) {
                $defaultFont = in_array('Arial', $cfDef['options'], true)
                    ? 'Arial'
                    : (string) ($cfDef['options'][0] ?? 'Arial');
                $trimmedRaw = trim($rawVal);
                if ($trimmedRaw !== '' && in_array($trimmedRaw, $cfDef['options'], true)) {
                    $cleanVal = $trimmedRaw;
                } else {
                    $sanitizedCandidate = $this->sanitizeLaserText($trimmedRaw);
                    $cleanVal = in_array($sanitizedCandidate, $cfDef['options'], true)
                        ? $sanitizedCandidate
                        : $defaultFont;
                }
                $this->customFieldValues[$cfId] = $cleanVal;
            } elseif ($fieldType === 'select' && !empty($cfDef['options'])) {
                $trimmedRaw = trim($rawVal);
                if ($trimmedRaw === '') {
                    $cleanVal = '';
                } elseif (in_array($trimmedRaw, $cfDef['options'], true)) {
                    $cleanVal = $trimmedRaw;
                } else {
                    $sanitizedCandidate = $this->sanitizeLaserText($trimmedRaw);
                    $cleanVal = in_array($sanitizedCandidate, $cfDef['options'], true)
                        ? $sanitizedCandidate
                        : (string) ($cfDef['options'][0] ?? '');
                }
            } elseif ($fieldType === 'date') {
                $trimmedDate = trim($rawVal);
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmedDate, $dm)) {
                    $cleanVal = "{$dm[3]}.{$dm[2]}.{$dm[1]}";
                } else {
                    $cleanVal = $this->sanitizeLaserText($trimmedDate);
                }
            } else {
                $normalizedRaw = str_replace(["\r\n", "\r"], "\n", $rawVal);
                $cleanVal = $this->sanitizeLaserText($normalizedRaw);
                $maxLen = max(1, (int) ($cfDef['max_length'] ?? 80));
                if (mb_strlen($cleanVal) > $maxLen) {
                    $cleanVal = mb_substr($cleanVal, 0, $maxLen);
                }
            }

            $surchargeCents = max(0, (int) ($cfDef['surcharge_cents'] ?? 0));
            if ($isOrderEditCustomer && $surchargeCents > 0) {
                $cfLabelKey = mb_strtolower(trim((string) ($cfDef['label'] ?? '')));
                $savedEntry = $savedById->get($cfId) ?? ($cfLabelKey !== '' ? $savedByLabel->get($cfLabelKey) : null);
                $savedVal = is_array($savedEntry) ? trim((string) ($savedEntry['value'] ?? '')) : '';
                $savedWasNeutral = in_array(mb_strtolower($savedVal), ['', 'kein symbol', 'keine', 'nein', '-'], true);
                $wasPreviouslyPaid = is_array($savedEntry) && (((int) ($savedEntry['surcharge_cents'] ?? 0)) > 0 || !$savedWasNeutral);

                if (!$wasPreviouslyPaid) {
                    $cleanVal = $savedVal;
                    $this->customFieldValues[$cfId] = $cleanVal;
                }
            }

            if ($validateRequired && $fieldType !== 'font' && !empty($cfDef['required']) && trim($cleanVal) === '') {
                $this->addError('customFieldValues.' . $cfId, "Bitte fülle das Pflichtfeld „{$cfDef['label']}“ aus.");
                $hasErrors = true;
            }

            $isNeutralOption = in_array(mb_strtolower(trim($cleanVal)), ['', 'kein symbol', 'keine', 'nein', '-'], true);
            $effectiveSurcharge = (!$isNeutralOption && $surchargeCents > 0) ? $surchargeCents : 0;

            $result[] = [
                'id'              => $cfId,
                'label'           => $cfDef['label'],
                'type'            => $fieldType,
                'value'           => $cleanVal,
                'surcharge_cents' => $effectiveSurcharge,
                'side'            => $this->resolveEffectiveCustomFieldSide($cfDef),
                'target_side'     => (string) ($cfDef['target_side'] ?? 'front'),
            ];
        }

        if ($hasErrors) {
            throw ValidationException::withMessages($this->getErrorBag()->messages());
        }

        return $result;
    }

    public function toggleAddon(string $addonId): void
    {
        if ($this->context === 'preview') {
            return;
        }

        $validIds = array_column($this->availableAddons, 'id');
        if (!in_array($addonId, $validIds, true)) {
            return;
        }

        if (in_array($addonId, $this->selectedAddons, true)) {
            $this->selectedAddons = array_values(array_diff($this->selectedAddons, [$addonId]));
            $isEnabled = false;
        } else {
            $this->selectedAddons[] = $addonId;
            $this->selectedAddons = array_values(array_unique($this->selectedAddons));
            $isEnabled = true;
        }

        $this->calculatePrice();

        if (!in_array($this->context, ['template_admin', 'preview', 'order_edit'], true)) {
            $addonMeta = collect($this->availableAddons)->firstWhere('id', $addonId);
            if ($addonMeta) {
                ProductTrackingService::trackAddonToggled(
                    $this->product->id ?? null,
                    (string) $addonMeta['id'],
                    (string) $addonMeta['name'],
                    $isEnabled,
                    ((int) ($addonMeta['price_cents'] ?? 0)) * max(1, (int) $this->qty),
                    $addonMeta['image_path'] ?? null,
                    $this->context === 'calculator' ? 'calculator' : 'configurator'
                );
            }
        }
    }

    public function setGiftWrapMode(string $mode): void
    {
        if ($this->context === 'preview') {
            return;
        }

        $this->giftWrapMode = ($mode === 'loose') ? 'loose' : 'wrapped';
    }

    public function resolveSelectedAddonsSummary(): array
    {
        if (empty($this->selectedAddons)) {
            return [];
        }

        $summary = [];
        foreach ($this->availableAddons as $addon) {
            if (in_array($addon['id'], $this->selectedAddons, true)) {
                $isWrap = \App\Models\Product\Product::isGiftWrapAddon($addon);
                $wrapMode = ($this->giftWrapMode === 'loose') ? 'loose' : 'wrapped';
                $wrapLabel = \App\Models\Product\Product::resolveGiftWrapModeLabel($wrapMode);

                $addonName = $isWrap
                    ? $addon['name'] . ' (' . $wrapLabel . ')'
                    : $addon['name'];

                $addonDesc = $isWrap
                    ? ($wrapMode === 'loose'
                        ? 'Passender Bogen Schwarz-Gold-Geschenkpapier inklusive Verschluss-Sticker lose beigelegt (Gravur vorher selbst begutachten)'
                        : 'Von Hand in edles Schwarz-Gold-Geschenkpapier eingeschlagen, direkt verschenkfertig')
                    : trim((string) ($addon['description'] ?? ''));

                $entry = [
                    'id'          => $addon['id'],
                    'name'        => $addonName,
                    'description' => $addonDesc,
                    'price_cents' => (int) $addon['price_cents'],
                    'image_path'  => $addon['image_path'] ?? null,
                ];

                if ($isWrap) {
                    $entry['wrap_mode'] = $wrapMode;
                }

                $summary[] = $entry;
            }
        }

        return $summary;
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

        $surchargeCents = 0;
        if ($variant && isset($variant['price']) && $variant['price'] !== '') {
            $surchargeCents = (int) round(((float) str_replace(',', '.', (string) $variant['price'])) * 100);
        }
        $displayUnitCents = (int) $this->product->price + $surchargeCents;
        if ($this->product->tax_included === false && !app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness()) {
            $taxRate = (float) ($this->product->tax_rate ?? shop_setting('default_tax_rate', 19.0));
            $displayUnitCents = (int) round($displayUnitCents * (1 + ($taxRate / 100)));
        }

        $fallbackMeta = $this->product->resolveMainImageMeta(null, true);

        $variantMeta = $this->variantId
            ? ($this->product->resolveVariantImageMeta($this->variantId) ?? $fallbackMeta)
            : $fallbackMeta;

        $this->dispatch('variant-selected',
            variantId: $this->variantId,
            variantName: $this->variantName,
            formattedPrice: number_format($displayUnitCents / 100, 2, ',', '.') . ' €',
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

        if (str_starts_with((string) $propertyName, 'customFieldValues') || str_starts_with((string) $propertyName, 'customFieldSides')) {
            $this->resetErrorBag($propertyName);
            if ($this->isUsingCustomFieldsMode() && $this->product->usesStudioConfigurator()) {
                $this->syncBeginnerPositionToCanvas();
            }
            $this->calculatePrice();
        }

        if ($propertyName === 'is_express') {
            $this->calculatePrice();
            ProductTrackingService::trackExpressToggled(
                $this->product->id ?? null,
                filter_var($this->is_express, FILTER_VALIDATE_BOOLEAN),
                (int) round($this->expressFee * 100),
                $this->context === 'calculator' ? 'calculator' : 'configurator'
            );
        }

        // ... (Restlicher File-Upload Code bleibt gleich)
        if ($propertyName === 'new_files') {
            if ($this->isBlockedByShopMaintenance()) {
                $this->addError('new_files', 'Wir führen gerade kurze Wartungsarbeiten durch. Das Gestalten und Bestellen ist in Kürze wieder freigeschaltet.');
                $this->reset('new_files');
                return;
            }

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
            $newRawPaths = [];
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

                    // 100 % unberührte Originaldatei (exakte Original-Bytes ohne Skalierung/Schärfung/Graustufen) dauerhaft sichern
                    $rawRelPath = $file->storeAs('bestellungen/cart-uploads', $safeUuid . '_raw.' . $extension, 'public');
                    if (!$rawRelPath || !Storage::disk('public')->exists($rawRelPath)) {
                        $this->addError('new_files.' . $key, "Die Originaldatei konnte nicht gespeichert werden.");
                        continue;
                    }
                    $fullRawPath = Storage::disk('public')->path($rawRelPath);

                    $newFileName = $this->applyLaserEffect($fullRawPath, $extension);
                    if (!$newFileName) {
                        if (Storage::disk('public')->exists($rawRelPath)) {
                            Storage::disk('public')->delete($rawRelPath);
                        }
                        $this->addError('new_files.' . $key, "Die Bilddatei konnte nicht verarbeitet werden.");
                        continue;
                    }

                    $finalPath = 'bestellungen/cart-uploads/' . $newFileName;
                    $this->raw_files[$finalPath] = $rawRelPath;
                    $this->uploaded_files[] = $finalPath;
                    $newPaths[] = $finalPath;
                    $newRawPaths[] = $rawRelPath;
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
                $this->registerSessionOwnedUploads(array_merge($newPaths, $newRawPaths));
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

        $maxDim = 1200;
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

        // Auf dunklen Oberflächen (z. B. schwarz eloxiertes Aluminium beim Seelenanhänger) legt der Laser
        // helles Silber-Weiß frei (und die Vorschau nutzt brightness(0) invert(1)).
        // Damit Halbton-Fotos dort positiv erscheinen (helle Gesichtspartien = silber-weiß, dunkle Schatten = schwarz)
        // und nicht als Röntgen-Negativ, kehren wir für echte Fotos auf dunklem Untergrund die Gravur-Intensität um.
        $overlayAnalysis = app(\App\Services\ConfiguratorService::class)->analyzeOverlayImage(
            $this->configSettings['overlay_image_path'] ?? ($this->product->preview_image_path ?? null),
            $this->configSettings ?? []
        );
        $isDarkSurface = !empty($overlayAnalysis['is_dark_surface']);
        $isPositivePhotoOnDark = false;

        if ($isDarkSurface) {
            $totalSampled = 0;
            $transparentSampled = 0;
            $opaqueCount = 0;
            $opaqueLumSum = 0.0;
            $stepX = max(1, (int) floor($width / 80));
            $stepY = max(1, (int) floor($height / 80));

            for ($sx = 0; $sx < $width; $sx += $stepX) {
                for ($sy = 0; $sy < $height; $sy += $stepY) {
                    $totalSampled++;
                    $c = imagecolorat($image, $sx, $sy);
                    $a = ($c >> 24) & 0x7F;
                    if ($a >= 105) {
                        $transparentSampled++;
                    } else {
                        $opaqueCount++;
                        $sr = ($c >> 16) & 0xFF;
                        $sg = ($c >> 8) & 0xFF;
                        $sb = $c & 0xFF;
                        $opaqueLumSum += ($sr * 0.299) + ($sg * 0.587) + ($sb * 0.114);
                    }
                }
            }

            $transparentRatio = $totalSampled > 0 ? ($transparentSampled / $totalSampled) : 0.0;
            $avgOpaqueLum = $opaqueCount > 0 ? ($opaqueLumSum / $opaqueCount) : 128.0;
            $isBlackSilhouetteCutout = ($transparentRatio > 0.15 && $avgOpaqueLum < 65.0);

            if (!$isBlackSilhouetteCutout) {
                $isPositivePhotoOnDark = true;
            }
        }

        if ($isPositivePhotoOnDark) {
            // Für echte Porträt-/Halbtonfotos auf Glas & dunklen Oberflächen:
            // Sanfte Mikrokontrast-/Kantenschärfung (Haare, Augen, Konturen) + Schutz vor überstrahlten
            // Gesichtsflächen (Stirn/Wangen/Hände), damit sie im Negativ nicht als schwarze Blöcke zulaufen.
            if (function_exists('imageconvolution')) {
                $sharpenMatrix = [
                    [ 0.0, -0.6,  0.0],
                    [-0.6,  3.4, -0.6],
                    [ 0.0, -0.6,  0.0],
                ];
                @imageconvolution($image, $sharpenMatrix, 1.0, 0.0);
            }
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

                if ($isPositivePhotoOnDark) {
                    // Positiv-Mapping für Glas & dunkle Eloxal-/Schiefer-Oberflächen:
                    // Helle Hautpartien sanft abrollen (Gamma 0.85, Mindest-Alpha 18 statt 10),
                    // damit Stirn, Wangen und Hände feine Binnenzeichnung behalten.
                    $normLum = max(0.0, min(1.0, $luminance / 255.0));
                    $curvedLum = pow($normLum, 0.85);
                    $targetAlpha = 123 - (int) round($curvedLum * 105);
                } else {
                    $engravingIntensity = 255 - $luminance;
                    $targetAlpha = 127 - ($engravingIntensity / 2);
                }

                $finalAlpha = (int) max(0, min(127, max($alpha, $targetAlpha)));

                imagesetpixel($laserImage, $x, $y, $alphaLut[$finalAlpha]);
            }
        }

        $inputFilename = pathinfo($filePath, PATHINFO_FILENAME);
        if (preg_match('/^(.+)_raw$/i', $inputFilename, $rawMatch)) {
            $newPath = dirname($filePath) . '/' . $rawMatch[1] . '_proc.png';
        } else {
            $newPath = preg_replace('/\.(jpg|jpeg|webp|png)$/i', '.png', $filePath);
            if ($newPath === $filePath) {
                $newPath = dirname($filePath) . '/' . $inputFilename . '_proc.png';
            }
        }
        $savedOk = @imagepng($laserImage, $newPath, 9);

        // NIEMALS eine _raw-Originaldatei physisch löschen!
        $isRawSource = (bool) preg_match('/_raw\.[a-z0-9]+$/i', basename($filePath));
        if (!$isRawSource && file_exists($filePath) && $filePath !== $newPath) {
            @unlink($filePath);
        }

        imagedestroy($image);
        imagedestroy($laserImage);

        if (!$savedOk || !is_file($newPath)) {
            return null;
        }

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

        $initialRawPath = $this->raw_files[$oldValue . ':initial']
            ?? $this->raw_files[$oldValue]
            ?? null;
        if ($initialRawPath === null) {
            foreach (array_merge($this->logos ?? [], $this->logos_back ?? []) as $existingLogo) {
                if (is_array($existingLogo) && ($existingLogo['value'] ?? '') === $oldValue && !empty($existingLogo['raw_value']) && is_string($existingLogo['raw_value'])) {
                    $initialRawPath = $existingLogo['raw_value'];
                    break;
                }
            }
        }

        $extMap = [
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => 'webp',
        ];
        $ext = $extMap[$imageInfo[2]] ?? 'png';
        $safeUuid = (string) Str::uuid();
        $newRawRelPath = 'bestellungen/cart-uploads/' . $safeUuid . '_raw.' . $ext;

        // Farbiges Filerobot-Ergebnis (vor applyLaserEffect!) dauerhaft als _raw sichern
        Storage::disk('public')->put($newRawRelPath, $decoded);
        $fullRawPath = Storage::disk('public')->path($newRawRelPath);

        $newFileName = $this->applyLaserEffect($fullRawPath, $ext);
        if (!$newFileName) {
            if (Storage::disk('public')->exists($newRawRelPath)) {
                Storage::disk('public')->delete($newRawRelPath);
            }
            return null;
        }

        $finalPath = 'bestellungen/cart-uploads/' . $newFileName;
        $finalUrl = asset('storage/' . $finalPath);

        $this->raw_files[$finalPath] = $newRawRelPath;
        if (
            is_string($initialRawPath)
            && $initialRawPath !== ''
            && str_starts_with($initialRawPath, 'bestellungen/cart-uploads/')
            && !str_contains($initialRawPath, '..')
            && !str_contains($initialRawPath, "\0")
            && !str_contains($initialRawPath, '\\')
        ) {
            $this->raw_files[$finalPath . ':initial'] = $initialRawPath;
        }
        if ($oldValue !== $finalPath) {
            unset($this->raw_files[$oldValue], $this->raw_files[$oldValue . ':initial']);
        }

        $toRegister = [$finalPath, $newRawRelPath];
        if (is_string($initialRawPath) && $initialRawPath !== '') {
            $toRegister[] = $initialRawPath;
        }
        $this->registerSessionOwnedUploads($toRegister);

        foreach ($this->uploaded_files as $idx => $uf) {
            if ($uf === $oldValue) {
                $this->uploaded_files[$idx] = $finalPath;
            }
        }

        foreach ($this->logos as &$l) {
            if (($l['value'] ?? '') === $oldValue) {
                $l['value'] = $finalPath;
                $l['url'] = $finalUrl;
                $l['raw_value'] = $newRawRelPath;
            }
        }
        unset($l);

        foreach ($this->logos_back as &$lb) {
            if (($lb['value'] ?? '') === $oldValue) {
                $lb['value'] = $finalPath;
                $lb['url'] = $finalUrl;
                $lb['raw_value'] = $newRawRelPath;
            }
        }
        unset($lb);

        // Nur die alte verarbeitete Vorschaudatei löschen, NIEMALS eine _raw-Originaldatei!
        $oldIsRaw = (bool) preg_match('/_raw\.[a-z0-9]+$/i', basename($oldValue));
        if (!$oldIsRaw && $oldValue !== $finalPath && Storage::disk('public')->exists($oldValue)) {
            Storage::disk('public')->delete($oldValue);
        }

        return [
            'value'     => $finalPath,
            'url'       => $finalUrl,
            'raw_value' => $newRawRelPath,
        ];
    }

    public function saveCroppedLogo(string $oldValue, string $base64Data, ?string $originalValue = null): ?array
    {
        if ($this->context === 'preview') {
            return null;
        }

        try {
            $this->rateLimit(30, 60);
        } catch (TooManyRequestsException $e) {
            return null;
        }

        $oldValue = trim($oldValue);
        $originalValue = $originalValue ? trim($originalValue) : $oldValue;

        if ($oldValue === '' || str_contains($oldValue, '..') || str_contains($oldValue, "\0")) {
            return null;
        }
        if ($originalValue === '' || str_contains($originalValue, '..') || str_contains($originalValue, "\0")) {
            return null;
        }

        $ownedUploads = session('configurator_owned_uploads', []);
        if (!is_array($ownedUploads)) {
            $ownedUploads = [];
        }
        $isStaff = auth()->guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();

        $isAllowedSource = str_starts_with($oldValue, 'vectors/')
            || (str_starts_with($oldValue, 'bestellungen/cart-uploads/') && ($isStaff || in_array($oldValue, $ownedUploads, true) || in_array($oldValue, $this->uploaded_files ?? [], true)));

        $isAllowedOriginal = str_starts_with($originalValue, 'vectors/')
            || (str_starts_with($originalValue, 'bestellungen/cart-uploads/') && ($isStaff || in_array($originalValue, $ownedUploads, true) || in_array($originalValue, $this->uploaded_files ?? [], true)));

        if (!$isAllowedSource || !$isAllowedOriginal) {
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

        $gdImg = @imagecreatefromstring($decoded);
        if (!$gdImg) {
            return null;
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($gdImg);
        }
        imagealphablending($gdImg, false);
        imagesavealpha($gdImg, true);

        $safeUuid = (string) Str::uuid();
        $finalPath = 'bestellungen/cart-uploads/' . $safeUuid . '_crop.png';
        $fullOutPath = Storage::disk('public')->path($finalPath);

        $dir = dirname($fullOutPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        imagepng($gdImg, $fullOutPath, 9);
        imagedestroy($gdImg);

        if (!is_file($fullOutPath)) {
            return null;
        }

        $finalUrl = asset('storage/' . $finalPath);
        $sourceRaw = $this->raw_files[$originalValue]
            ?? $this->raw_files[$oldValue]
            ?? null;
        $initialRaw = $this->raw_files[$originalValue . ':initial']
            ?? $this->raw_files[$oldValue . ':initial']
            ?? $sourceRaw;

        if (is_string($sourceRaw) && $sourceRaw !== '') {
            $this->raw_files[$finalPath] = $sourceRaw;
        }
        if (is_string($initialRaw) && $initialRaw !== '' && $initialRaw !== $sourceRaw) {
            $this->raw_files[$finalPath . ':initial'] = $initialRaw;
        }

        $toRegister = [$finalPath];
        if (
            str_starts_with($originalValue, 'bestellungen/cart-uploads/')
            && ($isStaff || in_array($originalValue, $ownedUploads, true) || in_array($originalValue, $this->uploaded_files ?? [], true))
        ) {
            $toRegister[] = $originalValue;
        }
        if (is_string($sourceRaw) && $sourceRaw !== '') {
            $toRegister[] = $sourceRaw;
        }
        if (is_string($initialRaw) && $initialRaw !== '') {
            $toRegister[] = $initialRaw;
        }
        $this->registerSessionOwnedUploads($toRegister);

        $foundInUploaded = false;
        foreach ($this->uploaded_files as $idx => $uf) {
            if ($uf === $oldValue) {
                $this->uploaded_files[$idx] = $finalPath;
                $foundInUploaded = true;
            }
        }
        if (!$foundInUploaded && str_starts_with($finalPath, 'bestellungen/cart-uploads/')) {
            $this->uploaded_files[] = $finalPath;
        }
        $this->uploaded_files = array_values(array_unique($this->uploaded_files));

        // Wenn $oldValue bereits ein eigener Zwischen-Zuschnitt war (und in keinem Template genutzt wird), Zwischen-Datei aufräumen
        if (
            $oldValue !== $finalPath
            && $oldValue !== $originalValue
            && str_starts_with($oldValue, 'bestellungen/cart-uploads/')
            && str_ends_with($oldValue, '_crop.png')
            && in_array($oldValue, $ownedUploads, true)
            && !app(\App\Services\CartFileCleanupService::class)->isFileUsedInTemplates(basename($oldValue))
            && Storage::disk('public')->exists($oldValue)
        ) {
            unset($this->raw_files[$oldValue], $this->raw_files[$oldValue . ':initial']);
            Storage::disk('public')->delete($oldValue);
        }

        return [
            'value'     => $finalPath,
            'url'       => $finalUrl,
            'raw_value' => $sourceRaw,
        ];
    }

    public function restoreOriginalLogo(string $currentValue, string $originalValue): ?array
    {
        if ($this->context === 'preview') {
            return null;
        }

        $currentValue = trim($currentValue);
        $originalValue = trim($originalValue);
        if ($originalValue === '' || str_contains($originalValue, '..') || str_contains($originalValue, "\0")) {
            return null;
        }

        $ownedUploads = session('configurator_owned_uploads', []);
        if (!is_array($ownedUploads)) {
            $ownedUploads = [];
        }

        $isAllowedOriginal = str_starts_with($originalValue, 'vectors/')
            || (str_starts_with($originalValue, 'bestellungen/cart-uploads/') && (in_array($originalValue, $ownedUploads, true) || in_array($originalValue, $this->uploaded_files ?? [], true)));

        if (!$isAllowedOriginal) {
            return null;
        }

        if (str_starts_with($originalValue, 'bestellungen/cart-uploads/') && !Storage::disk('public')->exists($originalValue)) {
            return null;
        }

        foreach ($this->uploaded_files as $idx => $uf) {
            if ($uf === $currentValue) {
                if (str_starts_with($originalValue, 'bestellungen/cart-uploads/')) {
                    $this->uploaded_files[$idx] = $originalValue;
                } else {
                    unset($this->uploaded_files[$idx]);
                }
            }
        }
        $this->uploaded_files = array_values(array_unique($this->uploaded_files));

        if (
            $currentValue !== $originalValue
            && str_starts_with($currentValue, 'bestellungen/cart-uploads/')
            && str_ends_with($currentValue, '_crop.png')
            && in_array($currentValue, $ownedUploads, true)
            && !app(\App\Services\CartFileCleanupService::class)->isFileUsedInTemplates(basename($currentValue))
            && Storage::disk('public')->exists($currentValue)
        ) {
            unset($this->raw_files[$currentValue], $this->raw_files[$currentValue . ':initial']);
            Storage::disk('public')->delete($currentValue);
        }

        $resolvedUrl = str_starts_with($originalValue, 'vectors/')
            ? app(\App\Services\ProductConfiguratorService::class)->resolveVectorUrl($originalValue)
            : asset('storage/' . $originalValue);

        return [
            'value' => $originalValue,
            'url'   => $resolvedUrl,
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

    protected function isBlockedByShopMaintenance(): bool
    {
        if (!filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (!in_array($this->context, ['add', 'edit', 'calculator'], true)) {
            return false;
        }

        if ($this->context === 'edit' && auth()->guard('admin')->check()) {
            $activeSessionCart = app(CartService::class)->getCart(false);
            if ($this->cartItem && (!$activeSessionCart || (string) $this->cartItem->cart_id !== (string) $activeSessionCart->id)) {
                return false;
            }
        }

        return true;
    }

    public function saveWithSnapshot($snapshotData, CartService $cartService, ?array $syncedStageState = null)
    {
        if ($this->context === 'preview') return;

        $isAdminOrderEdit = $this->context === 'order_edit'
            && (auth()->guard('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin());
        if ($isAdminOrderEdit) {
            $this->config_confirmed = true;
        }

        if (!$isAdminOrderEdit && $this->isBlockedByShopMaintenance()) {
            $msg = 'Wir führen gerade kurze Wartungsarbeiten durch. Das Gestalten und Bestellen ist in Kürze wieder freigeschaltet.';
            $this->addError('maintenance', $msg);
            session()->flash('error', $msg);
            return;
        }

        if ($this->context !== 'calculator' && !$isAdminOrderEdit && !$this->config_confirmed) {
            $this->addError('config_confirmed', 'Bitte setze noch diesen Haken zur Bestätigung deiner Angaben, um fortzufahren.');
            return;
        }

        if (is_array($syncedStageState)) {
            if (isset($syncedStageState['texts']) && is_array($syncedStageState['texts'])) {
                $this->texts = $syncedStageState['texts'];
            }
            if (isset($syncedStageState['logos']) && is_array($syncedStageState['logos'])) {
                $this->logos = $syncedStageState['logos'];
            }
            if (isset($syncedStageState['texts_back']) && is_array($syncedStageState['texts_back'])) {
                $this->texts_back = $syncedStageState['texts_back'];
            }
            if (isset($syncedStageState['logos_back']) && is_array($syncedStageState['logos_back'])) {
                $this->logos_back = $syncedStageState['logos_back'];
            }
        }

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

        $isAdminOrderEdit = $this->context === 'order_edit'
            && (auth()->guard('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin());
        if ($isAdminOrderEdit) {
            $this->config_confirmed = true;
        }

        if (!$isAdminOrderEdit && $this->isBlockedByShopMaintenance()) {
            $msg = 'Wir führen gerade kurze Wartungsarbeiten durch. Das Gestalten und Bestellen ist in Kürze wieder freigeschaltet.';
            $this->addError('maintenance', $msg);
            session()->flash('error', $msg);
            return;
        }

        if ($this->context !== 'calculator' && !$isAdminOrderEdit && !$this->config_confirmed) {
            $this->addError('config_confirmed', 'Bitte bestätigen Sie Ihre Angaben.');
            return;
        }

        if ($this->context !== 'template_admin') {
            $this->validate(['qty' => 'required|integer|min:1']);
        }

        $customFieldsData = [];
        if ($this->isUsingCustomFieldsMode()) {
            $validateReq = !in_array($this->context, ['template_admin', 'calculator'], true);
            $customFieldsData = $this->buildCustomFieldsData($validateReq);
            if ($this->product->usesStudioConfigurator()) {
                $this->syncBeginnerPositionToCanvas(true);
            }
        }

        try {
            DB::transaction(function () use ($cartService, $snapshotPath, $customFieldsData) {
                $cleanTexts = array_values(array_filter(array_map(function ($t) {
                    if (is_array($t) && isset($t['text'])) {
                        $t['text'] = $this->sanitizeLaserText($t['text']);
                    }
                    if (is_array($t)) {
                        $t['curve'] = isset($t['curve']) && is_numeric($t['curve'])
                            ? max(-100, min(100, (int) round((float) $t['curve'])))
                            : 0;
                    }
                    return $t;
                }, $this->texts ?? []), fn($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== ''));

                $cleanTextsBack = array_values(array_filter(array_map(function ($t) {
                    if (is_array($t) && isset($t['text'])) {
                        $t['text'] = $this->sanitizeLaserText($t['text']);
                    }
                    if (is_array($t)) {
                        $t['curve'] = isset($t['curve']) && is_numeric($t['curve'])
                            ? max(-100, min(100, (int) round((float) $t['curve'])))
                            : 0;
                    }
                    return $t;
                }, $this->texts_back ?? []), fn($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== ''));

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
                        if ($val !== '' && !str_starts_with($val, 'vectors/')) {
                            $ownedUploads = (array) session('configurator_owned_uploads', []);
                            $isStaff = auth()->guard('admin')->check()
                                || \App\Services\AI\AiAuthHelper::isAdmin()
                                || \App\Services\AI\AiAuthHelper::isEmployee();
                            $allowedCartUpload = str_starts_with($val, 'bestellungen/cart-uploads/')
                                && ($isStaff || in_array($val, $ownedUploads, true) || in_array($val, $this->uploaded_files, true) || app(\App\Services\CartFileCleanupService::class)->isFileUsedInTemplates(basename($val)));
                            if (!$allowedCartUpload && !in_array($val, $this->uploaded_files, true)) {
                                continue;
                            }
                        }
                        if ($val !== '' && empty($l['url'])) {
                            $l['url'] = str_starts_with($val, 'vectors/')
                                ? app(\App\Services\ProductConfiguratorService::class)->resolveVectorUrl($val)
                                : asset('storage/' . ltrim($val, '/'));
                        }
                        if (str_starts_with($val, 'vectors/')) {
                            unset($l['raw_value']);
                        } elseif ($val !== '') {
                            $mappedRaw = $this->raw_files[$val]
                                ?? ((!empty($l['original_value']) && is_string($l['original_value'])) ? ($this->raw_files[$l['original_value']] ?? null) : null)
                                ?? null;
                            if (
                                is_string($mappedRaw)
                                && str_starts_with($mappedRaw, 'bestellungen/cart-uploads/')
                                && !str_contains($mappedRaw, '..')
                                && !str_contains($mappedRaw, "\0")
                                && !str_contains($mappedRaw, '\\')
                            ) {
                                $l['raw_value'] = $mappedRaw;
                            } elseif (!empty($l['raw_value']) && is_string($l['raw_value'])) {
                                $rv = trim($l['raw_value']);
                                $ownedUploads = (array) session('configurator_owned_uploads', []);
                                $isStaff = auth()->guard('admin')->check()
                                    || \App\Services\AI\AiAuthHelper::isAdmin()
                                    || \App\Services\AI\AiAuthHelper::isEmployee();
                                if (
                                    str_starts_with($rv, 'bestellungen/cart-uploads/')
                                    && !str_contains($rv, '..')
                                    && !str_contains($rv, "\0")
                                    && !str_contains($rv, '\\')
                                    && ($isStaff || in_array($rv, $ownedUploads, true) || in_array($rv, array_values($this->raw_files), true) || app(\App\Services\CartFileCleanupService::class)->isFileUsedInTemplates(basename($rv)))
                                ) {
                                    $l['raw_value'] = $rv;
                                    $this->raw_files[$val] = $rv;
                                } else {
                                    unset($l['raw_value']);
                                }
                            } else {
                                unset($l['raw_value']);
                            }
                        }
                        $cleaned[] = $l;
                    }
                    return $cleaned;
                };

                $this->logos = $sanitizeLogos($this->logos);
                $this->logos_back = $sanitizeLogos($this->logos_back);

                $mainLogo = !empty($this->logos) ? $this->logos[0]['value'] : null;
                $mainText = collect($cleanTexts)->firstWhere('text', '!=', '')['text'] ?? '';

                if ($this->isUsingCustomFieldsMode()) {
                    $summaryParts = [];
                    $chosenCustomFont = 'Arial';
                    foreach ($customFieldsData as $cfd) {
                        if (($cfd['type'] ?? 'text') === 'font' && trim((string) ($cfd['value'] ?? '')) !== '') {
                            $chosenCustomFont = trim((string) $cfd['value']);
                            break;
                        }
                    }

                    $customLaserTextsFront = [];
                    $customLaserTextsBack = [];
                    $hasBackCfgForSummary = !empty($this->configSettings['has_back_side']);
                    foreach ($customFieldsData as $cfd) {
                        $cfdVal = trim((string) ($cfd['value'] ?? ''));
                        if ($cfdVal !== '') {
                            $cfdSide = ($hasBackCfgForSummary && ($cfd['side'] ?? 'front') === 'back') ? 'back' : 'front';
                            $cfdType = (string) ($cfd['type'] ?? 'text');
                            $labelForSummary = (string) ($cfd['label'] ?? '');
                            $labelLowerSummary = mb_strtolower($labelForSummary);
                            if ($hasBackCfgForSummary && $cfdType !== 'font') {
                                if ($cfdSide === 'back' && !str_contains($labelLowerSummary, 'rückseite')) {
                                    $labelForSummary .= ' (Rückseite)';
                                } elseif ($cfdSide === 'front' && ($cfd['target_side'] ?? 'front') === 'choice' && !str_contains($labelLowerSummary, 'vorderseite')) {
                                    $labelForSummary .= ' (Vorderseite)';
                                }
                            }
                            $summaryParts[] = $labelForSummary . ': ' . $cfdVal;
                            if ($cfdType !== 'font' && !in_array(mb_strtolower($cfdVal), ['kein symbol', 'keine', 'nein', '-'], true)) {
                                if ($cfdSide === 'back') {
                                    $customLaserTextsBack[] = $cfdVal;
                                } else {
                                    $customLaserTextsFront[] = $cfdVal;
                                }
                            }
                        }
                    }
                    $mainText = implode(' · ', $summaryParts);

                    if ($this->product->usesStudioConfigurator()) {
                        // Im Neulings-Modus auf Studio-Produkten hat syncBeginnerPositionToCanvas(true) bereits
                        // die exakte Smart-Fit-Position für Vorder- und Rückseite gesetzt.
                        $mainLogo = !empty($this->logos) ? $this->logos[0]['value'] : (!empty($this->logos_back) ? $this->logos_back[0]['value'] : null);
                    } else {
                        if (!empty($customLaserTextsFront)) {
                            $cleanTexts = [[
                                'id' => Str::uuid()->toString(),
                                'text' => implode("\n", $customLaserTextsFront),
                                'font' => $chosenCustomFont,
                                'align' => 'center',
                                'x' => 50,
                                'y' => 50,
                                'size' => 1.0,
                                'rotation' => 0,
                            ]];
                        }
                        if (!empty($customLaserTextsBack)) {
                            $cleanTextsBack = [[
                                'id' => Str::uuid()->toString(),
                                'text' => implode("\n", $customLaserTextsBack),
                                'font' => $chosenCustomFont,
                                'align' => 'center',
                                'x' => 50,
                                'y' => 50,
                                'size' => 1.0,
                                'rotation' => 0,
                            ]];
                        }
                    }
                }

                $hasBackContent = !empty($this->configSettings['has_back_side']) && (!empty($cleanTextsBack) || !empty($this->logos_back));
                if (!$hasBackContent) {
                    $cleanTextsBack = [];
                    $this->logos_back = [];
                }

                $finalSnapshotPath = !empty($snapshotPath) ? $snapshotPath : $this->snapshot_path;
                if (is_array($finalSnapshotPath)) {
                    foreach ($finalSnapshotPath as $k => $p) {
                        if (is_string($p) && str_starts_with($p, 'snapshots/')) {
                            $finalSnapshotPath[$k] = 'system/' . $p;
                        }
                    }
                    if (!$hasBackContent && isset($finalSnapshotPath['back'])) {
                        $orphanBack = $finalSnapshotPath['back'];
                        if (is_string($orphanBack) && $orphanBack !== '' && Storage::disk('public')->exists($orphanBack)) {
                            @Storage::disk('public')->delete($orphanBack);
                        }
                        unset($finalSnapshotPath['back']);
                    }
                } elseif (is_string($finalSnapshotPath) && str_starts_with($finalSnapshotPath, 'snapshots/')) {
                    $finalSnapshotPath = 'system/' . $finalSnapshotPath;
                }

                $activeUploadRefs = is_array($this->uploaded_files) ? $this->uploaded_files : [];
                foreach (array_merge($this->logos, $this->logos_back) as $stageLogo) {
                    if (!empty($stageLogo['value']) && is_string($stageLogo['value'])) {
                        $activeUploadRefs[] = $stageLogo['value'];
                    }
                    if (!empty($stageLogo['original_value']) && is_string($stageLogo['original_value'])) {
                        $activeUploadRefs[] = $stageLogo['original_value'];
                    }
                }
                $activeUploadRefs = array_values(array_unique($activeUploadRefs));

                $cleanRawFiles = [];
                foreach ($this->raw_files as $rk => $rv) {
                    if (!is_string($rk) || !is_string($rv)) {
                        continue;
                    }
                    $baseRk = str_ends_with($rk, ':initial') ? substr($rk, 0, -8) : $rk;
                    if (
                        $baseRk !== ''
                        && $rv !== ''
                        && in_array($baseRk, $activeUploadRefs, true)
                        && str_starts_with($baseRk, 'bestellungen/cart-uploads/')
                        && str_starts_with($rv, 'bestellungen/cart-uploads/')
                        && !str_contains($rk, '..')
                        && !str_contains($rk, "\0")
                        && !str_contains($rk, '\\')
                        && !str_contains($rv, '..')
                        && !str_contains($rv, "\0")
                        && !str_contains($rv, '\\')
                    ) {
                        $cleanRawFiles[$rk] = $rv;
                    }
                }

                $configData = [
                    'personalization_mode' => $this->isUsingCustomFieldsMode() ? 'custom_fields' : $this->product->getPersonalizationMode(),
                    'custom_fields_data' => $customFieldsData,
                    'custom_field_sides' => $this->customFieldSides,
                    'texts' => $cleanTexts,
                    'logos' => $this->logos,
                    'texts_back' => $cleanTextsBack,
                    'logos_back' => $this->logos_back,
                    'text' => $mainText,
                    'logo_path' => $mainLogo,
                    'files' => $this->uploaded_files,
                    'raw_files' => $cleanRawFiles,
                    'notes' => $this->notes,
                    'type' => $this->type,
                    'is_digital' => $this->isDigital,
                    'variant_id' => $this->variantId,
                    'variant_name' => $this->variantName,
                    'selected_addons' => array_values($this->selectedAddons),
                    'gift_wrap_mode' => ($this->giftWrapMode === 'loose') ? 'loose' : 'wrapped',
                    'addons_summary' => $this->resolveSelectedAddonsSummary(),
                    'snapshot_path' => $finalSnapshotPath,
                    'is_express' => $this->is_express,
                    'background_theme' => $this->backgroundTheme,
                ];

                if ($this->context !== 'template_admin') {
                    $configData['qty'] = $this->qty;
                }

                if ($this->context === 'add') {
                    $cartService->addItem($this->product, $this->qty, $configData);
                    $this->dispatch('cart-updated', openPreview: false);
                    $this->dispatch('notify', message: 'In den Warenkorb gelegt!');

                    $addonsSummary = is_array($configData['addons_summary'] ?? null) ? $configData['addons_summary'] : [];
                    $addonsUnitCents = array_sum(array_map(fn($a) => max(0, (int) ($a['price_cents'] ?? 0)), $addonsSummary));
                    $addonsRevenueCents = $addonsUnitCents * max(1, (int) $this->qty);

                    ProductTrackingService::trackConfiguratorSaved(
                        $this->product->id ?? null,
                        (int) round($this->totalPrice ?? 0),
                        [
                            'qty' => $this->qty,
                            'is_express' => filter_var($this->is_express, FILTER_VALIDATE_BOOLEAN),
                            'symbols_count' => count($this->logos) + count($this->logos_back),
                            'files_count' => count($this->uploaded_files),
                            'selected_addons' => $configData['selected_addons'] ?? [],
                            'addons_summary' => $addonsSummary,
                            'addons_revenue_cents' => $addonsRevenueCents,
                        ]
                    );

                    // Konfiguration im Vorschaufenster beibehalten, damit das Bild beim Hinzufügen in den Warenkorb ruhig & harmonisch bleibt
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
                    if (!auth()->guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
                        abort(403);
                    }
                    $this->dispatch('save-template-data', configData: $configData, previewImagePath: $this->product->preview_image_path);
                } elseif ($this->context === 'order_edit') {
                    $orderItem = \App\Models\Order\OrderOrderItem::with('order')->find($this->cartItem->id ?? null);

                    if (!$orderItem || !$orderItem->order) {
                        session()->flash('error', 'Bestellposition nicht gefunden.');
                        return;
                    }

                    $isAdmin = auth()->guard('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin();
                    $customerId = auth()->guard('customer')->id();
                    $isOwner = $customerId && $orderItem->order->customer_id === $customerId;

                    if (!$isAdmin && !$isOwner) {
                        session()->flash('error', 'Keine Berechtigung zur Bearbeitung dieser Bestellposition.');
                        return;
                    }

                    $orderStatus = (string) ($orderItem->order->status ?? '');
                    if ($isAdmin) {
                        if (in_array($orderStatus, ['shipped', 'completed', 'cancelled', 'refunded'], true)) {
                            session()->flash('error', 'Versendete, abgeschlossene oder stornierte Bestellungen können nicht mehr geändert werden.');
                            return;
                        }
                    } elseif ($orderStatus !== 'pending') {
                        session()->flash('error', 'Die Bestellung wird bereits bearbeitet. Änderungen sind nicht mehr möglich.');
                        return;
                    }

                    $configData = $cartService->sanitizeConfiguration($this->product, $configData) ?? $configData;
                    $existingConfig = is_array($orderItem->configuration) ? $orderItem->configuration : [];

                    // Alle bestehenden Zusatz-Metadaten der Bestellposition (z. B. shipping_checklist, manufacturing_checks, raw_files) bewahren
                    foreach (['shipping_checklist', 'order_packing_checks', 'manufacturing_checks', 'raw_files', 'laser_photo_adjustments'] as $preserveKey) {
                        if (array_key_exists($preserveKey, $existingConfig) && !array_key_exists($preserveKey, $configData)) {
                            $configData[$preserveKey] = $existingConfig[$preserveKey];
                        }
                    }

                    if (!$isAdmin) {
                        // Kunden dürfen im Nachbesserungsmodus ('order_edit') ausschließlich Gestaltungselemente ändern,
                        // niemals jedoch preis- oder leistungsrelevante Parameter (Variante, Aufpreis-Extras, Express-Status, Menge, Produkttyp).
                        $configData['variant_id'] = $existingConfig['variant_id'] ?? null;
                        $configData['variant_name'] = $existingConfig['variant_name'] ?? null;
                        $configData['selected_addons'] = $existingConfig['selected_addons'] ?? [];
                        $configData['addons_summary'] = $existingConfig['addons_summary'] ?? [];
                        $configData['is_express'] = $existingConfig['is_express'] ?? (bool) ($orderItem->order->is_express ?? false);
                        $configData['qty'] = (int) ($existingConfig['qty'] ?? $orderItem->quantity ?? 1);
                        $configData['type'] = $existingConfig['type'] ?? ($this->product->type ?? 'physical');
                        $configData['is_digital'] = $existingConfig['is_digital'] ?? (($this->product->type ?? 'physical') !== 'physical');
                    }

                    // Chronologische Blueprint-Timeline aufbauen: Vorherigen Entwurf inkl. Snapshot-Pfaden archivieren (Snapshots bleiben für den Vergleich erhalten!)
                    $history = is_array($existingConfig['configuration_history'] ?? null)
                        ? array_values(array_filter($existingConfig['configuration_history'], 'is_array'))
                        : [];
                    $nextHistVersion = empty($history)
                        ? 1
                        : (max(array_map(static fn (array $h): int => (int) ($h['version'] ?? 0), $history)) + 1);
                    $prevAdj = is_array($existingConfig['last_admin_adjustment'] ?? null) ? $existingConfig['last_admin_adjustment'] : null;
                    $prevActorType = $prevAdj !== null ? 'admin' : 'customer';
                    $prevActorLabel = $prevAdj !== null
                        ? (trim((string) ($prevAdj['adjusted_by'] ?? '')) ?: 'Mein Seelenfunke Admin')
                        : ($nextHistVersion === 1 ? 'Original-Entwurf beim Kauf' : 'Kunden-Anpassung');
                    $prevSavedAt = ($prevAdj !== null && !empty($prevAdj['adjusted_at']))
                        ? (string) $prevAdj['adjusted_at']
                        : ($orderItem->created_at ? $orderItem->created_at->toIso8601String() : now()->toIso8601String());

                    $history[] = [
                        'version'            => $nextHistVersion,
                        'actor_type'         => $prevActorType,
                        'actor_label'        => $prevActorLabel,
                        'saved_at'           => $prevSavedAt,
                        'config_fingerprint' => (string) ($orderItem->config_fingerprint ?? ''),
                        'snapshot_path'      => $existingConfig['snapshot_path'] ?? null,
                        'texts'              => $existingConfig['texts'] ?? [],
                        'texts_back'         => $existingConfig['texts_back'] ?? [],
                        'logos'              => $existingConfig['logos'] ?? [],
                        'logos_back'         => $existingConfig['logos_back'] ?? [],
                    ];
                    $configData['configuration_history'] = $history;

                    $newCurrentVersion = $nextHistVersion + 1;
                    $nowIso = now()->toIso8601String();
                    if ($isAdmin) {
                        $configData['last_admin_adjustment'] = [
                            'adjusted_at' => $nowIso,
                            'adjusted_by' => 'Mein Seelenfunke Admin',
                            'version'     => $newCurrentVersion,
                        ];
                    } else {
                        unset($configData['last_admin_adjustment']);
                    }

                    // Falls im schnellen Speichern kein neuer Snapshot mitkam, bisherigen Snapshot als Fallback behalten
                    if (empty($configData['snapshot_path']) && !empty($existingConfig['snapshot_path'])) {
                        $configData['snapshot_path'] = $existingConfig['snapshot_path'];
                    }

                    // Wenn zuvor ein Foto-Mangel beanstandet wurde, markiere diesen automatisch als nachgebessert!
                    if (!empty($orderItem->configuration['photo_issue'])) {
                        $configData['photo_issue'] = array_merge($orderItem->configuration['photo_issue'], [
                            'resolved' => true,
                            'resolved_at' => now()->toDateTimeString(),
                        ]);

                        // Vermerke die Nachbesserung in den Bestellnotizen
                        if ($orderItem->order) {
                            $actorNoteLabel = $isAdmin ? 'Mein Seelenfunke Admin' : 'Kunde';
                            $orderItem->order->update([
                                'notes' => trim(($orderItem->order->notes ?? '') . "\n[" . now()->format('d.m.Y H:i') . " {$actorNoteLabel}]: Foto/Design für " . $orderItem->product_name . " wurde aktualisiert.")
                            ]);
                        }
                    }

                    $fingerprintPayload = $configData;
                    unset($fingerprintPayload['configuration_history'], $fingerprintPayload['last_admin_adjustment'], $fingerprintPayload['snapshot_path']);

                    $orderItem->update([
                        'configuration' => $configData,
                        'config_fingerprint' => hash('sha256', json_encode($fingerprintPayload) ?: Str::random(16)),
                        // Preis- oder Mengenänderungen sind streng verboten in diesem Status.
                    ]);

                    $this->saved = true;
                    $this->dispatch('notify', message: $isAdmin ? 'Manufaktur-Änderung gespeichert & in Timeline protokolliert!' : 'Design erfolgreich aktualisiert!');
                    $this->dispatch('order-item-updated', itemId: $orderItem->id);
                }
            });
        } catch (\Throwable $e) {
            SystemLog::create([
                'title' => 'Konfigurator Speicherfehler',
                'type' => 'system',
                'status' => 'error',
                'action_id' => 'configurator_save',
                'message' => 'Fehler beim Speichern der Konfiguration: ' . $e->getMessage(),
                'payload' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace_excerpt' => mb_substr($e->getTraceAsString(), 0, 2000),
                    'product_id' => $this->product->id ?? null,
                    'context' => $this->context,
                ],
            ]);

            session()->flash('error', 'Die Konfiguration konnte nicht gespeichert werden.');
        }
    }

    public function getExpressFeeProperty(): float
    {
        $expressCents = max(0, (int) round(($this->totalPrice ?? 0) - (($this->currentPrice ?? 0) * max(1, (int) $this->qty))));
        return $expressCents / 100;
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

        $sanitizedXml = \App\Support\SafeHtmlAndSvgSanitizer::sanitizeSvg($content, true);
        if ($sanitizedXml === '' || !str_contains($sanitizedXml, '<svg')) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
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
