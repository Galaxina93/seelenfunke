<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Muster-Widerrufsformular - {{ $companyName }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm 20mm 15mm 20mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #1f2937;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        /* Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #C5A059;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .doc-title {
            font-size: 19pt;
            font-weight: bold;
            color: #111827;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
        }

        .doc-subtitle {
            font-size: 8.5pt;
            color: #6b7280;
            font-style: italic;
            margin: 0;
            line-height: 1.3;
        }

        .logo-cell {
            text-align: right;
            vertical-align: middle;
        }

        .logo {
            max-height: 48px;
            max-width: 180px;
        }

        .brand-text {
            font-size: 16pt;
            font-weight: bold;
            color: #111827;
        }

        .brand-sparkle {
            color: #C5A059;
        }

        /* Empfänger-Box */
        .recipient-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }

        .recipient-label {
            font-size: 8pt;
            font-weight: bold;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }

        .recipient-name {
            font-size: 11pt;
            font-weight: bold;
            color: #111827;
            margin-bottom: 2px;
        }

        .recipient-details {
            font-size: 9.5pt;
            color: #374151;
            line-height: 1.4;
        }

        /* Einleitungstext */
        .declaration-text {
            font-size: 9.5pt;
            color: #1f2937;
            margin-bottom: 18px;
            line-height: 1.45;
        }

        /* Ausfüllbereich */
        .form-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .form-section td {
            padding: 7px 0;
            vertical-align: bottom;
            border: none;
        }

        .field-label {
            font-size: 9pt;
            font-weight: bold;
            color: #374151;
            white-space: nowrap;
            padding-right: 8px;
            width: 1%;
        }

        .field-line {
            border-bottom: 1px dotted #9ca3af;
            height: 16px;
        }

        .field-box {
            border: 1px dashed #d1d5db;
            background-color: #fafaf9;
            border-radius: 4px;
            min-height: 52px;
            padding: 6px 10px;
            font-size: 8.5pt;
            color: #9ca3af;
            font-style: italic;
            margin-top: 4px;
            margin-bottom: 12px;
        }

        /* Signatur & Datum */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            margin-bottom: 16px;
        }

        .signature-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .signature-col-left {
            width: 45%;
        }

        .signature-col-space {
            width: 10%;
        }

        .signature-col-right {
            width: 45%;
        }

        .signature-line {
            border-bottom: 1px solid #4b5563;
            height: 36px;
            margin-bottom: 5px;
        }

        .signature-caption {
            font-size: 8pt;
            color: #6b7280;
        }

        /* Fußzeile */
        .footnote {
            font-size: 7.5pt;
            color: #9ca3af;
            margin-top: 15px;
            margin-bottom: 20px;
        }

        .footer-bar {
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            font-size: 7.5pt;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- Kopfbereich mit Titel und Logo / Shop-Name --}}
    <table class="header-table" role="presentation">
        <tr>
            <td>
                <h1 class="doc-title">Muster-Widerrufsformular</h1>
                <p class="doc-subtitle">
                    (Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)
                </p>
            </td>
            <td class="logo-cell">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="{{ $companyName }}" class="logo">
                @elseif(!empty($logoPath))
                    <img src="{{ $logoPath }}" alt="{{ $companyName }}" class="logo">
                @else
                    <span class="brand-text">{{ $companyName }}<span class="brand-sparkle">.</span></span>
                @endif
            </td>
        </tr>
    </table>

    {{-- Empfänger-Adresse (Anbieter) --}}
    <div class="recipient-card">
        <div class="recipient-label">An:</div>
        <div class="recipient-name">{{ $companyName }}</div>
        <div class="recipient-details">
            @if(!empty($ownerProprietor))
                Inhaberin: {{ $ownerProprietor }}<br>
            @endif
            {{ $street }}<br>
            {{ $zip }} {{ $city }}@if(!empty($country)), {{ $country }}@endif<br>
            E-Mail: {{ $email }}@if(!empty($phone)) &nbsp;•&nbsp; Tel.: {{ $phone }}@endif
            @if(!empty($website)) &nbsp;•&nbsp; Web: {{ $website }}@endif
        </div>
    </div>

    {{-- Gesetzliche Erklärung --}}
    <p class="declaration-text">
        Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*) / die Erbringung der folgenden Dienstleistung (*):
    </p>

    {{-- Artikel / Waren Bezeichnung --}}
    <div class="field-box">
        Genaue Bezeichnung der Ware(n) / Bestell- oder Rechnungsnummer:
    </div>

    {{-- Formular-Felder --}}
    <table class="form-section" role="presentation">
        <tr>
            <td class="field-label">Bestellt am (*):</td>
            <td class="field-line" style="width: 35%;"></td>
            <td class="field-label" style="padding-left: 20px;">Erhalten am (*):</td>
            <td class="field-line"></td>
        </tr>
    </table>

    <table class="form-section" role="presentation">
        <tr>
            <td class="field-label">Name des/der Verbraucher(s):</td>
            <td class="field-line"></td>
        </tr>
    </table>

    <table class="form-section" role="presentation">
        <tr>
            <td class="field-label">Anschrift des/der Verbraucher(s):</td>
            <td class="field-line"></td>
        </tr>
    </table>

    <table class="form-section" role="presentation">
        <tr>
            <td class="field-label" style="color: transparent;">Zusatzzeile Anschrift:</td>
            <td class="field-line"></td>
        </tr>
    </table>

    {{-- Unterschrift & Datum --}}
    <table class="signature-table" role="presentation">
        <tr>
            <td class="signature-col-left">
                <div class="signature-line"></div>
                <div class="signature-caption">Datum</div>
            </td>
            <td class="signature-col-space"></td>
            <td class="signature-col-right">
                <div class="signature-line"></div>
                <div class="signature-caption">Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)</div>
            </td>
        </tr>
    </table>

    {{-- Fußnote --}}
    <div class="footnote">
        (*) Unzutreffendes streichen.
    </div>

    {{-- Dezente Fußzeile --}}
    <div class="footer-bar">
        {{ $companyName }} &nbsp;•&nbsp; {{ $street }} &nbsp;•&nbsp; {{ $zip }} {{ $city }} &nbsp;•&nbsp; {{ $email }}
    </div>

</body>
</html>
