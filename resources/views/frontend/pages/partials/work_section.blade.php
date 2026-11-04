<section id="use-cases" class="bg-white overflow-hidden" aria-label="Anwendungsbereiche und Zielgruppen">

    {{-- ========================================== --}}
    {{-- 1. PRIVATE ANLÄSSE (Emotionale Geschenke) --}}
    {{-- ========================================== --}}
    <div id="fuer-herzensmenschen" class="relative bg-primary-dark overflow-hidden">
        {{-- Bild rechtsbündig auf Desktop --}}
        <div class="h-56 sm:h-72 md:absolute md:right-0 md:h-full md:w-1/2">
            <img class="w-full h-full object-cover opacity-90"
                 src="{{ asset('shop/frontend/other/trophy.webp') }}"
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
                    Unvergessliche <span class="text-primary">Momente</span> zum Anfassen
                </h3>
                <p class="mt-4 text-gray-300 text-lg leading-relaxed">
                    Ein persönliches Geschenk berührt dort, wo Worte allein nicht mehr ausreichen. Ob vergoldeter Edelstahl-Herzschmuck, ein massiver Talisman mit Koordinaten, eine gravierte Hundemarke, ein 360° Weizenglas oder unser K9-Seelen-Kristall: Wir verewigen deine Geschichte für die Ewigkeit.
                </p>
                <ul class="mt-6 space-y-3 text-white text-lg list-none">
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl shrink-0" aria-hidden="true">✨</span>
                        <span>Hochzeiten, Jahrestage & Herzensmenschen</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl shrink-0" aria-hidden="true">✨</span>
                        <span>Mama, Papa & unvergessliche Familienmomente</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl shrink-0" aria-hidden="true">✨</span>
                        <span>Treue Seelentiere & liebevolle Pfoten-Andenken</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="text-primary text-xl shrink-0" aria-hidden="true">✨</span>
                        <span>Geburtstage, Taufen & besondere Jubiläen</span>
                    </li>
                </ul>
                <div class="mt-8">
                    <a href="{{ route('shop') }}"
                       title="Zum Online-Shop für personalisierte Geschenke"
                       class="text-primary font-bold hover:text-white transition-colors border-b-2 border-primary pb-1">
                        Alle Unikate im Shop entdecken →
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
                 src="{{ asset('shop/frontend/other/appreciation.webp') }}"
                 alt="Hochwertige Firmenawards, Vereinsgeschenke und Trophäen mit Lasergravur"
                 loading="lazy">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
            <div class="md:ml-auto md:w-1/2 md:pl-12 md:text-right">

                <div class="text-sm font-bold uppercase tracking-widest text-primary-dark">
                    Für B2B Kunden, Teams & Vereine
                </div>

                <h3 class="mt-2 text-gray-900 text-3xl font-serif font-bold sm:text-4xl">
                    Wertschätzung mit <span class="text-[#8a6820]">bleibendem Eindruck</span>
                </h3>

                <p class="mt-4 text-gray-600 text-lg leading-relaxed">
                    Wahre Anerkennung verdient echte Substanz statt kurzlebiger Streuartikel. Von repräsentativen <strong>K9-Kristall-Trophäen</strong> für Firmenjubiläen und Ehrenämter über <strong>360° gravierte Kristall-Weizengläser</strong> für den Vereins-Stammtisch bis hin zu edlen <strong>Edelstahl- und Aluminium-Schlüsselanhängern</strong> mit eigenem Vereins- oder Firmenlogo fertigen wir Ihre Serie präzise in unserer deutschen Manufaktur.
                </p>

                {{-- Liste: Rechtsbündig mit Icons rechts --}}
                <ul class="mt-8 space-y-4 text-gray-700 text-lg w-full">

                    {{-- Item 1 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right"><strong>Attraktive Mengenrabatte</strong> live im Kalkulator berechnen</span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">💎</span>
                    </li>

                    {{-- Item 2 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right"><strong>Eigenes Firmen- oder Vereinslogo</strong> inklusive Präzisionsgravur</span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">🎁</span>
                    </li>

                    {{-- Item 3 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right">Bequemer <strong>Rechnungskauf</strong> für Firmen, Behörden & Vereine</span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">📄</span>
                    </li>

                    {{-- Item 4 --}}
                    <li class="flex items-start justify-end gap-3">
                        <span class="text-right">Sofort überreichfertig in edler <strong>Geschenkverpackung</strong></span>
                        <span class="text-primary text-xl flex-shrink-0 mt-1" aria-hidden="true">🏆</span>
                    </li>
                </ul>

                {{-- Button zum Kalkulator --}}
                <div class="mt-10 flex justify-center md:justify-end">
                    <a href="/calculator"
                       title="Individuelles Angebot für Firmenawards und Vereinsgeschenke berechnen"
                       class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-gray-900 hover:bg-primary transition shadow-lg transform hover:-translate-y-1">
                        Jetzt Serien-Preis berechnen
                        <svg class="ml-2 -mr-1 w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>

            </div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- 3. MANUFAKTUR & DIE 5 ENORMEN VORTEILE --}}
    {{-- ========================================== --}}
    <div id="manufaktur" class="relative bg-primary-dark overflow-hidden">
        {{-- Bild rechtsbündig --}}
        <div class="h-56 sm:h-72 md:absolute md:right-0 md:h-full md:w-1/2">
            <img class="w-full h-full object-cover hover:grayscale-0 transition-all duration-700"
                 src="{{ asset('shop/frontend/other/handmade.webp') }}"
                 alt="Lasergravur Manufaktur in Gifhorn, Deutschland"
                 loading="lazy">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
            <div class="md:w-1/2 md:pr-12">
                <div class="text-sm font-bold uppercase tracking-widest text-primary">
                    Die Manufaktur
                </div>
                <h3 class="mt-2 text-white text-3xl font-serif font-bold sm:text-4xl">
                    Handveredelt in <span class="text-primary">Deutschland</span>
                </h3>
                <p class="mt-4 text-gray-300 text-lg leading-relaxed">
                    Bei <strong>Mein Seelenfunke</strong> entsteht jedes Schmuckstück, jedes Glas und jeder Kristall in unserer eigenen Werkstatt in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}. Mit höchster Laserpräzision, echter Handarbeit und kurzen Wegen.
                </p>
                <ul class="mt-6 space-y-3 text-white text-lg list-none">
                    <li class="flex items-center gap-3">
                        <x-heroicon-s-check class="w-6 h-6 text-primary shrink-0" />
                        <span>High-End 5W UV- & MOPA-Faserlaser-Präzision</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <x-heroicon-s-check class="w-6 h-6 text-primary shrink-0" />
                        <span>Deutsche Manufaktur mit Sitz in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <x-heroicon-s-check class="w-6 h-6 text-primary shrink-0" />
                        <span>Interaktiver Live-3D- & 2D-Echtzeit-Konfigurator</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <x-heroicon-s-check class="w-6 h-6 text-primary shrink-0" />
                        <span>Eingetragene Marke (DPMA) & geprüfte Fachkunde</span>
                    </li>
                    <li class="flex items-center gap-3">
                        <x-heroicon-s-check class="w-6 h-6 text-primary shrink-0" />
                        <span>Deutschsprachiger Support & optionaler Geschenkbox-Service</span>
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
                 src="{{ funki_asset('shop/projekt/funki/funki.webp') }}"
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
                    @php
                        $workSupportEmail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
                    @endphp
                    <li class="group">
                        <a href="mailto:{{ $workSupportEmail }}"
                           title="E-Mail an den Kundenservice senden"
                           class="flex items-center gap-4 flex-row md:flex-row-reverse hover:text-[#8a6820] transition-colors">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center text-[#8a6820] group-hover:bg-primary group-hover:text-gray-900 transition-colors">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                            </div>
                            <span class="font-semibold">{{ $workSupportEmail }}</span>
                        </a>
                    </li>

                    {{-- Social Media / TikTok --}}
                    <li class="group">
                        <a href="https://www.tiktok.com/@mein_seelenfunke"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="Mein Seelenfunke auf TikTok folgen"
                           class="flex items-center gap-4 flex-row md:flex-row-reverse hover:text-[#8a6820] transition-colors">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center text-[#8a6820] group-hover:bg-primary group-hover:text-gray-900 transition-colors">
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
