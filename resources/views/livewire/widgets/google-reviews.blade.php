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
                <h2 class="text-3xl md:text-4xl font-serif font-bold text-primary dark:text-primary-400 mb-4">
                    Was unsere Kunden sagen
                </h2>

                <p class="text-gray-600 dark:text-gray-300 max-w-2xl mx-auto mb-6">
                    Ehrliches Feedback ist das schönste Geschenk. Hier sind einige Stimmen von Menschen, die wir bereits begleiten durften.
                </p>

                @if(isset($reviewsData['rating']))
                    <div class="inline-flex items-center bg-white dark:bg-gray-800 rounded-full px-6 py-2 shadow-sm border border-gray-100 dark:border-gray-700">
                        <span class="text-3xl font-bold text-gray-900 dark:text-white mr-3">{{ $reviewsData['rating'] }}</span>

                        {{-- Goldene Sterne --}}
                        <div class="flex text-yellow-400 space-x-1">
                            @for($i = 0; $i < 5; $i++)
                                <svg class="w-6 h-6 {{ $i < round($reviewsData['rating']) ? 'fill-current' : 'text-gray-200 dark:text-gray-600' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.005Z" clip-rule="evenodd" />
                                </svg>
                            @endfor
                        </div>

                        <span class="text-gray-400 dark:text-gray-500 text-sm font-medium ml-3 border-l border-gray-200 dark:border-gray-600 pl-3">
                            {{ $reviewsData['total_ratings'] ?? 0 }} Rezensionen
                        </span>
                    </div>
                @endif
            </div>

            {{-- Reviews Marquee --}}
            @if(!empty($reviewsData['reviews']))

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
                            @foreach($reviewsData['reviews'] as $review)
                                {{-- Einzelne Karte --}}
                                <div class="flex-shrink-0 w-[300px] md:w-[400px] mx-4">
                                    <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-sm hover:shadow-lg transition-shadow duration-300 border border-gray-100 dark:border-gray-700 flex flex-col h-full relative group cursor-default">

                                        {{-- Dekoratives Zitat --}}
                                        <div class="absolute top-4 right-6 text-8xl text-gray-50 dark:text-gray-700 font-serif opacity-50 select-none pointer-events-none">
                                            ”
                                        </div>

                                        {{-- Header: Bild & Name --}}
                                        <div class="flex items-center mb-6 relative z-10">
                                            <div class="relative flex-shrink-0">
                                                <img src="{{ $review['profile_photo_url'] }}"
                                                     alt="{{ $review['author_name'] }}"
                                                     class="w-12 h-12 rounded-full object-cover border-2 border-white dark:border-gray-800 shadow-sm ring-2 ring-primary ring-opacity-20"
                                                     referrerpolicy="no-referrer">
                                            </div>

                                            <div class="ml-4 overflow-hidden">
                                                <h4 class="font-bold text-gray-900 dark:text-white text-base truncate">
                                                    {{ $review['author_name'] }}
                                                </h4>
                                                <div class="flex items-center text-xs mt-0.5">
                                                    <div class="flex text-yellow-400 mr-2 flex-shrink-0">
                                                        @for($i = 0; $i < 5; $i++)
                                                            <svg class="w-3.5 h-3.5 {{ $i < $review['rating'] ? 'fill-current' : 'text-gray-200 dark:text-gray-600' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.005Z" clip-rule="evenodd" />
                                                            </svg>
                                                        @endfor
                                                    </div>
                                                    <span class="text-gray-400 font-light truncate">{{ \Carbon\Carbon::createFromTimestamp($review['time'])->diffForHumans() }}</span>
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

                {{-- Link zu Google --}}
                <div class="mt-12 flex justify-center">
                    <a href="https://search.google.com/local/reviews?placeid={{ config('services.google.place_id') }}"
                       target="_blank"
                       class="group inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary hover:border-primary dark:hover:border-primary transition-all duration-300 shadow-sm hover:shadow">

                        <x-heroicon-m-arrow-top-right-on-square class="w-5 h-5 text-gray-400 group-hover:text-primary transition-colors" />

                        <span>Alle Bewertungen auf Google lesen</span>
                    </a>
                </div>

            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <x-heroicon-o-chat-bubble-left-ellipsis class="w-12 h-12 text-gray-300 mb-3" />
                    <p class="text-gray-500">Momentan können keine Bewertungen geladen werden.</p>
                </div>
            @endif

        </div>
    </section>
</div>
