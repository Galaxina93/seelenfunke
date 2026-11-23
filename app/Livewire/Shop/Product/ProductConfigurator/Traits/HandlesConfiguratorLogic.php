<?php

namespace App\Livewire\Shop\Product\ProductConfigurator\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Services\ProductTrackingService;
use Livewire\Attributes\Renderless;

trait HandlesConfiguratorLogic
{
    public function calculatePrice()
    {
        $basePrice = $this->product->price;

        // 1. Varianten-Aufpreis addieren, falls vorhanden
        if ($this->variantId) {
            $variants = $this->product->variants_data ?? [];
            $variant = collect($variants)->firstWhere('id', $this->variantId);

            // Wenn die Variante gefunden wurde UND einen Aufpreis hinterlegt hat
            if ($variant && isset($variant['price']) && $variant['price'] !== '') {
                $surchargeCents = (int) round(((float) str_replace(',', '.', (string) $variant['price'])) * 100);
                $basePrice += $surchargeCents;
            }
        }

        // 2. Mengenrabatt (Staffelpreise) anwenden
        $relationTiers = $this->product->tierPrices ?? null;
        $tierPricing = ($relationTiers instanceof \Illuminate\Support\Collection && $relationTiers->isNotEmpty())
            ? $relationTiers
            : ($this->product->tier_pricing ?? []);

        if (!empty($tierPricing)) {
            if ($tierPricing instanceof \Illuminate\Support\Collection) {
                $tier = $tierPricing->where('qty', '<=', $this->qty)->sortByDesc('qty')->first();
                if ($tier) {
                    $basePrice -= ($basePrice * ($tier->percent / 100));
                }
            } elseif (is_array($tierPricing)) {
                usort($tierPricing, fn($a, $b) => ($b['qty'] ?? 0) <=> ($a['qty'] ?? 0));
                foreach ($tierPricing as $tier) {
                    if ($this->qty >= ($tier['qty'] ?? PHP_INT_MAX)) {
                        $basePrice -= ($basePrice * (($tier['percent'] ?? 0) / 100));
                        break;
                    }
                }
            }
        }

        $basePrice = (int) round($basePrice);

        // 3. Steuer aufschlagen (falls Netto-Eingabe und keine Kleinunternehmerregelung)
        if ($this->product->tax_included === false && !(bool) shop_setting('is_small_business', false)) {
            $taxRate = (float) ($this->product->tax_rate ?? shop_setting('default_tax_rate', 19.0));
            $basePrice = (int) round($basePrice * (1 + ($taxRate / 100)));
        }

        // 4. Optionale Aufpreis-Extras / Verpackungs-Upgrades & Bundle-Optionen pro Stück addieren
        if (property_exists($this, 'selectedAddons') && !empty($this->selectedAddons) && method_exists($this, 'resolveSelectedAddonsSummary')) {
            foreach ($this->resolveSelectedAddonsSummary() as $addonSummary) {
                $basePrice += max(0, (int) ($addonSummary['price_cents'] ?? 0));
            }
        }

        // 5. Optionale Feld-Aufpreise aus der Schnell-Personalisierung (custom_fields) pro Stück addieren
        $usesCustomFieldsPricing = method_exists($this, 'isUsingCustomFieldsMode')
            ? $this->isUsingCustomFieldsMode()
            : $this->product->usesCustomFieldsPersonalization();
        if ($usesCustomFieldsPricing && property_exists($this, 'customFieldValues') && is_array($this->customFieldValues)) {
            foreach ($this->product->getCustomFieldsDefinition(true) as $cfDef) {
                $surcharge = max(0, (int) ($cfDef['surcharge_cents'] ?? 0));
                if ($surcharge <= 0) {
                    continue;
                }
                $rawVal = trim((string) ($this->customFieldValues[$cfDef['id']] ?? ''));
                $isNeutralOption = in_array(mb_strtolower($rawVal), ['', 'kein symbol', 'keine', 'nein', '-'], true);
                if (!$isNeutralOption) {
                    $basePrice += $surcharge;
                }
            }
        }

        $this->currentPrice = $basePrice;

        $expressFee = 0;
        $isExpressVal = property_exists($this, 'is_express') ? filter_var($this->is_express, FILTER_VALIDATE_BOOLEAN) : false;
        
        if ($isExpressVal) {
            if (!\App\Services\Order\ShopCapacityService::isExpressAvailable()) {
                // Ab Drossel 3 (Level >= 3) Express-Fertigung deaktivieren
                $this->is_express = false;
            } else {
                $expressPercent = (float) shop_setting('express_surcharge_percent', 20.0);
                $expressMin = (int) shop_setting('express_surcharge_min', 500);
                $calculatedExpress = (int) round(($basePrice * $this->qty) * ($expressPercent / 100));
                $expressFee = max($expressMin, $calculatedExpress);
            }
        }

        $this->totalPrice = ($basePrice * $this->qty) + $expressFee;
    }

    public function addText($x = null, $y = null, $size = null)
    {
        if ($this->context === 'preview') return;
        $coords = $this->getBeginnerPositionCoordinates();
        $spawn = $this->resolveStudioSpawnCenter();
        $centerX = $spawn['x'];
        $centerY = $spawn['y'];
        $defaultSize = $size !== null ? (float) $size : (float) ($coords['studio_text_size'] ?? 0.72);

        $newItem = [
            'id' => Str::uuid()->toString(),
            'text' => '',
            'font' => 'Arial',
            'align' => 'center',
            'x' => $x ?? $centerX,
            'y' => $y ?? $centerY,
            'size' => $defaultSize,
            'rotation' => 0,
            'curve' => 0,
        ];

        // Prüft, welche Seite aktiv ist
        if ($this->activeSide === 'back') {
            $this->texts_back = array_values(is_array($this->texts_back) ? $this->texts_back : []);
            $this->texts_back[] = $newItem;
        } else {
            $this->texts = array_values(is_array($this->texts) ? $this->texts : []);
            $this->texts[] = $newItem;
        }
    }

    public function removeText($index)
    {
        if ($this->context === 'preview') return;

        // Array dynamisch ansteuern (auch das letzte Textfeld darf vollständig gelöscht werden)
        if ($this->activeSide === 'back') {
            unset($this->texts_back[$index]);
            $this->texts_back = array_values($this->texts_back);
        } else {
            unset($this->texts[$index]);
            $this->texts = array_values($this->texts);
        }
    }

    protected function clearUnusedEmptyTextsOnActiveSide(): void
    {
        $textProp = $this->activeSide === 'back' ? 'texts_back' : 'texts';
        $current = is_array($this->$textProp ?? null) ? $this->$textProp : [];
        if (empty($current)) {
            return;
        }

        $hasNonEmpty = collect($current)->contains(function ($t) {
            return is_array($t) && trim((string) ($t['text'] ?? '')) !== '';
        });

        if (!$hasNonEmpty) {
            $this->$textProp = [];
        }
    }

    protected function resolveNextTopStageZIndex(): int
    {
        $textProp = $this->activeSide === 'back' ? 'texts_back' : 'texts';
        $logoProp = $this->activeSide === 'back' ? 'logos_back' : 'logos';
        $texts = is_array($this->$textProp ?? null) ? array_values($this->$textProp) : [];
        $logos = is_array($this->$logoProp ?? null) ? array_values($this->$logoProp) : [];

        $maxZ = 30;
        foreach ($texts as $idx => $t) {
            if (!is_array($t)) {
                continue;
            }
            $z = (isset($t['zIndex']) && is_numeric($t['zIndex'])) ? (int) round((float) $t['zIndex']) : (30 + $idx);
            if ($z > $maxZ) {
                $maxZ = $z;
            }
        }
        foreach ($logos as $idx => $l) {
            if (!is_array($l)) {
                continue;
            }
            $z = (isset($l['zIndex']) && is_numeric($l['zIndex'])) ? (int) round((float) $l['zIndex']) : (10 + $idx);
            if ($z > $maxZ) {
                $maxZ = $z;
            }
        }

        return min(95, $maxZ + 1);
    }

