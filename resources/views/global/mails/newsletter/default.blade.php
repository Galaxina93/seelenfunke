{{-- resources/views/emails/newsletter/default.blade.php --}}

{{-- 1. HTML WRAPPER (Lädt Styles, Head & Body Start) --}}
@include('global.mails.partials.mail_html_tree', ['title' => $template->subject ?? 'Newsletter'])

<div class="container">

    {{-- 2. LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- 3. HAUPTINHALT (Dynamisch aus dem Template/Seeder) --}}
    {{-- Wir nutzen hier einen Wrapper für saubere Typografie, passend zum Shop-Design --}}
    <div style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #444; line-height: 1.6; font-size: 14px; margin-bottom: 30px;">
        {!! $content !!}
    </div>

    {{-- 4. STANDARD CTA BUTTON (Zum Shop) --}}
    {{-- Dieser wird immer angezeigt, damit der Kunde direkt klicken kann --}}
    <div style="text-align: center; margin-top: 30px; margin-bottom: 40px;">
        <a href="{{ url('/') }}"
           style="background-color: #C5A059; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            Zum Online-Shop
        </a>
    </div>

    {{-- 5. NEWSLETTER SPEZIFISCHER FOOTER (Abmeldung) --}}
    {{-- Dieser Teil ist exklusiv für Newsletter und nicht im globalen Footer enthalten --}}
    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px dashed #eee; text-align: center; font-size: 11px; color: #999;">
        <p style="margin-bottom: 5px;">
            Diese E-Mail wurde an <strong>{{ $subscriber->email }}</strong> gesendet.
        </p>
        <p>
            Sie möchten keine Neuigkeiten mehr erhalten?
            <br>
            {{-- Die Route muss in web.php definiert sein, z.B. Route::get('/newsletter/unsubscribe/{id}', ...) --}}
            <a href="{{ url('/newsletter/unsubscribe/' . $subscriber->id) }}" style="color: #999; text-decoration: underline;">
                Hier vom Newsletter abmelden
            </a>
        </p>
    </div>

    {{-- 6. GLOBALER FOOTER (Impressum, AGB, Bankdaten) --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
