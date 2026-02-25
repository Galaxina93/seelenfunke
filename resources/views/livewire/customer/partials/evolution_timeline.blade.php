<div x-show="show3DModal" style="display: none;" class="fixed inset-0 z-[2000] flex items-start justify-center pt-10 sm:pt-16 p-4 sm:px-10">
    <div class="absolute inset-0 bg-black/98 backdrop-blur-3xl" @click="close3DModal()" x-transition.opacity></div>

    <div class="relative w-full max-w-[100rem] h-[85vh] max-h-[900px] bg-gradient-to-b from-gray-900 to-black rounded-[2.5rem] sm:rounded-[4rem] shadow-[0_0_100px_rgba(0,0,0,1)] border border-gray-800 overflow-hidden flex flex-col"
         x-transition:enter="transition ease-out duration-300 delay-100"
         x-transition:enter-start="opacity-0 scale-95 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-10">

        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent opacity-60 pointer-events-none"></div>

        <div x-show="evolutionFlash" x-transition:enter="transition ease-in duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-out duration-1000" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0 z-[2050] flex items-center justify-center bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-white via-white/95 to-primary/50 pointer-events-none" style="display: none;"></div>

        <div x-show="showConfetti" x-transition.opacity.duration.500ms class="absolute inset-0 z-[2060] pointer-events-none flex items-center justify-center bg-black/40 backdrop-blur-sm" style="display: none;">
            <div class="bg-gray-900 p-6 sm:p-10 rounded-3xl shadow-[0_0_80px_rgba(197,160,89,0.8)] border-2 border-primary transform scale-110 animate-[bounce_1s_infinite] text-center pointer-events-auto">
                <span class="text-5xl sm:text-7xl mb-4 block drop-shadow-2xl">✨🎉✨</span>
                <h2 class="text-2xl sm:text-4xl font-serif font-black text-white mb-2 tracking-wide uppercase">Level Up!</h2>
                <p class="text-xl sm:text-2xl text-primary font-bold">Funki ist jetzt Level <span x-text="$wire.level"></span></p>
                <template x-if="rewardMessage">
                    <div class="mt-6 sm:mt-8 bg-gradient-to-r from-green-500/20 to-emerald-500/10 text-emerald-400 p-4 sm:p-5 rounded-xl border border-emerald-500/30 font-bold shadow-lg text-sm sm:text-base">
                        🎁 <span x-text="rewardMessage"></span>
                    </div>
                </template>
            </div>
        </div>

        {{-- TITLE BOX: Auf Mobile nach unten verschoben (top-20), damit Platz für das Schließen-Kreuz bleibt --}}
        <div class="absolute top-20 sm:top-10 left-4 right-4 sm:left-10 sm:right-auto z-[2020] flex justify-between items-start pointer-events-none">
            <div class="bg-gray-900/95 backdrop-blur-2xl border border-gray-700 rounded-[2rem] sm:rounded-[2.5rem] p-4 sm:p-6 flex items-center gap-4 sm:gap-8 shadow-2xl w-full max-w-lg pointer-events-auto">
                <div class="relative w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-gradient-to-b from-primary to-primary-dark border-2 border-gray-900 flex items-center justify-center shadow-[0_0_20px_rgba(197,160,89,0.6)] shrink-0">
                    <span class="text-gray-900 font-black text-xl sm:text-3xl">{{ $level }}</span>
                </div>
                <div class="flex-1">
                    <h3 class="text-white font-black text-xs sm:text-base uppercase tracking-[0.2em] mb-2 sm:mb-3">{{ $currentRankName }}</h3>
                    <div class="w-full h-2 sm:h-3 bg-gray-800 rounded-full overflow-hidden border border-gray-700 shadow-inner">
                        <div class="h-full bg-primary transition-all duration-1000 shadow-[0_0_15px_rgba(197,160,89,0.8)]" style="width: {{ $progressPercentage }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 relative">
            <div id="funki-3d-canvas-container" class="absolute inset-0 cursor-grab active:cursor-grabbing w-full h-full z-10" wire:ignore></div>
        </div>

        <div class="bg-gray-950/98 backdrop-blur-3xl border-t border-gray-800 relative z-[2020] flex flex-col">
            <div class="p-4 sm:p-8 overflow-x-auto no-scrollbar scroll-smooth flex items-center justify-start gap-4 sm:gap-8 px-6 sm:px-12">
                @php
                    $milestones = \App\Services\Gamification\GameConfig::getAppearanceMilestones();
                @endphp
                @foreach($milestones as $mLevel => $mName)
                    <div class="flex flex-col items-center gap-2 sm:gap-4 relative group shrink-0">
                        <button type="button"
                                @if($level >= $mLevel)
                                    @click="currentPath = '{{ asset('storage/funki/models/' . $mName . '.glb') }}'; currentImagePath = '{{ asset('storage/funki/models/images/' . $mName . '.png') }}'; window._funki3DLoader(currentPath);"
                                @endif
                                class="w-16 h-16 sm:w-24 sm:h-24 rounded-full bg-black border-2 flex items-center justify-center transition-all duration-1000 focus:outline-none {{ $level == $mLevel ? 'border-primary shadow-[0_0_25px_rgba(197,160,89,0.6)] scale-110 ring-4 ring-primary/20 z-10' : ($level > $mLevel ? 'border-primary/40 opacity-70 hover:opacity-100 hover:scale-105 hover:border-primary cursor-pointer' : 'border-gray-800 cursor-not-allowed') }}">
                            <img src="{{ asset('storage/funki/models/images/' . $mName . '.png') }}" class="w-full h-full object-cover rounded-full transition-all duration-1000 {{ $level >= $mLevel ? '' : 'blur-[10px] opacity-10 grayscale' }}">
                        </button>
                        <span class="text-[9px] sm:text-xs font-black uppercase tracking-widest {{ $level == $mLevel ? 'text-primary' : 'text-gray-600' }}">Level {{ $mLevel }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- CLOSE BUTTON: Oben rechts separiert --}}
        <button @click="close3DModal()" class="absolute top-4 right-4 sm:top-10 sm:right-10 z-[2050] p-2.5 sm:p-4 bg-gray-800 border-2 border-gray-700 rounded-full text-gray-400 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-[0_0_30px_rgba(0,0,0,0.8)] hover:scale-110 cursor-pointer">
            <svg class="w-5 h-5 sm:w-8 sm:h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>