    /**
     * @return array{x: float, y: float}
     */
    protected function resolveNonOverlappingLogoSpawn(float $baseX, float $baseY): array
    {
        $logoProp = $this->activeSide === 'back' ? 'logos_back' : 'logos';
        $logos = is_array($this->$logoProp ?? null) ? array_values($this->$logoProp) : [];

        $hasExactOverlap = false;
        foreach ($logos as $l) {
            if (!is_array($l)) {
                continue;
            }
            $lx = isset($l['x']) && is_numeric($l['x']) ? (float) $l['x'] : $baseX;
            $ly = isset($l['y']) && is_numeric($l['y']) ? (float) $l['y'] : $baseY;
            if (hypot($lx - $baseX, $ly - $baseY) < 1.5) {
                $hasExactOverlap = true;
                break;
            }
        }

        if (!$hasExactOverlap) {
            return ['x' => $baseX, 'y' => $baseY];
        }

        $stepCount = max(1, min(4, count($logos)));
        $offsets = [
            [3.2 * $stepCount, 2.8 * $stepCount],
            [-3.2 * $stepCount, 2.8 * $stepCount],
            [3.2 * $stepCount, -2.8 * $stepCount],
            [-3.2 * $stepCount, -2.8 * $stepCount],
        ];

        $cfg = is_array($this->configSettings ?? null)
            ? $this->configSettings
            : (is_array($this->product->configurator_settings ?? null) ? $this->product->configurator_settings : []);
        $shape = (string) ($cfg['area_shape'] ?? 'rect');
        $pts = is_array($cfg['custom_points'] ?? null) ? $cfg['custom_points'] : [];

        foreach ($offsets as [$dx, $dy]) {
            $cx = round(max(6.0, min(94.0, $baseX + $dx)), 2);
            $cy = round(max(6.0, min(94.0, $baseY + $dy)), 2);

            if ($shape === 'custom' && count($pts) >= 3) {
                if ($this->isStagePointInsideCustomPolygon($pts, $cx, $cy)) {
                    return ['x' => $cx, 'y' => $cy];
                }
                continue;
            }

            $left = (float) ($cfg['area_left'] ?? 10.0);
            $top = (float) ($cfg['area_top'] ?? 10.0);
            $width = max(8.0, (float) ($cfg['area_width'] ?? 80.0));
            $height = max(8.0, (float) ($cfg['area_height'] ?? 80.0));

            if ($shape === 'circle') {
                $midX = $left + ($width * 0.5);
                $midY = $top + ($height * 0.5);
                $rx = max(4.0, $width * 0.45);
                $ry = max(4.0, $height * 0.45);
                $norm = ((($cx - $midX) / $rx) ** 2) + ((($cy - $midY) / $ry) ** 2);
                if ($norm <= 1.0) {
                    return ['x' => $cx, 'y' => $cy];
                }
                continue;
            }

            if ($cx >= ($left + 2.0) && $cx <= ($left + $width - 2.0) && $cy >= ($top + 2.0) && $cy <= ($top + $height - 2.0)) {
                return ['x' => $cx, 'y' => $cy];
            }
        }

        return ['x' => $baseX, 'y' => $baseY];
    }

    protected function isStagePointInsideCustomPolygon(array $pts, float $px, float $py): bool
    {
        $inside = false;
        $n = count($pts);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) ($pts[$i]['x'] ?? 50.0);
            $yi = (float) ($pts[$i]['y'] ?? 50.0);
            $xj = (float) ($pts[$j]['x'] ?? 50.0);
            $yj = (float) ($pts[$j]['y'] ?? 50.0);
            $intersect = (($yi > $py) !== ($yj > $py))
                && ($px < ($xj - $xi) * ($py - $yi) / (($yj - $yi) ?: 1e-7) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    public function addStandardVector($filename, $x = null, $y = null)
    {
        if ($this->context === 'preview') return;
        $rawFilename = ltrim(str_replace('\\', '/', trim((string) $filename)), '/');
        if ($rawFilename === '' || str_contains($rawFilename, '..') || str_contains($rawFilename, "\0")) {
            return;
        }

        $vectorService = app(\App\Services\ProductConfiguratorService::class);
        $canonicalRel = $vectorService->resolveCanonicalVectorRelPath($rawFilename);
        if ($canonicalRel === null) {
            return;
        }

        $spawn = $this->resolveStudioSpawnCenter();
        if (is_numeric($x) && is_numeric($y)) {
            $centerX = round((float) $x, 2);
            $centerY = round((float) $y, 2);
        } else {
            $pos = $this->resolveNonOverlappingLogoSpawn(
                is_numeric($x) ? round((float) $x, 2) : $spawn['x'],
                is_numeric($y) ? round((float) $y, 2) : $spawn['y']
            );
            $centerX = $pos['x'];
            $centerY = $pos['y'];
        }

        $url = $vectorService->resolveVectorUrl($canonicalRel);
        $nextZ = $this->resolveNextTopStageZIndex();
        $defaultVectorSize = $this->resolveDefaultStudioLogoSize(true);

        $newItem = [
            'id' => Str::uuid()->toString(),
            'type' => 'vector',
            'value' => 'vectors/' . $canonicalRel,
            'url' => $url,
            'x' => $centerX,
            'y' => $centerY,
            'size' => $defaultVectorSize,
            'rotation' => 0,
            'zIndex' => $nextZ,
        ];

        if ($this->activeSide === 'back') {
            $this->logos_back = array_values(is_array($this->logos_back) ? $this->logos_back : []);
            $this->logos_back[] = $newItem;
        } else {
            $this->logos = array_values(is_array($this->logos) ? $this->logos : []);
            $this->logos[] = $newItem;
        }

        $this->clearUnusedEmptyTextsOnActiveSide();

        ProductTrackingService::trackSymbolAdded(
            $this->product->id ?? null,
            $canonicalRel,
            $this->selectedCategory ?? null,
            $this->activeSide ?? 'front'
        );
    }

    #[Renderless]
    public function trackSymbolAddedSilent(string $filename, ?string $category = null): void
    {
        if ($this->context === 'preview') {
            return;
        }

        $rawFilename = ltrim(str_replace('\\', '/', trim($filename)), '/');
        if ($rawFilename === '' || str_contains($rawFilename, '..') || str_contains($rawFilename, "\0")) {
            return;
        }

        $canonicalRel = app(\App\Services\ProductConfiguratorService::class)->resolveCanonicalVectorRelPath($rawFilename);
        if ($canonicalRel === null) {
            return;
        }

        $cleanCat = ($category !== null && trim($category) !== '') ? mb_substr(trim($category), 0, 64) : ($this->selectedCategory ?? null);

        ProductTrackingService::trackSymbolAdded(
            $this->product->id ?? null,
            $canonicalRel,
            $cleanCat,
            $this->activeSide ?? 'front'
        );
    }

    public function toggleLogo($type, $value)
    {
        if ($this->context === 'preview') return;
        $value = (string) $value;
        if ($value === '' || str_contains($value, '..') || str_contains($value, "\0")) {
            return;
        }

        // Bestimme das aktive Array
        $targetProperty = $this->activeSide === 'back' ? 'logos_back' : 'logos';
        $this->$targetProperty = array_values(is_array($this->$targetProperty) ? $this->$targetProperty : []);

        // Existiert das Logo auf der AKTUELLEN Seite schon? Dann entfernen.
        foreach ($this->$targetProperty as $key => $logo) {
            if (($logo['value'] ?? '') == $value) {
                unset($this->$targetProperty[$key]);
                $this->$targetProperty = array_values($this->$targetProperty);
                return;
            }
        }

        // Sicherheitscheck: Nur eigene hochgeladene Dateien oder Standard-Vektoren zulassen
        if (!in_array($value, $this->uploaded_files ?? [], true) && !str_starts_with($value, 'vectors/')) {
            return;
        }

        $spawn = $this->resolveStudioSpawnCenter();
        $centerX = $spawn['x'];
        $centerY = $spawn['y'];

        $isVectorLogo = str_starts_with($value, 'vectors/');
        $resolvedUrl = $isVectorLogo
            ? app(\App\Services\ProductConfiguratorService::class)->resolveVectorUrl($value)
            : asset('storage/' . $value);
        $defaultLogoSize = $this->resolveDefaultStudioLogoSize($isVectorLogo);

        // Ansonsten zur aktuellen Seite hinzufügen
        $logoEntry = [
            'id' => Str::uuid()->toString(),
            'type' => $isVectorLogo ? 'vector' : 'saved',
            'value' => $value,
            'url' => $resolvedUrl,
            'x' => $centerX,
            'y' => $centerY,
            'size' => $defaultLogoSize,
            'rotation' => 0
        ];
        if (!str_starts_with($value, 'vectors/')) {
            $rawForValue = $this->resolveRawFileForProcessedPath($value);
            if ($rawForValue !== null) {
                $logoEntry['raw_value'] = $rawForValue;
            }
        }
        $this->$targetProperty[] = $logoEntry;

        $this->clearUnusedEmptyTextsOnActiveSide();
    }

    protected function resolveRawFileForProcessedPath(string $path): ?string
    {
        if (!property_exists($this, 'raw_files') || !is_array($this->raw_files)) {
            return null;
        }
        $raw = $this->raw_files[$path] ?? null;
        if (
            is_string($raw)
            && $raw !== ''
            && str_starts_with($raw, 'bestellungen/cart-uploads/')
            && !str_contains($raw, '..')
            && !str_contains($raw, "\0")
            && !str_contains($raw, '\\')
        ) {
            return $raw;
        }
        return null;
    }

    protected function addFilesToStage($newPaths = null)
    {
        $spawn = $this->resolveStudioSpawnCenter();
        $centerX = $spawn['x'];
        $centerY = $spawn['y'];

        $targetProperty = $this->activeSide === 'back' ? 'logos_back' : 'logos';
        $this->$targetProperty = array_values(is_array($this->$targetProperty) ? $this->$targetProperty : []);

        $pathsToProcess = is_array($newPaths) ? $newPaths : $this->uploaded_files;

        $addedAny = false;
        foreach($pathsToProcess as $path) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {

                // Nur auf der aktuellen Seite prüfen
                $exists = collect($this->$targetProperty)->contains('value', $path);

                if (!$exists) {
                    $logoEntry = [
                        'id' => Str::uuid()->toString(),
                        'type' => 'saved',
                        'value' => $path,
                        'url' => asset('storage/' . $path),
                        'x' => $centerX,
                        'y' => $centerY,
                        'size' => 130,
                        'rotation' => 0
                    ];
                    $rawForPath = $this->resolveRawFileForProcessedPath($path);
                    if ($rawForPath !== null) {
                        $logoEntry['raw_value'] = $rawForPath;
                    }
                    $this->$targetProperty[] = $logoEntry;
                    $addedAny = true;
                }
            }
        }
        $this->$targetProperty = array_values($this->$targetProperty);
        if ($addedAny) {
            $this->clearUnusedEmptyTextsOnActiveSide();
        }
    }

