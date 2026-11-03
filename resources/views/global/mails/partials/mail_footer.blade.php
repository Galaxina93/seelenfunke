{{-- FOOTER --}}
<div class="footer">
    <p>
        <strong>{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}</strong> | Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
        {{ shop_setting('company_street') ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '')) : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('company_city') ? trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city')) : shop_setting('owner_city', '38518 Gifhorn') }}<br>
        Tel.: {{ shop_setting('company_phone', shop_setting('owner_phone', '+49 (0) 159 019 668 64')) }} |
        <a href="mailto:{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}">{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}</a> |
        <a href="{{ url('/') }}">{{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }}</a>
    </p>

    {{-- Rechtliche Zusatzangaben --}}
    @php
        $footerIban = shop_setting('owner_iban', '');
        $footerUstId = shop_setting('company_ust_id', shop_setting('owner_ust_id'));
        $footerTaxId = shop_setting('owner_tax_id', '19/143/11624');
    @endphp
    <p style="font-size: 10px; color: #bbb; margin-top: 10px; line-height: 1.4;">
        @if(!empty($footerIban)) IBAN: {{ $footerIban }}<br> @endif
        Steuernummer: {{ $footerTaxId }}
        @if($footerUstId) | USt-IdNr.: {{ $footerUstId }} @endif
        | Gerichtsstand (für Kaufleute): {{ shop_setting('owner_court', 'Gifhorn') }}
    </p>

    {{-- Rechtliche Links --}}
    <p style="margin-top: 15px;">
        <a href="{{ url('/agb') }}">AGB</a> |
        <a href="{{ url('/agb') }}#widerruf">Widerrufsbelehrung</a> |
        <a href="{{ url('/widerruf') }}">Vertrag widerrufen</a> |
        <a href="{{ url('/datenschutz') }}">Datenschutz</a> |
        <a href="{{ url('/impressum') }}">Impressum</a>
    </p>
</div>
