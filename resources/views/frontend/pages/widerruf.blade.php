<x-layouts.frontend_layout>
    <div class="px-4 py-16 sm:px-6 lg:px-8 bg-gray-50/50 min-h-screen">
        <div class="max-w-3xl mx-auto space-y-12">
            
            <div class="text-center mt-12">
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

            <div class="text-center pb-24">
                <a href="{{ route('agb') }}#widerrufsbelehrung" class="text-sm text-gray-500 hover:text-primary underline underline-offset-4 transition-colors">
                    Zur vollständigen Widerrufsbelehrung & AGB
                </a>
            </div>

        </div>
    </div>
</x-layouts.frontend_layout>