    public function removeFile($index)
    {
        if ($this->context === 'preview') return;
        $path = $this->uploaded_files[$index] ?? null;
        if (!$path) return;

        // WICHTIG: Wenn die Datei gelöscht wird, entfernen wir sie sicherheitshalber von BEIDEN Seiten!
        $this->logos = collect($this->logos)
            ->filter(fn($l) => $l['value'] !== $path)
            ->values()
            ->toArray();

        $this->logos_back = collect($this->logos_back)
            ->filter(fn($l) => $l['value'] !== $path)
            ->values()
            ->toArray();

        unset($this->uploaded_files[$index]);
        $this->uploaded_files = array_values($this->uploaded_files);

        $rawToCleanup = [];
        if (property_exists($this, 'raw_files') && is_array($this->raw_files)) {
            foreach ([$path, $path . ':initial'] as $rk) {
                if (!empty($this->raw_files[$rk]) && is_string($this->raw_files[$rk])) {
                    $rawToCleanup[] = $this->raw_files[$rk];
                }
                unset($this->raw_files[$rk]);
            }
        }

        // WICHTIG: Pfad validieren & nur Session-eigene (nirgends in Orders/Quotes/Carts/Templates genutzte) Dateien physisch löschen
        $ownedUploads = (array) session('configurator_owned_uploads', []);
        $isStaff = auth()->guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();
        $cleanupService = app(\App\Services\CartFileCleanupService::class);

        $pathsToDelete = array_values(array_unique(array_merge([$path], $rawToCleanup)));
        $remainingRawRefs = (property_exists($this, 'raw_files') && is_array($this->raw_files))
            ? array_values($this->raw_files)
            : [];

        foreach ($pathsToDelete as $candidatePath) {
            if (in_array($candidatePath, $remainingRawRefs, true) || in_array($candidatePath, $this->uploaded_files, true)) {
                continue;
            }
            $base = basename((string) $candidatePath);
            if (
                is_string($candidatePath)
                && str_starts_with($candidatePath, 'bestellungen/cart-uploads/')
                && !str_contains($candidatePath, '..')
                && ($isStaff || in_array($candidatePath, $ownedUploads, true))
                && !$cleanupService->isFileUsedInTemplates($base)
                && !$cleanupService->isFileUsedInOrders($base)
                && !$cleanupService->isFileUsedInQuotes($base)
                && !$cleanupService->isFileUsedInCarts($base)
            ) {
                if (Storage::disk('public')->exists($candidatePath)) {
                    Storage::disk('public')->delete($candidatePath);
                }
            }
        }

        if ($this->context === 'laser_freehand' && method_exists($this, 'syncFreehandStageSilent')) {
            $this->syncFreehandStageSilent();
            $this->dispatch('laser-freehand-files-updated', projectId: $this->laserStudioProjectId ?? null);
        }
    }

    protected function registerSessionOwnedUploads(array $paths): void
    {
        $owned = session('configurator_owned_uploads', []);
        if (!is_array($owned)) {
            $owned = [];
        }
        foreach ($paths as $p) {
            if (is_string($p) && str_starts_with($p, 'bestellungen/cart-uploads/') && !str_contains($p, '..') && !in_array($p, $owned, true)) {
                $owned[] = $p;
            }
        }
        if (count($owned) > 120) {
            $owned = array_slice($owned, -120);
        }
        session(['configurator_owned_uploads' => $owned]);
    }

    public function restoreDraftFiles(array $files): void
    {
        if ($this->context === 'preview') return;

        $isStaff = auth()->guard('admin')->check()
            || \App\Services\AI\AiAuthHelper::isAdmin()
            || \App\Services\AI\AiAuthHelper::isEmployee();
        $ownedUploads = session('configurator_owned_uploads', []);
        if (!is_array($ownedUploads)) {
            $ownedUploads = [];
        }

        $validFiles = [];
        foreach (array_slice($files, 0, 10) as $file) {
            if (!is_string($file) || str_contains($file, '..') || str_contains($file, "\0")) {
                continue;
            }
            if (!$isStaff && !in_array($file, $ownedUploads, true) && !in_array($file, $this->uploaded_files ?? [], true)) {
                continue;
            }
            if (str_starts_with($file, 'bestellungen/cart-uploads/') && Storage::disk('public')->exists($file)) {
                $validFiles[] = $file;
            }
        }
        $this->uploaded_files = array_values(array_unique($validFiles));
    }

    public function isLogoActive($path)
    {
        // Highlight in der UI nur, wenn es auf der *aktiven* Seite liegt
        $targetArray = $this->activeSide === 'back' ? $this->logos_back : $this->logos;
        return collect($targetArray)->contains('value', $path);
    }

    public function getRenderedLogosProperty()
    {
        $rendered = [];
        $vectorService = app(\App\Services\ProductConfiguratorService::class);
        foreach ($this->logos as $logo) {
            if (Str::startsWith($logo['value'], 'vectors/')) {
                $url = $vectorService->resolveVectorUrl($logo['value']);
            } else {
                $url = asset('storage/' . $logo['value']);
            }
            $rendered[] = array_merge($logo, ['url' => $url]);
        }
        return $rendered;
    }

    /**
     * Ermittelt die ideale Startkoordinate für neu im Studio-Modus eingefügte Elemente (Texte, Symbole, Bilder).
     * Bei rect/circle exakt identisch zu getBeginnerPositionCoordinates(); bei custom der optische 2D-Flächenschwerpunkt.
     *
     * @return array{x: float, y: float, inscribedWidthPct: float, inscribedHeightPct: float}
     */
    public function resolveStudioSpawnCenter(?array $settings = null): array
    {
        $cfg = is_array($settings) && !empty($settings)
            ? $settings
            : (is_array($this->configSettings ?? null) && !empty($this->configSettings)
                ? $this->configSettings
                : (is_array($this->product->configurator_settings ?? null) ? $this->product->configurator_settings : []));

        if (($cfg['area_shape'] ?? 'rect') === 'custom' && !empty($cfg['custom_points']) && is_array($cfg['custom_points']) && count($cfg['custom_points']) >= 3) {
            return $this->computeCustomPolygonVisualCenter($cfg['custom_points']);
        }

        $coords = $this->getBeginnerPositionCoordinates($cfg);
        $fallbackX = (float) ($cfg['area_left'] ?? 10) + ((float) ($cfg['area_width'] ?? 80) / 2.0);
        $fallbackY = (float) ($cfg['area_top'] ?? 10) + ((float) ($cfg['area_height'] ?? 80) / 2.0);

        return [
            'x'                  => (float) ($coords['center_x'] ?? $fallbackX),
            'y'                  => (float) ($coords['center_y'] ?? $fallbackY),
            'inscribedWidthPct'  => (float) ($coords['area_width_pct'] ?? ($cfg['area_width'] ?? 80.0)),
            'inscribedHeightPct' => (float) ($cfg['area_height'] ?? 80.0),
        ];
    }

