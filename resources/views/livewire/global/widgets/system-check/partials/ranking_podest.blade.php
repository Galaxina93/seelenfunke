@if($stats['product_ranking']->isNotEmpty())
    <div class="bg-white rounded-[2.5rem] md:rounded-[3rem] p-6 md:p-10 shadow-sm border border-slate-100 text-center relative overflow-hidden group">

        <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>

        {{-- Info Icon Desktop --}}
        <div class="hidden md:block absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help transition-colors" title="Meistverkaufte Produkte im Zeitraum.">
            <i class="solar-info-circle-bold-duotone text-xl"></i>
        </div>

        <h3 class="text-xs md:text-sm font-black text-slate-400 uppercase tracking-[0.2em] md:tracking-[0.3em] mb-8 md:mb-12 relative z-10">
            Meistverkaufte Unikate (Zeitraum)
        </h3>

        {{-- Podest Bereich - z-Index niedriger als Tooltip --}}
        <div class="flex flex-col md:flex-row justify-center items-center md:items-end gap-6 md:gap-12 relative z-10">
            @php $ranks = $stats['product_ranking']; @endphp

            @if(isset($ranks[1]))
                <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-2 md:order-1 w-full md:w-auto bg-slate-50/50 md:bg-transparent p-4 md:p-0 rounded-2xl border border-slate-100 md:border-0 group/item transition-all">
                    <div class="mb-0 md:mb-3 text-left md:text-center flex-1 md:flex-none">
                        <span class="block text-xs font-bold text-slate-500 truncate max-w-[150px] md:max-w-[120px]">{{ $ranks[1]->product_name }}</span>
                        <span class="text-[10px] font-black text-slate-400 bg-white md:bg-slate-100 px-2 py-0.5 rounded-full border border-slate-100 md:border-0">{{ $ranks[1]->qty }} Stk.</span>
                    </div>
                    <div class="w-12 h-12 md:w-32 md:h-32 bg-slate-200 rounded-xl md:rounded-t-2xl flex items-center justify-center border border-slate-300 shadow-inner group-hover/item:bg-slate-300 transition-colors">
                        <span class="text-xl md:text-4xl font-black text-slate-400 opacity-50">2</span>
                    </div>
                </div>
            @endif

            <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-1 md:order-2 w-full md:w-auto bg-indigo-50/30 md:bg-transparent p-4 md:p-0 rounded-2xl border border-indigo-100 md:border-0 relative group/item">
                <div class="hidden md:block absolute -top-8 left-[40%] -translate-x-1/2 text-3xl animate-bounce">👑</div>
                <div class="mb-0 md:mb-4 text-left md:text-center flex-1 md:flex-none">
                    <div class="md:hidden text-lg inline-block mr-1">👑</div>
                    <span class="block text-sm font-black text-indigo-700 truncate max-w-[180px] md:max-w-[160px]">{{ $ranks[0]->product_name }}</span>
                    <span class="text-xs font-bold text-white bg-indigo-500 px-3 py-1 rounded-full shadow-lg shadow-indigo-200">{{ $ranks[0]->qty }} Stk.</span>
                </div>
                <div class="w-14 h-14 md:w-44 md:h-48 bg-gradient-to-b from-indigo-500 to-indigo-600 rounded-xl md:rounded-t-[2.5rem] flex items-center justify-center shadow-2xl relative overflow-hidden group-hover/item:scale-105 transition-transform">
                    <div class="absolute inset-0 bg-white/10 opacity-0 group-hover/item:opacity-100 transition-opacity"></div>
                    <span class="text-2xl md:text-6xl font-black text-white drop-shadow-md">1</span>
                </div>
            </div>

            @if(isset($ranks[2]))
                <div class="flex flex-row md:flex-col items-center gap-4 md:gap-0 order-3 md:order-3 w-full md:w-auto bg-slate-50/50 md:bg-transparent p-4 md:p-0 rounded-2xl border border-slate-100 md:border-0 group/item transition-all">
                    <div class="mb-0 md:mb-3 text-left md:text-center flex-1 md:flex-none">
                        <span class="block text-xs font-bold text-slate-500 truncate max-w-[150px] md:max-w-[120px]">{{ $ranks[2]->product_name }}</span>
                        <span class="text-[10px] font-black text-slate-400 bg-white md:bg-slate-100 px-2 py-0.5 rounded-full border border-slate-100 md:border-0">{{ $ranks[2]->qty }} Stk.</span>
                    </div>
                    <div class="w-10 h-10 md:w-28 md:h-20 bg-slate-100 rounded-xl md:rounded-t-xl flex items-center justify-center border border-slate-200 shadow-inner group-hover/item:bg-slate-200 transition-colors">
                        <span class="text-lg md:text-2xl font-black text-slate-300 opacity-50">3</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
