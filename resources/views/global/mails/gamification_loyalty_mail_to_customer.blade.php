{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => $subjectLine ?? 'Deine Seelenfunken-Überraschung von Funki'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>{{ $headline ?? 'Ein besonderer Seelenfunke für dich! ✨' }}</h1>

    <p>Hallo {{ $firstName ?: 'liebes Seelenfunke-Mitglied' }},</p>

    <p>{!! nl2br(e($introText ?? 'unser kleiner Manufaktur-Begleiter Funki hat heute an dich gedacht und deinem Konto ein funkelndes Geschenk gutgeschrieben.')) !!}</p>

    {{-- GUTSCHRIFT-BOX IM MANUFAKTUR-DESIGN --}}
    <div style="background-color: #fffbeb; border: 1px solid #C5A059; border-radius: 8px; padding: 22px; margin: 28px 0; text-align: center;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: #92400e; font-weight: bold; margin-bottom: 6px;">
            {{ $badgeText ?? 'TREUE-GESCHENK GUTGESCHRIEBEN' }}
        </div>
        <div style="font-size: 28px; font-weight: bold; color: #C5A059; margin-bottom: 8px;">
            +{{ number_format((int) ($awardedSparks ?? 50), 0, ',', '.') }} ✨ Seelenfunken
        </div>
        <div style="font-size: 13px; color: #444444;">
            Dein aktuelles Guthaben: <strong>{{ number_format((int) ($newBalance ?? 50), 0, ',', '.') }} ✨ Funken</strong>
            &nbsp;•&nbsp;
            Dein Begleiter: <strong>Funki Level {{ (int) ($funkiLevel ?? 1) }}</strong>
        </div>

        @if(!empty($nextVoucherHint))
            <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e5d0a1; font-size: 12px; color: #78350f;">
                🎁 {{ $nextVoucherHint }}
            </div>
        @endif
    </div>

    <p>
        Du kannst deine gesammelten Seelenfunken jederzeit in deinem persönlichen Kundenbereich unter <strong>„Funki &amp; Gutscheine“</strong> gegen exklusive Rabatt-Gutscheine (5 %, 10 % oder 15 %) für dein nächstes personalisiertes Kunstwerk eintauschen.
    </p>

    {{-- BUTTON ZUM KUNDENBEREICH --}}
    <div style="text-align: center; margin: 36px 0;">
        <a href="{{ $dashboardUrl ?? url('/customer/dashboard') }}" style="background-color: #C5A059; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 15px;">
            Zu meinem Funki &amp; meinen Seelenfunken
        </a>
    </div>

    <div style="margin-top: 24px; font-size: 12px; color: #6c757d; line-height: 1.5;">
        Falls der Button nicht funktioniert, kopiere einfach den folgenden Link in deinen Browser:<br>
        <a href="{{ $dashboardUrl ?? url('/customer/dashboard') }}" style="color: #C5A059; word-break: break-all;">{{ $dashboardUrl ?? url('/customer/dashboard') }}</a>
    </div>

    <p style="margin-top: 30px;">Mit funkelnden Grüßen,<br>
        <strong>Dein Team &amp; Funki von Mein-Seelenfunke</strong></p>

    <div style="margin-top: 24px; padding-top: 14px; border-top: 1px solid #eeeeee; font-size: 11px; color: #777777; line-height: 1.5; text-align: center;">
        Du möchtest {{ ($mailType ?? 'birthday') === 'birthday' ? 'keine jährlichen Geburtstags-Nachrichten (🎂 Geburtstags-Funke)' : 'keine jährlichen Kauf-Jubiläums-Nachrichten (🕯️ Vorfreude-Funken)' }} mehr per E-Mail erhalten?<br>
        <a href="{{ $unsubscribeUrl ?? url('/newsletter?tab=unsubscribe') }}" style="color: #C5A059; text-decoration: underline;">E-Mail-Einstellungen in der Seelenpost-Verwaltung anpassen oder abmelden</a>
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
