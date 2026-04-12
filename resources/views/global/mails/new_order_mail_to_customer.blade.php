{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Bestellbestätigung'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Vielen Dank, {{ $data['contact']['vorname'] }}!</h1>
    <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten und bereiten diese nun mit viel Liebe für dich vor.</p>

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
