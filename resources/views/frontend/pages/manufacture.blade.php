<x-layouts.frontend_layout>
    <x-sections.page-container>

        {{--
                    NEUE SECTION: DIE GRÜNDERIN (Alina Steinhauer)
                    Modern, interaktiv und mit Fokus auf Langfristigkeit
                --}}
        <section class="py-24 bg-white overflow-hidden" aria-labelledby="founder-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="flex flex-col lg:flex-row items-center gap-16">

                    {{-- Linke Seite: Interaktives Profilbild mit Link zu #contact --}}
                    <div class="w-full lg:w-5/12 relative">
                        <a href="#contact" class="relative z-10 group cursor-pointer block">
                            {{-- Dekorativer Hintergrund-Shape --}}
                            <div class="absolute -inset-6 bg-primary/10 rounded-full scale-95 group-hover:scale-110 transition-transform duration-700 ease-in-out opacity-50 blur-2xl" aria-hidden="true"></div>

                            {{-- Das Bild mit Rahmen-Animation --}}
                            <div class="relative overflow-hidden rounded-2xl shadow-2xl aspect-[4/5] border-4 border-white transform transition-all duration-500 group-hover:-translate-y-2 group-hover:shadow-primary/20">
                                <img src="{{ asset('images/projekt/about/gruender-profil-b.jpg') }}"
                                     alt="Alina Steinhauer – Gründerin von Mein-Seelenfunke"
                                     class="object-cover w-full h-full transform transition-scale duration-1000 group-hover:scale-110">

                                {{-- Herz-Icon & "Kontakt"-Hinweis Overlay bei Hover --}}
                                <div class="absolute inset-0 bg-primary/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col items-center justify-center">
                                    <div class="bg-white/90 p-4 rounded-full animate-pulse mb-4">
                                        <svg class="w-8 h-8 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                    </div>
                                    <span class="text-white font-bold tracking-wider uppercase text-sm drop-shadow-md">Jetzt Kontakt aufnehmen</span>
                                </div>
                            </div>

                            {{-- Experience-Badge --}}
                            <div class="absolute -right-6 -bottom-6 bg-gray-900 text-white p-6 rounded-xl shadow-2xl transform transition-transform group-hover:rotate-6 group-hover:scale-110 duration-300">
                                <span class="block text-3xl font-bold text-primary">14+</span>
                                <span class="text-[10px] uppercase tracking-widest font-bold">Jahre IT-Power</span>
                            </div>
                        </a>
                    </div>

                    {{-- Rechte Seite: Content & Story --}}
                    <div class="w-full lg:w-7/12">
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-sm font-bold mb-6">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                            </span>
                            <span class="uppercase tracking-wider">The Heartbeat of Seelenfunke</span>
                        </div>

                        <h2 id="founder-heading" class="text-4xl lg:text-5xl font-serif font-bold text-gray-900 mb-6 leading-tight">
                            Alina Steinhauer: <br>
                            <span class="text-primary italic">Hingabe</span> in jedem Pixel & jedem Strahl.
                        </h2>

                        <div class="prose prose-lg text-gray-600 mb-10">
                            <p>
                                Mein Weg begann vor über 14 Jahren in der Welt der Bits und Bytes. Als IT-Spezialistin habe ich gelernt, dass wahre Qualität nur durch Struktur, Geduld und den Blick für das kleinste Detail entsteht. Ich habe ganze Online-Portale aus dem Boden gestampft und komplexe digitale Architekturen entworfen.
                            </p>
                            <p class="font-medium text-gray-900">
                                Doch „Mein-Seelenfunke“ ist kein Projekt von vielen. Es ist mein absolutes Herzensprojekt.
                            </p>
                            <p>
                                Ich bin nicht hier, um von einer Idee zur nächsten zu springen. Mein Ziel ist es, diese Firma mit 100% Fokus, Liebe und handwerklicher Perfektion langfristig zum Erfolg zu führen. Wenn Sie einen Seelen-Kristall in den Händen halten, halten Sie ein Stück meiner Vision und meiner Beständigkeit fest.
                            </p>
                        </div>

                        {{-- Skills mit rotierenden Icons bei Hover --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="group flex items-center space-x-4 p-4 bg-gray-50 rounded-xl transition-colors hover:bg-primary/5">
                                <div class="bg-white p-3 rounded-lg shadow-sm text-primary transition-transform duration-500 group-hover:rotate-[360deg]">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-14m-3-3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <span class="font-bold text-gray-800">Präzisions-Entwicklerin</span>
                            </div>

                            <div class="group flex items-center space-x-4 p-4 bg-gray-50 rounded-xl transition-colors hover:bg-primary/5">
                                <div class="bg-white p-3 rounded-lg shadow-sm text-primary transition-transform duration-500 group-hover:rotate-[360deg]">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <span class="font-bold text-gray-800">Technik-Visionärin</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Detaillierter Jahresverlauf --}}
                <div class="mt-24 relative">
                    {{-- Horizontale Linie (nur sichtbar ab Desktop, da mobil vertikaler Stack) --}}
                    <div class="absolute inset-0 hidden md:flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>

                    <div class="relative flex flex-col md:flex-row justify-between items-center md:items-start gap-12 md:gap-4">

                        {{-- Station 1: 2012-2015 --}}
                        <div class="bg-white px-4 group cursor-default max-w-xs flex flex-col items-center md:items-start text-center md:text-left">
                            <p class="text-primary font-bold text-2xl transition-transform duration-300 group-hover:scale-125 group-hover:-translate-y-1 inline-block">2012 — 2015</p>
                            <h4 class="text-gray-900 font-bold text-sm uppercase mt-2">Das Fundament</h4>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                Ausbildung zur Fachinformatikerin für Systemintegration. Hier lernte ich die Basis: Netzwerke, Serverstrukturen und Troubleshooting von der Pike auf.
                            </p>
                        </div>

                        {{-- Station 2: 2015-2019 --}}
                        <div class="bg-white px-4 group cursor-default max-w-xs flex flex-col items-center md:items-start text-center md:text-left">
                            <p class="text-primary font-bold text-2xl transition-transform duration-300 group-hover:scale-125 group-hover:-translate-y-1 inline-block">2015 — 2019</p>
                            <h4 class="text-gray-900 font-bold text-sm uppercase mt-2">IT-Operations & Support</h4>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                Einsatz im 1st- und 2nd-Level-Support, unter anderem für das DLR. Verantwortung für komplexe IT-Umgebungen und Anwendersysteme.
                            </p>
                        </div>

                        {{-- Station 3: 2019-2023 --}}
                        <div class="bg-white px-4 group cursor-default max-w-xs flex flex-col items-center md:items-start text-center md:text-left">
                            <p class="text-primary font-bold text-2xl transition-transform duration-300 group-hover:scale-125 group-hover:-translate-y-1 inline-block">2019 — 2023</p>
                            <h4 class="text-gray-900 font-bold text-sm uppercase mt-2">Web-Engineering</h4>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                Spezialisierung auf Full-Stack-Entwicklung. Umsetzung von über 30 Webprojekten und komplexen Portallösungen mit Laravel, Vue.js und SQL.
                            </p>
                        </div>

                        {{-- Station 4: HEUTE --}}
                        <div class="bg-white px-4 group cursor-default max-w-xs flex flex-col items-center md:items-start text-center md:text-left">
                            <p class="text-primary font-bold text-2xl transition-transform duration-300 group-hover:scale-125 group-hover:-translate-y-1 inline-block">Heute</p>
                            <h4 class="text-gray-900 font-bold text-sm uppercase mt-2">„Mein-Seelenfunke“</h4>
                            <p class="text-xs text-gray-600 font-semibold mt-1 leading-relaxed italic">
                                Vollendung der Vision: Die Verschmelzung von IT-Präzision und emotionaler Glaskunst als langfristiges Lebenswerk.
                            </p>
                        </div>

                    </div>
                </div>

                {{-- Kleiner Tech-Stapel für den visuellen "Beweis" --}}
                <div class="mt-16 flex flex-wrap justify-center gap-8 opacity-40 grayscale hover:grayscale-0 transition-all duration-500">
                    <span class="text-xs font-bold tracking-widest uppercase">PHP / Laravel</span>
                    <span class="text-xs font-bold tracking-widest uppercase">JavaScript / Vue</span>
                    <span class="text-xs font-bold tracking-widest uppercase">MySQL</span>
                    <span class="text-xs font-bold tracking-widest uppercase">IT-Infrastruktur</span>
                    <span class="text-xs font-bold tracking-widest uppercase">Laser-Technologie</span>
                </div>

            </div>
        </section>

        {{--
            SECTION 1: HERO (Dunkel & Edel)
            Fokus auf das Team und Technik.
        --}}
        <section class="relative bg-gray-900 pt-32 pb-20 lg:pt-40 lg:pb-28 overflow-hidden" aria-label="Einführung Manufaktur">
            {{-- Hintergrund-Element --}}
            <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]" aria-hidden="true"></div>

            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <span class="text-primary font-bold tracking-widest uppercase text-sm mb-4 block">
                    Made in Germany
                </span>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-serif font-bold text-white leading-tight mb-6">
                    Wo Licht auf <span class="text-primary">Materie</span> trifft.
                </h1>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto leading-relaxed">
                    Qualität entsteht nicht durch Zufall. Sie ist das Ergebnis von hochwertigen Rohstoffen,
                    modernster <strong>Lasertechnologie</strong> und unserer gemeinsamen Leidenschaft für das Detail.
                    Willkommen in unserer Manufaktur in Gifhorn.
                </p>
            </div>
        </section>

        {{--
            SECTION 2: DIE PHILOSOPHIE
            Warum macht IHR das? (Corporate Branding)
        --}}
        <section class="py-16 bg-white border-b border-gray-100" aria-labelledby="philosophie-heading">
            <div class="max-w-4xl mx-auto px-4 text-center">
                <h2 id="philosophie-heading" class="text-3xl font-serif font-bold text-gray-900 mb-6">Unsere Philosophie: Fokus statt Masse</h2>
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
        <section class="py-20 bg-gray-50" aria-labelledby="prozess-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">

                {{-- Header für Screenreader / SEO Struktur --}}
                <h2 id="prozess-heading" class="sr-only">Der Fertigungsprozess unserer Glas-Unikate</h2>

                {{-- SCHRITT 1: Das Rohmaterial --}}
                <article class="flex flex-col md:flex-row items-center gap-12">
                    <div class="w-full md:w-1/2 relative group">
                        <div class="absolute -inset-4 bg-primary/20 rounded-xl transform rotate-2 transition-transform group-hover:rotate-1" aria-hidden="true"></div>
                        <img src="{{ asset('images/projekt/other/k9_glas.png') }}"
                             alt="Hochreiner K9 Kristallglas Rohling vor der Gravur"
                             loading="lazy"
                             class="relative rounded-lg shadow-xl w-full object-cover h-80">
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="flex items-center mb-4">
                            <span class="text-5xl font-serif text-gray-200 font-bold mr-4" aria-hidden="true">01</span>
                            <h3 class="text-2xl font-bold text-gray-900">Das Material: K9 Kristall</h3>
                        </div>
                        <p class="text-gray-600 leading-relaxed mb-4">
                            Normales Glas hat oft einen Grünstich und Einschlüsse. Wir verwenden für unsere Arbeit ausschließlich <strong>K9 Hochleistungskristall</strong>.
                            Dieses optische Glas wird sonst für Linsen und Prismen verwendet.
                        </p>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start">
                                <span class="text-primary mr-2" aria-hidden="true">✓</span> Extreme Klarheit und Brillanz
                            </li>
                            <li class="flex items-start">
                                <span class="text-primary mr-2" aria-hidden="true">✓</span> Hohes Eigengewicht (Fühlt sich wertig an)
                            </li>
                            <li class="flex items-start">
                                <span class="text-primary mr-2" aria-hidden="true">✓</span> Von uns persönlich ausgewählt
                            </li>
                        </ul>
                    </div>
                </article>

                {{-- SCHRITT 2: Der Laser (Rechts Bild, Links Text) --}}
                <article class="flex flex-col md:flex-row-reverse items-center gap-12">
                    <div class="w-full md:w-1/2 relative group">
                        <div class="absolute -inset-4 bg-gray-200 rounded-xl transform -rotate-2 transition-transform group-hover:-rotate-1" aria-hidden="true"></div>
                        <img src="{{ asset('images/projekt/process/lasergravur.png') }}"
                             onerror="this.src='https://placehold.co/600x400/333/FFF?text=Laser+Technologie'"
                             alt="Präzise 3D-Lasergravur im Inneren des Glases"
                             loading="lazy"
                             class="relative rounded-lg shadow-xl w-full object-cover h-80">
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="flex items-center mb-4">
                            <span class="text-5xl font-serif text-gray-200 font-bold mr-4" aria-hidden="true">02</span>
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
                </article>

                {{-- SCHRITT 3: Finish & Kontrolle --}}
                <article class="flex flex-col md:flex-row items-center gap-12">
                    <div class="w-full md:w-1/2 relative group">
                        <div class="absolute -inset-4 bg-primary/20 rounded-xl transform rotate-2 transition-transform group-hover:rotate-1" aria-hidden="true"></div>
                        <img src="{{ asset('images/projekt/other/white_hands.png') }}"
                             alt="Manuelle Endkontrolle und Reinigung des Kristalls mit Handschuhen"
                             loading="lazy"
                             class="relative rounded-lg shadow-xl w-full object-cover h-80">
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="flex items-center mb-4">
                            <span class="text-5xl font-serif text-gray-200 font-bold mr-4" aria-hidden="true">03</span>
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
                </article>

            </div>
        </section>

        {{--
            SECTION 4: ZERTIFIZIERUNG / FACTS
            Unternehmens-Kompetenznachweis
        --}}
        <section class="py-16 bg-white" aria-labelledby="kompetenz-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gray-900 rounded-2xl p-8 md:p-12 text-center shadow-2xl relative overflow-hidden">
                    <div class="relative z-10">
                        <h2 id="kompetenz-heading" class="text-2xl font-bold text-white mb-4">Unsere Kompetenz für Ihre Sicherheit</h2>
                        <p class="text-gray-300 max-w-2xl mx-auto mb-8">
                            Wir arbeiten mit Hochleistungslasern der Klasse 4. Um höchste Qualität und Sicherheit zu gewährleisten,
                            haben wir uns intensiv fortgebildet und prüfen lassen.
                        </p>
                        <div class="flex justify-center gap-4 flex-wrap">
                            <div class="px-5 py-3 bg-white/10 rounded-lg border border-white/20 text-white font-medium flex items-center">
                                <span class="text-primary mr-2" aria-hidden="true">✓</span> Zertifizierte Laserschutzbeauftragte
                            </div>
                            <div class="px-5 py-3 bg-white/10 rounded-lg border border-white/20 text-white font-medium flex items-center">
                                <span class="text-primary mr-2" aria-hidden="true">✓</span> Fachkunde nach TROS
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{--
            SECTION 5: CTA
        --}}
        <section class="py-20 text-center" aria-labelledby="cta-heading">
            <h2 id="cta-heading" class="text-3xl font-serif font-bold text-gray-900 mb-6">Überzeugen Sie sich selbst</h2>
            <p class="text-gray-600 max-w-xl mx-auto mb-8">
                Ein Bild sagt mehr als tausend Worte, aber unsere Arbeit in den Händen zu halten, überzeugt sofort.
            </p>
            <a href="{{ route('product.detail') }}"
               title="Zum Seelen-Kristall Produkt"
               class="inline-flex justify-center items-center px-8 py-4 border border-transparent text-lg font-bold rounded-lg text-white bg-primary hover:bg-primary-dark transition-all shadow-lg hover:-translate-y-1">
                Zum Seelen-Kristall
            </a>
        </section>

        {{--Contact Section--}}
        @livewire('global.widgets.contact-form')

    </x-sections.page-container>
</x-layouts.frontend_layout>
