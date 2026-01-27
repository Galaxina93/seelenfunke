<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ihr persönliches Angebot</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
            color: #4a4a4a;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        /* HEADER */
        .header {
            border-bottom: 2px solid #C5A059;
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .header h1 {
            color: #C5A059;
            font-family: serif; /* Optional, falls passend zum Corporate Design */
            margin: 0;
            font-size: 24px;
        }

        /* CONTENT */
        h2 { color: #333; font-size: 18px; margin-top: 0; }
        p { margin-bottom: 15px; font-size: 14px; }

        .info-box {
            background-color: #f8f8f8;
            border-left: 4px solid #C5A059;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
        }

        /* BUTTON */
        .btn-container { text-align: center; margin: 30px 0; }
        .btn {
            background-color: #C5A059;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 16px;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(197, 160, 89, 0.3);
        }
        .btn:hover { background-color: #b08d4b; }

        /* TABLE */
        .item-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .item-table th { text-align: left; color: #888; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: normal; font-size: 12px; text-transform: uppercase; }
        .item-table td { padding: 12px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
        .item-name { font-weight: bold; color: #333; }
        .item-meta { font-size: 12px; color: #888; margin-top: 4px; }

        /* TOTALS */
        .totals { margin-top: 20px; text-align: right; }
        .total-row { margin-bottom: 5px; font-size: 14px; color: #666; }
        .total-final { font-size: 18px; font-weight: bold; color: #C5A059; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; display: inline-block; }

        /* FOOTER */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .footer a { color: #999; text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Mein Seelenfunke</h1>
    </div>

    <p>Hallo {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }},</p>

    <p>vielen Dank für deine Anfrage! Wir haben deine Konfiguration erhalten und geprüft.</p>

    <div class="info-box">
        <strong>Gut zu wissen:</strong><br>
        Deine hochgeladenen Logos und Bilder wurden sicher auf unserem geschützten Server gespeichert und liegen deiner Bestellung automatisch bei. Du musst sie nicht erneut senden.
    </div>

    <p>Hier ist eine Zusammenfassung deiner Wunschartikel. Im Anhang findest du zusätzlich das detaillierte PDF-Angebot.</p>

    {{-- ARTIKEL LISTE --}}
    <table class="item-table">
        <thead>
        <tr>
            <th width="60%">Artikel</th>
            <th width="15%" style="text-align: center;">Menge</th>
            <th width="25%" style="text-align: right;">Gesamt</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['items'] as $item)
            <tr>
                <td>
                    <div class="item-name">{{ $item['name'] }}</div>
                    <div class="item-meta">
                        Einzelpreis: {{ $item['single_price'] }} €<br>
                        @if(!empty($item['config']['text']))
                            Gravur: "<em>{{ $item['config']['text'] }}</em>"<br>
                        @endif
                        @if(!empty($item['config']['logo_storage_path']))
                            <span style="color:#2ecc71;">✔ Logo enthalten</span>
                        @endif
                    </div>
                </td>
                <td style="text-align: center;">{{ $item['quantity'] }}</td>
                <td style="text-align: right;">{{ $item['total_price'] }} €</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- PREISAUFSTELLUNG --}}
    <div class="totals">
        <div class="total-row">Zwischensumme (Netto): {{ $data['total_netto'] }} €</div>
        <div class="total-row">zzgl. 19% MwSt.: {{ $data['total_vat'] }} €</div>

        @if(!empty($data['express']) && $data['express'])
            <div class="total-row" style="color: #dc2626;">Inkl. Express-Zuschlag</div>
        @endif

        <div class="total-final">
            Gesamtsumme: {{ $data['total_gross'] }} €
        </div>
    </div>

    {{-- ACTION BUTTON --}}
    @if(isset($data['quote_token']))
        <div class="btn-container">
            <p style="margin-bottom: 15px; font-size: 13px;">Dieses Angebot ist gültig bis zum {{ $data['quote_expiry'] }}.</p>
            <a href="{{ route('quote.accept', ['token' => $data['quote_token']]) }}" class="btn">
                Jetzt Angebot verbindlich annehmen
            </a>
        </div>
    @endif

    <p style="font-size: 13px;">
        Solltest du noch Fragen oder Änderungswünsche haben, antworte einfach auf diese E-Mail.
    </p>

    <div class="footer">
        <p>
            Mein Seelenfunke | Inh. Alina Steinhauer<br>
            Carl-Goerdeler-Ring 26, 38518 Gifhorn<br>
            <a href="mailto:kontakt@mein-seelenfunke.de">kontakt@mein-seelenfunke.de</a> | <a href="{{ url('/') }}">www.mein-seelenfunke.de</a>
        </p>
    </div>
</div>
</body>
</html>
