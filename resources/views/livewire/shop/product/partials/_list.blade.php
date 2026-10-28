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
    @php
        $productCount = count($products ?? []);
    @endphp
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-10 gap-4 animate-fade-in-up">
        <div class="shrink-0">
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-tight whitespace-nowrap">Deine Kollektion</h1>
        </div>

        <div class="flex flex-wrap xl:flex-nowrap items-center justify-start xl:justify-end gap-2.5 w-full xl:w-auto">
            {{-- ANSICHTSAUSWAHL (Groß / Mittel / Liste) --}}
            <div class="inline-flex items-center p-1 bg-white dark:bg-gray-900/90 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] dark:shadow-inner shrink-0" role="group" aria-label="Ansichtsauswahl">
                {{-- Groß (Standard) --}}
                <button type="button"
                        wire:click="setLayoutMode('large')"
                        onclick="try { localStorage.setItem('admin_products_layout_mode', 'large'); } catch (e) {}"
                        class="px-3 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'large' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
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
                        class="px-3 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'medium' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
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
                        class="px-3 py-2 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 {{ $currentLayout === 'list' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Listenansicht">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>Liste</span>
                </button>
            </div>

            {{-- SORTIERUNG & SCHNELLFILTER --}}
            <div class="relative w-full sm:w-44 2xl:w-52 shrink-0">
                <select wire:model.live="sortBy"
                        aria-label="Produkte sortieren und filtern"
                        class="w-full pl-3.5 pr-8 py-3 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-xs font-bold text-gray-800 dark:text-gray-200 focus:bg-white dark:focus:bg-gray-950 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all dark:shadow-inner outline-none appearance-none cursor-pointer">
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
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            {{-- SUCHE MIT INTEGRIERTER PRODUKTANZAHL --}}
            <div class="relative w-full sm:w-52 2xl:w-64 group shrink-0">
                <input type="text" wire:model.live="search" placeholder="{{ $productCount }} {{ $productCount === 1 ? 'Produkt' : 'Produkte' }} suchen..."
                       class="w-full pl-10 pr-3.5 py-3 bg-white dark:bg-gray-900/80 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-[1.5rem] text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:bg-white dark:focus:bg-gray-950 focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all dark:shadow-inner outline-none">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400 dark:text-gray-500 group-focus-within:text-[var(--theme-color)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- ARCHIV / NEU (KI) / + NEU BUTTONS OBEN RECHTS --}}
            @if(!$showArchived)
                <button wire:click="toggleArchiveMode"
                        class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-400 px-4 py-3 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-inner hover:bg-gray-50 dark:hover:bg-gray-900 hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 whitespace-nowrap shrink-0">
                    Archiv öffnen
                </button>
                <button type="button" wire:click="openQuickAiModal"
                        class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-3 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest shadow-sm hover:scale-[1.02] transition-all flex items-center justify-center gap-1.5 whitespace-nowrap shrink-0"
                        title="KI-Produkt per Knopfdruck vollautomatisch anlegen">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Neu (KI)</span>
                </button>
                <button wire:click="createDraft"
                        class="bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white dark:text-gray-900 px-4 py-3 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-black/90 dark:hover:bg-white hover:scale-[1.02] transition-all flex items-center justify-center gap-1.5 whitespace-nowrap shrink-0">
                    <span class="text-sm leading-none">+</span>
                    <span>Neu</span>
                </button>
            @else
                <button wire:click="toggleArchiveMode"
                        class="bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-400 px-5 py-3 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest dark:shadow-inner hover:bg-gray-50 dark:hover:bg-gray-900 hover:text-gray-900 dark:hover:text-white transition-all flex items-center justify-center gap-2 whitespace-nowrap shrink-0">
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

                            <div class="min-w-0 flex-1 select-none cursor-default">
                                <div class="flex items-center gap-2.5 flex-wrap mb-1" x-data="{ copied: false }">
                                    <div class="flex items-center gap-1.5 min-w-0 max-w-full">
                                        <h3 class="font-serif text-base sm:text-lg font-bold text-gray-900 dark:text-white truncate">{{ $prod->name }}</h3>
                                        <button type="button"
                                                @click.stop="navigator.clipboard.writeText(@js($prod->name)); copied = true; setTimeout(() => copied = false, 2000)"
                                                title="Produktnamen kopieren"
                                                class="shrink-0 p-1 rounded-lg text-gray-400 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-all cursor-pointer"
                                                :class="copied ? '!text-emerald-500 !bg-emerald-50 dark:!bg-emerald-500/10' : ''">
                                            <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            <svg x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </div>
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
                                       class="inline-flex items-center gap-1.5 group/rating hover:opacity-90 transition-opacity cursor-pointer"
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
                                    <span class="relative group/sold inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold cursor-default select-none transition-colors {{ $soldQty > 0 ? 'bg-emerald-50/80 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200/70 dark:border-emerald-500/20' : 'text-gray-400 dark:text-gray-500' }}">
                                        <svg class="w-3.5 h-3.5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        <span>{{ $soldQty }}&times; verkauft</span>
                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 dark:bg-gray-800 px-2.5 py-1 text-[10px] font-bold text-white shadow-lg opacity-0 group-hover/sold:opacity-100 transition-opacity duration-200 z-30">
                                            Umsatz: {{ number_format($revenueCents / 100, 2, ',', '.') }} €
                                        </span>
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

                            {{-- 1-Klick Sortiment-Switch --}}
                            <button type="button"
                                    wire:click="toggleAssortment('{{ $prod->id }}')"
                                    title="Per Knopfdruck im Sortiment aktivieren / pausieren"
                                    class="px-3.5 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-xl border flex items-center gap-2 transition-all {{ $prod->status == 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30 hover:bg-emerald-100' : 'bg-white dark:bg-gray-950 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-emerald-400' }}">
                                <span class="w-2 h-2 rounded-full {{ $prod->status == 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400' }}"></span>
                                <span>{{ $prod->status == 'active' ? 'Im Sortiment' : 'Pausiert' }}</span>
                            </button>

                            {{-- 1-Klick Herkunfts-Siegel Switch (Made in Germany / Handveredelt in DE / Aus) --}}
                            @if($prod->type === 'physical')
                                @php $originMode = $prod->getOriginBadgeMode(); @endphp
                                <button type="button"
                                        wire:click="toggleOriginBadge('{{ $prod->id }}')"
                                        title="Per Klick umschalten: Made in Germany ↔ Handveredelt in Deutschland ↔ Aus"
                                        class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-xl border flex items-center gap-1.5 transition-all cursor-pointer {{ $originMode === 'made_in_germany' ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-500/40 hover:bg-amber-100' : ($originMode === 'handcrafted_in_germany' ? 'bg-stone-50 dark:bg-stone-800/70 text-stone-700 dark:text-stone-200 border-stone-300 dark:border-stone-600 hover:border-amber-400' : 'bg-white dark:bg-gray-950 text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-800 hover:border-gray-400 opacity-75') }}">
                                    @if($originMode !== 'none')
                                        <span class="inline-flex flex-col w-3.5 h-2.5 rounded-[2px] overflow-hidden shrink-0 border border-black/15">
                                            <span class="h-1/3 w-full bg-black"></span>
                                            <span class="h-1/3 w-full bg-[#DD0000]"></span>
                                            <span class="h-1/3 w-full bg-[#FFCE00]"></span>
                                        </span>
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                    @endif
                                    <span>{{ $originMode === 'made_in_germany' ? 'Made in Germany' : ($originMode === 'handcrafted_in_germany' ? 'Handveredelt in DE' : 'Herkunft: Aus') }}</span>
                                </button>
                            @endif

                            @if(!empty($prod->reorder_url))
                                <a href="{{ $prod->reorder_url }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30 hover:bg-blue-600 hover:text-white transition-all"
                                   title="Rohlinge direkt beim Händler nachbestellen">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <span>Kauflink</span>
                                </a>
                            @endif

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

                        {{-- 1-Klick Sortiment Switcher --}}
                        <div class="absolute {{ $currentLayout === 'medium' ? 'top-4 right-4' : 'top-6 right-6' }} z-20">
                            <button type="button"
                                    wire:click="toggleAssortment('{{ $prod->id }}')"
                                    title="Per Knopfdruck im Sortiment aktivieren / pausieren"
                                    class="{{ $currentLayout === 'medium' ? 'px-2.5 py-1.5 text-[8px]' : 'px-3.5 py-2 text-[9px]' }} font-black uppercase tracking-widest rounded-xl border shadow-sm flex items-center gap-2 transition-all backdrop-blur-md {{ $prod->status == 'active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/30 hover:bg-emerald-100' : 'bg-white dark:bg-gray-950/80 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-emerald-400' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $prod->status == 'active' ? 'bg-emerald-500 dark:shadow-[0_0_8px_currentColor] animate-pulse' : 'bg-gray-400 dark:bg-gray-500' }}"></span>
                                {{ $prod->status == 'active' ? 'Im Sortiment' : 'Pausiert' }}
                            </button>
                        </div>

                        {{-- Bild mit Schleifen --}}
                        <div class="{{ $currentLayout === 'medium' ? 'aspect-square rounded-2xl mb-4 mt-1.5' : 'aspect-square rounded-[1.5rem] mb-6 mt-2' }} bg-gray-100 dark:bg-gray-950 overflow-hidden relative border border-gray-200 dark:border-gray-800 dark:shadow-inner group-hover:border-gray-300 dark:group-hover:border-gray-700 transition-colors">

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
                                <img src="{{ $prodCardMainUrl }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 group-hover:opacity-100">
                            @elseif(!empty($prod->media_gallery[0]) && isset($prod->media_gallery[0]['type']) && $prod->media_gallery[0]['type'] == 'video')
                                <video src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity"></video>
                            @else
                                <div class="flex items-center justify-center h-full text-gray-400 dark:text-gray-700 text-[10px] flex-col gap-3 font-black uppercase tracking-widest">
                                    <svg class="w-10 h-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <span>Kein Bild</span>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5 min-w-0 {{ $currentLayout === 'medium' ? 'mb-1' : 'mb-1.5' }}" x-data="{ copied: false }">
                            <h3 class="font-serif {{ $currentLayout === 'medium' ? 'text-base' : 'text-lg md:text-xl' }} font-bold text-gray-900 dark:text-white truncate tracking-wide select-none cursor-default">{{ $prod->name }}</h3>
                            <button type="button"
                                    @click.stop="navigator.clipboard.writeText(@js($prod->name)); copied = true; setTimeout(() => copied = false, 2000)"
                                    title="Produktnamen kopieren"
                                    class="shrink-0 p-1 rounded-lg text-gray-400 hover:text-[var(--theme-color)] hover:bg-gray-100 dark:hover:bg-gray-800 transition-all cursor-pointer"
                                    :class="copied ? '!text-emerald-500 !bg-emerald-50 dark:!bg-emerald-500/10' : ''">
                                <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <svg x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>
                        </div>

                        {{-- Sternebewertung & Verkäufe --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 select-none cursor-default {{ $currentLayout === 'medium' ? 'mb-2' : 'mb-3' }}">
                            <a href="{{ route('admin.product-control-reviews', ['filterStatus' => $pendingReviewsCount > 0 ? 'pending' : 'approved', 'search' => $prod->name]) }}"
                               class="inline-flex items-center gap-1.5 group/rating hover:opacity-90 transition-opacity cursor-pointer"
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

                            <span class="relative group/sold inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold cursor-default select-none transition-colors {{ $soldQty > 0 ? 'bg-emerald-50/80 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200/70 dark:border-emerald-500/20' : 'text-gray-400 dark:text-gray-500' }}">
                                <svg class="w-3.5 h-3.5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span>{{ $soldQty }}&times; verkauft</span>
                                <span class="pointer-events-none absolute -top-8 right-0 whitespace-nowrap rounded-lg bg-gray-900 dark:bg-gray-800 px-2.5 py-1 text-[10px] font-bold text-white shadow-lg opacity-0 group-hover/sold:opacity-100 transition-opacity duration-200 z-30">
                                    Umsatz: {{ number_format($revenueCents / 100, 2, ',', '.') }} €
                                </span>
                            </span>
                        </div>

                        <div class="{{ $currentLayout === 'medium' ? 'mb-3.5' : 'mb-6' }} flex items-center justify-between gap-2 select-none cursor-default">
                            <p class="{{ $currentLayout === 'medium' ? 'text-xs sm:text-sm' : 'text-sm md:text-base' }} text-[var(--theme-color)] font-mono font-bold dark:drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]">{{ number_format($prod->price / 100, 2, ',', '.') }} €</p>
                            @if(!empty($prod->sku))
                                <span class="px-2 py-0.5 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200/80 dark:border-gray-800 text-[9px] font-mono font-bold text-gray-500 dark:text-gray-400 tracking-wider" title="Artikelnummer (SKU)">
                                    {{ $prod->sku }}
                                </span>
                            @endif
                        </div>

                        {{-- Lagerbestand & Herkunfts-Siegel Anzeige --}}
                        <div class="{{ $currentLayout === 'medium' ? 'mb-3.5' : 'mb-6' }} min-h-8 flex flex-wrap items-center gap-2"
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

                                {{-- Direkt-Kauflink beim Lieferanten --}}
                                @if(!empty($prod->reorder_url))
                                    <a href="{{ $prod->reorder_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30 dark:shadow-inner hover:bg-blue-600 dark:hover:bg-blue-500 hover:text-white hover:border-blue-600 transition-all z-10" title="Rohlinge beim Lieferanten nachbestellen">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        Kauflink
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

                            {{-- 1-Klick Herkunfts-Siegel Switch (Made in Germany / Handveredelt in DE / Aus) --}}
                            @if($prod->type === 'physical')
                                @php $cardOriginMode = $prod->getOriginBadgeMode(); @endphp
                                <button type="button"
                                        wire:click="toggleOriginBadge('{{ $prod->id }}')"
                                        title="Per Klick umschalten: Made in Germany ↔ Handveredelt in Deutschland ↔ Aus"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all cursor-pointer z-10 {{ $cardOriginMode === 'made_in_germany' ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-500/40 hover:bg-amber-100' : ($cardOriginMode === 'handcrafted_in_germany' ? 'bg-stone-50 dark:bg-stone-800/70 text-stone-700 dark:text-stone-200 border-stone-300 dark:border-stone-600 hover:border-amber-400' : 'bg-white dark:bg-gray-950 text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-800 hover:border-gray-400 opacity-75') }}">
                                    @if($cardOriginMode !== 'none')
                                        <span class="inline-flex flex-col w-3.5 h-2.5 rounded-[2px] overflow-hidden shrink-0 border border-black/15">
                                            <span class="h-1/3 w-full bg-black"></span>
                                            <span class="h-1/3 w-full bg-[#DD0000]"></span>
                                            <span class="h-1/3 w-full bg-[#FFCE00]"></span>
                                        </span>
                                    @else
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                    @endif
                                    <span>{{ $cardOriginMode === 'made_in_germany' ? 'Made in Germany' : ($cardOriginMode === 'handcrafted_in_germany' ? 'Handveredelt in DE' : 'Herkunft: Aus') }}</span>
                                </button>
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

    {{-- ===================================================================== --}}
    {{-- 1-KLICK KI-PRODUKT-ASSISTENT MODAL                                    --}}
    {{-- ===================================================================== --}}
    @if($showQuickAiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-2xl max-w-2xl w-full p-6 sm:p-8 relative overflow-hidden max-h-[92vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 text-[10px] font-black uppercase tracking-widest mb-2">
                            ⚡ 1-Link KI-Produkt-Pipeline (Vollautomatisch)
                        </span>
                        <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white">Neues Produkt per Knopfdruck anlegen</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Gib einfach nur den <strong>Link zum Rohling-Produkt</strong> an. Alle leeren Felder (Name, Maße, EK/VK, 3D-Animation, Overlay, Maßblatt-Bild &amp; Gravurzone) werden logisch von der KI errechnet.
                        </p>
                    </div>
                    <button type="button" wire:click="closeQuickAiModal" class="p-2 rounded-xl text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- 1. EINZIGES PFLICHTFELD: ROHLING-KAUFLINK --}}
                <div class="mb-5 p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-500/30">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-emerald-800 dark:text-emerald-300">
                            1. Link zum Rohling-Produkt (Temu / Amazon / Großhandel) * <span class="text-emerald-600 dark:text-emerald-400 font-bold">(Einziges Pflichtfeld)</span>
                        </label>
                        <button type="button" wire:click="analyzeQuickAiUrl" class="text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400 hover:underline flex items-center gap-1">
                            <span>✨ Werte vorab in Felder laden</span>
                        </button>
                    </div>
                    <input type="url" wire:model="quickAiReorderUrl" placeholder="https://www.temu.com/de/... oder Händler-Link hier einfügen" class="w-full px-4 py-3 rounded-xl bg-white dark:bg-gray-950 border border-emerald-300 dark:border-emerald-500/40 text-xs font-mono text-gray-900 dark:text-white outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20">
                    @error('quickAiReorderUrl') <span class="text-[10px] text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-emerald-700/80 dark:text-emerald-400/80 mt-1.5">
                        Die KI analysiert aus dem Link automatisch Originalbild (<code>top_gallery_url</code>), Gebindegröße, Stück-EK, optimale Marge, Geometrie und legt auf der letzten Galerie-Position das technische Maßblatt an.
                    </p>
                </div>

                {{-- Geometrie-Modus / Schnellvorlagen (Optional) --}}
                <div class="mb-4">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Geometrie-Erkennung / Optionales Preset</label>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <button type="button" wire:click="applyQuickAiPreset('auto')"
                                class="p-2.5 rounded-2xl border text-left transition-all {{ $quickAiPreset === 'auto' ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300' }}">
                            <div class="text-[10px] font-black uppercase tracking-wider">🤖 KI-Auto</div>
                            <div class="text-[9px] opacity-75 mt-0.5">Aus Link erkennen</div>
                        </button>
                        <button type="button" wire:click="applyQuickAiPreset('seelen-pfote-hundemarke')"
                                class="p-2.5 rounded-2xl border text-left transition-all {{ $quickAiPreset === 'seelen-pfote-hundemarke' ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300' }}">
                            <div class="text-[10px] font-black uppercase tracking-wider">Hundemarke</div>
                            <div class="text-[9px] opacity-75 mt-0.5">40x22 mm Knochen</div>
                        </button>
                        <button type="button" wire:click="applyQuickAiPreset('seelen-talisman')"
                                class="p-2.5 rounded-2xl border text-left transition-all {{ $quickAiPreset === 'seelen-talisman' ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300' }}">
                            <div class="text-[10px] font-black uppercase tracking-wider">Edelstahl-Barren</div>
                            <div class="text-[9px] opacity-75 mt-0.5">28x50 mm Talisman</div>
                        </button>
                        <button type="button" wire:click="applyQuickAiPreset('heavytool-opener')"
                                class="p-2.5 rounded-2xl border text-left transition-all {{ $quickAiPreset === 'heavytool-opener' ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300' }}">
                            <div class="text-[10px] font-black uppercase tracking-wider">Alu-Öffner</div>
                            <div class="text-[9px] opacity-75 mt-0.5">150 Stk. (0,29 € EK)</div>
                        </button>
                        <button type="button" wire:click="applyQuickAiPreset('bambus-pen')"
                                class="p-2.5 rounded-2xl border text-left transition-all {{ $quickAiPreset === 'bambus-pen' ? 'border-emerald-500 bg-emerald-50/70 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300' : 'border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300' }}">
                            <div class="text-[10px] font-black uppercase tracking-wider">Bambus-Stift</div>
                            <div class="text-[9px] opacity-75 mt-0.5">24 Stk. (0,30 € EK)</div>
                        </button>
                    </div>
                </div>

                {{-- 2. OPTIONALE FELDER (Werden bei Leerfeld automatisch von der KI berechnet) --}}
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        2. Optionale Angaben (Wenn leer, werden alle Werte logisch von der KI errechnet)
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Produktname <span class="font-normal opacity-70">(Optional)</span></label>
                        <input type="text" wire:model="quickAiName" placeholder="Automatisch durch KI aus Link..." class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Artikelnummer / SKU <span class="font-normal opacity-70">(Optional)</span></label>
                        <input type="text" wire:model="quickAiSku" placeholder="Auto-SKU (z.B. STEEL-PAW-001)" class="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm font-mono font-bold text-gray-900 dark:text-white outline-none focus:border-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">VK Brutto (€) <span class="font-normal opacity-70">(Opt.)</span></label>
                        <input type="text" wire:model="quickAiPrice" placeholder="KI (~90% Marge)" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm font-mono font-bold text-emerald-600 dark:text-emerald-400 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Stück-EK (€) <span class="font-normal opacity-70">(Opt.)</span></label>
                        <input type="text" wire:model="quickAiPurchasePrice" placeholder="KI aus Link" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm font-mono font-bold text-gray-900 dark:text-white outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Bestand <span class="font-normal opacity-70">(Opt.)</span></label>
                        <input type="number" wire:model="quickAiQuantity" placeholder="0 (Startbestand)" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm font-bold text-gray-900 dark:text-white outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Gewicht (g) <span class="font-normal opacity-70">(Opt.)</span></label>
                        <input type="number" wire:model="quickAiWeight" placeholder="Auto (g)" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm font-bold text-gray-900 dark:text-white outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Breite (mm) <span class="font-normal opacity-70">(Opt.)</span></label>
                        <input type="number" wire:model="quickAiWidth" placeholder="Auto (mm)" class="w-full px-3.5 py-2 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-900 dark:text-white outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Höhe (mm) <span class="font-normal opacity-70">(Opt.)</span></label>
                        <input type="number" wire:model="quickAiHeight" placeholder="Auto (mm)" class="w-full px-3.5 py-2 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-900 dark:text-white outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5">Tiefe (mm) <span class="font-normal opacity-70">(Opt.)</span></label>
                        <input type="number" step="0.1" wire:model="quickAiLength" placeholder="Auto (mm)" class="w-full px-3.5 py-2 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs font-bold text-gray-900 dark:text-white outline-none">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" wire:click="closeQuickAiModal" class="px-5 py-3 rounded-2xl border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                        Abbrechen
                    </button>
                    <button type="button" wire:click="createQuickAiProduct" class="px-6 py-3 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-black uppercase tracking-widest shadow-lg flex items-center gap-2 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Produkt sofort verkaufsfertig anlegen</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
