<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eingangsbestätigung Widerruf — Mein Seelenfunke</title>
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

        /* Daten-Box */
        .data-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 20px;
            border-radius: 16px;
            margin: 25px 0;
        }

        .data-row {
            margin-bottom: 12px;
        }
        
        .data-row:last-child {
            margin-bottom: 0;
        }

        .data-row strong {
            display: block;
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .data-row span {
            font-size: 15px;
            font-weight: bold;
            color: #111827;
            display: block;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        /* Info-Box für den rechtlichen Hinweis */
        .link-info {
            background-color: #fffbeb;
            border-radius: 12px;
            padding: 20px;
            margin-top: 35px;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
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
                    <h1>Eingangsbestätigung Widerruf</h1>

                    <p>Hallo {{ $revocationData['name'] }},</p>

                    <p>hiermit bestätigen wir Ihnen den unverzüglichen und fristgerechten elektronischen Eingang Ihrer Widerrufserklärung.</p>

                    <div class="data-box">
                        <div class="data-row">
                            <strong>Eingangsdatum:</strong>
                            <span>{{ $revocationData['timestamp'] }}</span>
                        </div>
                        <div class="data-row">
                            <strong>Vorgangs- / Bestellnummer:</strong>
                            <span>{{ $revocationData['order_number'] }}</span>
                        </div>
                        <div class="data-row">
                            <strong>Name:</strong>
                            <span>{{ $revocationData['name'] }}</span>
                        </div>
                        <div class="data-row">
                            <strong>E-Mail-Adresse:</strong>
                            <span>{{ $revocationData['email'] }}</span>
                        </div>
                        @if(!empty($revocationData['items']))
                        <div class="data-row">
                            <strong>Zusätzliche Angaben / Artikel:</strong>
                            <span>{{ $revocationData['items'] }}</span>
                        </div>
                        @endif
                        @if(!empty($revocationData['attachments']))
                        <div class="data-row">
                            <strong>Hochgeladene Nachweise:</strong>
                            <span>{{ count($revocationData['attachments']) }} Datei(en) angehängt</span>
                        </div>
                        @endif
                    </div>

                    <div class="link-info">
                        <p style="font-size: 13px; color: #92400e; font-weight: bold; margin-bottom: 8px !important;">
                            Wichtiger rechtlicher Hinweis:
                        </p>
                        <p style="font-size: 13px; color: #92400e; margin-bottom: 0 !important; line-height: 1.5;">
                            Diese E-Mail ist eine automatisierte Eingangsbestätigung, die wir Ihnen als Online-Händler nach Eingang eines Widerrufs gesetzlich unverzüglich zusenden müssen. Sie stellt keine inhaltliche Prüfung oder Anerkennung der rechtlichen Wirksamkeit Ihres Widerrufs dar. Wie gesetzlich verankert (§ 312g Abs. 2 Nr. 1 BGB), sind nach Kundenspezifikation personalisierte Gravur-Artikel vom Widerrufsrecht ausgeschlossen. Wir prüfen Ihr Anliegen und melden uns in Kürze manuell bei Ihnen.
                        </p>
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
                        <a href="{{ url('/agb') }}">AGB & Widerruf</a>
                    </p>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
