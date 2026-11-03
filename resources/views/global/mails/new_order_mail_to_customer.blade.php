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
        $hasPostalVoucher = !empty($data['has_postal_voucher']);
        
        foreach($data['items'] ?? [] as $item) {
            $itemType = $item['type'] ?? 'physical';
            $itemConf = $item['config'] ?? [];
            $isShippingOrExpress = str_contains(strtolower($item['name'] ?? ''), 'versand') || str_contains(strtolower($item['name'] ?? ''), 'express');
            
            if ($isShippingOrExpress) {
                continue;
            }

            if (!empty($itemConf['is_gift_voucher']) && ($itemConf['delivery_method'] ?? 'email') === 'post') {
                $hasPostalVoucher = true;
                $hasPhysical = true;
                $hasDigital = true;
                continue;
            }
            
            if ($itemType === 'digital') {
                $hasDigital = true;
            } else {
                $hasPhysical = true;
            }
        }
    @endphp

    @if($hasPostalVoucher)
        <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten. Wir bereiten deinen gedruckten Geschenkgutschein im edlen Umschlag (<strong>✉️ Per Post im Umschlag</strong>) nun mit viel Liebe für den Postversand vor. Zusätzlich steht dir das Gutschein-PDF bereits jetzt als Sofort-Download zur Verfügung!</p>
    @elseif($hasDigital && !$hasPhysical)
        <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten. Deine digitalen Produkte stehen ab sofort zum Download bereit!</p>
    @elseif($hasDigital && $hasPhysical)
        <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten. Die digitalen Produkte stehen ab sofort zum Download bereit, und deine physischen Schätze bereiten wir nun mit viel Liebe für dich vor.</p>
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
            $itemType = $item['type'] ?? 'physical';
            $isShippingOrExpress = str_contains(strtolower($item['name'] ?? ''), 'versand') || str_contains(strtolower($item['name'] ?? ''), 'express');
            $isPersonalizable = $item['is_personalizable'] ?? true;
            
            if ($itemType === 'physical' && !$isShippingOrExpress && $isPersonalizable) {
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

    @if(!empty($data['instant_downloads']) && is_array($data['instant_downloads']))
        {{-- SOFORT-DOWNLOAD LINKS (SIGNIERT & OHNE LOGIN NUTZBAR) --}}
        <div style="margin-top: 30px; padding: 20px; background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; text-align: center;">
            <h3 style="margin-top: 0; color: #047857; font-size: 16px;">🚀 Dein Sofort-Download ist bereit!</h3>
            <p style="font-size: 14px; color: #4b5563; line-height: 1.6; margin-bottom: 15px;">
                Du kannst deine digitalen Produkte und Gutscheine direkt hier über die geschützten Direkt-Links herunterladen:
            </p>
            @foreach($data['instant_downloads'] as $dl)
                <div style="margin-bottom: 10px; padding: 12px 16px; background-color: #ffffff; border: 1px solid #d1fae5; border-radius: 6px; text-align: left;">
                    <div style="font-weight: bold; font-size: 14px; color: #111827;">{{ $dl['title'] ?? 'Download' }}</div>
                    @if(!empty($dl['code']))
                        <div style="font-size: 12px; color: #b45309; font-family: monospace; margin-top: 4px;">
                            Gutscheincode: <strong>{{ $dl['code'] }}</strong>
                        </div>
                    @endif
                    <div style="margin-top: 8px;">
                        <a href="{{ $dl['url'] }}" style="display: inline-block; padding: 8px 16px; background-color: #059669; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 12px;">
                            📥 {{ ($dl['type'] ?? '') === 'gift_voucher' ? 'Gutschein-PDF herunterladen' : 'Datei herunterladen' }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($data['customer_needs_password_change']) && !empty($data['customer_temporary_password']))
        {{-- HINWEIS: KONTO ERSTELLT MIT EINMAL-PASSWORT --}}
        <div style="margin-top: 30px; padding: 20px; background-color: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; text-align: center;">
            <h3 style="margin-top: 0; color: #6d28d9; font-size: 16px;">🔑 Dein Kundenkonto wurde erstellt!</h3>
            <p style="font-size: 14px; color: #4b5563; line-height: 1.6; margin-bottom: 15px;">
                Da du als Gast eingekauft hast, haben wir automatisch ein Kundenkonto für dich angelegt, damit du jederzeit auf deine Bestellungen und digitalen Güter zugreifen kannst.
                Bitte logge dich mit folgenden Zugangsdaten ein und ändere anschließend dein Passwort:
            </p>
            <div style="display: inline-block; padding: 10px 20px; background-color: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; text-align: left; font-family: monospace; font-size: 14px; color: #1f2937; margin-bottom: 20px;">
                <strong>E-Mail:</strong> {{ $data['customer_email'] }}<br>
                <strong>Passwort:</strong> {{ $data['customer_temporary_password'] }}
            </div>
            <br>
            <a href="{{ url('/login') }}" style="display: inline-block; padding: 10px 20px; background-color: #6d28d9; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px;">Jetzt einloggen &amp; herunterladen</a>
        </div>
    @elseif($hasDigital && empty($data['instant_downloads']))
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

    {{-- GESETZLICHE VERTRAGSBESTÄTIGUNG & WIDERRUFSBELEHRUNG AUF DAUERHAFTEM DATENTRÄGER (§ 312f Abs. 2 BGB) --}}
    @php
        $revCompanyName = function_exists('shop_setting') ? shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) : 'Mein Seelenfunke';
        $revOwnerName   = function_exists('shop_setting') ? shop_setting('owner_proprietor', 'Alina Steinhauer') : 'Alina Steinhauer';
        $revStreet      = function_exists('shop_setting')
            ? (shop_setting('company_street') ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '')) : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26'))
            : 'Carl-Goerdeler-Ring 26';
        $revZip         = function_exists('shop_setting') ? shop_setting('company_zip', '38518') : '38518';
        $revCity        = function_exists('shop_setting') ? shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) : 'Gifhorn';
        $revEmail       = function_exists('shop_setting') ? shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) : 'kontakt@mein-seelenfunke.de';
        $revPhone       = function_exists('shop_setting') ? shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64')) : '+49 159 019 668 64';
    @endphp
    <div style="margin-top: 28px; padding: 16px 18px; background-color: #fafaf9; border: 1px solid #e7e5e4; border-radius: 8px; font-size: 12px; color: #57534e; line-height: 1.55;">
        <strong style="color: #1c1917;">Rechtliche Hinweise &amp; Vertragsbestätigung (§ 312f Abs. 2 BGB):</strong><br>
        Für deine Bestellung gelten unsere <a href="{{ url('/agb') }}" style="color: #9a6f1a; text-decoration: underline;">Allgemeinen Geschäftsbedingungen (AGB)</a> sowie die nachfolgende gesetzliche Widerrufsbelehrung.<br>
        • <strong>Personalisierte Unikate (§ 312g Abs. 2 Nr. 1 BGB):</strong> Für Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind (z.&nbsp;B. mit Wunschtext, Namen, Datum oder eigenem Motiv per Lasergravur), besteht <strong>kein</strong> gesetzliches Widerrufsrecht. Deine gesetzlichen Mängelrechte (2 Jahre Gewährleistung bei Transportschäden, Materialfehlern oder Fehlgravuren durch uns) bleiben hiervon uneingeschränkt bestehen.<br>
        • <strong>Nicht personalisierte Artikel &amp; Wertgutscheine:</strong> Hierfür gilt das gesetzliche 14-tägige Widerrufsrecht (<a href="{{ url('/widerruf') }}" style="color: #9a6f1a; text-decoration: underline;">elektronische Widerrufsfunktion gem. § 356a BGB</a>).
        @if($hasDigital)
            <br>• <strong>Bestätigung bei digitalen Inhalten (§ 356 Abs. 5 BGB):</strong> Du hast im Bestellprozess ausdrücklich zugestimmt, dass wir vor Ablauf der Widerrufsfrist mit der Bereitstellung der digitalen Inhalte beginnen, und deine Kenntnis bestätigt, dass mit Beginn der Vertragsausführung dein Widerrufsrecht für diese digitalen Inhalte erlischt.
        @endif

        <hr style="border: 0; border-top: 1px solid #e7e5e4; margin: 14px 0;">

        <strong style="color: #1c1917;">Widerrufsbelehrung für Verbraucher (§ 13 BGB)</strong><br>
        <strong>Widerrufsrecht:</strong> Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die letzte Ware in Besitz genommen haben bzw. hat (bei Verträgen über digitale Inhalte, die nicht auf einem körperlichen Datenträger geliefert werden: ab dem Tag des Vertragsabschlusses).<br>
        Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (<strong>{{ $revCompanyName }}, Inh. {{ $revOwnerName }}, {{ $revStreet }}, {{ $revZip }} {{ $revCity }}, Deutschland, Telefon: {{ $revPhone }}, E-Mail: {{ $revEmail }}</strong>) mittels einer eindeutigen Erklärung (z.&nbsp;B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das nachfolgende Muster-Widerrufsformular oder unsere elektronische Widerrufsfunktion unter <a href="{{ url('/widerruf') }}" style="color: #9a6f1a; text-decoration: underline;">{{ url('/widerruf') }}</a> verwenden, was jedoch nicht vorgeschrieben ist. Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.<br><br>
        <strong>Folgen des Widerrufs:</strong> Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet. Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist. Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden. Sie tragen die unmittelbaren Kosten der Rücksendung der Waren. Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist.<br><br>
        <strong style="color: #1c1917;">Muster-Widerrufsformular (Anlage 2 zu Art. 246a § 1 Abs. 2 S. 1 Nr. 1 EGBGB):</strong><br>
        <em>(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</em><br>
        — An: {{ $revCompanyName }}, Inh. {{ $revOwnerName }}, {{ $revStreet }}, {{ $revZip }} {{ $revCity }}, Telefon: {{ $revPhone }}, E-Mail: {{ $revEmail }}<br>
        — Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*) / die Erbringung der folgenden Dienstleistung (*)<br>
        — Bestellt am (*) / erhalten am (*)<br>
        — Name des/der Verbraucher(s)<br>
        — Anschrift des/der Verbraucher(s)<br>
        — Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)<br>
        — Datum<br>
        <em>(*) Unzutreffendes streichen.</em>
    </div>

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
