<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .message-body {
            margin-bottom: 30px;
        }
        .signature {
            margin-top: 40px;
            color: #666666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="message-body">
            {!! $bodyHtml !!}
        </div>
        
        @if($signatureHtml)
            <div class="signature">
                {!! $signatureHtml !!}
            </div>
        @endif
    </div>
</body>
</html>
