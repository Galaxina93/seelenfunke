@php
    $inCockpit = (bool) ($inCockpit ?? false);
    $targetItem = $targetItem ?? ($previewItem ?? ($this->previewItem ?? ($selectedOrder?->items?->first())));
    $effectiveExportSide = (string) ($exportSide ?? ($this->laserCockpitSide ?? 'front'));
    if (!in_array($effectiveExportSide, ['front', 'back'], true)) {
        $effectiveExportSide = 'front';
    }
    $showItemSwitcher = (bool) ($showItemSwitcher ?? !$inCockpit);
    $media = ($targetItem && method_exists($this, 'buildItemMediaPayload'))
        ? $this->buildItemMediaPayload($targetItem)
        : [
            'snapshots'            => [],
            'customer_files'       => [],
            'voucher_files'        => [],
            'has_front_export'     => false,
            'has_back_export'      => false,
            'has_open_photo_issue' => false,
            'total_count'          => 0,
        ];
    $mediaScopeKey = ($targetItem?->id ?? 'none') . '-' . ($inCockpit ? 'cockpit' : 'fallback');
    $canExportCurrentSide = $effectiveExportSide === 'back'
        ? !empty($media['has_back_export'])
        : (!empty($media['has_front_export']) || !empty($media['has_back_export']));
@endphp

