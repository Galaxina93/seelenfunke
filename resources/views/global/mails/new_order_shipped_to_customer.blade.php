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

    {{-- TRACKING HINWEIS --}}
    <div style="margin-top: 30px; padding: 15px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 13px; color: #166534; line-height: 1.5;">
        <strong>📦 Nächster Schritt:</strong><br>
        Das Paket sollte innerhalb der nächsten 1-3 Werktage bei dir eintreffen. Wir wünschen dir ganz viel Freude damit!
    </div>

    @if(!empty($data['tracking_numbers']) && count($data['tracking_numbers']) > 0)
        <div style="margin-top: 15px; padding: 15px; background-color: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; font-size: 13px; color: #92400e; line-height: 1.5; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.02)">
            <strong style="color: #b45309; font-size: 14px;">🚚 Deine DHL Sendungsverfolgung:</strong><br>
            <div style="margin-top: 5px;">Klicke direkt auf die Tracking-Nummer, um den aktuellen Status abzurufen:</div>
            <ul style="margin-top: 10px; margin-bottom: 0; padding-left: 20px;">
                @foreach($data['tracking_numbers'] as $trackingNumber)
                    <li style="margin-bottom: 4px;">
                        <a href="https://www.dhl.de/de/privatkunden/dhl-sendungsverfolgung.html?piececode={{ $trackingNumber }}" 
                           target="_blank" 
                           style="color: #d97706; font-weight: bold; text-decoration: underline;">
                            {{ $trackingNumber }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="margin-top: 15px; padding: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; color: #334155; line-height: 1.5;">
        <strong>💡 Gut zu wissen:</strong><br>
        Alle Details zu dieser Bestellung sowie deine Rechnung findest du jederzeit im Kundenportal unter <strong>Bestellungen</strong>.
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
