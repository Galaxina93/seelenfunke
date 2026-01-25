<x-layouts.frontend_layout>
    <x-sections.page-container>

        {{--
                SECTION 1: HERO (Dunkel & Edel)
                Fokus auf das Team und Technik.
            --}}
        <section class="relative bg-gray-900 pt-32 pb-20 lg:pt-40 lg:pb-28 overflow-hidden">
            {{-- Hintergrund-Element --}}
            <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <span class="text-primary font-bold tracking-widest uppercase text-sm mb-4 block">
                Made in Germany
            </span>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-serif font-bold text-white leading-tight mb-6">
                    Wo Licht auf <span class="text-primary">Materie</span> trifft.
                </h1>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto leading-relaxed">
                    Qualität entsteht nicht durch Zufall. Sie ist das Ergebnis von hochwertigen Rohstoffen,
                    modernster Lasertechnologie und unserer gemeinsamen Leidenschaft für das Detail.
                    Willkommen in unserer Manufaktur.
                </p>
            </div>
        </section>

        {{--
            SECTION 2: DIE PHILOSOPHIE
            Warum macht IHR das? (Corporate Branding)
        --}}
        <section class="py-16 bg-white border-b border-gray-100">
            <div class="max-w-4xl mx-auto px-4 text-center">
                <h2 class="text-3xl font-serif font-bold text-gray-900 mb-6">Unsere Philosophie: Fokus statt Masse</h2>
                <p class="text-gray-600 text-lg leading-relaxed">
                    In einer Welt voller Massenware haben wir uns bewusst für einen anderen Weg entschieden.
                    Anstatt hunderte verschiedene Plastik-Pokale anzubieten, konzentrieren wir uns voll und ganz auf
                    <strong>ein perfektes Material: K9 Kristallglas</strong>. Wir kennen jede Facette, jeden Lichtbrechungswinkel
                    und die exakten Lasereinstellungen, um dieses Material zum Leuchten zu bringen.
                </p>
            </div>
        </section>

        {{--
            SECTION 3: DER PROZESS (Zig-Zag Layout)
            Detaillierte Erklärung der Schritte aus EURER Sicht
        --}}
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">

                {{-- SCHRITT 1: Das Rohmaterial --}}
                <div class="flex flex-col md:flex-row items-center gap-12">
                    <div class="w-full md:w-1/2 relative group">
                        <div class="absolute -inset-4 bg-primary/20 rounded-xl transform rotate-2 transition-transform group-hover:rotate-1"></div>
                        <img src="{{ asset('images/projekt/other/k9_glas.png') }}"
                             alt="K9 Rohling"
                             class="relative rounded-lg shadow-xl w-full object-cover h-80">
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="flex items-center mb-4">
                            <span class="text-5xl font-serif text-gray-200 font-bold mr-4">01</span>
                            <h3 class="text-2xl font-bold text-gray-900">Das Material: K9 Kristall</h3>
                        </div>
                        <p class="text-gray-600 leading-relaxed mb-4">
                            Normales Glas hat oft einen Grünstich und Einschlüsse. Wir verwenden für unsere Arbeit ausschließlich <strong>K9 Hochleistungskristall</strong>.
                            Dieses optische Glas wird sonst für Linsen und Prismen verwendet.
                        </p>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <span class="text-primary mr-2">✓</span> Extreme Klarheit und Brillanz
                            </li>
                            <li class="flex items-start">
                                <span class="text-primary mr-2">✓</span> Hohes Eigengewicht (Fühlt sich wertig an)
                            </li>
                            <li class="flex items-start">
                                <span class="text-primary mr-2">✓</span> Von uns persönlich ausgewählt
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- SCHRITT 2: Der Laser (Rechts Bild, Links Text) --}}
                <div class="flex flex-col md:flex-row-reverse items-center gap-12">
                    <div class="w-full md:w-1/2 relative group">
                        <div class="absolute -inset-4 bg-gray-200 rounded-xl transform -rotate-2 transition-transform group-hover:-rotate-1"></div>
                        <img src="{{ asset('images/projekt/process/lasergravur.png') }}"
                             onerror="this.src='https://placehold.co/600x400/333/FFF?text=Laser+Technologie'"
                             alt="Laser Gravur Prozess"
                             class="relative rounded-lg shadow-xl w-full object-cover h-80">
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="flex items-center mb-4">
                            <span class="text-5xl font-serif text-gray-200 font-bold mr-4">02</span>
                            <h3 class="text-2xl font-bold text-gray-900">Präzision & Sicherheit</h3>
                        </div>
                        <p class="text-gray-600 leading-relaxed mb-4">
                            Eine <strong>zertifizierte Laserschutzbeauftragte</strong> ist in unserem Unternehmen tätig. Mit modernster Lasertechnologie brennen wir Ihr Motiv dauerhaft in das Glas ein.
                            Dabei entstehen winzige, präzise Veränderungen im Material (Weiß-Effekt).
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            Das Ergebnis ist eine Veredelung, die im Gegensatz zum Bedrucken niemals verblasst. Wir fertigen mit technischem Verstand und sicherem Handwerk.
                        </p>
                    </div>
                </div>

                {{-- SCHRITT 3: Finish & Kontrolle --}}
                <div class="flex flex-col md:flex-row items-center gap-12">
                    <div class="w-full md:w-1/2 relative group">
                        <div class="absolute -inset-4 bg-primary/20 rounded-xl transform rotate-2 transition-transform group-hover:rotate-1"></div>
                        <img src="{{ asset('images/projekt/other/white_hands.png') }}"
                             alt="Qualitätskontrolle"
                             class="relative rounded-lg shadow-xl w-full object-cover h-80">
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="flex items-center mb-4">
                            <span class="text-5xl font-serif text-gray-200 font-bold mr-4">03</span>
                            <h3 class="text-2xl font-bold text-gray-900">Der weiße Handschuh</h3>
                        </div>
                        <p class="text-gray-600 leading-relaxed mb-4">
                            Bevor ein "Seelen-Kristall" in die Box kommt, durchläuft er unsere <strong>manuelle Endkontrolle</strong>.
                            Wir entfernen jeden Fingerabdruck, polieren das Glas und prüfen die Gravur auf absolute Makellosigkeit.
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            Erst wenn wir zu 100% zufrieden sind, betten wir Ihr Unikat in die mit Seide ausgeschlagene Geschenkbox.
                        </p>
                    </div>
                </div>

            </div>
        </section>

        {{--
            SECTION 4: ZERTIFIZIERUNG / FACTS
            Unternehmens-Kompetenznachweis
        --}}
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gray-900 rounded-2xl p-8 md:p-12 text-center shadow-2xl relative overflow-hidden">
                    <div class="relative z-10">
                        <h2 class="text-2xl font-bold text-white mb-4">Unsere Kompetenz für Ihre Sicherheit</h2>
                        <p class="text-gray-300 max-w-2xl mx-auto mb-8">
                            Wir arbeiten mit Hochleistungslasern der Klasse 4. Um höchste Qualität und Sicherheit zu gewährleisten,
                            haben wir uns intensiv fortgebildet und prüfen lassen.
                        </p>
                        <div class="flex justify-center gap-4 flex-wrap">
                            <div class="px-5 py-3 bg-white/10 rounded-lg border border-white/20 text-white font-medium flex items-center">
                                <span class="text-primary mr-2">✓</span> Zertifizierte Laserschutzbeauftragte
                            </div>
                            <div class="px-5 py-3 bg-white/10 rounded-lg border border-white/20 text-white font-medium flex items-center">
                                <span class="text-primary mr-2">✓</span> Fachkunde nach TROS
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{--
            SECTION 5: CTA
        --}}
        <section class="py-20 text-center">
            <h2 class="text-3xl font-serif font-bold text-gray-900 mb-6">Überzeugen Sie sich selbst</h2>
            <p class="text-gray-600 max-w-xl mx-auto mb-8">
                Ein Bild sagt mehr als tausend Worte, aber unsere Arbeit in den Händen zu halten, überzeugt sofort.
            </p>
            <a href="{{ route('product.detail') }}" class="inline-flex justify-center items-center px-8 py-4 border border-transparent text-lg font-bold rounded-lg text-white bg-primary hover:bg-primary-dark transition-all shadow-lg hover:-translate-y-1">
                Zum Seelen-Kristall
            </a>
        </section>

        {{--Contact Section--}}
        @livewire('global.widgets.contact-form')

    </x-sections.page-container>
</x-layouts.frontend_layout>
