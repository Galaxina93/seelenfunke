<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort Zurücksetzen</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 40px;
        }
        .container {
            background-color: #ffffff;
            max-width: 600px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 8px;
        }
        h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #444;
        }
        a {
            color: #ffffff;
            background-color: #3490dc;
            display: inline-block;
            padding: 12px 24px;
            font-weight: bold;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        p {
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Passwort Zurücksetzen</h1>
    <p>Hallo,</p>
    <p>Wir haben eine Anfrage zum Zurücksetzen Ihres Passworts erhalten. Bitte klicken Sie auf den folgenden Link, um Ihr Passwort zurückzusetzen:</p>
    <p><a href="{{ $emailData['reset_link'] }}">Passwort zurücksetzen</a></p>
    <p>Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie bitte diese E-Mail.</p>
    <br>
    <strong>Dieser Link ist 60 Minuten gültig.</strong>
    <br>
    <p>Vielen Dank,</p>
    <p>Ihr Team</p>
</div>
</body>
</html>
