<div class="h-full lg:h-[calc(100vh-3rem)] flex flex-col bg-gray-900/90 backdrop-blur-xl lg:rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.5)] border-t lg:border border-gray-800 overflow-hidden lg:mx-6 lg:mb-6 animate-fade-in-up">

    {{-- DETAIL HEADER: Mobil optimiert (Flex-Wrap für Buttons) --}}
    <div class="bg-gray-900/50 backdrop-blur-xl border-b border-gray-800 px-4 py-4 lg:px-8 lg:py-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-5 shrink-0 z-30 sticky top-0 shadow-inner">
        <div class="flex items-center gap-4 sm:gap-6 w-full sm:w-auto">
            <button wire:click="closeDetail"
                    class="group flex items-center gap-3 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition-colors shrink-0">
                <div class="w-10 h-10 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center group-hover:bg-primary group-hover:border-primary group-hover:text-gray-900 transition-all duration-300 shadow-inner">
                    <svg class="w-5 h-5 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </div>
                <span class="hidden lg:inline">Zurück</span>
            </button>
            <div class="h-8 w-px bg-gray-800 hidden lg:block shrink-0"></div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl lg:text-2xl font-serif font-bold text-white flex flex-wrap items-center gap-3 tracking-tight">
                    Anfrage <span class="text-primary">#{{ $quote->quote_number }}</span>
                    <span class="text-[9px] lg:text-[10px] font-sans font-bold text-gray-400 bg-gray-950 px-2.5 py-1 rounded-md border border-gray-800 uppercase tracking-widest shadow-inner whitespace-nowrap">
                        {{ $quote->created_at->format('d.m.Y H:i') }}
                    </span>
                    @if($quote->is_express)
                        <span class="text-[9px] lg:text-[10px] font-black bg-red-500/10 text-red-400 px-2.5 py-1 rounded-md border border-red-500/30 uppercase tracking-widest whitespace-nowrap flex items-center gap-1.5 shadow-[0_0_15px_rgba(239,68,68,0.15)]">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_currentColor]"></span> Express
                        </span>
                    @endif
                </h1>
            </div>
        </div>

        {{-- ACTIONS: Volle Breite auf Mobilgeräten --}}
        <div class="flex flex-wrap sm:flex-nowrap gap-3 w-full sm:w-auto justify-end">
            @if($quote->status === 'open')
                {{-- ABLEHNEN BUTTON --}}
                <button wire:click="markAsRejected('{{ $quote->id }}')"
                        wire:confirm="Möchtest du diese Anfrage wirklich ablehnen?"
                        class="flex-1 sm:flex-none px-4 py-2.5 border border-red-500/30 text-red-400 bg-red-500/10 rounded-xl hover:bg-red-500 hover:text-white text-[10px] font-black uppercase tracking-widest transition-all shadow-inner">
                    Ablehnen
                </button>

                {{-- [NEU] OPTION 1: RECHNUNG --}}
                <button wire:click="convertToOrder('{{ $quote->id }}', 'invoice')"
                        wire:loading.attr="disabled"
                        class="flex-1 sm:flex-none px-4 py-2.5 bg-gray-950 border border-gray-800 text-gray-300 rounded-xl hover:bg-gray-800 hover:text-white text-[10px] font-black uppercase tracking-widest shadow-inner transition-all flex items-center justify-center gap-2 group">
                    <svg class="w-4 h-4 text-gray-500 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Rechnung
                </button>

                {{-- [NEU] OPTION 2: ONLINE ZAHLUNG --}}
                <button wire:click="convertToOrder('{{ $quote->id }}', 'stripe_link')"
                        wire:loading.attr="disabled"
                        class="flex-1 sm:flex-none px-5 py-2.5 bg-primary border border-primary/50 text-gray-900 rounded-xl hover:bg-primary-dark hover:text-white hover:scale-[1.02] text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(197,160,89,0.3)] transition-all flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Zahlungslink
                </button>

            @elseif($quote->status === 'converted' || $quote->status === 'accepted')
                <div class="w-full sm:w-auto text-center flex items-center justify-center gap-2 text-emerald-400 bg-emerald-500/10 px-5 py-2.5 rounded-xl border border-emerald-500/30 text-[10px] font-black uppercase tracking-widest shadow-inner">
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
    <div class="flex flex-col lg:flex-row flex-1 overflow-hidden bg-gray-950/50">
        @include('livewire.shop.shared.order-offer-detail-content', [
            'model' => $quote,
            'context' => 'quote',
            'selectedItemId' => $this->selectedQuoteItemId,
            'previewItem' => $this->previewItem // Nutzt die Property aus QuoteRequests.php
        ])
    </div>
</div>
