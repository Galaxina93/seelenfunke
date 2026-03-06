<?php

namespace App\Livewire\Shop\Configurator\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait HandlesConfiguratorLogic
{
    public function calculatePrice()
    {
        $basePrice = $this->product->price;

        // 1. NEU: Varianten-Preis überschreiben, falls vorhanden
        if ($this->variantId) {
            $variants = $this->product->variants_data ?? [];
            $variant = collect($variants)->firstWhere('id', $this->variantId);

            // Wenn die Variante gefunden wurde UND einen abweichenden Preis hat
            if ($variant && !empty($variant['price'])) {
                $basePrice = (int) round((float) $variant['price'] * 100);
            }
        }

        // 2. Mengenrabatt (Staffelpreise) anwenden
        $tierPricing = $this->product->tierPrices ?? $this->product->tier_pricing;

        if (!empty($tierPricing)) {
            if (is_object($tierPricing)) {
                $tier = $tierPricing->where('qty', '<=', $this->qty)->sortByDesc('qty')->first();
                if ($tier) {
                    $basePrice -= ($basePrice * ($tier->percent / 100));
                }
            } elseif (is_array($tierPricing)) {
                usort($tierPricing, fn($a, $b) => $b['qty'] <=> $a['qty']);
                foreach ($tierPricing as $tier) {
                    if ($this->qty >= $tier['qty']) {
                        $basePrice -= ($basePrice * ($tier['percent'] / 100));
                        break;
                    }
                }
            }
        }

        // 3. Steuer aufschlagen (falls Netto-Eingabe)
        if ($this->product->tax_included === false) {
            $taxRate = (float) ($this->product->tax_rate ?? 19.0);
            $basePrice = (int) round($basePrice * (1 + ($taxRate / 100)));
        }

        $this->currentPrice = $basePrice;
        $this->totalPrice = $basePrice * $this->qty;
    }

    public function addText($x = null, $y = null)
    {
        if ($this->context === 'preview') return;
        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $newItem = [
            'id' => Str::uuid()->toString(),
            'text' => '',
            'font' => 'Arial',
            'align' => 'center',
            'x' => $x ?? $centerX,
            'y' => $y ?? $centerY,
            'size' => 1.0,
            'rotation' => 0
        ];

        // Prüft, welche Seite aktiv ist
        if ($this->activeSide === 'back') {
            $this->texts_back[] = $newItem;
        } else {
            $this->texts[] = $newItem;
        }
    }

    public function removeText($index)
    {
        if ($this->context === 'preview') return;

        // Array dynamisch ansteuern
        if ($this->activeSide === 'back') {
            unset($this->texts_back[$index]);
            $this->texts_back = array_values($this->texts_back);
            if (count($this->texts_back) === 0 && !$this->isDigital) $this->addText();
        } else {
            unset($this->texts[$index]);
            $this->texts = array_values($this->texts);
            if (count($this->texts) === 0 && !$this->isDigital) $this->addText();
        }
    }

    public function addStandardVector($filename)
    {
        if ($this->context === 'preview') return;
        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $newItem = [
            'id' => Str::uuid()->toString(),
            'type' => 'vector',
            'value' => 'vectors/' . $filename,
            'url' => asset('images/configurator/vectors/' . $filename),
            'x' => $centerX,
            'y' => $centerY,
            'size' => 100,
            'rotation' => 0
        ];

        if ($this->activeSide === 'back') {
            $this->logos_back[] = $newItem;
        } else {
            $this->logos[] = $newItem;
        }
    }

    public function toggleLogo($type, $value)
    {
        if ($this->context === 'preview') return;

        // Bestimme das aktive Array
        $targetProperty = $this->activeSide === 'back' ? 'logos_back' : 'logos';

        // Existiert das Logo auf der AKTUELLEN Seite schon? Dann entfernen.
        foreach ($this->$targetProperty as $key => $logo) {
            if ($logo['value'] == $value) {
                unset($this->$targetProperty[$key]);
                $this->$targetProperty = array_values($this->$targetProperty);
                return;
            }
        }

        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        // Ansonsten zur aktuellen Seite hinzufügen
        $this->$targetProperty[] = [
            'id' => Str::uuid()->toString(),
            'type' => 'saved',
            'value' => $value,
            'url' => asset('storage/' . $value),
            'x' => $centerX,
            'y' => $centerY,
            'size' => 130,
            'rotation' => 0
        ];
    }

    public function addFilesToStage()
    {
        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $targetProperty = $this->activeSide === 'back' ? 'logos_back' : 'logos';

        foreach($this->uploaded_files as $path) {
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
                }
            }
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

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
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
        foreach ($this->logos as $logo) {
            $url = Str::startsWith($logo['value'], 'vectors/') ? asset('images/configurator/' . $logo['value']) : asset('storage/' . $logo['value']);
            $rendered[] = array_merge($logo, ['url' => $url]);
        }
        return $rendered;
    }
}
