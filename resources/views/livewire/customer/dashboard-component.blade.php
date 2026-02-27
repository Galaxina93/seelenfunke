<div class="w-full">
    @if(!$hasOptedIn)
        {{-- ========================================== --}}
        {{-- OPT-IN SCREEN --}}
        {{-- ========================================== --}}
        <div x-data="optInScreen()" @mousemove="handleMouse($event)" @mouseleave="resetMouse()"
             :class="isWarping ? 'opacity-0 scale-95 blur-sm' : 'opacity-100 scale-100 blur-0'"
             class="max-w-6xl mx-auto relative p-6 sm:p-10 lg:p-20 flex flex-col lg:flex-row items-center gap-10 lg:gap-16 transition-all duration-[1500ms] ease-in-out mt-8 lg:mt-12">

            <div class="absolute inset-0 pointer-events-none transition-opacity duration-300 transform-gpu" style="will-change: opacity, background;" :style="`background: radial-gradient(circle 600px at ${mouseX}px ${mouseY}px, rgba(197, 160, 89, 0.12), transparent 40%); opacity: ${isHovering ? 1 : 0};`"></div>

            <template x-teleport="body">
                <div x-show="isActivating" style="display: none;" class="fixed inset-0 z-[9000] pointer-events-none flex items-center justify-center overflow-hidden">
                    <div x-show="isActivating" x-transition:enter="transition ease-out duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0 bg-gray-950/95 backdrop-blur-xl"></div>
                    <div x-show="phase >= 1" x-transition:enter="transition ease-out duration-[1500ms]" x-transition:enter-start="scale-0 opacity-100" x-transition:enter-end="scale-[3] opacity-0" class="absolute w-[20rem] h-[20rem] md:w-[30rem] md:h-[30rem] border-[8px] border-primary rounded-full blur-[4px]"></div>
                    <div x-show="phase >= 1" x-transition:enter="transition ease-out duration-[1000ms] delay-100" x-transition:enter-start="scale-0 opacity-100" x-transition:enter-end="scale-[4] opacity-0" class="absolute w-[20rem] h-[20rem] md:w-[30rem] md:h-[30rem] border-[4px] border-white rounded-full"></div>
                    <div x-show="phase >= 1" class="relative z-10 w-32 h-32 md:w-48 md:h-48 rounded-full bg-primary/30 blur-2xl animate-pulse"></div>
                </div>
            </template>

            <div class="relative z-10 flex-1 text-center lg:text-left">
                @if(count($profileSteps) > 0)
                    <div class="mb-8 flex flex-col sm:flex-row flex-wrap items-center lg:items-start gap-3 p-4 sm:p-5 bg-gray-900 rounded-2xl border border-gray-800 shadow-inner">
                        <span class="w-full block text-[10px] text-gray-500 uppercase tracking-[0.2em] font-black mb-1">Profil vervollständigen:</span>
                        @foreach($profileSteps as $step)
                            <button @click="{!! $step['action'] !!}" class="px-4 py-2 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full text-[9px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse">{{ $step['label'] }}</button>
                        @endforeach
                    </div>
                @endif

                <span class="inline-block px-4 py-1.5 sm:px-5 sm:py-2 bg-primary/10 text-primary font-black uppercase tracking-widest rounded-xl mb-4 sm:mb-6 border border-primary/30 shadow-[0_0_15px_rgba(197,160,89,0.2)] animate-pulse text-[10px] sm:text-xs">Dein neues Erlebnis</span>
                <h2 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-serif font-bold text-white mb-4 sm:mb-6 leading-[1.1] drop-shadow-md tracking-tight">Einkaufen,<br>weit weg vom <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-amber-300">Standard.</span></h2>
                <p class="text-gray-400 text-sm sm:text-base md:text-lg mb-8 sm:mb-10 leading-relaxed max-w-2xl mx-auto lg:mx-0 font-medium">Willkommen in der Manufaktur! Dein Dashboard ist kein einfaches Kundenkonto mehr – es ist <strong class="text-white">spielerisch, interaktiv und lebendig</strong>. Begleite deinen persönlichen 3D-Gefährten auf seiner Reise.</p>

                <button @click="triggerEpicStart()" class="w-full sm:w-auto group relative px-8 sm:px-12 py-4 sm:py-5 bg-gradient-to-r from-primary to-primary-dark text-gray-900 rounded-xl font-black uppercase tracking-widest text-xs sm:text-sm shadow-[0_0_40px_rgba(197,160,89,0.5)] hover:scale-105 hover:shadow-[0_0_60px_rgba(197,160,89,0.8)] transition-all flex items-center justify-center gap-4 overflow-hidden transform-gpu mx-auto lg:mx-0">
                    <div class="absolute inset-0 bg-white/30 transform -skew-x-12 -translate-x-[150%] group-hover:translate-x-[150%] transition-transform duration-1000 ease-in-out"></div>
                    <span>Magie aktivieren</span>
                    <svg class="w-5 h-5 group-hover:translate-x-2 group-hover:scale-110 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
            </div>

            <div class="relative z-10 w-full lg:w-5/12 flex justify-center perspective-1000 mt-8 lg:mt-0 shrink-0">
                <div class="relative w-56 h-56 sm:w-72 sm:h-72 md:w-96 md:h-96 transform-gpu hover:rotate-y-12 hover:rotate-x-12 transition-transform duration-700 ease-out shrink-0">
                    <div class="absolute inset-0 bg-primary/20 rounded-full blur-[40px] md:blur-[50px] animate-pulse"></div>
                    <img src="{{ asset('storage/funki/models/images/funki_lvl_5_apprentice.png') }}" draggable="false" class="relative w-full h-full object-contain drop-shadow-[0_20px_30px_rgba(0,0,0,0.8)] animate-[float_6s_ease-in-out_infinite] pointer-events-none select-none z-10">
                </div>
            </div>
        </div>

    @else
        {{-- ========================================== --}}
        {{-- ZENTRALE (Funki Hub nach Opt-In) --}}
        {{-- ========================================== --}}
        <div class="relative animate-fade-in-up z-10 min-h-[85vh] flex flex-col items-center justify-center py-10"
             @funki-level-up.window="handleLevelUp($event.detail[0])"
             x-data="funkiHub('{{ $modelPath }}', '{{ $imagePath }}')"
             x-init="initShop()">

            <div wire:ignore x-show="darkFade" x-transition:leave="transition ease-in-out duration-[2000ms]" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[9999] bg-gray-950 pointer-events-none" style="display: none;"></div>

            <div class="relative z-10 text-center flex flex-col items-center w-full max-w-6xl px-4 sm:px-6">
                <style>
                    @keyframes subtleFloat { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
                    .animate-subtle-float { animation: subtleFloat 6s ease-in-out infinite; }
                    .perspective-1000 { perspective: 1000px; }
                    @keyframes slideUpFade { 0% { opacity: 0; transform: translateY(40px) scale(0.9); } 100% { opacity: 1; transform: translateY(0) scale(1); } }
                    .animate-level-up { animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                </style>

                <h2 class="text-3xl sm:text-5xl md:text-7xl font-serif font-bold mb-4 tracking-tight text-white drop-shadow-2xl">Willkommen, {{ auth()->user()->first_name }}</h2>
                <p class="text-gray-400 text-sm sm:text-xl mb-12 max-w-2xl leading-relaxed drop-shadow-md mx-auto">Deine persönliche Manufaktur. Betrachte deine Evolution, rüste dich im Shop aus oder verdiene Funken in den Spielen.</p>

                <div class="relative mb-36 sm:mb-44 flex flex-col items-center mt-8 w-full max-w-[18rem] sm:max-w-md md:max-w-lg lg:max-w-xl mx-auto">

                    <button @click.stop="showTitlesModal=true" class="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-900 border border-primary px-6 py-2.5 sm:px-10 sm:py-3 rounded-full font-black uppercase tracking-widest text-[10px] sm:text-xs text-white shadow-[0_0_25px_rgba(197,160,89,0.6)] flex items-center gap-2 sm:gap-3 hover:bg-primary hover:text-gray-900 transition-all z-40 hover:scale-110 group cursor-pointer whitespace-nowrap">
                        {{$currentRankName}}
                        <svg class="w-3 h-3 sm:w-4 sm:h-4 group-hover:rotate-90 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </button>

                    <div class="relative w-64 h-64 sm:w-80 sm:h-80 md:w-[26rem] md:h-[26rem] flex items-center justify-center shrink-0">

                        <svg class="absolute inset-0 w-full h-full -rotate-90 drop-shadow-[0_0_20px_rgba(197,160,89,0.5)] pointer-events-none z-20" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#1f2937" stroke-width="1.5"></circle>
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#c5a059" stroke-width="3" stroke-linecap="round"
                                    stroke-dasharray="301.59"
                                    stroke-dashoffset="{{ 301.59 - ($progressPercentage / 100) * 301.59 }}"
                                    class="transition-all duration-1000 ease-out"></circle>
                        </svg>

                        <button @click="open3DModal()" class="absolute inset-3 sm:inset-4 md:inset-5 rounded-full bg-gray-900 border-2 border-gray-800 shadow-[0_30px_60px_rgba(0,0,0,0.8)] flex items-center justify-center overflow-hidden z-10 group transition-transform duration-700 hover:scale-[1.02]">
                            <div class="absolute inset-0 bg-primary/10 rounded-full blur-[60px] md:blur-[80px] group-hover:bg-primary/30 transition-colors duration-700 pointer-events-none"></div>
                            <img :src="currentImagePath ? currentImagePath : '{{$imagePath}}'" src="{{$imagePath}}" class="w-full h-full object-contain p-4 sm:p-8 animate-subtle-float">
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-sm z-20 pointer-events-none">
                                <span class="bg-primary text-gray-900 px-6 py-3 md:px-8 md:py-4 rounded-xl font-black text-[10px] sm:text-xs uppercase tracking-widest shadow-2xl whitespace-nowrap">3D Modell öffnen</span>
                            </div>
                        </button>

                        <div class="absolute top-6 -left-6 sm:top-12 sm:-left-10 md:top-16 md:-left-12 z-30 bg-gray-900/95 backdrop-blur-md border border-blue-500/30 px-3 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-[0_0_20px_rgba(59,130,246,0.3)] flex items-center gap-2 sm:gap-3 hover:scale-105 transition-transform cursor-default">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-blue-500/20 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </div>
                            <div class="text-left hidden sm:block">
                                <p class="text-[8px] sm:text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Energie</p>
                                <p class="text-white font-bold text-xs sm:text-sm mt-1 leading-none">{{ $energyBalance }}/{{ $maxEnergy }}</p>
                            </div>
                            <div class="text-left sm:hidden">
                                <p class="text-white font-bold text-xs leading-none">{{ $energyBalance }}/{{ $maxEnergy }}</p>
                            </div>
                        </div>

                        <div class="absolute top-6 -right-6 sm:top-12 sm:-right-10 md:top-16 md:-right-12 z-30 bg-gray-900/95 backdrop-blur-md border border-primary/30 px-3 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-[0_0_20px_rgba(197,160,89,0.3)] flex items-center gap-2 sm:gap-3 hover:scale-105 transition-transform cursor-default">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                                <span class="text-xs sm:text-sm">✨</span>
                            </div>
                            <div class="text-left hidden sm:block">
                                <p class="text-[8px] sm:text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Guthaben</p>
                                <p class="text-white font-bold text-xs sm:text-sm mt-1 leading-none">{{ $balance }} F.</p>
                            </div>
                            <div class="text-left sm:hidden">
                                <p class="text-white font-bold text-xs leading-none">{{ $balance }} F.</p>
                            </div>
                        </div>

                        <div class="absolute -bottom-12 sm:-bottom-16 md:-bottom-20 left-1/2 -translate-x-1/2 z-30 flex flex-col items-center w-[130%] sm:w-[150%] md:w-max">
                            <div class="bg-gradient-to-r from-primary to-primary-dark text-gray-900 px-8 py-2.5 sm:px-12 sm:py-3 rounded-full font-black text-[10px] sm:text-sm uppercase tracking-[0.2em] shadow-[0_10px_20px_rgba(0,0,0,0.6)]">
                                Level {{$level}}
                            </div>

                            @if(!$isMaxLevel)
                                <div class="mt-3 sm:mt-4 w-full bg-gray-900/95 backdrop-blur-xl border border-gray-800 rounded-xl p-3 sm:p-4 text-center shadow-2xl">
                                    <p class="text-[9px] sm:text-xs font-black uppercase tracking-widest text-gray-400 whitespace-nowrap">
                                        <span class="text-primary">{{ $progressPercentage }}%</span> - Noch {{ $missingSparks }} Funken
                                    </p>
                                    @if($canUpgrade)
                                        <button wire:click="upgrade" wire:loading.attr="disabled" class="mt-3 sm:mt-4 w-full py-2.5 sm:py-3 bg-primary text-gray-900 rounded-lg text-[9px] sm:text-[10px] font-black uppercase tracking-widest hover:bg-white transition-colors shadow-[0_0_15px_rgba(197,160,89,0.4)] animate-[pulse_2s_infinite]">
                                            <span wire:loading.remove wire:target="upgrade">Level Up durchführen!</span>
                                            <span wire:loading wire:target="upgrade">Wirkt Magie...</span>
                                        </button>
                                    @endif
                                </div>
                            @else
                                <div class="mt-3 sm:mt-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-6 py-3 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest shadow-lg">
                                    Maximales Level
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-center gap-3 sm:gap-4 relative z-50 w-full sm:w-auto mt-4 sm:mt-0">
                    <a href="{{ route('customer.games') }}" class="w-full sm:w-auto px-6 py-4 sm:px-8 sm:py-4 bg-emerald-500 text-gray-900 rounded-xl font-black text-xs sm:text-sm hover:bg-emerald-400 transition-all uppercase tracking-widest shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:-translate-y-1 flex items-center justify-center gap-2">
                        <span class="text-lg sm:text-xl">🎮</span> Spielen
                    </a>
                    <a href="{{ route('customer.funki-shop') }}" class="w-full sm:w-auto px-6 py-4 sm:px-8 sm:py-4 bg-gray-800 text-white rounded-xl font-black text-xs sm:text-sm hover:bg-primary hover:text-gray-900 border border-gray-700 transition-all uppercase tracking-widest shadow-xl hover:-translate-y-1 flex items-center justify-center gap-2">
                        <span class="text-lg sm:text-xl">💎</span> Shop
                    </a>
                </div>
            </div>

            {{-- 3D EVOLUTION MODAL (Mit Level Up Animation!) --}}
            <template x-teleport="body">
                <div x-show="show3DModal" style="display: none;" class="fixed inset-0 z-[6000] flex flex-col items-center justify-between p-4 sm:p-10">
                    <div class="absolute inset-0 bg-black/98 backdrop-blur-3xl" @click="close3DModal()" x-transition.opacity></div>

                    <div class="relative w-full max-w-[90rem] flex-1 flex flex-col bg-gradient-to-b from-gray-900 to-black rounded-[2rem] sm:rounded-[3rem] shadow-[0_0_100px_rgba(0,0,0,1)] border border-gray-800 overflow-hidden"
                         x-transition:enter="transition ease-out duration-300 delay-100" x-transition:enter-start="opacity-0 scale-95 translate-y-10" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-10">

                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent opacity-60 pointer-events-none"></div>

                        <div x-show="evolutionFlash" x-transition:enter="transition ease-in duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-out duration-1000" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0 z-[6050] flex items-center justify-center bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-white via-white/95 to-primary/50 pointer-events-none" style="display: none;"></div>

                        <div x-show="showConfetti" x-transition class="absolute inset-0 z-[6060] pointer-events-none flex flex-col items-center justify-center" style="display: none;">
                            <h2 class="text-5xl md:text-8xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-primary to-amber-300 drop-shadow-[0_0_30px_rgba(197,160,89,1)] animate-bounce mb-4 text-center">LEVEL UP!</h2>
                            <p class="text-white text-lg md:text-2xl font-black uppercase tracking-widest drop-shadow-lg text-center" x-text="rewardMessage"></p>
                        </div>

                        <div class="absolute top-4 left-4 sm:top-8 sm:left-8 z-[6020] flex items-center gap-3 sm:gap-4 bg-gray-900/90 backdrop-blur-md border border-gray-700 rounded-[2rem] p-2 pr-4 sm:p-4 sm:pr-8 shadow-2xl pointer-events-auto">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-b from-primary to-primary-dark border-2 border-gray-900 flex items-center justify-center shadow-[0_0_20px_rgba(197,160,89,0.6)] shrink-0">
                                <span class="text-gray-900 font-black text-lg sm:text-xl">{{ $level }}</span>
                            </div>
                            <h3 class="text-white font-black text-[9px] sm:text-xs uppercase tracking-[0.2em]">{{ $currentRankName }}</h3>
                        </div>

                        <button @click="close3DModal()" class="absolute top-4 right-4 sm:top-8 sm:right-8 z-[6050] p-2.5 sm:p-3 bg-gray-800 border-2 border-gray-700 rounded-full text-gray-400 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-[0_0_30px_rgba(0,0,0,0.8)] hover:scale-110 cursor-pointer">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        <div class="flex-1 w-full relative min-h-0 pt-20 pb-4">
                            <div id="funki-3d-canvas-container" class="absolute inset-0 cursor-grab active:cursor-grabbing w-full h-full z-10" wire:ignore></div>
                        </div>

                        <div class="w-full bg-gray-950/98 backdrop-blur-3xl border-t border-gray-800 relative z-[6020] pb-safe">
                            <div class="flex flex-row items-center justify-start gap-4 sm:gap-8 px-4 sm:px-12 py-4 sm:py-6 overflow-x-auto no-scrollbar scroll-smooth w-full">
                                @php $milestones = \App\Services\Gamification\GameConfig::getAppearanceMilestones(); @endphp
                                @foreach($milestones as $mLevel => $mName)
                                    <div class="flex flex-col items-center gap-2 sm:gap-3 relative group shrink-0">
                                        <button type="button" @if($level >= $mLevel) @click="currentPath = '{{ asset('storage/funki/models/' . $mName . '.glb') }}'; currentImagePath = '{{ asset('storage/funki/models/images/' . $mName . '.png') }}'; window._funki3DLoader(currentPath);" @endif class="w-14 h-14 sm:w-20 sm:h-20 shrink-0 rounded-full bg-black border-2 flex items-center justify-center transition-all duration-1000 focus:outline-none {{ $level == $mLevel ? 'border-primary shadow-[0_0_25px_rgba(197,160,89,0.6)] scale-110 ring-4 ring-primary/20 z-10' : ($level > $mLevel ? 'border-primary/40 opacity-70 hover:opacity-100 hover:scale-105 hover:border-primary cursor-pointer' : 'border-gray-800 cursor-not-allowed') }}">
                                            <img src="{{ asset('storage/funki/models/images/' . $mName . '.png') }}" class="w-full h-full object-cover rounded-full transition-all duration-1000 {{ $level >= $mLevel ? '' : 'blur-[10px] opacity-10 grayscale' }}">
                                        </button>
                                        <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-widest {{ $level == $mLevel ? 'text-primary' : 'text-gray-600' }}">Level {{ $mLevel }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif

    <script>
        function optInScreen() {
            return {
                mouseX: 0, mouseY: 0, isHovering: false, isWarping: false, isActivating: false, phase: 0,
                handleMouse(e) { this.isHovering = true; const rect = this.$el.getBoundingClientRect(); this.mouseX = e.clientX - rect.left; this.mouseY = e.clientY - rect.top; },
                resetMouse() { this.isHovering = false; },
                triggerEpicStart() {
                    this.isWarping = true; this.isActivating = true;
                    window.dispatchEvent(new CustomEvent('warp-started'));
                    setTimeout(() => { this.phase = 1; }, 50);
                    setTimeout(() => { window.sessionStorage.setItem('funki_just_activated', 'true'); this.$wire.optIn(); }, 2500);
                }
            };
        }

        function funkiHub(initialModelPath, initialImagePath) {
            let scene, camera, renderer, controls, currentModel;
            let threeInitialized = false;
            let animationId = null;

            return {
                darkFade: false, evolutionFlash: false, showConfetti: false, rewardMessage: '',
                show3DModal: false, showTitlesModal: false,
                currentPath: initialModelPath || '',
                currentImagePath: initialImagePath || '',

                initShop() {
                    if (window.sessionStorage.getItem('funki_just_activated')) {
                        this.darkFade = true; window.sessionStorage.removeItem('funki_just_activated');
                        setTimeout(() => { this.darkFade = false; }, 100);
                    }
                },

                handleLevelUp(data) {
                    this.open3DModal();
                    setTimeout(() => {
                        this.evolutionFlash = true; // Trigger white flash
                        setTimeout(() => {
                            this.currentPath = data.newModelPath;
                            this.currentImagePath = data.newImagePath;
                            if (threeInitialized && window._funki3DLoader) {
                                window._funki3DLoader(this.currentPath, () => {
                                    this.evolutionFlash = false; // Fade out flash
                                    this.rewardMessage = data.reward || 'Neue Form freigeschaltet!';
                                    this.showConfetti = true; // Show Text
                                    setTimeout(() => { this.showConfetti = false; }, 4000);
                                });
                            }
                        }, 1000); // Wait for flash peak
                    }, 500); // Wait for modal to open
                },

                open3DModal() {
                    this.show3DModal = true;
                    setTimeout(() => {
                        if (!threeInitialized) {
                            this.initThreeJS();
                        } else {
                            this.resizeThreeJS();
                            if(!animationId && typeof window._funkiRestartAnimation === 'function') {
                                window._funkiRestartAnimation();
                            }
                        }
                    }, 100);
                },

                close3DModal() {
                    this.show3DModal = false;
                },

                initThreeJS() {
                    const container = document.getElementById('funki-3d-canvas-container');
                    if (!container || typeof window.THREE === 'undefined' || typeof window.GLTFLoader === 'undefined' || typeof window.OrbitControls === 'undefined') {
                        console.warn('ThreeJS or plugins not loaded properly.'); return;
                    }
                    scene = new window.THREE.Scene();
                    camera = new window.THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 0.1, 1000);
                    renderer = new window.THREE.WebGLRenderer({ antialias: true, alpha: true });
                    renderer.setSize(container.offsetWidth, container.offsetHeight);
                    renderer.setPixelRatio(window.devicePixelRatio);
                    container.appendChild(renderer.domElement);

                    scene.add(new window.THREE.AmbientLight(0xffffff, 2.5));
                    const dirLight = new window.THREE.DirectionalLight(0xffffff, 2.0); dirLight.position.set(5, 5, 5); scene.add(dirLight);

                    controls = new window.OrbitControls(camera, renderer.domElement);
                    controls.enableDamping = true; controls.minDistance = 1; controls.maxDistance = 5;

                    window._funki3DLoader = (path, cb) => {
                        if (!path || path.trim() === '') return;
                        const loader = new window.GLTFLoader();
                        if (currentModel) scene.remove(currentModel);
                        loader.load(path, (gltf) => {
                            currentModel = gltf.scene;
                            const box = new window.THREE.Box3().setFromObject(currentModel);
                            const center = box.getCenter(new window.THREE.Vector3());
                            currentModel.position.sub(center); currentModel.rotation.y = Math.PI / -2;
                            scene.add(currentModel); camera.position.set(0, 0.8, 2.5);
                            if (cb) cb();
                        });
                    };

                    window._funki3DLoader(this.currentPath);

                    const animate = () => {
                        if(!this.show3DModal) {
                            animationId = null;
                            return;
                        }
                        animationId = requestAnimationFrame(animate);
                        if (currentModel) currentModel.position.y = Math.sin(Date.now() * 0.002) * 0.05;
                        if (controls) controls.update();
                        if (renderer) renderer.render(scene, camera);
                    };

                    window._funkiRestartAnimation = animate;
                    animate();

                    threeInitialized = true;
                    window.addEventListener('resize', () => this.resizeThreeJS());
                },
                resizeThreeJS() {
                    const container = document.getElementById('funki-3d-canvas-container');
                    if (!camera || !renderer || !container) return;
                    camera.aspect = container.offsetWidth / container.offsetHeight; camera.updateProjectionMatrix();
                    renderer.setSize(container.offsetWidth, container.offsetHeight);
                }
            };
        }
    </script>
</div>
