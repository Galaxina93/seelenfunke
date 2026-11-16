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
            </div>
        </div>
        @endif

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20">

            {{-- Filter & Search Bar --}}
            <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-4 md:p-5 mb-12">

                @if(empty($isDigitalDomain))
                {{-- Kopfleiste: 3-Welten-Kollektions-Weiche + Begehbarer 3D-Showroom Button (0 Subtexte) --}}
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-2.5 mb-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 p-1.5 bg-gray-50 rounded-2xl border border-gray-100 flex-1">
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

                    <button type="button"
                            @click="$dispatch('open-louvre-showroom')"
                            class="inline-flex items-center justify-center gap-2.5 px-5 py-3.5 rounded-2xl bg-white hover:bg-[#FAF6EE] text-gray-900 border border-[#C5A059] shadow-sm hover:shadow-md text-xs font-bold uppercase tracking-wider transition-all shrink-0 cursor-pointer">
                        <svg class="w-4 h-4 text-[#C5A059] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l9 15H3L12 3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v15" />
                        </svg>
                        <span>3D shoppen</span>
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
    {{-- Begehbarer 3D-Showroom im Louvre-Stil (0 KB Initial-Last, On-Demand via Klick auf "3D shoppen") --}}
    <div id="sf-louvre-showroom-modal"
         wire:ignore
         x-data="window.sfLouvreShowroomModal()"
         @open-louvre-showroom.window="openShowroom()"
         @keydown.escape.window="handleEscape()">

        <div x-show="isOpen"
             x-cloak
             x-transition.opacity.duration.250ms
             role="dialog"
             aria-modal="true"
             aria-label="Mein Seelenfunke Louvre Showroom"
             class="fixed inset-0 z-[2147483500] bg-[#0B0E14] overflow-hidden select-none flex flex-col">

            {{-- Schlichter Schwarz-Gold Museums-Header inkl. Live/Solo-Schalter & Fokus-Schutz --}}
            <div class="relative z-30 h-14 px-3 sm:px-6 bg-[#0B0E14]/95 border-b border-[#C5A059]/35 flex items-center justify-between gap-2 sm:gap-3 shrink-0">
                <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-[#C5A059]/15 border border-[#C5A059]/45 flex items-center justify-center text-[#C5A059] shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l9 15H3L12 3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v15" />
                        </svg>
                    </span>
                    <span class="text-[11px] sm:text-xs font-bold uppercase tracking-[0.18em] text-[#FAF8F5] truncate">
                        MEIN SEELENFUNKE · LOUVRE SHOWROOM
                    </span>
                    <span x-show="funkiInfo.level > 0"
                          class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full bg-[#141821] border border-[#C5A059]/40 text-[10px] font-bold uppercase tracking-wider text-[#E6C687] shrink-0"
                          x-text="'Funki Lv. ' + funkiInfo.level">
                    </span>
                    <span x-show="!funkiInfo.is_guest && funkiInfo.weapon"
                          class="hidden xl:inline-flex items-center px-2.5 py-0.5 rounded-full bg-[#141821] border border-[#C5A059]/35 text-[10px] font-bold uppercase tracking-wider text-[#FAF8F5] shrink-0"
                          x-text="funkiInfo.weapon ? (funkiInfo.weapon.name + ' · ' + funkiInfo.weapon.class_label) : ''">
                    </span>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span x-show="multiplayer.is_authenticated && multiplayer.enabled && !soloMode && focusedProduct !== null"
                          class="hidden lg:inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-[#FAF8F5] text-[#111827] border border-[#C5A059]/60 text-[10px] font-bold uppercase tracking-wider">
                        <span class="text-[#C5A059]">&#10003;</span>
                        <span>Fokus-Schutz</span>
                    </span>

                    <button type="button"
                            x-show="multiplayer.is_authenticated && multiplayer.enabled"
                            data-testid="louvre-live-solo-toggle"
                            @click="toggleSoloMode()"
                            :class="soloMode ? 'bg-[#141821] text-[#FAF8F5] border-[#C5A059]/45' : 'bg-[#FAF8F5] text-[#111827] border-[#C5A059]'"
                            class="px-2.5 sm:px-3 py-1.5 rounded-xl border text-[11px] font-bold tracking-wide transition-colors cursor-pointer inline-flex items-center gap-1.5"
                            :title="soloMode ? 'In den gemeinsamen 12er-Raum wechseln' : 'Ungestört alleine umschauen (Solo)'">
                        <span class="w-2 h-2 rounded-full" :class="soloMode ? 'bg-gray-400' : 'bg-emerald-500'"></span>
                        <span x-text="soloMode ? 'Solo' : ('Live · ' + roomLabel() + ' (' + onlineCount() + ')')"></span>
                    </button>

                    <button type="button"
                            @click="closeShowroom()"
                            class="px-3 sm:px-3.5 py-2 rounded-xl bg-[#141821] hover:bg-[#1E2430] text-[#FAF8F5] border border-[#C5A059]/50 text-xs font-bold tracking-wide transition-colors cursor-pointer shrink-0">
                        &times; <span class="hidden sm:inline">Zurück zum Shop</span><span class="sm:hidden">Shop</span>
                    </button>
                </div>
            </div>

            {{-- 3D-Louvre Buehne + Räumliche Light-Mode Schwebetafel --}}
            <div class="relative flex-1 min-h-0 w-full overflow-hidden">
                <canvas x-ref="louvreCanvas"
                        class="absolute inset-0 w-full h-full block touch-none cursor-grab"></canvas>

                {{-- Lade- & Fehlerstatus --}}
                <div x-show="isLoading || loadError !== ''"
                     class="absolute inset-0 z-30 bg-[#0B0E14]/90 flex flex-col items-center justify-center p-6 text-center">
                    <template x-if="isLoading">
                        <div class="flex flex-col items-center gap-3">
                            <div class="w-10 h-10 rounded-full border-2 border-[#C5A059]/30 border-t-[#C5A059] animate-spin"></div>
                            <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#E6C687]">Louvre Galerie wird geöffnet</span>
                        </div>
                    </template>
                    <template x-if="!isLoading && loadError !== ''">
                        <div class="bg-white text-[#111827] rounded-2xl p-6 max-w-sm border border-gray-200 shadow-xl space-y-4">
                            <p class="text-sm font-semibold text-[#111827]" x-text="loadError"></p>
                            <button type="button"
                                    @click="closeShowroom()"
                                    class="px-5 py-2.5 rounded-xl bg-[#111827] text-white text-xs font-bold uppercase tracking-wider cursor-pointer">
                                Zurück zum Shop
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Stufe 2: Schwebendes World-Space Info-Schild am fokussierten Podest (100 % Seelenfunke Light-Mode, kein blockierendes Popup!) --}}
                <div x-ref="floatingCard"
                     data-testid="louvre-floating-space-card"
                     style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                     class="absolute top-0 left-0 z-20 will-change-transform transition-opacity duration-200 pointer-events-none">
                    <div @pointerdown.stop
                         class="pointer-events-auto w-[252px] sm:w-[280px] rounded-2xl bg-[#FAF8F5]/95 backdrop-blur-md text-[#111827] border border-[#C5A059]/65 shadow-xl p-3.5">
                        <div class="flex items-start gap-2.5">
                            <template x-if="focusedProduct && focusedProduct.preview_image_url">
                                <img :src="focusedProduct.preview_image_url"
                                     :alt="focusedProduct ? focusedProduct.name : ''"
                                     class="w-11 h-11 rounded-xl object-cover bg-white border border-[#C5A059]/35 shrink-0">
                            </template>
                            <div class="min-w-0 flex-1">
                                <span class="block text-[10px] font-bold uppercase tracking-[0.16em] text-[#9A7738] truncate"
                                      x-text="focusedProduct ? (focusedProduct.category_name || 'Kollektion') : ''"></span>
                                <h3 class="text-sm sm:text-[15px] font-serif font-bold text-[#111827] leading-snug line-clamp-2"
                                    x-text="focusedProduct ? focusedProduct.name : ''"></h3>
                                <span class="inline-block mt-0.5 text-sm font-bold text-[#111827]"
                                      x-text="focusedPriceLabel()"></span>
                            </div>
                        </div>

                        {{-- Maximal 3 kurze Vorteile mit goldenem Haken (#C5A059) --}}
                        <div x-show="focusedBenefits().length > 0"
                             class="mt-2.5 pt-2.5 border-t border-[#C5A059]/25 space-y-1.5">
                            <template x-for="(benefit, bIdx) in focusedBenefits()" :key="bIdx">
                                <div class="flex items-start gap-2 text-[11px] font-semibold text-[#111827]">
                                    <span class="text-[#C5A059] font-black leading-4 shrink-0">&#10003;</span>
                                    <span class="leading-4 line-clamp-1" x-text="benefit"></span>
                                </div>
                            </template>
                        </div>

                        {{-- 1 direkter Gold-Button zur Produktseite --}}
                        <a :href="focusedProduct ? focusedProduct.product_url : '#'"
                           class="mt-3 w-full py-2.5 px-4 rounded-xl bg-[#C5A059] hover:bg-[#B38E46] text-[#111827] font-bold text-xs tracking-wide shadow-sm flex items-center justify-center gap-1.5 transition-colors">
                            <span x-text="focusedProduct && focusedProduct.is_personalizable ? 'Jetzt gestalten' : 'Zum Produkt'"></span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                    <div class="w-3 h-3 rotate-45 bg-[#FAF8F5] border-r border-b border-[#C5A059]/65 mx-auto -mt-1.5 pointer-events-none"></div>
                </div>

                {{-- Ein-/ausklappbarer Light-Mode Raum-Chat (pausiert automatisch bei aktivem Exponat-Fokus / Kauf-Ruheschutz) --}}
                <div x-show="!isLoading && loadError === '' && chatOpen && multiplayer.is_authenticated && multiplayer.enabled && !soloMode && focusedProduct === null"
                     @pointerdown.stop
                     data-testid="louvre-room-chat-panel"
                     class="absolute bottom-36 right-3 sm:bottom-24 sm:right-6 z-20 w-[284px] sm:w-[316px] rounded-2xl bg-[#FAF8F5]/95 backdrop-blur-md text-[#111827] border border-[#C5A059]/65 shadow-2xl p-3 flex flex-col gap-2">
                    <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-[#C5A059]/25">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[#111827] truncate"
                                  x-text="roomLabel() + ' · Chat'"></span>
                        </div>
                        <button type="button"
                                @click="chatOpen = false"
                                class="w-6 h-6 rounded-lg hover:bg-black/5 text-gray-600 font-bold text-sm leading-none flex items-center justify-center cursor-pointer"
                                aria-label="Chat minimieren">&times;</button>
                    </div>

                    <div x-ref="chatScrollBox"
                         class="max-h-40 overflow-y-auto space-y-1.5 pr-1 text-xs">
                        <template x-if="visibleChatMessages().length === 0">
                            <div class="py-3 text-center text-[11px] font-semibold text-gray-500">
                                Noch keine Nachrichten im Raum.
                            </div>
                        </template>
                        <template x-for="msg in visibleChatMessages()" :key="msg.id">
                            <div class="rounded-xl bg-white/90 border border-[#C5A059]/25 px-2.5 py-1.5">
                                <div class="flex items-center justify-between gap-1.5">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <span class="font-bold text-[11px] text-[#111827] truncate" x-text="msg.display_name"></span>
                                        <span class="px-1.5 py-0.5 rounded bg-[#F5F1E8] border border-[#D8C9A8] text-[9px] font-black text-[#9A7738] shrink-0"
                                              x-text="msg.class_label || 'KLASSE C'"></span>
                                    </div>
                                    <div x-show="msg.player_id !== multiplayer.player_id" class="flex items-center gap-1 shrink-0">
                                        <button type="button"
                                                @click="mutePeer(msg.player_id)"
                                                class="text-[10px] font-bold text-gray-500 hover:text-gray-900 px-1 cursor-pointer"
                                                title="Spieler lokal stummschalten">Stumm</button>
                                        <button type="button"
                                                @click="reportPeer(msg.player_id)"
                                                class="text-[10px] font-bold text-red-600 hover:text-red-700 px-1 cursor-pointer"
                                                title="Unangemessene Nachricht melden">Melden</button>
                                    </div>
                                </div>
                                <p class="mt-0.5 text-[11px] text-gray-800 leading-snug break-words" x-text="msg.text"></p>
                            </div>
                        </template>
                    </div>

                    <div x-show="chatFeedback !== ''"
                         class="px-2.5 py-1.5 rounded-xl bg-white border border-[#C5A059]/45 text-[11px] font-semibold text-[#111827]"
                         x-text="chatFeedback"></div>

                    <form @submit.prevent="sendChatMessage()" class="flex items-center gap-1.5 pt-0.5">
                        <input type="text"
                               x-model="chatDraft"
                               maxlength="100"
                               placeholder="Nachricht..."
                               class="flex-1 min-w-0 px-2.5 py-1.5 rounded-xl bg-white border border-[#C5A059]/45 text-xs font-medium text-[#111827] placeholder-gray-400 focus:outline-none focus:border-[#C5A059]">
                        <button type="submit"
                                :disabled="chatSending"
                                class="px-3 py-1.5 rounded-xl bg-[#C5A059] hover:bg-[#B38E46] disabled:opacity-50 text-[#111827] text-xs font-bold transition-colors cursor-pointer shrink-0">
                            Senden
                        </button>
                    </form>
                </div>

                {{-- Virtueller Mobile-Stick unten links (1-Hand & Smartphone Steuerung) --}}
                <div x-show="!isLoading && loadError === '' && products.length > 0"
                     class="absolute bottom-20 left-4 sm:bottom-6 sm:left-6 z-20">
                    <div x-ref="stickZone"
                         @pointerdown.stop.prevent="startStick($event)"
                         @pointermove.stop.prevent="moveStick($event)"
                         @pointerup.stop.prevent="endStick()"
                         @pointercancel.stop.prevent="endStick()"
                         class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-[#0B0E14]/75 border border-[#C5A059]/45 backdrop-blur-sm flex items-center justify-center relative touch-none cursor-pointer shadow-lg"
                         aria-label="Bewegungssteuerung">
                        <div class="w-2 h-2 rounded-full bg-[#C5A059]/30"></div>
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#C5A059] border border-[#FFF8E1]/60 shadow-md pointer-events-none transition-transform duration-75"
                             :style="'transform: translate3d(' + stickUiX + 'px, ' + stickUiY + 'px, 0)'"></div>
                    </div>
                </div>

                {{-- Kompakte Action-Leiste unten rechts: Springen (alle), Gamification-Waffe & Raum-Chat (eingeloggte Kunden) --}}
                <div x-show="!isLoading && loadError === '' && products.length > 0"
                     @pointerdown.stop
                     class="absolute bottom-20 right-4 sm:bottom-6 sm:right-6 z-20 flex items-center gap-2">
                    <button type="button"
                            data-testid="louvre-jump-btn"
                            @click="triggerJump()"
                            class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-[#0B0E14]/85 hover:bg-[#141821] text-[#FAF8F5] border border-[#C5A059]/55 shadow-lg backdrop-blur-sm flex items-center justify-center text-base font-bold transition-colors cursor-pointer"
                            title="Springen (Leertaste)"
                            aria-label="Springen">
                        &#10514;
                    </button>

                    <button type="button"
                            x-show="!funkiInfo.is_guest && funkiInfo.weapon"
                            data-testid="louvre-weapon-btn"
                            @click="triggerWeapon()"
                            class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-[#0B0E14]/85 hover:bg-[#141821] text-[#C5A059] border border-[#C5A059]/65 shadow-lg backdrop-blur-sm flex items-center justify-center text-base font-bold transition-colors cursor-pointer"
                            :title="funkiInfo.weapon ? (funkiInfo.weapon.name + ' aktivieren (Taste F)') : 'Waffen-Effekt'"
                            aria-label="Waffen-Effekt">
                        &#10022;
                    </button>

                    <button type="button"
                            x-show="multiplayer.is_authenticated && multiplayer.enabled && !soloMode"
                            data-testid="louvre-chat-toggle-btn"
                            @click="toggleChatPanel()"
                            :class="chatOpen ? 'bg-[#FAF8F5] text-[#111827] border-[#C5A059]' : 'bg-[#0B0E14]/85 text-[#FAF8F5] border-[#C5A059]/55'"
                            class="relative h-11 sm:h-12 px-3.5 rounded-2xl border shadow-lg backdrop-blur-sm flex items-center justify-center gap-1.5 text-xs font-bold transition-colors cursor-pointer"
                            title="Raum-Chat öffnen"
                            aria-label="Raum-Chat">
                        <span>Chat</span>
                        <span x-show="unreadChatCount > 0 && !chatOpen"
                              class="px-1.5 py-0.5 rounded-full bg-[#C5A059] text-[#111827] text-[10px] font-black leading-none"
                              x-text="unreadChatCount"></span>
                    </button>
                </div>

                {{-- Kompakte Schwarz-Gold Museums-Leiste (Vorheriges | Exponat X/N | Naechstes) --}}
                <div x-show="!isLoading && loadError === '' && products.length > 0"
                     class="absolute bottom-4 inset-x-3 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 z-20 flex justify-center pointer-events-none">
                    <div class="pointer-events-auto inline-flex items-center gap-1.5 sm:gap-2 px-2.5 py-2 rounded-2xl bg-[#0B0E14]/90 border border-[#C5A059]/50 shadow-2xl backdrop-blur-md max-w-full">
                        <button type="button"
                                @click="prevExhibit()"
                                class="px-3 py-2 rounded-xl bg-[#141821] hover:bg-[#1E2430] text-[#FAF8F5] border border-[#C5A059]/35 text-xs font-bold transition-colors cursor-pointer shrink-0">
                            &lsaquo; <span class="hidden sm:inline">Vorheriges</span>
                        </button>

                        <button type="button"
                                @click="focusCurrentExhibit()"
                                class="px-3.5 py-2 rounded-xl bg-[#141821]/80 hover:bg-[#1E2430] border border-[#C5A059]/30 text-xs font-bold text-[#E6C687] truncate max-w-[180px] sm:max-w-[260px] cursor-pointer"
                                title="Zum Podest gehen">
                            <span x-text="'Exponat ' + (activeIndex + 1) + '/' + products.length"></span>
                            <span class="hidden md:inline text-[#FAF8F5]" x-show="activeExhibitName() !== ''" x-text="' · ' + activeExhibitName()"></span>
                        </button>

                        <button type="button"
                                @click="nextExhibit()"
                                class="px-3 py-2 rounded-xl bg-[#141821] hover:bg-[#1E2430] text-[#FAF8F5] border border-[#C5A059]/35 text-xs font-bold transition-colors cursor-pointer shrink-0">
                            <span class="hidden sm:inline">Nächstes</span> &rsaquo;
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.sfLouvreShowroomModal = function () {
            return {
                isOpen: false,
                isLoading: false,
                loadError: '',
                products: [],
                funkiInfo: { is_guest: true, level: 2, name: '', weapon: null, player_id: null },
                multiplayer: {
                    enabled: false,
                    is_authenticated: false,
                    solo_only: true,
                    room_id: null,
                    sync_interval_ms: 0,
                    player_id: null,
                    display_name: null,
                    weapon: null
                },
                soloMode: false,
                peers: [],
                chatOpen: false,
                chatDraft: '',
                chatSending: false,
                chatFeedback: '',
                chatMessages: [],
                unreadChatCount: 0,
                mutedPlayerIds: [],
                activeIndex: 0,
                focusedProduct: null,
                stickActive: false,
                stickUiX: 0,
                stickUiY: 0,
                prevOverflow: '',
                _engine: null,
                _syncTimer: null,
                _syncInFlight: false,
                _feedbackTimer: null,
                _seenMessageIds: null,

                csrfToken() {
                    const el = document.querySelector('meta[name="csrf-token"]');
                    return el ? (el.getAttribute('content') || '') : '';
                },

                roomLabel() {
                    const raw = String(this.multiplayer.room_id || '');
                    const m = raw.match(/(\d+)$/);
                    return m ? ('Raum ' + m[1]) : 'Raum 1';
                },

                onlineCount() {
                    if (this.soloMode || !this.multiplayer.enabled) return 1;
                    return 1 + (Array.isArray(this.peers) ? this.peers.length : 0);
                },

                openShowroom() {
                    if (this.isOpen) return;
                    this.isOpen = true;
                    this.isLoading = true;
                    this.loadError = '';
                    this.focusedProduct = null;
                    this.peers = [];
                    this.chatMessages = [];
                    this.unreadChatCount = 0;
                    this.chatFeedback = '';
                    this._seenMessageIds = new Set();
                    this.prevOverflow = document.body.style.overflow || '';
                    document.body.style.overflow = 'hidden';

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

                    const modulePromise = import('/js/seelenfunke-louvre-showroom-3d.js?v=20261116v2');

                    Promise.all([dataPromise, modulePromise])
                        .then(([payload, mod]) => {
                            if (!this.isOpen) return;
                            this.products = Array.isArray(payload.products) ? payload.products : [];
                            this.funkiInfo = payload.player_funki || { is_guest: true, level: 2, name: '', weapon: null, player_id: null };
                            this.multiplayer = payload.multiplayer || {
                                enabled: false,
                                is_authenticated: false,
                                solo_only: true,
                                room_id: null,
                                sync_interval_ms: 0,
                                player_id: null
                            };
                            this.soloMode = !this.multiplayer.enabled;
                            this.activeIndex = 0;
                            this.isLoading = false;

                            if (this.products.length === 0) {
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
                                    products: this.products,
                                    playerFunki: this.funkiInfo,
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
                                    onActiveExhibitChange: (idx) => {
                                        this.activeIndex = idx;
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

                                this.startMultiplayerLoop();
                            });
                        })
                        .catch(() => {
                            this.isLoading = false;
                            this.loadError = 'Der 3D-Showroom konnte auf diesem Gerät nicht geladen werden.';
                        });
                },

                startMultiplayerLoop() {
                    this.stopMultiplayerLoop();
                    if (!this.isOpen || !this.multiplayer.is_authenticated || !this.multiplayer.enabled) {
                        return;
                    }
                    const intervalMs = Math.max(200, Number(this.multiplayer.sync_interval_ms) || 220);
                    this.runMultiplayerSync(true);
                    this._syncTimer = setInterval(() => {
                        this.runMultiplayerSync(false);
                    }, intervalMs);
                },

                stopMultiplayerLoop() {
                    if (this._syncTimer) {
                        clearInterval(this._syncTimer);
                        this._syncTimer = null;
                    }
                    this._syncInFlight = false;
                },

                runMultiplayerSync(isInitialTick) {
                    if (!this.isOpen || !this._engine || this._syncInFlight) return;
                    if (!this.multiplayer.is_authenticated || !this.multiplayer.enabled) return;

                    this._syncInFlight = true;
                    const localState = this._engine.getLocalSyncState();
                    localState.soloMode = this.soloMode;

                    fetch('/shop/showroom-sync', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken()
                        },
                        body: JSON.stringify(localState)
                    })
                        .then((res) => (res.ok ? res.json() : null))
                        .then((data) => {
                            if (!data || !data.success || !this.isOpen || !this._engine) return;
                            if (data.room_id) {
                                this.multiplayer.room_id = data.room_id;
                            }
                            this.peers = Array.isArray(data.peers) ? data.peers : [];
                            this._engine.updatePeers(this.peers);
                            this.ingestRoomMessages(Array.isArray(data.messages) ? data.messages : [], isInitialTick);
                        })
                        .catch(() => {})
                        .finally(() => {
                            this._syncInFlight = false;
                        });
                },

                ingestRoomMessages(incoming, isInitialTick) {
                    if (!this._seenMessageIds) {
                        this._seenMessageIds = new Set();
                    }
                    const nowMs = Date.now();
                    let addedUnread = 0;

                    for (let i = 0; i < incoming.length; i++) {
                        const m = incoming[i];
                        if (!m || !m.id) continue;
                        if (!this._seenMessageIds.has(m.id)) {
                            this._seenMessageIds.add(m.id);
                            const isMuted = this.mutedPlayerIds.indexOf(m.player_id) !== -1;
                            const isRecent = Math.abs(nowMs - (Number(m.created_at_ms) || nowMs)) < 7500;
                            if (!isMuted && (!isInitialTick || isRecent) && this._engine) {
                                this._engine.showChatBubble(m.player_id, m.text);
                            }
                            if (!isInitialTick && !isMuted && m.player_id !== this.multiplayer.player_id && !this.chatOpen) {
                                addedUnread += 1;
                            }
                        }
                    }

                    this.chatMessages = incoming;
                    if (addedUnread > 0) {
                        this.unreadChatCount += addedUnread;
                    }
                },

                toggleSoloMode() {
                    if (!this.multiplayer.is_authenticated || !this.multiplayer.enabled) return;
                    this.soloMode = !this.soloMode;
                    if (this.soloMode) {
                        this.chatOpen = false;
                        this.peers = [];
                    }
                    if (this._engine) {
                        this._engine.setSoloMode(this.soloMode);
                    }
                    this.runMultiplayerSync(false);
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

                sendChatMessage() {
                    const text = String(this.chatDraft || '').trim();
                    if (text === '' || this.chatSending) return;
                    if (!this.multiplayer.is_authenticated || !this.multiplayer.enabled || this.soloMode) return;

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
                            this.chatDraft = '';
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
                    this.isOpen = false;
                    this.isLoading = false;
                    this.focusedProduct = null;
                    this.chatOpen = false;
                    this.stopMultiplayerLoop();
                    if (this._feedbackTimer) {
                        clearTimeout(this._feedbackTimer);
                        this._feedbackTimer = null;
                    }
                    this.endStick();
                    if (this._engine) {
                        this._engine.destroy();
                        this._engine = null;
                    }
                    document.body.style.overflow = this.prevOverflow;
                },

                handleEscape() {
                    if (!this.isOpen) return;
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
