<x-layouts.frontend_layout>
    <div x-data="{ showCertModal: false }">
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
                            <div class="absolute -right-6 -bottom-6 bg-gradient-to-br from-[#FAF7F2] via-[#FDFBF7] to-[#F3ECE0] border-2 border-primary/40 text-gray-900 p-6 rounded-2xl shadow-xl transform transition-transform group-hover:rotate-6 group-hover:scale-110 duration-300 overflow-hidden">
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
        <section class="relative bg-gray-950 border-y border-gray-800/80 pt-32 pb-24 lg:pt-40 lg:pb-32 overflow-hidden" aria-label="Einführung Manufaktur">
            {{-- Hintergrundbild: Wo Licht auf Materie trifft --}}
            <img src="{{ asset('shop/frontend/banners/manufaktur_licht_materie.webp') }}"
                 alt="Wo Licht auf Materie trifft – Laserstrahl trifft auf K9 Kristallglas in der Manufaktur"
                 loading="lazy"
                 decoding="async"
                 data-is-ai-generated="true"
                 class="absolute inset-0 w-full h-full object-cover object-center opacity-85 pointer-events-none select-none transition-opacity duration-700">

            {{-- Warmer Abdunklungs-Verlauf für optimalen Textkontrast --}}
            <div class="absolute inset-0 bg-gradient-to-b from-gray-950/70 via-gray-950/45 to-gray-950/85 pointer-events-none" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-amber-500/10 blur-[110px] rounded-full w-[520px] h-[520px] mx-auto opacity-30 pointer-events-none" aria-hidden="true"></div>

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
        <section class="py-20 bg-gray-50" aria-labelledby="prozess-heading">
            <style>
                @keyframes mfFloatSlow {
                    0%, 100% { transform: translateY(0px) rotate(0deg); }
                    50% { transform: translateY(-7px) rotate(0.4deg); }
                }
                @keyframes mfFloatSlowReverse {
                    0%, 100% { transform: translateY(0px) rotate(0deg); }
                    50% { transform: translateY(-7px) rotate(-0.4deg); }
                }
                @keyframes mfSparkleTwinkle {
                    0%, 100% { opacity: 0.25; transform: scale(0.85) rotate(0deg); }
                    50% { opacity: 0.95; transform: scale(1.2) rotate(15deg); }
                }
                @keyframes mfLaserPulse {
                    0%, 100% { opacity: 0.35; transform: scale(0.9); }
                    50% { opacity: 0.85; transform: scale(1.25); }
                }
                @keyframes mfSheenSweep {
                    0% { transform: translateX(-140%) skewX(-20deg); }
                    100% { transform: translateX(240%) skewX(-20deg); }
                }
                .mf-card-float-1 { animation: mfFloatSlow 5.5s ease-in-out infinite; will-change: transform; }
                .mf-card-float-2 { animation: mfFloatSlowReverse 6.2s ease-in-out infinite; will-change: transform; }
                .mf-card-float-3 { animation: mfFloatSlow 5.8s ease-in-out infinite 0.8s; will-change: transform; }
                .mf-sparkle-1 { animation: mfSparkleTwinkle 2.8s ease-in-out infinite; }
                .mf-sparkle-2 { animation: mfSparkleTwinkle 3.4s ease-in-out infinite 0.9s; }
                .mf-sparkle-3 { animation: mfSparkleTwinkle 2.5s ease-in-out infinite 1.5s; }
                .mf-laser-glow { animation: mfLaserPulse 2.2s ease-in-out infinite; }
                .group:hover .mf-sheen { animation: mfSheenSweep 1.35s cubic-bezier(0.4, 0, 0.2, 1) infinite; }
            </style>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">

                {{-- Header für Screenreader / SEO Struktur --}}
                <h2 id="prozess-heading" class="sr-only">Der Fertigungsprozess unserer Glas-Unikate</h2>

                {{-- SCHRITT 1: Das Rohmaterial --}}
                <article class="flex flex-col md:flex-row items-center gap-12">
                    <div class="w-full md:w-1/2 relative group cursor-pointer">
                        <div class="absolute -inset-4 bg-primary/20 rounded-2xl transform rotate-2 transition-all duration-700 group-hover:rotate-1 group-hover:bg-primary/30 group-hover:scale-[1.02]" aria-hidden="true"></div>
                        <div class="mf-card-float-1 relative rounded-xl shadow-xl overflow-hidden h-80 bg-[#F5EFE6]">
                            <img src="{{ asset('shop/frontend/other/k9_glas.webp') }}"
                                 alt="Hochreiner K9 Kristallglas Rohling vor der Gravur"
                                 loading="lazy"
                                 class="w-full h-full object-cover transform transition-transform duration-1000 ease-out group-hover:scale-105 group-hover:-rotate-1">

                            {{-- Animierter Lichtbrechungs-Glanz beim Hovern --}}
                            <div class="mf-sheen pointer-events-none absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-white/55 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" aria-hidden="true"></div>

                            {{-- Sanft funkelnde Kristall-Lichtpunkte --}}
                            <svg class="mf-sparkle-1 pointer-events-none absolute top-[22%] left-[44%] w-6 h-6 text-amber-400 drop-shadow-[0_0_8px_rgba(245,158,11,0.8)]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/>
                            </svg>
                            <svg class="mf-sparkle-2 pointer-events-none absolute bottom-[26%] left-[32%] w-4 h-4 text-primary drop-shadow-[0_0_6px_rgba(201,166,107,0.8)]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/>
                            </svg>
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
                    <div class="w-full md:w-1/2 relative group cursor-pointer">
                        <div class="absolute -inset-4 bg-gray-200 rounded-2xl transform -rotate-2 transition-all duration-700 group-hover:-rotate-1 group-hover:bg-primary/25 group-hover:scale-[1.02]" aria-hidden="true"></div>
                        <div class="mf-card-float-2 relative rounded-xl shadow-xl overflow-hidden h-80 bg-[#EAEDF2]">
                            <img src="{{ asset('shop/frontend/process/lasergravur.webp') }}"
                                 onerror="this.src='https://placehold.co/600x400/333/FFF?text=Laser+Technologie'"
                                 alt="Präzise 3D-Lasergravur im Inneren des Glases"
                                 loading="lazy"
                                 class="w-full h-full object-cover transform transition-transform duration-1000 ease-out group-hover:scale-105 group-hover:rotate-1">

                            {{-- Pulsierender Laser-Brennpunkt & Funkenflug --}}
                            <div class="mf-laser-glow pointer-events-none absolute top-[46%] left-[44%] w-16 h-16 -translate-x-1/2 -translate-y-1/2 rounded-full bg-amber-300/40 blur-md group-hover:bg-amber-400/65 transition-colors" aria-hidden="true"></div>
                            <div class="mf-sheen pointer-events-none absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-amber-200/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" aria-hidden="true"></div>
                            <svg class="mf-sparkle-3 pointer-events-none absolute top-[42%] left-[42%] w-6 h-6 text-amber-400 drop-shadow-[0_0_10px_rgba(251,191,36,0.95)]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/>
                            </svg>
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
                    <div class="w-full md:w-1/2 relative group cursor-pointer">
                        <div class="absolute -inset-4 bg-primary/20 rounded-2xl transform rotate-2 transition-all duration-700 group-hover:rotate-1 group-hover:bg-primary/30 group-hover:scale-[1.02]" aria-hidden="true"></div>
                        <div class="mf-card-float-3 relative rounded-xl shadow-xl overflow-hidden h-80 bg-[#F5EFE6]">
                            <img src="{{ asset('shop/frontend/other/white_hands.webp') }}"
                                 alt="Manuelle Endkontrolle und Reinigung des Kristalls mit Handschuhen"
                                 loading="lazy"
                                 class="w-full h-full object-cover transform transition-transform duration-1000 ease-out group-hover:scale-105 group-hover:-rotate-1">

                            {{-- Seidiger Politur-Glanzstreifen beim Hovern --}}
                            <div class="mf-sheen pointer-events-none absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-white/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" aria-hidden="true"></div>

                            {{-- Funkelnde Finish-Sterne --}}
                            <svg class="mf-sparkle-2 pointer-events-none absolute top-[28%] right-[30%] w-6 h-6 text-amber-400 drop-shadow-[0_0_8px_rgba(245,158,11,0.85)]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/>
                            </svg>
                            <svg class="mf-sparkle-1 pointer-events-none absolute top-[18%] left-[28%] w-4 h-4 text-primary drop-shadow-[0_0_6px_rgba(201,166,107,0.85)]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/>
                            </svg>
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
            SECTION 4: ZERTIFIZIERUNG / FACTS
            Unternehmens-Kompetenznachweis
        --}}
        <section class="py-16 bg-white" aria-labelledby="kompetenz-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gradient-to-br from-stone-50 via-white to-stone-50 border border-gray-200/80 rounded-3xl p-8 md:p-12 text-center shadow-xl relative overflow-hidden">
                    <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-primary/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
                    <div class="absolute -left-20 -top-20 w-80 h-80 bg-amber-500/5 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

                    <div class="relative z-10">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary/15 text-primary-dark text-xs font-bold uppercase tracking-wider mb-4 shadow-sm">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Geprüfte Sicherheitsstandards
                        </div>

                        <h2 id="kompetenz-heading" class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 mb-4">Unsere Kompetenz für Ihre Sicherheit</h2>
                        <p class="text-gray-600 max-w-2xl mx-auto mb-8 text-base leading-relaxed">
                            Wir arbeiten mit Hochleistungslasern der Klasse 4. Um höchste Qualität und Sicherheit zu gewährleisten,
                            haben wir uns intensiv fortgebildet und prüfen lassen.
                        </p>
                        <div class="flex justify-center gap-4 flex-wrap mb-8">
                            <button @click="showCertModal = true" class="px-5 py-3 bg-white hover:bg-stone-50 active:bg-stone-100 rounded-xl border border-gray-300 hover:border-primary text-gray-800 font-bold flex items-center gap-2.5 transition-all duration-300 transform hover:-translate-y-0.5 shadow-sm cursor-pointer group">
                                <svg class="w-5 h-5 text-primary transition-transform duration-300 group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span>Zertifizierte Laserschutzbeauftragte</span>
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-primary transition-colors ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                            <div class="px-5 py-3 bg-white rounded-xl border border-gray-300 text-gray-800 font-bold flex items-center gap-2.5 shadow-sm">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Fachkunde nach TROS Laserstrahlung</span>
                            </div>
                        </div>

                        {{-- TROS Info als Accordion --}}
                        <div x-data="{ open: false }" class="bg-white border border-gray-200 rounded-2xl max-w-3xl mx-auto shadow-sm overflow-hidden">
                            <button @click="open = !open"
                                    class="w-full px-6 py-4 flex items-center justify-between text-left focus:outline-none focus:bg-stone-50 hover:bg-stone-50 transition-colors gap-4 cursor-pointer">
                                <strong class="text-gray-900 font-bold text-sm sm:text-base">Was bedeutet TROS? Oberfläche oder echtes Handwerk?</strong>
                                <svg class="w-5 h-5 text-gray-400 transform transition-transform duration-300 flex-shrink-0"
                                     :class="{'rotate-180': open}"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div x-show="open"
                                 x-collapse
                                 x-cloak
                                 class="px-6 pb-6 text-sm text-gray-600 text-left leading-relaxed">
                                <div class="pt-3 border-t border-gray-100 mt-2">
                                    Die Qualifikation nach <strong class="text-gray-900 font-bold">TROS</strong> <em>(Technische Regeln zur Arbeitsschutzverordnung zu künstlicher optischer Strahlung)</em> belegt unsere strikte Einhaltung der strengen deutschen Richtlinien im Umgang mit hochkomplexen Lasersystemen der Industrieklasse 4.
                                    <br><br>
                                    Diese staatlich anerkannte Fachkunde untermauert, dass wir unsere Produkte nicht einfach im Hobbykeller "bedrucken", sondern unter hochprofessionellen, sicheren Industriebedingungen veredeln. Für dich bedeutet das: Dein Unikat entsteht in einer rechtssicheren Manufaktur, bei der Arbeitsschutz, technische Präzision und tiefes Fachwissen die absolute Grundlage bilden.
                                </div>
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
        <section id="herzensprojekte" class="py-24 bg-gradient-to-b from-white via-amber-500/5 to-white border-t border-gray-100 overflow-hidden" aria-labelledby="herzensprojekte-heading">
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">

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

                {{-- Gründerin Zitat & Call to Action Banner --}}
                <div class="bg-gradient-to-br from-stone-50 via-white to-amber-50/40 border border-gray-200/80 rounded-3xl p-8 sm:p-12 text-gray-900 shadow-xl relative overflow-hidden">
                    <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-primary/10 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
                    <div class="absolute -left-20 -top-20 w-80 h-80 bg-amber-500/5 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

                    <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-10">
                        <div class="max-w-2xl">
                            <span class="text-primary-dark font-bold tracking-wider uppercase text-xs mb-3 block">
                                Unsere Vision & Haltung
                            </span>
                            <blockquote class="text-xl sm:text-2xl font-serif italic text-gray-800 mb-6 leading-relaxed">
                                „Ich stehe mit ganzem Herzen dafür, meinen Kunden mit liebevoll handgefertigten Unikaten pure Freude zu schenken – und gleichzeitig soziale Fürsorge für Kinder aktiv zu leben. Ein Seelenfunke soll nicht nur im Zuhause unserer Kunden ein warmes Leuchten entfachen, sondern zeitgleich auch in Kinderaugen echte Begeisterung und ein Lächeln zaubern. Pure Freude beim Kunden und soziales Engagement für Kinder gehören für mich untrennbar zusammen.“
                            </blockquote>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-primary shadow-sm">
                                    <img src="{{ asset('shop/projekt/about/gruender-profil-b.webp') }}" alt="Alina Steinhauer" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <div class="font-bold text-sm text-gray-900">Alina Steinhauer</div>
                                    <div class="text-xs text-gray-500 font-medium">Gründerin von Mein Seelenfunke</div>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 flex flex-col items-center sm:items-start gap-4 text-center sm:text-left">
                            <a href="{{ route('shop') }}" class="px-8 py-4 bg-gradient-to-r from-primary-light to-primary hover:brightness-105 active:scale-95 text-gray-900 font-bold rounded-xl shadow-md transition-all flex items-center gap-3 cursor-pointer">
                                <span>Unikat gestalten & Gutes tun</span>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                            <span class="text-xs text-gray-500 font-medium">Jeder Einkauf stärkt lokale Kinder-Aktionen</span>
                        </div>
                    </div>
                </div>

                {{-- Legal Transparency Notice --}}
                <div class="mt-8 text-center">
                    <p class="text-xs text-gray-400 max-w-3xl mx-auto leading-relaxed">
                        <strong class="text-gray-500">Transparenzhinweis:</strong> Unsere Herzensprojekte sind eine freiwillige unternehmerische Initiative von Mein Seelenfunke (Cause-Related Marketing). Als Kunde tätigst du mit deinem Einkauf keine eigenständige Spende und erhältst keine steuerliche Spendenquittung. Die Zuwendungen an Kitas und regionale Kinderinitiativen finanzieren wir vollständig aus den erwirtschafteten Mitteln unseres Unternehmens.
                    </p>
                </div>

            </div>
        </section>

        {{--Contact Section--}}
        @livewire('frontend.contact-form')

    </x-sections.page-container>

    <!-- Modal Backdrop -->
    <div x-show="showCertModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         x-cloak
         style="display: none;">

        <!-- Modal Container -->
        <div @click.away="showCertModal = false"
             x-show="showCertModal"
             x-transition:enter="transition ease-out duration-300 transform scale-95 opacity-0"
             x-transition:enter-start="scale-95 opacity-0"
             x-transition:enter-end="scale-100 opacity-100"
             x-transition:leave="transition ease-in duration-200 transform scale-100 opacity-100"
             x-transition:leave-start="scale-100 opacity-100"
             x-transition:leave-end="scale-95 opacity-0"
             class="relative w-full max-w-5xl h-[85vh] bg-white border border-gray-200 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-stone-50/80">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <div class="text-left">
                        <h3 class="text-lg font-bold text-gray-900 leading-none">Laserschutzbeauftragte Zertifikat</h3>
                        <span class="text-xs text-gray-500">Alina Steinhauer, ausgestellt nach OStrV & TROS Laserstrahlung</span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ asset('shop/frontend/certificates/Laserschutzbeauftragte_Zertifikat_Alina_Steinhauer.pdf') }}"
                       target="_blank"
                       class="px-3 py-1.5 bg-white hover:bg-stone-100 text-gray-800 text-xs font-bold rounded-lg border border-gray-300 transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span>Vollbild / Herunterladen</span>
                    </a>
                    <button @click="showCertModal = false"
                            class="p-1.5 bg-white hover:bg-stone-100 text-gray-500 hover:text-gray-900 rounded-lg border border-gray-300 transition-colors shadow-sm cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Modal Content (PDF Embed / Iframe) -->
            <div class="flex-1 bg-stone-100 relative">
                <iframe src="{{ asset('shop/frontend/certificates/Laserschutzbeauftragte_Zertifikat_Alina_Steinhauer.pdf') }}#toolbar=0"
                        class="w-full h-full border-0"
                        title="Laserschutzbeauftragte Zertifikat PDF">
                    <div class="text-gray-500 p-8 text-center flex flex-col items-center justify-center h-full">
                        <p class="mb-4">Ihr Browser unterstützt die direkte PDF-Vorschau nicht.</p>
                        <a href="{{ asset('shop/frontend/certificates/Laserschutzbeauftragte_Zertifikat_Alina_Steinhauer.pdf') }}" target="_blank" class="px-4 py-2 bg-gradient-to-r from-primary-light to-primary text-gray-900 rounded-lg font-bold transition-all shadow-md">Zertifikat hier herunterladen</a>
                    </div>
                </iframe>
            </div>
        </div>
    </div>
    </div>
</x-layouts.frontend_layout>
