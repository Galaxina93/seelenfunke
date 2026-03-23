<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interner Widerruf Alarm</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6; color: #111827; margin: 0; padding: 30px; }
        .box { background-color: #ffffff; border-top: 5px solid #ef4444; border-radius: 8px; padding: 25px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        h1 { color: #b91c1c; font-size: 20px; font-weight: bold; margin-bottom: 20px; margin-top: 0; }
        .data { margin-bottom: 20px; }
        .data p { margin: 5px 0; font-size: 15px; }
        .data strong { display: inline-block; width: 140px; color: #4b5563; }
        .info { background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; font-size: 13px; color: #1e3a8a; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🚨 Neuer Widerruf eingegangen!</h1>
        <p style="font-size: 14px; color: #6b7280; margin-bottom: 25px;">Ein Kunde hat soeben über das Formular im Shop einen gesetzlichen Widerruf gemeldet.</p>
        
        <div class="data">
            <p><strong>Eingangsdatum:</strong> {{ $revocationData['timestamp'] }}</p>
            <p><strong>Bestellnummer:</strong> {{ $revocationData['order_number'] }}</p>
            <p><strong>Name:</strong> {{ $revocationData['name'] }}</p>
            <p><strong>E-Mail-Adresse:</strong> {{ $revocationData['email'] }}</p>
            @if(!empty($revocationData['items']))
                <p><strong>Zusatz/Artikel:</strong> {{ $revocationData['items'] }}</p>
            @endif
            @if(!empty($revocationData['attachments']))
                <p><strong>Nachweise:</strong> {{ count($revocationData['attachments']) }} Datei(en) angehängt</p>
            @endif
        </div>

        <div class="info">
            <strong>Nächste Schritte:</strong><br><br>
            Das System hat dem Kunden bereits die gesetzlich vorgeschriebene *Eingangsbestätigung* geschickt. 
            Bitte prüfe diese Anfrage nun manuell. Falls der Kunde versucht, eine personalisierte Auftragsanfertigung (z.B. Gravur) zu widerrufen, weise ihn freundlich darauf hin, dass gravierte Produkte nach § 312g Abs. 2 Nr. 1 BGB vom Widerruf ausgeschlossen sind.
        </div>
    </div>
</body>
</html>
