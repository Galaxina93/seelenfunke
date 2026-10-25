@php
    $currentLayout = in_array($layoutMode ?? 'large', ['large', 'medium', 'list'], true) ? $layoutMode : 'large';
@endphp

<div x-data="{
        syncProductLayout() {
            try {
                const saved = localStorage.getItem('admin_products_layout_mode');
                if (saved && ['large', 'medium', 'list'].includes(saved) && saved !== '{{ $currentLayout }}') {
                    $wire.setLayoutMode(saved);
                } else {
                    localStorage.setItem('admin_products_layout_mode', '{{ $currentLayout }}');
                }
            } catch (e) {}
        }
     }"
     x-init="syncProductLayout()">

    {{-- HEADER BEREICH --}}
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-end mb-10 gap-6 animate-fade-in-up">
        <div>
            <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-tight">Deine Kollektion</h1>
            <div class="flex items-center gap-4 text-gray-600 dark:text-gray-400">
                <p class="text-xs sm:text-sm font-medium">Verwalte deine Unikate.</p>
                <span class="px-3 py-1.5 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 dark:shadow-inner rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-500">
                    {{ count($products ?? []) }} Produkte
                </span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 sm:gap-4 w-full xl:w-auto">
            {{-- ANSICHTSAUSWAHL (Groß / Mittel / Liste) --}}
            <div class="inline-flex items-center p-1.5 bg-white dark:bg-gray-900/90 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] dark:shadow-inner self-start sm:self-auto" role="group" aria-label="Ansichtsauswahl">
                {{-- Groß (Standard) --}}
                <button type="button"
                        wire:click="setLayoutMode('large')"
                        onclick="try { localStorage.setItem('admin_products_layout_mode', 'large'); } catch (e) {}"
                        class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'large' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Große Kachelansicht">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1.5" />
                        <rect x="14" y="3" width="7" height="7" rx="1.5" />
                        <rect x="3" y="14" width="7" height="7" rx="1.5" />
                        <rect x="14" y="14" width="7" height="7" rx="1.5" />
                    </svg>
                    <span>Groß</span>
                </button>

                {{-- Mittel --}}
                <button type="button"
                        wire:click="setLayoutMode('medium')"
                        onclick="try { localStorage.setItem('admin_products_layout_mode', 'medium'); } catch (e) {}"
                        class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'medium' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Mittlere Kachelansicht">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="4.5" height="4.5" rx="1" />
                        <rect x="9.75" y="3" width="4.5" height="4.5" rx="1" />
                        <rect x="16.5" y="3" width="4.5" height="4.5" rx="1" />
                        <rect x="3" y="9.75" width="4.5" height="4.5" rx="1" />
                        <rect x="9.75" y="9.75" width="4.5" height="4.5" rx="1" />
                        <rect x="16.5" y="9.75" width="4.5" height="4.5" rx="1" />
                        <rect x="3" y="16.5" width="4.5" height="4.5" rx="1" />
                        <rect x="9.75" y="16.5" width="4.5" height="4.5" rx="1" />
                        <rect x="16.5" y="16.5" width="4.5" height="4.5" rx="1" />
                    </svg>
                    <span>Mittel</span>
                </button>

                {{-- Listenansicht --}}
                <button type="button"
                        wire:click="setLayoutMode('list')"
                        onclick="try { localStorage.setItem('admin_products_layout_mode', 'list'); } catch (e) {}"
                        class="px-3.5 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'list' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Listenansicht">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>Liste</span>
                </button>
            </div>

            {{-- SORTIERUNG & SCHNELLFILTER --}}
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="sortBy"
                        aria-label="Produkte sortieren und filtern"
                        class="w-full sm:w-56 pl-4 pr-9 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-xs font-bold text-gray-800 dark:text-gray-200 focus:bg-white dark:focus:bg-gray-950 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all dark:shadow-inner outline-none appearance-none cursor-pointer">
                    <optgroup label="Sortieren nach">
                        <option value="newest">Neueste zuerst</option>
                        <option value="bestseller">Am meisten verkauft</option>
                        <option value="least_sold">Am wenigsten verkauft</option>
                        <option value="revenue_desc">Höchster Umsatz</option>
                        <option value="rating_desc">Beste Bewertung</option>
                        <option value="reviews_desc">Meiste Bewertungen</option>
                        <option value="stock_asc">Niedrigster Bestand</option>
                        <option value="stock_desc">Höchster Bestand</option>
                        <option value="price_desc">Preis: Hoch &rarr; Niedrig</option>
                        <option value="price_asc">Preis: Niedrig &rarr; Hoch</option>
                        <option value="name_asc">Name: A &ndash; Z</option>
                    </optgroup>
                    <optgroup label="Filtern nach">
                        <option value="filter_active">Nur Aktive Produkte</option>
                        <option value="filter_draft">Nur Entwürfe</option>
                        <option value="filter_low_stock">Kritischer Bestand / Leer</option>
                        <option value="filter_physical">Nur Physisch</option>
                        <option value="filter_digital">Nur Digital</option>
                    </optgroup>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 dark:text-gray-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            {{-- SUCHE --}}
            <div class="relative w-full sm:w-72 group">
                <input type="text" wire:model.live="search" placeholder="Produkt suchen..."
                       class="w-full pl-12 pr-4 py-3.5 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:bg-white dark:focus:bg-gray-950 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all dark:shadow-inner outline-none">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-600 group-focus-within:text-[var(--theme-color)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- NEU/ARCHIV BUTTONS --}}
            @if(!$showArchived)
                <button wire:click="toggleArchiveMode"
                        class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-400 px-6 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-inner hover:bg-gray-50 dark:hover:bg-gray-900 hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                    Archiv öffnen
                </button>
                <button wire:click="createDraft"
                        class="bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white dark:text-gray-900 px-6 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-black/90 dark:hover:bg-white hover:scale-[1.02] transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                    <span class="text-sm leading-none">+</span> Neu
                </button>
            @else
                <button wire:click="toggleArchiveMode"
                        class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-400 px-6 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-inner hover:bg-gray-50 dark:hover:bg-gray-900 hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                    Zurück zur Kollektion
                </button>
            @endif
        </div>
    </div>

    {{-- EMPTY STATE --}}
    @if(blank($products))
        <div class="bg-white dark:bg-gray-900/50 backdrop-blur-md p-16 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-gray-800 text-center dark:shadow-inner animate-fade-in-up">
            <div class="w-20 h-20 rounded-[1.5rem] bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center mx-auto mb-5 dark:shadow-inner">
                <svg class="w-10 h-10 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-wide">Keine Produkte gefunden</h3>
            <p class="text-xs text-gray-600 dark:text-gray-500 mb-6">Starte jetzt und füge dein erstes Unikat hinzu.</p>
            <button wire:click="createDraft" class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white transition-colors border-b border-[var(--theme-color-30)] hover:border-gray-900 dark:hover:border-white pb-0.5">
                Erstelle das Erste
            </button>
        </div>
    @else
        {{-- Threshold laden --}}
        @php
            $threshold = \App\Models\System\SystemSetting::where('key', 'inventory_low_stock_threshold')->value('value') ?? 5;
        @endphp

        @if($currentLayout === 'list')
            {{-- ===================================================================== --}}
            {{-- 1. LISTENANSICHT (KOMPAKTE ZEILEN)                                    --}}
            {{-- ===================================================================== --}}
            <div wire:key="products-layout-list" class="flex flex-col gap-3 animate-fade-in-up" style="animation-delay: 100ms;">
                @foreach($products as $prod)
                    @php
                        $isPhysical = $prod->type === 'physical';
                        $maxSteps = $isPhysical ? 4 : 3;
                        $percentComplete = min(100, ($prod->completion_step / $maxSteps) * 100);
                        $isComplete = $prod->completion_step >= $maxSteps;
                        $ratingAvg = round((float) ($prod->approved_reviews_avg ?? 0), 1);
                        $ratingCount = (int) ($prod->approved_reviews_count ?? 0);
                        $pendingReviewsCount = (int) ($prod->pending_reviews_count ?? 0);
                        $soldQty = (int) ($prod->total_sold_qty ?? 0);
                        $revenueCents = (int) ($prod->total_revenue_cents ?? 0);
                    @endphp

                    <div wire:key="product-list-row-{{ $prod->id }}"
                         class="bg-white dark:bg-gray-900/80 backdrop-blur-md p-4 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-xl hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-300 flex flex-col lg:flex-row lg:items-center justify-between gap-4 relative overflow-hidden group">

                        {{-- Fortschrittsbalken Links --}}
                        <div class="absolute top-0 left-0 w-1.5 h-full bg-gray-100 dark:bg-gray-950 z-20">
                            <div class="w-full transition-all duration-500 {{ $isComplete ? 'bg-[var(--theme-color)]' : 'bg-red-500' }}" style="height: {{ $percentComplete }}%"></div>
                        </div>

                        {{-- Links: Vorschaubild + Name + Typ/SKU --}}
                        <div class="flex items-center gap-4 min-w-0 pl-2 flex-1">
                            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 overflow-hidden shrink-0 relative">
                                @php $prodMainUrl = $prod->resolveMainImageUrl(); @endphp
                                @if($prodMainUrl)
                                    <img src="{{ $prodMainUrl }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @elseif(!empty($prod->media_gallery[0]) && isset($prod->media_gallery[0]['type']) && $prod->media_gallery[0]['type'] == 'video')
                                    <video src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover"></video>
                                @else
                                    <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-700">
                                        <svg class="w-6 h-6 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2.5 flex-wrap mb-1">
                                    <h3 class="font-serif text-base sm:text-lg font-bold text-gray-900 dark:text-white truncate">{{ $prod->name }}</h3>
                                    @if($prod->type === 'digital')
                                        <span class="px-2.5 py-0.5 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 text-[9px] font-black uppercase tracking-widest">Digital</span>
                                    @elseif($prod->type === 'service')
                                        <span class="px-2.5 py-0.5 rounded-lg bg-orange-50 dark:bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-500/20 text-[9px] font-black uppercase tracking-widest">Service</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[9px] font-black uppercase tracking-widest">Physisch</span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="text-[var(--theme-color)] font-mono font-bold text-sm">{{ number_format($prod->price / 100, 2, ',', '.') }} €</span>
                                    @if(!empty($prod->sku))
                                        <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500">SKU: {{ $prod->sku }}</span>
                                    @endif

                                    {{-- Sternebewertung --}}
                                    <a href="{{ route('admin.product-control-reviews', ['filterStatus' => $pendingReviewsCount > 0 ? 'pending' : 'approved', 'search' => $prod->name]) }}"
                                       class="inline-flex items-center gap-1.5 group/rating hover:opacity-90 transition-opacity"
                                       title="{{ $ratingCount > 0 ? number_format($ratingAvg, 1, ',', '.') . ' von 5 Sternen (' . $ratingCount . ' freigegebene Bewertungen)' : 'Noch keine freigegebenen Bewertungen' }}">
                                        <div class="flex items-center">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="w-3.5 h-3.5 shrink-0 {{ $i <= round($ratingAvg) ? 'text-amber-400' : 'text-gray-200 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                                </svg>
                                            @endfor
                                        </div>
                                        <span class="text-[10px] font-bold {{ $ratingCount > 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500' }} group-hover/rating:text-[var(--theme-color)] transition-colors">
                                            @if($ratingCount > 0)
                                                {{ number_format($ratingAvg, 1, ',', '.') }} <span class="text-gray-400 dark:text-gray-500 font-medium">({{ $ratingCount }})</span>
                                            @else
                                                <span class="font-medium">(0)</span>
                                            @endif
                                        </span>
                                        @if($pendingReviewsCount > 0)
                                            <span class="px-1.5 py-0.5 rounded-md bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-[9px] font-black uppercase tracking-wider">
                                                +{{ $pendingReviewsCount }} neu
                                            </span>
                                        @endif
                                    </a>

                                    {{-- Verkäufe-Indikator --}}
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold {{ $soldQty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}"
                                          title="Verkaufte Einheiten: {{ $soldQty }} ({{ number_format($revenueCents / 100, 2, ',', '.') }} € Umsatz)">
                                        <svg class="w-3.5 h-3.5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        <span>{{ $soldQty }}&times; verkauft</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Rechts: Bestand + Status + Fortschritt + Aktionen --}}
                        <div class="flex flex-wrap items-center justify-between lg:justify-end gap-3 sm:gap-5 pl-2 lg:pl-0 border-t lg:border-t-0 pt-3 lg:pt-0 border-gray-100 dark:border-gray-800/80">

                            {{-- Lagerbestand (Inline Edit) --}}
                            <div class="flex items-center gap-2"
                                 x-data="{
                                     editing: false,
                                     qty: {{ $prod->quantity }}
                                 }">
                                @if($prod->track_quantity)
                                    <div x-show="!editing"
                                         @click="editing = true; $nextTick(() => $refs.qtyListInput.focus())"
                                         class="cursor-pointer group/qty flex items-center gap-2">
                                        @if($prod->quantity <= 0)
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                                {{ $prod->type === 'service' ? 'Ausgebucht' : 'Ausverkauft' }}
                                            </span>
                                        @elseif($prod->quantity < $threshold)
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'Stk.' }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'auf Lager' }}
                                            </span>
                                        @endif
                                    </div>

                                    <div x-show="editing" style="display: none;" class="flex items-center gap-1.5" @click.outside="editing = false; qty = {{ $prod->quantity }}">
                                        <input x-ref="qtyListInput"
                                               type="number"
                                               x-model="qty"
                                               class="w-16 px-2 py-1 text-xs font-bold text-center border border-[var(--theme-color)] bg-white dark:bg-gray-950 text-gray-900 dark:text-white rounded-lg outline-none"
                                               @keydown.enter="$wire.updateStock('{{ $prod->id }}', qty); editing = false"
                                               @keydown.escape="editing = false; qty = {{ $prod->quantity }}">
                                        <button @click="$wire.updateStock('{{ $prod->id }}', qty); editing = false" class="bg-[var(--theme-color)] text-white dark:text-gray-900 p-1.5 rounded-lg">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-500 border border-blue-200 dark:border-blue-500/30 opacity-70">
                                        Unbegrenzt
                                    </span>
                                @endif
                            </div>

                            {{-- Status Dropdown --}}
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" @click.away="open = false" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-xl border flex items-center gap-1.5 transition-all {{ $prod->status == 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30' : 'bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $prod->status == 'active' ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                    {{ $prod->status == 'active' ? 'Aktiv' : 'Entwurf' }}
                                    <svg class="w-3 h-3 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>

                                <div x-show="open" class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-950 rounded-xl shadow-xl border border-gray-200 dark:border-gray-800 overflow-hidden z-30" style="display: none;" x-transition>
                                    <button wire:click="updateStatus('{{ $prod->id }}', 'active'); open = false" class="w-full text-left px-4 py-2.5 text-[9px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-emerald-700 dark:text-emerald-400 border-b border-gray-200 dark:border-gray-800 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktiv
                                    </button>
                                    <button wire:click="updateStatus('{{ $prod->id }}', 'draft'); open = false" class="w-full text-left px-4 py-2.5 text-[9px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-500 flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Entwurf
                                    </button>
                                </div>
                            </div>

                            {{-- Schritt-Status --}}
                            <span class="text-[10px] font-black uppercase tracking-widest {{ $isComplete ? 'text-[var(--theme-color)]' : 'text-red-500 dark:text-red-400' }}">
                                @if($isComplete)
                                    <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Fertig</span>
                                @else
                                    Schritt {{ $prod->completion_step }}/{{ $maxSteps }}
                                @endif
                            </span>

                            {{-- Aktionen --}}
                            <div class="flex items-center gap-4 text-[10px] font-black uppercase tracking-widest">
                                @if($showArchived)
                                    <button wire:click="restoreProduct('{{ $prod->id }}')" class="text-emerald-600 dark:text-emerald-500 hover:text-emerald-700 transition-colors border-b border-emerald-600 pb-0.5">
                                        Wiederherstellen
                                    </button>
                                @else
                                    <button wire:click="archiveProduct('{{ $prod->id }}')" onclick="confirm('Produkt wirklich ins Archiv verschieben?') || event.stopImmediatePropagation()" class="text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-red-600 pb-0.5">
                                        Löschen
                                    </button>
                                    <button wire:click="edit('{{ $prod->id }}')" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-gray-900 dark:hover:border-white pb-0.5">
                                        Bearbeiten &rarr;
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- ===================================================================== --}}
            {{-- 2. GROSSE & MITTLERE KACHELANSICHT (GRID)                             --}}
            {{-- ===================================================================== --}}
            <div wire:key="products-layout-{{ $currentLayout }}"
                 class="grid {{ $currentLayout === 'medium' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 sm:gap-8' }} animate-fade-in-up"
                 style="animation-delay: 100ms;">
                @foreach($products as $prod)
                    {{-- Logik für Steps berechnen --}}
                    @php
                        $isPhysical = $prod->type === 'physical';
                        $maxSteps = $isPhysical ? 4 : 3;
                        $percentComplete = min(100, ($prod->completion_step / $maxSteps) * 100);
                        $isComplete = $prod->completion_step >= $maxSteps;
                        $ratingAvg = round((float) ($prod->approved_reviews_avg ?? 0), 1);
                        $ratingCount = (int) ($prod->approved_reviews_count ?? 0);
                        $pendingReviewsCount = (int) ($prod->pending_reviews_count ?? 0);
                        $soldQty = (int) ($prod->total_sold_qty ?? 0);
                        $revenueCents = (int) ($prod->total_revenue_cents ?? 0);
                    @endphp

                    <div wire:key="product-card-{{ $currentLayout }}-{{ $prod->id }}"
                         class="bg-white dark:bg-gray-900/80 backdrop-blur-md {{ $currentLayout === 'medium' ? 'p-4 rounded-3xl' : 'p-5 sm:p-6 rounded-[2.5rem]' }} border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl hover:border-gray-300 dark:hover:border-gray-700 dark:hover:shadow-[0_0_40px_rgba(0,0,0,0.6)] transition-all duration-300 flex flex-col h-full relative overflow-hidden group">

                        {{-- Fortschrittsbalken Oben --}}
                        <div class="absolute top-0 left-0 w-full h-1 bg-gray-100 dark:bg-gray-950 z-30">
                            <div class="h-full transition-all duration-500 {{ $isComplete ? 'bg-[var(--theme-color)] dark:shadow-[0_0_10px_rgba(197,160,89,0.8)]' : 'bg-red-500 dark:shadow-[0_0_10px_rgba(239,68,68,0.8)]' }}" style="width: {{ $percentComplete }}%"></div>
                        </div>

                        {{-- Status Switcher --}}
                        <div class="absolute {{ $currentLayout === 'medium' ? 'top-4 right-4' : 'top-6 right-6' }} z-20"
                             x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false"
                                    class="{{ $currentLayout === 'medium' ? 'px-2.5 py-1.5 text-[8px]' : 'px-3.5 py-2 text-[9px]' }} font-black uppercase tracking-widest rounded-xl border shadow-sm flex items-center gap-2 transition-all backdrop-blur-md {{ $prod->status == 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30' : 'bg-white dark:bg-gray-950/80 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $prod->status == 'active' ? 'bg-emerald-500 dark:shadow-[0_0_8px_currentColor] animate-pulse' : 'bg-gray-400 dark:bg-gray-500' }}"></span>
                                {{ $prod->status == 'active' ? 'Aktiv' : 'Entwurf' }}
                                <svg class="w-3.5 h-3.5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            <div x-show="open" class="absolute right-0 mt-2 w-36 bg-white dark:bg-gray-950 rounded-2xl shadow-xl dark:shadow-[0_0_30px_rgba(0,0,0,0.8)] border border-gray-200 dark:border-gray-800 overflow-hidden z-30" style="display: none;" x-transition>
                                <button wire:click="updateStatus('{{ $prod->id }}', 'active'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-emerald-700 dark:text-emerald-400 border-b border-gray-200 dark:border-gray-800 transition-colors flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktiv
                                </button>
                                <button wire:click="updateStatus('{{ $prod->id }}', 'draft'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-900 text-gray-600 dark:text-gray-500 transition-colors flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 dark:bg-gray-500"></span> Entwurf
                                </button>
                            </div>
                        </div>

                        {{-- Bild mit Schleifen --}}
                        <div class="{{ $currentLayout === 'medium' ? 'h-48 rounded-2xl mb-4 mt-1.5' : 'aspect-square rounded-[1.5rem] mb-6 mt-2' }} bg-gray-100 dark:bg-gray-950 overflow-hidden relative border border-gray-200 dark:border-gray-800 dark:shadow-inner group-hover:border-gray-300 dark:group-hover:border-gray-700 transition-colors">

                            {{-- BLAUE SCHLEIFE: DIGITAL --}}
                            @if($prod->type === 'digital')
                                <div class="absolute top-7 -left-12 z-10 pointer-events-none">
                                    <div class="bg-blue-600/90 backdrop-blur-sm text-white text-[9px] font-black py-1.5 w-48 transform -rotate-45 dark:shadow-[0_0_15px_rgba(37,99,235,0.5)] border-b border-blue-400/50 uppercase tracking-widest text-center">
                                        Digital
                                    </div>
                                </div>
                            @endif

                            {{-- ORANGE SCHLEIFE: DIENSTLEISTUNG --}}
                            @if($prod->type === 'service')
                                <div class="absolute top-7 -left-12 z-10 pointer-events-none">
                                    <div class="bg-orange-500/90 backdrop-blur-sm text-white dark:text-gray-900 text-[9px] font-black py-1.5 w-48 transform -rotate-45 dark:shadow-[0_0_15px_rgba(249,115,22,0.5)] border-b border-orange-300 uppercase tracking-widest text-center">
                                        Service
                                    </div>
                                </div>
                            @endif

                            @php $prodCardMainUrl = $prod->resolveMainImageUrl(); @endphp
                            @if($prodCardMainUrl)
                                <img src="{{ $prodCardMainUrl }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 opacity-80 group-hover:opacity-100">
                            @elseif(!empty($prod->media_gallery[0]) && isset($prod->media_gallery[0]['type']) && $prod->media_gallery[0]['type'] == 'video')
                                <video src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity"></video>
                            @else
                                <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-700 text-[10px] flex-col gap-3 font-black uppercase tracking-widest">
                                    <svg class="w-10 h-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Kein Bild</span>
                                </div>
                            @endif
                        </div>

                        <h3 class="font-serif {{ $currentLayout === 'medium' ? 'text-base mb-1' : 'text-lg md:text-xl mb-1.5' }} font-bold text-gray-900 dark:text-white truncate tracking-wide">{{ $prod->name }}</h3>

                        {{-- Sternebewertung & Verkäufe --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 {{ $currentLayout === 'medium' ? 'mb-2' : 'mb-3' }}">
                            <a href="{{ route('admin.product-control-reviews', ['filterStatus' => $pendingReviewsCount > 0 ? 'pending' : 'approved', 'search' => $prod->name]) }}"
                               class="inline-flex items-center gap-1.5 group/rating hover:opacity-90 transition-opacity"
                               title="{{ $ratingCount > 0 ? number_format($ratingAvg, 1, ',', '.') . ' von 5 Sternen (' . $ratingCount . ' freigegebene Bewertungen)' : 'Noch keine freigegebenen Bewertungen' }}">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3.5 h-3.5 shrink-0 {{ $i <= round($ratingAvg) ? 'text-amber-400' : 'text-gray-200 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-[11px] font-bold {{ $ratingCount > 0 ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500' }} group-hover/rating:text-[var(--theme-color)] transition-colors">
                                    @if($ratingCount > 0)
                                        {{ number_format($ratingAvg, 1, ',', '.') }} <span class="text-gray-400 dark:text-gray-500 font-medium">({{ $ratingCount }})</span>
                                    @else
                                        <span class="text-[10px] font-medium">(0)</span>
                                    @endif
                                </span>
                                @if($pendingReviewsCount > 0)
                                    <span class="px-1.5 py-0.5 rounded-md bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-[9px] font-black uppercase tracking-wider">
                                        +{{ $pendingReviewsCount }}
                                    </span>
                                @endif
                            </a>

                            <span class="inline-flex items-center gap-1 text-[10px] font-bold {{ $soldQty > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}"
                                  title="Verkaufte Einheiten: {{ $soldQty }} ({{ number_format($revenueCents / 100, 2, ',', '.') }} € Umsatz)">
                                <svg class="w-3.5 h-3.5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span>{{ $soldQty }}&times; verkauft</span>
                            </span>
                        </div>

                        <p class="{{ $currentLayout === 'medium' ? 'text-xs sm:text-sm mb-3.5' : 'text-sm md:text-base mb-6' }} text-[var(--theme-color)] font-mono font-bold dark:drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]">{{ number_format($prod->price / 100, 2, ',', '.') }} €</p>

                        {{-- Lagerbestand Anzeige --}}
                        <div class="{{ $currentLayout === 'medium' ? 'mb-3.5' : 'mb-6' }} h-8 flex items-center gap-3"
                             x-data="{
                                 editing: false,
                                 qty: {{ $prod->quantity }}
                             }">

                            @if($prod->track_quantity)
                                {{-- ANZEIGE MODUS --}}
                                <div x-show="!editing"
                                     @click="editing = true; $nextTick(() => $refs.qtyInput.focus())"
                                     class="cursor-pointer group/qty relative transition-transform hover:scale-[1.02] flex items-center gap-2">

                                    @if($prod->quantity <= 0)
                                        {{-- Ausverkauft / Voll --}}
                                        <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30 dark:shadow-inner group-hover/qty:border-red-400 transition-colors">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 dark:shadow-[0_0_8px_currentColor]"></span>
                                            {{ $prod->type === 'service' ? 'Ausgebucht' : 'Ausverkauft' }}
                                            <svg class="w-3.5 h-3.5 ml-1 text-red-500 opacity-50 group-hover/qty:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </span>
                                    @elseif($prod->quantity < $threshold)
                                        {{-- Kritischer Bestand --}}
                                        <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30 dark:shadow-inner group-hover/qty:border-amber-400 transition-colors">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse dark:shadow-[0_0_8px_currentColor]"></span>
                                            <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'Stk.' }} (Knapp)
                                            <svg class="w-3.5 h-3.5 ml-1 text-amber-500 opacity-50 group-hover/qty:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </span>
                                    @else
                                        {{-- OK --}}
                                        <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 dark:shadow-inner group-hover/qty:border-emerald-400 transition-colors">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:shadow-[0_0_8px_currentColor]"></span>
                                            <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'auf Lager' }}
                                            <svg class="w-3.5 h-3.5 ml-1 text-emerald-500 opacity-50 group-hover/qty:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </span>
                                    @endif
                                </div>

                                {{-- Nachbestellen Link (Wenn kritisch oder 0 UND Link vorhanden) --}}
                                @if($prod->quantity < $threshold && !empty($prod->reorder_url))
                                    <a href="{{ $prod->reorder_url }}" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30 dark:shadow-inner hover:bg-blue-600 dark:hover:bg-blue-500 hover:text-white hover:border-blue-600 transition-all z-10" title="Nachbestellen beim Lieferanten">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        Nachbestellen
                                    </a>
                                @endif

                                {{-- EDITIER MODUS --}}
                                <div x-show="editing" style="display: none;" class="flex items-center gap-2" @click.outside="editing = false; qty = {{ $prod->quantity }}">
                                    <input x-ref="qtyInput"
                                           type="number"
                                           x-model="qty"
                                           class="w-20 px-3 py-1.5 text-xs font-bold text-center border border-[var(--theme-color)] bg-white dark:bg-gray-950 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-[var(--theme-color-50)] outline-none dark:shadow-inner"
                                           @keydown.enter="$wire.updateStock('{{ $prod->id }}', qty); editing = false"
                                           @keydown.escape="editing = false; qty = {{ $prod->quantity }}">

                                    <button @click="$wire.updateStock('{{ $prod->id }}', qty); editing = false" class="bg-[var(--theme-color)] text-white dark:text-gray-900 p-2 rounded-xl hover:bg-black/90 dark:hover:bg-white transition-colors dark:shadow-[0_0_10px_rgba(197,160,89,0.3)]">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </div>
                            @else
                                {{-- Unbegrenzt --}}
                                <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-500 border border-blue-200 dark:border-blue-500/30 cursor-not-allowed opacity-60 dark:shadow-inner" title="Unbegrenzter Bestand">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    Unbegrenzt
                                </span>
                            @endif
                        </div>

                        {{-- Footer Steps (Dynamisch) --}}
                        <div class="mt-auto {{ $currentLayout === 'medium' ? 'pt-3.5' : 'pt-5' }} border-t border-gray-200 dark:border-gray-800 flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                            <span class="{{ $isComplete ? 'text-[var(--theme-color)] dark:drop-shadow-[0_0_5px_currentColor]' : 'text-red-500 dark:text-red-400' }}">
                                @if($isComplete)
                                    <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Fertig</span>
                                @else
                                    Schritt {{ $prod->completion_step }}/{{ $maxSteps }}
                                @endif
                            </span>
                            @if($showArchived)
                                <button wire:click="restoreProduct('{{ $prod->id }}')" class="text-emerald-600 dark:text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-400 transition-colors border-b border-emerald-600 hover:border-emerald-700 dark:hover:border-emerald-400 pb-0.5">
                                    Wiederherstellen
                                </button>
                            @else
                                <div class="flex items-center gap-4">
                                    <button wire:click="archiveProduct('{{ $prod->id }}')" onclick="confirm('Produkt wirklich ins Archiv verschieben?') || event.stopImmediatePropagation()" class="text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-red-600 dark:hover:border-red-400 pb-0.5">
                                        Löschen
                                    </button>
                                    <button wire:click="edit('{{ $prod->id }}')" class="text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors border-b border-gray-300 dark:border-gray-600 hover:border-gray-900 dark:hover:border-white pb-0.5">
                                        Bearbeiten &rarr;
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
