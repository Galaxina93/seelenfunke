{{-- HTML TREE (Lädt Styles & Head) --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Zahlung erfolgreich'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Juhu, Zahlung erhalten! 🚀</h1>
    <p>Hallo {{ !empty($data['contact']['vorname']) ? $data['contact']['vorname'] : 'du' }},</p>

    <p>gute Nachrichten: Die Zahlung für deine Bestellung <strong>#{{ $data['order_number'] ?? $data['quote_number'] }}</strong> ist erfolgreich bei uns eingegangen.</p>

    <p>Vielen Dank für dein Vertrauen! Deine Bestellung wurde nun automatisch für die Produktion freigegeben und unser Team legt los, deinen Seelenfunken zu fertigen.</p>

    {{-- ARTIKEL LISTE --}}
    <div style="margin-top: 30px; margin-bottom: 30px;">
        <h3 style="font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">Deine Bestellung:</h3>
        @include('global.mails.partials.mail_item_list')
    </div>

    {{-- PREISAUFSTELLUNG (NEU HINZUGEFÜGT) --}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => false])

    {{-- HINWEIS ZUM WEITEREN ABLAUF --}}
    <div style="margin-top: 30px; padding: 15px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 13px; color: #166534; line-height: 1.5;">
        <strong>✅ Nächster Schritt:</strong><br>
        Wir produzieren deine Bestellung und du erhältst eine weitere E-Mail, sobald sich das Paket auf den Weg zu dir macht.
    </div>

    @if(!empty($data['has_digital_documents']))
        <div style="margin-top: 15px; padding: 15px 18px; background-color: #faf8f5; border: 1px solid #e7e2d8; border-left: 4px solid #C5A059; border-radius: 6px; font-size: 13px; color: #44403c; line-height: 1.55;">
            <strong style="color: #1c1917;">📄 Digitale Dokumente zu deiner Bestellung:</strong><br>
            Zu deiner Bestellung gehören digitale Begleitdokumente. Du findest diese jederzeit in deinem <a href="{{ url('/orders') }}" style="color: #9a6f1a; font-weight: bold; text-decoration: underline;">Kundenportal</a> in der Bestellansicht unter <strong>Dokumente</strong>.
        </div>
    @endif

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
