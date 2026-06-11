{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Dein Geschenkgutschein'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Ein Geschenk für dich! ✨</h1>
    
    <p>Hallo {{ $voucher->recipient_name }},</p>

    <p>wir haben wundervolle Neuigkeiten: Jemand möchte dir eine ganz besondere Freude bereiten und schenkt dir einen Wertgutschein für **Mein Seelenfunke** im Wert von <strong>{{ number_format($voucher->initial_value / 100, 2, ',', '.') }} €</strong>!</p>

    @if($voucher->personal_message)
        {{-- PERSÖNLICHE NACHRICHT --}}
        <div style="margin-top: 25px; margin-bottom: 25px; padding: 20px; background-color: #fcf8f2; border-left: 4px solid #fbbf24; border-radius: 4px;">
            <p style="margin: 0; font-size: 15px; color: #4b5563; font-style: italic; line-height: 1.6;">
                „{{ $voucher->personal_message }}“
            </p>
        </div>
    @endif

    {{-- GUTSCHEINCODE BOX --}}
    <div style="margin-top: 30px; margin-bottom: 30px; padding: 25px; background-color: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; text-align: center;">
        <h3 style="margin-top: 0; color: #1f2937; font-size: 14px; text-transform: uppercase; tracking-wider: 1px;">Dein Gutscheincode:</h3>
        <div style="display: inline-block; padding: 12px 24px; background-color: #ffffff; border: 2px dashed #fbbf24; border-radius: 6px; font-family: monospace; font-size: 22px; font-weight: bold; color: #d97706; letter-spacing: 1px; margin-bottom: 15px;">
            {{ $voucher->code }}
        </div>
        <p style="font-size: 13px; color: #6b7280; line-height: 1.5; margin: 0;">
            Gib diesen Code einfach bei deiner nächsten Bestellung im Warenkorb oder während des Checkouts im Gutscheinfeld ein. Der Betrag wird direkt von deiner Bestellung abgezogen.
        </p>
    </div>

    {{-- CTA BUTTON --}}
    <div style="text-align: center; margin-top: 35px; margin-bottom: 35px;">
        <a href="{{ url('/shop') }}" style="display: inline-block; padding: 14px 28px; background-color: #d97706; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px; box-shadow: 0 4px 6px rgba(217, 119, 6, 0.15);">Jetzt im Shop einlösen</a>
    </div>

    {{-- HINWEIS ZUR PDF --}}
    <div style="padding: 15px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; font-size: 12px; color: #6b7280; line-height: 1.5;">
        <strong>📎 Hinweis zum Anhang:</strong> Wir haben dir diese E-Mail auch als edel gestaltetes PDF-Dokument angehängt. So kannst du den Gutschein ganz einfach ausdrucken und als physisches Geschenk überreichen.
    </div>

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
