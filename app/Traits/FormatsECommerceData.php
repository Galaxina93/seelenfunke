<?php

namespace App\Traits;

trait FormatsECommerceData
{
    /**
     * Bereitet die Daten für Mails, PDFs und Web-Views einheitlich auf.
     */
    public function toFormattedArray()
    {
        // 1. Globale Shop-Einstellungen laden
        $isSmallBusiness   = (bool)shop_setting('is_small_business', false);
        $defaultTaxRate    = (float)shop_setting('default_tax_rate', 19);
        $validityDays      = (int)shop_setting('order_quote_validity_days', 14);
        $expressSurcharge  = (int)shop_setting('express_surcharge', 2500);
        $shippingThreshold = (int)shop_setting('shipping_free_threshold', 5000);

        // Divisor für Netto-Rückrechnung (z.B. 1.19)
        $divisor = $isSmallBusiness ? 1.0 : (1 + ($defaultTaxRate / 100));

        // 2. Artikel aufbereiten & Brutto-Warenwert der Positionen berechnen
        $items = [];
        $goodsGrossCents = 0;

        foreach ($this->items as $item) {
            $goodsGrossCents += $item->total_price;
            $items[] = [
                'name'         => $item->product_name,
                'quantity'     => $item->quantity,
                'single_price' => number_format($item->unit_price / 100, 2, ',', '.'),
                'total_price'  => number_format($item->total_price / 100, 2, ',', '.'),
                'config'       => $item->configuration
            ];
        }

        // 3. Versandkosten-Logik (Zentrale Prüfung der Schwelle)
        // WICHTIG: Wenn Warenwert >= Schwelle, ist der Versand 0
        $actualShippingGross = ($goodsGrossCents >= $shippingThreshold) ? 0 : ($this->shipping_price ?? 0);
        $shippingNettoCents  = (int)round($actualShippingGross / $divisor);

        // 4. Express-Logik
        $expressGross = $this->is_express ? $expressSurcharge : 0;
        $expressNettoCents = (int)round($expressGross / $divisor);

        // 5. Netto-Gesamtwert bestimmen
        // Für Orders: total_price - tax_amount | Für Quotes: net_total
        $totalNettoCents = isset($this->tax_amount)
            ? ($this->total_price - $this->tax_amount)
            : ($this->net_total ?? 0);

        // 6. Reiner Warenwert Netto (Restwert nach Abzug von Express und Versand)
        $goodsNettoCents = $totalNettoCents - $expressNettoCents - $shippingNettoCents;

        return [
            // Identifikatoren
            'quote_number' => $this->order_number ?? $this->quote_number ?? 'N/A',
            'quote_token'  => $this->token ?? '',
            'quote_expiry' => $this->expires_at
                ? $this->expires_at->format('d.m.Y')
                : now()->addDays($validityDays)->format('d.m.Y'),

            'express'  => (bool)$this->is_express,
            'deadline' => $this->deadline,

            // Kontakt & Adressen (Nutzt Hilfsmethode)
            'contact'          => $this->getMailContactData(),
            'billing_address'  => $this->billing_address ?? [],
            'shipping_address' => $this->shipping_address ?? $this->billing_address ?? [],

            'items' => $items,

            // Formatiert für die Anzeige
            'total_netto'    => number_format($totalNettoCents / 100, 2, ',', '.'),
            'total_vat'      => number_format(($this->tax_amount ?? $this->tax_total ?? 0) / 100, 2, ',', '.'),
            'total_gross'    => number_format(($this->total_price ?? $this->gross_total ?? 0) / 100, 2, ',', '.'),
            'shipping_price' => number_format($actualShippingGross / 100, 2, ',', '.'),

            // Einzel-Netto-Werte
            'display_netto_goods'    => number_format($goodsNettoCents / 100, 2, ',', '.') . ' €',
            'display_netto_express'  => number_format($expressNettoCents / 100, 2, ',', '.') . ' €',
            'display_netto_shipping' => number_format($shippingNettoCents / 100, 2, ',', '.') . ' €',

            'is_small_business' => $isSmallBusiness,
            'tax_rate'          => $defaultTaxRate,
            'tax_note'          => $isSmallBusiness
                ? 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.'
                : "Enthaltene MwSt. ({$defaultTaxRate}%):",
        ];
    }

    /**
     * Hilfsmethode zur einheitlichen Kontakt-Extraktion.
     */
    private function getMailContactData()
    {
        // Logik für Order (billing_address Array)
        if (isset($this->billing_address['first_name'])) {
            return [
                'vorname'   => $this->billing_address['first_name'],
                'nachname'  => $this->billing_address['last_name'],
                'firma'     => $this->billing_address['company'] ?? '',
                'email'     => $this->email,
                'telefon'   => $this->billing_address['phone'] ?? $this->phone ?? '',
                'anmerkung' => $this->notes ?? $this->admin_notes ?? '',
                'country'   => $this->billing_address['country'] ?? 'DE'
            ];
        }

        // Logik für QuoteRequest (Direkte Attribute)
        return [
            'vorname'   => $this->first_name ?? '',
            'nachname'  => $this->last_name ?? '',
            'firma'     => $this->company ?? '',
            'email'     => $this->email ?? '',
            'telefon'   => $this->phone ?? '',
            'anmerkung' => $this->admin_notes ?? '',
            'country'   => $this->country ?? 'DE'
        ];
    }
}
