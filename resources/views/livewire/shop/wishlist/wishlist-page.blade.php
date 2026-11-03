<div class="bg-gray-50 min-h-screen pt-28 pb-16 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">

        {{-- Header & Teilen-Leiste --}}
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8">
            <div>
                <span class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-widest text-primary mb-2">
                    <svg class="w-4 h-4 fill-primary" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    {{ $isSharedView ? 'Geteilte Geschenkideen' : 'Merkzettel & Geschenkideen' }}
                </span>
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900">
                    {{ $isSharedView ? 'Geteilte Wunschliste' : 'Deine Wunschliste' }}
                </h1>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                @if($products->isNotEmpty())
                    @php
                        $waText = urlencode("Schau mal, das sind meine Lieblingsstücke bei Mein Seelenfunke ✨💝:\n" . $shareUrl);
                        $mailSubject = rawurlencode('Meine Wunschliste bei Mein Seelenfunke ✨');
                        $mailBody = rawurlencode("Hallo,\n\nhier ist meine Wunschliste mit meinen Lieblingsstücken aus der Manufaktur Mein Seelenfunke:\n" . $shareUrl);
                    @endphp
                    <div x-data="{ copied: false, url: @js($shareUrl) }"
                         class="flex flex-wrap items-center gap-2 bg-white px-3.5 py-2 rounded-2xl border border-gray-200/80 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 mr-1 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            Wunschliste teilen:
                        </span>

                        <button type="button"
                                @click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 2500)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border transition-all cursor-pointer"
                                :class="copied ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-gray-50 hover:bg-gray-100 border-gray-200 text-gray-700'">
                            <span x-show="!copied">🔗 Link kopieren</span>
                            <span x-show="copied" x-cloak>✓ Link kopiert!</span>
                        </button>

                        <a href="https://wa.me/?text={{ $waText }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-[#25D366] hover:bg-[#20bd5a] text-white transition-all shadow-xs">
                            <span>WhatsApp</span>
                        </a>

                        <a href="mailto:?subject={{ $mailSubject }}&body={{ $mailBody }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-900 hover:bg-black text-white transition-all">
                            <span>✉ E-Mail</span>
                        </a>
                    </div>
                @endif

                <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-600 hover:text-primary transition-colors ml-auto lg:ml-2">
                    ← Weiter im Shop stöbern
                </a>
            </div>
        </div>

        @if($isSharedView && $products->isNotEmpty())
            <div class="mb-8 p-5 rounded-3xl bg-gradient-to-r from-amber-50 via-white to-amber-50/60 border border-primary/30 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-primary/15 text-primary flex items-center justify-center shrink-0 font-bold text-xl">
                        💝
                    </div>
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-gray-900">Diese Wunschliste wurde mit dir geteilt</h2>
                        <p class="text-xs text-gray-600">Du kannst dir die Unikate direkt ansehen, im 3D-Konfigurator gestalten oder alle Artikel auf deine eigene Wunschliste übernehmen.</p>
                    </div>
                </div>
                <button type="button"
                        wire:click="importSharedWishlist"
                        class="shrink-0 px-5 py-2.5 rounded-xl bg-gray-900 hover:bg-primary text-white hover:text-gray-900 text-xs font-black uppercase tracking-wider transition-all cursor-pointer shadow-sm">
                    ♥ Auf meine Wunschliste übernehmen
                </button>
            </div>
        @endif

        @if($products->isEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center max-w-xl mx-auto my-8">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6 text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-serif font-bold text-gray-900 mb-2">Deine Wunschliste ist noch leer</h2>
                <p class="text-gray-500 text-sm mb-8">
                    Markiere deine Lieblingsstücke im Shop einfach mit einem Klick auf das Herz-Symbol, um sie für später oder besondere Anlässe zu speichern und mit deinen Liebsten zu teilen.
                </p>
                <a href="{{ route('shop') }}" class="inline-flex items-center justify-center bg-gray-900 text-white px-8 py-3.5 rounded-full font-bold text-xs uppercase tracking-widest hover:bg-primary hover:text-gray-900 transition-all shadow-lg">
                    Jetzt Unikate entdecken
                </a>
            </div>
        @else
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
                    border-color: var(--theme-color-30);
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
                }
                .sf-card-img {
                    transform: scale(1) translateZ(0);
                    transition: transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1);
                    will-change: transform;
                }
                .sf-card.sf-is-active .sf-card-img {
                    transform: scale(1.045) translateZ(0);
                }
                .sf-card-main-layer {
                    transition: opacity 0.55s cubic-bezier(0.22, 0.61, 0.36, 1);
                }
                .sf-card-hover-layer {
                    opacity: 0;
                    transition: opacity 0.55s cubic-bezier(0.22, 0.61, 0.36, 1);
                    will-change: opacity;
                }
                .sf-card.sf-is-active.sf-has-hover-media .sf-card-hover-layer {
                    opacity: 1;
                }
                .sf-card.sf-is-active.sf-has-hover-media .sf-card-main-layer {
                    opacity: 0;
                }
                .sf-card.sf-is-active.sf-has-hover-media .sf-ai-badge-main {
                    opacity: 0;
                }
                .sf-card .sf-ai-badge-hover {
                    opacity: 0;
                    transition: opacity 0.4s ease;
                }
                .sf-card.sf-is-active.sf-has-hover-media .sf-ai-badge-hover {
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
                .sf-card-sparkle {
                    opacity: 0;
                    transform: scale(0.35) translateZ(0);
                    transition:
                        opacity 0.75s cubic-bezier(0.22, 0.61, 0.36, 1) 0s,
                        transform 0.85s cubic-bezier(0.22, 0.61, 0.36, 1) 0s;
                }
                .sf-card.sf-is-active .sf-card-sparkle {
                    opacity: 0.92;
                    transform: scale(1) translateZ(0);
                }
            </style>
            <script>
                if (!window.sfCardHoverEnter) {
                    window.sfCardHoverEnter = function(cardEl) {
                        clearTimeout(cardEl._sfLeaveTimer);
                        cardEl.classList.add('sf-is-active');
                        const hoverVid = cardEl.querySelector('video[data-sf-hover-video]');
                        if (hoverVid && typeof hoverVid.play === 'function') {
                            hoverVid.currentTime = 0;
                            hoverVid.play().catch(() => {});
                        }
                    };
                    window.sfCardHoverLeave = function(cardEl) {
                        clearTimeout(cardEl._sfLeaveTimer);
                        cardEl._sfLeaveTimer = setTimeout(() => {
                            cardEl.classList.remove('sf-is-active');
                            const hoverVid = cardEl.querySelector('video[data-sf-hover-video]');
                            if (hoverVid && typeof hoverVid.pause === 'function') {
                                hoverVid.pause();
                            }
                        }, 350);
                    };
                }
            </script>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-8">
                @foreach($products as $product)
                    @php
                        $isSoldOut = ($product->track_quantity && $product->quantity <= 0 && !$product->continue_selling_when_out_of_stock);
                        $isBackorder = ($product->track_quantity && $product->quantity <= 0 && $product->continue_selling_when_out_of_stock);
                        $lowStock = ($product->track_quantity && $product->quantity > 0 && $product->quantity <= 5);
                        $productUrl = route('product.show', $product->slug);
                        $resolvedMainMeta = $product->resolveMainMediaMeta();
                        $resolvedMainMediaUrl = $resolvedMainMeta['url'] ?? null;
                        $resolvedMainMediaType = $resolvedMainMeta['type'] ?? 'image';
                        $resolvedMainPosterUrl = $resolvedMainMeta['poster_url'] ?? null;
                        $isAiCardImage = (bool) ($resolvedMainMeta['is_ai_generated'] ?? false);

                        $resolvedHoverMeta = $product->resolveHoverMediaMeta($resolvedMainMeta['path'] ?? null);
                        $resolvedHoverMediaUrl = $resolvedHoverMeta['url'] ?? null;
                        $resolvedHoverMediaType = $resolvedHoverMeta['type'] ?? 'image';
                        $isAiHoverImage = (bool) ($resolvedHoverMeta['is_ai_generated'] ?? false);
                        $hasHoverMedia = !empty($resolvedHoverMediaUrl) && !$isSoldOut;

                        $isWishlisted = in_array((string) $product->id, $wishlistProductIds ?? [], true);
                    @endphp

                    <div wire:key="wishlist-card-{{ $product->id }}"
                         onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                         onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                         class="sf-card {{ $hasHoverMedia ? 'sf-has-hover-media' : '' }} group relative flex flex-col h-full bg-white rounded-[2rem] p-3 border border-gray-100 shadow-sm {{ $isSoldOut ? 'opacity-80' : '' }}">

                        {{-- Bild Container (identisch zur Shop-Hauptansicht) --}}
                        <div class="aspect-square bg-gray-50 rounded-3xl overflow-hidden mb-5 relative border border-gray-100">

                            {{-- Herz-Button unten links --}}
                            <button type="button"
                                    x-data="{ wishlisted: @js($isWishlisted) }"
                                    @click.prevent="wishlisted = !wishlisted; if (window.sfTriggerWishlistHeartEffect) window.sfTriggerWishlistHeartEffect($event, wishlisted); $wire.toggleWishlist('{{ $product->id }}')"
                                    :class="wishlisted ? 'bg-red-500 text-white scale-105 shadow-[0_0_15px_rgba(239,68,68,0.45)]' : 'bg-white/90 text-gray-500 hover:text-red-500 hover:bg-white'"
                                    class="absolute bottom-3 left-3 z-30 w-9 h-9 rounded-full backdrop-blur-md shadow-md flex items-center justify-center transition-all duration-200 cursor-pointer"
                                    title="{{ $isWishlisted ? 'Von Wunschliste entfernen' : 'Auf die Wunschliste' }}"
                                    aria-label="Wunschliste">
                                <svg class="w-4 h-4 transition-transform duration-200" :class="wishlisted ? 'fill-current scale-110' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                            </button>

                            <a href="{{ $productUrl }}" class="block w-full h-full">
                                <div class="w-full h-full relative {{ $isSoldOut ? 'grayscale opacity-50' : '' }}">
                                    @if($resolvedMainMediaUrl)
                                        @if($resolvedMainMediaType === 'video')
                                            <video src="{{ $resolvedMainMediaUrl }}"
                                                   @if($resolvedMainPosterUrl) poster="{{ $resolvedMainPosterUrl }}" @endif
                                                   class="sf-card-main-layer w-full h-full object-cover {{ !$isSoldOut ? 'sf-card-img' : '' }}"
                                                   muted autoplay loop playsinline preload="metadata"></video>
                                        @else
                                            <img src="{{ $resolvedMainMediaUrl }}"
                                                 alt="{{ $product->name }}"
                                                 @if($isAiCardImage) data-is-ai-generated="true" @endif
                                                 class="sf-card-main-layer w-full h-full object-cover {{ !$isSoldOut ? 'sf-card-img' : '' }}">
                                        @endif
                                    @elseif(is_array($product->media_gallery) && count($product->media_gallery) > 0 && isset($product->media_gallery[0]['type']) && $product->media_gallery[0]['type'] === 'video')
                                        <video src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}" class="sf-card-main-layer w-full h-full object-cover" muted autoplay loop playsinline></video>
                                    @else
                                        <div class="flex items-center justify-center h-full text-gray-300">
                                            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif

                                    @if($hasHoverMedia)
                                        @if($resolvedHoverMediaType === 'video')
                                            <video src="{{ $resolvedHoverMediaUrl }}"
                                                   data-sf-hover-video="true"
                                                   class="sf-card-hover-layer absolute inset-0 w-full h-full object-cover sf-card-img pointer-events-none"
                                                   muted loop playsinline preload="metadata"></video>
                                        @else
                                            <img src="{{ $resolvedHoverMediaUrl }}"
                                                 alt="{{ $product->name }} – Ansicht 2"
                                                 loading="lazy"
                                                 decoding="async"
                                                 @if($isAiHoverImage) data-is-ai-generated="true" @endif
                                                 class="sf-card-hover-layer absolute inset-0 w-full h-full object-cover sf-card-img pointer-events-none">
                                        @endif
                                    @endif
                                </div>

                                @if($resolvedMainMediaUrl && $isAiCardImage)
                                    <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                          class="sf-ai-badge-main transition-opacity duration-300 pointer-events-none select-none absolute bottom-2 right-2 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                        KI-generiert
                                    </span>
                                @endif

                                @if($hasHoverMedia && $isAiHoverImage)
                                    <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                          class="sf-ai-badge-hover pointer-events-none select-none absolute bottom-2 right-2 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                        KI-generiert
                                    </span>
                                @endif

                                @if(!$isSoldOut)
                                    <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                                    <span class="sf-card-sparkle pointer-events-none absolute top-[14%] left-[15%] w-3.5 h-3.5 text-amber-200 drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                    </span>
                                    <span class="sf-card-sparkle pointer-events-none absolute top-[17%] right-[15%] w-4 h-4 text-amber-300 drop-shadow-[0_0_7px_rgba(251,191,36,0.95)] z-10" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                    </span>
                                    <span class="sf-card-sparkle pointer-events-none absolute bottom-[16%] right-[16%] w-4 h-4 text-amber-300 drop-shadow-[0_0_7px_rgba(251,191,36,0.95)] z-10" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                    </span>
                                @endif
                            </a>

                            {{-- Kompakter Prozent- & Neu-Indikator oben links --}}
                            <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5 pointer-events-none z-20">
                                @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                    @php
                                        $discountPercent = (int) round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100);
                                    @endphp
                                    <span class="w-fit bg-red-600/95 backdrop-blur-sm text-white text-[10px] font-black px-2.5 py-1 rounded-full shadow-md tracking-wider leading-none border border-white/15">
                                        -{{ max(1, $discountPercent) }}%
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Infos (identisch zur Shop-Hauptansicht) --}}
                        <div class="flex-1 flex flex-col px-3 pb-3 relative">
                            @php $originLabel = $product->getOriginBadgeLabel(); $originMode = $product->getOriginBadgeMode(); @endphp
                            @if($product->categories->isNotEmpty() || $originLabel)
                                <div class="flex flex-wrap items-center gap-1 mb-2">
                                    @if($originLabel)
                                        <span class="inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border {{ $originMode === 'made_in_germany' ? 'bg-amber-50/90 text-amber-900 border-amber-200/80' : 'bg-stone-50 text-stone-700 border-stone-200/80' }}">
                                            <span class="inline-flex flex-col w-3 h-2 rounded-[1.5px] overflow-hidden shrink-0 border border-black/15">
                                                <span class="h-1/3 w-full bg-black"></span>
                                                <span class="h-1/3 w-full bg-[#DD0000]"></span>
                                                <span class="h-1/3 w-full bg-[#FFCE00]"></span>
                                            </span>
                                            <span>{{ $originLabel }}</span>
                                        </span>
                                    @endif
                                    @foreach($product->categories->take(2) as $pCat)
                                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-gray-50 px-2 py-0.5 rounded-md">{{ $pCat->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <h3 class="text-lg font-serif font-bold {{ $isSoldOut ? 'text-gray-400' : 'text-gray-900 group-hover:text-[var(--theme-color)] transition-colors' }} mb-1.5 leading-tight line-clamp-2">
                                <a href="{{ $productUrl }}">
                                    {{ $product->name }}
                                </a>
                            </h3>

                            {{-- Sternebewertung --}}
                            @php
                                $ratingAvg = round((float) ($product->approved_reviews_avg ?? $product->average_rating ?? 0), 1);
                                $ratingCount = (int) ($product->approved_reviews_count ?? $product->review_count ?? 0);
                            @endphp
                            <a href="{{ $productUrl }}#kundenbewertungen-desktop"
                               class="inline-flex items-center gap-1.5 mb-2 group/rating w-fit">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3.5 h-3.5 shrink-0 {{ $i <= round($ratingAvg) ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-[11px] font-bold {{ $ratingCount > 0 ? 'text-gray-700' : 'text-gray-400' }}">
                                    @if($ratingCount > 0)
                                        {{ number_format($ratingAvg, 1, ',', '.') }} <span class="text-gray-400 font-medium">({{ $ratingCount }})</span>
                                    @else
                                        <span class="text-[10px] text-gray-400 font-medium">(0)</span>
                                    @endif
                                </span>
                            </a>

                            <div class="mt-auto pt-3 flex items-end justify-between">
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">
                                        @if($product->slug === 'geschenkgutschein' || ($product->price == 0 && $product->is_personalizable))
                                            Wunschbetrag
                                        @elseif($product->tierPrices->isNotEmpty())
                                            Ab
                                        @else
                                            Preis
                                        @endif
                                    </span>
                                    <div class="flex items-baseline gap-2">
                                        <span class="{{ $isSoldOut ? 'text-gray-400' : ($product->compare_at_price > $product->price ? 'text-red-600' : 'text-gray-900') }} font-bold text-xl font-serif whitespace-nowrap">
                                            @if($product->slug === 'geschenkgutschein' || ($product->price == 0 && $product->is_personalizable))
                                                ab 5,00&nbsp;€
                                            @else
                                                {{ number_format($product->gross_price / 100, 2, ',', '.') }}&nbsp;€
                                            @endif
                                        </span>
                                        @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                            <span class="text-xs text-gray-400 line-through font-medium whitespace-nowrap">
                                                {{ number_format($product->compare_at_price / 100, 2, ',', '.') }}&nbsp;€
                                            </span>
                                        @endif
                                    </div>
                                    @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                        <span class="text-[8px] text-gray-400 font-medium leading-tight mt-0.5">
                                            30-Tage-Bestpreis (§ 11 PAngV): {{ number_format($product->compare_at_price / 100, 2, ',', '.') }}&nbsp;€
                                        </span>
                                    @endif
                                    @if($product->base_price_info)
                                        <span class="text-[8px] text-gray-500 font-semibold leading-tight mt-0.5">
                                            Grundpreis: {{ $product->base_price_info }}
                                        </span>
                                    @endif
                                    <span class="text-[8px] text-gray-400 font-medium leading-tight mt-1">
                                        @if(shop_setting('is_small_business', false))
                                            Keine MwSt. gem. § 19 UStG · <a href="{{ route('versand') }}" target="_blank" class="hover:text-gray-600 underline">zzgl. Versand</a>
                                        @else
                                            inkl. MwSt. · <a href="{{ route('versand') }}" target="_blank" class="hover:text-gray-600 underline">zzgl. Versand</a>
                                        @endif
                                    </span>
                                </div>

                                <a href="{{ $productUrl }}" class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 transform group-hover:scale-110 {{ $isSoldOut ? 'bg-gray-100 text-gray-400' : 'bg-gray-900 text-white hover:bg-[var(--theme-color)] shadow-lg' }}">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
