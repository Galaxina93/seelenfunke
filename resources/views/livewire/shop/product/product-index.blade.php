<div>
    <div class="bg-gray-50 min-h-screen pb-20">

        {{-- Header / Hero Section --}}
        <div class="bg-gray-900 border-b border-gray-800 py-24 text-center relative overflow-hidden">
            <div class="absolute inset-0 bg-primary/10 blur-[100px] rounded-full w-[500px] h-[500px] mx-auto opacity-30 pointer-events-none"></div>
            <div class="relative z-10">
                <span class="text-primary font-bold uppercase tracking-[0.2em] text-xs mb-4 block">Handveredelt in Gifhorn</span>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-serif font-bold text-white mb-6">
                    Unsere Kollektion
                </h1>
                <p class="text-gray-400 max-w-2xl mx-auto text-lg leading-relaxed px-4">
                    Entdecke Unikate, die deine Seele berühren. Jedes Stück wird mit Liebe, modernster Technik und Sorgfalt für dich personalisiert.
                </p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 relative z-20">

            {{-- Filter & Search Bar --}}
            <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-4 md:p-6 mb-12">
                <div class="flex flex-col lg:flex-row gap-6 justify-between items-center">

                    {{-- Type Filter (Pills) --}}
                    <div class="flex bg-gray-50 p-1.5 rounded-xl border border-gray-100 w-full lg:w-auto overflow-x-auto no-scrollbar">
                        <button wire:click="$set('filterType', 'all')" class="flex-1 lg:flex-none flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'all' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                            Alle
                        </button>
                        <button wire:click="$set('filterType', 'physical')" class="flex-1 lg:flex-none flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'physical' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                            <svg class="w-4 h-4 {{ $filterType === 'physical' ? 'text-primary' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            Physisch
                        </button>
                        <button wire:click="$set('filterType', 'digital')" class="flex-1 lg:flex-none flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'digital' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                            <svg class="w-4 h-4 {{ $filterType === 'digital' ? 'text-blue-500' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            Digital
                        </button>
                        <button wire:click="$set('filterType', 'service')" class="flex-1 lg:flex-none flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'service' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">
                            <svg class="w-4 h-4 {{ $filterType === 'service' ? 'text-orange-500' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            Service
                        </button>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto flex-1">
                        {{-- Kategorie Dropdown --}}
                        <div class="relative w-full sm:w-1/3">
                            <select wire:model.live="filterCategory" class="w-full pl-4 pr-10 py-3.5 bg-gray-50 border-transparent rounded-xl text-sm font-bold text-gray-700 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all cursor-pointer appearance-none">
                                <option value="">Alle Kategorien</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>

                        {{-- Suche --}}
                        <div class="relative w-full sm:w-2/3 group">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Suche nach Name oder Eigenschaft..." class="w-full pl-12 pr-4 py-3.5 bg-gray-50 border-transparent rounded-xl text-sm font-medium text-gray-900 focus:bg-white focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all placeholder-gray-400">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-primary transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>

                            @if($search !== '')
                                <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Aktive Filter anzeigen (Nur wenn etwas ausgewählt ist) --}}
                @if($search !== '' || $filterType !== 'all' || $filterCategory !== '')
                    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-3 animate-fade-in">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest mr-2">Aktive Filter:</span>

                        @if($search !== '')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">
                                "{{ $search }}"
                                <button wire:click="$set('search', '')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterType !== 'all')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary-dark text-xs font-bold uppercase tracking-wider">
                                Typ: {{ match($filterType) { 'physical' => 'Physisch', 'digital' => 'Digital', 'service' => 'Service', default => $filterType } }}
                                <button wire:click="$set('filterType', 'all')" class="hover:text-red-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        @if($filterCategory !== '')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-900 text-white text-xs font-medium">
                                Kategorie: {{ $categories->firstWhere('id', $filterCategory)->name ?? 'Unbekannt' }}
                                <button wire:click="$set('filterCategory', '')" class="hover:text-red-400"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </span>
                        @endif

                        <button wire:click="resetFilters" class="text-xs font-bold text-red-500 hover:underline ml-auto">Alle Filter löschen</button>
                    </div>
                @endif
            </div>

            {{-- Produkt Grid --}}
            <div wire:loading.class="opacity-50 blur-sm pointer-events-none" class="transition-all duration-300">
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 lg:gap-8">
                        @foreach($products as $product)
                            @php
                                $isSoldOut = ($product->track_quantity && $product->quantity <= 0 && !$product->continue_selling_when_out_of_stock);
                                $isBackorder = ($product->track_quantity && $product->quantity <= 0 && $product->continue_selling_when_out_of_stock);
                                $lowStock = ($product->track_quantity && $product->quantity > 0 && $product->quantity <= 5);
                            @endphp

                            <div class="group relative flex flex-col h-full bg-white rounded-[2rem] p-3 border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-500 hover:border-primary/30 {{ $isSoldOut ? 'opacity-80' : '' }}">

                                {{-- Bild Container --}}
                                <div class="aspect-square bg-gray-50 rounded-3xl overflow-hidden mb-5 relative border border-gray-100">

                                    {{-- Product Type Badges --}}
                                    @if(!$isSoldOut && !$isBackorder)
                                        <div class="absolute top-3 right-3 z-30 flex flex-col gap-2 pointer-events-none">
                                            @if($product->type === 'digital')
                                                <div class="bg-blue-600/90 backdrop-blur text-white text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                                    Digital
                                                </div>
                                            @endif
                                            @if($product->type === 'service')
                                                <div class="bg-orange-500/90 backdrop-blur text-white text-[9px] font-black px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1.5">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                                    Service
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    <a href="{{ route('product.show', $product->slug) }}" class="block w-full h-full">

                                        <div class="w-full h-full {{ $isSoldOut ? 'grayscale opacity-50' : '' }}">
                                            @if(is_array($product->media_gallery) && count($product->media_gallery) > 0)
                                                @if(isset($product->media_gallery[0]['type']) && $product->media_gallery[0]['type'] === 'video')
                                                    <video src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}" class="w-full h-full object-cover" muted autoplay loop playsinline></video>
                                                @else
                                                    <img src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}"
                                                         alt="{{ $product->name }}"
                                                         class="w-full h-full object-cover transition-transform duration-1000 ease-out {{ !$isSoldOut ? 'group-hover:scale-110' : '' }}">
                                                @endif
                                            @elseif($product->preview_image_path)
                                                <img src="{{ asset('storage/'.$product->preview_image_path) }}"
                                                     class="w-full h-full object-cover transition-transform duration-1000 ease-out {{ !$isSoldOut ? 'group-hover:scale-110' : '' }}">
                                            @else
                                                <div class="flex items-center justify-center h-full text-gray-300">
                                                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                </div>
                                            @endif
                                        </div>

                                        @if(!$isSoldOut)
                                            <div class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
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
                                                    <div class="absolute top-[25%] -right-[35%] w-[160%] bg-gray-900/95 backdrop-blur text-white text-[9px] font-black py-2 shadow-xl transform rotate-45 uppercase tracking-[0.15em] border-y border-white/10 text-center">
                                                        Vorbestellbar
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Neu & Sale Badges --}}
                                    <div class="absolute top-3 left-3 flex flex-col gap-2 pointer-events-none z-20">
                                        @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                            <span class="bg-red-500 text-white text-[10px] font-black px-3 py-1.5 rounded-full shadow-md uppercase tracking-wider">Sale</span>
                                        @endif
                                        @if(!$isSoldOut && $product->created_at->diffInDays() < 21)
                                            <span class="bg-white/95 backdrop-blur text-gray-900 text-[10px] font-black px-3 py-1.5 rounded-full shadow-md uppercase tracking-wider border border-gray-100">Neu</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Infos --}}
                                <div class="flex-1 flex flex-col px-3 pb-3 relative">

                                    {{-- Kategorien des Produkts (als kleine Tags) --}}
                                    @if($product->categories->isNotEmpty())
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            @foreach($product->categories->take(2) as $pCat)
                                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest bg-gray-50 px-2 py-0.5 rounded-md">{{ $pCat->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <h3 class="text-lg font-serif font-bold {{ $isSoldOut ? 'text-gray-400' : 'text-gray-900 group-hover:text-primary transition-colors' }} mb-1 leading-tight line-clamp-2">
                                        <a href="{{ route('product.show', $product->slug) }}">
                                            {{ $product->name }}
                                        </a>
                                    </h3>

                                    @if($lowStock && !$isSoldOut)
                                        <div class="mb-2">
                                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black text-orange-600 bg-orange-50 px-2 py-1 rounded border border-orange-100 uppercase tracking-widest animate-pulse">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Nur noch {{ $product->quantity }}
                                            </span>
                                        </div>
                                    @endif

                                    <div class="mt-auto pt-4 flex items-end justify-between">
                                        <div class="flex flex-col">
                                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mb-0.5">
                                                @if($product->tierPrices->isNotEmpty())
                                                    Ab
                                                @else
                                                    Preis
                                                @endif
                                            </span>
                                            <div class="flex items-baseline gap-2">
                                                <span class="{{ $isSoldOut ? 'text-gray-400' : 'text-gray-900' }} font-bold text-xl font-serif">
                                                    {{ number_format($product->price / 100, 2, ',', '.') }} €
                                                </span>
                                                @if(!$isSoldOut && $product->compare_at_price > $product->price)
                                                    <span class="text-xs text-gray-400 line-through font-medium">
                                                        {{ number_format($product->compare_at_price / 100, 2, ',', '.') }} €
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- NEU: RECHTLICHE PAngV ANGABE (Sehr wichtig) --}}
                                            <span class="text-[8px] text-gray-400 font-medium leading-tight mt-1">
                                                inkl. MwSt. <a href="{{ route('versand') }}" target="_blank" class="hover:text-gray-600 underline">zzgl. Versand</a>
                                            </span>
                                        </div>

                                        {{-- Circle Action Button --}}
                                        <a href="{{ route('product.show', $product->slug) }}" class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 transform group-hover:scale-110 {{ $isSoldOut ? 'bg-gray-100 text-gray-400' : 'bg-gray-900 text-white hover:bg-primary shadow-lg' }}">
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
</div>
