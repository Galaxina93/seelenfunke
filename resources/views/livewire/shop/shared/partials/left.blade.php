<div class="w-full lg:w-1/2 h-1/2 lg:h-full overflow-y-auto bg-white custom-scrollbar z-10 lg:border-r border-gray-100">
    <div class="p-6 space-y-8">

        {{-- 1. EXPRESS ALERT --}}
        @if($model->is_express)
            <div class="bg-gradient-to-r from-red-50 to-white border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-red-100 text-red-600 rounded-full animate-pulse">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-red-800 font-bold uppercase tracking-wider text-sm">Express Auftrag</h3>
                        @if($model->deadline)
                            <p class="text-red-900 text-sm font-medium mt-1">
                                🏁 Deadline: <strong>{{ \Carbon\Carbon::parse($model->deadline)->format('d.m.Y') }}</strong>
                            </p>
                        @else
                            <p class="text-red-700 text-xs mt-1">Bitte priorisiert bearbeiten (FIFO beachten).</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- 2. KUNDENDATEN --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            {{-- Rechnungsadresse --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:border-primary/20 transition-colors">
                <h3 class="text-[10px] font-bold uppercase text-gray-400 mb-3 tracking-widest flex items-center gap-2">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Kunde / Rechnung
                </h3>
                <div class="text-sm text-gray-800 leading-relaxed">
                    <span class="font-bold text-base block mb-1">{{ $billing['name'] }}</span>
                    @if(!empty($billing['company'])) <span class="block text-gray-500 mb-1">{{ $billing['company'] }}</span> @endif
                    {{ $billing['address'] }}<br>
                    {{ $billing['city_zip'] }}<br>
                    <span class="uppercase text-xs font-bold text-gray-400">{{ $billing['country'] }}</span>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-50">
                    <a href="mailto:{{ $billing['email'] }}" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ $billing['email'] }}
                    </a>
                </div>
            </div>

            {{-- Lieferadresse (Nur Order) --}}
            @if($isOrder)
                @php $isDifferent = $shipping && serialize($model->billing_address) !== serialize($model->shipping_address); @endphp
                <div @class(['p-5 rounded-2xl border transition-colors shadow-sm', $isDifferent ? 'bg-amber-50/50 border-amber-200' : 'bg-white border-gray-100'])>
                    <h3 class="text-[10px] font-bold uppercase text-gray-400 mb-3 tracking-widest flex items-center gap-2">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                        Lieferung
                        @if($isDifferent) <span class="ml-auto text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold uppercase">Abweichend</span> @endif
                    </h3>
                    <div class="text-sm text-gray-800 leading-relaxed">
                        @if($shipping)
                            <span class="font-bold block mb-1">{{ $shipping['first_name'] }} {{ $shipping['last_name'] }}</span>
                            @if(!empty($shipping['company'])) {{ $shipping['company'] }}<br> @endif
                            {{ $shipping['address'] }}<br>
                            {{ $shipping['postal_code'] }} {{ $shipping['city'] }}<br>
                            <span class="uppercase text-xs font-bold text-gray-400">{{ $shipping['country'] }}</span>
                        @else
                            <span class="italic text-gray-400 flex items-center gap-2 mt-2">
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
            <h3 class="font-serif font-bold text-gray-900 text-lg mb-4 flex items-center justify-between">
                <span>Bestellpositionen</span>
                <span class="text-xs font-sans font-normal text-gray-400 bg-gray-100 px-2 py-1 rounded-full">{{ count($model->items) }} Artikel</span>
            </h3>

            <div class="space-y-3">
                @foreach($model->items as $item)
                    {{-- TYPE CHECK --}}
                    @php
                        $productType = $item->product->type ?? 'physical';
                        $isService = $productType === 'service';
                        $isDigital = $productType === 'digital';
                    @endphp

                    <div wire:click="selectItemForPreview('{{ $item->id }}')"
                         class="cursor-pointer border rounded-2xl p-3 transition-all relative overflow-hidden group
                                {{ $selectedItemId == $item->id ? 'border-primary ring-1 ring-primary bg-primary/5' : 'border-gray-100 bg-white hover:border-primary/30 hover:shadow-md' }}"
                    >
                        <div class="flex gap-4">
                            {{-- Bild --}}
                            <div class="h-16 w-16 bg-gray-50 rounded-xl border border-gray-100 overflow-hidden shrink-0 relative">
                                @php
                                    $conf = $item->configuration;
                                    $imgPath = $conf['preview_file'] ?? ($conf['logo_storage_path'] ?? ($item->product->preview_image_path ?? null));
                                @endphp
                                @if($imgPath)
                                    <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full object-cover">
                                @else
                                    <div class="h-full w-full flex items-center justify-center text-gray-300"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                @endif

                                {{-- Type Badge auf Bild --}}
                                @if($isService)
                                    <div class="absolute inset-0 bg-black/50 flex items-center justify-center text-white text-[10px] font-bold uppercase tracking-widest backdrop-blur-[1px]">Service</div>
                                @elseif($isDigital)
                                    <div class="absolute inset-0 bg-blue-500/50 flex items-center justify-center text-white text-[10px] font-bold uppercase tracking-widest backdrop-blur-[1px]">Digital</div>
                                @endif
                            </div>

                            {{-- Infos --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-gray-900 text-sm truncate pr-2">{{ $item->product_name }}</h4>
                                    <span class="font-mono font-bold text-gray-900 text-sm">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $item->quantity }}x á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</p>

                                {{-- Config Hints --}}
                                @if(!empty($conf))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @if(!empty($conf['text']))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] bg-gray-100 text-gray-600 border border-gray-200">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Text
                                            </span>
                                        @endif
                                        @if(!empty($conf['files']) || !empty($conf['logo_storage_path']))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] bg-blue-50 text-blue-600 border border-blue-100">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Dateien
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Arrow Indicator --}}
                            <div class="self-center text-gray-300 group-hover:text-primary transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 4. HINTERLEGTE DATEIEN & DOWNLOADS (ZUGEKLAPPT) --}}
        <div x-data="{ showFiles: false }" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden transition-all duration-300">
            <div @click="showFiles = !showFiles" class="bg-gray-50 px-6 py-3 border-b border-gray-100 flex justify-between items-center cursor-pointer hover:bg-gray-100 transition-colors">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Dateien & Downloads
                </h3>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-300" :class="showFiles ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>

            <div x-show="showFiles" x-collapse style="display: none;">
                <div class="p-4 space-y-3 bg-white">
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
                        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 transition-colors group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-10 w-10 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center shrink-0">
                                    @php $ext = pathinfo($path, PATHINFO_EXTENSION); @endphp
                                    <span class="text-[10px] font-black uppercase">{{ $ext }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-gray-900 truncate">{{ basename($path) }}</p>
                                    <p class="text-[10px] text-gray-400">Hinterlegt am {{ $model->created_at->format('d.m.Y') }}</p>
                                </div>
                            </div>
                            <a href="{{ asset('storage/'.$path) }}"
                               download="{{ basename($path) }}"
                               target="_blank"
                               class="ml-4 p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-primary hover:text-white transition-all shadow-sm"
                               title="Herunterladen">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            </a>
                        </div>
                    @empty
                        <div class="py-4 text-center">
                            <p class="text-xs text-gray-400 italic">Keine Dateien für diese Position hinterlegt.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 3. BESONDERE KUNDENWÜNSCHE (NEU) --}}
        @if(!empty($previewItem->configuration['notes']))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="bg-amber-50 px-6 py-3 border-b border-amber-100">
                    <h3 class="text-xs font-bold text-amber-800 uppercase tracking-widest flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        Besondere Kundenwünsche
                    </h3>
                </div>
                <div class="p-6 text-sm text-gray-700 leading-relaxed">
                    <div class="bg-amber-50/50 rounded-xl p-4 border border-amber-100 italic">
                        "{!! nl2br(e($previewItem->configuration['notes'])) !!}"
                    </div>
                </div>
            </div>
        @endif

        {{-- 5. COST SUMMARY --}}
        <div>
            <x-shop.cost-summary :model="$model" />
        </div>

        {{-- 6. LÖSCHEN --}}
        @if($isOrder)
            <div class="pt-8 border-t border-gray-100 flex justify-center">
                <button wire:click="delete('{{ $model->id }}')" wire:confirm="Wirklich löschen?" class="text-xs font-bold text-red-400 hover:text-red-600 uppercase tracking-widest hover:underline transition-colors">
                    Bestellung unwiderruflich löschen
                </button>
            </div>
        @endif

    </div>
</div>
