{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Bitte bestätige deine E-Mail-Adresse'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Willkommen bei Mein-Seelenfunke! ✨</h1>

    {{-- Der Name kann übergeben werden, falls verfügbar, ansonsten allgemein halten --}}
    <p>Hallo {{ $name ?? '' }},</p>

    <p>wir freuen uns sehr, dass du den Weg zu uns gefunden hast! Um deine Registrierung abzuschließen und dein neues Konto nutzen zu können, bestätige bitte noch kurz deine E-Mail-Adresse.</p>

    {{-- VERIFIZIERUNGS-BUTTON --}}
    <div style="text-align: center; margin: 40px 0;">
        {{-- Passe die Hintergrundfarbe an deine Mein-Seelenfunken CI-Farbe an --}}
        <a href="{{ $url }}" style="background-color: #d4a373; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px;">
            E-Mail-Adresse bestätigen
        </a>
    </div>

    {{-- HINWEIS FÜR PROBLEME MIT DEM BUTTON --}}
    <div style="margin-top: 30px; font-size: 13px; color: #6c757d; line-height: 1.5;">
        Falls der Button nicht funktioniert, kopiere einfach den folgenden Link und füge ihn in deinen Browser ein:<br>
        <a href="{{ $url }}" style="color: #d4a373; word-break: break-all;">{{ $url }}</a>
    </div>

    <p style="margin-top: 30px;">
        Solltest du dich nicht bei uns registriert haben, kannst du diese E-Mail einfach ignorieren.
    </p>

    <p>Mit funkelnden Grüßen,<br>
        <strong>Dein Team von Mein-Seelenfunken</strong></p>

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
