<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neuer Termin: {{ $event->title }}</title>
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
        .badge {
            display: inline-block;
            background-color: #f3f4f6;
            color: #4b5563;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 10px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
        }
        .event-details {
            background-color: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #C5A059;
            border-radius: 4px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .event-details strong {
            display: inline-block;
            width: 120px;
            color: #555;
        }
        .button {
            display: inline-block;
            background-color: #C5A059;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 15px;
            text-align: center;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #888;
            border-top: 1px solid #eee;
        }
        .hint {
            font-size: 13px;
            color: #666;
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Neuer Termin: {{ $event->title }}</h1>
    </div>

    <div class="content">
        <div class="badge">
            Automatische Termin-Synchronisation
        </div>
        
        <p>Hallo,</p>
        <p>Es wurde ein neuer Termin im System erstellt. Hier sind die Details:</p>

        <div class="event-details">
            <p><strong>Titel:</strong> {{ $event->title }}</p>
            <p><strong>Beginn:</strong> {{ $event->start_date ? $event->start_date->format('d.m.Y H:i') : 'Keine Angabe' }}</p>
            <p><strong>Ende:</strong> {{ $event->end_date ? $event->end_date->format('d.m.Y H:i') : 'Keine Angabe' }}</p>
            @if($event->is_all_day)
                <p><strong>Dauer:</strong> Ganztägig</p>
            @endif
            @if($event->description)
                <p style="margin-top: 15px;"><strong>Beschreibung:</strong><br>{!! nl2br(e($event->description)) !!}</p>
            @endif
        </div>

        <div style="text-align: center;">
            <a href="{{ $googleUrl }}" target="_blank" class="button">In Google Kalender eintragen</a>
        </div>

        <p class="hint">
            <strong>Tipp:</strong> Nutzt du Outlook, Apple Kalender oder ein anderes Programm? 
            Öffne einfach die angehängte <strong>termin.ics</strong> Datei, um den Termin mit einem Klick zu deinem Kalender hinzuzufügen.
        </p>

        <p>Herzliche Grüße,<br>Dein System-Team ✨</p>
    </div>

    <div class="footer">
        Diese E-Mail wurde automatisch vom Seelenfunke System generiert.<br>
        {{ date('d.m.Y H:i') }} Uhr
    </div>
</div>
</body>
</html>
