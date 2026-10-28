<x-layouts.frontend_layout
    title="Widerrufsbelehrung & Elektronischer Widerruf | Mein Seelenfunke"
    meta_description="Informationen zum gesetzlichen Widerrufsrecht, Ausnahmen bei personalisierten Gravur-Artikeln und elektronisches Widerrufsformular.">
    <div class="px-4 py-12 md:py-16 sm:px-6 lg:px-8 bg-gray-50/50 min-h-screen">
        <div class="max-w-3xl mx-auto space-y-12">
            
            <div class="text-center">
                <h1 class="text-3xl md:text-4xl font-black text-gray-900 mb-4 tracking-tight">Elektronischer Widerruf</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Nutzen Sie dieses Formular, um Ihre Vertragserklärung über nicht-personalisierte Waren (falls zutreffend) auf elektronischem Wege fristgerecht zu widerrufen.
                </p>
            </div>

            <livewire:shop.order.order-revocation-form />

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
                            Möchten Sie einen Artikel reklamieren, weil er beschädigt oder fehlerhaft ist? Hierfür müssen Sie <strong>keinen Widerruf</strong> erklären. Nach EU-Recht steht Ihnen die <strong>gesetzliche Gewährleistung der Vertragsmäßigkeit von mindestens 2 Jahren</strong> zu – mit Anspruch auf kostenlose Nachbesserung (Reparatur) oder Neulieferung.
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
