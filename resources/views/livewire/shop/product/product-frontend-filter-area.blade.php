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
                            class="bg-primary text-gray-900 px-8 py-4 rounded-full font-bold text-lg hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 shadow-lg shadow-primary/30 inline-flex items-center justify-center gap-2.5 cursor-pointer">
                        <span>3D Shoppen</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20">

            {{-- Filter & Search Bar --}}
            <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-4 md:p-5 mb-12">

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
                
                {{-- Suchleiste Volle Breite Oben --}}
                <div class="w-full mb-4">
                    <div class="relative w-full group">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Suche nach Name oder Eigenschaft..." class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-medium text-gray-900 focus:bg-white focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all placeholder-gray-400">
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
                @if($collectionWorld !== 'voucher')
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
                gap: 10px;
                z-index: 27;
                pointer-events: auto;
            }
            .sf-ability-btn {
                position: relative;
                width: 48px;
                height: 48px;
                border-radius: 13px;
                background: #FFFFFF;
                border: 1.5px solid rgba(197, 160, 89, 0.68);
                color: #8A692C;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 4px 14px rgba(17, 24, 39, 0.08), 0 0 0 1px rgba(250, 246, 238, 0.9) inset;
                transition: transform 0.14s ease, border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
                padding: 0;
            }
            .sf-ability-btn:hover {
                border-color: #C5A059;
                transform: translateY(-2px);
                background: #FAF6EE;
                color: #6E501B;
            }
            .sf-ability-btn.is-active {
                border-color: #C5A059;
                background: #FAF3E3;
                color: #78561C;
                box-shadow: 0 6px 18px rgba(197, 160, 89, 0.34);
            }
            .sf-ability-btn.is-cooldown {
                opacity: 0.56;
                border-color: rgba(156, 163, 175, 0.55);
                color: #6B7280;
            }
            .sf-ability-svg {
                width: 24px;
                height: 24px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
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
                left: 5px;
                font-size: 9px;
                font-weight: 900;
                color: #6B7280;
                line-height: 1;
                pointer-events: none;
            }
            .sf-ability-cd {
                position: absolute;
                bottom: 3px;
                right: 4px;
                font-size: 9px;
                font-weight: 800;
                color: #FFFFFF;
                background: rgba(17, 24, 39, 0.88);
                padding: 1px 4px;
                border-radius: 5px;
                line-height: 1;
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
            .sf-lobby-lava-chest-card {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.98);
                color: #111827;
                border: 1.5px solid #C5A059;
                box-shadow: 0 14px 32px rgba(17, 24, 39, 0.16);
                font-size: 12px;
                font-weight: 800;
                white-space: nowrap;
                cursor: pointer;
                text-decoration: none;
            }
            .sf-lobby-lava-chest-card.is-cta { background: #C5A059; color: #FFFFFF; }
            .sf-lobby-lava-chest-card.is-cta:hover { background: #B38E46; }
            .sf-lobby-unit-frame {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 9px;
                padding: 6px 13px 6px 7px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(197, 160, 89, 0.55);
                box-shadow: 0 8px 24px rgba(17, 24, 39, 0.08);
                color: #111827;
            }
            .sf-lobby-unit-avatar {
                width: 32px;
                height: 32px;
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
                flex-direction: column;
                gap: 3px;
                min-width: 134px;
            }
            .sf-lobby-unit-mana-row {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .sf-lobby-unit-mana-label {
                font-size: 9.5px;
                font-weight: 900;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #1D4ED8;
                line-height: 1;
                flex-shrink: 0;
            }
            .sf-lobby-unit-mana-track {
                flex: 1 1 auto;
                height: 6px;
                border-radius: 999px;
                background: rgba(29, 78, 216, 0.16);
                border: 1px solid rgba(29, 78, 216, 0.26);
                overflow: hidden;
            }
            .sf-lobby-unit-mana-fill {
                height: 100%;
                border-radius: 999px;
                background: linear-gradient(90deg, #1d4ed8 0%, #38bdf8 100%);
                transition: width 160ms ease;
            }
            .sf-lobby-unit-mana-val {
                font-size: 9.5px;
                font-weight: 800;
                color: #1E40AF;
                line-height: 1;
                flex-shrink: 0;
                font-variant-numeric: tabular-nums;
            }
            .sf-lobby-minimap-wrap {
                position: absolute;
                top: 62px;
                right: 18px;
                width: 180px;
                height: 180px;
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
            }
            .sf-lobby-sanctum-row.is-done { background: #FFFFFF; border-color: #C5A059; }
            .sf-lobby-sanctum-check {
                width: 22px;
                height: 22px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 900;
                flex-shrink: 0;
                background: #FFFFFF;
                border: 1.5px solid rgba(197, 160, 89, 0.55);
                color: #6B7280;
            }
            .sf-lobby-sanctum-row.is-done .sf-lobby-sanctum-check {
                background: #FAF3E3;
                border-color: #C5A059;
                color: #C5A059;
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
            @media (max-width: 900px), (pointer: coarse) {
                .sf-lobby-stick-wrap { display: block; bottom: calc(70px + env(safe-area-inset-bottom, 0px)); left: 12px; }
                .sf-lobby-stick { width: 70px; height: 70px; }
                .sf-lobby-minimap-wrap { width: 136px; height: 136px; top: 58px; right: 12px; }
                .sf-ability-btn { width: 44px; height: 44px; border-radius: 11px; }
                .sf-ability-svg { width: 21px; height: 21px; }
                .sf-ability-tooltip { display: none; }
            }
            @media (max-width: 767px) {
                .sf-lobby-topbar { padding: 8px 10px; gap: 6px; }
                .sf-lobby-zone-badge { padding: 5px 9px; font-size: 11px; }
                .sf-lobby-pill-btn, .sf-lobby-close-btn { padding: 6px 9px; font-size: 11px; }
                .sf-lobby-kbd, .sf-lobby-hide-sm { display: none !important; }
                .sf-lobby-chat-panel { bottom: calc(70px + env(safe-area-inset-bottom, 0px)); right: 10px; }
                .sf-lobby-chat-panel.is-expanded { width: calc(100vw - 20px); }
                .sf-lobby-copyright { font-size: 9.5px; left: 10px; bottom: calc(20px + env(safe-area-inset-bottom, 0px)); }
                .sf-lobby-keybind-grid { grid-template-columns: 1fr; }
            }
            @media (max-width: 559px) {
                .sf-lobby-minimap-wrap { width: 116px; height: 116px; top: 96px; right: 10px; }
                .sf-lobby-unit-meta { min-width: 112px; }
                .sf-lobby-gold-crest-bar { min-height: 56px; padding: 5px 8px calc(6px + env(safe-area-inset-bottom, 0px)); justify-content: flex-end; }
                .sf-lobby-copyright { left: 10px; font-size: 9px; max-width: 96px; overflow: hidden; text-overflow: ellipsis; }
                .sf-lobby-abilities-dock { gap: 6px; }
                .sf-ability-btn { width: 40px; height: 40px; border-radius: 10px; }
                .sf-ability-svg { width: 19px; height: 19px; }
                .sf-ability-key { display: none; }
                .sf-lobby-floating-card { width: 266px; padding: 12px 13px 10px; }
                .sf-lobby-prod-title { font-size: 13.5px; }
            }
        </style>

        <template x-teleport="body">
            <div id="sf-louvre-showroom-modal"
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
                        <div class="sf-lobby-unit-frame" data-testid="lobby-player-unit-frame">
                            <span class="sf-lobby-unit-avatar">✦</span>
                            <div class="sf-lobby-unit-meta">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11px;font-weight:800;line-height:1.15;">
                                    <span style="max-width:112px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                          x-text="playerHud.name || 'Funki'"></span>
                                    <span style="color:#9A7738;"
                                          x-text="'Stufe ' + (playerHud.level || 2) + ' · ✨ ' + (playerHud.funkenBalance || 0)"></span>
                                </div>
                                <div class="sf-lobby-unit-mana-row">
                                    <span class="sf-lobby-unit-mana-label">Mana</span>
                                    <div class="sf-lobby-unit-mana-track">
                                        <div class="sf-lobby-unit-mana-fill"
                                             :style="'width:' + Math.max(0, Math.min(100, Math.round(((playerHud.mana ?? 100) / Math.max(1, playerHud.maxMana || 100)) * 100))) + '%'"></div>
                                    </div>
                                    <span class="sf-lobby-unit-mana-val"
                                          x-text="Math.round(playerHud.mana ?? 100)">100</span>
                                </div>
                            </div>
                        </div>

                        <div class="sf-lobby-nav-pill">
                            <span class="sf-lobby-zone-badge"
                                  data-testid="lobby-current-zone-badge"
                                  x-text="currentZoneLabel()">🛍️ Kollektion</span>
                            <button type="button"
                                    x-show="currentZone === 'heroes' && isCustomerAuthenticated()"
                                    data-testid="lobby-stages-preview-btn"
                                    @click="cycleStagesPreview()"
                                    class="sf-lobby-stages-btn">
                                <span x-text="stagesPreviewLabel()"></span>
                            </button>
                        </div>
                    </div>

                    <div class="sf-lobby-actions">
                        <span x-show="currentZone === 'heroes' && isCustomerAuthenticated() && !soloMode && focusedProduct !== null"
                              class="sf-lobby-badge sf-lobby-hide-sm">
                            <span style="color:#C5A059;">&#10003;</span>
                            <span>Fokus-Schutz</span>
                        </span>

                        <button type="button"
                                x-show="currentZone === 'heroes' && isCustomerAuthenticated()"
                                data-testid="louvre-live-solo-toggle"
                                @click="toggleSoloMode()"
                                class="sf-lobby-pill-btn"
                                :title="soloMode ? 'Multiplayer aktivieren' : 'Ungestört alleine umschauen (Solo)'">
                            <span class="sf-lobby-dot" :class="soloMode ? 'is-solo' : 'is-live'"></span>
                            <span x-text="soloMode ? 'Solo' : 'Live'"></span>
                        </button>

                        {{-- Manueller Multiplayer-Instanzen-Waehler (NUR in der Halle der Helden aktiv!) --}}
                        <div x-show="currentZone === 'heroes' && isCustomerAuthenticated() && !soloMode"
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
                                x-show="currentZone === 'heroes'"
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

                        <button type="button"
                                data-testid="lobby-audio-mute-btn"
                                @click="toggleQuickMute()"
                                :class="!audioState.allMuted ? 'sf-lobby-pill-btn is-gold' : 'sf-lobby-pill-btn'"
                                :title="audioState.allMuted ? 'Ton aktivieren' : 'Ton stummschalten'">
                            <span x-text="audioState.allMuted ? '🔇' : '🔊'"></span>
                        </button>

                        <button type="button"
                                data-testid="louvre-settings-btn"
                                @click="toggleSettingsModal()"
                                :class="settingsOpen ? 'sf-lobby-pill-btn is-gold' : 'sf-lobby-pill-btn'"
                                title="Einstellungen">
                            <span>⚙️</span>
                        </button>

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

                    {{-- Kinoreife Elfenbein-Gold-Blende beim Klick auf "Jetzt gestalten" --}}
                    <div class="sf-lobby-ivory-transition" :class="{ 'is-fading': productTransitionActive }"></div>

                    {{-- Runde Echtzeit-Minimap oben rechts mit sticky Uhrzeit-Badge & Jahreszeiten-Tooltip --}}
                    <div x-show="!isLoading && loadError === ''"
                         @pointerdown.stop
                         data-testid="lobby-minimap"
                         class="sf-lobby-minimap-wrap">
                        <canvas x-ref="minimapCanvas" width="180" height="180" class="sf-lobby-minimap-canvas"></canvas>
                        <span class="sf-lobby-minimap-time"
                              :title="minimapTooltip()"
                              x-text="environmentInfo.server_time || '12:00'"></span>
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

                    {{-- Schwebendes World-Space Schatztruhen-Schild (Bereich 3: 10-Stufen Magma-Welt) --}}
                    <div x-ref="floatingLavaChestCard"
                         data-testid="lobby-lava-chest-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <div @pointerdown.stop style="display:flex;flex-direction:column;align-items:center;gap:6px;">
                            <template x-if="!isCustomerAuthenticated()">
                                <button type="button"
                                        @click="openSanctumGateModal('lava')"
                                        class="sf-lobby-lava-chest-card">
                                    <span>🔒</span>
                                    <span>Anmelden &rarr;</span>
                                </button>
                            </template>
                            <template x-if="isCustomerAuthenticated() && lavaChestState.claimedToday">
                                <div class="sf-lobby-lava-chest-card">
                                    <span style="color:#C5A059;">&#10003;</span>
                                    <span x-text="lavaChestState.statusMessage || 'Heute bereits geöffnet ✓'"></span>
                                </div>
                            </template>
                            <template x-if="isCustomerAuthenticated() && !lavaChestState.claimedToday">
                                <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
                                    <div class="sf-lobby-lava-chest-card" style="cursor:default;">
                                        <span x-text="'Magma-Truhe · +' + (lavaChestState.rewardPreview || 5) + ' ✨ Funken'">Magma-Truhe · +5 ✨ Funken</span>
                                    </div>
                                    <button type="button"
                                            @click="claimLavaChest()"
                                            :disabled="lavaChestState.claiming"
                                            class="sf-lobby-lava-chest-card is-cta">
                                        <span x-text="lavaChestState.claiming ? 'Truhe wird geöffnet...' : 'Truhe öffnen'"></span>
                                    </button>
                                </div>
                            </template>
                            <span x-show="lavaChestState.statusMessage !== '' && !lavaChestState.claimedToday && !lavaChestState.claiming"
                                  style="pointer-events:none;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,0.95);border:1px solid rgba(197,160,89,0.5);font-size:11px;font-weight:700;color:#111827;"
                                  x-text="lavaChestState.statusMessage"></span>
                        </div>
                        <div class="sf-lobby-floating-arrow"></div>
                    </div>

                    {{-- Schwebendes Light-Mode Schild am verschlossenen West-Prunktor zur Aussenwelt --}}
                    <div x-ref="floatingOuterGateCard"
                         data-testid="lobby-outer-gate-card"
                         style="opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(-9999px, -9999px, 0);"
                         class="sf-lobby-floating-anchor">
                        <div @pointerdown.stop class="sf-lobby-portal-pill">
                            <span>🔒</span>
                            <span>Tor zur Außenwelt · Bald geöffnet</span>
                        </div>
                    </div>

                    {{-- Helles Light-Mode Voraussetzungs-Modal (3-Stufen-Zugangscheck fuer Halle der Helden & Magma-Welt, Clean-Design, 0 Subtexte) --}}
                    <div x-show="sanctumGateOpen"
                         x-cloak
                         @pointerdown.stop
                         @click.self="closeSanctumGateModal()"
                         data-testid="lobby-sanctum-gate-modal"
                         class="sf-lobby-settings-backdrop">
                        <div class="sf-lobby-settings-card" role="dialog" aria-label="Zugangsvoraussetzungen">
                            <div class="sf-lobby-settings-head">
                                <h3 class="sf-lobby-settings-title">
                                    <span x-text="sanctumGateZone === 'lava' ? '🌋' : '🏛️'"></span>
                                    <span x-text="sanctumGateZone === 'lava' ? 'Magma-Welt' : 'Halle der Helden'"></span>
                                </h3>
                                <button type="button"
                                        @click="closeSanctumGateModal()"
                                        style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;"
                                        aria-label="Schließen">&times;</button>
                            </div>

                            <div class="sf-lobby-sanctum-list">
                                <div class="sf-lobby-sanctum-row"
                                     data-testid="sanctum-check-login"
                                     :class="{ 'is-done': sanctumGateState.checks.login }">
                                    <span>Angemeldet</span>
                                    <span class="sf-lobby-sanctum-check"
                                          x-text="sanctumGateState.checks.login ? '✓' : '1'"></span>
                                </div>

                                <div class="sf-lobby-sanctum-row"
                                     data-testid="sanctum-check-order"
                                     :class="{ 'is-done': sanctumGateState.checks.order }">
                                    <span>Mindestens 1 Bestellung</span>
                                    <span class="sf-lobby-sanctum-check"
                                          x-text="sanctumGateState.checks.order ? '✓' : '2'"></span>
                                </div>

                                <div class="sf-lobby-sanctum-row"
                                     data-testid="sanctum-check-magic"
                                     :class="{ 'is-done': sanctumGateState.checks.magic }">
                                    <span>✨ Magie aktivieren</span>
                                    <span class="sf-lobby-sanctum-check"
                                          x-text="sanctumGateState.checks.magic ? '✓' : '3'"></span>
                                </div>
                            </div>

                            <div>
                                <template x-if="!sanctumGateState.checks.login">
                                    <a :href="sanctumGateState.loginUrl || '/login'"
                                       data-testid="sanctum-gate-cta-btn"
                                       class="sf-lobby-prod-cta"
                                       style="margin-top:2px;">
                                        <span>Anmelden</span>
                                        <span>&rarr;</span>
                                    </a>
                                </template>
                                <template x-if="sanctumGateState.checks.login && !sanctumGateState.checks.order">
                                    <button type="button"
                                            @click="closeSanctumGateModal()"
                                            data-testid="sanctum-gate-cta-btn"
                                            class="sf-lobby-prod-cta"
                                            style="margin-top:2px;border:none;cursor:pointer;">
                                        <span>Kollektion ansehen</span>
                                    </button>
                                </template>
                                <template x-if="sanctumGateState.checks.login && sanctumGateState.checks.order && !sanctumGateState.checks.magic">
                                    <a :href="sanctumGateState.dashboardUrl || '/dashboard'"
                                       data-testid="sanctum-gate-cta-btn"
                                       class="sf-lobby-prod-cta"
                                       style="margin-top:2px;">
                                        <span>✨ Magie aktivieren</span>
                                        <span>&rarr;</span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Helles Light-Mode Modal fuer das Epische Tor vor Magma-Raum 6 (Manufaktur-Meisterschluessel) --}}
                    <div x-show="masterKeyModalOpen"
                         x-cloak
                         @pointerdown.stop
                         @click.self="masterKeyModalOpen = false"
                         data-testid="lobby-master-key-modal"
                         class="sf-lobby-settings-backdrop">
                        <div class="sf-lobby-settings-card" role="dialog" aria-label="Manufaktur-Meisterschlüssel">
                            <div class="sf-lobby-settings-head">
                                <h3 class="sf-lobby-settings-title">
                                    <span>🔑</span>
                                    <span>Manufaktur-Meisterschlüssel</span>
                                </h3>
                                <button type="button"
                                        @click="masterKeyModalOpen = false"
                                        style="appearance:none;width:28px;height:28px;border-radius:9px;border:1px solid rgba(197,160,89,0.35);background:#FAF8F5;color:#111827;font-size:15px;font-weight:800;cursor:pointer;"
                                        aria-label="Schließen">&times;</button>
                            </div>

                            <div class="sf-lobby-sanctum-list">
                                <div class="sf-lobby-sanctum-row is-done">
                                    <span>Magma-Stufen 6 bis 10</span>
                                    <span class="sf-lobby-sanctum-check">✓</span>
                                </div>
                            </div>

                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <button type="button"
                                        @click="masterKeyModalOpen = false; closeShowroom()"
                                        class="sf-lobby-prod-cta"
                                        style="margin-top:0;border:none;cursor:pointer;">
                                    <span>Unikat im Shop gestalten</span>
                                </button>
                                <a :href="playerHud.giftVoucherUrl || '/produkt/geschenkgutschein'"
                                   class="sf-lobby-step-btn"
                                   style="text-align:center;text-decoration:none;padding:9px 14px;border-radius:12px;">
                                    15,00 € Gutschein sichern &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Kompaktes Light-Mode ESC-Einstellungsmenü (⚙️ Gamification Clean-Design, 0 Subtexte) --}}
                    <div x-show="settingsOpen"
                         x-cloak
                         @pointerdown.stop
                         @click.self="closeSettingsModal()"
                         data-testid="lobby-settings-modal"
                         class="sf-lobby-settings-backdrop">
                        <div class="sf-lobby-settings-card" role="dialog" aria-label="Einstellungen">
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
                                    <span style="font-size:11px;font-weight:800;color:#9A7738;"
                                          x-text="(userSettings.volume <= 0 || audioState.allMuted) ? 'Stumm' : (userSettings.volume + ' %')"></span>
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
                                    Weiterspielen
                                </button>
                                <button type="button"
                                        @click="closeShowroom()"
                                        class="sf-lobby-step-btn"
                                        style="padding:9px 14px;border-radius:12px;">
                                    Lobby verlassen
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Ein-/ausklappbarer Light-Mode Raum-Chat (nur in der Multiplayer-Halle der Helden) --}}
                    <div x-show="!isLoading && loadError === '' && currentZone === 'heroes' && chatOpen && focusedProduct === null"
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

                    {{-- Schlanke Schnellwahl über der Zierleiste in der Halle der Helden --}}
                    <div x-show="!isLoading && loadError === '' && currentZone === 'heroes'" class="sf-lobby-bottom-stack">
                        <div class="sf-lobby-bottom-pill">
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

                    {{-- Goldene Zierleiste am unteren Rand: Links © Mein-Seelenfunke + Mittig 5 moderne SVG-Fähigkeiten (1 bis 5) mit Hover-Tooltips --}}
                    <div x-show="!isLoading && loadError === ''"
                         @pointerdown.stop
                         data-testid="lobby-gold-crest-bar"
                         class="sf-lobby-gold-crest-bar">
                        <span class="sf-lobby-copyright">&copy; Mein-Seelenfunke</span>

                        <div data-testid="louvre-abilities-dock" class="sf-lobby-abilities-dock">
                            <button type="button"
                                    @click="triggerAbility(1)"
                                    data-testid="ability-slot-1-btn"
                                    class="sf-ability-btn"
                                    :class="{ 'is-active': abilityState.combo && abilityState.combo.active, 'is-cooldown': abilityState.combo && abilityState.combo.cooldown > 0 }"
                                    aria-label="Funken-Hieb">
                                <span class="sf-ability-svg">
                                    <svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21.5 4.5L9.5 16.5"/>
                                        <path d="M17.5 4.5H21.5V8.5"/>
                                        <path d="M6.5 14.5L13.5 21.5"/>
                                        <path d="M5 23L9.5 18.5"/>
                                        <path d="M18.5 12.5C21 15 21.5 18.8 19.2 21.5" stroke-opacity="0.62"/>
                                    </svg>
                                </span>
                                <span class="sf-ability-key" x-text="keyLabel('strike')"></span>
                                <span x-show="abilityState.combo && abilityState.combo.cooldown > 0"
                                      class="sf-ability-cd"
                                      x-text="Math.ceil(abilityState.combo.cooldown) + 's'"></span>
                                <span x-show="abilityState.combo && abilityState.combo.stage > 0"
                                      class="sf-ability-combo-badge"
                                      x-text="'x' + abilityState.combo.stage"></span>
                                <div class="sf-ability-tooltip" role="tooltip">
                                    <div class="sf-ability-tooltip-head">
                                        <span class="sf-ability-tooltip-name">Funken-Hieb</span>
                                        <span class="sf-ability-tooltip-key" x-text="'Taste ' + keyLabel('strike')"></span>
                                    </div>
                                    <div class="sf-ability-tooltip-meta">
                                        <span>0 Mana</span>
                                        <span class="sf-ability-tooltip-cd">· Kombo 1-4</span>
                                    </div>
                                    <p class="sf-ability-tooltip-desc">4-stufige Nahkampf-Kombo mit goldenem Wirbel und Finisher-Druckwelle.</p>
                                </div>
                            </button>

                            <button type="button"
                                    @click="triggerAbility(2)"
                                    data-testid="ability-slot-2-btn"
                                    class="sf-ability-btn"
                                    :class="{ 'is-active': abilityState.sprint.active, 'is-cooldown': abilityState.sprint.cooldown > 0 }"
                                    aria-label="Licht-Sprint">
                                <span class="sf-ability-svg">
                                    <svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4.5 10.5H15.5C17.7 10.5 19.2 9 19.2 7.2C19.2 5.5 17.8 4.3 16.2 4.3C14.8 4.3 13.7 5.2 13.5 6.5"/>
                                        <path d="M3.5 15H20.5C22.6 15 24.2 16.5 24.2 18.5C24.2 20.4 22.7 21.8 20.9 21.8C19.3 21.8 18.1 20.8 17.9 19.4"/>
                                        <path d="M6.5 19.5H13.5"/>
                                        <circle cx="22" cy="9.5" r="1.3" fill="currentColor" stroke="none"/>
                                    </svg>
                                </span>
                                <span class="sf-ability-key" x-text="keyLabel('sprint')"></span>
                                <span x-show="abilityState.sprint.cooldown > 0"
                                      class="sf-ability-cd"
                                      x-text="Math.ceil(abilityState.sprint.cooldown) + 's'"></span>
                                <div class="sf-ability-tooltip" role="tooltip">
                                    <div class="sf-ability-tooltip-head">
                                        <span class="sf-ability-tooltip-name">Licht-Sprint</span>
                                        <span class="sf-ability-tooltip-key" x-text="'Taste ' + keyLabel('sprint')"></span>
                                    </div>
                                    <div class="sf-ability-tooltip-meta">
                                        <span>18 Mana</span>
                                        <span class="sf-ability-tooltip-cd">· 10 s Abklingzeit</span>
                                    </div>
                                    <p class="sf-ability-tooltip-desc">Erhöht dein Lauftempo 4,5 Sekunden lang um 55 % mit goldener Funkenspur.</p>
                                </div>
                            </button>

                            <button type="button"
                                    @click="triggerAbility(3)"
                                    data-testid="ability-slot-3-btn"
                                    class="sf-ability-btn"
                                    :class="{ 'is-cooldown': abilityState.titanJump.cooldown > 0 }"
                                    aria-label="Aufwind-Impuls">
                                <span class="sf-ability-svg">
                                    <svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M14 4.5L8.2 11.2H11.8V18.5H16.2V11.2H19.8L14 4.5Z"/>
                                        <path d="M7 21.2C9.2 19.6 11.5 19 14 19C16.5 19 18.8 19.6 21 21.2"/>
                                        <path d="M9.5 24C11 23.1 12.4 22.7 14 22.7C15.6 22.7 17 23.1 18.5 24" stroke-opacity="0.65"/>
                                    </svg>
                                </span>
                                <span class="sf-ability-key" x-text="keyLabel('titanJump')"></span>
                                <span x-show="abilityState.titanJump.cooldown > 0"
                                      class="sf-ability-cd"
                                      x-text="Math.ceil(abilityState.titanJump.cooldown) + 's'"></span>
                                <div class="sf-ability-tooltip" role="tooltip">
                                    <div class="sf-ability-tooltip-head">
                                        <span class="sf-ability-tooltip-name">Aufwind-Impuls</span>
                                        <span class="sf-ability-tooltip-key" x-text="'Taste ' + keyLabel('titanJump')"></span>
                                    </div>
                                    <div class="sf-ability-tooltip-meta">
                                        <span>22 Mana</span>
                                        <span class="sf-ability-tooltip-cd">· 8 s Abklingzeit</span>
                                    </div>
                                    <p class="sf-ability-tooltip-desc">Katapultiert dich kraftvoll in die Höhe zum Überwinden weiter Schluchten und Ringe.</p>
                                </div>
                            </button>

                            <button type="button"
                                    @click="triggerAbility(4)"
                                    data-testid="ability-slot-4-btn"
                                    class="sf-ability-btn"
                                    :class="{ 'is-active': abilityState.shield.active, 'is-cooldown': abilityState.shield.cooldown > 0 }"
                                    aria-label="Aegis-Schild">
                                <span class="sf-ability-svg">
                                    <svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M14 3.8L22 7.2V13.6C22 18.6 18.6 22.5 14 24.4C9.4 22.5 6 18.6 6 13.6V7.2L14 3.8Z"/>
                                        <path d="M14 8.2L15.6 11.8L19.4 13.4L15.6 15L14 18.6L12.4 15L8.6 13.4L12.4 11.8L14 8.2Z" stroke-width="1.4"/>
                                    </svg>
                                </span>
                                <span class="sf-ability-key" x-text="keyLabel('shield')"></span>
                                <span x-show="abilityState.shield.cooldown > 0"
                                      class="sf-ability-cd"
                                      x-text="Math.ceil(abilityState.shield.cooldown) + 's'"></span>
                                <div class="sf-ability-tooltip" role="tooltip">
                                    <div class="sf-ability-tooltip-head">
                                        <span class="sf-ability-tooltip-name">Aegis-Schild</span>
                                        <span class="sf-ability-tooltip-key" x-text="'Taste ' + keyLabel('shield')"></span>
                                    </div>
                                    <div class="sf-ability-tooltip-meta">
                                        <span>25 Mana</span>
                                        <span class="sf-ability-tooltip-cd">· 15 s Abklingzeit</span>
                                    </div>
                                    <p class="sf-ability-tooltip-desc">Schützt 6,5 s vor Schockwellen und katapultiert dich bei Lava-Kontakt einmalig hoch hinaus, bevor das Schild zerbricht.</p>
                                </div>
                            </button>

                            <button type="button"
                                    @click="triggerAbility(5)"
                                    data-testid="ability-slot-5-btn"
                                    class="sf-ability-btn"
                                    :class="{ 'is-cooldown': abilityState.burst.cooldown > 0 }"
                                    aria-label="Seelen-Nova">
                                <span class="sf-ability-svg">
                                    <svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <circle cx="14" cy="14" r="3.6"/>
                                        <circle cx="14" cy="14" r="8.2" stroke-dasharray="2.6 2.6"/>
                                        <path d="M14 2.8V5.8M14 22.2V25.2M2.8 14H5.8M22.2 14H25.2M6.1 6.1L8.2 8.2M19.8 19.8L21.9 21.9M21.9 6.1L19.8 8.2M8.2 19.8L6.1 21.9"/>
                                    </svg>
                                </span>
                                <span class="sf-ability-key" x-text="keyLabel('burst')"></span>
                                <span x-show="abilityState.burst.cooldown > 0"
                                      class="sf-ability-cd"
                                      x-text="Math.ceil(abilityState.burst.cooldown) + 's'"></span>
                                <div class="sf-ability-tooltip" role="tooltip">
                                    <div class="sf-ability-tooltip-head">
                                        <span class="sf-ability-tooltip-name">Seelen-Nova</span>
                                        <span class="sf-ability-tooltip-key" x-text="'Taste ' + keyLabel('burst')"></span>
                                    </div>
                                    <div class="sf-ability-tooltip-meta">
                                        <span>30 Mana</span>
                                        <span class="sf-ability-tooltip-cd">· 20 s Abklingzeit</span>
                                    </div>
                                    <p class="sf-ability-tooltip-desc">Entfesselt eine radiale 24K-Gold-Energiewelle und löst nahe Gefahrenringe im Umkreis auf.</p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

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
                    settingsOpen: false,
                    keybindingsExpanded: false,
                    listeningKeyAction: '',
                    userSettings: {
                        volume: 50,
                        cameraSensitivity: 100,
                        useRealName: false,
                        graphicsQuality: 'high',
                    },
                    keybindings: {
                        strike: 'Digit1',
                        sprint: 'Digit2',
                        titanJump: 'Digit3',
                        shield: 'Digit4',
                        burst: 'Digit5',
                        jump: 'Space',
                        moveForward: 'KeyW',
                        moveLeft: 'KeyA',
                        moveBack: 'KeyS',
                        moveRight: 'KeyD',
                    },
                    audioState: {
                        bgmVolume: 50,
                        bgmMuted: false,
                        sfxVolume: 50,
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
                        mana: 100,
                        maxMana: 100,
                        giftVoucherUrl: '/produkt/geschenkgutschein',
                    },
                    sanctumGateOpen: false,
                    sanctumGateZone: 'heroes',
                    sanctumGateState: {
                        canEnter: false,
                        reason: 'login_required',
                        checks: {
                            login: false,
                            order: false,
                            magic: false,
                        },
                        loginUrl: '/login',
                        dashboardUrl: '/dashboard',
                    },
                    masterKeyModalOpen: false,
                    lavaChestState: {
                        level: 1,
                        rewardPreview: 5,
                        nearChest: false,
                        isAuthenticated: false,
                        claimedToday: false,
                        claiming: false,
                        canClaim: false,
                        statusMessage: '',
                    },
                    abilityState: {
                        sprint: { active: false, timer: 0, cooldown: 0, maxCd: 10 },
                        titanJump: { cooldown: 0, maxCd: 8 },
                        shield: { active: false, timer: 0, cooldown: 0, maxCd: 15 },
                        burst: { cooldown: 0, maxCd: 20 },
                        combo: { stage: 0, windowTimer: 0, active: false, cooldown: 0, maxCd: 1.8 },
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
                    _syncTimer: null,
                    _syncInFlight: false,
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
                            { key: 'strike', label: '1 · Funken-Hieb' },
                            { key: 'sprint', label: '2 · Licht-Sprint' },
                            { key: 'titanJump', label: '3 · Aufwind-Impuls' },
                            { key: 'shield', label: '4 · Aegis-Schild' },
                            { key: 'burst', label: '5 · Seelen-Nova' },
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

                    toggleSettingsModal() {
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

                    minimapTooltip() {
                        const season = this.environmentInfo.season_label || 'Herbst';
                        if (this.environmentInfo.holiday_label) {
                            return season + ' · ' + this.environmentInfo.holiday_label;
                        }
                        return season;
                    },

                    canEnterSanctums() {
                        return Boolean(this.sanctumGateState && this.sanctumGateState.canEnter);
                    },

                    openSanctumGateModal(zone, incomingState) {
                        const facade = getFacade();
                        if (facade && typeof facade.releasePointerLock === 'function') {
                            facade.releasePointerLock();
                        }
                        this.sanctumGateZone = (zone === 'lava') ? 'lava' : 'heroes';
                        const resolved = incomingState
                            || (facade && typeof facade.getSanctumAccessState === 'function' ? facade.getSanctumAccessState() : null);
                        if (resolved && typeof resolved === 'object') {
                            this.sanctumGateState = {
                                canEnter: Boolean(resolved.canEnter),
                                reason: resolved.reason || 'login_required',
                                checks: {
                                    login: Boolean(resolved.checks && resolved.checks.login),
                                    order: Boolean(resolved.checks && resolved.checks.order),
                                    magic: Boolean(resolved.checks && resolved.checks.magic),
                                },
                                loginUrl: resolved.loginUrl || '/login',
                                dashboardUrl: resolved.dashboardUrl || '/dashboard',
                            };
                        }
                        this.sanctumGateOpen = true;
                    },

                    closeSanctumGateModal() {
                        this.sanctumGateOpen = false;
                        if (privateAudio) privateAudio.playUiClick();
                    },

                    toggleQuickMute() {
                        if (privateAudio && typeof privateAudio.toggleQuickMute === 'function') {
                            this.audioState = privateAudio.toggleQuickMute();
                        }
                    },

                    claimLavaChest() {
                        const facade = getFacade();
                        if (facade && typeof facade.claimLavaChest === 'function') {
                            facade.claimLavaChest();
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
                        if (this.currentZone === 'lava') return '🌋 Magma-Welt';
                        return '🛍️ Kollektion';
                    },

                    stagesPreviewLabel() {
                        if (this.stagesPreviewMode === '1-5') return '✨ Stufen 1-5';
                        if (this.stagesPreviewMode === '6-10') return '✨ Stufen 6-10';
                        return '✨ Stufen';
                    },

                    switchZone(targetZone) {
                        const z = targetZone === 'lava'
                            ? 'lava'
                            : ((targetZone === 'heroes' || targetZone === 'hall') ? 'heroes' : 'gallery');
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

                    startLobbyMusicFromGesture() {
                        if (privateAudio && typeof privateAudio.startLobbyMusicFromGesture === 'function') {
                            this.audioState = privateAudio.startLobbyMusicFromGesture();
                            return;
                        }
                        if (typeof window !== 'undefined' && typeof window.getSeelenfunkeLobbyAudio === 'function') {
                            privateAudio = window.getSeelenfunkeLobbyAudio({ isAuthenticated: this.isCustomerAuthenticated() });
                            if (privateAudio && typeof privateAudio.startLobbyMusicFromGesture === 'function') {
                                this.audioState = privateAudio.startLobbyMusicFromGesture();
                                return;
                            }
                        }
                        try {
                            const rawExplicit = localStorage.getItem('sf_lobby_bgm_explicit_muted');
                            const rawVol = localStorage.getItem('sf_funki_audio_volume');
                            const explicitMuted = (rawExplicit === '1' || rawExplicit === 'true') || rawVol === '0';
                            sessionStorage.setItem('sf_funki_bgm_session_enabled', '1');
                            if (!explicitMuted) {
                                localStorage.setItem('sf_funki_audio_muted', '0');
                                localStorage.setItem('sf_funki_bgm_muted', '0');
                                if (window.SfFunkiBgm) {
                                    if ((window.SfFunkiBgm.volume || 0) <= 0) {
                                        window.SfFunkiBgm.setVolume(50, false);
                                    }
                                    window.SfFunkiBgm.setMuted(false, false);
                                    window.SfFunkiBgm.onModalOpen();
                                } else if (typeof Audio !== 'undefined') {
                                    const preEl = window.__sfPrewarmLobbyBgmEl || new Audio('/shop/gamification/audio/bgm_funki_game.mp3');
                                    preEl.loop = true;
                                    preEl.preload = 'auto';
                                    preEl.volume = 0.25;
                                    window.__sfPrewarmLobbyBgmEl = preEl;
                                    const p = preEl.play();
                                    if (p && typeof p.catch === 'function') p.catch(() => {});
                                }
                            }
                        } catch (e) {}
                    },

                    openShowroom(initialZone = 'gallery') {
                        // User-Gesture-Token zu 100 % synchron im Klick-Event sichern (VOR fetch / import)!
                        this.startLobbyMusicFromGesture();

                        const requestedZone = initialZone === 'lava'
                            ? 'lava'
                            : ((initialZone === 'heroes' || initialZone === 'hall') ? 'heroes' : 'gallery');
                        if (this.isOpen) {
                            this.switchZone(requestedZone);
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
                        this.settingsOpen = false;
                        this.sanctumGateOpen = false;
                        this.masterKeyModalOpen = false;
                        this.listeningKeyAction = '';
                        this.peers = [];
                        this.chatMessages = [];
                        this.unreadChatCount = 0;
                        this.chatFeedback = '';
                        this._activeSyncZone = null;
                        this._seenMessageIds = new Set();
                        this.loadFriendsList();
                        if (!this._abilityPollTimer) {
                            this._abilityPollTimer = setInterval(() => this.syncAbilityState(), 90);
                        }
                        this.prevHtmlOverflow = document.documentElement.style.overflow || '';
                        this.prevOverflow = document.body.style.overflow || '';
                        document.documentElement.style.overflow = 'hidden';
                        document.body.style.overflow = 'hidden';

                        if (!this._pageHideBound) {
                            this._pageHideBound = () => {
                                if (this.isOpen && this._activeSyncZone === 'heroes') {
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

                        const modulePromise = import('/js/seelenfunke-louvre-showroom-3d.js?v=20261117v5');

                        Promise.all([dataPromise, heroesPromise, modulePromise])
                            .then(([payload, heroesJson, mod]) => {
                                if (!this.isOpen) return;
                                privateMod = mod;
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

                                const rawPlayer = (payload.player && typeof payload.player === 'object') ? payload.player : null;
                                if (payload.environment && typeof payload.environment === 'object') {
                                    this.environmentInfo = Object.assign({}, this.environmentInfo, payload.environment);
                                }

                                const isAuth = Boolean(
                                    (rawPlayer && rawPlayer.is_authenticated)
                                    || this.isCustomerAuthenticated()
                                );
                                this.lobbyAuthenticated = isAuth;
                                this.soloMode = false;
                                this.activeIndex = 0;

                                const hasOrder = Boolean(rawPlayer && (rawPlayer.has_completed_order ?? false));
                                const magicActive = Boolean(rawPlayer && rawPlayer.gamification_active);
                                const fallbackCanEnter = Boolean(isAuth && hasOrder && magicActive);
                                const resolvedCanEnter = (rawPlayer && rawPlayer.can_enter_sanctums !== undefined)
                                    ? Boolean(rawPlayer.can_enter_sanctums && fallbackCanEnter)
                                    : fallbackCanEnter;
                                this.sanctumGateState = {
                                    canEnter: resolvedCanEnter,
                                    reason: resolvedCanEnter
                                        ? null
                                        : ((rawPlayer && rawPlayer.sanctum_block_reason)
                                            || (!isAuth ? 'login_required' : (!hasOrder ? 'order_required' : 'magic_activation_required'))),
                                    checks: {
                                        login: isAuth,
                                        order: hasOrder,
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
                                    this.audioState = privateAudio.startLobbyMusicFromGesture();
                                    if (privateAudioUnsub) privateAudioUnsub();
                                    privateAudioUnsub = privateAudio.onChange((snap) => {
                                        this.audioState = snap;
                                    });
                                }

                                this.lavaChestState = {
                                    level: 1,
                                    rewardPreview: 5,
                                    nearChest: false,
                                    isAuthenticated: isAuth,
                                    claimedToday: Boolean(payload.lava_parkour && payload.lava_parkour.claimed_today),
                                    claiming: false,
                                    canClaim: Boolean(isAuth && !(payload.lava_parkour && payload.lava_parkour.claimed_today)),
                                    statusMessage: '',
                                };

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
                                        floatingLavaChestEl: this.$refs.floatingLavaChestCard || null,
                                        floatingOuterGateEl: this.$refs.floatingOuterGateCard || null,
                                        products: this.products,
                                        environment: payload.environment || null,
                                        player: rawPlayer,
                                        heroesPayload: this.heroesPayload,
                                        playerFunki: this.funkiInfo,
                                        lavaParkour: payload.lava_parkour || null,
                                        audio: privateAudio || undefined,
                                        isAuthenticatedCustomer: isAuth,
                                        initialZone: requestedZone,
                                        soloMode: this.soloMode,
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
                                        onAuthGatePrompt: (zone, accessState) => {
                                            this.openSanctumGateModal(zone, accessState);
                                        },
                                        onMasterKeyPromptChange: (flag) => {
                                            if (flag) {
                                                const f = getFacade();
                                                if (f && typeof f.releasePointerLock === 'function') {
                                                    f.releasePointerLock();
                                                }
                                                this.masterKeyModalOpen = true;
                                            }
                                        },
                                        onPlayerHudChange: (hud) => {
                                            if (hud && typeof hud === 'object') {
                                                this.playerHud = Object.assign({}, this.playerHud, hud);
                                                if (hud.environment) {
                                                    this.environmentInfo = Object.assign({}, this.environmentInfo, hud.environment);
                                                }
                                            }
                                        },
                                        onLavaChestStateChange: (state) => {
                                            this.lavaChestState = state;
                                        },
                                        onSelectProduct: (prod) => {
                                            this.launchProductTransition(prod);
                                        }
                                    });
                                    setFacade(createdFacade);
                                    if (typeof createdFacade.getPlayerHudState === 'function') {
                                        this.playerHud = Object.assign({}, this.playerHud, createdFacade.getPlayerHudState());
                                    }
                                    if (typeof createdFacade.getSanctumAccessState === 'function') {
                                        const stAccess = createdFacade.getSanctumAccessState();
                                        this.sanctumGateState = {
                                            canEnter: Boolean(stAccess.canEnter),
                                            reason: stAccess.reason || null,
                                            checks: Object.assign({ login: false, order: false, magic: false }, stAccess.checks || {}),
                                            loginUrl: stAccess.loginUrl || '/login',
                                            dashboardUrl: stAccess.dashboardUrl || '/dashboard',
                                        };
                                    }
                                    this.currentZone = createdFacade.getCurrentZone();

                                    if (this.mutedPlayerIds.length > 0) {
                                        createdFacade.setMutedPlayers(this.mutedPlayerIds);
                                    }

                                    // Ruhiges Solo-Shopping in 'gallery'/'lava': Multiplayer-Instanz startet erst beim Betreten von 'heroes'!
                                    if (isAuth && this.canEnterSanctums() && this.currentZone === 'heroes') {
                                        this.handleZoneSyncTransition('heroes');
                                    }
                                    this.renderMinimap();
                                });
                            })
                            .catch(() => {
                                this.isLoading = false;
                                this.loadError = 'Der 3D-Showroom konnte auf diesem Gerät nicht geladen werden.';
                            });
                    },

                    handleZoneSyncTransition(nextZone) {
                        const z = String(nextZone || 'gallery').toLowerCase();
                        const facade = getFacade();

                        if (z === 'heroes') {
                            if (facade && typeof facade.refreshHeroesData === 'function') {
                                facade.refreshHeroesData(false);
                            }
                            if (this.isCustomerAuthenticated() && this.canEnterSanctums() && !this.soloMode && (this._activeSyncZone !== 'heroes' || !this._syncTimer)) {
                                this._activeSyncZone = 'heroes';
                                this.joinLobbyInstance();
                            }
                            return;
                        }

                        // Solo-Zonen ('gallery', 'lava', 'labyrinth'): lokal sofort leeren, Loop stoppen & Heldenhallen-Platz freigeben!
                        const wasInHeroes = (this._activeSyncZone === 'heroes' || Boolean(this._syncTimer));
                        this._activeSyncZone = z;
                        this.instanceMenuOpen = false;
                        this.chatOpen = false;
                        this.stopMultiplayerLoop();
                        this.peers = [];
                        if (facade) {
                            facade.updatePeers([]);
                        }

                        if (wasInHeroes && this.lobbyAuthenticated && !this.soloMode && facade) {
                            const st = facade.getHudSnapshot();
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
                                    rot_y: st.rot_y,
                                    speed: st.speed,
                                    zone: z,
                                    combo_seq: st.combo_seq,
                                    combo_stage: st.combo_stage
                                })
                            }).catch(() => {});
                        }
                    },

                    joinLobbyInstance() {
                        const facade = getFacade();
                        if (!this.isOpen || !facade || !this.isCustomerAuthenticated() || this.soloMode || this.currentZone !== 'heroes') {
                            return;
                        }
                        const st = facade.getHudSnapshot();
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
                                rot_y: st.rot_y,
                                speed: st.speed,
                                zone: 'heroes',
                                combo_seq: st.combo_seq,
                                combo_stage: st.combo_stage
                            })
                        })
                            .then((res) => res.json().catch(() => null).then((b) => {
                                if (res.status === 401 || res.status === 403) {
                                    this.stopMultiplayerLoop();
                                    const fGate = getFacade();
                                    if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                                        fGate.handleSanctumHttpResponse(res.status, b, 'heroes');
                                    }
                                    return null;
                                }
                                return res.ok ? b : null;
                            }))
                            .then((data) => {
                                if (!data || !data.ok || !this.isOpen || this.currentZone !== 'heroes') return;
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
                                const f2 = getFacade();
                                if (f2) {
                                    f2.updatePeers(this.peers);
                                }
                                if (this.lobbyAuthenticated && this.currentZone === 'heroes') {
                                    this.startMultiplayerLoop();
                                }
                            })
                            .catch(() => {});
                    },

                    startMultiplayerLoop() {
                        this.stopMultiplayerLoop();
                        if (!this.isOpen || !this.lobbyAuthenticated || this.soloMode || this.currentZone !== 'heroes') {
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
                        const facade = getFacade();
                        if (!this.isOpen || !facade || this._syncInFlight) return;
                        if (!this.lobbyAuthenticated || this.soloMode || this.currentZone !== 'heroes') {
                            this.stopMultiplayerLoop();
                            return;
                        }

                        this._syncInFlight = true;
                        const st = facade.getHudSnapshot();

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
                                rot_y: st.rot_y,
                                speed: st.speed,
                                zone: 'heroes',
                                combo_seq: st.combo_seq,
                                combo_stage: st.combo_stage
                            })
                        })
                            .then((res) => res.json().catch(() => null).then((b) => {
                                if (res.status === 401 || res.status === 403) {
                                    this.stopMultiplayerLoop();
                                    const fGate = getFacade();
                                    if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                                        fGate.handleSanctumHttpResponse(res.status, b, 'heroes');
                                    }
                                    return null;
                                }
                                return res.ok ? b : null;
                            }))
                            .then((data) => {
                                const f2 = getFacade();
                                if (!data || !data.ok || !this.isOpen || !f2 || this.currentZone !== 'heroes') return;
                                if (data.instance_id) {
                                    this.lobbyInstanceId = Number(data.instance_id) || this.lobbyInstanceId;
                                }
                                if (Array.isArray(data.instances)) {
                                    this.lobbyInstances = data.instances;
                                }
                                this.peers = Array.isArray(data.peers) ? data.peers : [];
                                f2.updatePeers(this.peers);
                            })
                            .catch(() => {})
                            .finally(() => {
                                this._syncInFlight = false;
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
                        const st = facade.getHudSnapshot();
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
                                y: st.y,
                                z: st.z,
                                rot_y: st.rot_y,
                                zone: 'heroes'
                            })
                        })
                            .then((res) => res.json().catch(() => null).then((b) => {
                                if (res.status === 401 || res.status === 403) {
                                    this.stopMultiplayerLoop();
                                    const fGate = getFacade();
                                    if (fGate && typeof fGate.handleSanctumHttpResponse === 'function') {
                                        fGate.handleSanctumHttpResponse(res.status, b, 'heroes');
                                    }
                                    return null;
                                }
                                return res.ok ? b : null;
                            }))
                            .then((data) => {
                                const f2 = getFacade();
                                if (!data || !data.ok || !this.isOpen || !f2) return;
                                if (data.instance_id) {
                                    this.lobbyInstanceId = Number(data.instance_id) || cleanTarget;
                                    this.customInstanceInput = this.lobbyInstanceId;
                                }
                                if (Array.isArray(data.instances)) {
                                    this.lobbyInstances = data.instances;
                                }
                                this.peers = Array.isArray(data.peers) ? data.peers : [];
                                f2.switchInstance(this.peers);
                                if (this.lobbyAuthenticated && !this.soloMode && this.currentZone === 'heroes') {
                                    this.startMultiplayerLoop();
                                }
                            })
                            .catch(() => {});
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
                                    const facade = getFacade();
                                    if (facade) {
                                        facade.showChatBubble(body.entry.player_id, body.entry.text);
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
                        const facade = getFacade();
                        if (facade && typeof facade.triggerEmote === 'function') {
                            facade.triggerEmote('wave');
                        }
                        this.sendChatMessage('/wave');
                    },

                    triggerAbility(slot) {
                        const facade = getFacade();
                        if (!facade || typeof facade.triggerAbility !== 'function') return;
                        facade.triggerAbility(slot);
                        this.syncAbilityState();
                    },

                    syncAbilityState() {
                        const facade = getFacade();
                        if (facade && typeof facade.getAbilityState === 'function') {
                            this.abilityState = facade.getAbilityState();
                        }
                        if (facade && typeof facade.getPlayerHudState === 'function') {
                            this.playerHud = Object.assign({}, this.playerHud, facade.getPlayerHudState());
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

                        const w = canvas.width || 180;
                        const h = canvas.height || 180;
                        const scale = w / 180;
                        const cx = w * 0.5;
                        const cy = h * 0.5;
                        const rad = w * 0.5 - 8 * scale;

                        ctx.clearRect(0, 0, w, h);
                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(cx, cy, rad + 4 * scale, 0, Math.PI * 2);
                        ctx.clip();

                        ctx.fillStyle = snap.zone === 'lava' ? '#FBF5EC' : '#FAF8F5';
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
                            if (m.type === 'chest' || m.type === 'portal') {
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
                        if (!playerId || playerId === this.multiplayer.player_id) return;
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
                        const facade = getFacade();
                        if (facade) {
                            facade.triggerJump();
                        }
                    },

                    triggerWeapon() {
                        const facade = getFacade();
                        if (facade) {
                            facade.triggerWeapon();
                            this.syncAbilityState();
                        }
                    },

                    closeShowroom() {
                        if (!this.isOpen) return;
                        this.stopMultiplayerLoop();
                        if (!this.soloMode && this._activeSyncZone === 'heroes') {
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
                        this.sanctumGateOpen = false;
                        this.masterKeyModalOpen = false;
                        this.listeningKeyAction = '';
                        this.chatOpen = false;
                        if (privateAudio && typeof privateAudio.setLobbyActive === 'function') {
                            privateAudio.setLobbyActive(false);
                        }
                        if (typeof window !== 'undefined' && window.__sfPrewarmLobbyBgmEl) {
                            try {
                                window.__sfPrewarmLobbyBgmEl.pause();
                                window.__sfPrewarmLobbyBgmEl.volume = 0;
                            } catch (e) {}
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
                        if (this.sanctumGateOpen) {
                            this.closeSanctumGateModal();
                            return;
                        }
                        if (this.masterKeyModalOpen) {
                            this.masterKeyModalOpen = false;
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
