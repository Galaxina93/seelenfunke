<div>
    <div>
        @if($isVisible)
            <div x-data="{
                show: true,
                clicked: false,
                topPos: Math.floor(Math.random() * 80) + 10 + '%',
                leftPos: Math.floor(Math.random() * 80) + 10 + '%'
             }"
                 x-show="show"
                 x-transition.opacity.duration.1000ms
                 class="fixed z-[9999] cursor-pointer group"
                 :style="`top: ${topPos}; left: ${leftPos};`"
                 @click="clicked = true; setTimeout(() => { $wire.collectSpark() }, 600)">

                {{-- Normale Ansicht --}}
                <div x-show="!clicked" class="relative w-12 h-12 flex items-center justify-center animate-[bounce_3s_infinite] hover:scale-125 transition-transform duration-300">
                    <div class="absolute inset-0 bg-primary/40 rounded-full blur-md animate-pulse"></div>
                    <div class="absolute inset-2 bg-primary rounded-full blur-sm"></div>
                    <span class="relative z-10 text-2xl drop-shadow-[0_0_10px_rgba(255,255,255,0.8)]">✨</span>
                </div>

                {{-- Klick Animation (Explosion) --}}
                <div x-show="clicked" x-cloak class="absolute inset-0 flex items-center justify-center">
                    <div class="w-24 h-24 bg-primary/50 rounded-full blur-lg animate-[ping_0.5s_cubic-bezier(0,0,0.2,1)_forwards]"></div>
                    <span class="absolute text-xl font-black text-primary drop-shadow-[0_0_8px_currentColor] animate-[floatUp_0.6s_ease-out_forwards]">+1</span>
                </div>

            </div>

            <style>
                @keyframes floatUp {
                    0% { transform: translateY(0) scale(1); opacity: 1; }
                    100% { transform: translateY(-40px) scale(1.5); opacity: 0; }
                }
            </style>
        @endif
    </div>
</div>
