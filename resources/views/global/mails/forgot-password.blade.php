{{-- HTML TREE (Öffnet HTML, Head und Body) --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Passwort zurücksetzen'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- EINLEITUNG --}}
    <div class="content-body" style="font-family: sans-serif; color: #333333; line-height: 1.6; font-size: 15px;">
        <p>Hallo,</p>

        <p>du erhältst diese E-Mail, weil wir eine Anfrage zum Zurücksetzen des Passworts für dein Benutzerkonto erhalten haben.</p>

        <div class="info-notice" style="background-color: #f9f9f9; border-left: 3px solid #C5A059; padding: 15px; margin: 25px 0;">
            <span style="color: #C5A059; font-weight: bold; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;">Sicherheitshinweis</span><br>
            <span style="color: #666666;">Dieser Link zum Zurücksetzen des Passworts läuft in <strong>60 Minuten</strong> ab.</span>
        </div>

        <p style="margin-top: 30px;">
            Klicke auf den untenstehenden Button, um dein Passwort neu festzulegen. Falls du kein neues Passwort angefordert hast, ist keine weitere Aktion erforderlich.
        </p>
    </div>

    {{-- ACTION BUTTON --}}
    <div class="action-section" style="margin: 45px 0; text-align: center; border-top: 1px solid #f5f5f5; padding-top: 40px;">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td align="center">
                    <table border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td align="center" style="border-radius: 4px;" bgcolor="#C5A059">
                                <a href="{{ $emailData['reset_link'] }}"
                                   target="_blank"
                                   style="font-size: 15px; font-family: sans-serif; color: #ffffff; text-decoration: none; border-radius: 4px; padding: 16px 40px; border: 1px solid #C5A059; display: inline-block; font-weight: bold; letter-spacing: 0.5px;">
                                    Passwort jetzt zurücksetzen
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin-top: 25px; font-size: 13px; color: #999999;">
            Sollte der Button nicht funktionieren, kopiere bitte den folgenden Link direkt in deinen Browser:<br>
            <span style="color: #C5A059; word-break: break-all;">{{ $emailData['reset_link'] }}</span>
        </p>
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
