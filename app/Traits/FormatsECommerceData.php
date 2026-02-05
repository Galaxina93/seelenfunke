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
            $lineGross = $item->total_price ?? 0;
            $goodsGrossCents += $lineGross;

            $items[] = [
                'name'         => $item->product_name ?? $item->name ?? 'Unbekanntes Produkt',
                'quantity'     => $item->quantity,
                'single_price' => number_format(($item->unit_price ?? 0) / 100, 2, ',', '.'),
                'total_price'  => number_format($lineGross / 100, 2, ',', '.'),
                'config'       => $item->configuration
            ];
        }

        // 3. Brutto-Summen aus dem Model ermitteln (Robustheit für Invoice, Order, Quote)
        // Wir suchen das Brutto-Feld: Order (total_price), Quote (gross_total), Invoice (total)
        $grossTotalCents = $this->total_price ?? $this->gross_total ?? $this->total ?? 0;

        // Wir suchen das Steuer-Feld: Order (tax_amount), Quote (tax_total), Invoice (tax_amount)
        $taxAmountCents = $this->tax_amount ?? $this->tax_total ?? 0;

        // Falls tax_amount 0 ist (z.B. bei Invoices nicht explizit gespeichert), rückrechnen
        if ($taxAmountCents === 0 && !$isSmallBusiness && $grossTotalCents > 0) {
            $taxAmountCents = (int)($grossTotalCents - round($grossTotalCents / $divisor));
        }

        // Gesamt-Netto
        $totalNettoCents = $grossTotalCents - $taxAmountCents;

        // 4. Versandkosten-Logik (Zentrale Prüfung der Schwelle)
        // Wir prüfen subtotal_price (Order) oder goodsGrossCents (berechnet) gegen die Schwelle
        $calcBase = $this->subtotal_price ?? $goodsGrossCents;
        $actualShippingGross = ($calcBase >= $shippingThreshold) ? 0 : ($this->shipping_price ?? $this->shipping_cost ?? 0);
        $shippingNettoCents  = (int)round($actualShippingGross / $divisor);

        // 5. Express-Logik
        $expressGross = $this->is_express ? $expressSurcharge : 0;
        $expressNettoCents = (int)round($expressGross / $divisor);

        // 6. Reiner Warenwert Netto (Gesamt-Netto minus Nebenkosten)
        // Verhindert negative Werte, falls die Summen-Felder in der DB leer sind
        $goodsNettoCents = max(0, $totalNettoCents - $expressNettoCents - $shippingNettoCents);

        return [
            // Identifikatoren
            'quote_number' => $this->invoice_number ?? $this->order_number ?? $this->quote_number ?? 'N/A',
            'quote_token'  => $this->token ?? '',
            'quote_expiry' => $this->expires_at
                ? $this->expires_at->format('d.m.Y')
                : ($this->due_date ? $this->due_date->format('d.m.Y') : now()->addDays($validityDays)->format('d.m.Y')),

            'express'  => (bool)$this->is_express,
            'deadline' => $this->deadline,

            // Kontakt & Adressen
            'contact'          => $this->getMailContactData(),
            'billing_address'  => $this->billing_address ?? [],
            'shipping_address' => $this->shipping_address ?? $this->billing_address ?? [],

            'items' => $items,

            // Formatiert für die Anzeige
            'total_netto'    => number_format($totalNettoCents / 100, 2, ',', '.'),
            'total_vat'      => number_format($taxAmountCents / 100, 2, ',', '.'),
            'total_gross'    => number_format($grossTotalCents / 100, 2, ',', '.'),
            'shipping_price' => number_format($actualShippingGross / 100, 2, ',', '.'),

            // Einzel-Netto-Werte für Partials
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
        // Logik für Order / Invoice (billing_address Array)
        if (isset($this->billing_address['first_name'])) {
            return [
                'vorname'   => $this->billing_address['first_name'],
                'nachname'  => $this->billing_address['last_name'],
                'firma'     => $this->billing_address['company'] ?? '',
                'email'     => $this->email ?? ($this->order->email ?? ''),
                'telefon'   => $this->billing_address['phone'] ?? $this->phone ?? '',
                'anmerkung' => $this->notes ?? ($this->admin_notes ?? ''),
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
