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
        },
        scrollToReviews() {
            const el = this.$refs.leftScrollContainer;
            const target = document.getElementById('kundenbewertungen-desktop');
            if (window.innerWidth >= 1024 && el && target) {
                el.scrollTo({ top: Math.max(0, target.offsetTop - 24), behavior: 'smooth' });
            } else {
                const mobileTarget = document.getElementById('kundenbewertungen-mobile');
                if (mobileTarget) {
                    mobileTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }
     }"
     @scroll-to-reviews.window="scrollToReviews()"
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
        $primaryMeta = $product->resolveMainMediaMeta(null, $product->isAutoSeasonalMainImageEnabled());
        if (!empty($primaryMeta['url'])) {
            $displayGallery[] = [
                'type'            => $primaryMeta['type'] ?? 'image',
                'url'             => $primaryMeta['url'],
                'alt'             => $primaryMeta['label'] ?? $product->name,
                'is_ai_generated' => (bool) ($primaryMeta['is_ai_generated'] ?? true),
            ];
        }
        foreach ($product->media_gallery ?? [] as $m) {
            if (is_array($m) && !empty($m['path'])) {
                $url = \App\Models\Product\ProductTemplate::resolveImageUrl($m['path']);
                $alreadyIn = collect($displayGallery)->contains(fn($g) => $g['url'] === $url);
                if ($url && !$alreadyIn) {
                    $bindingLabel = $product->resolveMediaVariantBindingLabel($m['variant_binding'] ?? null);
                    $displayGallery[] = [
                        'type'            => $m['type'] ?? 'image',
                        'url'             => $url,
                        'alt'             => $m['alt'] ?? ($bindingLabel ? $product->name . ' – ' . $bindingLabel : $product->name),
                        'variant_label'   => $bindingLabel,
                        'is_ai_generated' => filter_var($m['is_ai_generated'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ];
                }
            }
        }
    @endphp
    <div class="space-y-4 sm:space-y-6"
         x-data="{
            galleryItems: @js($displayGallery),
            activeMedia: '{{ !empty($displayGallery[0]) ? $displayGallery[0]['url'] : '' }}',
            activeType: '{{ !empty($displayGallery[0]) ? ($displayGallery[0]['type'] ?? 'image') : 'image' }}',
            activeIsAi: {{ !empty($displayGallery[0]['is_ai_generated']) ? 'true' : 'false' }},
            activeAlt: @js(!empty($displayGallery[0]['alt']) ? $displayGallery[0]['alt'] : $product->name),

            // Zoom & Extra-Zoomfenster (identische Logik zum 3D Produktkonfigurator)
            stageZoom: 1.0,
            stagePanX: 0.0,
            stagePanY: 0.0,
            _zoomFocusX: null,
            _zoomFocusY: null,
            isDraggingMinimap: false,
            _minimapOffsetX: 0,
            _minimapOffsetY: 0,
            isPanningStage: false,
            _panStartX: 0,
            _panStartY: 0,
            _panOriginX: 0,
            _panOriginY: 0,
            _justDraggedStage: false,

            // Lightbox-Großansicht
            lightboxOpen: false,
            lightboxIndex: 0,

            setStageZoom(val) {
                const z = Math.max(1.0, Math.min(3.0, Number(val) || 1.0));
                if (z <= 1.01) {
                    this.stageZoom = 1.0;
                    this.stagePanX = 0.0;
                    this.stagePanY = 0.0;
                    this._zoomFocusX = null;
                    this._zoomFocusY = null;
                    return;
                }
                if (this._zoomFocusX === null || this._zoomFocusY === null) {
                    this._zoomFocusX = 50;
                    this._zoomFocusY = 50;
                }
                const maxPan = 50 * (z - 1);
                this.stageZoom = Number(z.toFixed(3));
                this.stagePanX = Number(Math.max(-maxPan, Math.min(maxPan, (50 - this._zoomFocusX) * z)).toFixed(2));
                this.stagePanY = Number(Math.max(-maxPan, Math.min(maxPan, (50 - this._zoomFocusY) * z)).toFixed(2));
            },
            stepStageZoom(delta) {
                const next = Math.max(1.0, Math.min(3.0, Number((Number(this.stageZoom || 1.0) + Number(delta || 0)).toFixed(2))));
                this.setStageZoom(next);
            },
            resetStageZoom() {
                this.setStageZoom(1.0);
            },
            getMinimapViewportStyle() {
                const z = Math.max(1.0, Number(this.stageZoom || 1.0));
                const vw = 100 / z;
                const vh = 100 / z;
                const left = Math.max(0, Math.min(100 - vw, 50 - (50 + Number(this.stagePanX || 0)) / z));
                const top = Math.max(0, Math.min(100 - vh, 50 - (50 + Number(this.stagePanY || 0)) / z));
                return `left: ${left.toFixed(2)}%; top: ${top.toFixed(2)}%; width: ${vw.toFixed(2)}%; height: ${vh.toFixed(2)}%;`;
            },
            startMinimapDrag(event) {
                if (this.stageZoom <= 1.01) return;
                const minimap = this.$refs.zoomMinimap;
                if (!minimap) return;
                const rect = minimap.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const mx = ((clientX - rect.left) / Math.max(1, rect.width)) * 100;
                const my = ((clientY - rect.top) / Math.max(1, rect.height)) * 100;
                const z = Math.max(1.01, Number(this.stageZoom || 1.01));
                const cx = 50 - Number(this.stagePanX || 0) / z;
                const cy = 50 - Number(this.stagePanY || 0) / z;
                const halfW = 50 / z;
                const halfH = 50 / z;
                this.isDraggingMinimap = true;
                if (Math.abs(mx - cx) <= halfW && Math.abs(my - cy) <= halfH) {
                    this._minimapOffsetX = mx - cx;
                    this._minimapOffsetY = my - cy;
                } else {
                    this._minimapOffsetX = 0;
                    this._minimapOffsetY = 0;
                    this.updateMinimapFromEvent(event);
                }
            },
            updateMinimapFromEvent(event) {
                if (!this.isDraggingMinimap || this.stageZoom <= 1.01) return;
                const minimap = this.$refs.zoomMinimap;
                if (!minimap) return;
                const rect = minimap.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const mx = ((clientX - rect.left) / Math.max(1, rect.width)) * 100;
                const my = ((clientY - rect.top) / Math.max(1, rect.height)) * 100;
                const targetCx = mx - (this._minimapOffsetX || 0);
                const targetCy = my - (this._minimapOffsetY || 0);
                const z = Math.max(1.01, Number(this.stageZoom || 1.01));
                const maxPan = 50 * (z - 1);
                this.stagePanX = Number(Math.max(-maxPan, Math.min(maxPan, (50 - targetCx) * z)).toFixed(2));
                this.stagePanY = Number(Math.max(-maxPan, Math.min(maxPan, (50 - targetCy) * z)).toFixed(2));
                this._zoomFocusX = Number((50 - this.stagePanX / z).toFixed(2));
                this._zoomFocusY = Number((50 - this.stagePanY / z).toFixed(2));
            },
            startStagePan(event) {
                if (this.activeType !== 'image' || this.stageZoom <= 1.01) return;
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                this.isPanningStage = true;
                this._panStartX = clientX;
                this._panStartY = clientY;
                this._panOriginX = this.stagePanX;
                this._panOriginY = this.stagePanY;
                this._justDraggedStage = false;
            },
            handleWindowMove(event) {
                if (this.isDraggingMinimap) {
                    this.updateMinimapFromEvent(event);
                    return;
                }
                if (!this.isPanningStage || this.stageZoom <= 1.01) return;
                const stage = this.$refs.mediaStage;
                if (!stage) return;
                const rect = stage.getBoundingClientRect();
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;
                const dx = clientX - this._panStartX;
                const dy = clientY - this._panStartY;
                if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                    this._justDraggedStage = true;
                }
                const z = Math.max(1.01, Number(this.stageZoom || 1.01));
                const maxPan = 50 * (z - 1);
                const pctDx = (dx / Math.max(1, rect.width)) * 100;
                const pctDy = (dy / Math.max(1, rect.height)) * 100;
                this.stagePanX = Number(Math.max(-maxPan, Math.min(maxPan, this._panOriginX + pctDx)).toFixed(2));
                this.stagePanY = Number(Math.max(-maxPan, Math.min(maxPan, this._panOriginY + pctDy)).toFixed(2));
                this._zoomFocusX = Number((50 - this.stagePanX / z).toFixed(2));
                this._zoomFocusY = Number((50 - this.stagePanY / z).toFixed(2));
            },
            handleWindowUp() {
                if (this.isDraggingMinimap) {
                    this.isDraggingMinimap = false;
                }
                if (this.isPanningStage) {
                    this.isPanningStage = false;
                    setTimeout(() => { this._justDraggedStage = false; }, 80);
                }
            },
            getImageSlides() {
                const slides = (this.galleryItems || []).filter(item => (item.type || 'image') === 'image' && item.url);
                if (this.activeType === 'image' && this.activeMedia && !slides.some(s => s.url === this.activeMedia)) {
                    return [{ type: 'image', url: this.activeMedia, alt: this.activeAlt || @js($product->name) }, ...slides];
                }
                return slides;
            },
            openLightbox() {
                if (this.activeType !== 'image' || !this.activeMedia) return;
                const slides = this.getImageSlides();
                const idx = slides.findIndex(s => s.url === this.activeMedia);
                this.lightboxIndex = idx >= 0 ? idx : 0;
                this.lightboxOpen = true;
            },
            closeLightbox() {
                this.lightboxOpen = false;
            },
            nextSlide() {
                const slides = this.getImageSlides();
                if (!slides.length) return;
                this.lightboxIndex = (this.lightboxIndex + 1) % slides.length;
            },
            prevSlide() {
                const slides = this.getImageSlides();
                if (!slides.length) return;
                this.lightboxIndex = (this.lightboxIndex - 1 + slides.length) % slides.length;
            }
         }"
         @mousemove.window="handleWindowMove($event)"
         @touchmove.window="handleWindowMove($event)"
         @mouseup.window="handleWindowUp()"
         @touchend.window="handleWindowUp()"
         @keydown.escape.window="if (lightboxOpen) closeLightbox()"
         @keydown.arrow-right.window="if (lightboxOpen) nextSlide()"
         @keydown.arrow-left.window="if (lightboxOpen) prevSlide()"
         @variant-selected.window="if ($event.detail && $event.detail.imageUrl) { activeMedia = $event.detail.imageUrl; activeType = 'image'; activeIsAi = !!$event.detail.imageIsAi; if ($event.detail.imageLabel) { activeAlt = @js($product->name . ' ') + $event.detail.imageLabel; } resetStageZoom(); }">

        @if(!empty($displayGallery))
        {{-- Zoom-Regler über die volle Breite (identisch zum 3D Produktkonfigurator) --}}
        <div x-show="activeType === 'image'"
             x-cloak
             @mousedown.stop
             @touchstart.stop
             class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl border w-full transition-all bg-white/95 border-gray-200/80 text-gray-800 shadow-2xs">
            <button type="button"
                    @click.stop="stepStageZoom(-0.15)"
                    :disabled="stageZoom <= 1.01"
                    :class="stageZoom > 1.01 ? 'hover:text-primary active:scale-90 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                    class="w-6 h-6 rounded-full flex items-center justify-center transition-all select-none shrink-0"
                    title="Herauszoomen">
                <x-heroicon-m-magnifying-glass-minus class="w-4 h-4 text-[#C5A059]" />
            </button>
            <input type="range"
                   min="1"
                   max="3"
                   step="0.02"
                   :value="stageZoom"
                   @input.stop="setStageZoom($event.target.value)"
                   aria-label="Produktbild zoomen (100% bis 300%)"
                   class="flex-1 h-1.5 rounded-lg appearance-none cursor-pointer accent-[#C5A059] bg-gray-200"
                   title="Produktbild zoomen (100% - 300%)">
            <button type="button"
                    @click.stop="stepStageZoom(0.15)"
                    :disabled="stageZoom >= 2.99"
                    :class="stageZoom < 2.99 ? 'hover:text-primary active:scale-90 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                    class="w-6 h-6 rounded-full flex items-center justify-center transition-all select-none shrink-0"
                    title="Heranzoomen">
                <x-heroicon-m-magnifying-glass-plus class="w-4 h-4 text-[#C5A059]" />
            </button>
            <button type="button"
                    @click.stop="resetStageZoom()"
                    :class="stageZoom > 1.01 ? 'text-[#9A7B3E] hover:text-primary cursor-pointer' : 'text-gray-400 cursor-default'"
                    class="min-w-[38px] text-right text-[10px] font-black tracking-tight transition-colors select-none shrink-0"
                    title="Zoom zurücksetzen (100%)">
                <span x-text="Math.round(stageZoom * 100) + '%'">100%</span>
            </button>
        </div>
        @endif

        {{-- Hauptbild / Video Container --}}
        <div x-ref="mediaStage"
             @mousedown="startStagePan($event)"
             @touchstart="startStagePan($event)"
             @click="if (activeType === 'image' && stageZoom <= 1.01 && !_justDraggedStage) openLightbox()"
             :class="activeType === 'image' ? (stageZoom > 1.01 ? 'cursor-grab active:cursor-grabbing touch-none' : 'cursor-zoom-in') : ''"
             class="aspect-square bg-gray-50 rounded-2xl overflow-hidden border border-gray-100 shadow-sm relative group select-none">

            @if(!empty($displayGallery))
                {{-- Bild Anzeige mit synchroner Configurator Zoom-/Pan-Transformation --}}
                <div x-show="activeType === 'image'"
                     class="w-full h-full"
                     :style="`transform-origin: 50% 50%; transform: translate3d(${stagePanX}%, ${stagePanY}%, 0) scale(${stageZoom}); -webkit-transform: translate3d(${stagePanX}%, ${stagePanY}%, 0) scale(${stageZoom});`">
                    <img :src="activeMedia"
                         :alt="activeAlt"
                         :data-is-ai-generated="activeIsAi ? 'true' : null"
                         fetchpriority="high"
                         decoding="async"
                         draggable="false"
                         :class="stageZoom <= 1.01 ? 'transition-transform duration-700 lg:group-hover:scale-105' : 'transition-none'"
                         class="w-full h-full object-cover pointer-events-none"
                         alt="{{ !empty($displayGallery[0]['alt']) ? $displayGallery[0]['alt'] : $product->name }}">
                </div>

                {{-- Dezentes Hover-"Plus"-Icon für Großansicht (verschwindet beim Heranzoomen) --}}
                <div x-show="activeType === 'image' && stageZoom <= 1.01"
                     x-cloak
                     class="hidden lg:flex absolute inset-0 z-10 items-center justify-center opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-300 pointer-events-none">
                    <button type="button"
                            @click.stop="openLightbox()"
                            aria-label="Produktbild in Großansicht öffnen"
                            title="In Großansicht anzeigen"
                            class="pointer-events-auto w-11 h-11 rounded-full bg-white/80 hover:bg-white text-gray-700 hover:text-[#C5A059] border border-gray-200/80 shadow-sm backdrop-blur-sm flex items-center justify-center transition-all duration-300 group-hover:scale-105 cursor-zoom-in">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                </div>

                {{-- Extra-Zoomfenster (Navigator / Minimap) oben rechts beim Heranzoomen --}}
                <div x-show="activeType === 'image' && stageZoom > 1.01"
                     x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-90"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-90"
                     x-ref="zoomMinimap"
                     @mousedown.stop.prevent="startMinimapDrag($event)"
                     @touchstart.stop.prevent="startMinimapDrag($event)"
                     @click.stop
                     title="Ausschnitt im Zoomfenster verschieben"
                     class="absolute top-2.5 right-2.5 z-40 w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden shadow-[0_8px_24px_rgba(0,0,0,0.28)] border-2 border-[#C5A059] bg-white/95 backdrop-blur-md cursor-grab active:cursor-grabbing select-none touch-none pointer-events-auto">
                    <img :src="activeMedia" class="absolute inset-0 w-full h-full object-cover pointer-events-none" alt="">
                    <div class="absolute z-[5] border-2 border-[#C5A059] bg-[#C5A059]/15 rounded-[3px] pointer-events-none shadow-[0_0_0_999px_rgba(0,0,0,0.42)]"
                         :style="getMinimapViewportStyle()">
                        <div class="absolute inset-0 flex items-center justify-center opacity-75">
                            <div class="w-1.5 h-1.5 rounded-full bg-[#C5A059] shadow-sm"></div>
                        </div>
                    </div>
                </div>

                {{-- KI-Generiert Hinweis unten rechts --}}
                <span x-show="activeType === 'image' && activeIsAi"
                      x-cloak
                      style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                      class="pointer-events-none select-none absolute bottom-2.5 right-2.5 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                    KI-generiert
                </span>

                {{-- Video Anzeige (nur mounten wenn tatsächlich ein Video ausgewählt ist, verhindert kaputten JPEG-Decode im <video>-Tag) --}}
                <template x-if="activeType === 'video' && activeMedia">
                    <video :src="activeMedia"
                           poster="{{ !empty($displayGallery[0]['url']) ? $displayGallery[0]['url'] : '' }}"
                           preload="metadata"
                           playsinline
                           controls
                           class="w-full h-full object-cover">
                    </video>
                </template>
            @else
                <div class="flex items-center justify-center h-full text-gray-300">
                    <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            @endif

            {{-- Badges --}}
            <div class="absolute top-4 left-4 z-20 pointer-events-none flex flex-col items-start gap-1.5">
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
                <button type="button" aria-label="Vorherige Vorschaubilder" @click="scroll('left')" class="hidden lg:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md items-center justify-center text-gray-600 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all opacity-0 group-hover/slider:opacity-100 -translate-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M15 19l-7-7 7-7" /></svg>
                </button>

                {{-- Container für Thumbnails --}}
                <div x-ref="thumbnailContainer" class="flex gap-4 overflow-x-auto pb-2 scrollbar-hide snap-x no-scrollbar" style="scrollbar-width: none; -ms-overflow-style: none;">
                    @foreach($displayGallery as $media)
                        <div class="snap-start shrink-0" style="width: calc(20% - 13px);">
                            <button type="button"
                                    aria-label="Vorschau: {{ $media['alt'] ?? $product->name }}"
                                    @click="activeMedia = '{{ $media['url'] }}'; activeType = '{{ $media['type'] ?? 'image' }}'; activeIsAi = {{ !empty($media['is_ai_generated']) ? 'true' : 'false' }}; activeAlt = @js($media['alt'] ?? $product->name); resetStageZoom()"
                                    class="aspect-square w-full rounded-lg overflow-hidden border-2 transition-all block relative cursor-pointer"
                                    :class="activeMedia === '{{ $media['url'] }}' ? 'border-[var(--theme-color)] ring-2 ring-[var(--theme-color-20)]' : 'border-transparent hover:border-gray-300'">

                                @if(isset($media['type']) && $media['type'] === 'video')
                                    <div class="w-full h-full bg-gray-900 flex items-center justify-center text-white">
                                        <video src="{{ $media['url'] }}" preload="metadata" muted playsinline class="absolute inset-0 w-full h-full object-cover opacity-50"></video>
                                        <svg class="w-6 h-6 relative z-10" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z" /></svg>
                                    </div>
                                @else
                                    <img src="{{ $media['url'] }}"
                                         alt="{{ $media['alt'] ?? $product->name }}"
                                         loading="lazy"
                                         decoding="async"
                                         @if(!empty($media['is_ai_generated'])) data-is-ai-generated="true" @endif
                                         class="w-full h-full object-cover">
                                @endif
                            </button>
                        </div>
                    @endforeach
                </div>

                {{-- Rechter Pfeil (Nur Desktop sichtbar) --}}
                <button type="button" aria-label="Nächste Vorschaubilder" @click="scroll('right')" class="hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-white/90 backdrop-blur border border-gray-200 rounded-full shadow-md items-center justify-center text-gray-600 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all opacity-0 group-hover/slider:opacity-100 translate-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        @endif

        {{-- Lightbox Modal (Großansicht im selben Fenster, Light-Mode) --}}
        <template x-teleport="body">
            <div x-show="lightboxOpen"
                 x-cloak
                 style="display: none;"
                 x-transition.opacity.duration.200ms
                 @click="closeLightbox()"
                 class="fixed inset-0 z-[2147483647] flex items-center justify-center p-4 sm:p-6 bg-[#262016]/55 backdrop-blur-md">
                <div @click.stop
                     x-show="lightboxOpen"
                     x-transition:enter="transition ease-out duration-250"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="relative w-full max-w-3xl rounded-3xl overflow-hidden shadow-2xl border border-[#e6d7bc] bg-[#fdfbf7] text-gray-900 p-4 sm:p-6">

                    <button type="button"
                            @click="closeLightbox()"
                            aria-label="Großansicht schließen"
                            title="Schließen"
                            class="absolute top-3.5 right-3.5 z-20 w-9 h-9 rounded-full flex items-center justify-center bg-white hover:bg-[#f7f1e6] text-gray-600 hover:text-gray-900 border border-[#e6d7bc] shadow-sm transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <template x-if="lightboxOpen && getImageSlides().length > 0">
                        <div class="relative flex flex-col items-center">
                            <div class="relative w-full flex items-center justify-center bg-white rounded-2xl border border-[#eadcc4]/80 overflow-hidden p-2 sm:p-4">
                                <button type="button"
                                        x-show="getImageSlides().length > 1"
                                        @click.stop="prevSlide()"
                                        aria-label="Vorheriges Bild"
                                        class="absolute left-2.5 sm:left-4 z-10 w-10 h-10 rounded-full bg-white/90 hover:bg-white text-gray-700 hover:text-[#C5A059] border border-gray-200 shadow-sm flex items-center justify-center transition-colors cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                                </button>

                                <img :src="(getImageSlides()[lightboxIndex] || {}).url || activeMedia"
                                     :alt="(getImageSlides()[lightboxIndex] || {}).alt || activeAlt"
                                     class="max-h-[75vh] w-auto max-w-full rounded-xl object-contain select-none">

                                <button type="button"
                                        x-show="getImageSlides().length > 1"
                                        @click.stop="nextSlide()"
                                        aria-label="Nächstes Bild"
                                        class="absolute right-2.5 sm:right-4 z-10 w-10 h-10 rounded-full bg-white/90 hover:bg-white text-gray-700 hover:text-[#C5A059] border border-gray-200 shadow-sm flex items-center justify-center transition-colors cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>

                            <div class="mt-3 flex items-center justify-between w-full gap-4 px-1">
                                <p class="text-xs sm:text-sm font-medium text-gray-700 truncate" x-text="(getImageSlides()[lightboxIndex] || {}).alt || activeAlt"></p>
                                <span x-show="getImageSlides().length > 1"
                                      class="text-xs font-bold text-[#9A7B3E] shrink-0"
                                      x-text="(lightboxIndex + 1) + ' / ' + getImageSlides().length"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

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
            gpsrOpen: false,
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

            {{-- Produktsicherheit & Herstellerangaben (GPSR - Verordnung (EU) 2023/988) --}}
            @if(($product->type ?? 'physical') === 'physical')
                <div class="bg-gray-50/70 rounded-2xl border border-gray-100 w-full block clear-both overflow-hidden transition-all">
                    <button type="button"
                            @click="gpsrOpen = !gpsrOpen; refreshLeftScroll()"
                            class="w-full p-5 sm:px-6 flex items-center justify-between gap-4 text-left hover:bg-gray-100/60 transition-colors cursor-pointer group">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <h4 class="text-sm font-bold uppercase text-gray-900 tracking-wider group-hover:text-[var(--theme-color)] transition-colors">
                                Produktsicherheit & Hersteller
                            </h4>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-white text-gray-500 border border-gray-200">
                                GPSR
                            </span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 group-hover:text-[var(--theme-color)] shrink-0 transition-colors">
                            <span x-text="gpsrOpen ? 'Ausblenden' : 'Anzeigen'">Anzeigen</span>
                            <svg class="w-4 h-4 transition-transform duration-300" :class="gpsrOpen ? 'rotate-180 text-[var(--theme-color)]' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>

                    <div x-show="gpsrOpen"
                         x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="px-6 pb-6 pt-3 border-t border-gray-200/60 text-xs text-gray-600 space-y-3 leading-relaxed">
                        <div>
                            <p class="font-bold text-gray-900 mb-0.5">Hersteller & Verantwortliche Person in der EU (Art. 19 GPSR):</p>
                            <p>
                                {{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }} – Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                                {{ shop_setting('company_street') ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '')) : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('company_city') ? trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city')) : shop_setting('owner_city', '38518 Gifhorn') }}, Deutschland<br>
                                E-Mail: <a href="mailto:{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}" class="text-[var(--theme-color)] underline">{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}</a> | Modell-/Artikel-Nr.: <span class="font-mono text-gray-800">{{ $product->sku ?: ('MSF-' . $product->id) }}</span>
                            </p>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 mb-0.5">Sicherheits- & Pflegehinweise:</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Dekorations-, Erinnerungs- bzw. Gebrauchsartikel (kein Kinderspielzeug).</li>
                                <li>Bei Artikeln mit Schlüsselringen/Ösen: Achtung, verschluckbare Kleinteile (Erstickungsgefahr für Kinder unter 36 Monaten).</li>
                                <li>Bei Glas- und Schieferartikeln: Vor Stürzen schützen (Schnittgefahr bei Glas-/Schieferbruch); Kristallglas nicht dauerhaft gebündelter Sonneneinstrahlung nahe brennbaren Materialien aussetzen.</li>
                                <li>Pflege: Mit einem weichen, leicht angefeuchteten Tuch ohne aggressive Reinigungsmittel abwischen (nicht spülmaschinengeeignet).</li>
                            </ul>
                        </div>
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

    {{-- Fließender Fade-Übergang unten + mittiger Scroll-Button mit Icon darunter ohne Border (Desktop) --}}
    <div x-show="canScrollDown"
         x-transition.opacity.duration.300ms
         class="hidden lg:flex pointer-events-none absolute bottom-0 inset-x-0 h-32 bg-gradient-to-t from-white via-white/95 to-transparent z-20 items-end justify-center pb-2">
        <button type="button"
                @click="scrollDownStep()"
                aria-label="Weiter nach unten scrollen"
                title="Weiter nach unten scrollen"
                class="pointer-events-auto group flex flex-col items-center justify-center gap-1 px-6 py-2 bg-transparent text-gray-800 hover:text-[var(--theme-color)] transition-all duration-300 cursor-pointer">
            <span class="text-xs font-extrabold uppercase tracking-wider text-center">Mehr Details & Bewertungen</span>
            <svg class="w-6 h-6 text-[var(--theme-color)] transition-transform duration-300 group-hover:translate-y-1 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>

</div>
