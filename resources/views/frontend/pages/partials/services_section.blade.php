<section id="services" class="bg-white text-black py-24 px-6 lg:px-12" aria-labelledby="services-heading">
    <header class="text-center mb-16">
        <h2 id="services-heading" class="text-primary-dark font-serif font-bold text-3xl sm:text-4xl lg:text-5xl">
            Veredelungen & Materialien für Ihre Unikate
        </h2>
        <p class="mt-4 text-gray-600 text-base max-w-3xl mx-auto">
            Wir starten mit unseren exklusiven <strong>Glas-Unikaten aus K9-Kristall</strong>. Doch das ist erst der Anfang. Entdecken Sie hier, welche hochwertigen Veredelungen wir zukünftig für personalisierte Geschenke anbieten werden.
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
                     alt="K9-Kristallglas mit präziser 3D-Lasergravur und Weiß-Effekt"
                     loading="lazy"
                     class="w-full h-56 object-cover transform group-hover:scale-110 transition-transform duration-500">
            </figure>

            <div class="bg-gradient-to-br from-primary to-primary-dark p-6 relative">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-4 shadow-lg absolute -top-8 right-6">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-primary">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-white mb-1">Glas & Kristall</h3>
                <span class="text-white/80 text-sm font-medium tracking-wider">PREMIUM GRAVUR</span>
            </div>

            <div class="p-6">
                <p class="text-gray-600 mb-4">Unsere Königsdisziplin. Wir veredeln hochwertiges <strong>K9-Kristallglas</strong> mit präzisen Lasergravuren, die durch den Weiß-Effekt wie gefrostet wirken.</p>
                <ul class="text-sm text-gray-500 space-y-2 mb-6" aria-label="Produktmerkmale Glas & Kristall">
                    <li class="flex items-center gap-2"><span class="text-primary" aria-hidden="true">✔</span> Individueller Text & Wunschmotiv</li>
                    <li class="flex items-center gap-2"><span class="text-primary" aria-hidden="true">✔</span> Massives, schweres Kristallglas</li>
                    <li class="flex items-center gap-2"><span class="text-primary" aria-hidden="true">✔</span> Standardmäßig in edler Box</li>
                </ul>
                <a href="{{ route('shop') }}"
                   title="Zur Kategorie Glas & Kristall im Shop"
                   class="block text-center w-full bg-primary text-white py-2 rounded-md font-bold hover:bg-primary-dark transition-colors">
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
                <img src="{{ asset('images/projekt/products/schiefer.png') }}"
                     alt="Personalisierte Schieferplatten mit Lasergravur"
                     loading="lazy"
                     class="w-full h-56 object-cover">
            </figure>
            {{-- Header Grau statt Bunt --}}
            <div class="bg-gray-600 p-6 relative">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-200 mb-1">Schiefer & Natur</h3>
                <span class="text-gray-300/80 text-sm font-medium tracking-wider">RUSTIKALE UNIKATE</span>
            </div>
            <div class="p-6">
                <p class="text-gray-500 mb-4">Jedes Stück ein Unikat. Die natürliche Struktur des Schiefers sorgt für starke Kontraste bei der Gravur.</p>
                <ul class="text-sm text-gray-400 space-y-2" aria-label="Produktmerkmale Schiefer"><li class="flex items-center gap-2">Isoliert & Wetterfest</li><li class="flex items-center gap-2">Untersetzer & Schilder</li></ul>
            </div>
        </article>

        {{-- 3. METALL & BUSINESS (INAKTIV) --}}
        <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">
            {{-- OVERLAY BADGE --}}
            <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
            </div>
            <figure class="overflow-hidden">
                <img src="{{ asset('images/projekt/products/liebesfunke-metallkarte.png') }}"
                     alt="Lasergravur auf Metallkarten und Aluminium"
                     loading="lazy"
                     class="w-full h-56 object-cover">
            </figure>
            <div class="bg-gray-700 p-6 relative">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-200 mb-1">Metall & Business</h3>
                <span class="text-gray-300/80 text-sm font-medium tracking-wider">MODERNE ELEGANZ</span>
            </div>
            <div class="p-6"><p class="text-gray-500 mb-4">Hauchdünne Metallkarten und Visitenkarten aus Aluminium oder Edelstahl für einen bleibenden Eindruck.</p></div>
        </article>

        {{-- 4. KLEINARTIKEL (INAKTIV) --}}
        <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">

            {{-- OVERLAY BADGE --}}
            <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
            </div>

            <figure class="overflow-hidden">
                {{-- Bild-Pfad bitte entsprechend anpassen, z.B. auf einen Schlüsselanhänger --}}
                <img src="{{ asset('images/projekt/products/flaschenoeffner.png') }}"
                     alt="Personalisierte Schlüsselanhänger und Flaschenöffner"
                     loading="lazy"
                     class="w-full h-56 object-cover">
            </figure>

            <div class="bg-gray-600 p-6 relative">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                    {{-- Icon: Schlüssel --}}
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-200 mb-1">Klein & Fein</h3>
                <span class="text-gray-300/80 text-sm font-medium tracking-wider">ACCESSOIRES</span>
            </div>

            <div class="p-6">
                <p class="text-gray-500 mb-4">Große Freude im kleinen Format. Wir planen edle Flaschenöffner und Schlüsselanhänger als perfekte Mitbringsel und Werbegeschenke.</p>
            </div>
        </article>

        {{-- 5. GESCHENKSERVICE (INAKTIV - wie gewünscht) --}}
        <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">
            {{-- OVERLAY BADGE --}}
            <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
            </div>
            <figure class="overflow-hidden">
                <img src="{{ asset('images/projekt/products/geschenkpapier.jpg') }}"
                     alt="Exklusive Geschenkverpackung für Ihr Unikat"
                     loading="lazy"
                     class="w-full h-56 object-cover">
            </figure>
            <div class="bg-gray-500 p-6 relative">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H4.5a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-200 mb-1">Liebevoll Verpackt</h3>
                <span class="text-gray-300/80 text-sm font-medium tracking-wider">ALL-INCLUSIVE SERVICE</span>
            </div>
            <div class="p-6"><p class="text-gray-500 mb-4">Das Auspacken ist Teil des Erlebnisses. Edle Verpackungen runden Ihr Unikat perfekt ab.</p></div>
        </article>

        {{-- 6. INDIVIDUELLE WÜNSCHE (INAKTIV) --}}
        <article class="bg-gray-50 rounded-2xl shadow-none border border-gray-200 overflow-hidden opacity-60 grayscale-[0.9] cursor-default relative group">
            {{-- OVERLAY BADGE --}}
            <div class="absolute inset-0 z-20 flex items-center justify-center bg-white/20 backdrop-blur-[1px]">
                <div class="bg-black/80 text-white px-6 py-2 rounded-full font-bold tracking-widest border border-white/20 shadow-xl transform -rotate-12">DEMNÄCHST</div>
            </div>
            <figure class="overflow-hidden">
                <img src="{{ asset('images/projekt/products/individuell.png') }}"
                     alt="Sonderanfertigungen und individuelle Laser-Projekte"
                     loading="lazy"
                     class="w-full h-56 object-cover">
            </figure>
            <div class="bg-gray-800 p-6 relative">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4 shadow-sm absolute -top-8 right-6">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-200 mb-1">Deine Idee</h3>
                <span class="text-gray-300/80 text-sm font-medium tracking-wider">WIR MACHEN ES MÖGLICH</span>
            </div>
            <div class="p-6"><p class="text-gray-500 mb-4">Sie haben eine spezielle Idee für eine Gravur? Wir prüfen die Machbarkeit für zukünftige Projekte.</p></div>
        </article>

    </div>
</section>