@if($targetItem)
    <div data-order-media-dropdown
         x-data="{
            mediaScope: '{{ $mediaScopeKey }}',
            mediaOpen: Boolean(window.__sfOrderMediaOpen && window.__sfOrderMediaOpen['{{ $mediaScopeKey }}']),
            lightboxUrl: null,
            lightboxTitle: '',
            toggleMediaDropdown() {
                this.mediaOpen = !this.mediaOpen;
                window.__sfOrderMediaOpen = window.__sfOrderMediaOpen || {};
                window.__sfOrderMediaOpen[this.mediaScope] = this.mediaOpen;
            },
            openMediaLightbox(url, title) {
                if (!url) return;
                this.lightboxUrl = url;
                this.lightboxTitle = title || 'Vorschau';
            },
            closeMediaLightbox() {
                this.lightboxUrl = null;
                this.lightboxTitle = '';
            }
         }"
         @keydown.escape.window="if (lightboxUrl) closeMediaLightbox()"
         class="space-y-2">

        {{-- Einklappbare Kachel-Kopfzeile: Medien --}}
        <div @click="toggleMediaDropdown()"
             role="button"
             tabindex="0"
             @keydown.enter.prevent="toggleMediaDropdown()"
             @keydown.space.prevent="toggleMediaDropdown()"
             class="bg-gray-50 dark:bg-gray-950 px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] flex items-center justify-between gap-2 transition-colors cursor-pointer select-none">
            <div class="flex items-center gap-2 min-w-0">
                <svg class="w-4 h-4 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                <span class="text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white">Medien</span>
                <span class="px-2 py-0.5 rounded-md bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)] font-mono text-[10px] font-black">
                    {{ $media['total_count'] }}
                </span>
                @if(!empty($media['has_open_photo_issue']))
                    <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0" title="Foto-Nachbesserung offen"></span>
                @endif
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <span class="hidden sm:inline text-[9px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    .XCS · SVG · PNG
                </span>
                <span :class="mediaOpen ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'"
                      class="w-6 h-6 rounded-lg border inline-flex items-center justify-center transition-all">
                    <svg class="w-3.5 h-3.5 transition-transform duration-150"
                         :class="mediaOpen ? 'rotate-180' : ''"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </span>
            </div>
        </div>

        {{-- Aufgeklappter Medien-Bereich --}}
        <div x-cloak
             x-show="mediaOpen"
             style="display: none;"
             class="bg-gray-50 dark:bg-gray-950 p-3 sm:p-3.5 rounded-xl border border-gray-200 dark:border-gray-800 space-y-3">

            {{-- Artikel-Schnellwahl im Fallback-Modus bei Mehrpositions-Bestellungen --}}
            @if($showItemSwitcher && isset($selectedOrder) && $selectedOrder && $selectedOrder->items->count() > 1)
                <div class="flex items-center gap-1.5 overflow-x-auto custom-scrollbar pb-1">
                    @foreach($selectedOrder->items as $mItem)
                        @php $isSelMediaItem = (string) $mItem->id === (string) $targetItem->id; @endphp
                        <button type="button"
                                wire:click="selectItemForPreview('{{ $mItem->id }}')"
                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border transition-all cursor-pointer shrink-0 {{ $isSelMediaItem
                                    ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]'
                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">
                            {{ $mItem->quantity }}&times; {{ $mItem->product_name }}
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="space-y-2 max-h-[42vh] overflow-y-auto custom-scrollbar pr-0.5">
                {{-- 1. Sicherungen (Vorderseite / Rückseite) mit Lightbox im selben Fenster --}}
                @foreach($media['snapshots'] as $snap)
                    <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                        <div class="flex items-center gap-2.5 min-w-0">
                            @if($snap['is_image'])
                                <button type="button"
                                        @click.stop="openMediaLightbox(@js($snap['preview_url']), @js($snap['label']))"
                                        title="Großansicht öffnen"
                                        class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] overflow-hidden shrink-0 cursor-zoom-in transition-colors">
                                    <img src="{{ $snap['preview_url'] }}" alt="{{ $snap['label'] }}" class="w-full h-full object-cover" loading="lazy">
                                </button>
                            @else
                                <span class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[10px] font-black uppercase text-[var(--theme-color)] shrink-0">
                                    {{ strtoupper($snap['ext']) }}
                                </span>
                            @endif

                            <div class="min-w-0">
                                <button type="button"
                                        @if($snap['is_image']) @click.stop="openMediaLightbox(@js($snap['preview_url']), @js($snap['label']))" @endif
                                        class="text-xs font-bold text-gray-900 dark:text-white hover:text-[var(--theme-color)] truncate block text-left {{ $snap['is_image'] ? 'cursor-pointer' : 'cursor-default' }}">
                                    {{ $snap['label'] }}
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <a href="{{ $snap['download_url'] }}"
                               download="{{ $snap['download_name'] }}"
                               @click.stop
                               title="Sicherung herunterladen"
                               class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-[var(--theme-color)] hover:text-gray-950 hover:border-[var(--theme-color)] transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach

                {{-- 2. Kunden-Uploads inkl. Zahnrad (Laser-Foto-Studio), Warndreieck & _raw Original-Download --}}
                @foreach($media['customer_files'] as $cf)
                    <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                        <div class="flex items-center gap-2.5 min-w-0">
                            @if($cf['is_image'])
                                <button type="button"
                                        @click.stop="openMediaLightbox(@js($cf['preview_url']), @js($cf['label']))"
                                        title="Großansicht öffnen"
                                        class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] overflow-hidden shrink-0 cursor-zoom-in transition-colors">
                                    <img src="{{ $cf['preview_url'] }}" alt="{{ $cf['label'] }}" class="w-full h-full object-cover" loading="lazy">
                                </button>
                            @else
                                <span class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[10px] font-black uppercase text-[var(--theme-color)] shrink-0">
                                    {{ strtoupper($cf['ext']) }}
                                </span>
                            @endif

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <button type="button"
                                            @if($cf['is_image']) @click.stop="openMediaLightbox(@js($cf['preview_url']), @js($cf['label']))" @endif
                                            class="text-xs font-bold text-gray-900 dark:text-white hover:text-[var(--theme-color)] truncate max-w-[180px] sm:max-w-[260px] text-left {{ $cf['is_image'] ? 'cursor-pointer' : 'cursor-default' }}">
                                        {{ $cf['label'] }}
                                    </button>

                                    @if($cf['has_untouched_original'])
                                        <span class="px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[9px] font-black uppercase tracking-wider shrink-0">
                                            Original
                                        </span>
                                    @endif

                                    @if($cf['is_laser_optimized'])
                                        <span class="px-1.5 py-0.5 rounded-md bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-40)] text-[9px] font-black uppercase tracking-wider shrink-0">
                                            Laser-optimiert
                                        </span>
                                    @endif
                                </div>

                                @if($cf['has_photo_issue'] && !$cf['photo_issue_resolved'])
                                    <div class="text-[10px] font-bold text-amber-600 dark:text-amber-400 truncate mt-0.5">
                                        Nachbesserung offen: {{ $cf['photo_issue_reason'] }}
                                    </div>
                                @elseif($cf['photo_issue_resolved'])
                                    <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">
                                        ✓ Nachgebessert
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($cf['is_raster'])
                                <button type="button"
                                        data-open-photo-studio
                                        @click.stop
                                        wire:click="openLaserPhotoStudio('{{ $targetItem->id }}', '{{ $cf['raw_key'] }}')"
                                        title="Bild für Laser nachbearbeiten"
                                        aria-label="Bild für Laser nachbearbeiten"
                                        class="p-2 rounded-lg border transition-colors cursor-pointer {{ $cf['is_laser_optimized']
                                            ? 'bg-[var(--theme-color-15)] border-[var(--theme-color)] text-[var(--theme-color)] hover:bg-[var(--theme-color)] hover:text-gray-950'
                                            : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)]' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </button>
                            @endif

                            <button type="button"
                                    @click.stop
                                    wire:click="openPhotoIssueModal('{{ $targetItem->id }}', '{{ $cf['raw_key'] }}', '{{ $cf['label'] }}')"
                                    title="Foto-Problem melden"
                                    class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-amber-500 hover:border-amber-500 transition-colors cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </button>

                            @if($cf['has_photo_issue'] && !$cf['photo_issue_resolved'])
                                <button type="button"
                                        @click.stop
                                        wire:click="resolvePhotoIssue('{{ $targetItem->id }}')"
                                        title="Mangel als erledigt markieren"
                                        class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-colors cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            @endif

                            <a href="{{ $cf['download_url'] }}"
                               download="{{ $cf['download_name'] }}"
                               @click.stop
                               title="Originaldatei herunterladen"
                               class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-[var(--theme-color)] hover:text-gray-950 hover:border-[var(--theme-color)] transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach

                {{-- 3. Gutschein-Dateien (falls Geschenkgutschein) --}}
                @foreach($media['voucher_files'] as $vf)
                    <div class="flex items-center justify-between gap-3 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[10px] font-black uppercase text-[var(--theme-color)] shrink-0">
                                {{ strtoupper($vf['ext']) }}
                            </span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $vf['label'] }}</span>
                        </div>
                        <a href="{{ $vf['download_url'] }}"
                           @click.stop
                           title="Herunterladen"
                           class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-[var(--theme-color)] hover:text-gray-950 hover:border-[var(--theme-color)] transition-colors shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                        </a>
                    </div>
                @endforeach

                @if($media['total_count'] === 0)
                    <div class="py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">
                        Keine Kunden-Uploads für diese Position hinterlegt.
                    </div>
                @endif
            </div>

            {{-- 4. Untere Laser-Export-Zeile: XTOOL PROJEKT (.XCS) | LASER-SVG | KONTUR-PNG --}}
            @if($canExportCurrentSide)
                <div class="pt-2.5 border-t border-gray-200 dark:border-gray-800 space-y-2">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <button type="button"
                                @click.stop
                                wire:click.prevent="downloadLaserXcsFile('{{ $targetItem->id }}', '{{ $effectiveExportSide }}', {{ $inCockpit ? 'laserParams' : 'null' }})"
                                wire:loading.attr="disabled"
                                wire:target="downloadLaserXcsFile"
                                class="sf-laser-action-btn w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-[var(--theme-color)] hover:brightness-95 text-gray-950 text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                            <span>XTOOL PROJEKT (.XCS)</span>
                        </button>

                        <button type="button"
                                @click.stop
                                wire:click.prevent="downloadLaserFile('{{ $targetItem->id }}', '{{ $effectiveExportSide }}')"
                                wire:loading.attr="disabled"
                                wire:target="downloadLaserFile"
                                class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                            <span>LASER-SVG</span>
                        </button>

                        <button type="button"
                                @click.stop
                                wire:click.prevent="downloadLaserPngFile('{{ $targetItem->id }}', '{{ $effectiveExportSide }}')"
                                wire:loading.attr="disabled"
                                wire:target="downloadLaserPngFile"
                                class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                            <span>KONTUR-PNG</span>
                        </button>
                    </div>

                    @if(!$inCockpit && !empty($media['has_back_export']) && $effectiveExportSide === 'front')
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <button type="button"
                                    @click.stop
                                    wire:click.prevent="downloadLaserXcsFile('{{ $targetItem->id }}', 'back')"
                                    class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-40)] hover:bg-[var(--theme-color)] hover:text-gray-950 text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                <span>.XCS (RÜCKSEITE)</span>
                            </button>
                            <button type="button"
                                    @click.stop
                                    wire:click.prevent="downloadLaserFile('{{ $targetItem->id }}', 'back')"
                                    class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                <span>LASER-SVG (RÜCKSEITE)</span>
                            </button>
                            <button type="button"
                                    @click.stop
                                    wire:click.prevent="downloadLaserPngFile('{{ $targetItem->id }}', 'back')"
                                    class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                <span>KONTUR-PNG (RÜCKSEITE)</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Integrierte Lightbox im selben Fenster (Baustein 1: kein neuer Tab!) --}}
        <template x-teleport="body">
            <div x-cloak
                 x-show="lightboxUrl !== null"
                 style="display: none;"
                 @click.self="closeMediaLightbox()"
                 class="fixed inset-0 z-[160] flex items-center justify-center bg-black/75 backdrop-blur-sm p-4">
                <div class="relative max-w-4xl w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between gap-3">
                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate" x-text="lightboxTitle"></span>
                        <button type="button"
                                @click="closeMediaLightbox()"
                                title="Schließen"
                                class="w-8 h-8 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white flex items-center justify-center cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="p-4 sm:p-6 bg-gray-100/80 dark:bg-gray-950 flex items-center justify-center max-h-[78vh] overflow-auto">
                        <img :src="lightboxUrl || ''" :alt="lightboxTitle" class="max-w-full max-h-[70vh] object-contain rounded-xl">
                    </div>
                </div>
            </div>
        </template>
    </div>
@endif
