<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 xl:col-span-3 flex flex-col">
    <div class="flex justify-between items-start mb-6">
        <div>
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="solar-chart-2-bold-duotone text-blue-500"></i> Traffic Analyse
            </h2>
            <p class="text-xs text-slate-400 mt-1">Besucherzahlen der aktuellen Woche</p>
        </div>
        <div class="text-right">
            <div class="text-sm text-slate-500">Seitenaufrufe Heute</div>
            <div class="text-2xl font-bold text-blue-600">{{ $stats['frontend_visits_today'] }}</div>
        </div>
    </div>

    <div class="relative w-full h-64">
        <canvas id="visitsChart"></canvas>
    </div>
</div>
