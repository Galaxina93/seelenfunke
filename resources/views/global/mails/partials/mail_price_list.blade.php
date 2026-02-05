{{-- resources/views/mails/partials/mail_price_list.blade.php --}}
<div class="totals" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
    <table width="100%" style="border-collapse: collapse;">

        {{-- Warenwert Netto --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Warenwert (Netto):</td>
            <td width="100" class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">{{ $data['display_netto_goods'] }}</td>
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

        {{-- Express-Zuschlag (dynamisch aus Trait) --}}
        @if($data['express'])
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-size: 13px; text-align: right;">Express-Service (Netto):</td>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-size: 13px; text-align: right;">{{ $data['display_netto_express'] }}</td>
            </tr>
        @endif

        {{-- Versandkosten (Netto) --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Versandkosten (Netto):</td>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                @if($data['shipping_price'] == '0,00')
                    <span style="color: #16a34a; font-weight: bold;">Kostenlos</span>
                @else
                    {{ $data['display_netto_shipping'] }}
                @endif
            </td>
        </tr>

        {{-- Zwischensumme Netto --}}
        <tr>
            <td class="text-right" style="padding-top: 10px; padding-bottom: 5px; color: #222; font-size: 14px; font-weight: bold; text-align: right; border-top: 1px solid #f5f5f5;">Gesamtsumme (Netto):</td>
            <td class="text-right" style="padding-top: 10px; padding-bottom: 5px; color: #222; font-size: 14px; font-weight: bold; text-align: right; border-top: 1px solid #f5f5f5;">{{ $data['total_netto'] }} €</td>
        </tr>

        {{-- Steuer-Informationen --}}
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

        {{-- Gesamtsumme Brutto --}}
        <tr class="totals-final">
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">Gesamtsumme (Brutto):</td>
            <td class="text-right" style="padding-top: 15px; border-top: 2px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">{{ $data['total_gross'] }} €</td>
        </tr>
    </table>
</div>
