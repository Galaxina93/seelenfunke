<x-layouts.frontend_layout>

    <x-sections.page-container>

        <!-- Hero Section -->
        <section id="home"
                 class="relative pt-16 overflow-hidden text-white"
                 {{-- WICHTIG: Tausche das Bild gegen ein emotionales Produktbild (z.B. Trophäe im Dunkeln leuchtend) --}}
                 style="background: url('{{ asset('images/projekt/other/header_bg.png') }}') center/cover no-repeat;"
                 aria-label="Mein Seelenfunke - Personalisierte Geschenke">

            <div class="absolute inset-0 bg-primary-dark/80"></div> {{-- Nutzt dein neues Soft-Black mit 80% Deckkraft --}}

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32 md:py-48 relative z-10">
                <div class="text-center">

                    {{-- Hauptüberschrift --}}
                    <h1 class="text-3xl md:text-6xl font-serif font-bold mb-6 floating-animation leading-tight">
                        <span class="text-primary">Ein Funke, der bleibt.</span><br>
                        Personalisierte Unikate für die Ewigkeit.
                    </h1>

                    {{-- Unterüberschrift --}}
                    <p class="text-lg md:text-2xl mb-12 opacity-90 font-light max-w-3xl mx-auto">
                        Handveredelte Geschenke aus Glas, Schiefer & Metall. <br class="hidden md:block">
                        Erschaffe jetzt dein persönliches Leucht-Unikat.
                    </p>

                    {{-- Buttons --}}
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
                        {{-- Button 1: Hauptaktion (Zum Shop) --}}
                        <a href="{{ route('shop') }}"
                           class="bg-primary text-white px-8 py-4 rounded-full font-semibold text-lg hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 shadow-lg shadow-primary/30 pulse-button"
                           aria-label="Jetzt personalisieren">
                            JETZT Unikat bestellen
                        </a>

                        {{-- Button 2: Sekundär (Kosten Kalkulator B2B) --}}
                        <a href="/calculator" target="_blank"
                           class="bg-transparent border-2 border-primary text-primary px-8 py-4 rounded-full font-semibold text-lg hover:bg-primary hover:text-white transition-all transform hover:scale-105"
                           aria-label="Angebotskalkulator">
                            Angebotskalkulator öffnen
                        </a>
                    </div>

                    {{-- Trust-Elemente statt Telefonnummer --}}
                    <div class="flex flex-col md:flex-row justify-center items-center gap-6 text-white mt-8 opacity-80 text-sm md:text-base">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span>Made in Germany</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Schneller Versand</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            <span>Mit Liebe verpackt</span>
                        </div>
                    </div>

                </div>
            </div>

            <div class="absolute top-20 left-10 w-32 h-32 bg-primary opacity-20 blur-3xl rounded-full floating-animation"></div>
            <div class="absolute bottom-20 right-10 w-40 h-40 bg-primary-light opacity-20 blur-3xl rounded-full floating-animation" style="animation-delay: 1s;"></div>
        </section>

        <!-- Service Section -->
        <section id="services" class="bg-white text-black py-24 px-6 lg:px-12">
            <header class="text-center mb-16">
                <h2 class="text-primary-dark font-serif font-bold text-3xl sm:text-4xl lg:text-5xl">
                    Einzigartige Veredelungen
                </h2>
                <p class="mt-4 text-gray-600 text-base max-w-3xl mx-auto">
                    Wir starten mit unseren exklusiven Glas-Unikaten. Doch das ist erst der Anfang. Hier siehst du, welche Veredelungen wir in Zukunft anbieten werden.
                </p>
            </header>

            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                {{-- =====================================================================
                     1. GLAS & KRISTALL (AKTIV - Das Startprodukt)
                ===================================================================== --}}
                <article class="bg-white rounded-2xl shadow-xl overflow-hidden transition-transform hover:scale-105 duration-300 group ring-2 ring-primary ring-offset-4 relative z-10">
                    <figure class="overflow-hidden relative">
                        {{-- Badge: JETZT VERFÜGBAR --}}
                        <div class="absolute top-4 right-4 bg-primary text-white text-xs font-bold px-3 py-1 rounded-full z-20 shadow-md animate-pulse">
                            JETZT VERFÜGBAR
                        </div>
                        <img src="{{ asset('images/projekt/products/seelen-kristall_w.jpg') }}"
                             alt="Hochwertige Lasergravur auf Glas"
                             class="w-full h-56 object-cover transform group-hover:scale-110 transition-transform duration-500">
                    </figure>

                    <div class="bg-gradient-to-br from-primary to-primary-dark p-6 relative">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg absolute -top-8 right-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-primary">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-1">Glas & Kristall</h3>
                        <span class="text-white/80 text-sm font-medium tracking-wider">PREMIUM GRAVUR</span>
                    </div>

                    <div class="p-6">
                        <p class="text-gray-600 mb-4">Unsere Königsdisziplin. Wir veredeln hochwertiges K9-Kristallglas mit präzisen Lasergravuren, die wie gefrostet wirken.</p>
                        <ul class="text-sm text-gray-500 space-y-2 mb-6">
                            <li class="flex items-center gap-2"><span class="text-primary">✔</span> Individueller Text & Wunschmotiv</li>
                            <li class="flex items-center gap-2"><span class="text-primary">✔</span> Massives, schweres Kristallglas</li>
                            <li class="flex items-center gap-2"><span class="text-primary">✔</span> Standardmäßig in edler Box</li>
                        </ul>
                        <a href="{{ route('shop') }}" class="block text-center w-full bg-primary text-white py-2 rounded-md font-bold hover:bg-primary-dark transition-colors">
                            Zum Shop
                        </a>
                    </div>
                </article>

                {{-- 2. SCHIEFER & NATUR (INAKTIV) --}}
                <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">
                    {{-- OVERLAY BADGE --}}
                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                        <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">
                            DEMNÄCHST
                        </div>
                    </div>

                    <figure class="overflow-hidden">
                        <img src="{{ asset('images/projekt/products/schiefer.png') }}" alt="Personalisierter Schiefer" class="w-full h-56 object-cover">
                    </figure>
                    {{-- Header Grau statt Bunt --}}
                    <div class="bg-gray-600 p-6 relative">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-200 mb-1">Schiefer & Natur</h3>
                        <span class="text-gray-300/80 text-sm font-medium tracking-wider">RUSTIKALE UNIKATE</span>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-500 mb-4">Jedes Stück ein Unikat. Die natürliche Struktur des Schiefers sorgt für starke Kontraste.</p>
                        <ul class="text-sm text-gray-400 space-y-2"><li class="flex items-center gap-2">Isoliert & Wetterfest</li><li class="flex items-center gap-2">Untersetzer & Schilder</li></ul>
                    </div>
                </article>

                {{-- 3. METALL & BUSINESS (INAKTIV) --}}
                <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">
                    {{-- OVERLAY BADGE --}}
                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                        <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
                    </div>
                    <figure class="overflow-hidden">
                        <img src="{{ asset('images/projekt/products/liebesfunke-metallkarte.png') }}" alt="Lasergravur auf Metall" class="w-full h-56 object-cover">
                    </figure>
                    <div class="bg-gray-700 p-6 relative">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-200 mb-1">Metall & Business</h3>
                        <span class="text-gray-300/80 text-sm font-medium tracking-wider">MODERNE ELEGANZ</span>
                    </div>
                    <div class="p-6"><p class="text-gray-500 mb-4">Hauchdünne Metallkarten und Visitenkarten aus Aluminium oder Edelstahl.</p></div>
                </article>

                {{-- 4. KLEINARTIKEL (INAKTIV) --}}
                <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">

                    {{-- OVERLAY BADGE --}}
                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                        <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
                    </div>

                    <figure class="overflow-hidden">
                        {{-- Bild-Pfad bitte entsprechend anpassen, z.B. auf einen Schlüsselanhänger --}}
                        <img src="{{ asset('images/projekt/products/flaschenoeffner.png') }}" alt="Personalisierte Schlüsselanhänger und Öffner" class="w-full h-56 object-cover">
                    </figure>

                    <div class="bg-gray-600 p-6 relative">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                            {{-- Icon: Schlüssel --}}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-200 mb-1">Klein & Fein</h3>
                        <span class="text-gray-300/80 text-sm font-medium tracking-wider">ACCESSOIRES</span>
                    </div>

                    <div class="p-6">
                        <p class="text-gray-500 mb-4">Große Freude im kleinen Format. Wir planen edle Flaschenöffner und Schlüsselanhänger als perfekte Mitbringsel.</p>
                    </div>
                </article>

                {{-- 5. GESCHENKSERVICE (INAKTIV - wie gewünscht) --}}
                <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">
                    {{-- OVERLAY BADGE --}}
                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                        <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
                    </div>
                    <figure class="overflow-hidden">
                        <img src="{{ asset('images/projekt/products/geschenkpapier.jpg') }}" alt="Geschenkverpackung" class="w-full h-56 object-cover">
                    </figure>
                    <div class="bg-gray-500 p-6 relative">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H4.5a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-200 mb-1">Liebevoll Verpackt</h3>
                        <span class="text-gray-300/80 text-sm font-medium tracking-wider">ALL-INCLUSIVE SERVICE</span>
                    </div>
                    <div class="p-6"><p class="text-gray-500 mb-4">Das Auspacken ist Teil des Erlebnisses. Edle Verpackungen für dein Unikat.</p></div>
                </article>

                {{-- 6. INDIVIDUELLE WÜNSCHE (INAKTIV) --}}
                <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">
                    {{-- OVERLAY BADGE --}}
                    <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                        <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
                    </div>
                    <figure class="overflow-hidden">
                        <img src="{{ asset('images/projekt/products/individuell.png') }}" alt="Laser in Aktion" class="w-full h-56 object-cover">
                    </figure>
                    <div class="bg-gray-800 p-6 relative">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-200 mb-1">Deine Idee</h3>
                        <span class="text-gray-300/80 text-sm font-medium tracking-wider">WIR MACHEN ES MÖGLICH</span>
                    </div>
                    <div class="p-6"><p class="text-gray-500 mb-4">Du hast eine spezielle Idee? Wir prüfen die Machbarkeit für die Zukunft.</p></div>
                </article>

            </div>
        </section>

        <!-- Work Areas Section -->
        <section id="use-cases" class="bg-white overflow-hidden">

            {{-- ========================================== --}}
            {{-- 1. PRIVATE ANLÄSSE (Emotionale Geschenke) --}}
            {{-- ========================================== --}}
            <div id="fuer-herzensmenschen" class="relative bg-primary-dark overflow-hidden">
                {{-- Bild rechtsbündig auf Desktop --}}
                <div class="h-56 sm:h-72 md:absolute md:right-0 md:h-full md:w-1/2">
                    <img class="w-full h-full object-cover opacity-90"
                         src="{{ asset('images/projekt/other/trophy.png') }}"
                         alt="Personalisiertes Glasgeschenk für Hochzeiten">
                    {{-- Goldener Overlay-Schleier --}}
                    <div class="absolute inset-0 bg-primary-dark/20"></div>
                </div>

                <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
                    <div class="md:w-1/2 md:pr-12">
                        <h3 class="text-sm font-bold uppercase tracking-widest text-primary">
                            Für Herzensmenschen
                        </h3>
                        <p class="mt-2 text-white text-3xl font-serif font-bold sm:text-4xl">
                            Unvergessliche <span class="text-primary">Momente</span>
                        </p>
                        <p class="mt-4 text-gray-300 text-lg">
                            Ein Geschenk sagt mehr als tausend Worte. Unsere Glas-Unikate sind perfekt für die emotionalen Höhepunkte des Lebens.
                        </p>
                        <ul class="mt-6 space-y-3 text-white text-lg list-none">
                            <li class="flex items-center gap-3">
                                <span class="text-primary text-xl">✨</span> Hochzeiten & Jahrestage
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="text-primary text-xl">✨</span> Geburten & Taufen
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="text-primary text-xl">✨</span> Erinnerungen an Haustiere
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="text-primary text-xl">✨</span> Geburtstage & Feiertage
                            </li>
                        </ul>
                        <div class="mt-8">
                            <a href="{{ route('shop') }}" class="text-primary font-bold hover:text-white transition-colors border-b-2 border-primary pb-1">
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
                         alt="Firmenaward aus Glas">
                </div>

                <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
                    <div class="md:ml-auto md:w-1/2 md:pl-12 md:text-right">

                        <h3 class="text-sm font-bold uppercase tracking-widest text-primary-dark">
                            Für B2B Kunden
                        </h3>

                        <p class="mt-2 text-gray-900 text-3xl font-serif font-bold sm:text-4xl">
                            Der <span class="text-primary">Seelen-Kristall</span>
                        </p>

                        {{-- Hier der psychologische Dreh: Fokus auf das EINE Produkt --}}
                        <p class="mt-4 text-gray-600 text-lg leading-relaxed">
                            Wir glauben an Klasse statt Masse. Deshalb konzentrieren wir uns voll und ganz auf dieses Meisterstück aus massivem Kristallglas. Die perfekte Wahl für besondere Ehrungen, die wirklich Gewicht haben.
                        </p>

                        {{-- Liste: Rechtsbündig mit Icons rechts --}}
                        <ul class="mt-8 space-y-4 text-gray-700 text-lg w-full">

                            {{-- Item 1 --}}
                            <li class="flex items-start justify-end gap-3">
                                <span class="text-right"><strong>Mengenrabatte</strong> (ab 10 Stk.) automatisch berechnen</span>
                                <span class="text-primary text-xl flex-shrink-0 mt-1">💎</span>
                            </li>

                            {{-- Item 2 --}}
                            <li class="flex items-start justify-end gap-3">
                                <span class="text-right"><strong>Inklusive Lasergravur</strong> & Geschenkbox</span>
                                <span class="text-primary text-xl flex-shrink-0 mt-1">🎁</span>
                            </li>

                            {{-- Item 3 --}}
                            <li class="flex items-start justify-end gap-3">
                                <span class="text-right">Bequemer <strong>Rechnungskauf</strong> für Vereine</span>
                                <span class="text-primary text-xl flex-shrink-0 mt-1">📄</span>
                            </li>

                            {{-- Item 4 --}}
                            <li class="flex items-start justify-end gap-3">
                                <span class="text-right">Das Highlight für Jubiläen & Awards</span>
                                <span class="text-primary text-xl flex-shrink-0 mt-1">🏆</span>
                            </li>
                        </ul>

                        {{-- Button zum Kalkulator --}}
                        <div class="mt-10 flex justify-center md:justify-end">
                            <a href="/calculator" class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-gray-900 hover:bg-primary transition shadow-lg transform hover:-translate-y-1">
                                Jetzt Preis berechnen
                                <svg class="ml-2 -mr-1 w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                    {{-- Hier idealerweise ein Bild von DIR am Laser oder Nahaufnahme der Maschine --}}
                    <img class="w-full h-full object-cover hover:grayscale-0 transition-all duration-700"
                         src="{{ asset('images/projekt/other/handmade.png') }}"
                         alt="Laser Manufaktur in Deutschland">
                </div>

                <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
                    <div class="md:w-1/2 md:pr-12">
                        <h3 class="text-sm font-bold uppercase tracking-widest text-primary">
                            Unsere Manufaktur
                        </h3>
                        <p class="mt-2 text-white text-3xl font-serif font-bold sm:text-4xl">
                            Handveredelt in <span class="text-primary">Deutschland</span>
                        </p>
                        <p class="mt-4 text-gray-300 text-lg">
                            Wir sind kein anonymer Großhändler. Jedes Stück wird in unserer Manufaktur geprüft, gereinigt und mit modernster Laser-Technologie für dich personalisiert.
                        </p>
                        <ul class="mt-6 space-y-3 text-white text-lg list-none">
                            <li class="flex items-center gap-3">
                                <span class="text-primary text-xl">✔</span> Liebe zum Detail bei jedem Stück
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="text-primary text-xl">✔</span> Schnelle Bearbeitung (1-3 Tage)
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="text-primary text-xl">✔</span> Wir starten mit Glas und haben noch viel vor.
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
                {{-- Änderung: Flexbox für bessere Zentrierung auf Mobile hinzugefügt --}}
                <div class="h-56 sm:h-72 md:absolute md:left-0 md:h-full md:w-1/2 bg-white flex items-center justify-center">
                    <img
                        class="w-full h-full object-contain object-center p-4 md:p-0"
                        src="{{ asset('images/projekt/funki/funki.png') }}"
                        alt="Kontakt aufnehmen">
                </div>

                <div class="relative max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8 lg:py-24">
                    <div class="md:ml-auto md:w-1/2 md:pl-12 md:text-right">
                        <h3 class="text-sm font-bold uppercase tracking-widest text-primary-dark">
                            Noch Fragen?
                        </h3>

                        <p class="mt-2 text-gray-900 text-3xl font-serif font-bold sm:text-4xl">
                            Wir sind für dich da.
                        </p>

                        <p class="mt-4 text-lg text-gray-600">
                            Du hast eine spezielle Idee, eine Frage zum Foto oder möchtest eine größere Menge für deinen Verein bestellen? Schreib uns einfach!
                        </p>

                        <ul class="mt-8 space-y-4 text-gray-800 text-lg list-none inline-block text-left md:text-right w-full">
                            {{-- E-Mail --}}
                            <li class="group">
                                <a href="mailto:kontakt@mein-seelenfunke.de" class="flex items-center gap-4 flex-row md:flex-row-reverse hover:text-primary transition-colors">
                                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                                    </div>
                                    <span class="font-semibold">kontakt@mein-seelenfunke.de</span>
                                </a>
                            </li>

                            {{-- Social Media / TikTok --}}
                            <li class="group">
                                <a href="https://www.tiktok.com/@mein_seelenfunke" target="_blank" class="flex items-center gap-4 flex-row md:flex-row-reverse hover:text-primary transition-colors">
                                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                        {{-- TikTok Icon --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-6 h-6"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                                    </div>
                                    <span class="font-semibold">Folge uns auf TikTok</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </section>

        <!-- About Section -->
        <section id="about" class="bg-gray-50 py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                {{-- HEADLINE & VISION --}}
                <div class="text-center mb-16 fade-in">
                    <h2 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-6">
                        Warum <span class="text-primary">Mein Seelenfunke?</span>
                    </h2>
                    <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto leading-relaxed">
                        "Geschenke von der Stange landen in der Schublade. Ein Seelenfunke bleibt im Herzen.
                        Wir erschaffen keine Produkte, sondern konservieren Erinnerungen die persönlich und hochwertig sind,
                        beim Auspacken für Gänsehaut zu sorgen."
                    </p>

                    {{-- BUTTONS: Etsy Shop & E-Mail --}}
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">

                        <a href="{{ route('shop') }}"
                           class="inline-flex items-center px-8 py-3 bg-primary text-white text-base font-medium rounded-full shadow-lg hover:bg-primary-dark transition transform hover:scale-105">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" /></svg>
                            Zum Shop
                        </a>

                        {{-- 2. Button: E-Mail Kontakt --}}
                        <a href="mailto:kontakt@mein-seelenfunke.de"
                           class="inline-flex items-center px-8 py-3 bg-white text-primary border-2 border-primary text-base font-medium rounded-full shadow hover:bg-gray-50 transition transform hover:scale-105">
                            ✉️ E-Mail schreiben
                        </a>
                    </div>
                </div>

                <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 overflow-x-hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

                        {{-- LINKE SEITE: DIE STORY (Founder) --}}
                        <div class="fade-in">
                            <div class="bg-white rounded-2xl p-8 shadow-xl border-t-4 border-primary">
                                <div class="flex items-start gap-4 mb-6">
                                    {{-- Hier dein Profilbild einfügen --}}
                                    <img src="{{ asset('images/projekt/about/gruender-profil.jpg') }}" alt="Gründerin von Mein Seelenfunke"
                                         class="w-16 h-16 rounded-full object-cover border-2 border-primary" />
                                    <div>
                                        <div class="text-primary text-4xl font-serif leading-none mb-1">“</div>
                                        <h3 class="text-xl font-bold text-gray-800">Alina Steinhauer</h3>
                                        <p class="text-sm text-primary font-semibold">Gründerin & Inhaberin</p>
                                    </div>
                                </div>
                                <p class="text-gray-700 mb-4 leading-relaxed">
                                    Wir haben uns oft gefragt: <strong>Warum schenken wir so oft Dinge, die keine Bedeutung haben?</strong>
                                    Warum muss Personalisierung oft billig aussehen?
                                </p>
                                <p class="text-gray-700 mb-6 leading-relaxed">
                                    Unser Ziel ist es, das zu ändern. In unserer Manufaktur in Gifhorn kombinieren wir modernste Laser-Technologie mit echtem Handwerk.
                                    Jedes Stück Glas, das unsere Werkstatt verlässt, wird von uns sorgfältig geprüft und veredelt.
                                </p>
                                <div class="p-4 bg-primary/10 rounded-lg">
                                    <p class="font-medium text-gray-800">
                                        Unser Versprechen: <span class="text-primary font-bold">Ein Funke, der bleibt.</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- RECHTE SEITE: 3 WERTE KACHELN --}}
                        <div class="fade-in space-y-6" style="animation-delay: 0.3s;">

                            <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900 ml-4">Premium Qualität</h4>
                                </div>
                                <p class="text-gray-600 text-sm">Hochreines K9-Kristallglas statt billigem Plastik. Eine Gravur, die ewig hält.</p>
                            </div>

                            <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900 ml-4">Persönlicher Ansprechpartner</h4>
                                </div>
                                <p class="text-gray-600 text-sm">Wir beraten dich gerne schnell und direkt per Mail.</p>
                            </div>

                            <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H4.5a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-bold text-gray-900 ml-4">Liebevoll verpackt</h4>
                                </div>
                                <p class="text-gray-600 text-sm">Dein Unikat kommt sicher gepolstert, schnell und unkompliziert bei dir an. </p>
                            </div>

                        </div>

                    </div>
                </div>

                {{-- Customer Feedbacks --}}
                @livewire('global.widgets.google-reviews')

                <section class="bg-white overflow-hidden rounded-3xl mt-12 shadow-sm border border-gray-100">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-gray-900">

                        <div class="text-center mb-16">
                            <h2 class="text-3xl font-bold mb-4 font-serif">Das Herz der Manufaktur</h2>
                            <p class="max-w-2xl mx-auto text-lg text-gray-600 leading-relaxed">
                                Hinter <em>Mein Seelenfunke</em> steht eine klare Vision: Hochwertiges Kristallglas mit modernster Veredelungstechnik zu verbinden.
                                Wir sind kein anonymer Großkonzern, sondern eine spezialisierte Manufaktur in Gifhorn, die für Qualität, persönlichen Service und echte Werte steht.
                            </p>
                        </div>

                        {{-- Team Grid --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto mb-16">

                            {{-- 1. ALINA (VISION & LEITUNG) --}}
                            <div class="flex flex-col sm:flex-row items-center gap-6 p-6 bg-white border-2 border-primary/20 rounded-3xl shadow-xl transition relative overflow-hidden hover:shadow-2xl">
                                <div class="absolute top-0 right-0 bg-primary text-white text-[10px] font-bold px-3 py-1 rounded-bl-lg uppercase tracking-widest">
                                    Geschäftsführung
                                </div>

                                <img src="{{ asset('images/projekt/about/gruender-profil.jpg') }}" alt="Alina Steinhauer"
                                     class="w-32 h-32 rounded-2xl border-4 border-primary object-cover shadow-md flex-shrink-0" />

                                <div class="text-center sm:text-left">
                                    <h3 class="text-2xl font-bold text-gray-900">Alina Steinhauer</h3>
                                    <p class="text-primary font-bold uppercase tracking-wide text-xs mt-1">
                                        Gründerin & Laserschutzbeauftragte
                                    </p>
                                    <p class="text-gray-600 mt-3 text-sm italic">
                                        "Qualität ist kein Zufall, sondern eine Haltung. Ich habe diese Manufaktur gegründet, um bleibende Werte zu schaffen. Mein Name steht für die Garantie, dass jedes Stück, das unser Haus verlässt, höchsten Ansprüchen genügt."
                                    </p>
                                </div>
                            </div>

                            {{-- 2. FUNKI (DER ALLESKÖNNER) --}}
                            <div class="flex flex-col sm:flex-row items-center gap-6 p-6 bg-white border-2 border-indigo-500/20 rounded-3xl shadow-xl transition relative overflow-hidden hover:shadow-2xl">
                                <div class="absolute top-0 right-0 bg-indigo-600 text-white text-[10px] font-bold px-3 py-1 rounded-bl-lg uppercase tracking-widest">
                                    System & Support
                                </div>

                                <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" alt="Funki"
                                     class="w-32 h-32 rounded-2xl border-4 border-indigo-500 object-cover shadow-md flex-shrink-0" />

                                <div class="text-center sm:text-left">
                                    <h3 class="text-2xl font-bold text-gray-900">Funki</h3>
                                    <p class="text-indigo-600 font-bold uppercase tracking-wide text-xs mt-1">
                                        Digitale Seele & Alleskönner
                                    </p>
                                    <p class="text-gray-600 mt-3 text-sm">
                                        Funki ist das Herz unserer Automatisierung. Er behält den Überblick über alle Bestellungen, koordiniert die Logik im Hintergrund und sorgt dafür, dass kein Seelenfunke verloren geht. Mit Admin-Rechten ausgestattet, ist er unser unermüdlicher 24/7 Begleiter.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Produktion & Service Bereich (Volle Breite, keine Kachel) --}}
                        <div class="max-w-5xl mx-auto pt-12 border-t border-gray-100">
                            <div class="flex flex-col md:flex-row items-center gap-8 md:gap-12">
                                <div class="w-24 h-24 rounded-2xl bg-primary/10 flex items-center justify-center text-5xl flex-shrink-0 text-primary shadow-inner">
                                    🛠️
                                </div>
                                <div class="flex-1 text-center md:text-left">
                                    <div class="flex flex-col md:flex-row md:items-center gap-2 mb-4">
                                        <h3 class="text-2xl font-bold text-gray-900">Produktion & Service</h3>
                                        <span class="hidden md:block text-gray-300">|</span>
                                        <span class="text-primary font-bold uppercase tracking-widest text-sm">Made in Gifhorn</span>
                                    </div>
                                    <p class="text-gray-600 text-lg leading-relaxed">
                                        Unser Anspruch ist absolute Präzision. Von der ersten individuellen Beratung bis zum sicheren, liebevollen Versand Ihres Unikats. Wir kombinieren traditionelle handwerkliche Werte mit zertifizierter, modernster Sicherheitstechnik. Egal ob persönliches Einzelstück oder komplexer Firmenauftrag: Unser eingespieltes Team sorgt für einen reibungslosen Ablauf und Ergebnisse, die begeistern.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </section>

            </div>
        </section>
        <section class="bg-gradient-to-br from-gray-900 to-black text-white py-24 relative overflow-hidden">

            {{-- Dekorativer Hintergrund-Effekt (Goldener Schimmer) --}}
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full bg-primary/10 blur-[100px] rounded-full opacity-30"></div>
            <div class="absolute inset-0 bg-black/40"></div>

            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
                <div class="fade-in space-y-8">

                    {{-- Headline: Selbstbewusst & Professionell --}}
                    <h2 class="text-3xl md:text-5xl font-serif font-bold leading-tight">
                        Hinter diesem Shop steckt kein Algorithmus.<br>
                        <span class="text-primary">Sondern echtes Handwerk aus Deutschland.</span>
                    </h2>

                    {{-- Der emotionale Text --}}
                    <p class="text-lg md:text-xl text-gray-200 font-light leading-relaxed">
                        In Zeiten von anonymer Massenware gehen wir bewusst einen anderen Weg.
                        Wenn Sie bei einem Großkonzern bestellen, sind Sie oft nur eine Bestellnummer im System.
                        <br><br>
                        <strong>Bei uns ist das anders. Jede Bestellung wird in unserer Manufaktur persönlich bearbeitet, geprüft und gefeiert.</strong>
                        <br><br>
                        Mit Ihrem Auftrag unterstützen Sie kein riesiges Logistikzentrum, sondern ein lokales Unternehmen, das auf Qualität und Nachhaltigkeit setzt.
                    </p>

                    {{-- CTA Button --}}
                    <div class="pt-6 flex flex-col items-center gap-4">
                        <a href="{{ route('calculator') }}"
                           class="inline-flex items-center gap-3 bg-primary text-white px-10 py-5 rounded-full font-bold text-lg shadow-[0_0_25px_rgba(201,166,107,0.3)] hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 hover:shadow-[0_0_40px_rgba(255,255,255,0.5)]">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            Jetzt Angebot berechnen
                        </a>
                        <p class="text-xs text-gray-500 uppercase tracking-widest font-semibold opacity-80">
                            Unverbindlich & Sofort
                        </p>
                    </div>
                </div>
            </div>

            <!-- Infinite Logo Marquee -->
            <div class="relative flex overflow-hidden group/container pt-8">

                {{-- Erster Animations-Block --}}
                <div class="flex items-center gap-20 md:gap-32 animate-marquee whitespace-nowrap flex-shrink-0">
                    @for ($i = 0; $i < 10; $i++)
                        <div class="flex items-center group">
                            {{-- Logo: Harmonische Größe (h-12 auf Mobile, h-20 auf Desktop) --}}
                            <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}"
                                 alt="Mein Seelenfunke Logo"
                                 class="h-24 md:h-20 w-auto opacity-40 group-hover:opacity-100 transition-all duration-700 ease-in-out transform group-hover:scale-110">
                        </div>
                    @endfor
                </div>

                {{-- Zweiter Animations-Block (Loop-Kopie) --}}
                <div class="flex items-center gap-20 md:gap-32 animate-marquee whitespace-nowrap flex-shrink-0 ml-20 md:ml-32" aria-hidden="true">
                    @for ($i = 0; $i < 10; $i++)
                        <div class="flex items-center group">
                            <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}"
                                 alt="Mein Seelenfunke Logo"
                                 class="h-24 md:h-20 w-auto opacity-40 group-hover:opacity-100 transition-all duration-700 ease-in-out transform group-hover:scale-110">
                        </div>
                    @endfor
                </div>
            </div>

            <style>
                @keyframes marquee {
                    0% { transform: translateX(0); }
                    100% { transform: translateX(-100%); }
                }

                .animate-marquee {
                    /* Geschwindigkeit auf 60s angepasst - bei kleineren Logos wirkt zu langsam oft "stehend" */
                    animation: marquee 60s linear infinite;
                    will-change: transform;
                }

                .group\/container:hover .animate-marquee {
                    animation-play-state: paused;
                }
            </style>

        </section>

        <!-- Process Section -->
        <section id="process" class="bg-white py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                {{-- HEADLINE --}}
                <div class="text-center mb-20 fade-in">
                    <h2 class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-6">
                        Von der Idee zum <span class="text-primary">Seelenfunken</span>
                    </h2>
                    <p class="text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
                        Transparenz schafft Vertrauen. Ihr Unikat ist keine Lagerware. Hier sehen Sie, wie wir Ihr Produkt Schritt für Schritt in unserer Manufaktur fertigen. Von der Datenprüfung bis zum sicheren Versand.
                    </p>
                </div>

                <div class="relative">
                    {{-- Verbindungslinie (Nur Desktop) --}}
                    <div class="hidden lg:block absolute top-12 left-0 w-full h-1 bg-gray-100 my-4 rounded-full overflow-hidden z-0">
                        <div class="h-full bg-gradient-to-r from-primary-light via-primary to-primary-dark w-full opacity-30"></div>
                    </div>

                    {{-- PROZESS SCHRITTE --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-6">

                        @php
                            $steps = [
                                [
                                    'video' => 'beratung',
                                    'image' => '/images/projekt/process/beratung.png',
                                    'title' => 'Auftrag & Design',
                                    'text' => 'Alles beginnt mit Ihrer Idee. Wir prüfen Ihre Daten oder erstellen gemeinsam ein Layout, das perfekt auf das Glas abgestimmt ist.'
                                ],
                                [
                                    'video' => 'lasergravur',
                                    'image' => '/images/projekt/process/lasergravur.png',
                                    'title' => 'High-End Laser',
                                    'text' => 'Mit modernster Lasertechnologie wird Ihr Motiv dauerhaft und gestochen scharf in das Material eingearbeitet. Präzision im Mikrometerbereich.'
                                ],
                                [
                                    'video' => '4_augen',
                                    'image' => '/images/projekt/process/handveredelung.png',
                                    'title' => 'Veredelung & Check',
                                    'text' => 'Jedes Stück wird von Hand gereinigt, poliert und durchläuft unsere strenge 4-Augen-Qualitätsprüfung. Nur Makelloses verlässt das Haus.'
                                ],
                                [
                                    'video' => 'edle_verpackung',
                                    'image' => '/images/projekt/process/edle_verpackung.png',
                                    'title' => 'Edle Verpackung',
                                    'text' => 'Der erste Eindruck zählt. Ihr Unikat wird direkt in unserer hochwertigen Geschenkbox verpackt und ist somit bereit zur feierlichen Übergabe.'
                                ],
                                [
                                    'video' => 'sicherer_versand',
                                    'image' => '/images/projekt/process/sicherer_versand.png',
                                    'title' => 'Sicherer Versand',
                                    'text' => 'Wir verpacken bruchsicher in speziellen Kartonagen. Ihr Paket wird versichert an unseren Logistikpartner übergeben.'
                                ]
                            ];
                        @endphp

                        @foreach($steps as $index => $step)
                            <div class="text-center fade-in group relative z-10" style="animation-delay: {{ $index * 0.2 }}s;">

                                <div class="relative inline-block transition-transform transform group-hover:-translate-y-2 duration-300">

                                    {{-- Container für Bild ODER Video --}}
                                    <div class="w-32 h-32 bg-white border-4 border-primary rounded-full overflow-hidden mx-auto mb-6 shadow-xl relative z-10 group-hover:shadow-2xl group-hover:border-primary-dark transition-all">

                                        @if(isset($step['video']))
                                            {{-- High-Performance Video-Logik --}}
                                            <video
                                                autoplay
                                                loop
                                                muted
                                                playsinline
                                                preload="none"
                                                loading="lazy"
                                                poster="{{ asset($step['image']) }}" {{-- Zeigt das Bild, während das Video lädt --}}
                                                class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500">

                                                {{-- Falls du eine WebM Version hast (sehr empfohlen für Speed) --}}
                                                <source src="{{ asset('images/projekt/process/' . $step['video'] . '.webm') }}" type="video/webm">
                                                {{-- Fallback MP4 --}}
                                                {{--<source src="{{ asset('images/projekt/process/' . $step['video'] . '.mp4') }}" type="video/mp4">--}}

                                                {{-- Fallback Image falls Video gar nicht geht --}}
                                                <img src="{{ asset($step['image']) }}" alt="{{ $step['title'] }}">
                                            </video>
                                        @else
                                            {{-- Klassische Bild-Logik --}}
                                            <img src="{{ asset($step['image']) }}"
                                                 onerror="this.src='https://placehold.co/200x200/f8f8f8/CCCCCC?text={{ $index+1 }}'; this.style.objectFit='cover';"
                                                 alt="{{ $step['title'] }}"
                                                 loading="lazy"
                                                 class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500">
                                        @endif

                                    </div>

                                    {{-- Nummer Badge --}}
                                    <div class="absolute -top-1 -right-1 w-8 h-8 bg-primary text-white font-bold rounded-full flex items-center justify-center border-2 border-white shadow-md z-20">
                                        {{ $index + 1 }}
                                    </div>
                                </div>

                                <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-primary transition-colors">
                                    {{ $step['title'] }}
                                </h3>
                                <p class="text-gray-600 text-sm leading-relaxed px-1">
                                    {{ $text = $step['text'] }}
                                </p>
                            </div>
                        @endforeach

                    </div>
                </div>

                {{-- CALL TO ACTION --}}
                <div class="text-center mt-20 fade-in">
                    <p class="text-xl text-gray-500 mb-10 italic font-serif max-w-3xl mx-auto">
                        "Verlassen Sie sich auf einen reibungslosen Ablauf und ein Ergebnis, das begeistert."
                    </p>

                    <a href="{{ route('calculator') }}"
                       class="inline-flex items-center gap-3 bg-primary text-white px-10 py-4 rounded-full font-bold text-lg shadow-lg hover:bg-primary-dark transition-all transform hover:scale-105 hover:shadow-2xl">
                        <span>Jetzt Preis berechnen</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- 360° carefree -->
        <section id="carefree" class="py-24 bg-gray-50 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-12">
                <div class="grid md:grid-cols-2 gap-x-12 gap-y-16 items-center">

                    <div class="fade-in">
                        <span class="text-primary font-serif font-semibold mb-2 inline-block tracking-wider uppercase text-sm">Service-Versprechen</span>
                        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 font-serif">
                            Unser Rundum-Sorglos-Paket
                        </h2>

                        <p class="text-gray-700 text-lg mb-4 leading-relaxed">
                            Ob für verdiente Mitarbeiter, langjährige Partner oder einen Herzensmenschen: Schenken sollte Freude bereiten, keinen Stress verursachen.
                        </p>
                        <p class="text-gray-700 text-lg mb-6 leading-relaxed">
                            Bei <strong>Mein Seelenfunke</strong> erhalten Sie nicht einfach nur ein Stück Glas. Wir übernehmen für Sie den kompletten Prozess von der Prüfung Ihrer Daten bis zum sicheren Versand an den Empfänger.
                        </p>

                        {{-- Highlight Box --}}
                        <p class="text-white text-lg mb-10 leading-relaxed bg-gradient-to-r from-primary to-primary-dark p-6 rounded-xl shadow-lg border-l-4 border-white/30">
                            <strong>Unser Anspruch:</strong> Sie bestellen bequem vom Schreibtisch oder Sofa aus und wir sorgen dafür, dass beim Auspacken Begeisterung entsteht.
                        </p>

                        <h3 class="text-xl font-bold text-gray-800 mb-6">Ihre Vorteile im Überblick:</h3>
                        <ul class="space-y-4 text-gray-700 mb-12">

                            {{-- Vorteil 1 --}}
                            <li class="flex items-start">
                                <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <div>
                                    <strong class="font-semibold text-gray-900">Alles aus einer Hand:</strong>
                                    Keine externen Dienstleister, keine Verzögerungen. Designprüfung, Veredelung und Logistik erfolgen direkt in unserer Manufaktur. Das garantiert kurze Wege und präzise Ergebnisse.
                                </div>
                            </li>

                            {{-- Vorteil 2 --}}
                            <li class="flex items-start">
                                <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <div>
                                    <strong class="font-semibold text-gray-900">Qualitätsgarantie:</strong>
                                    Bevor ein Paket unser Haus verlässt, wird es streng kontrolliert. Keine Kratzer, keine Fingerabdrücke. Nur reines Glas und eine perfekte Gravur.
                                </div>
                            </li>

                            {{-- Vorteil 3 --}}
                            <li class="flex items-start">
                                <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <div>
                                    <strong class="font-semibold text-gray-900">Geschenkfertig geliefert:</strong>
                                    Ihr Unikat kommt <strong>standardmäßig</strong> in einer hochwertigen, mit Stoff ausgelegten Geschenkbox. Bereit zur sofortigen Übergabe oder Ehrung.
                                </div>
                            </li>

                            {{-- Vorteil 4 --}}
                            <li class="flex items-start">
                                <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <div>
                                    <strong class="font-semibold text-gray-900">Persönlicher Service:</strong>
                                    Haben Sie Sonderwünsche? Sie landen in keinem Callcenter, sondern sprechen direkt mit unseren Experten. Wir finden für fast jede Anforderung eine Lösung.
                                </div>
                            </li>

                        </ul>

                    </div>

                    {{-- BILD RECHTS --}}
                    <div class="flex justify-center items-center relative fade-in" style="animation-delay: 0.3s;">
                        {{-- Dekorativer Hintergrundkreis --}}
                        <div class="absolute inset-0 bg-primary/5 rounded-full transform scale-90 blur-3xl"></div>

                        <img src="{{ asset('images/projekt/logo/logo-up.png') }}"
                             alt="Full-Service Manufaktur"
                             class="relative w-full max-w-md object-cover transform hover:scale-105 transition duration-500 drop-shadow-2xl">
                    </div>
                </div>
            </div>
        </section>

        <!--Process 2 Section-->
        {{--<section id="quality-process" class="bg-white py-16 px-6 md:px-12">
            <div class="max-w-7xl mx-auto text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 mb-4">
                    Von der Idee zum <span class="text-primary">Seelenfunken</span>
                </h2>
                <p class="text-lg text-gray-700 max-w-3xl mx-auto leading-relaxed">
                    Transparenz schafft Vertrauen. Hier sehen Sie, wie wir mit Präzision und Herzblut Ihr persönliches Unikat fertigen – vom digitalen Entwurf bis zum sicheren Versand.
                </p>
            </div>

            <div class="max-w-6xl mx-auto grid gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 text-left">
                @php
                    // Strategie: Prozess statt Historie. Das zeigt Kompetenz.
                    $items = [
                        [
                            // Bildidee: Jemand am Laptop/Tablet mit einer Vektorgrafik
                            'image' => '/images/projekt/process/beratung.png',
                            'title' => '1. Beratung & Design',
                            'text' => 'Alles beginnt mit Ihrer Idee. Wir prüfen Ihre Daten oder erstellen gemeinsam ein Layout, das perfekt auf das Glas abgestimmt ist.'
                        ],
                        [
                            // Bildidee: Nahaufnahme des Laserkopfs (vielleicht dein Laser-Bild von vorhin)
                            'image' => '/images/projekt/process/lasergravur.png',
                            'title' => '2. High-End Lasergravur',
                            'text' => 'Mit modernster Lasertechnologie wird Ihr Motiv dauerhaft und gestochen scharf in das Material eingearbeitet. Präzision im Mikrometerbereich.'
                        ],
                        [
                            // Bildidee: Hände (z.B. mit Handschuhen), die das Glas polieren/prüfen
                            'image' => '/images/projekt/process/handveredelung.png',
                            'title' => '3. Handveredelung',
                            'text' => 'Maschinen sind präzise, aber das Auge ist unersetzlich. Jedes Stück wird von Hand gereinigt, poliert und finalisiert.'
                        ],
                        [
                            // Bildidee: Jemand hält das Glas gegen das Licht (Qualitätscheck)
                            'image' => '/images/projekt/process/4_augen.png',
                            'title' => '4. 4-Augen Qualitätsprüfung',
                            'text' => 'Nur makellose Produkte verlassen unsere Manufaktur. Wir prüfen auf Kratzer, Gravurtiefe und Sauberkeit.'
                        ],
                        [
                            // Bildidee: Die blaue Geschenkbox, schön drapiert
                            'image' => '/images/projekt/process/edle_verpackung.png',
                            'title' => '5. Edle Verpackung',
                            'text' => 'Der erste Eindruck zählt. Ihr Unikat wird direkt in unserer hochwertigen Geschenkbox verpackt – bereit zur Übergabe.'
                        ],
                        [
                            // Bildidee: Ein DHL Paket oder Stapel versandbereiter Kartons
                            'image' => '/images/projekt/process/sicherer_versand.png',
                            'title' => '6. Sicherer Versand',
                            'text' => 'Bruchsicher verpackt und schnell versendet. Bei Großaufträgen koordinieren wir den Logistikprozess für Sie.'
                        ],
                    ];
                @endphp

                @foreach ($items as $index => $item)
                    <div class="group h-full">
                        <div class="flex flex-col h-full bg-white rounded-xl shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-300 transform hover:-translate-y-1">

                            --}}{{-- Bild-Container --}}{{--
                            <div class="h-48 overflow-hidden relative bg-gray-50 border-b border-gray-100">
                                --}}{{-- Overlay beim Hover --}}{{--
                                <div class="absolute inset-0 bg-primary/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10"></div>

                                <img src="{{ $item['image'] }}"
                                     --}}{{-- Placeholder Logik beibehalten --}}{{--
                                     onerror="this.src='https://placehold.co/600x400/f8f8f8/CCCCCC?text=Prozess+Schritt+{{ $index+1 }}'; this.style.objectFit='cover';"
                                     alt="{{ $item['title'] }}"
                                     class="object-cover w-full h-full transition-transform duration-700 group-hover:scale-110">
                            </div>

                            <div class="p-6 flex-1 flex flex-col">
                                <div class="flex items-center mb-3">
                                    --}}{{-- Nummerierung als Design-Element --}}{{--
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-sm mr-3">
                                        {{ $index + 1 }}
                                    </div>
                                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Schritt</span>
                                </div>

                                <h3 class="text-lg font-bold text-gray-800 mb-2 group-hover:text-primary transition-colors">
                                    --}}{{-- Titel bereinigen (Nummer entfernen, da oben schon Icon) --}}{{--
                                    {{ str_replace(($index+1).'. ', '', $item['title']) }}
                                </h3>
                                <p class="text-gray-600 text-sm leading-relaxed">{{ $item['text'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            --}}{{-- Button "Mehr anzeigen" entfernt, da 6 Schritte perfekt in das Grid passen und man den Prozess immer ganz sehen sollte --}}{{--
        </section>--}}

        <!--FAQ Section-->
        @php
            $freeThreshold = (int) shop_setting('shipping_free_threshold', 5000);
            $shippingCost = (int) shop_setting('shipping_cost', 490);
            $expressSurcharge = (int) shop_setting('express_surcharge', 2500);
        @endphp

        <section id="faq" class="bg-gradient-to-b from-gray-50 to-white py-24 scroll-mt-20">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

                {{-- HEADLINE --}}
                <div class="text-center mb-20 fade-in">
            <span class="inline-block px-4 py-1.5 mb-4 text-xs font-bold tracking-widest text-primary uppercase bg-primary/10 rounded-full">
                Häufige Fragen
            </span>
                    <h2 class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-6 text-serif">
                        Alles, was du über deinen <span class="text-primary italic">Seelenfunken</span> wissen musst
                    </h2>
                    <div class="w-24 h-1 bg-primary/30 mx-auto rounded-full mb-8 text-serif"></div>
                    <p class="text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto text-serif">
                        Du hast noch offene Punkte? Hier findest du die Antworten rund um unsere Manufaktur in Gifhorn, den Versand und die Personalisierung.
                    </p>
                </div>

                {{-- FAQ ACCORDION --}}
                <div class="space-y-6 fade-in">

                    {{-- FRAGE: Material --}}
                    <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                        <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                            <div class="flex items-center gap-4">
                                <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                </div>
                                <h3 class="text-lg md:text-xl font-bold font-serif">Ist das wirklich Glas oder Acryl/Plastik?</h3>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                            </div>
                        </summary>
                        <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                            Wir verwenden ausschließlich **massives K9-Kristallglas**. Das ist kein leichtes Plastik oder Acryl, sondern schweres, optisch reines Glas, das speziell für Laserinnengravuren entwickelt wurde. Du wirst den Qualitätsunterschied sofort am Gewicht und der Brillanz spüren.
                        </div>
                    </details>

                    {{-- FRAGE: Foto --}}
                    <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                        <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                            <div class="flex items-center gap-4 text-serif">
                                <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary text-serif">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 00-2 2z" /></svg>
                                </div>
                                <h3 class="text-lg md:text-xl font-bold font-serif text-serif">Kann ich auch ein eigenes Foto gravieren lassen?</h3>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif text-serif">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                            </div>
                        </summary>
                        <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                            Ja, absolut! Das ist unsere Spezialität. Du kannst uns dein Wunschfoto einfach im Konfigurator hochladen. Wichtig ist eine möglichst gute Auflösung. Bevor wir den Laser starten, prüft unser Team jedes Bild manuell. Sollte die Qualität nicht ausreichen, melden wir uns proaktiv bei dir.
                        </div>
                    </details>

                    {{-- FRAGE: Versandkosten (DYNAMISCH) --}}
                    <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                        <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                            <div class="flex items-center gap-4 text-serif">
                                <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary">
                                    <svg class="w-6 h-6 text-serif" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                                </div>
                                <h3 class="text-lg md:text-xl font-bold font-serif">Was kostet der Versand & wie schnell seid ihr?</h3>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif">
                            <svg class="w-6 h-6 transition-transform group-open:hidden text-serif text-serif" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block text-serif text-serif" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                            </div>
                        </summary>
                        <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                            <p class="mb-4">
                                Innerhalb Deutschlands versenden wir ab einem Bestellwert von <strong>{{ number_format($freeThreshold / 100, 2, ',', '.') }} €</strong> grundsätzlich <strong>versandkostenfrei</strong>. Darunter berechnen wir eine kleine Pauschale von {{ number_format($shippingCost / 100, 2, ',', '.') }} €.
                            </p>
                            <p class="mb-4">
                                Die Fertigung dauert in der Regel 1–3 Werktage. Der Versand erfolgt sicher per DHL.
                            </p>
                            <a href="{{ url('/versand') }}" class="inline-flex items-center gap-1.5 text-primary font-bold hover:underline">
                                Alle Details zur Lieferung & EU-Versand ansehen
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                            </a>
                        </div>
                    </details>

                    {{-- FRAGE: Express --}}
                    @if($expressSurcharge > 0)
                        <details class="group bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden [&_summary::-webkit-details-marker]:hidden transition-all duration-300 open:ring-2 open:ring-primary/20">
                            <summary class="flex cursor-pointer items-center justify-between gap-4 p-8 text-gray-900 transition-colors hover:bg-gray-50/50">
                                <div class="flex items-center gap-4 text-serif">
                                    <div class="hidden sm:flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary text-serif">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                    </div>
                                    <h3 class="text-lg md:text-xl font-bold font-serif text-serif italic">Bietet ihr einen Express-Service an?</h3>
                                </div>
                                <div class="ml-4 flex-shrink-0 text-serif">
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white transition-all duration-300 group-open:rotate-180 group-open:bg-primary group-open:text-white group-open:border-primary shadow-sm text-serif text-serif">
                            <svg class="w-6 h-6 transition-transform group-open:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            <svg class="w-6 h-6 hidden group-open:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2 text-serif"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                        </span>
                                </div>
                            </summary>
                            <div class="px-8 pb-8 sm:pl-20 text-gray-600 leading-relaxed border-t border-gray-50 pt-4 text-serif">
                                Ja! Wenn es besonders eilig ist, kannst du für {{ number_format($expressSurcharge / 100, 2, ',', '.') }} € unseren Express-Service buchen. Dein Auftrag wird dann mit höchster Priorität gefertigt und bevorzugt dem Versanddienstleister übergeben.
                            </div>
                        </details>
                    @endif

                </div>

                {{-- Footer Text --}}
                <div class="mt-20 text-center bg-white p-10 rounded-[2.5rem] shadow-sm border border-gray-50 text-serif">
                    <h4 class="text-lg font-bold text-gray-900 mb-2">Noch etwas unklar?</h4>
                    <p class="text-gray-500 mb-6">
                        Schreib uns einfach eine Nachricht. Wir antworten meist innerhalb weniger Stunden.
                    </p>
                    <a href="mailto:kontakt@mein-seelenfunke.de" class="inline-flex items-center gap-2 px-8 py-3 bg-gray-900 text-white font-bold rounded-full hover:bg-primary transition-all duration-300 group text-serif">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Jetzt Kontakt aufnehmen
                    </a>
                </div>

            </div>
        </section>

        {{--Contact Section--}}
        @livewire('global.widgets.contact-form')

    </x-sections.page-container>

</x-layouts.frontend_layout>
