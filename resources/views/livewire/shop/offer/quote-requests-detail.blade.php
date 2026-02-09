<div class="h-full lg:h-[calc(100vh-3rem)] flex flex-col bg-white lg:rounded-xl shadow-lg border-t lg:border border-gray-200 overflow-hidden lg:mx-6 lg:mb-6">

    {{-- DETAIL HEADER: Mobil optimiert (Flex-Wrap für Buttons) --}}
    <div class="bg-white border-b border-gray-200 px-4 py-3 lg:px-6 lg:py-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shrink-0 z-30 sticky top-0">
        <div class="flex items-center gap-3 lg:gap-4 w-full sm:w-auto">
            <button wire:click="closeDetail"
                    class="p-2 lg:p-0 text-gray-500 hover:text-gray-900 flex items-center gap-1 text-sm font-bold transition">
                <svg class="w-5 h-5 lg:w-4 lg:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
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
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Rechnung
                </button>

                {{-- [NEU] OPTION 2: ONLINE ZAHLUNG --}}
                <button wire:click="convertToOrder('{{ $quote->id }}', 'stripe_link')"
                        wire:loading.attr="disabled"
                        class="flex-1 sm:flex-none px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-bold shadow-sm transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Zahlungslink
                </button>

            @elseif($quote->status === 'converted' || $quote->status === 'accepted')
                <div class="w-full sm:w-auto text-center flex items-center justify-center gap-2 text-green-700 bg-green-50 px-3 py-2 rounded-lg border border-green-200 text-xs font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Umgewandelt
                </div>
            @endif
        </div>
    </div>

    {{-- SPLIT CONTENT --}}
    <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">
        @include('livewire.shop.shared.order-offer-detail-content', [
            'model' => $quote,
            'context' => 'quote',
            'selectedItemId' => $this->selectedQuoteItemId,
            'previewItem' => $this->previewItem // Nutzt die Property aus QuoteRequests.php
        ])
    </div>
</div>
