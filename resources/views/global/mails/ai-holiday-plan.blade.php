<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $messageSubject }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f0f7f8;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 650px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 26px;
            font-family: serif;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px;
            line-height: 1.8;
            color: #4b5563;
        }
        .agent-badge {
            display: inline-flex;
            align-items: center;
            background-color: #ecfdf5;
            color: #047857;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 6px 14px;
            border-radius: 20px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .message-box {
            background-color: #f9fafb;
            padding: 25px;
            border-left: 4px solid #10b981;
            border-radius: 0 8px 8px 0;
            margin-top: 25px;
            margin-bottom: 25px;
            white-space: pre-wrap;
            font-size: 15px;
        }
        .footer {
            background-color: #f3f4f6;
            padding: 25px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .highlight {
            color: #059669;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>✈️ Dein Exklusiver Reiseplan</h1>
    </div>

    <div class="content">
        <div class="agent-badge">
            Reiseplanung von Agent: <strong>{{ $agentName }}</strong>
        </div>
        
        <p>Hallo,</p>
        <p>Ich habe dir wie gewünscht deinen maßgeschneiderten Urlaubsplan zusammengestellt. Das Dokument enthält alle wichtigen Stationen, Checklisten und Details für deine Reise.</p>

        <div class="message-box">{!! nl2br(e($messageContent)) !!}</div>

        <p>Die generierte <span class="highlight">Reiseplanung als PDF</span> findest du im Anhang dieser E-Mail.</p>
        
        <p>Ich wünsche dir eine wunderbare Reise!<br><br>Herzliche Grüße,<br>Dein KI-Team ✨</p>
    </div>

    <div class="footer">
        Dieser Reiseplan wurde automatisch von Deinem persönlichen KI-Assistenten generiert.<br>
        {{ date('d.m.Y H:i') }} Uhr
    </div>
</div>
</body>
</html>
