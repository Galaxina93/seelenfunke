{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Deine Empfehlungs-Belohnung'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Juhu, deine Empfehlung war erfolgreich! 🎉</h1>
    
    <p>Hallo {{ $referral->referrer_first_name ?: 'lieber Seelenfunke-Kunde' }},</p>

    <p>großartige Neuigkeiten: Ein Freund hat soeben über deine Empfehlung eine Bestellung bei uns aufgegeben! Als Dankeschön schenken wir dir wie versprochen <strong>5,00 € Einkaufsguthaben</strong> für deinen nächsten Einkauf. Dein persönlicher Gutschein verfügt nun über insgesamt <strong>{{ number_format($voucher->current_balance / 100, 2, ',', '.') }} € Guthaben</strong>!</p>

    {{-- GUTSCHEINCODE BOX --}}
    <div style="margin-top: 30px; margin-bottom: 30px; padding: 25px; background-color: #fdfaf4; border: 1px solid #fde68a; border-radius: 12px; text-align: center;">
        <h3 style="margin-top: 0; color: #78350f; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">Dein persönlicher Gutscheincode:</h3>
        <div style="display: inline-block; padding: 14px 28px; background-color: #ffffff; border: 2px dashed #f59e0b; border-radius: 8px; font-family: monospace; font-size: 24px; font-weight: 800; color: #b45309; letter-spacing: 2px; margin-bottom: 10px;">
            {{ $voucher->code }}
        </div>
        <p style="font-size: 14px; font-weight: 700; color: #92400e; margin: 0 0 10px 0;">
            Aktuelles Gesamtguthaben: {{ number_format($voucher->current_balance / 100, 2, ',', '.') }} €
        </p>
        <p style="font-size: 12px; color: #78350f; line-height: 1.5; margin: 0;">
            Gib diesen Code einfach bei deiner nächsten Bestellung im Warenkorb oder Checkout ein. Das Guthaben wird automatisch von deinem Rechnungsbetrag abgezogen.
        </p>
    </div>

    {{-- CTA BUTTON --}}
    <div style="text-align: center; margin-top: 35px; margin-bottom: 35px;">
        <a href="{{ url('/shop') }}" style="display: inline-block; padding: 14px 28px; background-color: #d97706; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; box-shadow: 0 4px 6px rgba(217, 119, 6, 0.2);">Jetzt im Shop stöbern</a>
    </div>

    <p style="font-size: 13px; color: #6b7280; line-height: 1.6;">
        Du kannst deinen Empfehlungscode <strong>{{ $referral->code }}</strong> übrigens beliebig oft an weitere Freunde und Bekannte weitergeben. Für jede erfolgreiche Neukunden-Bestellung schenken wir dir jedes Mal weitere 5 € Einkaufsguthaben!
    </p>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
