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
        $taxesBreakdownCents = [];

        foreach ($this->items ?? [] as $item) {
            $lineGross = $item->total_price ?? $item['total_price'] ?? 0;
            $goodsGrossCents += $lineGross;

            // SICHERER ZUGRIFF AUF CONFIGURATION (Verhindert stdClass Fehler)
            $config = null;
            if (is_object($item) && isset($item->configuration)) {
                $config = $item->configuration;
            } elseif (is_array($item) && isset($item['configuration'])) {
                $config = $item['configuration'];
            }

            // TAX CALCULATION FOR ITEM
            $itemTax = is_object($item) ? ($item->tax_rate ?? null) : ($item['tax_rate'] ?? null);
            $taxRate = (float)($itemTax !== null ? $itemTax : $defaultTaxRate);
            $lineNet = (int)round($lineGross / (1 + ($taxRate / 100)));
            $lineTax = $lineGross - $lineNet;

            if (!$isSmallBusiness) {
                $strRate = number_format($taxRate, 0);
                if (!isset($taxesBreakdownCents[$strRate])) $taxesBreakdownCents[$strRate] = 0;
                $taxesBreakdownCents[$strRate] += $lineTax;
            }

            $items[] = [
                'name'         => is_object($item) ? ($item->product_name ?? $item->name ?? 'Unbekanntes Produkt') : ($item['product_name'] ?? 'Unbekanntes Produkt'),
                'quantity'     => is_object($item) ? $item->quantity : ($item['quantity'] ?? 1),
                'single_price' => number_format((is_object($item) ? ($item->unit_price ?? 0) : ($item['unit_price'] ?? 0)) / 100, 2, ',', '.'),
                'total_price'  => number_format($lineGross / 100, 2, ',', '.'),
                'config'       => $config
            ];
        }

        // Apply discount proportion to taxes
        $totalDiscountCents = ($this->discount_amount ?? 0) + ($this->volume_discount ?? 0);
        $discountRatio = $goodsGrossCents > 0 ? max(0, ($goodsGrossCents - $totalDiscountCents) / $goodsGrossCents) : 1;

        if (!$isSmallBusiness) {
            foreach ($taxesBreakdownCents as $rate => $tax) {
                $taxesBreakdownCents[$rate] = (int)round($tax * $discountRatio);
            }
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

        // 4. MAXIMALER STEUERSATZ (Für Versand & Express relevant in EU)
        // Find the maximum tax rate used by any item in this specific order/invoice
        $maxTaxRate = -1.0;
        foreach ($this->items ?? [] as $item) {
            $itemTax = null;
            if (is_object($item)) {
                $itemTax = $item->tax_rate ?? ($item->product->tax_rate ?? null);
            } else {
                $itemTax = $item['tax_rate'] ?? ($item['product']['tax_rate'] ?? null);
            }
            $taxRate = (float)($itemTax !== null ? $itemTax : $defaultTaxRate);
            if ($taxRate > $maxTaxRate) {
                $maxTaxRate = $taxRate;
            }
        }
        
        // Wenn keine Items da sind oder nichts gefunden wurde, Fallback verwenden
        if ($maxTaxRate < 0) {
            $maxTaxRate = $defaultTaxRate;
        }

        $dynamicDivisor = $isSmallBusiness ? 1.0 : (1 + ($maxTaxRate / 100));

        // 5. Versandkosten-Logik (Zentrale Prüfung der Schwelle)
        // Wir prüfen subtotal_price (Order) oder goodsGrossCents (berechnet) gegen die Schwelle
        $calcBase = $this->subtotal_price ?? $goodsGrossCents;
        $actualShippingGross = ($calcBase >= $shippingThreshold) ? 0 : ($this->shipping_price ?? $this->shipping_cost ?? 0);
        $shippingNettoCents  = (int)round($actualShippingGross / $dynamicDivisor);

        if ($actualShippingGross > 0 && !$isSmallBusiness) {
            $shippingTaxCents = $actualShippingGross - $shippingNettoCents;
            $strShipRate = number_format($maxTaxRate, 0);
            if (!isset($taxesBreakdownCents[$strShipRate])) $taxesBreakdownCents[$strShipRate] = 0;
            $taxesBreakdownCents[$strShipRate] += $shippingTaxCents;
        }

        // 6. Express-Logik
        $expressGross = $this->is_express ? $expressSurcharge : 0;
        $expressNettoCents = (int)round($expressGross / $dynamicDivisor);

        if ($expressGross > 0 && !$isSmallBusiness) {
            $expressTaxCents = $expressGross - $expressNettoCents;
            $strExpRate = number_format($maxTaxRate, 0);
            if (!isset($taxesBreakdownCents[$strExpRate])) $taxesBreakdownCents[$strExpRate] = 0;
            $taxesBreakdownCents[$strExpRate] += $expressTaxCents;
        }

        // 7. Reiner Warenwert Netto (Gesamt-Netto minus Nebenkosten)
        // Verhindert negative Werte, falls die Summen-Felder in der DB leer sind
        $goodsNettoCents = max(0, $totalNettoCents - $expressNettoCents - $shippingNettoCents);

        // 7. Breakdown formatieren
        $formattedTaxBreakdown = [];
        if (!$isSmallBusiness) {
            foreach ($taxesBreakdownCents as $rate => $cents) {
                if ($cents > 0 || floatval($rate) == 0) {
                    $formattedTaxBreakdown[$rate] = number_format($cents / 100, 2, ',', '.');
                }
            }
        }

        return [
            // Identifikatoren
            'quote_number' => $this->invoice_number ?? $this->order_number ?? $this->quote_number ?? 'N/A',
            'quote_token'  => $this->token ?? '',
            'quote_expiry' => $this->expires_at
                ? $this->expires_at->format('d.m.Y')
                : ($this->due_date ? $this->due_date->format('d.m.Y') : now()->addDays($validityDays)->format('d.m.Y')),

            'express'  => (bool)$this->is_express,
            'deadline' => $this->deadline,

            'payment_url' => $this->payment_url ?? null,

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

            // --- NEU: GUTSCHEINE UND RABATTE ---
            'volume_discount' => number_format(($this->volume_discount ?? 0) / 100, 2, ',', '.'),
            'discount_amount' => number_format(($this->discount_amount ?? 0) / 100, 2, ',', '.'),
            'coupon_code'     => $this->coupon_code ?? null,
            // -----------------------------------

            // Steuer Aufschlüsselung
            'tax_breakdown'  => $formattedTaxBreakdown,

            // Einzel-Netto-Werte für Partials
            'display_netto_goods'    => number_format($goodsNettoCents / 100, 2, ',', '.') . ' €',
            'display_netto_express'  => number_format($expressNettoCents / 100, 2, ',', '.') . ' €',
            'display_netto_shipping' => number_format($shippingNettoCents / 100, 2, ',', '.') . ' €',

            'is_small_business' => $isSmallBusiness,
            'tax_rate'          => $defaultTaxRate,
            'tax_note'          => $isSmallBusiness
                ? 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.'
                : "Enthaltene MwSt. ({$defaultTaxRate}%):",

            // Erweiterung für Buchhaltung & Archivierung
            'is_e_invoice'      => (bool)($this->is_e_invoice ?? false),
            'pdf_exists'        => (bool)($this->has_archived_pdf ?? false),
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
