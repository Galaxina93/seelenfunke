{{-- resources/views/mails/partials/mail_price_list.blade.php --}}
<div class="totals" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
    <table width="100%" style="border-collapse: collapse;">

        {{-- Warenwert Brutto (Berechnet aus Gesamtsumme minus Nebenkosten) --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Warenwert (Brutto):</td>
            <td width="100" class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                @php
                    // Wir konvertieren die formatierten Strings zurück in Zahlen für eine sichere Kalkulation
                    $totalGrossNum = (float)str_replace(['.', ','], ['', '.'], $data['total_gross']);
                    $shippingGrossNum = (float)str_replace(['.', ','], ['', '.'], $data['shipping_price']);
                    $expressGrossNum = (!empty($data['express'])) ? (float)shop_setting('express_surcharge', 2500) / 100 : 0;

                    // Der Brutto-Warenwert ist die Gesamtsumme abzüglich der Versand- und Expresskosten
                    $goodsGrossCalculated = $totalGrossNum - $shippingGrossNum - $expressGrossNum;
                @endphp
                {{ number_format($goodsGrossCalculated, 2, ',', '.') }} €
            </td>
        </tr>

        {{-- Mengenrabatt (falls vorhanden und nicht bereits im Warenwert verrechnet) --}}
        @if(isset($order) && $order->volume_discount > 0)
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">Mengenrabatt:</td>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">-{{ number_format($order->volume_discount / 100, 2, ',', '.') }} €</td>
            </tr>
        @endif

        {{-- Gutschein (falls vorhanden) --}}
        @if(isset($order) && $order->discount_amount > 0)
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">Gutschein ({{ $order->coupon_code }}):</td>
                <td class="text-right" style="padding-bottom: 5px; color: #16a34a; font-size: 13px; text-align: right;">-{{ number_format($order->discount_amount / 100, 2, ',', '.') }} €</td>
            </tr>
        @endif

        {{-- Versandkosten Brutto --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Versandkosten:</td>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                @if($data['shipping_price'] == '0,00')
                    <span style="color: #16a34a; font-weight: bold;">Kostenlos</span>
                @else
                    {{ $data['shipping_price'] }} €
                @endif
            </td>
        </tr>

        {{-- Express-Zuschlag Brutto (falls vorhanden) --}}
        @if($data['express'])
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-size: 13px; text-align: right;">Express-Service:</td>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-size: 13px; text-align: right;">
                    {{ number_format($expressGrossNum, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Gesamtsumme Brutto (Der finale Zahlbetrag) --}}
        <tr class="totals-final">
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">Gesamtsumme:</td>
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">{{ $data['total_gross'] }} €</td>
        </tr>

        {{-- Steuerrechtliche Aufschlüsselung (Informativ am Ende) --}}
        <tr>
            <td class="text-right" style="padding-top: 15px; color: #888; font-size: 11px; font-style: italic; text-align: right;">
                Nettobetrag:
            </td>
            <td class="text-right" style="padding-top: 15px; color: #888; font-size: 11px; font-style: italic; text-align: right;">
                {{ $data['total_netto'] }} €
            </td>
        </tr>
        <tr>
            <td class="text-right" style="padding-bottom: 10px; color: #888; font-size: 11px; font-style: italic; text-align: right;">
                {{ $data['tax_note'] }}
            </td>
            <td class="text-right" style="padding-bottom: 10px; color: #888; font-size: 11px; font-style: italic; text-align: right;">
                @if(empty($data['is_small_business']))
                    {{ $data['total_vat'] }} €
                @endif
            </td>
        </tr>
    </table>
</div>
