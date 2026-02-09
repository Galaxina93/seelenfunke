{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Zahlung erfolgreich'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE & ZAHLUNGSBESTÄTIGUNG --}}
    <h1>Juhu, Zahlung erhalten! 🚀</h1>
    <p>Hallo {{ $data['contact']['vorname'] }},</p>

    <p>gute Nachrichten: Die Zahlung für deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> ist erfolgreich bei uns eingegangen.</p>

    <p>Vielen Dank für dein Vertrauen! Deine Bestellung wurde nun automatisch für die Produktion freigegeben und unser Team legt los, deinen Seelenfunken zu fertigen.</p>

    {{-- ARTIKEL LISTE (Zur Orientierung, was bestellt wurde) --}}
    <div style="margin-top: 30px; margin-bottom: 30px;">
        <h3 style="font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Deine Bestellung:</h3>
        @include('global.mails.partials.mail_item_list')
    </div>

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => false])

    {{-- Kleiner Hinweis zum weiteren Ablauf --}}
    <div style="margin-top: 30px; padding: 15px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 13px; color: #166534; line-height: 1.5;">
        <strong>✅ Nächster Schritt:</strong><br>
        Wir produzieren deine Bestellung und du erhältst eine weitere E-Mail, sobald sich das Paket auf den Weg zu dir macht.
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
