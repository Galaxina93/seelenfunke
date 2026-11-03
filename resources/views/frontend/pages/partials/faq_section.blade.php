@php
    $freeThreshold = (int) shop_setting('shipping_free_threshold', 5000);
    $shippingCost = (int) shop_setting('shipping_cost', 490);
    $expressPercent = (float) shop_setting('express_surcharge_percent', 20);
    $expressPercentFormatted = rtrim(rtrim(number_format($expressPercent, 2, ',', '.'), '0'), ',');
    $expressMin = (int) shop_setting('express_surcharge_min', 500);
@endphp

<section id="faq" class="bg-gradient-to-b from-gray-50 to-white py-24 scroll-mt-20" aria-labelledby="faq-heading">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- HEADLINE --}}
        <div class="text-center mb-20 fade-in">
            <span class="inline-block px-4 py-1.5 mb-4 text-xs font-bold tracking-widest text-[#8a6820] uppercase bg-primary/10 rounded-full">
                Häufige Fragen
            </span>
            <h2 id="faq-heading" class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-6 text-serif">
                Alles, was du über deinen <span class="text-[#8a6820] italic">Seelenfunken</span> wissen musst
            </h2>
            <div class="w-24 h-1 bg-primary/30 mx-auto rounded-full mb-8 text-serif" aria-hidden="true"></div>
            <p class="text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto text-serif">
                Du hast noch offene Punkte? Hier findest du die Antworten rund um unsere Manufaktur in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}, den Versand und die Personalisierung.
            </p>
        </div>

        {{-- FAQ ACCORDION --}}
        <div class="space-y-6 fade-in" id="faq-container">

            {{-- FRAGE 1: Materialien & Qualität --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Aus welchen Materialien bestehen eure Rohlinge, ist das echtes Kristallglas und Edelstahl?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4">
                    Bei uns gibt es kein billiges Plastik oder dünnes Blech. Für unseren <a href="{{ route('product.show', 'seelen-kristall') }}" class="text-[#8a6820] font-bold hover:underline">Seelen-Kristall</a> verwenden wir ausschließlich <strong>massives, optisch reines K9-Kristallglas</strong> mit handpolierten Facetten. Unser Schmuck (<a href="{{ route('product.show', 'goldenes-seelen-herz') }}" class="text-[#8a6820] font-bold hover:underline">Das Goldene Seelen-Herz</a>) und <a href="{{ route('product.show', 'seelen-talisman') }}" class="text-[#8a6820] font-bold hover:underline">Der Seelen-Talisman</a> bestehen aus schwerem, rostfreiem <strong>Edelstahl mit edler 14-Karat-Echtgold-Ion-Plating-Veredelung</strong>, während unsere tiefschwarzen Herz- und Hundemarken aus federleichtem, <strong>eloxiertem Aluminium</strong> gefertigt sind.
                </div>
            </details>

            {{-- FRAGE 2: 2D/3D-Konfigurator, Sprüche & Motive --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 00-2 2z" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Wie gestalte ich mein Unikat im 2D/3D-Konfigurator und gibt es fertige Vorlagen oder Sprüche?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4">
                    In unserem <strong>interaktiven 2D- & 3D-Echtzeit-Konfigurator</strong> siehst du dein Produkt schon vor der Bestellung von allen Seiten. Du kannst aus liebevoll gestalteten <strong>Anlass-Vorlagen</strong> starten, unsere <strong>„Kreativ-Starthilfe“</strong> mit fertigen Gravursprüchen nutzen, aus über 50 feinen Vektorsymbolen wählen oder eigene Grafiken/Logos hochladen. Bei unseren Anhängern und Hundemarken kannst du sogar <strong>Vorder- und Rückseite ohne Aufpreis</strong> individuell gravieren lassen.
                </div>
            </details>

            {{-- FRAGE 3: Haltbarkeit der Lasergravur --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Wie haltbar ist die Lasergravur auf Schmuck, Schlüsselanhängern, Hundemarken & Gläsern?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4">
                    Da wir nichts aufdrucken oder aufkleben, sondern mit unserem <strong>Dual-Lasersystem (Kaltlicht-UV- & MOPA-Faserlaser)</strong> direkt in die Materialoberfläche gravieren, ist deine Personalisierung <strong>für die Ewigkeit gemacht</strong>. Auf Edelstahl, 14k Gold-Schmuck und Hundemarken (<a href="{{ route('product.show', 'seelen-pfote-hundemarke') }}" class="text-[#8a6820] font-bold hover:underline">Die Seelen-Pfote</a>) ist die Gravur <strong>100 % wasserfest, rostfrei und abriebfest</strong>. Auf K9-Kristallglas und unserem <a href="{{ route('product.show', 'weizenglas-personalisiert') }}" class="text-[#8a6820] font-bold hover:underline">personalisierten Weizenbierglas</a> entsteht ein dauerhafter, edler Frost-Effekt, der niemals verblasst.
                </div>
            </details>

            {{-- FRAGE 4: Geschenkverpackung & Diskreter Direktversand ohne Rechnung im Paket --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Kommt mein Unikat in einer Geschenkverpackung und liegt eine Rechnung im Paket?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4">
                    Jedes Stück verlässt unsere Manufaktur liebevoll verpackt, je nach Produkt im edlen Samtschaum-Etui, Schmuckbeutel oder Seidenpapier mit unserem goldenen <strong>„Mein Seelenfunke“-Markensiegel</strong> (zusätzliche Geschenkbox-Upgrades kannst du direkt im Konfigurator auswählen). <strong>Perfekt zum direkten Verschenken:</strong> Wir legen <strong>keine Papierrechnung mit Preisen ins Paket</strong>! Deine Rechnung erhältst du ausschließlich digital per E-Mail, sodass du das Paket auch direkt an die beschenkte Person senden lassen kannst.
                </div>
            </details>

            {{-- FRAGE 5: Versandkosten & Lieferzeit (DYNAMISCH) --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Was kostet der Versand & wie schnell wird meine Bestellung geliefert?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4">
                    <p class="mb-4">
                        Innerhalb Deutschlands versenden wir ab einem Bestellwert von <strong>{{ number_format($freeThreshold / 100, 2, ',', '.') }} €</strong> grundsätzlich <strong>versandkostenfrei</strong>. Darunter berechnen wir eine faire Pauschale von {{ number_format($shippingCost / 100, 2, ',', '.') }} €.
                    </p>
                    <p class="mb-4">
                        Da jedes Stück individuell für dich in unserer Manufaktur in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }} gefertigt wird, beträgt die Produktionszeit in der Regel <strong>1–3 Werktage</strong>. Der anschließende Versand erfolgt klimafreundlich und stoßsicher verpackt per <strong>DHL inklusive Sendungsverfolgung</strong>.
                    </p>
                    <a href="{{ url('/versand') }}"
                       title="Übersicht der Versandkosten und Lieferzeiten"
                       class="inline-flex items-center gap-1.5 text-[#8a6820] font-bold hover:underline">
                        Alle Details zur Lieferung & EU-Versand ansehen
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                </div>
            </details>

            {{-- FRAGE 6: Express-Fertigung --}}
            @if($expressPercent > 0)
                <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                    <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                        <div class="flex items-center gap-4">
                            <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <h3 class="text-lg md:text-xl font-bold font-serif italic m-0">Ich brauche kurzfristig ein Geschenk: Bietet ihr eine Express-Fertigung an?</h3>
                        </div>
                        <div class="ml-4 flex-shrink-0" aria-hidden="true">
                            <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm">
                                <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                            </span>
                        </div>
                    </summary>
                    <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4">
                        Ja! Wenn es besonders eilig ist, kannst du direkt im Konfigurator oder im Warenkorb für {{ $expressPercentFormatted }} % des Warenwerts (mindestens {{ number_format($expressMin / 100, 2, ',', '.') }} €) unsere <strong>Express-Fertigung</strong> hinzubuchen. Dein Auftrag zieht dann in der Manufaktur-Warteschlange nach ganz vorn und wird bevorzugt an DHL übergeben.
                    </div>
                </details>
            @endif

            {{-- FRAGE 7: Seelenfunken-Treuesystem & Funki --}}
            <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                    <div class="flex items-center gap-4">
                        <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                        </div>
                        <h3 class="text-lg md:text-xl font-bold font-serif m-0">Was sind „Seelenfunken“ und wie kann ich damit beim Einkauf sparen?</h3>
                    </div>
                    <div class="ml-4 flex-shrink-0" aria-hidden="true">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                    </div>
                </summary>
                <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4">
                    Mit einem kostenlosen Kundenkonto kannst du unsere <strong>Seelenfunken-Magie</strong> aktivieren: Du sammelst bei jeder Bestellung, auf Entdeckungsreise durch den Shop sowie für ehrliche Produktbewertungen wertvolle Seelenfunken. Damit steigt dein persönlicher Begleiter <strong>Funki</strong> im Level auf, und du kannst deine gesammelten Funken jederzeit direkt im Kundenbereich gegen echte <strong>Rabatt-Gutscheine (bis zu 15 % Rabatt)</strong> eintauschen.
                </div>
            </details>

        </div>

        {{-- Footer Text --}}
        <div class="mt-20 text-center bg-white p-10 rounded-[2.5rem] shadow-sm border border-gray-50 text-serif">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Noch etwas unklar?</h3>
            <p class="text-gray-600 mb-6">
                Schreib uns einfach eine Nachricht. Wir antworten meist innerhalb weniger Stunden.
            </p>
            <a href="mailto:{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}"
               title="Kundenservice per E-Mail kontaktieren"
               class="inline-flex items-center gap-2 px-8 py-3 bg-gray-900 text-white font-bold rounded-full hover:bg-primary transition-all duration-300 group text-serif">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Jetzt Kontakt aufnehmen
            </a>
        </div>

    </div>
</section>

{{-- STRUCTURED DATA FOR FAQ (JSON-LD) --}}
<script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "Aus welchen Materialien bestehen eure Rohlinge, ist das echtes Kristallglas und Edelstahl?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Für unseren Seelen-Kristall verwenden wir ausschließlich massives, optisch reines K9-Kristallglas mit handpolierten Facetten. Unser Schmuck (Das Goldene Seelen-Herz) und Der Seelen-Talisman bestehen aus rostfreiem Edelstahl mit 14-Karat-Echtgold-Ion-Plating-Veredelung, während unsere schwarzen Herz- und Hundemarken aus federleichtem, eloxiertem Aluminium gefertigt sind."
          }
        },
        {
          "@type": "Question",
          "name": "Wie gestalte ich mein Unikat im 2D/3D-Konfigurator und gibt es fertige Vorlagen oder Sprüche?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Im interaktiven 2D- & 3D-Echtzeit-Konfigurator siehst du dein Produkt live von allen Seiten. Du kannst aus Anlass-Vorlagen starten, unsere Kreativ-Starthilfe mit fertigen Gravursprüchen nutzen, aus über 50 Vektorsymbolen wählen oder eigene Grafiken hochladen. Anhänger und Hundemarken sind ohne Aufpreis beidseitig gravierbar."
          }
        },
        {
          "@type": "Question",
          "name": "Wie haltbar ist die Lasergravur auf Schmuck, Schlüsselanhängern, Hundemarken & Gläsern?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Durch unser Dual-Lasersystem (Kaltlicht-UV- & MOPA-Faserlaser) wird direkt in das Material graviert. Auf Edelstahl, 14k Gold-Schmuck und Hundemarken ist die Gravur 100 % wasserfest, rostfrei und abriebfest. Auf K9-Kristallglas und Weizenbiergläsern entsteht ein dauerhafter Frost-Effekt, der niemals verblasst."
          }
        },
        {
          "@type": "Question",
          "name": "Kommt mein Unikat in einer Geschenkverpackung und liegt eine Rechnung im Paket?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Jedes Stück wird liebevoll im Samtschaum-Etui, Schmuckbeutel oder Seidenpapier mit goldenem Mein Seelenfunke-Siegel verpackt. Wir legen keine Papierrechnung mit Preisen ins Paket. Die Rechnung erhältst du rein digital per E-Mail, ideal für den direkten Geschenkversand."
          }
        },
        {
          "@type": "Question",
          "name": "Was kostet der Versand & wie schnell wird meine Bestellung geliefert?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Innerhalb Deutschlands versenden wir ab {{ number_format($freeThreshold / 100, 2, ',', '.') }} € Bestellwert versandkostenfrei (darunter {{ number_format($shippingCost / 100, 2, ',', '.') }} €). Die Manufaktur-Fertigung dauert ca. 1–3 Werktage, der Versand erfolgt per DHL inklusive Sendungsverfolgung."
          }
        }
    @if($expressPercent > 0)
        ,{
          "@type": "Question",
          "name": "Ich brauche kurzfristig ein Geschenk: Bietet ihr eine Express-Fertigung an?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Ja! Für {{ $expressPercentFormatted }} % des Warenwerts (mindestens {{ number_format($expressMin / 100, 2, ',', '.') }} €) wird dein Auftrag in der Manufaktur priorisiert gefertigt und bevorzugt an DHL übergeben."
          }
        }
    @endif
        ,{
          "@type": "Question",
          "name": "Was sind „Seelenfunken“ und wie kann ich damit beim Einkauf sparen?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Mit aktiviertem Kundenkonto sammelst du bei Bestellungen, auf den Shop-Seiten sowie für verifizierte Produktbewertungen Seelenfunken, mit denen dein Begleiter Funki im Level aufsteigt und du dir bis zu 15 % Rabatt-Gutscheine freischalten kannst."
          }
        }
    ]
  }
</script>
