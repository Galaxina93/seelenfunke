<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmeldung bestätigen — Mein Seelenfunke</title>
    <style>
        /* Outlook & Webmail Fixes */
        #body h1, #body h2, #body h3, #body p {
            margin: 0;
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #fcfbf9;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #374151;
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
        }

        table { border-collapse: collapse; width: 100%; }

        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #fcfbf9;
            padding-top: 40px;
            padding-bottom: 40px;
        }

        /* Das "Seelenfunke" Card-Design: Stark abgerundete Ecken & sanfter Schatten */
        .content {
            max-width: 600px;
            background-color: #ffffff;
            margin: 0 auto;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
            border: 1px solid #f3f4f6;
        }

        .header {
            background-color: #111827; /* Das tiefe Schwarz aus dem Dashboard */
            padding: 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .logo-text {
            color: #ffffff;
            font-family: 'Playfair Display', Georgia, serif; /* Edle Serif-Schrift */
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 1px;
            text-decoration: none;
        }

        .sparkle {
            color: #C5A059; /* Das Gold von Seelenfunke */
        }

        .body {
            padding: 50px 40px;
            background-color: #ffffff;
        }

        /* Typografie */
        h1 {
            font-family: Georgia, serif;
            color: #111827;
            font-size: 28px;
            font-weight: bold;
            line-height: 1.3;
            margin-bottom: 25px !important;
            text-align: center;
        }

        p {
            margin-bottom: 18px !important;
            font-size: 15px;
            color: #4b5563;
        }

        /* Der Button: Gold & abgerundet */
        .btn-container {
            text-align: center;
            margin: 35px 0;
        }

        .btn {
            display: inline-block;
            background-color: #C5A059;
            background: linear-gradient(to right, #C5A059, #b08d4b);
            color: #ffffff !important;
            padding: 16px 36px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.25);
            transition: transform 0.2s ease;
        }

        /* Info-Box für den Fallback-Link */
        .link-info {
            background-color: #f9fafb;
            border-radius: 12px;
            padding: 15px;
            margin-top: 30px;
            border: 1px solid #f3f4f6;
        }

        .footer {
            background-color: #f9fafb;
            padding: 30px 40px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }

        .footer a {
            color: #6b7280;
            text-decoration: none;
            font-weight: bold;
        }

        .footer a:hover {
            color: #C5A059;
        }

        .signature {
            margin-top: 35px;
            border-top: 1px solid #f3f4f6;
            padding-top: 25px;
            font-style: italic;
        }
    </style>
</head>
<body id="body">
<table class="wrapper" role="presentation">
    <tr>
        <td>
            <div class="content">
                <div class="header">
                    <span class="logo-text">Mein Seelenfunke<span class="sparkle">.</span></span>
                </div>

                <div class="body">
                    <h1>Lass uns deine Anmeldung bestätigen ✨</h1>

                    <p>Hallo,</p>

                    <p>schön, dass du dabei bist! Vielen Dank für dein Interesse an unserem Newsletter. Wir freuen uns sehr darauf, dir bald Einblicke hinter die Kulissen unserer Manufaktur, Inspirationen und exklusive Neuigkeiten zu unseren Unikaten zusenden zu dürfen.</p>

                    <p>Damit wir wissen, dass die Anfrage wirklich von dir kommt, klicke bitte auf den goldenen Button unten:</p>

                    <div class="btn-container">
                        <a href="{{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}" class="btn">
                            Anmeldung jetzt bestätigen
                        </a>
                    </div>

                    <p style="font-size: 13px; text-align: center; color: #9ca3af;">
                        Falls du dich nicht angemeldet hast, kannst du diese Nachricht einfach ignorieren.
                    </p>

                    <div class="link-info">
                        <p style="font-size: 13px; color: #6b7280; margin-bottom: 5px !important;">
                            Probleme mit dem Button? Kopiere diesen Link in deinen Browser:
                        </p>
                        <a href="{{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}" style="color: #C5A059; font-size: 12px; word-break: break-all;">
                            {{ route('newsletter.verify', ['token' => $subscriber->verification_token]) }}
                        </a>
                    </div>

                    <div class="signature">
                        <p>Herzliche Grüße aus der Manufaktur,<br>
                            <strong style="color: #111827;">Dein Team von Mein Seelenfunke</strong></p>
                    </div>
                </div>

                <div class="footer">
                    <p style="margin-bottom: 10px !important;">&copy; {{ date('Y') }} Mein Seelenfunke — Handveredelte Unikate</p>
                    <p>
                        <a href="{{ url('/impressum') }}">Impressum</a> &nbsp;•&nbsp;
                        <a href="{{ url('/datenschutz') }}">Datenschutz</a> &nbsp;•&nbsp;
                        <a href="{{ url('/agb') }}">AGB</a>
                    </p>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
