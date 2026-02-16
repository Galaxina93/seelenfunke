<div class="p-4 md:p-6 bg-slate-50 min-h-screen space-y-8">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{-- Header mit Filtern --}}
    @include('livewire.global.widgets.system-check.partials.header_with_filters')

    {{-- RANKING (TOP) --}}
    @include('livewire.global.widgets.system-check.partials.ranking_podest')

    {{-- Operativer Status --}}
    <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-slate-100 relative overflow-hidden group">
        <div class="absolute top-4 right-4 text-slate-300 hover:text-indigo-500 cursor-help"
             title="Kritische Systemzustände.">
            <i class="solar-info-circle-bold-duotone text-xl"></i>
        </div>
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.2em]">Operativer Status</h3>
            <span
                class="text-[10px] font-bold uppercase tracking-wider text-slate-300 bg-slate-50 px-3 py-1 rounded-full">Live Action</span>
        </div>
        <livewire:global.widgets.health-check/>
    </div>

    {{-- KPI KACHELN (Modularisiert & Inkl. Offene Posten) --}}
    <livewire:global.widgets.system-check-k-p-i :stats="$stats"/>

    {{-- FINANZ & PERFORMANCE --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

        {{-- Chart Gewinnentwicklung --}}
        <div
            class="lg:col-span-4 bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col relative group"
            wire:ignore>
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest">Gewinn-Entwicklung</h3>
                <div class="flex gap-2">
                    <span class="flex items-center gap-1 text-[10px] font-bold text-indigo-600"><span
                            class="w-2 h-2 rounded-full bg-indigo-500"></span> Umsatz</span>
                    <span class="flex items-center gap-1 text-[10px] font-bold text-emerald-600"><span
                            class="w-2 h-2 rounded-full bg-emerald-500"></span> Gewinn</span>
                </div>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="profitChart"></canvas>
            </div>
        </div>

        {{-- Top Kostentreiber --}}
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 relative group" wire:ignore>
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-4 text-center">Top
                Kostentreiber</h3>
            <div class="h-48 relative">
                <canvas id="expensesChart"></canvas>
            </div>
        </div>

        {{-- Top Kunden --}}
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 relative group" wire:ignore>
            <h3 class="text-sm font-black text-slate-400 uppercase tracking-widest mb-4 text-center">Top Kunden
                (Umsatz)</h3>
            <div class="h-48 relative">
                <canvas id="customersChart"></canvas>
            </div>
        </div>

        @include('livewire.global.widgets.system-check.partials.performance_highlights')
    </div>


    <div class="border-t border-slate-200 pt-8 mt-8">

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            @include('livewire.global.widgets.system-check.partials.frontend_analytics_chart')
        </div>

        @include('livewire.global.widgets.system-check.partials.history_and_logins')

    </div>

    @include('livewire.global.widgets.system-check.partials.chart_scripts_extended')
</div>
