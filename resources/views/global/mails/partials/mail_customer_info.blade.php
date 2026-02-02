{{-- resources/views/mails/partials/mail_customer_info.blade.php --}}

{{-- 1. KONTAKT-DETAILS (Die "Customer Card" für Admin-Mails) --}}
@if(isset($showContactCard) && $showContactCard)
    <div class="customer-card" style="background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
        <h2 style="font-size: 14px; margin-top: 0; color: #C5A059; text-transform: uppercase; letter-spacing: 1px;">Kundendaten</h2>
        <table width="100%" style="font-size: 13px; border-collapse: collapse;">
            <tr>
                <td style="font-weight: bold; padding: 4px 0; color: #666; width: 120px; vertical-align: top;">Name:</td>
                <td style="padding: 4px 0; color: #222;">{{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}</td>
            </tr>
            @if(!empty($data['contact']['firma']))
                <tr>
                    <td style="font-weight: bold; padding: 4px 0; color: #666; vertical-align: top;">Firma:</td>
                    <td style="padding: 4px 0; color: #222;">{{ $data['contact']['firma'] }}</td>
                </tr>
            @endif
            <tr>
                <td style="font-weight: bold; padding: 4px 0; color: #666; vertical-align: top;">E-Mail:</td>
                <td style="padding: 4px 0; color: #222;"><a href="mailto:{{ $data['contact']['email'] }}" style="color: #C5A059; text-decoration: none;">{{ $data['contact']['email'] }}</a></td>
            </tr>
            @if(!empty($data['contact']['telefon']))
                <tr>
                    <td style="font-weight: bold; padding: 4px 0; color: #666; vertical-align: top;">Telefon:</td>
                    <td style="padding: 4px 0; color: #222;">{{ $data['contact']['telefon'] }}</td>
                </tr>
            @endif
        </table>
    </div>
@endif

{{-- 2. ADRESS-TABELLE (Rechnung & Lieferung - für Bestellbestätigungen) --}}
@if(isset($order))
    <table width="100%" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; border-collapse: collapse;">
        <tr>
            {{-- Rechnungsadresse --}}
            <td width="50%" valign="top" style="padding-right: 20px;">
                <h4 style="margin: 0 0 10px 0; font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 1px;">Rechnungsadresse</h4>
                <p style="margin: 0; font-size: 13px; color: #444; line-height: 1.6;">
                    {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}<br>
                    @if(!empty($data['contact']['firma'])) {{ $data['contact']['firma'] }}<br> @endif
                    {{ $order->billing_address['address'] }}<br>
                    {{ $order->billing_address['postal_code'] }} {{ $order->billing_address['city'] }}<br>
                    {{ $data['contact']['country'] }}
                </p>
            </td>

            {{-- Lieferadresse --}}
            <td width="50%" valign="top">
                <h4 style="margin: 0 0 10px 0; font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 1px;">Lieferadresse</h4>
                <p style="margin: 0; font-size: 13px; color: #444; line-height: 1.6;">
                    @php $ship = $order->shipping_address ?? $order->billing_address; @endphp
                    {{ $ship['first_name'] }} {{ $ship['last_name'] }}<br>
                    @if(!empty($ship['company'])) {{ $ship['company'] }}<br> @endif
                    {{ $ship['address'] }}<br>
                    {{ $ship['postal_code'] }} {{ $ship['city'] }}<br>
                    {{ $ship['country'] }}
                </p>
            </td>
        </tr>
    </table>
@endif
