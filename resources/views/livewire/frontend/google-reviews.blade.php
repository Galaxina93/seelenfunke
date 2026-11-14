<div x-data="{
        lightbox: {
            open: false,
            items: [],
            index: 0,
            author: '',
            rating: 5,
            text: '',
            productName: '',
            productUrl: null
        },
        openLightbox(items, startIndex = 0, meta = {}) {
            if (!items || !items.length) return;
            this.lightbox.items = items;
            this.lightbox.index = startIndex;
            this.lightbox.author = meta.author || 'Verifizierter Kunde';
            this.lightbox.rating = meta.rating || 5;
            this.lightbox.text = meta.text || '';
            this.lightbox.productName = meta.productName || '';
            this.lightbox.productUrl = meta.productUrl || null;
            this.lightbox.open = true;
        },
        closeLightbox() {
            this.lightbox.open = false;
        },
        nextSlide() {
            if (!this.lightbox.items.length) return;
            this.lightbox.index = (this.lightbox.index + 1) % this.lightbox.items.length;
        },
        prevSlide() {
            if (!this.lightbox.items.length) return;
            this.lightbox.index = (this.lightbox.index - 1 + this.lightbox.items.length) % this.lightbox.items.length;
        }
    }"
    @keydown.escape.window="if (lightbox.open) closeLightbox()"
    @keydown.arrow-right.window="if (lightbox.open) nextSlide()"
    @keydown.arrow-left.window="if (lightbox.open) prevSlide()">
    {{-- Custom Style für die nahtlose Endlos-Marquee Animation & Funken-Effekte --}}
    <style>
        @keyframes sfSeamlessMarquee {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-50%, 0, 0); }
        }
        .animate-marquee {
            display: flex;
            width: max-content;
            animation: sfSeamlessMarquee var(--marquee-duration, 72s) linear infinite;
            will-change: transform;
            backface-visibility: hidden;
        }
        /* Animation pausieren, wenn man mit der Maus drüber fährt oder die Lightbox offen ist */
        .animate-marquee:hover,
        .animate-marquee.is-paused {
            animation-play-state: paused;
        }
        @media (prefers-reduced-motion: reduce) {
            .animate-marquee {
                animation-play-state: paused !important;
            }
        }

        @keyframes sfThumbSparkleA {
            0% { transform: translate(0, 6px) scale(0.35) rotate(0deg); opacity: 0; }
            35% { opacity: 1; }
            100% { transform: translate(-10px, -18px) scale(1.15) rotate(25deg); opacity: 0; }
        }
        @keyframes sfThumbSparkleB {
            0% { transform: translate(0, 6px) scale(0.3) rotate(0deg); opacity: 0; }
            40% { opacity: 1; }
            100% { transform: translate(12px, -20px) scale(1.1) rotate(-22deg); opacity: 0; }
        }
        @keyframes sfThumbSparkleC {
            0% { transform: translate(0, 4px) scale(0.4) rotate(0deg); opacity: 0; }
            45% { opacity: 0.95; }
            100% { transform: translate(-5px, -15px) scale(1.05) rotate(18deg); opacity: 0; }
        }
        .group\/media:hover .sf-sparkle-1 {
            animation: sfThumbSparkleA 1.25s ease-out infinite;
        }
        .group\/media:hover .sf-sparkle-2 {
            animation: sfThumbSparkleB 1.45s ease-out infinite 0.22s;
        }
        .group\/media:hover .sf-sparkle-3 {
            animation: sfThumbSparkleC 1.35s ease-out infinite 0.5s;
        }
        .group\/media:hover .sf-sparkle-4 {
            animation: sfThumbSparkleA 1.55s ease-out infinite 0.75s;
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
                            Hinweis gem. § 5b Abs. 3 UWG: Alle hier angezeigten Shop-Bewertungen stammen ausschließlich von verifizierten Käufern, die das jeweilige Produkt nachweislich über ihr Kundenkonto bei uns erworben haben. Als Dankeschön erhalten verifizierte Käufer für ihre Bewertung ergebnisoffen (unabhängig von der Sternezahl) +{{ \App\Services\Gamification\GameConfig::REVIEW_SPARK_REWARD }} Seelenfunken (Treuepunkte).
                        @else
                            Hinweis gem. § 5b Abs. 3 UWG: Diese Rezensionen stammen von unserem Google-Unternehmensprofil. Eine technische Überprüfung durch uns, ob die Rezensenten unsere Produkte tatsächlich erworben haben, findet bei externen Google-Bewertungen nicht statt.
                        @endif
                    </p>
                @endif
            </div>

            {{-- Reviews Bereich (Automatisch: 1–3 Bewertungen zentriert ohne Dopplung | ab 4 Bewertungen Endlos-Marquee) --}}
            @if(!empty($reviewItems))
                @php
                    $baseCount = count($reviewItems);
                    $useMarquee = $baseCount >= 4;
                    $minCardsPerHalf = 8;
                    $repeatsPerHalf = $useMarquee ? (int) max(1, ceil($minCardsPerHalf / $baseCount)) : 1;
                    $halfCount = $useMarquee ? 2 : 1;
                    $cardsPerHalf = $baseCount * $repeatsPerHalf;
                    $marqueeDurationSec = max(48, $cardsPerHalf * 8);
                @endphp

                {{-- Container (mit Fade-Effekt links und rechts im Marquee-Modus) --}}
                <div class="relative w-full">

                    @if($useMarquee)
                        {{-- Fade Links --}}
                        <div class="absolute left-0 top-0 bottom-0 w-16 md:w-32 z-10 bg-gradient-to-r from-gray-50 to-transparent dark:from-gray-900 pointer-events-none"></div>

                        {{-- Fade Rechts --}}
                        <div class="absolute right-0 top-0 bottom-0 w-16 md:w-32 z-10 bg-gradient-to-l from-gray-50 to-transparent dark:from-gray-900 pointer-events-none"></div>
                    @endif

                    {{-- Track: Bei 1–3 Bewertungen edel zentriert (keine Namens-Dopplung), ab 4 Bewertungen nahtloser Endlos-Slider --}}
                    <div class="{{ $useMarquee ? 'animate-marquee py-4' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap justify-center items-stretch gap-6 md:gap-8 py-4' }}"
                         @if($useMarquee)
                             style="--marquee-duration: {{ $marqueeDurationSec }}s;"
                             :class="{ 'is-paused': lightbox.open }"
                         @endif>

                        @for ($half = 0; $half < $halfCount; $half++)
                            @for ($rep = 0; $rep < $repeatsPerHalf; $rep++)
                                @foreach($reviewItems as $review)
                                    @php
                                        $reviewMedia = $review['media'] ?? [];
                                        $mediaCount = is_array($reviewMedia) ? count($reviewMedia) : 0;
                                        $isClone = ($half > 0 || $rep > 0);
                                    @endphp
                                    {{-- Einzelne Karte (Klone für Screenreader & Crawler ausgeblendet) --}}
                                    <div class="flex-shrink-0 w-full max-w-[380px] sm:w-[360px] md:w-[400px] {{ $useMarquee ? 'mx-4' : '' }}" @if($isClone) aria-hidden="true" data-nosnippet @endif>
                                    <div class="bg-white dark:bg-gray-800 p-7 sm:p-8 rounded-2xl shadow-sm hover:shadow-lg transition-shadow duration-300 border border-gray-100 dark:border-gray-700 flex flex-col h-full relative group cursor-default">

                                        {{-- Dekoratives Zitat --}}
                                        <div class="absolute top-4 right-6 text-8xl text-gray-50 dark:text-gray-700 font-serif opacity-50 select-none pointer-events-none">
                                            ”
                                        </div>

                                        {{-- Header: Lokales Initialen-Badge (DSGVO-konform ohne externe Avatar-Aufrufe) & Name --}}
                                        <div class="flex items-center mb-5 relative z-10">
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
                                        @if(trim((string) ($review['text'] ?? '')) !== '')
                                            <div class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed italic flex-grow relative z-10 line-clamp-4">
                                                "{{ Str::limit((string) ($review['text'] ?? ''), 200) }}"
                                            </div>
                                        @else
                                            <div class="flex-grow"></div>
                                        @endif

                                        {{-- Kundenbilder / Videos unterhalb des Kommentars --}}
                                        @if($mediaCount > 0)
                                            <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700/70 relative z-10"
                                                 x-data="{ activeThumb: 0, total: {{ $mediaCount }} }">

                                                <div class="flex items-center justify-between mb-2.5 text-[11px]">
                                                    @if(!empty($review['product_name']))
                                                        <span class="inline-flex items-center gap-1 text-[#8a6820] dark:text-[#f5dca0] font-semibold truncate max-w-[70%]">
                                                            <svg class="w-3 h-3 flex-shrink-0 text-[#C5A059]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                                <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                                                            </svg>
                                                            <span class="truncate">{{ $review['product_name'] }}</span>
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400 font-medium">
                                                            <svg class="w-3 h-3 text-[#C5A059]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                                <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                                                            </svg>
                                                            <span>Kundenaufnahme</span>
                                                        </span>
                                                    @endif

                                                    @if($mediaCount > 1)
                                                        <span class="px-2 py-0.5 rounded-full bg-amber-50 dark:bg-gray-700 text-[#8a6820] dark:text-amber-300 font-bold text-[10px] border border-amber-200/60 dark:border-gray-600"
                                                              x-text="(activeThumb + 1) + ' / ' + total + ' Aufnahmen'">
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Haupt-Vorschau / Inline-Slider (Komplettes Bild sichtbar ohne Beschnitt + Funken-Hover) --}}
                                                <div class="relative w-full h-48 sm:h-52 rounded-2xl overflow-hidden border border-[#C5A059]/35 bg-gradient-to-br from-[#fdfbf7] via-[#f9f4e8] to-[#f4ecdc] group/media cursor-pointer transition-all duration-300 hover:border-[#C5A059] hover:shadow-[0_10px_25px_rgba(197,160,89,0.28)]"
                                                     @click.stop="openLightbox(@js($reviewMedia), activeThumb, {
                                                         author: @js($review['author_name']),
                                                         rating: {{ (int) $review['rating'] }},
                                                         text: @js($review['text']),
                                                         productName: @js($review['product_name'] ?? ''),
                                                         productUrl: @js($review['product_url'] ?? null)
                                                     })">

                                                    @foreach($reviewMedia as $mIdx => $mediaItem)
                                                        <div x-show="activeThumb === {{ $mIdx }}"
                                                             x-transition.opacity.duration.250ms
                                                             class="absolute inset-0 w-full h-full flex items-center justify-center p-2">
                                                            @if(($mediaItem['type'] ?? 'image') === 'video')
                                                                <video src="{{ $mediaItem['url'] }}"
                                                                       muted loop playsinline preload="metadata"
                                                                       class="relative z-10 max-w-full max-h-full object-contain rounded-xl shadow-sm transition-transform duration-500 group-hover/media:scale-[1.03]"></video>
                                                                <div class="absolute inset-0 z-20 flex items-center justify-center">
                                                                    <span class="w-11 h-11 rounded-full bg-white/95 border border-[#C5A059] text-[#8a6820] flex items-center justify-center shadow-md transition-transform duration-300 group-hover/media:scale-110">
                                                                        <svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                                    </span>
                                                                </div>
                                                            @else
                                                                {{-- Sanfter, heller Ambient-Hintergrund aus den Bildfarben --}}
                                                                <img src="{{ $mediaItem['url'] }}"
                                                                     alt=""
                                                                     aria-hidden="true"
                                                                     loading="lazy"
                                                                     class="absolute inset-0 w-full h-full object-cover blur-xl opacity-25 scale-110 pointer-events-none select-none">
                                                                {{-- Vollständig sichtbares Kundenbild ohne Beschnitt oben/unten --}}
                                                                <img src="{{ $mediaItem['url'] }}"
                                                                     alt="Kundenbild von {{ $review['author_name'] }}"
                                                                     loading="lazy"
                                                                     class="relative z-10 max-w-full max-h-full object-contain rounded-xl shadow-[0_4px_14px_rgba(138,104,32,0.14)] transition-transform duration-500 group-hover/media:scale-[1.04]">
                                                            @endif
                                                        </div>
                                                    @endforeach

                                                    {{-- Sanfter heller Gold-Schimmer beim Hover --}}
                                                    <div class="absolute inset-0 bg-gradient-to-t from-amber-500/10 via-transparent to-white/20 opacity-0 group-hover/media:opacity-100 transition-opacity duration-300 pointer-events-none z-10"></div>

                                                    {{-- Kleine schwebende Gold-Funken beim Hover --}}
                                                    <div class="absolute inset-0 pointer-events-none overflow-hidden z-20" aria-hidden="true">
                                                        <svg class="sf-sparkle-1 opacity-0 absolute bottom-4 left-6 w-4 h-4 text-amber-500 drop-shadow-[0_0_6px_rgba(251,191,36,0.95)]" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                                                        </svg>
                                                        <svg class="sf-sparkle-2 opacity-0 absolute bottom-7 right-8 w-5 h-5 text-[#C5A059] drop-shadow-[0_0_8px_rgba(197,160,89,0.95)]" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                                                        </svg>
                                                        <svg class="sf-sparkle-3 opacity-0 absolute top-5 left-1/4 w-3.5 h-3.5 text-amber-400 drop-shadow-[0_0_6px_rgba(251,191,36,0.9)]" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                                                        </svg>
                                                        <svg class="sf-sparkle-4 opacity-0 absolute top-5 right-1/4 w-4 h-4 text-amber-500 drop-shadow-[0_0_6px_rgba(245,158,11,0.95)]" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                                                        </svg>
                                                    </div>

                                                    {{-- Freundliches, helles Großansicht Badge --}}
                                                    <div class="absolute bottom-2.5 right-2.5 z-20 px-3 py-1 rounded-full bg-white/95 backdrop-blur-md border border-[#C5A059]/55 text-[#8a6820] text-[10px] font-bold tracking-wide flex items-center gap-1.5 shadow-sm group-hover/media:bg-[#fdfbf7] group-hover/media:scale-105 transition-all pointer-events-none">
                                                        <svg class="w-3 h-3 text-amber-500" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                                                        </svg>
                                                        <span>Großansicht</span>
                                                    </div>

                                                    {{-- Helle Inline-Slider Pfeile bei mehreren Bildern --}}
                                                    @if($mediaCount > 1)
                                                        <button type="button"
                                                                @click.stop="activeThumb = (activeThumb - 1 + total) % total"
                                                                title="Vorheriges Bild"
                                                                class="absolute left-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/40 shadow-sm flex items-center justify-center opacity-0 group-hover/media:opacity-100 transition-all cursor-pointer z-20">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                                        </button>
                                                        <button type="button"
                                                                @click.stop="activeThumb = (activeThumb + 1) % total"
                                                                title="Nächstes Bild"
                                                                class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/40 shadow-sm flex items-center justify-center opacity-0 group-hover/media:opacity-100 transition-all cursor-pointer z-20">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                                        </button>
                                                    @endif
                                                </div>

                                                {{-- Mini-Thumbnail-Leiste unterhalb, falls mehrere Bilder hinterlegt sind --}}
                                                @if($mediaCount > 1)
                                                    <div class="flex items-center gap-2 mt-2.5">
                                                        @foreach($reviewMedia as $tIdx => $thumbItem)
                                                            <button type="button"
                                                                    @click.stop="activeThumb = {{ $tIdx }}"
                                                                    :class="activeThumb === {{ $tIdx }} ? 'ring-2 ring-[#C5A059] border-white opacity-100 scale-[1.03]' : 'opacity-65 hover:opacity-100 border-amber-200/60'"
                                                                    class="relative w-12 h-12 rounded-xl overflow-hidden border transition-all cursor-pointer flex-shrink-0 bg-[#fdfbf7]">
                                                                @if(($thumbItem['type'] ?? 'image') === 'video')
                                                                    <video src="{{ $thumbItem['url'] }}" muted preload="metadata" class="w-full h-full object-cover"></video>
                                                                    <span class="absolute inset-0 flex items-center justify-center bg-black/25 text-white">
                                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                                    </span>
                                                                @else
                                                                    <img src="{{ $thumbItem['url'] }}" alt="Vorschau {{ $tIdx + 1 }}" loading="lazy" class="w-full h-full object-cover">
                                                                @endif
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                    </div>
                                </div>
                                @endforeach
                            @endfor
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
                            Wir veröffentlichen ausschließlich echte und geprüfte Kundenbewertungen (§ 5b Abs. 3 UWG). Nach deiner Bestellung kannst du dein Unikat direkt auf der jeweiligen Produktseite bewerten und erhältst als Dankeschön <strong class="text-[#8a6820] dark:text-primary">+{{ \App\Services\Gamification\GameConfig::REVIEW_SPARK_REWARD }} Seelenfunken</strong> für dein Treuekonto.
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

    {{-- FREUNDLICHES, HELLES LIGHTBOX SLIDER MODAL FÜR KUNDENBILDER & VIDEOS --}}
    <template x-teleport="body">
        <div x-show="lightbox.open"
             style="display: none;"
             x-transition.opacity.duration.250ms
             class="fixed inset-0 z-[9800] flex items-center justify-center p-3 sm:p-6">

            {{-- Sanfter, heller Blur-Hintergrund --}}
            <div class="fixed inset-0 bg-gray-900/35 backdrop-blur-sm"
                 @click="closeLightbox()"></div>

            <template x-if="lightbox.open && lightbox.items.length > 0">
                <div class="relative z-10 w-full max-w-3xl rounded-[2rem] bg-white border-2 border-[#C5A059]/45 shadow-[0_25px_80px_rgba(138,104,32,0.22)] overflow-hidden flex flex-col max-h-[92vh]"
                     @click.stop>

                    {{-- Freundlicher, heller Modal Header im Seelenfunke-Stil --}}
                    <div class="flex items-center justify-between px-5 sm:px-7 py-4 border-b border-amber-100 bg-gradient-to-r from-[#fdfbf7] via-amber-50/45 to-[#fdfbf7]">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-11 h-11 rounded-full bg-amber-50 text-[#8a6820] font-serif font-bold text-base flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-[#C5A059]/25 shrink-0 select-none"
                                 x-text="(lightbox.author || 'K').charAt(0).toUpperCase()"></div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-serif font-bold text-gray-900 text-base sm:text-lg truncate" x-text="lightbox.author"></span>
                                    <div class="flex text-amber-400">
                                        <template x-for="star in 5" :key="star">
                                            <svg class="w-4 h-4" :class="star <= lightbox.rating ? 'text-amber-400' : 'text-gray-200'" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                            </svg>
                                        </template>
                                    </div>
                                    @if($reviewSource === 'shop')
                                        <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-[10px] font-bold">
                                            <span>✓</span>
                                            <span>Verifizierter Kauf</span>
                                        </span>
                                    @endif
                                </div>
                                <template x-if="lightbox.productName">
                                    <div class="text-xs text-[#8a6820] font-semibold truncate mt-0.5">
                                        <template x-if="lightbox.productUrl">
                                            <a :href="lightbox.productUrl" class="hover:underline inline-flex items-center gap-1">
                                                <svg class="w-3 h-3 text-[#C5A059]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/></svg>
                                                <span x-text="'Bewertetes Unikat: ' + lightbox.productName"></span>
                                                <span>→</span>
                                            </a>
                                        </template>
                                        <template x-if="!lightbox.productUrl">
                                            <span x-text="'Bewertetes Unikat: ' + lightbox.productName"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0">
                            <template x-if="lightbox.items.length > 1">
                                <span class="px-3 py-1 rounded-full bg-amber-50 border border-[#C5A059]/40 text-xs font-bold text-[#8a6820]"
                                      x-text="(lightbox.index + 1) + ' / ' + lightbox.items.length"></span>
                            </template>
                            <button type="button"
                                    @click="closeLightbox()"
                                    class="w-9 h-9 rounded-full bg-white hover:bg-amber-50 text-gray-500 hover:text-gray-900 border border-gray-200 hover:border-[#C5A059] shadow-xs flex items-center justify-center transition-all cursor-pointer"
                                    aria-label="Großansicht schließen">
                                ✕
                            </button>
                        </div>
                    </div>

                    {{-- Helle, freundliche Galerie-Bühne (Slider) --}}
                    <div class="relative flex-1 bg-gradient-to-b from-[#fdfbf7] via-[#f7f2e4] to-[#fdfbf7] flex items-center justify-center min-h-[300px] sm:min-h-[450px] max-h-[65vh] overflow-hidden select-none p-4 sm:p-6">
                        <template x-if="lightbox.items[lightbox.index]">
                            <div class="relative w-full h-full flex items-center justify-center">
                                <template x-if="lightbox.items[lightbox.index].type === 'video'">
                                    <video :key="lightbox.items[lightbox.index].url"
                                           :src="lightbox.items[lightbox.index].url"
                                           controls autoplay playsinline
                                           class="relative z-10 max-h-[58vh] max-w-full rounded-2xl shadow-[0_15px_40px_rgba(138,104,32,0.18)] border-2 border-white ring-1 ring-[#C5A059]/30 bg-white"></video>
                                </template>
                                <template x-if="lightbox.items[lightbox.index].type !== 'video'">
                                    <div class="relative w-full h-full flex items-center justify-center">
                                        <img :src="lightbox.items[lightbox.index].url"
                                             alt=""
                                             aria-hidden="true"
                                             class="absolute inset-0 w-full h-full object-cover blur-2xl opacity-20 scale-110 pointer-events-none">
                                        <img :key="lightbox.items[lightbox.index].url"
                                             :src="lightbox.items[lightbox.index].url"
                                             :alt="'Kundenaufnahme von ' + lightbox.author"
                                             class="relative z-10 max-h-[58vh] max-w-full object-contain rounded-2xl shadow-[0_15px_40px_rgba(138,104,32,0.18)] border-2 border-white ring-1 ring-[#C5A059]/25 bg-white">
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Helle Slider Navigation Links / Rechts --}}
                        <template x-if="lightbox.items.length > 1">
                            <div>
                                <button type="button"
                                        @click="prevSlide()"
                                        class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/45 flex items-center justify-center shadow-md hover:shadow-lg transition-all cursor-pointer"
                                        aria-label="Vorherige Aufnahme">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button"
                                        @click="nextSlide()"
                                        class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/45 flex items-center justify-center shadow-md hover:shadow-lg transition-all cursor-pointer"
                                        aria-label="Nächste Aufnahme">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Heller Footer mit Kommentar & Thumbnail-Slider --}}
                    <div class="px-5 sm:px-7 py-4 bg-white border-t border-amber-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-start gap-2.5 flex-1 min-w-0" x-show="lightbox.text">
                            <span class="text-2xl leading-none font-serif text-[#C5A059] select-none" aria-hidden="true">„</span>
                            <p class="text-xs sm:text-sm text-gray-700 italic line-clamp-2"
                               x-text="lightbox.text + '“'"></p>
                        </div>

                        <template x-if="lightbox.items.length > 1">
                            <div class="flex items-center gap-2.5 shrink-0 justify-center sm:justify-end">
                                <template x-for="(item, idx) in lightbox.items" :key="idx">
                                    <button type="button"
                                            @click="lightbox.index = idx"
                                            :class="lightbox.index === idx ? 'ring-2 ring-[#C5A059] border-white opacity-100 scale-105 shadow-sm' : 'opacity-60 hover:opacity-95 border-amber-200/70'"
                                            class="relative w-14 h-14 rounded-xl overflow-hidden border transition-all cursor-pointer bg-[#fdfbf7] shrink-0">
                                        <template x-if="item.type === 'video'">
                                            <div class="w-full h-full relative">
                                                <video :src="item.url" muted preload="metadata" class="w-full h-full object-cover"></video>
                                                <span class="absolute inset-0 flex items-center justify-center bg-black/25 text-white">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                </span>
                                            </div>
                                        </template>
                                        <template x-if="item.type !== 'video'">
                                            <img :src="item.url" class="w-full h-full object-cover" alt="">
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
