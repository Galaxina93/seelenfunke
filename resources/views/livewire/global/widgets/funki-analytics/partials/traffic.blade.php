<div class="bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] shadow-2xl border border-gray-800 p-5 md:p-8 xl:col-span-3 flex flex-col w-full overflow-hidden">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 md:mb-8 gap-4">
        <div>
            <h2 class="text-lg md:text-xl font-bold text-white flex items-center gap-2 md:gap-3">
                <i class="solar-chart-2-bold-duotone text-primary text-xl md:text-2xl drop-shadow-[0_0_10px_rgba(197,160,89,0.5)]"></i>
                Traffic Analyse
            </h2>
            <p class="text-[9px] md:text-xs font-black uppercase tracking-widest text-gray-500 mt-1 md:mt-2">Besucherzahlen der aktuellen Woche</p>
        </div>
        <div class="text-left sm:text-right bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-800 shadow-inner w-full sm:w-auto">
            <div class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Seitenaufrufe Heute</div>
            <div class="text-2xl md:text-3xl font-black text-primary drop-shadow-[0_0_15px_rgba(197,160,89,0.4)]">{{ $stats['frontend_visits_today'] }}</div>
        </div>
    </div>

    <div class="relative w-full h-56 md:h-80">
        <canvas id="visitsChart"></canvas>
    </div>
</div>
