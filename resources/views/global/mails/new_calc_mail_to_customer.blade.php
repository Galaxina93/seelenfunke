{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree')

<div class="container" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border: 1px solid #eeeeee;">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- EINLEITUNG --}}
    <div class="content-body" style="font-family: sans-serif; color: #333333; line-height: 1.6; font-size: 15px;">
        <p>Hallo <strong>{{ $data['contact']['vorname'] }} {{ $data['contact']['nachname'] }}</strong>,</p>

        <p>vielen Dank für dein Interesse! Wir haben deine Konfiguration geprüft und freuen uns, dir heute dein persönliches Angebot für deine Wunschartikel senden zu dürfen.</p>

        <div class="info-notice" style="background-color: #f9f9f9; border-left: 3px solid #C5A059; padding: 15px; margin: 25px 0;">
            <span style="color: #C5A059; font-weight: bold; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;">Wichtiger Hinweis zu deinen Dateien</span><br>
            <span style="color: #666666;">Deine hochgeladenen Logos und Bilder sind bereits sicher auf unserem Server hinterlegt. Du musst diese für die Bestellung nicht erneut einsenden.</span>
        </div>

        <p style="margin-top: 30px;">
            Nachfolgend findest du die Zusammenfassung deiner Anfrage. Das detaillierte Angebot haben wir dir zudem als <strong>PDF im Anhang</strong> beigefügt.
        </p>
    </div>

    {{-- ANSPRACHE --}}
    <div style="margin-top: 35px;">
        <h3 style="font-size: 14px; color: #999999; text-transform: uppercase; letter-spacing: 1.5px; border-bottom: 1px solid #eeeeee; padding-bottom: 10px; margin-bottom: 15px;">
            Deine Auswahl
        </h3>

        {{-- KUNDENAUSWAHL --}}
        @include('global.mails.partials.mail_item_list')

    </div>

    {{-- PREISAUFSTELLUNG --}}
    <div style="margin-top: 20px;">
        @include('global.mails.partials.mail_price_list')
    </div>

    {{-- ACTION BUTTON --}}
    @if(isset($data['quote_token']))
        <div class="action-section" style="margin: 45px 0; text-align: center; border-top: 1px solid #f5f5f5; padding-top: 40px;">
            <p style="color: #666666; margin-bottom: 25px; font-size: 15px;">
                Über den Button gelangst du zu deiner persönlichen Verwaltung. Dort kannst du das Angebot <strong>annehmen</strong> oder bei Bedarf noch einmal <strong>bearbeiten</strong>.
            </p>

            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td align="center">
                        <table border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td align="center" style="border-radius: 4px;" bgcolor="#C5A059">
                                    <a href="{{ route('quote.accept', ['token' => $data['quote_token']]) }}"
                                       target="_blank"
                                       style="font-size: 15px; font-family: sans-serif; color: #ffffff; text-decoration: none; border-radius: 4px; padding: 16px 40px; border: 1px solid #C5A059; display: inline-block; font-weight: bold; letter-spacing: 0.5px;">
                                        Angebot prüfen & bestätigen
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <p style="margin-top: 20px; font-size: 12px; color: #bbbbbb;">
                Dieses Angebot ist unverbindlich und gültig bis zum <strong>{{ $data['quote_expiry'] }}</strong>.
            </p>
        </div>
    @endif

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
