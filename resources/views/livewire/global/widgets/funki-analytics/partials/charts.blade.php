<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <div class="lg:col-span-4 bg-gray-900/80 backdrop-blur-md rounded-[2rem] sm:rounded-[2.5rem] p-5 sm:p-8 shadow-2xl border border-gray-800 flex flex-col relative group" wire:ignore>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest">Gewinn-Entwicklung</h3>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
            <span class="flex items-center gap-1.5 sm:gap-2 text-[9px] sm:text-[10px] font-black text-white uppercase tracking-widest bg-gray-950 px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-full border border-gray-800">
                <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-gray-500 shadow-[0_0_8px_currentColor]"></span>
                Umsatz
            </span>
                <span class="flex items-center gap-1.5 sm:gap-2 text-[9px] sm:text-[10px] font-black text-white uppercase tracking-widest bg-gray-950 px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-full border border-gray-800">
                <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-primary shadow-[0_0_8px_currentColor]"></span>
                Gewinn
            </span>
            </div>
        </div>
        <div class="h-64 sm:h-80 w-full relative">
            <canvas id="profitChart"></canvas>
        </div>
    </div>
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] p-8 shadow-2xl border border-gray-800 relative group" wire:ignore>
        <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest mb-6 text-center">Top Kostentreiber</h3>
        <div class="h-56 relative">
            <canvas id="expensesChart"></canvas>
        </div>
    </div>

    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] p-8 shadow-2xl border border-gray-800 relative group" wire:ignore>
        <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest mb-6 text-center">Top Kunden (Umsatz)</h3>
        <div class="h-56 relative">
            <canvas id="customersChart"></canvas>
        </div>
    </div>

    <div class="lg:col-span-2 bg-gray-900/80 backdrop-blur-md rounded-[3rem] p-10 shadow-2xl border border-gray-800 relative overflow-hidden flex flex-col justify-center group transition-all hover:border-primary/40 hover:shadow-[0_0_40px_rgba(197,160,89,0.15)]">
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/20 rounded-full blur-[80px] opacity-40 group-hover:opacity-70 transition-opacity duration-700"></div>

        <div class="relative z-10 flex flex-col items-center text-center">
            <div class="flex items-center gap-3 mb-6">
                <h4 class="text-[11px] font-black text-gray-500 uppercase tracking-[0.4em]">Umsatz-König</h4>
                <span class="bg-primary text-gray-900 text-[9px] font-black px-4 py-1.5 rounded-full shadow-[0_0_15px_rgba(197,160,89,0.5)] animate-pulse tracking-widest">CHAMPION</span>
            </div>
            @if($stats['high_revenue_prod'])
                <div class="text-3xl md:text-4xl font-serif font-bold text-white leading-tight max-w-lg mx-auto">{{ $stats['high_revenue_prod']->product_name }}</div>
                <div class="text-5xl font-black text-primary tracking-tighter mt-4 drop-shadow-[0_0_15px_rgba(197,160,89,0.4)]">{{ number_format($stats['high_revenue_prod']->total, 2, ',', '.') }} €</div>
            @else
                <div class="text-gray-500 italic text-sm">Keine Verkäufe im Zeitraum</div>
            @endif
        </div>

        <div class="relative my-14 flex justify-center items-center">
            <div class="absolute inset-0 flex items-center px-10">
                <div class="w-full h-px bg-gradient-to-r from-transparent via-gray-700 to-transparent"></div>
            </div>
            <div class="relative z-10 bg-gray-900 px-6">
                <div class="w-14 h-14 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center text-gray-500 shadow-inner group-hover:text-primary group-hover:rotate-180 transition-all duration-1000">
                    <i class="solar-transfer-vertical-bold-duotone text-3xl"></i>
                </div>
            </div>
        </div>

        <div class="relative z-10 flex flex-col items-center text-center">
            <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-3 flex items-center gap-2">
                <i class="solar-graph-down-bold-duotone text-red-400 text-lg"></i> Performance-Tief
            </h4>
            @if($stats['low_revenue_prod'])
                <div class="text-lg font-bold text-gray-300 max-w-md mx-auto">{{ $stats['low_revenue_prod']->product_name }}</div>
                <div class="text-2xl font-black text-red-400 tracking-tight mt-2 drop-shadow-[0_0_10px_rgba(248,113,113,0.3)]">{{ number_format($stats['low_revenue_prod']->total, 2, ',', '.') }} €</div>
            @else
                <div class="text-gray-600 italic text-xs">Keine Daten</div>
            @endif
        </div>
    </div>
</div>
