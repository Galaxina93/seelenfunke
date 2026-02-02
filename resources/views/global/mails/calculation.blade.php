<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333333;
            line-height: 1.5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-top: 5px solid #C5A059;
        }

        /* HEADER */
        .header {
            border-bottom: 2px solid #C5A059;
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .logo { max-width: 180px; height: auto; }

        /* TEXT */
        h1 { font-size: 22px; color: #111; margin-bottom: 10px; font-weight: bold; }
        .badge-express { background: #dc2626; color: white; padding: 3px 8px; border-radius: 4px; font-size: 10px; vertical-align: middle; margin-left: 10px; }

        /* TABELLE */
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .table th { text-align: left; color: #888; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .table td { padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
        .text-right { text-align: right; }

        /* PRODUKT VORSCHAU */
        .preview-container {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            background-color: #f9f9f9;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            overflow: hidden;
            margin-top: 10px;
        }
        .marker { position: absolute; width: 8px; height: 8px; border-radius: 50%; margin-left: -4px; margin-top: -4px; border: 1px solid white; box-shadow: 0 0 2px rgba(0,0,0,0.5); }
        .marker-text { background-color: #007bff; }
        .marker-logo { background-color: #28a745; }

        /* DETAILS */
        .detail-info { font-size: 11px; color: #666; margin-top: 4px; line-height: 1.4; }
        .detail-label { font-weight: bold; color: #444; margin-right: 4px; }
        .note-box { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 8px; margin-top: 8px; font-size: 11px; border-radius: 4px; }

        /* TOTALS */
        .totals { margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        .totals-final { font-size: 18px; font-weight: bold; color: #C5A059; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }

        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 11px; color: #999; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein Seelenfunke" class="logo">
    </div>

    <h1>Deine Preiskalkulation / Anfrage
        @if(!empty($data['express']))
            <span class="badge-express">EXPRESS</span>
        @endif
    </h1>
    <p>Hallo {{ $data['contact']['vorname'] }}, vielen Dank für dein Interesse! Hier ist die Zusammenfassung deiner aktuellen Kalkulation:</p>

    <table class="table">
        <thead>
        <tr>
            <th width="65%">Artikel & Konfiguration</th>
            <th width="10%" class="text-right">Menge</th>
            <th width="25%" class="text-right">Preis (Brutto)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['items'] as $item)
            @php
                $conf = $item['config'] ?? [];
                $imgPath = $conf['product_image_path'] ?? null;
            @endphp
            <tr>
                <td>
                    <strong style="font-size: 14px; color: #222;">{{ $item['name'] }}</strong>

                    @if(!empty($imgPath))
                        <div class="preview-container" style="background-image: url('{{ asset($imgPath) }}');">
                            @if(isset($conf['text_x'])) <div class="marker marker-text" style="left: {{ $conf['text_x'] }}%; top: {{ $conf['text_y'] }}%;"></div> @endif
                            @if(isset($conf['logo_x'])) <div class="marker marker-logo" style="left: {{ $conf['logo_x'] }}%; top: {{ $conf['logo_y'] }}%;"></div> @endif
                        </div>
                    @endif

                    <div class="detail-info">
                        @if(!empty($conf['text']))
                            <div><span class="detail-label">Gravur:</span> "{{ $conf['text'] }}" ({{ $conf['font'] ?? 'Standard' }})</div>
                        @endif
                        @if(!empty($conf['logo_storage_path']))
                            <div style="color: #C5A059;">✓ Logo-Datei im Anhang enthalten</div>
                        @endif
                    </div>

                    @if(!empty($conf['notes']))
                        <div class="note-box"><strong>Deine Anmerkung:</strong> {{ $conf['notes'] }}</div>
                    @endif
                </td>
                <td class="text-right">{{ $item['quantity'] }}x</td>
                <td class="text-right">{{ $item['total_price'] }} €</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table width="100%">
            {{-- Hier nutzen wir direkt die berechneten Werte der Komponente ohne Rückrechnung --}}
            <tr>
                <td class="text-right" style="color: #666; font-size: 13px;">Warenwert (Netto):</td>
                <td width="100" class="text-right" style="color: #666; font-size: 13px;">{{ $data['total_netto'] }} €</td>
            </tr>

            <tr>
                <td class="text-right" style="color: #666; font-size: 13px;">Versand ({{ $data['contact']['country'] }}):</td>
                <td class="text-right" style="color: #666; font-size: 13px;">
                    {{ ($data['shipping_price'] === '0,00') ? 'Kostenlos' : $data['shipping_price'] . ' €' }}
                </td>
            </tr>

            @if(!empty($data['express']))
                <tr>
                    <td class="text-right" style="color: #dc2626; font-size: 13px;">Express-Zuschlag:</td>
                    <td class="text-right" style="color: #dc2626; font-size: 13px;">25,00 €</td>
                </tr>
            @endif

            <tr>
                <td class="text-right" style="color: #888; font-size: 11px; font-style: italic;">Enthaltene MwSt. (19%):</td>
                <td class="text-right" style="color: #888; font-size: 11px; font-style: italic;">{{ $data['total_vat'] }} €</td>
            </tr>

            <tr class="totals-final">
                <td class="text-right" style="padding-top: 15px; border-top: 1px solid #eee;">Gesamtsumme (Brutto):</td>
                <td class="text-right" style="padding-top: 15px; border-top: 1px solid #eee;">{{ $data['total_gross'] }} €</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Dies ist ein unverbindliches Angebot. Gültig für 14 Tage.<br>
            Mein Seelenfunke | <a href="mailto:kontakt@mein-seelenfunke.de">kontakt@mein-seelenfunke.de</a></p>
    </div>
</div>

</body>
</html>
