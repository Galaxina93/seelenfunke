{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree')

<div class="container">
    <div class="header">
        <h1>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</h1>
    </div>

    <p>Hallo {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }},</p>

    <p>vielen Dank für deine Anfrage! Wir haben deine Konfiguration erhalten und geprüft.</p>

    <div class="info-box">
        <strong>Gut zu wissen:</strong><br>
        Deine hochgeladenen Logos und Bilder wurden sicher auf unserem geschützten Server gespeichert und liegen deiner Bestellung automatisch bei. Du musst sie nicht erneut senden.
    </div>

    <p>Hier ist eine Zusammenfassung deiner Wunschartikel. Im Anhang findest du zusätzlich das detaillierte PDF-Angebot.</p>

    {{-- ARTIKEL LISTE --}}
    @include('global.mails.partials.mail_item_list')

    {{-- PREISAUFSTELLUNG --}}
    @include('global.mails.partials.mail_price_list')

    {{-- ACTION BUTTON --}}
    @if(isset($data['quote_token']))
        <div class="btn-container">
            <p style="margin-bottom: 15px; font-size: 13px;">
                Über den folgenden Link gelangst du zu deiner persönlichen Angebots-Verwaltung.<br>
                Dort kannst du das <strong>Angebot annehmen</strong> oder <strong>nachträglich bearbeiten</strong>.
            </p>
            <a href="{{ route('quote.accept', ['token' => $data['quote_token']]) }}" class="btn">
                Zum Angebot & Bearbeitung
            </a>
            <p style="margin-top: 10px; font-size: 11px; color: #999;">
                Gültig bis zum {{ $data['quote_expiry'] }}
            </p>
        </div>
    @endif

    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