    /**
     * Berechnet den optischen 2D-Flächenschwerpunkt eines Freiform-Polygons (Gaußsche Trapezformel / Shoelace)
     * inklusive Scanline-Prüfung gegen konkave Kerben (z. B. Herz-Einkerbung oben oder Pfoten-Durchbruch seitlich),
     * ohne bestehende gespeicherte Element-Koordinaten oder getBeginnerPositionCoordinates() zu verändern.
     *
     * @param array<int, mixed> $rawPoints
     * @return array{x: float, y: float, inscribedWidthPct: float, inscribedHeightPct: float}
     */
    public function computeCustomPolygonVisualCenter(array $rawPoints): array
    {
        $pts = [];
        foreach ($rawPoints as $p) {
            if (is_array($p) && isset($p['x'], $p['y']) && is_numeric($p['x']) && is_numeric($p['y'])) {
                $pts[] = [
                    'x' => (float) $p['x'],
                    'y' => (float) $p['y'],
                ];
            }
        }

        $n = count($pts);
        if ($n < 3) {
            return [
                'x'                  => 50.0,
                'y'                  => 50.0,
                'inscribedWidthPct'  => 80.0,
                'inscribedHeightPct' => 80.0,
            ];
        }

        $xs = array_column($pts, 'x');
        $ys = array_column($pts, 'y');
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        // 1. Gaußsche Trapezformel (Shoelace Area Centroid)
        $signedArea2 = 0.0;
        $sumCx = 0.0;
        $sumCy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $p0 = $pts[$i];
            $p1 = $pts[($i + 1) % $n];
            $cross = ($p0['x'] * $p1['y']) - ($p1['x'] * $p0['y']);
            $signedArea2 += $cross;
            $sumCx += ($p0['x'] + $p1['x']) * $cross;
            $sumCy += ($p0['y'] + $p1['y']) * $cross;
        }

        $cx = abs($signedArea2) > 1e-4 ? ($sumCx / (3.0 * $signedArea2)) : (($minX + $maxX) * 0.5);
        $cy = abs($signedArea2) > 1e-4 ? ($sumCy / (3.0 * $signedArea2)) : (($minY + $maxY) * 0.5);

