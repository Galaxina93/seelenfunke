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
                        Planen Sie eine Ehrung für Ihren Verein oder benötigen Sie hochwertige Mitarbeitergeschenke?
                        Mit unserem interaktiven Kalkulator erstellen Sie sich sofort ein unverbindliches Angebot und erhalten eine automatische <strong>Mengenrabatt-Berechnung</strong>.
                    </p>
                </div>

                <div class="my-16 sm:mt-20 scroll-mt-24" id="calculator-area">
                    {{-- Hinweis: Stelle sicher, dass deine Komponente auch wirklich unter diesem Namen registriert ist --}}
                    @livewire('global.widgets.calculator')
                </div>

                <div class="bg-gray-50 px-6 py-12 sm:p-12 rounded-2xl shadow-sm border border-gray-100 mb-20">
                    <h3 class="text-xl sm:text-2xl font-serif font-bold text-center text-gray-900 mb-12">
                        So einfach funktioniert es
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-10 text-center">

                        <div class="group">
                            <div class="w-24 h-24 mx-auto bg-white rounded-full shadow-md flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300 border border-primary/20">
                                <img src="{{ asset('images/projekt/other/unikat.png') }}" alt="Unikat Icon" class="w-18 h-18 object-contain">
                            </div>
                            <h4 class="font-bold text-gray-900 text-lg mb-3">1. Unikate auswählen</h4>
                            <p class="text-sm sm:text-base text-gray-600 px-4">
                                Wählen Sie aus unseren hochwertigen Glas-Trophäen, Schlüsselanhängern oder Zubehör.
                            </p>
                        </div>

                        <div class="group">
                            <div class="w-24 h-24 mx-auto bg-white rounded-full shadow-md flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300 border border-primary/20">
                                <img src="{{ asset('images/projekt/other/menge.png') }}" alt="Menge Icon" class="w-18 h-18 object-contain">
                            </div>
                            <h4 class="font-bold text-gray-900 text-lg mb-3">2. Menge bestimmen</h4>
                            <p class="text-sm sm:text-base text-gray-600 px-4">
                                Geben Sie die gewünschte Stückzahl an. Unser System berechnet sofort Ihren <strong>Vorteilspreis</strong>.
                            </p>
                        </div>

                        <div class="group">
                            <div class="w-24 h-24 mx-auto bg-white rounded-full shadow-md flex items-center justify-center mb-5 group-hover:scale-110 transition-transform duration-300 border border-primary/20">
                                <img src="{{ asset('images/projekt/other/angebot.png') }}" alt="Angebot Icon" class="w-18 h-18 object-contain">
                            </div>
                            <h4 class="font-bold text-gray-900 text-lg mb-3">3. Angebot erhalten</h4>
                            <p class="text-sm sm:text-base text-gray-600 px-4">
                                Sie erhalten Ihr detailliertes Angebot sofort als PDF per E-Mail.
                            </p>
                        </div>

                    </div>
                </div>

                <div class="text-center mt-16 border-t border-gray-100 pt-10">
                    <p class="text-gray-500 text-lg">
                        Haben Sie Sonderwünsche oder benötigen Sie eine Spezialanfertigung?<br>
                        Schreiben Sie uns direkt an <a href="mailto:kontakt@mein-seelenfunke.de" class="text-primary hover:underline font-semibold">kontakt@mein-seelenfunke.de</a>.
                    </p>
                </div>

            </div>
        </section>

    </x-sections.page-container>
</x-layouts.frontend_layout>
