@php
    $priorityOrder = [
        'seelen-kristall' => 1,
        'goldenes-seelen-herz' => 2,
        'seelen-anhaenger' => 3,
        'seelen-pfote-hundemarke' => 4,
        'seelen-pfote' => 5,
        'seelen-talisman' => 6,
        'id-armband-silber-herz' => 7,
        'echtsilber-seelen-armband' => 8,
        'id-armband-gold-herz' => 9,
        'gold-vermeil-seelen-armband' => 10,
    ];

    $showcaseProducts = \App\Models\Product\Product::where('status', 'active')
        ->where('slug', '!=', 'seelenfunke-ingame-artifact')
        ->with(['categories', 'tierPrices'])
        ->withCount(['reviews as approved_reviews_count' => fn ($q) => $q->where('status', 'approved')])
        ->withAvg(['reviews as approved_reviews_avg' => fn ($q) => $q->where('status', 'approved')], 'rating')
        ->get()
        ->filter(fn (\App\Models\Product\Product $product): bool => $product->isAvailable())
        ->sortBy(function (\App\Models\Product\Product $product) use ($priorityOrder): array {
            $isNonPhysical = ($product->type !== 'physical' || $product->slug === 'geschenkgutschein') ? 1 : 0;
            $slugRank = $priorityOrder[$product->slug] ?? 999;

            return [$isNonPhysical, $slugRank, (string) $product->id];
        })
        ->values()
        ->take(3);

    $manufakturCity = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
@endphp

