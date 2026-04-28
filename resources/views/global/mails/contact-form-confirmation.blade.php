<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eingangsbestätigung Deiner Anfrage</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #ffffff;
            padding: 30px 40px;
            text-align: center;
            border-bottom: 3px solid #C5A059;
        }
        .header h1 {
            color: #C5A059;
            margin: 0;
            font-size: 22px;
            font-family: serif;
        }
        .content {
            padding: 40px;
            line-height: 1.6;
        }
        .ticket-box {
            background-color: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #C5A059;
            border-radius: 4px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .ticket-box .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            display: block;
            margin-bottom: 5px;
        }
        .ticket-number {
            font-size: 20px;
            color: #333;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Bestätigung Deiner Anfrage</h1>
    </div>

    <div class="content">
        <p>Hallo {{ $emailData['first_name'] }} {{ $emailData['last_name'] }},</p>
        
        <p>vielen Dank für Deine Nachricht! Hiermit bestätigen wir den Eingang Deiner Anfrage bei {{ env('APP_NAME', 'Mein Seelenfunke') }}. Unser Team wird sich Dein Anliegen schnellstmöglich ansehen und sich bei Dir melden.</p>

        <div class="ticket-box">
            <span class="label">Deine Ticket-Nummer</span>
            <span class="ticket-number">{{ $emailData['ticket_number'] }}</span>
        </div>

        <p>Bitte bewahre diese Ticket-Nummer für spätere Rückfragen auf. Du wirst auf genau diese E-Mail eine Antwort von unserem Support-Team erhalten.</p>
        
        <p>Herzliche Grüße,<br>Dein {{ env('APP_NAME', 'Seelenfunke') }} Team ✨</p>
    </div>

    <div class="footer">
        Diese E-Mail wurde automatisch generiert.<br>
        {{ date('d.m.Y H:i') }} Uhr
    </div>
</div>
</body>
</html>
