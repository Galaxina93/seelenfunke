<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Angebot - {{ $data['quote_number'] ?? '' }}</title>
    <style>
        /* SEITENRÄNDER: Unten genug Platz für den Footer lassen */
        @page {
            margin: 40px 40px 120px 40px; /* Unten 120px Rand */
        }

        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }

        /* --- FOOTER (Fixiert im unteren Seitenrand) --- */
        footer {
            position: fixed;
            bottom: -80px; /* Positioniert im @page margin-bottom Bereich */
            left: 0px;
            right: 0px;
            height: 90px;
            font-size: 10px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        /* --- HEADER --- */
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #C5A059;
            padding-bottom: 10px;
        }
        .logo { width: 150px; }

        /* --- TABELLEN & INHALT --- */
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border-bottom: 1px solid #eee; padding: 10px; vertical-align: top; }
        .table th { background: #f9f9f9; text-align: left; color: #888; text-transform: uppercase; font-size: 10px; }
        .text-right { text-align: right; }

        .detail-block { margin-top: 5px; font-size: 11px; color: #555; }
        .detail-row { margin-bottom: 3px; }
        .meta-label { font-weight: bold; color: #444; width: 80px; display: inline-block; }

        .note-box { background: #ffffee; border: 1px solid #eec; padding: 5px; margin-top: 8px; font-style: italic; font-size: 10px; }

        .totals-table { width: 100%; margin-top: 30px; border-collapse: collapse; page-break-inside: avoid; }
        .totals-table td { padding: 5px 10px; text-align: right; }
        .totals-label { font-weight: bold; color: #555; }
        .totals-value { width: 120px; }
        .gross-row td { border-top: 2px solid #333; border-bottom: 2px solid #333; padding-top: 8px; padding-bottom: 8px; font-size: 14px; font-weight: bold; color: #000; }
        .vat-row td { border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 10px; }

        /* --- VORSCHAU BOX --- */
        .preview-wrapper {
            margin-top: 15px;
            margin-bottom: 5px;
            display: block;
            page-break-inside: avoid;
        }

        .preview-container {
            position: relative;
            width: 100px;
            height: 100px;
            display: inline-block;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fcfcfc;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            overflow: hidden;
        }

        .marker {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: -4px;
            margin-top: -4px;
            border: 1px solid white;
            box-shadow: 0 0 2px rgba(0,0,0,0.5);
            z-index: 20;
        }
        .marker-text { background-color: #007bff; }
        .marker-logo { background-color: #28a745; }

        .legend { font-size: 9px; color: #888; margin-top: 4px; }
        .dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 3px; vertical-align: middle; }
    </style>
</head>
<body>

{{-- FOOTER (Muss vor dem Content stehen) --}}
<footer>
    <table width="100%" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td valign="top" width="33%">
                <strong>Angaben gemäß § 5 TMG</strong><br>
                Mein Seelenfunke<br>
                Inhaberin: Alina Steinhauer
            </td>
            <td valign="top" width="33%">
                <strong>Anschrift</strong><br>
                Carl-Goerdeler-Ring 26<br>
                38518 Gifhorn<br>
                Deutschland
            </td>
            <td valign="top" width="33%" style="text-align: right;">
                <strong>Kontakt</strong><br>
                Tel: +49 (0) 159 019 668 64<br>
                E-Mail: kontakt@mein-seelenfunke.de
            </td>
        </tr>
    </table>
</footer>

{{-- HAUPTINHALT --}}
<main>
    <div class="header">
        <table width="100%">
            <tr>
                <td><img src="{{ public_path('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="logo"></td>
                <td class="text-right">
                    <strong>Angebot: {{ $data['quote_number'] ?? 'N/A' }}</strong><br>
                    Datum: {{ now()->format('d.m.Y') }}
                </td>
            </tr>
        </table>
    </div>

    <h3>Angebot für {{ $data['contact']['firma'] ?? ($data['contact']['vorname'] . ' ' . $data['contact']['nachname']) }}</h3>

    <table class="table">
        <thead>
        <tr>
            <th width="45%">Artikel & Konfiguration</th>
            <th width="20%">Details</th>
            <th width="10%" class="text-right">Menge</th>
            <th width="10%" class="text-right">Einzelpreis</th>
            <th width="15%" class="text-right">Gesamt</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['items'] as $item)
            {{-- Nur echte Artikel anzeigen, Versand und Express kommen in die Summen-Tabelle --}}
            @if($item['name'] !== 'Versand & Verpackung' && $item['name'] !== 'Express-Service' && !str_contains($item['name'], 'Versand'))
                <tr>
                    {{-- SPALTE 1: ARTIKEL & VORSCHAU --}}
                    <td>
                        <div style="margin-bottom: 5px;"><strong>{{ $item['name'] }}</strong></div>

                        {{-- Bild mit Markern --}}
                        @if(!empty($item['config']) && !empty($item['config']['product_image_path']))
                            @if(file_exists(public_path($item['config']['product_image_path'])))
                                <div class="preview-wrapper">
                                    <div class="preview-container" style="background-image: url('{{ public_path($item['config']['product_image_path']) }}');">

                                        {{-- Blauer Punkt für Text --}}
                                        @if(isset($item['config']['text_x']))
                                            <div class="marker marker-text" style="left: {{ $item['config']['text_x'] }}%; top: {{ $item['config']['text_y'] }}%;"></div>
                                        @endif

                                        {{-- Grüner Punkt für Logo --}}
                                        @if(isset($item['config']['logo_x']) && !empty($item['config']['logo_storage_path']))
                                            <div class="marker marker-logo" style="left: {{ $item['config']['logo_x'] }}%; top: {{ $item['config']['logo_y'] }}%;"></div>
                                        @endif
                                    </div>

                                    <div class="legend">
                                        @if(isset($item['config']['text_x']))
                                            <span class="dot" style="background-color:#007bff;"></span>Text
                                        @endif
                                        @if(isset($item['config']['logo_x']) && !empty($item['config']['logo_storage_path']))
                                            &nbsp; <span class="dot" style="background-color:#28a745;"></span>Logo
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if(!empty($item['config']['notes']))
                            <div class="note-box">
                                <strong>Anmerkung:</strong><br>
                                {{ $item['config']['notes'] }}
                            </div>
                        @endif
                    </td>

                    {{-- SPALTE 2: TEXT DETAILS --}}
                    <td>
                        @if(!empty($item['config']))
                            <div class="detail-block">
                                @if(!empty($item['config']['text']))
                                    <div class="detail-row"><span class="meta-label">Gravur:</span> "{{ $item['config']['text'] }}"</div>
                                    <div class="detail-row"><span class="meta-label">Schrift:</span> {{ $item['config']['font'] }}</div>
                                    <div class="detail-row"><span class="meta-label">Pos:</span> {{ $item['config']['text_pos_label'] ?? 'Zentriert' }}</div>
                                @endif

                                @if(!empty($item['config']['logo_storage_path']))
                                    <div class="detail-row" style="margin-top:8px; color:green;"><span class="meta-label">Logo:</span> Datei erhalten</div>
                                    <div class="detail-row"><span class="meta-label">Pos:</span> {{ $item['config']['logo_pos_label'] ?? 'Oben Mitte' }}</div>
                                @endif
                            </div>
                        @endif
                    </td>

                    <td class="text-right">{{ $item['quantity'] }}</td>
                    <td class="text-right">{{ $item['single_price'] }} €</td>
                    <td class="text-right">{{ $item['total_price'] }} €</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    {{-- BERECHNUNG DER ZWISCHENSUMMEN --}}
    @php
        $vatRate = 0.19;
        $goodsGross = 0;
        $shippingGross = 0;
        $expressGross = (!empty($data['express']) && $data['express']) ? 25.00 : 0;

        foreach($data['items'] as $item) {
            $price = (float)str_replace(',', '.', str_replace('.', '', $item['total_price']));
            if($item['name'] === 'Versand & Verpackung' || str_contains($item['name'], 'Versand')) {
                $shippingGross = $price;
            } elseif($item['name'] !== 'Express-Service') {
                $goodsGross += $price;
            }
        }

        $goodsNetto = $goodsGross / (1 + $vatRate);
        $shippingNetto = $shippingGross / (1 + $vatRate);
        $expressNetto = $expressGross / (1 + $vatRate);
    @endphp

    <table class="totals-table">
        <tr>
            <td width="60%"></td>
            <td class="totals-label">Warenwert (Netto):</td>
            <td class="totals-value">{{ number_format($goodsNetto, 2, ',', '.') }} €</td>
        </tr>

        @if($shippingGross > 0)
            <tr>
                <td></td>
                <td class="totals-label">Versandkosten (Netto):</td>
                <td class="totals-value">{{ number_format($shippingNetto, 2, ',', '.') }} €</td>
            </tr>
        @endif

        @if($expressGross > 0)
            <tr>
                <td></td>
                <td class="totals-label" style="color: #dc2626;">Express-Zuschlag (Netto):</td>
                <td class="totals-value">{{ number_format($expressNetto, 2, ',', '.') }} €</td>
            </tr>
        @endif

        <tr>
            <td></td>
            <td class="totals-label">Zwischensumme (Netto):</td>
            <td class="totals-value">{{ $data['total_netto'] }} €</td>
        </tr>
        <tr class="vat-row">
            <td></td>
            <td class="totals-label">zzgl. 19% MwSt.:</td>
            <td class="totals-value">{{ $data['total_vat'] }} €</td>
        </tr>
        <tr class="gross-row">
            <td></td>
            <td><strong>Gesamtsumme (Brutto):</strong></td>
            <td><strong>{{ $data['total_gross'] }} €</strong></td>
        </tr>
    </table>

    <div style="margin-top: 40px; font-size: 11px; color: #555; border-top: 1px solid #eee; padding-top: 15px;">
        <strong>Kundeninformationen:</strong><br>
        {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}
        @if(!empty($data['contact']['firma'])) ({{ $data['contact']['firma'] }}) @endif
        <br>E-Mail: {{ $data['contact']['email'] }}
        @if(!empty($data['contact']['telefon'])) | Tel: {{ $data['contact']['telefon'] }} @endif

        @if(!empty($data['express']))
            <div style="margin-top: 10px; color: #dc2626; font-weight: bold; border: 1px solid #dc2626; padding: 5px; display: inline-block;">
                EXPRESS-AUFTRAG
                @if(!empty($data['deadline']))
                    (Wunschtermin: {{ \Carbon\Carbon::parse($data['deadline'])->format('d.m.Y') }})
                @endif
            </div>
        @endif

        <div style="margin-top: 15px; font-style: italic; color: #888;">
            Dieses Angebot wurde digital erstellt und ist gültig bis zum {{ $data['quote_expiry'] }}.
        </div>
    </div>
</main>

</body>
</html>
