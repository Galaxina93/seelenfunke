{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Wichtige Rückfrage zu deinem Gravur-Foto'])

<body>

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Wichtige Rückfrage zu deinem Foto 📸✨</h1>
    <p>Hallo {{ $data['first_name'] ?? 'liebe/r Kunde/in' }},</p>

    <p>wir möchten, dass dein persönliches Seelenstück absolut perfekt wird!</p>
    <p>Bevor wir den Laser in unserer Manufaktur starten, prüfen wir jedes Wunschfoto von Hand auf optimale Kontraste und Schärfe für die Lasergravur.</p>

    {{-- HINWEIS-BOX --}}
    <div style="margin: 25px 0; padding: 20px; background-color: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 8px; font-size: 14px; color: #92400e; line-height: 1.6;">
        <strong style="font-size: 15px; color: #78350f; display: block; margin-bottom: 8px;">
            Hinweis zu: {{ $data['product_name'] ?? 'Deinem Wunschartikel' }} (Bestellung #{{ $data['order_number'] ?? '' }})
        </strong>
        <p style="margin: 0 0 8px 0; color: #92400e;">
            <strong>Grund der Rückfrage:</strong> {{ $data['reason'] }}
        </p>
        @if(!empty($data['note']))
            <p style="margin: 0; padding: 10px; background-color: #ffffff; border-radius: 6px; border: 1px dashed #fcd34d; color: #78350f; font-style: italic;">
                💬 <strong>Tipp aus der Manufaktur:</strong> „{{ $data['note'] }}“
            </p>
        @endif
    </div>

    {{-- CALL TO ACTION BUTTON --}}
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $data['edit_url'] }}" 
           target="_blank" 
           style="display: inline-block; background-color: #C5A059; color: #111111; font-weight: bold; font-size: 15px; text-decoration: none; padding: 14px 28px; border-radius: 8px; box-shadow: 0 2px 8px rgba(197,160,89,0.3);">
            📸 Neues Foto hochladen & anpassen →
        </a>
    </div>

    {{-- ANLEITUNG --}}
    <div style="margin-top: 20px; padding: 15px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; color: #334155; line-height: 1.5;">
        <strong>So einfach geht’s:</strong><br>
        1. Klicke auf den Button oben oder melde dich in deinem Kundenkonto an.<br>
        2. Klicke bei deiner Bestellung auf <strong>„Artikel anpassen“</strong>.<br>
        3. Wähle dein neues Foto im Konfigurator aus und speichere dein Design.<br>
        <em>Sobald dein Foto hinterlegt ist, starten wir sofort mit der Gravur!</em>
    </div>

    <p style="margin-top: 25px; font-size: 13px; color: #666;">
        Du bist unsicher, welches Foto sich am besten eignet? Antworte einfach direkt auf diese E-Mail – wir beraten dich gerne persönlich!
    </p>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
