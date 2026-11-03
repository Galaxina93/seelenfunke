<x-layouts.frontend_layout
    title="Widerrufsbelehrung & Elektronischer Widerruf | Mein Seelenfunke"
    meta_description="Gesetzliche Widerrufsbelehrung, Ausnahmen bei personalisierten Gravur-Artikeln (§ 312g Abs. 2 Nr. 1 BGB), Muster-Widerrufsformular und elektronische Widerrufsfunktion (§ 356a BGB).">
    @php
        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $ownerName = shop_setting('owner_proprietor', 'Alina Steinhauer');
        $street = shop_setting('company_street')
            ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', ''))
            : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
        $zip = shop_setting('company_zip', '38518');
        $city = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
        $email = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
        $phone = shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'));
    @endphp
    <div class="px-4 pt-28 pb-16 lg:pt-32 lg:pb-24 sm:px-6 lg:px-8 bg-gray-50/50 min-h-screen">
        <div class="max-w-3xl mx-auto space-y-12">
            
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-black text-gray-900 mb-4 tracking-tight">Widerrufsbelehrung &amp; Elektronischer Widerruf</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Nachfolgend finden Sie das elektronische Widerrufsformular (§ 356a BGB) sowie unsere vollständige gesetzliche Widerrufsbelehrung für Verbraucher (§ 13 BGB).
                </p>
            </div>

            <livewire:shop.order.order-revocation-form />

            {{-- Vollständige gesetzliche Widerrufsbelehrung direkt auf /widerruf (Art. 246a § 1 Abs. 2 EGBGB) --}}
            <div id="widerrufsbelehrung" class="scroll-mt-28 bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 shadow-xs space-y-6 text-sm text-gray-700 leading-relaxed">
                <div class="border-b border-gray-100 pb-4">
                    <span class="text-[11px] font-bold uppercase tracking-widest text-primary block mb-1">Gesetzliche Verbraucherinformationen</span>
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900">Widerrufsbelehrung für Verbraucher (§ 13 BGB)</h2>
                </div>

                <div class="space-y-3">
                    <h3 class="font-bold text-base text-gray-900">Widerrufsrecht</h3>
                    <p>
                        Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die letzte Ware in Besitz genommen haben bzw. hat (bei Verträgen über digitale Inhalte, die nicht auf einem körperlichen Datenträger geliefert werden: ab dem Tag des Vertragsabschlusses).
                    </p>
                    <p>
                        Um Ihr Widerrufsrecht auszuüben, müssen Sie uns:
                    </p>
                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-200 font-medium text-gray-900">
                        {{ $companyName }} – Inh. {{ $ownerName }}<br>
                        {{ $street }}, {{ $zip }} {{ $city }}, Deutschland<br>
                        E-Mail: <a href="mailto:{{ $email }}" class="text-primary underline">{{ $email }}</a><br>
                        Telefon: {{ $phone }}
                    </div>
                    <p>
                        mittels einer eindeutigen Erklärung (z.&nbsp;B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist. Sie können den Widerruf auch direkt elektronisch über unser obenstehendes <strong>Online-Widerrufsformular (§ 356a BGB)</strong> auf dieser Seite erklären; machen Sie von dieser Möglichkeit Gebrauch, so werden wir Ihnen unverzüglich (z.&nbsp;B. per E-Mail) eine Bestätigung über den Eingang eines solchen Widerrufs auf einem dauerhaften Datenträger übermitteln. Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.
                    </p>
                </div>

                <div class="space-y-3">
                    <h3 class="font-bold text-base text-gray-900">Folgen des Widerrufs</h3>
                    <p>
                        Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.
                    </p>
                    <p>
                        Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist. Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden. Sie tragen die unmittelbaren Kosten der Rücksendung der Waren. Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist (§ 357a Abs. 1 BGB).
                    </p>
                </div>

                <div class="p-4 rounded-xl bg-amber-50/80 border border-amber-200/80 space-y-2 text-amber-950">
                    <h3 class="font-bold text-base text-amber-950">Gesetzliche Ausschluss- und Erlöschensgründe (§ 312g Abs. 2 Nr. 1 &amp; § 356 Abs. 5 BGB)</h3>
                    <p>
                        • <strong>Personalisierte Waren (§ 312g Abs. 2 Nr. 1 BGB):</strong> Das Widerrufsrecht besteht <strong>nicht</strong> bei Verträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind (z.&nbsp;B. Artikel mit individueller Lasergravur nach Kundenwunsch wie Namen, Daten, Wunschtexten oder hochgeladenen Fotos/Motiven). Ihre gesetzlichen Mängelrechte (2 Jahre Gewährleistung bei Transportschäden, Materialfehlern oder fehlerhafter Gravur) bleiben hiervon uneingeschränkt bestehen.
                    </p>
                    <p>
                        • <strong>Digitale Inhalte (§ 356 Abs. 5 BGB):</strong> Bei Verträgen über die Bereitstellung von nicht auf einem körperlichen Datenträger befindlichen digitalen Inhalten erlischt das Widerrufsrecht vorzeitig, wenn Sie ausdrücklich zugestimmt haben, dass wir mit der Vertragserfüllung vor Ablauf der Widerrufsfrist beginnen, Sie Ihre Kenntnis bestätigt haben, dass durch Ihre Zustimmung mit Beginn der Vertragserfüllung Ihr Widerrufsrecht erlischt, und wir Ihnen eine entsprechende Bestätigung gemäß § 312f BGB zur Verfügung gestellt haben.
                    </p>
                </div>

                <div class="pt-4 border-t border-gray-100 space-y-2">
                    <h3 class="font-bold text-base text-gray-900">Muster-Widerrufsformular (Anlage 2 zu Art. 246a § 1 Abs. 2 S. 1 Nr. 1 EGBGB)</h3>
                    <p class="text-xs text-gray-500 italic">(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück oder nutzen Sie unser elektronisches Formular oben auf dieser Seite.)</p>
                    <div class="p-4 rounded-xl bg-gray-50 border border-gray-200 text-xs sm:text-sm space-y-1.5 font-mono text-gray-800">
                        <p>An: {{ $companyName }}, Inh. {{ $ownerName }}, {{ $street }}, {{ $zip }} {{ $city }}, Telefon: {{ $phone }}, E-Mail: {{ $email }}</p>
                        <p>— Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*) / die Erbringung der folgenden Dienstleistung (*)</p>
                        <p>— Bestellt am (*) / erhalten am (*)</p>
                        <p>— Name des/der Verbraucher(s)</p>
                        <p>— Anschrift des/der Verbraucher(s)</p>
                        <p>— Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)</p>
                        <p>— Datum</p>
                        <p class="text-[11px] text-gray-500 pt-1">(*) Unzutreffendes streichen.</p>
                    </div>
                </div>
            </div>

            {{-- Infobox: Abgrenzung zu Reklamation & Gesetzlicher Gewährleistung --}}
            <div class="bg-gradient-to-r from-blue-50/90 via-indigo-50/50 to-blue-50/90 border border-blue-200/80 rounded-2xl p-6 sm:p-8 shadow-xs">
                <div class="flex flex-col sm:flex-row items-start gap-5">
                    <div class="flex-shrink-0 w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
                        🇪🇺
                    </div>
                    <div class="space-y-3 flex-1 text-left">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-base sm:text-lg font-bold text-blue-950">
                                Ware defekt oder fehlerhaft geliefert?
                            </h2>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800 px-2.5 py-0.5 rounded-full">
                                Gesetzliche Gewährleistung
                            </span>
                        </div>
                        <p class="text-sm text-blue-900/90 leading-relaxed">
                            Möchten Sie einen Artikel reklamieren, weil er beschädigt oder fehlerhaft ist? Hierfür müssen Sie <strong>keinen Widerruf</strong> erklären. Nach EU-Recht steht Ihnen die <strong>gesetzliche Gewährleistung der Vertragsmäßigkeit von mindestens 2 Jahren</strong> zu, inklusive Anspruch auf kostenlose Nachbesserung (Reparatur) oder Neulieferung.
                        </p>
                        <div class="pt-2 flex flex-wrap items-center gap-4 text-xs font-semibold">
                            <a href="{{ route('agb') }}#gewaehrleistung" class="inline-flex items-center gap-1.5 text-primary hover:text-primary-dark underline underline-offset-4 transition-colors">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>EU-Hinweis zur Gewährleistung in den AGB ansehen</span>
                            </a>
                            <a href="{{ asset('shop/frontend/legal/eu_gewaehrleistung_notice_de.svg') }}" target="_blank" class="inline-flex items-center gap-1.5 text-primary hover:text-primary-dark underline underline-offset-4 transition-colors">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                <span>Offizielles EU-Mitteilungsblatt anzeigen</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Infobox: Seelenfunke Creator Partnerprogramm (B2B / § 14 BGB) --}}
            <div id="creator-partnerprogramm" class="scroll-mt-28 bg-amber-50/80 border border-primary/30 rounded-2xl p-6 sm:p-8 shadow-xs">
                <div class="flex flex-col sm:flex-row items-start gap-5">
                    <div class="flex-shrink-0 w-12 h-12 rounded-2xl bg-primary text-white flex items-center justify-center font-bold text-xl shadow-md">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                    </div>
                    <div class="space-y-3 flex-1 text-left">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-base sm:text-lg font-bold text-gray-900">
                                Hinweis für das „Seelenfunke Creator Partnerprogramm“
                            </h2>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider bg-primary/15 text-primary-dark px-2.5 py-0.5 rounded-full border border-primary/25">
                                B2B-Kooperation (§ 14 BGB)
                            </span>
                        </div>
                        <p class="text-sm text-gray-800 leading-relaxed">
                            Das gesetzliche Verbraucher-Widerrufsrecht (§§ 312g, 355 BGB) gilt ausschließlich für Verbraucher (§ 13 BGB). Da die Teilnahme am <strong>Seelenfunke Creator Partnerprogramm</strong> ausschließlich selbstständigen Creatorn, Freiberuflern und Gewerbetreibenden vorbehalten ist, die als <strong>Unternehmer im Sinne des § 14 BGB</strong> auf eigene Rechnung handeln, besteht für den Abschluss der Creator-Kooperationsvereinbarung kein Verbraucher-Widerrufsrecht. Sie können Ihre Teilnahme am Partnerprogramm jedoch jederzeit formfrei und ohne Mindestlaufzeit gemäß § 10 Abs. 6 unserer AGB beenden.
                        </p>
                        <div class="pt-1">
                            <a href="{{ route('agb') }}#creator-partnerprogramm" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:text-primary-dark underline underline-offset-4 transition-colors">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Zu § 10 Seelenfunke Creator Partnerprogramm in den AGB</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center pb-24 space-y-3">
                <div>
                    <a href="{{ route('widerruf.pdf') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-primary transition-colors">
                        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Lieber per Post? Muster-Widerrufsformular als PDF herunterladen</span>
                    </a>
                </div>
                <div>
                    <a href="{{ route('agb') }}#widerrufsbelehrung" class="text-xs text-gray-400 hover:text-primary underline underline-offset-4 transition-colors">
                        Zur vollständigen Widerrufsbelehrung & AGB
                    </a>
                </div>
            </div>

        </div>
    </div>
</x-layouts.frontend_layout>
