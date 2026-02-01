<x-layouts.frontend_layout>
    <x-sections.page-container>

        <section class="bg-white py-20 sm:py-20 md:py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-12">

                <div class="text-center mb-12 sm:mb-16">
                    <span class="text-primary font-serif italic text-lg mb-2 block">Transparenz & Schnelligkeit</span>
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-6">
                        Ihr Angebot in Sekunden
                    </h2>
                    <p class="text-base sm:text-lg text-gray-600 max-w-3xl mx-auto leading-relaxed">
                        In nur drei Schritten zum Angebot: <strong>Unikat auswählen</strong>, <strong>Menge festlegen</strong> und
                        <strong>sofort Ihr individuelles PDF-Angebot per E-Mail erhalten</strong> – inklusive automatischer Vorteilspreis-Berechnung.
                    </p>

                    <div class="w-full flex justify-center">
                        <div class="max-w-5xl w-full">

                            <div class="flex flex-col sm:flex-row justify-center items-stretch gap-8 mt-10">

                                <!-- Item -->
                                <div class="flex items-center gap-4 text-left w-full sm:w-80 mx-auto">
                                    <div class="w-24 h-24 flex-shrink-0 bg-white rounded-full shadow-sm flex items-center justify-center border border-primary/20">
                                        <img src="{{ asset('images/projekt/other/unikat.png') }}"
                                             alt="Unikat auswählen"
                                             class="w-16 h-16 object-contain">
                                    </div>
                                    <div class="flex flex-col justify-center">
                                        <p class="font-semibold text-gray-900 leading-tight">1. Unikat wählen</p>
                                        <p class="text-sm text-gray-600 leading-snug">
                                            Glas-Trophäen, Schlüsselanhänger oder Zubehör
                                        </p>
                                    </div>
                                </div>

                                <!-- Item -->
                                <div class="flex items-center gap-4 text-left w-full sm:w-80 mx-auto">
                                    <div class="w-24 h-24 flex-shrink-0 bg-white rounded-full shadow-sm flex items-center justify-center border border-primary/20">
                                        <img src="{{ asset('images/projekt/other/menge.png') }}"
                                             alt="Menge bestimmen"
                                             class="w-16 h-16 object-contain">
                                    </div>
                                    <div class="flex flex-col justify-center">
                                        <p class="font-semibold text-gray-900 leading-tight">2. Menge festlegen</p>
                                        <p class="text-sm text-gray-600 leading-snug">
                                            Automatische Vorteilspreis-Berechnung
                                        </p>
                                    </div>
                                </div>

                                <!-- Item -->
                                <div class="flex items-center gap-4 text-left w-full sm:w-80 mx-auto">
                                    <div class="w-24 h-24 flex-shrink-0 bg-white rounded-full shadow-sm flex items-center justify-center border border-primary/20">
                                        <img src="{{ asset('images/projekt/other/angebot.png') }}"
                                             alt="Angebot erhalten"
                                             class="w-16 h-16 object-contain">
                                    </div>
                                    <div class="flex flex-col justify-center">
                                        <p class="font-semibold text-gray-900 leading-tight">3. Angebot erhalten</p>
                                        <p class="text-sm text-gray-600 leading-snug">
                                            PDF sofort per E-Mail
                                        </p>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>


                </div>

                <div class="my-16 sm:mt-20 scroll-mt-24" id="calculator-area">
                    {{-- Hinweis: Stelle sicher, dass deine Komponente auch wirklich unter diesem Namen registriert ist --}}
                    @livewire('global.widgets.calculator')
                </div>

            </div>
        </section>

    </x-sections.page-container>
</x-layouts.frontend_layout>
