<div class="lg:col-span-2 bg-white rounded-[3rem] p-10 shadow-sm border border-slate-100 relative overflow-hidden flex flex-col justify-center group transition-all hover:shadow-xl hover:shadow-indigo-500/5">

    {{-- Hintergrund-Akzent --}}
    <div class="absolute -top-24 -right-24 w-64 h-64 bg-indigo-50 rounded-full blur-3xl opacity-40 group-hover:opacity-60 transition-opacity"></div>

    {{-- OBERER BEREICH: UMSATZ-KÖNIG --}}
    <div class="relative z-10 flex flex-col items-center text-center">

        {{-- Titel mit hüpfendem Badge --}}
        <div class="flex items-center gap-3 mb-4">
            <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.4em]">Umsatz-König</h4>
            <span class="bg-emerald-500 text-white text-[9px] font-black px-3 py-1 rounded-full shadow-lg shadow-emerald-200 animate-bounce">
                CHAMPION
            </span>
        </div>

        @if($stats['high_revenue_prod'])
            <div class="text-3xl font-serif font-bold text-slate-900 leading-tight max-w-lg mx-auto">
                {{ $stats['high_revenue_prod']->product_name }}
            </div>
            <div class="text-5xl font-black text-emerald-600 tracking-tighter mt-3 drop-shadow-sm">
                {{ number_format($stats['high_revenue_prod']->total, 2, ',', '.') }} €
            </div>
        @else
            <div class="text-slate-300 italic text-sm">Keine Verkäufe im Zeitraum</div>
        @endif
    </div>

    {{-- MITTIGE TRENNLINIE --}}
    <div class="relative my-12 flex justify-center items-center">
        <div class="absolute inset-0 flex items-center px-20">
            <div class="w-full h-px bg-gradient-to-r from-transparent via-slate-100 to-transparent"></div>
        </div>
        <div class="relative z-10 bg-white px-6">
            <div class="w-12 h-12 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-300 shadow-inner group-hover:rotate-180 transition-transform duration-1000">
                <i class="solar-transfer-vertical-bold-duotone text-2xl"></i>
            </div>
        </div>
    </div>

    {{-- UNTERER BEREICH: SCHLUSSLICHT --}}
    <div class="relative z-10 flex flex-col items-center text-center">
        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-2 flex items-center gap-2">
            <i class="solar-graph-down-bold-duotone text-rose-400 text-lg"></i>
            Performance-Tief
        </h4>

        @if($stats['low_revenue_prod'])
            <div class="text-lg font-bold text-slate-700 max-w-md mx-auto">
                {{ $stats['low_revenue_prod']->product_name }}
            </div>
            <div class="text-2xl font-black text-rose-500 tracking-tight mt-1">
                {{ number_format($stats['low_revenue_prod']->total, 2, ',', '.') }} €
            </div>
        @else
            <div class="text-slate-300 italic text-xs">Keine Daten</div>
        @endif
    </div>

</div>
