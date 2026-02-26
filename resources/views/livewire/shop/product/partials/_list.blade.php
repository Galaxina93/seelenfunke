<div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 font-sans antialiased text-gray-300 min-h-screen">

    {{-- HEADER BEREICH --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-10 gap-6 animate-fade-in-up">
        <div>
            <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white mb-2 tracking-tight">Deine Kollektion</h1>
            <div class="flex items-center gap-4 text-gray-400">
                <p class="text-xs sm:text-sm font-medium">Verwalte deine Unikate.</p>
                <span class="px-3 py-1.5 bg-gray-950 border border-gray-800 shadow-inner rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-500">
                    {{ count($products) }} Produkte
                </span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
            {{-- SUCHE --}}
            <div class="relative w-full sm:w-72 group">
                <input type="text" wire:model.live="search" placeholder="Produkt suchen..."
                       class="w-full pl-12 pr-4 py-3.5 bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-[1.5rem] text-sm text-white placeholder-gray-600 focus:bg-gray-950 focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all shadow-inner outline-none">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-600 group-focus-within:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- NEU BUTTON --}}
            <button wire:click="createDraft"
                    class="bg-primary border border-primary/50 text-gray-900 px-6 py-3.5 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-primary-dark hover:scale-[1.02] transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                <span class="text-sm leading-none">+</span> Neu
            </button>
        </div>
    </div>

    {{-- EMPTY STATE --}}
    @if($products->isEmpty())
        <div class="bg-gray-900/50 backdrop-blur-md p-16 rounded-[3rem] border-2 border-dashed border-gray-800 text-center shadow-inner animate-fade-in-up">
            <div class="w-20 h-20 rounded-[1.5rem] bg-gray-950 border border-gray-800 flex items-center justify-center mx-auto mb-5 shadow-inner">
                <svg class="w-10 h-10 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h3 class="text-xl font-serif font-bold text-white mb-2 tracking-wide">Keine Produkte gefunden</h3>
            <p class="text-xs text-gray-500 mb-6">Starte jetzt und füge dein erstes Unikat hinzu.</p>
            <button wire:click="createDraft" class="text-[10px] font-black uppercase tracking-widest text-primary hover:text-white transition-colors border-b border-primary/30 hover:border-white pb-0.5">
                Erstelle das Erste
            </button>
        </div>
    @else
        {{-- Threshold laden --}}
        @php
            $threshold = \App\Models\ShopSetting::where('key', 'inventory_low_stock_threshold')->value('value') ?? 5;
        @endphp

        {{-- PRODUKT GRID --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 sm:gap-8 animate-fade-in-up" style="animation-delay: 100ms;">
            @foreach($products as $prod)
                {{-- Logik für Steps berechnen --}}
                @php
                    $isPhysical = $prod->type === 'physical';
                    $maxSteps = $isPhysical ? 4 : 3;
                    $percentComplete = min(100, ($prod->completion_step / $maxSteps) * 100);
                    $isComplete = $prod->completion_step >= $maxSteps;
                @endphp

                <div class="bg-gray-900/80 backdrop-blur-md p-5 sm:p-6 rounded-[2.5rem] border border-gray-800 shadow-2xl hover:border-gray-700 hover:shadow-[0_0_40px_rgba(0,0,0,0.6)] transition-all duration-300 flex flex-col h-full relative overflow-hidden group">

                    {{-- Fortschrittsbalken Oben --}}
                    <div class="absolute top-0 left-0 w-full h-1 bg-gray-950 z-30">
                        <div class="h-full transition-all duration-500 {{ $isComplete ? 'bg-primary shadow-[0_0_10px_rgba(197,160,89,0.8)]' : 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.8)]' }}" style="width: {{ $percentComplete }}%"></div>
                    </div>

                    {{-- Status Switcher --}}
                    <div class="absolute top-6 right-6 z-20" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" class="px-3.5 py-2 text-[9px] font-black uppercase tracking-widest rounded-xl border shadow-lg flex items-center gap-2 transition-all backdrop-blur-md {{ $prod->status == 'active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30' : 'bg-gray-950/80 text-gray-400 border-gray-700' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $prod->status == 'active' ? 'bg-emerald-500 shadow-[0_0_8px_currentColor] animate-pulse' : 'bg-gray-500' }}"></span>
                            {{ $prod->status == 'active' ? 'Aktiv' : 'Entwurf' }}
                            <svg class="w-3.5 h-3.5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div x-show="open" class="absolute right-0 mt-2 w-36 bg-gray-950 rounded-2xl shadow-[0_0_30px_rgba(0,0,0,0.8)] border border-gray-800 overflow-hidden z-30" style="display: none;" x-transition>
                            <button wire:click="updateStatus('{{ $prod->id }}', 'active'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-900 text-emerald-400 border-b border-gray-800 transition-colors flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktiv
                            </button>
                            <button wire:click="updateStatus('{{ $prod->id }}', 'draft'); open = false" class="w-full text-left px-5 py-3.5 text-[10px] font-black uppercase tracking-widest hover:bg-gray-900 text-gray-500 transition-colors flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-500"></span> Entwurf
                            </button>
                        </div>
                    </div>

                    {{-- Bild mit Schleifen --}}
                    <div class="aspect-square bg-gray-950 rounded-[1.5rem] mb-6 overflow-hidden relative mt-2 border border-gray-800 shadow-inner group-hover:border-gray-700 transition-colors">

                        {{-- BLAUE SCHLEIFE: DIGITAL --}}
                        @if($prod->type === 'digital')
                            <div class="absolute top-7 -left-12 z-10 pointer-events-none">
                                <div class="bg-blue-600/90 backdrop-blur-sm text-white text-[9px] font-black py-1.5 w-48 transform -rotate-45 shadow-[0_0_15px_rgba(37,99,235,0.5)] border-b border-blue-400/50 uppercase tracking-widest text-center">
                                    Digital
                                </div>
                            </div>
                        @endif

                        {{-- ORANGE SCHLEIFE: DIENSTLEISTUNG --}}
                        @if($prod->type === 'service')
                            <div class="absolute top-7 -left-12 z-10 pointer-events-none">
                                <div class="bg-orange-500/90 backdrop-blur-sm text-gray-900 text-[9px] font-black py-1.5 w-48 transform -rotate-45 shadow-[0_0_15px_rgba(249,115,22,0.5)] border-b border-orange-300 uppercase tracking-widest text-center">
                                    Service
                                </div>
                            </div>
                        @endif

                        @if(!empty($prod->media_gallery[0]))
                            @if(isset($prod->media_gallery[0]['type']) && $prod->media_gallery[0]['type'] == 'video')
                                <video src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity"></video>
                            @else
                                <img src="{{ asset('storage/'. (is_array($prod->media_gallery[0]) ? $prod->media_gallery[0]['path'] : $prod->media_gallery[0])) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 opacity-80 group-hover:opacity-100">
                            @endif
                        @else
                            <div class="flex items-center justify-center h-full text-gray-700 text-[10px] flex-col gap-3 font-black uppercase tracking-widest">
                                <svg class="w-10 h-10 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Kein Bild</span>
                            </div>
                        @endif
                    </div>

                    <h3 class="font-serif text-lg md:text-xl font-bold text-white truncate mb-1.5 tracking-wide">{{ $prod->name }}</h3>
                    <p class="text-sm md:text-base text-primary font-mono font-bold mb-6 drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]">{{ number_format($prod->price / 100, 2, ',', '.') }} €</p>

                    {{-- Lagerbestand Anzeige --}}
                    <div class="mb-6 h-8 flex items-center"
                         x-data="{
                             editing: false,
                             qty: {{ $prod->quantity }}
                         }">

                        @if($prod->track_quantity)
                            {{-- ANZEIGE MODUS --}}
                            <div x-show="!editing"
                                 @click="editing = true; $nextTick(() => $refs.qtyInput.focus())"
                                 class="cursor-pointer group/qty relative transition-transform hover:scale-[1.02]">

                                @if($prod->quantity <= 0)
                                    {{-- Ausverkauft / Voll --}}
                                    <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-red-500/10 text-red-400 border border-red-500/30 shadow-inner group-hover/qty:border-red-400 transition-colors">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 shadow-[0_0_8px_currentColor]"></span>
                                        {{ $prod->type === 'service' ? 'Ausgebucht' : 'Ausverkauft' }}
                                        <svg class="w-3.5 h-3.5 ml-1 text-red-500 opacity-50 group-hover/qty:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                @elseif($prod->quantity < $threshold)
                                    {{-- Kritischer Bestand --}}
                                    <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-400 border border-amber-500/30 shadow-inner group-hover/qty:border-amber-400 transition-colors">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse shadow-[0_0_8px_currentColor]"></span>
                                        <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'Stk.' }} (Knapp)
                                        <svg class="w-3.5 h-3.5 ml-1 text-amber-500 opacity-50 group-hover/qty:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                @else
                                    {{-- OK --}}
                                    <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 shadow-inner group-hover/qty:border-emerald-400 transition-colors">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_currentColor]"></span>
                                        <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'auf Lager' }}
                                        <svg class="w-3.5 h-3.5 ml-1 text-emerald-500 opacity-50 group-hover/qty:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                @endif
                            </div>

                            {{-- EDITIER MODUS --}}
                            <div x-show="editing" style="display: none;" class="flex items-center gap-2" @click.outside="editing = false; qty = {{ $prod->quantity }}">
                                <input x-ref="qtyInput"
                                       type="number"
                                       x-model="qty"
                                       class="w-20 px-3 py-1.5 text-xs font-bold text-center border border-primary bg-gray-950 text-white rounded-xl focus:ring-2 focus:ring-primary/50 outline-none shadow-inner"
                                       @keydown.enter="$wire.updateStock('{{ $prod->id }}', qty); editing = false"
                                       @keydown.escape="editing = false; qty = {{ $prod->quantity }}">

                                <button @click="$wire.updateStock('{{ $prod->id }}', qty); editing = false" class="bg-primary text-gray-900 p-2 rounded-xl hover:bg-primary-dark transition-colors shadow-[0_0_10px_rgba(197,160,89,0.3)]">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        @else
                            {{-- Unbegrenzt --}}
                            <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-500 border border-blue-500/30 cursor-not-allowed opacity-60 shadow-inner" title="Unbegrenzter Bestand">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Unbegrenzt
                            </span>
                        @endif
                    </div>

                    {{-- Footer Steps (Dynamisch) --}}
                    <div class="mt-auto pt-5 border-t border-gray-800 flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
                        <span class="{{ $isComplete ? 'text-primary drop-shadow-[0_0_5px_currentColor]' : 'text-red-400' }}">
                            @if($isComplete)
                                <span class="flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Fertig</span>
                            @else
                                Schritt {{ $prod->completion_step }}/{{ $maxSteps }}
                            @endif
                        </span>
                        <button wire:click="edit('{{ $prod->id }}')" class="text-gray-500 hover:text-white transition-colors border-b border-gray-600 hover:border-white pb-0.5">
                            Bearbeiten &rarr;
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
