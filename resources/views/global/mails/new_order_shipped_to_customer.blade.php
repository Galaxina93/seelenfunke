{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Deine Bestellung ist unterwegs'])

<body>

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Juhu, deine Bestellung ist unterwegs! 🚚</h1>
    <p>Hallo {{ $data['contact']['vorname'] }},</p>

    <p>gute Neuigkeiten: Wir haben deinen Seelenfunken soeben liebevoll verpackt und an unseren Versanddienstleister übergeben.</p>
    <p>Deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> ist nun auf dem Weg zu dir.</p>

    {{-- ARTIKEL LISTE --}}
    <div style="margin-top: 30px; margin-bottom: 30px;">
        <h3 style="font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">In diesem Paket:</h3>
        @include('global.mails.partials.mail_item_list')
    </div>

    {{-- PREISAUFSTELLUNG (Optional, kann man auch weglassen bei Versandmail) --}}
    {{-- @include('global.mails.partials.mail_price_list') --}}

    {{-- ADRESSEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => false])

    {{-- TRACKING HINWEIS (Platzhalter, falls du später Tracking-IDs hast) --}}
    <div style="margin-top: 30px; padding: 15px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 13px; color: #166534; line-height: 1.5;">
        <strong>📦 Nächster Schritt:</strong><br>
        Das Paket sollte innerhalb der nächsten 1-3 Werktage bei dir eintreffen. Wir wünschen dir ganz viel Freude damit!
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
