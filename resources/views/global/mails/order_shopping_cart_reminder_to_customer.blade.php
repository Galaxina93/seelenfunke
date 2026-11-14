{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Deine Auswahl bei Mein Seelenfunke ist noch für dich gespeichert'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- FREUNDLICHE, DRUCKFREIE ANSPRACHE --}}
    <h1>Dein Warenkorb wartet ganz in Ruhe auf dich</h1>
    <p>Hallo {{ !empty($cart->customer?->first_name) ? $cart->customer->first_name : 'liebe Besucherin, lieber Besucher' }},</p>

    <p>bei deinem letzten Besuch hast du dir etwas Schönes ausgesucht und in deinen Warenkorb gelegt. Falls dir im Alltag einfach etwas dazwischengekommen ist oder du noch in Ruhe darüber nachdenken möchtest, ist das überhaupt kein Problem.</p>
    <p>Ganz ohne Eile und völlig unverbindlich: Wir haben deine Auswahl sicher für dich aufbewahrt, damit du jederzeit entspannt weiterschauen kannst.</p>

    {{-- ARTIKEL LISTE (NUR AKTIVE PRODUKTE) --}}
    @php
        $itemsToRender = isset($activeItems)
            ? $activeItems
            : $cart->items->filter(fn($i) => $i->product && $i->product->status === 'active');
    @endphp
    @if($itemsToRender->isNotEmpty())
        <div style="margin-top: 28px; margin-bottom: 28px; padding: 20px; background-color: #faf9f6; border-radius: 10px; border: 1px solid #e7e2d3;">
            <h3 style="font-size: 15px; color: #1f2937; border-bottom: 1px solid #e7e2d3; padding-bottom: 10px; margin-bottom: 14px; margin-top: 0;">Für dich gespeichert:</h3>

            <ul style="padding-left: 20px; margin-bottom: 0;">
                @foreach($itemsToRender as $item)
                    @php
                        $remConf = is_array($item->configuration) ? $item->configuration : [];
                        $remAddons = [];
                        if (!empty($remConf['addons_summary']) && is_array($remConf['addons_summary'])) {
                            foreach ($remConf['addons_summary'] as $a) {
                                if (!empty($a['name'])) $remAddons[] = $a['name'];
                            }
                        }
                        $hasPersonalDesign = !empty($remConf['texts'])
                            || !empty($remConf['logos'])
                            || !empty($remConf['custom_text'])
                            || !empty($remConf['engraving_text'])
                            || !empty($remConf['notes']);
                    @endphp
                    <li style="margin-bottom: 10px; color: #1f2937;">
                        <strong>{{ $item->quantity }}x</strong> {{ $item->product->name }}
                        @if(!empty($remConf['variant_name']))
                            <span style="font-size: 12px; color: #64748b;">({{ $remConf['variant_name'] }})</span>
                        @endif
                        @if($hasPersonalDesign)
                            <div style="font-size: 12px; color: #8a6d2f; margin-top: 3px;">✓ Inklusive deiner persönlichen Gestaltung gespeichert</div>
                        @endif
                        @if(!empty($remAddons))
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">✓ Extras: {{ implode(', ', $remAddons) }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- CALL TO ACTION (MATT-GOLD #C5A059) --}}
    <div style="text-align: center; margin-top: 32px; margin-bottom: 32px;">
        <a href="{{ $cartLink }}"
           style="background-color: #C5A059; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 15px; display: inline-block;">
            Gespeicherten Warenkorb ansehen
        </a>
    </div>

    {{-- HERZLICHES HILFSANGEBOT OHNE DRUCK --}}
    <div style="margin-top: 16px; padding: 16px 18px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; line-height: 1.6;">
        <strong>Kann ich dir bei deiner Auswahl helfen?</strong><br>
        Falls du noch eine Frage zu deiner Wunschgravur hast oder im Konfigurator etwas gehakt hat, antworte einfach direkt auf diese E-Mail oder schreib uns über unser <a href="{{ url('/kontakt') }}" style="color: #C5A059; text-decoration: underline; font-weight: bold;">Kontaktformular</a>. Wir helfen dir von Herzen gerne weiter.
    </div>

    <p style="margin-top: 24px; margin-bottom: 8px;">
        Herzliche Grüße aus unserer Manufaktur in Gifhorn,<br>
        <strong>Alina von Mein Seelenfunke</strong>
    </p>

    {{-- RECHTLICHER HINWEIS & WIDERSPRUCHSRECHT (§ 7 Abs. 3 UWG / Art. 21 DSGVO) --}}
    <div style="margin-top: 30px; padding: 15px 20px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; color: #64748b; line-height: 1.6;">
        <p style="margin: 0 0 6px 0;">
            <strong>Hinweis zum Datenschutz &amp; Abmeldung:</strong> Du erhältst diese freundliche Erinnerung zu deinem gespeicherten Warenkorb bei Mein Seelenfunke.
        </p>
        <p style="margin: 0;">
            Wenn du in Zukunft <strong>keine Erinnerungen an gespeicherte Warenkörbe</strong> mehr erhalten möchtest, kannst du dem jederzeit kostenfrei (ohne andere Kosten als die Übermittlungskosten nach den Basistarifen deines Providers) mit einem Klick widersprechen:
            <br>
            <a href="{{ $unsubscribeUrl ?? url('/newsletter?tab=unsubscribe') }}" style="color: #475569; text-decoration: underline; font-weight: bold;">
                Hier klicken, um keine Warenkorb-Erinnerungen mehr zu erhalten
            </a>
        </p>
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
