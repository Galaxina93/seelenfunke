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
        .fold-mark-1 { position: absolute; left: 0; top: 105mm; width: 5mm; border-bottom: 1px solid #000; }
        .punch-mark { position: absolute; left: 0; top: 148.5mm; width: 7mm; border-bottom: 1px solid #000; }
        .fold-mark-2 { position: absolute; left: 0; top: 210mm; width: 5mm; border-bottom: 1px solid #000; }

        /* Absenderzeile im Sichtfenster (DIN 5008) */
        .return-address {
            position: absolute;
            left: 25mm;
            top: 45mm;
            font-size: 8pt;
            text-decoration: underline;
            color: #333;
        }

        /* Empfängeradresse (DIN 5008) */
        .recipient-address {
            position: absolute;
            left: 25mm;
            top: 50mm;
            width: 85mm;
            height: 40mm;
        }

        /* Infoblock rechts (DIN 5008 Form B) */
        .info-block {
            position: absolute;
            left: 125mm;
            top: 50mm;
            width: 75mm;
            font-size: 10pt;
        }
        .info-block table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-block td {
            padding: 2px 0;
            vertical-align: top;
        }
        .info-block .label {
            font-weight: normal;
            color: #555;
            width: 40%;
        }

        /* Datum (rechtsbündig, unter dem Infoblock) */
        .date-right {
            position: absolute;
            right: 25mm;
            top: 90mm;
        }

        /* Briefinhalt */
        .content {
            position: absolute;
            left: 25mm;
            right: 25mm;
            top: 105mm;
        }

        h1 {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 20px;
        }

        p {
            margin-bottom: 15px;
            text-align: justify;
        }

        .signature-block {
            margin-top: 40px;
        }

        /* Fußzeile */
        .footer {
            position: absolute;
            bottom: 15mm;
            left: 25mm;
            right: 25mm;
            font-size: 8pt;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 5px;
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
            // Absender formatieren (Neutral auf den Inhaber/in laufen lassen)
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
                    <td>
                        {{ $senderName }}<br>
                        {{ $senderStreetAndHouse }}<br>
                        {{ $senderZipAndCity }}
                    </td>
                </tr>
                @if(!empty($ownerPhone))
                <tr>
                    <td class="label">Telefon:</td>
                    <td>{{ $ownerPhone }}</td>
                </tr>
                @endif
                @if(!empty($ownerEmail))
                <tr>
                    <td class="label">E-Mail:</td>
                    <td>{{ $ownerEmail }}</td>
                </tr>
                @endif
                @if($item->contract_number)
                <tr>
                    <td class="label">Vertragsnr.:</td>
                    <td><strong>{{ $item->contract_number }}</strong></td>
                </tr>
                @endif
                @if($item->contract_end_date)
                <tr>
                    <td class="label">Vertragsende:</td>
                    <td>{{ \Carbon\Carbon::parse($item->contract_end_date)->format('d.m.Y') }}</td>
                </tr>
                @endif
            </table>
        </div>

        <!-- Datum -->
        <div class="date-right">
            @php $cityOnly = preg_replace('/^\d+\s+/', '', $senderZipAndCity); @endphp
            {{ $cityOnly ? trim($cityOnly) . ', den ' : '' }}{{ $date }}
        </div>

        <!-- Hauptinhalt -->
        <div class="content">
            <h1>Kündigung des Vertrages / Abonnements "{{ $item->name }}"</h1>

            <p>Sehr geehrte Damen und Herren,</p>

            <p>
                hiermit kündige ich den oben genannten Vertrag (ggf. Vertragsnummer: 
                @if($item->contract_number) <strong>{{ $item->contract_number }}</strong> @else _________________ @endif)
                sowie alle damit verbundenen Zusatzleistungen ordentlich und fristgerecht <strong>{{ $cancellationDateText }}</strong>.
            </p>

            <p>
                Mit Wirksamwerden der Kündigung erlischt gleichzeitig die Ihnen erteilte Einzugsermächtigung zum Einzug der fälligen Beträge per Lastschrift.
            </p>

            <p>
                Bitte senden Sie mir innerhalb der nächsten 14 Tage eine schriftliche Bestätigung dieser Kündigung (gerne per E-Mail) unter Angabe des Beendigungszeitpunktes zu.
            </p>

            <p>
                Sollten Sie mich bezüglich Rückwerbeangeboten kontaktieren wollen, bitte ich hiervon abzusehen.
            </p>

            <p>Vielen Dank und mit freundlichen Grüßen</p>

            <div class="signature-block">
                <br><br><br>
                _________________________________________<br>
                {{ $senderName }}
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
