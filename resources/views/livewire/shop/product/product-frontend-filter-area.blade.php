<div>
    @push('structured_data')
        @if(!empty($shopSchema))
            <script type="application/ld+json">{!! json_encode($shopSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
    @endpush
    <div class="bg-gray-50 min-h-screen pb-20">

        {{-- Header / Hero Section: saisonaler Hauptshop-Header --}}
        <div class="bg-gray-950 border-b border-gray-800 py-16 md:py-20 text-center relative overflow-hidden">
            @if(!empty($heroBanner['url']))
                <img src="{{ $heroBanner['url'] }}"
                     alt="Unsere Kollektion Atmosphäre"
                     loading="eager"
                     decoding="async"
                     class="absolute inset-0 w-full h-full object-cover object-center opacity-85 pointer-events-none select-none transition-opacity duration-700">
                <div class="absolute inset-0 bg-gradient-to-b from-gray-950/65 via-gray-950/45 to-gray-950/85 pointer-events-none"></div>
            @endif
            <div class="absolute inset-0 bg-[var(--theme-color-10)] blur-[100px] rounded-full w-[500px] h-[500px] mx-auto opacity-25 pointer-events-none"></div>
            <div class="relative z-10 px-4">
                <span class="text-[var(--theme-color)] font-bold uppercase tracking-[0.2em] text-xs mb-3 block drop-shadow">Handveredelt in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-serif font-bold text-white mb-5 drop-shadow-lg">
                    Unsere Kollektion
                </h1>
                <p class="text-gray-200 max-w-2xl mx-auto text-lg leading-relaxed px-4 drop-shadow">
                    Entdecke Unikate, die deine Seele berühren. Jedes Stück wird mit Liebe, modernster Technik und Sorgfalt für dich personalisiert.
                </p>

                <div class="mt-7 flex justify-center">
                    <button type="button"
                            data-testid="hero-open-3d-showroom-btn"
                            @click="$dispatch('open-louvre-showroom')"
                            class="bg-primary text-gray-900 px-8 py-4 rounded-full font-bold text-lg hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 shadow-lg shadow-primary/30 inline-flex items-center justify-center gap-2.5 cursor-pointer">
                        <span>3D Shoppen</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20">

            {{-- Benutzerfreundliche Clean-Design Filter- & Suchleiste (Mobil & Desktop optimiert) --}}
            @php
                $activeHolidayLabel = $filterHoliday !== '' ? ($availableHolidays[$filterHoliday] ?? ucfirst((string) $filterHoliday)) : null;
                $activeCategoryObj = $filterCategory !== '' ? ($categories->firstWhere('id', (int) $filterCategory) ?? $categories->firstWhere('id', $filterCategory)) : null;
                $activeCategoryLabel = $activeCategoryObj?->name ?? ($filterCategory !== '' ? (\App\Models\Product\ProductCategory::find($filterCategory)?->name ?? 'Kategorie') : null);
            @endphp
            <div x-data="{ openDropdown: null }"
                 @click.outside="openDropdown = null"
                 @keydown.escape.window="openDropdown = null"
                 class="bg-white rounded-3xl shadow-xl shadow-gray-200/60 border border-gray-100 p-4 sm:p-5 mb-10 sm:mb-12 relative z-30">

                <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3">
                    {{-- Suchfeld --}}
                    <div class="relative flex-1 min-w-0 group">
                        <input wire:model.live.debounce.300ms="search"
                               type="text"
                               placeholder="Suche nach Name oder Eigenschaft..."
                               class="w-full py-3.5 pl-12 pr-11 bg-gray-50 border border-gray-200 rounded-2xl text-sm sm:text-base font-medium text-gray-900 focus:bg-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all placeholder-gray-400">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-[var(--theme-color)] transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        @if($search !== '')
                            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center">
                                <button type="button"
                                        wire:click="$set('search', '')"
                                        class="w-8 h-8 rounded-xl bg-gray-200/70 hover:bg-red-50 text-gray-500 hover:text-red-600 flex items-center justify-center transition-colors cursor-pointer"
                                        aria-label="Suche leeren">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        @endif
                    </div>

                    @if(!empty($availableHolidays) || $categories->isNotEmpty() || (($saleCount ?? 0) > 0 || $filterSale))
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0">
                            {{-- Anlass Dropdown (Touch-optimiert + sr-only Select für A11y/Tests) --}}
                            @if(!empty($availableHolidays))
                                <div class="relative w-full sm:w-auto sm:min-w-[210px]">
                                    <select wire:model.live="filterHoliday" class="sr-only" tabindex="-1" aria-hidden="true">
                                        <option value="">Alle Anlässe</option>
                                        @foreach($availableHolidays as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>

                                    <button type="button"
                                            @click="openDropdown = openDropdown === 'holiday' ? null : 'holiday'"
                                            :aria-expanded="openDropdown === 'holiday'"
                                            class="w-full flex items-center justify-between gap-3 py-2.5 px-3.5 rounded-2xl border text-left transition-all cursor-pointer {{ $filterHoliday !== '' ? 'bg-[#FAF6EE] border-primary text-gray-900 shadow-xs' : 'bg-gray-50 border-gray-200 text-gray-800 hover:bg-white hover:border-gray-300' }}">
                                        <span class="flex items-center gap-3 min-w-0">
                                            <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors {{ $filterHoliday !== '' ? 'bg-primary text-white shadow-2xs' : 'bg-white border border-gray-200/80 text-primary' }}">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                                            </span>
                                            <span class="text-sm font-bold truncate">
                                                {{ $activeHolidayLabel ?? 'Alle Anlässe' }}
                                            </span>
                                        </span>
                                        <span class="w-7 h-7 rounded-lg bg-white/90 border border-gray-200/70 flex items-center justify-center text-gray-500 shrink-0">
                                            <svg class="w-4 h-4 transition-transform duration-200" :class="openDropdown === 'holiday' ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 9l-7 7-7-7" /></svg>
                                        </span>
                                    </button>

                                    <div x-show="openDropdown === 'holiday'"
                                         x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 translate-y-1"
                                         class="absolute left-0 right-0 sm:min-w-[240px] mt-2 bg-white rounded-2xl shadow-2xl border border-gray-200 p-2 z-50 max-h-72 overflow-y-auto space-y-1">
                                        <button type="button"
                                                wire:click="$set('filterHoliday', '')"
                                                @click="openDropdown = null"
                                                class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl text-sm font-bold transition-colors cursor-pointer {{ $filterHoliday === '' ? 'bg-[#FAF6EE] text-gray-900 border border-primary/30' : 'text-gray-700 hover:bg-gray-50 active:bg-gray-100' }}">
                                            <span>Alle Anlässe</span>
                                            @if($filterHoliday === '')
                                                <svg class="w-4 h-4 text-[#C5A059] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            @endif
                                        </button>
                                        @foreach($availableHolidays as $val => $label)
                                            @php $isHolidaySelected = mb_strtolower((string) $filterHoliday) === mb_strtolower((string) $val); @endphp
                                            <button type="button"
                                                    wire:click="$set('filterHoliday', '{{ $val }}')"
                                                    @click="openDropdown = null"
                                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl text-sm font-bold transition-colors cursor-pointer {{ $isHolidaySelected ? 'bg-[#FAF6EE] text-gray-900 border border-primary/30' : 'text-gray-700 hover:bg-gray-50 active:bg-gray-100' }}">
                                                <span class="truncate">{{ $label }}</span>
                                                @if($isHolidaySelected)
                                                    <svg class="w-4 h-4 text-[#C5A059] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Kategorie Dropdown (Touch-optimiert + sr-only Select für A11y/Tests) --}}
                            @if($categories->isNotEmpty())
                                <div class="relative w-full sm:w-auto sm:min-w-[220px]">
                                    <select wire:model.live="filterCategory" class="sr-only" tabindex="-1" aria-hidden="true">
                                        <option value="">Alle Kategorien</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>

                                    <button type="button"
                                            @click="openDropdown = openDropdown === 'category' ? null : 'category'"
                                            :aria-expanded="openDropdown === 'category'"
                                            class="w-full flex items-center justify-between gap-3 py-2.5 px-3.5 rounded-2xl border text-left transition-all cursor-pointer {{ $filterCategory !== '' ? 'bg-[#FAF6EE] border-primary text-gray-900 shadow-xs' : 'bg-gray-50 border-gray-200 text-gray-800 hover:bg-white hover:border-gray-300' }}">
                                        <span class="flex items-center gap-3 min-w-0">
                                            <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-colors {{ $filterCategory !== '' ? 'bg-primary text-white shadow-2xs' : 'bg-white border border-gray-200/80 text-primary' }}">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                                            </span>
                                            <span class="text-sm font-bold truncate">
                                                {{ $activeCategoryLabel ?? 'Alle Kategorien' }}
                                            </span>
                                        </span>
                                        <span class="w-7 h-7 rounded-lg bg-white/90 border border-gray-200/70 flex items-center justify-center text-gray-500 shrink-0">
                                            <svg class="w-4 h-4 transition-transform duration-200" :class="openDropdown === 'category' ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M19 9l-7 7-7-7" /></svg>
                                        </span>
                                    </button>

                                    <div x-show="openDropdown === 'category'"
                                         x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 translate-y-1"
                                         class="absolute left-0 right-0 sm:min-w-[250px] mt-2 bg-white rounded-2xl shadow-2xl border border-gray-200 p-2 z-50 max-h-72 overflow-y-auto space-y-1">
                                        <button type="button"
                                                wire:click="$set('filterCategory', '')"
                                                @click="openDropdown = null"
                                                class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl text-sm font-bold transition-colors cursor-pointer {{ $filterCategory === '' ? 'bg-[#FAF6EE] text-gray-900 border border-primary/30' : 'text-gray-700 hover:bg-gray-50 active:bg-gray-100' }}">
                                            <span>Alle Kategorien</span>
                                            @if($filterCategory === '')
                                                <svg class="w-4 h-4 text-[#C5A059] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            @endif
                                        </button>
                                        @foreach($categories as $cat)
                                            @php $isCatSelected = (string) $filterCategory === (string) $cat->id; @endphp
                                            <button type="button"
                                                    wire:click="$set('filterCategory', '{{ $cat->id }}')"
                                                    @click="openDropdown = null"
                                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-3 rounded-xl text-sm font-bold transition-colors cursor-pointer {{ $isCatSelected ? 'bg-[#FAF6EE] text-gray-900 border border-primary/30' : 'text-gray-700 hover:bg-gray-50 active:bg-gray-100' }}">
                                                <span class="truncate">{{ $cat->name }}</span>
                                                @if($isCatSelected)
                                                    <svg class="w-4 h-4 text-[#C5A059] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Angebote Button (nur sichtbar, wenn echte Angebote aktiv sind oder der Filter gesetzt ist) --}}
                            @if(($saleCount ?? 0) > 0 || $filterSale)
                                <button type="button"
                                        wire:click="toggleSaleFilter"
                                        class="w-full sm:w-auto flex items-center justify-between sm:justify-center gap-3 py-2.5 px-3.5 sm:px-4 rounded-2xl border text-sm font-bold tracking-wide transition-all whitespace-nowrap cursor-pointer {{ $filterSale ? 'bg-red-600 text-white border-red-600 shadow-sm' : 'bg-gray-50 text-red-600 border-gray-200 hover:bg-red-50 hover:border-red-200' }}">
                                    <span class="flex items-center gap-3">
                                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 {{ $filterSale ? 'bg-white/20 text-white' : 'bg-white border border-red-100 text-red-600' }}">
                                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                        </span>
                                        <span class="uppercase text-xs sm:text-sm font-extrabold tracking-wider">Angebote</span>
                                    </span>
                                    @if(($saleCount ?? 0) > 0)
                                        <span class="px-2.5 py-1 rounded-full text-xs font-black leading-none {{ $filterSale ? 'bg-white/20 text-white' : 'bg-red-100 text-red-600' }}">{{ $saleCount }}</span>
                                    @endif
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Aktive Filter anzeigen (Nur wenn etwas ausgewählt ist) --}}
                @if($search !== '' || $filterSale || $filterCategory !== '' || $filterHoliday !== '')
                    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-2.5 animate-fade-in">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mr-1">Aktive Filter:</span>

                        @if($search !== '')
                            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-gray-100 text-gray-800 text-xs font-bold">
                                <span>„{{ $search }}“</span>
                                <button type="button" wire:click="$set('search', '')" class="hover:text-red-500 cursor-pointer"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterSale)
                            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-red-50 text-red-600 text-xs font-bold uppercase tracking-wider border border-red-200">
                                <span>Nur Angebote</span>
                                <button type="button" wire:click="$set('filterSale', false)" class="hover:text-red-800 cursor-pointer"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterHoliday !== '')
                            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#FAF6EE] text-gray-900 text-xs font-bold border border-primary/40">
                                <span>Anlass: {{ $activeHolidayLabel }}</span>
                                <button type="button" wire:click="$set('filterHoliday', '')" class="hover:text-red-500 cursor-pointer"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterCategory !== '')
                            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-[#FAF6EE] text-gray-900 border border-primary/40 text-xs font-bold">
                                <span>Kategorie: {{ $activeCategoryLabel }}</span>
                                <button type="button" wire:click="$set('filterCategory', '')" class="hover:text-red-500 cursor-pointer"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        <button type="button" wire:click="resetFilters" class="text-xs font-bold text-red-600 hover:text-red-700 hover:underline ml-auto py-1 px-2 cursor-pointer">Alle Filter löschen</button>
                    </div>
                @endif
            </div>

            {{-- Produkt Grid --}}
            <div wire:loading.class="opacity-50 blur-sm pointer-events-none"
                 wire:target="search, filterSale, toggleSaleFilter, filterCategory, filterHoliday, resetFilters, gotoPage, nextPage, previousPage"
                 class="transition-all duration-300">
                @if($products->isEmpty())
                    <div class="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm animate-fade-in-up">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 text-gray-300 mb-6">
                            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <h3 class="text-xl font-serif font-bold text-gray-900 mb-2">Keine Treffer gefunden</h3>
                        <p class="text-gray-500 mb-6 max-w-md mx-auto">Es tut uns leid, aber zu deiner Auswahl haben wir aktuell keine passenden Produkte in der Kollektion.</p>
                        <button wire:click="resetFilters" class="px-6 py-2.5 bg-gray-900 text-white rounded-full font-bold text-sm shadow-md hover:bg-black transition-all">Filter zurücksetzen</button>
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
                            cardEl.querySelectorAll('.sf-card-sparkle').forEach((el, i) => {
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
                            const hoverVid = cardEl.querySelector('video[data-sf-hover-video]');
                            if (hoverVid) {
                                hoverVid.play().catch(() => {});
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-8">
                        @foreach($products as $product)
                            @php
                                $isSoldOut = ($product->track_quantity && $product->quantity <= 0 && !$product->continue_selling_when_out_of_stock);
                                $isBackorder = ($product->track_quantity && $product->quantity <= 0 && $product->continue_selling_when_out_of_stock);
                                $lowStock = ($product->track_quantity && $product->quantity > 0 && $product->quantity <= 5);

                                // Template Projection Swap Logik
                                $projectedTemplate = null;
                                if (!empty($filterHoliday) && $product->relationLoaded('templates') && $product->templates->isNotEmpty()) {
                                    $projectedTemplate = $product->templates->first();
                                }
                                $productUrl = $projectedTemplate ? route('product.show', ['product' => $product->slug, 't' => $projectedTemplate->id]) : route('product.show', $product->slug);
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

                                $resolvedHoverMeta = !$isSoldOut
                                    ? $product->resolveHoverMediaMeta($resolvedMainMeta['path'] ?? null)
                                    : null;
                                $hasHoverMedia = !empty($resolvedHoverMeta['url']);
                                $resolvedHoverMediaType = $resolvedHoverMeta['type'] ?? 'image';
                                $resolvedHoverMediaUrl = $resolvedHoverMeta['url'] ?? null;
                                $isAiHoverMedia = (bool) ($resolvedHoverMeta['is_ai_generated'] ?? false);
                            @endphp

                            <div onmouseenter="window.sfCardHoverEnter && window.sfCardHoverEnter(this)"
                                 onmouseleave="window.sfCardHoverLeave && window.sfCardHoverLeave(this)"
                                 class="sf-card {{ $hasHoverMedia ? 'sf-has-hover' : '' }} group relative flex flex-col h-full bg-white rounded-[2rem] p-3 border border-gray-100 shadow-sm {{ $isSoldOut ? 'opacity-80' : '' }}">

                                {{-- Bild Container --}}
                                <div class="aspect-square bg-gray-50 rounded-3xl overflow-hidden mb-5 relative border border-gray-100">

                                    <button type="button"
                                            x-data="window.sfWishlistButtonData('{{ $product->id }}', @js(in_array((string) $product->id, array_map('strval', $wishlistIds ?? $wishlistProductIds ?? []), true)))"
                                            @sf-wishlist-sync.window="syncFromStore($event.detail)"
                                            @wishlist-optimistic-delta.window="syncOptimistic($event.detail)"
                                            @click.prevent="toggle($event)"
                                            :class="wishlisted ? 'bg-red-500 text-white scale-105 shadow-[0_0_15px_rgba(239,68,68,0.45)]' : 'bg-white/90 text-gray-500 hover:text-red-500 hover:bg-white'"
                                            class="absolute bottom-3 left-3 z-30 w-9 h-9 rounded-full backdrop-blur-md shadow-md flex items-center justify-center transition-all duration-200 cursor-pointer"
                                            :title="wishlisted ? 'Von Wunschliste entfernen' : 'Auf die Wunschliste'"
                                            aria-label="Wunschliste">
                                        <svg class="w-4 h-4 transition-transform duration-200" :class="wishlisted ? 'fill-current scale-110' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                        </svg>
                                    </button>

                                    {{-- Product Type Badges --}}
                                    @if(!$isSoldOut && !$isBackorder)
                                        <div class="absolute top-3 right-3 z-30 flex flex-col gap-2 pointer-events-none">
                                            @if($product->type === 'digital')
                                                <div class="bg-[#F5F1E8]/95 backdrop-blur-sm text-gray-900 border border-[#D8C9A8] text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                                    <svg class="w-3 h-3 text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                                                    Gutschein
                                                </div>
                                            @endif
                                            @if($product->type === 'service')
                                                <div class="bg-[#F5F1E8]/95 backdrop-blur-sm text-gray-900 border border-[#D8C9A8] text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                                    <svg class="w-3 h-3 text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                                    Service
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    <a href="{{ $productUrl }}" class="block w-full h-full relative">

                                        <div class="w-full h-full relative {{ $isSoldOut ? 'grayscale opacity-50' : '' }}">
                                            @if($resolvedMainImageUrl && $resolvedMainMediaType === 'video')
                                                <video src="{{ $resolvedMainImageUrl }}"
                                                       @if($resolvedMainPosterUrl) poster="{{ $resolvedMainPosterUrl }}" @endif
                                                       @if($isAiCardImage) data-is-ai-generated="true" @endif
                                                       data-sf-main-video
                                                       class="w-full h-full object-cover sf-card-main-layer {{ !$isSoldOut ? 'sf-card-img' : '' }}"
                                                       muted autoplay loop playsinline></video>
                                            @elseif($resolvedMainImageUrl)
                                                <img src="{{ $resolvedMainImageUrl }}"
                                                     alt="{{ $projectedTemplate ? $projectedTemplate->name : $product->name }}"
                                                     @if($isAiCardImage) data-is-ai-generated="true" @endif
                                                     class="w-full h-full object-cover sf-card-main-layer {{ !$isSoldOut ? 'sf-card-img' : '' }}">
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
                                                         alt="{{ str_replace([' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' '], ' · ', (string) ($resolvedHoverMeta['label'] ?? ($product->name . ' · Hover-Ansicht'))) }}"
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

                                        @if(!$isSoldOut)
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
                                        @endif
                                    </a>

                                    {{-- Ausverkauft / Vorbestellen Ribbon --}}
                                    @if($isSoldOut || $isBackorder)
                                        <div class="absolute inset-0 pointer-events-none overflow-hidden z-30 rounded-3xl">
                                            <div class="absolute top-0 right-0 w-32 h-32">
                                                @if($isSoldOut)
                                                    <div class="absolute top-[25%] -right-[35%] w-[160%] bg-red-600/95 backdrop-blur text-white text-[10px] font-black py-2 shadow-xl transform rotate-45 uppercase tracking-[0.2em] border-y border-white/20 text-center">
                                                        Ausverkauft
                                                    </div>
                                                @else
                                                    <div class="absolute top-[25%] -right-[35%] w-[160%] bg-[#F5F1E8]/95 backdrop-blur-sm text-gray-900 text-[9px] font-black py-2 shadow-md transform rotate-45 uppercase tracking-[0.15em] border-y border-[#D8C9A8] text-center">
                                                        Vorbestellbar
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Kompakter Prozent- & Neu-Indikator oben links (verdeckt niemals das Produktmotiv) --}}
                                    <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5 pointer-events-none z-20">
                                        @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                            @php
                                                $discountPercent = (int) round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100);
                                            @endphp
                                            <span class="w-fit bg-red-600/95 backdrop-blur-sm text-white text-[10px] font-black px-2.5 py-1 rounded-full shadow-md tracking-wider leading-none border border-white/15">
                                                -{{ max(1, $discountPercent) }}%
                                            </span>
                                        @endif
                                        @if(!$isSoldOut && $product->created_at->diffInDays() < 21)
                                            <span class="w-fit bg-white/95 backdrop-blur text-gray-900 text-[10px] font-black px-2.5 py-1 rounded-full shadow-md uppercase tracking-wider leading-none border border-gray-100">
                                                Neu
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Infos --}}
                                <div class="flex-1 flex flex-col px-3 pb-3 relative">

                                    {{-- Kategorien des Produkts --}}
                                    @if($product->categories->isNotEmpty())
                                        <div class="flex flex-wrap items-center gap-1 mb-2">
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
                                                Nur noch {{ $product->quantity }}
                                            </span>
                                        </div>
                                    @endif

                                    <div class="mt-auto pt-3 flex items-end justify-between">
                                        <div class="flex flex-col">
                                            @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                                @php
                                                    $saleBadgeText = $product->configurator_settings['autopilot_badge_text'] ?? ($heroBanner['badge_text'] ?? 'Angebot');
                                                @endphp
                                                <div class="flex items-center gap-1.5 mb-1">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-extrabold uppercase tracking-wider bg-red-50 text-red-600 border border-red-200/80 w-fit">
                                                        {{ $saleBadgeText }}
                                                    </span>
                                                    @if($product->tierPrices->isNotEmpty())
                                                        <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">Ab</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">
                                                    @if($product->slug === 'geschenkgutschein' || ($product->price == 0 && $product->is_personalizable))
                                                        Wunschbetrag
                                                    @elseif($product->tierPrices->isNotEmpty())
                                                        Ab
                                                    @else
                                                        Preis
                                                    @endif
                                                </span>
                                            @endif
                                            <div class="flex items-baseline gap-2">
                                                <span class="{{ $isSoldOut ? 'text-gray-400' : ($product->compare_at_price > $product->price ? 'text-red-600' : 'text-gray-900') }} font-bold text-xl font-serif">
                                                    @if($product->slug === 'geschenkgutschein' || ($product->price == 0 && $product->is_personalizable))
                                                        ab 5,00 €
                                                    @else
                                                        {{ number_format($product->gross_price / 100, 2, ',', '.') }} €
                                                    @endif
                                                </span>
                                                @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                                    <span class="text-xs text-gray-400 line-through font-medium">
                                                        {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                                    </span>
                                                @endif
                                            </div>

                                            @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                                <span class="text-[10px] sm:text-[11px] text-gray-500 font-medium leading-tight mt-0.5">
                                                    30-Tage-Bestpreis (§ 11 PAngV): {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                                </span>
                                            @endif

                                            {{-- NEU: RECHTLICHE PAngV ANGABE (Sehr wichtig) --}}
                                            <span class="text-[10px] sm:text-[11px] text-gray-500 font-medium leading-tight mt-1">
                                                @if($product->type === 'digital')
                                                    @if(shop_setting('is_small_business', false))
                                                        Keine MwSt. gem. § 19 UStG · Sofort-Download
                                                    @else
                                                        {{ ($product->tax_included ? 'inkl. MwSt.' : 'zzgl. MwSt.') . ' · Sofort-Download' }}
                                                    @endif
                                                @elseif(shop_setting('is_small_business', false))
                                                    Keine MwSt. gem. § 19 UStG · <a href="{{ route('versand') }}" target="_blank" class="hover:text-gray-600 underline">zzgl. Versand</a>
                                                @else
                                                    {{ $product->tax_included ? 'inkl. MwSt.' : 'zzgl. MwSt.' }} <a href="{{ route('versand') }}" target="_blank" class="hover:text-gray-600 underline">zzgl. Versand</a>
                                                @endif
                                            </span>
                                        </div>

                                        {{-- Circle Action Button --}}
                                        <a href="{{ $productUrl }}" class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 transform group-hover:scale-110 {{ $isSoldOut ? 'bg-gray-100 text-gray-400' : 'bg-gray-900 text-white hover:bg-[var(--theme-color)] shadow-lg' }}">
                                            @if($isSoldOut)
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                            @else
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                                            @endif
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination Links --}}
                    <div class="mt-16 border-t border-gray-200 pt-8">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Vereinte 3D-User-Lobby: Louvre-Produktgalerie + Goldenes Ehrenportal + Halle der Helden (0 KB Initial-Last, 0 Tailwind-Abhaengigkeit) --}}
    <div id="sf-louvre-showroom-host"
         wire:ignore
         x-data="window.sfLouvreShowroomModal()"
         x-init="initLobbyListeners()"
         @open-louvre-showroom.window="openShowroom(($event.detail && $event.detail.zone) ? $event.detail.zone : 'gallery')">

        <style id="sf-3d-lobby-scoped-css">
            [x-cloak] { display: none !important; }
            @keyframes sfLobbySpin { to { transform: rotate(360deg); } }
            .sf-lobby-modal, .sf-lobby-modal * {
                user-select: none !important;
                -webkit-user-select: none !important;
                -webkit-touch-callout: none !important;
            }
            .sf-lobby-modal input, .sf-lobby-modal textarea {
                user-select: text !important;
                -webkit-user-select: text !important;
            }
            .sf-lobby-modal {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                inset: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                height: 100dvh !important;
                z-index: 2147483000 !important;
                background: #FAF8F5 !important;
                overflow: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                color: #111827;
            }
            .sf-lobby-modal.is-open { display: flex !important; flex-direction: column !important; }
            .sf-lobby-modal.is-closed { display: none !important; }
            .sf-lobby-topbar {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                padding: 12px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                flex-wrap: wrap;
                pointer-events: none;
                z-index: 30;
                box-sizing: border-box;
            }
            .sf-lobby-nav-pill {
                pointer-events: auto;
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid rgba(197, 160, 89, 0.45);
                border-radius: 999px;
                padding: 4px;
                box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
                display: inline-flex;
                align-items: center;
                gap: 4px;
                flex-wrap: wrap;
                max-width: 100%;
                box-sizing: border-box;
            }
            .sf-lobby-zone-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 13px;
                border-radius: 999px;
                background: #FAF8F5;
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.40);
                font-size: 12px;
                font-weight: 800;
                line-height: 1.2;
                letter-spacing: 0.01em;
                white-space: nowrap;
                pointer-events: none;
                cursor: default;
            }
            .sf-lobby-stages-btn {
                appearance: none;
                border: 1px solid rgba(197, 160, 89, 0.55);
                border-radius: 999px;
                padding: 6px 12px;
                font-size: 11px;
                font-weight: 800;
                line-height: 1.2;
                background: #FAF8F5;
                color: #111827;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                white-space: nowrap;
            }
            .sf-lobby-stages-btn:hover { background: #FFFFFF; }
            .sf-lobby-actions {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 7px;
                flex-wrap: wrap;
                margin-right: 244px;
            }
            .sf-lobby-pill-btn {
                appearance: none;
                background: rgba(255, 255, 255, 0.95);
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.48);
                border-radius: 999px;
                padding: 7px 12px;
                font-size: 12px;
                font-weight: 800;
                line-height: 1.2;
                box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                white-space: nowrap;
                transition: background-color 150ms ease, border-color 150ms ease;
            }
            .sf-lobby-pill-btn:hover { background: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-pill-btn.is-gold { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-close-btn {
                appearance: none;
                background: rgba(255, 255, 255, 0.96);
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.55);
                border-radius: 999px;
                padding: 7px 14px;
                font-size: 12px;
                font-weight: 800;
                line-height: 1.2;
                box-shadow: 0 8px 24px rgba(17, 24, 39, 0.10);
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 7px;
                white-space: nowrap;
            }
            .sf-lobby-close-btn:hover { background: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-kbd {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 6px;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.45);
                color: #6B7280;
                font-size: 10px;
                font-weight: 800;
                line-height: 1.1;
            }
            .sf-lobby-badge {
                background: rgba(255, 255, 255, 0.94);
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.45);
                border-radius: 999px;
                padding: 6px 11px;
                font-size: 11px;
                font-weight: 800;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                white-space: nowrap;
                box-shadow: 0 6px 18px rgba(17, 24, 39, 0.06);
            }
            .sf-lobby-dot { width: 8px; height: 8px; border-radius: 999px; display: inline-block; flex-shrink: 0; }
            .sf-lobby-dot.is-live { background: #10B981; }
            .sf-lobby-dot.is-solo { background: #9CA3AF; }
            .sf-lobby-instance-wrap { position: relative; display: inline-flex; }
            .sf-lobby-instance-popover {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                width: 260px;
                background: #FFFFFF;
                border: 1px solid rgba(197, 160, 89, 0.45);
                border-radius: 16px;
                padding: 12px;
                box-shadow: 0 18px 40px rgba(17, 24, 39, 0.14);
                z-index: 60;
                color: #111827;
                box-sizing: border-box;
            }
            .sf-lobby-instance-list {
                max-height: 176px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .sf-lobby-instance-item {
                appearance: none;
                width: 100%;
                padding: 7px 11px;
                border-radius: 11px;
                border: 1px solid rgba(197, 160, 89, 0.38);
                background: #FAF8F5;
                color: #111827;
                font-size: 11px;
                font-weight: 800;
                display: flex;
                align-items: center;
                justify-content: space-between;
                cursor: pointer;
                box-sizing: border-box;
            }
            .sf-lobby-instance-item:hover { background: #F5EFE2; }
            .sf-lobby-instance-item.is-current { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-instance-form {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid rgba(197, 160, 89, 0.28);
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .sf-lobby-instance-input {
                width: 58px;
                padding: 6px 8px;
                border-radius: 10px;
                border: 1px solid rgba(197, 160, 89, 0.55);
                background: #FAF8F5;
                color: #111827;
                font-size: 12px;
                font-weight: 800;
                outline: none;
                box-sizing: border-box;
            }
            .sf-lobby-instance-submit {
                appearance: none;
                flex: 1;
                padding: 7px 10px;
                border-radius: 10px;
                border: none;
                background: #C5A059;
                color: #FFFFFF;
                font-size: 11px;
                font-weight: 800;
                cursor: pointer;
            }
            .sf-lobby-stage {
                position: relative;
                flex: 1 1 auto;
                min-height: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
            }
            .sf-lobby-canvas {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100% !important;
                height: 100% !important;
                display: block;
                touch-action: none;
                cursor: grab;
            }
            .sf-lobby-ivory-transition {
                position: absolute;
                inset: 0;
                z-index: 44;
                pointer-events: none;
                opacity: 0;
                background: radial-gradient(circle at 50% 45%, rgba(255, 252, 244, 0.92) 0%, rgba(250, 245, 232, 0.98) 58%, rgba(235, 214, 170, 0.99) 100%);
                transition: opacity 540ms cubic-bezier(0.22, 1, 0.36, 1);
            }
            .sf-lobby-ivory-transition.is-fading {
                opacity: 1;
                pointer-events: auto;
            }
            .sf-lobby-loading {
                position: absolute;
                inset: 0;
                z-index: 26;
                background: #FAF8F5;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 14px;
                padding: 24px;
                text-align: center;
            }
            .sf-lobby-spinner {
                width: 40px;
                height: 40px;
                border-radius: 999px;
                border: 3px solid rgba(197, 160, 89, 0.26);
                border-top-color: #C5A059;
                animation: sfLobbySpin 0.85s linear infinite;
            }
            .sf-lobby-loading-label {
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.16em;
                text-transform: uppercase;
                color: #9A7738;
            }
            .sf-lobby-error-card {
                background: #FFFFFF;
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.55);
                border-radius: 18px;
                padding: 22px 24px;
                max-width: 360px;
                box-shadow: 0 16px 36px rgba(17, 24, 39, 0.12);
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 14px;
            }
            .sf-lobby-floating-anchor {
                position: absolute;
                top: 0;
                left: 0;
                z-index: 20;
                will-change: transform;
                transition: opacity 180ms ease;
                pointer-events: none;
            }
            .sf-lobby-floating-card {
                position: relative;
                width: 296px;
                max-width: calc(100vw - 24px);
                background: rgba(255, 255, 255, 0.98);
                border: 1px solid rgba(197, 160, 89, 0.62);
                border-radius: 18px;
                padding: 14px 16px 12px;
                box-shadow: 0 16px 38px rgba(17, 24, 39, 0.12), 0 0 0 1px rgba(255, 255, 255, 0.7) inset;
                color: #111827;
                pointer-events: auto;
                box-sizing: border-box;
            }
            .sf-lobby-floating-arrow {
                width: 12px;
                height: 12px;
                background: rgba(255, 255, 255, 0.98);
                border-right: 1px solid rgba(197, 160, 89, 0.62);
                border-bottom: 1px solid rgba(197, 160, 89, 0.62);
                transform: rotate(45deg);
                margin: -6px auto 0;
                pointer-events: none;
            }
            .sf-lobby-prod-row1 {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 10px;
            }
            .sf-lobby-prod-title {
                margin: 0;
                font-family: Georgia, "Times New Roman", serif;
                font-size: 15px;
                font-weight: 700;
                line-height: 1.24;
                color: #111827;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                flex: 1 1 auto;
                min-width: 0;
            }
            .sf-lobby-prod-price-badge {
                flex-shrink: 0;
                display: inline-flex;
                align-items: center;
                padding: 2px 9px;
                border-radius: 999px;
                background: #FAF6EE;
                border: 1px solid rgba(197, 160, 89, 0.52);
                font-size: 11.5px;
                font-weight: 800;
                color: #8A692C;
                white-space: nowrap;
            }
            .sf-lobby-prod-summary {
                margin: 6px 0 0;
                font-size: 11.5px;
                font-weight: 500;
                line-height: 1.38;
                color: #4B5563;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .sf-lobby-prod-cta {
                appearance: none;
                margin-top: 11px;
                width: 100%;
                padding: 9px 14px;
                border-radius: 999px;
                border: 1px solid #B89146;
                background: #C5A059;
                color: #FFFFFF !important;
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.02em;
                text-decoration: none !important;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                box-sizing: border-box;
                cursor: pointer;
                box-shadow: 0 6px 16px rgba(197, 160, 89, 0.28);
                transition: background-color 150ms ease, transform 130ms ease;
            }
            .sf-lobby-prod-cta:hover { background: #B38E46; transform: translateY(-1px); }
            .sf-lobby-hero-head { display: flex; align-items: center; gap: 8px; min-width: 0; }
            .sf-lobby-hero-rank {
                padding: 3px 9px;
                border-radius: 999px;
                background: #C5A059;
                color: #FFFFFF;
                font-size: 11px;
                font-weight: 900;
                flex-shrink: 0;
            }
            .sf-lobby-hero-name {
                margin: 0;
                font-family: Georgia, "Times New Roman", serif;
                font-size: 15px;
                font-weight: 700;
                color: #111827;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                flex: 1;
            }
            .sf-lobby-hero-me {
                padding: 2px 7px;
                border-radius: 999px;
                background: #FAF8F5;
                border: 1px solid #C5A059;
                color: #9A7738;
                font-size: 9px;
                font-weight: 900;
                flex-shrink: 0;
            }
            .sf-lobby-hero-stats {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid rgba(197, 160, 89, 0.28);
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .sf-lobby-hero-chip {
                padding: 4px 9px;
                border-radius: 9px;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.45);
                color: #111827;
                font-size: 11px;
                font-weight: 800;
            }
            .sf-lobby-portal-pill {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.97);
                color: #111827;
                border: 1px solid #C5A059;
                box-shadow: 0 14px 32px rgba(17, 24, 39, 0.14);
                font-size: 12px;
                font-weight: 800;
                text-decoration: none;
                white-space: nowrap;
            }
            .sf-lobby-chat-panel {
                position: absolute;
                bottom: calc(74px + env(safe-area-inset-bottom, 0px));
                right: 20px;
                z-index: 28;
                width: 304px;
                max-width: calc(100vw - 24px);
                background: rgba(255, 255, 255, 0.97);
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.55);
                border-radius: 16px;
                padding: 12px;
                box-shadow: 0 16px 38px rgba(17, 24, 39, 0.14);
                display: flex;
                flex-direction: column;
                gap: 8px;
                box-sizing: border-box;
            }
            .sf-lobby-chat-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                padding-bottom: 6px;
                border-bottom: 1px solid rgba(197, 160, 89, 0.25);
            }
            .sf-lobby-chat-scroll {
                max-height: 156px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 6px;
                padding-right: 2px;
            }
            .sf-lobby-chat-msg {
                border-radius: 11px;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.26);
                padding: 6px 9px;
                font-size: 11px;
                color: #111827;
            }
            .sf-lobby-chat-panel.is-expanded { width: 440px; }
            .sf-lobby-chat-panel.is-expanded .sf-lobby-chat-scroll { max-height: 280px; }
            .sf-lobby-friends-scroll {
                max-height: 156px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 5px;
                padding-right: 2px;
            }
            .sf-lobby-chat-panel.is-expanded .sf-lobby-friends-scroll { max-height: 280px; }
            .sf-emote-chip {
                appearance: none;
                border-radius: 6px;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.35);
                padding: 2px 6px;
                font-size: 10px;
                font-weight: 700;
                color: #4B5563;
                cursor: pointer;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .sf-emote-chip:hover {
                border-color: #C5A059;
                color: #111827;
                background: #FFFFFF;
            }
            .sf-lobby-gold-crest-bar {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                min-height: 62px;
                padding: 6px 20px calc(8px + env(safe-area-inset-bottom, 0px));
                background: linear-gradient(180deg, rgba(250, 248, 245, 0.88) 0%, rgba(255, 255, 255, 0.98) 38%, #FAF6EE 100%);
                border-top: 2px solid #C5A059;
                box-shadow: 0 -8px 28px rgba(17, 24, 39, 0.09), 0 1px 0 rgba(255, 255, 255, 0.9) inset;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 26;
                pointer-events: auto;
                box-sizing: border-box;
            }
            .sf-lobby-gold-crest-bar::before {
                content: "";
                position: absolute;
                top: 3px;
                left: 18px;
                right: 18px;
                height: 1px;
                background: linear-gradient(90deg, transparent 0%, rgba(197, 160, 89, 0.55) 18%, rgba(197, 160, 89, 0.85) 50%, rgba(197, 160, 89, 0.55) 82%, transparent 100%);
                pointer-events: none;
            }
            .sf-lobby-copyright {
                position: absolute;
                left: 20px;
                bottom: calc(18px + env(safe-area-inset-bottom, 0px));
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.03em;
                color: #7C6334;
                white-space: nowrap;
                pointer-events: none;
            }
            .sf-lobby-abilities-dock {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 9px;
                z-index: 27;
                pointer-events: auto;
            }
            .sf-ability-btn {
                position: relative;
                width: 46px;
                height: 46px;
                border-radius: 12px;
                background: #121318;
                border: 1.5px solid rgba(197, 160, 89, 0.82);
                color: #FDE68A;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 6px 16px rgba(17, 24, 39, 0.18);
                transition: transform 0.14s ease, border-color 0.15s ease, box-shadow 0.15s ease;
                padding: 0;
            }
            .sf-ability-btn:hover {
                border-color: #C5A059;
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(197, 160, 89, 0.34);
            }
            .sf-ability-btn.is-active {
                border-color: #FDE68A;
                box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.45), 0 6px 18px rgba(197, 160, 89, 0.38);
            }
            .sf-ability-btn.is-cooldown {
                border-color: rgba(156, 163, 175, 0.55);
            }
            .sf-ability-btn.is-cooldown::after,
            .sf-item-slot-btn.is-cooldown::after {
                content: "";
                position: absolute;
                inset: 0;
                border-radius: inherit;
                background: rgba(10, 12, 18, 0.68);
                z-index: 2;
                pointer-events: none;
            }
            .sf-slot-media {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                border-radius: inherit;
                overflow: hidden;
                display: block;
                pointer-events: none;
                z-index: 1;
            }
            .sf-slot-icon-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: inherit;
                display: block;
                pointer-events: none;
            }
            .sf-ability-svg {
                width: 22px;
                height: 22px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: relative;
                z-index: 2;
                pointer-events: none;
            }
            .sf-ability-svg svg {
                width: 100%;
                height: 100%;
                display: block;
            }
            .sf-ability-key {
                position: absolute;
                top: 3px;
                left: 3px;
                background: rgba(15, 18, 25, 0.82);
                color: #FDE68A;
                padding: 1px 4px;
                border-radius: 5px;
                font-size: 9px;
                font-weight: 900;
                line-height: 1.05;
                z-index: 3;
                pointer-events: none;
            }
            .sf-ability-cd {
                position: absolute;
                bottom: 3px;
                right: 4px;
                font-size: 9px;
                font-weight: 800;
                color: #FFFFFF;
                background: rgba(17, 24, 39, 0.90);
                padding: 1px 4px;
                border-radius: 5px;
                line-height: 1;
                z-index: 4;
                pointer-events: none;
            }
            .sf-ability-combo-badge {
                position: absolute;
                top: -6px;
                right: -6px;
                background: #C5A059;
                color: #FFFFFF;
                font-size: 9px;
                font-weight: 900;
                padding: 2px 5px;
                border-radius: 999px;
                border: 1.5px solid #FFFFFF;
                line-height: 1;
                box-shadow: 0 3px 8px rgba(17, 24, 39, 0.18);
                z-index: 5;
                pointer-events: none;
            }
            .sf-ability-tooltip {
                position: absolute;
                bottom: calc(100% + 12px);
                left: 50%;
                transform: translateX(-50%) translateY(4px);
                width: 224px;
                padding: 10px 12px;
                border-radius: 13px;
                background: rgba(255, 255, 255, 0.98);
                border: 1px solid #C5A059;
                box-shadow: 0 14px 32px rgba(17, 24, 39, 0.16);
                color: #111827;
                text-align: left;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 140ms ease, transform 140ms ease;
                z-index: 60;
                box-sizing: border-box;
                white-space: normal;
            }
            .sf-ability-btn:hover .sf-ability-tooltip,
            .sf-ability-btn:focus-visible .sf-ability-tooltip {
                opacity: 1;
                visibility: visible;
                transform: translateX(-50%) translateY(0);
            }
            .sf-ability-tooltip-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 6px;
                margin-bottom: 4px;
            }
            .sf-ability-tooltip-name {
                font-size: 12px;
                font-weight: 800;
                color: #111827;
            }
            .sf-ability-tooltip-key {
                padding: 1px 6px;
                border-radius: 5px;
                background: #FAF6EE;
                border: 1px solid rgba(197, 160, 89, 0.5);
                font-size: 9.5px;
                font-weight: 800;
                color: #8A692C;
            }
            .sf-ability-tooltip-meta {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 10px;
                font-weight: 800;
                color: #1D4ED8;
                margin-bottom: 5px;
            }
            .sf-ability-tooltip-cd { color: #6B7280; }
            .sf-ability-tooltip-desc {
                margin: 0;
                font-size: 10.5px;
                font-weight: 500;
                line-height: 1.36;
                color: #374151;
            }
            .sf-lobby-settings-backdrop {
                position: absolute;
                inset: 0;
                z-index: 48;
                background: rgba(17, 24, 39, 0.24);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px;
                pointer-events: auto;
                box-sizing: border-box;
            }
            .sf-lobby-settings-card {
                width: 392px;
                max-width: calc(100vw - 24px);
                max-height: calc(100dvh - 40px);
                overflow-y: auto;
                background: #FFFFFF;
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.55);
                border-radius: 20px;
                padding: 18px 20px;
                box-shadow: 0 22px 52px rgba(17, 24, 39, 0.18);
                display: flex;
                flex-direction: column;
                gap: 14px;
                box-sizing: border-box;
            }
            .sf-lobby-settings-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                padding-bottom: 10px;
                border-bottom: 1px solid rgba(197, 160, 89, 0.28);
            }
            .sf-lobby-settings-title {
                margin: 0;
                font-size: 15px;
                font-weight: 800;
                color: #111827;
                display: flex;
                align-items: center;
                gap: 7px;
            }
            .sf-lobby-settings-section {
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.34);
                border-radius: 14px;
                padding: 11px 13px;
                display: flex;
                flex-direction: column;
                gap: 9px;
            }
            .sf-lobby-settings-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .sf-lobby-settings-label {
                font-size: 12px;
                font-weight: 800;
                color: #111827;
            }
            .sf-lobby-slider {
                width: 100%;
                height: 6px;
                accent-color: #C5A059;
                cursor: pointer;
            }
            .sf-lobby-keybind-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 7px;
                padding-top: 4px;
            }
            .sf-lobby-keybind-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 6px;
                padding: 6px 9px;
                border-radius: 10px;
                background: #FFFFFF;
                border: 1px solid rgba(197, 160, 89, 0.35);
                cursor: pointer;
                font-size: 11px;
                font-weight: 700;
                color: #111827;
                text-align: left;
            }
            .sf-lobby-keybind-item:hover { border-color: #C5A059; }
            .sf-lobby-keybind-item.is-listening { border-color: #C5A059; background: #FAF3E3; }
            .sf-lobby-keybind-pill {
                padding: 2px 7px;
                border-radius: 6px;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.48);
                font-size: 10px;
                font-weight: 800;
                color: #9A7738;
                white-space: nowrap;
            }
            .sf-lobby-unit-frame {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 7px 14px 7px 8px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.96);
                border: 1.5px solid rgba(197, 160, 89, 0.60);
                box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
                color: #111827;
            }
            .sf-lobby-unit-avatar {
                width: 34px;
                height: 34px;
                border-radius: 999px;
                background: #FAF8F5;
                border: 1.5px solid #C5A059;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                font-weight: 900;
                color: #9A7738;
                flex-shrink: 0;
            }
            .sf-lobby-unit-meta {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 11.5px;
                font-weight: 800;
                line-height: 1.2;
            }
            .sf-lobby-topright-stack {
                position: absolute;
                top: 14px;
                right: 18px;
                z-index: 25;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 8px;
                pointer-events: none;
            }
            .sf-outer-gate-banner {
                padding: 15px 16px;
                border-radius: 14px;
                background: #FAF8F5;
                border: 1.5px solid rgba(197, 160, 89, 0.55);
                text-align: center;
                font-size: 13.5px;
                font-weight: 600;
                line-height: 1.55;
                color: #1F2937;
                letter-spacing: 0.005em;
            }
            .sf-atelier-star-card {
                pointer-events: auto;
                width: 274px;
                max-width: calc(100vw - 32px);
                background: #FAF8F5;
                color: #111827;
                border: 1.5px solid #C5A059;
                border-radius: 16px;
                padding: 13px 15px;
                box-shadow: 0 18px 42px rgba(17, 24, 39, 0.16), 0 0 0 1px rgba(255, 255, 255, 0.9) inset;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 9px;
                box-sizing: border-box;
                text-align: center;
            }
            .sf-atelier-star-title {
                margin: 0;
                font-family: Georgia, "Times New Roman", serif;
                font-size: 14px;
                font-weight: 700;
                color: #111827;
                line-height: 1.25;
            }
            .sf-atelier-star-row {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 5px;
            }
            .sf-atelier-star-btn {
                appearance: none;
                border: none;
                background: transparent;
                padding: 2px 3px;
                font-size: 27px;
                line-height: 1;
                color: #DDD5C4;
                cursor: pointer;
                transition: transform 0.14s ease, color 0.14s ease;
            }
            .sf-atelier-star-btn.is-lit,
            .sf-atelier-star-btn:hover {
                color: #C5A059;
            }
            .sf-atelier-star-btn:hover {
                transform: scale(1.16);
            }
            .sf-atelier-star-preview {
                font-size: 24px;
                line-height: 1;
                color: #C5A059;
                letter-spacing: 3px;
            }
            .sf-atelier-star-thanks {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-size: 12px;
                font-weight: 800;
                color: #785A28;
            }
            .sf-atelier-star-edit-btn {
                appearance: none;
                border: 1px solid rgba(197, 160, 89, 0.45);
                background: #FFFFFF;
                color: #4B5563;
                border-radius: 999px;
                padding: 2px 8px;
                font-size: 10.5px;
                font-weight: 700;
                cursor: pointer;
            }
            .sf-atelier-star-feedback {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 7px;
                margin-top: 2px;
            }
            .sf-atelier-star-textarea {
                width: 100%;
                min-height: 58px;
                max-height: 110px;
                resize: vertical;
                border-radius: 10px;
                border: 1px solid rgba(197, 160, 89, 0.52);
                background: #FFFFFF;
                color: #111827;
                padding: 7px 9px;
                font-size: 12px;
                font-family: inherit;
                box-sizing: border-box;
                outline: none;
            }
            .sf-lobby-minimap-wrap {
                position: relative;
                width: 232px;
                height: 232px;
                z-index: 25;
                pointer-events: auto;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .sf-lobby-minimap-canvas {
                width: 100%;
                height: 100%;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.96);
                border: 2.5px solid #C5A059;
                box-shadow: 0 12px 30px rgba(17, 24, 39, 0.16), 0 0 0 3px rgba(255, 255, 255, 0.72);
                display: block;
            }
            .sf-lobby-minimap-time {
                position: absolute;
                bottom: -9px;
                left: 50%;
                transform: translateX(-50%);
                padding: 2px 10px;
                border-radius: 999px;
                background: #FFFFFF;
                border: 1px solid #C5A059;
                color: #111827;
                font-size: 10.5px;
                font-weight: 800;
                line-height: 1.25;
                white-space: nowrap;
                box-shadow: 0 4px 12px rgba(17, 24, 39, 0.12);
                cursor: help;
            }
            @keyframes sfLobbyPingPulse {
                0% { transform: scale(1); opacity: 0.38; }
                70% { transform: scale(1.85); opacity: 0; }
                100% { transform: scale(1.85); opacity: 0; }
            }
            .sf-lobby-ping-anchor {
                position: absolute;
                top: -8px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 34;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                pointer-events: auto;
            }
            .sf-lobby-ping-dot {
                appearance: none;
                width: 18px;
                height: 18px;
                padding: 0;
                margin: 0;
                border-radius: 999px;
                background: #FFFFFF;
                border: 1.5px solid #C5A059;
                box-shadow: 0 4px 10px rgba(17, 24, 39, 0.14);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                position: relative;
                outline: none;
            }
            .sf-lobby-ping-core {
                width: 9px;
                height: 9px;
                border-radius: 999px;
                display: block;
                position: relative;
                z-index: 2;
                background: #10B981;
                transition: background-color 160ms ease;
            }
            .sf-lobby-ping-pulse {
                position: absolute;
                width: 9px;
                height: 9px;
                border-radius: 999px;
                background: #10B981;
                opacity: 0.34;
                animation: sfLobbyPingPulse 2.4s ease-out infinite;
                pointer-events: none;
            }
            .sf-lobby-ping-dot.is-optimal .sf-lobby-ping-core,
            .sf-lobby-ping-dot.is-optimal .sf-lobby-ping-pulse { background: #10B981; }
            .sf-lobby-ping-dot.is-good .sf-lobby-ping-core,
            .sf-lobby-ping-dot.is-good .sf-lobby-ping-pulse { background: #D97706; }
            .sf-lobby-ping-dot.is-delayed .sf-lobby-ping-core,
            .sf-lobby-ping-dot.is-delayed .sf-lobby-ping-pulse { background: #DC2626; }
            .sf-lobby-ping-tooltip {
                position: absolute;
                top: calc(100% + 8px);
                right: -56px;
                width: 214px;
                background: #FAF8F5;
                border: 1px solid #C5A059;
                border-radius: 14px;
                padding: 10px 12px;
                box-shadow: 0 14px 32px rgba(17, 24, 39, 0.15);
                color: #111827;
                box-sizing: border-box;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-4px);
                transition: opacity 150ms ease, transform 150ms ease, visibility 150ms ease;
                pointer-events: none;
                z-index: 40;
            }
            .sf-lobby-ping-anchor:hover .sf-lobby-ping-tooltip,
            .sf-lobby-ping-anchor:focus-within .sf-lobby-ping-tooltip,
            .sf-lobby-ping-anchor.is-open .sf-lobby-ping-tooltip {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
                pointer-events: auto;
            }
            .sf-lobby-ping-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                padding-bottom: 6px;
                margin-bottom: 6px;
                border-bottom: 1px solid rgba(197, 160, 89, 0.28);
                font-size: 11.5px;
                font-weight: 800;
                color: #111827;
            }
            .sf-lobby-ping-badge {
                padding: 1px 7px;
                border-radius: 999px;
                font-size: 10px;
                font-weight: 800;
                line-height: 1.35;
                white-space: nowrap;
                background: #ECFDF5;
                border: 1px solid #A7F3D0;
                color: #065F46;
            }
            .sf-lobby-ping-badge.is-good {
                background: #FFFBEB;
                border-color: #FDE68A;
                color: #92400E;
            }
            .sf-lobby-ping-badge.is-delayed {
                background: #FEF2F2;
                border-color: #FECACA;
                color: #B91C1C;
            }
            .sf-lobby-ping-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                font-size: 11px;
                line-height: 1.45;
                color: #4B5563;
            }
            .sf-lobby-ping-row strong {
                font-weight: 800;
                color: #111827;
                white-space: nowrap;
            }
            .sf-lobby-exhibit-step-row {
                margin-top: 8px;
                padding-top: 7px;
                border-top: 1px solid rgba(197, 160, 89, 0.22);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 4px;
            }
            .sf-lobby-sanctum-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .sf-lobby-sanctum-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
                padding: 10px 12px;
                border-radius: 12px;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.34);
                font-size: 13px;
                font-weight: 800;
                color: #111827;
                transition: border-color 150ms ease, background-color 150ms ease;
            }
            .sf-lobby-sanctum-row.is-clickable { cursor: pointer; }
            .sf-lobby-sanctum-row.is-clickable:hover { border-color: #C5A059; background: #FFFFFF; }
            .sf-lobby-sanctum-row.is-done { background: #FFFFFF; border-color: #C5A059; cursor: default; }
            .sf-lobby-sanctum-check {
                width: 24px;
                height: 24px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 12.5px;
                font-weight: 900;
                flex-shrink: 0;
                background: #FEF2F2;
                border: 1.5px solid #FECACA;
                color: #DC2626;
                line-height: 1;
            }
            .sf-lobby-sanctum-row.is-done .sf-lobby-sanctum-check {
                background: #FAF6EE;
                border-color: #C5A059;
                color: #C5A059;
            }
            .sf-lobby-inline-panel {
                padding: 12px;
                border-radius: 14px;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.45);
                display: flex;
                flex-direction: column;
                gap: 9px;
            }
            .sf-lobby-inline-input {
                width: 100%;
                padding: 9px 11px;
                border-radius: 10px;
                border: 1px solid rgba(197, 160, 89, 0.55);
                background: #FFFFFF;
                color: #111827;
                font-size: 12.5px;
                font-weight: 600;
                outline: none;
                box-sizing: border-box;
            }
            .sf-lobby-inline-input:focus { border-color: #C5A059; box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.18); }
            .sf-lobby-pwd-wrap { position: relative; width: 100%; display: flex; align-items: center; }
            .sf-lobby-pwd-wrap .sf-lobby-inline-input { padding-right: 38px; }
            .sf-lobby-pwd-eye {
                position: absolute;
                right: 6px;
                top: 50%;
                transform: translateY(-50%);
                width: 28px;
                height: 28px;
                border-radius: 8px;
                border: none;
                background: transparent;
                color: #6B7280;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                padding: 0;
            }
            .sf-lobby-pwd-eye:hover { color: #111827; background: #FAF6EE; }
            .sf-lobby-inline-error {
                padding: 7px 10px;
                border-radius: 9px;
                background: #FEF2F2;
                border: 1px solid #FECACA;
                color: #B91C1C;
                font-size: 11.5px;
                font-weight: 700;
            }
            .sf-lobby-inline-check {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
                font-weight: 700;
                color: #111827;
                cursor: pointer;
            }
            .sf-lobby-inline-terms-box {
                position: relative;
                padding: 9px 11px;
                border-radius: 12px;
                background: #FFFFFF;
                border: 1px solid rgba(197, 160, 89, 0.35);
                transition: background-color 160ms ease, border-color 160ms ease;
            }
            .sf-lobby-inline-terms-box.is-checked {
                background: #FAF6EE;
                border-color: rgba(197, 160, 89, 0.65);
            }
            .sf-lobby-prod-cta.is-locked,
            .sf-lobby-prod-cta.is-locked:hover {
                background: #E5E7EB !important;
                color: #6B7280 !important;
                border: 1px solid #D1D5DB !important;
                box-shadow: none !important;
                opacity: 0.72;
                filter: grayscale(1);
                cursor: not-allowed !important;
                transform: none !important;
            }
            .sf-lobby-stick-wrap { display: none; position: absolute; bottom: calc(74px + env(safe-area-inset-bottom, 0px)); left: 14px; z-index: 25; }
            .sf-lobby-stick {
                width: 78px;
                height: 78px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.90);
                border: 1.5px solid rgba(197, 160, 89, 0.58);
                box-shadow: 0 10px 26px rgba(17, 24, 39, 0.12);
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                touch-action: none;
                cursor: pointer;
            }
            .sf-lobby-stick-knob {
                width: 32px;
                height: 32px;
                border-radius: 999px;
                background: #C5A059;
                border: 2px solid #FFFFFF;
                box-shadow: 0 4px 12px rgba(17, 24, 39, 0.18);
                pointer-events: none;
            }
            .sf-lobby-bottom-stack {
                position: absolute;
                bottom: calc(70px + env(safe-area-inset-bottom, 0px));
                left: 12px;
                right: 12px;
                z-index: 24;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 7px;
                pointer-events: none;
            }
            .sf-lobby-bottom-pill {
                pointer-events: auto;
                display: inline-flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 6px 10px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid rgba(197, 160, 89, 0.48);
                box-shadow: 0 10px 28px rgba(17, 24, 39, 0.12);
                max-width: 100%;
                box-sizing: border-box;
            }
            .sf-lobby-step-btn {
                appearance: none;
                padding: 6px 12px;
                border-radius: 999px;
                border: 1px solid rgba(197, 160, 89, 0.40);
                background: #FAF8F5;
                color: #111827;
                font-size: 11px;
                font-weight: 800;
                cursor: pointer;
                white-space: nowrap;
            }
            .sf-lobby-step-btn:hover { background: #F5EFE2; }
            .sf-lobby-step-btn.is-active { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-gear-btn {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 7px 14px;
                border-radius: 999px;
                background: #FAF8F5;
                border: 1.5px solid rgba(197, 160, 89, 0.65);
                color: #111827;
                font-size: 11.5px;
                font-weight: 800;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(17, 24, 39, 0.08);
                transition: transform 0.14s ease, border-color 0.15s ease, background-color 0.15s ease;
            }
            .sf-lobby-gear-btn:hover { border-color: #C5A059; background: #FFFFFF; transform: translateY(-1px); }
            .sf-lobby-gear-btn.is-active { background: #C5A059; color: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-char-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-top: 2px;
            }
            .sf-lobby-char-card {
                position: relative;
                appearance: none;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 16px 12px 14px;
                border-radius: 16px;
                border: 1.5px solid rgba(197, 160, 89, 0.42);
                background: #FAF8F5;
                color: #111827;
                cursor: pointer;
                transition: transform 0.14s ease, border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
            }
            .sf-lobby-char-card:hover {
                border-color: #C5A059;
                background: #FFFFFF;
                transform: translateY(-2px);
            }
            .sf-lobby-char-card.is-selected {
                border-color: #C5A059;
                background: #FFFFFF;
                box-shadow: 0 8px 20px rgba(197, 160, 89, 0.16);
            }
            .sf-lobby-char-check {
                position: absolute;
                top: 8px;
                right: 8px;
                width: 20px;
                height: 20px;
                border-radius: 999px;
                background: #C5A059;
                color: #FFFFFF;
                font-size: 11px;
                font-weight: 900;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .sf-lobby-char-emblem {
                width: 64px;
                height: 64px;
                border-radius: 999px;
                background: #FFFFFF;
                border: 1.5px solid rgba(197, 160, 89, 0.50);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 10px rgba(17, 24, 39, 0.06);
            }
            .sf-lobby-char-name {
                font-size: 14px;
                font-weight: 900;
                color: #111827;
                letter-spacing: 0.01em;
            }
            .sf-lobby-char-tag {
                display: inline-flex;
                align-items: center;
                padding: 2px 9px;
                border-radius: 999px;
                background: rgba(197, 160, 89, 0.14);
                color: #8C6A2E;
                font-size: 10.5px;
                font-weight: 800;
            }
            @media (max-width: 900px), (pointer: coarse) {
                .sf-lobby-topbar { padding: 8px 10px; gap: 6px; }
                .sf-lobby-actions { margin-right: 0; }
                .sf-lobby-unit-frame { padding: 5px 11px 5px 6px; gap: 7px; }
                .sf-lobby-unit-avatar { width: 30px; height: 30px; font-size: 11.5px; }
                .sf-lobby-unit-meta { font-size: 11px; gap: 6px; }
                .sf-lobby-stick-wrap { display: block; bottom: calc(68px + env(safe-area-inset-bottom, 0px)); left: 10px; }
                .sf-lobby-stick { width: 68px; height: 68px; }
                .sf-lobby-topright-stack { top: 62px; right: 10px; gap: 6px; }
                .sf-lobby-minimap-wrap { width: 138px; height: 138px; }
                .sf-lobby-minimap-time { font-size: 9.5px; padding: 1px 7px; }
                .sf-lobby-ping-tooltip { right: -48px; width: 202px; }
            }
            @media (max-width: 767px) {
                .sf-lobby-zone-badge { padding: 4px 8px; font-size: 10px; }
                .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 5px 8px; font-size: 11px; }
                .sf-lobby-kbd, .sf-lobby-hide-sm, .sf-lobby-copyright { display: none !important; }
                .sf-lobby-chat-panel { bottom: calc(68px + env(safe-area-inset-bottom, 0px)); right: 10px; }
                .sf-lobby-chat-panel.is-expanded { width: calc(100vw - 20px); }
                .sf-lobby-keybind-grid { grid-template-columns: 1fr; }
                .sf-lobby-gold-crest-bar { min-height: 50px; padding: 5px 10px calc(6px + env(safe-area-inset-bottom, 0px)); justify-content: flex-end; }
            }
            @media (max-width: 559px) {
                .sf-lobby-topright-stack { top: 64px; right: 8px; gap: 5px; }
                .sf-lobby-minimap-wrap { width: 138px; height: 138px; }
                .sf-lobby-stick-wrap { bottom: calc(64px + env(safe-area-inset-bottom, 0px)); left: 8px; }
                .sf-lobby-stick { width: 64px; height: 64px; }
                .sf-lobby-floating-card { width: 266px; padding: 12px 13px 10px; }
                .sf-lobby-prod-title { font-size: 13.5px; }
            }
        </style>

        <template x-teleport="body">
            <div id="sf-louvre-showroom-modal"
                 data-sf-3d-lobby-modal="showroom"
                 x-ref="lobbyModalRoot"
                 x-show="isOpen"
                 x-cloak
                 @contextmenu.prevent
                 role="dialog"
                 aria-modal="true"
                 aria-label="MEIN SEELENFUNKE · LOUVRE SHOWROOM"
                 :class="isOpen ? 'sf-lobby-modal is-open' : 'sf-lobby-modal is-closed'"
                 style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100dvh !important; z-index: 2147483000 !important; background: #FAF8F5 !important; overflow: hidden !important; margin: 0 !important; padding: 0 !important;">

                {{-- Edle schwebende Light-Mode HUD-Leiste (Spieler-Frame oben links + Aktionen oben rechts) --}}
                <div class="sf-lobby-topbar">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;pointer-events:none;">
                        <div class="sf-lobby-unit-frame"
                             @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             data-testid="lobby-player-unit-frame">
                            <span class="sf-lobby-unit-avatar">✦</span>
                            <div class="sf-lobby-unit-meta">
                                <span style="max-width:128px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                      x-text="playerHud.name || 'Funki'"></span>
                                <span style="color:#9A7738;white-space:nowrap;"
                                      x-text="'Stufe ' + (playerHud.level || 2) + ' · ✨ ' + (playerHud.funkenBalance || 0)"></span>
                            </div>
                        </div>

                        <div class="sf-lobby-nav-pill" @pointerdown.stop @mousedown.stop @click.stop>
                            <span class="sf-lobby-zone-badge"
                                  data-testid="lobby-current-zone-badge"
                                  x-text="currentZoneLabel()">🛍️ Kollektion</span>
                            <button type="button"
                                    data-testid="lobby-char-switch-btn"
                                    @click="openCharacterPicker()"
                                    class="sf-lobby-stages-btn">
                                <span x-text="userSettings.characterGender === 'female' ? '✦ Weiblich' : '✦ Männlich'"></span>
                            </button>
                            <button type="button"
                                    x-show="equippedArmory && equippedArmory.weapon"
                                    x-cloak
                                    data-testid="lobby-weapon-swing-btn"
                                    @click="triggerLobbyWeaponSwing()"
                                    class="sf-lobby-stages-btn"
                                    style="border-color:#C5A059;background:#FFFFFF;">
                                <span style="color:#C5A059;">⚔️</span>
                                <span>Waffe nutzen [Q]</span>
                            </button>
                            <button type="button"
                                    x-show="currentZone === 'heroes' && isCustomerAuthenticated()"
                                    data-testid="lobby-stages-preview-btn"
                                    @click="cycleStagesPreview()"
                                    class="sf-lobby-stages-btn">
                                <span x-text="stagesPreviewLabel()"></span>
                            </button>
                        </div>
                    </div>

                    <div class="sf-lobby-actions" @pointerdown.stop @mousedown.stop>
                        <span x-show="currentZone === 'heroes' && isCustomerAuthenticated() && !soloMode && !multiplayerDisabled && focusedProduct !== null"
                              class="sf-lobby-badge sf-lobby-hide-sm">
                            <span style="color:#C5A059;">&#10003;</span>
                            <span>Fokus-Schutz</span>
                        </span>

                        <button type="button"
                                x-show="currentZone === 'heroes' && isCustomerAuthenticated() && !multiplayerDisabled"
                                data-testid="louvre-live-solo-toggle"
                                @click="toggleSoloMode()"
                                class="sf-lobby-pill-btn"
                                :title="soloMode ? 'Multiplayer aktivieren' : 'Ungestört alleine umschauen (Solo)'">
                            <span class="sf-lobby-dot" :class="soloMode ? 'is-solo' : 'is-live'"></span>
                            <span x-text="soloMode ? 'Solo' : 'Live'"></span>
                        </button>

                        {{-- Manueller Multiplayer-Instanzen-Waehler (NUR in der Halle der Helden aktiv!) --}}
                        <div x-show="currentZone === 'heroes' && isCustomerAuthenticated() && !soloMode && !multiplayerDisabled"
                             @click.outside="instanceMenuOpen = false"
                             class="sf-lobby-instance-wrap">
                            <button type="button"
                                    id="sf-lobby-instance-btn"
                                    @click="instanceMenuOpen = !instanceMenuOpen"
                                    class="sf-lobby-pill-btn">
                                <span x-text="'🌐 Instanz #' + lobbyInstanceId + ' · ' + currentInstanceCount() + '/' + lobbyCapacity + ' ▾'"></span>
                            </button>

                            <div id="sf-lobby-instance-menu"
                                 x-show="instanceMenuOpen"
                                 x-cloak
                                 @pointerdown.stop
                                 class="sf-lobby-instance-popover">
                                <div class="sf-lobby-instance-list">
                                    <template x-for="inst in availableInstances()" :key="inst.id">
                                        <button type="button"
                                                @click="switchLobbyInstance(inst.id)"
                                                :class="Number(inst.id) === Number(lobbyInstanceId) ? 'sf-lobby-instance-item is-current' : 'sf-lobby-instance-item'">
                                            <span x-text="'Instanz #' + inst.id"></span>
                                            <span x-text="inst.count + '/' + (inst.capacity || lobbyCapacity)"></span>
                                        </button>
                                    </template>
                                </div>

                                <form @submit.prevent="switchLobbyInstance(customInstanceInput)"
                                      class="sf-lobby-instance-form">
                                    <span style="font-size:11px;font-weight:800;color:#111827;white-space:nowrap;">Instanz #</span>
                                    <input type="number"
                                           min="1"
                                           max="50"
                                           x-model.number="customInstanceInput"
                                           aria-label="Instanz Nummer"
                                           class="sf-lobby-instance-input">
                                    <button type="submit" class="sf-lobby-instance-submit">
                                        Wechseln
                                    </button>
                                </form>
                            </div>
                        </div>

                        <button type="button"
                                x-show="currentZone === 'heroes' && !soloMode && !multiplayerDisabled"
                                data-testid="louvre-chat-toggle-btn"
                                @click="toggleChatPanel()"
                                :class="chatOpen ? 'sf-lobby-pill-btn is-gold' : 'sf-lobby-pill-btn'"
                                title="Raum-Chat öffnen"
                                aria-label="Raum-Chat">
                            <span>💬 Chat</span>
                            <span x-show="unreadChatCount > 0 && !chatOpen"
                                  style="padding:2px 6px;border-radius:999px;background:#C5A059;color:#FFFFFF;font-size:10px;font-weight:900;line-height:1;"
                                  x-text="unreadChatCount"></span>
                        </button>
                    </div>
                </div>

                {{-- 3D-User-Lobby Buehne + Schwebende Light-Mode Raum-Tafeln (0 Popups, 0 Tailwind-Abhaengigkeit) --}}
                <div class="sf-lobby-stage">
                    <canvas x-ref="louvreCanvas" class="sf-lobby-canvas"></canvas>

                    {{-- Kinoreife Elfenbein-Gold-Blende beim Klick auf "Jetzt gestalten" --}}
                    <div class="sf-lobby-ivory-transition" :class="{ 'is-fading': productTransitionActive }"></div>

                    {{-- Oben rechts: Runde 232x232 Light-Mode Minimap (mobil kompakt 138px) --}}
                    <div x-show="!isLoading && loadError === ''"
                         @pointerdown.stop
                         @mousedown.stop
                         @click.stop
                         class="sf-lobby-topright-stack">
                        <div data-testid="lobby-minimap" class="sf-lobby-minimap-wrap">
                            <div class="sf-lobby-ping-anchor"
                                 :class="{ 'is-open': pingTooltipOpen }"
                                 @click.outside="pingTooltipOpen = false">
                                <button type="button"
                                        data-testid="lobby-minimap-ping-dot"
                                        @click="pingTooltipOpen = !pingTooltipOpen"
                                        :class="'sf-lobby-ping-dot is-' + pingTier()"
                                        aria-label="Serververbindung">
                                    <span class="sf-lobby-ping-pulse"></span>
                                    <span class="sf-lobby-ping-core"></span>
                                </button>
                                <div data-testid="lobby-minimap-ping-tooltip"
                                     class="sf-lobby-ping-tooltip"
                                     role="status">
                                    <div class="sf-lobby-ping-head">
                                        <span>Serververbindung</span>
                                        <span :class="'sf-lobby-ping-badge' + (pingTier() === 'optimal' ? '' : (' is-' + pingTier()))"
                                              x-text="pingStatusLabel()">Optimal</span>
                                    </div>
                                    <div class="sf-lobby-ping-row">
                                        <span>Ping</span>
                                        <strong x-text="pingDisplayLabel()">-- ms</strong>
                                    </div>
                                    <div class="sf-lobby-ping-row">
                                        <span>Jitter</span>
                                        <strong x-text="jitterDisplayLabel()">±0 ms</strong>
                                    </div>
                                    <div class="sf-lobby-ping-row">
                                        <span>Modus</span>
                                        <strong x-text="pingModeLabel()">Atelier (Solo)</strong>
                                    </div>
                                    <div x-show="currentZone === 'heroes' && isCustomerAuthenticated() && !soloMode && !multiplayerDisabled"
                                         class="sf-lobby-ping-row">
                                        <span>Instanz</span>
                                        <strong x-text="pingInstanceLabel()">Halle #1 (1/20)</strong>
                                    </div>
                                </div>
                            </div>
                            <canvas x-ref="minimapCanvas" width="232" height="232" class="sf-lobby-minimap-canvas"></canvas>
                            <span class="sf-lobby-minimap-time"
                                  :title="minimapTooltip()"
                                  x-text="environmentInfo.server_time || '12:00'"></span>
                        </div>
                    </div>

                    {{-- Lade- & Fehlerstatus im Light-Mode --}}
                    <div x-show="isLoading || loadError !== ''" class="sf-lobby-loading">
                        <template x-if="isLoading">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
                                <div class="sf-lobby-spinner"></div>
                                <span class="sf-lobby-loading-label">3D-Lobby wird geöffnet</span>
                            </div>
                        </template>
                        <template x-if="!isLoading && loadError !== ''">
                            <div class="sf-lobby-error-card">
                                <p style="margin:0;font-size:14px;font-weight:700;color:#111827;" x-text="loadError"></p>
                                <button type="button"
                                        @click="closeShowroom()"
                                        class="sf-lobby-pill-btn is-gold">
                                    Zurück zum Shop
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Einheitliches 2-Zeilen Edel-Exponat-Modal (identisch für Gäste & eingeloggte Magie-Spieler, 100 % Light-Mode) --}}
                    <div x-ref="floatingCard"
                         data-testid="louvre-floating-space-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <div @pointerdown.stop class="sf-lobby-floating-card">
                            <div class="sf-lobby-prod-row1">
                                <h4 class="sf-lobby-prod-title"
                                    x-text="focusedProduct ? focusedProduct.name : ''"></h4>
                                <span x-show="focusedPriceLabel() !== ''"
                                      class="sf-lobby-prod-price-badge"
                                      x-text="focusedPriceLabel()"></span>
                            </div>

                            <p x-show="focusedSummaryText() !== ''"
                               class="sf-lobby-prod-summary"
                               x-text="focusedSummaryText()"></p>

                            <a :href="focusedProduct ? (focusedProduct.product_url || '/shop') : '/shop'"
                               @click.prevent="launchProductTransition(focusedProduct)"
                               class="sf-lobby-prod-cta">
                                <span x-text="focusedProduct && focusedProduct.is_personalizable ? 'Jetzt gestalten' : 'Zum Produkt'"></span>
                            </a>

                            <div x-show="products.length > 1" class="sf-lobby-exhibit-step-row">
                                <button type="button" @click="prevExhibit()" class="sf-lobby-step-btn" style="padding:4px 9px;font-size:10px;">
                                    &lsaquo; Vorheriges
                                </button>
                                <button type="button"
                                        @click="focusCurrentExhibit()"
                                        style="appearance:none;border:none;background:transparent;font-size:10.5px;font-weight:800;color:#6B7280;cursor:pointer;"
                                        x-text="(activeIndex + 1) + '/' + products.length"></button>
                                <button type="button" @click="nextExhibit()" class="sf-lobby-step-btn" style="padding:4px 9px;font-size:10px;">
                                    Nächstes &rsaquo;
                                </button>
                            </div>
                        </div>
                        <div class="sf-lobby-floating-arrow"></div>
                    </div>

                    {{-- Schwebende World-Space Helden-Stats-Tafel (Bereich 2: Top-5-Ehrenpodeste) --}}
                    <div x-ref="floatingHeroCard"
                         data-testid="lobby-floating-hero-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <div @pointerdown.stop class="sf-lobby-floating-card">
                            <div class="sf-lobby-hero-head">
                                <span class="sf-lobby-hero-rank"
                                      x-text="focusedHero ? ('#' + focusedHero.slot) : '#1'"></span>
                                <h3 class="sf-lobby-hero-name"
                                    x-text="focusedHero ? focusedHero.display_name : 'Freier Ehrenplatz'"></h3>
                                <span x-show="focusedHero && focusedHero.is_me" class="sf-lobby-hero-me">
                                    DU
                                </span>
                            </div>

                            <div x-show="focusedHero && !focusedHero.is_placeholder" class="sf-lobby-hero-stats">
                                <span class="sf-lobby-hero-chip"
                                      x-text="focusedHero ? ('Stufe ' + focusedHero.level + ' · ' + focusedHero.level_title) : ''"></span>
                                <span class="sf-lobby-hero-chip"
                                      x-text="focusedHero ? ('✨ ' + (focusedHero.sparks_formatted || focusedHero.sparks) + ' Funken') : ''"></span>
                                <span class="sf-lobby-hero-chip"
                                      x-text="focusedHero ? (focusedHero.ship_class || 'KLASSE C') : ''"></span>
                                <span class="sf-lobby-hero-chip"
                                      x-text="focusedHero ? ('🏆 ' + (focusedHero.titles_count || 1) + ' Erfolge') : ''"></span>
                            </div>
                        </div>
                        <div class="sf-lobby-floating-arrow"></div>
                    </div>

                    {{-- Schwebendes Light-Mode Schild am goldenen Ehrenportal bei fehlenden Voraussetzungen --}}
                    <div x-ref="floatingPortalCard"
                         data-testid="lobby-guest-portal-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <button type="button"
                                @pointerdown.stop
                                @click="openSanctumGateModal('heroes')"
                                class="sf-lobby-portal-pill"
                                style="cursor:pointer;">
                            <span>🔒</span>
                            <span x-text="isCustomerAuthenticated() ? 'Halle der Helden · Voraussetzungen' : 'Mitglieder-Bereich · Anmelden'"></span>
                            <span>&rarr;</span>
                        </button>
                    </div>

                    {{-- Schwebendes Light-Mode Schild am monumentalen Tor zur Aussenwelt --}}
                    <div x-ref="floatingOuterGateCard"
                         data-testid="lobby-outer-gate-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <button type="button"
                                @pointerdown.stop
                                @click="openOuterGateModal()"
                                class="sf-lobby-portal-pill"
                                style="cursor:pointer;">
                            <span>✨</span>
                            <span>Tor zur Außenwelt · Fantastische Welt &amp; Abenteuer in Planung</span>
                        </button>
                    </div>

                    {{-- Schwebende Light-Mode Ausruestungs-Karte am Ruestungsstaender & Wand-Waffenhalter (Clean-Design, 0 Subtexte) --}}
                    <div x-ref="floatingArmoryCard"
                         data-testid="lobby-armory-prompt-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <div @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             style="pointer-events:auto;background:rgba(255,255,255,0.97);border:1px solid rgba(197,160,89,0.58);border-radius:16px;padding:10px 12px;box-shadow:0 12px 28px rgba(17,24,39,0.11);display:flex;flex-direction:column;gap:8px;min-width:236px;max-width:292px;">
                            <button type="button"
                                    data-testid="armory-primary-action-btn"
                                    @click="interactFocusedArmoryItem()"
                                    style="appearance:none;width:100%;padding:8px 12px;border-radius:11px;border:1px solid #C5A059;background:#FAF8F5;color:#111827;font-size:12px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                <span style="display:inline-flex;align-items:center;gap:6px;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:20px;padding:0 5px;border-radius:6px;background:#C5A059;color:#FFFFFF;font-size:11px;font-weight:900;">E</span>
                                    <span x-text="armoryPrompt.focusedLabel || 'Kristall-Brustpanzer'"></span>
                                </span>
                                <span style="color:#C5A059;font-size:11px;font-weight:800;"
                                      x-text="armoryPrompt.isEquipped ? 'Ablegen' : 'Anziehen'"></span>
                            </button>

                            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;">
                                <template x-for="slotItem in armorySlotChips()" :key="slotItem.key">
                                    <button type="button"
                                            :data-testid="'armory-chip-' + slotItem.key"
                                            @click="toggleArmoryKey(slotItem.key)"
                                            :style="slotItem.active
                                                ? 'appearance:none;padding:5px 6px;border-radius:8px;border:1px solid #C5A059;background:#FAF8F5;color:#111827;font-size:10.5px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;'
                                                : (armoryPrompt.focusedKey === slotItem.key
                                                    ? 'appearance:none;padding:5px 6px;border-radius:8px;border:1px solid rgba(197,160,89,0.75);background:#FFFFFF;color:#111827;font-size:10.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;'
                                                    : 'appearance:none;padding:5px 6px;border-radius:8px;border:1px solid rgba(17,24,39,0.12);background:#FFFFFF;color:#374151;font-size:10.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:4px;')">
                                        <span x-show="slotItem.active" style="color:#C5A059;font-weight:900;">&#10003;</span>
                                        <span x-text="slotItem.short"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div class="sf-lobby-floating-arrow"></div>
                    </div>

                    {{-- 1-Klick Light-Mode Bewertungs-Kaertchen an der 3D-Sternebewertungs-Station neben dem Ehrenportal --}}
                    <div x-ref="floatingAtelierStarCard"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <div @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             data-testid="atelier-star-rating-card"
                             class="sf-atelier-star-card">
                            <h4 class="sf-atelier-star-title">Wie gefällt dir unser Atelier?</h4>

                            <template x-if="atelierReview.can_rate">
                                <div style="width:100%;display:flex;flex-direction:column;align-items:center;gap:8px;">
                                    <div class="sf-atelier-star-row" @mouseleave="previewAtelierStars(0)">
                                        <template x-for="star in [1, 2, 3, 4, 5]" :key="'atelier-star-' + star">
                                            <button type="button"
                                                    :data-testid="'atelier-star-btn-' + star"
                                                    :aria-label="star + ' Sterne'"
                                                    @mouseenter="previewAtelierStars(star)"
                                                    @click="submitAtelierStarRating(star)"
                                                    class="sf-atelier-star-btn"
                                                    :class="{ 'is-lit': star <= (atelierStarHover > 0 ? atelierStarHover : atelierReview.my_rating) }">★</button>
                                        </template>
                                    </div>

                                    <template x-if="atelierFeedbackOpen">
                                        <form @submit.prevent="submitAtelierStarComment()" class="sf-atelier-star-feedback">
                                            <textarea x-model="atelierFeedbackDraft"
                                                      maxlength="1500"
                                                      placeholder="Was können wir noch besser machen?"
                                                      aria-label="Was können wir noch besser machen?"
                                                      class="sf-atelier-star-textarea"></textarea>
                                            <button type="submit"
                                                    :disabled="atelierRatingBusy"
                                                    class="sf-lobby-prod-cta"
                                                    style="margin-top:0;border:none;cursor:pointer;padding:7px 12px;font-size:12px;">
                                                <span x-text="atelierRatingBusy ? 'Wird gesendet...' : 'Senden'"></span>
                                            </button>
                                        </form>
                                    </template>

                                    <template x-if="!atelierFeedbackOpen && atelierReview.has_rated && !atelierRatingEditing">
                                        <div class="sf-atelier-star-thanks">
                                            <span>✓ Danke für deine Bewertung!</span>
                                            <button type="button"
                                                    @click="startAtelierRatingEdit()"
                                                    class="sf-atelier-star-edit-btn">Ändern</button>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!atelierReview.can_rate">
                                <div style="width:100%;display:flex;flex-direction:column;align-items:center;gap:8px;">
                                    <div class="sf-atelier-star-preview" aria-hidden="true">★★★★★</div>
                                    <button type="button"
                                            data-testid="atelier-star-login-btn"
                                            @click="openLoginForAtelierRating()"
                                            class="sf-lobby-prod-cta"
                                            style="margin-top:0;border:none;cursor:pointer;padding:8px 14px;font-size:12px;">
                                        <span>Anmelden &amp; bewerten</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                        <div class="sf-lobby-floating-arrow"></div>
                    </div>

                    {{-- Helles Light-Mode Modal am Tor zur Aussenwelt (Clean-Design, rollenabhaengiger 1-Klick-SSO-Uebergang) --}}
                    <div x-show="outerGateModalOpen"
                         x-cloak
                         @pointerdown.stop
                         @mousedown.stop
                         @click.self="closeOuterGateModal()"
                         data-testid="lobby-outer-gate-modal"
                         class="sf-lobby-settings-backdrop">
                        <div class="sf-lobby-settings-card"
                             @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             role="dialog"
                             aria-label="Tor zur Außenwelt">
                            <div class="sf-lobby-settings-head">
                                <h3 class="sf-lobby-settings-title">
                                    <span>✨</span>
                                    <span>Tor zur Außenwelt</span>
                                </h3>
                                <button type="button"
                                        @click="closeOuterGateModal()"
                                        style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;"
                                        aria-label="Schließen">&times;</button>
                            </div>

                            <div class="sf-outer-gate-banner">Hinter diesem Tor warten bald eine fantastische Welt und spannende Abenteuer auf dich, an denen das Team von Mein-Seelenfunke in Zukunft arbeiten möchte.</div>

                            <template x-if="mmorpgCanEnter">
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <button type="button"
                                            data-testid="outer-gate-enter-mmorpg-btn"
                                            @click="enterFunkenreichMmorpg()"
                                            class="sf-lobby-prod-cta"
                                            style="margin-top:0;border:none;cursor:pointer;">
                                        <span>✨ In die Außenwelt starten &rarr;</span>
                                    </button>
                                    <button type="button"
                                            @click="closeOuterGateModal()"
                                            class="sf-lobby-step-btn"
                                            style="padding:9px 14px;border-radius:12px;">
                                        Schließen
                                    </button>
                                </div>
                            </template>

                            <template x-if="!mmorpgCanEnter">
                                <button type="button"
                                        data-testid="outer-gate-ack-btn"
                                        @click="closeOuterGateModal()"
                                        class="sf-lobby-prod-cta"
                                        style="margin-top:0;border:none;cursor:pointer;">
                                    <span>Verstanden</span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Helles Light-Mode Voraussetzungs-Modal (Rotes ✕ vs. Goldenes ✓, In-Game-Login mit Auge-Icon & 1-Klick-Magie, Clean-Design) --}}
                    <div x-show="sanctumGateOpen"
                         x-cloak
                         @pointerdown.stop
                         @mousedown.stop
                         @click.self="closeSanctumGateModal()"
                         data-testid="lobby-sanctum-gate-modal"
                         class="sf-lobby-settings-backdrop">
                        <div class="sf-lobby-settings-card"
                             @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             role="dialog"
                             aria-label="Zugangsvoraussetzungen">
                            <div class="sf-lobby-settings-head">
                                <h3 class="sf-lobby-settings-title">
                                    <span>🏛️</span>
                                    <span>Halle der Helden</span>
                                </h3>
                                <button type="button"
                                        @click="closeSanctumGateModal()"
                                        style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;"
                                        aria-label="Schließen">&times;</button>
                            </div>

                            <div class="sf-lobby-sanctum-list">
                                <div class="sf-lobby-sanctum-row"
                                     data-testid="sanctum-check-login"
                                     @click="handleSanctumStepClick('login')"
                                     :class="{ 'is-done': sanctumGateState.checks.login, 'is-clickable': !sanctumGateState.checks.login }">
                                    <span>Angemeldet</span>
                                    <span class="sf-lobby-sanctum-check"
                                          x-text="sanctumGateState.checks.login ? '✓' : '✕'"></span>
                                </div>

                                <template x-if="!sanctumGateState.checks.login && sanctumExpandedStep === 'login'">
                                    <form @submit.prevent="submitInlineLobbyLogin()"
                                          data-testid="sanctum-inline-login-form"
                                          class="sf-lobby-inline-panel">
                                        <input type="email"
                                               x-model="inlineLoginEmail"
                                               placeholder="E-Mail"
                                               autocomplete="email"
                                               required
                                               aria-label="E-Mail"
                                               class="sf-lobby-inline-input">

                                        <div class="sf-lobby-pwd-wrap">
                                            <input :type="inlineLoginShowPassword ? 'text' : 'password'"
                                                   x-model="inlineLoginPassword"
                                                   placeholder="Passwort"
                                                   autocomplete="current-password"
                                                   required
                                                   aria-label="Passwort"
                                                   class="sf-lobby-inline-input">
                                            <button type="button"
                                                    data-testid="sanctum-login-password-eye"
                                                    @click="inlineLoginShowPassword = !inlineLoginShowPassword"
                                                    class="sf-lobby-pwd-eye"
                                                    :aria-label="inlineLoginShowPassword ? 'Passwort verbergen' : 'Passwort anzeigen'"
                                                    :title="inlineLoginShowPassword ? 'Passwort verbergen' : 'Passwort anzeigen'">
                                                <svg x-show="!inlineLoginShowPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                <svg x-show="inlineLoginShowPassword" x-cloak width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                                </svg>
                                            </button>
                                        </div>

                                        <input x-show="inlineLoginTwoFactorRequired"
                                               x-cloak
                                               type="text"
                                               inputmode="numeric"
                                               maxlength="10"
                                               x-model="inlineLoginTwoFactorCode"
                                               placeholder="2FA-Code"
                                               autocomplete="one-time-code"
                                               aria-label="2FA-Code"
                                               class="sf-lobby-inline-input">

                                        <div x-show="inlineAuthError !== ''"
                                             x-cloak
                                             class="sf-lobby-inline-error"
                                             x-text="inlineAuthError"></div>

                                        <button type="submit"
                                                data-testid="sanctum-inline-login-submit"
                                                :disabled="inlineAuthBusy"
                                                class="sf-lobby-prod-cta"
                                                style="margin-top:2px;border:none;">
                                            <span x-text="inlineAuthBusy ? 'Anmeldung...' : 'Anmelden'"></span>
                                        </button>
                                    </form>
                                </template>

                                <div class="sf-lobby-sanctum-row"
                                     data-testid="sanctum-check-magic"
                                     @click="handleSanctumStepClick('magic')"
                                     :class="{ 'is-done': sanctumGateState.checks.magic, 'is-clickable': !sanctumGateState.checks.magic }">
                                    <span>✨ Magie aktivieren</span>
                                    <span class="sf-lobby-sanctum-check"
                                          x-text="sanctumGateState.checks.magic ? '✓' : '✕'"></span>
                                </div>

                                <template x-if="sanctumGateState.checks.login && !sanctumGateState.checks.magic && sanctumExpandedStep === 'magic'">
                                    <div data-testid="sanctum-inline-magic-panel" class="sf-lobby-inline-panel">
                                        <label x-ref="inlineMagicTermsBox"
                                               class="sf-lobby-inline-check sf-lobby-inline-terms-box sf-magic-thin-aura"
                                               :class="{ 'is-checked': inlineMagicAcceptTerms }">
                                            <span x-show="!inlineMagicAcceptTerms" class="sf-magic-thin-aura-ring" aria-hidden="true"></span>
                                            <input type="checkbox"
                                                   data-testid="sanctum-magic-terms-checkbox"
                                                   x-model="inlineMagicAcceptTerms"
                                                   @change="onInlineMagicTermsChange()"
                                                   style="width:16px;height:16px;accent-color:#C5A059;cursor:pointer;flex-shrink:0;">
                                            <span>Teilnahmebedingungen akzeptieren</span>
                                        </label>

                                        <label class="sf-lobby-inline-check" style="padding:2px 11px;">
                                            <input type="checkbox"
                                                   data-testid="sanctum-magic-ranking-checkbox"
                                                   x-model="inlineMagicRankingOptIn"
                                                   style="width:16px;height:16px;accent-color:#C5A059;cursor:pointer;flex-shrink:0;">
                                            <span>In der Halle der Helden sichtbar</span>
                                        </label>

                                        <div x-show="inlineAuthError !== ''"
                                             x-cloak
                                             class="sf-lobby-inline-error"
                                             x-text="inlineAuthError"></div>

                                        <button type="button"
                                                x-ref="inlineMagicActivateBtn"
                                                data-testid="sanctum-inline-magic-submit"
                                                :disabled="inlineAuthBusy"
                                                :aria-disabled="(!inlineMagicAcceptTerms || inlineAuthBusy) ? 'true' : 'false'"
                                                @mouseenter="if (!inlineMagicAcceptTerms) nudgeInlineMagicTermsHint($el)"
                                                @click="if (!inlineMagicAcceptTerms) { nudgeInlineMagicTermsHint($el); return; } submitInlineActivateMagic($el)"
                                                class="sf-lobby-prod-cta sf-magic-thin-aura sf-magic-thin-aura-cta"
                                                :class="(!inlineMagicAcceptTerms || inlineAuthBusy) ? 'is-locked bg-gray-200 text-gray-500 border border-gray-300 shadow-none opacity-55 grayscale cursor-not-allowed' : 'cursor-pointer'"
                                                style="margin-top:2px;position:relative;overflow:hidden;">
                                            <span x-show="inlineMagicCtaAura && inlineMagicAcceptTerms" x-cloak class="sf-magic-thin-aura-ring" aria-hidden="true"></span>
                                            <span style="position:relative;z-index:2;" x-text="inlineAuthBusy ? 'Aktivierung...' : '✨ Magie aktivieren'"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <template x-if="!sanctumGateState.checks.login && sanctumExpandedStep !== 'login'">
                                    <button type="button"
                                            @click="handleSanctumStepClick('login')"
                                            data-testid="sanctum-gate-cta-btn"
                                            class="sf-lobby-prod-cta"
                                            style="margin-top:2px;border:none;cursor:pointer;">
                                        <span>Anmelden</span>
                                        <span>&rarr;</span>
                                    </button>
                                </template>
                                <template x-if="sanctumGateState.checks.login && !sanctumGateState.checks.magic && sanctumExpandedStep !== 'magic'">
                                    <button type="button"
                                            @click="triggerSanctumMagicCta()"
                                            data-testid="sanctum-gate-cta-btn"
                                            class="sf-lobby-prod-cta"
                                            style="margin-top:2px;border:none;cursor:pointer;">
                                        <span>✨ Magie aktivieren</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Clean Light-Mode Starter-Charakterauswahl (Universelle Vorlage Maennlich & Weiblich, 0 Subtexte) --}}
                    <div x-show="characterPickerOpen && !isLoading && loadError === ''"
                         x-cloak
                         @pointerdown.stop
                         @mousedown.stop
                         @click.self="closeCharacterPicker()"
                         data-testid="lobby-character-picker-modal"
                         class="sf-lobby-settings-backdrop">
                        <div class="sf-lobby-settings-card"
                             @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             role="dialog"
                             aria-label="Charakter wählen"
                             style="max-width:360px;">
                            <div class="sf-lobby-settings-head">
                                <h3 class="sf-lobby-settings-title">
                                    <span style="color:#C5A059;">✦</span>
                                    <span>Charakter wählen</span>
                                </h3>
                                <button type="button"
                                        @click="closeCharacterPicker()"
                                        style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;"
                                        aria-label="Schließen">&times;</button>
                            </div>

                            <div class="sf-lobby-char-grid">
                                <button type="button"
                                        data-testid="lobby-char-select-male"
                                        @click="chooseCharacter('male')"
                                        class="sf-lobby-char-card"
                                        :class="{ 'is-selected': (userSettings.characterGender || 'male') !== 'female' }">
                                    <span x-show="(userSettings.characterGender || 'male') !== 'female'"
                                          class="sf-lobby-char-check">&#10003;</span>
                                    <span class="sf-lobby-char-emblem" aria-hidden="true">
                                        <svg width="42" height="42" viewBox="0 0 48 48" fill="none">
                                            <circle cx="24" cy="18.5" r="9.5" fill="#F3D2BA" stroke="#C5A059" stroke-width="1.8"/>
                                            <path d="M14.8 16.5C15.5 10.5 19.5 8 24 8C28.5 8 32.5 10.5 33.2 16.5C30.2 14.2 27.2 13.4 24 13.4C20.8 13.4 17.8 14.2 14.8 16.5Z" fill="#6B442A"/>
                                            <circle cx="20.6" cy="19.2" r="1.35" fill="#3B2314"/>
                                            <circle cx="27.4" cy="19.2" r="1.35" fill="#3B2314"/>
                                            <path d="M21.4 22.8Q24 24.6 26.6 22.8" stroke="#9C5A44" stroke-width="1.5" stroke-linecap="round"/>
                                            <path d="M12.5 40C13.5 32.5 18 29.5 24 29.5C30 29.5 34.5 32.5 35.5 40Z" fill="#C9B38C" stroke="#C5A059" stroke-width="1.6"/>
                                        </svg>
                                    </span>
                                    <span class="sf-lobby-char-name">Männlich</span>
                                    <span class="sf-lobby-char-tag">Starter · Barfuß</span>
                                </button>

                                <button type="button"
                                        data-testid="lobby-char-select-female"
                                        @click="chooseCharacter('female')"
                                        class="sf-lobby-char-card"
                                        :class="{ 'is-selected': userSettings.characterGender === 'female' }">
                                    <span x-show="userSettings.characterGender === 'female'"
                                          class="sf-lobby-char-check">&#10003;</span>
                                    <span class="sf-lobby-char-emblem" aria-hidden="true">
                                        <svg width="42" height="42" viewBox="0 0 48 48" fill="none">
                                            <path d="M31.5 17C35.8 18.5 37.5 23.5 35.2 28.5C33.8 25.5 32.5 23.2 30.8 21.5Z" fill="#6B442A"/>
                                            <circle cx="24" cy="18.5" r="9.2" fill="#F6D8C2" stroke="#C5A059" stroke-width="1.8"/>
                                            <path d="M14.8 17.5C15.4 10.6 19.4 8 24 8C28.6 8 32.6 10.6 33.2 17.5C30.4 14.6 27.3 13.5 24 13.5C20.7 13.5 17.6 14.6 14.8 17.5Z" fill="#7A4E31"/>
                                            <circle cx="20.6" cy="19.2" r="1.4" fill="#3B2314"/>
                                            <circle cx="27.4" cy="19.2" r="1.4" fill="#3B2314"/>
                                            <path d="M18.9 18.1L17.7 17.2M29.1 18.1L30.3 17.2" stroke="#3B2314" stroke-width="1.2" stroke-linecap="round"/>
                                            <path d="M21.4 22.8Q24 24.8 26.6 22.8" stroke="#B65E53" stroke-width="1.5" stroke-linecap="round"/>
                                            <path d="M13.2 40C14.2 32.8 18.4 29.8 24 29.8C29.6 29.8 33.8 32.8 34.8 40Z" fill="#CEB994" stroke="#C5A059" stroke-width="1.6"/>
                                        </svg>
                                    </span>
                                    <span class="sf-lobby-char-name">Weiblich</span>
                                    <span class="sf-lobby-char-tag">Starter · Barfuß</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Kompaktes Light-Mode ESC-Einstellungsmenü (⚙️ Gamification Clean-Design, 0 Subtexte) --}}
                    <div x-show="settingsOpen"
                         x-cloak
                         @pointerdown.stop
                         @mousedown.stop
                         @click.self="closeSettingsModal()"
                         data-testid="lobby-settings-modal"
                         class="sf-lobby-settings-backdrop">
                        <div class="sf-lobby-settings-card"
                             @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             role="dialog"
                             aria-label="Einstellungen">
                            <div class="sf-lobby-settings-head">
                                <h3 class="sf-lobby-settings-title">
                                    <span>⚙️</span>
                                    <span>Einstellungen</span>
                                </h3>
                                <button type="button"
                                        @click="closeSettingsModal()"
                                        style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;"
                                        aria-label="Schließen">&times;</button>
                            </div>

                            <div class="sf-lobby-settings-section">
                                <div class="sf-lobby-settings-row">
                                    <span class="sf-lobby-settings-label">Lautstärke</span>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:11px;font-weight:800;color:#9A7738;"
                                              x-text="(userSettings.volume <= 0 || audioState.allMuted) ? 'Stumm' : (userSettings.volume + ' %')"></span>
                                        <button type="button"
                                                data-testid="lobby-settings-mute-btn"
                                                @click="toggleQuickMute()"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': !audioState.allMuted && userSettings.volume > 0 }"
                                                style="padding:4px 10px;font-size:10.5px;"
                                                x-text="(audioState.allMuted || userSettings.volume <= 0) ? '🔇 Stumm' : '🔊 Ton an'"></button>
                                    </div>
                                </div>
                                <input type="range"
                                       min="0"
                                       max="100"
                                       step="1"
                                       :value="userSettings.volume"
                                       @input="setMasterVolume($event.target.value)"
                                       data-testid="lobby-settings-volume-slider"
                                       class="sf-lobby-slider"
                                       aria-label="Lautstärke">
                            </div>

                            <div class="sf-lobby-settings-section">
                                <div class="sf-lobby-settings-row">
                                    <span class="sf-lobby-settings-label">Vollbild</span>
                                    <button type="button"
                                            data-testid="lobby-settings-fullscreen-btn"
                                            @click="toggleFullscreen()"
                                            class="sf-lobby-step-btn"
                                            :class="{ 'is-active': isFullscreen }"
                                            x-text="isFullscreen ? 'Vollbild beenden' : 'Vollbild aktivieren'"></button>
                                </div>
                            </div>

                            <div class="sf-lobby-settings-section">
                                <div class="sf-lobby-settings-row">
                                    <span class="sf-lobby-settings-label">Kamera-Tempo</span>
                                    <span style="font-size:11px;font-weight:800;color:#9A7738;"
                                          x-text="userSettings.cameraSensitivity + ' %'"></span>
                                </div>
                                <input type="range"
                                       min="50"
                                       max="150"
                                       step="1"
                                       :value="userSettings.cameraSensitivity"
                                       @input="setCameraSensitivity($event.target.value)"
                                       data-testid="lobby-camera-sensitivity-slider"
                                       class="sf-lobby-slider"
                                       aria-label="Kamera-Tempo">
                            </div>

                            <div class="sf-lobby-settings-section">
                                <div class="sf-lobby-settings-row">
                                    <span class="sf-lobby-settings-label">Charakter</span>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <button type="button"
                                                data-testid="lobby-settings-char-male"
                                                @click="selectCharacterGender('male')"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': (userSettings.characterGender || 'male') !== 'female' }">
                                            Männlich
                                        </button>
                                        <button type="button"
                                                data-testid="lobby-settings-char-female"
                                                @click="selectCharacterGender('female')"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': userSettings.characterGender === 'female' }">
                                            Weiblich
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="sf-lobby-settings-section">
                                <div class="sf-lobby-settings-row">
                                    <span class="sf-lobby-settings-label">Himmel</span>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <button type="button"
                                                data-testid="lobby-settings-sky-auto"
                                                @click="setSkyTimeMode('auto')"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': !userSettings.skyTimeMode || userSettings.skyTimeMode === 'auto' }">
                                            Auto
                                        </button>
                                        <button type="button"
                                                data-testid="lobby-settings-sky-day"
                                                @click="setSkyTimeMode('day')"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': userSettings.skyTimeMode === 'day' }">
                                            Tag
                                        </button>
                                        <button type="button"
                                                data-testid="lobby-settings-sky-night"
                                                @click="setSkyTimeMode('night')"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': userSettings.skyTimeMode === 'night' }">
                                            Nacht
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="sf-lobby-settings-section">
                                <div class="sf-lobby-settings-row">
                                    <span class="sf-lobby-settings-label">Grafik-Stufe</span>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <button type="button"
                                                @click="setGraphicsQuality('high')"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': (userSettings.graphicsQuality || 'high') === 'high' }">
                                            Hoch
                                        </button>
                                        <button type="button"
                                                @click="setGraphicsQuality('balanced')"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': userSettings.graphicsQuality === 'balanced' }">
                                            Ausgewogen
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="sf-lobby-settings-section">
                                <label class="sf-lobby-settings-row" style="cursor:pointer;">
                                    <span class="sf-lobby-settings-label">Echten Vornamen nutzen</span>
                                    <input type="checkbox"
                                           data-testid="lobby-use-real-name-checkbox"
                                           :checked="userSettings.useRealName"
                                           @change="setUseRealName($event.target.checked)"
                                           style="width:17px;height:17px;accent-color:#C5A059;cursor:pointer;">
                                </label>
                            </div>

                            <div class="sf-lobby-settings-section">
                                <div class="sf-lobby-settings-row">
                                    <span class="sf-lobby-settings-label">Steuerung</span>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <button type="button"
                                                x-show="keybindingsExpanded"
                                                data-testid="lobby-keybind-reset-btn"
                                                @click="resetKeybindings()"
                                                class="sf-lobby-step-btn">
                                            Zurücksetzen
                                        </button>
                                        <button type="button"
                                                data-testid="lobby-keybind-expand-btn"
                                                @click="toggleKeybindingsExpanded()"
                                                class="sf-lobby-step-btn"
                                                :class="{ 'is-active': keybindingsExpanded }"
                                                x-text="keybindingsExpanded ? 'Fertig' : 'Ändern'"></button>
                                    </div>
                                </div>

                                <div x-show="keybindingsExpanded" class="sf-lobby-keybind-grid">
                                    <template x-for="item in keybindActionsList()" :key="item.key">
                                        <button type="button"
                                                @click="startListeningKey(item.key)"
                                                class="sf-lobby-keybind-item"
                                                :class="{ 'is-listening': listeningKeyAction === item.key }">
                                            <span x-text="item.label"></span>
                                            <span class="sf-lobby-keybind-pill"
                                                  x-text="listeningKeyAction === item.key ? 'Taste...' : keyLabel(item.key)"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div style="display:flex;align-items:center;gap:8px;padding-top:2px;">
                                <button type="button"
                                        @click="closeSettingsModal()"
                                        class="sf-lobby-prod-cta"
                                        style="margin-top:0;flex:1;border:none;cursor:pointer;">
                                    Weiter erkunden
                                </button>
                                <button type="button"
                                        data-testid="lobby-settings-exit-btn"
                                        @click="closeShowroom()"
                                        class="sf-lobby-step-btn"
                                        style="padding:9px 14px;border-radius:12px;">
                                    Atelier verlassen
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Ein-/ausklappbarer Light-Mode Raum-Chat (nur in der Multiplayer-Halle der Helden) --}}
                    <div x-show="!isLoading && loadError === '' && currentZone === 'heroes' && !soloMode && !multiplayerDisabled && chatOpen && focusedProduct === null"
                         @pointerdown.stop
                         @mousedown.stop
                         @click.stop
                         data-testid="louvre-room-chat-panel"
                         class="sf-lobby-chat-panel"
                         :class="{ 'is-expanded': chatExpanded }">
                        <div class="sf-lobby-chat-head">
                            <div style="display:flex;align-items:center;gap:6px;min-width:0;">
                                <span class="sf-lobby-dot is-live"></span>
                                <span style="font-size:11px;font-weight:800;letter-spacing:0.05em;text-transform:uppercase;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                      x-text="'Instanz #' + lobbyInstanceId + ' · ' + (chatTab === 'friends' ? 'Freunde' : 'Chat')"></span>
                            </div>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <button type="button"
                                        @click="chatExpanded = !chatExpanded"
                                        style="appearance:none;width:24px;height:24px;border-radius:8px;border:none;background:transparent;color:#4B5563;font-size:12px;font-weight:800;cursor:pointer;"
                                        :title="chatExpanded ? 'Fenster verkleinern' : 'Fenster vergrößern'"
                                        x-text="chatExpanded ? '🗗' : '⛶'"></button>
                                <button type="button"
                                        @click="chatOpen = false"
                                        style="appearance:none;width:24px;height:24px;border-radius:8px;border:none;background:transparent;color:#4B5563;font-size:15px;font-weight:800;cursor:pointer;"
                                        aria-label="Chat minimieren">&times;</button>
                            </div>
                        </div>

                        {{-- Tab-Umschalter für Chat & Freundesystem --}}
                        <div style="display:flex;align-items:center;gap:4px;padding:2px 0 6px;border-bottom:1px solid rgba(197, 160, 89, 0.2);">
                            <button type="button"
                                    @click="chatTab = 'chat'"
                                    :style="chatTab === 'chat' ? 'background:#C5A059;color:#FFF;border-color:#C5A059;' : 'background:#FAF8F5;color:#4B5563;border-color:rgba(197,160,89,0.3);'"
                                    style="appearance:none;border-radius:7px;border-width:1px;border-style:solid;padding:3px 9px;font-size:10px;font-weight:800;cursor:pointer;">
                                Chat
                            </button>
                            <button type="button"
                                    @click="chatTab = 'friends'"
                                    :style="chatTab === 'friends' ? 'background:#C5A059;color:#FFF;border-color:#C5A059;' : 'background:#FAF8F5;color:#4B5563;border-color:rgba(197,160,89,0.3);'"
                                    style="appearance:none;border-radius:7px;border-width:1px;border-style:solid;padding:3px 9px;font-size:10px;font-weight:800;cursor:pointer;">
                                Freunde <span x-show="friendsList.length > 0" x-text="'(' + friendsList.length + ')'"></span>
                            </button>
                        </div>

                        {{-- Fall A: Nicht angemeldete Gäste erhalten eine saubere Anmeldekarte --}}
                        <template x-if="!isCustomerAuthenticated()">
                            <div style="padding:16px 12px;text-align:center;background:#FAF8F5;border-radius:12px;border:1px solid rgba(197,160,89,0.35);margin:6px 0;">
                                <div style="font-size:22px;margin-bottom:6px;">✨</div>
                                <div style="font-weight:800;font-size:13px;color:#111827;margin-bottom:4px;">Mitglieder-Raum-Chat</div>
                                <p style="font-size:11px;color:#4B5563;line-height:1.4;margin:0 0 12px 0;">
                                    Melde dich an, um mit anderen Besuchern im 3D-Showroom live zu chatten, Animationen (/dance) auszuführen und Freunde hinzuzufügen.
                                </p>
                                <a href="/login"
                                   style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;background:#C5A059;color:#FFFFFF;font-size:11px;font-weight:800;text-decoration:none;">
                                    <span>Jetzt anmelden</span>
                                    <span>&rarr;</span>
                                </a>
                            </div>
                        </template>

                        {{-- Fall B: Angemeldete Kunden sehen den echten Chat --}}
                        <template x-if="isCustomerAuthenticated() && chatTab === 'chat'">
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <div x-ref="chatScrollBox" class="sf-lobby-chat-scroll">
                                    <template x-if="visibleChatMessages().length === 0">
                                        <div style="padding:12px 0;text-align:center;font-size:11px;font-weight:600;color:#6B7280;">
                                            Noch keine Nachrichten im Raum.
                                        </div>
                                    </template>
                                    <template x-for="msg in visibleChatMessages()" :key="msg.id">
                                        <div class="sf-lobby-chat-msg">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                                                <div style="display:flex;align-items:center;gap:5px;min-width:0;">
                                                    <span style="font-weight:800;font-size:11px;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="msg.display_name"></span>
                                                    <span style="padding:1px 6px;border-radius:5px;background:#FFFFFF;border:1px solid rgba(197,160,89,0.45);font-size:9px;font-weight:900;color:#9A7738;flex-shrink:0;"
                                                          x-text="msg.class_label || 'KLASSE C'"></span>
                                                </div>
                                                <div x-show="!isOwnChatMessage(msg)" style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
                                                    <button type="button"
                                                            @click="toggleFriend(msgPeerKey(msg), msg.display_name)"
                                                            style="appearance:none;border:none;background:transparent;font-size:10px;font-weight:800;cursor:pointer;padding:0 3px;"
                                                            :style="isFriend(msgPeerKey(msg)) ? 'color:#C5A059;' : 'color:#4B5563;'"
                                                            :title="isFriend(msgPeerKey(msg)) ? 'Freund entfernen' : 'Als Freund hinzufügen'"
                                                            x-text="isFriend(msgPeerKey(msg)) ? '★ Freund' : '+ Freund'"></button>
                                                    <button type="button"
                                                            @click="mutePeer(msgPeerKey(msg))"
                                                            style="appearance:none;border:none;background:transparent;font-size:10px;font-weight:800;color:#6B7280;cursor:pointer;padding:0 3px;"
                                                            title="Spieler lokal stummschalten">Stumm</button>
                                                    <button type="button"
                                                            @click="reportPeer(msgPeerKey(msg))"
                                                            style="appearance:none;border:none;background:transparent;font-size:10px;font-weight:800;color:#DC2626;cursor:pointer;padding:0 3px;"
                                                            title="Unangemessene Nachricht melden">Melden</button>
                                                </div>
                                            </div>
                                            <p style="margin:3px 0 0;font-size:11px;color:#1F2937;line-height:1.35;word-break:break-word;" x-text="msg.text"></p>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="hasActiveChatModerationNotice()"
                                     x-cloak
                                     data-testid="louvre-chat-moderation-banner"
                                     :style="chatStrikeInfo.isMuted
                                         ? 'padding:8px 10px;border-radius:11px;background:#FEF2F2;border:1px solid #FECACA;border-left:3px solid #DC2626;display:flex;flex-direction:column;gap:4px;'
                                         : 'padding:8px 10px;border-radius:11px;background:#FEF2F2;border:1px solid rgba(197,160,89,0.55);border-left:3px solid #C5A059;display:flex;flex-direction:column;gap:4px;'"
                                     role="alert">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;flex-wrap:wrap;">
                                        <span data-testid="louvre-chat-moderation-badge"
                                              :style="chatStrikeInfo.isMuted
                                                  ? 'display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:999px;background:#DC2626;color:#FFFFFF;font-size:10px;font-weight:900;letter-spacing:0.03em;text-transform:uppercase;'
                                                  : 'display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:999px;background:#B91C1C;color:#FFFFFF;border:1px solid #C5A059;font-size:10px;font-weight:900;letter-spacing:0.03em;text-transform:uppercase;'"
                                              x-text="chatStrikeBadgeLabel()"></span>
                                        <span x-show="chatStrikeInfo.isMuted"
                                              data-testid="louvre-chat-moderation-timer"
                                              style="font-size:10px;font-weight:800;color:#991B1B;white-space:nowrap;"
                                              x-text="chatMuteCountdownLabel()"></span>
                                    </div>
                                    <div x-show="chatStrikeInfo.bannerText !== ''"
                                         style="font-size:11px;font-weight:700;color:#7F1D1D;line-height:1.35;word-break:break-word;"
                                         x-text="chatStrikeInfo.bannerText"></div>
                                </div>

                                <div x-show="chatFeedback !== ''"
                                     style="padding:6px 9px;border-radius:10px;background:#FAF8F5;border:1px solid rgba(197,160,89,0.45);font-size:11px;font-weight:700;color:#111827;"
                                     x-text="chatFeedback"></div>

                                {{-- Schnellwahl für Animationen/Slash-Befehle --}}
                                <div style="display:flex;align-items:center;gap:4px;overflow-x:auto;padding-bottom:2px;">
                                    <button type="button" :disabled="chatStrikeInfo.isMuted" @click="sendChatMessage('/dance')" class="sf-emote-chip" :style="chatStrikeInfo.isMuted ? 'opacity:0.45;cursor:not-allowed;' : ''" title="Tanzen (/dance)">💃 /dance</button>
                                    <button type="button" :disabled="chatStrikeInfo.isMuted" @click="sendChatMessage('/wave')" class="sf-emote-chip" :style="chatStrikeInfo.isMuted ? 'opacity:0.45;cursor:not-allowed;' : ''" title="Winken (/wave)">👋 /wave</button>
                                    <button type="button" :disabled="chatStrikeInfo.isMuted" @click="sendChatMessage('/bow')" class="sf-emote-chip" :style="chatStrikeInfo.isMuted ? 'opacity:0.45;cursor:not-allowed;' : ''" title="Verbeugen (/bow)">🙇 /bow</button>
                                    <button type="button" :disabled="chatStrikeInfo.isMuted" @click="sendChatMessage('/cheer')" class="sf-emote-chip" :style="chatStrikeInfo.isMuted ? 'opacity:0.45;cursor:not-allowed;' : ''" title="Jubeln (/cheer)">🙌 /cheer</button>
                                    <button type="button" :disabled="chatStrikeInfo.isMuted" @click="sendChatMessage('/sit')" class="sf-emote-chip" :style="chatStrikeInfo.isMuted ? 'opacity:0.45;cursor:not-allowed;' : ''" title="Hinsetzen (/sit)">🧘 /sit</button>
                                    <button type="button" :disabled="chatStrikeInfo.isMuted" @click="sendChatMessage('/clap')" class="sf-emote-chip" :style="chatStrikeInfo.isMuted ? 'opacity:0.45;cursor:not-allowed;' : ''" title="Klatschen (/clap)">👏 /clap</button>
                                </div>

                                <form @submit.prevent="sendChatMessage()" style="display:flex;align-items:center;gap:6px;padding-top:2px;">
                                    <input type="text"
                                           data-testid="louvre-chat-input"
                                           x-model="chatDraft"
                                           :disabled="chatStrikeInfo.isMuted"
                                           maxlength="100"
                                           :placeholder="chatStrikeInfo.isMuted ? chatMutePlaceholder() : 'Nachricht oder /dance...'"
                                           :style="chatStrikeInfo.isMuted
                                               ? 'flex:1;min-width:0;padding:7px 10px;border-radius:10px;background:#F3F4F6;border:1px solid #FECACA;font-size:12px;font-weight:700;color:#991B1B;outline:none;cursor:not-allowed;'
                                               : 'flex:1;min-width:0;padding:7px 10px;border-radius:10px;background:#FAF8F5;border:1px solid rgba(197,160,89,0.5);font-size:12px;font-weight:600;color:#111827;outline:none;'">
                                    <button type="submit"
                                            data-testid="louvre-chat-submit-btn"
                                            :disabled="chatSending || chatStrikeInfo.isMuted"
                                            :style="(chatSending || chatStrikeInfo.isMuted)
                                                ? 'appearance:none;padding:7px 12px;border-radius:10px;border:none;background:#D1D5DB;color:#6B7280;font-size:12px;font-weight:800;cursor:not-allowed;flex-shrink:0;'
                                                : 'appearance:none;padding:7px 12px;border-radius:10px;border:none;background:#C5A059;color:#FFFFFF;font-size:12px;font-weight:800;cursor:pointer;flex-shrink:0;'">
                                        Senden
                                    </button>
                                </form>
                            </div>
                        </template>

                        {{-- Fall C: Freundesliste Tab --}}
                        <template x-if="isCustomerAuthenticated() && chatTab === 'friends'">
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <div class="sf-lobby-friends-scroll">
                                    <template x-if="friendsList.length === 0">
                                        <div style="padding:16px 8px;text-align:center;font-size:11px;font-weight:600;color:#6B7280;">
                                            Noch keine Freunde hinzugefügt.<br>
                                            <span style="font-size:10px;color:#9CA3AF;">Klicke im Chat auf <strong>+ Freund</strong> neben einem Spieler!</span>
                                        </div>
                                    </template>
                                    <template x-for="f in friendsList" :key="f.player_id">
                                        <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 8px;background:#FAF8F5;border:1px solid rgba(197,160,89,0.25);border-radius:10px;">
                                            <div style="display:flex;align-items:center;gap:6px;min-width:0;">
                                                <span class="sf-lobby-dot" :class="isPeerOnline(f.player_id) ? 'is-live' : ''" :title="isPeerOnline(f.player_id) ? 'Online im Raum' : 'Offline'"></span>
                                                <span style="font-size:11px;font-weight:800;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="f.display_name"></span>
                                                <span x-show="isPeerOnline(f.player_id)" style="font-size:9px;font-weight:800;color:#16A34A;background:#DCFCE7;padding:1px 5px;border-radius:4px;">Im Raum</span>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:4px;">
                                                <button type="button"
                                                        @click="waveToFriend(f.player_id, f.display_name)"
                                                        style="appearance:none;border:none;background:transparent;cursor:pointer;font-size:12px;padding:2px;"
                                                        title="Freund zuwinken">👋</button>
                                                <button type="button"
                                                        @click="toggleFriend(f.player_id, f.display_name)"
                                                        style="appearance:none;border:none;background:transparent;cursor:pointer;font-size:12px;color:#9CA3AF;padding:2px;"
                                                        title="Freund entfernen">&times;</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Virtueller Mobile-Stick unten links --}}
                    <div x-show="!isLoading && loadError === ''" class="sf-lobby-stick-wrap">
                        <div x-ref="stickZone"
                             @pointerdown.stop.prevent="startStick($event)"
                             @pointermove.stop.prevent="moveStick($event)"
                             @pointerup.stop.prevent="endStick()"
                             @pointercancel.stop.prevent="endStick()"
                             class="sf-lobby-stick"
                             aria-label="Bewegungssteuerung">
                            <div class="sf-lobby-stick-knob"
                                 :style="'transform: translate3d(' + stickUiX + 'px, ' + stickUiY + 'px, 0)'"></div>
                        </div>
                    </div>

                    {{-- Schlanke Schnellwahl über der Zierleiste (Halle der Helden Podeste) --}}
                    <div x-show="!isLoading && loadError === '' && currentZone === 'heroes'"
                         class="sf-lobby-bottom-stack">
                        <div @pointerdown.stop
                             @mousedown.stop
                             @click.stop
                             class="sf-lobby-bottom-pill">
                            <template x-for="slotNum in [1, 2, 3, 4, 5]" :key="slotNum">
                                <button type="button"
                                        @click="focusHeroSlot(slotNum)"
                                        :class="focusedHero && focusedHero.slot === slotNum ? 'sf-lobby-step-btn is-active' : 'sf-lobby-step-btn'"
                                        x-text="'#' + slotNum">
                                </button>
                            </template>
                            <button type="button"
                                    @click="focusMyHeroPlace()"
                                    class="sf-lobby-step-btn is-active">
                                ⭐ Mein Platz
                            </button>
                        </div>
                    </div>

                    {{-- Ruhige goldene Zierleiste am unteren Rand: Links © Mein-Seelenfunke + Rechts Einstellungen (Esc) --}}
                    <div x-show="!isLoading && loadError === ''"
                         @pointerdown.stop
                         @mousedown.stop
                         @click.stop
                         data-testid="lobby-gold-crest-bar"
                         class="sf-lobby-gold-crest-bar"
                         style="justify-content:space-between;">
                        <span class="sf-lobby-copyright">&copy; Mein-Seelenfunke</span>

                        <button type="button"
                                @click="toggleSettingsModal()"
                                data-testid="louvre-settings-btn"
                                class="sf-lobby-gear-btn"
                                :class="{ 'is-active': settingsOpen }"
                                title="Einstellungen (Esc)"
                                aria-label="Einstellungen">
                            <span>⚙️ Einstellungen</span>
                            <span style="opacity:0.75;font-size:10px;">Esc</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @php
        $sfInitialAdminAuth = \Illuminate\Support\Facades\Auth::guard('admin')->check();
        $sfInitialCustomer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
        $sfInitialMmorpgCanEnter = $sfInitialAdminAuth || (
            $sfInitialCustomer
            && (!method_exists($sfInitialCustomer, 'trashed') || !$sfInitialCustomer->trashed())
            && (!method_exists($sfInitialCustomer, 'hasVerifiedEmail') || $sfInitialCustomer->hasVerifiedEmail())
            && !$sfInitialCustomer->needs_password_change
            && \Illuminate\Support\Facades\Schema::hasColumn('customers', 'mmorpg_access_enabled')
            && (bool) ($sfInitialCustomer->mmorpg_access_enabled ?? false)
        );
    @endphp
    <script>
        (function () {
            'use strict';

            // Private Closure-Speicher (0 Zugriff auf Engine, Player, Szene oder Koordinaten aus F12-Konsole / Alpine.$data!)
            const privateShowroomFacades = typeof WeakMap !== 'undefined' ? new WeakMap() : new Map();

            window.sfLouvreShowroomModal = function () {
                const instanceToken = Object.freeze({});
                let privateMod = null;
                let privateAudio = null;
                let privateAudioUnsub = null;
                let privateLobbyWs = null;
                let privateWsTickWorker = null;
                let privateWsTickWorkerUrl = null;
                let privateWsSendTimer = null;
                let privateWsPingTimer = null;
                let privateWsWarmupPingTimer = null;
                let privateWsHandshakeFallbackTimer = null;
                let privateWsUrgentTimer = null;
                let privateLastWsSentState = null;
                let privateLastWsSentAtMs = 0;
                let privateLastWsPingSentAtMs = 0;
                let privateWsTickMs = 33;

                const getFacade = () => privateShowroomFacades.get(instanceToken) || null;
                const setFacade = (facade) => {
                    if (facade) {
                        privateShowroomFacades.set(instanceToken, facade);
                    } else {
                        privateShowroomFacades.delete(instanceToken);
                    }
                };

                return {
                    isOpen: false,
                    isFullscreen: false,
                    isLoading: false,
                    loadError: '',
                    currentZone: 'gallery',
                    stagesPreviewMode: 'live',
                    products: [],
                    heroesPayload: {
                        founding_era: true,
                        total_heroes: 0,
                        my_hero_slot: null,
                        customer_logged_in: false,
                        customer_opt_in: false,
                        level_models: {},
                        heroes: []
                    },
                    funkiInfo: { is_guest: true, level: 2, name: '', weapon: null, player_id: null },
                    multiplayer: {
                        enabled: false,
                        is_authenticated: false,
                        solo_only: true,
                        room_id: null,
                        sync_interval_ms: 165,
                        player_id: null,
                        peer_id: null,
                        display_name: null,
                        weapon: null
                    },
                    lobbyAuthenticated: false,
                    lobbyInstanceId: 1,
                    lobbyCapacity: 20,
                    lobbyInstances: [{ id: 1, count: 1, capacity: 20, is_full: false, is_current: true }],
                    instanceMenuOpen: false,
                    customInstanceInput: 1,
                    soloMode: false,
                    multiplayerDisabled: false,
                    _wsDisabledByServer: false,
                    peers: [],
                    chatOpen: false,
                    chatDraft: '',
                    chatSending: false,
                    chatFeedback: '',
                    chatStrikeInfo: {
                        strikes: 0,
                        isMuted: false,
                        remainingSec: 0,
                        permanent: false,
                        bannerText: '',
                        level: ''
                    },
                    _chatMuteTickTimer: null,
                    _chatMuteUntilPerfMs: 0,
                    chatMessages: [],
                    unreadChatCount: 0,
                    mutedPlayerIds: [],
                    chatExpanded: false,
                    chatTab: 'chat',
                    friendsList: [],
                    characterPickerOpen: false,
                    settingsOpen: false,
                    keybindingsExpanded: false,
                    listeningKeyAction: '',
                    userSettings: {
                        volume: 5,
                        cameraSensitivity: 100,
                        useRealName: false,
                        graphicsQuality: 'high',
                        characterGender: 'male',
                        skyTimeMode: 'auto',
                    },
                    keybindings: {
                        jump: 'Space',
                        moveForward: 'KeyW',
                        moveLeft: 'KeyA',
                        moveBack: 'KeyS',
                        moveRight: 'KeyD',
                    },
                    audioState: {
                        bgmVolume: 5,
                        bgmMuted: false,
                        sfxVolume: 5,
                        sfxMuted: false,
                        allMuted: false,
                    },
                    environmentInfo: {
                        season_key: 'autumn',
                        season_label: 'Herbst',
                        holiday_key: null,
                        holiday_label: null,
                        server_time: '12:00',
                    },
                    playerHud: {
                        isAuthenticated: false,
                        canEnterSanctums: false,
                        name: 'Gast-Funki',
                        level: 2,
                        levelTitle: 'Lehrling',
                        funkenBalance: 0,
                        mmorpgCanEnter: @json($sfInitialMmorpgCanEnter),
                        mmorpgEnterUrl: '/funkenreich/enter',
                    },
                    mmorpgCanEnter: @json($sfInitialMmorpgCanEnter),
                    mmorpgEnterUrl: '/funkenreich/enter',
                    atelierReview: {
                        area: 'funkenreich',
                        label: '3D-Atelier',
                        can_rate: false,
                        has_rated: false,
                        my_rating: 0,
                        my_comment: '',
                        submit_url: '/customer/experience-reviews',
                    },
                    atelierStarHover: 0,
                    atelierRatingEditing: false,
                    atelierFeedbackOpen: false,
                    atelierFeedbackDraft: '',
                    atelierRatingBusy: false,
                    atelierStarStationFocused: false,
                    _loginOpenedFromStarStation: false,
                    outerGateModalOpen: false,
                    armoryPrompt: {
                        visible: false,
                        focusedKey: 'chest',
                        focusedLabel: 'Kristall-Brustpanzer',
                        isEquipped: false,
                    },
                    equippedArmory: {
                        head: false,
                        chest: false,
                        hands: false,
                        feet: false,
                        weapon: null,
                    },
                    sanctumGateOpen: false,
                    sanctumGateZone: 'heroes',
                    sanctumExpandedStep: '',
                    inlineLoginEmail: '',
                    inlineLoginPassword: '',
                    inlineLoginShowPassword: false,
                    inlineLoginTwoFactorRequired: false,
                    inlineLoginTwoFactorCode: '',
                    inlineMagicAcceptTerms: false,
                    inlineMagicCtaAura: false,
                    inlineMagicRankingOptIn: true,
                    inlineAuthBusy: false,
                    inlineAuthError: '',
                    _loggedInDuringLobbySession: false,
                    sanctumGateState: {
                        canEnter: false,
                        reason: 'login_required',
                        checks: {
                            login: false,
                            magic: false,
                        },
                        loginUrl: '/login',
                        dashboardUrl: '/dashboard',
                    },
                    _abilityPollTimer: null,
                    productTransitionActive: false,
                    _productTransitionTimer: null,
                    _productFadeTimer: null,
                    activeIndex: 0,
                    focusedProduct: null,
                    focusedHero: null,
                    stickActive: false,
                    stickUiX: 0,
                    stickUiY: 0,
                    prevHtmlOverflow: '',
                    prevOverflow: '',
                    pingTooltipOpen: false,
                    netStats: {
                        pingMs: null,
                        jitterMs: 0,
                        tickHz: 'Atelier · Bereit',
                        tickIntervalMs: 165,
                        lossPct: 0,
                        hasError: false,
                        samples: 0,
                        lastRttMs: 0,
                        sentCount: 0,
                        failCount: 0,
                    },
                    _syncTimer: null,
                    _urgentSyncWatchTimer: null,
                    _pingProbeTimer: null,
                    _pingWarmupTimer: null,
                    _pingProbeInFlight: false,
                    _netSampleIsDirectWire: false,
                    _rttWindow: null,
                    _resetPingOnNextWireSample: false,
                    wsConnected: false,
                    _syncInFlight: false,
                    _syncPendingImmediate: false,
                    _lastSyncSentAtMs: 0,
                    _lastWatchSyncSnap: null,
                    _activeSyncZone: null,
                    _feedbackTimer: null,
                    _fsResizeTimer: null,
                    _seenMessageIds: null,
                    _pageHideBound: null,
                    _captureKeydownBound: null,
                    _fullscreenChangeBound: null,

                    keybindActionsList() {
                        return [
                            { key: 'jump', label: 'Sprung' },
                            { key: 'moveForward', label: 'Vorwärts' },
                            { key: 'moveLeft', label: 'Links' },
                            { key: 'moveBack', label: 'Zurück' },
                            { key: 'moveRight', label: 'Rechts' },
                        ];
                    },

                    keyLabel(actionKey) {
                        const code = (this.keybindings && this.keybindings[actionKey]) ? this.keybindings[actionKey] : '';
                        if (privateMod && typeof privateMod.formatLobbyKeyLabel === 'function') {
                            return privateMod.formatLobbyKeyLabel(code);
                        }
                        if (code === 'Space') return 'Leertaste';
                        if (code.indexOf('Digit') === 0) return code.slice(5);
                        if (code.indexOf('Key') === 0) return code.slice(3).toUpperCase();
                        return code || '?';
                    },

                    openCharacterPicker() {
                        const facade = getFacade();
                        if (facade && typeof facade.releasePointerLock === 'function') {
                            facade.releasePointerLock();
                        }
                        this.settingsOpen = false;
                        this.sanctumGateOpen = false;
                        this.outerGateModalOpen = false;
                        this.characterPickerOpen = true;
                        if (privateAudio) {
                            privateAudio.playUiClick();
                        }
                    },

                    closeCharacterPicker() {
                        this.characterPickerOpen = false;
                        if (privateAudio) {
                            privateAudio.playUiClick();
                        }
                    },

                    chooseCharacter(gender) {
                        this.selectCharacterGender(gender);
                        this.characterPickerOpen = false;
                    },

                    toggleSettingsModal() {
                        this.characterPickerOpen = false;
                        this.settingsOpen = !this.settingsOpen;
                        if (!this.settingsOpen) {
                            this.listeningKeyAction = '';
                        }
                        if (privateAudio) {
                            privateAudio.playUiClick();
                        }
                    },

                    closeSettingsModal() {
                        this.settingsOpen = false;
                        this.listeningKeyAction = '';
                    },

                    toggleKeybindingsExpanded() {
                        this.keybindingsExpanded = !this.keybindingsExpanded;
                        if (!this.keybindingsExpanded) {
                            this.listeningKeyAction = '';
                        }
                        if (privateAudio) privateAudio.playUiClick();
                    },

                    startListeningKey(actionKey) {
                        this.listeningKeyAction = (this.listeningKeyAction === actionKey) ? '' : actionKey;
                    },

                    assignKeybinding(actionKey, code) {
                        if (!actionKey || !code) return;
                        const updated = Object.assign({}, this.keybindings, { [actionKey]: code });
                        const facade = getFacade();
                        if (facade && typeof facade.updateSettings === 'function') {
                            facade.updateSettings({ keybindings: updated });
                            const snap = facade.getHudSnapshot();
                            this.keybindings = (snap && snap.keybindings) ? Object.assign({}, snap.keybindings) : updated;
                        } else if (privateMod && typeof privateMod.saveLobbyKeybindings === 'function') {
                            this.keybindings = privateMod.saveLobbyKeybindings(updated);
                        } else {
                            this.keybindings = updated;
                        }
                        this.listeningKeyAction = '';
                        if (privateAudio) privateAudio.playUiSwitch();
                    },

                    resetKeybindings() {
                        const facade = getFacade();
                        if (facade && typeof facade.updateSettings === 'function') {
                            facade.updateSettings({ resetKeybindings: true });
                            const snap = facade.getHudSnapshot();
                            if (snap && snap.keybindings) {
                                this.keybindings = Object.assign({}, snap.keybindings);
                            }
                        } else if (privateMod && typeof privateMod.resetLobbyKeybindings === 'function') {
                            this.keybindings = privateMod.resetLobbyKeybindings();
                        }
                        this.listeningKeyAction = '';
                        if (privateAudio) privateAudio.playUiSwitch();
                    },

                    setMasterVolume(val) {
                        const vol = Math.max(0, Math.min(100, Math.round(Number(val) || 0)));
                        this.userSettings.volume = vol;
                        const facade = getFacade();
                        if (facade && typeof facade.updateSettings === 'function') {
                            facade.updateSettings({ volume: vol });
                        } else if (privateMod && typeof privateMod.saveLobbyUserSettings === 'function') {
                            privateMod.saveLobbyUserSettings({ volume: vol });
                        }
                        if (privateAudio) {
                            privateAudio.setBgmVolume(vol, true);
                            privateAudio.setBgmMuted(vol === 0, true);
                            privateAudio.setSfxVolume(vol);
                            privateAudio.setSfxMuted(vol === 0);
                            this.audioState = privateAudio.getState();
                        }
                    },

                    setCameraSensitivity(val) {
                        const sens = Math.max(50, Math.min(150, Math.round(Number(val) || 100)));
                        this.userSettings.cameraSensitivity = sens;
                        const facade = getFacade();
                        if (facade && typeof facade.updateSettings === 'function') {
                            facade.updateSettings({ cameraSensitivity: sens });
                        } else if (privateMod && typeof privateMod.saveLobbyUserSettings === 'function') {
                            privateMod.saveLobbyUserSettings({ cameraSensitivity: sens });
                        }
                    },

                    setUseRealName(checked) {
                        const flag = Boolean(checked);
                        this.userSettings.useRealName = flag;
                        const facade = getFacade();
                        if (facade && typeof facade.updateSettings === 'function') {
                            facade.updateSettings({ useRealName: flag });
                        } else if (privateMod && typeof privateMod.saveLobbyUserSettings === 'function') {
                            privateMod.saveLobbyUserSettings({ useRealName: flag });
                        }
                        if (privateAudio) privateAudio.playUiSwitch();
                    },

                    setGraphicsQuality(level) {
                        const clean = level === 'balanced' ? 'balanced' : 'high';
                        this.userSettings.graphicsQuality = clean;
                        const facade = getFacade();
                        if (facade && typeof facade.setGraphicsQuality === 'function') {
                            facade.setGraphicsQuality(clean);
                        } else if (privateMod && typeof privateMod.saveLobbyUserSettings === 'function') {
                            privateMod.saveLobbyUserSettings({ graphicsQuality: clean });
                        }
                        if (privateAudio) privateAudio.playUiSwitch();
                    },

                    selectCharacterGender(gender) {
                        const clean = gender === 'female' ? 'female' : 'male';
                        this.userSettings = Object.assign({}, this.userSettings, { characterGender: clean });
                        const facade = getFacade();
                        if (facade && typeof facade.setCharacterGender === 'function') {
                            facade.setCharacterGender(clean);
                        } else if (privateMod && typeof privateMod.saveLobbyUserSettings === 'function') {
                            privateMod.saveLobbyUserSettings({ characterGender: clean });
                        }
                        if (this.isOpen && this.currentZone === 'heroes' && !this.soloMode && !this.multiplayerDisabled) {
                            this._sendWsStateNow(true);
                            this.requestImmediateMultiplayerSync();
                        }
                        if (privateAudio) privateAudio.playUiSwitch();
                    },

                    setSkyTimeMode(mode) {
                        const clean = (mode === 'day' || mode === 'night') ? mode : 'auto';
                        this.userSettings = Object.assign({}, this.userSettings, { skyTimeMode: clean });
                        const facade = getFacade();
                        if (facade && typeof facade.updateSettings === 'function') {
                            facade.updateSettings({ skyTimeMode: clean });
                            if (typeof facade.getEnvironment === 'function') {
                                this.environmentInfo = Object.assign({}, this.environmentInfo, facade.getEnvironment());
                            }
                        } else if (privateMod && typeof privateMod.saveLobbyUserSettings === 'function') {
                            privateMod.saveLobbyUserSettings({ skyTimeMode: clean });
                        }
                        if (privateAudio) privateAudio.playUiSwitch();
                    },

                    minimapTooltip() {
                        const season = this.environmentInfo.season_label || 'Herbst';
                        const skySuffix = this.environmentInfo.is_night ? ' · Sternenhimmel' : '';
                        if (this.environmentInfo.holiday_label) {
                            return season + ' · ' + this.environmentInfo.holiday_label + skySuffix;
                        }
                        return season + skySuffix;
                    },

                    _nowPerfMs() {
                        return (typeof performance !== 'undefined' && typeof performance.now === 'function')
                            ? performance.now()
                            : Date.now();
                    },

                    pingTier() {
                        const ns = this.netStats || {};
                        if (ns.hasError || (ns.pingMs !== null && ns.pingMs > 195)) {
                            return 'delayed';
                        }
                        if (ns.pingMs !== null && ns.pingMs > 95) {
                            return 'good';
                        }
                        return 'optimal';
                    },

                    pingStatusLabel() {
                        const t = this.pingTier();
                        if (t === 'delayed') return 'Verzögert';
                        if (t === 'good') return 'Gut';
                        return 'Optimal';
                    },

                    pingDisplayLabel() {
                        const pm = this.netStats ? this.netStats.pingMs : null;
                        return pm !== null ? (pm + ' ms') : '... ms';
                    },

                    jitterDisplayLabel() {
                        const jm = this.netStats ? (this.netStats.jitterMs || 0) : 0;
                        return '±' + jm + ' ms';
                    },

                    pingModeLabel() {
                        if (this.currentZone === 'heroes') {
                            if (this.isCustomerAuthenticated() && !this.soloMode && !this.multiplayerDisabled) {
                                return this.wsConnected ? 'Echtzeit-WS (30 Hz)' : 'Live-Sync (6 Hz)';
                            }
                            return 'Halle (Solo)';
                        }
                        return 'Atelier (Solo)';
                    },

                    pingInstanceLabel() {
                        return 'Halle #' + this.lobbyInstanceId + ' (' + this.currentInstanceCount() + '/' + this.lobbyCapacity + ')';
                    },

                    recordNetRttSample(rttMs, isDirectWire, tickLabel) {
                        const cleanRtt = Math.max(1, Number(rttMs) || 1);
                        const ns = this.netStats;
                        const sent = (ns.sentCount || 0) + 1;
                        const directFlag = Boolean(isDirectWire);
                        const switchingChannel = (directFlag !== Boolean(this._netSampleIsDirectWire)) || Boolean(this._resetPingOnNextWireSample);
                        if (switchingChannel || !Array.isArray(this._rttWindow)) {
                            this._rttWindow = [];
                            this._resetPingOnNextWireSample = false;
                        }
                        this._netSampleIsDirectWire = directFlag;
                        this._rttWindow.push(cleanRtt);
                        while (this._rttWindow.length > 5) {
                            this._rttWindow.shift();
                        }
                        const sorted = this._rttWindow.slice().sort((a, b) => a - b);
                        const mid = Math.floor(sorted.length / 2);
                        const medianRtt = (sorted.length % 2 === 1)
                            ? sorted[mid]
                            : (sorted[mid - 1] + sorted[mid]) * 0.5;

                        let nextPing = Math.max(1, Math.round(medianRtt));
                        let nextJitter = 0;
                        if (!switchingChannel && ns.samples > 0 && ns.pingMs !== null) {
                            const prevRef = ns.lastRttMs || medianRtt;
                            const delta = Math.abs(medianRtt - prevRef);
                            const rawJitter = this._rttWindow.length <= 1 ? 0 : ((ns.jitterMs || 0) * 0.72 + delta * 0.28);
                            const rawPing = ns.pingMs * 0.65 + medianRtt * 0.35;
                            nextPing = Math.max(1, Math.round(rawPing));
                            nextJitter = Math.max(0, Math.round(rawJitter));
                        }
                        const resolvedHz = tickLabel || (this.wsConnected ? '30,0 Hz' : (ns.tickHz || '6,0 Hz'));
                        this.netStats = Object.assign({}, ns, {
                            pingMs: nextPing,
                            jitterMs: nextJitter,
                            tickHz: resolvedHz,
                            lossPct: Math.round(((ns.failCount || 0) / sent) * 100),
                            hasError: false,
                            samples: (ns.samples || 0) + 1,
                            lastRttMs: medianRtt,
                            sentCount: sent,
                        });
                    },

                    recordNetSample(rttMs, tickLabel) {
                        if (this.wsConnected || privateLobbyWs) return;
                        this.recordNetRttSample(rttMs, false, tickLabel);
                    },

                    recordNetFailure() {
                        const ns = this.netStats;
                        const sent = (ns.sentCount || 0) + 1;
                        const fails = (ns.failCount || 0) + 1;
                        this.netStats = Object.assign({}, ns, {
                            hasError: true,
                            lossPct: Math.round((fails / sent) * 100),
                            sentCount: sent,
                            failCount: fails,
                        });
                    },

                    probeSoloAtelierPing() {
                        if (!this.isOpen || this._pingProbeInFlight) return;
                        if (this.currentZone === 'heroes' && this.lobbyAuthenticated && !this.soloMode && !this.multiplayerDisabled && (this.wsConnected || this._syncTimer)) {
                            return;
                        }
                        this._pingProbeInFlight = true;
                        const t0 = this._nowPerfMs();

                        fetch('/lobby-ping.php', {
                            method: 'GET',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: { 'Accept': 'application/json' },
                        })
                            .then((res) => {
                                if (res.ok) {
                                    this.recordNetRttSample(this._nowPerfMs() - t0, true, 'Atelier · Bereit');
                                    return null;
                                }
                                const tFallback = this._nowPerfMs();
                                return fetch('/up', {
                                    method: 'HEAD',
                                    credentials: 'same-origin',
                                    cache: 'no-store',
                                }).then((rUp) => {
                                    if (rUp.ok) {
                                        this.recordNetRttSample(this._nowPerfMs() - tFallback, true, 'Atelier · Bereit');
                                    } else {
                                        this.recordNetFailure();
                                    }
                                });
                            })
                            .catch(() => {
                                this.recordNetFailure();
                            })
                            .finally(() => {
                                this._pingProbeInFlight = false;
                            });
                    },

                    startAtelierPingLoop() {
                        this.stopAtelierPingLoop();
                        fetch('/lobby-ping.php', {
                            method: 'GET',
                            credentials: 'same-origin',
                            cache: 'no-store',
                        })
                            .catch(() => {})
                            .finally(() => {
                                if (!this.isOpen) return;
                                if (this._pingWarmupTimer) {
                                    clearTimeout(this._pingWarmupTimer);
                                }
                                this._pingWarmupTimer = setTimeout(() => {
                                    this._pingWarmupTimer = null;
                                    this.probeSoloAtelierPing();
                                }, 150);
                            });
                        this._pingProbeTimer = setInterval(() => {
                            this.probeSoloAtelierPing();
                        }, 10000);
                    },

                    stopAtelierPingLoop() {
                        if (this._pingWarmupTimer) {
                            clearTimeout(this._pingWarmupTimer);
                            this._pingWarmupTimer = null;
                        }
                        if (this._pingProbeTimer) {
                            clearInterval(this._pingProbeTimer);
                            this._pingProbeTimer = null;
                        }
                        this._pingProbeInFlight = false;
                    },

                    openOuterGateModal(mmorpgState) {
                        const facade = getFacade();
                        if (facade && typeof facade.releasePointerLock === 'function') {
                            facade.releasePointerLock();
                        }
                        if (mmorpgState && typeof mmorpgState === 'object') {
                            if (mmorpgState.canEnter !== undefined) {
                                this.mmorpgCanEnter = Boolean(mmorpgState.canEnter);
                            }
                            if (typeof mmorpgState.enterUrl === 'string' && mmorpgState.enterUrl.trim() !== '') {
                                this.mmorpgEnterUrl = mmorpgState.enterUrl.trim();
                            }
                        } else if (facade && typeof facade.getMmorpgAccessState === 'function') {
                            const st = facade.getMmorpgAccessState();
                            if (st) {
                                this.mmorpgCanEnter = Boolean(st.canEnter);
                                this.mmorpgEnterUrl = st.enterUrl || '/funkenreich/enter';
                            }
                        }
                        this.sanctumGateOpen = false;
                        this.settingsOpen = false;
                        this.characterPickerOpen = false;
                        this.outerGateModalOpen = true;
                        if (privateAudio) privateAudio.playUiClick();
                    },

                    closeOuterGateModal() {
                        this.outerGateModalOpen = false;
                        if (privateAudio) privateAudio.playUiClick();
                    },

                    armorySlotChips() {
                        const eq = this.equippedArmory || {};
                        return [
                            { key: 'helm', short: 'Helm', active: Boolean(eq.head) },
                            { key: 'chest', short: 'Brust', active: Boolean(eq.chest) },
                            { key: 'gauntlets', short: 'Hände', active: Boolean(eq.hands) },
                            { key: 'boots', short: 'Stiefel', active: Boolean(eq.feet) },
                            { key: 'lance', short: 'Lanze', active: eq.weapon === 'lance' },
                            { key: 'spear', short: 'Speer', active: eq.weapon === 'spear' },
                        ];
                    },

                    toggleArmoryKey(itemKey) {
                        const facade = getFacade();
                        if (facade && typeof facade.toggleArmoryItem === 'function') {
                            facade.toggleArmoryItem(itemKey);
                        }
                    },

                    interactFocusedArmoryItem() {
                        const facade = getFacade();
                        if (facade && typeof facade.toggleArmoryItem === 'function') {
                            facade.toggleArmoryItem(this.armoryPrompt.focusedKey || null);
                        }
                    },

                    triggerLobbyWeaponSwing() {
                        const facade = getFacade();
                        if (facade && typeof facade.triggerWeaponSwing === 'function') {
                            facade.triggerWeaponSwing();
                        }
                    },

                    enterFunkenreichMmorpg() {
                        if (!this.mmorpgCanEnter) return;
                        const url = String(this.mmorpgEnterUrl || '/funkenreich/enter').trim() || '/funkenreich/enter';
                        if (typeof window !== 'undefined' && typeof window.__sfForceStopAllLobbyAudio === 'function') {
                            window.__sfForceStopAllLobbyAudio();
                        }
                        window.location.href = url;
                    },

                    dispatchLivewireAuthSync() {
                        if (typeof window !== 'undefined' && window.Livewire && typeof window.Livewire.dispatch === 'function') {
                            window.Livewire.dispatch('auth-status-changed');
                            window.Livewire.dispatch('cart-updated');
                            window.Livewire.dispatch('wishlist-updated');
                        }
                    },

                    updateDocumentCsrfToken(nextToken) {
                        if (!nextToken || typeof document === 'undefined') return;
                        const clean = String(nextToken).trim();
                        if (!clean) return;
                        let meta = document.querySelector('meta[name="csrf-token"]');
                        if (!meta) {
                            meta = document.createElement('meta');
                            meta.setAttribute('name', 'csrf-token');
                            document.head.appendChild(meta);
                        }
                        meta.setAttribute('content', clean);
                    },

                    canEnterSanctums() {
                        return Boolean(this.sanctumGateState && this.sanctumGateState.canEnter);
                    },

                    openSanctumGateModal(zone, incomingState) {
                        const facade = getFacade();
                        if (facade && typeof facade.releasePointerLock === 'function') {
                            facade.releasePointerLock();
                        }
                        this.outerGateModalOpen = false;
                        this.characterPickerOpen = false;
                        this.sanctumGateZone = 'heroes';
                        this.inlineAuthError = '';
                        const resolved = incomingState
                            || (facade && typeof facade.getSanctumAccessState === 'function' ? facade.getSanctumAccessState() : null);
                        if (resolved && typeof resolved === 'object') {
                            this.sanctumGateState = {
                                canEnter: Boolean(resolved.canEnter),
                                reason: resolved.reason || 'login_required',
                                checks: {
                                    login: Boolean(resolved.checks && resolved.checks.login),
                                    magic: Boolean(resolved.checks && resolved.checks.magic),
                                },
                                loginUrl: resolved.loginUrl || '/login',
                                dashboardUrl: resolved.dashboardUrl || '/dashboard',
                            };
                        }
                        if (!this.sanctumGateState.checks.login) {
                            this.sanctumExpandedStep = 'login';
                        } else if (!this.sanctumGateState.checks.magic) {
                            this.sanctumExpandedStep = 'magic';
                        } else {
                            this.sanctumExpandedStep = '';
                        }
                        this.sanctumGateOpen = true;
                    },

                    closeSanctumGateModal() {
                        this.sanctumGateOpen = false;
                        this.inlineAuthError = '';
                        if (privateAudio) privateAudio.playUiClick();
                    },

                    handleSanctumStepClick(step) {
                        this.inlineAuthError = '';
                        if (step === 'login') {
                            if (!this.sanctumGateState.checks.login) {
                                this.sanctumExpandedStep = (this.sanctumExpandedStep === 'login') ? '' : 'login';
                                if (privateAudio) privateAudio.playUiClick();
                            }
                            return;
                        }
                        if (step === 'magic') {
                            if (!this.sanctumGateState.checks.login) {
                                this.sanctumExpandedStep = 'login';
                            } else if (!this.sanctumGateState.checks.magic) {
                                this.sanctumExpandedStep = (this.sanctumExpandedStep === 'magic') ? '' : 'magic';
                                if (privateAudio) privateAudio.playUiClick();
                            }
                        }
                    },

                    triggerSanctumMagicCta() {
                        this.inlineAuthError = '';
                        if (!this.sanctumGateState.checks.login) {
                            this.sanctumExpandedStep = 'login';
                            return;
                        }
                        if (this.inlineMagicAcceptTerms) {
                            this.submitInlineActivateMagic();
                        } else {
                            this.sanctumExpandedStep = 'magic';
                        }
                    },

                    applyLiveAccountResponse(body) {
                        if (!body || typeof body !== 'object') return;
                        if (body.csrf_token) {
                            this.updateDocumentCsrfToken(body.csrf_token);
                        }
                        if (body.atelier_review && typeof body.atelier_review === 'object') {
                            this.applyAtelierReviewPayload(body.atelier_review);
                        } else if (body.status === 'authenticated' || body.success) {
                            this.atelierReview = Object.assign({}, this.atelierReview, { can_rate: true });
                        }
                        if (body.player_funki && typeof body.player_funki === 'object') {
                            this.funkiInfo = Object.assign({}, this.funkiInfo || {}, body.player_funki);
                            if (!body.player_funki.is_guest) {
                                this.lobbyAuthenticated = true;
                                this.multiplayer.is_authenticated = true;
                                if (body.player_funki.player_id) {
                                    this.multiplayer.player_id = body.player_funki.player_id;
                                }
                            }
                        }
                        const sa = body.sanctum_access || null;
                        if (sa && typeof sa === 'object') {
                            if (sa.is_authenticated !== undefined) {
                                this.lobbyAuthenticated = Boolean(sa.is_authenticated);
                                this.multiplayer.is_authenticated = Boolean(sa.is_authenticated);
                            }
                            this.sanctumGateState = {
                                canEnter: Boolean(sa.can_enter_sanctums),
                                reason: sa.block_reason || null,
                                checks: {
                                    login: Boolean(sa.is_authenticated),
                                    magic: Boolean(sa.gamification_active),
                                },
                                loginUrl: sa.login_url || this.sanctumGateState.loginUrl || '/login',
                                dashboardUrl: sa.dashboard_url || this.sanctumGateState.dashboardUrl || '/dashboard',
                            };
                        }
                        if (body.mmorpg_can_enter !== undefined) {
                            this.mmorpgCanEnter = Boolean(body.mmorpg_can_enter);
                        } else if (body.player && body.player.mmorpg_can_enter !== undefined) {
                            this.mmorpgCanEnter = Boolean(body.player.mmorpg_can_enter);
                        }
                        if (typeof body.mmorpg_enter_url === 'string' && body.mmorpg_enter_url.trim() !== '') {
                            this.mmorpgEnterUrl = body.mmorpg_enter_url.trim();
                        } else if (body.player && typeof body.player.mmorpg_enter_url === 'string' && body.player.mmorpg_enter_url.trim() !== '') {
                            this.mmorpgEnterUrl = body.player.mmorpg_enter_url.trim();
                        }

                        const facade = getFacade();
                        if (facade && typeof facade.applyLiveAccountUpdate === 'function') {
                            const nextAccess = facade.applyLiveAccountUpdate(body);
                            if (nextAccess && typeof nextAccess === 'object') {
                                this.sanctumGateState = {
                                    canEnter: Boolean(nextAccess.canEnter),
                                    reason: nextAccess.reason || null,
                                    checks: {
                                        login: Boolean(nextAccess.checks && nextAccess.checks.login),
                                        magic: Boolean(nextAccess.checks && nextAccess.checks.magic),
                                    },
                                    loginUrl: nextAccess.loginUrl || '/login',
                                    dashboardUrl: nextAccess.dashboardUrl || '/dashboard',
                                };
                            }
                            if (typeof facade.getPlayerHudState === 'function') {
                                const hud = facade.getPlayerHudState();
                                this.playerHud = Object.assign({}, this.playerHud, hud);
                            }
                        }
                    },

                    submitInlineLobbyLogin() {
                        if (this.inlineAuthBusy) return;
                        const email = String(this.inlineLoginEmail || '').trim();
                        const password = String(this.inlineLoginPassword || '');
                        if (!email || !password) {
                            this.inlineAuthError = 'Bitte E-Mail und Passwort eingeben.';
                            return;
                        }
                        this.inlineAuthBusy = true;
                        this.inlineAuthError = '';

                        const reqBody = {
                            email,
                            password,
                        };
                        if (this.inlineLoginTwoFactorRequired && String(this.inlineLoginTwoFactorCode || '').trim() !== '') {
                            reqBody.two_factor_code = String(this.inlineLoginTwoFactorCode || '').trim();
                        }

                        fetch('/funkenwerk-welt/inline-login', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify(reqBody),
                        })
                            .then((res) => res.json().catch(() => null).then((b) => ({ status: res.status, body: b })))
                            .then(({ body }) => {
                                this.inlineAuthBusy = false;
                                if (!body) {
                                    this.inlineAuthError = 'Anmeldung fehlgeschlagen.';
                                    return;
                                }
                                if (body.csrf_token) {
                                    this.updateDocumentCsrfToken(body.csrf_token);
                                }
                                if (body.status === 'two_factor_required') {
                                    this.inlineLoginTwoFactorRequired = true;
                                    this.inlineAuthError = body.message || 'Bitte 2FA-Code eingeben.';
                                    return;
                                }
                                if (body.status === 'password_change_required' && body.redirect_url) {
                                    if (typeof window !== 'undefined' && typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                        window.__sfForceStopAllLobbyAudio();
                                    }
                                    window.location.href = String(body.redirect_url);
                                    return;
                                }
                                if (body.status === 'authenticated' || body.success) {
                                    this.inlineLoginPassword = '';
                                    this.inlineLoginTwoFactorCode = '';
                                    this.inlineLoginTwoFactorRequired = false;
                                    this._loggedInDuringLobbySession = true;
                                    this.dispatchLivewireAuthSync();
                                    this.applyLiveAccountResponse(body);

                                    if (this._loginOpenedFromStarStation || this.sanctumGateState.canEnter) {
                                        this._loginOpenedFromStarStation = false;
                                        this.sanctumExpandedStep = '';
                                        this.sanctumGateOpen = false;
                                    } else if (!this.sanctumGateState.checks.magic) {
                                        this.sanctumExpandedStep = 'magic';
                                    }
                                    return;
                                }
                                this.inlineAuthError = body.message || 'Zugangsdaten ungültig.';
                            })
                            .catch(() => {
                                this.inlineAuthBusy = false;
                                this.inlineAuthError = 'Verbindung fehlgeschlagen.';
                            });
                    },

                    applyAtelierReviewPayload(rev) {
                        if (!rev || typeof rev !== 'object') return;
                        const cleanRating = Math.max(0, Math.min(5, parseInt(rev.my_rating || 0, 10) || 0));
                        this.atelierReview = {
                            area: String(rev.area || 'funkenreich'),
                            label: String(rev.label || '3D-Atelier'),
                            can_rate: Boolean(rev.can_rate),
                            has_rated: Boolean(rev.has_rated || cleanRating > 0),
                            my_rating: cleanRating,
                            my_comment: typeof rev.my_comment === 'string' ? rev.my_comment : '',
                            submit_url: String(rev.submit_url || '/customer/experience-reviews'),
                        };
                        this.atelierFeedbackDraft = this.atelierReview.my_comment || '';
                    },

                    openLoginForAtelierRating() {
                        this._loginOpenedFromStarStation = true;
                        this.openSanctumGateModal('heroes');
                    },

                    previewAtelierStars(starVal) {
                        const clean = Math.max(0, Math.min(5, parseInt(starVal, 10) || 0));
                        this.atelierStarHover = clean;
                        const facade = getFacade();
                        if (facade && typeof facade.previewAtelierStarHover === 'function') {
                            facade.previewAtelierStarHover(clean);
                        }
                    },

                    startAtelierRatingEdit() {
                        this.atelierRatingEditing = true;
                        if (this.atelierReview.my_rating >= 1 && this.atelierReview.my_rating <= 3) {
                            this.atelierFeedbackOpen = true;
                        }
                    },

                    submitAtelierStarRating(stars) {
                        if (this.atelierRatingBusy) return;
                        const cleanStars = Math.max(1, Math.min(5, parseInt(stars, 10) || 5));
                        this.atelierRatingBusy = true;
                        this.atelierStarHover = 0;
                        this.atelierReview = Object.assign({}, this.atelierReview, {
                            my_rating: cleanStars,
                            has_rated: true,
                        });
                        this.atelierRatingEditing = false;
                        this.atelierFeedbackOpen = cleanStars <= 3;

                        const facade = getFacade();
                        if (facade && typeof facade.setAtelierReviewState === 'function') {
                            facade.setAtelierReviewState(this.atelierReview, { celebrate: true, stars: cleanStars });
                        }

                        fetch(this.atelierReview.submit_url || '/customer/experience-reviews', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({
                                area: this.atelierReview.area || 'funkenreich',
                                rating: cleanStars,
                                keep_existing_comment: true,
                            }),
                        })
                            .then((res) => res.json().catch(() => null))
                            .then((body) => {
                                this.atelierRatingBusy = false;
                                if (body && body.ok && body.review) {
                                    const savedRating = parseInt(body.review.rating || cleanStars, 10) || cleanStars;
                                    const savedComment = typeof body.review.comment === 'string' ? body.review.comment : this.atelierReview.my_comment;
                                    this.atelierReview = Object.assign({}, this.atelierReview, {
                                        my_rating: savedRating,
                                        has_rated: true,
                                        my_comment: savedComment,
                                    });
                                    this.atelierFeedbackDraft = savedComment || '';
                                }
                            })
                            .catch(() => {
                                this.atelierRatingBusy = false;
                            });
                    },

                    submitAtelierStarComment() {
                        if (this.atelierRatingBusy) return;
                        const rating = Math.max(1, Math.min(5, parseInt(this.atelierReview.my_rating || 3, 10) || 3));
                        const commentText = String(this.atelierFeedbackDraft || '').trim();
                        this.atelierRatingBusy = true;

                        fetch(this.atelierReview.submit_url || '/customer/experience-reviews', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({
                                area: this.atelierReview.area || 'funkenreich',
                                rating,
                                comment: commentText,
                            }),
                        })
                            .then((res) => res.json().catch(() => null))
                            .then((body) => {
                                this.atelierRatingBusy = false;
                                this.atelierFeedbackOpen = false;
                                this.atelierRatingEditing = false;
                                const savedComment = (body && body.ok && body.review && typeof body.review.comment === 'string')
                                    ? body.review.comment
                                    : commentText;
                                this.atelierReview = Object.assign({}, this.atelierReview, {
                                    my_comment: savedComment,
                                });
                            })
                            .catch(() => {
                                this.atelierRatingBusy = false;
                                this.atelierFeedbackOpen = false;
                            });
                    },

                    sparkMountRoot() {
                        const fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
                        if (fsEl) {
                            return this.$refs.showroomModal || this.$refs.lobbyModalRoot || fsEl;
                        }
                        return document.body;
                    },

                    onInlineMagicTermsChange() {
                        const root = this.sparkMountRoot();
                        if (this.inlineMagicAcceptTerms) {
                            this.inlineMagicCtaAura = false;
                            if (privateAudio) privateAudio.playUiSwitch();
                            if (window.sfSparkFlow && typeof window.sfSparkFlow.fly === 'function') {
                                window.sfSparkFlow.fly(this.$refs.inlineMagicTermsBox, this.$refs.inlineMagicActivateBtn, {
                                    mode: 'unlock',
                                    runKey: 'lobby-inline-magic-flow',
                                    mountRoot: root,
                                    onArrival: () => {
                                        if (this.inlineMagicAcceptTerms) {
                                            this.inlineMagicCtaAura = true;
                                        }
                                    },
                                });
                            } else {
                                this.inlineMagicCtaAura = true;
                            }
                        } else {
                            this.inlineMagicCtaAura = false;
                            if (privateAudio) privateAudio.playUiClick();
                            if (window.sfSparkFlow && typeof window.sfSparkFlow.fly === 'function') {
                                window.sfSparkFlow.fly(this.$refs.inlineMagicActivateBtn, this.$refs.inlineMagicTermsBox, {
                                    mode: 'relock',
                                    runKey: 'lobby-inline-magic-flow',
                                    mountRoot: root,
                                });
                            }
                        }
                    },

                    nudgeInlineMagicTermsHint(btnEl) {
                        if (this.inlineMagicAcceptTerms || this.inlineAuthBusy) return;
                        if (window.sfSparkFlow && typeof window.sfSparkFlow.fly === 'function') {
                            window.sfSparkFlow.fly(btnEl || this.$refs.inlineMagicActivateBtn, this.$refs.inlineMagicTermsBox, {
                                mode: 'hint',
                                cooldownKey: 'lobby-inline-magic-hint',
                                mountRoot: this.sparkMountRoot(),
                            });
                        }
                    },

                    submitInlineActivateMagic(btnEl) {
                        if (this.inlineAuthBusy) return;
                        if (!this.inlineMagicAcceptTerms) {
                            this.nudgeInlineMagicTermsHint(btnEl);
                            return;
                        }
                        this.inlineAuthBusy = true;
                        this.inlineAuthError = '';

                        fetch('/funkenwerk-welt/activate-magic', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({
                                accept_terms: true,
                                ranking_opt_in: Boolean(this.inlineMagicRankingOptIn),
                            }),
                        })
                            .then((res) => res.json().catch(() => null).then((b) => ({ status: res.status, body: b })))
                            .then(({ body }) => {
                                this.inlineAuthBusy = false;
                                if (!body) {
                                    this.inlineAuthError = 'Aktivierung fehlgeschlagen.';
                                    return;
                                }
                                if (body.csrf_token) {
                                    this.updateDocumentCsrfToken(body.csrf_token);
                                }
                                if (body.status === 'magic_activated' || body.success) {
                                    this.sanctumExpandedStep = '';
                                    this.sanctumGateOpen = false;
                                    this.applyLiveAccountResponse(Object.assign({}, body, { play_awaken_fx: true }));
                                    this.dispatchLivewireAuthSync();
                                    return;
                                }
                                this.inlineAuthError = body.message || 'Aktivierung nicht möglich.';
                            })
                            .catch(() => {
                                this.inlineAuthBusy = false;
                                this.inlineAuthError = 'Verbindung fehlgeschlagen.';
                            });
                    },

                    toggleQuickMute() {
                        if (privateAudio && typeof privateAudio.toggleQuickMute === 'function') {
                            this.audioState = privateAudio.toggleQuickMute();
                        }
                    },

                    initLobbyListeners() {
                        if (!this._captureKeydownBound) {
                            this._captureKeydownBound = (e) => {
                                if (!this.isOpen) return;
                                const key = e.key || '';
                                const code = e.code || '';

                                if (this.listeningKeyAction !== '') {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    if (key === 'Escape' || code === 'Escape' || e.keyCode === 27) {
                                        this.listeningKeyAction = '';
                                        return;
                                    }
                                    if (code) {
                                        this.assignKeybinding(this.listeningKeyAction, code);
                                    }
                                    return;
                                }

                                if (key === 'Escape' || code === 'Escape' || e.keyCode === 27) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    this.handleEscape();
                                    return;
                                }

                                const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
                                if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

                                if (!this.settingsOpen && (code === 'KeyF' || key === 'f' || key === 'F')) {
                                    const usedAsBind = Object.values(this.keybindings || {}).indexOf('KeyF') !== -1;
                                    if (!usedAsBind) {
                                        e.preventDefault();
                                        this.toggleFullscreen();
                                    }
                                }
                            };
                            window.addEventListener('keydown', this._captureKeydownBound, true);
                        }

                        if (!this._fullscreenChangeBound) {
                            this._fullscreenChangeBound = () => {
                                const fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
                                this.isFullscreen = Boolean(fsEl);
                                const facade = getFacade();
                                if (facade) {
                                    facade.resize();
                                    requestAnimationFrame(() => {
                                        const f2 = getFacade();
                                        if (f2) f2.resize();
                                    });
                                    if (this._fsResizeTimer) clearTimeout(this._fsResizeTimer);
                                    this._fsResizeTimer = setTimeout(() => {
                                        const f3 = getFacade();
                                        if (f3) f3.resize();
                                    }, 120);
                                }
                            };
                            document.addEventListener('fullscreenchange', this._fullscreenChangeBound);
                            document.addEventListener('webkitfullscreenchange', this._fullscreenChangeBound);
                        }
                    },

                    toggleFullscreen() {
                        const activeFs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
                        if (activeFs) {
                            if (typeof document.exitFullscreen === 'function') {
                                Promise.resolve(document.exitFullscreen()).catch(() => {});
                            } else if (typeof document.webkitExitFullscreen === 'function') {
                                document.webkitExitFullscreen();
                            }
                            return;
                        }

                        const modalRoot = this.$refs.lobbyModalRoot || document.getElementById('sf-louvre-showroom-modal');
                        if (!modalRoot) return;

                        if (typeof modalRoot.requestFullscreen === 'function') {
                            Promise.resolve(modalRoot.requestFullscreen({ navigationUI: 'hide' }))
                                .then(() => { this.isFullscreen = true; })
                                .catch(() => {});
                        } else if (typeof modalRoot.webkitRequestFullscreen === 'function') {
                            modalRoot.webkitRequestFullscreen();
                            this.isFullscreen = true;
                        }
                    },

                    csrfToken() {
                        const el = document.querySelector('meta[name="csrf-token"]');
                        return el ? (el.getAttribute('content') || '') : '';
                    },

                    isCustomerAuthenticated() {
                        return Boolean(
                            this.lobbyAuthenticated
                            || this.multiplayer.is_authenticated
                            || (this.funkiInfo && !this.funkiInfo.is_guest)
                            || (this.heroesPayload && this.heroesPayload.customer_logged_in)
                        );
                    },

                    availableInstances() {
                        if (Array.isArray(this.lobbyInstances) && this.lobbyInstances.length > 0) {
                            return this.lobbyInstances;
                        }
                        return [{
                            id: this.lobbyInstanceId,
                            count: 1 + (Array.isArray(this.peers) ? this.peers.length : 0),
                            capacity: this.lobbyCapacity,
                            is_full: false,
                            is_current: true
                        }];
                    },

                    currentInstanceCount() {
                        const list = this.availableInstances();
                        for (let i = 0; i < list.length; i++) {
                            if (Number(list[i].id) === Number(this.lobbyInstanceId)) {
                                return Math.max(1, Number(list[i].count) || (1 + this.peers.length));
                            }
                        }
                        return 1 + (Array.isArray(this.peers) ? this.peers.length : 0);
                    },

                    currentZoneLabel() {
                        if (this.currentZone === 'heroes') return '🏛️ Halle der Helden';
                        return '🛍️ Kollektion';
                    },

                    stagesPreviewLabel() {
                        if (this.stagesPreviewMode === '1-5') return '✨ Stufen 1-5';
                        if (this.stagesPreviewMode === '6-10') return '✨ Stufen 6-10';
                        return '✨ Stufen';
                    },

                    switchZone(targetZone) {
                        const z = (targetZone === 'heroes' || targetZone === 'hall') ? 'heroes' : 'gallery';
                        if (privateAudio) privateAudio.playUiClick();
                        const facade = getFacade();
                        if (facade) {
                            facade.setZone(z, false);
                            const snap = facade.getHudSnapshot();
                            this.currentZone = (snap && snap.zone) ? snap.zone : z;
                        } else {
                            this.currentZone = z;
                        }
                        this.handleZoneSyncTransition(this.currentZone);
                    },

                    cycleStagesPreview() {
                        const facade = getFacade();
                        if (!facade) return;
                        this.stagesPreviewMode = facade.cyclePreviewHeroLevel();
                    },

                    focusHeroSlot(slotNum) {
                        const facade = getFacade();
                        if (facade) {
                            facade.focusHeroSlot(slotNum);
                        }
                    },

                    focusMyHeroPlace() {
                        const facade = getFacade();
                        if (facade) {
                            facade.focusMyHeroPlace();
                        }
                    },

                    startLobbyMusicFromGesture(targetZone = 'gallery') {
                        const cleanZone = (targetZone === 'heroes' || targetZone === 'hall') ? 'heroes' : 'gallery';
                        if (privateAudio && typeof privateAudio.startLobbyMusicFromGesture === 'function') {
                            this.audioState = privateAudio.startLobbyMusicFromGesture(cleanZone);
                            return;
                        }
                        if (typeof window !== 'undefined' && typeof window.getSeelenfunkeLobbyAudio === 'function') {
                            privateAudio = window.getSeelenfunkeLobbyAudio({ isAuthenticated: this.isCustomerAuthenticated() });
                            if (privateAudio && typeof privateAudio.startLobbyMusicFromGesture === 'function') {
                                this.audioState = privateAudio.startLobbyMusicFromGesture(cleanZone);
                                return;
                            }
                        }
                        try {
                            const rawExplicit = localStorage.getItem('sf_lobby_bgm_explicit_muted');
                            const rawVol = localStorage.getItem('sf_funki_audio_volume');
                            const explicitMuted = (rawExplicit === '1' || rawExplicit === 'true') || rawVol === '0';
                            if (!explicitMuted && typeof Audio !== 'undefined') {
                                localStorage.setItem('sf_funki_audio_muted', '0');
                                const preEl = window.__sfPrewarmLobbyBgmEl || new Audio('/shop/gamification/audio/bgm_funki_game.mp3');
                                preEl.loop = true;
                                preEl.preload = 'auto';
                                preEl.volume = 0.025;
                                window.__sfPrewarmLobbyBgmEl = preEl;
                                if (!(window.__sfLobbyAudioRegistry instanceof Set)) {
                                    window.__sfLobbyAudioRegistry = new Set();
                                }
                                window.__sfLobbyAudioRegistry.add(preEl);
                                window.__sfLobbyAudioGraceUntil = Date.now() + 1200;
                                setTimeout(() => {
                                    if (!this.isOpen && window.__sfPrewarmLobbyBgmEl === preEl) {
                                        try {
                                            preEl.pause();
                                            preEl.volume = 0;
                                            preEl.currentTime = 0;
                                        } catch (e2) {}
                                        window.__sfPrewarmLobbyBgmEl = null;
                                        if (typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                            window.__sfForceStopAllLobbyAudio();
                                        }
                                    }
                                }, 1500);
                                const p = preEl.play();
                                if (p && typeof p.catch === 'function') p.catch(() => {});
                            }
                        } catch (e) {}
                    },

                    openShowroom(initialZone = 'gallery') {
                        const requestedZone = (initialZone === 'heroes' || initialZone === 'hall') ? 'heroes' : 'gallery';
                        // User-Gesture-Token zu 100 % synchron im Klick-Event sichern (VOR fetch / import)!
                        this.startLobbyMusicFromGesture(requestedZone);
                        if (this.isOpen) {
                            this.switchZone(requestedZone);
                            if (requestedZone === 'gallery') {
                                this.characterPickerOpen = true;
                            }
                            return;
                        }

                        this.initLobbyListeners();
                        this.isOpen = true;
                        this.isFullscreen = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
                        this.isLoading = true;
                        this.loadError = '';
                        this.currentZone = 'gallery';
                        this.stagesPreviewMode = 'live';
                        this.focusedProduct = null;
                        this.focusedHero = null;
                        this.productTransitionActive = false;
                        if (this._productTransitionTimer) {
                            clearTimeout(this._productTransitionTimer);
                            this._productTransitionTimer = null;
                        }
                        if (this._productFadeTimer) {
                            clearTimeout(this._productFadeTimer);
                            this._productFadeTimer = null;
                        }
                        this.instanceMenuOpen = false;
                        this.pingTooltipOpen = false;
                        this.settingsOpen = false;
                        this.characterPickerOpen = (requestedZone === 'gallery');
                        this.sanctumGateOpen = false;
                        this.outerGateModalOpen = false;
                        this.listeningKeyAction = '';
                        this.peers = [];
                        this.multiplayerDisabled = false;
                        this._wsDisabledByServer = false;
                        this._stopChatMuteCountdown();
                        this.chatStrikeInfo = {
                            strikes: 0,
                            isMuted: false,
                            remainingSec: 0,
                            permanent: false,
                            bannerText: '',
                            level: ''
                        };
                        this.chatMessages = [];
                        this.unreadChatCount = 0;
                        this.chatFeedback = '';
                        this._activeSyncZone = null;
                        this._seenMessageIds = new Set();
                        this.loadFriendsList();
                        this.startAtelierPingLoop();
                        if (!this._abilityPollTimer) {
                            this._abilityPollTimer = setInterval(() => this.syncAbilityState(), 120);
                        }
                        this.prevHtmlOverflow = document.documentElement.style.overflow || '';
                        this.prevOverflow = document.body.style.overflow || '';
                        document.documentElement.style.overflow = 'hidden';
                        document.body.style.overflow = 'hidden';

                        if (!this._pageHideBound) {
                            this._pageHideBound = () => {
                                if (this.isOpen && this._activeSyncZone === 'heroes') {
                                    this.closeLobbyWebSocket(true);
                                    this.sendLobbyLeaveBeacon();
                                }
                                if (typeof window !== 'undefined' && typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                    window.__sfForceStopAllLobbyAudio();
                                }
                            };
                            window.addEventListener('pagehide', this._pageHideBound);
                        }

                        if (!this._livewireNavigatingBound) {
                            this._livewireNavigatingBound = () => {
                                if (this.isOpen) {
                                    this.closeShowroom();
                                } else if (typeof window !== 'undefined' && typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                    window.__sfForceStopAllLobbyAudio();
                                }
                            };
                            document.addEventListener('livewire:navigating', this._livewireNavigatingBound);
                        }

                        const dataPromise = fetch('/shop/showroom-data', {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json' }
                        }).then((res) => {
                            if (!res.ok) {
                                throw new Error('Showroom ist aktuell nicht erreichbar.');
                            }
                            return res.json();
                        });

                        const heroesPromise = fetch('/funkenwerk-welt/heroes-data', {
                            method: 'GET',
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: { 'Accept': 'application/json' }
                        })
                            .then((res) => (res.ok ? res.json() : null))
                            .catch(() => null);

                        const modulePromise = import('/js/seelenfunke-louvre-showroom-3d.js?v=20261120v36');

                        Promise.all([dataPromise, heroesPromise, modulePromise])
                            .then(([payload, heroesJson, mod]) => {
                                if (!this.isOpen) {
                                    if (mod && typeof mod.forceStopAllLobbyAudio === 'function') {
                                        mod.forceStopAllLobbyAudio();
                                    } else if (typeof window !== 'undefined' && typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                        window.__sfForceStopAllLobbyAudio();
                                    }
                                    return;
                                }
                                privateMod = mod;
                                this.products = Array.isArray(payload.products) ? payload.products : [];
                                this.funkiInfo = payload.player_funki || { is_guest: true, level: 2, name: '', weapon: null, player_id: null };
                                if (heroesJson && heroesJson.success) {
                                    this.heroesPayload = heroesJson;
                                }
                                this.multiplayer = Object.assign({
                                    enabled: false,
                                    is_authenticated: false,
                                    solo_only: true,
                                    room_id: null,
                                    sync_interval_ms: 165,
                                    player_id: null,
                                    peer_id: null
                                }, payload.multiplayer || {});

                                const rawPlayer = (payload.player && typeof payload.player === 'object') ? payload.player : null;
                                if (payload.environment && typeof payload.environment === 'object') {
                                    this.environmentInfo = Object.assign({}, this.environmentInfo, payload.environment);
                                }
                                if (payload.atelier_review && typeof payload.atelier_review === 'object') {
                                    this.applyAtelierReviewPayload(payload.atelier_review);
                                }
                                if (payload.mmorpg_can_enter !== undefined) {
                                    this.mmorpgCanEnter = Boolean(payload.mmorpg_can_enter);
                                } else if (rawPlayer && rawPlayer.mmorpg_can_enter !== undefined) {
                                    this.mmorpgCanEnter = Boolean(rawPlayer.mmorpg_can_enter);
                                }
                                if (typeof payload.mmorpg_enter_url === 'string' && payload.mmorpg_enter_url.trim() !== '') {
                                    this.mmorpgEnterUrl = payload.mmorpg_enter_url.trim();
                                } else if (rawPlayer && typeof rawPlayer.mmorpg_enter_url === 'string' && rawPlayer.mmorpg_enter_url.trim() !== '') {
                                    this.mmorpgEnterUrl = rawPlayer.mmorpg_enter_url.trim();
                                }

                                const isAuth = Boolean(
                                    (rawPlayer && rawPlayer.is_authenticated)
                                    || this.isCustomerAuthenticated()
                                );
                                this.lobbyAuthenticated = isAuth;
                                this.soloMode = false;
                                this.multiplayerDisabled = Boolean(
                                    isAuth
                                    && payload.multiplayer
                                    && payload.multiplayer.enabled === false
                                    && payload.multiplayer.solo_only === true
                                );
                                this._wsDisabledByServer = this.multiplayerDisabled;
                                this.activeIndex = 0;

                                const magicActive = Boolean(rawPlayer && rawPlayer.gamification_active);
                                const fallbackCanEnter = Boolean(isAuth && magicActive);
                                const resolvedCanEnter = (rawPlayer && rawPlayer.can_enter_sanctums !== undefined)
                                    ? Boolean(rawPlayer.can_enter_sanctums)
                                    : fallbackCanEnter;
                                this.sanctumGateState = {
                                    canEnter: resolvedCanEnter,
                                    reason: resolvedCanEnter
                                        ? null
                                        : ((rawPlayer && rawPlayer.sanctum_block_reason && rawPlayer.sanctum_block_reason !== 'order_required')
                                            ? rawPlayer.sanctum_block_reason
                                            : (!isAuth ? 'login_required' : 'magic_activation_required')),
                                    checks: {
                                        login: isAuth,
                                        magic: magicActive,
                                    },
                                    loginUrl: (rawPlayer && rawPlayer.login_url) || '/login',
                                    dashboardUrl: (rawPlayer && rawPlayer.dashboard_url) || '/dashboard',
                                };

                                if (typeof mod.loadLobbyUserSettings === 'function') {
                                    this.userSettings = mod.loadLobbyUserSettings();
                                }
                                if (typeof mod.loadLobbyKeybindings === 'function') {
                                    this.keybindings = mod.loadLobbyKeybindings();
                                }
                                if (typeof mod.getLobbyAudioInstance === 'function') {
                                    privateAudio = mod.getLobbyAudioInstance({ isAuthenticated: isAuth });
                                    privateAudio.setSfxVolume(this.userSettings.volume);
                                    privateAudio.setSfxMuted(this.userSettings.volume <= 0);
                                    this.audioState = privateAudio.startLobbyMusicFromGesture(requestedZone);
                                    if (privateAudioUnsub) privateAudioUnsub();
                                    privateAudioUnsub = privateAudio.onChange((snap) => {
                                        this.audioState = snap;
                                    });
                                }

                                this.isLoading = false;

                                if (this.products.length === 0 && !isAuth) {
                                    this.loadError = 'Aktuell sind keine Ausstellungsstücke freigegeben.';
                                    return;
                                }

                                this.$nextTick(() => {
                                    if (!this.isOpen || !this.$refs.louvreCanvas) return;
                                    const prevFacade = getFacade();
                                    if (prevFacade) {
                                        prevFacade.destroy();
                                        setFacade(null);
                                    }
                                    const createdFacade = mod.createLouvreShowroom({
                                        canvas: this.$refs.louvreCanvas,
                                        floatingCardEl: this.$refs.floatingCard || null,
                                        floatingHeroCardEl: this.$refs.floatingHeroCard || null,
                                        floatingPortalCardEl: this.$refs.floatingPortalCard || null,
                                        floatingOuterGateEl: this.$refs.floatingOuterGateCard || null,
                                        floatingArmoryEl: this.$refs.floatingArmoryCard || null,
                                        floatingStarStationEl: this.$refs.floatingAtelierStarCard || null,
                                        products: this.products,
                                        environment: payload.environment || null,
                                        player: rawPlayer,
                                        heroesPayload: this.heroesPayload,
                                        playerFunki: this.funkiInfo,
                                        atelierReview: this.atelierReview,
                                        mmorpgCanEnter: this.mmorpgCanEnter,
                                        mmorpgEnterUrl: this.mmorpgEnterUrl,
                                        audio: privateAudio || undefined,
                                        isAuthenticatedCustomer: isAuth,
                                        initialZone: requestedZone,
                                        soloMode: this.soloMode,
                                        characterGender: (this.userSettings && this.userSettings.characterGender === 'female') ? 'female' : 'male',
                                        onFocusProductChange: (prod, idx) => {
                                            this.focusedProduct = prod || null;
                                            const f = getFacade();
                                            if (f) {
                                                f.setFocusProtected(Boolean(prod));
                                            }
                                            if (typeof idx === 'number' && idx >= 0) {
                                                this.activeIndex = idx;
                                            }
                                        },
                                        onFocusHeroChange: (heroInfo) => {
                                            this.focusedHero = heroInfo || null;
                                        },
                                        onActiveExhibitChange: (idx) => {
                                            this.activeIndex = idx;
                                        },
                                        onZoneChange: (zone) => {
                                            this.currentZone = zone;
                                            this.handleZoneSyncTransition(zone);
                                        },
                                        onLocalUrgentSyncNeeded: () => {
                                            this.requestImmediateMultiplayerSync();
                                        },
                                        onAuthGatePrompt: (zone, accessState) => {
                                            this.openSanctumGateModal(zone, accessState);
                                        },
                                        onOuterGateModalOpen: (mmorpgState) => {
                                            this.openOuterGateModal(mmorpgState);
                                        },
                                        onArmoryStateChange: (st) => {
                                            if (!st || typeof st !== 'object') return;
                                            if (st.equipped) {
                                                this.equippedArmory = Object.assign({}, this.equippedArmory, st.equipped);
                                            }
                                            this.armoryPrompt = {
                                                visible: Boolean(st.visible),
                                                focusedKey: st.focusedKey || 'chest',
                                                focusedLabel: st.focusedLabel || 'Kristall-Brustpanzer',
                                                isEquipped: Boolean(st.isEquipped),
                                            };
                                        },
                                        onAtelierStarStationFocusChange: (focused) => {
                                            this.atelierStarStationFocused = Boolean(focused);
                                            if (!focused) {
                                                this.atelierStarHover = 0;
                                            }
                                        },
                                        onPlayerHudChange: (hud) => {
                                            if (hud && typeof hud === 'object') {
                                                this.playerHud = Object.assign({}, this.playerHud, hud);
                                                if (hud.mmorpgCanEnter !== undefined) {
                                                    this.mmorpgCanEnter = Boolean(hud.mmorpgCanEnter);
                                                }
                                                if (typeof hud.mmorpgEnterUrl === 'string' && hud.mmorpgEnterUrl.trim() !== '') {
                                                    this.mmorpgEnterUrl = hud.mmorpgEnterUrl.trim();
                                                }
                                                if (hud.environment) {
                                                    this.environmentInfo = Object.assign({}, this.environmentInfo, hud.environment);
                                                }
                                            }
                                        },
                                        onSelectProduct: (prod) => {
                                            this.launchProductTransition(prod);
                                        }
                                    });
                                    setFacade(createdFacade);
                                    if (typeof createdFacade.getPlayerHudState === 'function') {
                                        this.playerHud = Object.assign({}, this.playerHud, createdFacade.getPlayerHudState());
                                    }
                                    if (typeof createdFacade.getEnvironment === 'function') {
                                        this.environmentInfo = Object.assign({}, this.environmentInfo, createdFacade.getEnvironment());
                                    }
                                    if (typeof createdFacade.getSanctumAccessState === 'function') {
                                        const stAccess = createdFacade.getSanctumAccessState();
                                        this.sanctumGateState = {
                                            canEnter: Boolean(stAccess.canEnter),
                                            reason: stAccess.reason || null,
                                            checks: {
                                                login: Boolean(stAccess.checks && stAccess.checks.login),
                                                magic: Boolean(stAccess.checks && stAccess.checks.magic),
                                            },
                                            loginUrl: stAccess.loginUrl || '/login',
                                            dashboardUrl: stAccess.dashboardUrl || '/dashboard',
                                        };
                                    }
                                    this.currentZone = createdFacade.getCurrentZone();

                                    if (this.mutedPlayerIds.length > 0) {
                                        createdFacade.setMutedPlayers(this.mutedPlayerIds);
                                    }

                                    // Ruhiges Solo-Shopping in 'gallery': Multiplayer-Instanz startet erst beim Betreten von 'heroes'!
                                    if (isAuth && this.canEnterSanctums() && this.currentZone === 'heroes') {
                                        this.handleZoneSyncTransition('heroes');
                                    }
                                    this.renderMinimap();
                                });
                            })
                            .catch(() => {
                                this.isLoading = false;
                                this.loadError = 'Der 3D-Showroom konnte auf diesem Gerät nicht geladen werden.';
                                if (typeof window !== 'undefined') {
                                    if (window.__sfPrewarmLobbyBgmEl) {
                                        try {
                                            window.__sfPrewarmLobbyBgmEl.pause();
                                            window.__sfPrewarmLobbyBgmEl.volume = 0;
                                            window.__sfPrewarmLobbyBgmEl.currentTime = 0;
                                        } catch (e2) {}
                                        window.__sfPrewarmLobbyBgmEl = null;
                                    }
                                    if (typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                        window.__sfForceStopAllLobbyAudio();
                                    }
                                }
                            });
                    },

                    _buildWsStatePayload(st) {
                        return {
                            type: 'state',
                            x: st.x,
                            y: st.y,
                            z: st.z,
                            vx: st.vx || 0,
                            vy: st.vy || 0,
                            vz: st.vz || 0,
                            rot_y: st.rot_y,
                            speed: st.speed || 0,
                            jumping: Boolean(st.jumping),
                            sprint: Boolean(st.sprint),
                            wall_cling: Boolean(st.wall_cling),
                            wall_nx: st.wall_nx || 0,
                            wall_nz: st.wall_nz || 0,
                            somersault_seq: st.somersault_seq || 0,
                            somersault_dir: st.somersault_dir || 0,
                            somersault_prog: st.somersault_prog || 0,
                            emote: st.emote || null,
                            avatar_gender: st.avatar_gender === 'female'
                                ? 'female'
                                : ((this.userSettings && this.userSettings.characterGender === 'female') ? 'female' : 'male'),
                        };
                    },

                    _hasWsStateChanged(st, prev, nowMs) {
                        if (!prev) return true;
                        if ((nowMs - (privateLastWsSentAtMs || 0)) >= 400) return true;
                        if (Math.abs((st.x || 0) - (prev.x || 0)) > 0.004) return true;
                        if (Math.abs((st.y || 0) - (prev.y || 0)) > 0.004) return true;
                        if (Math.abs((st.z || 0) - (prev.z || 0)) > 0.004) return true;
                        let dYaw = (st.rot_y || 0) - (prev.rot_y || 0);
                        while (dYaw > Math.PI) dYaw -= Math.PI * 2;
                        while (dYaw < -Math.PI) dYaw += Math.PI * 2;
                        if (Math.abs(dYaw) > 0.012) return true;
                        if (((st.speed || 0) <= 0.02) !== ((prev.speed || 0) <= 0.02)) return true;
                        if (Math.abs((st.speed || 0) - (prev.speed || 0)) > 0.03) return true;
                        if (Boolean(st.jumping) !== Boolean(prev.jumping)) return true;
                        if (Boolean(st.sprint) !== Boolean(prev.sprint)) return true;
                        if (Boolean(st.wall_cling) !== Boolean(prev.wall_cling)) return true;
                        if ((st.somersault_seq || 0) !== (prev.somersault_seq || 0)) return true;
                        if ((st.somersault_dir || 0) !== (prev.somersault_dir || 0)) return true;
                        if ((st.emote || null) !== (prev.emote || null)) return true;
                        if ((st.avatar_gender || 'male') !== (prev.avatar_gender || 'male')) return true;
                        return false;
                    },

                    _sendWsStateNow(force = false) {
                        if (!this.isOpen || !this.wsConnected || !privateLobbyWs || privateLobbyWs.readyState !== 1) return false;
                        if (this.soloMode || this.currentZone !== 'heroes') return false;
                        const facade = getFacade();
                        if (!facade) return false;
                        const st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
                        if (!st) return false;
                        const nowMs = this._nowPerfMs();
                        if (!force && !this._hasWsStateChanged(st, privateLastWsSentState, nowMs)) {
                            return false;
                        }
                        const pkt = this._buildWsStatePayload(st);
                        try {
                            privateLobbyWs.send(JSON.stringify(pkt));
                            privateLastWsSentAtMs = nowMs;
                            privateLastWsSentState = pkt;
                            return true;
                        } catch (e) {
                            return false;
                        }
                    },

                    _upsertLocalPeerList(peerObj) {
                        if (!peerObj || typeof peerObj !== 'object') return;
                        const pid = String(peerObj.peer_id || peerObj.player_id || '');
                        if (!pid) return;
                        if (this.multiplayer && this.multiplayer.peer_id && pid === String(this.multiplayer.peer_id)) {
                            return;
                        }
                        const next = Array.isArray(this.peers) ? this.peers.slice() : [];
                        let foundIdx = -1;
                        for (let i = 0; i < next.length; i++) {
                            const cur = next[i];
                            if (cur && (String(cur.peer_id || '') === pid || String(cur.player_id || '') === pid)) {
                                foundIdx = i;
                                break;
                            }
                        }
                        if (foundIdx >= 0) {
                            next[foundIdx] = Object.assign({}, next[foundIdx], peerObj);
                        } else if (next.length < 19) {
                            next.push(peerObj);
                        }
                        this.peers = next;
                    },

                    _removeLocalPeerFromList(peerId) {
                        if (!peerId || !Array.isArray(this.peers)) return;
                        const target = String(peerId);
                        this.peers = this.peers.filter((p) => p && String(p.peer_id || '') !== target && String(p.player_id || '') !== target);
                    },

                    _stopWsTickLoop() {
                        if (privateWsSendTimer) {
                            clearInterval(privateWsSendTimer);
                            privateWsSendTimer = null;
                        }
                        if (privateWsTickWorker) {
                            try {
                                privateWsTickWorker.onmessage = null;
                                privateWsTickWorker.onerror = null;
                                privateWsTickWorker.postMessage(0);
                                privateWsTickWorker.terminate();
                            } catch (e) {}
                            privateWsTickWorker = null;
                        }
                        if (privateWsTickWorkerUrl && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') {
                            try {
                                URL.revokeObjectURL(privateWsTickWorkerUrl);
                            } catch (e2) {}
                            privateWsTickWorkerUrl = null;
                        }
                    },

                    _startWsTickLoop(intervalMs, pingFn) {
                        this._stopWsTickLoop();
                        const cleanMs = Math.max(25, Math.min(100, Math.round(Number(intervalMs) || 33)));
                        const onTick = () => {
                            if (!this.isOpen || !this.wsConnected) return;
                            this._sendWsStateNow(false);
                            if (typeof pingFn === 'function' && (this._nowPerfMs() - (privateLastWsPingSentAtMs || 0)) >= 1500) {
                                pingFn();
                            }
                        };
                        if (typeof Worker !== 'undefined' && typeof Blob !== 'undefined' && typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
                            try {
                                const blobUrl = URL.createObjectURL(new Blob(
                                    ['let t=null;onmessage=e=>{clearInterval(t);if(e.data>0)t=setInterval(()=>postMessage(1),e.data);}'],
                                    { type: 'application/javascript' }
                                ));
                                const worker = new Worker(blobUrl);
                                privateWsTickWorkerUrl = blobUrl;
                                privateWsTickWorker = worker;
                                worker.onmessage = onTick;
                                worker.onerror = () => {
                                    if (privateWsTickWorker === worker) {
                                        this._stopWsTickLoop();
                                        if (this.isOpen && this.wsConnected) {
                                            privateWsSendTimer = setInterval(onTick, cleanMs);
                                        }
                                    }
                                };
                                worker.postMessage(cleanMs);
                                return;
                            } catch (err) {
                                this._stopWsTickLoop();
                            }
                        }
                        privateWsSendTimer = setInterval(onTick, cleanMs);
                    },

                    _clearWsTimers() {
                        this._stopWsTickLoop();
                        if (privateWsPingTimer) {
                            clearInterval(privateWsPingTimer);
                            privateWsPingTimer = null;
                        }
                        if (privateWsWarmupPingTimer) {
                            clearTimeout(privateWsWarmupPingTimer);
                            privateWsWarmupPingTimer = null;
                        }
                        if (privateWsHandshakeFallbackTimer) {
                            clearTimeout(privateWsHandshakeFallbackTimer);
                            privateWsHandshakeFallbackTimer = null;
                        }
                        if (privateWsUrgentTimer) {
                            clearTimeout(privateWsUrgentTimer);
                            privateWsUrgentTimer = null;
                        }
                    },

                    _closeLobbyWs(sendLeave = false) {
                        this.closeLobbyWebSocket(sendLeave);
                    },

                    _applyServerMultiplayerDisabled(facadeOverride = null) {
                        this._wsDisabledByServer = true;
                        this.multiplayerDisabled = true;
                        this._closeLobbyWs(false);
                        this.stopHttpSyncTimers();
                        this.instanceMenuOpen = false;
                        this.chatOpen = false;
                        this.peers = [];
                        this.lobbyInstances = [];
                        this.netStats = Object.assign({}, this.netStats, { tickHz: 'Halle (Solo)' });
                        const f = facadeOverride || getFacade();
                        if (f) {
                            if (typeof f.updatePeers === 'function') {
                                f.updatePeers([]);
                            }
                            if (typeof f.setSoloMode === 'function') {
                                f.setSoloMode(true);
                            }
                        }
                    },

                    closeLobbyWebSocket(sendLeave = false) {
                        this._clearWsTimers();
                        const ws = privateLobbyWs;
                        privateLobbyWs = null;
                        this.wsConnected = false;
                        privateLastWsSentState = null;
                        this._rttWindow = [];
                        this._resetPingOnNextWireSample = true;
                        if (!ws) return;
                        ws.onopen = null;
                        ws.onmessage = null;
                        ws.onerror = null;
                        ws.onclose = null;
                        try {
                            if (sendLeave && ws.readyState === 1) {
                                ws.send(JSON.stringify({ type: 'leave' }));
                            }
                        } catch (e) {}
                        try {
                            ws.close(1000, 'leave');
                        } catch (e2) {}
                    },

                    connectLobbyWebSocket(wsRelay, fallbackHttpRttMs) {
                        if (wsRelay && wsRelay.url && wsRelay.ticket) {
                            this._wsDisabledByServer = false;
                            this.multiplayerDisabled = false;
                        }
                        if (this._wsDisabledByServer || this.multiplayerDisabled || this.soloMode) {
                            return;
                        }
                        if (!wsRelay || !wsRelay.url || !wsRelay.ticket || typeof WebSocket === 'undefined') {
                            if (typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0) {
                                this.recordNetSample(fallbackHttpRttMs, '6,0 Hz');
                            }
                            this.scheduleNextMultiplayerSync(165);
                            return;
                        }
                        if (!this.isOpen || !this.lobbyAuthenticated || this.currentZone !== 'heroes') return;

                        this.closeLobbyWebSocket(false);
                        privateWsTickMs = Math.max(25, Math.min(100, Number(wsRelay.tick_ms) || 33));
                        const wsUrl = String(wsRelay.url) + (String(wsRelay.url).indexOf('?') === -1 ? '?' : '&') + 'ticket=' + encodeURIComponent(String(wsRelay.ticket));
                        let ws = null;
                        try {
                            ws = new WebSocket(wsUrl);
                        } catch (err) {
                            this.wsConnected = false;
                            if (typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0) {
                                this.recordNetSample(fallbackHttpRttMs, '6,0 Hz');
                            }
                            this.scheduleNextMultiplayerSync(165);
                            return;
                        }
                        privateLobbyWs = ws;

                        privateWsHandshakeFallbackTimer = setTimeout(() => {
                            privateWsHandshakeFallbackTimer = null;
                            if (!this.wsConnected && privateLobbyWs === ws && this.isOpen && this.lobbyAuthenticated && !this.soloMode && !this.multiplayerDisabled && !this._wsDisabledByServer && this.currentZone === 'heroes') {
                                this.closeLobbyWebSocket(false);
                                if (typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0) {
                                    this.recordNetSample(fallbackHttpRttMs, '6,0 Hz');
                                }
                                this.scheduleNextMultiplayerSync(165);
                            }
                        }, 2500);

                        const sendWsPing = () => {
                            if (!this.wsConnected || privateLobbyWs !== ws || ws.readyState !== 1) return;
                            const nowP = this._nowPerfMs();
                            privateLastWsPingSentAtMs = nowP;
                            try {
                                ws.send(JSON.stringify({ type: 'ping', c: nowP }));
                            } catch (e) {}
                        };

                        ws.onmessage = (ev) => {
                            if (privateLobbyWs !== ws || !this.isOpen) return;
                            let msg = null;
                            try {
                                msg = JSON.parse(ev.data);
                            } catch (e) {
                                return;
                            }
                            if (!msg || typeof msg !== 'object') return;
                            const facade = getFacade();

                            if (msg.type === 'multiplayer_disabled' || msg.solo_mode === true) {
                                this._applyServerMultiplayerDisabled(facade);
                                return;
                            }
                            if (this.soloMode || this.multiplayerDisabled || this.currentZone !== 'heroes') return;

                            if (msg.type === 'welcome') {
                                this.wsConnected = true;
                                this._rttWindow = [];
                                this._resetPingOnNextWireSample = true;
                                this._netSampleIsDirectWire = true;
                                this.netStats = Object.assign({}, this.netStats, {
                                    hasError: false,
                                    tickHz: '30,0 Hz',
                                    tickIntervalMs: privateWsTickMs,
                                });
                                if (msg.instance_id) {
                                    this.lobbyInstanceId = Number(msg.instance_id) || this.lobbyInstanceId;
                                    this.customInstanceInput = this.lobbyInstanceId;
                                }
                                if (msg.peer_id) {
                                    this.multiplayer.peer_id = String(msg.peer_id);
                                    if (facade && typeof facade.setLocalPeerId === 'function') {
                                        facade.setLocalPeerId(msg.peer_id);
                                    }
                                }
                                this.peers = Array.isArray(msg.peers) ? msg.peers : [];
                                if (facade) {
                                    facade.updatePeers(this.peers);
                                }
                                this._clearWsTimers();
                                this._sendWsStateNow(true);
                                sendWsPing();
                                privateWsWarmupPingTimer = setTimeout(() => {
                                    privateWsWarmupPingTimer = null;
                                    sendWsPing();
                                }, 300);
                                this._startWsTickLoop(privateWsTickMs, sendWsPing);
                                privateWsPingTimer = setInterval(() => {
                                    if ((this._nowPerfMs() - (privateLastWsPingSentAtMs || 0)) >= 1450) {
                                        sendWsPing();
                                    }
                                }, 1500);
                                this.scheduleNextMultiplayerSync(10000);
                                return;
                            }

                            if (msg.type === 'peer_state' && msg.peer) {
                                if (facade && typeof facade.upsertPeer === 'function') {
                                    facade.upsertPeer(msg.peer);
                                }
                                this._upsertLocalPeerList(msg.peer);
                                return;
                            }

                            if (msg.type === 'peer_left' && msg.peer_id) {
                                if (facade && typeof facade.removePeer === 'function') {
                                    facade.removePeer(msg.peer_id);
                                }
                                this._removeLocalPeerFromList(msg.peer_id);
                                return;
                            }

                            if (msg.type === 'pong') {
                                const rtt = Math.max(1, this._nowPerfMs() - Number(msg.c));
                                this.recordNetRttSample(rtt, true, '30,0 Hz');
                            }
                        };

                        const handleWsDisconnect = (ev) => {
                            if (ev && Number(ev.code) === 4001) {
                                this._applyServerMultiplayerDisabled();
                                return;
                            }
                            if (privateLobbyWs !== ws) return;
                            this._clearWsTimers();
                            privateLobbyWs = null;
                            const wasConnected = this.wsConnected;
                            this.wsConnected = false;
                            privateLastWsSentState = null;
                            if (this.isOpen && this.lobbyAuthenticated && !this.soloMode && !this.multiplayerDisabled && !this._wsDisabledByServer && this.currentZone === 'heroes') {
                                this.netStats = Object.assign({}, this.netStats, { tickHz: '6,0 Hz' });
                                if (!wasConnected && typeof fallbackHttpRttMs === 'number' && fallbackHttpRttMs > 0 && this.netStats.pingMs === null) {
                                    this.recordNetSample(fallbackHttpRttMs, '6,0 Hz');
                                }
                                if (wasConnected || !this._syncTimer) {
                                    this.scheduleNextMultiplayerSync(165);
                                }
                            }
                        };

                        ws.onerror = handleWsDisconnect;
                        ws.onclose = handleWsDisconnect;
                    },

                    handleZoneSyncTransition(nextZone) {
                        const z = String(nextZone || 'gallery').toLowerCase();
                        const facade = getFacade();

                        if (z === 'heroes') {
                            if (facade && typeof facade.refreshHeroesData === 'function') {
                                facade.refreshHeroesData(false);
                            }
                            if (this.isCustomerAuthenticated() && this.canEnterSanctums() && !this.soloMode && (this._activeSyncZone !== 'heroes' || (!this._syncTimer && !this.wsConnected))) {
                                this._activeSyncZone = 'heroes';
                                this.joinLobbyInstance();
                            }
                            return;
                        }

                        // Solo-Zonen ('gallery', 'lava'): lokal sofort leeren, Loop stoppen & Heldenhallen-Platz freigeben!
                        const wasInHeroes = (this._activeSyncZone === 'heroes' || Boolean(this._syncTimer) || this.wsConnected);
                        this._activeSyncZone = z;
                        this.instanceMenuOpen = false;
                        this.chatOpen = false;
                        this.stopMultiplayerLoop();
                        this.netStats = Object.assign({}, this.netStats, { tickHz: 'Atelier · Bereit' });
                        this.peers = [];
                        if (facade) {
                            facade.updatePeers([]);
                        }
                        this.probeSoloAtelierPing();

                        if (wasInHeroes && this.lobbyAuthenticated && !this.soloMode && facade) {
                            const st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
                            fetch('/funkenwerk-welt/lobby/sync', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken()
                                },
                                body: JSON.stringify({
                                    x: st.x,
                                    y: st.y,
                                    z: st.z,
                                    vx: st.vx,
                                    vy: st.vy || 0,
                                    vz: st.vz,
                                    rot_y: st.rot_y,
                                    speed: st.speed,
                                    jumping: Boolean(st.jumping),
                                    sprint: Boolean(st.sprint),
                                    wall_cling: Boolean(st.wall_cling),
                                    wall_nx: st.wall_nx || 0,
                                    wall_nz: st.wall_nz || 0,
                                    somersault_seq: st.somersault_seq || 0,
                                    somersault_dir: st.somersault_dir || 0,
                                    somersault_prog: st.somersault_prog || 0,
                                    zone: z,
                                    emote: st.emote || null,
                                    avatar_gender: st.avatar_gender === 'female'
                                        ? 'female'
                                        : ((this.userSettings && this.userSettings.characterGender === 'female') ? 'female' : 'male'),
                                    ability_slot: st.ability_slot || 0,
                                    weapon_seq: st.weapon_seq || 0,
                                    combo_seq: st.combo_seq,
                                    combo_stage: st.combo_stage
                                })
                            }).catch(() => {});
                        }
                    },

                    _ingestLobbySyncMessages(rawMessages) {
                        if (!Array.isArray(rawMessages) || rawMessages.length === 0) return;
                        if (!this._seenMessageIds) this._seenMessageIds = new Set();
                        const facade = getFacade();
                        for (let i = 0; i < rawMessages.length; i++) {
                            const m = rawMessages[i];
                            if (!m || !m.text) continue;
                            const mid = String(m.id || ((m.peer_id || m.player_id || '') + ':' + (m.ts || '') + ':' + m.text));
                            if (this._seenMessageIds.has(mid)) continue;
                            this._seenMessageIds.add(mid);
                            const peerKey = String(m.peer_id || m.player_id || '');
                            const normalized = {
                                id: mid,
                                peer_id: peerKey,
                                player_id: String(m.player_id || peerKey),
                                display_name: String(m.display_name || m.sender || 'Funki'),
                                class_label: m.class_label || 'KLASSE C',
                                text: String(m.text),
                            };
                            this.chatMessages = this.chatMessages.concat([normalized]).slice(-25);
                            if (facade && peerKey && this.mutedPlayerIds.indexOf(peerKey) === -1) {
                                facade.showChatBubble(peerKey, normalized.text);
                            }
                            if (!this.chatOpen && !this.isOwnChatMessage(normalized)) {
                                this.unreadChatCount += 1;
                            }
                        }
                    },

                    joinLobbyInstance() {
                        const facade = getFacade();
                        if (!this.isOpen || !facade || !this.isCustomerAuthenticated() || this.soloMode || this.currentZone !== 'heroes') {
                            return;
                        }
                        const st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
                        const t0 = this._nowPerfMs();
                        this._lastSyncSentAtMs = t0;

                        fetch('/funkenwerk-welt/lobby/join', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                x: st.x,
                                y: st.y,
                                z: st.z,
                                vx: st.vx,
                                vy: st.vy || 0,
                                vz: st.vz,
                                rot_y: st.rot_y,
                                speed: st.speed,
                                jumping: Boolean(st.jumping),
                                sprint: Boolean(st.sprint),
                                wall_cling: Boolean(st.wall_cling),
                                wall_nx: st.wall_nx || 0,
                                wall_nz: st.wall_nz || 0,
                                somersault_seq: st.somersault_seq || 0,
                                somersault_dir: st.somersault_dir || 0,
                                somersault_prog: st.somersault_prog || 0,
                                zone: 'heroes',
                                emote: st.emote || null,
                                avatar_gender: st.avatar_gender === 'female'
                                    ? 'female'
                                    : ((this.userSettings && this.userSettings.characterGender === 'female') ? 'female' : 'male'),
                                ability_slot: st.ability_slot || 0,
                                weapon_seq: st.weapon_seq || 0,
                                combo_seq: st.combo_seq,
                                combo_stage: st.combo_stage
                            })
                        })
                            .then((res) => {
                                const rtt = this._nowPerfMs() - t0;
                                return res.json().catch(() => null).then((b) => {
                                    if (res.status === 401 || res.status === 403) {
                                        this.stopMultiplayerLoop();
                                        const fGate = getFacade();
                                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                                            fGate.handleSanctumHttpResponse(res.status, b, 'heroes');
                                        }
                                        return null;
                                    }
                                    if (res.ok) {
                                        const hasWs = Boolean(b && b.ws_relay && b.ws_relay.url && b.ws_relay.ticket);
                                        if (!hasWs && !this.wsConnected) {
                                            this.recordNetSample(rtt, '6,0 Hz');
                                        }
                                        if (b && typeof b === 'object') {
                                            b._joinHttpRtt = rtt;
                                        }
                                    } else {
                                        this.recordNetFailure();
                                    }
                                    return res.ok ? b : null;
                                });
                            })
                            .then((data) => {
                                if (!data || !data.ok || !this.isOpen || this.currentZone !== 'heroes') return;
                                this.lobbyAuthenticated = Boolean(data.authenticated);
                                if (data.chat_moderation && typeof data.chat_moderation === 'object') {
                                    this._syncChatModeration(data.chat_moderation);
                                }
                                const f2 = getFacade();
                                if (data.multiplayer_disabled || data.solo_mode === true) {
                                    this._applyServerMultiplayerDisabled(f2);
                                    return;
                                }
                                if (this.multiplayerDisabled) {
                                    this.multiplayerDisabled = false;
                                    if (f2 && typeof f2.setSoloMode === 'function') {
                                        f2.setSoloMode(Boolean(this.soloMode));
                                    }
                                }
                                if (data.tick_interval_ms && !this.wsConnected) {
                                    this.netStats = Object.assign({}, this.netStats, {
                                        tickIntervalMs: Math.max(120, Math.min(250, Number(data.tick_interval_ms) || 165)),
                                    });
                                }
                                if (data.instance_id) {
                                    this.lobbyInstanceId = Number(data.instance_id) || 1;
                                    this.customInstanceInput = this.lobbyInstanceId;
                                }
                                if (data.hard_cap) {
                                    this.lobbyCapacity = Number(data.hard_cap) || 20;
                                }
                                if (Array.isArray(data.instances)) {
                                    this.lobbyInstances = data.instances;
                                }
                                this.peers = Array.isArray(data.peers) ? data.peers : [];
                                if (f2) {
                                    if (data.peer_id) {
                                        this.multiplayer.peer_id = String(data.peer_id);
                                        if (typeof f2.setLocalPeerId === 'function') {
                                            f2.setLocalPeerId(data.peer_id);
                                        }
                                    }
                                    f2.updatePeers(this.peers);
                                }
                                if (Array.isArray(data.messages)) {
                                    this._ingestLobbySyncMessages(data.messages);
                                }
                                if (this.lobbyAuthenticated && !this.soloMode && !this.multiplayerDisabled && this.currentZone === 'heroes') {
                                    const hasWsRelay = Boolean(data.ws_relay && data.ws_relay.url && data.ws_relay.ticket);
                                    if (hasWsRelay) {
                                        this._wsDisabledByServer = false;
                                    }
                                    this.startMultiplayerLoop(hasWsRelay ? 10000 : undefined);
                                    if (hasWsRelay) {
                                        this.connectLobbyWebSocket(data.ws_relay, data._joinHttpRtt);
                                    }
                                }
                            })
                            .catch(() => {
                                this.recordNetFailure();
                            });
                    },

                    computeAdaptiveSyncIntervalMs(st) {
                        if (this.wsConnected) {
                            if (this.netStats && this.netStats.tickHz !== '30,0 Hz') {
                                this.netStats = Object.assign({}, this.netStats, { tickHz: '30,0 Hz' });
                            }
                            return 10000;
                        }
                        const speed = st ? (Number(st.speed) || 0) : 0;
                        const jumping = Boolean(st && st.jumping);
                        const hasPeers = Array.isArray(this.peers) && this.peers.length > 0;
                        const isActive = speed > 0.05 || jumping || hasPeers;
                        const nextHz = isActive ? '6,0 Hz' : 'Ruhe-Puls';
                        if (this.netStats && this.netStats.tickHz !== nextHz) {
                            this.netStats = Object.assign({}, this.netStats, { tickHz: nextHz });
                        }
                        return isActive ? 165 : 450;
                    },

                    scheduleNextMultiplayerSync(overrideDelayMs) {
                        if (this._syncTimer) {
                            clearTimeout(this._syncTimer);
                            this._syncTimer = null;
                        }
                        if (!this.isOpen || !this.lobbyAuthenticated || this.soloMode || this.currentZone !== 'heroes') {
                            return;
                        }
                        const facade = getFacade();
                        const st = facade ? (typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot()) : null;
                        const delay = typeof overrideDelayMs === 'number' ? overrideDelayMs : this.computeAdaptiveSyncIntervalMs(st);
                        this._syncTimer = setTimeout(() => {
                            this._syncTimer = null;
                            this.runMultiplayerSync();
                        }, delay);
                    },

                    requestImmediateMultiplayerSync() {
                        if (!this.isOpen || !this.lobbyAuthenticated || this.soloMode || this.currentZone !== 'heroes') {
                            return;
                        }
                        if (this.wsConnected && privateLobbyWs && privateLobbyWs.readyState === 1) {
                            const wsElapsed = this._nowPerfMs() - (privateLastWsSentAtMs || 0);
                            if (wsElapsed >= 16) {
                                if (privateWsUrgentTimer) {
                                    clearTimeout(privateWsUrgentTimer);
                                    privateWsUrgentTimer = null;
                                }
                                this._sendWsStateNow(true);
                            } else if (!privateWsUrgentTimer) {
                                const wsWait = Math.max(4, Math.ceil(16 - wsElapsed));
                                privateWsUrgentTimer = setTimeout(() => {
                                    privateWsUrgentTimer = null;
                                    this._sendWsStateNow(true);
                                }, wsWait);
                            }
                            return;
                        }
                        if (this._syncInFlight) {
                            this._syncPendingImmediate = true;
                            return;
                        }
                        const elapsed = this._nowPerfMs() - (this._lastSyncSentAtMs || 0);
                        if (elapsed < 75) {
                            this._syncPendingImmediate = true;
                            const waitMs = Math.max(8, Math.ceil(75 - elapsed));
                            this.scheduleNextMultiplayerSync(waitMs);
                            return;
                        }
                        this._syncPendingImmediate = false;
                        if (this._syncTimer) {
                            clearTimeout(this._syncTimer);
                            this._syncTimer = null;
                        }
                        this.runMultiplayerSync();
                    },

                    checkUrgentLocalSyncWatch() {
                        if (!this.isOpen || !this.lobbyAuthenticated || this.soloMode || this.currentZone !== 'heroes') {
                            this._lastWatchSyncSnap = null;
                            return;
                        }
                        const facade = getFacade();
                        if (!facade) return;
                        const st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
                        if (!st) return;

                        const moving = (Number(st.speed) || 0) > 0.08;
                        const jumping = Boolean(st.jumping);
                        const wallCling = Boolean(st.wall_cling);
                        const somersaultSeq = st.somersault_seq || 0;
                        const emote = st.emote || null;
                        const velAngle = moving ? Math.atan2(Number(st.vx) || 0, Number(st.vz) || 0) : 0;

                        if (!this._lastWatchSyncSnap) {
                            this._lastWatchSyncSnap = { moving, jumping, wallCling, somersaultSeq, emote, velAngle };
                            return;
                        }
                        const prev = this._lastWatchSyncSnap;
                        let rawDiff = velAngle - prev.velAngle;
                        while (rawDiff > Math.PI) rawDiff -= Math.PI * 2;
                        while (rawDiff < -Math.PI) rawDiff += Math.PI * 2;
                        const dirTurned = moving && prev.moving && Math.abs(rawDiff) > (25 * Math.PI / 180);

                        if (
                            moving !== prev.moving
                            || (!prev.jumping && jumping)
                            || wallCling !== prev.wallCling
                            || somersaultSeq !== prev.somersaultSeq
                            || emote !== prev.emote
                            || dirTurned
                        ) {
                            this._lastWatchSyncSnap = { moving, jumping, wallCling, somersaultSeq, emote, velAngle };
                            this.requestImmediateMultiplayerSync();
                        }
                    },

                    stopHttpSyncTimers() {
                        if (this._syncTimer) {
                            clearTimeout(this._syncTimer);
                            this._syncTimer = null;
                        }
                        if (this._urgentSyncWatchTimer) {
                            clearInterval(this._urgentSyncWatchTimer);
                            this._urgentSyncWatchTimer = null;
                        }
                        this._syncInFlight = false;
                        this._syncPendingImmediate = false;
                        this._lastWatchSyncSnap = null;
                    },

                    startMultiplayerLoop(initialDelayMs) {
                        this.stopHttpSyncTimers();
                        if (!this.isOpen || !this.lobbyAuthenticated || this.soloMode || this.currentZone !== 'heroes') {
                            return;
                        }
                        this._urgentSyncWatchTimer = setInterval(() => {
                            this.checkUrgentLocalSyncWatch();
                        }, 60);
                        this.scheduleNextMultiplayerSync(initialDelayMs);
                    },

                    stopMultiplayerLoop() {
                        this.closeLobbyWebSocket(true);
                        this.stopHttpSyncTimers();
                    },

                    runMultiplayerSync() {
                        const facade = getFacade();
                        if (!this.isOpen || !facade) return;
                        if (!this.lobbyAuthenticated || this.soloMode || this.currentZone !== 'heroes') {
                            this.stopMultiplayerLoop();
                            return;
                        }
                        if (this._syncInFlight) {
                            if (!this.wsConnected) {
                                this._syncPendingImmediate = true;
                            }
                            return;
                        }

                        this._syncInFlight = true;
                        const st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
                        const t0 = this._nowPerfMs();
                        this._lastSyncSentAtMs = t0;
                        const nextHzLabel = this.wsConnected
                            ? '30,0 Hz'
                            : (this.computeAdaptiveSyncIntervalMs(st) <= 165 ? '6,0 Hz' : 'Ruhe-Puls');

                        fetch('/funkenwerk-welt/lobby/sync', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                x: st.x,
                                y: st.y,
                                z: st.z,
                                vx: st.vx,
                                vy: st.vy || 0,
                                vz: st.vz,
                                rot_y: st.rot_y,
                                speed: st.speed,
                                jumping: Boolean(st.jumping),
                                sprint: Boolean(st.sprint),
                                wall_cling: Boolean(st.wall_cling),
                                wall_nx: st.wall_nx || 0,
                                wall_nz: st.wall_nz || 0,
                                somersault_seq: st.somersault_seq || 0,
                                somersault_dir: st.somersault_dir || 0,
                                somersault_prog: st.somersault_prog || 0,
                                zone: 'heroes',
                                emote: st.emote || null,
                                avatar_gender: st.avatar_gender === 'female'
                                    ? 'female'
                                    : ((this.userSettings && this.userSettings.characterGender === 'female') ? 'female' : 'male'),
                                ability_slot: st.ability_slot || 0,
                                weapon_seq: st.weapon_seq || 0,
                                combo_seq: st.combo_seq,
                                combo_stage: st.combo_stage
                            })
                        })
                            .then((res) => {
                                const rtt = this._nowPerfMs() - t0;
                                return res.json().catch(() => null).then((b) => {
                                    if (res.status === 401 || res.status === 403) {
                                        this.stopMultiplayerLoop();
                                        const fGate = getFacade();
                                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                                            fGate.handleSanctumHttpResponse(res.status, b, 'heroes');
                                        }
                                        return null;
                                    }
                                    if (res.ok) {
                                        if (!this.wsConnected && !privateLobbyWs) {
                                            this.recordNetSample(rtt, nextHzLabel);
                                        }
                                    } else if (!this.wsConnected && !privateLobbyWs) {
                                        this.recordNetFailure();
                                    }
                                    return res.ok ? b : null;
                                });
                            })
                            .then((data) => {
                                const f2 = getFacade();
                                if (!data || !data.ok || !this.isOpen || !f2 || this.currentZone !== 'heroes') return;
                                if (data.chat_moderation && typeof data.chat_moderation === 'object') {
                                    this._syncChatModeration(data.chat_moderation);
                                }
                                if (data.multiplayer_disabled || data.solo_mode === true) {
                                    this._applyServerMultiplayerDisabled(f2);
                                    return;
                                }
                                if (this.multiplayerDisabled) {
                                    this.multiplayerDisabled = false;
                                    if (typeof f2.setSoloMode === 'function') {
                                        f2.setSoloMode(Boolean(this.soloMode));
                                    }
                                }
                                if (data.ws_relay && data.ws_relay.url && data.ws_relay.ticket && !this.wsConnected && !privateLobbyWs) {
                                    this._wsDisabledByServer = false;
                                    this.connectLobbyWebSocket(data.ws_relay);
                                }
                                if (data.tick_interval_ms && !this.wsConnected) {
                                    this.netStats = Object.assign({}, this.netStats, {
                                        tickIntervalMs: Math.max(120, Math.min(250, Number(data.tick_interval_ms) || 165)),
                                    });
                                }
                                if (data.instance_id) {
                                    this.lobbyInstanceId = Number(data.instance_id) || this.lobbyInstanceId;
                                }
                                if (Array.isArray(data.instances)) {
                                    this.lobbyInstances = data.instances;
                                }
                                if (data.peer_id) {
                                    this.multiplayer.peer_id = String(data.peer_id);
                                    if (typeof f2.setLocalPeerId === 'function') {
                                        f2.setLocalPeerId(data.peer_id);
                                    }
                                }
                                if (!this.wsConnected) {
                                    this.peers = Array.isArray(data.peers) ? data.peers : [];
                                    f2.updatePeers(this.peers);
                                }
                                if (Array.isArray(data.messages)) {
                                    this._ingestLobbySyncMessages(data.messages);
                                }
                            })
                            .catch(() => {
                                if (!this.wsConnected && !privateLobbyWs) {
                                    this.recordNetFailure();
                                }
                            })
                            .finally(() => {
                                this._syncInFlight = false;
                                if (!this.isOpen || !this.lobbyAuthenticated || this.soloMode || this.multiplayerDisabled || this.currentZone !== 'heroes') {
                                    return;
                                }
                                if (!this.wsConnected && this._syncPendingImmediate) {
                                    this._syncPendingImmediate = false;
                                    this.requestImmediateMultiplayerSync();
                                } else {
                                    this.scheduleNextMultiplayerSync(this.wsConnected ? 10000 : undefined);
                                }
                            });
                    },

                    switchLobbyInstance(targetId) {
                        const facade = getFacade();
                        if (!this.isOpen || !facade || !this.lobbyAuthenticated) return;
                        const cleanTarget = Math.max(1, Math.min(50, parseInt(targetId, 10) || 1));
                        if (this.currentZone !== 'heroes') {
                            facade.setZone('heroes', false);
                            this.currentZone = 'heroes';
                            this._activeSyncZone = 'heroes';
                        }
                        const st = typeof facade.getLocalSyncState === 'function' ? facade.getLocalSyncState() : facade.getHudSnapshot();
                        this.instanceMenuOpen = false;
                        this.closeLobbyWebSocket(true);
                        const t0 = this._nowPerfMs();

                        fetch('/funkenwerk-welt/lobby/switch', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                target_instance: cleanTarget,
                                x: st.x,
                                y: st.y,
                                z: st.z,
                                vx: st.vx,
                                vy: st.vy || 0,
                                vz: st.vz,
                                rot_y: st.rot_y,
                                speed: st.speed,
                                jumping: Boolean(st.jumping),
                                sprint: Boolean(st.sprint),
                                wall_cling: Boolean(st.wall_cling),
                                wall_nx: st.wall_nx || 0,
                                wall_nz: st.wall_nz || 0,
                                somersault_seq: st.somersault_seq || 0,
                                somersault_dir: st.somersault_dir || 0,
                                somersault_prog: st.somersault_prog || 0,
                                avatar_gender: st.avatar_gender === 'female'
                                    ? 'female'
                                    : ((this.userSettings && this.userSettings.characterGender === 'female') ? 'female' : 'male'),
                                zone: 'heroes'
                            })
                        })
                            .then((res) => {
                                const rtt = this._nowPerfMs() - t0;
                                return res.json().catch(() => null).then((b) => {
                                    if (res.status === 401 || res.status === 403) {
                                        this.stopMultiplayerLoop();
                                        const fGate = getFacade();
                                        if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                                            fGate.handleSanctumHttpResponse(res.status, b, 'heroes');
                                        }
                                        return null;
                                    }
                                    if (res.ok) {
                                        const hasWsSwitch = Boolean(b && b.ws_relay && b.ws_relay.url && b.ws_relay.ticket);
                                        if (!hasWsSwitch && !this.wsConnected) {
                                            this.recordNetSample(rtt, '6,0 Hz');
                                        }
                                        if (b && typeof b === 'object') {
                                            b._switchHttpRtt = rtt;
                                        }
                                    } else {
                                        this.recordNetFailure();
                                    }
                                    return res.ok ? b : null;
                                });
                            })
                            .then((data) => {
                                const f2 = getFacade();
                                if (!data || !data.ok || !this.isOpen || !f2) return;
                                if (data.chat_moderation && typeof data.chat_moderation === 'object') {
                                    this._syncChatModeration(data.chat_moderation);
                                }
                                if (data.multiplayer_disabled || data.solo_mode === true) {
                                    this._applyServerMultiplayerDisabled(f2);
                                    return;
                                }
                                if (data.instance_id) {
                                    this.lobbyInstanceId = Number(data.instance_id) || cleanTarget;
                                    this.customInstanceInput = this.lobbyInstanceId;
                                }
                                if (Array.isArray(data.instances)) {
                                    this.lobbyInstances = data.instances;
                                }
                                if (data.peer_id) {
                                    this.multiplayer.peer_id = String(data.peer_id);
                                    if (typeof f2.setLocalPeerId === 'function') {
                                        f2.setLocalPeerId(data.peer_id);
                                    }
                                }
                                this.chatMessages = [];
                                this._seenMessageIds = new Set();
                                this.peers = Array.isArray(data.peers) ? data.peers : [];
                                f2.switchInstance(this.peers);
                                if (Array.isArray(data.messages)) {
                                    this._ingestLobbySyncMessages(data.messages);
                                }
                                if (this.lobbyAuthenticated && !this.soloMode && !this.multiplayerDisabled && this.currentZone === 'heroes') {
                                    const hasWsRelay = Boolean(data.ws_relay && data.ws_relay.url && data.ws_relay.ticket);
                                    if (hasWsRelay) {
                                        this._wsDisabledByServer = false;
                                    }
                                    this.startMultiplayerLoop(hasWsRelay ? 10000 : undefined);
                                    if (hasWsRelay) {
                                        this.connectLobbyWebSocket(data.ws_relay, data._switchHttpRtt);
                                    }
                                }
                            })
                            .catch(() => {
                                this.recordNetFailure();
                            });
                    },

                    sendLobbyLeaveBeacon() {
                        if (!this.lobbyAuthenticated) return;
                        const token = this.csrfToken();
                        if (navigator.sendBeacon && typeof FormData !== 'undefined') {
                            const fd = new FormData();
                            fd.append('_token', token);
                            navigator.sendBeacon('/funkenwerk-welt/lobby/leave', fd);
                            return;
                        }
                        fetch('/funkenwerk-welt/lobby/leave', {
                            method: 'POST',
                            credentials: 'same-origin',
                            keepalive: true,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({})
                        }).catch(() => {});
                    },

                    toggleSoloMode() {
                        if (!this.isCustomerAuthenticated()) return;
                        this.soloMode = !this.soloMode;
                        this.instanceMenuOpen = false;
                        if (this.soloMode) {
                            this.chatOpen = false;
                            this.peers = [];
                            this._activeSyncZone = null;
                            this.stopMultiplayerLoop();
                            this.sendLobbyLeaveBeacon();
                        } else if (this.currentZone === 'heroes') {
                            this._activeSyncZone = null;
                            this.handleZoneSyncTransition('heroes');
                        }
                        const facade = getFacade();
                        if (facade) {
                            facade.setSoloMode(this.soloMode);
                        }
                    },

                    toggleChatPanel() {
                        this.chatOpen = !this.chatOpen;
                        if (this.chatOpen) {
                            this.unreadChatCount = 0;
                        }
                    },

                    msgPeerKey(msg) {
                        if (!msg || typeof msg !== 'object') return '';
                        return String(msg.peer_id || msg.player_id || '');
                    },

                    isOwnChatMessage(msg) {
                        if (!msg || typeof msg !== 'object') return false;
                        const pk = String(msg.peer_id || '');
                        const pl = String(msg.player_id || '');
                        if (pk && this.multiplayer.peer_id && pk === String(this.multiplayer.peer_id)) return true;
                        if (pl && this.multiplayer.player_id && pl === String(this.multiplayer.player_id)) return true;
                        if (pk && this.multiplayer.player_id && pk === String(this.multiplayer.player_id)) return true;
                        return false;
                    },

                    visibleChatMessages() {
                        if (!Array.isArray(this.chatMessages)) return [];
                        return this.chatMessages.filter((m) => {
                            if (!m) return false;
                            const key = this.msgPeerKey(m);
                            return !key || this.mutedPlayerIds.indexOf(key) === -1;
                        });
                    },

                    showFeedback(text) {
                        this.chatFeedback = String(text || '').replace(/\s[\u2013\u2014]\s/g, ' · ').trim();
                        if (this._feedbackTimer) {
                            clearTimeout(this._feedbackTimer);
                        }
                        this._feedbackTimer = setTimeout(() => {
                            this.chatFeedback = '';
                        }, 4000);
                    },

                    formatMuteRemaining(sec) {
                        const s = Math.max(0, Math.ceil(Number(sec) || 0));
                        if (s >= 3600) {
                            const hrs = Math.floor(s / 3600);
                            const mins = Math.floor((s % 3600) / 60);
                            return hrs + ' Std. ' + mins + ' Min.';
                        }
                        if (s >= 60) {
                            const mins = Math.floor(s / 60);
                            const rem = s % 60;
                            return String(mins).padStart(2, '0') + ':' + String(rem).padStart(2, '0') + ' Min.';
                        }
                        return s + ' Sek.';
                    },

                    chatStrikeBadgeLabel() {
                        const info = this.chatStrikeInfo || {};
                        const st = Number(info.strikes) || 0;
                        if (info.isMuted) {
                            if (st === 2 && !info.permanent && info.level !== 'auto_mute_24h' && info.level !== 'auto_mute_permanent') {
                                return 'Abmahnung 2/3';
                            }
                            return 'Chat gesperrt';
                        }
                        if (st === 2 || info.level === 'auto_mute_15m') {
                            return 'Abmahnung 2/3';
                        }
                        if (st === 1 || info.level === 'auto_warning_1') {
                            return 'Verwarnung 1/3';
                        }
                        return 'Hausordnung';
                    },

                    chatMuteCountdownLabel() {
                        const info = this.chatStrikeInfo || {};
                        if (info.permanent) return 'Dauerhaft';
                        if (info.remainingSec > 0) return this.formatMuteRemaining(info.remainingSec);
                        return 'Aktiv';
                    },

                    chatMutePlaceholder() {
                        const info = this.chatStrikeInfo || {};
                        if (info.permanent) return 'Chat gesperrt (dauerhaft)';
                        if (info.remainingSec > 0) return 'Chat gesperrt (' + this.formatMuteRemaining(info.remainingSec) + ')';
                        return 'Chat gesperrt';
                    },

                    hasActiveChatModerationNotice() {
                        const info = this.chatStrikeInfo;
                        if (!info) return false;
                        return Boolean(info.isMuted || info.bannerText !== '' || info.level !== '');
                    },

                    _stopChatMuteCountdown() {
                        if (this._chatMuteTickTimer) {
                            clearInterval(this._chatMuteTickTimer);
                            this._chatMuteTickTimer = null;
                        }
                        this._chatMuteUntilPerfMs = 0;
                    },

                    _startChatMuteCountdown(seconds) {
                        this._stopChatMuteCountdown();
                        const cleanSec = Math.max(0, Math.ceil(Number(seconds) || 0));
                        if (cleanSec <= 0 || !this.isOpen) return;
                        this._chatMuteUntilPerfMs = this._nowPerfMs() + (cleanSec * 1000);
                        this._chatMuteTickTimer = setInterval(() => {
                            if (!this.isOpen || !this.chatStrikeInfo || !this.chatStrikeInfo.isMuted || this.chatStrikeInfo.permanent) {
                                this._stopChatMuteCountdown();
                                return;
                            }
                            const left = Math.max(0, Math.ceil((this._chatMuteUntilPerfMs - this._nowPerfMs()) / 1000));
                            if (left <= 0) {
                                this._stopChatMuteCountdown();
                                this.chatStrikeInfo = Object.assign({}, this.chatStrikeInfo, {
                                    isMuted: false,
                                    remainingSec: 0,
                                    bannerText: this.chatStrikeInfo.strikes > 0 ? '' : this.chatStrikeInfo.bannerText,
                                    level: ''
                                });
                                return;
                            }
                            this.chatStrikeInfo = Object.assign({}, this.chatStrikeInfo, {
                                remainingSec: left
                            });
                        }, 1000);
                    },

                    _syncChatModeration(cm) {
                        if (!cm || typeof cm !== 'object') return;
                        const strikes = Math.max(0, parseInt(cm.strikes ?? 0, 10) || 0);
                        const permanent = Boolean(cm.permanent_mute);
                        const remainingSec = permanent ? 0 : Math.max(0, Math.ceil(Number(cm.remaining_seconds) || 0));
                        const isMuted = Boolean(cm.is_muted || permanent || remainingSec > 0);
                        const prev = this.chatStrikeInfo || {};
                        let nextLevel = prev.level || '';
                        let nextBanner = prev.bannerText || '';

                        if (isMuted) {
                            if (permanent) {
                                nextLevel = 'auto_mute_permanent';
                                if (!nextBanner) nextBanner = 'Hausordnung: Dein Chat-Zugang ist dauerhaft gesperrt.';
                            } else if (strikes >= 3) {
                                nextLevel = 'auto_mute_24h';
                                if (!nextBanner) nextBanner = 'Hausordnung: Dein Chat ist vorübergehend gesperrt.';
                            } else if (strikes === 2) {
                                nextLevel = 'auto_mute_15m';
                                if (!nextBanner) nextBanner = 'Hausordnung: Dein Chat ist für 15 Minuten gesperrt.';
                            } else {
                                nextLevel = 'chat_muted';
                                if (!nextBanner) nextBanner = 'Dein Chat ist aktuell gesperrt.';
                            }
                        } else if (prev.isMuted) {
                            nextLevel = '';
                            nextBanner = '';
                        }

                        this.chatStrikeInfo = {
                            strikes,
                            isMuted,
                            remainingSec,
                            permanent,
                            bannerText: String(nextBanner || '').replace(/\s[\u2013\u2014]\s/g, ' · ').trim(),
                            level: nextLevel
                        };

                        if (isMuted && !permanent && remainingSec > 0) {
                            this._startChatMuteCountdown(remainingSec);
                        } else {
                            this._stopChatMuteCountdown();
                        }
                    },

                    _applyChatModerationError(body) {
                        if (!body || typeof body !== 'object') return false;
                        const code = String(body.code || '').trim();
                        if (code === 'multiplayer_disabled' || body.multiplayer_disabled === true) {
                            this._applyServerMultiplayerDisabled();
                            return true;
                        }
                        const modCodes = ['auto_warning_1', 'auto_mute_15m', 'auto_mute_24h', 'auto_mute_permanent', 'chat_muted', 'muted'];
                        if (modCodes.indexOf(code) === -1 && !(body.chat_moderation && typeof body.chat_moderation === 'object')) {
                            return false;
                        }

                        const cm = (body.chat_moderation && typeof body.chat_moderation === 'object') ? body.chat_moderation : {};
                        let strikes = Math.max(0, parseInt(body.strike ?? body.strikes ?? cm.strikes ?? this.chatStrikeInfo.strikes ?? 0, 10) || 0);
                        if (code === 'auto_warning_1' && strikes < 1) strikes = 1;
                        if (code === 'auto_mute_15m' && strikes < 2) strikes = 2;
                        if (code === 'auto_mute_24h' && strikes < 3) strikes = 3;
                        if (code === 'auto_mute_permanent' && strikes < 4) strikes = 4;

                        const permanent = Boolean(body.permanent_mute || cm.permanent_mute || code === 'auto_mute_permanent');
                        const defaultRem = code === 'auto_mute_15m' ? 900 : (code === 'auto_mute_24h' ? 86400 : 0);
                        const rawRem = body.remaining_seconds !== undefined ? body.remaining_seconds : cm.remaining_seconds;
                        const remainingSec = permanent ? 0 : Math.max(0, Math.ceil(Number(rawRem !== undefined ? rawRem : defaultRem) || 0));
                        const isMuted = Boolean(
                            permanent
                            || remainingSec > 0
                            || cm.is_muted
                            || code === 'auto_mute_15m'
                            || code === 'auto_mute_24h'
                            || code === 'auto_mute_permanent'
                            || code === 'chat_muted'
                            || code === 'muted'
                        );
                        const fallbackMsg = isMuted
                            ? 'Hausordnung: Chat-Sperre aktiv.'
                            : 'Bitte beachte unsere freundliche Hausordnung im Raum-Chat.';
                        const cleanText = String(body.message || fallbackMsg).replace(/\s[\u2013\u2014]\s/g, ' · ').trim();

                        this.chatFeedback = '';
                        this.chatStrikeInfo = {
                            strikes,
                            isMuted,
                            remainingSec,
                            permanent,
                            bannerText: cleanText,
                            level: code || (isMuted ? 'chat_muted' : 'auto_warning_1')
                        };

                        if (isMuted && !permanent && remainingSec > 0) {
                            this._startChatMuteCountdown(remainingSec);
                        } else {
                            this._stopChatMuteCountdown();
                        }
                        return true;
                    },

                    sendChatMessage(overrideText) {
                        const raw = overrideText !== undefined ? overrideText : this.chatDraft;
                        const text = String(raw || '').trim();
                        if (text === '' || this.chatSending) return;
                        if (!this.isCustomerAuthenticated() || this.soloMode || this.multiplayerDisabled) return;
                        if (this.chatStrikeInfo && this.chatStrikeInfo.isMuted) return;

                        if (text.startsWith('/')) {
                            const parts = text.split(' ');
                            const cmd = parts[0].toLowerCase();
                            const validEmotes = {
                                '/dance': { emote: 'dance', text: '* tanzt ausgelassen durch die Halle *' },
                                '/wave': { emote: 'wave', text: '* winkt freundlich in die Runde *' },
                                '/bow': { emote: 'bow', text: '* verbeugt sich respektvoll *' },
                                '/cheer': { emote: 'cheer', text: '* jubelt begeistert *' },
                                '/sit': { emote: 'sit', text: '* setzt sich gemütlich hin *' },
                                '/clap': { emote: 'clap', text: '* klatscht Beifall *' },
                            };

                            if (validEmotes[cmd]) {
                                const eInfo = validEmotes[cmd];
                                const facade = getFacade();
                                if (facade && typeof facade.triggerEmote === 'function') {
                                    facade.triggerEmote(eInfo.emote);
                                }
                                this._sendActualChatMessage(eInfo.text);
                                this.chatDraft = '';
                                return;
                            }

                            if (cmd === '/help') {
                                this.showFeedback('Befehle: /dance, /wave, /bow, /cheer, /sit, /clap, /jump, /help');
                                this.chatDraft = '';
                                return;
                            }

                            if (cmd === '/jump') {
                                this.triggerJump();
                                this.chatDraft = '';
                                return;
                            }
                        }

                        this._sendActualChatMessage(text);
                        this.chatDraft = '';
                    },

                    _sendActualChatMessage(text) {
                        if (this.chatStrikeInfo && this.chatStrikeInfo.isMuted) return;
                        this.chatSending = true;
                        fetch('/funkenwerk-welt/lobby/chat', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({ message: text })
                        })
                            .then((res) => res.json().catch(() => null).then((body) => ({ ok: res.ok, status: res.status, body })))
                            .then(({ ok, body }) => {
                                if (!ok || !body || !(body.ok || body.success)) {
                                    if (this._applyChatModerationError(body)) {
                                        return;
                                    }
                                    this.showFeedback((body && body.message) ? body.message : 'Nachricht konnte nicht gesendet werden.');
                                    return;
                                }
                                if (body.chat_moderation && typeof body.chat_moderation === 'object') {
                                    this._syncChatModeration(body.chat_moderation);
                                }
                                this.chatFeedback = '';
                                const entry = body.entry || body.message_entry || null;
                                if (entry && entry.text) {
                                    if (!this._seenMessageIds) this._seenMessageIds = new Set();
                                    const eid = String(entry.id || ((entry.peer_id || entry.player_id || 'self') + ':' + entry.text));
                                    this._seenMessageIds.add(eid);
                                    const peerKey = String(entry.peer_id || entry.player_id || this.multiplayer.peer_id || '');
                                    const normalized = {
                                        id: eid,
                                        peer_id: peerKey,
                                        player_id: String(entry.player_id || peerKey),
                                        display_name: String(entry.display_name || this.playerHud.name || 'Funki'),
                                        class_label: entry.class_label || 'KLASSE C',
                                        text: String(entry.text),
                                    };
                                    this.chatMessages = this.chatMessages.concat([normalized]).slice(-25);
                                    const facade = getFacade();
                                    if (facade) {
                                        facade.showChatBubble(peerKey || 'self', normalized.text);
                                    }
                                }
                                if (Array.isArray(body.messages)) {
                                    this._ingestLobbySyncMessages(body.messages);
                                }
                            })
                            .catch(() => {
                                this.showFeedback('Verbindung zum Raum-Chat fehlgeschlagen.');
                            })
                            .finally(() => {
                                this.chatSending = false;
                            });
                    },

                    loadFriendsList() {
                        try {
                            const raw = localStorage.getItem('sf_louvre_friends');
                            this.friendsList = raw ? JSON.parse(raw) : [];
                            if (!Array.isArray(this.friendsList)) this.friendsList = [];
                        } catch (e) {
                            this.friendsList = [];
                        }
                    },

                    saveFriendsList() {
                        try {
                            localStorage.setItem('sf_louvre_friends', JSON.stringify(this.friendsList));
                        } catch (e) {}
                    },

                    isFriend(playerId) {
                        return this.friendsList.some((f) => f && f.player_id === playerId);
                    },

                    isPeerOnline(playerId) {
                        if (!playerId || !Array.isArray(this.peers)) return false;
                        return this.peers.some((p) => p && (p.peer_id === playerId || p.player_id === playerId));
                    },

                    toggleFriend(playerId, displayName) {
                        if (!playerId) return;
                        if (this.isFriend(playerId)) {
                            this.friendsList = this.friendsList.filter((f) => f && f.player_id !== playerId);
                            this.saveFriendsList();
                            this.showFeedback((displayName || 'Spieler') + ' aus Freunden entfernt.');
                        } else {
                            this.friendsList.push({
                                player_id: playerId,
                                display_name: displayName || 'Mitspieler',
                                added_at: Date.now()
                            });
                            this.saveFriendsList();
                            this.showFeedback((displayName || 'Spieler') + ' zu Freunden hinzugefügt!');
                        }
                    },

                    waveToFriend(playerId, displayName) {
                        const facade = getFacade();
                        if (facade && typeof facade.triggerEmote === 'function') {
                            facade.triggerEmote('wave');
                        }
                        this.sendChatMessage('/wave');
                    },

                    syncAbilityState() {
                        const facade = getFacade();
                        if (facade && typeof facade.getPlayerHudState === 'function') {
                            const latestHud = facade.getPlayerHudState();
                            this.playerHud = Object.assign({}, this.playerHud, latestHud);
                        }
                        this.renderMinimap();
                    },

                    renderMinimap() {
                        const canvas = this.$refs.minimapCanvas;
                        const facade = getFacade();
                        if (!canvas || !facade || typeof facade.getMinimapState !== 'function') return;
                        const ctx = canvas.getContext('2d');
                        if (!ctx) return;
                        const snap = facade.getMinimapState();
                        if (!snap) return;

                        const w = canvas.width || 232;
                        const h = canvas.height || 232;
                        const scale = w / 180;
                        const cx = w * 0.5;
                        const cy = h * 0.5;
                        const rad = w * 0.5 - 8 * scale;

                        ctx.clearRect(0, 0, w, h);
                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(cx, cy, rad + 4 * scale, 0, Math.PI * 2);
                        ctx.clip();

                        ctx.fillStyle = '#FAF8F5';
                        ctx.fillRect(0, 0, w, h);

                        ctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
                        ctx.lineWidth = Math.max(1, 1.4 * scale);
                        ctx.beginPath();
                        ctx.arc(cx, cy, rad * 0.62, 0, Math.PI * 2);
                        ctx.stroke();

                        ctx.beginPath();
                        ctx.arc(cx, cy, rad * 0.30, 0, Math.PI * 2);
                        ctx.stroke();

                        const markers = Array.isArray(snap.markers) ? snap.markers : [];
                        for (let i = 0; i < markers.length; i++) {
                            const m = markers[i];
                            const mx = cx + (m.u - 0.5) * rad * 1.68;
                            const my = cy + (m.v - 0.5) * rad * 1.68;
                            ctx.beginPath();
                            if (m.type === 'portal') {
                                ctx.fillStyle = m.active ? '#C5A059' : '#9CA3AF';
                                ctx.arc(mx, my, 5.2 * scale, 0, Math.PI * 2);
                            } else {
                                ctx.fillStyle = m.active ? '#C5A059' : 'rgba(17, 24, 39, 0.36)';
                                ctx.arc(mx, my, (m.active ? 4.4 : 3.2) * scale, 0, Math.PI * 2);
                            }
                            ctx.fill();
                        }

                        if (snap.player) {
                            const px = cx + (snap.player.u - 0.5) * rad * 1.68;
                            const py = cy + (snap.player.v - 0.5) * rad * 1.68;
                            ctx.save();
                            ctx.translate(px, py);
                            ctx.rotate(-(snap.player.yaw || 0) + Math.PI);
                            ctx.fillStyle = '#111827';
                            ctx.beginPath();
                            ctx.moveTo(0, -8.5 * scale);
                            ctx.lineTo(6.0 * scale, 6.8 * scale);
                            ctx.lineTo(0, 3.6 * scale);
                            ctx.lineTo(-6.0 * scale, 6.8 * scale);
                            ctx.closePath();
                            ctx.fill();
                            ctx.restore();
                        }

                        ctx.restore();
                    },

                    mutePeer(playerId) {
                        if (!playerId || playerId === this.multiplayer.player_id || playerId === this.multiplayer.peer_id) return;
                        if (this.mutedPlayerIds.indexOf(playerId) === -1) {
                            this.mutedPlayerIds = this.mutedPlayerIds.concat([playerId]);
                            const facade = getFacade();
                            if (facade) {
                                facade.setMutedPlayers(this.mutedPlayerIds);
                            }
                            this.showFeedback('Spieler für diese Sitzung stummgeschaltet.');
                        }
                    },

                    reportPeer(playerId) {
                        if (!playerId || playerId === this.multiplayer.player_id || playerId === this.multiplayer.peer_id) return;
                        this.mutePeer(playerId);

                        fetch('/funkenwerk-welt/lobby/report', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken()
                            },
                            body: JSON.stringify({
                                target_peer_id: playerId,
                                target_player_id: playerId
                            })
                        })
                            .then((res) => res.json().catch(() => null))
                            .then((body) => {
                                if (body && (body.ok || body.success)) {
                                    this.showFeedback('Meldung übermittelt und Spieler stummgeschaltet.');
                                }
                            })
                            .catch(() => {});
                    },

                    triggerJump() {
                        const facade = getFacade();
                        if (facade) {
                            facade.triggerJump();
                        }
                    },

                    closeShowroom() {
                        if (!this.isOpen) {
                            if (typeof window !== 'undefined' && typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                window.__sfForceStopAllLobbyAudio();
                            }
                            return;
                        }
                        this.stopMultiplayerLoop();
                        this.stopAtelierPingLoop();
                        this._stopChatMuteCountdown();
                        this.pingTooltipOpen = false;
                        if (!this.soloMode && !this.multiplayerDisabled && this._activeSyncZone === 'heroes') {
                            this.sendLobbyLeaveBeacon();
                        }
                        this._activeSyncZone = null;
                        const activeFs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
                        if (activeFs) {
                            if (typeof document.exitFullscreen === 'function') {
                                Promise.resolve(document.exitFullscreen()).catch(() => {});
                            } else if (typeof document.webkitExitFullscreen === 'function') {
                                document.webkitExitFullscreen();
                            }
                        }
                        this.isFullscreen = false;
                        this.isOpen = false;
                        this.isLoading = false;
                        this.focusedProduct = null;
                        this.focusedHero = null;
                        this.productTransitionActive = false;
                        if (this._productTransitionTimer) {
                            clearTimeout(this._productTransitionTimer);
                            this._productTransitionTimer = null;
                        }
                        if (this._productFadeTimer) {
                            clearTimeout(this._productFadeTimer);
                            this._productFadeTimer = null;
                        }
                        this.instanceMenuOpen = false;
                        this.settingsOpen = false;
                        this.characterPickerOpen = false;
                        this.sanctumGateOpen = false;
                        this.outerGateModalOpen = false;
                        this.listeningKeyAction = '';
                        this.chatOpen = false;
                        if (privateAudio && typeof privateAudio.setLobbyActive === 'function') {
                            privateAudio.setLobbyActive(false);
                        }
                        if (typeof window !== 'undefined') {
                            if (window.__sfPrewarmLobbyBgmEl) {
                                try {
                                    window.__sfPrewarmLobbyBgmEl.pause();
                                    window.__sfPrewarmLobbyBgmEl.volume = 0;
                                    window.__sfPrewarmLobbyBgmEl.currentTime = 0;
                                } catch (e) {}
                                window.__sfPrewarmLobbyBgmEl = null;
                            }
                            if (typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                window.__sfForceStopAllLobbyAudio();
                            }
                        }
                        if (privateAudioUnsub) {
                            privateAudioUnsub();
                            privateAudioUnsub = null;
                        }
                        if (this._feedbackTimer) {
                            clearTimeout(this._feedbackTimer);
                            this._feedbackTimer = null;
                        }
                        if (this._fsResizeTimer) {
                            clearTimeout(this._fsResizeTimer);
                            this._fsResizeTimer = null;
                        }
                        if (this._abilityPollTimer) {
                            clearInterval(this._abilityPollTimer);
                            this._abilityPollTimer = null;
                        }
                        this.endStick();
                        const facade = getFacade();
                        if (facade) {
                            facade.destroy();
                            setFacade(null);
                        }
                        document.documentElement.style.overflow = this.prevHtmlOverflow;
                        document.body.style.overflow = this.prevOverflow;
                        if (this._loggedInDuringLobbySession) {
                            this._loggedInDuringLobbySession = false;
                            this.dispatchLivewireAuthSync();
                        }
                    },

                    handleEscape() {
                        if (!this.isOpen) return;
                        const facade = getFacade();
                        if (facade && typeof facade.releasePointerLock === 'function') {
                            facade.releasePointerLock();
                        }
                        if (this.listeningKeyAction !== '') {
                            this.listeningKeyAction = '';
                            return;
                        }
                        if (this.outerGateModalOpen) {
                            this.closeOuterGateModal();
                            return;
                        }
                        if (this.sanctumGateOpen) {
                            this.closeSanctumGateModal();
                            return;
                        }
                        if (this.characterPickerOpen) {
                            this.closeCharacterPicker();
                            return;
                        }
                        if (this.settingsOpen) {
                            this.closeSettingsModal();
                            return;
                        }
                        if (this.instanceMenuOpen) {
                            this.instanceMenuOpen = false;
                            return;
                        }
                        this.settingsOpen = true;
                        if (privateAudio) {
                            privateAudio.playUiClick();
                        }
                    },

                    activeExhibitName() {
                        const p = this.products[this.activeIndex];
                        return p ? p.name : '';
                    },

                    focusedBenefits() {
                        if (!this.focusedProduct || !Array.isArray(this.focusedProduct.benefits)) {
                            return [];
                        }
                        return this.focusedProduct.benefits.slice(0, 3);
                    },

                    focusedSummaryText() {
                        if (!this.focusedProduct) return '';
                        const stripDashes = (str) => String(str || '').replace(/\s[\u2013\u2014]\s/g, ' · ').trim();
                        const shortDesc = stripDashes(this.focusedProduct.short_description || '');
                        if (shortDesc !== '') {
                            return shortDesc;
                        }
                        if (Array.isArray(this.focusedProduct.benefits) && this.focusedProduct.benefits.length > 0) {
                            return this.focusedProduct.benefits
                                .slice(0, 2)
                                .map((b) => stripDashes(b))
                                .filter((b) => b !== '')
                                .join(' · ');
                        }
                        return '';
                    },

                    launchProductTransition(prod) {
                        const targetProd = prod || this.focusedProduct;
                        const targetUrl = (targetProd && targetProd.product_url) ? targetProd.product_url : '/shop';
                        if (this._productTransitionTimer) return;

                        const facade = getFacade();
                        if (facade && typeof facade.startExhibitTransition === 'function') {
                            facade.startExhibitTransition(this.activeIndex);
                        } else if (privateAudio && typeof privateAudio.playProductTransition === 'function') {
                            privateAudio.playProductTransition();
                        }

                        if (this._productFadeTimer) clearTimeout(this._productFadeTimer);
                        this._productFadeTimer = setTimeout(() => {
                            this.productTransitionActive = true;
                        }, 140);

                        this._productTransitionTimer = setTimeout(() => {
                            this._productTransitionTimer = null;
                            if (typeof window !== 'undefined') {
                                if (window.__sfPrewarmLobbyBgmEl) {
                                    try {
                                        window.__sfPrewarmLobbyBgmEl.pause();
                                        window.__sfPrewarmLobbyBgmEl.volume = 0;
                                        window.__sfPrewarmLobbyBgmEl.currentTime = 0;
                                    } catch (e) {}
                                    window.__sfPrewarmLobbyBgmEl = null;
                                }
                                if (typeof window.__sfForceStopAllLobbyAudio === 'function') {
                                    window.__sfForceStopAllLobbyAudio();
                                }
                            }
                            window.location.href = targetUrl;
                        }, 680);
                    },

                    focusedPriceLabel() {
                        if (!this.focusedProduct) return '';
                        const raw = String(this.focusedProduct.price_formatted || '').trim();
                        if (this.focusedProduct.is_personalizable && raw !== '' && !/^ab\s/i.test(raw)) {
                            return 'ab ' + raw;
                        }
                        return raw;
                    },

                    prevExhibit() {
                        const facade = getFacade();
                        if (!facade || this.products.length === 0) return;
                        facade.prevExhibit();
                    },

                    nextExhibit() {
                        const facade = getFacade();
                        if (!facade || this.products.length === 0) return;
                        facade.nextExhibit();
                    },

                    focusCurrentExhibit() {
                        const facade = getFacade();
                        if (!facade || this.products.length === 0) return;
                        facade.focusExhibit(this.activeIndex, false);
                    },

                    startStick(e) {
                        this.stickActive = true;
                        const zone = this.$refs.stickZone;
                        if (zone && zone.setPointerCapture) {
                            zone.setPointerCapture(e.pointerId);
                        }
                        this.moveStick(e);
                    },

                    moveStick(e) {
                        if (!this.stickActive || !this.$refs.stickZone) return;
                        const rect = this.$refs.stickZone.getBoundingClientRect();
                        const cx = rect.left + rect.width * 0.5;
                        const cy = rect.top + rect.height * 0.5;
                        const maxR = Math.max(16, rect.width * 0.34);
                        let dx = e.clientX - cx;
                        let dy = e.clientY - cy;
                        const dist = Math.hypot(dx, dy);
                        if (dist > maxR) {
                            dx = (dx / dist) * maxR;
                            dy = (dy / dist) * maxR;
                        }
                        this.stickUiX = Math.round(dx);
                        this.stickUiY = Math.round(dy);
                        const facade = getFacade();
                        if (facade) {
                            facade.setJoystickVector(dx / maxR, dy / maxR);
                        }
                    },

                    endStick() {
                        this.stickActive = false;
                        this.stickUiX = 0;
                        this.stickUiY = 0;
                        const facade = getFacade();
                        if (facade) {
                            facade.setJoystickVector(0, 0);
                        }
                    }
                };
            };
        })();
    </script>
</div>
