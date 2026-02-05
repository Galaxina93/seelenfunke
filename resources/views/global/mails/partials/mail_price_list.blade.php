{{-- resources/views/mails/partials/mail_price_list.blade.php --}}
<div class="totals" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
    <table width="100%" style="border-collapse: collapse;">

        {{-- Warenwert Brutto (Warenkorb-Summe vor Versand/Rabatt) --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Warenwert:</td>
            <td width="100" class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                @php
                    // Wir berechnen den Brutto-Warenwert der Items für die Anzeige
                    $itemsGrossCents = 0;
                    foreach($data['items'] as $item) {
                        $itemsGrossCents += (float)str_replace(['.', ','], ['', ''], $item['total_price']);
                    }
                @endphp
                {{ number_format($itemsGrossCents / 100, 2, ',', '.') }} €
            </td>
        </tr>

        {{-- Mengenrabatt (falls vorhanden) --}}
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
                    @php
                        $expressGross = shop_setting('express_surcharge', 2500);
                    @endphp
                    {{ number_format($expressGross / 100, 2, ',', '.') }} €
                </td>
            </tr>
        @endif

        {{-- Gesamtsumme Brutto (Hervorgehoben) --}}
        <tr class="totals-final">
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">Gesamtsumme:</td>
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">{{ $data['total_gross'] }} €</td>
        </tr>

        {{-- Steuerrechtliche Aufschlüsselung (Informativ am Ende) --}}
        <tr>
            <td class="text-right" style="padding-top: 10px; color: #888; font-size: 11px; font-style: italic; text-align: right;">
                Nettobetrag:
            </td>
            <td class="text-right" style="padding-top: 10px; color: #888; font-size: 11px; font-style: italic; text-align: right;">
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
