{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Deine Einladung ins Team'])

<body>

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    <h1>Willkommen im Team!</h1>
    <p>Hallo {{ !empty($data['label']) ? $data['label'] : 'du' }},</p>

    <p>hier ist dein persönlicher Zugang für deinen Arbeitsvertrag als <strong>{{ $data['job_title'] ?? 'Mitarbeiter/in' }}</strong>.</p>

    <div style="margin-top: 24px; margin-bottom: 24px; padding: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; color: #0f172a; line-height: 1.6;">
        <div style="margin-bottom: 12px;">
            <strong>1. Link öffnen:</strong><br>
            <a href="{{ $data['url'] ?? '#' }}" target="_blank" style="display: inline-block; margin-top: 6px; padding: 10px 18px; background-color: #111827; color: #ffffff; text-decoration: none; font-weight: bold; border-radius: 8px;">
                Jetzt Onboarding starten
            </a>
        </div>

        <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
            <strong>2. Sicherheitscode eingeben:</strong><br>
            <span style="display: inline-block; margin-top: 6px; padding: 8px 14px; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; font-family: monospace; font-size: 18px; font-weight: bold; letter-spacing: 3px; color: #111827;">
                {{ $data['code'] ?? '' }}
            </span>
        </div>

        <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
            <strong>3. Daten eintragen & unterschreiben:</strong><br>
            Danach gelangst du direkt in dein Mitarbeiter-Cockpit.
        </div>
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
