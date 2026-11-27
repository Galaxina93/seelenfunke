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
            border-bottom: 3px solid #6b7280;
        }
        .header h1 {
            color: #6b7280;
            margin: 0;
            font-size: 22px;
            font-family: sans-serif;
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
            border-left: 4px solid #6b7280;
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
    <div class="header" style="background-color:#18181B;border-bottom:3px solid #C5A059;padding:22px 30px;text-align:left;">
        <div style="font-size:15px;font-weight:800;letter-spacing:0.14em;color:#F5DCA0;">Alina Steinhauer</div>
    </div>

    <div class="content" style="padding:30px;">
        <div class="message-box" style="background:#FFFFFF;border:none;padding:0;margin:0 0 22px 0;color:#27272A;font-size:14px;line-height:1.65;">{!! nl2br(e($messageContent)) !!}</div>

        <p style="margin:22px 0 0 0;font-size:14px;line-height:1.6;color:#27272A;">
            Mit freundlichen Grüßen<br>
            <strong style="color:#18181B;">Alina Steinhauer</strong>
        </p>
    </div>

    <div class="footer" style="background-color:#FAF8F4;padding:18px 30px;text-align:left;font-size:11.5px;line-height:1.6;color:#71717A;border-top:1px solid #EAE4D9;">
        <strong style="color:#18181B;">Alina Steinhauer</strong> · Carl-Goerdeler-Ring 26 · 38518 Gifhorn<br>
        Tel.: +49 1590 1966864
    </div>
</div>
</body>
</html>
