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
            max-width: 650px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .header {
            background: #6b7280;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 26px;
            font-family: sans-serif;
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
            background-color: #f3f4f6;
            color: #4b5563;
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
            border-left: 4px solid #6b7280;
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
    </style>
</head>
<body>
<div class="container">
    <div class="header" style="background-color:#18181B;border-bottom:3px solid #C5A059;padding:22px 30px;text-align:left;">
        <div style="font-size:15px;font-weight:800;letter-spacing:0.14em;color:#F5DCA0;">Alina Steinhauer · Mein-Seelenfunke</div>
    </div>

    <div class="content" style="padding:30px;">
        <div class="message-box" style="background:#FFFFFF;border:none;padding:0;margin:0 0 22px 0;color:#27272A;font-size:14px;line-height:1.65;">{!! nl2br(e($messageContent)) !!}</div>

        <p style="margin:22px 0 0 0;font-size:14px;line-height:1.6;color:#27272A;">
            Mit freundlichen Grüßen<br>
            <strong style="color:#18181B;">Alina Steinhauer</strong>
        </p>
    </div>

    <div class="footer" style="background-color:#FAF8F4;padding:18px 30px;text-align:left;font-size:11.5px;line-height:1.6;color:#71717A;border-top:1px solid #EAE4D9;">
        <strong style="color:#18181B;">Mein-Seelenfunke · Inhaberin: Alina Steinhauer</strong> · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
    </div>
</div>
</body>
</html>
