{{-- resources/views/mails/partials/mail_price_list.blade.php --}}
<div class="totals" style="margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px;">
    <table width="100%" style="border-collapse: collapse;">
        {{-- Warenwert --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Warenwert (Netto):</td>
            <td width="100" class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">{{ $data['total_netto'] }} €</td>
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

        {{-- Versandkosten --}}
        <tr>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">Versandkosten:</td>
            <td class="text-right" style="padding-bottom: 5px; color: #666; font-size: 13px; text-align: right;">
                {{ ($data['shipping_price'] == '0,00') ? 'Kostenlos' : $data['shipping_price'] . ' €' }}
            </td>
        </tr>

        {{-- Express-Zuschlag --}}
        @if(!empty($data['express']))
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-size: 13px; text-align: right;">Express-Service:</td>
                <td class="text-right" style="padding-bottom: 5px; color: #dc2626; font-size: 13px; text-align: right;">25,00 €</td>
            </tr>
        @endif

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

        {{-- Gesamtsumme --}}
        <tr class="totals-final">
            <td class="text-right" style="padding-top: 15px; border-top: 1px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">Gesamtsumme (Brutto):</td>
            <td class="text-right" style="padding-top: 15px; border-top: 1px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059; text-align: right;">{{ $data['total_gross'] }} €</td>
        </tr>
    </table>
</div>
