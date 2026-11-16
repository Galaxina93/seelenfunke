<div>
    @push('structured_data')
        @if(!empty($shopSchema))
            <script type="application/ld+json">{!! json_encode($shopSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
    @endpush
    <div class="bg-gray-50 min-h-screen pb-20">

        {{-- Header / Hero Section: Light-Mode Engineering Header auf Digital-Domain, sonst saisonaler Hauptshop-Header --}}
        @if(!empty($isDigitalDomain))
            <div class="bg-white border-b border-[#E6DAC3] py-14 md:py-18 text-center relative overflow-hidden">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="flex justify-end mb-4">
                        <div class="inline-flex items-center rounded-xl border border-[#D8C9A8] bg-[#FAF7F0] p-1 shadow-xs" role="group" aria-label="Language switcher">
                            <a href="{{ request()->fullUrlWithQuery(['lang' => 'de']) }}"
                               wire:click.prevent="setDigitalLocale('de')"
                               class="px-3 py-1 rounded-lg text-xs font-black tracking-wider transition-all {{ ($digitalLocale ?? 'de') === 'de' ? 'bg-[#C5A059] text-white shadow-xs' : 'text-gray-700 hover:text-gray-900' }}">
                                DE
                            </a>
                            <span class="px-1 text-xs font-bold text-[#C5A059]/60 select-none">|</span>
                            <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                               wire:click.prevent="setDigitalLocale('en')"
                               class="px-3 py-1 rounded-lg text-xs font-black tracking-wider transition-all {{ ($digitalLocale ?? 'de') === 'en' ? 'bg-[#C5A059] text-white shadow-xs' : 'text-gray-700 hover:text-gray-900' }}">
                                EN
                            </a>
                        </div>
                    </div>

                    @if(($digitalLocale ?? 'de') === 'en')
                        <span class="text-[#C5A059] font-bold uppercase tracking-[0.22em] text-xs mb-3 block">AI Architecture &amp; Autonomous Systems</span>
                        <h1 class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-4">
                            Autonomous AI Architecture Blueprints
                        </h1>
                        <p class="text-gray-600 max-w-2xl mx-auto text-base md:text-lg leading-relaxed">
                            Production-grade reference code, multi-agent orchestration engines, self-healing sentinels, and zero-secret vault brokers for instant deployment.
                        </p>
                    @else
                        <span class="text-[#C5A059] font-bold uppercase tracking-[0.22em] text-xs mb-3 block">AI Architecture &amp; Autonomous Systems</span>
                        <h1 class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-4">
                            Software- &amp; KI-Architektur-Blueprints
                        </h1>
                        <p class="text-gray-600 max-w-2xl mx-auto text-base md:text-lg leading-relaxed">
                            Produktionsreife Referenz-Systeme für autonome Selbstheilung, Multi-Agenten-Orchestrierung und Zero-Secret-Security als direkter Sofort-Download.
                        </p>
                    @endif
                </div>
            </div>
        @else
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
                @if($collectionWorld === 'atelier')
                    <span class="text-[#C5A059] font-bold uppercase tracking-[0.2em] text-xs mb-3 block drop-shadow">Atelier Gifhorn · 585er &amp; 333er Echtgold · 925er Silber</span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-serif font-bold text-white mb-5 drop-shadow-lg">
                        Atelier Echtgold &amp; Silber
                    </h1>
                    <p class="text-gray-200 max-w-2xl mx-auto text-lg leading-relaxed px-4 drop-shadow">
                        Meisterstücke aus echtem Gold und Sterling Silber. Jedes Schmuckstück wird mit weißen Handschuhen in unserer Manufaktur individuell für dich veredelt.
                    </p>
                @elseif($collectionWorld === 'voucher')
                    <span class="text-[var(--theme-color)] font-bold uppercase tracking-[0.2em] text-xs mb-3 block drop-shadow">Sofort-Freude &amp; Edel-Umschlag</span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-serif font-bold text-white mb-5 drop-shadow-lg">
                        Geschenkgutscheine
                    </h1>
                    <p class="text-gray-200 max-w-2xl mx-auto text-lg leading-relaxed px-4 drop-shadow">
                        Verschenke freie Auswahl in unserer Manufaktur, wahlweise als Sofort-PDF oder als lasergravierte Aluminiumkarte im Edel-Umschlag mit Wachssiegel.
                    </p>
                @else
                    <span class="text-[var(--theme-color)] font-bold uppercase tracking-[0.2em] text-xs mb-3 block drop-shadow">Handveredelt in Gifhorn</span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-serif font-bold text-white mb-5 drop-shadow-lg">
                        Unsere Kollektion
                    </h1>
                    <p class="text-gray-200 max-w-2xl mx-auto text-lg leading-relaxed px-4 drop-shadow">
                        Entdecke Unikate, die deine Seele berühren. Jedes Stück wird mit Liebe, modernster Technik und Sorgfalt für dich personalisiert.
                    </p>
                @endif

                <div class="mt-7 flex justify-center">
                    <button type="button"
                            data-testid="hero-open-3d-showroom-btn"
                            @click="$dispatch('open-louvre-showroom')"
                            class="inline-flex items-center justify-center gap-2.5 px-7 py-3.5 rounded-2xl bg-white hover:bg-[#FAF6EE] text-gray-900 border-2 border-[#C5A059] shadow-lg text-xs sm:text-sm font-bold uppercase tracking-widest transition-all cursor-pointer">
                        <svg class="w-4 h-4 text-[#C5A059] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l9 15H3L12 3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v15" />
                        </svg>
                        <span>3D shoppen</span>
                    </button>
                </div>
            </div>
        </div>
        @endif

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20">

            {{-- Filter & Search Bar --}}
            <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-4 md:p-5 mb-12">

                @if(empty($isDigitalDomain))
                {{-- Kopfleiste: 3-Welten-Kollektions-Weiche (volle Breite, 0 Subtexte) --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 p-1.5 bg-gray-50 rounded-2xl border border-gray-100 mb-4">
                        <button type="button"
                                wire:click="setCollectionWorld('manufaktur')"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all cursor-pointer {{ $collectionWorld === 'manufaktur' ? 'bg-[#F5F1E8] text-gray-900 border border-[#D8C9A8] shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-white/70 border border-transparent' }}">
                            <svg class="w-4 h-4 shrink-0 {{ $collectionWorld === 'manufaktur' ? 'text-gray-900' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            <span class="truncate">Manufaktur-Kollektion</span>
                            @if(($manufakturCount ?? 0) > 0)
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black leading-none {{ $collectionWorld === 'manufaktur' ? 'bg-white/90 text-gray-900 border border-[#D8C9A8]/60' : 'bg-gray-200/80 text-gray-600' }}">{{ $manufakturCount }}</span>
                            @endif
                        </button>

                        <button type="button"
                                wire:click="setCollectionWorld('atelier')"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all cursor-pointer {{ $collectionWorld === 'atelier' ? 'bg-[#F5F1E8] text-gray-900 border border-[#D8C9A8] shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-white/70 border border-transparent' }}">
                            <svg class="w-4 h-4 shrink-0 {{ $collectionWorld === 'atelier' ? 'text-gray-900' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                            <span class="truncate">Atelier Echtgold &amp; Silber</span>
                            @if(($atelierCount ?? 0) > 0)
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black leading-none {{ $collectionWorld === 'atelier' ? 'bg-white/90 text-gray-900 border border-[#D8C9A8]/60' : 'bg-gray-200/80 text-gray-600' }}">{{ $atelierCount }}</span>
                            @endif
                        </button>

                        <button type="button"
                                wire:click="setCollectionWorld('voucher')"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all cursor-pointer {{ $collectionWorld === 'voucher' ? 'bg-[#F5F1E8] text-gray-900 border border-[#D8C9A8] shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-white/70 border border-transparent' }}">
                            <svg class="w-4 h-4 shrink-0 {{ $collectionWorld === 'voucher' ? 'text-gray-900' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                            <span class="truncate">Geschenkgutscheine</span>
                            @if(($voucherCount ?? 0) > 0)
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black leading-none {{ $collectionWorld === 'voucher' ? 'bg-white/90 text-gray-900 border border-[#D8C9A8]/60' : 'bg-gray-200/80 text-gray-600' }}">{{ $voucherCount }}</span>
                            @endif
                        </button>
                </div>
                @endif
                
                {{-- Suchleiste Volle Breite Oben --}}
                <div class="w-full {{ empty($isDigitalDomain) ? 'mb-4' : '' }}">
                    <div class="relative w-full group">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ !empty($isDigitalDomain) ? (($digitalLocale ?? 'de') === 'en' ? 'Search architecture blueprints...' : 'Architektur-Blueprint suchen...') : 'Suche nach Name oder Eigenschaft...' }}" class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:bg-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all placeholder-gray-400">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-[var(--theme-color)] transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        @if($search !== '')
                            <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Untere Ebene: Pills & Dropdowns (Kompakt auf Desktop, damit nichts abschneidet) --}}
                @if(empty($isDigitalDomain) && $collectionWorld !== 'voucher')
                <div class="flex flex-col xl:flex-row gap-3 justify-between items-stretch xl:items-center">

                    {{-- Personalizable & Sale Pills --}}
                    <div class="flex flex-col lg:flex-row gap-2.5 w-full xl:w-auto shrink-0">
                        {{-- Personalizable Filter (Pills) --}}
                        <div class="flex bg-gray-50 p-1 rounded-xl border border-gray-100 w-full lg:w-auto overflow-x-auto no-scrollbar">
                            <button wire:click="$set('filterPersonalizable', 'all')" class="flex-1 lg:flex-none flex items-center justify-center px-3 py-2 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterPersonalizable === 'all' ? 'bg-white shadow-sm text-gray-900 border border-gray-200' : 'text-gray-500 hover:text-gray-700' }}">
                                Alle
                            </button>
                            <button wire:click="$set('filterPersonalizable', 'yes')" class="flex-1 lg:flex-none flex items-center justify-center px-3 py-2 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterPersonalizable === 'yes' ? 'bg-[var(--theme-color-10)] text-[var(--theme-color)] shadow-sm border border-[var(--theme-color-30)]' : 'text-gray-500 hover:text-[var(--theme-color)]' }}">
                                Personalisiert
                            </button>
                            @if(!empty($hasStandardProducts))
                                <button wire:click="$set('filterPersonalizable', 'no')" class="flex-1 lg:flex-none flex items-center justify-center px-3 py-2 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterPersonalizable === 'no' ? 'bg-gray-200 text-gray-800 shadow-sm border border-gray-300' : 'text-gray-500 hover:text-gray-800' }}">
                                    Standard
                                </button>
                            @endif
                        </div>

                        {{-- Angebote / Sale Filter Pill --}}
                        <div class="flex bg-gray-50 p-1 rounded-xl border border-gray-100 w-full lg:w-auto">
                            <button wire:click="toggleSaleFilter" class="w-full lg:w-auto flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterSale ? 'bg-red-500 text-white shadow-sm' : 'text-red-600 hover:bg-red-50' }}">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                <span>Angebote</span>
                                @if(($saleCount ?? 0) > 0)
                                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-black leading-none {{ $filterSale ? 'bg-white/20 text-white' : 'bg-red-100 text-red-600' }}">{{ $saleCount }}</span>
                                @endif
                            </button>
                        </div>
                    </div>

                    {{-- Dropdowns Container --}}
                    @if(!empty($availableHolidays) || $categories->isNotEmpty())
                        <div class="flex flex-col sm:flex-row gap-2.5 w-full xl:w-auto flex-1 min-w-0 justify-end">
                            {{-- Anlass Dropdown --}}
                            @if(!empty($availableHolidays))
                                <div class="relative w-full {{ $categories->isNotEmpty() ? 'sm:w-1/2 xl:w-[175px]' : 'sm:w-full xl:w-[200px]' }}">
                                    <select wire:model.live="filterHoliday" class="w-full h-[42px] pl-3.5 pr-9 py-0 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold text-gray-700 focus:bg-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all cursor-pointer appearance-none truncate">
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

                            {{-- Kategorie Dropdown --}}
                            @if($categories->isNotEmpty())
                                <div class="relative w-full {{ !empty($availableHolidays) ? 'sm:w-1/2 xl:w-[185px]' : 'sm:w-full xl:w-[200px]' }}">
                                    <select wire:model.live="filterCategory" class="w-full h-[42px] pl-3.5 pr-9 py-0 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold text-gray-700 focus:bg-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all cursor-pointer appearance-none truncate">
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
                @endif

                {{-- Aktive Filter anzeigen (Nur wenn etwas ausgewählt ist) --}}
                @if($search !== '' || $filterPersonalizable !== 'all' || $filterSale || $filterCategory !== '' || $filterHoliday !== '')
                    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-3 animate-fade-in">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mr-2">Aktive Filter:</span>

                        @if($search !== '')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">
                                "{{ $search }}"
                                <button wire:click="$set('search', '')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterSale)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 text-red-600 text-xs font-bold uppercase tracking-wider border border-red-200">
                                Nur Angebote
                                <button wire:click="$set('filterSale', false)" class="hover:text-red-800"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterPersonalizable !== 'all')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 text-gray-800 text-xs font-bold uppercase tracking-wider border border-gray-200">
                                {{ $filterPersonalizable === 'yes' ? 'Personalisierbar' : 'Standard-Artikel' }}
                                <button wire:click="$set('filterPersonalizable', 'all')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterHoliday !== '')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-pink-50 text-pink-600 text-xs font-bold uppercase tracking-wider border border-pink-100">
                                Anlass: {{ $availableHolidays[$filterHoliday] ?? ucfirst($filterHoliday) }}
                                <button wire:click="$set('filterHoliday', '')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterCategory !== '')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#F5F1E8] text-gray-900 border border-[#D8C9A8] text-xs font-bold">
                                Kategorie: {{ $categories->firstWhere('id', $filterCategory)->name ?? \App\Models\Product\ProductCategory::find($filterCategory)?->name ?? 'Unbekannt' }}
                                <button wire:click="$set('filterCategory', '')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        <button wire:click="resetFilters" class="text-xs font-bold text-red-500 hover:underline ml-auto">Alle Filter löschen</button>
                    </div>
                @endif
            </div>

            {{-- Produkt Grid --}}
            <div wire:loading.class="opacity-50 blur-sm pointer-events-none"
                 wire:target="search, collectionWorld, setCollectionWorld, filterType, filterPersonalizable, filterSale, toggleSaleFilter, filterCategory, filterHoliday, resetFilters, gotoPage, nextPage, previousPage"
                 class="transition-all duration-300">
                @if($products->isEmpty())
                    @if($collectionWorld === 'atelier' && $search === '' && !$filterSale && $filterCategory === '' && $filterHoliday === '')
                        <div class="text-center py-16 px-6 bg-white rounded-3xl border border-[#C5A059]/30 shadow-sm animate-fade-in-up max-w-3xl mx-auto">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[#C5A059]/10 text-[#C5A059] mb-5 border border-[#C5A059]/25">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                            </div>
                            <span class="text-[#C5A059] font-bold uppercase tracking-[0.2em] text-[11px] mb-2 block">Exklusive Einzelanfertigung · 585er Echtgold &amp; 925er Silber</span>
                            <h3 class="text-2xl font-serif font-bold text-gray-900 mb-3">Unser Echtgold- &amp; Silber-Atelier wird vorbereitet</h3>
                            <p class="text-gray-600 mb-6 max-w-xl mx-auto text-sm leading-relaxed">
                                Unsere neuen Meisterstücke aus massivem 585er und 333er Echtgold sowie 925er Sterling Silber durchlaufen aktuell die Original-Fotoproduktion in unserer Manufaktur in Gifhorn und sind in Kürze hier konfigurierbar.
                            </p>
                            <div class="flex flex-wrap items-center justify-center gap-4 text-xs font-bold text-gray-700 mb-7">
                                <span class="inline-flex items-center gap-1.5"><span class="text-[#C5A059]">✓</span> 585er &amp; 333er Echtgold</span>
                                <span class="inline-flex items-center gap-1.5"><span class="text-[#C5A059]">✓</span> Inkl. Westpack-Schmuckbox</span>
                                <span class="inline-flex items-center gap-1.5"><span class="text-[#C5A059]">✓</span> Handveredelt in Gifhorn</span>
                            </div>
                            <button type="button" wire:click="setCollectionWorld('manufaktur')" class="px-6 py-3 bg-[#F5F1E8] text-gray-900 border border-[#D8C9A8] rounded-full font-bold text-xs uppercase tracking-wider shadow-sm hover:bg-[#EDE6D6] transition-all cursor-pointer">
                                Zur Manufaktur-Kollektion
                            </button>
                        </div>
                    @else
                        <div class="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm animate-fade-in-up">
                            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 text-gray-300 mb-6">
                                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                            <h3 class="text-xl font-serif font-bold text-gray-900 mb-2">Keine Treffer gefunden</h3>
                            <p class="text-gray-500 mb-6 max-w-md mx-auto">Es tut uns leid, aber zu deiner Auswahl haben wir aktuell keine passenden Produkte in der Kollektion.</p>
                            <button wire:click="resetFilters" class="px-6 py-2.5 bg-gray-900 text-white rounded-full font-bold text-sm shadow-md hover:bg-black transition-all">Filter zurücksetzen</button>
                        </div>
                    @endif
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

                                    @php
                                        $isWishlisted = in_array((string) $product->id, $wishlistProductIds ?? [], true);
                                    @endphp
                                    <button type="button"
                                            x-data="{ wishlisted: @js($isWishlisted) }"
                                            @click.prevent="wishlisted = !wishlisted; if (window.sfTriggerWishlistHeartEffect) window.sfTriggerWishlistHeartEffect($event, wishlisted); $wire.toggleWishlist('{{ $product->id }}')"
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
                                            @if($product->isAtelierLine())
                                                <div class="bg-[#F5F1E8]/95 backdrop-blur-sm text-gray-900 border border-[#D8C9A8] text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                                    <svg class="w-3 h-3 text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
                                                    Atelier
                                                </div>
                                            @endif
                                            @if($product->type === 'digital')
                                                <div class="bg-[#F5F1E8]/95 backdrop-blur-sm text-gray-900 border border-[#D8C9A8] text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                                    <svg class="w-3 h-3 text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" /></svg>
                                                    {{ !empty($isDigitalDomain) ? (($digitalLocale ?? 'de') === 'en' ? 'ZIP Blueprint' : 'ZIP-Paket') : 'Gutschein' }}
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

                                    {{-- Kategorien & Herkunfts-Siegel des Produkts --}}
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

                                    @php
                                        $cardTitle = (!empty($isDigitalDomain) && ($digitalLocale ?? 'de') === 'en' && !empty($product->attributes['name_en']))
                                            ? $product->attributes['name_en']
                                            : $product->name;
                                    @endphp
                                    <h3 class="text-lg font-serif font-bold {{ $isSoldOut ? 'text-gray-400' : 'text-gray-900 group-hover:text-[var(--theme-color)] transition-colors' }} mb-1.5 leading-tight line-clamp-2">
                                        <a href="{{ $productUrl }}">
                                            {{ $cardTitle }}
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
                                                @if(!empty($isDigitalDomain) || $product->type === 'digital')
                                                    @if(shop_setting('is_small_business', false))
                                                        {{ ($digitalLocale ?? 'de') === 'en' ? 'No VAT (§ 19 UStG) · Instant Download' : 'Keine MwSt. gem. § 19 UStG · Sofort-Download' }}
                                                    @else
                                                        {{ ($digitalLocale ?? 'de') === 'en' ? 'incl. VAT · Instant Download' : (($product->tax_included ? 'inkl. MwSt.' : 'zzgl. MwSt.') . ' · Sofort-Download') }}
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

    @if(empty($isDigitalDomain))
    {{-- Vereinte 3D-User-Lobby: Louvre-Produktgalerie + Goldenes Ehrenportal + Halle der Helden (0 KB Initial-Last, 0 Tailwind-Abhaengigkeit) --}}
    <div id="sf-louvre-showroom-host"
         wire:ignore
         x-data="window.sfLouvreShowroomModal()"
         x-init="initLobbyListeners()"
         @open-louvre-showroom.window="openShowroom(($event.detail && $event.detail.zone) ? $event.detail.zone : 'gallery')">

        <style id="sf-3d-lobby-scoped-css">
            [x-cloak] { display: none !important; }
            @keyframes sfLobbySpin { to { transform: rotate(360deg); } }
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
                user-select: none !important;
                -webkit-user-select: none !important;
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
                padding: 14px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                flex-wrap: wrap;
                pointer-events: none;
                z-index: 30;
                box-sizing: border-box;
            }
            .sf-lobby-nav-pill {
                pointer-events: auto;
                background: rgba(255, 255, 255, 0.94);
                border: 1px solid rgba(197, 160, 89, 0.42);
                border-radius: 999px;
                padding: 5px;
                box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
                display: inline-flex;
                align-items: center;
                gap: 4px;
                flex-wrap: wrap;
                max-width: 100%;
                box-sizing: border-box;
            }
            .sf-lobby-zone-btn {
                appearance: none;
                border: none;
                border-radius: 999px;
                padding: 7px 14px;
                font-size: 12px;
                font-weight: 800;
                line-height: 1.2;
                background: transparent;
                color: #111827;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                white-space: nowrap;
                transition: background-color 150ms ease, color 150ms ease;
            }
            .sf-lobby-zone-btn:hover { background: rgba(197, 160, 89, 0.14); }
            .sf-lobby-zone-btn.is-active { background: #C5A059 !important; color: #FFFFFF !important; }
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
                gap: 8px;
                flex-wrap: wrap;
            }
            .sf-lobby-pill-btn {
                appearance: none;
                background: rgba(255, 255, 255, 0.94);
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.48);
                border-radius: 999px;
                padding: 7px 13px;
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
                width: 276px;
                max-width: calc(100vw - 24px);
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(197, 160, 89, 0.55);
                border-radius: 16px;
                padding: 14px 16px;
                box-shadow: 0 14px 34px rgba(17, 24, 39, 0.12);
                color: #111827;
                pointer-events: auto;
                box-sizing: border-box;
            }
            .sf-lobby-floating-arrow {
                width: 12px;
                height: 12px;
                background: rgba(255, 255, 255, 0.96);
                border-right: 1px solid rgba(197, 160, 89, 0.55);
                border-bottom: 1px solid rgba(197, 160, 89, 0.55);
                transform: rotate(45deg);
                margin: -6px auto 0;
                pointer-events: none;
            }
            .sf-lobby-prod-head { display: flex; align-items: flex-start; gap: 10px; }
            .sf-lobby-prod-thumb {
                width: 46px;
                height: 46px;
                border-radius: 12px;
                object-fit: cover;
                background: #FAF8F5;
                border: 1px solid rgba(197, 160, 89, 0.35);
                flex-shrink: 0;
            }
            .sf-lobby-prod-cat {
                display: block;
                font-size: 10px;
                font-weight: 800;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #9A7738;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .sf-lobby-prod-title {
                margin: 2px 0 0;
                font-family: Georgia, "Times New Roman", serif;
                font-size: 15px;
                font-weight: 700;
                line-height: 1.28;
                color: #111827;
            }
            .sf-lobby-prod-price { display: inline-block; margin-top: 3px; font-size: 13px; font-weight: 800; color: #111827; }
            .sf-lobby-prod-benefits {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid rgba(197, 160, 89, 0.25);
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .sf-lobby-prod-benefit-row {
                display: flex;
                align-items: flex-start;
                gap: 6px;
                font-size: 11px;
                font-weight: 700;
                line-height: 1.3;
                color: #111827;
            }
            .sf-lobby-prod-check { color: #C5A059; font-weight: 900; flex-shrink: 0; }
            .sf-lobby-prod-cta {
                margin-top: 11px;
                width: 100%;
                padding: 9px 14px;
                border-radius: 12px;
                background: #C5A059;
                color: #FFFFFF;
                font-size: 12px;
                font-weight: 800;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                box-sizing: border-box;
                transition: background-color 150ms ease;
            }
            .sf-lobby-prod-cta:hover { background: #B38E46; }
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
                background: rgba(255, 255, 255, 0.96);
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
                bottom: 84px;
                right: 20px;
                z-index: 24;
                width: 304px;
                max-width: calc(100vw - 24px);
                background: rgba(255, 255, 255, 0.96);
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
            .sf-lobby-chat-panel.is-expanded {
                width: 440px;
            }
            .sf-lobby-chat-panel.is-expanded .sf-lobby-chat-scroll {
                max-height: 280px;
            }
            .sf-lobby-friends-scroll {
                max-height: 156px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 5px;
                padding-right: 2px;
            }
            .sf-lobby-chat-panel.is-expanded .sf-lobby-friends-scroll {
                max-height: 280px;
            }
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
            .sf-lobby-abilities-dock {
                position: absolute;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%);
                display: flex;
                align-items: center;
                gap: 8px;
                z-index: 23;
                pointer-events: auto;
            }
            .sf-ability-btn {
                position: relative;
                width: 44px;
                height: 44px;
                border-radius: 12px;
                background: rgba(11, 14, 20, 0.90);
                border: 1px solid rgba(197, 160, 89, 0.45);
                color: #FAF8F5;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.28);
                transition: transform 0.12s ease, border-color 0.15s ease, background 0.15s ease;
                user-select: none;
            }
            .sf-ability-btn:hover {
                border-color: #C5A059;
                transform: translateY(-2px);
                background: rgba(18, 22, 32, 0.96);
            }
            .sf-ability-btn.is-active {
                border-color: #D4AF37;
                box-shadow: 0 0 14px rgba(197, 160, 89, 0.65);
                background: rgba(197, 160, 89, 0.25);
            }
            .sf-ability-btn.is-cooldown {
                opacity: 0.58;
                border-color: rgba(148, 163, 184, 0.3);
            }
            .sf-ability-icon {
                font-size: 15px;
                line-height: 1;
                color: #C5A059;
            }
            .sf-ability-key {
                position: absolute;
                top: 2px;
                left: 4px;
                font-size: 9px;
                font-weight: 800;
                color: rgba(255, 255, 255, 0.45);
                line-height: 1;
            }
            .sf-ability-cd {
                position: absolute;
                bottom: 2px;
                right: 3px;
                font-size: 9px;
                font-weight: 800;
                color: #FFFFFF;
                background: rgba(0, 0, 0, 0.75);
                padding: 1px 3px;
                border-radius: 4px;
                line-height: 1;
            }
            .sf-lobby-stick-wrap { position: absolute; bottom: 22px; left: 20px; z-index: 22; }
            .sf-lobby-stick {
                width: 84px;
                height: 84px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.88);
                border: 1px solid rgba(197, 160, 89, 0.52);
                box-shadow: 0 10px 26px rgba(17, 24, 39, 0.12);
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                touch-action: none;
                cursor: pointer;
            }
            .sf-lobby-stick-knob {
                width: 34px;
                height: 34px;
                border-radius: 999px;
                background: #C5A059;
                border: 2px solid #FFFFFF;
                box-shadow: 0 4px 12px rgba(17, 24, 39, 0.18);
                pointer-events: none;
            }
            .sf-lobby-quick-actions {
                position: absolute;
                bottom: 22px;
                right: 20px;
                z-index: 22;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .sf-lobby-icon-btn {
                appearance: none;
                height: 44px;
                min-width: 44px;
                padding: 0 12px;
                border-radius: 14px;
                background: rgba(255, 255, 255, 0.94);
                color: #111827;
                border: 1px solid rgba(197, 160, 89, 0.55);
                box-shadow: 0 10px 24px rgba(17, 24, 39, 0.10);
                font-size: 13px;
                font-weight: 800;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                cursor: pointer;
            }
            .sf-lobby-icon-btn:hover { background: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-bottom-stack {
                position: absolute;
                bottom: 14px;
                left: 12px;
                right: 12px;
                z-index: 21;
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
                background: rgba(255, 255, 255, 0.94);
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
            .sf-lobby-exhibit-btn {
                appearance: none;
                padding: 6px 14px;
                border-radius: 999px;
                border: 1px solid rgba(197, 160, 89, 0.35);
                background: #FFFFFF;
                color: #111827;
                font-size: 11px;
                font-weight: 800;
                cursor: pointer;
                max-width: 260px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .sf-lobby-hint-pill {
                pointer-events: none;
                padding: 5px 14px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.90);
                border: 1px solid rgba(197, 160, 89, 0.38);
                color: #374151;
                font-size: 11px;
                font-weight: 700;
                box-shadow: 0 6px 18px rgba(17, 24, 39, 0.08);
                white-space: nowrap;
            }
            @media (max-width: 767px) {
                .sf-lobby-topbar { padding: 10px 12px; gap: 8px; }
                .sf-lobby-zone-btn { padding: 6px 10px; font-size: 11px; }
                .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 6px 10px; font-size: 11px; }
                .sf-lobby-kbd, .sf-lobby-hint-pill, .sf-lobby-hide-sm { display: none !important; }
                .sf-lobby-stick-wrap { bottom: 72px; left: 12px; }
                .sf-lobby-stick { width: 74px; height: 74px; }
                .sf-lobby-quick-actions { bottom: 72px; right: 12px; }
                .sf-lobby-chat-panel { bottom: 132px; right: 12px; }
                .sf-lobby-chat-panel.is-expanded { width: calc(100vw - 24px); }
                .sf-lobby-abilities-dock { bottom: 78px; gap: 6px; }
                .sf-ability-btn { width: 38px; height: 38px; border-radius: 10px; }
                .sf-lobby-exhibit-btn { max-width: 175px; }
            }
        </style>

        <template x-teleport="body">
            <div id="sf-louvre-showroom-modal"
                 x-ref="lobbyModalRoot"
                 x-show="isOpen"
                 x-cloak
                 role="dialog"
                 aria-modal="true"
                 aria-label="MEIN SEELENFUNKE · LOUVRE SHOWROOM"
                 :class="isOpen ? 'sf-lobby-modal is-open' : 'sf-lobby-modal is-closed'"
                 style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100dvh !important; z-index: 2147483000 !important; background: #FAF8F5 !important; overflow: hidden !important; margin: 0 !important; padding: 0 !important;">

                {{-- Edle schwebende Light-Mode HUD-Leiste --}}
                <div class="sf-lobby-topbar">
                    <div class="sf-lobby-nav-pill">
                        <button type="button"
                                data-testid="lobby-zone-gallery-btn"
                                @click="switchZone('gallery')"
                                :class="currentZone === 'gallery' ? 'sf-lobby-zone-btn is-active' : 'sf-lobby-zone-btn'">
                            <span>🛍️ Kollektion</span>
                        </button>
                        <button type="button"
                                data-testid="lobby-zone-heroes-btn"
                                @click="switchZone('heroes')"
                                :class="currentZone === 'heroes' ? 'sf-lobby-zone-btn is-active' : 'sf-lobby-zone-btn'">
                            <span x-show="!isCustomerAuthenticated()">🔒</span>
                            <span>🏛️ Halle der Helden</span>
                        </button>
                        <button type="button"
                                x-show="currentZone === 'heroes' && isCustomerAuthenticated()"
                                data-testid="lobby-stages-preview-btn"
                                @click="cycleStagesPreview()"
                                class="sf-lobby-stages-btn">
                            <span x-text="stagesPreviewLabel()"></span>
                        </button>
                    </div>

                    <div class="sf-lobby-actions">
                        <span x-show="isCustomerAuthenticated() && !soloMode && focusedProduct !== null"
                              class="sf-lobby-badge sf-lobby-hide-sm">
                            <span style="color:#C5A059;">&#10003;</span>
                            <span>Fokus-Schutz</span>
                        </span>

                        <button type="button"
                                x-show="isCustomerAuthenticated()"
                                data-testid="louvre-live-solo-toggle"
                                @click="toggleSoloMode()"
                                class="sf-lobby-pill-btn"
                                :title="soloMode ? 'Multiplayer aktivieren' : 'Ungestört alleine umschauen (Solo)'">
                            <span class="sf-lobby-dot" :class="soloMode ? 'is-solo' : 'is-live'"></span>
                            <span x-text="soloMode ? 'Solo' : 'Live'"></span>
                        </button>

                        {{-- Manueller Multiplayer-Instanzen-Waehler (Light-Mode Clean-Design, 0 Subtexte) --}}
                        <div x-show="isCustomerAuthenticated() && !soloMode"
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
                                data-testid="lobby-fullscreen-btn"
                                @click="toggleFullscreen()"
                                :class="isFullscreen ? 'sf-lobby-pill-btn is-gold' : 'sf-lobby-pill-btn'"
                                title="Vollbild umschalten (Taste F)">
                            <span x-text="isFullscreen ? '🗗 Teilbild' : '⛶ Vollbild'"></span>
                        </button>

                        <button type="button"
                                data-testid="lobby-close-btn"
                                @click="closeShowroom()"
                                class="sf-lobby-close-btn"
                                title="Zurück zum Shop (Esc)">
                            <span>✕ Schließen</span>
                            <span class="sf-lobby-kbd">Esc</span>
                        </button>
                    </div>
                </div>

                {{-- 3D-User-Lobby Buehne + Schwebende Light-Mode Raum-Tafeln (0 Popups, 0 Tailwind-Abhaengigkeit) --}}
                <div class="sf-lobby-stage">
                    <canvas x-ref="louvreCanvas" class="sf-lobby-canvas"></canvas>

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

                    {{-- Schwebendes World-Space Produkt-Schild (Bereich 1: Kollektion, 100 % Light-Mode) --}}
                    <div x-ref="floatingCard"
                         data-testid="louvre-floating-space-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <div @pointerdown.stop class="sf-lobby-floating-card">
                            <div class="sf-lobby-prod-head">
                                <template x-if="focusedProduct && focusedProduct.preview_image_url">
                                    <img :src="focusedProduct.preview_image_url"
                                         :alt="focusedProduct ? focusedProduct.name : ''"
                                         class="sf-lobby-prod-thumb">
                                </template>
                                <div style="min-width:0;flex:1;">
                                    <span class="sf-lobby-prod-cat"
                                          x-text="focusedProduct ? (focusedProduct.category_name || 'Kollektion') : ''"></span>
                                    <h3 class="sf-lobby-prod-title"
                                        x-text="focusedProduct ? focusedProduct.name : ''"></h3>
                                    <span class="sf-lobby-prod-price"
                                          x-text="focusedPriceLabel()"></span>
                                </div>
                            </div>

                            <div x-show="focusedBenefits().length > 0" class="sf-lobby-prod-benefits">
                                <template x-for="(benefit, bIdx) in focusedBenefits()" :key="bIdx">
                                    <div class="sf-lobby-prod-benefit-row">
                                        <span class="sf-lobby-prod-check">&#10003;</span>
                                        <span x-text="benefit"></span>
                                    </div>
                                </template>
                            </div>

                            <a :href="focusedProduct ? focusedProduct.product_url : '#'" class="sf-lobby-prod-cta">
                                <span x-text="focusedProduct && focusedProduct.is_personalizable ? 'Jetzt gestalten' : 'Zum Produkt'"></span>
                                <span>&rarr;</span>
                            </a>
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

                    {{-- Schwebendes Light-Mode Schild am goldenen Ehrenportal fuer nicht eingeloggte Gaeste --}}
                    <div x-ref="floatingPortalCard"
                         data-testid="lobby-guest-portal-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <a href="/login" @pointerdown.stop class="sf-lobby-portal-pill">
                            <span>🔒</span>
                            <span>Mitglieder-Lobby · Anmelden</span>
                            <span>&rarr;</span>
                        </a>
                    </div>

                    {{-- Ein-/ausklappbarer Light-Mode Raum-Chat --}}
                    <div x-show="!isLoading && loadError === '' && chatOpen && focusedProduct === null"
                         @pointerdown.stop
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
                                                <div x-show="msg.player_id !== multiplayer.player_id" style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
                                                    <button type="button"
                                                            @click="toggleFriend(msg.player_id, msg.display_name)"
                                                            style="appearance:none;border:none;background:transparent;font-size:10px;font-weight:800;cursor:pointer;padding:0 3px;"
                                                            :style="isFriend(msg.player_id) ? 'color:#C5A059;' : 'color:#4B5563;'"
                                                            :title="isFriend(msg.player_id) ? 'Freund entfernen' : 'Als Freund hinzufügen'"
                                                            x-text="isFriend(msg.player_id) ? '★ Freund' : '+ Freund'"></button>
                                                    <button type="button"
                                                            @click="mutePeer(msg.player_id)"
                                                            style="appearance:none;border:none;background:transparent;font-size:10px;font-weight:800;color:#6B7280;cursor:pointer;padding:0 3px;"
                                                            title="Spieler lokal stummschalten">Stumm</button>
                                                    <button type="button"
                                                            @click="reportPeer(msg.player_id)"
                                                            style="appearance:none;border:none;background:transparent;font-size:10px;font-weight:800;color:#DC2626;cursor:pointer;padding:0 3px;"
                                                            title="Unangemessene Nachricht melden">Melden</button>
                                                </div>
                                            </div>
                                            <p style="margin:3px 0 0;font-size:11px;color:#1F2937;line-height:1.35;word-break:break-word;" x-text="msg.text"></p>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="chatFeedback !== ''"
                                     style="padding:6px 9px;border-radius:10px;background:#FAF8F5;border:1px solid rgba(197,160,89,0.45);font-size:11px;font-weight:700;color:#111827;"
                                     x-text="chatFeedback"></div>

                                {{-- Schnellwahl für Animationen/Slash-Befehle --}}
                                <div style="display:flex;align-items:center;gap:4px;overflow-x:auto;padding-bottom:2px;">
                                    <button type="button" @click="sendChatMessage('/dance')" class="sf-emote-chip" title="Tanzen (/dance)">💃 /dance</button>
                                    <button type="button" @click="sendChatMessage('/wave')" class="sf-emote-chip" title="Winken (/wave)">👋 /wave</button>
                                    <button type="button" @click="sendChatMessage('/bow')" class="sf-emote-chip" title="Verbeugen (/bow)">🙇 /bow</button>
                                    <button type="button" @click="sendChatMessage('/cheer')" class="sf-emote-chip" title="Jubeln (/cheer)">🙌 /cheer</button>
                                    <button type="button" @click="sendChatMessage('/sit')" class="sf-emote-chip" title="Hinsetzen (/sit)">🧘 /sit</button>
                                    <button type="button" @click="sendChatMessage('/clap')" class="sf-emote-chip" title="Klatschen (/clap)">👏 /clap</button>
                                </div>

                                <form @submit.prevent="sendChatMessage()" style="display:flex;align-items:center;gap:6px;padding-top:2px;">
                                    <input type="text"
                                           x-model="chatDraft"
                                           maxlength="100"
                                           placeholder="Nachricht oder /dance..."
                                           style="flex:1;min-width:0;padding:7px 10px;border-radius:10px;background:#FAF8F5;border:1px solid rgba(197,160,89,0.5);font-size:12px;font-weight:600;color:#111827;outline:none;">
                                    <button type="submit"
                                            :disabled="chatSending"
                                            style="appearance:none;padding:7px 12px;border-radius:10px;border:none;background:#C5A059;color:#FFFFFF;font-size:12px;font-weight:800;cursor:pointer;flex-shrink:0;">
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

                    {{-- Kompakte Action-Leiste unten rechts --}}
                    <div x-show="!isLoading && loadError === ''"
                         @pointerdown.stop
                         class="sf-lobby-quick-actions">
                        <button type="button"
                                data-testid="louvre-jump-btn"
                                @click="triggerJump()"
                                class="sf-lobby-icon-btn"
                                title="Springen (Leertaste)"
                                aria-label="Springen">
                            &#10514;
                        </button>

                        <button type="button"
                                data-testid="louvre-weapon-btn"
                                @click="triggerWeapon()"
                                class="sf-lobby-icon-btn"
                                style="color:#9A7738;"
                                :title="funkiInfo.weapon ? (funkiInfo.weapon.name + ' aktivieren (Rechtsklick / Taste G)') : 'Aktion ausführen (Rechtsklick / Taste G)'"
                                aria-label="Waffen-Effekt">
                            &#10022;
                        </button>

                        <button type="button"
                                data-testid="louvre-chat-toggle-btn"
                                @click="toggleChatPanel()"
                                class="sf-lobby-icon-btn"
                                title="Raum-Chat öffnen"
                                aria-label="Raum-Chat">
                            <span>Chat</span>
                            <span x-show="unreadChatCount > 0 && !chatOpen"
                                  style="padding:2px 6px;border-radius:999px;background:#C5A059;color:#FFFFFF;font-size:10px;font-weight:900;line-height:1;"
                                  x-text="unreadChatCount"></span>
                        </button>
                    </div>

                    {{-- Spezialfähigkeiten Schnellwahl-Dock (Tasten 1-4) --}}
                    <div x-show="!isLoading && loadError === '' && focusedProduct === null"
                         @pointerdown.stop
                         data-testid="louvre-abilities-dock"
                         class="sf-lobby-abilities-dock">
                        <button type="button"
                                @click="triggerAbility(1)"
                                data-testid="ability-slot-1-btn"
                                class="sf-ability-btn"
                                :class="{ 'is-active': abilityState.sprint.active, 'is-cooldown': abilityState.sprint.cooldown > 0 }"
                                title="Funken-Sprint (Taste 1): +50% Tempo">
                            <span class="sf-ability-icon">⚡</span>
                            <span class="sf-ability-key">1</span>
                            <span x-show="abilityState.sprint.cooldown > 0" class="sf-ability-cd" x-text="Math.ceil(abilityState.sprint.cooldown) + 's'"></span>
                        </button>
                        <button type="button"
                                @click="triggerAbility(2)"
                                data-testid="ability-slot-2-btn"
                                class="sf-ability-btn"
                                :class="{ 'is-cooldown': abilityState.titanJump.cooldown > 0 }"
                                title="Titan-Sprung (Taste 2): 2.5x Sprunghöhe">
                            <span class="sf-ability-icon">▲</span>
                            <span class="sf-ability-key">2</span>
                            <span x-show="abilityState.titanJump.cooldown > 0" class="sf-ability-cd" x-text="Math.ceil(abilityState.titanJump.cooldown) + 's'"></span>
                        </button>
                        <button type="button"
                                @click="triggerAbility(3)"
                                data-testid="ability-slot-3-btn"
                                class="sf-ability-btn"
                                :class="{ 'is-active': abilityState.shield.active, 'is-cooldown': abilityState.shield.cooldown > 0 }"
                                title="Goldener Schutzschild (Taste 3): 6s Schutz">
                            <span class="sf-ability-icon">🛡</span>
                            <span class="sf-ability-key">3</span>
                            <span x-show="abilityState.shield.cooldown > 0" class="sf-ability-cd" x-text="Math.ceil(abilityState.shield.cooldown) + 's'"></span>
                        </button>
                        <button type="button"
                                @click="triggerAbility(4)"
                                data-testid="ability-slot-4-btn"
                                class="sf-ability-btn"
                                :class="{ 'is-cooldown': abilityState.burst.cooldown > 0 }"
                                title="Radialer Funkenstoß (Taste 4): Druckwelle 3.8m">
                            <span class="sf-ability-icon">✦</span>
                            <span class="sf-ability-key">4</span>
                            <span x-show="abilityState.burst.cooldown > 0" class="sf-ability-cd" x-text="Math.ceil(abilityState.burst.cooldown) + 's'"></span>
                        </button>
                    </div>

                    {{-- Untere Navigation + dezente Light-Mode Steuerungs-Pill --}}
                    <div x-show="!isLoading && loadError === ''" class="sf-lobby-bottom-stack">
                        <div x-show="currentZone === 'gallery' && products.length > 0" class="sf-lobby-bottom-pill">
                            <button type="button" @click="prevExhibit()" class="sf-lobby-step-btn">
                                &lsaquo; <span class="sf-lobby-hide-sm">Vorheriges</span>
                            </button>

                            <button type="button"
                                    @click="focusCurrentExhibit()"
                                    class="sf-lobby-exhibit-btn"
                                    title="Zum Podest gehen">
                                <span x-text="'Exponat ' + (activeIndex + 1) + '/' + products.length"></span>
                                <span class="sf-lobby-hide-sm" x-show="activeExhibitName() !== ''" x-text="' · ' + activeExhibitName()"></span>
                            </button>

                            <button type="button" @click="nextExhibit()" class="sf-lobby-step-btn">
                                <span class="sf-lobby-hide-sm">Nächstes</span> &rsaquo;
                            </button>
                        </div>

                        <div x-show="currentZone === 'heroes'" class="sf-lobby-bottom-pill">
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

                        <div class="sf-lobby-hint-pill">
                            WASD / Pfeiltasten · Maus ziehen · ⛶ Vollbild · Esc Schließen
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <script>
        window.sfLouvreShowroomModal = function () {
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
                    sync_interval_ms: 1800,
                    player_id: null,
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
                peers: [],
                chatOpen: false,
                chatDraft: '',
                chatSending: false,
                chatFeedback: '',
                chatMessages: [],
                unreadChatCount: 0,
                mutedPlayerIds: [],
                chatExpanded: false,
                chatTab: 'chat',
                friendsList: [],
                abilityState: {
                    sprint: { active: false, timer: 0, cooldown: 0 },
                    titanJump: { cooldown: 0 },
                    shield: { active: false, timer: 0, cooldown: 0 },
                    burst: { cooldown: 0 },
                },
                _abilityPollTimer: null,
                activeIndex: 0,
                focusedProduct: null,
                focusedHero: null,
                stickActive: false,
                stickUiX: 0,
                stickUiY: 0,
                prevHtmlOverflow: '',
                prevOverflow: '',
                _engine: null,
                _syncTimer: null,
                _syncInFlight: false,
                _feedbackTimer: null,
                _fsResizeTimer: null,
                _seenMessageIds: null,
                _pageHideBound: null,
                _captureKeydownBound: null,
                _fullscreenChangeBound: null,

                initLobbyListeners() {
                    if (!this._captureKeydownBound) {
                        this._captureKeydownBound = (e) => {
                            if (!this.isOpen) return;
                            const key = e.key || '';
                            const code = e.code || '';

                            if (key === 'Escape' || code === 'Escape' || e.keyCode === 27) {
                                e.preventDefault();
                                e.stopPropagation();
                                this.handleEscape();
                                return;
                            }

                            const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
                            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
                        };
                        window.addEventListener('keydown', this._captureKeydownBound, true);
                    }

                    if (!this._fullscreenChangeBound) {
                        this._fullscreenChangeBound = () => {
                            const fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
                            this.isFullscreen = Boolean(fsEl);
                            if (this._engine) {
                                this._engine.resize();
                                requestAnimationFrame(() => {
                                    if (this._engine) this._engine.resize();
                                });
                                if (this._fsResizeTimer) clearTimeout(this._fsResizeTimer);
                                this._fsResizeTimer = setTimeout(() => {
                                    if (this._engine) this._engine.resize();
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

                stagesPreviewLabel() {
                    if (this.stagesPreviewMode === '1-5') return '✨ Stufen 1-5';
                    if (this.stagesPreviewMode === '6-10') return '✨ Stufen 6-10';
                    return '✨ Stufen';
                },

                switchZone(targetZone) {
                    const z = (targetZone === 'heroes' || targetZone === 'hall') ? 'heroes' : 'gallery';
                    if (this._engine) {
                        this._engine.goToZone(z, false);
                        this.currentZone = this._engine.getCurrentZone();
                    } else {
                        this.currentZone = z;
                    }
                },

                cycleStagesPreview() {
                    if (!this._engine) return;
                    this.stagesPreviewMode = this._engine.cycleStagesPreviewMode();
                },

                focusHeroSlot(slotNum) {
                    if (this._engine) {
                        this._engine.goToHeroSlot(slotNum);
                    }
                },

                focusMyHeroPlace() {
                    if (this._engine) {
                        this._engine.goToMyHeroPlace();
                    }
                },

                openShowroom(initialZone = 'gallery') {
                    const requestedZone = (initialZone === 'heroes' || initialZone === 'hall') ? 'heroes' : 'gallery';
                    if (this.isOpen) {
                        this.switchZone(requestedZone);
                        return;
                    }

                    this.initLobbyListeners();
                    this.isOpen = true;
                    this.isFullscreen = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
                    this.isLoading = true;
                    this.loadError = '';
                    this.currentZone = requestedZone;
                    this.stagesPreviewMode = 'live';
                    this.focusedProduct = null;
                    this.focusedHero = null;
                    this.instanceMenuOpen = false;
                    this.peers = [];
                    this.chatMessages = [];
                    this.unreadChatCount = 0;
                    this.chatFeedback = '';
                    this._seenMessageIds = new Set();
                    this.loadFriendsList();
                    if (!this._abilityPollTimer) {
                        this._abilityPollTimer = setInterval(() => this.syncAbilityState(), 120);
                    }
                    this.prevHtmlOverflow = document.documentElement.style.overflow || '';
                    this.prevOverflow = document.body.style.overflow || '';
                    document.documentElement.style.overflow = 'hidden';
                    document.body.style.overflow = 'hidden';

                    if (!this._pageHideBound) {
                        this._pageHideBound = () => {
                            if (this.isOpen) {
                                this.sendLobbyLeaveBeacon();
                            }
                        };
                        window.addEventListener('pagehide', this._pageHideBound);
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

                    const modulePromise = import('/js/seelenfunke-louvre-showroom-3d.js?v=20261116v4');

                    Promise.all([dataPromise, heroesPromise, modulePromise])
                        .then(([payload, heroesJson, mod]) => {
                            if (!this.isOpen) return;
                            this.products = Array.isArray(payload.products) ? payload.products : [];
                            this.funkiInfo = payload.player_funki || { is_guest: true, level: 2, name: '', weapon: null, player_id: null };
                            if (heroesJson && heroesJson.success) {
                                this.heroesPayload = heroesJson;
                            }
                            this.multiplayer = payload.multiplayer || {
                                enabled: false,
                                is_authenticated: false,
                                solo_only: true,
                                room_id: null,
                                sync_interval_ms: 1800,
                                player_id: null
                            };

                            const isAuth = this.isCustomerAuthenticated();
                            this.lobbyAuthenticated = isAuth;
                            this.soloMode = false;
                            this.activeIndex = 0;
                            this.isLoading = false;

                            if (this.products.length === 0 && !isAuth) {
                                this.loadError = 'Aktuell sind keine Ausstellungsstücke freigegeben.';
                                return;
                            }

                            this.$nextTick(() => {
                                if (!this.isOpen || !this.$refs.louvreCanvas) return;
                                if (this._engine) {
                                    this._engine.destroy();
                                    this._engine = null;
                                }
                                this._engine = mod.createLouvreShowroom({
                                    canvas: this.$refs.louvreCanvas,
                                    floatingCardEl: this.$refs.floatingCard || null,
                                    floatingHeroCardEl: this.$refs.floatingHeroCard || null,
                                    floatingPortalCardEl: this.$refs.floatingPortalCard || null,
                                    products: this.products,
                                    heroesPayload: this.heroesPayload,
                                    playerFunki: this.funkiInfo,
                                    isAuthenticatedCustomer: isAuth,
                                    initialZone: requestedZone,
                                    soloMode: this.soloMode,
                                    onFocusProductChange: (prod, idx) => {
                                        this.focusedProduct = prod || null;
                                        if (this._engine) {
                                            this._engine.setFocusProtected(Boolean(prod));
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
                                    },
                                    onSelectProduct: (prod) => {
                                        if (prod && prod.product_url) {
                                            window.location.href = prod.product_url;
                                        }
                                    }
                                });

                                if (this.mutedPlayerIds.length > 0 && this._engine) {
                                    this._engine.setMutedPlayers(this.mutedPlayerIds);
                                }

                                if (isAuth) {
                                    this.joinLobbyInstance();
                                }
                            });
                        })
                        .catch(() => {
                            this.isLoading = false;
                            this.loadError = 'Der 3D-Showroom konnte auf diesem Gerät nicht geladen werden.';
                        });
                },

                joinLobbyInstance() {
                    if (!this.isOpen || !this._engine || !this.isCustomerAuthenticated() || this.soloMode) {
                        return;
                    }
                    const st = this._engine.getLocalSyncState();
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
                            z: st.z,
                            rot_y: st.rot_y,
                            speed: st.speed,
                            zone: st.zone
                        })
                    })
                        .then((res) => (res.ok ? res.json() : null))
                        .then((data) => {
                            if (!data || !data.ok || !this.isOpen) return;
                            this.lobbyAuthenticated = Boolean(data.authenticated);
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
                            if (this._engine) {
                                this._engine.updatePeers(this.peers);
                            }
                            if (this.lobbyAuthenticated) {
                                this.startMultiplayerLoop();
                            }
                        })
                        .catch(() => {});
                },

                startMultiplayerLoop() {
                    this.stopMultiplayerLoop();
                    if (!this.isOpen || !this.lobbyAuthenticated || this.soloMode) {
                        return;
                    }
                    this._syncTimer = setInterval(() => {
                        this.runMultiplayerSync();
                    }, 1800);
                },

                stopMultiplayerLoop() {
                    if (this._syncTimer) {
                        clearInterval(this._syncTimer);
                        this._syncTimer = null;
                    }
                    this._syncInFlight = false;
                },

                runMultiplayerSync() {
                    if (!this.isOpen || !this._engine || this._syncInFlight) return;
                    if (!this.lobbyAuthenticated || this.soloMode) return;

                    this._syncInFlight = true;
                    const st = this._engine.getLocalSyncState();

                    fetch('/funkenwerk-welt/lobby/sync', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken()
                        },
                        body: JSON.stringify({
                            instance_id: this.lobbyInstanceId,
                            x: st.x,
                            z: st.z,
                            rot_y: st.rot_y,
                            speed: st.speed,
                            zone: st.zone
                        })
                    })
                        .then((res) => (res.ok ? res.json() : null))
                        .then((data) => {
                            if (!data || !data.ok || !this.isOpen || !this._engine) return;
                            if (data.instance_id) {
                                this.lobbyInstanceId = Number(data.instance_id) || this.lobbyInstanceId;
                            }
                            if (Array.isArray(data.instances)) {
                                this.lobbyInstances = data.instances;
                            }
                            this.peers = Array.isArray(data.peers) ? data.peers : [];
                            this._engine.updatePeers(this.peers);
                        })
                        .catch(() => {})
                        .finally(() => {
                            this._syncInFlight = false;
                        });
                },

                switchLobbyInstance(targetId) {
                    if (!this.isOpen || !this._engine || !this.lobbyAuthenticated) return;
                    const cleanTarget = Math.max(1, Math.min(50, parseInt(targetId, 10) || 1));
                    const st = this._engine.getLocalSyncState();
                    this.instanceMenuOpen = false;

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
                            z: st.z,
                            rot_y: st.rot_y,
                            zone: st.zone
                        })
                    })
                        .then((res) => (res.ok ? res.json() : null))
                        .then((data) => {
                            if (!data || !data.ok || !this.isOpen || !this._engine) return;
                            if (data.instance_id) {
                                this.lobbyInstanceId = Number(data.instance_id) || cleanTarget;
                                this.customInstanceInput = this.lobbyInstanceId;
                            }
                            if (Array.isArray(data.instances)) {
                                this.lobbyInstances = data.instances;
                            }
                            this.peers = Array.isArray(data.peers) ? data.peers : [];
                            this._engine.updatePeers(this.peers);
                        })
                        .catch(() => {});
                },

                sendLobbyLeaveBeacon() {
                    if (!this.lobbyAuthenticated) return;
                    const token = this.csrfToken();
                    if (navigator.sendBeacon && typeof FormData !== 'undefined') {
                        const fd = new FormData();
                        fd.append('_token', token);
                        fd.append('instance_id', String(this.lobbyInstanceId));
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
                        body: JSON.stringify({ instance_id: this.lobbyInstanceId })
                    }).catch(() => {});
                },

                toggleSoloMode() {
                    if (!this.isCustomerAuthenticated()) return;
                    this.soloMode = !this.soloMode;
                    this.instanceMenuOpen = false;
                    if (this.soloMode) {
                        this.chatOpen = false;
                        this.peers = [];
                        this.stopMultiplayerLoop();
                        this.sendLobbyLeaveBeacon();
                    } else {
                        this.joinLobbyInstance();
                    }
                    if (this._engine) {
                        this._engine.setSoloMode(this.soloMode);
                    }
                },

                toggleChatPanel() {
                    this.chatOpen = !this.chatOpen;
                    if (this.chatOpen) {
                        this.unreadChatCount = 0;
                    }
                },

                visibleChatMessages() {
                    if (!Array.isArray(this.chatMessages)) return [];
                    return this.chatMessages.filter((m) => m && this.mutedPlayerIds.indexOf(m.player_id) === -1);
                },

                showFeedback(text) {
                    this.chatFeedback = text;
                    if (this._feedbackTimer) {
                        clearTimeout(this._feedbackTimer);
                    }
                    this._feedbackTimer = setTimeout(() => {
                        this.chatFeedback = '';
                    }, 4000);
                },

                sendChatMessage(overrideText) {
                    const raw = overrideText !== undefined ? overrideText : this.chatDraft;
                    const text = String(raw || '').trim();
                    if (text === '' || this.chatSending) return;
                    if (!this.isCustomerAuthenticated() || this.soloMode) return;

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
                            if (this._engine && typeof this._engine.triggerEmote === 'function') {
                                this._engine.triggerEmote(eInfo.emote);
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
                    this.chatSending = true;
                    fetch('/shop/showroom-chat', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken()
                        },
                        body: JSON.stringify({ message: text })
                    })
                        .then((res) => res.json().then((body) => ({ ok: res.ok, body })))
                        .then(({ ok, body }) => {
                            if (!ok || !body || !body.success) {
                                this.showFeedback((body && body.message) ? body.message : 'Nachricht konnte nicht gesendet werden.');
                                return;
                            }
                            this.chatFeedback = '';
                            if (body.entry) {
                                if (!this._seenMessageIds) this._seenMessageIds = new Set();
                                this._seenMessageIds.add(body.entry.id);
                                this.chatMessages = this.chatMessages.concat([body.entry]).slice(-15);
                                if (this._engine) {
                                    this._engine.showChatBubble(body.entry.player_id, body.entry.text);
                                }
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
                    return this.peers.some((p) => p && p.player_id === playerId);
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
                    if (this._engine && typeof this._engine.triggerEmote === 'function') {
                        this._engine.triggerEmote('wave');
                    }
                    this.sendChatMessage('/wave');
                },

                triggerAbility(slot) {
                    if (!this._engine || typeof this._engine.triggerAbility !== 'function') return;
                    this._engine.triggerAbility(slot);
                    this.syncAbilityState();
                },

                syncAbilityState() {
                    if (this._engine && typeof this._engine.getAbilityState === 'function') {
                        this.abilityState = this._engine.getAbilityState();
                    }
                },

                mutePeer(playerId) {
                    if (!playerId || playerId === this.multiplayer.player_id) return;
                    if (this.mutedPlayerIds.indexOf(playerId) === -1) {
                        this.mutedPlayerIds = this.mutedPlayerIds.concat([playerId]);
                        if (this._engine) {
                            this._engine.setMutedPlayers(this.mutedPlayerIds);
                        }
                        this.showFeedback('Spieler für diese Sitzung stummgeschaltet.');
                    }
                },

                reportPeer(playerId) {
                    if (!playerId || playerId === this.multiplayer.player_id) return;
                    this.mutePeer(playerId);

                    fetch('/shop/showroom-report', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken()
                        },
                        body: JSON.stringify({ target_player_id: playerId })
                    })
                        .then((res) => res.json())
                        .then((body) => {
                            if (body && body.success) {
                                this.showFeedback('Meldung übermittelt und Spieler stummgeschaltet.');
                            }
                        })
                        .catch(() => {});
                },

                triggerJump() {
                    if (this._engine) {
                        this._engine.triggerJump();
                    }
                },

                triggerWeapon() {
                    if (this._engine) {
                        this._engine.triggerWeaponAction();
                    }
                },

                closeShowroom() {
                    if (!this.isOpen) return;
                    this.stopMultiplayerLoop();
                    if (!this.soloMode) {
                        this.sendLobbyLeaveBeacon();
                    }
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
                    this.instanceMenuOpen = false;
                    this.chatOpen = false;
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
                    if (this._engine) {
                        this._engine.destroy();
                        this._engine = null;
                    }
                    document.documentElement.style.overflow = this.prevHtmlOverflow;
                    document.body.style.overflow = this.prevOverflow;
                },

                handleEscape() {
                    if (!this.isOpen) return;
                    const fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
                    if (fsEl) {
                        this.toggleFullscreen();
                        return;
                    }
                    if (this.instanceMenuOpen) {
                        this.instanceMenuOpen = false;
                        return;
                    }
                    this.closeShowroom();
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

                focusedPriceLabel() {
                    if (!this.focusedProduct) return '';
                    const raw = String(this.focusedProduct.price_formatted || '').trim();
                    if (this.focusedProduct.is_personalizable && raw !== '' && !/^ab\s/i.test(raw)) {
                        return 'ab ' + raw;
                    }
                    return raw;
                },

                prevExhibit() {
                    if (!this._engine || this.products.length === 0) return;
                    const nextIdx = (this.activeIndex - 1 + this.products.length) % this.products.length;
                    this._engine.goToExhibit(nextIdx, false);
                },

                nextExhibit() {
                    if (!this._engine || this.products.length === 0) return;
                    const nextIdx = (this.activeIndex + 1) % this.products.length;
                    this._engine.goToExhibit(nextIdx, false);
                },

                focusCurrentExhibit() {
                    if (!this._engine || this.products.length === 0) return;
                    this._engine.goToExhibit(this.activeIndex, false);
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
                    if (this._engine) {
                        this._engine.setJoystickVector(dx / maxR, dy / maxR);
                    }
                },

                endStick() {
                    this.stickActive = false;
                    this.stickUiX = 0;
                    this.stickUiY = 0;
                    if (this._engine) {
                        this._engine.setJoystickVector(0, 0);
                    }
                }
            };
        };
    </script>
    @endif
</div>
