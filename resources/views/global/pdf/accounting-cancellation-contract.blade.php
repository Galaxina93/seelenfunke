<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Kündigung - {{ $item->name }}</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            padding: 0;
            box-sizing: border-box;
        }

        /* Faltmarken und Lochmarke (DIN 5008) */
        .fold-mark-1 { position: absolute; left: 0; top: 105mm; width: 5mm; border-bottom: 1px solid #777; }
        .punch-mark  { position: absolute; left: 0; top: 148.5mm; width: 7mm; border-bottom: 1px solid #777; }
        .fold-mark-2 { position: absolute; left: 0; top: 210mm; width: 5mm; border-bottom: 1px solid #777; }

        /* Absenderzeile im Sichtfenster (DIN 5008 Form B: 45mm von oben) */
        .return-address {
            position: absolute;
            left: 25mm;
            top: 45mm;
            width: 85mm;
            font-size: 7.5pt;
            text-decoration: underline;
            color: #444;
            white-space: nowrap;
            overflow: hidden;
        }

        /* Empfängeradresse (DIN 5008 Form B: 50mm - 90mm) */
        .recipient-address {
            position: absolute;
            left: 25mm;
            top: 50mm;
            width: 85mm;
            height: 40mm;
            font-size: 10.5pt;
            line-height: 1.4;
        }

        /* Infoblock rechts (DIN 5008 Form B: ab 120mm von links, 48mm von oben) */
        .info-block {
            position: absolute;
            left: 120mm;
            top: 48mm;
            width: 65mm;
            font-size: 9pt;
            line-height: 1.35;
        }
        .info-block table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-block td {
            padding: 1.5px 0;
            vertical-align: top;
        }
        .info-block .label {
            font-weight: normal;
            color: #666;
            width: 32%;
            white-space: nowrap;
        }
        .info-block .val {
            color: #111;
            word-break: break-word;
        }

        /* Datum unter dem Infoblock (fließt dynamisch mit, kein Overlap möglich!) */
        .info-date {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px dotted #ccc;
            text-align: right;
            font-size: 9.5pt;
            color: #222;
        }

        /* Briefinhalt (Start unterhalb der 1. Falzmarke bei 108mm) */
        .content {
            position: absolute;
            left: 25mm;
            right: 25mm;
            top: 108mm;
        }

        .subject-box {
            margin-bottom: 22px;
        }
        .subject-box h1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0 0 4px 0;
            color: #000;
        }
        .subject-box .ref-num {
            font-size: 10pt;
            font-weight: bold;
            color: #444;
        }

        p {
            margin: 0 0 14px 0;
            text-align: left;
        }

        .signature-block {
            margin-top: 30px;
        }
        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #777;
            width: 65mm;
            padding-top: 5px;
            font-size: 10pt;
            color: #222;
        }

        /* Fußzeile */
        .footer {
            position: absolute;
            bottom: 12mm;
            left: 25mm;
            right: 25mm;
            font-size: 8pt;
            color: #777;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Falz- und Lochmarken -->
        <div class="fold-mark-1"></div>
        <div class="punch-mark"></div>
        <div class="fold-mark-2"></div>

        @php
            // Absenderdaten aus Shop-Settings
            $senderName = shop_setting('owner_proprietor', '');
            $senderStreetAndHouse = shop_setting('owner_street', '');
            $senderZipAndCity = shop_setting('owner_city', '');
            
            $senderLine = trim($senderName . ' • ' . $senderStreetAndHouse . ' • ' . $senderZipAndCity, ' •');
            
            $ownerPhone = shop_setting('owner_phone', '');
            $ownerEmail = shop_setting('owner_email', '');
            $ownerWebsite = shop_setting('owner_website', '');
        @endphp

        <!-- Absenderzeile für Fensterbriefumschlag -->
        <div class="return-address">
            {{ $senderLine }}
        </div>

        <!-- Empfängeradresse -->
        <div class="recipient-address">
            <strong>{{ $item->provider_company ?? $item->name }}</strong><br>
            @if($item->provider_department)
                {{ $item->provider_department }}<br>
            @endif
            @if($item->provider_street)
                {{ $item->provider_street }} {{ $item->provider_house_number }}<br>
            @endif
            @if($item->provider_zip || $item->provider_city)
                {{ $item->provider_zip }} {{ $item->provider_city }}
            @endif
        </div>

        <!-- Informationsblock (rechts) -->
        <div class="info-block">
            <table>
                <tr>
                    <td class="label">Absender:</td>
                    <td class="val">
                        {{ $senderName }}<br>
                        {{ $senderStreetAndHouse }}<br>
                        {{ $senderZipAndCity }}
                    </td>
                </tr>
                @if(!empty($ownerPhone))
                <tr>
                    <td class="label">Telefon:</td>
                    <td class="val">{{ $ownerPhone }}</td>
                </tr>
                @endif
                @if(!empty($ownerEmail))
                <tr>
                    <td class="label">E-Mail:</td>
                    <td class="val" style="word-break: break-all;">{{ $ownerEmail }}</td>
                </tr>
                @endif
                @if($item->contract_number)
                <tr>
                    <td class="label">Vertragsnr.:</td>
                    <td class="val"><strong>{{ $item->contract_number }}</strong></td>
                </tr>
                @endif
                @if($item->contract_end_date)
                <tr>
                    <td class="label">Vertragsende:</td>
                    <td class="val">{{ \Carbon\Carbon::parse($item->contract_end_date)->format('d.m.Y') }}</td>
                </tr>
                @endif
            </table>

            <!-- Datum (integriert im Infoblock - 100% kollisionsfrei) -->
            <div class="info-date">
                @php $cityOnly = preg_replace('/^\d+\s+/', '', $senderZipAndCity); @endphp
                {{ $cityOnly ? trim($cityOnly) . ', den ' : '' }}{{ $date }}
            </div>
        </div>

        <!-- Hauptinhalt -->
        <div class="content">
            <div class="subject-box">
                <h1>Kündigung des Vertrages "{{ $item->name }}"</h1>
                @if($item->contract_number)
                    <div class="ref-num">Vertrags- / Referenznummer: {{ $item->contract_number }}</div>
                @endif
            </div>

            <p>Sehr geehrte Damen und Herren,</p>

            <p>
                hiermit kündige ich den oben genannten Vertrag
                @if($item->contract_number) mit der Vertragsnummer <strong>{{ $item->contract_number }}</strong>@endif
                sowie alle damit verbundenen Zusatzleistungen und Optionen ordentlich und fristgerecht <strong>{{ $cancellationDateText }}</strong>{{ !str_contains($cancellationDateText, 'nächstmöglichen') ? ', hilfsweise zum nächstmöglichen Termin' : '' }}.
            </p>

            <p>
                Mit Wirksamwerden der Kündigung erlischt gleichzeitig die Ihnen erteilte Einzugsermächtigung bzw. das SEPA-Lastschriftmandat zum Einzug fälliger Beträge.
            </p>

            <p>
                Bitte senden Sie mir innerhalb der nächsten 14 Tage eine schriftliche Bestätigung dieser Kündigung unter verbindlicher Angabe des Beendigungszeitpunktes (gerne auch per E-Mail) zu.
            </p>

            <p>
                Von telefonischen oder schriftlichen Angeboten zur Kundenrückgewinnung bitte ich ausdrücklich abzusehen.
            </p>

            <div class="signature-block">
                <p style="margin-bottom: 0;">Vielen Dank und mit freundlichen Grüßen</p>
                <div class="signature-line">
                    {{ $senderName }}
                </div>
            </div>
        </div>

        <!-- Fußzeile -->
        <div class="footer">
            {{ $senderName }} | {{ $senderStreetAndHouse }}, {{ $senderZipAndCity }} 
            @if(!empty($ownerEmail))| E-Mail: {{ $ownerEmail }}@endif
            @if(!empty($ownerWebsite))| Web: {{ $ownerWebsite }}@endif
        </div>
    </div>
</body>
</html>
