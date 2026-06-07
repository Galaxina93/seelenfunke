{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Bestellbestätigung'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Vielen Dank, {{ $data['contact']['vorname'] }}!</h1>
    @php
        $hasPhysical = false;
        $hasDigital = false;
        
        foreach($data['items'] ?? [] as $item) {
            $itemType = $item['type'] ?? 'physical';
            $isShippingOrExpress = str_contains(strtolower($item['name'] ?? ''), 'versand') || str_contains(strtolower($item['name'] ?? ''), 'express');
            
            if ($isShippingOrExpress) {
                continue;
            }
            
            if ($itemType === 'digital') {
                $hasDigital = true;
            } else {
                $hasPhysical = true;
            }
        }
    @endphp

    @if($hasDigital && !$hasPhysical)
        <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten. Deine digitalen Produkte stehen ab sofort in deinem Kundenkonto zum Download bereit!</p>
    @elseif($hasDigital && $hasPhysical)
        <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten. Die digitalen Produkte stehen ab sofort in deinem Kundenkonto zum Download bereit, und deine physischen Schätze bereiten wir nun mit viel Liebe für dich vor.</p>
    @else
        <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten und bereiten diese nun mit viel Liebe für dich vor.</p>
    @endif

    {{-- ARTIKEL LISTE --}}
    @include('global.mails.partials.mail_item_list')

    {{--PREISAUFSTELLUNG--}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => true])

    @php
        $hasPhysicalProduct = false;
        foreach($data['items'] ?? [] as $item) {
            $confType = $item['config']['type'] ?? 'physical';
            $isShippingOrExpress = str_contains(strtolower($item['name'] ?? ''), 'versand') || str_contains(strtolower($item['name'] ?? ''), 'express');
            $isPersonalizable = $item['is_personalizable'] ?? true;
            
            if ($confType === 'physical' && !$isShippingOrExpress && $isPersonalizable) {
                $hasPhysicalProduct = true;
                break;
            }
        }
    @endphp

    @if($hasPhysicalProduct)
        {{-- HINWEIS: ARTIKEL ANPASSEN --}}
        <div style="margin-top: 30px; padding: 20px; background-color: #fff8e1; border: 1px solid #ffecb3; border-radius: 8px; text-align: center;">
            <h3 style="margin-top: 0; color: #d97706; font-size: 16px;">✏️ Fehler in deiner Produktkonfiguration bemerkt?</h3>
            <p style="font-size: 14px; color: #4b5563; line-height: 1.6; margin-bottom: 0;">
                Keine Panik! Solange deine Bestellung noch <strong>nicht in Bearbeitung</strong> genommen wurde, kannst du in deinem Kundenportal über die Detailansicht der Bestellung das Design nachträglich selbst anpassen.
                <br><br>
                <a href="{{ url('/orders') }}" style="display: inline-block; padding: 10px 20px; background-color: #d97706; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px;">Bestellung prüfen</a>
            </p>
        </div>
    @endif

    @if(!empty($data['customer_needs_password_change']) && !empty($data['customer_temporary_password']))
        {{-- HINWEIS: KONTO ERSTELLT MIT EINMAL-PASSWORT --}}
        <div style="margin-top: 30px; padding: 20px; background-color: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; text-align: center;">
            <h3 style="margin-top: 0; color: #6d28d9; font-size: 16px;">🔑 Dein Kundenkonto wurde erstellt!</h3>
            <p style="font-size: 14px; color: #4b5563; line-height: 1.6; margin-bottom: 15px;">
                Da du als Gast eingekauft hast, haben wir automatisch ein Kundenkonto für dich angelegt, damit du auf deine digitalen Güter zugreifen kannst.
                Bitte logge dich mit folgenden Zugangsdaten ein und ändere anschließend dein Passwort:
            </p>
            <div style="display: inline-block; padding: 10px 20px; background-color: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; text-align: left; font-family: monospace; font-size: 14px; color: #1f2937; margin-bottom: 20px;">
                <strong>E-Mail:</strong> {{ $data['customer_email'] }}<br>
                <strong>Passwort:</strong> {{ $data['customer_temporary_password'] }}
            </div>
            <br>
            <a href="{{ url('/login') }}" style="display: inline-block; padding: 10px 20px; background-color: #6d28d9; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px;">Jetzt einloggen & herunterladen</a>
        </div>
    @elseif($hasDigital)
        {{-- HINWEIS: DIGITALE DOWNLOADS --}}
        <div style="margin-top: 30px; padding: 20px; background-color: #e0f7fa; border: 1px solid #b2ebf2; border-radius: 8px; text-align: center;">
            <h3 style="margin-top: 0; color: #00838f; font-size: 16px;">🚀 Deine digitalen Downloads sind bereit!</h3>
            <p style="font-size: 14px; color: #4b5563; line-height: 1.6; margin-bottom: 0;">
                Du kannst deine digitalen Dateien direkt im Kundenkonto unter "Bestellungen" herunterladen.
                <br><br>
                <a href="{{ url('/orders') }}" style="display: inline-block; padding: 10px 20px; background-color: #00838f; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px;">Dateien herunterladen</a>
            </p>
        </div>
    @endif

    {{-- HINWEIS ZUR XML DATEI (Wird vom System nur bei gewerblichen Kunden befüllt/angezeigt) --}}
    @if(!empty($xmlPath))
        <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; font-size: 12px; color: #6c757d; line-height: 1.5;">
            <strong>ℹ️ Hinweis zum Anhang:</strong><br>
            Neben der gewohnten <strong>PDF-Rechnung</strong> finden Sie im Anhang auch eine <strong>XML-Datei</strong>.
            Diese dient der automatischen Verarbeitung für Geschäftskunden (E-Rechnung gemäß EN16931).
        </div>
    @endif

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
