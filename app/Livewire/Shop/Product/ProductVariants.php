<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductVariants extends Component
{
    public Product $product;

    // Die Matrix der Endprodukte
    public array $variants = [];

    public function mount(Product $product)
    {
        $this->product = $product;
        // Wir laden die Varianten aus dem Product-Model.
        // Falls du eine eigene Tabelle dafür hast, lade sie von dort.
        // Als "bombproof" Standalone-Lösung speichere ich es in eine JSON-Eigenschaft oder nutze einen Array-Cast am Model.
        $this->variants = $this->product->variants_data ?? [];
    }

    public function generateMatrix()
    {
        // 1. Aktuelle Attribute aus dem Eltern-Produkt laden
        $attributes = $this->product->refresh()->attributes ?? [];
        $parsedAttributes = [];

        // 2. Werte bereinigen und aufteilen (Trenner: Komma)
        foreach ($attributes as $key => $val) {
            if (!empty($val)) {
                // Teile am Komma, entferne Leerzeichen, filtere leere Einträge
                $options = array_map('trim', explode(',', $val));
                $options = array_filter($options);

                if (count($options) > 0) {
                    $parsedAttributes[$key] = $options;
                }
            }
        }

        // Falls keine parsebaren Eigenschaften existieren, abbrechen
        if (empty($parsedAttributes)) {
            session()->flash('variants_success', 'Keine kommagetrennten Eigenschaften gefunden.');
            return;
        }

        // 3. Kartesisches Produkt (Alle Kombinationsmöglichkeiten kreuzen)
        $combinations = [[]];
        foreach ($parsedAttributes as $attrName => $options) {
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

        foreach ($combinations as $index => $combo) {
            // Signatur generieren, um die Variante eindeutig zu erkennen (z.B. "Farbe:Rot|Größe:M")
            ksort($combo); // Alphabetisch sortieren für konsistente Signatur
            $signature = implode('|', array_map(function ($k, $v) { return "$k:$v"; }, array_keys($combo), $combo));
            $name = implode(' - ', $combo);

            // Prüfen, ob diese Kombination bereits in unseren Varianten existiert
            $existingVariant = collect($this->variants)->firstWhere('signature', $signature);

            if ($existingVariant) {
                // Behalte bestehende Usereingaben bei
                $newVariantsList[] = [
                    'id' => $existingVariant['id'] ?? Str::uuid()->toString(),
                    'signature' => $signature,
                    'name' => $name,
                    'attributes' => $combo,
                    'sku' => $existingVariant['sku'] ?? "{$baseSku}-" . ($index + 1),
                    'price' => $existingVariant['price'] ?? '',
                    'stock' => $existingVariant['stock'] ?? '',
                    'is_active' => $existingVariant['is_active'] ?? true,
                ];
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
