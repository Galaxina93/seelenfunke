{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', [
    'title' => 'Angebot - ' . ($data['quote_number'] ?? '')
])

@php
    $isSmallBusiness = (bool)shop_setting('is_small_business', false);
    $ownerName = shop_setting('owner_name', 'Mein Seelenfunke');
    $proprietor = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $ownerStreet = shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
    $ownerCity = shop_setting('owner_city', '38518 Gifhorn');
    $ownerEmail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
    $ownerWeb = shop_setting('owner_website', 'www.mein-seelenfunke.de');
    $ownerIban = shop_setting('owner_iban', 'Wird nachgereicht');
    $taxId = shop_setting('owner_tax_id', '19/143/11624');
    $ustId = shop_setting('owner_ust_id');
    $court = shop_setting('owner_court', 'Gifhorn');

        // Abweichende Lieferadresse prüfen
    $hasDifferentShipping = !empty($invoice->shipping_address) &&
                            serialize($invoice->billing_address) !== serialize($invoice->shipping_address);
@endphp


<div class="container">
    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')


    <table class="address-container">
        <tr>
            <td class="address-box">
                <div class="sender-small">{{ $ownerName }} · {{ $ownerStreet }} · {{ $ownerCity }}</div>
                <strong>{{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}</strong><br>
                @if(!empty($data['contact']['firma'])) {{ $data['contact']['firma'] }}<br> @endif
                @if(!empty($data['billing_address']))
                    {{ $data['billing_address']['address'] }}<br>
                    {{ $data['billing_address']['postal_code'] }} {{ $data['billing_address']['city'] }}<br>
                    {{ $data['billing_address']['country'] ?? 'DE' }}
                @endif

                @if($hasDifferentShipping)
                    <div class="shipping-address-box">
                        <div style="font-size: 8px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 2px;">Lieferadresse:</div>
                        <div style="font-size: 9px; line-height: 1.3; color: #666;">
                            {{ $data['shipping_address']['first_name'] }} {{ $data['shipping_address']['last_name'] }}<br>
                            @if(!empty($data['shipping_address']['company'])) {{ $data['shipping_address']['company'] }}<br> @endif
                            {{ $data['shipping_address']['address'] }}<br>
                            {{ $data['shipping_address']['postal_code'] }} {{ $data['shipping_address']['city'] }}<br>
                            {{ $data['shipping_address']['country'] ?? 'DE' }}
                        </div>
                    </div>
                @endif
            </td>
            <td class="address-box text-right">
                <div style="margin-top: 15px;">
                    <strong>{{ $ownerName }}</strong><br>
                    Inh. {{ $proprietor }}<br>
                    {{ $ownerStreet }}<br>
                    {{ $ownerCity }}<br>
                    Deutschland
                </div>
            </td>
        </tr>
    </table>

    <div class="subject">Angebot für Ihre individuelle Anfrage</div>
    <div style="margin-bottom: 20px;">
        vielen Dank für Ihr Interesse an unseren Produkten und das damit verbundene Vertrauen!<br>
        Gerne unterbreiten wir Ihnen hiermit das gewünschte Angebot:
    </div>

    {{-- KUNDENAUSWAHL --}}
    @include('global.mails.partials.mail_item_list')

    {{-- PREISAUFSTELLUNG --}}
    @include('global.mails.partials.mail_price_list')

    <div class="clear"></div>

    <div style="margin-top: 30px; font-size: 11px; color: #555; border-top: 1px solid #eee; padding-top: 15px;">
        @if($isSmallBusiness)
            <p style="font-size: 10px; color: #666; font-style: italic; margin-bottom: 10px;">
                Hinweis: Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG.
            </p>
        @endif

        <strong>Kontakt für Rückfragen:</strong><br>
        {{ $ownerEmail }} @if(!empty($data['contact']['telefon'])) | {{ $data['contact']['telefon'] }} @endif

        @if(!empty($data['express']))
            <div style="margin-top: 10px; color: #dc2626; font-weight: bold; border: 1px solid #dc2626; padding: 5px; display: inline-block;">
                EXPRESS-AUFTRAG
                @if(!empty($data['deadline'])) (Wunschtermin: {{ \Carbon\Carbon::parse($data['deadline'])->format('d.m.Y') }}) @endif
            </div>
        @endif

        <div style="margin-top: 15px; font-style: italic; color: #888;">
            Dieses Angebot wurde digital erstellt und ist gültig bis zum {{ $data['quote_expiry'] }}.
        </div>
    </div>

    {{-- FOOTER --}}
    @include("global.mails.partials.mail_footer")
</div>



</body>
</html>
