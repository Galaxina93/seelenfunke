<div class="w-full mb-8">
    <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 shadow-2xl border border-gray-800 flex flex-col relative group w-full" wire:ignore>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <h3 class="text-xs sm:text-sm font-black text-gray-400 uppercase tracking-widest">Gewinn-Entwicklung</h3>
            <div class="flex flex-wrap items-center gap-2">
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
