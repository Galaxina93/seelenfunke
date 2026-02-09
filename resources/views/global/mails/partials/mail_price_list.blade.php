{{-- resources/views/global/mails/partials/mail_price_list.blade.php --}}
<div class="totals" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
    <table width="100%" style="border-collapse: collapse;">

        @php
            // 1. Hilfsfunktion zum Parsen von deutschen Zahlen-Strings (z.B. "1.250,50") zu Floats
            $parseNum = function($val) {
                if(is_numeric($val)) return (float)$val;
                if(empty($val)) return 0.0;
                // Entferne Tausender-Punkt, ersetze Komma durch Punkt
                return (float)str_replace(['.', ','], ['', '.'], $val);
            };

            // 2. Werte holen
            $totalGross = $parseNum($data['total_gross']);
            $shippingGross = $parseNum($data['shipping_price']);

            // Rabatte (Wir schauen, ob sie im $order Objekt oder im $data Array stecken)
            $volDiscount = 0;
            $couponDiscount = 0;
            $couponCode = null;

            if(isset($order)) {
                $volDiscount = $order->volume_discount / 100; // DB ist in Cents
                $couponDiscount = $order->discount_amount / 100;
                $couponCode = $order->coupon_code;
            } elseif(isset($data['volume_discount']) || isset($data['discount_amount'])) {
                // Fallback, falls $data die Werte direkt hat (als String)
                $volDiscount = $parseNum($data['volume_discount'] ?? 0);
                $couponDiscount = $parseNum($data['discount_amount'] ?? 0);
                $couponCode = $data['coupon_code'] ?? '';
            }

            // 3. Express Berechnung
            $expressGross = 0;
            if(!empty($data['express']) || (isset($order) && $order->is_express)) {
                $expressGross = (float)shop_setting('express_surcharge', 2500) / 100;
            }

            // 4. KORREKTE RÜCKWÄRTSRECHNUNG:
            // Warenwert = Endsumme + Rabatte - Versand - Express
            $goodsValue = $totalGross + $volDiscount + $couponDiscount - $shippingGross - $expressGross;
        @endphp

        {{-- Warenwert Brutto --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Warenwert (Brutto):</td>
            <td width="120" class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                {{ number_format($goodsValue, 2, ',', '.') }} €
            </td>
        </tr>

        {{-- Mengenrabatt --}}
        @if($volDiscount > 0)
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">Mengenrabatt:</td>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">
                    -{{ number_format($volDiscount, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Gutschein --}}
        @if($couponDiscount > 0)
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">
                    Gutschein {{ $couponCode ? "($couponCode)" : '' }}:
                </td>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">
                    -{{ number_format($couponDiscount, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Express-Zuschlag --}}
        @if($expressGross > 0)
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-weight: bold; font-size: 13px; text-align: right;">
                    🔥 Express-Service:
                </td>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-weight: bold; font-size: 13px; text-align: right;">
                    +{{ number_format($expressGross, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Versandkosten --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Versandkosten:</td>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                @if($shippingGross <= 0)
                    <span style="color: #16a34a; font-weight: bold;">Kostenlos</span>
                @else
                    {{ number_format($shippingGross, 2, ',', '.') }} €
                @endif
            </td>
        </tr>

        {{-- Gesamtsumme Brutto --}}
        <tr class="totals-final">
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">Gesamtsumme:</td>
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">
                {{ $data['total_gross'] }} €
            </td>
        </tr>

        {{-- Steuerrechtliche Aufschlüsselung --}}
        <tr>
            <td class="text-right" style="padding-top: 15px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                Nettobetrag:
            </td>
            <td class="text-right" style="padding-top: 15px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                {{ $data['total_netto'] }} €
            </td>
        </tr>
        <tr>
            <td class="text-right" style="padding-bottom: 10px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                {{ $data['tax_note'] ?? 'Enthaltene MwSt.:' }}
            </td>
            <td class="text-right" style="padding-bottom: 10px; color: #9ca3af; font-size: 11px; font-style: italic; text-align: right;">
                @if(empty($data['is_small_business']) && isset($data['total_vat']))
                    {{ $data['total_vat'] }} €
                @elseif(!empty($data['is_small_business']))
                    0,00 €
                @endif
            </td>
        </tr>
    </table>

    {{-- ZAHLUNGSBUTTON --}}
    @if(isset($data['payment_url']) && $data['payment_url'])
        <div style="margin-top: 30px; margin-bottom: 10px; text-align: center; border-top: 1px dashed #eee; padding-top: 20px;">
            <p style="margin-bottom: 15px; color: #555; font-size: 14px;">
                Um Ihre Bestellung abzuschließen, können Sie hier sicher online bezahlen:
            </p>

            <a href="{{ $data['payment_url'] }}"
               style="background-color: #C5A059; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                🔒 Jetzt sicher bezahlen
            </a>

            <p style="font-size: 11px; color: #999; margin-top: 12px;">
                (Kreditkarte, PayPal, Apple Pay, Klarna, Sofort)
            </p>
        </div>
    @endif

</div>
