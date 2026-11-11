<?php

namespace App\Livewire\Shop\Product\ProductConfigurator\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Services\ProductTrackingService;

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
            $shopCapacityLevel = (int) \Illuminate\Support\Facades\Cache::get('shop_capacity_level', shop_setting('shop_capacity_level', 0));
            if ($shopCapacityLevel >= 2) {
                // Bei hoher Auslastung Express deaktivieren
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
        $centerX = $coords['center_x'] ?? ($this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2));
        $centerY = $coords['center_y'] ?? ($this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2));
        $defaultSize = $size !== null ? (float) $size : (float) ($coords['studio_text_size'] ?? 0.72);

        $newItem = [
            'id' => Str::uuid()->toString(),
            'text' => '',
            'font' => 'Arial',
            'align' => 'center',
            'x' => $x ?? $centerX,
            'y' => $y ?? $centerY,
            'size' => $defaultSize,
            'rotation' => 0
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

    public function addStandardVector($filename)
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

        $coords = $this->getBeginnerPositionCoordinates();
        $centerX = $coords['center_x'] ?? ($this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2));
        $centerY = $coords['center_y'] ?? ($this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2));

        $url = $vectorService->resolveVectorUrl($canonicalRel);

        $newItem = [
            'id' => Str::uuid()->toString(),
            'type' => 'vector',
            'value' => 'vectors/' . $canonicalRel,
            'url' => $url,
            'x' => $centerX,
            'y' => $centerY,
            'size' => 100,
            'rotation' => 0
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

        $coords = $this->getBeginnerPositionCoordinates();
        $centerX = $coords['center_x'] ?? ($this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2));
        $centerY = $coords['center_y'] ?? ($this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2));

        $resolvedUrl = str_starts_with($value, 'vectors/')
            ? app(\App\Services\ProductConfiguratorService::class)->resolveVectorUrl($value)
            : asset('storage/' . $value);

        // Ansonsten zur aktuellen Seite hinzufügen
        $this->$targetProperty[] = [
            'id' => Str::uuid()->toString(),
            'type' => str_starts_with($value, 'vectors/') ? 'vector' : 'saved',
            'value' => $value,
            'url' => $resolvedUrl,
            'x' => $centerX,
            'y' => $centerY,
            'size' => 130,
            'rotation' => 0
        ];

        $this->clearUnusedEmptyTextsOnActiveSide();
    }

    public function addFilesToStage($newPaths = null)
    {
        $coords = $this->getBeginnerPositionCoordinates();
        $centerX = $coords['center_x'] ?? ($this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2));
        $centerY = $coords['center_y'] ?? ($this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2));

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
                    $this->$targetProperty[] = [
                        'id' => Str::uuid()->toString(),
                        'type' => 'saved',
                        'value' => $path,
                        'url' => asset('storage/' . $path),
                        'x' => $centerX,
                        'y' => $centerY,
                        'size' => 130,
                        'rotation' => 0
                    ];
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

        // WICHTIG: Pfad validieren, um Path Traversal oder unbefugtes Löschen zu verhindern
        if (is_string($path) && str_starts_with($path, 'bestellungen/cart-uploads/') && !str_contains($path, '..')) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
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
        if (count($owned) > 50) {
            $owned = array_slice($owned, -50);
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

        $pkgKey = (string) ($settings['template_package'] ?? ($this->product->slug ?? ''));
        if ($pkgKey === 'seelen-anhaenger') {
            $centerX = 50.0;
            $centerY = 72.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 65.5;
            $defaultSymbolSize = 38.0;
            $defaultTextX = 50.0;
            $defaultTextY = 76.0;
            $defaultTextSize = 0.72;
            $defaultStudioTextSize = 0.66;
        } elseif ($pkgKey === 'seelen-pfote-hundemarke') {
            $centerX = 41.0;
            $centerY = 50.0;
            $defaultSymbolX = 41.0;
            $defaultSymbolY = 44.5;
            $defaultSymbolSize = 34.0;
            $defaultTextX = 41.0;
            $defaultTextY = 54.5;
            $defaultTextSize = 0.70;
            $defaultStudioTextSize = 0.56;
        } elseif ($pkgKey === 'seelen-talisman') {
            $centerX = 50.0;
            $centerY = 66.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 56.0;
            $defaultSymbolSize = 46.0;
            $defaultTextX = 50.0;
            $defaultTextY = 73.0;
            $defaultTextSize = 0.74;
            $defaultStudioTextSize = 0.58;
        } elseif (in_array($pkgKey, ['goldenes-seelen-herz', 'echtgold-seelen-herz'], true)) {
            $centerX = 50.0;
            $centerY = 57.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 49.0;
            $defaultSymbolSize = 42.0;
            $defaultTextX = 50.0;
            $defaultTextY = 61.5;
            $defaultTextSize = 0.82;
            $defaultStudioTextSize = 0.62;
        } elseif (in_array($pkgKey, ['id-armband-silber-herz', 'id-armband-gold-herz'], true)) {
            $centerX = 57.0;
            $centerY = 50.0;
            $defaultSymbolX = 57.0;
            $defaultSymbolY = 46.5;
            $defaultSymbolSize = 22.0;
            $defaultTextX = 57.0;
            $defaultTextY = 53.0;
            $defaultTextSize = 0.56;
            $defaultStudioTextSize = 0.50;
        } elseif ($pkgKey === 'seelen-kristall') {
            $centerX = 50.0;
            $centerY = 50.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 35.0;
            $defaultSymbolSize = 80.0;
            $defaultTextX = 50.0;
            $defaultTextY = 58.0;
            $defaultTextSize = 1.20;
            $defaultStudioTextSize = 0.88;
        } elseif ($pkgKey === 'weizenglas-personalisiert') {
            $centerX = 50.0;
            $centerY = 47.0;
            $defaultSymbolX = 50.0;
            $defaultSymbolY = 35.0;
            $defaultSymbolSize = 72.0;
            $defaultTextX = 50.0;
            $defaultTextY = 55.0;
            $defaultTextSize = 1.05;
            $defaultStudioTextSize = 0.85;
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
     * Synchronisiert die Eingaben des „Neuling“-Modus (Symbol oben mittig + Wunschtext unten mittig)
     * direkt auf das 2D/3D-Zeichenbrett ($this->logos und $this->texts).
     */
    public function syncBeginnerPositionToCanvas(bool $forSave = false): void
    {
        if (!method_exists($this, 'isUsingCustomFieldsMode') || !$this->isUsingCustomFieldsMode() || !$this->product->usesStudioConfigurator()) {
            return;
        }

        $coords = $this->getBeginnerPositionCoordinates();
        $definitions = $this->product->getCustomFieldsDefinition(true);
        $vectorService = app(\App\Services\ProductConfiguratorService::class);

        $chosenFont = 'Arial';
        $matchedSymbolRel = null;
        $textLines = [];
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

            $isSymbolField = ($fType === 'select') && (str_contains($labelLower, 'symbol') || str_contains(mb_strtolower($cfId), 'symbol'));
            if ($isSymbolField) {
                $matchedSymbolRel = $this->resolveBeginnerSymbolVectorRelPath($rawVal);
                continue;
            }

            if ($fType === 'date' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawVal, $dm)) {
                $cleanVal = "{$dm[3]}.{$dm[2]}.{$dm[1]}";
            } else {
                $cleanVal = method_exists($this, 'sanitizeLaserText') ? $this->sanitizeLaserText($rawVal) : trim($rawVal);
            }

            if ($cleanVal !== '' && !in_array(mb_strtolower($cleanVal), ['kein symbol', 'keine', 'nein', '-'], true)) {
                // Falls ein Select-Feld ohne 'symbol' im Label dennoch ein bekanntes Symbol enthält:
                $maybeSym = ($fType === 'select' && $matchedSymbolRel === null) ? $this->resolveBeginnerSymbolVectorRelPath($cleanVal) : null;
                if ($maybeSym !== null) {
                    $matchedSymbolRel = $maybeSym;
                } else {
                    $textLines[] = $cleanVal;
                }
            } elseif (!empty($cfDef['required']) && !empty($cfDef['placeholder'])) {
                $ph = trim((string) preg_replace('/^z\.\s*B\.\s*/iu', '', (string) $cfDef['placeholder']));
                if ($ph !== '' && $fallbackPreviewText === 'Dein Wunschtext') {
                    $fallbackPreviewText = $ph;
                }
            }
        }

        if ($matchedSymbolRel !== null) {
            $vectorVal = 'vectors/' . ltrim($matchedSymbolRel, '/');
            $this->logos = [[
                'id'       => $this->logos[0]['id'] ?? 'beginner_symbol_1',
                'type'     => 'vector',
                'value'    => $vectorVal,
                'url'      => $vectorService->resolveVectorUrl($vectorVal),
                'x'        => $coords['symbol_x'],
                'y'        => $coords['symbol_y'],
                'size'     => $coords['symbol_size'],
                'rotation' => 0,
            ]];
        } else {
            $this->logos = [];
        }

        $rawDisplayText = !empty($textLines)
            ? implode("\n", $textLines)
            : (($forSave || $this->context === 'preview') ? '' : $fallbackPreviewText);

        $displayText = $this->formatBeginnerTextForWorkingArea($rawDisplayText, (float) ($coords['area_width_pct'] ?? 40.0));

        if ($displayText !== '') {
            $lineCount = substr_count($displayText, "\n") + 1;
            $baseTextSize = (float) ($coords['text_size'] ?? 0.75);
            if ($lineCount === 2) {
                $effectiveTextSize = round($baseTextSize * 0.90, 2);
            } elseif ($lineCount >= 3) {
                $effectiveTextSize = round($baseTextSize * 0.80, 2);
            } else {
                $effectiveTextSize = $baseTextSize;
            }

            $this->texts = [[
                'id'       => $this->texts[0]['id'] ?? 'beginner_text_1',
                'text'     => $displayText,
                'font'     => $chosenFont,
                'align'    => 'center',
                'x'        => $coords['text_x'],
                'y'        => $matchedSymbolRel !== null ? $coords['text_y'] : $coords['center_y'],
                'size'     => $effectiveTextSize,
                'rotation' => 0,
            ]];
        } else {
            $this->texts = [];
        }

        $this->logos_back = [];
        $this->texts_back = [];
    }
}
