<div class="flex flex-col gap-6 border-b border-gray-800 pb-6 w-full">
    @if(session()->has('error'))
        <div class="w-full bg-rose-900/50 border border-rose-500/50 text-rose-200 px-4 py-3 rounded-xl mb-2 flex items-center gap-3 shadow-[0_0_15px_rgba(244,63,94,0.15)]">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-rose-500" />
            <span class="text-sm">{{ session('error') }}</span>
        </div>
    @endif
    @if(session()->has('success'))
        <div class="w-full bg-emerald-900/50 border border-emerald-500/50 text-emerald-200 px-4 py-3 rounded-xl mb-2 flex items-center gap-3 shadow-[0_0_15px_rgba(16,185,129,0.15)]">
            <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-500" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <!-- Header Row (Title + Button) -->
    <div class="w-full flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-white tracking-tight flex items-center gap-3">
                <i class="solar-pulse-bold-duotone text-primary"></i>Datenanalysezentrum
            </h1>
            <p class="text-gray-400 mt-2 text-sm">Echtzeitanalyse von Systemstatus, Finanzen und Performance.</p>
        </div>
        <div class="flex items-center gap-4 w-full md:w-auto">
            <button wire:click="generateMission" class="h-10 px-4 rounded-xl bg-gray-900 border border-gray-700 hover:border-white text-xs font-bold text-white transition-all flex items-center justify-center gap-2 group whitespace-nowrap shadow-inner">
                <x-heroicon-s-bolt class="w-4 h-4 text-emerald-400 group-hover:animate-pulse" />
                <span wire:loading.remove wire:target="generateMission">Was jetzt?</span>
                <span wire:loading wire:target="generateMission" class="animate-pulse text-gray-400">Analysiert...</span>
            </button>
            @include('livewire.shop.ai.ask-ai-dropdown', [
                'themeColor' => 'primary',
                'actionMethod' => 'downloadAiReport',
                'buttonText' => 'CEO PDF Report generieren',
                'loadingText' => 'Berechne PDF...'
            ])
        </div>
    </div>

    <!-- Filter Bar Row (Full Width) -->
    <div class="bg-gray-900/80 backdrop-blur-md p-2 rounded-xl sm:rounded-2xl shadow-2xl border border-gray-800 flex flex-col sm:flex-row flex-wrap items-center gap-4 w-full justify-between">
        <div class="flex flex-1 items-center gap-3 px-2 w-full sm:w-auto">
            <div class="relative w-full sm:w-40">
                <label class="absolute -top-2 left-2 bg-gray-950 px-2 rounded-full text-[9px] font-bold text-gray-400 z-10">Von</label>
                <input type="date" wire:model.live.blur="dateStart" class="w-full relative bg-gray-950 border-gray-800 rounded-lg text-xs font-bold text-white focus:ring-primary focus:border-primary py-1.5 transition-all">
            </div>
            <span class="text-gray-600 hidden sm:block">-</span>
            <div class="relative w-full sm:w-40">
                <label class="absolute -top-2 left-2 bg-gray-950 px-2 rounded-full text-[9px] font-bold text-gray-400 z-10">Bis</label>
                <input type="date" wire:model.live.blur="dateEnd" class="w-full relative bg-gray-950 border-gray-800 rounded-lg text-xs font-bold text-white focus:ring-primary focus:border-primary py-1.5 transition-all">
            </div>
        </div>

        <div class="flex gap-2 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0 scrollbar-hide">
            <button type="button" wire:click.prevent="setCurrentMonth" @class(['whitespace-nowrap flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 active:scale-95', 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' => $rangeMode === 'current_month', 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white' => $rangeMode !== 'current_month'])>
                Aktueller Monat
            </button>
            <button type="button" wire:click.prevent="setWholeYear" @class(['whitespace-nowrap flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 active:scale-95', 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' => $rangeMode === 'year', 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white' => $rangeMode !== 'year'])>
                Ganzes Jahr
            </button>
        </div>

        <div class="hidden md:block h-8 w-px bg-gray-800"></div>

        <div class="flex bg-gray-950 p-1 rounded-xl w-full sm:w-auto border border-gray-800 overflow-x-auto scrollbar-hide">
            <button type="button" wire:click.prevent="$set('filterType', 'all')" @class(['flex-1 sm:flex-none whitespace-nowrap px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' => $filterType === 'all', 'text-gray-500 hover:text-white' => $filterType !== 'all'])>
                Alles
            </button>
            <button type="button" wire:click.prevent="$set('filterType', 'business')" @class(['flex-1 sm:flex-none whitespace-nowrap px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' => $filterType === 'business', 'text-gray-500 hover:text-white' => $filterType !== 'business'])>
                Gewerbe
            </button>
            <button type="button" wire:click.prevent="$set('filterType', 'private')" @class(['flex-1 sm:flex-none whitespace-nowrap px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' => $filterType === 'private', 'text-gray-500 hover:text-white' => $filterType !== 'private'])>
                Privat
            </button>
        </div>
    </div>
</div>
