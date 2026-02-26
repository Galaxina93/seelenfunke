<div class="w-full lg:w-1/2 h-1/2 lg:h-full overflow-y-auto bg-gray-950/30 custom-scrollbar z-10 lg:border-r border-gray-800">
    <div class="p-6 md:p-8 space-y-8">

        {{-- 1. EXPRESS ALERT --}}
        @if($model->is_express)
            <div class="bg-gradient-to-r from-red-500/10 to-transparent border-l-4 border-red-500 p-5 rounded-r-2xl shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-red-500/20 text-red-400 rounded-full animate-pulse shadow-[0_0_15px_rgba(239,68,68,0.2)] shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-red-400 font-black uppercase tracking-widest text-xs drop-shadow-[0_0_8px_currentColor]">Express Auftrag</h3>
                        @if($model->deadline)
                            <p class="text-white text-sm font-bold mt-1 tracking-wide">
                                🏁 Deadline: <span class="text-red-400">{{ \Carbon\Carbon::parse($model->deadline)->format('d.m.Y') }}</span>
                            </p>
                        @else
                            <p class="text-gray-400 text-xs mt-1 font-medium">Bitte priorisiert bearbeiten (FIFO beachten).</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- 2. KUNDENDATEN --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            {{-- Rechnungsadresse --}}
            <div class="bg-gray-900/50 backdrop-blur-md p-6 rounded-3xl border border-gray-800 shadow-inner hover:border-primary/30 transition-colors">
                <h3 class="text-[9px] font-black uppercase text-gray-500 mb-4 tracking-widest flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Kunde / Rechnung
                </h3>
                <div class="text-sm text-gray-300 leading-relaxed font-medium">
                    <span class="font-bold text-white text-base block mb-1">{{ $billing['name'] }}</span>
                    @if(!empty($billing['company'])) <span class="block text-primary mb-1">{{ $billing['company'] }}</span> @endif
                    {{ $billing['address'] }}<br>
                    {{ $billing['city_zip'] }}<br>
                    <span class="uppercase text-[10px] font-black tracking-wider text-gray-500 mt-1 block">{{ $billing['country'] }}</span>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-800/50">
                    <a href="mailto:{{ $billing['email'] }}" class="text-xs font-bold text-primary hover:text-white transition-colors flex items-center gap-2 group">
                        <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ $billing['email'] }}
                    </a>
                </div>
            </div>

            {{-- Lieferadresse (Nur Order) --}}
            @if($isOrder)
                @php $isDifferent = $shipping && serialize($model->billing_address) !== serialize($model->shipping_address); @endphp
                <div @class(['p-6 rounded-3xl border transition-colors shadow-inner backdrop-blur-md', $isDifferent ? 'bg-amber-900/10 border-amber-500/30' : 'bg-gray-900/50 border-gray-800'])>
                    <h3 class="text-[9px] font-black uppercase text-gray-500 mb-4 tracking-widest flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                        Lieferung
                        @if($isDifferent) <span class="ml-auto text-[9px] bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded-md font-black uppercase tracking-widest shadow-[0_0_10px_rgba(245,158,11,0.1)]">Abweichend</span> @endif
                    </h3>
                    <div class="text-sm text-gray-300 leading-relaxed font-medium">
                        @if($shipping)
                            <span class="font-bold text-white block mb-1">{{ $shipping['first_name'] }} {{ $shipping['last_name'] }}</span>
                            @if(!empty($shipping['company'])) <span class="text-amber-400 block mb-1">{{ $shipping['company'] }}</span> @endif
                            {{ $shipping['address'] }}<br>
                            {{ $shipping['postal_code'] }} {{ $shipping['city'] }}<br>
                            <span class="uppercase text-[10px] font-black tracking-wider text-gray-500 mt-1 block">{{ $shipping['country'] }}</span>
                        @else
                            <span class="italic text-gray-500 flex items-center gap-2 mt-3 text-xs">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                Identisch mit Rechnung
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- 3. POSITIONEN --}}
        <div>
            <h3 class="font-serif font-bold text-white text-xl mb-5 flex items-center justify-between tracking-tight">
                <span>Bestellpositionen</span>
                <span class="text-[10px] font-sans font-black uppercase tracking-widest text-primary bg-primary/10 border border-primary/20 px-3 py-1.5 rounded-full shadow-[0_0_15px_rgba(197,160,89,0.15)]">{{ count($model->items) }} Artikel</span>
            </h3>

            <div class="space-y-4">
                @foreach($model->items as $item)
                    {{-- TYPE CHECK --}}
                    @php
                        $productType = $item->product->type ?? 'physical';
                        $isService = $productType === 'service';
                        $isDigital = $productType === 'digital';
                    @endphp

                    <div wire:click="selectItemForPreview('{{ $item->id }}')"
                         class="cursor-pointer border rounded-[2rem] p-4 transition-all duration-300 relative overflow-hidden group
                                {{ $selectedItemId == $item->id ? 'border-primary shadow-[0_0_30px_rgba(197,160,89,0.15)] bg-primary/5' : 'border-gray-800 bg-gray-900/50 hover:border-gray-600 hover:bg-gray-900 shadow-inner' }}"
                    >
                        <div class="flex gap-5">
                            {{-- Bild --}}
                            <div class="h-20 w-20 bg-gray-950 rounded-2xl border border-gray-800 overflow-hidden shrink-0 relative shadow-inner">
                                @php
                                    $conf = $item->configuration;
                                    $imgPath = $conf['preview_file'] ?? ($conf['logo_storage_path'] ?? ($item->product->preview_image_path ?? null));
                                @endphp
                                @if($imgPath)
                                    <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full object-cover group-hover:scale-110 transition-transform duration-700">
                                @else
                                    <div class="h-full w-full flex items-center justify-center text-gray-700"><svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                @endif

                                {{-- Type Badge auf Bild --}}
                                @if($isService)
                                    <div class="absolute inset-0 bg-black/70 flex items-center justify-center text-white text-[9px] font-black uppercase tracking-widest backdrop-blur-sm">Service</div>
                                @elseif($isDigital)
                                    <div class="absolute inset-0 bg-blue-900/70 flex items-center justify-center text-blue-300 text-[9px] font-black uppercase tracking-widest backdrop-blur-sm">Digital</div>
                                @endif
                            </div>

                            {{-- Infos --}}
                            <div class="flex-1 min-w-0 flex flex-col justify-center">
                                <div class="flex justify-between items-start gap-4">
                                    <h4 class="font-bold text-white text-base truncate">{{ $item->product_name }}</h4>
                                    <span class="font-mono font-bold text-primary text-base whitespace-nowrap">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</span>
                                </div>
                                <p class="text-[11px] text-gray-500 font-medium mt-1 uppercase tracking-wider">{{ $item->quantity }}x á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</p>

                                {{-- Config Hints --}}
                                @if(!empty($conf))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if(!empty($conf['text']))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-gray-800 text-gray-400 border border-gray-700 shadow-sm">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Text
                                            </span>
                                        @endif
                                        @if(!empty($conf['files']) || !empty($conf['logo_storage_path']))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-400 border border-blue-500/30 shadow-sm">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Dateien
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Arrow Indicator --}}
                            <div class="self-center text-gray-600 group-hover:text-primary transition-colors group-hover:translate-x-1 transform duration-300">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 4. HINTERLEGTE DATEIEN & DOWNLOADS (ZUGEKLAPPT) --}}
        <div x-data="{ showFiles: false }" class="bg-gray-900/50 rounded-[2rem] shadow-inner border border-gray-800 overflow-hidden transition-all duration-300">
            <div @click="showFiles = !showFiles" class="bg-gray-950 px-6 py-4 border-b border-gray-800 flex justify-between items-center cursor-pointer hover:bg-gray-800 transition-colors">
                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-3">
                    <div class="p-1.5 rounded-lg bg-gray-800 text-gray-300 border border-gray-700 shadow-inner">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </div>
                    Dateien & Downloads
                </h3>
                <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center border border-gray-700 shadow-inner">
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-300" :class="showFiles ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <div x-show="showFiles" x-collapse style="display: none;">
                <div class="p-5 space-y-3 bg-gray-900/30">
                    @php
                        // Dateien aus der Konfiguration sammeln
                        $files = $previewItem->configuration['files'] ?? [];
                        $logoPath = $previewItem->configuration['logo_storage_path'] ?? null;

                        // Wenn Logo existiert und noch nicht in der Liste ist, hinzufügen
                        if($logoPath && !in_array($logoPath, $files)) {
                            array_unshift($files, $logoPath);
                        }
                    @endphp

                    @forelse($files as $path)
                        <div class="flex items-center justify-between p-3.5 rounded-2xl border border-gray-800 bg-gray-950 hover:bg-gray-800 hover:border-gray-700 transition-colors group shadow-inner">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="h-12 w-12 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-xl flex items-center justify-center shrink-0 shadow-sm">
                                    @php $ext = pathinfo($path, PATHINFO_EXTENSION); @endphp
                                    <span class="text-[11px] font-black uppercase tracking-wider">{{ $ext }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-white truncate">{{ basename($path) }}</p>
                                    <p class="text-[10px] font-medium uppercase tracking-widest text-gray-500 mt-0.5">Hinterlegt am {{ $model->created_at->format('d.m.Y') }}</p>
                                </div>
                            </div>
                            <a href="{{ asset('storage/'.$path) }}"
                               download="{{ basename($path) }}"
                               target="_blank"
                               class="ml-4 p-3 bg-gray-800 text-gray-400 rounded-xl hover:bg-primary hover:text-gray-900 hover:shadow-[0_0_15px_rgba(197,160,89,0.4)] transition-all"
                               title="Herunterladen">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </div>
                    @empty
                        <div class="py-6 text-center">
                            <div class="w-12 h-12 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center mx-auto mb-3 text-gray-600">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <p class="text-xs text-gray-500 font-medium">Keine Dateien für diese Position hinterlegt.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- xTool Download --}}
            @if(isset($previewItem->configuration['texts']) || isset($previewItem->configuration['logos']))
                <div class="bg-gray-950 px-6 py-5 border-t border-gray-800">
                    <a href="{{ route('admin.orders.laserfile', $previewItem->id) }}" target="_blank" class="w-full flex items-center justify-center gap-3 px-6 py-3.5 bg-gray-800 text-white text-xs font-black uppercase tracking-widest rounded-xl border border-gray-700 shadow-inner hover:bg-primary hover:text-gray-900 hover:border-primary hover:shadow-[0_0_20px_rgba(197,160,89,0.3)] transition-all duration-300 group">
                        <svg class="w-5 h-5 text-primary group-hover:text-gray-900 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        xTool Laser-Datei laden (.svg)
                    </a>
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mt-3 text-center leading-relaxed max-w-sm mx-auto">Generiert automatisch eine maßstabsgetreue Vektordatei für XCS.</p>
                </div>
            @endif
        </div>

        {{-- 5. BESONDERE KUNDENWÜNSCHE (NEU) --}}
        @if(!empty($previewItem->configuration['notes']))
            <div class="bg-amber-900/10 rounded-[2rem] shadow-inner border border-amber-500/20 overflow-hidden mb-6">
                <div class="bg-amber-500/10 px-6 py-4 border-b border-amber-500/20">
                    <h3 class="text-[10px] font-black text-amber-400 uppercase tracking-widest flex items-center gap-2 drop-shadow-[0_0_8px_currentColor]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        Besondere Kundenwünsche
                    </h3>
                </div>
                <div class="p-6 text-sm text-amber-100/80 leading-relaxed font-medium">
                    <div class="bg-amber-950/50 rounded-2xl p-5 border border-amber-900 shadow-inner italic">
                        "{!! nl2br(e($previewItem->configuration['notes'])) !!}"
                    </div>
                </div>
            </div>
        @endif

        {{-- 6. COST SUMMARY --}}
        <div>
            <x-shop.cost-summary :model="$model" />
        </div>

        {{-- 7. LÖSCHEN --}}
        @if($isOrder)
            <div class="pt-8 border-t border-gray-800 flex justify-center">
                <button wire:click="delete('{{ $model->id }}')" wire:confirm="Wirklich löschen?" class="text-[10px] font-black text-red-500 hover:text-red-400 hover:shadow-[0_0_15px_currentColor] uppercase tracking-widest border border-transparent hover:border-red-500/50 bg-transparent hover:bg-red-500/10 px-4 py-2 rounded-lg transition-all">
                    Bestellung unwiderruflich löschen
                </button>
            </div>
        @endif

    </div>
</div>
