<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lieferschein {{ $deliveryNoteNumber }}</title>
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
            border-bottom: 2px solid #374151;
            padding-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .doc-title {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 3px;
        }
        .doc-subtitle {
            font-size: 9px;
            color: #6b7280;
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
            font-size: 8.5px;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
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
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
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
            background-color: #374151;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 7px 8px;
            border: 1px solid #374151;
        }
        .items-table td {
            border: 1px solid #e5e7eb;
            padding: 7px 8px;
            font-size: 10px;
            vertical-align: top;
        }
        .items-table tr.even {
            background-color: #f9fafb;
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
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-left: 3px solid #6b7280;
        }
        .summary-bar td {
            padding: 6px 10px;
            font-size: 9.5px;
            color: #4b5563;
            border: none;
        }

        /* Notes Box */
        .notes-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-left: 3px solid #6b7280;
            border-radius: 3px;
            padding: 10px 12px;
            margin-bottom: 25px;
            font-size: 9.5px;
            color: #374151;
        }
        .notes-title {
            font-weight: bold;
            color: #1f2937;
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

<div id="footer">
    <table class="footer-table">
        <tr>
            <td style="width: 40%; text-align: left;">
                Lieferschein {{ $deliveryNoteNumber }}
            </td>
            <td style="width: 30%; text-align: center;">
                <span class="page-number"></span>
            </td>
            <td style="width: 30%; text-align: right;">
                Lieferdatum: {{ $deliveryDate }}
            </td>
        </tr>
    </table>
</div>

<!-- Header -->
<table class="header-table">
    <tr>
        <td style="width: 60%;">
            <div class="doc-title">Lieferschein</div>
            <div class="doc-subtitle">Warenbegleitschein / Auslieferung</div>
        </td>
        <td style="width: 40%; text-align: right; font-size: 9.5px; color: #6b7280;">
            Datum: <strong>{{ $deliveryDate }}</strong>
        </td>
    </tr>
</table>

<!-- Address & Meta Box -->
<table class="info-table">
    <tr>
        <td style="width: 55%; padding-right: 20px;">
            @if(!empty($senderInfo))
                <div class="sender-line">{{ $senderInfo }}</div>
            @endif
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
                        <td class="meta-label">Referenz / Auftrag:</td>
                        <td class="meta-value">{{ $orderReference }}</td>
                    </tr>
                    @endif
                    @if(!empty($shippingMethod))
                    <tr>
                        <td class="meta-label">Versandart:</td>
                        <td class="meta-value">{{ $shippingMethod }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- Intro text -->
<div class="intro-block">
    Mit dieser Sendung erhalten Sie folgende Positionen:
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
                            <div class="sig-line">Unterschrift des Empfängers</div>
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
