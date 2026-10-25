{{-- LINKE SEITE: Medien Galerie, Details & Bewertungen --}}
<div class="w-full lg:sticky lg:top-24 lg:self-start relative"
     x-data="{
        canScrollDown: true,
        canScrollUp: false,
        checkScroll() {
            const el = this.$refs.leftScrollContainer;
            if (!el) return;
            this.canScrollUp = el.scrollTop > 16;
            this.canScrollDown = (el.scrollHeight - el.scrollTop - el.clientHeight) > 20;
        },
        scrollDownStep() {
            const el = this.$refs.leftScrollContainer;
            if (!el) return;
            el.scrollBy({ top: Math.round(el.clientHeight * 0.55), behavior: 'smooth' });
        }
     }"
     x-init="
        $nextTick(() => {
            checkScroll();
            setTimeout(() => checkScroll(), 400);
            if (window.ResizeObserver && $refs.leftScrollContainer) {
                const ro = new ResizeObserver(() => checkScroll());
                ro.observe($refs.leftScrollContainer);
                if ($refs.leftScrollContent) ro.observe($refs.leftScrollContent);
            }
        });
     "
     @resize.window.passive="checkScroll()">

    {{-- Sanfter Fade-Übergang oben (wenn nach unten gescrollt wurde) --}}
    <div x-show="canScrollUp"
         x-cloak
         x-transition.opacity.duration.300ms
         class="hidden lg:block pointer-events-none absolute top-0 inset-x-0 h-10 bg-gradient-to-b from-white via-white/75 to-transparent z-20">
    </div>

    {{-- Scrollbarer Container (Desktop) --}}
    <div x-ref="leftScrollContainer"
         @scroll.passive="checkScroll()"
         class="w-full lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto no-scrollbar lg:pb-20">

        <div x-ref="leftScrollContent">

    {{-- ========================================== --}}
    {{-- 1. MEDIEN GALERIE (Immer sichtbar) --}}
    {{-- ========================================== --}}
    @php
        $displayGallery = [];
        if ($product->isAutoSeasonalMainImageEnabled()) {
            $primaryMeta = $product->resolveMainImageMeta(null, true);
            if (!empty($primaryMeta['url'])) {
                $displayGallery[] = [
                    'type'            => 'image',
                    'url'             => $primaryMeta['url'],
                    'alt'             => $primaryMeta['label'] ?? $product->name,
                    'is_ai_generated' => (bool) ($primaryMeta['is_ai_generated'] ?? false),
                ];
            }
        }
        foreach ($product->media_gallery ?? [] as $m) {
            if (is_array($m) && !empty($m['path'])) {
                $url = \App\Models\Product\ProductTemplate::resolveImageUrl($m['path']);
                $alreadyIn = collect($displayGallery)->contains(fn($g) => $g['url'] === $url);
                if ($url && !$alreadyIn) {
                    $displayGallery[] = [
                        'type'            => $m['type'] ?? 'image',
                        'url'             => $url,
                        'alt'             => $m['alt'] ?? $product->name,
                        'is_ai_generated' => filter_var($m['is_ai_generated'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];
                }
            }
        }
    @endphp
    <div class="space-y-4 sm:space-y-6"
         x-data="{
            activeMedia: '{{ !empty($displayGallery[0]) ? $displayGallery[0]['url'] : '' }}',
            activeType: '{{ !empty($displayGallery[0]) ? ($displayGallery[0]['type'] ?? 'image') : 'image' }}',
            activeIsAi: {{ !empty($displayGallery[0]['is_ai_generated']) ? 'true' : 'false' }}
         }">

        {{-- Hauptbild / Video Container --}}
        <div class="aspect-square bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 shadow-sm relative group">

            @if(!empty($displayGallery))
                {{-- Bild Anzeige --}}
                <img x-show="activeType === 'image'"
                     :src="activeMedia"
                     :data-is-ai-generated="activeIsAi ? 'true' : null"
                     class="w-full h-full object-cover transition-transform duration-700 lg:group-hover:scale-105"
                     alt="{{ $product->name }}">

                {{-- KI-Generiert Hinweis unten rechts --}}
                <span x-show="activeType === 'image' && activeIsAi"
                      x-cloak
                      class="pointer-events-none select-none absolute bottom-3 right-3 z-20 bg-black/55 backdrop-blur-sm text-white/90 text-[9px] font-semibold tracking-wider px-2 py-0.5 rounded-md border border-white/15 shadow-sm">
                    KI-generiert
                </span>

                {{-- Video Anzeige --}}
                <video x-show="activeType === 'video'"
                       :src="activeMedia"
                       controls
                       class="w-full h-full object-cover">
                </video>
            @else
                <div class="flex items-center justify-center h-full text-gray-300">
                    <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            @endif

            {{-- Badges --}}
            <div class="absolute top-4 left-4 flex flex-col items-start gap-1.5">
                @if($product->compare_at_price > $product->price)
                    <span class="w-fit bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">Sale</span>
                @endif
                @if($product->created_at->diffInDays() < 30)
                    <span class="w-fit bg-[var(--theme-color)] text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">Neu</span>
                @endif
                @if($product->type === 'digital')
                    <span class="w-fit bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">Digital</span>
                @endif
            </div>

        </div>

        {{-- Thumbnails Slider --}}
        @if(count($displayGallery) > 1)
            <div class="relative group/slider mt-4"
                 x-data="{
                    scroll(direction) {
                        const container = this.$refs.thumbnailContainer;
                        const scrollAmount = container.offsetWidth / 2;
                        container.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
                    }
                 }">

                {{-- Linker Pfeil (Nur Desktop sichtbar) --}}
                <button @click="scroll('left')" class="hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md items-center justify-center text-gray-600 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all opacity-0 group-hover/slider:opacity-100 -translate-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M15 19l-7-7 7-7" /></svg>
                </button>

                {{-- Container für Thumbnails --}}
                <div x-ref="thumbnailContainer" class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide snap-x no-scrollbar" style="scrollbar-width: none; -ms-overflow-style: none;">
                    @foreach($displayGallery as $media)
                        <div class="snap-start shrink-0" style="width: calc(20% - 13px);">
                            <button @click="activeMedia = '{{ $media['url'] }}'; activeType = '{{ $media['type'] ?? 'image' }}'; activeIsAi = {{ !empty($media['is_ai_generated']) ? 'true' : 'false' }}"
                                    class="aspect-square w-full rounded-lg overflow-hidden border-2 transition-all block relative"
                                    :class="activeMedia === '{{ $media['url'] }}' ? 'border-[var(--theme-color)] ring-2 ring-[var(--theme-color-20)]' : 'border-transparent hover:border-gray-300'">

                                @if(isset($media['type']) && $media['type'] === 'video')
                                    <div class="w-full h-full bg-gray-900 flex items-center justify-center text-white">
                                        <video src="{{ $media['url'] }}" class="absolute inset-0 w-full h-full object-cover opacity-50"></video>
                                        <svg class="w-6 h-6 relative z-10" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                                    </div>
                                @else
                                    <img src="{{ $media['url'] }}"
                                         @if(!empty($media['is_ai_generated'])) data-is-ai-generated="true" @endif
                                         class="w-full h-full object-cover">
                                @endif
                            </button>
                        </div>
                    @endforeach
                </div>

                {{-- Rechter Pfeil (Nur Desktop sichtbar) --}}
                <button @click="scroll('right')" class="hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md items-center justify-center text-gray-600 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all opacity-0 group-hover/slider:opacity-100 translate-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        @endif

        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    </div>

    {{-- ========================================== --}}
    {{-- 2. PRODUKTDETAILS & BEWERTUNGEN (NUR AUF DESKTOP) --}}
    {{-- Auf Mobile wird dieser Teil in der right.blade.php ganz unten angezeigt --}}
    {{-- ========================================== --}}
    @php
        $rawDescription = trim((string) $this->product->description);
        $sentences = preg_split('/(?<=[.!?])\s+/u', $rawDescription, 3, PREG_SPLIT_NO_EMPTY);
        $teaserDescription = isset($sentences[0], $sentences[1])
            ? $sentences[0] . ' ' . $sentences[1]
            : \Illuminate\Support\Str::limit($rawDescription, 180);
        $hasMoreDescription = mb_strlen($rawDescription) > mb_strlen($teaserDescription) + 5;
        $validAttributes = array_filter($this->product->attributes ?? [], fn($v) => !empty($v));
    @endphp
    <div class="hidden lg:flex flex-col mt-8 w-full"
         x-data="{
            descOpen: false,
            propsOpen: false,
            refreshLeftScroll() {
                $nextTick(() => {
                    if (typeof checkScroll === 'function') checkScroll();
                    setTimeout(() => { if (typeof checkScroll === 'function') checkScroll(); }, 250);
                });
            }
         }">
        <div class="border-t border-gray-100 pt-6 w-full block clear-both space-y-4">

            {{-- Produktbeschreibung (Standardmäßig zugeklappt mit 2-Sätze-Teaser) --}}
            @if($rawDescription !== '')
                <div class="bg-gray-50/70 rounded-2xl p-6 border border-gray-100 w-full block transition-all">
                    <button type="button"
                            @click="descOpen = !descOpen; refreshLeftScroll()"
                            class="w-full flex items-center justify-between gap-4 text-left group cursor-pointer">
                        <h3 class="font-serif text-xl font-bold text-gray-900 group-hover:text-[var(--theme-color)] transition-colors">
                            Beschreibung
                        </h3>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[var(--theme-color)] shrink-0">
                            <span x-text="descOpen ? 'Weniger anzeigen' : 'Weiterlesen'">Weiterlesen</span>
                            <svg class="w-4 h-4 transition-transform duration-300" :class="descOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>

                    {{-- Teaser (erste 2 Sätze) wenn zugeklappt --}}
                    <div x-show="!descOpen"
                         @click="descOpen = true; refreshLeftScroll()"
                         class="mt-3 text-gray-600 text-sm leading-relaxed break-words line-clamp-3 cursor-pointer">
                        {{ $teaserDescription }}
                    </div>

                    {{-- Volltext wenn aufgeklappt --}}
                    <div x-show="descOpen"
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="mt-4 pt-3 border-t border-gray-200/60 text-gray-600 leading-relaxed break-words text-sm whitespace-pre-line">
                        {!! nl2br(e($rawDescription)) !!}
                    </div>
                </div>
            @endif

            {{-- Eigenschaften (Standardmäßig zugeklappt) --}}
            @if(!empty($validAttributes))
                <div class="bg-gray-50/70 rounded-2xl border border-gray-100 w-full block clear-both overflow-hidden transition-all">
                    <button type="button"
                            @click="propsOpen = !propsOpen; refreshLeftScroll()"
                            class="w-full p-5 sm:px-6 flex items-center justify-between gap-4 text-left hover:bg-gray-100/60 transition-colors cursor-pointer group">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <h4 class="text-sm font-bold uppercase text-gray-900 tracking-wider group-hover:text-[var(--theme-color)] transition-colors">
                                Eigenschaften
                            </h4>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-white text-gray-500 border border-gray-200">
                                {{ count($validAttributes) }} Details
                            </span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 group-hover:text-[var(--theme-color)] shrink-0 transition-colors">
                            <span x-text="propsOpen ? 'Ausblenden' : 'Anzeigen'">Anzeigen</span>
                            <svg class="w-4 h-4 transition-transform duration-300" :class="propsOpen ? 'rotate-180 text-[var(--theme-color)]' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>

                    <div x-show="propsOpen"
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="px-6 pb-6 pt-2 border-t border-gray-200/60">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm w-full">
                            @foreach($validAttributes as $key => $val)
                                <div class="flex flex-col border-b border-gray-200/70 pb-2.5 sm:border-0 sm:pb-0 w-full">
                                    <dt class="text-gray-500 text-xs mb-0.5">{{ $key }}</dt>
                                    <dd class="font-bold text-gray-900 break-words">{{ $val }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            @endif

        </div>

        {{-- Kundenbewertungen (Desktop Ansicht - rückt durch eingeklappte Details direkt nach oben) --}}
        <div id="kundenbewertungen-desktop" class="mt-6 scroll-mt-24 w-full block clear-both">
            <livewire:shop.product.product-reviews :product="$product" />
        </div>
    </div>

        </div>
    </div>

    {{-- Fließender Fade-Übergang unten + deutlich sichtbarer, kräftiger Scroll-Button (Desktop) --}}
    <div x-show="canScrollDown"
         x-transition.opacity.duration.300ms
         class="hidden lg:flex pointer-events-none absolute bottom-0 inset-x-0 h-32 bg-gradient-to-t from-white via-white/90 to-transparent z-20 items-end justify-center pb-3">
        <button type="button"
                @click="scrollDownStep()"
                aria-label="Weiter nach unten scrollen"
                title="Weiter nach unten scrollen"
                class="pointer-events-auto group inline-flex items-center gap-2.5 px-5 py-2.5 rounded-full bg-white hover:bg-[var(--theme-color)] text-gray-900 hover:text-white border-2 border-[var(--theme-color)] shadow-lg shadow-gray-900/15 hover:shadow-xl transition-all duration-300 cursor-pointer ring-4 ring-white/90">
            <span class="text-xs font-extrabold uppercase tracking-wider">Mehr Details & Bewertungen</span>
            <span class="w-7 h-7 rounded-full bg-[var(--theme-color-10)] group-hover:bg-white/20 flex items-center justify-center text-[var(--theme-color)] group-hover:text-white transition-colors">
                <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </span>
        </button>
    </div>

</div>
