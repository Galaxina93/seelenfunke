<x-layouts.frontend_layout>
    <x-sections.page-container>

        <section class="relative bg-white pt-32 pb-16 lg:pt-40 lg:pb-24 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-24 items-center">

                    {{-- Linke Seite: Text & CTA --}}
                    <div class="order-2 lg:order-1">
                        <div class="inline-flex items-center px-3 py-1 rounded-full bg-primary/10 text-primary-dark text-xs font-bold uppercase tracking-widest mb-6">
                            Exklusiv-Edition
                        </div>
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-serif font-bold text-gray-900 leading-tight mb-6">
                            Der <span class="text-primary">Seelen-Kristall</span>
                        </h1>
                        <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                            Ein massives Meisterwerk aus reinem K9-Kristallglas.
                            Geschaffen, um den einen Moment festzuhalten, der für immer bleibt.
                            Inklusive Lasergravur und edler Geschenkbox.
                        </p>

                        <div class="flex flex-col sm:flex-row gap-4 mb-10">
                            <a href="{{ route('calculator') }}" class="inline-flex justify-center items-center px-8 py-4 border border-transparent text-lg font-bold rounded-lg text-white bg-primary-dark hover:bg-primary transition-all shadow-lg hover:shadow-primary/40 transform hover:-translate-y-1">
                                Preis berechnen
                                <svg class="ml-2 -mr-1 w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                            <a href="#details" class="inline-flex justify-center items-center px-8 py-4 border-2 border-gray-200 text-lg font-bold rounded-lg text-gray-700 hover:border-primary hover:text-primary transition-colors">
                                Details ansehen
                            </a>
                        </div>

                        <div class="flex items-center gap-6 text-sm text-gray-500 font-medium">
                            <div class="flex items-center">
                                <span class="text-green-500 mr-2">✓</span> Sofort lieferbar
                            </div>
                            <div class="flex items-center">
                                <span class="text-green-500 mr-2">✓</span> Ab 1 Stück
                            </div>
                            <div class="flex items-center">
                                <span class="text-primary mr-2">★</span> Premium Qualität
                            </div>
                        </div>
                    </div>

                    {{-- Rechte Seite: Großes Produktbild --}}
                    <div class="order-1 lg:order-2 relative group">
                        {{-- Dekorativer Hintergrundkreis --}}
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[120%] bg-gradient-to-br from-gray-100 to-white rounded-full opacity-70 blur-3xl -z-10"></div>

                        <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-gray-100 bg-white p-4">
                            <img src="{{ asset('images/projekt/products/seelen-kristall_w.jpg') }}"
                                 alt="Seelen-Kristall Trophäe"
                                 class="w-full h-auto transform transition duration-1000 group-hover:scale-105">

                            {{-- Preis Badge --}}
                            <div class="absolute top-6 right-6 bg-white/95 backdrop-blur shadow-lg rounded-xl p-4 text-center border border-gray-100">
                                <span class="block text-gray-500 text-xs uppercase font-bold">Ab</span>
                                <span class="block text-2xl font-bold text-gray-900">39,90 €</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{--
            SECTION 2: HIGHLIGHTS (Icons)
            Warum dieses Produkt?
        --}}
        <section id="details" class="py-16 bg-gray-50 border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {{-- Feature 1 --}}
                    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center text-primary text-2xl mb-6">
                            💎
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Massives K9 Kristallglas</h3>
                        <p class="text-gray-600">Kein Acryl, kein Plastik. Unser Glas wiegt schwer in der Hand und bricht das Licht in spektralen Farben. Ein Unterschied, den man fühlt.</p>
                    </div>

                    {{-- Feature 2 --}}
                    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center text-primary text-2xl mb-6">
                            ✨
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Hochpräzise Lasergravur</h3>
                        <p class="text-gray-600">Ihr Logo und Text werden dauerhaft in das Glas graviert. Kratzfest, spülmaschinenfest und gestochen scharf bis ins kleinste Detail.</p>
                    </div>

                    {{-- Feature 3 --}}
                    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center text-primary text-2xl mb-6">
                            🎁
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Geschenkbox inklusive</h3>
                        <p class="text-gray-600">Jeder Seelen-Kristall kommt in einer hochwertigen, mit Seide ausgelegten Geschenkbox. Bereit für die feierliche Übergabe.</p>
                    </div>
                </div>
            </div>
        </section>

        {{--
            SECTION 3: TECHNISCHE DATEN & EMOTION
            Split Screen: Links Daten, Rechts Bild
        --}}
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

                    {{-- Linke Seite: Bild --}}
                    <div class="relative">
                        <img src="{{ asset('images/projekt/other/appreciation.png') }}"
                             alt="Seelen-Kristall Detail"
                             class="rounded-lg shadow-2xl w-full object-cover h-[500px]">
                        {{-- Kleines Detailbild overlay --}}
                        <div class="absolute -bottom-6 -right-6 w-48 h-48 bg-white p-2 rounded-lg shadow-xl hidden md:block">
                            <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" class="w-full h-full object-contain border border-gray-100 rounded">
                        </div>
                    </div>

                    {{-- Rechte Seite: Tabelle --}}
                    <div>
                        <h2 class="text-3xl font-serif font-bold text-gray-900 mb-6">
                            Details, die überzeugen.
                        </h2>
                        <p class="text-gray-600 mb-8 text-lg">
                            Der Seelen-Kristall ist zeitlos. Durch den aufwendigen Facettenschliff an den Kanten fängt er das Umgebungslicht ein und lässt die Lasergravur hell erstrahlen.
                        </p>

                        <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                            <h3 class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-4">Technische Spezifikationen</h3>
                            <dl class="space-y-4">
                                <div class="flex justify-between border-b border-gray-200 pb-2">
                                    <dt class="text-gray-600">Material</dt>
                                    <dd class="font-bold text-gray-900">K9 Hochleistungs-Kristallglas</dd>
                                </div>
                                <div class="flex justify-between border-b border-gray-200 pb-2">
                                    <dt class="text-gray-600">Maße</dt>
                                    <dd class="font-bold text-gray-900">160mm*180mm*40mm</dd>
                                </div>
                                <div class="flex justify-between border-b border-gray-200 pb-2">
                                    <dt class="text-gray-600">Gewicht</dt>
                                    <dd class="font-bold text-gray-900">ca. 930g (Massiv)</dd>
                                </div>
                                <div class="flex justify-between border-b border-gray-200 pb-2">
                                    <dt class="text-gray-600">Veredelung</dt>
                                    <dd class="font-bold text-gray-900">UV-Lasergravur (Weiß-Effekt)</dd>
                                </div>
                                <div class="flex justify-between pt-2">
                                    <dt class="text-gray-600">Verpackung</dt>
                                    <dd class="font-bold text-gray-900">Geschenkbox</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="mt-8">
                            <p class="text-sm text-gray-500 mb-4 italic">
                                * Hinweis: Da es sich um ein handveredeltes Produkt handelt, sind minimale Abweichungen möglich.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        {{--
            SECTION 4: PREIS-KALKULATOR CTA
            Der "Abschluss"-Bereich
        --}}
        <section class="py-20 bg-primary-dark text-white relative overflow-hidden">
            {{-- Background Pattern --}}
            <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>

            <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
                <h2 class="text-3xl sm:text-4xl font-serif font-bold mb-6">
                    Bereit für echte Wertschätzung?
                </h2>
                <p class="text-lg text-gray-300 mb-10 max-w-2xl mx-auto">
                    Egal ob Einzelstück für den besten Mitarbeiter oder 100 Stück für das ganze Turnier.
                    Nutzen Sie unseren Kalkulator für sofortige Staffelpreise.
                </p>

                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="{{ route('calculator') }}" class="inline-flex justify-center items-center px-8 py-4 border border-transparent text-lg font-bold rounded-lg text-primary-dark bg-white hover:bg-gray-100 transition-all shadow-xl hover:scale-105">
                        Zum Angebotskalkulator
                    </a>
                    <a href="#contact" class="inline-flex justify-center items-center px-8 py-4 border border-white text-lg font-bold rounded-lg text-white hover:bg-white hover:text-primary-dark transition-colors">
                        Frage stellen
                    </a>
                </div>

                <p class="mt-8 text-sm text-gray-400">
                    Keine versteckten Kosten. Angebot sofort als PDF.
                </p>
            </div>
        </section>

        {{--Contact Section--}}
        @livewire('global.widgets.contact-form')

    </x-sections.page-container>
</x-layouts.frontend_layout>
