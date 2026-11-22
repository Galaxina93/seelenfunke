<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Großbrief Etikett {{ $orderNumber }}</title>
    <style>
        @page {
            size: 288pt 432pt;
            margin: 0;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 288pt;
            height: 432pt;
            font-family: 'DejaVu Sans', sans-serif;
            color: #000000;
            background: #ffffff;
        }
        .sheet {
            width: 264pt;
            height: 408pt;
            margin: 12pt;
            position: relative;
        }
        .top-table {
            width: 100%;
            border-collapse: collapse;
        }
        .top-table td {
            vertical-align: top;
            padding: 0;
        }
        .sender-cell {
            width: 136pt;
            padding-right: 8pt !important;
        }
        .sender-label {
            font-size: 6.5pt;
            font-weight: bold;
            letter-spacing: 0.8pt;
            text-transform: uppercase;
            margin-bottom: 3pt;
        }
        .sender-box {
            font-size: 8pt;
            line-height: 1.35;
            border-bottom: 1pt solid #000000;
            padding-bottom: 6pt;
        }
        .sender-brand {
            font-weight: bold;
            font-size: 8.5pt;
        }
        .franking-cell {
            width: 128pt;
            text-align: right;
        }
        .franking-box {
            width: 120pt;
            height: 82pt;
            margin-left: auto;
            border: 1pt dashed #000000;
            text-align: center;
            padding: 8pt 6pt;
        }
        .franking-box-porto {
            border: 1.5pt solid #000000;
            padding-top: 14pt;
        }
        .franking-title {
            font-size: 7pt;
            font-weight: bold;
            letter-spacing: 0.6pt;
            text-transform: uppercase;
            margin-bottom: 8pt;
        }
        .porto-tag {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 1pt;
            line-height: 1.15;
        }
        .porto-code {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 1.5pt;
            margin-top: 3pt;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .franking-hint {
            font-size: 7pt;
            line-height: 1.35;
            margin-top: 10pt;
        }
        .recipient-block {
            margin-top: 26pt;
            padding: 14pt 10pt;
            border: 1.2pt solid #000000;
        }
        .service-badge {
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.9pt;
            text-transform: uppercase;
            margin-bottom: 10pt;
            padding-bottom: 4pt;
            border-bottom: 0.8pt solid #000000;
        }
        .recipient-name {
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.3;
        }
        .recipient-line {
            font-size: 12.5pt;
            line-height: 1.4;
        }
        .recipient-city {
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.4;
            margin-top: 3pt;
        }
        .recipient-country {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.6pt;
            text-transform: uppercase;
            margin-top: 4pt;
        }
        .tracking-note {
            margin-top: 12pt;
            font-size: 8pt;
            font-family: 'DejaVu Sans Mono', monospace;
        }
        .footer-meta {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 0.8pt dashed #000000;
            padding-top: 6pt;
            font-size: 7pt;
            line-height: 1.35;
        }
        .footer-strong {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="sheet">
    <table class="top-table">
        <tr>
            <td class="sender-cell">
                <div class="sender-label">Absender</div>
                <div class="sender-box">
                    <div class="sender-brand">{{ $sender['name'] }}</div>
                    <div>{{ $sender['street'] }}</div>
                    <div>{{ $sender['postal_code'] }} {{ $sender['city'] }}</div>
                </div>
            </td>
            <td class="franking-cell">
                @if(!empty($portoCode))
                    <div class="franking-box franking-box-porto">
                        <div class="porto-tag">#PORTO</div>
                        <div class="porto-code">{{ $portoCode }}</div>
                    </div>
                @else
                    <div class="franking-box">
                        <div class="franking-title">Deutsche Post</div>
                        <div class="franking-hint">
                            Briefmarke 1,80 €<br>
                            oder #PORTO-Code
                        </div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="recipient-block">
        <div class="service-badge">Deutsche Post · Großbrief</div>

        @if(!empty($recipient['company']))
            <div class="recipient-line" style="font-weight: bold; margin-bottom: 2pt;">{{ $recipient['company'] }}</div>
        @endif
        <div class="recipient-name">{{ $recipient['name'] }}</div>
        <div class="recipient-line">{{ $recipient['street'] }}</div>
        <div class="recipient-city">{{ $recipient['postal_code'] }} {{ $recipient['city'] }}</div>
        @if(!empty($recipient['country_label']))
            <div class="recipient-country">{{ $recipient['country_label'] }}</div>
        @endif

        @if(!empty($trackingNumber))
            <div class="tracking-note">Sendungs-ID: {{ $trackingNumber }}</div>
        @endif
    </div>

    <div class="footer-meta">
        <div><span class="footer-strong">Auftrag:</span> #{{ $orderNumber }} · <span class="footer-strong">Verpackung:</span> {{ $packagingLabel }}</div>
        <div>Mein-Seelenfunke Manufaktur · 4×6" Großbrief-Adressetikett</div>
    </div>
</div>
</body>
</html>
