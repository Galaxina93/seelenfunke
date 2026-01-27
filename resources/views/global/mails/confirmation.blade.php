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
            margin: 0 auto;
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

        /* PRODUKT VORSCHAU (CSS aus PDF adaptiert) */
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

        /* TOTALS */
        .totals { margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        .totals-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 13px; }
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

<div class="container">
    {{-- HEADER --}}
    <div class="header">
        {{-- Logo muss absolute URL sein für E-Mails --}}
        <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein Seelenfunke" class="logo">
    </div>

    {{-- ANSPRACHE --}}
    <h1>Vielen Dank, {{ $order->billing_address['first_name'] }}!</h1>
    <p>Wir haben deine Bestellung <strong>#{{ $order->order_number }}</strong> erhalten und bereiten diese nun mit viel Liebe für dich vor.</p>

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
        @foreach($order->items as $item)
            @php
                // Konfiguration sicher abrufen
                $conf = $item->configuration ?? [];
                // Bildpfad prüfen (muss public sein)
                $imgPath = $conf['product_image_path'] ?? null;
                $hasImage = $imgPath && file_exists(public_path($imgPath));
            @endphp
            <tr>
                <td>
                    <strong style="font-size: 14px; color: #222;">{{ $item->product_name }}</strong>

                    {{-- VISUELLE VORSCHAU --}}
                    @if($hasImage)
                        <div class="preview-wrapper">
                            {{-- Hintergrundbild mit asset() laden für URL --}}
                            <div class="preview-container" style="background-image: url('{{ asset($imgPath) }}');">
                                {{-- Blauer Punkt für Text --}}
                                @if(isset($conf['text_x']))
                                    <div class="marker marker-text" style="left: {{ $conf['text_x'] }}%; top: {{ $conf['text_y'] }}%;"></div>
                                @endif
                                {{-- Grüner Punkt für Logo --}}
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
                    </div>

                    {{-- HINWEIS --}}
                    @if(!empty($conf['notes']))
                        <div class="note-box">
                            <strong>Deine Anmerkung:</strong><br>
                            {{ $conf['notes'] }}
                        </div>
                    @endif
                </td>
                <td class="text-right">{{ $item->quantity }}x</td>
                <td class="text-right">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- TOTALS BLOCK IN DER MAIL --}}
    <div class="totals">
        {{-- 1. ECHTER WARENWERT (Originalsumme) --}}
        @php
            // Originalsumme zurückrechnen (Subtotal + Mengenrabatt)
            $originalSum = $order->subtotal_price + ($order->volume_discount ?? 0);
        @endphp

        <div class="totals-row">
            <span>Warenwert</span>
            <span>{{ number_format($originalSum / 100, 2, ',', '.') }} €</span>
        </div>

        {{-- 2. MENGENRABATT --}}
        @if(isset($order->volume_discount) && $order->volume_discount > 0)
            <div class="totals-row" style="color: #16a34a;">
                <span>Mengenrabatt</span>
                <span>-{{ number_format($order->volume_discount / 100, 2, ',', '.') }} €</span>
            </div>
        @endif

        {{-- 3. ZWISCHENSUMME (Nach Mengenrabatt) --}}
        {{-- Optional: Wenn Mengenrabatt existiert, macht eine Zwischensumme Sinn, sonst weglassen --}}
        @if(isset($order->volume_discount) && $order->volume_discount > 0)
            <div class="totals-row" style="border-top: 1px dashed #eee; margin-top: 5px; padding-top: 5px;">
                <span>Zwischensumme</span>
                <span>{{ number_format($order->subtotal_price / 100, 2, ',', '.') }} €</span>
            </div>
        @endif

        {{-- 4. GUTSCHEIN --}}
        @if(isset($order->discount_amount) && $order->discount_amount > 0)
            <div class="totals-row" style="color: #16a34a;">
                <span>Gutschein ({{ $order->coupon_code }})</span>
                <span>-{{ number_format($order->discount_amount / 100, 2, ',', '.') }} €</span>
            </div>
        @endif

        {{-- 5. VERSAND & STEUER --}}
        <div class="totals-row" style="color: #888;">
            <span>Versand</span>
            <span>{{ $order->shipping_price > 0 ? number_format($order->shipping_price / 100, 2, ',', '.') . ' €' : 'Kostenlos' }}</span>
        </div>
        <div class="totals-row" style="color: #888; font-size: 11px;">
            <span>Enthaltene MwSt.</span>
            <span>{{ number_format($order->tax_amount / 100, 2, ',', '.') }} €</span>
        </div>

        {{-- 6. ENDSUMME --}}
        <div class="totals-row totals-final">
            <span>Gesamtsumme</span>
            <span>{{ number_format($order->total_price / 100, 2, ',', '.') }} €</span>
        </div>
    </div>

    {{-- ADRESSEN --}}
    <table width="100%" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
        <tr>
            <td width="50%" valign="top">
                <h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #888;">Rechnungsadresse</h4>
                <p style="margin: 0; font-size: 13px; color: #444;">
                    {{ $order->billing_address['first_name'] }} {{ $order->billing_address['last_name'] }}<br>
                    @if(!empty($order->billing_address['company'])) {{ $order->billing_address['company'] }}<br> @endif
                    {{ $order->billing_address['address'] }}<br>
                    {{ $order->billing_address['postal_code'] }} {{ $order->billing_address['city'] }}<br>
                    {{ $order->billing_address['country'] }}
                </p>
            </td>
            <td width="50%" valign="top">
                {{-- Falls Lieferadresse abweichend implementiert ist, hier anzeigen, sonst Billing --}}
                <h4 style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #888;">Lieferadresse</h4>
                <p style="margin: 0; font-size: 13px; color: #444;">
                    @php $ship = $order->shipping_address ?? $order->billing_address; @endphp
                    {{ $ship['first_name'] ?? $ship['first_name'] }} {{ $ship['last_name'] ?? $ship['last_name'] }}<br>
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
