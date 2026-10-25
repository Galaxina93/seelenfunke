@php
    $crossSell = $this->product->resolveCrossSellProducts(4);
    $relatedProducts = $crossSell['products'] ?? collect();
@endphp

@if($relatedProducts->isNotEmpty())
    <section class="mt-20 pt-16 border-t border-gray-100 w-full clear-both">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
            <div>
                <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900">
                    {{ $crossSell['title'] }}
                </h2>
                <p class="text-sm text-gray-500 mt-1.5">
                    {{ $crossSell['subtitle'] }}
                </p>
            </div>

            <a href="{{ route('shop') }}"
               class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-600 hover:text-[var(--theme-color)] transition-colors shrink-0 group">
                <span>Zur gesamten Kollektion</span>
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </a>
        </div>

        <style>
            .sf-rel-card {
                transform: translate3d(0, 0, 0);
                transition:
                    transform 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                    box-shadow 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                    border-color 0.75s cubic-bezier(0.22, 0.61, 0.36, 1);
                will-change: transform;
            }
            .sf-rel-card.sf-is-active {
                transform: translate3d(0, -6px, 0);
                border-color: var(--theme-color-30);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            }
            .sf-rel-img {
                transform: scale(1) translateZ(0);
                transition: transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1);
                will-change: transform;
            }
            .sf-rel-card.sf-is-active .sf-rel-img {
                transform: scale(1.045) translateZ(0);
            }
            .sf-rel-sheen {
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
            .sf-rel-card.sf-is-active .sf-rel-sheen {
                transform: translate3d(32%, 32%, 0);
            }
            @keyframes sfRelSparkleFloatA {
                0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
                33% { transform: translate3d(4px, -6px, 0) rotate(18deg); }
                66% { transform: translate3d(-3px, -2px, 0) rotate(-12deg); }
            }
            @keyframes sfRelSparkleFloatB {
                0%, 100% { transform: translate3d(0, 0, 0) rotate(0deg); }
                33% { transform: translate3d(-5px, -4px, 0) rotate(-20deg); }
                66% { transform: translate3d(3px, -7px, 0) rotate(14deg); }
            }
            .sf-rel-sparkle {
                opacity: 0;
                transform: scale(0.35) translateZ(0);
                transition:
                    opacity 0.75s cubic-bezier(0.22, 0.61, 0.36, 1) 0s,
                    transform 0.85s cubic-bezier(0.22, 0.61, 0.36, 1) 0s;
                will-change: transform, opacity;
            }
            .sf-rel-card.sf-is-active .sf-rel-sparkle {
                opacity: 0.92;
                transform: scale(1) translateZ(0);
            }
            .sf-rel-card.sf-is-active .sf-rel-sparkle-1 { transition-delay: 0.04s; }
            .sf-rel-card.sf-is-active .sf-rel-sparkle-2 { transition-delay: 0.11s; }
            .sf-rel-card.sf-is-active .sf-rel-sparkle-3 { transition-delay: 0.18s; }
            .sf-rel-card.sf-is-active .sf-rel-sparkle-4 { transition-delay: 0.25s; }
            .sf-rel-card.sf-is-active .sf-rel-sparkle-5 { transition-delay: 0.32s; }
            .sf-rel-card.sf-is-active .sf-rel-sparkle-6 { transition-delay: 0.39s; }
            .sf-rel-sparkle > svg { display: block; width: 100%; height: 100%; will-change: transform; }
            .sf-rel-sparkle-1 > svg { animation: sfRelSparkleFloatA 3.4s ease-in-out infinite; }
            .sf-rel-sparkle-2 > svg { animation: sfRelSparkleFloatB 4.0s ease-in-out infinite 0.4s; }
            .sf-rel-sparkle-3 > svg { animation: sfRelSparkleFloatB 3.2s ease-in-out infinite 0.9s; }
            .sf-rel-sparkle-4 > svg { animation: sfRelSparkleFloatA 3.8s ease-in-out infinite 1.3s; }
            .sf-rel-sparkle-5 > svg { animation: sfRelSparkleFloatA 4.2s ease-in-out infinite 0.7s; }
            .sf-rel-sparkle-6 > svg { animation: sfRelSparkleFloatB 3.6s ease-in-out infinite 1.6s; }
        </style>
        <script>
            window.sfRandomizeCardSparkles = function(cardEl) {
                const zones = [
                    { top: [10, 28], left: [10, 35] },
                    { top: [10, 28], left: [62, 86] },
                    { top: [36, 60], left: [8, 28] },
                    { top: [36, 60], left: [68, 88] },
                    { top: [66, 86], left: [12, 40] },
                    { top: [66, 86], left: [58, 86] },
                ];
                const rand = (min, max) => (min + Math.random() * (max - min)).toFixed(1) + '%';
                cardEl.querySelectorAll('.sf-card-sparkle, .sf-rel-sparkle').forEach((el, i) => {
                    const z = zones[i % zones.length];
                    el.style.top = rand(z.top[0], z.top[1]);
                    el.style.left = rand(z.left[0], z.left[1]);
                    el.style.right = 'auto';
                    el.style.bottom = 'auto';
                });
            };
            window.sfCardHoverEnter = function(cardEl) {
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
            window.sfCardHoverLeave = function(cardEl) {
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-8">
            @foreach($relatedProducts as $relProduct)
                @php
                    $isSoldOut = ($relProduct->track_quantity && $relProduct->quantity <= 0 && !$relProduct->continue_selling_when_out_of_stock);
                    $isBackorder = ($relProduct->track_quantity && $relProduct->quantity <= 0 && $relProduct->continue_selling_when_out_of_stock);
                    $lowStock = ($relProduct->track_quantity && $relProduct->quantity > 0 && $relProduct->quantity <= 5);

                    $relUrl = route('product.show', $relProduct->slug);
                    $resolvedMeta = $relProduct->resolveMainImageMeta();
                    $resolvedImageUrl = $resolvedMeta['url'] ?? null;
                    $isAiCardImage = (bool) ($resolvedMeta['is_ai_generated'] ?? false);

                    $ratingAvg = round((float) ($relProduct->approved_reviews_avg ?? $relProduct->average_rating ?? 0), 1);
                    $ratingCount = (int) ($relProduct->approved_reviews_count ?? $relProduct->review_count ?? 0);
                @endphp

                <div onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                     onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                     class="sf-rel-card group relative flex flex-col h-full bg-white rounded-[2rem] p-3 border border-gray-100 shadow-sm {{ $isSoldOut ? 'opacity-80' : '' }}">

                    {{-- Bild Container --}}
                    <div class="aspect-square bg-gray-50 rounded-3xl overflow-hidden mb-5 relative border border-gray-100">

                        {{-- Product Type Badges --}}
                        @if(!$isSoldOut && !$isBackorder)
                            <div class="absolute top-3 right-3 z-30 flex flex-col gap-2 pointer-events-none">
                                @if($relProduct->type === 'digital')
                                    <div class="bg-blue-600/90 backdrop-blur text-white text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        Digital
                                    </div>
                                @endif
                                @if($relProduct->type === 'service')
                                    <div class="bg-orange-500/90 backdrop-blur text-white text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                        Service
                                    </div>
                                @endif
                            </div>
                        @endif

                        <a href="{{ $relUrl }}" class="block w-full h-full">
                            <div class="w-full h-full {{ $isSoldOut ? 'grayscale opacity-50' : '' }}">
                                @if($resolvedImageUrl)
                                    <img src="{{ $resolvedImageUrl }}"
                                         alt="{{ $relProduct->name }}"
                                         @if($isAiCardImage) data-is-ai-generated="true" @endif
                                         class="w-full h-full object-cover {{ !$isSoldOut ? 'sf-rel-img' : '' }}">
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-300">
                                        <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                            </div>

                            @if($resolvedImageUrl && $isAiCardImage)
                                <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                      class="pointer-events-none select-none absolute bottom-2 right-2 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                    KI-generiert
                                </span>
                            @endif

                            @if(!$isSoldOut)
                                <div class="sf-rel-sheen pointer-events-none z-10" aria-hidden="true"></div>
                                <span class="sf-rel-sparkle sf-rel-sparkle-1 pointer-events-none absolute top-[14%] left-[15%] w-3.5 h-3.5 text-amber-200 drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-rel-sparkle sf-rel-sparkle-2 pointer-events-none absolute top-[17%] right-[15%] w-4 h-4 text-amber-300 drop-shadow-[0_0_7px_rgba(251,191,36,0.95)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-rel-sparkle sf-rel-sparkle-3 pointer-events-none absolute top-[48%] left-[11%] w-2.5 h-2.5 text-amber-100 drop-shadow-[0_0_5px_rgba(251,191,36,0.85)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-rel-sparkle sf-rel-sparkle-4 pointer-events-none absolute top-[44%] right-[12%] w-3 h-3 text-amber-200 drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-rel-sparkle sf-rel-sparkle-5 pointer-events-none absolute bottom-[21%] left-[16%] w-3.5 h-3.5 text-amber-200 drop-shadow-[0_0_6px_rgba(251,191,36,0.88)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-rel-sparkle sf-rel-sparkle-6 pointer-events-none absolute bottom-[16%] right-[16%] w-4 h-4 text-amber-300 drop-shadow-[0_0_7px_rgba(251,191,36,0.95)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                            @endif
                        </a>

                        {{-- Kompakter Prozent- & Neu-Indikator oben links --}}
                        <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5 pointer-events-none z-20">
                            @if(!$isSoldOut && $relProduct->compare_at_price > $relProduct->price)
                                @php
                                    $discountPercent = (int) round((($relProduct->compare_at_price - $relProduct->price) / $relProduct->compare_at_price) * 100);
                                @endphp
                                <span class="w-fit bg-red-600/95 backdrop-blur-sm text-white text-[10px] font-black px-2.5 py-1 rounded-full shadow-md tracking-wider leading-none border border-white/15">
                                    -{{ max(1, $discountPercent) }}%
                                </span>
                            @endif
                            @if(!$isSoldOut && $relProduct->created_at->diffInDays() < 21)
                                <span class="w-fit bg-white/95 backdrop-blur text-gray-900 text-[10px] font-black px-2.5 py-1 rounded-full shadow-md uppercase tracking-wider leading-none border border-gray-100">
                                    Neu
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Infos --}}
                    <div class="flex-1 flex flex-col px-3 pb-3 relative">

                        @if($relProduct->categories->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach($relProduct->categories->take(2) as $pCat)
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-gray-50 px-2 py-0.5 rounded-md">{{ $pCat->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        <h3 class="text-lg font-serif font-bold {{ $isSoldOut ? 'text-gray-400' : 'text-gray-900 group-hover:text-[var(--theme-color)] transition-colors' }} mb-1.5 leading-tight line-clamp-2">
                            <a href="{{ $relUrl }}">
                                {{ $relProduct->name }}
                            </a>
                        </h3>

                        {{-- Sternebewertung --}}
                        <a href="{{ $relUrl }}#kundenbewertungen-desktop"
                           class="inline-flex items-center gap-1.5 mb-2 group/rating w-fit"
                           title="{{ $ratingCount > 0 ? number_format($ratingAvg, 1, ',', '.') . ' von 5 Sternen (' . $ratingCount . ' Bewertungen)' : 'Noch keine Bewertungen' }}">
                            <div class="flex items-center">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-3.5 h-3.5 shrink-0 {{ $i <= round($ratingAvg) ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                    </svg>
                                @endfor
                            </div>
                            <span class="text-[11px] font-bold {{ $ratingCount > 0 ? 'text-gray-700' : 'text-gray-400' }} group-hover/rating:text-[var(--theme-color)] transition-colors">
                                @if($ratingCount > 0)
                                    {{ number_format($ratingAvg, 1, ',', '.') }} <span class="text-gray-400 font-medium">({{ $ratingCount }})</span>
                                @else
                                    <span class="text-[10px] text-gray-400 font-medium">(0)</span>
                                @endif
                            </span>
                        </a>

                        @if($lowStock && !$isSoldOut)
                            <div class="mb-2">
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-black text-orange-600 bg-orange-50 px-2 py-1 rounded border border-orange-100 uppercase tracking-widest animate-pulse">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Nur noch {{ $relProduct->quantity }}
                                </span>
                            </div>
                        @endif

                        <div class="mt-auto pt-3 flex items-end justify-between">
                            <div class="flex flex-col">
                                @if(!$isSoldOut && $relProduct->compare_at_price > $relProduct->price)
                                    @php
                                        $saleBadgeText = $relProduct->configurator_settings['autopilot_badge_text'] ?? 'Angebot';
                                    @endphp
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-extrabold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200/80 w-fit">
                                            {{ $saleBadgeText }}
                                        </span>
                                        @if($relProduct->tierPrices->isNotEmpty())
                                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Ab</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">
                                        @if($relProduct->slug === 'geschenkgutschein' || ($relProduct->price == 0 && $relProduct->is_personalizable))
                                            Wunschbetrag
                                        @elseif($relProduct->tierPrices->isNotEmpty())
                                            Ab
                                        @else
                                            Preis
                                        @endif
                                    </span>
                                @endif

                                <div class="flex items-baseline gap-2">
                                    <span class="{{ $isSoldOut ? 'text-gray-400' : ($relProduct->compare_at_price > $relProduct->price ? 'text-red-600' : 'text-gray-900') }} font-bold text-xl font-serif">
                                        @if($relProduct->slug === 'geschenkgutschein' || ($relProduct->price == 0 && $relProduct->is_personalizable))
                                            ab 5,00 €
                                        @else
                                            {{ number_format($relProduct->price / 100, 2, ',', '.') }} €
                                        @endif
                                    </span>
                                    @if(!$isSoldOut && $relProduct->compare_at_price > $relProduct->price)
                                        <span class="text-xs text-gray-400 line-through font-medium">
                                            {{ number_format($relProduct->compare_at_price / 100, 2, ',', '.') }} €
                                        </span>
                                    @endif
                                </div>

                                @if(!$isSoldOut && $relProduct->compare_at_price > $relProduct->price)
                                    <span class="text-[8px] text-gray-400 font-medium leading-tight mt-0.5">
                                        30-Tage-Bestpreis (§ 11 PAngV): {{ number_format($relProduct->compare_at_price / 100, 2, ',', '.') }} €
                                    </span>
                                @endif

                                <span class="text-[8px] text-gray-400 font-medium leading-tight mt-1">
                                    @if(shop_setting('is_small_business', false))
                                        Keine MwSt. gem. § 19 UStG · <a href="{{ route('versand') }}" target="_blank" class="hover:text-gray-600 underline">zzgl. Versand</a>
                                    @else
                                        inkl. MwSt. <a href="{{ route('versand') }}" target="_blank" class="hover:text-gray-600 underline">zzgl. Versand</a>
                                    @endif
                                </span>
                            </div>

                            {{-- Circle Action Button --}}
                            <a href="{{ $relUrl }}" class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 transform group-hover:scale-110 {{ $isSoldOut ? 'bg-gray-100 text-gray-400' : 'bg-gray-900 text-white hover:bg-[var(--theme-color)] shadow-lg' }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
