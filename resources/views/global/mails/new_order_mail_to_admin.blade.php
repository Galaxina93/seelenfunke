{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Neue Bestellung'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1 style="color: #166534;">Neue Bestellung erhalten! 💸🥂💸
        @if(!empty($data['express']))
            <span class="badge-express" style="color: #333; padding: 3px 8px; border-radius: 4px; font-size: 11px; border: 1px solid #C5A059; cursor: help;" title="Vorteil: Sofortige Priorisierung in der Manufaktur vor regulären Bestellungen. Zusammensetzung: {{ number_format((float) shop_setting('express_surcharge_percent', 20.0), 0, ',', '.') }} % vom Warenwert (mind. {{ number_format(((int) shop_setting('express_surcharge_min', 500)) / 100, 2, ',', '.') }} € Basis-Pauschale).">Express-Service <span style="color: #C5A059; font-weight: bold;">&#10003;</span></span>
        @endif
    </h1>

    <p>Hallo Alina, herzlichen Glückwunsch! Ein Kunde hat gerade eine <strong>Bestellung (#{{ $data['quote_number'] }})</strong> über den Checkout abgeschlossen und bezahlt.</p>

    {{-- ARTIKEL LISTE --}}
    <h3 style="font-size: 14px; color: #888; text-transform: uppercase; margin-bottom: 10px;">Bestellte Produkte</h3>
    @include('global.mails.partials.mail_item_list')

    {{-- PREISAUFSTELLUNG --}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    {{-- showContactCard zeigt die kompakte Box oben, isset($order) im Partial zeigt zusätzlich die Adress-Spalten --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => true])

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
