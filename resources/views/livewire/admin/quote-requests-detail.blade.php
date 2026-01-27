<div class="h-[calc(100vh-3rem)] flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mx-6 mb-6">

    {{-- DETAIL HEADER --}}
    <div class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center shrink-0 z-20 relative">
        <div class="flex items-center gap-4">
            <button wire:click="closeDetail" class="text-gray-500 hover:text-gray-900 flex items-center gap-1 text-sm font-bold transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Zurück
            </button>
            <div class="h-6 w-px bg-gray-300"></div>
            <div>
                <h1 class="text-xl font-serif font-bold text-gray-900 flex items-center gap-2">
                    Anfrage {{ $quote->quote_number }}
                    <span class="text-xs font-sans font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                        {{ $quote->created_at->format('d.m.Y H:i') }}
                    </span>
                    @if($quote->is_express)
                        <span class="text-xs font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded border border-red-200 uppercase">Express</span>
                    @endif
                </h1>
            </div>
        </div>

        {{-- ACTIONS --}}
        <div class="flex gap-3">
            @if($quote->status === 'open')
                <button wire:click="markAsRejected('{{ $quote->id }}')" class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 text-xs font-bold transition">
                    Ablehnen
                </button>
                <button wire:click="convertToOrder('{{ $quote->id }}')"
                        wire:confirm="Möchtest du diese Anfrage wirklich in eine verbindliche Bestellung umwandeln?"
                        class="px-4 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-bold shadow-sm transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Angebot annehmen & Bestellung anlegen
                </button>
            @elseif($quote->status === 'converted')
                <div class="flex items-center gap-2 text-green-700 bg-green-50 px-3 py-1.5 rounded-lg border border-green-200 text-xs font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Wurde in Bestellung umgewandelt
                </div>
            @elseif($quote->status === 'rejected')
                <div class="flex items-center gap-3">
                    <div class="text-red-700 bg-red-50 px-3 py-1.5 rounded-lg border border-red-200 text-xs font-bold">
                        Abgelehnt
                    </div>
                    {{-- NEU: Rückgängig machen --}}
                    <button wire:click="markAsOpen('{{ $quote->id }}')" class="text-gray-500 hover:text-gray-900 text-xs underline">
                        Ablehnung aufheben (wieder öffnen)
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- SPLIT CONTENT --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- LINKS: Details & Liste (Scrollbar) --}}
        <div class="w-1/2 overflow-y-auto border-r border-gray-200 bg-white custom-scrollbar z-10">
            <div class="p-6 space-y-8">

                {{-- Kundendaten --}}
                <div class="grid grid-cols-2 gap-6">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Antragsteller
                        </h3>
                        <div class="text-sm text-gray-900 leading-snug">
                            <span class="font-bold">{{ $quote->first_name }} {{ $quote->last_name }}</span><br>
                            @if(!empty($quote->company)) {{ $quote->company }}<br> @endif
                            <a href="mailto:{{ $quote->email }}" class="text-primary hover:underline">{{ $quote->email }}</a><br>
                            {{ $quote->phone ?: 'Keine Telefonnummer' }}
                        </div>
                        @if($quote->is_guest)
                            <div class="mt-2 inline-flex items-center gap-1 text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded">Gast</div>
                        @else
                            <div class="mt-2 inline-flex items-center gap-1 text-[10px] bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">Registriert</div>
                        @endif
                    </div>

                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <h3 class="text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Interne Notizen
                        </h3>
                        <div class="text-sm text-gray-600 italic">
                            {{ $quote->admin_notes ?: 'Keine Notizen vorhanden.' }}
                        </div>
                    </div>
                </div>

                {{-- Artikelliste --}}
                <div>
                    <h3 class="font-bold text-gray-900 mb-4 px-1 flex items-center justify-between">
                        <span>Positionen der Anfrage</span>
                        <span class="text-xs font-normal text-gray-400">Klicke zum Anzeigen</span>
                    </h3>
                    <div class="space-y-3">
                        @foreach($quote->items as $item)
                            <div
                                wire:click="selectItemForPreview('{{ $item->id }}')"
                                class="cursor-pointer border rounded-xl p-3 transition-all relative overflow-hidden group
                                {{ $this->previewItem && $this->previewItem->id == $item->id ? 'border-primary ring-1 ring-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                            >
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center gap-4">
                                        {{-- Thumbnail --}}
                                        <div class="h-14 w-14 bg-white rounded-lg border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                            @php
                                                $conf = $item->configuration;
                                                // Versuche Bild zu finden: Preview -> Logo -> Produktbild
                                                $imgPath = $conf['preview_file'] ?? ($conf['logo_storage_path'] ?? ($item->product->preview_image_path ?? null));
                                            @endphp
                                            @if($imgPath && file_exists(public_path('storage/'.$imgPath)))
                                                <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full object-contain">
                                            @elseif($imgPath && file_exists(public_path($imgPath)))
                                                <img src="{{ asset($imgPath) }}" class="h-full w-full object-contain">
                                            @else
                                                <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            @endif
                                        </div>

                                        <div>
                                            <div class="font-bold text-gray-900 text-sm">{{ $item->product_name }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $item->quantity }} Stück á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <div class="font-mono font-bold text-gray-900 text-sm">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</div>
                                        @if($this->previewItem && $this->previewItem->id == $item->id)
                                            <div class="text-[10px] text-primary font-bold mt-1 bg-white px-2 py-0.5 rounded-full shadow-sm inline-block">WIRD ANGEZEIGT</div>
                                        @else
                                            <div class="text-[10px] text-gray-400 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">Anzeigen &rarr;</div>
                                        @endif
                                    </div>
                                </div>

                                {{-- DETAILS (Gravur, Dateien, Notes) --}}
                                @if(!empty($conf))
                                    <div class="mt-3 pt-3 border-t border-gray-200/60 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">

                                        {{-- 1. Gravurtext --}}
                                        @if(!empty($conf['text']))
                                            <div>
                                                <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Gravurtext</span>
                                                <div class="font-serif italic text-gray-800 bg-gray-50 px-2 py-1.5 rounded border border-gray-100">
                                                    "{{ $conf['text'] }}"
                                                </div>
                                            </div>
                                        @endif

                                        {{-- 2. Anmerkungen --}}
                                        @if(!empty($conf['notes']))
                                            <div>
                                                <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Kunden-Anmerkung</span>
                                                <div class="text-gray-700 bg-yellow-50 px-2 py-1.5 rounded border border-yellow-100">
                                                    {{ $conf['notes'] }}
                                                </div>
                                            </div>
                                        @endif

                                        {{-- 3. Dateien --}}
                                        @php
                                            $files = $conf['files'] ?? [];
                                            if(empty($files) && !empty($conf['logo_storage_path'])) {
                                                $files[] = $conf['logo_storage_path'];
                                            }
                                        @endphp

                                        @if(count($files) > 0)
                                            <div class="col-span-1 md:col-span-2">
                                                <span class="block text-gray-400 uppercase font-bold text-[10px] mb-1">Hochgeladene Dateien ({{ count($files) }})</span>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach($files as $file)
                                                        <a href="{{ asset('storage/'.$file) }}" target="_blank" download class="flex items-center gap-2 bg-white border border-gray-300 rounded px-3 py-1.5 hover:bg-gray-50 hover:border-primary hover:text-primary transition group/btn">
                                                            <svg class="w-4 h-4 text-gray-500 group-hover/btn:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            <span class="truncate max-w-[150px]">{{ basename($file) }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Summen --}}
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                    <h4 class="text-xs font-bold uppercase text-gray-500 mb-4 border-b border-gray-200 pb-2">Kalkulation</h4>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between text-gray-600"><span>Netto</span><span>{{ number_format($quote->net_total / 100, 2, ',', '.') }} €</span></div>
                        <div class="flex justify-between text-gray-600"><span>MwSt</span><span>{{ number_format($quote->tax_total / 100, 2, ',', '.') }} €</span></div>
                        <div class="pt-3 mt-1 border-t border-gray-200 flex justify-between items-end"><span class="font-bold text-gray-900">Gesamtbetrag (Brutto)</span><span class="text-xl font-bold text-primary">{{ number_format($quote->gross_total / 100, 2, ',', '.') }} €</span></div>
                    </div>
                </div>

            </div>
        </div>

        {{-- RECHTS: Configurator Preview --}}
        <div class="w-1/2 bg-gray-50 flex flex-col border-l border-gray-200 h-full overflow-hidden">
            <div class="flex-1 p-6 bg-gray-100 h-full overflow-y-auto custom-scrollbar">
                @if($this->previewItem)
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex flex-col min-h-0">
                        {{-- Header Configurator --}}
                        <div class="bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center shrink-0">
                            <div>
                                <h3 class="font-bold text-gray-800">{{ $this->previewItem->product_name }}</h3>
                                <p class="text-xs text-gray-400">Produkt-ID: {{ $this->previewItem->product_id }}</p>
                            </div>
                            <div class="text-right text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded">
                                Visuelle Vorschau
                            </div>
                        </div>

                        {{-- CONFIGURATOR COMPONENT --}}
                        <div class="relative">
                            {{-- Falls das Item kein Product hat (gelöscht), Fehler vermeiden --}}
                            @if($this->previewItem->product)
                                <livewire:shop.configurator
                                    :product="$this->previewItem->product"
                                    :initialData="$this->previewItem->configuration"
                                    :qty="$this->previewItem->quantity"
                                    context="preview"
                                    :key="'quote-conf-'.$this->previewItem->id"
                                />
                            @else
                                <div class="p-8 text-center text-red-500">Produkt wurde gelöscht. Keine Vorschau verfügbar.</div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="h-full flex flex-col items-center justify-center text-gray-400 space-y-4">
                        <p class="font-medium">Wähle links eine Position aus.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
