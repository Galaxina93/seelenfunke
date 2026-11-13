<div class="mb-10">
    {{-- Header & Ansichts-Steuerung --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-gray-200 pb-4 mb-6">
        <div class="flex items-center gap-3">
            <h3 class="text-lg font-bold text-gray-800 uppercase tracking-wider">
                Unser Sortiment
            </h3>
            @if(isset($catalogProducts))
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 border border-gray-200">
                    {{ $catalogProducts->total() }} Artikel
                </span>
            @endif
        </div>

        @if(!empty($dbProducts))
            <div class="flex flex-wrap items-center gap-2.5">
                {{-- Sortierung --}}
                <div class="relative">
                    <select wire:model.live="sortBy" aria-label="Sortierung" class="h-9 pl-3 pr-8 py-0 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer appearance-none">
                        <option value="default">Sortierung: Neueste</option>
                        <option value="name_asc">Name: A – Z</option>
                        <option value="price_asc">Preis: Aufsteigend</option>
                        <option value="price_desc">Preis: Absteigend</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center px-2.5 pointer-events-none text-gray-500" aria-hidden="true">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>

                {{-- Pro Seite Auswahl --}}
                <div class="flex items-center bg-gray-50 p-1 rounded-xl border border-gray-200" role="group" aria-label="Artikel pro Seite">
                    @foreach([6, 12, 24] as $countOption)
                        <button type="button"
                                wire:click="setPerPage({{ $countOption }})"
                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all {{ (int)($perPage ?? 6) === $countOption ? 'bg-white text-gray-900 shadow-sm border border-gray-200/80' : 'text-gray-500 hover:text-gray-800' }}"
                                title="{{ $countOption }} Artikel pro Seite">
                            {{ $countOption }}
                        </button>
                    @endforeach
                </div>

                {{-- Ansichts-Umschalter (Grid vs. Kompakt-Liste) --}}
                <div class="flex items-center bg-gray-50 p-1 rounded-xl border border-gray-200" role="group" aria-label="Ansicht wechseln">
                    <button type="button"
                            wire:click="setViewMode('grid')"
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all {{ ($viewMode ?? 'grid') === 'grid' ? 'bg-white text-gray-900 shadow-sm border border-gray-200/80' : 'text-gray-500 hover:text-gray-800' }}"
                            title="Kachel-Ansicht (wie im Shop)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                        <span class="hidden sm:inline">Kacheln</span>
                    </button>
                    <button type="button"
                            wire:click="setViewMode('list')"
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all {{ ($viewMode ?? 'grid') === 'list' ? 'bg-white text-gray-900 shadow-sm border border-gray-200/80' : 'text-gray-500 hover:text-gray-800' }}"
                            title="Kompakt-Ansicht (Schnellauswahl)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        <span class="hidden sm:inline">Kompakt</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if(empty($dbProducts))
        <div class="flex flex-col items-center justify-center py-12 text-gray-500 bg-gray-50 rounded-xl border border-dashed border-gray-300">
            <span class="text-sm font-medium">Aktuell keine Produkte verfügbar.</span>
        </div>
    @else
        {{-- Filter & Search Bar (analog zu /shop/) --}}
        <div class="bg-gray-50/70 rounded-2xl border border-gray-200/80 p-4 mb-8 shadow-sm">
            {{-- Suchleiste --}}
            <div class="w-full mb-3.5">
                <div class="relative w-full group">
                    <input wire:model.live.debounce.300ms="search"
                           type="text"
                           placeholder="Sortiment durchsuchen (Name, Beschreibung oder Artikelnummer)..."
                           class="w-full pl-11 pr-10 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all placeholder-gray-400">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    @if(($search ?? '') !== '')
                        <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-red-500 transition-colors" title="Suche zurücksetzen">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Filter-Pills & Dropdowns --}}
            <div class="flex flex-col xl:flex-row gap-3 justify-between items-stretch xl:items-center">
                <div class="flex flex-wrap gap-2 w-full xl:w-auto">
                    {{-- Personalisierbar Filter --}}
                    <div class="flex bg-white p-1 rounded-xl border border-gray-200 overflow-x-auto no-scrollbar">
                        <button type="button" wire:click="$set('filterPersonalizable', 'all')" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ ($filterPersonalizable ?? 'all') === 'all' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-500 hover:text-gray-800' }}">
                            Alle
                        </button>
                        <button type="button" wire:click="$set('filterPersonalizable', 'yes')" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ ($filterPersonalizable ?? 'all') === 'yes' ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:text-primary' }}">
                            Personalisiert
                        </button>
                        <button type="button" wire:click="$set('filterPersonalizable', 'no')" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ ($filterPersonalizable ?? 'all') === 'no' ? 'bg-gray-200 text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}">
                            Standard
                        </button>
                    </div>

                    {{-- Staffelpreise Filter Pill --}}
                    @if(($tierPricingCount ?? 0) > 0)
                        <div class="flex bg-white p-1 rounded-xl border border-gray-200">
                            <button type="button" wire:click="toggleTierPricingFilter" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ !empty($filterTierPricing) ? 'bg-emerald-600 text-white shadow-sm' : 'text-emerald-700 hover:bg-emerald-50' }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                <span>Staffelpreise</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black leading-none {{ !empty($filterTierPricing) ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700' }}">{{ $tierPricingCount }}</span>
                            </button>
                        </div>
                    @endif

                    {{-- Angebote / Sale Filter Pill --}}
                    @if(($saleCount ?? 0) > 0)
                        <div class="flex bg-white p-1 rounded-xl border border-gray-200">
                            <button type="button" wire:click="toggleSaleFilter" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ !empty($filterSale) ? 'bg-red-500 text-white shadow-sm' : 'text-red-600 hover:bg-red-50' }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                <span>Angebote</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black leading-none {{ !empty($filterSale) ? 'bg-white/20 text-white' : 'bg-red-100 text-red-600' }}">{{ $saleCount }}</span>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Anlass & Kategorie Dropdowns --}}
                @if(!empty($availableHolidays) || (isset($categories) && $categories->isNotEmpty()))
                    <div class="flex flex-col sm:flex-row gap-2.5 w-full xl:w-auto justify-end">
                        @if(!empty($availableHolidays))
                            <div class="relative w-full sm:w-48">
                                <select wire:model.live="filterHoliday" aria-label="Anlass filtern" class="w-full h-10 pl-3.5 pr-9 py-0 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer appearance-none truncate">
                                    <option value="">Alle Anlässe</option>
                                    @foreach($availableHolidays as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500" aria-hidden="true">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </div>
                        @endif

                        @if(isset($categories) && $categories->isNotEmpty())
                            <div class="relative w-full sm:w-52">
                                <select wire:model.live="filterCategory" aria-label="Kategorie filtern" class="w-full h-10 pl-3.5 pr-9 py-0 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer appearance-none truncate">
                                    <option value="">Alle Kategorien</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-gray-500" aria-hidden="true">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Aktive Filter Chips --}}
            @if(($search ?? '') !== '' || ($filterPersonalizable ?? 'all') !== 'all' || !empty($filterSale) || !empty($filterTierPricing) || ($filterCategory ?? '') !== '' || ($filterHoliday ?? '') !== '' || ($sortBy ?? 'default') !== 'default')
                <div class="mt-3.5 pt-3.5 border-t border-gray-200/70 flex flex-wrap items-center gap-2">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mr-1">Aktive Filter:</span>

                    @if(($search ?? '') !== '')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white border border-gray-200 text-gray-700 text-xs font-medium">
                            „{{ $search }}“
                            <button type="button" wire:click="$set('search', '')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif

                    @if(!empty($filterTierPricing))
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-bold uppercase tracking-wider border border-emerald-200">
                            Mit Staffelpreisen
                            <button type="button" wire:click="$set('filterTierPricing', false)" class="hover:text-emerald-900"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif

                    @if(!empty($filterSale))
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-50 text-red-600 text-xs font-bold uppercase tracking-wider border border-red-200">
                            Nur Angebote
                            <button type="button" wire:click="$set('filterSale', false)" class="hover:text-red-800"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif

                    @if(($filterPersonalizable ?? 'all') !== 'all')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white text-gray-800 text-xs font-bold uppercase tracking-wider border border-gray-200">
                            {{ $filterPersonalizable === 'yes' ? 'Personalisierbar' : 'Standard-Artikel' }}
                            <button type="button" wire:click="$set('filterPersonalizable', 'all')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif

                    @if(($filterHoliday ?? '') !== '')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-pink-50 text-pink-600 text-xs font-bold uppercase tracking-wider border border-pink-100">
                            Anlass: {{ $availableHolidays[$filterHoliday] ?? ucfirst($filterHoliday) }}
                            <button type="button" wire:click="$set('filterHoliday', '')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif

                    @if(($filterCategory ?? '') !== '')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-900 text-white text-xs font-medium">
                            Kategorie: {{ isset($categories) ? ($categories->firstWhere('id', $filterCategory)->name ?? 'Ausgewählt') : 'Ausgewählt' }}
                            <button type="button" wire:click="$set('filterCategory', '')" class="hover:text-red-400"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </span>
                    @endif

                    <button type="button" wire:click="resetFilters" class="text-xs font-bold text-red-500 hover:underline ml-auto">
                        Alle Filter zurücksetzen
                    </button>
                </div>
            @endif
        </div>

        {{-- Produktliste / Grid --}}
        <div wire:loading.class="opacity-50 pointer-events-none"
             wire:target="search, filterPersonalizable, filterSale, toggleSaleFilter, filterTierPricing, toggleTierPricingFilter, filterCategory, filterHoliday, sortBy, perPage, setPerPage, setViewMode, resetFilters, gotoPage, nextPage, previousPage"
             class="transition-opacity duration-200">

            @if(isset($catalogProducts) && $catalogProducts->isEmpty())
                <div class="text-center py-16 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white text-gray-400 mb-4 shadow-sm">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <h4 class="text-lg font-serif font-bold text-gray-900 mb-1">Keine passenden Produkte gefunden</h4>
                    <p class="text-sm text-gray-500 mb-5 max-w-md mx-auto">Zu deiner aktuellen Filterauswahl wurden leider keine Artikel im Sortiment gefunden.</p>
                    <button type="button" wire:click="resetFilters" class="px-5 py-2 bg-gray-900 text-white rounded-full font-bold text-xs shadow hover:bg-black transition-all">
                        Filter zurücksetzen
                    </button>
                </div>
            @elseif(($viewMode ?? 'grid') === 'grid' && isset($catalogProducts))
                {{-- KACHEL-ANSICHT (Analog zu /shop/) --}}
                <style>
                    .sf-card {
                        transform: translate3d(0, 0, 0);
                        transition:
                            transform 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                            box-shadow 0.75s cubic-bezier(0.22, 0.61, 0.36, 1),
                            border-color 0.75s cubic-bezier(0.22, 0.61, 0.36, 1);
                        will-change: transform;
                    }
                    .sf-card:hover,
                    .sf-card.sf-is-active {
                        transform: translate3d(0, -6px, 0);
                        border-color: var(--theme-color-30, rgba(197, 160, 89, 0.35));
                        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
                    }
                    .sf-card-img {
                        transform: scale(1) translateZ(0);
                        transition:
                            transform 1.35s cubic-bezier(0.22, 0.61, 0.36, 1),
                            opacity 0.55s cubic-bezier(0.22, 0.61, 0.36, 1);
                        will-change: transform, opacity;
                    }
                    .sf-card:hover .sf-card-img,
                    .sf-card.sf-is-active .sf-card-img {
                        transform: scale(1.045) translateZ(0);
                    }
                    .sf-card.sf-has-hover:hover .sf-card-main-layer,
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
                    .sf-card.sf-has-hover:hover .sf-card-hover-layer,
                    .sf-card.sf-has-hover.sf-is-active .sf-card-hover-layer {
                        opacity: 1;
                        transform: scale(1.045) translateZ(0);
                    }
                    .sf-card.sf-has-hover:hover .sf-ai-badge-main,
                    .sf-card.sf-has-hover.sf-is-active .sf-ai-badge-main {
                        opacity: 0;
                    }
                    .sf-ai-badge-hover {
                        opacity: 0;
                        transition: opacity 0.45s ease;
                    }
                    .sf-card.sf-has-hover:hover .sf-ai-badge-hover,
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
                    .sf-card:hover .sf-card-sheen,
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
                    .sf-card:hover .sf-card-sparkle,
                    .sf-card.sf-is-active .sf-card-sparkle {
                        opacity: 0.92;
                        transform: scale(1) translateZ(0);
                    }
                    .sf-card:hover .sf-card-sparkle-1, .sf-card.sf-is-active .sf-card-sparkle-1 { transition-delay: 0.04s; }
                    .sf-card:hover .sf-card-sparkle-2, .sf-card.sf-is-active .sf-card-sparkle-2 { transition-delay: 0.11s; }
                    .sf-card:hover .sf-card-sparkle-3, .sf-card.sf-is-active .sf-card-sparkle-3 { transition-delay: 0.18s; }
                    .sf-card:hover .sf-card-sparkle-4, .sf-card.sf-is-active .sf-card-sparkle-4 { transition-delay: 0.25s; }
                    .sf-card:hover .sf-card-sparkle-5, .sf-card.sf-is-active .sf-card-sparkle-5 { transition-delay: 0.32s; }
                    .sf-card:hover .sf-card-sparkle-6, .sf-card.sf-is-active .sf-card-sparkle-6 { transition-delay: 0.39s; }
                    .sf-card-sparkle > svg { display: block; width: 100%; height: 100%; will-change: transform; }
                    .sf-card-sparkle-1 > svg { animation: sfSparkleFloatA 3.4s ease-in-out infinite; }
                    .sf-card-sparkle-2 > svg { animation: sfSparkleFloatB 4.0s ease-in-out infinite 0.4s; }
                    .sf-card-sparkle-3 > svg { animation: sfSparkleFloatB 3.2s ease-in-out infinite 0.9s; }
                    .sf-card-sparkle-4 > svg { animation: sfSparkleFloatA 3.8s ease-in-out infinite 1.3s; }
                    .sf-card-sparkle-5 > svg { animation: sfSparkleFloatA 4.2s ease-in-out infinite 0.7s; }
                    .sf-card-sparkle-6 > svg { animation: sfSparkleFloatB 3.6s ease-in-out infinite 1.6s; }
                </style>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($catalogProducts as $product)
                        @php
                            $projectedTemplate = null;
                            if (!empty($filterHoliday) && $product->relationLoaded('templates') && $product->templates->isNotEmpty()) {
                                $projectedTemplate = $product->templates->first();
                            }

                            $resolvedMainMeta = $projectedTemplate && $projectedTemplate->preview_image
                                ? [
                                    'type'            => 'image',
                                    'path'            => $projectedTemplate->preview_image,
                                    'url'             => $projectedTemplate->preview_image_url,
                                    'poster_url'      => $projectedTemplate->preview_image_url,
                                    'is_ai_generated' => (bool) ($projectedTemplate->is_ai_generated ?? true),
                                ]
                                : $product->resolveMainMediaMeta();

                            $resolvedMainMediaType = $resolvedMainMeta['type'] ?? 'image';
                            $resolvedMainImageUrl = $resolvedMainMeta['url'] ?? null;
                            $resolvedMainPosterUrl = $resolvedMainMeta['poster_url'] ?? null;
                            $isAiCardImage = (bool) ($resolvedMainMeta['is_ai_generated'] ?? false);

                            $resolvedHoverMeta = $product->resolveHoverMediaMeta($resolvedMainMeta['path'] ?? null);
                            $hasHoverMedia = !empty($resolvedHoverMeta['url']);
                            $resolvedHoverMediaType = $resolvedHoverMeta['type'] ?? 'image';
                            $resolvedHoverMediaUrl = $resolvedHoverMeta['url'] ?? null;
                            $isAiHoverMedia = (bool) ($resolvedHoverMeta['is_ai_generated'] ?? false);
                            $isAiHoverImage = $isAiHoverMedia;
                            $isSmallBusiness = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);

                            $calcProduct = $dbProducts[$product->id] ?? null;
                            $displayPrice = $calcProduct['display_price'] ?? ($product->gross_price / 100);
                        @endphp

                        <div wire:key="calc-grid-{{ $product->id }}"
                             onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                             onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                             @mouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter($el)"
                             @mouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave($el)"
                             @if($projectedTemplate)
                                 wire:click="openConfig('{{ $product->id }}', '{{ $projectedTemplate->id }}')"
                             @else
                                 wire:click="openConfig('{{ $product->id }}')"
                             @endif
                             class="sf-card {{ $hasHoverMedia ? 'sf-has-hover' : '' }} group relative flex flex-col h-full bg-white rounded-[2rem] p-3 border border-gray-100 shadow-sm cursor-pointer">

                            {{-- Bild-Container --}}
                            <div class="aspect-square bg-gray-50 rounded-3xl overflow-hidden mb-4 relative border border-gray-100">
                                <div class="w-full h-full relative">
                                    @if($resolvedMainImageUrl && $resolvedMainMediaType === 'video')
                                        <video src="{{ $resolvedMainImageUrl }}"
                                               @if($resolvedMainPosterUrl) poster="{{ $resolvedMainPosterUrl }}" @endif
                                               @if($isAiCardImage) data-is-ai-generated="true" @endif
                                               data-sf-main-video
                                               class="w-full h-full object-cover sf-card-main-layer sf-card-img"
                                               muted autoplay loop playsinline></video>
                                    @elseif($resolvedMainImageUrl)
                                        <img src="{{ $resolvedMainImageUrl }}"
                                             alt="{{ $projectedTemplate ? $projectedTemplate->name : $product->name }}"
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
                                                 alt="{{ $resolvedHoverMeta['label'] ?? ($product->name . ' – Hover-Ansicht') }}"
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

                                @if($hasHoverMedia && $isAiHoverImage)
                                    <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                          class="sf-ai-badge-hover pointer-events-none select-none absolute bottom-2 right-2 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                        KI-generiert
                                    </span>
                                @endif

                                <div class="sf-card-sheen pointer-events-none z-10" aria-hidden="true"></div>
                                <span class="sf-card-sparkle sf-card-sparkle-1 pointer-events-none absolute top-[14%] left-[15%] w-3.5 h-3.5 text-amber-200 drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-card-sparkle sf-card-sparkle-2 pointer-events-none absolute top-[17%] right-[15%] w-4 h-4 text-amber-300 drop-shadow-[0_0_7px_rgba(251,191,36,0.95)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-card-sparkle sf-card-sparkle-3 pointer-events-none absolute top-[48%] left-[11%] w-2.5 h-2.5 text-amber-100 drop-shadow-[0_0_5px_rgba(251,191,36,0.85)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-card-sparkle sf-card-sparkle-4 pointer-events-none absolute top-[44%] right-[12%] w-3 h-3 text-amber-200 drop-shadow-[0_0_6px_rgba(251,191,36,0.9)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-card-sparkle sf-card-sparkle-5 pointer-events-none absolute bottom-[21%] left-[16%] w-3.5 h-3.5 text-amber-200 drop-shadow-[0_0_6px_rgba(251,191,36,0.88)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>
                                <span class="sf-card-sparkle sf-card-sparkle-6 pointer-events-none absolute bottom-[16%] right-[16%] w-4 h-4 text-amber-300 drop-shadow-[0_0_7px_rgba(251,191,36,0.95)] z-10" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.8L21 11l-6.8 2.2L12 20l-2.2-6.8L3 11l6.8-2.2L12 2z"/></svg>
                                </span>

                                {{-- Badges oben links --}}
                                <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5 pointer-events-none z-20">
                                    @if($product->compare_at_price > $product->price)
                                        @php
                                            $discountPercent = (int) round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100);
                                        @endphp
                                        <span class="w-fit bg-red-600/95 backdrop-blur-sm text-white text-[10px] font-black px-2.5 py-1 rounded-full shadow-md tracking-wider leading-none border border-white/15">
                                            -{{ max(1, $discountPercent) }}%
                                        </span>
                                    @endif
                                    @if($product->created_at && $product->created_at->diffInDays() < 21)
                                        <span class="w-fit bg-white/95 backdrop-blur text-gray-900 text-[10px] font-black px-2.5 py-1 rounded-full shadow-md uppercase tracking-wider leading-none border border-gray-100">
                                            Neu
                                        </span>
                                    @endif
                                </div>

                                {{-- Personalisierbar / B2B Direkt-Badge oben rechts --}}
                                <div class="absolute top-3 right-3 pointer-events-none z-20">
                                    @if($product->is_personalizable)
                                        <span class="bg-gray-900/80 backdrop-blur text-white text-[9px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm">
                                            Konfigurierbar
                                        </span>
                                    @else
                                        <span class="bg-emerald-600/90 backdrop-blur text-white text-[9px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm">
                                            Direktwahl
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Produkt-Infos --}}
                            <div class="flex-1 flex flex-col px-2 pb-2">
                                {{-- Herkunfts-Siegel & Kategorien --}}
                                @php
                                    $originLabel = $product->getOriginBadgeLabel();
                                    $originMode = $product->getOriginBadgeMode();
                                @endphp
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

                                <h4 class="text-base font-serif font-bold text-gray-900 group-hover:text-primary transition-colors mb-1 leading-snug line-clamp-2">
                                    {{ $product->name }}
                                </h4>

                                {{-- Sternebewertung --}}
                                @php
                                    $ratingAvg = round((float) ($product->approved_reviews_avg ?? $product->average_rating ?? 0), 1);
                                    $ratingCount = (int) ($product->approved_reviews_count ?? $product->review_count ?? 0);
                                @endphp
                                <div class="inline-flex items-center gap-1.5 mb-2">
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
                                </div>

                                @if(!empty($product->short_description))
                                    <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed mb-3">{{ $product->short_description }}</p>
                                @endif

                                {{-- Staffelpreise Vorschau --}}
                                @if($product->tierPrices->isNotEmpty())
                                    <div class="mb-3 pt-2 border-t border-gray-100">
                                        <span class="text-[10px] font-bold uppercase text-emerald-700 block mb-1">Staffelpreise verfügbar:</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($product->tierPrices->sortBy('qty')->take(4) as $tier)
                                                <span class="text-[10px] bg-emerald-50 text-emerald-700 font-semibold px-1.5 py-0.5 rounded border border-emerald-100">
                                                    ab {{ $tier->qty }} Stk: -{{ 0 + $tier->percent }}%
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Preis & Action Button --}}
                                <div class="mt-auto pt-2 flex items-end justify-between border-t border-gray-50">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">
                                            {{ $product->tierPrices->isNotEmpty() ? 'Ab' : 'Einzelpreis' }}
                                        </span>
                                        <div class="flex items-baseline gap-2">
                                            <span class="font-bold text-lg font-serif text-gray-900 group-hover:text-primary transition-colors">
                                                {{ number_format($displayPrice, 2, ',', '.') }} €
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
                                        <span class="text-[10px] text-gray-500 font-medium leading-tight mt-0.5">
                                            {{ $isSmallBusiness ? 'Endpreis gem. § 19 UStG' : 'inkl. MwSt.' }} · <a href="{{ route('versand') }}" target="_blank" @click.stop class="underline hover:text-gray-600">zzgl. Versand</a>
                                        </span>
                                    </div>

                                    <div class="w-10 h-10 rounded-full bg-gray-900 text-white group-hover:bg-primary flex items-center justify-center shadow-md transition-all duration-300 group-hover:scale-110 shrink-0" title="Zur Kalkulation hinzufügen">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- KOMPAKT-ANSICHT (Horizontale B2B Schnellauswahl-Karten) --}}
                @php
                    $itemsToRender = isset($catalogProducts) ? $catalogProducts : collect($dbProducts);
                    $isSmallBusiness = filter_var(shop_setting('is_small_business', false), FILTER_VALIDATE_BOOLEAN);
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
                    @foreach($itemsToRender as $item)
                        @php
                            $isModel = $item instanceof \App\Models\Product\Product;
                            $product = $isModel ? $item : null;
                            $pId = $isModel ? $item->id : $item['id'];
                            $calcProduct = $dbProducts[$pId] ?? ($isModel ? null : $item);
                            $pName = $isModel ? $item->name : ($item['name'] ?? '');
                            $pDesc = $isModel ? $item->short_description : ($item['desc'] ?? '');

                            $projectedTemplate = null;
                            if ($isModel && !empty($filterHoliday) && $item->relationLoaded('templates') && $item->templates->isNotEmpty()) {
                                $projectedTemplate = $item->templates->first();
                            }

                            $listMediaMeta = ($projectedTemplate && $projectedTemplate->preview_image)
                                ? [
                                    'type'            => 'image',
                                    'path'            => $projectedTemplate->preview_image,
                                    'url'             => $projectedTemplate->preview_image_url,
                                    'poster_url'      => $projectedTemplate->preview_image_url,
                                    'is_ai_generated' => (bool) ($projectedTemplate->is_ai_generated ?? true),
                                ]
                                : ($isModel ? $item->resolveMainMediaMeta() : []);

                            $pMediaType = $listMediaMeta['type'] ?? 'image';
                            $pImage = ($listMediaMeta['url'] ?? null) ?: ($calcProduct['image'] ?? '');
                            $pPosterUrl = $listMediaMeta['poster_url'] ?? null;
                            $isAiCardImage = (bool) ($listMediaMeta['is_ai_generated'] ?? ($calcProduct['is_ai_generated'] ?? false));
                            $pDisplayPrice = $calcProduct['display_price'] ?? ($isModel ? ($item->gross_price / 100) : 0);
                            $pTaxIncluded = $calcProduct['tax_included'] ?? ($isModel ? $item->tax_included : true);
                            $pTiers = $calcProduct['tier_pricing'] ?? [];
                        @endphp
                        <div wire:key="calc-list-{{ $pId }}"
                             class="relative group flex flex-row items-stretch p-3 md:p-4 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-primary/30 transition-all duration-200 cursor-pointer active:scale-[0.99]"
                             @if($projectedTemplate)
                                 wire:click="openConfig('{{ $pId }}', '{{ $projectedTemplate->id }}')"
                             @else
                                 wire:click="openConfig('{{ $pId }}')"
                             @endif
                             wire:loading.class="opacity-50 pointer-events-none"
                             wire:target="openConfig('{{ $pId }}')">

                            {{-- Bild --}}
                            <div class="flex-shrink-0 mr-4 self-start relative">
                                @if(!empty($pImage) && $pMediaType === 'video')
                                    <video src="{{ str_starts_with($pImage, 'http') ? $pImage : asset($pImage) }}"
                                           @if($pPosterUrl) poster="{{ $pPosterUrl }}" @endif
                                           @if($isAiCardImage) data-is-ai-generated="true" @endif
                                           class="w-24 h-24 md:w-24 md:h-24 object-cover rounded-lg bg-gray-50 border border-gray-100"
                                           muted autoplay loop playsinline></video>
                                    @if($isAiCardImage)
                                        <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                              class="pointer-events-none select-none absolute bottom-1 right-1 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                            KI-generiert
                                        </span>
                                    @endif
                                @elseif(!empty($pImage))
                                    <img src="{{ str_starts_with($pImage, 'http') ? $pImage : asset($pImage) }}"
                                         alt="{{ $pName }}"
                                         @if($isAiCardImage) data-is-ai-generated="true" @endif
                                         class="w-24 h-24 md:w-24 md:h-24 object-cover rounded-lg bg-gray-50 border border-gray-100">
                                    @if($isAiCardImage)
                                        <span style="font-size: 7px; line-height: 1; padding: 2px 5px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                              class="pointer-events-none select-none absolute bottom-1 right-1 z-20 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                            KI-generiert
                                        </span>
                                    @endif
                                @else
                                    <div class="w-24 h-24 md:w-24 md:h-24 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-center text-gray-300">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                            </div>

                            {{-- Inhalt --}}
                            <div class="flex-1 min-w-0 flex flex-col justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-900 group-hover:text-primary transition truncate">{{ $pName }}</h4>
                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2 leading-relaxed">{{ $pDesc }}</p>
                                </div>
                                <div class="mt-3">
                                    <div class="flex flex-wrap items-baseline gap-x-1 justify-between items-end">
                                        <div class="flex flex-col">
                                            <div class="flex flex-wrap items-baseline gap-x-2">
                                                <span class="text-sm font-bold text-primary">ab {{ number_format($pDisplayPrice, 2, ',', '.') }} €</span>
                                                @if($product && $product->compare_at_price > $product->price)
                                                    <span class="text-xs text-gray-400 line-through font-medium">
                                                        {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                                    </span>
                                                @endif
                                            </div>
                                            @if($product && $product->compare_at_price > $product->price)
                                                <span class="text-[10px] text-gray-500 font-medium leading-tight mt-0.5">
                                                    30-Tage-Bestpreis (§ 11 PAngV): {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                                </span>
                                            @endif
                                            <span class="text-[10px] text-gray-500 font-medium leading-tight mt-0.5">
                                                {{ $isSmallBusiness ? 'Endpreis gem. § 19 UStG' : 'inkl. MwSt.' }} · <a href="{{ route('versand') }}" target="_blank" @click.stop class="underline hover:text-gray-600">zzgl. Versand</a>
                                            </span>
                                        </div>
                                        <div class="hidden md:flex w-8 h-8 rounded-full bg-white/90 backdrop-blur border border-gray-200 text-gray-400 items-center justify-center shadow-sm transition-all duration-200 group-hover:border-primary/60 group-hover:text-primary group-hover:shadow-md group-hover:scale-[1.03]">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        </div>
                                    </div>

                                    {{-- Staffelpreise --}}
                                    @if(!empty($pTiers))
                                        <div class="mt-2 pt-2 border-t border-gray-50">
                                            <span class="text-[10px] font-bold uppercase text-green-600 block mb-1">Staffelpreise verfügbar:</span>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach(collect($pTiers)->sortBy('qty')->take(4) as $tier)
                                                    <span class="text-[10px] bg-green-50 text-green-700 px-1.5 py-0.5 rounded border border-green-100">
                                                        ab {{ $tier['qty'] }} Stk: -{{ 0 + $tier['percent'] }}%
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Paginierung --}}
            @if(isset($catalogProducts) && $catalogProducts->hasPages())
                <div class="mt-10 pt-6 border-t border-gray-200">
                    {{ $catalogProducts->links(data: ['scrollTo' => '#calculator-anchor']) }}
                </div>
            @endif
        </div>
    @endif
</div>
