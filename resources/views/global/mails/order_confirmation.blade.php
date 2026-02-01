<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bestellbestätigung</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333333;
            line-height: 1.5;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* HEADER */
        .header {
            border-bottom: 2px solid #C5A059;
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .logo {
            max-width: 180px;
            height: auto;
        }

        /* TEXT */
        h1 { font-size: 22px; color: #111; margin-bottom: 10px; font-weight: bold; }
        p { font-size: 14px; color: #555; margin-bottom: 15px; }

        /* TABELLE */
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .table th { text-align: left; color: #888; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .table td { padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
        .text-right { text-align: right; }

        /* PRODUKT VORSCHAU */
        .preview-wrapper {
            margin-top: 10px;
            display: block;
        }
        .preview-container {
            position: relative;
            width: 100px;
            height: 100px;
            display: inline-block;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            background-color: #f9f9f9;
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

        /* DETAILS */
        .detail-info { font-size: 11px; color: #666; margin-top: 4px; line-height: 1.4; }
        .detail-label { font-weight: bold; color: #444; margin-right: 4px; }
        .note-box { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 8px; margin-top: 8px; font-size: 11px; border-radius: 4px; }

        /* SEAL / FINGERPRINT */
        .seal-box { margin-top: 10px; padding: 6px 10px; background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 4px; display: inline-block; }
        .seal-text { font-size: 9px; color: #166534; font-family: monospace; }

        /* TOTALS */
        .totals { margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        .totals-row { margin-bottom: 5px; font-size: 13px; }
        .totals-final { font-size: 18px; font-weight: bold; color: #C5A059; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }

        /* FOOTER */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
        .footer a { color: #C5A059; text-decoration: none; }
    </style>
</head>
<body>

@php
    // Nutzung der zentralen Formatierungs-Methode aus dem Order-Model
    $data = $order->toFormattedArray();
@endphp

<div class="container">

    {{-- HEADER --}}
    <div class="header">
        <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein Seelenfunke" class="logo">
    </div>

    {{-- ANSPRACHE --}}
    <h1>Vielen Dank, {{ $data['contact']['vorname'] }}!</h1>
    <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten und bereiten diese nun mit viel Liebe für dich vor.</p>

    {{-- ARTIKEL LISTE --}}
    <table class="table">
        <thead>
        <tr>
            <th width="60%">Artikel & Konfiguration</th>
            <th width="15%" class="text-right">Menge</th>
            <th width="25%" class="text-right">Preis</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data['items'] as $item)
            @php
                $conf = $item['config'] ?? [];
                $imgPath = $conf['product_image_path'] ?? null;
                $hasImage = !empty($imgPath);
            @endphp
            <tr>
                <td>
                    <strong style="font-size: 14px; color: #222;">{{ $item['name'] }}</strong>

                    {{-- VISUELLE VORSCHAU --}}
                    @if($hasImage)
                        <div class="preview-wrapper">
                            <div class="preview-container" style="background-image: url('{{ asset($imgPath) }}');">
                                @if(isset($conf['text_x']))
                                    <div class="marker marker-text" style="left: {{ $conf['text_x'] }}%; top: {{ $conf['text_y'] }}%;"></div>
                                @endif

                                @if(isset($conf['logo_x']) && !empty($conf['logo_storage_path']))
                                    <div class="marker marker-logo" style="left: {{ $conf['logo_x'] }}%; top: {{ $conf['logo_y'] }}%;"></div>
                                @endif
                            </div>
                            <div style="font-size: 9px; color: #999; margin-top: 2px;">
                                @if(isset($conf['text_x'])) <span style="color:#007bff;">●</span> Text @endif
                                @if(isset($conf['logo_x']) && !empty($conf['logo_storage_path'])) <span style="color:#28a745; margin-left:5px;">●</span> Logo @endif
                            </div>
                        </div>
                    @endif

                    {{-- DETAILS --}}
                    <div class="detail-info">
                        @if(!empty($conf['text']))
                            <div><span class="detail-label">Gravur:</span> "{{ $conf['text'] }}"</div>
                            <div><span class="detail-label">Schrift:</span> {{ $conf['font'] ?? 'Standard' }}</div>
                        @endif

                        @if(!empty($conf['logo_storage_path']))
                            <div style="margin-top: 4px;">
                                <span class="detail-label">Logo:</span>
                                <a href="{{ asset('storage/'.$conf['logo_storage_path']) }}" style="color:#C5A059; text-decoration:underline;">Datei ansehen</a>
                            </div>
                        @endif

                        {{-- ECHTHEITS-SIEGEL (Hash aus dem Datenbank-Feld des Models) --}}
                        @if(!empty($order->items->firstWhere('product_name', $item['name'])->config_fingerprint))
                            <div class="seal-box">
                                <span style="font-size: 8px; font-weight: bold; color: #166534; display: block; text-transform: uppercase;">Digitales Siegel</span>
                                <span class="seal-text">{{ substr($order->items->firstWhere('product_name', $item['name'])->config_fingerprint, 0, 16) }}...</span>
                            </div>
                        @endif
                    </div>

                    {{-- HINWEIS --}}
                    @if(!empty($conf['notes']))
                        <div class="note-box">
                            <strong>Deine Anmerkung:</strong><br>
                            {{ $conf['notes'] }}
                        </div>
                    @endif
                </td>
                <td class="text-right">{{ $item['quantity'] }}x</td>
                <td class="text-right">{{ $item['total_price'] }} €</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- TOTALS BLOCK --}}
    <div class="totals">
        <table width="100%">
            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #666;">Warenwert (Netto):</td>
                <td width="100" class="text-right" style="padding-bottom: 5px; color: #666;">{{ $data['total_netto'] }} €</td>
            </tr>

            @if($order->volume_discount > 0)
                <tr>
                    <td class="text-right" style="padding-bottom: 5px; color: #16a34a;">Mengenrabatt:</td>
                    <td class="text-right" style="padding-bottom: 5px; color: #16a34a;">-{{ number_format($order->volume_discount / 100, 2, ',', '.') }} €</td>
                </tr>
            @endif

            @if($order->discount_amount > 0)
                <tr>
                    <td class="text-right" style="padding-bottom: 5px; color: #16a34a;">Gutschein ({{ $order->coupon_code }}):</td>
                    <td class="text-right" style="padding-bottom: 5px; color: #16a34a;">-{{ number_format($order->discount_amount / 100, 2, ',', '.') }} €</td>
                </tr>
            @endif

            <tr>
                <td class="text-right" style="padding-bottom: 5px; color: #666;">Versand:</td>
                <td class="text-right" style="padding-bottom: 5px; color: #666;">{{ $data['shipping_price'] }} €</td>
            </tr>

            @if($data['express'])
                <tr>
                    <td class="text-right" style="padding-bottom: 5px; color: #dc2626;">Express-Service:</td>
                    <td class="text-right" style="padding-bottom: 5px; color: #dc2626;">25,00 €</td>
                </tr>
            @endif

            <tr>
                <td class="text-right" style="padding-bottom: 10px; color: #888; font-size: 11px; font-style: italic;">Enthaltene MwSt. (19%):</td>
                <td class="text-right" style="padding-bottom: 10px; color: #888; font-size: 11px; font-style: italic;">{{ $data['total_vat'] }} €</td>
            </tr>

            <tr class="totals-final">
                <td class="text-right" style="padding-top: 15px; border-top: 1px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059;">Gesamtsumme (Brutto):</td>
                <td class="text-right" style="padding-top: 15px; border-top: 1px solid #eee; font-size: 18px; font-weight: bold; color: #C5A059;">{{ $data['total_gross'] }} €</td>
            </tr>
        </table>
    </div>

    {{-- ADRESSEN --}}
    <table width="100%" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
        <tr>
            <td width="50%" valign="top">
                <h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #888;">Rechnungsadresse</h4>
                <p style="margin: 0; font-size: 13px; color: #444;">
                    {{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}<br>
                    @if(!empty($data['contact']['firma'])) {{ $data['contact']['firma'] }}<br> @endif
                    {{ $order->billing_address['address'] }}<br>
                    {{ $order->billing_address['postal_code'] }} {{ $order->billing_address['city'] }}<br>
                    {{ $data['contact']['country'] }}
                </p>
            </td>
            <td width="50%" valign="top">
                <h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #888;">Lieferadresse</h4>
                <p style="margin: 0; font-size: 13px; color: #444;">
                    @php $ship = $order->shipping_address ?? $order->billing_address; @endphp
                    {{ $ship['first_name'] }} {{ $ship['last_name'] }}<br>
                    @if(!empty($ship['company'])) {{ $ship['company'] }}<br> @endif
                    {{ $ship['address'] }}<br>
                    {{ $ship['postal_code'] }} {{ $ship['city'] }}<br>
                    {{ $ship['country'] }}
                </p>
            </td>
        </tr>
    </table>

    {{-- FOOTER --}}
    <div class="footer">
        <p>
            Mein Seelenfunke | Inh. Alina Steinhauer<br>
            Carl-Goerdeler-Ring 26, 38518 Gifhorn<br>
            <a href="mailto:kontakt@mein-seelenfunke.de">kontakt@mein-seelenfunke.de</a> | <a href="{{ url('/') }}">www.mein-seelenfunke.de</a>
        </p>
        <p>
            <a href="{{ url('/agb') }}">AGB</a> | <a href="{{ url('/datenschutz') }}">Datenschutz</a> | <a href="{{ url('/impressum') }}">Impressum</a>
        </p>
    </div>
</div>

</body>
</html>
