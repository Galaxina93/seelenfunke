{{-- Nutzt deinen zentralen HTML-Kopf für Styles --}}
@include('global.mails.partials.mail_html_tree')

<div class="container" style="border-top: 6px solid #16a34a;"> {{-- Grüner Balken für echte Bestellungen --}}

    {{-- HEADER --}}
    <div class="header">
        <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein Seelenfunke" class="logo">
    </div>

    {{-- ANSPRACHE --}}
    <h1 style="color: #166534;">Neue Bestellung erhalten! 🥂 💸
        @if(!empty($data['express']))
            <span class="badge-express" style="background: #dc2626; color: white; padding: 3px 8px; border-radius: 4px; font-size: 10px;">EXPRESS</span>
        @endif
    </h1>

    <p>Hallo Alina, herzlichen Glückwunsch! Ein Kunde hat gerade eine <strong>Bestellung (#{{ $data['quote_number'] }})</strong> über den Checkout abgeschlossen und bezahlt.</p>

    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
        <p style="margin: 0; color: #166534; font-size: 13px;">
            <strong>Status:</strong> Die Zahlung wurde erfolgreich verarbeitet. Du kannst nun mit der Produktion beginnen.
        </p>
    </div>

    {{-- ARTIKEL LISTE --}}
    <h3 style="font-size: 14px; color: #888; text-transform: uppercase; margin-bottom: 10px;">Bestellte Produkte</h3>
    @include('global.mails.partials.mail_bought_products_list')

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
