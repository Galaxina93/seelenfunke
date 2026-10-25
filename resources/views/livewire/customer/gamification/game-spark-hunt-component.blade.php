<div>
    @if($isVisible)
        <div x-data="{
            show: true,
            clicked: false,
            topPos: (Math.floor(Math.random() * 62) + 16) + '%',
            leftPos: (Math.floor(Math.random() * 72) + 8) + '%'
         }"
             x-show="show"
             x-transition.opacity.duration.700ms
             class="fixed z-[9990] cursor-pointer select-none group"
             :style="`top: ${topPos}; left: ${leftPos};`"
             title="Geheimer Seelenfunke – Klicke zum Einsammeln!"
             @click="if(!clicked) { clicked = true; setTimeout(() => { $wire.collectSpark(); show = false; }, 650); }">

            {{-- Schwebender Goldener Seelenfunke --}}
            <div x-show="!clicked" class="relative w-14 h-14 flex items-center justify-center animate-[sparkFloat_3.2s_ease-in-out_infinite] group-hover:scale-125 transition-transform duration-300">
                {{-- Äußere goldene Aura --}}
                <div class="absolute inset-0 bg-amber-400/35 dark:bg-amber-300/40 rounded-full blur-xl animate-pulse"></div>
                {{-- Rotierender Lichtkranz --}}
                <div class="absolute inset-1.5 rounded-full border border-amber-300/60 dark:border-amber-200/70 animate-[spin_6s_linear_infinite] shadow-[0_0_15px_rgba(245,158,11,0.7)]"></div>
                {{-- Innerer goldener Kern --}}
                <div class="absolute inset-3 bg-gradient-to-tr from-amber-500 via-yellow-300 to-white rounded-full blur-[2px] opacity-90"></div>
                {{-- 4-Strahlen Gold-Funke SVG --}}
                <svg class="relative z-10 w-8 h-8 text-amber-100 drop-shadow-[0_0_12px_rgba(251,191,36,1)]" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 1L14.8 9.2L23 12L14.8 14.8L12 23L9.2 14.8L1 12L9.2 9.2L12 1Z" />
                </svg>
                {{-- Kleine Begleit-Funken --}}
                <span class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-yellow-200 shadow-[0_0_8px_#fde047] animate-ping"></span>
            </div>

            {{-- Klick-Animation (Goldene Supernova-Explosion) --}}
            <div x-show="clicked" x-cloak class="relative w-14 h-14 flex items-center justify-center pointer-events-none">
                <div class="absolute w-28 h-28 bg-gradient-to-r from-amber-400/70 via-yellow-200/80 to-amber-500/70 rounded-full blur-xl animate-[ping_0.55s_cubic-bezier(0,0,0.2,1)_forwards]"></div>
                <div class="absolute w-16 h-16 border-2 border-amber-200 rounded-full animate-[ping_0.45s_ease-out_forwards]"></div>
                <span class="relative z-20 whitespace-nowrap text-base sm:text-lg font-serif font-black text-amber-300 drop-shadow-[0_2px_10px_rgba(245,158,11,0.95)] animate-[sparkFloatUp_0.65s_ cubic-bezier(0.16,1,0.3,1)_forwards]">
                    +1 ✨ Funke!
                </span>
            </div>
        </div>

        <style>
            @keyframes sparkFloat {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                50% { transform: translateY(-8px) rotate(8deg); }
            }
            @keyframes sparkFloatUp {
                0% { transform: translateY(0) scale(0.9); opacity: 1; }
                100% { transform: translateY(-44px) scale(1.35); opacity: 0; }
            }
        </style>
    @endif
</div>
