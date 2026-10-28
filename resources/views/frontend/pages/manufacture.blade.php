@php
    $aboutSchema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'AboutPage',
                '@id' => route('manufacture') . '#aboutpage',
                'url' => route('manufacture'),
                'name' => 'Die Manufaktur & Qualitätsversprechen – Mein Seelenfunke',
                'description' => 'Erfahre mehr über Gründerin Alina Steinhauer, unsere Lasergravur-Manufaktur in Gifhorn, veredelte Materialien (K9-Kristallglas, Naturschiefer, Metall) und geprüfte Sicherheitsstandards.',
                'mainEntity' => [
                    '@id' => url('/#organization'),
                ],
            ],
            [
                '@type' => 'Person',
                '@id' => url('/manufaktur#founder'),
                'name' => shop_setting('owner_proprietor', 'Alina Steinhauer'),
                'jobTitle' => 'Gründerin & Inhaberin',
                'image' => asset('shop/projekt/about/gruender-profil-b.webp'),
                'worksFor' => [
                    '@id' => url('/#organization'),
                ],
                'url' => route('manufacture'),
            ],
        ],
    ];
@endphp
<x-layouts.frontend_layout
    title="Die Manufaktur, Materialkunde & Alina Steinhauer | Mein Seelenfunke"
    meta_description="Blick hinter die Kulissen unserer Lasergravur-Manufaktur in Gifhorn: Gründerin Alina Steinhauer, optisches K9-Kristallglas, Naturschiefer & höchste Präzision."
    :og_image="asset('shop/projekt/about/gruender-profil-b.webp')">
    @push('structured_data')
        <script type="application/ld+json">{!! json_encode($aboutSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush
    <div x-data="{ showCertModal: false }">
        <x-sections.page-container>

        {{--
                    NEUE SECTION: DIE GRÜNDERIN (Alina Steinhauer)
                    Modern, interaktiv und mit Fokus auf Langfristigkeit
                --}}
        <section class="py-12 md:py-16 bg-white overflow-hidden" aria-labelledby="founder-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="flex flex-col lg:flex-row items-center gap-16">

                    {{-- Linke Seite: Interaktives Profilbild mit Link zu #contact --}}
                    <div class="w-full lg:w-5/12 relative">
                        <a href="#contact" class="relative z-10 group cursor-pointer block">
                            {{-- Dekorativer Hintergrund-Shape --}}
                            <div class="absolute -inset-6 bg-primary/10 rounded-full scale-95 group-hover:scale-110 transition-transform duration-700 ease-in-out opacity-50 blur-2xl" aria-hidden="true"></div>

                            {{-- Das Bild mit Rahmen-Animation --}}
                            <div class="relative overflow-hidden rounded-2xl shadow-2xl aspect-[4/5] border-4 border-white transform transition-all duration-500 group-hover:-translate-y-2 group-hover:shadow-primary/20">
                                <img src="{{ asset('shop/projekt/about/gruender-profil-b.webp') }}"
                                     alt="Alina Steinhauer, Gründerin von Mein-Seelenfunke"
                                     class="object-cover w-full h-full transform transition-scale duration-1000 group-hover:scale-110">

                                {{-- Herz-Icon & "Kontakt"-Hinweis Overlay bei Hover --}}
                                <div class="absolute inset-0 bg-primary/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col items-center justify-center">
                                    <div class="bg-white/90 p-4 rounded-full animate-pulse mb-4">
                                        <svg class="w-8 h-8 text-primary" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                    </div>
                                    <span class="text-white font-bold tracking-wider uppercase text-sm drop-shadow-md">Jetzt Kontakt aufnehmen</span>
                                </div>
                            </div>

                            {{-- Experience-Badge (im edlen hellen Creme-/Champagner-Farbton wie die Gutscheinkarte) --}}
                            <div class="absolute right-2 -bottom-4 sm:-right-6 sm:-bottom-6 bg-gradient-to-br from-[#FAF7F2] via-[#FDFBF7] to-[#F3ECE0] border-2 border-primary/40 text-gray-900 p-4 sm:p-6 rounded-2xl shadow-xl transform transition-transform group-hover:rotate-6 group-hover:scale-110 duration-300 overflow-hidden">
                                <div class="absolute top-2 left-2 w-3 h-3 border-t-2 border-l-2 border-primary/60"></div>
                                <div class="absolute top-2 right-2 w-3 h-3 border-t-2 border-r-2 border-primary/60"></div>
                                <div class="absolute bottom-2 left-2 w-3 h-3 border-b-2 border-l-2 border-primary/60"></div>
                                <div class="absolute bottom-2 right-2 w-3 h-3 border-b-2 border-r-2 border-primary/60"></div>
                                <span class="block text-3xl font-serif font-bold text-primary leading-none mb-1">14+</span>
                                <span class="block text-[10px] uppercase tracking-widest font-extrabold text-gray-800">Jahre IT-Power</span>
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
                            <span class="uppercase tracking-wider">Mein Traum!</span>
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
                            <p class="text-primary font-bold text-2xl transition-transform duration-300 group-hover:scale-125 group-hover:-translate-y-1 inline-block">2012 - 2015</p>
                            <h4 class="text-gray-900 font-bold text-sm uppercase mt-2">Das Fundament</h4>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                Ausbildung zur Fachinformatikerin für Systemintegration. Hier lernte ich die Basis: Netzwerke, Serverstrukturen und Troubleshooting von der Pike auf.
                            </p>
                        </div>

                        {{-- Station 2: 2015-2019 --}}
                        <div class="bg-white px-4 group cursor-default max-w-xs flex flex-col items-center md:items-start text-center md:text-left">
                            <p class="text-primary font-bold text-2xl transition-transform duration-300 group-hover:scale-125 group-hover:-translate-y-1 inline-block">2015 - 2019</p>
                            <h4 class="text-gray-900 font-bold text-sm uppercase mt-2">IT-Operations & Support</h4>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                                Einsatz im 1st- und 2nd-Level-Support, unter anderem für das DLR. Verantwortung für komplexe IT-Umgebungen und Anwendersysteme.
                            </p>
                        </div>

                        {{-- Station 3: 2019-2023 --}}
                        <div class="bg-white px-4 group cursor-default max-w-xs flex flex-col items-center md:items-start text-center md:text-left">
                            <p class="text-primary font-bold text-2xl transition-transform duration-300 group-hover:scale-125 group-hover:-translate-y-1 inline-block">2019 - 2023</p>
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
            SECTION 1: HERO (Dunkel & Edel mit warmem Manufaktur-Hintergrundbild)
            Fokus auf das Team und Technik.
        --}}
        <section class="relative bg-stone-950 border-y border-stone-800/80 pt-32 pb-24 lg:pt-40 lg:pb-32 overflow-hidden" aria-label="Einführung Manufaktur">
            {{-- Hintergrundbild: Wo Licht auf Materie trifft --}}
            <img src="{{ asset('shop/frontend/banners/manufaktur_licht_materie.webp') }}"
                 alt="Wo Licht auf Materie trifft – Laserstrahl trifft auf K9 Kristallglas in der Manufaktur"
                 loading="lazy"
                 decoding="async"
                 data-is-ai-generated="true"
                 class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none select-none">

            {{-- Ruhiger, dezenter Abdunklungs-Verlauf für gute Sichtbarkeit des Hintergrundbildes --}}
            <div class="absolute inset-0 bg-gradient-to-b from-stone-950/45 via-stone-950/25 to-stone-950/55 pointer-events-none" aria-hidden="true"></div>

            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <span class="text-primary font-bold tracking-[0.2em] uppercase text-xs sm:text-sm mb-4 block drop-shadow">
                    Made in Germany
                </span>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-serif font-bold text-white leading-tight mb-6 drop-shadow-lg">
                    Wo Licht auf <span class="text-primary">Materie</span> trifft.
                </h1>
                <p class="text-lg sm:text-xl text-gray-200 max-w-3xl mx-auto leading-relaxed drop-shadow">
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
            SECTION 3: DER PROZESS (Zig-Zag Layout mit GPU-beschleunigten Mikro- & Hover-Animationen)
            Detaillierte Erklärung der Schritte aus EURER Sicht
        --}}
        <section class="py-20 bg-gray-50 overflow-hidden" aria-labelledby="prozess-heading">
            <style>
                .sf-card {
                    transform: translate3d(0, 0, 0);
                    transition: transform 0.75s cubic-bezier(0.22, 0.61, 0.36, 1);
                    will-change: transform;
                }
                .sf-card.sf-is-active {
                    transform: translate3d(0, -6px, 0);
                }
                .sf-card-img {
                    transform: scale(1) translateZ(0);
                    transition: transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1);
                    will-change: transform;
                }
                .sf-card.sf-is-active .sf-card-img {
                    transform: scale(1.045) translateZ(0);
                }
                .sf-card-sheen {
                    position: absolute;
                    inset: -100%;
                    background: linear-gradient(
                        135deg,
                        rgba(255, 255, 255, 0) 40%,
                        rgba(255, 251, 235, 0.06) 44%,
                        rgba(255, 248, 225, 0.22) 47.5%,
                        rgba(255, 246, 215, 0.44) 50%,
                        rgba(255, 248, 225, 0.22) 52.5%,
                        rgba(255, 251, 235, 0.06) 56%,
                        rgba(255, 255, 255, 0) 60%
                    );
                    transform: translate3d(-32%, -32%, 0);
                    transition: transform 1.45s cubic-bezier(0.22, 0.61, 0.36, 1);
                    will-change: transform;
                }
                .sf-card.sf-is-active .sf-card-sheen {
                    transform: translate3d(32%, 32%, 0);
                }
                @keyframes sfSparkleFloatA {
                    0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
                    33% { transform: translate3d(4px, -6px, 0) rotate(18deg); }
                    66% { transform: translate3d(-3px, -2px, 0) rotate(-12deg); }
                }
                @keyframes sfSparkleFloatB {
                    0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
                    33% { transform: translate3d(-5px, -4px, 0) rotate(-20deg); }
                    66% { transform: translate3d(3px, -7px, 0) rotate(14deg); }
                }
                .sf-card-sparkle {
                    opacity: 0;
                    transform: scale(0.35) translateZ(0);
                    transition:
                        opacity 0.75s cubic-bezier(0.22, 0.61, 0.36, 1) 0s,
                        transform 0.85s cubic-bezier(0.22, 0.61, 0.36, 1) 0s;
                    will-change: transform, opacity;
                }
                .sf-card.sf-is-active .sf-card-sparkle {
                    opacity: 0.94;
                    transform: scale(1) translateZ(0);
                }
                .sf-card.sf-is-active .sf-card-sparkle-1 { transition-delay: 0.04s; }
                .sf-card.sf-is-active .sf-card-sparkle-2 { transition-delay: 0.11s; }
                .sf-card.sf-is-active .sf-card-sparkle-3 { transition-delay: 0.18s; }
                .sf-card.sf-is-active .sf-card-sparkle-4 { transition-delay: 0.25s; }
                .sf-card.sf-is-active .sf-card-sparkle-5 { transition-delay: 0.32s; }
                .sf-card.sf-is-active .sf-card-sparkle-6 { transition-delay: 0.39s; }
                .sf-card-sparkle > svg { display: block; width: 100%; height: 100%; will-change: transform; }
                .sf-card-sparkle-1 > svg { animation: sfSparkleFloatA 3.4s ease-in-out infinite; }
                .sf-card-sparkle-2 > svg { animation: sfSparkleFloatB 4.0s ease-in-out infinite 0.4s; }
                .sf-card-sparkle-3 > svg { animation: sfSparkleFloatB 3.2s ease-in-out infinite 0.9s; }
                .sf-card-sparkle-4 > svg { animation: sfSparkleFloatA 3.8s ease-in-out infinite 1.3s; }
                .sf-card-sparkle-5 > svg { animation: sfSparkleFloatA 4.2s ease-in-out infinite 0.7s; }
                .sf-card-sparkle-6 > svg { animation: sfSparkleFloatB 3.6s ease-in-out infinite 1.6s; }
            </style>
            <script>
                window.sfRandomizeCardSparkles = window.sfRandomizeCardSparkles || function(cardEl) {
                    const zones = [
                        { top: [10, 28], left: [10, 35] },
                        { top: [10, 28], left: [62, 86] },
                        { top: [36, 60], left: [8, 28] },
                        { top: [36, 60], left: [68, 88] },
                        { top: [66, 86], left: [12, 40] },
                        { top: [66, 86], left: [58, 86] },
                    ];
                    const rand = (min, max) => (min + Math.random() * (max - min)).toFixed(1) + '%';
                    cardEl.querySelectorAll('.sf-card-sparkle').forEach((el, i) => {
                        const z = zones[i % zones.length];
                        el.style.top = rand(z.top[0], z.top[1]);
                        el.style.left = rand(z.left[0], z.left[1]);
                        el.style.right = 'auto';
                        el.style.bottom = 'auto';
                    });
                };
                window.sfCardHoverEnter = window.sfCardHoverEnter || function(cardEl) {
                    clearTimeout(cardEl._sfLeaveTimer);
                    clearTimeout(cardEl._sfCleanupTimer);
                    if (!cardEl._sfBusy) {
                        window.sfRandomizeCardSparkles(cardEl);
                        void cardEl.offsetWidth;
                    }
                    cardEl._sfBusy = true;
                    if (!cardEl.classList.contains('sf-is-active')) {
                        cardEl._sfEnterTime = performance.now();
                        cardEl.classList.add('sf-is-active');
                    }
                };
                window.sfCardHoverLeave = window.sfCardHoverLeave || function(cardEl) {
                    clearTimeout(cardEl._sfLeaveTimer);
                    clearTimeout(cardEl._sfCleanupTimer);
                    const minForward = 950;
                    const elapsed = cardEl._sfEnterTime ? (performance.now() - cardEl._sfEnterTime) : minForward;
                    const waitMs = Math.max(0, minForward - elapsed);
                    cardEl._sfLeaveTimer = setTimeout(() => {
                        cardEl.classList.remove('sf-is-active');
                        cardEl._sfCleanupTimer = setTimeout(() => {
                            cardEl._sfBusy = false;
                        }, 1350);
                    }, waitMs);
                };
            </script>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">

                {{-- Header für Screenreader / SEO Struktur --}}
                <h2 id="prozess-heading" class="sr-only">Der Fertigungsprozess unserer Glas-Unikate</h2>

                {{-- SCHRITT 1: Das Rohmaterial --}}
                <article class="flex flex-col md:flex-row items-center gap-12">
                    <div onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                         onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                         class="sf-card w-full md:w-1/2 relative group cursor-pointer">
                        <div class="absolute -inset-4 bg-primary/20 rounded-2xl transform rotate-2 transition-all duration-700 group-hover:rotate-1 group-hover:bg-primary/30" aria-hidden="true"></div>
                        <div class="relative rounded-xl shadow-xl overflow-hidden h-80 bg-[#F5EFE6]">
                            <img src="{{ asset('shop/frontend/other/k9_glas.webp') }}"
                                 alt="Hochreiner K9 Kristallglas Rohling vor der Gravur"
                                 loading="lazy"
                                 class="sf-card-img w-full h-full object-cover">

                            <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                            @for($sp = 1; $sp <= 6; $sp++)
                                <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-4 h-4 text-amber-400' : 'w-3.5 h-3.5 text-amber-300' }} drop-shadow-[0_0_6px_rgba(245,158,11,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                            @endfor
                        </div>
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
                    <div onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                         onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                         class="sf-card w-full md:w-1/2 relative group cursor-pointer">
                        <div class="absolute -inset-4 bg-gray-200 rounded-2xl transform -rotate-2 transition-all duration-700 group-hover:-rotate-1 group-hover:bg-primary/25" aria-hidden="true"></div>
                        <div class="relative rounded-xl shadow-xl overflow-hidden h-80 bg-[#EAEDF2]">
                            <img src="{{ asset('shop/frontend/process/lasergravur.webp') }}"
                                 onerror="this.src='https://placehold.co/600x400/333/FFF?text=Laser+Technologie'"
                                 alt="Präzise 3D-Lasergravur im Inneren des Glases"
                                 loading="lazy"
                                 class="sf-card-img w-full h-full object-cover">

                            <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                            @for($sp = 1; $sp <= 6; $sp++)
                                <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-4 h-4 text-amber-400' : 'w-3.5 h-3.5 text-amber-300' }} drop-shadow-[0_0_6px_rgba(245,158,11,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                            @endfor
                        </div>
                    </div>
                    <div class="w-full md:w-1/2">
                        <div class="flex items-center mb-4">
                            <span class="text-5xl font-serif text-gray-200 font-bold mr-4" aria-hidden="true">02</span>
                            <h3 class="text-2xl font-bold text-gray-900">Präzision & Sicherheit</h3>
                        </div>
                        <p class="text-gray-600 leading-relaxed mb-4">
                            Eine <strong class="text-gray-900">zertifizierte Laserschutzbeauftragte</strong> (<button @click="showCertModal = true" class="text-primary hover:underline font-semibold focus:outline-none">Zertifikat ansehen</button>) ist in unserem Unternehmen tätig. Mit modernster Lasertechnologie brennen wir Ihr Motiv dauerhaft in das Glas ein.
                            Dabei entstehen winzige, präzise Veränderungen im Material (Weiß-Effekt).
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            Das Ergebnis ist eine Veredelung, die im Gegensatz zum Bedrucken niemals verblasst. Wir fertigen mit technischem Verstand und sicherem Handwerk.
                        </p>
                    </div>
                </article>

                {{-- SCHRITT 3: Finish & Kontrolle --}}
                <article class="flex flex-col md:flex-row items-center gap-12">
                    <div onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                         onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                         class="sf-card w-full md:w-1/2 relative group cursor-pointer">
                        <div class="absolute -inset-4 bg-primary/20 rounded-2xl transform rotate-2 transition-all duration-700 group-hover:rotate-1 group-hover:bg-primary/30" aria-hidden="true"></div>
                        <div class="relative rounded-xl shadow-xl overflow-hidden h-80 bg-[#F5EFE6]">
                            <img src="{{ asset('shop/frontend/other/white_hands.webp') }}"
                                 alt="Manuelle Endkontrolle und Reinigung des Kristalls mit Handschuhen"
                                 loading="lazy"
                                 class="sf-card-img w-full h-full object-cover">

                            <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                            @for($sp = 1; $sp <= 6; $sp++)
                                <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-4 h-4 text-amber-400' : 'w-3.5 h-3.5 text-amber-300' }} drop-shadow-[0_0_6px_rgba(245,158,11,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                            @endfor
                        </div>
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
            SECTION 4: ZERTIFIZIERUNG / FACTS (Dunkel, Matt & Ruhig über die Seite)
            Unternehmens-Kompetenznachweis
        --}}
        <section class="relative bg-stone-950 border-y border-stone-800/80 py-24 lg:py-32 overflow-hidden" aria-labelledby="kompetenz-heading">
            {{-- Hintergrundbild: Geprüfte Sicherheitsstandards & Präzisionsoptik --}}
            <img src="{{ asset('shop/frontend/banners/manufaktur_sicherheit_kompetenz.webp') }}"
                 alt="Geprüfte Sicherheitsstandards – Laserschutzoptik, Messschraube und Kristallglas auf dunkler Werkbank"
                 loading="lazy"
                 decoding="async"
                 data-is-ai-generated="true"
                 class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none select-none">

            {{-- Dezenter, matter Verlauf, damit das Hintergrundbild klar erkennbar bleibt --}}
            <div class="absolute inset-0 bg-gradient-to-b from-stone-950/50 via-stone-950/30 to-stone-950/60 pointer-events-none" aria-hidden="true"></div>

            <div class="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-center">

                    {{-- Linke Spalte: Text & kurze TROS-Erklärung (7 Spalten) --}}
                    <div class="lg:col-span-7 text-center lg:text-left">
                        <span class="text-primary font-bold tracking-[0.2em] uppercase text-xs sm:text-sm mb-3 block drop-shadow">
                            Geprüfte Sicherheitsstandards
                        </span>

                        <h2 id="kompetenz-heading" class="text-3xl sm:text-4xl lg:text-5xl font-serif font-bold text-white leading-tight mb-5 drop-shadow-lg">
                            Unsere Kompetenz für Ihre <span class="text-primary">Sicherheit</span>.
                        </h2>

                        <p class="text-base sm:text-lg text-stone-200 mb-4 leading-relaxed drop-shadow max-w-2xl mx-auto lg:mx-0">
                            Wir arbeiten mit Hochleistungslasern der Klasse 4. Um höchste Qualität und Sicherheit zu gewährleisten,
                            haben wir uns intensiv fortgebildet und staatlich anerkannt als Laserschutzbeauftragte prüfen lassen.
                        </p>

                        <p class="text-sm sm:text-base text-stone-300 leading-relaxed drop-shadow max-w-2xl mx-auto lg:mx-0">
                            Die Zertifizierung nach <strong class="text-primary font-semibold">TROS</strong> <span class="text-stone-400">(Technische Regeln zur Arbeitsschutzverordnung zu künstlicher optischer Strahlung)</span> bestätigt den rechtssicheren, fachkundigen Einsatz unserer Industrieklasse-4-Lasersysteme – für echtes, präzises Manufaktur-Handwerk nach strengen deutschen Standards.
                        </p>
                    </div>

                    {{-- Rechte Spalte: Das Zertifikat edel in Szene gesetzt mit dem "Unsere Kollektion" Hover-Licht- & Sterne-Effekt --}}
                    <div class="lg:col-span-5 flex justify-center">
                        <div onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                             onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                             @click="showCertModal = true"
                             role="button"
                             tabindex="0"
                             @keydown.enter.prevent="showCertModal = true"
                             aria-label="Zertifikat Laserschutzbeauftragte vergrößern"
                             class="sf-card relative w-full max-w-[300px] sm:max-w-[330px] bg-[#FAF7F2] p-3.5 rounded-3xl shadow-2xl border-2 border-primary/55 cursor-pointer group select-none">

                            {{-- Dezente goldene Eck-Akzente --}}
                            <div class="absolute top-2 left-2 w-3 h-3 border-t-2 border-l-2 border-primary/60 rounded-tl-xs pointer-events-none"></div>
                            <div class="absolute top-2 right-2 w-3 h-3 border-t-2 border-r-2 border-primary/60 rounded-tr-xs pointer-events-none"></div>

                            {{-- Zertifikat-Bild mit Licht-Glanz (sf-card-sheen) & Funkel-Sternen (sf-card-sparkle) --}}
                            <div class="relative rounded-2xl overflow-hidden bg-white border border-stone-200/80 aspect-[1/1.414] shadow-inner">
                                <img src="{{ asset('shop/frontend/certificates/Laserschutzbeauftragte_Zertifikat_Alina_Steinhauer.webp') }}"
                                     alt="Zertifikat Laserschutzbeauftragte – Alina Steinhauer"
                                     loading="lazy"
                                     class="sf-card-img w-full h-full object-cover object-top">

                                {{-- Diagonaler Licht-Effekt wie bei "Unsere Kollektion" --}}
                                <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>

                                {{-- Schwebe-Sterne wie bei "Unsere Kollektion" --}}
                                @for($sp = 1; $sp <= 6; $sp++)
                                    <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-4 h-4 text-amber-400' : 'w-3.5 h-3.5 text-amber-300' }} drop-shadow-[0_0_6px_rgba(245,158,11,0.9)] z-20" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                    </span>
                                @endfor
                            </div>

                            {{-- Edle Fußzeile unter dem Zertifikat --}}
                            <div class="mt-3 px-1.5 flex items-center justify-between gap-2">
                                <div class="text-left min-w-0">
                                    <span class="block text-[10px] font-extrabold uppercase tracking-wider text-primary-dark truncate">Geprüftes Originalzertifikat</span>
                                    <span class="block text-[11px] font-medium text-stone-600 truncate">Alina Steinhauer • OStrV / TROS</span>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary text-stone-950 text-[11px] font-bold shrink-0 group-hover:bg-[#b8955a] transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
                                    </svg>
                                    <span>Ansehen</span>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        {{--
            SECTION 5: HERZENSPROJEKTE (Handwerk mit Herz & Soziales Engagement)
            Verbindung von Präzision mit regionaler Fürsorge
        --}}
        <section id="herzensprojekte" class="py-24 bg-gradient-to-b from-white via-amber-500/5 to-white overflow-hidden" aria-labelledby="herzensprojekte-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                {{-- Header Badge & Title --}}
                <div class="max-w-3xl mx-auto text-center mb-16">
                    <div class="inline-flex items-center space-x-2 px-4 py-1.5 rounded-full bg-primary/15 text-primary text-sm font-bold mb-4 shadow-sm">
                        <span class="text-base" aria-hidden="true">❤️</span>
                        <span class="uppercase tracking-wider">Verantwortung & Engagement</span>
                    </div>

                    <h2 id="herzensprojekte-heading" class="text-3xl sm:text-4xl lg:text-5xl font-serif font-bold text-gray-900 leading-tight mb-6">
                        Handwerk mit Herz: <br>
                        <span class="text-primary italic">Ein Funke, der Gutes tut</span>.
                    </h2>

                    <p class="text-lg text-gray-600 leading-relaxed">
                        Für uns bei <strong class="text-gray-900">Mein Seelenfunke</strong> ist echte Handwerkskunst untrennbar mit Menschlichkeit verbunden. Wir glauben fest daran, dass ein Unternehmen der Gesellschaft und besonders den Kleinsten unter uns etwas zurückgeben muss.
                    </p>
                </div>

                {{-- 3 Säulen unseres Engagements --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                    {{-- Säule 1 --}}
                    <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:shadow-xl hover:border-primary/30 transition-all duration-300 flex flex-col group">
                        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 text-primary flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-inner">
                            🧸
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Kitas & Kinder vor Ort</h3>
                        <p class="text-sm text-gray-600 leading-relaxed flex-grow">
                            Unsere Hilfe geht direkt an Kindertagesstätten, Kindergärten und kleine Bildungseinrichtungen in Gifhorn und Umgebung, etwa mit handgefertigtem Holzspielzeug, Fördermaterialien und Projekten zum Mitmachen.
                        </p>
                        <div class="mt-6 pt-4 border-t border-gray-100 flex items-center text-xs font-semibold text-primary">
                            <span>Regional & greifbar</span>
                        </div>
                    </div>

                    {{-- Säule 2 --}}
                    <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:shadow-xl hover:border-primary/30 transition-all duration-300 flex flex-col group">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-inner">
                            🎁
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Fester Teil jeder Bestellung</h3>
                        <p class="text-sm text-gray-600 leading-relaxed flex-grow">
                            Kein Spendenaufruf an der Kasse, kein Cent Aufpreis für dich. Wir spenden bei jeder einzelnen Bestellung einen festen Betrag aus unseren eigenen Unternehmenserlösen direkt in den Herzensprojekte-Fonds.
                        </p>
                        <div class="mt-6 pt-4 border-t border-gray-100 flex items-center text-xs font-semibold text-emerald-600">
                            <span>100% aus Unternehmensmitteln</span>
                        </div>
                    </div>

                    {{-- Säule 3 --}}
                    <div class="bg-white rounded-2xl p-8 border border-gray-100 shadow-sm hover:shadow-xl hover:border-primary/30 transition-all duration-300 flex flex-col group">
                        <div class="w-14 h-14 rounded-2xl bg-blue-500/10 text-blue-600 flex items-center justify-center text-3xl mb-6 group-hover:scale-110 transition-transform shadow-inner">
                            🤝
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Persönlich & Transparent</h3>
                        <p class="text-sm text-gray-600 leading-relaxed flex-grow">
                            Wir übergeben Sachmittel und Unterstützung persönlich vor Ort. In unserer Community und auf unserem Blog teilen wir Einblicke, damit du genau siehst, wo die Freude bei den Kindern ankommt.
                        </p>
                        <div class="mt-6 pt-4 border-t border-gray-100 flex items-center text-xs font-semibold text-blue-600">
                            <span>Nachvollziehbar & ehrlich</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        {{--
            SECTION 5B: UNSERE VISION & HALTUNG (Dunkel, Matt & Ruhig über die Seite)
        --}}
        <section class="relative bg-stone-950 border-y border-stone-800/80 py-24 lg:py-32 overflow-hidden" aria-label="Unsere Vision und Haltung">
            {{-- Hintergrundbild: Unsere Vision & Haltung --}}
            <img src="{{ asset('shop/frontend/banners/manufaktur_vision_haltung.webp') }}"
                 alt="Unsere Vision & Haltung – Handgefertigtes Holz- und Kristallherz bei warmem Kerzenschein in der Manufaktur"
                 loading="lazy"
                 decoding="async"
                 data-is-ai-generated="true"
                 class="absolute inset-0 w-full h-full object-cover object-center pointer-events-none select-none">

            {{-- Dezenter, matter Verlauf für gute Sichtbarkeit des Hintergrundbildes --}}
            <div class="absolute inset-0 bg-gradient-to-b from-stone-950/50 via-stone-950/30 to-stone-950/60 pointer-events-none" aria-hidden="true"></div>

            <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <span class="text-primary font-bold tracking-[0.2em] uppercase text-xs sm:text-sm mb-4 block drop-shadow">
                    Unsere Vision & Haltung
                </span>

                <blockquote class="text-lg sm:text-xl lg:text-2xl font-serif italic text-stone-100 mb-10 leading-relaxed drop-shadow">
                    „Ich stehe mit ganzem Herzen dafür, meinen Kunden mit liebevoll handveredelten Unikaten pure Freude zu schenken – und gleichzeitig soziale Fürsorge für Kinder aktiv zu leben. Ein Seelenfunke soll nicht nur im Zuhause unserer Kunden ein warmes Leuchten entfachen, sondern zeitgleich auch in Kinderaugen echte Begeisterung und ein Lächeln zaubern. Pure Freude beim Kunden und soziales Engagement für Kinder gehören für mich untrennbar zusammen.“
                </blockquote>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-6 sm:gap-10">
                    <div class="flex items-center gap-3.5 text-left">
                        <div class="w-11 h-11 rounded-full overflow-hidden border border-primary/60 shrink-0">
                            <img src="{{ asset('shop/projekt/about/gruender-profil-b.webp') }}" alt="Alina Steinhauer" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <div class="font-semibold text-sm text-white">Alina Steinhauer</div>
                            <div class="text-xs text-stone-300">Gründerin von Mein Seelenfunke</div>
                        </div>
                    </div>

                    <div class="hidden sm:block w-px h-9 bg-white/15" aria-hidden="true"></div>

                    <div class="flex flex-col items-center sm:items-start gap-1.5">
                        <a href="{{ route('shop') }}"
                           class="px-6 py-3 bg-primary hover:bg-[#b8955a] border border-primary text-stone-950 text-sm font-semibold rounded-xl shadow-sm transition-colors duration-200 flex items-center gap-2.5 cursor-pointer group whitespace-nowrap">
                            <span>Unikat gestalten & Gutes tun</span>
                            <svg class="w-4 h-4 text-stone-950 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                        <span class="text-[11px] text-stone-300">Jeder Einkauf stärkt lokale Kinder-Aktionen</span>
                    </div>
                </div>

                {{-- Legal Transparency Notice --}}
                <div class="mt-12 pt-6 border-t border-white/10">
                    <p class="text-[11px] sm:text-xs text-gray-400/80 max-w-3xl mx-auto leading-relaxed">
                        <strong class="text-gray-300 font-medium">Transparenzhinweis:</strong> Unsere Herzensprojekte sind eine freiwillige unternehmerische Initiative von Mein Seelenfunke (Cause-Related Marketing). Als Kunde tätigst du mit deinem Einkauf keine eigenständige Spende und erhältst keine steuerliche Spendenquittung. Die Zuwendungen an Kitas und regionale Kinderinitiativen finanzieren wir vollständig aus den erwirtschafteten Mitteln unseres Unternehmens.
                    </p>
                </div>
            </div>
        </section>

        {{--Contact Section--}}
        @livewire('frontend.contact-form')

    </x-sections.page-container>

    <!-- Modernes Zertifikat-Showcase Modal -->
    <div x-show="showCertModal"
         @keydown.escape.window="showCertModal = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 flex items-center justify-center p-4 sm:p-6 bg-black/75 backdrop-blur-sm"
         x-cloak
         style="display: none; z-index: 100000;">

        <!-- Modal Container -->
        <div @click.away="showCertModal = false"
             x-show="showCertModal"
             x-transition:enter="transition ease-out duration-300 transform scale-95 opacity-0"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100"
             x-transition:leave="transition ease-in duration-200 transform scale-100 opacity-100"
             x-transition:leave-start="scale-100 opacity-100"
             x-transition:leave-end="scale-95 opacity-0"
             style="max-height: 90vh;"
             class="relative w-full max-w-3xl bg-[#FAF7F2] border border-primary/45 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

            <!-- Modal Header (Fixiert oben, schneidet niemals ab) -->
            <div class="px-5 sm:px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4 bg-white shrink-0">
                <div class="flex items-center gap-3.5 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-primary/15 border border-primary/35 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div class="text-left min-w-0">
                        <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 truncate">Zertifizierte Laserschutzbeauftragte</h3>
                        <span class="block text-xs text-gray-500 truncate">Alina Steinhauer • Fachkunde gemäß OStrV & TROS Laserstrahlung</span>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 shrink-0">
                    <a href="{{ asset('shop/frontend/certificates/Laserschutzbeauftragte_Zertifikat_Alina_Steinhauer.pdf') }}"
                       target="_blank"
                       class="px-3.5 py-2 bg-primary hover:bg-[#b8955a] text-gray-900 text-xs font-bold rounded-xl border border-primary transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span class="hidden sm:inline">Original-PDF</span>
                    </a>
                    <button @click="showCertModal = false"
                            aria-label="Schließen"
                            class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-600 hover:text-gray-900 rounded-xl border border-gray-200 transition-colors cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Content: Inszeniertes Zertifikat mit dem "Unsere Kollektion" Hover-Licht- & Sterne-Effekt -->
            <div class="flex-1 overflow-y-auto p-5 sm:p-8">
                <div onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                     onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                     class="sf-card relative w-full max-w-xl mx-auto bg-white p-3 sm:p-4 rounded-2xl shadow-xl border-2 border-primary/45">

                    <div class="relative rounded-xl overflow-hidden border border-gray-200 bg-white">
                        <img src="{{ asset('shop/frontend/certificates/Laserschutzbeauftragte_Zertifikat_Alina_Steinhauer.webp') }}"
                             alt="Zertifikat Laserschutzbeauftragte – Alina Steinhauer"
                             class="sf-card-img w-full h-auto block select-none">

                        {{-- Diagonaler Licht-Effekt wie bei "Unsere Kollektion" --}}
                        <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>

                        {{-- Schwebe-Sterne wie bei "Unsere Kollektion" --}}
                        @for($sp = 1; $sp <= 6; $sp++)
                            <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-5 h-5 text-amber-400' : 'w-4 h-4 text-amber-300' }} drop-shadow-[0_0_6px_rgba(245,158,11,0.9)] z-20" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                            </span>
                        @endfor
                    </div>
                </div>

                {{-- Prüf- & Nachweis-Details --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full max-w-xl mx-auto mt-5 text-left">
                    <div class="bg-white px-4 py-3 rounded-xl border border-gray-200 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">Prüfinstitut</span>
                        <span class="block text-xs font-bold text-gray-900 mt-0.5">Luminus Institut Bielefeld</span>
                    </div>
                    <div class="bg-white px-4 py-3 rounded-xl border border-gray-200 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">Staatliche Zulassung</span>
                        <span class="block text-xs font-bold text-gray-900 mt-0.5">ZFU-Nr. 7540225 (OStrV / TROS)</span>
                    </div>
                    <div class="bg-white px-4 py-3 rounded-xl border border-gray-200 shadow-sm">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">Zertifikats-ID</span>
                        <span class="block text-xs font-mono font-bold text-gray-900 mt-0.5 truncate">S81XMGO9LBD1TMH74QRZ</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</x-layouts.frontend_layout>
