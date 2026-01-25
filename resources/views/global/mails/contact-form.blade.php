<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Kontaktanfrage - Webseite</title>

        <style>

        </style>
    </head>
    <body>
        <div style="height: 100%;">
            <div class="content">

                Kontaktanfrage von <b>{{ $emailData['first_name'] }} {{ $emailData['last_name'] }}</b>

                <br><br>

                <span><b>E-Mail:</b> {{ $emailData['email'] }}</span><br>
                <span><b>Telefon:</b> {{ $emailData['phone'] }}</span><br>

                <br><br>

                <span>
                    <b>Nachricht:</b> <br>
                    {{ $emailData['message'] }}
                </span>

                <br><br>

                <span>Liebe Grüße,</span><br>
                <span>Webseite - {{ env('APP_NAME') }}</span>

                <br><br>


            </div>
        </div>
    </body>
</html>
