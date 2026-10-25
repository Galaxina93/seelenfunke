<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductVariants extends Component
{
    #[\Livewire\Attributes\Locked]
    public Product $product;

    // Die Matrix der Endprodukte
    public array $variants = [];

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount(Product $product)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $this->product = $product;
        // Wir laden die Varianten aus dem Product-Model.
        $this->variants = $this->product->variants_data ?? [];
    }

    #[On('product-updated')]
    public function refreshProduct(): void
    {
        $this->product->refresh();
        $this->variants = $this->product->variants_data ?? [];
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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
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