        $intervalsAtY = static function (float $y) use ($pts, $n): array {
            $xHits = [];
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                $xi = $pts[$i]['x'];
                $yi = $pts[$i]['y'];
                $xj = $pts[$j]['x'];
                $yj = $pts[$j]['y'];
                if (($yi > $y) !== ($yj > $y)) {
                    $xHits[] = $xi + ($y - $yi) * ($xj - $xi) / ($yj - $yi);
                }
            }
            sort($xHits);
            $out = [];
            for ($k = 0; $k + 1 < count($xHits); $k += 2) {
                $out[] = [$xHits[$k], $xHits[$k + 1]];
            }
            return $out;
        };

        $intervalsAtX = static function (float $x) use ($pts, $n): array {
            $yHits = [];
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                $xi = $pts[$i]['x'];
                $yi = $pts[$i]['y'];
                $xj = $pts[$j]['x'];
                $yj = $pts[$j]['y'];
                if (($xi > $x) !== ($xj > $x)) {
                    $yHits[] = $yi + ($x - $xi) * ($yj - $yi) / ($xj - $xi);
                }
            }
            sort($yHits);
            $out = [];
            for ($k = 0; $k + 1 < count($yHits); $k += 2) {
                $out[] = [$yHits[$k], $yHits[$k + 1]];
            }
            return $out;
        };

        // 2. Scanline-Prüfung für Innenbreite & sicheren Abstand zu konkaven Kerben (z. B. Herz-Kerbe oben)
        $bboxW = max(1.0, $maxX - $minX);
        $bboxH = max(1.0, $maxY - $minY);
        $bandHalfH = min(5.5, $bboxH * 0.20);

        $bestScore = -1.0;
        $bestX = $cx;
        $bestY = $cy;
        $bestW = max(8.0, $bboxW);
        $bestH = max(8.0, $bboxH);

        $steps = 32;
        $yStart = $minY + ($bboxH * 0.16);
        $yEnd = $maxY - ($bboxH * 0.16);

        for ($s = 0; $s <= $steps; $s++) {
            $ty = $yStart + ($yEnd - $yStart) * ($s / $steps);
            $ivMid = $intervalsAtY($ty);
            $ivTop = $intervalsAtY($ty - $bandHalfH);
            $ivBot = $intervalsAtY($ty + $bandHalfH);
            if (empty($ivMid) || empty($ivTop) || empty($ivBot)) {
                continue;
            }

            foreach ($ivMid as [$lMid, $rMid]) {
                foreach ($ivTop as [$lTop, $rTop]) {
                    foreach ($ivBot as [$lBot, $rBot]) {
                        $left = max($lMid, $lTop, $lBot);
                        $right = min($rMid, $rTop, $rBot);
                        $effW = $right - $left;
                        if ($effW <= 4.0) {
                            continue;
                        }

                        $midIntervalX = ($left + $right) * 0.5;
                        $vIvs = $intervalsAtX($midIntervalX);
                        $symVertH = 0.0;
                        $inVert = false;
                        foreach ($vIvs as [$vy0, $vy1]) {
                            if ($ty > $vy0 && $ty < $vy1) {
                                $symVertH = 2.0 * min($ty - $vy0, $vy1 - $ty);
                                if ($symVertH >= $bandHalfH * 1.6) {
                                    $inVert = true;
                                }
                                break;
                            }
                        }
                        if (!$inVert) {
                            continue;
                        }

                        $vertFactor = min(1.0, $symVertH / max(8.0, min(20.5, $bboxH * 0.52)));
                        $distNorm = abs($ty - $cy) / $bboxH;
                        $centroidFactor = 1.0 - min(0.50, $distNorm * 1.18);

                        $score = $effW * (0.55 + 0.45 * $vertFactor) * $centroidFactor;
                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $bestX = $midIntervalX;
                            $bestY = $ty;
                            $bestW = $effW;
                            $bestH = $symVertH;
                        }
                    }
                }
            }
        }

        // Sicherheits-Fallback bei extrem schmalen/konkaven Sonderformen: garantiert immer im breitesten Schnitt bei cy
        if ($bestScore < 0.0) {
            $fallbackIntervals = $intervalsAtY($cy);
            $widest = 0.0;
            foreach ($fallbackIntervals as [$fl, $fr]) {
                if (($fr - $fl) > $widest) {
                    $widest = $fr - $fl;
                    $bestX = ($fl + $fr) * 0.5;
                    $bestY = $cy;
                    $bestW = max(8.0, $widest);
                }
            }
        }

        return [
            'x'                  => round($bestX, 2),
            'y'                  => round($bestY, 2),
            'inscribedWidthPct'  => round(max(8.0, $bestW), 2),
            'inscribedHeightPct' => round(max(6.0, $bestH), 2),
        ];
    }

    /**
     * Berechnet die maßstabsgerechte Startgröße neu eingefügter Symbole/Uploads im Studio-Modus.
     * Bei kleinen Schmuck-Rohlingen (blankWidthMm <= 45) startet das Motiv sofort kräftig, aber plattenkonform.
     */
    protected function resolveDefaultStudioLogoSize(bool $isVector = true): int
    {
        $settings = is_array($this->configSettings ?? null) && !empty($this->configSettings)
            ? $this->configSettings
            : (is_array($this->product->configurator_settings ?? null) ? $this->product->configurator_settings : []);

        $blankWidthMm = (float) ($settings['xtool_plane_width'] ?? ($settings['xtool_d_top'] ?? ($this->product->width ?? 0)));
        $blankHeightMm = (float) ($settings['xtool_plane_height'] ?? ($settings['xtool_height'] ?? ($this->product->height ?? 0)));
        $pkgKey = (string) ($settings['template_package'] ?? ($this->product->slug ?? ''));

        if (in_array($pkgKey, ['id-armband-silber-herz', 'id-armband-gold-herz'], true) || ($blankWidthMm > 0 && $blankWidthMm <= 45.0 && $blankHeightMm > 0 && $blankHeightMm <= 8.0)) {
            return $isVector ? 48 : 54;
        }

        if ($blankWidthMm > 0 && $blankWidthMm <= 45.0) {
            $coords = $this->getBeginnerPositionCoordinates($settings);
            $baseSymSize = (int) round((float) ($coords['symbol_size'] ?? 64));

            return $isVector
                ? max(58, min(92, $baseSymSize + 4))
                : max(84, min(130, (int) round($baseSymSize * 1.35)));
        }

        return $isVector ? 100 : 130;
    }

    /**
     * Berechnet die vordefinierte „Neulings-Position“ (Symbol oben mittig & Text unten mittig)
     * innerhalb der aktiven Gravurfläche des Produkts (oder nutzt explizit im Backend Schritt 4.3 hinterlegte Werte).
     */
    public function getBeginnerPositionCoordinates(array $settings = []): array
    {
        if (empty($settings)) {
            $settings = is_array($this->configSettings ?? null) && !empty($this->configSettings)
                ? $this->configSettings
                : (is_array($this->product->configurator_settings ?? null) ? $this->product->configurator_settings : []);
        }

        if (($settings['area_shape'] ?? 'rect') === 'custom' && !empty($settings['custom_points']) && is_array($settings['custom_points'])) {
            $xs = array_map(fn($p) => (float) ($p['x'] ?? 50), $settings['custom_points']);
            $ys = array_map(fn($p) => (float) ($p['y'] ?? 50), $settings['custom_points']);
            $minX = min($xs);
            $maxX = max($xs);
            $minY = min($ys);
            $maxY = max($ys);
        } else {
            $minX = (float) ($settings['area_left'] ?? 25);
            $minY = (float) ($settings['area_top'] ?? 25);
            $maxX = $minX + (float) ($settings['area_width'] ?? 50);
            $maxY = $minY + (float) ($settings['area_height'] ?? 50);
        }

        $w = max(8.0, $maxX - $minX);
        $h = max(8.0, $maxY - $minY);
        $centerX = round($minX + ($w / 2.0), 2);
        $centerY = round($minY + ($h / 2.0), 2);

        $defaultSymbolX = $centerX;
        $defaultSymbolY = round($minY + ($h * 0.36), 2);
        $defaultSymbolSize = round(max(32.0, min(110.0, min($w, $h) * 1.15)), 1);

        $defaultTextX = $centerX;
        $defaultTextY = round($minY + ($h * 0.64), 2);
        $defaultTextSize = 0.85;
        $defaultStudioTextSize = round(max(0.48, min(0.88, ($w - 10.0) / 36.0)), 2);

        $blankWidthMm = (float) ($settings['xtool_plane_width'] ?? 0);
        if ($blankWidthMm > 0 && $blankWidthMm <= 45.0) {
            $plateBoxW = (float) ($settings['xtool_plate_box_pct']['width'] ?? $w);
            $mmPerEm = (4.0 * $blankWidthMm) / max(10.0, $plateBoxW);
            $mmPerLogo = $blankWidthMm / (5.0 * max(10.0, $plateBoxW));
            $defaultTextSize = round(max(1.05, min(2.40, 3.6 / max(0.5, $mmPerEm))), 2);
            $defaultStudioTextSize = round(max(1.05, min(2.35, 3.4 / max(0.5, $mmPerEm))), 2);
            $defaultSymbolSize = round(max(46.0, min(92.0, 6.2 / max(0.02, $mmPerLogo))), 1);
        }

        $pkgKey = (string) ($settings['template_package'] ?? ($this->product->slug ?? ''));
        if ($pkgKey === 'seelen-anhaenger') {
            $centerX = 50.0;
            $centerY = 72.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 65.5;
            $defaultSymbolSize = 58.0;
            $defaultTextX = 50.0;
            $defaultTextY = 76.0;
            $defaultTextSize = 1.15;
            $defaultStudioTextSize = 1.10;
        } elseif ($pkgKey === 'seelen-pfote-hundemarke') {
            $centerX = 41.0;
            $centerY = 50.0;
            $defaultSymbolX = 41.0;
            $defaultSymbolY = 44.5;
            $defaultSymbolSize = 58.0;
            $defaultTextX = 41.0;
            $defaultTextY = 54.5;
            $defaultTextSize = 1.85;
            $defaultStudioTextSize = 1.80;
        } elseif ($pkgKey === 'seelen-talisman') {
            $centerX = 50.0;
            $centerY = 66.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 56.0;
            $defaultSymbolSize = 58.0;
            $defaultTextX = 50.0;
            $defaultTextY = 73.0;
            $defaultTextSize = 1.15;
            $defaultStudioTextSize = 1.10;
        } elseif (in_array($pkgKey, ['goldenes-seelen-herz', 'echtgold-seelen-herz'], true)) {
            $centerX = 50.0;
            $centerY = 57.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 49.0;
            $defaultSymbolSize = 76.0;
            $defaultTextX = 50.0;
            $defaultTextY = 61.5;
            $defaultTextSize = 2.25;
            $defaultStudioTextSize = 2.15;
        } elseif (in_array($pkgKey, ['id-armband-silber-herz', 'id-armband-gold-herz'], true)) {
            $centerX = 57.0;
            $centerY = 50.0;
            $defaultSymbolX = 57.0;
            $defaultSymbolY = 46.5;
            $defaultSymbolSize = 46.0;
            $defaultTextX = 57.0;
            $defaultTextY = 53.0;
            $defaultTextSize = 2.18;
            $defaultStudioTextSize = 2.15;
        } elseif ($pkgKey === 'seelen-kristall') {
            $centerX = 50.0;
            $centerY = 45.6;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 32.0;
            $defaultSymbolSize = 92.0;
            $defaultTextX = 50.0;
            $defaultTextY = 55.5;
            $defaultTextSize = 1.32;
            $defaultStudioTextSize = 1.25;
        } elseif ($pkgKey === 'weizenglas-personalisiert') {
            $centerX = 50.0;
            $centerY = 47.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 35.0;
            $defaultSymbolSize = 74.0;
            $defaultTextX = 50.0;
            $defaultTextY = 55.0;
            $defaultTextSize = 1.10;
            $defaultStudioTextSize = 0.95;
        }

        $symbolX = isset($settings['beginner_symbol_x']) && $settings['beginner_symbol_x'] !== '' && is_numeric($settings['beginner_symbol_x'])
            ? round((float) $settings['beginner_symbol_x'], 2)
            : $defaultSymbolX;
        $symbolY = isset($settings['beginner_symbol_y']) && $settings['beginner_symbol_y'] !== '' && is_numeric($settings['beginner_symbol_y'])
            ? round((float) $settings['beginner_symbol_y'], 2)
            : $defaultSymbolY;
        $symbolSize = isset($settings['beginner_symbol_size']) && $settings['beginner_symbol_size'] !== '' && is_numeric($settings['beginner_symbol_size'])
            ? round((float) $settings['beginner_symbol_size'], 1)
            : $defaultSymbolSize;

        $textX = isset($settings['beginner_text_x']) && $settings['beginner_text_x'] !== '' && is_numeric($settings['beginner_text_x'])
            ? round((float) $settings['beginner_text_x'], 2)
            : $defaultTextX;
        $textY = isset($settings['beginner_text_y']) && $settings['beginner_text_y'] !== '' && is_numeric($settings['beginner_text_y'])
            ? round((float) $settings['beginner_text_y'], 2)
            : $defaultTextY;
        $textSize = isset($settings['beginner_text_size']) && $settings['beginner_text_size'] !== '' && is_numeric($settings['beginner_text_size'])
            ? round((float) $settings['beginner_text_size'], 2)
            : $defaultTextSize;

        return [
            'symbol_x'            => $symbolX,
            'symbol_y'            => $symbolY,
            'symbol_size'         => $symbolSize,
            'text_x'              => $textX,
            'text_y'              => $textY,
            'text_size'           => $textSize,
            'studio_text_size'    => $defaultStudioTextSize,
            'area_width_pct'      => round($w, 2),
            'center_x'            => $centerX,
            'center_y'            => $centerY,
            'default_symbol_x'    => $defaultSymbolX,
            'default_symbol_y'    => $defaultSymbolY,
            'default_symbol_size' => $defaultSymbolSize,
            'default_text_x'      => $defaultTextX,
            'default_text_y'      => $defaultTextY,
            'default_text_size'   => $defaultTextSize,
        ];
    }

    /**
     * Ordnet die Textauswahl eines Neulings-Symbols (z. B. „Herz ♥“, „Pfote 🐾“) dem kanonischen Vektor-Pfad zu.
     */
    public function resolveBeginnerSymbolVectorRelPath(?string $rawSymbolValue): ?string
    {
        $valLower = mb_strtolower(trim((string) $rawSymbolValue));
        if ($valLower === '' || in_array($valLower, ['kein symbol', 'keine', 'nein', '-'], true)) {
            return null;
        }

        $symbolMap = [
            'herz'          => 'Liebe/herz.svg',
            'pfote'         => 'Tiere/pfotenabdruck.svg',
            'unendlichkeit' => 'Liebe/unendlichkeit.svg',
            'stern'         => 'Symbole/stern.svg',
            'schutzengel'   => 'Symbole/schutzengel.svg',
            'engel'         => 'Symbole/schutzengel.svg',
            'lebensbaum'    => 'Natur/lebensbaum.svg',
            'baum'          => 'Natur/lebensbaum.svg',
            'kleeblatt'     => 'Symbole/kleeblatt.svg',
            'klee'          => 'Symbole/kleeblatt.svg',
            'krone'         => 'Symbole/krone.svg',
            'anker'         => 'Symbole/anker.svg',
        ];

        foreach ($symbolMap as $key => $relPath) {
            if (str_starts_with($valLower, $key) || str_contains($valLower, $key)) {
                return $relPath;
            }
        }

        return null;
    }

    /**
     * Formatiert mehrzeiligen oder überlangen Wunschtext im Neulings-Modus so,
     * dass manuelle Zeilenumbrüche (\n) erhalten bleiben und zu lange Einzelzeilen
     * passend zur Breite der Arbeitsfläche sauber an Wortgrenzen umgebrochen werden.
     */
    public function formatBeginnerTextForWorkingArea(string $rawText, float $areaWidthPct = 40.0): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($rawText));
        if ($normalized === '') {
            return '';
        }

        $maxCharsPerLine = max(12, min(28, (int) round($areaWidthPct * 0.56)));
        $rawLines = explode("\n", $normalized);
        $wrappedLines = [];

        foreach ($rawLines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }
            if (mb_strlen($trimmedLine) > $maxCharsPerLine && str_contains($trimmedLine, ' ')) {
                $wrapped = wordwrap($trimmedLine, $maxCharsPerLine, "\n", true);
                foreach (explode("\n", $wrapped) as $subLine) {
                    $subLine = trim($subLine);
                    if ($subLine !== '') {
                        $wrappedLines[] = $subLine;
                    }
                }
            } else {
                $wrappedLines[] = $trimmedLine;
            }
        }

        return implode("\n", array_slice($wrappedLines, 0, 5));
    }

    /**
     * Liefert den durchschnittlichen Zeichenbreiten-Faktor einer Konfigurator-Schriftart (relativ zu 1em).
     */
    public function getBeginnerFontWidthFactor(string $fontName): float
    {
        $fLower = mb_strtolower(trim($fontName));
        if (in_array($fLower, ['cinzel', 'bebas neue', 'montserrat', 'comfortaa'], true)) {
            return 0.62;
        }
        if (in_array($fLower, ['dancing script', 'great vibes', 'alex brush', 'sacramento', 'pacifico', 'allura'], true)) {
            return 0.48;
        }
        return 0.55;
    }

    /**
     * Berechnet die effektive Kontur-Geometrie (inkl. horizontaler Spiegelung auf der Rückseite)
     * für die exakte Smart-Fit-Kollisions- und Konturprüfung.
     */
    public function getEffectiveShapeGeometryForSide(string $side = 'front', array $settings = []): array
    {
        if (empty($settings)) {
            $settings = is_array($this->configSettings ?? null) && !empty($this->configSettings)
                ? $this->configSettings
                : (is_array($this->product->configurator_settings ?? null) ? $this->product->configurator_settings : []);
        }

        $isMirroredBack = ($side === 'back' && !empty($settings['has_back_side']) && ($settings['overlay_type'] ?? 'plane') !== 'cylinder');
        $areaShape = (string) ($settings['area_shape'] ?? 'rect');

        if ($areaShape === 'custom' && !empty($settings['custom_points']) && is_array($settings['custom_points'])) {
            $pts = [];
            foreach ($settings['custom_points'] as $p) {
                if (is_array($p) && isset($p['x'], $p['y']) && is_numeric($p['x']) && is_numeric($p['y'])) {
                    $px = (float) $p['x'];
                    $py = (float) $p['y'];
                    $pts[] = [
                        'x' => $isMirroredBack ? round(100.0 - $px, 3) : $px,
                        'y' => $py,
                    ];
                }
            }
            if ($isMirroredBack && count($pts) >= 3) {
                $pts = array_reverse($pts);
            }
            if (count($pts) >= 3) {
                $xs = array_column($pts, 'x');
                $ys = array_column($pts, 'y');
                return [
                    'shape'       => 'custom',
                    'is_mirrored' => $isMirroredBack,
                    'points'      => $pts,
                    'min_x'       => (float) min($xs),
                    'max_x'       => (float) max($xs),
                    'min_y'       => (float) min($ys),
                    'max_y'       => (float) max($ys),
                ];
            }
        }

        $rawLeft = (float) ($settings['area_left'] ?? 25.0);
        $top = (float) ($settings['area_top'] ?? 25.0);
        $width = max(6.0, (float) ($settings['area_width'] ?? 50.0));
        $height = max(6.0, (float) ($settings['area_height'] ?? 50.0));
        $left = $isMirroredBack ? max(0.0, min(100.0, 100.0 - $rawLeft - $width)) : $rawLeft;

        return [
            'shape'       => ($areaShape === 'circle' ? 'circle' : 'rect'),
            'is_mirrored' => $isMirroredBack,
            'points'      => [],
            'min_x'       => $left,
            'max_x'       => $left + $width,
            'min_y'       => $top,
            'max_y'       => $top + $height,
        ];
    }

    /**
     * Wählt aus aufsteigend sortierten Strahl-Schnittpunkten das Innen-Intervall, das $hint enthält (oder am nächsten liegt).
     */
    protected function pickRayIntervalContaining(array $hits, float $hint): ?array
    {
        $count = count($hits);
        if ($count < 2) {
            return null;
        }
        sort($hits, SORT_NUMERIC);

        $bestPair = null;
        $bestDist = INF;
        for ($i = 0; $i + 1 < $count; $i += 2) {
            $a = (float) $hits[$i];
            $b = (float) $hits[$i + 1];
            if (($b - $a) < 1.5) {
                continue;
            }
            if ($hint >= ($a - 0.05) && $hint <= ($b + 0.05)) {
                return [$a, $b];
            }
            $dist = ($hint < $a) ? ($a - $hint) : ($hint - $b);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $bestPair = [$a, $b];
            }
        }

        return $bestPair;
    }

    /**
     * Misst per Vertikal-Strahlschnitt die echte nutzbare Innenhöhe [safe_top, safe_bottom] der grünen Gravurkontur
     * rund um $anchorX (berücksichtigt Einschnürungen wie Knochen-Taille, Herz-Kerbe oder Ösen-Aussparungen).
     */
    public function measureVerticalSafeSpanAtX(array $geom, float $anchorX, float $hintY): array
    {
        $shape = (string) ($geom['shape'] ?? 'rect');
        $sliceAtX = function (float $px) use ($geom, $shape, $hintY): ?array {
            if ($shape === 'custom' && !empty($geom['points'])) {
                $pts = $geom['points'];
                $n = count($pts);
                $hits = [];
                for ($i = 0; $i < $n; $i++) {
                    $p1 = $pts[$i];
                    $p2 = $pts[($i + 1) % $n];
                    $x1 = (float) $p1['x'];
                    $y1 = (float) $p1['y'];
                    $x2 = (float) $p2['x'];
                    $y2 = (float) $p2['y'];
                    if ((($x1 <= $px && $x2 > $px) || ($x2 <= $px && $x1 > $px)) && abs($x2 - $x1) > 1e-6) {
                        $t = ($px - $x1) / ($x2 - $x1);
                        $hits[] = $y1 + $t * ($y2 - $y1);
                    }
                }
                return $this->pickRayIntervalContaining($hits, $hintY);
            }

            if ($shape === 'circle') {
                $cx = ($geom['min_x'] + $geom['max_x']) / 2.0;
                $cy = ($geom['min_y'] + $geom['max_y']) / 2.0;
                $rx = max(3.0, ($geom['max_x'] - $geom['min_x']) / 2.0);
                $ry = max(3.0, ($geom['max_y'] - $geom['min_y']) / 2.0);
                $dxNorm = ($px - $cx) / $rx;
                if (abs($dxNorm) >= 0.98) {
                    return null;
                }
                $dy = $ry * sqrt(max(0.0, 1.0 - ($dxNorm * $dxNorm)));
                return [$cy - $dy, $cy + $dy];
            }

            return [(float) $geom['min_y'], (float) $geom['max_y']];
        };

        $tops = [];
        $bottoms = [];
        foreach ([-3.0, 0.0, 3.0] as $offset) {
            $pair = $sliceAtX($anchorX + $offset);
            if ($pair !== null) {
                $tops[] = $pair[0];
                $bottoms[] = $pair[1];
            }
        }

        if (!empty($tops) && (min($bottoms) - max($tops)) >= 6.0) {
            $rawTop = (float) max($tops);
            $rawBottom = (float) min($bottoms);
        } else {
            $centerPair = $sliceAtX($anchorX);
            if ($centerPair !== null && ($centerPair[1] - $centerPair[0]) >= 6.0) {
                $rawTop = (float) $centerPair[0];
                $rawBottom = (float) $centerPair[1];
            } else {
                $rawTop = (float) ($geom['min_y'] ?? 25.0);
                $rawBottom = (float) ($geom['max_y'] ?? 75.0);
            }
        }

        $span = max(6.0, $rawBottom - $rawTop);
        $padY = max(1.1, min(2.4, $span * 0.065));

        return [
            'raw_top'     => round($rawTop, 2),
            'raw_bottom'  => round($rawBottom, 2),
            'safe_top'    => round($rawTop + $padY, 2),
            'safe_bottom' => round($rawBottom - $padY, 2),
        ];
    }

    /**
     * Misst per Horizontal-Strahlschnitt die symmetrisch um $anchorX nutzbare Innenbreite im Höhenband [$yMin, $yMax]
     * (respektiert seitliche Ausstanzungen wie den Pfoten-Durchbruch unten rechts bzw. gespiegelt unten links).
     */
    public function measureHorizontalSafeWidthInBand(array $geom, float $anchorX, float $yMin, float $yMax): float
    {
        $shape = (string) ($geom['shape'] ?? 'rect');
        $bTop = min($yMin, $yMax);
        $bBot = max($yMin, $yMax);
        $bandH = max(0.5, $bBot - $bTop);

        $sliceAtY = function (float $py) use ($geom, $shape, $anchorX): ?array {
            if ($shape === 'custom' && !empty($geom['points'])) {
                $pts = $geom['points'];
                $n = count($pts);
                $hits = [];
                for ($i = 0; $i < $n; $i++) {
                    $p1 = $pts[$i];
                    $p2 = $pts[($i + 1) % $n];
                    $x1 = (float) $p1['x'];
                    $y1 = (float) $p1['y'];
                    $x2 = (float) $p2['x'];
                    $y2 = (float) $p2['y'];
                    if ((($y1 <= $py && $y2 > $py) || ($y2 <= $py && $y1 > $py)) && abs($y2 - $y1) > 1e-6) {
                        $t = ($py - $y1) / ($y2 - $y1);
                        $hits[] = $x1 + $t * ($x2 - $x1);
                    }
                }
                return $this->pickRayIntervalContaining($hits, $anchorX);
            }

            if ($shape === 'circle') {
                $cx = ($geom['min_x'] + $geom['max_x']) / 2.0;
                $cy = ($geom['min_y'] + $geom['max_y']) / 2.0;
                $rx = max(3.0, ($geom['max_x'] - $geom['min_x']) / 2.0);
                $ry = max(3.0, ($geom['max_y'] - $geom['min_y']) / 2.0);
                $dyNorm = ($py - $cy) / $ry;
                if (abs($dyNorm) >= 0.98) {
                    return null;
                }
                $dx = $rx * sqrt(max(0.0, 1.0 - ($dyNorm * $dyNorm)));
                return [$cx - $dx, $cx + $dx];
            }

            return [(float) $geom['min_x'], (float) $geom['max_x']];
        };

        $symWidths = [];
        foreach ([0.10, 0.30, 0.50, 0.70, 0.90] as $ratio) {
            $py = $bTop + ($bandH * $ratio);
            $pair = $sliceAtY($py);
            if ($pair !== null) {
                $leftDist = $anchorX - (float) $pair[0];
                $rightDist = (float) $pair[1] - $anchorX;
                if ($leftDist > 2.0 && $rightDist > 2.0) {
                    $symWidths[] = 2.0 * min($leftDist, $rightDist);
                }
            }
        }

        $rawWidth = !empty($symWidths)
            ? (float) min($symWidths)
            : max(10.0, (float) (($geom['max_x'] ?? 75.0) - ($geom['min_x'] ?? 25.0)));

        return round(max(8.0, $rawWidth - 2.4), 2);
    }

    /**
     * Berechnet kollisionsfrei und kontursicher Position & Größe von Symbol und Text für eine Produktseite ('front' | 'back').
     */
    public function solveSmartBeginnerSideLayout(
        string $side,
        ?string $symbolRelPath,
        string $rawText,
        string $fontName,
        array $coords,
        array $settings = [],
        string $existingLogoId = 'beginner_symbol_1',
        string $existingTextId = 'beginner_text_1'
    ): array {
        $geom = $this->getEffectiveShapeGeometryForSide($side, $settings);
        $isMirrored = !empty($geom['is_mirrored']);

        $symX = $isMirrored
            ? round(100.0 - (float) ($coords['symbol_x'] ?? 50.0), 2)
            : round((float) ($coords['symbol_x'] ?? 50.0), 2);
        $txtX = $isMirrored
            ? round(100.0 - (float) ($coords['text_x'] ?? 50.0), 2)
            : round((float) ($coords['text_x'] ?? 50.0), 2);
        $centerX = $isMirrored
            ? round(100.0 - (float) ($coords['center_x'] ?? 50.0), 2)
            : round((float) ($coords['center_x'] ?? 50.0), 2);
        $centerY = round((float) ($coords['center_y'] ?? 50.0), 2);

        $vSpan = $this->measureVerticalSafeSpanAtX($geom, $centerX, $centerY);
        $safeTop = (float) $vSpan['safe_top'];
        $safeBottom = (float) $vSpan['safe_bottom'];
        $availH = max(6.0, $safeBottom - $safeTop);
        $opticalMidY = round(($safeTop + $safeBottom) / 2.0, 2);

        $midSafeW = $this->measureHorizontalSafeWidthInBand(
            $geom,
            $txtX,
            $safeTop + ($availH * 0.15),
            $safeBottom - ($availH * 0.15)
        );

        $displayText = $this->formatBeginnerTextForWorkingArea($rawText, $midSafeW);
        $hasSymbol = ($symbolRelPath !== null && trim($symbolRelPath) !== '');
        $hasText = ($displayText !== '');

        if (!$hasSymbol && !$hasText) {
            return ['logo' => null, 'text' => null];
        }

        $symSize = (float) ($coords['symbol_size'] ?? 64.0);
        $baseTxtSize = (float) ($coords['text_size'] ?? 0.95);
        $fontWidthFactor = $this->getBeginnerFontWidthFactor($fontName);

        $lines = $hasText ? explode("\n", $displayText) : [];
        $lineCount = count($lines);
        $maxLineChars = 0;
        foreach ($lines as $ln) {
            $maxLineChars = max($maxLineChars, mb_strlen($ln));
        }

        if ($lineCount === 2) {
            $txtSize = $baseTxtSize * 0.90;
        } elseif ($lineCount >= 3) {
            $txtSize = $baseTxtSize * max(0.60, 0.80 - (($lineCount - 3) * 0.07));
        } else {
            $txtSize = $baseTxtSize;
        }

        $finalSymY = round((float) ($coords['symbol_y'] ?? $opticalMidY), 2);
        $finalTxtY = round((float) ($coords['text_y'] ?? $opticalMidY), 2);

        if ($hasSymbol && !$hasText) {
            $symH = $symSize * 0.19;
            if ($symH > ($availH * 0.86)) {
                $symSize = ($availH * 0.86) / 0.19;
                $symH = $symSize * 0.19;
            }
            $finalSymY = $opticalMidY;
            $symSafeW = $this->measureHorizontalSafeWidthInBand($geom, $symX, $finalSymY - ($symH * 0.42), $finalSymY + ($symH * 0.42));
            if (($symSize * 0.19) > ($symSafeW * 0.92)) {
                $symSize = ($symSafeW * 0.92) / 0.19;
            }
            $symSize = round(max(22.0, $symSize), 1);
        } elseif (!$hasSymbol && $hasText) {
            $txtH = $lineCount * 4.60 * $txtSize;
            if ($txtH > ($availH * 0.92)) {
                $txtSize *= ($availH * 0.92) / $txtH;
                $txtH = $lineCount * 4.60 * $txtSize;
            }
            if (($centerY - ($txtH / 2.0)) >= ($safeTop - 0.3) && ($centerY + ($txtH / 2.0)) <= ($safeBottom + 0.3)) {
                $finalTxtY = $centerY;
            } else {
                $finalTxtY = $opticalMidY;
            }
            $txtSafeW = $this->measureHorizontalSafeWidthInBand($geom, $txtX, $finalTxtY - ($txtH * 0.42), $finalTxtY + ($txtH * 0.42));
            $estTxtW = ($maxLineChars * 4.0 * $fontWidthFactor * $txtSize) + 1.6;
            if ($estTxtW > $txtSafeW && $estTxtW > 0.1) {
                $txtSize *= ($txtSafeW / $estTxtW);
            }
            $txtSize = round(max(0.42, $txtSize), 2);
        } else {
            // Symbol + Text gemeinsam auf derselben Seite: garantiert kollisionsfrei stapeln & skalieren
            $minGap = max(1.4, min(2.8, $availH * 0.08));
            $symH = $symSize * 0.19;
            $txtH = $lineCount * 4.60 * $txtSize;

            if (($symH + $minGap + $txtH) > $availH) {
                $availForElements = max(4.0, $availH - $minGap);
                $vScale = $availForElements / max(1.0, $symH + $txtH);
                $symSize = max(22.0, $symSize * $vScale);
                $txtSize = max(0.42, $txtSize * $vScale);
                $symH = $symSize * 0.19;
                $txtH = $lineCount * 4.60 * $txtSize;
            }

            $curSymY = (float) ($coords['symbol_y'] ?? $opticalMidY);
            $curTxtY = (float) ($coords['text_y'] ?? $opticalMidY);
            $curFitsTop = ($curSymY - ($symH / 2.0)) >= ($safeTop - 0.3);
            $curFitsBottom = ($curTxtY + ($txtH / 2.0)) <= ($safeBottom + 0.3);
            $curGapOk = (($curTxtY - ($txtH / 2.0)) - ($curSymY + ($symH / 2.0))) >= ($minGap - 0.2);

            if ($curFitsTop && $curFitsBottom && $curGapOk) {
                $finalSymY = round($curSymY, 2);
                $finalTxtY = round($curTxtY, 2);
            } else {
                $effGap = min($minGap, max(0.9, $availH - $symH - $txtH));
                $totalStackH = $symH + $effGap + $txtH;
                $stackTop = $safeTop + max(0.0, ($availH - $totalStackH) / 2.0);
                $finalSymY = round($stackTop + ($symH / 2.0), 2);
                $finalTxtY = round($stackTop + $symH + $effGap + ($txtH / 2.0), 2);
            }

            $symSafeW = $this->measureHorizontalSafeWidthInBand($geom, $symX, $finalSymY - ($symH * 0.42), $finalSymY + ($symH * 0.42));
            if (($symSize * 0.19) > ($symSafeW * 0.92)) {
                $symSize = max(22.0, ($symSafeW * 0.92) / 0.19);
            }

            $txtSafeW = $this->measureHorizontalSafeWidthInBand($geom, $txtX, $finalTxtY - ($txtH * 0.42), $finalTxtY + ($txtH * 0.42));
            $estTxtW = ($maxLineChars * 4.0 * $fontWidthFactor * $txtSize) + 1.6;
            if ($estTxtW > $txtSafeW && $estTxtW > 0.1) {
                $txtSize = max(0.42, $txtSize * ($txtSafeW / $estTxtW));
            }

            $symSize = round($symSize, 1);
            $txtSize = round($txtSize, 2);
        }

        $logoItem = null;
        if ($hasSymbol) {
            $vectorVal = 'vectors/' . ltrim((string) $symbolRelPath, '/');
            $vectorService = app(\App\Services\ProductConfiguratorService::class);
            $logoItem = [
                'id'       => $existingLogoId,
                'type'     => 'vector',
                'value'    => $vectorVal,
                'url'      => $vectorService->resolveVectorUrl($vectorVal),
                'x'        => $symX,
                'y'        => $finalSymY,
                'size'     => $symSize,
                'rotation' => 0,
            ];
        }

        $textItem = null;
        if ($hasText) {
            $textItem = [
                'id'       => $existingTextId,
                'text'     => $displayText,
                'font'     => $fontName,
                'align'    => 'center',
                'x'        => $txtX,
                'y'        => $finalTxtY,
                'size'     => $txtSize,
                'rotation' => 0,
            ];
        }

        return ['logo' => $logoItem, 'text' => $textItem];
    }

    /**
     * Synchronisiert die Eingaben des „Neuling“-Modus (Vorderseite & optional Rückseite)
     * kontur- und kollisionssicher auf das 2D/3D-Zeichenbrett ($this->logos, $this->texts, $this->logos_back, $this->texts_back).
     */
    public function syncBeginnerPositionToCanvas(bool $forSave = false): void
    {
        if (!method_exists($this, 'isUsingCustomFieldsMode') || !$this->isUsingCustomFieldsMode() || !$this->product->usesStudioConfigurator()) {
            return;
        }

        $settings = is_array($this->configSettings ?? null) && !empty($this->configSettings)
            ? $this->configSettings
            : (is_array($this->product->configurator_settings ?? null) ? $this->product->configurator_settings : []);
        $coords = $this->getBeginnerPositionCoordinates($settings);
        $definitions = $this->product->getCustomFieldsDefinition(true);
        $hasBackSide = !empty($settings['has_back_side']);

        $chosenFont = 'Arial';
        $frontSymbolRel = null;
        $backSymbolRel = null;
        $frontTextLines = [];
        $backTextLines = [];
        $fallbackPreviewText = 'Dein Wunschtext';

        foreach ($definitions as $cfDef) {
            $cfId = (string) ($cfDef['id'] ?? '');
            $fType = (string) ($cfDef['type'] ?? 'text');
            $labelLower = mb_strtolower((string) ($cfDef['label'] ?? ''));
            $rawVal = trim(str_replace(["\r\n", "\r"], "\n", (string) ($this->customFieldValues[$cfId] ?? '')));

            if ($fType === 'font') {
                if ($rawVal !== '') {
                    $chosenFont = $rawVal;
                }
                continue;
            }

            $effectiveSide = ($hasBackSide && method_exists($this, 'resolveEffectiveCustomFieldSide'))
                ? $this->resolveEffectiveCustomFieldSide($cfDef)
                : 'front';

            $isSymbolField = ($fType === 'select') && (str_contains($labelLower, 'symbol') || str_contains(mb_strtolower($cfId), 'symbol'));
            if ($isSymbolField) {
                $resolvedSym = $this->resolveBeginnerSymbolVectorRelPath($rawVal);
                if ($effectiveSide === 'back') {
                    $backSymbolRel = $resolvedSym;
                } else {
                    $frontSymbolRel = $resolvedSym;
                }
                continue;
            }

            if ($fType === 'date' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawVal, $dm)) {
                $cleanVal = "{$dm[3]}.{$dm[2]}.{$dm[1]}";
            } else {
                $cleanVal = method_exists($this, 'sanitizeLaserText') ? $this->sanitizeLaserText($rawVal) : trim($rawVal);
            }

            if ($cleanVal !== '' && !in_array(mb_strtolower($cleanVal), ['kein symbol', 'keine', 'nein', '-'], true)) {
                $currentSideSym = ($effectiveSide === 'back') ? $backSymbolRel : $frontSymbolRel;
                $maybeSym = ($fType === 'select' && $currentSideSym === null) ? $this->resolveBeginnerSymbolVectorRelPath($cleanVal) : null;
                if ($maybeSym !== null) {
                    if ($effectiveSide === 'back') {
                        $backSymbolRel = $maybeSym;
                    } else {
                        $frontSymbolRel = $maybeSym;
                    }
                } else {
                    if ($effectiveSide === 'back') {
                        $backTextLines[] = $cleanVal;
                    } else {
                        $frontTextLines[] = $cleanVal;
                    }
                }
            } elseif ($effectiveSide === 'front' && !empty($cfDef['required']) && !empty($cfDef['placeholder'])) {
                $ph = trim((string) preg_replace('/^z\.\s*B\.\s*/iu', '', (string) $cfDef['placeholder']));
                if ($ph !== '' && $fallbackPreviewText === 'Dein Wunschtext') {
                    $fallbackPreviewText = $ph;
                }
            }
        }

        $rawFrontText = !empty($frontTextLines)
            ? implode("\n", $frontTextLines)
            : (($forSave || $this->context === 'preview') ? '' : $fallbackPreviewText);

        $frontLayout = $this->solveSmartBeginnerSideLayout(
            'front',
            $frontSymbolRel,
            $rawFrontText,
            $chosenFont,
            $coords,
            $settings,
            $this->logos[0]['id'] ?? 'beginner_symbol_1',
            $this->texts[0]['id'] ?? 'beginner_text_1'
        );

        $this->logos = $frontLayout['logo'] !== null ? [$frontLayout['logo']] : [];
        $this->texts = $frontLayout['text'] !== null ? [$frontLayout['text']] : [];

        if ($hasBackSide && ($backSymbolRel !== null || !empty($backTextLines))) {
            $rawBackText = implode("\n", $backTextLines);
            $backLayout = $this->solveSmartBeginnerSideLayout(
                'back',
                $backSymbolRel,
                $rawBackText,
                $chosenFont,
                $coords,
                $settings,
                $this->logos_back[0]['id'] ?? 'beginner_symbol_back_1',
                $this->texts_back[0]['id'] ?? 'beginner_text_back_1'
            );
            $this->logos_back = $backLayout['logo'] !== null ? [$backLayout['logo']] : [];
            $this->texts_back = $backLayout['text'] !== null ? [$backLayout['text']] : [];
        } else {
            $this->logos_back = [];
            $this->texts_back = [];
        }
    }
}
