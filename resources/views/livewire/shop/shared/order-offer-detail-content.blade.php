@php
    // DATEN NORMALISIERUNG
    $isOrder = $context === 'order';
    $isQuote = $context === 'quote';

    // ADRESSDATEN
    if ($isOrder) {
        $billing = [
            'name' => $model->billing_address['first_name'] . ' ' . $model->billing_address['last_name'],
            'company' => $model->billing_address['company'] ?? null,
            'address' => $model->billing_address['address'],
            'city_zip' => $model->billing_address['postal_code'] . ' ' . $model->billing_address['city'],
            'country' => $model->billing_address['country'],
            'email' => $model->email
        ];
        $shipping = $model->shipping_address ?? null;
    } else {
        $billing = [
            'name' => $model->first_name . ' ' . $model->last_name,
            'company' => $model->company ?? null,
            'address' => trim(($model->street ?? '') . ' ' . ($model->house_number ?? '')),
            'city_zip' => ($model->postal ?? '') . ' ' . ($model->city ?? ''),
            'country' => $model->country ?? 'DE',
            'email' => $model->email
        ];
        $shipping = null;
    }

    $isDigitalItem = isset($previewItem->configuration['is_digital']) && $previewItem->configuration['is_digital'];
    $fingerprint = $previewItem->config_fingerprint ?? null;
@endphp

{{-- LINKE SPALTE: DETAILS --}}
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

        {{-- 4. COST SUMMARY --}}
        <div>
            <x-shop.cost-summary :model="$model" />
        </div>

        {{-- 5. LÖSCHEN --}}
        @if($isOrder)
            <div class="pt-8 border-t border-gray-100 flex justify-center">
                <button wire:click="delete('{{ $model->id }}')" wire:confirm="Wirklich löschen?" class="text-xs font-bold text-red-400 hover:text-red-600 uppercase tracking-widest hover:underline transition-colors">
                    Bestellung unwiderruflich löschen
                </button>
            </div>
        @endif

    </div>
</div>

{{-- RECHTE SPALTE: PREVIEW --}}
<div class="w-full lg:w-1/2 h-1/2 lg:h-full bg-gray-50 flex flex-col border-l-0 lg:border-l border-gray-200">
    <div class="flex-1 p-6 h-full overflow-y-auto custom-scrollbar">
        @if($previewItem)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 flex flex-col h-full overflow-hidden">

                {{-- Preview Header --}}
                <div class="bg-white border-b border-gray-100 px-6 py-4 shrink-0 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-gray-900">{{ $previewItem->product_name }}</h3>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest">Detail-Ansicht</p>
                    </div>
                    @if($isDigitalItem)
                        <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded uppercase">Digital</span>
                    @elseif($isService)
                        <span class="bg-gray-800 text-white text-[10px] font-bold px-2 py-1 rounded uppercase">Service</span>
                    @endif
                </div>

                {{-- Configurator Content --}}
                <div class="flex-1 bg-gray-50/50 relative overflow-hidden">
                    @if($previewItem->product)
                        <livewire:shop.configurator.configurator
                            :product="$previewItem->product->id"
                            :initialData="$previewItem->configuration"
                            :qty="$previewItem->quantity"
                            context="preview"
                            :key="'admin-preview-'.$previewItem->id"
                        />
                    @else
                        <div class="flex items-center justify-center h-full text-red-400 font-bold">Produkt gelöscht</div>
                    @endif
                </div>

                {{-- Footer Info --}}
                @if($fingerprint)
                    <div class="bg-green-50 px-6 py-3 border-t border-green-100 flex items-center gap-3">
                        <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <div class="text-[10px] text-green-800">
                            <strong>Originalgetreu:</strong> Diese Konfiguration ist digital signiert und unveränderlich.
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="h-full flex flex-col items-center justify-center text-center text-gray-400">
                <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </div>
                <p class="font-medium text-gray-500">Wähle eine Position links aus,</p>
                <p class="text-sm">um die Konfiguration zu prüfen.</p>
            </div>
        @endif
    </div>
</div>
