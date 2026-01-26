<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmeldung bestätigen</title>
    <style>
        /* Email Reset & Basics */
        body { margin: 0; padding: 0; background-color: #f9fafb; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-collapse: collapse; width: 100%; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f9fafb; padding-bottom: 40px; }
        .content { max-width: 600px; background-color: #ffffff; margin: 0 auto; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .header { background-color: #1f2937; padding: 30px 20px; text-align: center; }
        .logo { color: #ffffff; font-size: 24px; font-weight: bold; letter-spacing: 1px; font-family: Georgia, serif; text-decoration: none; }
        .body { padding: 40px 30px; color: #374151; line-height: 1.6; }
        .btn-container { text-align: center; margin: 30px 0; }
        .btn { display: inline-block; background-color: #C5A059; color: #ffffff; padding: 14px 32px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(197, 160, 89, 0.3); transition: background-color 0.3s; }
        .btn:hover { background-color: #b08d4b; }
        .footer { background-color: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; }
        .footer a { color: #6b7280; text-decoration: underline; }
    </style>
</head>
<body>
<table class="wrapper" role="presentation">
    <tr>
        <td>
            <div class="content">
                <div class="header">
                    {{-- Falls du ein Logo-Bild hast, nutze: <img src="{{ asset('logo.png') }}" ...> --}}
                    <span class="logo">Mein Seelenfunke</span>
                </div>

                <div class="body">
                    <h1 style="margin-top: 0; font-family: Georgia, serif; color: #111827; font-size: 24px;">Fast geschafft!</h1>

                    <p>Hallo,</p>

                    <p>vielen Dank für dein Interesse an unserem Newsletter. Wir freuen uns sehr, dich bald über neue Unikate und Geschichten aus unserer Manufaktur informieren zu dürfen.</p>

                    <p>Um Missbrauch zu vermeiden, müssen wir sicherstellen, dass diese E-Mail-Adresse wirklich dir gehört (Double Opt-In).</p>

                    <div class="btn-container">
                        <a href="{{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}" class="btn">
                            Anmeldung bestätigen
                        </a>
                    </div>

                    <p style="font-size: 14px; color: #6b7280;">
                        Falls der Button nicht funktioniert, kopiere bitte diesen Link in deinen Browser:<br>
                        <a href="{{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}" style="color: #C5A059; word-break: break-all;">
                            {{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}
                        </a>
                    </p>

                    <p>Solltest du dich nicht angemeldet haben, kannst du diese E-Mail einfach ignorieren.</p>

                    <p style="margin-top: 30px;">
                        Herzliche Grüße,<br>
                        <strong>Dein Team von Mein Seelenfunke</strong>
                    </p>
                </div>

                <div class="footer">
                    <p>&copy; {{ date('Y') }} Mein Seelenfunke. Alle Rechte vorbehalten.</p>
                    <p>
                        <a href="{{ url('/impressum') }}">Impressum</a> •
                        <a href="{{ url('/datenschutz') }}">Datenschutz</a>
                    </p>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
