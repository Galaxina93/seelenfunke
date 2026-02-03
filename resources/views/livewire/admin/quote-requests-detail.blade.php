<div class="h-full lg:h-[calc(100vh-3rem)] flex flex-col bg-white lg:rounded-xl shadow-lg border-t lg:border border-gray-200 overflow-hidden lg:mx-6 lg:mb-6">

    {{-- DETAIL HEADER: Mobil optimiert (Flex-Wrap für Buttons) --}}
    <div class="bg-white border-b border-gray-200 px-4 py-3 lg:px-6 lg:py-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0 z-30 sticky top-0">
        <div class="flex items-center gap-3 lg:gap-4 w-full sm:w-auto">
            <button wire:click="closeDetail" class="p-2 lg:p-0 text-gray-500 hover:text-gray-900 flex items-center gap-1 text-sm font-bold transition">
                <svg class="w-5 h-5 lg:w-4 lg:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span class="hidden lg:inline">Zurück</span>
            </button>
            <div class="h-6 w-px bg-gray-300 hidden lg:block"></div>
            <div class="truncate">
                <h1 class="text-lg lg:text-xl font-serif font-bold text-gray-900 flex flex-wrap items-center gap-2">
                    Anfrage {{ $quote->quote_number }}
                    <span class="text-[10px] lg:text-xs font-sans font-normal text-gray-500 bg-gray-100 px-2 py-0.5 rounded whitespace-nowrap">
                        {{ $quote->created_at->format('d.m.Y H:i') }}
                    </span>
                    @if($quote->is_express)
                        <span class="text-[10px] lg:text-xs font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded border border-red-200 uppercase whitespace-nowrap">Express</span>
                    @endif
                </h1>
            </div>
        </div>

        {{-- ACTIONS: Volle Breite auf Mobilgeräten --}}
        <div class="flex gap-2 w-full sm:w-auto justify-end">
            @if($quote->status === 'open')
                <button wire:click="markAsRejected('{{ $quote->id }}')" class="flex-1 sm:flex-none px-3 py-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 text-xs font-bold transition">
                    Ablehnen
                </button>
                <button wire:click="convertToOrder('{{ $quote->id }}')"
                        wire:confirm="Soll diese Anfrage wirklich in eine Bestellung umgewandelt werden?"
                        class="flex-1 sm:flex-none px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-bold shadow-sm transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Annehmen
                </button>
            @elseif($quote->status === 'converted' || $quote->status === 'accepted')
                <div class="w-full sm:w-auto text-center flex items-center justify-center gap-2 text-green-700 bg-green-50 px-3 py-2 rounded-lg border border-green-200 text-xs font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Umgewandelt
                </div>
            @endif
        </div>
    </div>

    {{-- SPLIT CONTENT: Stacked auf Mobile, Row auf Desktop --}}
    <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">

        {{-- LINKS: Details & Liste (Scrollbar) --}}
        <div class="w-full lg:w-1/2 overflow-y-auto border-b lg:border-b-0 lg:border-r border-gray-200 bg-white custom-scrollbar z-10">
            <div class="p-4 lg:p-6 space-y-6 lg:space-y-8">

                {{-- Kundendaten & Notizen: Responsive Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <h3 class="text-[10px] lg:text-xs font-bold uppercase text-gray-500 mb-2 flex items-center gap-1 tracking-wider">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Antragsteller
                        </h3>
                        <div class="text-sm text-gray-900 leading-relaxed">
                            <span class="font-bold block text-base">{{ $quote->first_name }} {{ $quote->last_name }}</span>
                            @if(!empty($quote->company)) <span class="block text-gray-600">{{ $quote->company }}</span> @endif
                            <a href="mailto:{{ $quote->email }}" class="text-primary hover:underline block mt-1">{{ $quote->email }}</a>
                            <span class="block mt-1">{{ $quote->phone ?: 'Keine Tel.' }}</span>
                        </div>
                    </div>

                    <div class="bg-amber-50/30 p-4 rounded-xl border border-amber-100/50">
                        <h3 class="text-[10px] lg:text-xs font-bold uppercase text-amber-600/70 mb-2 flex items-center gap-1 tracking-wider">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Interne Notizen
                        </h3>
                        <div class="text-sm text-amber-900 italic leading-snug">
                            {{ $quote->admin_notes ?: 'Keine internen Notizen.' }}
                        </div>
                    </div>
                </div>

                {{-- Artikelliste --}}
                <div id="positions-anchor">
                    <h3 class="font-bold text-gray-900 mb-4 px-1 flex items-center justify-between">
                        <span class="text-sm lg:text-base">Anfrage-Positionen</span>
                        <span class="text-[10px] lg:text-xs font-normal text-gray-400">Position wählen für Vorschau</span>
                    </h3>
                    <div class="space-y-3">
                        @foreach($quote->items as $item)
                            <div
                                wire:click="selectItemForPreview('{{ $item->id }}')"
                                class="cursor-pointer border rounded-xl p-3 lg:p-4 transition-all relative overflow-hidden group
                                {{ $this->previewItem && $this->previewItem->id == $item->id ? 'border-primary ring-2 ring-primary/20 bg-primary/5 shadow-sm' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                            >
                                <div class="flex justify-between items-start gap-3">
                                    <div class="flex items-center gap-3 lg:gap-4 overflow-hidden">
                                        {{-- Thumbnail: Zentralisiert über MediaGallery --}}
                                        <div class="h-16 w-16 bg-white rounded-lg border border-gray-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                            @php
                                                $conf = $item->configuration;
                                                $imgPath = $conf['preview_file'] ?? ($item->product->preview_image_path ?? ($item->product->media_gallery[0]['path'] ?? null));
                                            @endphp
                                            @if($imgPath)
                                                <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full object-contain">
                                            @else
                                                <svg class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900 text-sm lg:text-base truncate">{{ $item->product_name }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5">{{ $item->quantity }}x á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</div>
                                        </div>
                                    </div>

                                    <div class="text-right flex-shrink-0">
                                        <div class="font-mono font-bold text-gray-900 text-sm lg:text-base">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</div>
                                        @if($this->previewItem && $this->previewItem->id == $item->id)
                                            <span class="hidden lg:inline-block text-[10px] text-primary font-bold mt-1 bg-white px-2 py-0.5 rounded-full shadow-sm">VORSCHAU AKTIV</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- KONFIGURATION-DETAILS: Mobil kompakter --}}
                                @if(!empty($conf))
                                    <div class="mt-4 pt-3 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @if(!empty($conf['text']))
                                            <div class="bg-gray-50/50 p-2 rounded">
                                                <span class="block text-gray-400 uppercase font-bold text-[9px] tracking-widest mb-1">Gravurtext</span>
                                                <div class="font-serif italic text-gray-800 text-xs break-words">"{{ $conf['text'] }}"</div>
                                            </div>
                                        @endif

                                        @if(!empty($conf['files']) || !empty($conf['logo_storage_path']))
                                            <div class="bg-gray-50/50 p-2 rounded">
                                                <span class="block text-gray-400 uppercase font-bold text-[9px] tracking-widest mb-1">Dateien</span>
                                                <div class="flex flex-wrap gap-1">
                                                    @php $files = $conf['files'] ?? (array)($conf['logo_storage_path'] ?? []); @endphp
                                                    @foreach($files as $file)
                                                        <span class="inline-flex items-center text-[10px] text-primary truncate max-w-full">
                                                            <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                            Datei vorhanden
                                                        </span>
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

                {{-- Summenblock --}}
                <div class="bg-gray-50 text-white p-5 lg:p-6 rounded-2xl shadow-inner">
                    @php
                        // Neue Logik: Daten direkt aus der 'shop-settings' Tabelle beziehen
                        $isSmallBusiness = (bool)shop_setting('is_small_business', false);
                        $taxRate = (float)shop_setting('default_tax_rate', 19.0);
                    @endphp

                    <div class="space-y-3">
                        <div class="flex justify-between text-xs lg:text-sm text-gray-400">
                            <span>Netto-Summe</span>
                            <span>{{ number_format($quote->net_total / 100, 2, ',', '.') }} €</span>
                        </div>
                        <div class="flex justify-between text-xs lg:text-sm text-gray-400">
                            <span>Versandkosten</span>
                            <span>{{ number_format(($quote->shipping_price ?? 0) / 100, 2, ',', '.') }} €</span>
                        </div>

                        @if(!$isSmallBusiness)
                            <div class="flex justify-between text-xs lg:text-sm text-gray-400">
                                <span>MwSt. ({{ number_format($taxRate, 0) }}%)</span>
                                <span>{{ number_format($quote->tax_total / 100, 2, ',', '.') }} €</span>
                            </div>
                        @else
                            <div class="flex justify-between text-[10px] lg:text-xs text-gray-500 italic border-b border-gray-800 pb-2">
                                <span>Steuerfrei gemäß § 19 UStG</span>
                                <span>0,00 €</span>
                            </div>
                        @endif

                        <div class="pt-3 mt-1 border-t border-gray-700 flex justify-between items-center">
                            <span class="font-bold text-sm lg:text-base">Brutto-Gesamt</span>
                            <span class="text-xl lg:text-2xl font-bold text-primary">{{ number_format($quote->gross_total / 100, 2, ',', '.') }} €</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- RECHTS: Visual Preview (Scrollbar nur Desktop) --}}
        <div class="w-full lg:w-1/2 bg-gray-50 flex flex-col border-t lg:border-t-0 lg:border-l border-gray-200 min-h-[500px] lg:h-full">
            <div class="flex-1 p-4 lg:p-6 bg-gray-100 overflow-y-auto custom-scrollbar">
                @if($this->previewItem)
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 flex flex-col h-full min-h-[400px]">
                        {{-- Configurator Header --}}
                        <div class="bg-white border-b border-gray-100 px-4 py-3 lg:px-6 lg:py-4 flex justify-between items-center shrink-0">
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-800 text-sm lg:text-base truncate">{{ $this->previewItem->product_name }}</h3>
                                <p class="text-[10px] text-gray-400 uppercase tracking-tighter">Konfigurations-Vorschau</p>
                            </div>
                            <button wire:click="$set('previewItem', null)" class="lg:hidden text-gray-400 p-2">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- CONFIGURATOR COMPONENT: Live Preview --}}
                        <div class="relative flex-1 bg-gray-50/50 flex flex-col">
                            @if($this->previewItem->product)
                                <div class="flex-1">
                                    <livewire:shop.configurator
                                        :product="$this->previewItem->product->id"
                                        :initialData="$this->previewItem->configuration"
                                        :qty="$this->previewItem->quantity"
                                        context="preview"
                                        :key="'quote-preview-'.$this->previewItem->id"
                                    />
                                </div>
                            @else
                                <div class="p-12 text-center">
                                    <div class="text-red-500 font-bold">Produkt nicht mehr verfügbar.</div>
                                    <p class="text-xs text-gray-400 mt-2">Das Produkt wurde aus dem Katalog entfernt.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="h-full flex flex-col items-center justify-center text-center p-8">
                        <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </div>
                        <p class="font-medium text-gray-500">Klicke eine Position an,</p>
                        <p class="text-sm text-gray-400">um das Design im Konfigurator zu prüfen.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
