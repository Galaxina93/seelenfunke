<div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row justify-between items-end mb-10 gap-4">
        <div>
            <h1 class="text-4xl font-serif font-bold text-gray-900 mb-2">Deine Kollektion</h1>
            <div class="flex items-center gap-4 text-gray-500">
                <p>Verwalte deine Unikate.</p>
                <span class="px-2 py-0.5 bg-gray-200 rounded text-xs font-bold">{{ count($products) }} Produkte</span>
            </div>
        </div>

        <div class="flex gap-4 w-full md:w-auto">
            <div class="relative w-full md:w-64">
                <input type="text" wire:model.live="search" placeholder="Produkt suchen..." class="w-full pl-10 pr-4 py-3 rounded-full border-0 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary text-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <button wire:click="createDraft" class="bg-primary text-white px-6 py-3 rounded-full font-semibold shadow-lg shadow-primary/30 hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 flex items-center gap-2 whitespace-nowrap">
                <span>+</span> Neu
            </button>
        </div>
    </div>

    @if($products->isEmpty())
        <div class="bg-white p-16 rounded-2xl border-2 border-dashed border-gray-300 text-center">
            <h3 class="text-xl font-serif text-gray-900 mb-2">Keine Produkte gefunden</h3>
            <button wire:click="createDraft" class="text-primary font-bold hover:underline">Erstelle das Erste</button>
        </div>
    @else
        {{-- Threshold laden --}}
        @php
            $threshold = \App\Models\ShopSetting::where('key', 'inventory_low_stock_threshold')->value('value') ?? 5;
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($products as $prod)
                {{-- Logik für Steps berechnen --}}
                @php
                    $isPhysical = $prod->type === 'physical';
                    $maxSteps = $isPhysical ? 4 : 3;
                    // Prozent berechnen, max 100%
                    $percentComplete = min(100, ($prod->completion_step / $maxSteps) * 100);
                    // Ist fertig?
                    $isComplete = $prod->completion_step >= $maxSteps;
                @endphp

                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition flex flex-col h-full relative overflow-hidden group">

                    {{-- Fortschrittsbalken Oben --}}
                    <div class="absolute top-0 left-0 w-full h-1 bg-gray-100 z-30">
                        <div class="h-full transition-all duration-500 {{ $isComplete ? 'bg-primary' : 'bg-red-500' }}" style="width: {{ $percentComplete }}%"></div>
                    </div>

                    {{-- Status Switcher --}}
                    <div class="absolute top-4 right-4 z-20" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" class="px-3 py-1 text-xs font-bold rounded-full border shadow-sm flex items-center gap-1 transition-colors {{ $prod->status == 'active' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-50 text-gray-600 border-gray-200' }}">
                            <span class="w-2 h-2 rounded-full {{ $prod->status == 'active' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                            {{ $prod->status == 'active' ? 'Aktiv' : 'Entwurf' }}
                            <svg class="w-3 h-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div x-show="open" class="absolute right-0 mt-2 w-32 bg-white rounded-lg shadow-xl border border-gray-100 overflow-hidden z-30" style="display: none;">
                            <button wire:click="updateStatus('{{ $prod->id }}', 'active'); open = false" class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 text-green-700 font-bold">Aktiv</button>
                            <button wire:click="updateStatus('{{ $prod->id }}', 'draft'); open = false" class="w-full text-left px-4 py-2 text-xs hover:bg-gray-50 text-gray-600">Entwurf</button>
                        </div>
                    </div>

                    {{-- Bild mit Schleifen --}}
                    <div class="aspect-square bg-gray-50 rounded-xl mb-4 overflow-hidden relative mt-2 border border-gray-100">
                        {{-- BLAUE SCHLEIFE: DIGITAL --}}
                        @if($prod->type === 'digital')
                            <div class="absolute top-6 -left-12 z-10 pointer-events-none">
                                <div class="bg-blue-600 text-white text-[10px] font-bold py-1 w-48 transform -rotate-45 shadow-md border-b border-blue-800 uppercase tracking-widest text-center">
                                    Digital
                                </div>
                            </div>
                        @endif

                        {{-- ORANGE SCHLEIFE: DIENSTLEISTUNG --}}
                        @if($prod->type === 'service')
                            <div class="absolute top-6 -left-12 z-10 pointer-events-none">
                                <div class="bg-orange-500 text-white text-[10px] font-bold py-1 w-48 transform -rotate-45 shadow-md border-b border-orange-700 uppercase tracking-widest text-center">
                                    Service
                                </div>
                            </div>
                        @endif

                        @if(!empty($prod->media_gallery[0]))
                            @if(isset($prod->media_gallery[0]['type']) && $prod->media_gallery[0]['type'] == 'video')
                                <video src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover"></video>
                            @else
                                <img src="{{ asset('storage/'. (is_array($prod->media_gallery[0]) ? $prod->media_gallery[0]['path'] : $prod->media_gallery[0])) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                            @endif
                        @else
                            <div class="flex items-center justify-center h-full text-gray-300 text-sm flex-col gap-2">
                                <svg class="w-8 h-8 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Kein Bild</span>
                            </div>
                        @endif
                    </div>

                    <h3 class="font-serif text-lg font-bold text-gray-900 truncate mb-1">{{ $prod->name }}</h3>
                    <p class="text-sm text-gray-500 font-mono mb-6">{{ number_format($prod->price / 100, 2, ',', '.') }} €</p>

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
                                 class="cursor-pointer group relative transition-all hover:scale-105">

                                @if($prod->quantity <= 0)
                                    {{-- Ausverkauft / Voll --}}
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-100 hover:border-red-300 hover:bg-red-100 transition">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                        {{ $prod->type === 'service' ? 'Ausgebucht' : 'Ausverkauft' }}
                                        <svg class="w-3 h-3 ml-1 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                @elseif($prod->quantity < $threshold)
                                    {{-- Kritischer Bestand --}}
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100 hover:border-amber-300 hover:bg-amber-100 transition">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'Stk.' }} (Knapp)
                                        <svg class="w-3 h-3 ml-1 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                @else
                                    {{-- OK --}}
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100 hover:border-green-300 hover:bg-green-100 transition">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                        <span x-text="qty"></span> {{ $prod->type === 'service' ? 'Plätze' : 'auf Lager' }}
                                        <svg class="w-3 h-3 ml-1 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </span>
                                @endif
                            </div>

                            {{-- EDITIER MODUS --}}
                            <div x-show="editing" style="display: none;" class="flex items-center gap-2" @click.outside="editing = false; qty = {{ $prod->quantity }}">
                                <input x-ref="qtyInput"
                                       type="number"
                                       x-model="qty"
                                       class="w-20 px-2 py-1 text-xs font-bold text-center border border-primary rounded-lg focus:ring-2 focus:ring-primary/20 outline-none"
                                       @keydown.enter="$wire.updateStock('{{ $prod->id }}', qty); editing = false"
                                       @keydown.escape="editing = false; qty = {{ $prod->quantity }}">

                                <button @click="$wire.updateStock('{{ $prod->id }}', qty); editing = false" class="bg-primary text-white p-1 rounded-md hover:bg-primary-dark">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </button>
                            </div>
                        @else
                            {{-- Unbegrenzt --}}
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100 cursor-not-allowed opacity-70" title="Unbegrenzter Bestand">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Unbegrenzt
                            </span>
                        @endif
                    </div>

                    {{-- Footer Steps (Dynamisch) --}}
                    <div class="mt-auto pt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                        <span class="font-bold {{ $isComplete ? 'text-primary' : 'text-red-500' }}">
                            @if($isComplete)
                                <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Fertig</span>
                            @else
                                Schritt {{ $prod->completion_step }}/{{ $maxSteps }}
                            @endif
                        </span>
                        <button wire:click="edit('{{ $prod->id }}')" class="text-gray-900 hover:text-primary font-bold hover:underline transition-colors">
                            Bearbeiten &rarr;
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
