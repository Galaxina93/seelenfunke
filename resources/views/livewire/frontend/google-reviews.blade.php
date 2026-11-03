<div>
    {{-- Custom Style für die Marquee Animation --}}
    <style>
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .animate-marquee {
            display: flex;
            width: max-content;
            animation: marquee 120s linear infinite;
            will-change: transform;
        }
        /* Animation pausieren, wenn man mit der Maus drüber fährt */
        .animate-marquee:hover {
            animation-play-state: paused;
        }
    </style>

    <section class="py-16 bg-gray-50 dark:bg-gray-900 overflow-hidden">
        <div class="w-full">

            {{-- Header Bereich --}}
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-serif font-bold text-[#8a6820] dark:text-primary mb-4">
                    Was unsere Kunden sagen
                </h2>

                <p class="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-6">
                    Ehrliches Feedback ist das schönste Geschenk. Hier sind einige Stimmen von Menschen, die wir bereits begleiten durften.
                </p>

                @php
                    $reviewSource = $reviewsData['source'] ?? 'none';
                    $reviewItems = $reviewsData['reviews'] ?? $reviewsData['produkte/reviews'] ?? [];
                    $googlePlaceId = (string) config('services.google.place_id', '');
                    $hasValidGooglePlaceId = $googlePlaceId !== '' && !str_starts_with($googlePlaceId, 'deine-place-id');
                @endphp

                @if(isset($reviewsData['rating']) && ($reviewsData['total_ratings'] ?? 0) > 0)
                    <div class="inline-flex flex-wrap items-center justify-center max-w-full bg-white dark:bg-gray-800 rounded-2xl sm:rounded-full px-4 sm:px-6 py-2 shadow-sm border border-gray-100 dark:border-gray-700 gap-y-1">
                        <span class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mr-2.5 sm:mr-3">{{ number_format((float) $reviewsData['rating'], 1, ',', '.') }}</span>

                        {{-- Goldene Sterne --}}
                        <div class="flex text-yellow-400 space-x-1">
                            @for($i = 0; $i < 5; $i++)
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 {{ $i < round($reviewsData['rating']) ? 'fill-current' : 'text-gray-200 dark:text-gray-600' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.005Z" clip-rule="evenodd" />
                                </svg>
                            @endfor
                        </div>

                        <span class="text-gray-600 dark:text-gray-300 text-xs sm:text-sm font-medium ml-2.5 sm:ml-3 border-l border-gray-200 dark:border-gray-600 pl-2.5 sm:pl-3">
                            {{ $reviewsData['total_ratings'] ?? 0 }} {{ ($reviewsData['total_ratings'] ?? 0) === 1 ? 'Bewertung' : 'Bewertungen' }}
                        </span>
                    </div>
                    <p class="mt-3 text-[11px] text-gray-500 dark:text-gray-400 max-w-xl mx-auto leading-relaxed">
                        @if($reviewSource === 'shop')
                            Hinweis gem. § 5b Abs. 3 UWG: Alle hier angezeigten Shop-Bewertungen stammen ausschließlich von verifizierten Käufern, die das jeweilige Produkt nachweislich über ihr Kundenkonto bei uns erworben haben.
                        @else
                            Hinweis gem. § 5b Abs. 3 UWG: Diese Rezensionen stammen von unserem Google-Unternehmensprofil. Eine technische Überprüfung durch uns, ob die Rezensenten unsere Produkte tatsächlich erworben haben, findet bei externen Google-Bewertungen nicht statt.
                        @endif
                    </p>
                @endif
            </div>

            {{-- Reviews Marquee --}}
            @if(!empty($reviewItems))

                {{-- Container mit Fade-Effekt links und rechts --}}
                <div class="relative w-full">

                    {{-- Fade Links --}}
                    <div class="absolute left-0 top-0 bottom-0 w-16 md:w-32 z-10 bg-gradient-to-r from-gray-50 to-transparent dark:from-gray-900 pointer-events-none"></div>

                    {{-- Fade Rechts --}}
                    <div class="absolute right-0 top-0 bottom-0 w-16 md:w-32 z-10 bg-gradient-to-l from-gray-50 to-transparent dark:from-gray-900 pointer-events-none"></div>

                    {{-- Die Animation Track --}}
                    <div class="animate-marquee py-4">

                        {{-- Wir loopen 2x durch die Daten, um einen nahtlosen Übergang zu schaffen --}}
                        @for ($k = 0; $k < 2; $k++)
                            @foreach($reviewItems as $review)
                                {{-- Einzelne Karte (Zweiter Durchlauf für Screenreader & Crawler ausgeblendet) --}}
                                <div class="flex-shrink-0 w-[300px] md:w-[400px] mx-4" @if($k === 1) aria-hidden="true" data-nosnippet @endif>
                                    <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-sm hover:shadow-lg transition-shadow duration-300 border border-gray-100 dark:border-gray-700 flex flex-col h-full relative group cursor-default">

                                        {{-- Dekoratives Zitat --}}
                                        <div class="absolute top-4 right-6 text-8xl text-gray-50 dark:text-gray-700 font-serif opacity-50 select-none pointer-events-none">
                                            ”
                                        </div>

                                        {{-- Header: Lokales Initialen-Badge (DSGVO-konform ohne externe Avatar-Aufrufe) & Name --}}
                                        <div class="flex items-center mb-6 relative z-10">
                                            <div class="relative flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full bg-amber-50 dark:bg-gray-700 text-[#8a6820] dark:text-primary font-serif font-bold text-lg flex items-center justify-center border-2 border-white dark:border-gray-800 shadow-sm ring-2 ring-primary/20 select-none" aria-hidden="true">
                                                    {{ mb_strtoupper(mb_substr((string) ($review['author_name'] ?? 'K'), 0, 1)) }}
                                                </div>
                                            </div>

                                            <div class="ml-4 overflow-hidden">
                                                <h3 class="font-bold text-gray-900 dark:text-white text-base truncate">
                                                    {{ $review['author_name'] }}
                                                </h3>
                                                <div class="flex items-center text-xs mt-0.5">
                                                    <div class="flex text-yellow-400 mr-2 flex-shrink-0">
                                                        @for($i = 0; $i < 5; $i++)
                                                            <svg class="w-3.5 h-3.5 {{ $i < $review['rating'] ? 'fill-current' : 'text-gray-200 dark:text-gray-600' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.005Z" clip-rule="evenodd" />
                                                            </svg>
                                                        @endfor
                                                    </div>
                                                    <span class="text-gray-600 dark:text-gray-400 font-light truncate">{{ \Carbon\Carbon::createFromTimestamp($review['time'])->diffForHumans() }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Text --}}
                                        <div class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed italic flex-grow relative z-10 line-clamp-4">
                                            "{{ Str::limit($review['text'], 200) }}"
                                        </div>

                                    </div>
                                </div>
                            @endforeach
                        @endfor
                    </div>
                </div>

                {{-- Link zu Google (nur wenn echte Place-ID hinterlegt ist) --}}
                @if($hasValidGooglePlaceId)
                    <div class="mt-12 flex justify-center">
                        <a href="https://search.google.com/local/reviews?placeid={{ $googlePlaceId }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="group inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary hover:border-primary dark:hover:border-primary transition-all duration-300 shadow-sm hover:shadow">

                            <x-heroicon-m-arrow-top-right-on-square class="w-5 h-5 text-gray-400 group-hover:text-primary transition-colors" />

                            <span>Alle Bewertungen auf Google lesen</span>
                        </a>
                    </div>
                @endif

            @else
                <div class="max-w-xl mx-auto px-4">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-gray-700 p-8 text-center shadow-sm">
                        <x-heroicon-o-sparkles class="w-10 h-10 text-primary mx-auto mb-3" />
                        <h3 class="font-serif font-bold text-lg text-gray-900 dark:text-white mb-2">Deine Meinung zählt</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed mb-5">
                            Wir veröffentlichen ausschließlich echte und geprüfte Kundenbewertungen (§ 5b Abs. 3 UWG). Nach deiner Bestellung kannst du dein Unikat direkt auf der jeweiligen Produktseite bewerten und erhältst als Dankeschön <strong class="text-[#8a6820] dark:text-primary">+50 Seelenfunken</strong> für dein Treuekonto.
                        </p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gray-900 dark:bg-primary text-white dark:text-gray-900 text-xs font-bold uppercase tracking-wider hover:bg-primary hover:text-gray-900 transition-colors">
                            <span>Unikate im Shop entdecken</span>
                            <x-heroicon-m-arrow-right class="w-4 h-4" />
                        </a>
                    </div>
                </div>
            @endif

        </div>
    </section>
</div>
