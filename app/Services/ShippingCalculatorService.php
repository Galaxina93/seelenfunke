<?php

namespace App\Services;

use App\Models\ShopSetting;
use App\Models\Shipping\ShippingZone;
use Illuminate\Support\Collection;

class ShippingCalculatorService
{
    /**
     * Prüft, ob für diese Zusammenstellung Versandkosten anfallen.
     *
     * @param Collection|array $items Liste von CartItems oder Products
     * @return bool
     */
    public function needsShipping($items): bool
    {
        // 1. Leerer Warenkorb braucht keinen Versand
        if (collect($items)->isEmpty()) {
            return false;
        }

        // 2. Einstellung "Versand für Digitales überspringen" laden
        // Wir nutzen shop_setting() Helper falls vorhanden, sonst DB direkt
        $skipForNonPhysical = filter_var(shop_setting('skip_shipping_for_digital', false), FILTER_VALIDATE_BOOLEAN);

        // Wenn die Einstellung AUS ist, berechnen wir IMMER Versand (sofern Items da sind)
        if (!$skipForNonPhysical) {
            return true;
        }

        // 3. Prüfen: Sind ALLE Produkte "nicht-physisch" (also Digital oder Service)?
        // Wir nutzen every(): Gibt true zurück, wenn der Callback für ALLE Elemente true ist.
        $allNonPhysical = collect($items)->every(function ($item) {
            // Flexible Handhabung: $item kann CartItem oder Product sein
            $product = $item instanceof \App\Models\Product\Product ? $item : ($item->product ?? null);

            if (!$product) return false;

            // ERWEITERTE LOGIK:
            // Wir prüfen, ob das Produkt NICHT physisch ist.
            // Das schließt 'digital' UND 'service' ein.
            if (method_exists($product, 'isPhysical')) {
                return !$product->isPhysical();
            }

            // Fallback auf alte Methode, falls Model noch nicht aktualisiert
            return $product->isDigital();
        });

        // Wenn alles nicht-physisch ist (Digital/Service) -> KEIN Versand (false)
        // Wenn auch nur eins physisch ist -> Versand (true)
        return !$allNonPhysical;
    }

    /**
     * Berechnet die exakten Versandkosten.
     * Ersetzt die alte 'determineShippingCost' Logik.
     */
    public function calculateShippingCost($items, int $cartValueCents, float $totalWeight, string $countryCode = 'DE'): array
    {
        // SCHRITT 0: Brauchen wir überhaupt Versand?
        if (!$this->needsShipping($items)) {
            return [
                'cost' => 0,
                'is_free' => true,
                'missing' => 0,
                'reason' => 'non_physical_only' // Debug Info: Nur Digitales oder Services
            ];
        }

        // Globale Einstellungen laden
        $globalThreshold = (int) shop_setting('shipping_free_threshold', 5000);
        $globalDefaultCost = (int) shop_setting('shipping_cost', 490);

        // SCHRITT 1: Zone finden
        $zone = ShippingZone::whereHas('countries', fn($q) => $q->where('country_code', $countryCode))
            ->with('rates')
            ->first();

        // Fallback "Weltweit"
        if (!$zone) {
            $zone = ShippingZone::where('name', 'Weltweit')->with('rates')->first();
        }

        // Initialisierung Status für DE (Prioritäts-Logik)
        $isFreeByGlobalSettings = ($countryCode === 'DE' && $cartValueCents >= $globalThreshold);

        // Absoluter Fallback (Keine Zone/Rate)
        if (!$zone) {
            $missing = $isFreeByGlobalSettings ? 0 : max(0, $globalThreshold - $cartValueCents);
            return [
                'cost' => $isFreeByGlobalSettings ? 0 : $globalDefaultCost,
                'is_free' => $isFreeByGlobalSettings,
                'missing' => ($countryCode === 'DE') ? $missing : 0
            ];
        }

        // SCHRITT 2: Passende Rate finden
        $validRates = $zone->rates()
            ->where(function($q) use ($totalWeight) {
                $q->where('min_weight', '<=', $totalWeight)
                    ->where(fn($sub) => $sub->where('max_weight', '>=', $totalWeight)->orWhereNull('max_weight'));
            })
            ->where('min_price', '<=', $cartValueCents)
            ->orderBy('price', 'asc')
            ->get();

        $bestRate = $validRates->first();

        // SCHRITT 3: "Noch X Euro bis versandkostenfrei"
        $freeShippingRateFromZone = $zone->rates()
            ->where('price', 0)
            ->where('min_price', '>', $cartValueCents)
            ->where('min_weight', '<=', $totalWeight)
            ->orderBy('min_price', 'asc')
            ->first();

        $missing = 0;

        // A: Logik Deutschland
        if ($countryCode === 'DE') {
            if (!$isFreeByGlobalSettings) {
                $missing = max(0, $globalThreshold - $cartValueCents);

                if ($freeShippingRateFromZone) {
                    $zoneMissing = max(0, $freeShippingRateFromZone->min_price - $cartValueCents);
                    $missing = min($missing, $zoneMissing);
                }
            }
        }
        // B: Logik Ausland
        else {
            if ($freeShippingRateFromZone) {
                $missing = max(0, $freeShippingRateFromZone->min_price - $cartValueCents);
            }
        }

        // SCHRITT 4: Finales Resultat

        // FALL 1: DE & Global Free
        if ($isFreeByGlobalSettings) {
            return [
                'cost' => 0,
                'is_free' => true,
                'missing' => 0
            ];
        }

        // FALL 2: Rate gefunden
        if ($bestRate) {
            $finalCost = $bestRate->price;
            $isFree = ($finalCost === 0);

            // Sicherheits-Check für DE
            if ($countryCode === 'DE' && $finalCost > 0) {
                $finalCost = $globalDefaultCost;
            }

            return [
                'cost' => $finalCost,
                'is_free' => $isFree,
                'missing' => $isFree ? 0 : $missing
            ];
        }

        // FALL 3: Fallback
        return [
            'cost' => ($countryCode === 'DE') ? $globalDefaultCost : 2990,
            'is_free' => false,
            'missing' => $missing
        ];
    }
}
