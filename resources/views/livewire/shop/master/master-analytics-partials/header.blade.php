<div :class="focusMode ? 'border-transparent pb-0' : 'border-b border-gray-200 dark:border-gray-800 pb-6'"
     class="flex flex-col gap-6 w-full transition-all duration-300 {{ $focusMode ? 'border-transparent pb-0' : 'border-b border-gray-200 dark:border-gray-800 pb-6' }}">
    @if(session()->has('error'))
        <div class="w-full bg-rose-50 dark:bg-rose-900/50 border border-rose-200 dark:border-rose-500/50 text-rose-700 dark:text-rose-200 px-4 py-3 rounded-xl mb-2 flex items-center gap-3 dark:shadow-[0_0_15px_rgba(244,63,94,0.15)]">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-rose-500" />
            <span class="text-sm">{{ session('error') }}</span>
        </div>
    @endif
    @if(session()->has('success'))
        <div class="w-full bg-emerald-50 dark:bg-emerald-900/50 border border-emerald-200 dark:border-emerald-500/50 text-emerald-700 dark:text-emerald-200 px-4 py-3 rounded-xl mb-2 flex items-center gap-3 dark:shadow-[0_0_15px_rgba(16,185,129,0.15)]">
            <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-500" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <!-- Header Row + Systemweite Projekt-Suche unterhalb Datenanalysezentrum (Sichtbar in Vollansicht & Fokus-Modus) -->
    <div class="w-full flex flex-col gap-4">
        <div class="w-full flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div x-show="!focusMode" style="{{ $focusMode ? 'display: none;' : '' }}" x-transition.opacity.duration.250ms>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
                    <i class="solar-pulse-bold-duotone text-primary"></i>Datenanalysezentrum
                </h1>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full" :class="focusMode ? 'md:justify-between' : 'md:w-auto md:ml-auto'">
                {{-- Kompakte Seelenfunke Magie-Suchleiste im Fokus-Modus direkt links neben dem Modus-Switch --}}
                <div x-show="focusMode" style="{{ $focusMode ? '' : 'display: none;' }}" class="flex-1 max-w-2xl">
                    <button type="button"
                            @click="$dispatch('open-sf-global-search')"
                            class="relative overflow-hidden w-full flex items-center justify-between gap-3 px-4 py-2.5 rounded-2xl bg-white/95 dark:bg-gray-900/90 border border-[#C5A059]/35 hover:border-[#C5A059] shadow-sm transition-all group cursor-pointer text-left">
                        <span class="flex items-center gap-2.5 min-w-0">
                            <span class="sf-magic-search-star text-xs font-bold">✦</span>
                            <x-heroicon-m-magnifying-glass class="w-4 h-4 text-[#C5A059] shrink-0" />
                            <span class="text-xs sm:text-sm font-semibold text-gray-500 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white truncate transition-colors">
                                Projekt durchsuchen: Einstellung, Bestellung, Produkt, Aufgabe, Rechnung...
                            </span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-[#C5A059]/15 text-[#C5A059] text-[10px] font-black uppercase tracking-widest shrink-0">Suche</span>
                    </button>
                </div>

                <button type="button"
                        @click="focusMode = !focusMode; $wire.saveFocusMode(focusMode); window.sfPlaySound && window.sfPlaySound('switch'); setTimeout(() => window.dispatchEvent(new Event('resize')), 50)"
                        @mouseenter="window.sfPlaySound && window.sfPlaySound('hover')"
                        :class="focusMode
                            ? 'bg-[#C5A059]/15 text-[#C5A059] border-[#C5A059]/40 dark:shadow-[0_0_15px_rgba(197,160,89,0.2)]'
                            : 'bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                        class="px-4 py-2.5 rounded-xl border text-xs font-black uppercase tracking-widest flex items-center justify-center gap-2.5 transition-all duration-200 active:scale-95 shrink-0 cursor-pointer {{ $focusMode ? 'bg-[#C5A059]/15 text-[#C5A059] border-[#C5A059]/40 dark:shadow-[0_0_15px_rgba(197,160,89,0.2)]' : 'bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                        title="Zwischen Fokus-Ansicht (nur offene Punkte) und Vollansicht (alle Kacheln) wechseln">
                    <span x-show="focusMode" style="{{ $focusMode ? '' : 'display: none;' }}" class="inline-flex items-center shrink-0">
                        <x-heroicon-s-sparkles class="w-4 h-4 text-[#C5A059]" />
                    </span>
                    <span x-show="!focusMode" style="{{ !$focusMode ? '' : 'display: none;' }}" class="inline-flex items-center shrink-0">
                        <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                    </span>
                    <span x-text="focusMode ? 'Alle Kacheln' : 'Fokus-Modus'">{{ $focusMode ? 'Alle Kacheln' : 'Fokus-Modus' }}</span>
                </button>
            </div>
        </div>

        {{-- Volle Systemweite Projekt-Suche direkt unterhalb Datenanalysezentrum --}}
        <div x-show="!focusMode" style="{{ $focusMode ? 'display: none;' : '' }}" x-transition.opacity.duration.250ms>
            <div class="relative overflow-hidden rounded-2xl bg-white/95 dark:bg-gray-900/90 border border-[#C5A059]/35 hover:border-[#C5A059] shadow-sm transition-all">
                <div class="sf-magic-search-ring opacity-70"></div>
                <div class="relative z-10 flex items-center gap-3 px-4 py-3">
                    <span class="sf-magic-search-star text-sm font-bold">✦</span>
                    <x-heroicon-m-magnifying-glass class="w-5 h-5 text-[#C5A059] shrink-0" />
                    <input type="text"
                           readonly
                           @focus="$el.blur(); $dispatch('open-sf-global-search')"
                           @click="$dispatch('open-sf-global-search')"
                           placeholder="Systemweit suchen: Shop-Einstellungen, Bestellungen, Produkte, Aufgaben, Buchhaltung, Konzepte & Wissen..."
                           class="flex-1 min-w-0 bg-transparent border-0 focus:ring-0 focus:outline-none text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 cursor-pointer" />
                    <span class="px-3 py-1 rounded-xl bg-[#C5A059] text-gray-950 text-[10px] font-black uppercase tracking-widest shrink-0">Projekt-Suche</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar Row (Nur im Standard-Modus sichtbar; im Fokus-Modus über das goldene Zahnrad im Kreis erreichbar) -->
    <div x-show="!focusMode"
         style="{{ $focusMode ? 'display: none;' : '' }}"
         x-transition.opacity.duration.250ms
         class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md p-2 rounded-xl sm:rounded-2xl dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 flex flex-col sm:flex-row flex-wrap items-center gap-4 w-full justify-between transition-colors duration-300">
        <div class="flex flex-1 items-center gap-3 px-2 w-full sm:w-auto">
            <div class="relative w-full sm:w-40">
                <label class="absolute -top-2 left-2 bg-gray-50 dark:bg-gray-950 px-2 rounded-full text-[9px] font-bold text-gray-500 dark:text-gray-400 z-10 border border-gray-200 dark:border-gray-800">Von</label>
                <input type="date" wire:model.live.blur="dateStart" class="w-full relative bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 rounded-lg text-xs font-bold text-gray-800 dark:text-white focus:ring-primary focus:border-primary py-1.5 transition-all">
            </div>
            <span class="text-gray-400 dark:text-gray-600 hidden sm:block">-</span>
            <div class="relative w-full sm:w-40">
                <label class="absolute -top-2 left-2 bg-gray-50 dark:bg-gray-950 px-2 rounded-full text-[9px] font-bold text-gray-500 dark:text-gray-400 z-10 border border-gray-200 dark:border-gray-800">Bis</label>
                <input type="date" wire:model.live.blur="dateEnd" class="w-full relative bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 rounded-lg text-xs font-bold text-gray-800 dark:text-white focus:ring-primary focus:border-primary py-1.5 transition-all">
            </div>
        </div>

        <div class="flex gap-2 w-full sm:w-auto overflow-x-auto pb-1 sm:pb-0 scrollbar-hide">
            <button type="button" wire:click.prevent="setCurrentMonth" @class(['whitespace-nowrap flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 active:scale-95', 'bg-primary text-gray-900 dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' => $rangeMode === 'current_month', 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' => $rangeMode !== 'current_month'])>
                Aktueller Monat
            </button>
            <button type="button" wire:click.prevent="setWholeYear" @class(['whitespace-nowrap flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200 active:scale-95', 'bg-primary text-gray-900 dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' => $rangeMode === 'year', 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' => $rangeMode !== 'year'])>
                Ganzes Jahr
            </button>
        </div>

        <div class="hidden md:block h-8 w-px bg-gray-200 dark:bg-gray-800"></div>

        <div class="flex bg-gray-100 dark:bg-gray-950 p-1 rounded-xl w-full sm:w-auto border border-gray-200 dark:border-gray-800 overflow-x-auto scrollbar-hide">
            <button type="button" wire:click.prevent="$set('filterType', 'all')" @class(['flex-1 sm:flex-none whitespace-nowrap px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-primary text-gray-900 dark:shadow-[0_0_10px_rgba(197,160,89,0.3)]' => $filterType === 'all', 'text-gray-500 hover:text-gray-900 dark:hover:text-white' => $filterType !== 'all'])>
                Alles
            </button>
            <button type="button" wire:click.prevent="$set('filterType', 'business')" @class(['flex-1 sm:flex-none whitespace-nowrap px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-primary text-gray-900 dark:shadow-[0_0_10px_rgba(197,160,89,0.3)]' => $filterType === 'business', 'text-gray-500 hover:text-gray-900 dark:hover:text-white' => $filterType !== 'business'])>
                Gewerbe
            </button>
            <button type="button" wire:click.prevent="$set('filterType', 'private')" @class(['flex-1 sm:flex-none whitespace-nowrap px-4 py-1.5 rounded-lg text-xs font-bold transition-all duration-200', 'bg-primary text-gray-900 dark:shadow-[0_0_10px_rgba(197,160,89,0.3)]' => $filterType === 'private', 'text-gray-500 hover:text-gray-900 dark:hover:text-white' => $filterType !== 'private'])>
                Privat
            </button>
        </div>
    </div>
</div>
