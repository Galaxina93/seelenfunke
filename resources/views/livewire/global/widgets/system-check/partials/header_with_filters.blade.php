<div class="flex flex-col xl:flex-row justify-between items-start xl:items-end gap-6 border-b border-slate-200 pb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
            <i class="solar-pulse-bold-duotone text-indigo-500"></i>
            BI Intelligence Center
        </h1>
        <p class="text-slate-500 mt-2 text-sm">Echtzeitanalyse von Systemstatus, Finanzen und Performance.</p>
    </div>

    {{-- FILTER BAR --}}
    <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap items-center gap-4 w-full xl:w-auto">

        {{-- Datumswähler --}}
        <div class="flex items-center gap-2 px-2 w-full md:w-auto justify-between">
            <div class="relative flex-1 md:flex-none">
                <label class="absolute -top-2 left-2 bg-white px-1 text-[10px] font-bold text-slate-400">Von</label>
                <input type="date"
                       wire:model.live.blur="dateStart"
                       class="w-full border-slate-200 rounded-lg text-xs font-bold text-slate-600 focus:ring-indigo-500 focus:border-indigo-500 py-1.5 transition-all">
            </div>

            <span class="text-slate-300 hidden md:block">-</span>

            <div class="relative flex-1 md:flex-none">
                <label class="absolute -top-2 left-2 bg-white px-1 text-[10px] font-bold text-slate-400">Bis</label>
                <input type="date"
                       wire:model.live.blur="dateEnd"
                       class="w-full border-slate-200 rounded-lg text-xs font-bold text-slate-600 focus:ring-indigo-500 focus:border-indigo-500 py-1.5 transition-all">
            </div>
        </div>

        {{-- Quick Selection Buttons (REPARIERT: Markierung) --}}
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0 scrollbar-hide">
            <button type="button"
                    wire:click.prevent="setCurrentMonth"
                @class([
                    'whitespace-nowrap flex-1 md:flex-none px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-200 active:scale-95',
                    'bg-indigo-600 text-white shadow-md' => $rangeMode === 'current_month',
                    'bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-indigo-600' => $rangeMode !== 'current_month'
                ])>
                Aktueller Monat
            </button>
            <button type="button"
                    wire:click.prevent="setWholeYear"
                @class([
                    'whitespace-nowrap flex-1 md:flex-none px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-200 active:scale-95',
                    'bg-indigo-600 text-white shadow-md' => $rangeMode === 'year',
                    'bg-slate-50 text-slate-500 hover:bg-slate-100 hover:text-indigo-600' => $rangeMode !== 'year'
                ])>
                Ganzes Jahr
            </button>
        </div>

        <div class="hidden md:block h-8 w-px bg-slate-100"></div>

        {{-- Typ Switcher --}}
        <div class="flex bg-slate-100 p-1 rounded-xl w-full md:w-auto">
            <button type="button" wire:click.prevent="$set('filterType', 'all')"
                @class(['flex-1 md:flex-none px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-white text-indigo-600 shadow-sm' => $filterType === 'all', 'text-slate-500 hover:text-slate-700' => $filterType !== 'all'])>Alles</button>

            <button type="button" wire:click.prevent="$set('filterType', 'business')"
                @class(['flex-1 md:flex-none px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-white text-indigo-600 shadow-sm' => $filterType === 'business', 'text-slate-500 hover:text-slate-700' => $filterType !== 'business'])>Gewerbe</button>

            <button type="button" wire:click.prevent="$set('filterType', 'private')"
                @class(['flex-1 md:flex-none px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-white text-indigo-600 shadow-sm' => $filterType === 'private', 'text-slate-500 hover:text-slate-700' => $filterType !== 'private'])>Privat</button>
        </div>
    </div>
</div>
