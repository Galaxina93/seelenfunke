<div class="w-full flex items-center justify-between gap-3 lg:gap-4 h-full min-w-0">

    {{-- LOGO (Nur Mobile - deutlich größer!) --}}
    <div class="lg:hidden shrink-0 flex items-center h-full mr-2">
        <a href="{{ route('customer.dashboard') }}" class="flex items-center">
            <img src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" class="h-12 sm:h-14 w-auto object-contain drop-shadow-[0_0_10px_rgba(197,160,89,0.3)]" alt="Logo">
        </a>
    </div>

    {{-- GUTHABEN & ENERGIE (Wird nur im Shop/Spiele im Header angezeigt) --}}
    @if(!request()->routeIs('customer.dashboard'))
        <div class="flex items-center gap-3 sm:gap-6 shrink-0 h-full">
            <div class="text-left flex flex-col justify-center">
                <div class="hidden sm:block text-[9px] sm:text-[10px] text-gray-500 font-black uppercase tracking-[0.2em] mb-0.5 leading-none">Guthaben</div>
                <div class="text-sm sm:text-2xl font-serif font-bold text-primary tracking-tight leading-none">{{ $balance }} <span class="hidden sm:inline text-xs font-sans">Funken</span><span class="sm:hidden text-[9px] font-sans ml-0.5">F.</span></div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2 bg-gray-900 border border-gray-800 px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg sm:rounded-xl shadow-inner relative group cursor-help shrink-0">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                <div class="flex flex-col">
                    <span class="hidden sm:block text-[8px] text-gray-500 font-black uppercase tracking-widest leading-none">Energie</span>
                    <span class="text-white font-bold leading-none sm:mt-0.5 text-[10px] sm:text-xs">{{ $energyBalance }} / {{ $maxEnergy }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- PROFIL & ICONS (Nimmt sich allen verfügbaren Platz und scrollt sauber nach rechts) --}}
    <div class="flex-1 flex items-center justify-end h-full min-w-0 overflow-hidden">
        <div class="flex items-center justify-end w-full h-full overflow-x-auto no-scrollbar scroll-smooth">
            @livewire('global.profile.profile-dropdown')
        </div>
    </div>
</div>
