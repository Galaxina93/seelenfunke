<div>
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 animate-fade-in-up min-h-full flex flex-col relative z-10 text-gray-700 dark:text-gray-300">

        {{-- SEITEN-HEADER (Wird ausgeblendet, wenn man in der Detailansicht ist) --}}
        @if(!$selectedOrderId)
            <div class="mb-8 lg:mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-6">
                <div>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Meine Bestellungen</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 text-xs sm:text-sm uppercase tracking-widest font-bold">Die Historie deiner Schätze</p>
                </div>

                {{-- SUCHE --}}
                <div class="relative w-full sm:w-80 md:w-96 group">
                    <div class="absolute inset-0 bg-primary/20 rounded-full blur opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <input wire:model.live.debounce.300ms="searchOrder" type="text" placeholder="Bestellnummer suchen..." class="relative w-full rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:border-primary focus:ring-primary text-sm px-6 py-4 pl-14 transition-all placeholder-gray-400 dark:placeholder-gray-500 font-medium tracking-wide outline-none">
                    <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 absolute left-5 top-3.5 group-hover:text-primary transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
        @endif

        @php
            $countries = shop_setting('active_countries', []);
            $getCountryName = fn($code) => $countries[$code] ?? $code;
        @endphp

        @if($selectedOrderId && $selectedOrder)
            {{-- ========================================== --}}
            {{-- DETAIL ANSICHT --}}
            {{-- ========================================== --}}
            <div class="space-y-10 w-full">

                {{-- NAVIGATION & BESTELLNUMMER --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                    <button wire:click="resetOrderView" class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-all font-bold tracking-widest uppercase text-xs group px-6 py-3 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-600 shadow-sm dark:shadow-lg hover:shadow-md">
                    <span class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center mr-3 group-hover:border-primary/50 transition-colors shadow-inner text-gray-600 dark:text-gray-300 group-hover:text-primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </span>
                        Zurück zur Übersicht
                    </button>
                    <div class="text-left sm:text-right">
                        <h1 class="text-3xl md:text-4xl font-serif font-bold text-gray-900 dark:text-white flex items-center sm:justify-end gap-4 mb-2">
                            Bestellung #{{ $selectedOrder->order_number }}
                            @if($selectedOrder->is_express)
                                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest bg-gradient-to-r from-red-500/20 to-pink-600/20 text-red-500 dark:text-red-400 border border-red-500/50 shadow-sm dark:shadow-[0_0_20px_rgba(239,68,68,0.2)]">
                                🚀 Express
                            </span>
                            @endif
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                            Erfasst am <time datetime="{{ $selectedOrder->created_at }}" class="text-primary">{{ $selectedOrder->created_at->format('d.m.Y - H:i') }} Uhr</time>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

                    {{-- LINKE SPALTE: Artikel --}}
                    <div class="lg:col-span-2 space-y-8">

                        {{-- Status Bar / Progress --}}
                        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 rounded-[2.5rem] p-6 sm:p-8">
                            @php
                                $status = $selectedOrder->status;
                                $progress = match($status) { 'pending' => '15%', 'processing' => '50%', 'shipped' => '80%', 'completed' => '100%', 'cancelled', 'refunded' => '100%', default => '0%' };
                                $barColor = match($status) { 'cancelled', 'refunded' => 'bg-red-500', 'completed' => 'bg-emerald-500', 'shipped' => 'bg-purple-500', 'processing' => 'bg-blue-500', default => 'bg-orange-500' };
                                $isProcessing = in_array($status, ['processing', 'shipped', 'completed']);
                                $isShipped = in_array($status, ['shipped', 'completed']);
                                $isCompleted = $status === 'completed';
                                $isCancelled = in_array($status, ['cancelled', 'refunded']);
                            @endphp

                            <div class="flex justify-between items-end mb-4 px-2">
                                <span class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-[0.2em]">Aktueller Status</span>
                                @php
                                    $statusBadge = match($status) {
                                        'completed' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20',
                                        'shipped' => 'text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/20',
                                        'cancelled' => 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20',
                                        'refunded' => 'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-500/10 border border-gray-200 dark:border-gray-500/20',
                                        'processing' => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 shadow-sm',
                                        default => 'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-500/10 border border-orange-200 dark:border-orange-500/20'
                                    };
                                @endphp
                                <span class="text-xs font-black px-4 py-1.5 rounded-full {{ $statusBadge }}">
                                {{ match($status) { 'pending'=>'Wartend', 'processing'=>'In Bearbeitung', 'shipped'=>'Unterwegs', 'completed'=>'Zugestellt', 'cancelled'=>'Storniert', 'refunded'=>'Erstattet', default=>'Unbekannt' } }}
                            </span>
                            </div>

                            <div class="overflow-hidden h-4 mb-6 text-xs flex rounded-full bg-gray-100 dark:bg-gray-800 shadow-inner">
                                <div style="width: {{ $progress }}" class="flex flex-col text-center whitespace-nowrap text-white justify-center {{ $barColor }} transition-all duration-1000 ease-out relative overflow-hidden shadow-sm dark:shadow-[0_0_20px_currentColor]">
                                    <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
                                </div>
                            </div>

                            @if(!$isCancelled)
                                <div class="grid grid-cols-4 text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-400 dark:text-gray-600 text-center">
                                    <span class="text-orange-500 font-bold">Bestellt</span>
                                    <span class="{{ $isProcessing ? 'text-blue-600 dark:text-blue-400 font-bold' : '' }}">Bearbeitung</span>
                                    <span class="{{ $isShipped ? 'text-purple-600 dark:text-purple-400 font-bold' : '' }}">Unterwegs</span>
                                    <span class="{{ $isCompleted ? 'text-emerald-600 dark:text-emerald-500 font-bold' : '' }}">Zugestellt</span>
                                </div>
                            @else
                                <div class="mt-6 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-2xl p-5 flex items-center justify-center gap-4 text-red-600 dark:text-red-400">
                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <div>
                                        <p class="font-bold text-lg">Bestellung storniert</p>
                                        @if($selectedOrder->cancellation_reason) <p class="text-xs opacity-80 mt-1 uppercase tracking-widest">{{ $selectedOrder->cancellation_reason }}</p> @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Artikel Liste --}}
                        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 rounded-[2.5rem] overflow-hidden">
                            <div class="px-8 py-6 border-b border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/50">
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white font-serif tracking-tight">Artikelübersicht</h3>
                            </div>

                            <ul role="list" class="divide-y divide-gray-100 dark:divide-gray-800/50">
                                @foreach($selectedOrder->items as $item)
                                    <li class="p-6 sm:p-8 flex flex-col sm:flex-row gap-6 sm:gap-8 hover:bg-gray-50 dark:hover:bg-gray-800/20 transition-colors">
                                        {{-- Produktbild --}}
                                        <div class="h-32 w-32 flex-shrink-0 overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-950 shadow-sm dark:shadow-lg relative group">
                                            @if($item->product && !empty($item->product->media_gallery) && isset($item->product->media_gallery[0]))
                                                <img src="{{ Storage::url($item->product->media_gallery[0]['path']) }}" class="h-full w-full object-cover object-center group-hover:scale-110 transition-transform duration-700">
                                            @elseif($item->product && $item->product->preview_image_path)
                                                <img src="{{ Storage::url($item->product->preview_image_path) }}" class="h-full w-full object-cover object-center group-hover:scale-110 transition-transform duration-700">
                                            @else
                                                <div class="h-full w-full flex items-center justify-center text-gray-400 dark:text-gray-700">
                                                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                </div>
                                            @endif
                                            @php $type = $item->product->type ?? 'physical'; @endphp
                                            <div class="absolute top-2 left-2 px-2.5 py-1 bg-black/80 backdrop-blur text-[9px] font-black uppercase tracking-widest text-white rounded-md border border-gray-700">
                                                {{ match($type) { 'digital' => 'Digital', 'service' => 'Service', default => 'Artikel' } }}
                                            </div>
                                        </div>

                                        <div class="flex-1 flex flex-col justify-between">
                                            <div>
                                                <div class="flex justify-between items-start mb-2">
                                                    <h4 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $item->product_name }}</h4>
                                                    <p class="text-xl font-serif font-bold text-primary ml-4 whitespace-nowrap">
                                                        {{ number_format(($item->total_price / 100), 2, ',', '.') }} €
                                                    </p>
                                                </div>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">
                                                    {{ $item->quantity }}x je {{ number_format(($item->unit_price / 100), 2, ',', '.') }} €
                                                </p>

                                                @if(!empty($item->configuration['is_gift_voucher']))
                                                    <div class="mt-2.5 flex flex-wrap items-center gap-2 text-xs">
                                                        @if(!empty($item->configuration['recipient_name']))
                                                            <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 font-medium">
                                                                <strong>Für:</strong> {{ $item->configuration['recipient_name'] }}
                                                            </span>
                                                        @endif
                                                        @if(($item->configuration['delivery_method'] ?? 'email') === 'post')
                                                            <span class="px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 font-bold">
                                                                ✉️ Per Post im Umschlag (+ 3,50 € Versand)
                                                            </span>
                                                        @else
                                                            <span class="px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30 font-bold">
                                                                📧 Per E-Mail / PDF-Download (Versandkostenfrei)
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="mt-6 flex flex-wrap items-center gap-4">
                                                @if(!empty($item->configuration) && $item->product && $item->product->isPersonalizable())
                                                    <button wire:click="openPreview('{{ $item->id }}')" class="inline-flex items-center gap-3 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $previewItemId == $item->id ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.4)]' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700' }}">
                                                        <span>{{ $previewItemId == $item->id ? 'Design verbergen' : 'Design ansehen' }}</span>
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    </button>
                                                @endif

                                                @php
                                                    $photoIssue = $item->configuration['photo_issue'] ?? null;
                                                    $hasPhotoIssue = $photoIssue && empty($photoIssue['resolved']);
                                                    $isPhotoIssueResolved = $photoIssue && !empty($photoIssue['resolved']);
                                                @endphp

                                                @if($selectedOrder->status === 'pending' && $type === 'physical' && $item->product && $item->product->isPersonalizable())
                                                    <button wire:click="openEdit('{{ $item->id }}')" class="inline-flex items-center gap-3 px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $editItemId == $item->id ? 'bg-cyan-500 text-white shadow-sm dark:shadow-[0_0_15px_rgba(6,182,212,0.4)]' : ($hasPhotoIssue ? 'bg-amber-500 text-gray-950 hover:bg-amber-600 shadow-md ring-2 ring-amber-400/50' : 'bg-cyan-50 dark:bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 hover:bg-cyan-500 hover:text-white border border-cyan-200 dark:border-cyan-500/30') }}">
                                                        <span>{{ $editItemId == $item->id ? 'Abbrechen' : ($hasPhotoIssue ? 'Foto jetzt nachbessern' : 'Artikel anpassen') }}</span>
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                    </button>
                                                @endif
                                            </div>
                                            @if($selectedOrder->status === 'pending' && $type === 'physical' && $item->product && $item->product->isPersonalizable())
                                                <div x-data="{ successMsg: false, showNotice: true }"
                                                     x-on:order-item-updated.window="if($event.detail.itemId == '{{ $item->id }}') { successMsg = true; setTimeout(() => successMsg = false, 5000) }">

                                                    {{-- MANUFAKTUR-RÜCKFRAGE HINWEISBOX --}}
                                                    @if($hasPhotoIssue)
                                                        <div class="mt-4 bg-amber-50 dark:bg-amber-950/40 border-2 border-amber-400 dark:border-amber-600 rounded-2xl p-4 shadow-sm">
                                                            <div class="flex items-start gap-3.5">
                                                                <div class="p-2.5 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0">
                                                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <h5 class="text-xs font-black uppercase tracking-wider text-amber-900 dark:text-amber-200">
                                                                            📸 Manufaktur-Rückfrage: Foto bitte nachbessern
                                                                        </h5>
                                                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-200/80 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200">
                                                                            Aktion erforderlich
                                                                        </span>
                                                                    </div>
                                                                    <p class="text-xs text-amber-800 dark:text-amber-300 mt-1 font-medium">
                                                                        <strong>Beanstandung:</strong> {{ $photoIssue['reason'] ?? 'Qualität nicht optimal' }}
                                                                    </p>
                                                                    @if(!empty($photoIssue['note']))
                                                                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-1.5 p-2.5 rounded-xl bg-white/80 dark:bg-gray-900/80 border border-amber-200 dark:border-amber-800/60 italic">
                                                                            💬 „{{ $photoIssue['note'] }}“
                                                                        </p>
                                                                    @endif
                                                                    <p class="text-[11px] text-amber-700/90 dark:text-amber-400/90 mt-2 leading-relaxed">
                                                                        Klicke oben auf <strong>„Foto jetzt nachbessern“</strong>, um dein Bild im Konfigurator auszutauschen. Sobald du speicherst, starten wir umgehend mit der Gravur!
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @elseif($isPhotoIssueResolved)
                                                        <div class="mt-4 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 rounded-xl p-3 flex items-center gap-3 shadow-sm">
                                                            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            <p class="text-xs text-emerald-800 dark:text-emerald-300 font-medium">
                                                                <strong>Danke!</strong> Dein Foto wurde aktualisiert. Unser Manufaktur-Team prüft die neue Datei und setzt die Fertigung fort.
                                                            </p>
                                                        </div>
                                                    @endif

                                                    @if(!$hasPhotoIssue)
                                                        <div x-show="showNotice" x-transition class="mt-4 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl p-3 flex items-start justify-between gap-3 shadow-sm">
                                                            <div class="flex items-start gap-3">
                                                                <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                <p class="text-xs text-amber-700 dark:text-amber-500/90 font-medium">
                                                                    <strong>Wichtiger Hinweis:</strong> Solange die Bestellung nicht in Bearbeitung geht, sind Anpassungen möglich.
                                                                </p>
                                                            </div>
                                                            <button type="button" @click="showNotice = false" class="text-amber-400 hover:text-amber-600 dark:hover:text-amber-300 p-0.5 rounded cursor-pointer transition-colors" title="Hinweis ausblenden">
                                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                            </button>
                                                        </div>
                                                    @endif

                                                    <template x-if="successMsg">
                                                        <div x-transition class="mt-2 bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 rounded-xl p-3 flex items-center gap-3 shadow-sm">
                                                            <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                            <p class="text-xs text-green-700 dark:text-green-400 font-medium">
                                                                Sicherungsscreenshot unter Dokumente aktualisiert.
                                                            </p>
                                                        </div>
                                                    </template>
                                                </div>
                                            @endif
                                        </div>
                                    </li>

                                    @if($previewItemId == $item->id && $this->previewItem)
                                        <div class="bg-gray-50 dark:bg-gray-950 p-6 sm:p-8 border-y border-gray-200 dark:border-gray-800 animate-fade-in-down shadow-inner" x-data="{ showHints: true }">
                                            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-6">
                                                <h4 class="text-sm font-black text-gray-500 dark:text-gray-400 uppercase tracking-[0.3em]">Manufaktur Details</h4>
                                                <div class="flex items-center gap-3">
                                                    @if($item->product && !$item->product->isDigital() && !$item->product->isService())
                                                        <button type="button"
                                                                @click="showHints = !showHints; window.dispatchEvent(new CustomEvent('toggle-configurator-hints', { detail: { show: showHints } }))"
                                                                class="text-[11px] font-bold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-colors cursor-pointer">
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                            <span x-text="showHints ? 'Hinweise ausblenden' : 'Hinweise einblenden'"></span>
                                                        </button>
                                                    @endif
                                                    @if($item->config_fingerprint)
                                                        <span class="text-[10px] bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400 px-3 py-1 rounded-full border border-green-200 dark:border-green-500/30 flex items-center gap-2 font-black uppercase tracking-widest shadow-sm dark:shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                            Versiegelt
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm dark:shadow-2xl w-full">
                                                <livewire:shop.product.product-configurator.product-configurator :product="$this->previewItem->product" :initialData="$this->previewItem->configuration" :qty="$this->previewItem->quantity" context="preview" :key="'conf-prev-'.$this->previewItem->id" />
                                            </div>
                                        </div>
                                    @endif

                                    @if($editItemId == $item->id && $this->editItem)
                                        <div class="bg-gray-50 dark:bg-gray-950 p-6 sm:p-8 border-y border-gray-200 dark:border-gray-800 animate-fade-in-down shadow-inner" x-data="{ showHints: true }">
                                            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-6">
                                                <h4 class="text-sm font-black text-cyan-600 dark:text-cyan-500 uppercase tracking-[0.3em] flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                    Artikel anpassen
                                                </h4>
                                                <div class="flex items-center gap-3">
                                                    @if($item->product && !$item->product->isDigital() && !$item->product->isService())
                                                        <button type="button"
                                                                @click="showHints = !showHints; window.dispatchEvent(new CustomEvent('toggle-configurator-hints', { detail: { show: showHints } }))"
                                                                class="text-[11px] font-bold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-3 py-1.5 rounded-lg flex items-center gap-1.5 transition-colors cursor-pointer">
                                                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                            <span x-text="showHints ? 'Hinweise ausblenden' : 'Hinweise einblenden'"></span>
                                                        </button>
                                                    @endif
                                                    <div class="text-[10px] text-gray-600 dark:text-gray-400 font-bold uppercase tracking-widest bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-3 py-1.5 rounded-lg flex items-center gap-2">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        Live-Änderung an der Bestellung
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="bg-white dark:bg-gray-900 rounded-[2rem] border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm dark:shadow-2xl w-full">
                                                <livewire:shop.product.product-configurator.product-configurator :product="$this->editItem->product" :cartItem="$this->editItem" context="order_edit" :key="'conf-edit-'.$this->editItem->id" />
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </ul>

                            <x-shop.cost-summary
                                :model="$selectedOrder"
                                design="adaptive"
                                :showTitle="false"
                                containerOverride="bg-gray-50 dark:bg-gray-950 p-6 sm:p-8 border-t border-gray-200 dark:border-gray-800"
                            />
                        </div>
                    </div>

                    {{-- RECHTE SPALTE: Info & Dokumente --}}
                    <div class="space-y-8">
                        {{-- DHL Sendungsverfolgung (Golden Box) --}}
                        @if($selectedOrder->shipments && $selectedOrder->shipments->isNotEmpty())
                        <div class="bg-primary/5 p-6 sm:p-8 rounded-[2.5rem] border border-primary/20 shadow-inner relative z-10 group overflow-hidden pointer-events-auto">
                            <div class="absolute inset-0 overflow-hidden rounded-[2.5rem] pointer-events-none">
                                <div class="absolute inset-0 bg-gradient-to-r from-primary/0 via-primary/5 to-primary/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000 ease-in-out"></div>
                            </div>

                            <div class="relative z-20">
                                <h4 class="text-xs font-black text-amber-800 dark:text-primary uppercase tracking-widest mb-6 flex items-center gap-3 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">
                                    <span class="p-2 rounded-lg bg-primary/10 border border-primary/20 shadow-inner">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </span>
                                    DHL Tracking
                                </h4>

                                <div class="space-y-4">
                                    @foreach($selectedOrder->shipments as $index => $shipment)
                                        <div>
                                            @if($selectedOrder->shipments->count() > 1)
                                                <div class="flex items-center gap-2 mb-1.5 ml-1">
                                                    <label class="text-[9px] font-black text-amber-800 dark:text-primary uppercase tracking-[0.2em] drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Paket {{ $index + 1 }}</label>
                                                </div>
                                            @endif
                                            <div class="relative flex items-center" x-data="{ copySuccess: false }">
                                                <input type="text" readonly value="{{ $shipment->tracking_number }}"
                                                       class="w-full rounded-2xl bg-white/90 dark:bg-gray-950/80 border border-primary/30 py-4 pl-5 pr-24 font-mono tracking-[0.1em] text-sm text-primary shadow-inner outline-none uppercase truncate transition-colors focus:border-primary/60">

                                                <div class="absolute right-2 flex gap-1 items-center">
                                                    <button @click="navigator.clipboard.writeText('{{ $shipment->tracking_number }}'); copySuccess = true; setTimeout(() => copySuccess = false, 2000)"
                                                            class="p-2 text-primary/70 hover:text-primary hover:bg-primary/10 rounded-xl transition-all" title="Nummer kopieren">
                                                        <svg x-show="!copySuccess" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                        <svg x-show="copySuccess" x-cloak class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    </button>
                                                    <a href="https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode={{ $shipment->tracking_number }}"
                                                       target="_blank"
                                                       class="p-2 text-primary/70 hover:text-primary hover:bg-primary/10 rounded-xl transition-all" title="Auf DHL.de verfolgen">
                                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if($selectedOrder->shipments->count() > 1)
                                    <p class="text-[9px] font-black uppercase tracking-widest text-primary/70 mt-4 ml-1 leading-relaxed">
                                        Hinweis: Diese Bestellung wurde in {{ $selectedOrder->shipments->count() }} Pakete aufgeteilt.
                                    </p>
                                @endif

                                @if(session()->has('delivery_confirmed'))
                                    <div class="mt-4 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-medium flex items-center gap-2.5">
                                        <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span>{{ session('delivery_confirmed') }}</span>
                                    </div>
                                @endif

                                @if($selectedOrder->status === 'shipped')
                                    <div class="mt-5 pt-4 border-t border-primary/20 flex flex-col sm:flex-row items-center justify-between gap-3">
                                        <div class="text-[11px] text-amber-900/80 dark:text-primary/90 font-medium">
                                            Paket schon erhalten?
                                        </div>
                                        <button wire:click="confirmDelivery('{{ $selectedOrder->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:confirm="Möchten Sie bestätigen, dass Sie alle Pakete dieser Bestellung erhalten haben?"
                                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-primary text-gray-900 hover:bg-primary/90 text-xs font-black uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-2 cursor-pointer">
                                            <svg class="w-4 h-4 text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Erhalt bestätigen</span>
                                        </button>
                                    </div>
                                @elseif($selectedOrder->status === 'completed')
                                    <div class="mt-4 pt-3 border-t border-primary/20 flex items-center gap-2 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Erfolgreich zugestellt</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 rounded-[2.5rem] p-6 sm:p-8">
                            <h3 class="text-xs font-black text-gray-500 dark:text-gray-400 mb-6 uppercase tracking-[0.3em] flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                Transaktion
                            </h3>
                            @php
                                $paymentBadge = match($selectedOrder->payment_status) {
                                    'paid' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30',
                                    'unpaid' => 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30',
                                    'refunded' => 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30',
                                    default => 'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700'
                                };
                            @endphp
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ match($selectedOrder->payment_method) { 'paypal'=>'PayPal', 'stripe'=>'Kreditkarte', 'invoice'=>'Rechnung', default=>'Online Zahlung' } }}</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border {{ $paymentBadge }}">
                                {{ match($selectedOrder->payment_status) { 'paid' => 'Bezahlt', 'unpaid' => 'Offen', 'refunded' => 'Erstattet', default => ucfirst($selectedOrder->payment_status) } }}
                            </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                Datum: {{ $selectedOrder->payment_status === 'paid' ? ($selectedOrder->invoices->first()?->paid_at?->format('d.m.Y H:i') ?? '-') : 'Ausstehend' }}
                            </p>
                        </div>

                        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 rounded-[2.5rem] p-6 sm:p-8">
                            <h3 class="text-xs font-black text-gray-500 dark:text-gray-400 mb-6 uppercase tracking-[0.3em] flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Dokumente
                            </h3>
                            @if($selectedOrder->invoices->isEmpty())
                                <div class="text-center py-8 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800">
                                    <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-700 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">Rechnung wird erstellt</p>
                                </div>
                            @else
                                <div class="space-y-4">
                                    @foreach($selectedOrder->invoices as $invoice)
                                        <div class="group flex items-center justify-between p-4 rounded-2xl border transition-all duration-300 {{ $invoice->isCreditNote() ? 'border-red-300 dark:border-red-500/30 bg-red-50 dark:bg-red-500/5 hover:bg-red-100 dark:hover:bg-red-500/10' : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 hover:border-primary/50' }}">
                                            <div class="flex-1 flex items-center gap-4 min-w-0">
                                                <div class="{{ $invoice->isCreditNote() ? 'text-red-500' : 'text-primary' }} shrink-0">
                                                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-bold tracking-wider {{ $invoice->isCreditNote() ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white group-hover:text-primary' }} transition-colors truncate">
                                                        {{ $invoice->invoice_number }}
                                                    </p>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mt-1 truncate">{{ $invoice->created_at->format('d.m.Y') }}</p>
                                                </div>
                                            </div>
                                            <a href="{{ route('invoice.download', $invoice->id) }}" target="_blank" class="w-10 h-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-500 hover:text-primary group-hover:border-primary transition-all shadow-sm shrink-0" title="Herunterladen">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </a>
                                        </div>
                                    @endforeach
 
                                    @php
                                        $downloadsUnlocked = $selectedOrder->payment_status === 'paid' && !in_array($selectedOrder->status, ['cancelled', 'refunded'], true);
                                    @endphp

                                    {{-- Digitale Downloads als Dokumente --}}
                                    @foreach($selectedOrder->items as $item)
                                        @if($item->product && $item->product->type === 'digital' && !empty($item->product->digital_download_path))
                                        <div class="group flex items-center justify-between p-4 rounded-2xl border transition-all duration-300 border-cyan-200 dark:border-cyan-500/30 bg-cyan-50 dark:bg-cyan-500/5 hover:bg-cyan-100 dark:hover:bg-cyan-500/10 hover:border-cyan-400 dark:hover:border-cyan-500/50">
                                            <div class="flex-1 flex items-center gap-4 min-w-0">
                                                <div class="text-cyan-600 dark:text-cyan-500 shrink-0">
                                                    <svg class="w-8 h-8 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-bold tracking-wider text-cyan-700 dark:text-cyan-400 group-hover:text-cyan-600 dark:group-hover:text-cyan-300 transition-colors truncate" title="{{ $item->product_name }}">
                                                        {{ $item->product_name }}
                                                    </p>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mt-1 truncate">Digitale Produktdatei ({{ pathinfo($item->product->digital_filename ?? '', PATHINFO_EXTENSION) ?: 'File' }})</p>
                                                </div>
                                            </div>
                                            @if($downloadsUnlocked)
                                                <a href="{{ route('digital-product.download', $item->id) }}" target="_blank" class="w-10 h-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-500 hover:text-cyan-500 group-hover:border-cyan-500 transition-all shadow-sm shrink-0" title="Datei herunterladen">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                </a>
                                            @else
                                                <span class="px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-[9px] font-bold uppercase tracking-wider shrink-0">
                                                    {{ in_array($selectedOrder->status, ['cancelled', 'refunded'], true) ? 'Gesperrt' : 'Nach Zahlung' }}
                                                </span>
                                            @endif
                                        </div>
                                        @endif
                                    @endforeach

                                    {{-- Geschenkgutscheine (PDF) als Dokumente --}}
                                    @foreach($selectedOrder->items as $item)
                                        @if(!empty($item->configuration['is_gift_voucher']))
                                            @php
                                                $vouchers = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $item->id)->get();
                                            @endphp
                                            @foreach($vouchers as $voucher)
                                                <div class="group flex items-center justify-between p-4 rounded-2xl border transition-all duration-300 border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/5 hover:bg-amber-100 dark:hover:bg-amber-500/10 hover:border-amber-400 dark:hover:border-amber-500/50">
                                                    <div class="flex-1 flex items-center gap-4 min-w-0">
                                                        <div class="text-amber-600 dark:text-amber-500 shrink-0">
                                                            <svg class="w-8 h-8 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-bold tracking-wider text-amber-700 dark:text-amber-400 group-hover:text-amber-600 dark:group-hover:text-amber-300 transition-colors truncate" title="Gutschein für {{ $voucher->recipient_name }}">
                                                                Gutschein: {{ $voucher->code }}
                                                            </p>
                                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mt-1 truncate">Wertgutschein PDF (für {{ $voucher->recipient_name }})</p>
                                                        </div>
                                                    </div>
                                                    @if($downloadsUnlocked && $voucher->is_active)
                                                        <a href="{{ route('customer.gift-voucher.download', $voucher->id) }}" target="_blank" class="w-10 h-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-500 hover:text-amber-500 group-hover:border-amber-500 transition-all shadow-sm shrink-0" title="Gutschein PDF herunterladen">
                                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                        </a>
                                                    @else
                                                        <span class="px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-[9px] font-bold uppercase tracking-wider shrink-0">
                                                            {{ !$voucher->is_active ? 'Eingelöst/Inaktiv' : 'Nach Zahlung' }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    @endforeach
 
                                    {{-- Configurator Snapshots als Dokumente --}}
                                    @php
                                        $allSnapshots = [];
                                        foreach($selectedOrder->items as $item) {
                                            if (!empty($item->configuration['snapshot_path'])) {
                                                $paths = is_array($item->configuration['snapshot_path'])
                                                    ? $item->configuration['snapshot_path']
                                                     : ['Konfiguration' => $item->configuration['snapshot_path']];

                                                foreach($paths as $side => $path) {
                                                    $label = count($paths) > 1
                                                        ? $item->product_name . ' (' . ($side === 'front' ? 'Vorder-' : ($side === 'back' ? 'Rückseite' : ucfirst($side))) . ')'
                                                        : $item->product_name . ' (Sicherung)';

                                                    $allSnapshots[] = [
                                                        'label' => $label,
                                                        'path' => $path,
                                                        'date' => $selectedOrder->created_at->format('d.m.Y')
                                                    ];
                                                }
                                            }
                                        }
                                    @endphp

                                    @foreach($allSnapshots as $snap)
                                        <div class="group flex items-center justify-between p-4 rounded-2xl border transition-all duration-300 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 hover:border-primary/50">
                                            <div class="flex-1 flex items-center gap-4 min-w-0">
                                                <div class="text-primary shrink-0">
                                                    <svg class="w-8 h-8 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-bold tracking-wider text-gray-900 dark:text-white group-hover:text-primary transition-colors truncate" title="{{ $snap['label'] }}">
                                                        {{ $snap['label'] }}
                                                    </p>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest mt-1 truncate">{{ $snap['date'] }}</p>
                                                </div>
                                            </div>

                                            <a href="{{ resolve_snapshot_url($snap['path'], asset('storage/' . $snap['path'])) }}" download="Seelenfunke_Entwurf_{{ $this->selectedOrder->order_number }}_{{ Str::slug($snap['label']) }}.{{ pathinfo($snap['path'], PATHINFO_EXTENSION) }}" target="_blank" class="w-10 h-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-500 hover:text-primary group-hover:border-primary transition-all shadow-sm shrink-0" title="Herunterladen">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 rounded-[2.5rem] p-6 sm:p-8">
                            <div class="mb-8">
                                <h3 class="text-xs font-black text-gray-500 dark:text-gray-400 mb-4 uppercase tracking-[0.3em] flex items-center gap-3">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    Lieferadresse
                                </h3>
                                @php $ship = $selectedOrder->shipping_address ?? $selectedOrder->billing_address; @endphp
                                <address class="text-sm text-gray-600 dark:text-gray-400 not-italic leading-loose pl-6 border-l-2 border-primary/50">
                                    <span class="text-gray-900 dark:text-white font-bold block mb-2 text-base">{{ $ship['first_name'] ?? '' }} {{ $ship['last_name'] ?? '' }}</span>
                                    @if(!empty($ship['company'])) <span class="text-primary font-bold">{{ $ship['company'] }}</span><br> @endif
                                    {{ $ship['address'] ?? '' }}<br>
                                    {{ $ship['postal_code'] ?? '' }} {{ $ship['city'] ?? '' }}<br>
                                    {{ $getCountryName($ship['country'] ?? 'DE') }}
                                </address>
                            </div>
                            <div class="pt-8 border-t border-gray-200 dark:border-gray-800">
                                <h3 class="text-xs font-black text-gray-500 dark:text-gray-400 mb-4 uppercase tracking-[0.3em] flex items-center gap-3">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                                    Rechnungsadresse
                                </h3>
                                <address class="text-sm text-gray-500 dark:text-gray-500 not-italic leading-relaxed pl-6 border-l-2 border-gray-200 dark:border-gray-800">
                                    <span class="text-gray-800 dark:text-gray-300 font-bold block mb-1">{{ $selectedOrder->billing_address['first_name'] ?? '' }} {{ $selectedOrder->billing_address['last_name'] ?? '' }}</span>
                                    @if(!empty($selectedOrder->billing_address['company'])) {{ $selectedOrder->billing_address['company'] }}<br> @endif
                                    {{ $selectedOrder->billing_address['address'] ?? '' }}<br>
                                    {{ $selectedOrder->billing_address['postal_code'] ?? '' }} {{ $selectedOrder->billing_address['city'] ?? '' }}<br>
                                    {{ $getCountryName($selectedOrder->billing_address['country'] ?? 'DE') }}
                                </address>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        @else
            {{-- ========================================== --}}
            {{-- LISTEN ANSICHT (Übersicht aller Bestellungen) --}}
            {{-- ========================================== --}}
            <div class="space-y-8 w-full">
                @forelse($orders as $o)
                    <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden hover:border-primary/50 transition-all duration-500 group" wire:key="order-list-{{ $o->id }}">
                        <div class="px-6 sm:px-8 py-6 bg-gray-50/80 dark:bg-gray-800/40 border-b border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-6">
                            <div class="flex items-center gap-6">
                                <div class="w-16 h-16 rounded-full bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-primary font-serif font-bold text-2xl shadow-sm dark:shadow-inner group-hover:border-primary/50 transition-colors">
                                    {{ substr($o->order_number, -2) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-3 mb-1">
                                        <span class="font-bold text-gray-900 dark:text-white text-lg tracking-wide">#{{ $o->order_number }}</span>
                                        @if($o->is_express)
                                            <span class="bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest border border-red-200 dark:border-red-500/30">Express</span>
                                        @endif
                                    </div>
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ $o->created_at->format('d. F Y') }} <span class="mx-2 text-gray-300 dark:text-gray-700">|</span> {{ $o->items->count() }} Artikel</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-8">
                                <div class="text-right hidden sm:block">
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase font-black tracking-widest mb-1">Gesamtbetrag</p>
                                    <p class="font-serif font-bold text-2xl text-primary">{{ number_format($o->total_price / 100, 2, ',', '.') }} €</p>
                                </div>
                                <button wire:click="showOrder('{{ $o->id }}')" class="flex items-center gap-4 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 hover:border-gray-400 dark:hover:border-primary text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-primary px-6 py-3 rounded-full text-xs font-black uppercase tracking-widest transition-all shadow-sm dark:shadow-xl dark:group-hover:shadow-[0_0_20px_rgba(197,160,89,0.2)]">
                                    Details
                                    <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-900 group-hover:bg-gray-200 dark:group-hover:bg-primary/20 flex items-center justify-center transition-colors">
                                        <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="p-6 sm:p-8 relative group/slider"
                             x-data="{
                                    scrollAmount: 0,
                                    container: null,
                                    init() { this.container = this.$refs.sliderContainer; },
                                    scroll(direction) {
                                        const scrollVal = 300;
                                        if(direction === 'left') this.container.scrollBy({ left: -scrollVal, behavior: 'smooth' });
                                        else this.container.scrollBy({ left: scrollVal, behavior: 'smooth' });
                                    }
                                 }">

                            @php $limit = 10; @endphp

                            @if($o->items->count() > 4)
                                <button @click.stop="scroll('left')" class="absolute left-4 top-1/3 -translate-y-1/2 z-10 w-12 h-12 bg-white/90 dark:bg-gray-900/90 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-full shadow-md dark:shadow-2xl flex items-center justify-center text-gray-600 dark:text-gray-400 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 disabled:opacity-0 -translate-x-4 group-hover/slider:translate-x-0 duration-500">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                </button>
                            @endif

                            <div x-ref="sliderContainer" class="flex gap-6 overflow-x-auto pb-6 scrollbar-hide snap-x relative z-0" style="scrollbar-width: none; -ms-overflow-style: none;">
                                @foreach($o->items->take($limit) as $item)
                                    <div class="snap-start shrink-0 w-40 relative group/item cursor-pointer" wire:click="showOrder('{{ $o->id }}')">
                                        <div class="aspect-square rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 overflow-hidden relative mb-4 shadow-sm group-hover/item:shadow-[0_0_25px_rgba(197,160,89,0.3)] group-hover/item:border-primary/50 transition-all duration-500">
                                            @if($item->product && !empty($item->product->media_gallery) && isset($item->product->media_gallery[0]))
                                                <img src="{{ Storage::url($item->product->media_gallery[0]['path']) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover/item:scale-110">
                                            @elseif($item->product && $item->product->preview_image_path)
                                                <img src="{{ Storage::url($item->product->preview_image_path) }}" class="w-full h-full object-cover transition-transform duration-700 group-hover/item:scale-110">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-gray-700"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                                            @endif

                                            @if($item->quantity > 1)
                                                <div class="absolute top-2 right-2 bg-primary text-gray-900 text-[10px] font-black uppercase px-2 py-1 rounded-lg shadow-sm">
                                                    {{ $item->quantity }}x
                                                </div>
                                            @endif
                                        </div>
                                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300 truncate group-hover/item:text-gray-900 dark:group-hover/item:text-white transition-colors text-center" title="{{ $item->product_name }}">
                                            {{ $item->product_name }}
                                        </p>
                                    </div>
                                @endforeach

                                @if($o->items->count() > $limit)
                                    <div class="snap-start shrink-0 w-32 flex items-center justify-center">
                                        <button wire:click="showOrder('{{ $o->id }}')" class="text-xs font-black uppercase tracking-widest text-gray-500 hover:text-primary transition-all flex flex-col items-center gap-2 group/more">
                                        <span class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 group-hover/more:border-primary flex items-center justify-center text-2xl shadow-sm dark:shadow-inner transition-colors">
                                            +{{ $o->items->count() - $limit }}
                                        </span>
                                            weitere
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if($o->items->count() > 4)
                                <button @click.stop="scroll('right')" class="absolute right-4 top-1/3 -translate-y-1/2 z-10 w-12 h-12 bg-white/90 dark:bg-gray-900/90 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-full shadow-md dark:shadow-2xl flex items-center justify-center text-gray-600 dark:text-gray-400 hover:text-primary hover:border-primary transition-all opacity-0 group-hover/slider:opacity-100 translate-x-4 group-hover/slider:translate-x-0 duration-500">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            @endif

                            <div class="mt-4 pt-6 border-t border-gray-100 dark:border-gray-800/50 flex justify-between items-center">
                                @php
                                    $statusColor = match($o->status) {
                                        'completed' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20',
                                        'shipped' => 'text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-500/10 border-purple-200 dark:border-purple-500/20',
                                        'cancelled' => 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/20',
                                        'refunded' => 'text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-500/10 border-gray-200 dark:border-gray-500/20',
                                        'processing' => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/20',
                                        default => 'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-500/10 border-orange-200 dark:border-orange-500/20'
                                    };
                                    $statusLabel = match($o->status) {
                                        'completed' => 'Zustellung erfolgreich',
                                        'shipped' => 'Unterwegs zu dir',
                                        'cancelled' => 'Storniert',
                                        'refunded' => 'Erstattet',
                                        'processing' => 'Wird vorbereitet',
                                        'pending' => 'Bestellung eingegangen',
                                        default => 'Unbekannt'
                                    };
                                @endphp
                                <div class="flex items-center gap-3 px-4 py-2 rounded-full border text-[10px] font-black uppercase tracking-widest {{ $statusColor }}">
                                    @if($o->status === 'shipped') <svg class="w-4 h-4 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> @endif
                                    @if($o->status === 'completed') <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> @endif
                                    {{ $statusLabel }}
                                </div>

                                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-600 hidden sm:block">
                                    Letztes Update: <span class="text-gray-600 dark:text-gray-400">{{ $o->updated_at->diffForHumans() }}</span>
                                </div>
                            </div>

                            {{-- DHL SENDUNGSVERFOLGUNG (OVERVIEW LISTE) --}}
                            @if($o->shipments && $o->shipments->isNotEmpty())
                            <div class="mt-4" x-data="{ copySuccessIds: [] }">
                                <div class="bg-primary/5 px-6 sm:px-8 py-6 border-t border-primary/20 shadow-inner group">
                                    <div class="flex flex-col sm:flex-row gap-6 sm:items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <span class="p-2.5 rounded-xl bg-primary/10 border border-primary/20 shadow-inner text-primary">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            </span>
                                            <div>
                                                <h4 class="text-sm font-black text-amber-800 dark:text-primary uppercase tracking-widest drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">DHL Tracking</h4>
                                                @if($o->shipments->count() > 1)
                                                    <p class="text-[10px] uppercase font-black text-primary/70 tracking-[0.2em] mt-1">Split-Versand ({{ $o->shipments->count() }} Pakete)</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex-1 lg:max-w-xl grid gap-3 w-full">
                                            @foreach($o->shipments as $index => $shipment)
                                                <div class="relative flex items-center">
                                                    @if($o->shipments->count() > 1)
                                                        <span class="absolute left-4 text-[10px] font-black text-primary/70 uppercase pointer-events-none tracking-widest">Paket {{ $index + 1 }}:</span>
                                                    @endif
                                                    <input type="text" readonly value="{{ $shipment->tracking_number }}"
                                                           class="w-full rounded-2xl bg-white/90 dark:bg-gray-950/80 border border-primary/30 py-3.5 {{ $o->shipments->count() > 1 ? 'pl-[90px]' : 'pl-5' }} pr-24 font-mono tracking-[0.1em] text-sm md:text-base text-primary shadow-inner outline-none uppercase truncate">

                                                    <div class="absolute right-2 flex gap-1 items-center">
                                                        <button @click.stop="navigator.clipboard.writeText('{{ $shipment->tracking_number }}'); copySuccessIds.push('{{ $shipment->id }}'); setTimeout(() => copySuccessIds = copySuccessIds.filter(id => id !== '{{ $shipment->id }}'), 2000)"
                                                                class="p-2 text-primary/70 hover:text-primary hover:bg-primary/10 rounded-xl transition-all" title="Kopieren">
                                                            <svg x-show="!copySuccessIds.includes('{{ $shipment->id }}')" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                            <svg x-show="copySuccessIds.includes('{{ $shipment->id }}')" x-cloak class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        </button>
                                                        <a href="https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode={{ $shipment->tracking_number }}"
                                                           target="_blank"
                                                           @click.stop
                                                           class="p-2 text-primary/70 hover:text-primary hover:bg-primary/10 rounded-xl transition-all" title="Auf DHL.de verfogen">
                                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                        </a>
                                                    </div>
                                                </div>
                                            @endforeach

                                            @if($o->status === 'shipped')
                                                <div class="mt-2 pt-3 border-t border-primary/20 flex items-center justify-between">
                                                    <span class="text-[11px] text-amber-900/80 dark:text-primary/90 font-medium">Paket schon angekommen?</span>
                                                    <button wire:click.stop="confirmDelivery('{{ $o->id }}')"
                                                            wire:confirm="Möchten Sie bestätigen, dass Sie alle Pakete dieser Bestellung erhalten haben?"
                                                            class="px-3.5 py-1.5 rounded-xl bg-primary text-gray-900 hover:bg-primary/90 text-[11px] font-black uppercase tracking-wider shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                                                        <svg class="w-3.5 h-3.5 text-gray-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span>Erhalt bestätigen</span>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-900 rounded-[3rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-12 sm:p-20 text-center animate-fade-in-up">
                        <div class="w-32 h-32 bg-gray-100 dark:bg-gray-950 rounded-full flex items-center justify-center mx-auto mb-8 text-gray-400 dark:text-gray-700 border border-gray-200 dark:border-gray-800 shadow-inner">
                            <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2-2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <h2 class="text-3xl font-serif font-bold text-gray-900 dark:text-white mb-4">Noch keine Bestellungen</h2>
                        <p class="text-gray-500 dark:text-gray-400 text-base sm:text-lg mb-10 max-w-lg mx-auto">Die Chronik deiner Seelenstücke ist noch leer. Zeit, die Manufaktur zu erkunden.</p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center gap-3 bg-gradient-to-r from-primary-light to-primary hover:from-primary hover:to-primary-light text-gray-900 border border-primary/40 px-10 py-5 rounded-2xl font-black uppercase tracking-widest shadow-sm dark:shadow-[0_0_30px_rgba(197,160,89,0.4)] hover:scale-105 transition-all">
                            Zum Shop
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    </div>
                @endforelse
            </div>

            <div class="mt-12 text-gray-500 dark:text-gray-400 max-w-7xl mx-auto w-full">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
