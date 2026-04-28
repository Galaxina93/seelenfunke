{{-- resources/views/global/mails/newsletter/verification_mail_to_customer.blade.php --}}

{{-- 1. HTML WRAPPER (Lädt Styles, Head & Body Start) --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Anmeldung bestätigen — Mein Seelenfunke'])

<div class="container">

    {{-- 2. LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- 3. HAUPTINHALT --}}
    <div style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #444; line-height: 1.6; font-size: 14px; margin-bottom: 30px;">
        
        <h1 style="font-family: Georgia, serif; color: #111827; font-size: 24px; font-weight: bold; line-height: 1.3; margin-top: 0; margin-bottom: 20px;">Lass uns deine Anmeldung bestätigen ✨</h1>

        <p style="margin-bottom: 15px;">Hallo,</p>

        <p style="margin-bottom: 15px;">schön, dass du dabei bist! Vielen Dank für dein Interesse an unserem Newsletter. Wir freuen uns sehr darauf, dir bald Einblicke hinter die Kulissen unserer Manufaktur, Inspirationen und exklusive Neuigkeiten zu unseren Unikaten zusenden zu dürfen.</p>

        <p style="margin-bottom: 25px;">Damit wir wissen, dass die Anfrage wirklich von dir kommt, klicke bitte auf den goldenen Button unten:</p>

        {{-- 4. CTA BUTTON --}}
        <div style="text-align: center; margin-top: 30px; margin-bottom: 40px;">
            <a href="{{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}"
               style="background-color: #C5A059; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                Anmeldung jetzt bestätigen
            </a>
        </div>

        <p style="font-size: 12px; text-align: center; color: #9ca3af; margin-bottom: 30px;">
            Falls du dich nicht angemeldet hast, kannst du diese Nachricht einfach ignorieren.
        </p>

        <div style="background-color: #f9fafb; border-radius: 12px; padding: 15px; margin-top: 30px; border: 1px solid #f3f4f6;">
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 5px; margin-top: 0;">
                Probleme mit dem Button? Kopiere diesen Link in deinen Browser:
            </p>
            <a href="{{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}" style="color: #C5A059; font-size: 12px; word-break: break-all;">
                {{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}
            </a>
        </div>

        <div style="margin-top: 35px; border-top: 1px dashed #eee; padding-top: 25px; font-style: italic;">
            <p style="margin-top: 0; margin-bottom: 0;">Herzliche Grüße aus der Manufaktur,<br>
                <strong style="color: #111827;">Dein Team von Mein Seelenfunke</strong></p>
        </div>
    </div>

    {{-- 5. GLOBALER FOOTER (Impressum, AGB, Bankdaten) --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
