{{-- FOOTER --}}
<div class="footer">
    <p>
        <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> | Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
        {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}<br>
        <a href="mailto:{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}">{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</a> |
        <a href="{{ url('/') }}">{{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }}</a>
    </p>

    {{-- Rechtliche Zusatzangaben --}}
    <p style="font-size: 10px; color: #bbb; margin-top: 10px; line-height: 1.4;">
        IBAN: {{ shop_setting('owner_iban', 'Wird nachgereicht') }}<br>
        Steuernummer: {{ shop_setting('owner_tax_id') }}
        @if(shop_setting('owner_ust_id')) | USt-IdNr.: {{ shop_setting('owner_ust_id') }} @endif
        | Gerichtsstand: {{ shop_setting('owner_court', 'Gifhorn') }}
    </p>

    {{-- Rechtliche Links --}}
    <p style="margin-top: 15px;">
        <a href="{{ url('/agb') }}">AGB</a> |
        <a href="{{ url('/datenschutz') }}">Datenschutz</a> |
        <a href="{{ url('/impressum') }}">Impressum</a>
    </p>
</div>
