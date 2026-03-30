<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Antwort auf Deine Anfrage</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-w-xl mx-auto p-4">
    <h2 style="color: #C5A059;">Hallo {{ $emailData['first_name'] }} {{ $emailData['last_name'] }},</h2>
    
    <div style="white-space: pre-wrap; margin-bottom: 2rem;">{{ $emailData['replyMessage'] }}</div>
    
    <p style="color: #666; font-size: 0.9em; border-top: 1px solid #eee; padding-top: 1rem;">
        Ticket ID: <strong>{{ $emailData['ticket_number'] }}</strong><br>
        Dies ist eine Antwort auf Deine Kontaktaufnahme. Bitte belasse die Ticketnummer im Betreff, falls Du antwortest.
    </p>
    
    <p style="color: #999; font-size: 0.8em; margin-top: 2rem;">
        Liebe Grüße,<br>
        Dein Mein-Seelenfunke Team
    </p>
</body>
</html>
