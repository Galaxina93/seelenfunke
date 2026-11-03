<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductVariants extends Component
{
    use WithFileUploads;

    #[\Livewire\Attributes\Locked]
    public Product $product;

    // Die Matrix der Endprodukte
    public array $variants = [];

    // Optionale Zusatz-Optionen / Verpackungs-Upgrades & Bundle-Extras mit Aufpreis
    public array $addons = [];

    // Temporäre Datei-Uploads für Addon-Vorschaubilder
    public array $addonUploads = [];

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount(Product $product)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $this->product = $product;
        // Wir laden die bereinigten Varianten aus dem Product-Model.
        $this->variants = $this->product->getSanitizedVariantsData(true);
        $this->addons = $this->loadSanitizedAddons();
    }

    #[On('product-updated')]
    public function refreshProduct(): void
    {
        $this->product->refresh();
        $this->variants = $this->product->getSanitizedVariantsData(true);
        $this->addons = $this->loadSanitizedAddons();
    }

    protected function loadSanitizedAddons(): array
    {
        if (!$this->product->isPhysical()) {
            return [];
        }

        return $this->product->getEffectiveAddons(false);
    }

    public function addAddon(string $presetName = '', string $presetDesc = '', string $presetPrice = '3.90', ?string $presetImage = null): void
    {
        if (!$this->product->isPhysical() || count($this->addons) >= 30) {
            return;
        }

        $lowerPreset = mb_strtolower(trim($presetName));
        $canonicalId = Str::uuid()->toString();
        if ($lowerPreset !== '') {
            if (str_contains($lowerPreset, 'schmuckbox') || str_contains($lowerPreset, 'geschenkbox')) {
                $canonicalId = 'gift_box_westpack';
            } elseif (str_contains($lowerPreset, 'geschenkpapier')) {
                $canonicalId = 'gift_wrap_black_gold';
            }

            // Verhindere Duplikate bei Klick auf Schnell-Buttons
            foreach ($this->addons as $idx => $existing) {
                $exId = (string) ($existing['id'] ?? '');
                $exName = mb_strtolower(trim((string) ($existing['name'] ?? '')));
                if ($exId === $canonicalId || ($exName !== '' && $exName === $lowerPreset)) {
                    $this->addons[$idx]['is_active'] = true;
                    $this->addons[$idx]['price'] = $presetPrice;
                    $this->addons[$idx]['price_cents'] = max(0, (int) round(((float) str_replace(',', '.', $presetPrice)) * 100));
                    $this->saveAddons();
                    return;
                }
            }
        }

        $newAddon = [
            'id'          => $canonicalId,
            'name'        => $presetName,
            'description' => $presetDesc,
            'price'       => $presetPrice,
            'price_cents' => max(0, (int) round(((float) str_replace(',', '.', $presetPrice)) * 100)),
            'image_path'  => $presetImage,
            'is_active'   => true,
        ];
        $newAddon['image_path'] = Product::resolveAddonImagePath($newAddon);
        $newAddon['image_url'] = Product::resolveAddonImageUrl($newAddon['image_path']);

        $this->addons[] = $newAddon;

        $this->saveAddons();
    }

    protected function isDeletableCustomAddonImage(?string $path): bool
    {
        if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        $protectedDefaults = [
            'produkte/addons/edle-schmuckbox-schwarz-gold.jpg',
            'produkte/addons/geschenkpapier-schwarz-gold.jpg',
        ];
        if (in_array($path, $protectedDefaults, true)) {
            return false;
        }
        return str_starts_with($path, 'produkte/addons/') || str_starts_with($path, 'products/addons/');
    }

    public function updatedAddonUploads($file, $index): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $idx = (int) $index;
        if (!isset($this->addons[$idx]) || !$file) {
            return;
        }

        $this->validate([
            "addonUploads.{$idx}" => 'image|mimes:jpeg,png,jpg,webp,gif|max:5120',
        ]);

        // Serverseitige MIME- & Bilddimensions-Prüfung (Schutz gegen Polyglot-Uploads & manipulierte Header)
        $realPath = $file->getRealPath();
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $realPath ? finfo_file($finfo, $realPath) : '';
        finfo_close($finfo);

        $allowedMimeExtMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        $imgDimensions = $realPath ? @getimagesize($realPath) : false;

        if (
            !isset($allowedMimeExtMap[$realMime])
            || $imgDimensions === false
            || ($imgDimensions[0] ?? 0) <= 0
            || ($imgDimensions[1] ?? 0) <= 0
            || ($imgDimensions[0] ?? 0) > 6000
            || ($imgDimensions[1] ?? 0) > 6000
        ) {
            unset($this->addonUploads[$idx]);
            return;
        }

        // Falls ein altes hochgeladenes Bild (in storage/app/public/produkte/addons) existiert, aufräumen (Standard-Assets schützen)
        $oldPath = $this->addons[$idx]['image_path'] ?? null;
        if ($this->isDeletableCustomAddonImage($oldPath) && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $safeFilename = Str::uuid()->toString() . '.' . $allowedMimeExtMap[$realMime];
        $storedPath = $file->storeAs('produkte/addons', $safeFilename, 'public');
        $this->addons[$idx]['image_path'] = $storedPath;
        $this->addons[$idx]['image_url'] = Product::resolveAddonImageUrl($storedPath);
        unset($this->addonUploads[$idx]);

        $this->saveAddons();
    }

    public function removeAddonImage(int $index): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        if (!isset($this->addons[$index])) {
            return;
        }

        $oldPath = $this->addons[$index]['image_path'] ?? null;
        if ($this->isDeletableCustomAddonImage($oldPath) && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->addons[$index]['image_path'] = null;
        $this->addons[$index]['image_url'] = null;
        $this->saveAddons();
    }

    public function removeAddon(int $index): void
    {
        if (isset($this->addons[$index])) {
            $oldPath = $this->addons[$index]['image_path'] ?? null;
            if ($this->isDeletableCustomAddonImage($oldPath) && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            unset($this->addons[$index]);
            $this->addons = array_values($this->addons);
            $this->saveAddons();
        }
    }

    public function saveAddons(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $cleanAddons = [];
        foreach (array_slice($this->addons, 0, 30) as $addon) {
            if (!is_array($addon)) {
                continue;
            }
            $name = trim(strip_tags((string) ($addon['name'] ?? '')));
            $desc = trim(strip_tags((string) ($addon['description'] ?? '')));
            $rawPrice = (float) str_replace(',', '.', (string) ($addon['price'] ?? '0'));
            $priceCents = max(0, min(500000, (int) round($rawPrice * 100)));
            $imagePath = Product::resolveAddonImagePath($addon);

            $cleanAddons[] = [
                'id'          => !empty($addon['id']) ? (string) $addon['id'] : Str::uuid()->toString(),
                'name'        => mb_substr($name, 0, 120),
                'description' => mb_substr($desc, 0, 250),
                'price'       => number_format($priceCents / 100, 2, '.', ''),
                'price_cents' => $priceCents,
                'image_path'  => $imagePath,
                'image_url'   => Product::resolveAddonImageUrl($imagePath),
                'is_active'   => (bool) ($addon['is_active'] ?? true),
            ];
        }

        $this->addons = $cleanAddons;
        $settings = is_array($this->product->configurator_settings) ? $this->product->configurator_settings : [];
        $settings['addons'] = $cleanAddons;
        $this->product->configurator_settings = $settings;
        $this->product->save();

        $this->dispatch('product-updated');
        $this->dispatch('variants-saved');
    }

    public function generateMatrix()
    {
        // 1. Nur echte Varianten-Eigenschaften (mind. 2 kommagetrennte Optionen, nicht ausgeschlossen) laden
        $parsedAttributes = $this->product->refresh()->getVariantAttributeOptions();

        // Falls keine parsebaren Varianten-Eigenschaften existieren, abbrechen
        if (empty($parsedAttributes)) {
            session()->flash('variants_error', 'Keine Varianten-Eigenschaften mit mehreren kommagetrennten Werten (z. B. "Silber, Gold") gefunden.');
            return;
        }

        // 3. Kartesisches Produkt (Alle Kombinationsmöglichkeiten kreuzen, mit DoS-Limit von max. 200 Varianten)
        $combinations = [[]];
        foreach ($parsedAttributes as $attrName => $options) {
            if (count($combinations) * count($options) > 200) {
                session()->flash('variants_error', 'Zu viele Varianten-Kombinationen (Maximal 200 erlaubt).');
                return;
            }
            $append = [];
            foreach ($combinations as $currentCombination) {
                foreach ($options as $option) {
                    $currentCombination[$attrName] = $option;
                    $append[] = $currentCombination;
                }
            }
            $combinations = $append;
        }

        // 4. Matrix mit bestehenden Daten zusammenführen (Overwriting verhindern)
        $newVariantsList = [];
        $baseSku = $this->product->sku ?: 'SKU';
        $existingCollection = collect($this->variants);

        foreach ($combinations as $index => $combo) {
            // Signatur generieren, um die Variante eindeutig zu erkennen (z.B. "Farbe:Rot|Größe:M")
            ksort($combo); // Alphabetisch sortieren für konsistente Signatur
            $signature = implode('|', array_map(function ($k, $v) { return "$k:$v"; }, array_keys($combo), $combo));
            $name = implode(' - ', $combo);

            // Prüfen, ob diese Kombination bereits in unseren Varianten existiert
            $existingVariant = $existingCollection->firstWhere('signature', $signature);

            // Smart-Fallback: Falls zuvor Einzel-Attribute in der Signatur steckten, über Teil-Attribute oder Namen matchen
            if (!$existingVariant) {
                $existingVariant = $existingCollection->first(function ($v) use ($combo, $name) {
                    if (!is_array($v)) {
                        return false;
                    }
                    if (($v['name'] ?? '') === $name) {
                        return true;
                    }
                    $vAttrs = is_array($v['attributes'] ?? null) ? $v['attributes'] : [];
                    if (empty($vAttrs)) {
                        return false;
                    }
                    foreach ($combo as $ck => $cv) {
                        if (($vAttrs[$ck] ?? null) !== $cv) {
                            return false;
                        }
                    }
                    return true;
                });
            }

            if ($existingVariant) {
                // Behalte bestehende Usereingaben (inkl. ID, Preis, Bestand, SKU) bei
                $newVariantsList[] = array_merge($existingVariant, [
                    'id' => $existingVariant['id'] ?? Str::uuid()->toString(),
                    'signature' => $signature,
                    'name' => $name,
                    'attributes' => $combo,
                    'sku' => $existingVariant['sku'] ?? "{$baseSku}-" . ($index + 1),
                    'price' => $existingVariant['price'] ?? '',
                    'stock' => $existingVariant['stock'] ?? '',
                    'is_active' => $existingVariant['is_active'] ?? true,
                ]);
            } else {
                // Komplett neue Variante anlegen
                $newVariantsList[] = [
                    'id' => Str::uuid()->toString(),
                    'signature' => $signature,
                    'name' => $name,
                    'attributes' => $combo,
                    'sku' => "{$baseSku}-" . ($index + 1),
                    'price' => '',
                    'stock' => '',
                    'is_active' => true,
                ];
            }
        }

        $this->variants = $newVariantsList;
        $this->saveVariants();

        session()->flash('variants_success', count($this->variants) . ' Varianten erfolgreich verarbeitet.');
    }

    public function removeVariant($index)
    {
        if (isset($this->variants[$index])) {
            unset($this->variants[$index]);
            $this->variants = array_values($this->variants);
            $this->saveVariants();
        }
    }

    public function saveVariants()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        // Sanitize variants data (ensure prices and stocks are valid and cap at 200 variants)
        $cleanVariants = [];
        foreach (array_slice($this->variants, 0, 200) as $variant) {
            if (!is_array($variant)) continue;
            if (isset($variant['price']) && $variant['price'] !== '') {
                $variant['price'] = max(0, (float)str_replace(',', '.', $variant['price']));
            }
            if (isset($variant['stock']) && $variant['stock'] !== '') {
                $variant['stock'] = max(0, (int)$variant['stock']);
            }
            $variant['is_active'] = (bool) ($variant['is_active'] ?? true);
            $cleanVariants[] = $variant;
        }
        $this->variants = $cleanVariants;

        $this->product->variants_data = $this->variants;
        $this->product->save();

        $this->dispatch('product-updated');
        $this->dispatch('variants-saved'); // <-- NEU: Signal an AlpineJS
    }

    public function render()
    {
        return view('livewire.shop.product.product-variants');
    }
}
