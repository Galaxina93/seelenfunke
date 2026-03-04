@if(isset($stats['product_ranking']) && count($stats['product_ranking']) > 0)
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] md:rounded-[3rem] p-6 md:p-10 shadow-2xl border border-gray-800 text-center relative overflow-hidden group">
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary-dark via-primary to-primary-light"></div>

        <div class="hidden md:block absolute top-6 right-6 text-gray-600 hover:text-primary transition-colors cursor-help" title="Meistverkaufte Produkte im Zeitraum.">
            <i class="solar-info-circle-bold-duotone text-2xl"></i>
        </div>

        <h3 class="text-xs md:text-sm font-black text-gray-500 uppercase tracking-[0.2em] md:tracking-[0.3em] mb-8 md:mb-12 relative z-10">Meistverkaufte Unikate (Zeitraum)</h3>

        <div class="flex flex-col md:flex-row justify-center items-center md:items-end gap-6 md:gap-12 relative z-10">
            @php $ranks = array_values($stats['product_ranking']); @endphp

            {{-- PLATZ 2 --}}
            @if(isset($ranks[1]))
                <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-2 md:order-1 w-full md:w-auto bg-gray-950 md:bg-transparent p-4 md:p-0 rounded-2xl border border-gray-800 md:border-0 group/item transition-all">
                    <div class="mb-0 md:mb-3 text-left md:text-center flex-1 md:flex-none">
                        <span class="block text-xs font-bold text-gray-400 truncate max-w-[150px] md:max-w-[120px]">{{ $ranks[1]['product_name'] ?? 'Produkt' }}</span>
                        <span class="text-[10px] font-black text-gray-500 bg-gray-800 md:bg-gray-800 px-3 py-1 rounded-full border border-gray-700 md:border-0 mt-1 inline-block">{{ $ranks[1]['qty'] ?? 0 }} Stk.</span>
                    </div>
                    <div class="w-12 h-12 md:w-32 md:h-32 bg-gray-800 rounded-xl md:rounded-t-3xl flex items-center justify-center border border-gray-700 shadow-inner group-hover/item:bg-gray-700 transition-colors">
                        <span class="text-xl md:text-4xl font-black text-gray-500 opacity-50">2</span>
                    </div>
                </div>
            @endif

            {{-- PLATZ 1 --}}
            @if(isset($ranks[0]))
                <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-1 md:order-2 w-full md:w-auto bg-primary/10 md:bg-transparent p-4 md:p-0 rounded-2xl border border-primary/30 md:border-0 relative group/item">
                    <div class="hidden md:block absolute -top-10 left-[37%] -translate-x-1/2 text-4xl animate-bounce drop-shadow-[0_0_15px_rgba(197,160,89,0.8)]">👑</div>
                    <div class="mb-0 md:mb-4 text-left md:text-center flex-1 md:flex-none">
                        <div class="md:hidden text-lg inline-block mr-1">👑</div>
                        <span class="block text-sm font-black text-primary truncate max-w-[180px] md:max-w-[160px]">{{ $ranks[0]['product_name'] ?? 'Produkt' }}</span>
                        <span class="text-xs font-bold text-gray-900 bg-primary px-3 py-1 rounded-full shadow-[0_0_15px_rgba(197,160,89,0.5)] mt-1 inline-block">{{ $ranks[0]['qty'] ?? 0 }} Stk.</span>
                    </div>
                    <div class="w-14 h-14 md:w-44 md:h-48 bg-gradient-to-b from-primary to-primary-dark rounded-xl md:rounded-t-[3rem] flex items-center justify-center shadow-[0_0_40px_rgba(197,160,89,0.3)] relative overflow-hidden group-hover/item:scale-105 transition-transform border border-primary/50">
                        <div class="absolute inset-0 bg-white/10 opacity-0 group-hover/item:opacity-100 transition-opacity"></div>
                        <span class="text-2xl md:text-6xl font-black text-gray-900 drop-shadow-md">1</span>
                    </div>
                </div>
            @endif

            {{-- PLATZ 3 --}}
            @if(isset($ranks[2]))
                <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-3 md:order-3 w-full md:w-auto bg-gray-950 md:bg-transparent p-4 md:p-0 rounded-2xl border border-gray-800 md:border-0 group/item transition-all">
                    <div class="mb-0 md:mb-3 text-left md:text-center flex-1 md:flex-none">
                        <span class="block text-xs font-bold text-gray-400 truncate max-w-[150px] md:max-w-[120px]">{{ $ranks[2]['product_name'] ?? 'Produkt' }}</span>
                        <span class="text-[10px] font-black text-gray-500 bg-gray-800 md:bg-gray-800 px-3 py-1 rounded-full border border-gray-700 md:border-0 mt-1 inline-block">{{ $ranks[2]['qty'] ?? 0 }} Stk.</span>
                    </div>
                    <div class="w-10 h-10 md:w-28 md:h-20 bg-gray-900 rounded-xl md:rounded-t-2xl flex items-center justify-center border border-gray-800 shadow-inner group-hover/item:bg-gray-800 transition-colors">
                        <span class="text-lg md:text-2xl font-black text-gray-600 opacity-50">3</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
