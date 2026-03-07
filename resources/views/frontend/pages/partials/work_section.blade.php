<section id="use-cases" class="bg-white overflow-hidden" aria-label="Anwendungsbereiche und Zielgruppen">

    {{-- ========================================== --}}
    {{-- 1. PRIVATE ANLÄSSE (Emotionale Geschenke) --}}
    {{-- ========================================== --}}
    <div id="fuer-herzensmenschen" class="relative bg-primary-dark overflow-hidden">
        {{-- Bild rechtsbündig auf Desktop --}}
        <div class="h-56 sm:h-72 md:absolute md:right-0 md:h-full md:w-1/2">
            <img class="w-full h-full object-cover opacity-90"
                 src="{{ asset('images/projekt/other/trophy.png') }}"
                 alt="Personalisierte 3D-Glasgeschenke und Trophäen für Hochzeiten"
                 loading="lazy">
            {{-- Goldener Overlay-Schleier --}}
            <div class="absolute inset-0 bg-primary-dark/20"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
            <div class="md:w-1/2 md:pr-12">
                {{-- SEO: Dies ist eine Dachzeile, keine Hauptüberschrift -> span/div statt h3 --}}
                <div class="text-sm font-bold uppercase tracking-widest text-primary">
                    Für Herzensmenschen
                </div>
                {{-- SEO: Die wichtige Aussage ist jetzt H3 --}}
                <h3 class="mt-2 text-white text-3xl font-serif font-bold sm:text-4xl">
                    Unvergessliche <span class="text-primary">Momente</span>
                </h3>
                <p class="mt-4 text-gray-300 text-lg">
                    Ein Geschenk sagt mehr als tausend Worte. Unsere Glas-Unikate sind perfekt für die emotionalen Höhepunkte des Lebens – veredelt durch präzise Lasergravur.
                </p>
                <ul class="mt-6 space-y-3 text-white text-lg list-none">
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl" aria-hidden="true">✨</span> Hochzeiten & Jahrestage
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl" aria-hidden="true">✨</span> Geburten & Taufen
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl" aria-hidden="true">✨</span> Erinnerungen an Haustiere
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl" aria-hidden="true">✨</span> Geburtstage & Feiertage
                    </li>
                </ul>
                <div class="mt-8">
                    <a href="{{ route('shop') }}"
                       title="Zum Online-Shop für personalisierte Geschenke"
                       class="text-primary font-bold hover:text-white transition-colors border-b-2 border-primary pb-1">
                        Zum Shop →
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- 2. FIRMEN & VEREINE (Awards / B2B) --}}
    {{-- ========================================== --}}
    <div id="fuer-firmen" class="relative bg-gray-50 overflow-hidden">
        {{-- Bild linksbündig auf Desktop --}}
        <div class="h-56 sm:h-72 md:absolute md:left-0 md:h-full md:w-1/2">
            <img class="w-full h-full object-cover"
                 src="{{ asset('images/projekt/other/appreciation.png') }}"
                 alt="Hochwertige Firmenawards und Trophäen aus Glas für Mitarbeiter"
                 loading="lazy">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
            <div class="md:ml-auto md:w-1/2 md:pl-12 md:text-right">

                <div class="text-sm font-bold uppercase tracking-widest text-primary-dark">
                    Für B2B Kunden & Vereine
                </div>

                <h3 class="mt-2 text-gray-900 text-3xl font-serif font-bold sm:text-4xl">
                    Der <span class="text-primary">Seelen-Kristall</span>
                </h3>

                <p class="mt-4 text-gray-600 text-lg leading-relaxed">
                    Wir glauben an Klasse statt Masse. Deshalb konzentrieren wir uns voll und ganz auf dieses Meisterstück aus massivem <strong>K9-Kristallglas</strong>. Die perfekte Wahl für besondere Ehrungen, Awards und Jubiläen.
                </p>

                {{-- Liste: Rechtsbündig mit Icons rechts --}}
                <ul class="mt-8 space-y-4 text-gray-700 text-lg w-full">

                    {{-- Item 1 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right"><strong>Mengenrabatte</strong> (ab 10 Stk.) automatisch berechnen</span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">💎</span>
                    </li>

                    {{-- Item 2 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right"><strong>Inklusive Lasergravur</strong> & Geschenkbox</span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">🎁</span>
                    </li>

                    {{-- Item 3 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right">Bequemer <strong>Rechnungskauf</strong> für Vereine</span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">📄</span>
                    </li>

                    {{-- Item 4 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right">Das Highlight für Jubiläen & Awards</span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">🏆</span>
                    </li>
                </ul>

                {{-- Button zum Kalkulator --}}
                <div class="mt-10 flex justify-center md:justify-end">
                    <a href="/calculator"
                       title="Individuelles Angebot für Firmenawards berechnen"
                       class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-gray-900 hover:bg-primary transition shadow-lg transform hover:-translate-y-1">
                        Jetzt Preis berechnen
                        <svg class="ml-2 -mr-1 w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>

            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- 3. MANUFAKTUR & VISION (Vertrauen aufbauen) --}}
    {{-- ========================================== --}}
    <div id="manufaktur" class="relative bg-primary-dark overflow-hidden">
        {{-- Bild rechtsbündig --}}
        <div class="h-56 sm:h-72 md:absolute md:right-0 md:h-full md:w-1/2">
            <img class="w-full h-full object-cover hover:grayscale-0 transition-all duration-700"
                 src="{{ asset('images/projekt/other/handmade.png') }}"
                 alt="Lasergravur Manufaktur in Gifhorn, Deutschland"
                 loading="lazy">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
            <div class="md:w-1/2 md:pr-12">
                <div class="text-sm font-bold uppercase tracking-widest text-primary">
                    Unsere Manufaktur
                </div>
                <h3 class="mt-2 text-white text-3xl font-serif font-bold sm:text-4xl">
                    Handveredelt in <span class="text-primary">Deutschland</span>
                </h3>
                <p class="mt-4 text-gray-300 text-lg">
                    Wir sind kein anonymer Großhändler. Jedes Stück wird in unserer Manufaktur in Gifhorn geprüft, gereinigt und mit modernster Laser-Technologie für dich personalisiert.
                </p>
                <ul class="mt-6 space-y-3 text-white text-lg list-none">
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl" aria-hidden="true">✔</span> Liebe zum Detail bei jedem Stück
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl" aria-hidden="true">✔</span> Schnelle Bearbeitung (1-3 Tage)
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl" aria-hidden="true">✔</span> Wir starten mit Glas und haben noch viel vor.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- 4. KONTAKT (Standard) --}}
    {{-- ========================================== --}}
    <div id="kontakt" class="relative bg-white overflow-hidden border-t border-gray-100">
        {{-- Bild linksbündig --}}
        <div class="h-56 sm:h-72 md:absolute md:left-0 md:h-full md:w-1/2 bg-white flex items-center justify-center">
            <img class="w-full h-full object-contain object-center p-4 md:p-0"
                 src="{{ asset('images/projekt/funki/funki.png') }}"
                 alt="Funki Maskottchen hilft bei Kontaktfragen"
                 loading="lazy">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
            <div class="md:ml-auto md:w-1/2 md:pl-12 md:text-right">
                <div class="text-sm font-bold uppercase tracking-widest text-primary-dark">
                    Noch Fragen?
                </div>

                <h3 class="mt-2 text-gray-900 text-3xl font-serif font-bold sm:text-4xl">
                    Wir sind für dich da.
                </h3>

                <p class="mt-4 text-lg text-gray-600">
                    Du hast eine spezielle Idee, eine Frage zum Foto oder möchtest eine größere Menge für deinen Verein bestellen? Schreib uns einfach!
                </p>

                <ul class="mt-8 space-y-4 text-gray-800 text-lg list-none inline-block text-left md:text-right w-full">
                    {{-- E-Mail --}}
                    <li class="group">
                        <a href="mailto:kontakt@mein-seelenfunke.de"
                           title="E-Mail an den Kundenservice senden"
                           class="flex items-center gap-4 flex-row md:flex-row-reverse hover:text-primary transition-colors">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                            </div>
                            <span class="font-semibold">kontakt@mein-seelenfunke.de</span>
                        </a>
                    </li>

                    {{-- Social Media / TikTok --}}
                    <li class="group">
                        <a href="https://www.tiktok.com/@mein_seelenfunke"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="Mein Seelenfunke auf TikTok folgen"
                           class="flex items-center gap-4 flex-row md:flex-row-reverse hover:text-primary transition-colors">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                {{-- TikTok Icon --}}
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-6 h-6"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                            </div>
                            <span class="font-semibold">Folge uns auf TikTok</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

</section>
