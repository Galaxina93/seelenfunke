<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widerruf erfolgreich bearbeitet — Mein Seelenfunke</title>
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

        /* Das "Seelenfunke" Card-Design */
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
            font-size: 26px;
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

        /* Info-Box für positiven Hinweis */
        .link-info {
            background-color: #ecfdf5;
            border-radius: 12px;
            padding: 20px;
            margin-top: 35px;
            border: 1px solid #a7f3d0;
            border-left: 4px solid #10b981;
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
                    <h1>Widerruf erfolgreich bearbeitet</h1>

                    <p>Hallo {{ $revocation->name }},</p>

                    <p>wir haben deine Retoure zur Bestellung <strong>{{ $revocation->order_number }}</strong> erhalten und erfolgreich geprüft.</p>

                    <p>Dein Widerruf wurde damit <strong>vollständig abgewickelt</strong> und der entsprechende Betrag wird dir gutgeschrieben.</p>

                    <div class="link-info">
                        <p style="font-size: 14px; color: #065f46; font-weight: bold; margin-bottom: 8px !important;">
                            Info zur Rückerstattung:
                        </p>
                        <p style="font-size: 14px; color: #065f46; margin-bottom: 0 !important; line-height: 1.5;">
                            Die Gutschrift erfolgt in Kürze über dieselbe Zahlungsmethode, die du bei deiner ursprünglichen Bestellung verwendet hast. Bitte beachte, dass es je nach Zahlungsanbieter (z. B. PayPal, Kreditkarte, Klarna) mitunter einige Werktage in Anspruch nehmen kann, bis der Betrag auf deinem Konto sichtbar ist.
                        </p>
                    </div>

                    <p style="margin-top: 25px !important;">Wir hoffen sehr, dich bald wieder bei uns begrüßen zu dürfen und wünschen dir bis dahin eine wundervolle Zeit!</p>

                    <p style="margin-top: 20px !important;">Solltest du noch Rückfragen haben, antworte gerne einfach auf diese E-Mail.</p>

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
                        <a href="{{ url('/agb') }}">AGB & Widerruf</a>
                    </p>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
