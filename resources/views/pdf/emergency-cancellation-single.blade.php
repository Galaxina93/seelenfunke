<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sonderkündigung - {{ $item->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 15mm 20mm 15mm; }
        body { font-family: sans-serif; font-size: 11pt; color: #333333; margin: 0; padding: 20px; line-height: 1.5; background-color: #ffffff; }
    </style>
</head>
<body>

@php
    $senderName = $settings['owner_proprietor'] ?? 'Keine Angabe';
    $senderStreetAndHouse = $settings['company_street'] ?? '';
    $senderZipAndCity = ($settings['company_zip'] ?? '') . ' ' . ($settings['company_city'] ?? '');
@endphp

<!-- Empfänger -->
<div style="margin-top: 40px; margin-bottom: 40px;">
    <strong>{{ $item->provider_company ?? $item->name }}</strong><br>
    @if($item->provider_street)
        {{ $item->provider_street }} {{ $item->provider_house_number }}<br>
    @endif
    @if($item->provider_zip || $item->provider_city)
        {{ $item->provider_zip }} {{ $item->provider_city }}
    @endif
</div>

<!-- Info-Block rechtsbündig -->
<div style="text-align: right; margin-bottom: 30px;">
    <strong>Absender:</strong><br>
    {{ $senderName }}<br>
    {{ $senderStreetAndHouse }}<br>
    {{ $senderZipAndCity }}<br><br>
    @if($item->contract_number)
        <strong>Vertragsnummer:</strong> {{ $item->contract_number }}<br>
    @endif
    Datum: {{ $date }}
</div>

<h1 style="color: #000; font-size: 14pt; font-weight: bold; margin-bottom: 20px;">Sonderkündigung im Todesfall - Vertrag: "{{ $item->name }}"</h1>

<p>Sehr geehrte Damen und Herren,</p>

<p>
    hiermit kündige ich den oben genannten Vertrag (ggf. Vertragsnummer: 
    @if($item->contract_number) <strong>{{ $item->contract_number }}</strong> @else _________________ @endif)
    sowie alle damit verbundenen Zusatzleistungen <strong>außerordentlich aufgrund des Versterbens der Vertragsinhaberin ({{ $senderName }})</strong> zum nächstmöglichen Zeitpunkt.
</p>

<p>
    Die Sterbeurkunde liegt diesem Schreiben in Kopie bei.
</p>

<p>
    Mit Wirksamwerden der Kündigung erlischt gleichzeitig eine etwaig erteilte Einzugsermächtigung zum Einzug der fälligen Beträge per Lastschrift.
</p>

<p>
    Bitte senden Sie mir innerhalb der nächsten 14 Tage eine schriftliche Bestätigung dieser Kündigung unter Angabe des Beendigungszeitpunktes zu.
</p>

<p>Vielen Dank für Ihr Verständnis in dieser schweren Zeit.</p>
<p>Mit freundlichen Grüßen</p>

<div style="margin-top: 50px;">
    _________________________________________<br>
    Unterschrift ({{ $settings['emergency_contact_family'] ?? 'Jan Steinhauer, Kerstin Steinhauer, Tim Steinhauer' }})<br>
    <span style="font-size: 9px; color: #666;">in Vertretung für die Verstorbene / den Nachlass</span>
</div>

</body>
</html>
