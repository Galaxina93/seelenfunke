<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $messageSubject }}</title>
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
        .agent-badge {
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
        .message-box {
            background-color: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #C5A059;
            border-radius: 4px;
            margin-top: 20px;
            margin-bottom: 20px;
            white-space: pre-wrap; /* Wichtig für Zeilenumbrüche aus Text */
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
        <h1>Nachricht von {{ env('APP_NAME', 'Seelenfunke') }}</h1>
    </div>

    <div class="content">
        <div class="agent-badge">
            Generiert von Agent: <strong>{{ $agentName }}</strong>
        </div>
        
        <p>Hallo,</p>
        <p>Du hast eine neue Nachricht aus deinem Arbeitsbereich erhalten:</p>

        <div class="message-box">{!! nl2br(e($messageContent)) !!}</div>

        <p>Herzliche Grüße,<br>{{ $agentName }} ✨</p>
    </div>

    <div class="footer">
        Diese E-Mail wurde automatisch von Deinem persönlichen KI-Assistenten generiert.<br>
        {{ date('d.m.Y H:i') }} Uhr
    </div>
</div>
</body>
</html>
