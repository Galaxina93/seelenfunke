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
                {{-- ABLEHNEN BUTTON --}}
                <button wire:click="markAsRejected('{{ $quote->id }}')"
                        wire:confirm="Möchtest du diese Anfrage wirklich ablehnen?"
                        class="flex-1 sm:flex-none px-3 py-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 text-xs font-bold transition">
                    Ablehnen
                </button>

                {{-- [NEU] OPTION 1: RECHNUNG --}}
                <button wire:click="convertToOrder('{{ $quote->id }}', 'invoice')"
                        wire:loading.attr="disabled"
                        class="flex-1 sm:flex-none px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-xs font-bold shadow-sm transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Rechnung
                </button>

                {{-- [NEU] OPTION 2: ONLINE ZAHLUNG --}}
                <button wire:click="convertToOrder('{{ $quote->id }}', 'stripe_link')"
                        wire:loading.attr="disabled"
                        class="flex-1 sm:flex-none px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-bold shadow-sm transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Zahlungslink
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
        @include('livewire.shop.shared.detail-content', [
            'model' => $quote,          // Wir mappen $quote auf $model
            'context' => 'quote',       // Kontext setzen
            'selectedItemId' => $this->selectedQuoteItemId // ID für Vorschau
        ])

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
                                    <livewire:shop.configurator.configurator
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
