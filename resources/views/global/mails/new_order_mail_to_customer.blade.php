{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Bestellbestätigung'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Vielen Dank, {{ $data['contact']['vorname'] }}!</h1>
    <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten und bereiten diese nun mit viel Liebe für dich vor.</p>

    {{-- ARTIKEL LISTE --}}
    @include('global.mails.partials.mail_item_list')

    {{--PREISAUFSTELLUNG--}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => true])

    {{-- [NEU] HINWEIS ZUR XML DATEI --}}
    <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; font-size: 12px; color: #6c757d; line-height: 1.5;">
        <strong>ℹ️ Hinweis zum Anhang:</strong><br>
        Neben der gewohnten <strong>PDF-Rechnung</strong> finden Sie im Anhang auch eine <strong>XML-Datei</strong>.
        Diese dient der automatischen Verarbeitung für Geschäftskunden (E-Rechnung gemäß EN16931).
        <br>Falls Sie Privatkunde sind, können Sie die XML-Datei einfach ignorieren.
    </div>

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
