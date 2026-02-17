<?php

namespace App\Livewire\Shop\Configurator\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait HandlesConfiguratorLogic
{
    public function calculatePrice()
    {
        $basePrice = $this->product->price;
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

        $this->texts[] = [
            'id' => Str::uuid()->toString(),
            'text' => '',
            'font' => 'Arial',
            'align' => 'center',
            'x' => $x ?? $centerX,
            'y' => $y ?? $centerY,
            'size' => 1.0,
            'rotation' => 0
        ];
    }

    public function removeText($index)
    {
        if ($this->context === 'preview') return;
        unset($this->texts[$index]);
        $this->texts = array_values($this->texts);
        if (count($this->texts) === 0 && !$this->isDigital) $this->addText();
    }

    public function toggleLogo($type, $value)
    {
        if ($this->context === 'preview') return;
        foreach ($this->logos as $key => $logo) {
            if ($logo['value'] == $value) {
                unset($this->logos[$key]);
                $this->logos = array_values($this->logos);
                return;
            }
        }
        $centerX = $this->configSettings['area_left'] + ($this->configSettings['area_width'] / 2);
        $centerY = $this->configSettings['area_top'] + ($this->configSettings['area_height'] / 2);

        $this->logos[] = [
            'id' => Str::uuid()->toString(),
            'type' => 'saved',
            'value' => $value,
            'url' => asset('storage/' . $value), // <--- HIER REIN
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

        foreach($this->uploaded_files as $path) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);

            // WICHTIG: Nur Bilder kommen in die Vorschau (Stage)
            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) {

                // Prüfen ob schon auf der Stage
                $exists = collect($this->logos)->contains('value', $path);

                if (!$exists) {
                    $this->logos[] = [
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
            // PDFs werden ignoriert für die Stage, bleiben aber in $uploaded_files erhalten
        }
    }

    public function removeFile($index)
    {
        if ($this->context === 'preview') return;

        // 1. Pfad holen
        $path = $this->uploaded_files[$index] ?? null;
        if (!$path) return;

        // 2. Aus den aktiven Logos auf der Stage entfernen
        // WICHTIG: Wir nutzen collect()->values(), um den Index neu zu ordnen
        $this->logos = collect($this->logos)
            ->filter(fn($l) => $l['value'] !== $path)
            ->values()
            ->toArray();

        // 3. Aus den hochgeladenen Dateien löschen
        unset($this->uploaded_files[$index]);
        $this->uploaded_files = array_values($this->uploaded_files);

        // 4. Physisch vom Speicher löschen
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function isLogoActive($path)
    {
        return collect($this->logos)->contains('value', $path);
    }

    public function getRenderedLogosProperty()
    {
        $rendered = [];
        foreach ($this->logos as $logo) {
            $url = asset('storage/' . $logo['value']);
            $rendered[] = array_merge($logo, ['url' => $url]);
        }
        return $rendered;
    }
}
