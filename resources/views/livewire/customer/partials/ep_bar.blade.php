<div class="relative w-full bg-gray-950 flex flex-col xl:flex-row items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-800 z-20 gap-4 md:gap-6">

    <div class="flex flex-col xl:flex-row items-center w-full xl:w-auto xl:pr-6 xl:border-r border-gray-800 shrink-0 gap-3 xl:gap-4">

        <a href="/" target="_blank" class="shrink-0 mb-1 xl:mb-0 relative z-30">
            <img src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" class="h-16 md:h-20 w-auto hover:scale-105 transition-transform duration-300" alt="Logo">
        </a>

        <div class="flex items-center justify-center gap-4 sm:gap-6 w-full xl:w-auto xl:ml-2">
            <div class="text-center xl:text-left">
                <div class="text-[10px] text-gray-500 font-black uppercase tracking-[0.2em] mb-1 leading-none">Guthaben</div>
                <div class="text-xl md:text-2xl font-serif font-bold text-primary tracking-tight">{{ $balance }} <span class="text-xs md:text-sm">Funken</span></div>
            </div>

            @if(!$isMaxLevel)
                <button wire:click="upgrade" wire:loading.attr="disabled" @disabled(!$canUpgrade) class="group relative px-4 py-2.5 sm:px-5 sm:py-3 md:px-8 md:py-4 rounded-full text-[10px] md:text-xs font-black uppercase tracking-widest transition-all overflow-hidden shrink-0 {{ $canUpgrade ? 'bg-primary text-gray-900 shadow-[0_0_40px_rgba(197,160,89,1)] hover:bg-white hover:scale-110 animate-[pulse_1.5s_infinite]' : 'bg-gray-800 text-gray-500 cursor-not-allowed' }}">
                    <span wire:loading.remove wire:target="upgrade" class="relative z-10 flex items-center gap-1.5 sm:gap-2 whitespace-nowrap">
                        @if($canUpgrade)
                            {{-- Arrow Up (Up Vote) Icon anstatt dem alten --}}
                            <svg class="w-4 h-4 md:w-5 md:h-5 transition-transform animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            Upgraden
                        @else
                            Nicht genug
                        @endif
                    </span>
                    <span wire:loading wire:target="upgrade" class="relative z-10 flex items-center gap-1.5 sm:gap-2 whitespace-nowrap">
                        <svg class="animate-spin h-4 w-4 md:h-5 md:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        Wirkt...
                    </span>
                </button>
            @endif
        </div>

    </div>

    <div class="flex-1 max-w-xl mx-4 hidden xl:flex flex-col">
        <div class="flex justify-between text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">
            <span>Fortschritt zu Level {{ $level + 1 }}</span>
            <span>{{ $progressPercentage }}%</span>
        </div>
        <div class="h-3 bg-gray-900 rounded-full overflow-hidden border border-gray-800 shadow-inner w-full">
            <div class="h-full bg-gradient-to-r from-primary-dark to-primary shadow-[0_0_15px_rgba(197,160,89,0.5)] transition-all duration-1000" style="width: {{ $progressPercentage }}%"></div>
        </div>
    </div>

    {{-- GLOBALE PROFIL NAVIGATION (Zentralisiert) --}}
    <div class="w-full xl:w-auto flex justify-center xl:justify-end mt-1 xl:mt-0 relative z-30">
        @livewire('global.profile.profile-dropdown')
    </div>
</div>
