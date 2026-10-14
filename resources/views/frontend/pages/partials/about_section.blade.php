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
                   title="Zum Online-Shop für handgefertigte Unikate"
                   class="inline-flex items-center px-8 py-3 bg-primary text-gray-900 text-base font-medium rounded-full shadow-lg hover:bg-primary-dark hover:text-white transition transform hover:scale-105">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 2.25a.75.75 0 000 1.5h1.386c.17 0 .318.114.362.278l2.558 9.592a3.752 3.752 0 00-2.806 3.63c0 .414.336.75.75.75h15.75a.75.75 0 000-1.5H5.378A2.25 2.25 0 017.5 15h11.218a.75.75 0 00.674-.421 60.358 60.358 0 002.96-7.228.75.75 0 00-.525-.965A60.864 60.864 0 005.68 4.509l-.232-.867A1.875 1.875 0 003.636 2.25H2.25zM3.75 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM16.5 20.25a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z" /></svg>
                    Zum Shop
                </a>

                {{-- 2. Button: E-Mail Kontakt --}}
                <a href="mailto:kontakt@mein-seelenfunke.de"
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
                                 alt="Alina Steinhauer, Gründerin von Mein Seelenfunke"
                                 width="64" height="64"
                                 loading="lazy"
                                 class="w-16 h-16 rounded-full object-cover border-2 border-primary" />
                            <div>
                                <div class="text-[#8a6820] text-4xl font-serif leading-none mb-1" aria-hidden="true">“</div>
                                <h3 class="text-xl font-bold text-gray-800">Alina Steinhauer</h3>
                                <p class="text-sm text-[#8a6820] font-semibold">Gründerin & Inhaberin</p>
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
                            <h4 class="text-lg font-bold text-gray-900 ml-4">Premium Qualität</h4>
                        </div>
                        <p class="text-gray-600 text-sm">Hochreines K9-Kristallglas statt billigem Plastik. Eine Gravur, die ewig hält.</p>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 ml-4">Ansprechpartner</h4>
                        </div>
                        <p class="text-gray-600 text-sm">Wir beraten dich gerne schnell und direkt per Mail.</p>
                    </div>

                    <div class="bg-white rounded-2xl p-6 shadow-md hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
                        <div class="flex items-center mb-3">
                            <div class="w-12 h-12 bg-primary/10 group-hover:bg-primary transition-colors rounded-full flex items-center justify-center text-primary group-hover:text-white">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
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
        @livewire('frontend.google-reviews')

        <section class="bg-white overflow-hidden rounded-3xl mt-12 shadow-sm border border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-gray-900">

                <div class="text-center mb-16">
                    <h3 class="text-3xl font-bold mb-4 font-serif">Das Herz der Manufaktur</h3>
                    <p class="max-w-2xl mx-auto text-lg text-gray-600 leading-relaxed">
                        Hinter <em>Mein Seelenfunke</em> steht eine klare Vision: Hochwertiges Kristallglas mit modernster Veredelungstechnik zu verbinden.
                        Wir sind kein anonymer Großkonzern, sondern eine spezialisierte Manufaktur in Gifhorn, die für Qualität, Service und echte Werte steht.
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
                                     alt="Alina Steinhauer, Gründerin von Mein Seelenfunke"
                                     width="192" height="192"
                                     loading="lazy"
                                     class="w-full h-full object-cover">
                            </div>
                            <div class="absolute -inset-2 bg-primary/20 rounded-2xl blur-lg -z-0"></div>
                        </div>

                        {{-- Text & Zitat --}}
                        <div class="text-center md:text-left flex-1">
                            <h4 class="text-3xl font-serif font-bold text-gray-900 leading-tight">Alina Steinhauer</h4>
                            <p class="text-[#8a6820] font-bold uppercase tracking-wider text-xs mt-1 mb-4">
                                Gründerin & zertifizierte Laserschutzbeauftragte
                            </p>

                            <blockquote class="text-gray-700 text-base sm:text-lg leading-relaxed italic border-l-0 md:border-l-4 md:border-primary/40 md:pl-4 my-4">
                                "Qualität ist kein Zufall, sondern eine persönliche Haltung. Ich habe diese Manufaktur gegründet, um bleibende Werte zu schaffen. Hinter jedem Seelenkristall stehe ich mit meinem Namen und der Garantie, dass jedes Stück in unserer Gifhorner Werkstatt mit echter Sorgfalt von Hand gefertigt und geprüft wird."
                            </blockquote>

                            {{-- Vertrauensmerkmale --}}
                            <div class="mt-6 flex flex-wrap justify-center md:justify-start gap-3">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-800 shadow-xs">
                                    <span>🛡️</span> Fachkunde nach TROS Laserstrahlung
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-800 shadow-xs">
                                    <span>🤍</span> Manuelle Endkontrolle
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white border border-gray-200 text-gray-800 shadow-xs">
                                    <span>🇩🇪</span> Handwerk aus Gifhorn
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
                                <h3 class="text-2xl font-bold text-gray-900">Produktion & Service</h3>
                                <span class="hidden md:block text-gray-300">|</span>
                                <span class="text-[#8a6820] font-bold uppercase tracking-widest text-sm">Made in Gifhorn</span>
                            </div>
                            <p class="text-gray-600 text-lg leading-relaxed">
                                Unser Anspruch ist absolute Präzision. Wir kombinieren traditionelle handwerkliche Werte mit zertifizierter, modernster Sicherheitstechnik. Egal ob persönliches Einzelstück oder komplexer Firmenauftrag: Unser eingespieltes Team sorgt für einen reibungslosen Ablauf und Ergebnisse, die begeistern.
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
                   title="Angebot für personalisierte Geschenke online berechnen"
                   class="inline-flex items-center gap-3 bg-primary text-white px-10 py-5 rounded-full font-bold text-lg shadow-[0_0_25px_rgba(201,166,107,0.3)] hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 hover:shadow-[0_0_40px_rgba(255,255,255,0.5)]">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
