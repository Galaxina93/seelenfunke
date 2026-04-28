<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neue Kontaktanfrage</title>
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
        }
        .field-row {
            margin-bottom: 15px;
        }
        .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            margin-bottom: 4px;
            display: block;
        }
        .value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        .message-box {
            background-color: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #C5A059;
            border-radius: 4px;
            margin-top: 20px;
            font-style: italic;
            color: #555;
            line-height: 1.6;
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
        <h1>Neue Nachricht von der Website</h1>
    </div>

    <div class="content">
        <div class="field-row">
            <span class="label">Absender</span>
            <span class="value">{{ $emailData['first_name'] }} {{ $emailData['last_name'] }}</span>
        </div>

        <div class="field-row">
            <span class="label">E-Mail Adresse</span>
            <span class="value"><a href="mailto:{{ $emailData['email'] }}" style="color: #C5A059; text-decoration: none;">{{ $emailData['email'] }}</a></span>
        </div>

        @if(!empty($emailData['phone']))
            <div class="field-row">
                <span class="label">Telefonnummer</span>
                <span class="value">{{ $emailData['phone'] }}</span>
            </div>
        @endif

        <span class="label" style="margin-top: 30px;">Nachricht</span>
        <div class="message-box">
            {!! nl2br(e($emailData['message'])) !!}
        </div>
    </div>

    <div class="footer">
        Gesendet über das Kontaktformular von {{ env('APP_NAME') }}<br>
        {{ date('d.m.Y H:i') }} Uhr
    </div>
</div>
</body>
</html>
