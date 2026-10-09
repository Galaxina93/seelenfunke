<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lieferschein {{ $deliveryNoteNumber }} - {{ $ownerName ?? 'Mein Seelenfunke' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 22mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            margin: 0;
            padding: 0;
            line-height: 1.45;
            background-color: #ffffff;
        }

        /* Typography */
        h1, h2, h3 { color: #111827; margin: 0 0 8px 0; }
        p { margin: 0 0 8px 0; }

        /* Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            border-bottom: 2px solid #C5A059;
            padding-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }
        .logo {
            max-height: 52px;
            max-width: 220px;
        }
        .doc-title {
            font-size: 22px;
            font-weight: 900;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-align: right;
            margin-bottom: 3px;
        }
        .badge {
            display: inline-block;
            background-color: #f5eedc;
            color: #8c6d23;
            border: 1px solid #d4af37;
            padding: 2px 7px;
            font-size: 8.5px;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Address & Meta Section */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .info-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }
        .sender-line {
            font-size: 8px;
            color: #8c6d23;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .recipient-box {
            font-size: 11px;
            line-height: 1.5;
            color: #111827;
            min-height: 85px;
        }
        .recipient-name {
            font-weight: bold;
            font-size: 12px;
            color: #111827;
        }
        .meta-card {
            background-color: #fafaf9;
            border: 1px solid #e7e5e4;
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 9.5px;
        }
        .meta-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-card td {
            padding: 2px 0;
            border: none;
        }
        .meta-label {
            color: #6b7280;
            font-weight: 500;
            width: 45%;
        }
        .meta-value {
            color: #111827;
            font-weight: bold;
            text-align: right;
        }

        /* Intro message */
        .intro-block {
            margin-bottom: 15px;
            font-size: 11px;
            color: #374151;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #111827;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 7px 8px;
            border: 1px solid #111827;
        }
        .items-table td {
            border: 1px solid #e5e7eb;
            padding: 7px 8px;
            font-size: 10px;
            vertical-align: top;
        }
        .items-table tr.even {
            background-color: #fafaf9;
        }
        .item-name {
            font-weight: bold;
            color: #111827;
        }
        .item-notes {
            font-size: 8.5px;
            color: #6b7280;
            margin-top: 3px;
        }
        .col-pos { width: 6%; text-align: center; }
        .col-qty { width: 10%; text-align: center; font-weight: bold; }
        .col-unit { width: 10%; text-align: center; color: #4b5563; }
        .col-desc { width: 48%; text-align: left; }
        .col-note { width: 26%; text-align: left; }

        /* Summary / Total Items Bar */
        .summary-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #fdfbf7;
            border: 1px solid #e7e5e4;
            border-left: 3px solid #C5A059;
        }
        .summary-bar td {
            padding: 6px 10px;
            font-size: 9.5px;
            color: #4b5563;
            border: none;
        }

        /* Notes Box */
        .notes-box {
            background-color: #fdfbf7;
            border: 1px solid #f3ebd8;
            border-left: 3px solid #C5A059;
            border-radius: 3px;
            padding: 10px 12px;
            margin-bottom: 25px;
            font-size: 9.5px;
            color: #374151;
        }
        .notes-title {
            font-weight: bold;
            color: #8c6d23;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        /* Receipt / Signature block */
        .receipt-section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .receipt-section td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .receipt-box {
            border: 1px dashed #cbd5e1;
            background-color: #ffffff;
            border-radius: 4px;
            padding: 12px;
        }
        .receipt-heading {
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 4px;
        }
        .receipt-text {
            font-size: 8.5px;
            color: #64748b;
            margin-bottom: 25px;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            font-size: 8.5px;
            color: #475569;
        }

        /* Footer */
        #footer {
            position: fixed;
            bottom: -16mm;
            left: 0px;
            right: 0px;
            height: 12mm;
            font-size: 8px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            border: none;
            padding: 0;
            vertical-align: top;
            line-height: 1.4;
        }
        .page-number:after {
            content: "Seite " counter(page);
            font-weight: bold;
            color: #111827;
        }
    </style>
</head>
<body>

@php
    $ownerName   = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
    $proprietor  = shop_setting('owner_proprietor', 'Alina Steinhauer');
    $ownerStreet = shop_setting('company_street', shop_setting('owner_street', 'Carl-Goerdeler-Ring')) . ' ' . shop_setting('company_street_number', '26');
    $ownerCity   = shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
    $ownerEmail  = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
    $ownerWeb    = shop_setting('owner_website', 'www.mein-seelenfunke.de');
    $ownerPhone  = shop_setting('owner_phone', '');
    $logoPath    = public_path('shop/projekt/logo/mein-seelenfunke-logo.svg');

    // Sender line
    $senderLine = !empty($senderInfo) ? $senderInfo : "$ownerName · $ownerStreet · $ownerCity";
@endphp

<div id="footer">
    <table class="footer-table">
        <tr>
            <td style="width: 38%; text-align: left;">
                <strong>{{ $ownerName }}</strong> | Inh. {{ $proprietor }}<br>
                {{ $ownerStreet }} · {{ $ownerCity }}
            </td>
            <td style="width: 32%; text-align: center;">
                E-Mail: {{ $ownerEmail }}<br>
                Web: {{ str_replace(['http://', 'https://'], '', $ownerWeb) }}
            </td>
            <td style="width: 30%; text-align: right;">
                <span class="page-number"></span><br>
                Generiert durch KI: {{ $agentName ?? 'Funkira' }}
            </td>
        </tr>
    </table>
</div>

<!-- Header -->
<table class="header-table">
    <tr>
        <td style="width: 50%;">
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="{{ $ownerName }}" class="logo">
            @else
                <div style="font-size: 20px; font-weight: 900; color: #C5A059;">{{ $ownerName }}</div>
            @endif
        </td>
        <td style="width: 50%; text-align: right;">
            <div class="doc-title">Lieferschein</div>
            <div class="badge">Warenbegleitschein</div>
        </td>
    </tr>
</table>

<!-- Address & Meta Box -->
<table class="info-table">
    <tr>
        <td style="width: 55%; padding-right: 20px;">
            <div class="sender-line">{{ $senderLine }}</div>
            <div class="recipient-box">
                <div class="recipient-name">{{ $recipientName }}</div>
                @if(!empty($recipientAddress))
                    <div style="margin-top: 4px;">{!! nl2br(e($recipientAddress)) !!}</div>
                @endif
            </div>
        </td>
        <td style="width: 45%;">
            <div class="meta-card">
                <table>
                    <tr>
                        <td class="meta-label">Lieferschein-Nr.:</td>
                        <td class="meta-value">{{ $deliveryNoteNumber }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Lieferdatum:</td>
                        <td class="meta-value">{{ $deliveryDate }}</td>
                    </tr>
                    @if(!empty($orderReference))
                    <tr>
                        <td class="meta-label">Ihre Referenz:</td>
                        <td class="meta-value">{{ $orderReference }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="meta-label">Versandart:</td>
                        <td class="meta-value">{{ $shippingMethod ?? 'Paketversand / Standard' }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- Intro text -->
<div class="intro-block">
    Sehr geehrte Damen und Herren,<br>
    mit dieser Lieferung erhalten Sie nachfolgend aufgeführte Positionen:
</div>

<!-- Items Table -->
<table class="items-table">
    <thead>
        <tr>
            <th class="col-pos">Pos.</th>
            <th class="col-qty">Menge</th>
            <th class="col-unit">Einheit</th>
            <th class="col-desc">Artikelbezeichnung / Beschreibung</th>
            <th class="col-note">Hinweis / Details</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalQuantity = 0;
        @endphp
        @forelse($items as $index => $item)
            @php
                $qty = is_numeric($item['quantity'] ?? null) ? (float)$item['quantity'] : 1;
                $totalQuantity += $qty;
                $pos = $item['pos'] ?? ($index + 1);
            @endphp
            <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                <td class="col-pos">{{ $pos }}</td>
                <td class="col-qty">{{ $item['quantity'] ?? 1 }}</td>
                <td class="col-unit">{{ $item['unit'] ?? 'Stk.' }}</td>
                <td class="col-desc">
                    <div class="item-name">{{ $item['name'] ?? 'Artikel' }}</div>
                    @if(!empty($item['description']) && $item['description'] !== ($item['name'] ?? ''))
                        <div class="item-notes">{{ $item['description'] }}</div>
                    @endif
                </td>
                <td class="col-note">
                    {{ $item['notes'] ?? '-' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align: center; padding: 15px; color: #9ca3af;">Keine Positionen aufgeführt.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<!-- Total Items Summary -->
<table class="summary-bar">
    <tr>
        <td style="width: 50%;">
            <strong>Positionen:</strong> {{ count($items) }}
        </td>
        <td style="width: 50%; text-align: right;">
            <strong>Gesamtartikelmenge:</strong> {{ $totalQuantity }}
        </td>
    </tr>
</table>

<!-- Notes / Remarks (Optional) -->
@if(!empty($notes))
<div class="notes-box">
    <div class="notes-title">Hinweise zur Sendung:</div>
    <div>{!! nl2br(e($notes)) !!}</div>
</div>
@endif

<!-- Signature / Receipt confirmation -->
<table class="receipt-section">
    <tr>
        <td style="width: 100%;">
            <div class="receipt-box">
                <div class="receipt-heading">Empfangsbestätigung</div>
                <div class="receipt-text">Die vorstehend aufgeführten Waren wurden vollständig und unversehrt übernommen:</div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 45%; vertical-align: bottom;">
                            <div class="sig-line">Datum, Ort</div>
                        </td>
                        <td style="width: 10%;"></td>
                        <td style="width: 45%; vertical-align: bottom;">
                            <div class="sig-line">Unterschrift & Stempel des Empfängers</div>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
