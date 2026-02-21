<x-layouts.frontend_layout>
    <x-sections.page-container>

        {{--
            SECTION 1: HERO
            Fokus: Viel Weißraum, edle Typografie, großes Produktbild.
        --}}
        <section class="relative bg-white pt-32 pb-16 lg:pt-40 lg:pb-32 overflow-hidden" aria-label="Produktvorstellung">
            {{-- Dezentster Hintergrund-Verlauf (kaum wahrnehmbar für Tiefe) --}}
            <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-b from-gray-50/50 to-white pointer-events-none" aria-hidden="true"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-24 items-center">

                    {{-- Linke Seite: Text & CTA --}}
                    <div class="order-2 lg:order-1 text-center lg:text-left">
                        {{-- Badge --}}
                        <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-primary/5 border border-primary/20 text-primary-dark text-[11px] font-bold uppercase tracking-[0.2em] mb-8">
                            Exklusiv-Edition
                        </div>

                        {{-- Headline --}}
                        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-serif font-bold text-gray-900 leading-[1.1] mb-6">
                            Der <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-primary-dark">Seelen-Kristall</span>
                        </h1>

                        {{-- Subline --}}
                        <p class="text-lg sm:text-xl text-gray-500 mb-10 leading-relaxed font-light max-w-lg mx-auto lg:mx-0">
                            Ein massives Meisterwerk aus reinem K9-Kristallglas.
                            Geschaffen, um den einen Moment festzuhalten, der für immer bleibt.
                            <span class="block mt-2 text-gray-900 font-medium">Inklusive Lasergravur & Geschenkbox.</span>
                        </p>

                        {{-- Buttons --}}
                        <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start mb-12">
                            <a href="{{ route('calculator') }}"
                               title="Preis für Ihr Unikat berechnen"
                               class="group inline-flex justify-center items-center px-8 py-4 bg-primary text-white text-lg font-bold rounded-xl shadow-lg shadow-primary/20 hover:bg-primary-dark hover:shadow-primary/40 hover:-translate-y-1 transition-all duration-300">
                                Preis berechnen
                                <svg class="ml-2 w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                            <a href="#details"
                               title="Details zum Seelen-Kristall ansehen"
                               class="inline-flex justify-center items-center px-8 py-4 bg-white border border-gray-200 text-lg font-bold rounded-xl text-gray-600 hover:text-primary hover:border-primary/50 transition-colors shadow-sm">
                                Details ansehen
                            </a>
                        </div>

                        {{-- Trust Indicators --}}
                        <ul class="flex flex-wrap justify-center lg:justify-start gap-x-8 gap-y-3 text-sm text-gray-500 font-medium list-none">
                            <li class="flex items-center">
                                <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center mr-2 text-xs" aria-hidden="true">✓</span>
                                Sofort lieferbar
                            </li>
                            <li class="flex items-center">
                                <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center mr-2 text-xs" aria-hidden="true">✓</span>
                                Ab 1 Stück
                            </li>
                            <li class="flex items-center">
                                <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mr-2 text-xs" aria-hidden="true">★</span>
                                Premium Qualität
                            </li>
                        </ul>
                    </div>

                    {{-- Rechte Seite: Großes Produktbild --}}
                    <div class="order-1 lg:order-2 relative group perspective-1000">
                        {{-- Dekorativer Hintergrundkreis (Subtil) --}}
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[110%] h-[110%] bg-radial-gradient from-gray-100 to-transparent opacity-60 blur-3xl -z-10" aria-hidden="true"></div>

                        <div class="relative rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50 border border-gray-100 bg-white p-2 md:p-4 transform transition-transform duration-700 hover:scale-[1.02]">
                            <img src="{{ asset('images/projekt/products/seelen-kristall_w.jpg') }}"
                                 alt="Der Seelen-Kristall: Eine hochwertige Trophäe aus massivem K9-Glas mit individueller Gravur"
                                 loading="eager"
                                 class="w-full h-auto rounded-2xl object-cover">

                            {{-- Floating Badge --}}
                            <div class="absolute top-8 right-8 bg-white/90 backdrop-blur-md shadow-xl rounded-2xl p-4 text-center border border-white/50">
                                <span class="block text-gray-400 text-[10px] uppercase font-bold tracking-wider">Ab</span>
                                <span class="block text-3xl font-serif font-bold text-gray-900">39<span class="text-lg">,90€</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{--
            SECTION 2: HIGHLIGHTS (Icons)
            Clean, Gray Background zur Abgrenzung
        --}}
        <section id="details" class="py-24 bg-gray-50 border-y border-gray-100/50" aria-labelledby="details-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 id="details-heading" class="text-3xl font-serif font-bold text-gray-900 mb-4">Warum der Seelen-Kristall?</h2>
                    <p class="text-gray-500">Qualität, die man spürt. Ein Geschenk, das bleibt.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {{-- Feature 1 --}}
                    <article class="bg-white p-10 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:border-primary/20 transition-all duration-300 group">
                        <div class="w-14 h-14 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 text-2xl mb-6 group-hover:scale-110 transition-transform" aria-hidden="true">
                            💎
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Massives K9 Kristall</h3>
                        <p class="text-gray-500 leading-relaxed text-sm">Kein Acryl, kein Plastik. Unser Glas wiegt schwer in der Hand (ca. 1kg) und bricht das Licht in spektralen Farben.</p>
                    </article>

                    {{-- Feature 2 --}}
                    <article class="bg-white p-10 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:border-primary/20 transition-all duration-300 group">
                        <div class="w-14 h-14 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600 text-2xl mb-6 group-hover:scale-110 transition-transform" aria-hidden="true">
                            ✨
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Präzise Lasergravur</h3>
                        <p class="text-gray-500 leading-relaxed text-sm">Ihr Logo und Text werden dauerhaft in das Glas graviert. Kratzfest, zeitlos und gestochen scharf bis ins Detail.</p>
                    </article>

                    {{-- Feature 3 --}}
                    <article class="bg-white p-10 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:border-primary/20 transition-all duration-300 group">
                        <div class="w-14 h-14 bg-purple-50 rounded-xl flex items-center justify-center text-purple-600 text-2xl mb-6 group-hover:scale-110 transition-transform" aria-hidden="true">
                            🎁
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Geschenkbox inklusive</h3>
                        <p class="text-gray-500 leading-relaxed text-sm">Jeder Seelen-Kristall kommt in einer hochwertigen, mit Satin ausgelegten Geschenkbox. Bereit für die Übergabe.</p>
                    </article>
                </div>
            </div>
        </section>

        {{--
            SECTION 3: DETAILS & EMOTION
            Weißer Hintergrund
        --}}
        <section class="py-24 bg-white overflow-hidden" aria-labelledby="specs-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">

                    {{-- Linke Seite: Bild --}}
                    <div class="relative">
                        <div class="absolute inset-0 bg-gray-100 rounded-3xl transform rotate-3 scale-95 origin-bottom-right -z-10" aria-hidden="true"></div>
                        <img src="{{ asset('images/projekt/products/seelen-kristall-spec.png') }}"
                             alt="Detailaufnahme des Seelen-Kristalls: Facettenschliff und Lasergravur"
                             loading="lazy"
                             class="rounded-3xl shadow-2xl w-full object-cover h-[500px] border border-gray-100">

                        {{-- Kleines Detailbild Overlay --}}
                        <div class="absolute -bottom-8 -right-8 w-40 h-40 bg-white p-3 rounded-2xl shadow-xl hidden md:block border border-gray-100 animate-float" aria-hidden="true">
                            <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="w-full h-full object-contain rounded-lg" alt="">
                        </div>
                    </div>

                    {{-- Rechte Seite: Tabelle --}}
                    <div>
                        <h2 id="specs-heading" class="text-4xl font-serif font-bold text-gray-900 mb-6">
                            Details, die überzeugen.
                        </h2>
                        <p class="text-gray-600 mb-10 text-lg font-light leading-relaxed">
                            Der Seelen-Kristall ist zeitlos. Durch den aufwendigen Facettenschliff an den Kanten fängt er das Umgebungslicht ein und lässt die Lasergravur hell erstrahlen. Ein Objekt für die Ewigkeit.
                        </p>

                        <div class="bg-white rounded-2xl p-8 shadow-[0_0_50px_-12px_rgba(0,0,0,0.08)] border border-gray-100">
                            <h3 class="text-xs font-bold text-primary uppercase tracking-[0.2em] mb-6">Technische Spezifikationen</h3>

                            <dl class="divide-y divide-gray-100">
                                <div class="grid grid-cols-3 gap-4 py-4">
                                    <dt class="text-gray-500 font-medium text-sm">Material</dt>
                                    <dd class="col-span-2 font-bold text-gray-900">K9 Hochleistungs-Kristallglas</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4 py-4">
                                    <dt class="text-gray-500 font-medium text-sm">Maße (H*B*T)</dt>
                                    <dd class="col-span-2 font-bold text-gray-900">160 x 180 x 40 mm</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4 py-4">
                                    <dt class="text-gray-500 font-medium text-sm">Gewicht</dt>
                                    <dd class="col-span-2 font-bold text-gray-900">ca. 930g (Massiv)</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4 py-4">
                                    <dt class="text-gray-500 font-medium text-sm">Veredelung</dt>
                                    <dd class="col-span-2 font-bold text-gray-900">UV-Lasergravur (Weiß-Effekt)</dd>
                                </div>
                                <div class="grid grid-cols-3 gap-4 py-4 pt-4 pb-0">
                                    <dt class="text-gray-500 font-medium text-sm">Verpackung</dt>
                                    <dd class="col-span-2 font-bold text-gray-900 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-primary" aria-hidden="true"></span>
                                        Premium Geschenkbox
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="mt-8 flex items-start gap-3">
                            <svg class="w-5 h-5 text-gray-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-xs text-gray-400 italic">
                                Hinweis: Da es sich um ein handveredeltes Produkt handelt, sind minimale Abweichungen möglich. Jedes Stück ist ein Unikat.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        {{--
            SECTION 4: PREIS-KALKULATOR CTA
            Dunkler Kontrastbereich als Abschluss des Info-Teils
        --}}
        <section class="py-24 bg-gray-900 relative overflow-hidden" aria-labelledby="cta-heading">
            {{-- Background Pattern --}}
            <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');" aria-hidden="true"></div>

            {{-- Schein --}}
            <div class="absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-primary/20 rounded-full blur-[120px]" aria-hidden="true"></div>

            <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
                <h2 id="cta-heading" class="text-3xl sm:text-5xl font-serif font-bold text-white mb-6">
                    Bereit für echte Wertschätzung?
                </h2>
                <p class="text-xl text-gray-400 mb-12 max-w-2xl mx-auto font-light">
                    Egal ob Einzelstück für den besten Mitarbeiter oder 100 Stück für das ganze Turnier.
                    Nutzen Sie unseren Kalkulator für sofortige Staffelpreise.
                </p>

                <div class="flex flex-col sm:flex-row justify-center gap-5">
                    <a href="{{ route('calculator') }}"
                       title="Jetzt Preis online berechnen"
                       class="inline-flex justify-center items-center px-10 py-5 border border-transparent text-lg font-bold rounded-xl text-black bg-primary hover:bg-white transition-all shadow-[0_0_30px_-5px_rgba(234,179,8,0.4)] hover:shadow-white/20 hover:-translate-y-1">
                        Zum Angebotskalkulator
                    </a>
                    <a href="#contact"
                       title="Kontaktieren Sie uns für Fragen"
                       class="inline-flex justify-center items-center px-10 py-5 border border-white/20 text-lg font-bold rounded-xl text-white hover:bg-white/10 transition-colors backdrop-blur-sm">
                        Frage stellen
                    </a>
                </div>

                <p class="mt-10 text-sm text-gray-500 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Keine versteckten Kosten. Angebot sofort als PDF.
                </p>
            </div>
        </section>

        {{-- Contact Section (Livewire Component) --}}
        {{--
             Anmerkung: Das Formular wird hier eingebunden.
             Da die Komponente selbst dunkel gestaltet wurde (wie im vorherigen Schritt),
             passt sie perfekt unter die dunkle CTA Section als nahtloser Abschluss.
        --}}
        @livewire('global.widgets.contact-form')

    </x-sections.page-container>
</x-layouts.frontend_layout>
