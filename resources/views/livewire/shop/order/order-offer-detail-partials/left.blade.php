<div class="w-full lg:w-1/2 h-auto lg:h-full lg:overflow-y-auto bg-gray-50/50 dark:bg-gray-950/30 custom-scrollbar z-10 lg:border-r border-gray-200 dark:border-gray-800">
    <div class="p-6 md:p-8 space-y-8">

        {{-- 1. EXPRESS ALERT --}}
        @if($model->is_express)
            <div class="bg-gradient-to-r from-red-500/10 to-transparent border-l-4 border-red-500 p-5 rounded-r-2xl shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="p-2 bg-red-500/20 text-red-400 rounded-full animate-pulse shadow-[0_0_15px_rgba(239,68,68,0.2)] shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <div>
                        <h3 class="text-red-400 font-black uppercase tracking-widest text-xs drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Express Auftrag</h3>
                        @if($model->deadline)
                            <p class="text-gray-900 dark:text-white text-sm font-bold mt-1 tracking-wide">
                                🏁 Deadline: <span class="text-red-400">{{ \Carbon\Carbon::parse($model->deadline)->format('d.m.Y') }}</span>
                            </p>
                        @else
                            <p class="text-gray-600 dark:text-gray-400 text-[11px] mt-1.5 font-medium leading-relaxed">
                                Bitte vor allen Standard-Aufträgen priorisiert fertigen und versenden. Innerhalb der Express-Aufträge selbst gilt das <strong>First-In, First-Out (FIFO) Prinzip</strong>: Chronologisch nach Bestelleingang abarbeiten.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- 2. KUNDENDATEN --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            {{-- Rechnungsadresse --}}
            <div class="bg-white dark:bg-gray-900/50 backdrop-blur-md p-6 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner hover:border-[var(--theme-color)]/30 transition-colors">
                <h3 class="text-[9px] font-black uppercase text-gray-500 mb-4 tracking-widest flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Kunde / Rechnung
                </h3>
                <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">
                    <span class="font-bold text-gray-900 dark:text-white text-base block mb-1">{{ $billing['name'] }}</span>
                    @if(!empty($billing['company'])) <span class="block text-[var(--theme-color)] mb-1">{{ $billing['company'] }}</span> @endif
                    {{ $billing['address'] }}<br>
                    {{ $billing['city_zip'] }}<br>
                    <span class="uppercase text-[10px] font-black tracking-wider text-gray-500 mt-1 block">{{ $billing['country'] }}</span>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800/50">
                    <a href="mailto:{{ $billing['email'] }}" class="text-xs font-bold text-[var(--theme-color)] hover:text-white transition-colors flex items-center gap-2 group">
                        <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ $billing['email'] }}
                    </a>
                </div>
            </div>

            {{-- Lieferadresse (Nur Order) --}}
            @if($isOrder)
                @php $isDifferent = $shipping && serialize($model->billing_address) !== serialize($model->shipping_address); @endphp
                <div @class(['p-6 rounded-3xl border transition-colors shadow-sm dark:shadow-inner backdrop-blur-md', $isDifferent ? 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-500/30' : 'bg-white dark:bg-gray-900/50 border-gray-200 dark:border-gray-800'])>
                    <h3 class="text-[9px] font-black uppercase text-gray-500 mb-4 tracking-widest flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                        Lieferung
                        @if($isDifferent) <span class="ml-auto text-[9px] bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded-md font-black uppercase tracking-widest shadow-[0_0_10px_rgba(245,158,11,0.1)]">Abweichend</span> @endif
                    </h3>
                    <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">
                        @if($shipping)
                            <span class="font-bold text-gray-900 dark:text-white block mb-1">{{ $shipping['first_name'] }} {{ $shipping['last_name'] }}</span>
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
            <h3 class="font-serif font-bold text-gray-900 dark:text-white text-xl mb-4 flex items-center justify-between tracking-tight">
                <span>Bestellpositionen</span>
                <span class="text-[10px] font-sans font-black uppercase tracking-widest text-[var(--theme-color)] bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] px-3 py-1.5 rounded-full shadow-[0_0_15px_var(--theme-color-15)]">{{ count($model->items) }} Artikel</span>
            </h3>

            {{-- Progress Bar (Only show if it's an Order!) --}}
            @if($isOrder)
                @php
                    $totalItems = $model->items->count();
                    $completedItems = $model->items->where('is_completed', true)->count();
                    $progressPct = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0;
                @endphp
                @if($totalItems > 0)
                    <div class="mb-5 bg-gray-200 dark:bg-gray-900/50 rounded-full h-2.5 overflow-hidden border border-gray-300 dark:border-gray-800 shadow-inner w-full flex">
                        <div class="bg-emerald-500 h-full transition-all duration-700 ease-out shadow-none dark:shadow-[0_0_10px_rgba(16,185,129,0.5)]" style="width: {{ $progressPct }}%"></div>
                    </div>
                @endif
            @endif

            <div class="space-y-4">
                @foreach($model->items as $item)
                    {{-- TYPE CHECK --}}
                    @php
                        $productType = $item->product->type ?? 'physical';
                        $isService = $productType === 'service';
                        $isDigital = $productType === 'digital';
                        $isCompletedClass = 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/50 hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-900 shadow-sm dark:shadow-inner';
                        if ($isOrder) {
                            $isCompletedClass = $item->is_completed 
                                ? 'border-emerald-500/50 bg-emerald-50 dark:bg-emerald-950/20 shadow-sm dark:shadow-[0_0_15px_rgba(16,185,129,0.1)] hover:border-emerald-400' 
                                : 'border-red-200 dark:border-red-500/30 bg-white dark:bg-gray-900/50 hover:border-red-400 shadow-sm dark:shadow-inner';
                        }
                    @endphp

                    <div wire:click="selectItemForPreview('{{ $item->id }}')"
                         class="cursor-pointer border border-[2px] rounded-[1.5rem] sm:rounded-[2rem] p-3 sm:p-4 transition-all duration-300 relative overflow-hidden group
                                {{ $isCompletedClass }}
                                {{ $selectedItemId == $item->id ? 'ring-2 ring-[var(--theme-color)] ring-offset-2 ring-offset-white dark:ring-offset-gray-950' : '' }}"
                    >
                        @if($isOrder)
                            {{-- Add Checkbox --}}
                            <div class="absolute top-3 sm:top-4 right-3 sm:right-5 z-20" wire:click.stop="toggleItemCompletion('{{ $item->id }}')">
                                 <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full border-[2px] sm:border-[2.5px] flex items-center justify-center transition-colors {{ $item->is_completed ? 'bg-emerald-500 border-emerald-500 text-gray-900 shadow-[0_0_10px_rgba(16,185,129,0.4)]' : 'border-gray-400 dark:border-gray-500 text-transparent hover:border-[var(--theme-color)] hover:text-[var(--theme-color-50)]' }}">
                                     <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                 </div>
                            </div>
                        @endif

                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-5">
                            {{-- Bild --}}
                            <div class="flex items-start gap-3 sm:contents">
                                <div class="h-16 w-16 sm:h-20 sm:w-20 bg-gray-100 dark:bg-gray-950 rounded-xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden shrink-0 relative shadow-sm dark:shadow-inner">
                                @php
                                    $conf = $item->configuration;
                                    $snapshotImg = null;
                                    if (!empty($conf['snapshot_path'])) {
                                        if (is_array($conf['snapshot_path'])) {
                                            $snapshotImg = $conf['snapshot_path']['front'] ?? null;
                                        } else {
                                            $snapshotImg = $conf['snapshot_path'];
                                        }
                                    }
                                    $imgPath = $snapshotImg ?? ($conf['preview_file'] ?? ($conf['logo_storage_path'] ?? ($item->product->preview_image_path ?? null)));
                                @endphp
                                @if($imgPath)
                                    <img src="{{ asset('storage/'.$imgPath) }}" class="h-full w-full {{ $snapshotImg ? 'object-contain p-1' : 'object-cover' }} group-hover:scale-110 transition-transform duration-700">
                                @else
                                    <div class="h-full w-full flex items-center justify-center text-gray-700"><svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                @endif

                                {{-- Type Badge auf Bild --}}
                                @if($isService)
                                    <div class="absolute bottom-0 left-0 right-0 bg-black/85 text-white text-[8px] font-black uppercase tracking-widest text-center py-0.5 z-10">Service</div>
                                @elseif($isDigital)
                                    <div class="absolute bottom-0 left-0 right-0 bg-blue-600/90 text-white text-[8px] font-black uppercase tracking-widest text-center py-0.5 z-10">Digital</div>
                                @endif
                            </div>

                            {{-- Mobile Head (Title & Price) when stacked --}}
                            <div class="flex-1 min-w-0 flex flex-col justify-center sm:hidden pr-8">
                                <h4 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base mb-1 line-clamp-2 leading-tight">{{ $item->product_name }}</h4>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-[var(--theme-color)] text-sm whitespace-nowrap">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</span>
                                    <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wider">({{ $item->quantity }}x)</span>
                                </div>
                            </div>
                            </div>

                            {{-- Infos --}}
                            <div class="flex-1 min-w-0 flex flex-col justify-center">
                                <div class="hidden sm:flex justify-between items-start gap-4">
                                    <h4 class="font-bold text-gray-900 dark:text-white text-base truncate">{{ $item->product_name }}</h4>
                                    <span class="font-mono font-bold text-[var(--theme-color)] text-base whitespace-nowrap">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</span>
                                </div>
                                <p class="hidden sm:block text-[11px] text-gray-500 font-medium mt-1 uppercase tracking-wider">{{ $item->quantity }}x á {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</p>

                                {{-- Config Hints --}}
                                @if(!empty($conf))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if(!empty($conf['is_gift_voucher']))
                                            @if(($conf['delivery_method'] ?? 'email') === 'post')
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 shadow-sm">
                                                    ✉️ Per Post im Umschlag (+ 3,50 €)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/30 shadow-sm">
                                                    📧 Per E-Mail / PDF
                                                </span>
                                            @endif
                                            @if(!empty($conf['recipient_name']))
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[9px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700">
                                                    Für: {{ $conf['recipient_name'] }}
                                                </span>
                                            @endif
                                        @endif
                                        @if(!empty($conf['text']))
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-400 border border-gray-300 dark:border-gray-700 shadow-sm">
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

                                {{-- Partial Fulfillment UI --}}
                                @if($isOrder)
                                    <div class="mt-4 flex items-center justify-between bg-gray-100/70 dark:bg-gray-950/50 p-2 sm:p-2.5 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner w-full xl:w-max gap-2 sm:gap-4" onclick="event.stopPropagation()">
                                        <div class="flex items-center gap-2 sm:gap-3 w-full justify-between sm:justify-start">
                                            <button wire:click.prevent="decrementCompletedQuantity('{{ $item->id }}')" 
                                                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-red-50 dark:hover:bg-red-500/20 hover:text-red-600 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-500/30 border border-gray-200 dark:border-transparent shadow-sm transition-colors disabled:opacity-50"
                                                    @if($item->completed_quantity <= 0) disabled @endif>
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                                            </button>
                                            
                                            <div class="flex flex-col items-center justify-center min-w-[5rem]">
                                                @if($item->completed_quantity < $item->quantity)
                                                    <span class="text-[10px] font-black text-amber-500 leading-none mb-1">
                                                        #{{ $item->completed_quantity + 1 }} in Arbeit
                                                    </span>
                                                @else
                                                    <span class="text-[10px] font-black text-emerald-500 leading-none mb-1 drop-shadow-[0_0_5px_rgba(16,185,129,0.5)]">
                                                        Fertig
                                                    </span>
                                                @endif
                                                <div class="flex items-center gap-1 font-bold text-[9px] text-gray-500 uppercase tracking-widest">
                                                    <span>Erledigt:</span>
                                                    <span class="text-gray-900 dark:text-white">{{ $item->completed_quantity }} / {{ $item->quantity }}</span>
                                                </div>
                                            </div>

                                            <button wire:click.prevent="incrementCompletedQuantity('{{ $item->id }}')"
                                                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/20 hover:text-emerald-600 dark:hover:text-emerald-400 hover:border-emerald-200 dark:hover:border-emerald-500/30 border border-gray-200 dark:border-transparent shadow-sm transition-colors disabled:opacity-50"
                                                    @if($item->completed_quantity >= $item->quantity) disabled @endif>
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                            </button>

                                            @if($item->quantity > 1)
                                                <div class="w-px h-5 bg-gray-300 dark:bg-gray-800 mx-1"></div>
                                                <button wire:click.prevent="setAllCompletedQuantity('{{ $item->id }}')"
                                                        class="text-[9px] font-black uppercase tracking-widest text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1.5 rounded-lg shadow-sm hover:bg-emerald-500/20 transition-colors disabled:opacity-50"
                                                        @if($item->completed_quantity >= $item->quantity) disabled @endif
                                                        title="Alle als verpackt markieren">
                                                    Alles
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Arrow Indicator --}}
                            <div class="hidden sm:block self-center text-gray-600 group-hover:text-[var(--theme-color)] transition-colors group-hover:translate-x-1 transform duration-300 {{ $isOrder ? 'mr-8' : '' }}">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 4. HINTERLEGTE DATEIEN & DOWNLOADS (ZUGEKLAPPT) (Nur für personalisierbare Produkte) --}}
        @php
            $isPersonalizable = !isset($previewItem->product)
                || $previewItem->product->isPersonalizable() !== false
                || !empty($previewItem->configuration['is_gift_voucher']);
        @endphp

        @if($isPersonalizable)
            <div x-data="{ showFiles: false }" :class="showFiles ? 'overflow-visible' : 'overflow-hidden'" class="bg-white dark:bg-gray-900/50 rounded-[2rem] shadow-sm dark:shadow-inner border border-gray-200 dark:border-gray-800 transition-all duration-300">
            <div @click="showFiles = !showFiles" class="bg-gray-50 dark:bg-gray-950 px-6 py-4 rounded-t-[2rem] border-b border-gray-200 dark:border-gray-800 flex justify-between items-center cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-3">
                    <div class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 shadow-sm dark:shadow-inner">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </div>
                    Dateien & Downloads
                </h3>
                <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center border border-gray-200 dark:border-gray-700 shadow-sm dark:shadow-inner">
                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-300" :class="showFiles ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <div x-show="showFiles" x-collapse style="display: none;">
                <div class="p-5 space-y-3 bg-gray-50/50 dark:bg-gray-900/30">
                    @php
                        // Dateien aus der Konfiguration sammeln
                        $files = $previewItem ? ($previewItem->configuration['files'] ?? []) : [];
                        $logoPath = $previewItem ? ($previewItem->configuration['logo_storage_path'] ?? null) : null;
                        $snapshotPath = $previewItem ? ($previewItem->configuration['snapshot_path'] ?? null) : null;
                        
                        $allFiles = [];

                        // Wenn Gutschein-Produkt, die generierten Gutscheine aus der DB laden
                        if ($previewItem) {
                            $isVoucher = ($previewItem->product->slug ?? '') === 'geschenkgutschein' || (!empty($previewItem->configuration['is_gift_voucher']));
                            if ($isVoucher) {
                                $giftVouchers = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $previewItem->id)->get();
                                foreach ($giftVouchers as $gv) {
                                    $allFiles[] = [
                                        'label' => 'Gutschein PDF (' . $gv->code . ')',
                                        'download_url' => route('customer.gift-voucher.download', $gv->id),
                                        'ext' => 'pdf',
                                        'created_at' => $gv->created_at,
                                    ];
                                }
                            }
                        }

                        // Wenn Logo existiert und noch nicht in der Liste ist, hinzufügen
                        if($logoPath) {
                            $allFiles[] = [
                                'label' => 'Kunden-Logo',
                                'path' => $logoPath,
                                'ext' => pathinfo($logoPath, PATHINFO_EXTENSION),
                                'is_customer_file' => true,
                            ];
                        }

                        // Reguläre Dateien anhängen
                        foreach ($files as $f) {
                            $allFiles[] = [
                                'label' => basename($f),
                                'path' => $f,
                                'ext' => pathinfo($f, PATHINFO_EXTENSION),
                                'is_customer_file' => true,
                            ];
                        }
                        
                        // Snapshot als separaten bewertbaren Download hinzufügen
                        if(!empty($snapshotPath)) {
                            if (is_array($snapshotPath)) {
                                foreach($snapshotPath as $side => $s) {
                                    $label = 'Sicherung (' . ($side === 'front' ? 'Vorderseite' : ($side === 'back' ? 'Rückseite' : ucfirst($side))) . ')';
                                    array_unshift($allFiles, [
                                        'label' => $label,
                                        'path' => $s,
                                        'ext' => pathinfo($s, PATHINFO_EXTENSION),
                                        'is_customer_file' => false,
                                    ]);
                                }
                            } else {
                                array_unshift($allFiles, [
                                    'label' => 'Sicherung (Konfiguration)',
                                    'path' => $snapshotPath,
                                    'ext' => pathinfo($snapshotPath, PATHINFO_EXTENSION),
                                    'is_customer_file' => false,
                                ]);
                            }
                        }
                    @endphp

                    @forelse($allFiles as $fileObj)
                        @php
                            $isOrder = ($model instanceof \App\Models\Order\OrderOrder);
                            $isCustomerFile = !empty($fileObj['is_customer_file']);
                            $filePath = $fileObj['path'] ?? '';
                            $fileLabel = $fileObj['label'] ?? '';
                            
                            $photoIssue = $previewItem->configuration['photo_issue'] ?? null;
                            $hasIssue = false;
                            $isResolved = false;
                            
                            if ($isCustomerFile && $photoIssue) {
                                if (($photoIssue['file'] ?? '') === $filePath || ($photoIssue['file_name'] ?? '') === $fileLabel) {
                                    $hasIssue = true;
                                    $isResolved = !empty($photoIssue['resolved']);
                                }
                            }

                            // Datei-Metadaten für Hover-Info & Copy ermitteln
                            $fileSizeFormatted = null;
                            $fileDimensions = null;
                            $fileMime = null;
                            $fileModified = isset($fileObj['created_at']) ? $fileObj['created_at']->format('d.m.Y H:i') : $model->created_at->format('d.m.Y H:i');
                            $copyValue = $filePath ?: ($fileObj['download_url'] ?? $fileLabel);
                            $storageZoneLabel = $filePath ? ('public/' . ltrim($filePath, '/')) : 'Dynamischer PDF-Stream';

                            if ($filePath) {
                                $absCandidate = null;
                                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($filePath)) {
                                    $absCandidate = \Illuminate\Support\Facades\Storage::disk('public')->path($filePath);
                                    $storageZoneLabel = 'public/' . ltrim($filePath, '/');
                                } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                                    $absCandidate = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
                                    $storageZoneLabel = 'private/' . ltrim($filePath, '/');
                                }

                                if ($absCandidate && is_file($absCandidate)) {
                                    $bytes = (int) @filesize($absCandidate);
                                    $fileSizeFormatted = $bytes >= 1048576
                                        ? number_format($bytes / 1048576, 2, ',', '.') . ' MB'
                                        : number_format($bytes / 1024, 1, ',', '.') . ' KB';
                                    $fileMime = @mime_content_type($absCandidate) ?: strtoupper($fileObj['ext'] ?? '');
                                    $fileModified = date('d.m.Y H:i', (int) @filemtime($absCandidate));
                                    if (in_array(strtolower($fileObj['ext'] ?? ''), ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                                        $imgDims = @getimagesize($absCandidate);
                                        if ($imgDims) {
                                            $fileDimensions = $imgDims[0] . ' × ' . $imgDims[1] . ' px';
                                        }
                                    }
                                }
                            }
                        @endphp
                        <div x-data="{ copied: false }" class="relative flex items-center justify-between p-3.5 rounded-2xl border transition-colors group shadow-sm dark:shadow-inner {{ $hasIssue && !$isResolved ? 'border-amber-400 bg-amber-50/40 dark:bg-amber-950/20' : ($isResolved ? 'border-emerald-400/60 bg-emerald-50/30 dark:bg-emerald-950/10' : 'border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-700') }}">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0 shadow-sm {{ $hasIssue && !$isResolved ? 'bg-amber-500/10 text-amber-500 border border-amber-500/30' : ($isResolved ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/30' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20') }}">
                                    <span class="text-[11px] font-black uppercase tracking-wider">{{ $fileObj['ext'] }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $fileObj['label'] }}</p>

                                        {{-- Info Hover Icon (!) + Copy Icon --}}
                                        <div class="inline-flex items-center gap-1.5 shrink-0">
                                            <div class="relative group/fileinfo inline-flex items-center">
                                                <span class="w-4 h-4 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] flex items-center justify-center text-[9px] font-black cursor-help transition-colors" title="Datei-Details anzeigen">!</span>
                                                <div class="absolute bottom-full left-0 mb-2 hidden group-hover/fileinfo:block z-50 w-72 p-3.5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl text-[10px] font-mono text-gray-700 dark:text-gray-300 space-y-1.5">
                                                    <div class="font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-1.5 mb-1 flex items-center justify-between gap-2">
                                                        <span class="truncate">{{ $fileObj['label'] }}</span>
                                                        @if($fileSizeFormatted)
                                                            <span class="text-[var(--theme-color)] shrink-0">{{ $fileSizeFormatted }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="text-gray-400 shrink-0">Speicherort:</span>
                                                        <span class="text-right break-all text-gray-800 dark:text-gray-200">{{ $storageZoneLabel }}</span>
                                                    </div>
                                                    @if($fileMime)
                                                        <div class="flex justify-between gap-2">
                                                            <span class="text-gray-400">Typ / MIME:</span>
                                                            <span>{{ $fileMime }}</span>
                                                        </div>
                                                    @endif
                                                    @if($fileDimensions)
                                                        <div class="flex justify-between gap-2">
                                                            <span class="text-gray-400">Auflösung:</span>
                                                            <span>{{ $fileDimensions }}</span>
                                                        </div>
                                                    @endif
                                                    <div class="flex justify-between gap-2">
                                                        <span class="text-gray-400">Zeitstempel:</span>
                                                        <span>{{ $fileModified }}</span>
                                                    </div>
                                                    <div class="pt-1.5 mt-1 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                                                        <span class="text-[9px] text-gray-400">Pfad in Zwischenablage:</span>
                                                        <button type="button"
                                                                @click.stop="navigator.clipboard.writeText(@js($copyValue)); copied = true; setTimeout(() => copied = false, 2000)"
                                                                class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color)] hover:text-gray-950 text-gray-700 dark:text-gray-300 font-bold text-[9px] flex items-center gap-1 transition-colors cursor-pointer">
                                                            <x-heroicon-s-clipboard-document x-show="!copied" class="w-3 h-3 text-gray-500" />
                                                            <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3 h-3 text-emerald-500" />
                                                            <span x-text="copied ? 'Kopiert!' : 'Kopieren'"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <button type="button"
                                                    @click.stop="navigator.clipboard.writeText(@js($copyValue)); copied = true; setTimeout(() => copied = false, 2000)"
                                                    class="text-gray-500 hover:text-[var(--theme-color)] transition-colors p-0.5 cursor-pointer"
                                                    title="Dateipfad kopieren">
                                                <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-[var(--theme-color)] transition-colors" />
                                                <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                                            </button>
                                        </div>

                                        @if($hasIssue && !$isResolved)
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 shrink-0">
                                                Nachbesserung angefordert
                                            </span>
                                        @elseif($isResolved)
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 shrink-0">
                                                ✓ Nachgebessert
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[10px] font-medium uppercase tracking-widest text-gray-500 mt-0.5 flex items-center gap-2">
                                        <span>Hinterlegt am {{ isset($fileObj['created_at']) ? $fileObj['created_at']->format('d.m.Y') : $model->created_at->format('d.m.Y') }}</span>
                                        @if($fileSizeFormatted)
                                            <span class="text-gray-400">• {{ $fileSizeFormatted }}</span>
                                        @endif
                                    </p>
                                    @if($hasIssue && !$isResolved)
                                        <p class="text-[11px] text-amber-800 dark:text-amber-300 mt-1 font-medium">
                                            <strong>Mangel:</strong> {{ $photoIssue['reason'] ?? '' }}
                                            @if(!empty($photoIssue['note']))
                                                <span class="opacity-80 italic">({{ $photoIssue['note'] }})</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 ml-4 shrink-0">
                                @if($isCustomerFile && $isOrder)
                                    @if($hasIssue && !$isResolved)
                                        <button wire:click="openPhotoIssueModal('{{ $previewItem->id }}', '{{ $fileObj['path'] }}', '{{ $fileObj['label'] }}')"
                                                type="button"
                                                class="p-3 bg-amber-500 hover:bg-amber-600 text-gray-950 font-bold rounded-xl transition-all shadow-sm cursor-pointer flex items-center gap-1.5 text-xs"
                                                title="Mangel bearbeiten / Erinnerung senden">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            <span class="hidden sm:inline">Mangel</span>
                                        </button>
                                        <button wire:click="resolvePhotoIssue('{{ $previewItem->id }}')"
                                                type="button"
                                                class="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500 hover:text-white rounded-xl border border-emerald-200 dark:border-emerald-800 transition-all shadow-sm cursor-pointer"
                                                title="Mangel als erledigt markieren">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    @else
                                        <button wire:click="openPhotoIssueModal('{{ $previewItem->id }}', '{{ $fileObj['path'] }}', '{{ $fileObj['label'] }}')"
                                                type="button"
                                                class="p-3 bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/30 rounded-xl transition-all shadow-sm dark:shadow-inner cursor-pointer"
                                                title="Foto bemängeln / Nachbesserung anfordern">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                            </svg>
                                        </button>
                                    @endif
                                @endif

                                <a href="{{ $fileObj['download_url'] ?? asset('storage/'.$fileObj['path']) }}"
                                   @if(empty($fileObj['download_url'])) download="{{ $fileObj['label'] . '.' . $fileObj['ext'] }}" @endif
                                   target="_blank"
                                   class="p-3 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded-xl hover:bg-[var(--theme-color)] hover:text-gray-900 shadow-sm dark:shadow-inner transition-all"
                                   title="Herunterladen">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            </div>
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
            @php
                $hasFront = (!empty($previewItem->configuration['texts']) || !empty($previewItem->configuration['logos']));
                $hasBack = (!empty($previewItem->configuration['texts_back']) || !empty($previewItem->configuration['logos_back']));
            @endphp

            @if($hasFront || $hasBack)
                <div class="bg-gray-50 dark:bg-gray-950 px-6 py-5 border-t border-gray-200 dark:border-gray-800">
                    <div class="flex flex-col gap-3">
                        @if($hasFront)
                            <button wire:click.prevent="downloadLaserFile('{{ $previewItem->id }}', 'front')" class="w-full flex items-center justify-center gap-3 px-6 py-3.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-xs font-black uppercase tracking-widest rounded-xl border border-gray-300 dark:border-gray-700 shadow-sm dark:shadow-inner hover:bg-[var(--theme-color)] hover:text-gray-900 hover:border-[var(--theme-color)] transition-all duration-300 group focus:outline-none">
                                <svg class="w-5 h-5 text-[var(--theme-color)] group-hover:text-gray-900 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                xTool Laser-Datei laden (VORDERSEITE)
                            </button>
                        @endif

                        @if($hasBack)
                            <button wire:click.prevent="downloadLaserFile('{{ $previewItem->id }}', 'back')" class="w-full flex items-center justify-center gap-3 px-6 py-3.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-xs font-black uppercase tracking-widest rounded-xl border border-gray-300 dark:border-gray-700 shadow-sm dark:shadow-inner hover:bg-[var(--theme-color)] hover:text-gray-900 hover:border-[var(--theme-color)] transition-all duration-300 group focus:outline-none">
                                <svg class="w-5 h-5 text-[var(--theme-color)] group-hover:text-gray-900 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                xTool Laser-Datei laden (RÜCKSEITE)
                            </button>
                        @endif
                    </div>
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mt-3 text-center leading-relaxed max-w-sm mx-auto">Generiert automatisch maßstabsgetreue SVG Dateien für XCS.</p>
                </div>
            @endif
        </div>
        @endif

        {{-- 5. BESONDERE KUNDENWÜNSCHE (NEU) --}}
        @if(!empty($previewItem->configuration['notes']))
            <div class="bg-amber-50/50 dark:bg-amber-900/10 rounded-[2rem] shadow-sm dark:shadow-inner border border-amber-200 dark:border-amber-500/20 overflow-hidden mb-6">
                <div class="bg-amber-100/50 dark:bg-amber-500/10 px-6 py-4 border-b border-amber-200 dark:border-amber-500/20">
                    <h3 class="text-[10px] font-black text-amber-800 dark:text-amber-400 uppercase tracking-widest flex items-center gap-2 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        Besondere Kundenwünsche
                    </h3>
                </div>
                <div class="p-6 text-sm text-amber-900/80 dark:text-amber-100/80 leading-relaxed font-medium">
                    <div class="bg-white dark:bg-amber-950/50 rounded-2xl p-5 border border-amber-200 dark:border-amber-900 shadow-sm dark:shadow-inner italic">
                        "{!! nl2br(e($previewItem->configuration['notes'])) !!}"
                    </div>
                </div>
            </div>
        @endif

        {{-- 6. COST SUMMARY --}}
        <div>
            <x-shop.cost-summary :model="$model" design="adaptive" />
        </div>

        {{-- 7. LÖSCHEN --}}
        @if($isOrder)
            <div class="pt-8 border-t border-gray-200 dark:border-gray-800 flex justify-center">
                <button wire:click="delete('{{ $model->id }}')" wire:confirm="Wirklich löschen?" class="text-[10px] font-black text-red-500 hover:text-red-400 hover:shadow-[0_0_15px_currentColor] uppercase tracking-widest border border-transparent hover:border-red-500/50 bg-transparent hover:bg-red-500/10 px-4 py-2 rounded-lg transition-all">
                    Bestellung unwiderruflich löschen
                </button>
            </div>
        @endif

    </div>
</div>
