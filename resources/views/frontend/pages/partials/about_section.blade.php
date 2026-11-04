<section id="about" class="bg-gray-50 py-20" aria-labelledby="about-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- HEADLINE & VISION --}}
        <div class="text-center mb-16 fade-in">
            <h2 id="about-heading" class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-6">
                Warum <span class="text-[#8a6820]">Mein Seelenfunke?</span><br>
                <span class="text-2xl md:text-3xl text-gray-500 font-sans font-light mt-2 block">Ihre Manufaktur für personalisierte Geschenke</span>
            </h2>
            <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto leading-relaxed">
                "Geschenke von der Stange landen in der Schublade. Ein Seelenfunke bleibt im Herzen.
                Wir erschaffen keine Produkte, sondern konservieren Erinnerungen, die persönlich und hochwertig sind,
                um beim Auspacken für Gänsehaut zu sorgen."
            </p>

            {{-- BUTTONS: Etsy Shop & E-Mail --}}
            <div class="flex flex-col sm:flex-row gap-4 justify-center">

                <a href="{{ route('shop') }}"
                   title="Zum Online-Shop für handveredelte Unikate"
                   class="inline-flex items-center px-8 py-3 bg-primary text-gray-900 text-base font-medium rounded-full shadow-lg hover:bg-primary-dark hover:text-white transition transform hover:scale-105">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" /></svg>
                    Zum Shop
                </a>

                {{-- 2. Button: E-Mail Kontakt --}}
                <a href="mailto:{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}"
                   title="Kontaktieren Sie uns per E-Mail"
                   class="inline-flex items-center px-8 py-3 bg-white text-[#8a6820] border-2 border-primary text-base font-medium rounded-full shadow hover:bg-gray-50 transition transform hover:scale-105">
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
                            <img src="{{ asset('shop/projekt/about/gruender-welcome-sm.webp') }}"
                                 alt="{{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}, Gründerin von {{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}"
                                 width="64" height="64"
                                 loading="lazy"
                                 class="w-16 h-16 rounded-full object-cover border-2 border-primary" />
                            <div>
                                <div class="text-[#8a6820] text-4xl font-serif leading-none mb-1" aria-hidden="true">“</div>
                                <h3 class="text-xl font-bold text-gray-800">{{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}</h3>
                                <p class="text-sm text-[#8a6820] font-semibold">Gründerin & Inhaberin</p>
                            </div>
                        </div>
                        <p class="text-gray-700 mb-4 leading-relaxed">
                            Ich habe mich oft gefragt: <strong>Warum schenken wir so oft Dinge, die schon nach wenigen Wochen in einer Schublade verschwinden?</strong>
                            Und warum sieht Personalisierung im Internet so oft nach liebloser Massenware aus?
                        </p>
                        <p class="text-gray-700 mb-6 leading-relaxed">
                            Genau deshalb gibt es <strong>Mein Seelenfunke</strong>. In unserer deutschen Manufaktur in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }} verbinden wir hochmoderne 5W UV- und MOPA-Faserlaser-Präzision mit echter Hingabe.
                            Ob vergoldeter Edelstahl-Schmuck, massive Schlüssel- und Haustiermarken, 360° gravierte Kristallgläser oder schweres K9-Vollkristall: Jedes einzelne Unikat wird vor dem Versand von Hand geprüft, poliert und liebevoll verpackt.
                        </p>
                        <div class="p-4 bg-primary/10 rounded-lg">
                            <p class="font-medium text-gray-800">
                                Unser Versprechen: <span class="text-[#8a6820] font-bold">Ein Funke, der bleibt.</span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- RECHTE SEITE: 3 WERTE KACHELN --}}
                <div class="fade-in space-y-6" style="animation-delay: 0.3s;">

                    <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 ml-4">Hochwertige Materialien & High-End Laser</h4>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">Vergoldeter und polierter Edelstahl, schwarz eloxiertes Aluminium und optisch reines K9-Kristallglas statt billigem Plastik. Dauerhaft tiefengraviert für die Ewigkeit.</p>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 ml-4">Deutscher Support & Live-3D-Vorschau</h4>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">Gestalte dein Wunschmotiv vorab live im 3D-Konfigurator. Bei Sonderwünschen erreichst du jederzeit unseren persönlichen, deutschsprachigen Support direkt in Gifhorn.</p>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H4.5a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 ml-4">Eingetragene Marke & Geschenk-Service</h4>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">Als beim DPMA eingetragene Marke liefern wir jedes Stück handgeprüft per DHL, auf Wunsch in unserer mattschwarzen Schmuckschachtel mit Gold-Logo und direkt an den Beschenkten ohne Rechnung im Paket.</p>
                    </div>

                </div>

            </div>
        </div>

        {{-- Customer Feedbacks --}}
        @livewire('frontend.google-reviews')

        <section class="bg-white overflow-hidden rounded-3xl mt-12 shadow-sm border border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-gray-900">

                <div class="text-center mb-16">
                    <h3 class="text-3xl font-bold mb-4 font-serif">Das Herz der Manufaktur</h3>
                    <p class="max-w-3xl mx-auto text-lg text-gray-600 leading-relaxed">
                        Hinter <em>{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}</em> steht eine klare Überzeugung: Wertvolle Erinnerungen verdienen echte Juwelier- und Manufaktur-Qualität.
                        In unserer Werkstatt in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }} veredeln wir edlen Gold-Edelstahlschmuck, massive Schlüssel- und Haustiermarken, 360° Kristallgläser und K9-Vollkristall mit modernster Laser-Technologie.
                    </p>
                </div>

                {{-- Gründerin Spotlight: Alina Steinhauer --}}
                <div class="max-w-4xl mx-auto mb-16">
                    <div class="bg-gradient-to-br from-amber-500/5 via-white to-amber-500/10 border-2 border-primary/30 rounded-3xl p-8 sm:p-12 shadow-xl hover:shadow-2xl transition-all duration-300 relative overflow-hidden flex flex-col md:flex-row items-center gap-8 md:gap-12">

                        <div class="absolute top-0 right-0 bg-primary text-gray-900 text-[10px] font-bold px-4 py-1.5 rounded-bl-xl uppercase tracking-widest shadow-sm">
                            Inhaberin & Handwerksleitung
                        </div>

                        {{-- Profilbild mit edlem Rahmen --}}
                        <div class="relative shrink-0 group">
                            <div class="w-40 h-40 sm:w-48 sm:h-48 rounded-2xl overflow-hidden border-4 border-white shadow-2xl relative z-10 transform group-hover:scale-105 transition-transform duration-500">
                                <img src="{{ asset('shop/projekt/about/gruender-welcome.webp') }}"
                                     alt="{{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}, Gründerin von {{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}"
                                     width="192" height="192"
                                     loading="lazy"
                                     class="w-full h-full object-cover">
                            </div>
                            <div class="absolute -inset-2 bg-primary/20 rounded-2xl blur-lg -z-0"></div>
                        </div>

                        {{-- Text & Zitat --}}
                        <div class="text-center md:text-left flex-1">
                            <h4 class="text-3xl font-serif font-bold text-gray-900 leading-tight">{{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}</h4>
                            <p class="text-[#8a6820] font-bold uppercase tracking-wider text-xs mt-1 mb-4">
                                Gründerin, Markeninhaberin & zertifizierte Laserschutzbeauftragte
                            </p>

                            <blockquote class="text-gray-700 text-base sm:text-lg leading-relaxed italic border-l-0 md:border-l-4 md:border-primary/40 md:pl-4 my-4">
                                "Qualität ist kein Zufall, sondern eine persönliche Haltung. Ich habe diese Manufaktur in Deutschland gegründet, um bleibende Werte zu schaffen. Ob filigranes Gold-Herzcollier, treue Hundemarke oder massiver Seelen-Kristall: Hinter jedem Unikat stehe ich mit meinem Namen und meinem persönlichen Versprechen, dass jedes Stück in unserer Werkstatt mit höchster Laser-Präzision veredelt und von Hand geprüft wird."
                            </blockquote>

                            {{-- Vertrauensmerkmale --}}
                            <div class="mt-6 flex flex-wrap justify-center md:justify-start gap-3">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-800 shadow-xs">
                                    <span>🛡️</span> Fachkunde nach TROS Laserstrahlung
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-800 shadow-xs">
                                    <span>®</span> Eingetragene Marke beim DPMA
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-800 shadow-xs">
                                    <span>🤍</span> Manuelle 4-Augen-Endkontrolle
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-800 shadow-xs">
                                    <span>🇩🇪</span> Deutsche Manufaktur in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}
                                </span>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Produktion & Service Bereich --}}
                <div class="max-w-5xl mx-auto pt-12 border-t border-gray-100">
                    <div class="flex flex-col md:flex-row items-center gap-8 md:gap-12">
                        <div class="w-24 h-24 rounded-2xl bg-primary/10 flex items-center justify-center text-5xl flex-shrink-0 text-primary shadow-inner">
                            🛠️
                        </div>
                        <div class="flex-1 text-center md:text-left">
                            <div class="flex flex-col md:flex-row md:items-center gap-2 mb-4">
                                <h3 class="text-2xl font-bold text-gray-900">High-End Lasertechnik & Handarbeit</h3>
                                <span class="hidden md:block text-gray-300">|</span>
                                <span class="text-[#8a6820] font-bold uppercase tracking-widest text-sm">Handveredelt in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}</span>
                            </div>
                            <p class="text-gray-600 text-lg leading-relaxed">
                                Unser Anspruch ist kompromisslose Präzision auf jedem Material. Mit unserem Dual-Lasersystem aus 5W Kaltlicht-UV-Laser und MOPA-Faserlaser erzielen wir mikrometergenaue Tiefengravuren im Kristallglas sowie tiefschwarze, abriebfeste Anlassgravuren auf vergoldetem Edelstahl und Aluminium. Vom liebevollen Einzelstück bis zur großen Vereins- oder Firmenserie begleitet dich unser deutschsprachiges Team persönlich.
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
        <div class="fade-in space-y-6">

            {{-- Headline: Kürzer, klar & selbstbewusst --}}
            <h2 class="text-3xl md:text-4xl font-serif font-bold leading-tight">
                Hinter diesem Shop steckt kein Algorithmus.<br>
                <span class="text-primary">Sondern echtes Handwerk.</span>
            </h2>

            {{-- Kompakter, klarer Text --}}
            <div class="text-lg text-gray-200 font-light leading-relaxed max-w-2xl mx-auto space-y-3">
                <p>
                    Bei <strong>Mein Seelenfunke</strong> gestaltest du dein Unikat live im 3D-Konfigurator, und wir fertigen es direkt in unserer eigenen Manufaktur in <strong>{{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}</strong>.
                </p>
                <p class="font-medium text-white">
                    Jedes Schmuckstück, jeder Anhänger und jeder Kristall wird mit unserem High-End-Lasersystem präzise veredelt, von Hand geprüft und sicher gepolstert (auf Wunsch in unserer edlen Schmuckbox) versendet.
                </p>
            </div>

            {{-- 4 Vorteile nebeneinander mit goldenem Haken (ohne Kachel-BG & ohne Subtext) --}}
            <div class="flex flex-wrap justify-center items-center gap-x-8 gap-y-3 pt-2">
                <div class="inline-flex items-center gap-2 text-sm font-semibold text-gray-100">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>High-End Laser</span>
                </div>
                <div class="inline-flex items-center gap-2 text-sm font-semibold text-gray-100">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Deutsche Firma</span>
                </div>
                <div class="inline-flex items-center gap-2 text-sm font-semibold text-gray-100">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Echtzeit-Vorschau</span>
                </div>
                <div class="inline-flex items-center gap-2 text-sm font-semibold text-gray-100">
                    <svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>DPMA-Marke</span>
                </div>
            </div>

            {{-- CTA Buttons --}}
            <div class="pt-6 flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="{{ route('shop') }}"
                   title="Jetzt Unikat im 3D-Konfigurator gestalten"
                   class="inline-flex items-center gap-3 bg-primary text-gray-900 px-9 py-4 rounded-full font-bold text-lg shadow-[0_0_25px_rgba(201,166,107,0.3)] hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105">
                    <span>Jetzt Unikat im Shop gestalten</span>
                </a>
                <a href="{{ route('calculator') }}"
                   title="Angebot für personalisierte Geschenke online berechnen"
                   class="inline-flex items-center gap-3 bg-white/10 border border-primary/50 text-white px-8 py-4 rounded-full font-semibold text-lg hover:bg-primary hover:text-gray-900 transition-all transform hover:scale-105">
                    <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>B2B- & Mengen-Kalkulator</span>
                </a>
            </div>
        </div>
    </div>

    <div class="relative flex overflow-hidden group/container pt-8" aria-hidden="true">

        {{-- Erster Animations-Block --}}
        <div class="flex items-center gap-20 md:gap-32 animate-marquee whitespace-nowrap flex-shrink-0">
            @for ($i = 0; $i < 10; $i++)
                <div class="flex items-center group">
                    {{-- Logo: Harmonische Größe (h-12 auf Mobile, h-20 auf Desktop) --}}
                    <img src="{{ asset('shop/projekt/logo/mein-seelenfunke-logo.svg') }}"
                         alt="Mein Seelenfunke Logo"
                         width="600" height="229"
                         loading="lazy"
                         class="h-24 md:h-20 w-auto opacity-40 group-hover:opacity-100 transition-all duration-700 ease-in-out transform group-hover:scale-110">
                </div>
            @endfor
        </div>

        {{-- Zweiter Animations-Block (Loop-Kopie) --}}
        <div class="flex items-center gap-20 md:gap-32 animate-marquee whitespace-nowrap flex-shrink-0 ml-20 md:ml-32">
            @for ($i = 0; $i < 10; $i++)
                <div class="flex items-center group">
                    <img src="{{ asset('shop/projekt/logo/mein-seelenfunke-logo.svg') }}"
                         alt="Mein Seelenfunke Logo"
                         width="600" height="229"
                         loading="lazy"
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