<section id="services" class="bg-white text-black py-20" aria-labelledby="services-heading">
    <style>
        .sf-card {
            transform: translate3d(0, 0, 0);
            transition:
                transform 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                box-shadow 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                border-color 0.75s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform;
        }
        .sf-card.sf-is-active {
            transform: translate3d(0, -6px, 0);
            border-color: #D8C9A8;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        }
        .sf-card-img {
            transform: scale(1) translateZ(0);
            transition:
                transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1),
                opacity 0.55s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform, opacity;
        }
        .sf-card.sf-is-active .sf-card-img {
            transform: scale(1.045) translateZ(0);
        }
        .sf-card.sf-has-hover.sf-is-active .sf-card-main-layer {
            opacity: 0;
        }
        .sf-card-hover-layer {
            opacity: 0;
            transform: scale(1) translateZ(0);
            transition:
                opacity 0.55s cubic-bezier(0.22, 0.61, 0.36, 1),
                transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1);
            will-change: transform, opacity;
        }
        .sf-card.sf-has-hover.sf-is-active .sf-card-hover-layer {
            opacity: 1;
            transform: scale(1.045) translateZ(0);
        }
        .sf-card.sf-has-hover.sf-is-active .sf-ai-badge-main {
            opacity: 0;
        }
        .sf-ai-badge-hover {
            opacity: 0;
            transition: opacity 0.45s ease;
        }
        .sf-card.sf-has-hover.sf-is-active .sf-ai-badge-hover {
            opacity: 1;
        }
        .sf-card-sheen {
            position: absolute;
            inset: -100%;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0) 40%,
                rgba(255, 251, 235, 0.05) 44%,
                rgba(255, 248, 225, 0.18) 47.5%,
                rgba(255, 246, 215, 0.38) 50%,
                rgba(255, 248, 225, 0.18) 52.5%,
                rgba(255, 251, 235, 0.05) 56%,
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
            opacity: 0.92;
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
            const hoverVid = cardEl.querySelector('video[data-sf-hover-video]');
            if (hoverVid) {
                hoverVid.play().catch(() => {});
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
                const hoverVid = cardEl.querySelector('video[data-sf-hover-video]');
                if (hoverVid) {
                    hoverVid.pause();
                    hoverVid.currentTime = 0;
                }
                cardEl._sfCleanupTimer = setTimeout(() => {
                    cardEl._sfBusy = false;
                }, 1350);
            }, waitMs);
        };
    </script>

    <header class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-12">
        <h2 id="services-heading" class="text-primary-dark font-serif font-bold text-3xl sm:text-4xl lg:text-5xl">
            Unsere beliebtesten Unikate
        </h2>
        <p class="mt-4 text-gray-600 text-base max-w-3xl mx-auto">
            Handveredelt in unserer Manufaktur in {{ $manufakturCity }}: Wähle dein Lieblingsstück und gestalte es direkt im Live-Konfigurator.
        </p>
    </header>

    @if($showcaseProducts->isNotEmpty())
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
            @foreach($showcaseProducts as $product)
                @php
                    $productUrl = route('product.show', $product->slug);
                    $resolvedMainMeta = $product->resolveMainMediaMeta();
                    $resolvedMainMediaType = $resolvedMainMeta['type'] ?? 'image';
                    $resolvedMainImageUrl = $resolvedMainMeta['url'] ?? null;
                    $resolvedMainPosterUrl = $resolvedMainMeta['poster_url'] ?? null;
                    $isAiCardImage = (bool) ($resolvedMainMeta['is_ai_generated'] ?? false);

                    $resolvedHoverMeta = $product->resolveHoverMediaMeta($resolvedMainMeta['path'] ?? null);
                    $hasHoverMedia = !empty($resolvedHoverMeta['url']);
                    $resolvedHoverMediaType = $resolvedHoverMeta['type'] ?? 'image';
                    $resolvedHoverMediaUrl = $resolvedHoverMeta['url'] ?? null;
                    $isAiHoverMedia = (bool) ($resolvedHoverMeta['is_ai_generated'] ?? false);

                    $ratingAvg = round((float) ($product->approved_reviews_avg ?? 0), 1);
                    $ratingCount = (int) ($product->approved_reviews_count ?? 0);

                    $atelierHaystack = mb_strtolower($product->name . ' ' . $product->slug);
                    $isSilverOrVermeilAtelier = str_contains($atelierHaystack, 'silber')
                        || str_contains($atelierHaystack, 'vermeil')
                        || str_contains($atelierHaystack, '925');
                    $atelierBadgeLabel = $isSilverOrVermeilAtelier ? '925er Silber · Atelier' : 'Atelier';
                @endphp

                <article onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                         onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                         onclick="if(!event.target.closest('a, button')) window.location.href='{{ $productUrl }}'"
                         data-testid="homepage-showcase-card"
                         class="sf-card {{ $hasHoverMedia ? 'sf-has-hover' : '' }} group relative flex flex-col h-full bg-white rounded-[2rem] p-3.5 border border-gray-100 shadow-sm cursor-pointer">

                    <div class="aspect-square bg-gray-50 rounded-3xl overflow-hidden mb-5 relative border border-gray-100">
                        @if($product->isAtelierLine())
                            <div class="absolute top-3 right-3 z-30 pointer-events-none">
                                <div class="bg-[#F5F1E8]/95 backdrop-blur-sm text-gray-900 border border-[#D8C9A8] text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                                    {{ $atelierBadgeLabel }}
                                </div>
                            </div>
                        @endif

                        @if($product->compare_at_price > $product->price)
                            @php
                                $discountPercent = (int) round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100);
                            @endphp
                            <div class="absolute top-3 left-3 z-20 pointer-events-none">
                                <span class="w-fit bg-red-600/95 backdrop-blur-sm text-white text-[10px] font-black px-2.5 py-1 rounded-full shadow-md tracking-wider leading-none border border-white/15">
                                    -{{ max(1, $discountPercent) }}%
                                </span>
                            </div>
                        @endif

                        <a href="{{ $productUrl }}" class="block w-full h-full relative">
                            <div class="w-full h-full relative">
                                @if($resolvedMainImageUrl && $resolvedMainMediaType === 'video')
                                    <video src="{{ $resolvedMainImageUrl }}"
                                           @if($resolvedMainPosterUrl) poster="{{ $resolvedMainPosterUrl }}" @endif
                                           @if($isAiCardImage) data-is-ai-generated="true" @endif
                                           class="w-full h-full object-cover sf-card-main-layer sf-card-img"
                                           muted autoplay loop playsinline></video>
                                @elseif($resolvedMainImageUrl)
                                    <img src="{{ $resolvedMainImageUrl }}"
                                         alt="{{ $product->name }}"
                                         loading="lazy"
                                         decoding="async"
                                         @if($isAiCardImage) data-is-ai-generated="true" @endif
                                         class="w-full h-full object-cover sf-card-main-layer sf-card-img">
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-300">
                                        <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif

                                @if($hasHoverMedia && $resolvedHoverMediaUrl)
                                    @if($resolvedHoverMediaType === 'video')
                                        <video src="{{ $resolvedHoverMediaUrl }}"
                                               @if($isAiHoverMedia) data-is-ai-generated="true" @endif
                                               data-sf-hover-video
                                               preload="metadata"
                                               muted loop playsinline
                                               class="absolute inset-0 w-full h-full object-cover sf-card-hover-layer pointer-events-none"></video>
                                    @else
                                        <img src="{{ $resolvedHoverMediaUrl }}"
                                             alt="{{ str_replace([' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' '], ' · ', (string) ($resolvedHoverMeta['label'] ?? ($product->name . ' · Detailansicht'))) }}"
                                             loading="lazy"
                                             decoding="async"
                                             @if($isAiHoverMedia) data-is-ai-generated="true" @endif
                                             class="absolute inset-0 w-full h-full object-cover sf-card-hover-layer pointer-events-none">
                                    @endif
                                @endif
                            </div>

                            @if($resolvedMainImageUrl && $isAiCardImage)
                                <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                      class="sf-ai-badge-main transition-opacity duration-300 pointer-events-none select-none absolute bottom-2 right-2 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                    KI-generiert
                                </span>
                            @endif

                            @if($hasHoverMedia && $isAiHoverMedia)
                                <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                      class="sf-ai-badge-hover pointer-events-none select-none absolute bottom-2 right-2 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                    KI-generiert
                                </span>
                            @endif

                            <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                            @for($sp = 1; $sp <= 6; $sp++)
                                <span class="sf-card-sparkle sf-card-sparkle-{{ $sp }} pointer-events-none absolute top-[20%] left-[20%] {{ $sp % 2 === 0 ? 'w-3.5 h-3.5 text-amber-300' : 'w-3 h-3 text-amber-200' }} drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                            @endfor
                        </a>
                    </div>

                    <div class="flex-1 flex flex-col px-3 pb-3">
                        <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 group-hover:text-primary-dark transition-colors mb-1.5 leading-tight line-clamp-2">
                            <a href="{{ $productUrl }}">
                                {{ $product->name }}
                            </a>
                        </h3>

                        @if($ratingCount > 0)
                            <div class="inline-flex items-center gap-1.5 mb-2">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3.5 h-3.5 shrink-0 {{ $i <= round($ratingAvg) ? 'text-[#C5A059]' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-[11px] font-bold text-gray-700">
                                    {{ number_format($ratingAvg, 1, ',', '.') }} <span class="text-gray-400 font-medium">({{ $ratingCount }})</span>
                                </span>
                            </div>
                        @endif

                        <div class="mt-auto pt-4 flex items-end justify-between gap-3">
                            <div class="flex flex-col">
                                <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">
                                    @if($product->tierPrices->isNotEmpty())
                                        Ab
                                    @else
                                        Preis
                                    @endif
                                </span>
                                <div class="flex items-baseline gap-2">
                                    <span class="{{ $product->compare_at_price > $product->price ? 'text-red-600' : 'text-gray-900' }} font-bold text-xl font-serif">
                                        {{ number_format($product->gross_price / 100, 2, ',', '.') }} €<span class="text-xs text-gray-400 font-sans font-normal ml-0.5">*</span>
                                    </span>
                                    @if($product->compare_at_price > $product->price)
                                        <span class="text-xs text-gray-400 line-through font-medium">
                                            {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                        </span>
                                    @endif
                                </div>
                                @if($product->compare_at_price > $product->price)
                                    <span class="text-[10px] text-gray-500 font-medium leading-tight mt-0.5">
                                        30-Tage-Bestpreis (§ 11 PAngV): {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                    </span>
                                @endif
                            </div>

                            <a href="{{ $productUrl }}"
                               class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-full bg-[#F5F1E8] text-gray-900 border border-[#D8C9A8] font-bold text-xs whitespace-nowrap hover:bg-gray-900 hover:text-white hover:border-gray-900 group-hover:bg-gray-900 group-hover:text-white group-hover:border-gray-900 transition-colors shadow-sm">
                                Jetzt gestalten →
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 text-center">
        <a href="{{ route('shop') }}"
           class="inline-flex items-center justify-center gap-2.5 bg-primary text-gray-900 px-8 py-4 rounded-full font-bold text-base sm:text-lg hover:bg-primary-dark hover:text-white transition-colors shadow-md">
            <span>Alle Unikate im Shop entdecken →</span>
        </a>
    </div>

    <p class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6 text-center text-xs text-gray-500">
        * Alle angegebenen Preise verstehen sich {{ ((bool) shop_setting('is_small_business', false)) ? 'als Endpreise gem. § 19 UStG' : 'inkl. gesetzl. MwSt.' }}, <a href="{{ route('versand') }}" class="underline hover:text-gray-700">zzgl. Versandkosten</a>.
    </p>
</section>
