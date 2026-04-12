<div class="w-full mb-8">
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 flex flex-col relative group w-full" wire:ignore>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <div>
                <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest">Gewinn-Entwicklung</h3>
                @php
                    $bEven = $stats['break_even_period'] ?? 0;
                    $currentRev = $stats['total_revenue'] ?? 0;
                    $missing = max(0, $bEven - $currentRev);
                @endphp
                <div class="mt-1 flex items-center gap-2">
                    @if($missing > 0)
                        <span class="text-[10px] font-bold text-red-400">Noch {{ number_format($missing, 2, ',', '.') }} € benötigt</span>
                    @else
                        <span class="text-[10px] font-bold text-emerald-400">Break-Even erreicht!</span>
                    @endif
                    <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black">({{ number_format($currentRev, 2, ',', '.') }} € / {{ number_format($bEven, 2, ',', '.') }} €)</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="flex items-center gap-1.5 text-[9px] font-black text-white uppercase tracking-widest bg-gray-950 px-2.5 py-1 rounded-full border border-gray-800">
                    <span class="w-2 h-2 rounded-full bg-red-500 shadow-[0_0_8px_currentColor]"></span> Break-Even
                </span>
                <span class="flex items-center gap-1.5 text-[9px] font-black text-white uppercase tracking-widest bg-gray-950 px-2.5 py-1 rounded-full border border-gray-800">
                    <span class="w-2 h-2 rounded-full bg-gray-500 shadow-[0_0_8px_currentColor]"></span> Umsatz
                </span>
                <span class="flex items-center gap-1.5 text-[9px] font-black text-white uppercase tracking-widest bg-gray-950 px-2.5 py-1 rounded-full border border-gray-800">
                    <span class="w-2 h-2 rounded-full bg-primary shadow-[0_0_8px_currentColor]"></span> Gewinn
                </span>
            </div>
        </div>
        <div class="h-64 sm:h-72 w-full relative">
            <canvas id="profitChart"></canvas>
        </div>
    </div>
</div>
