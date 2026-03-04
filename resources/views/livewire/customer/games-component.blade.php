<div class="p-4 sm:p-6 lg:p-10 min-h-full flex flex-col relative z-10" x-data="kristallKollaps3D()">

    {{-- AUDIO BGM --}}
    <audio x-ref="bgmAudio" src="{{ asset('storage/gamification/music/cristall_kollaps_bg_music.mp3') }}" loop preload="auto"></audio>

    {{-- SEITEN-HEADER --}}
    <div class="mb-8 sm:mb-10">
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-white tracking-tight">Manufaktur Spiele</h1>
        <p class="text-gray-400 mt-2 text-xs sm:text-sm uppercase tracking-widest font-bold">Verdiene Funken durch Geschick & Taktik</p>
    </div>

    {{-- SPIELAUSWAHL (Menü) --}}
    <div x-show="!activeGame" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-10" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 sm:gap-8">

        <div @click="activeGame = 'kristall'" class="group relative bg-gray-900 rounded-[2rem] sm:rounded-[2.5rem] border border-gray-800 p-6 sm:p-8 hover:border-emerald-500/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(16,185,129,0.15)] flex flex-col cursor-pointer overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl sm:rounded-3xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-6 sm:mb-8 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                <span class="text-4xl sm:text-5xl drop-shadow-[0_0_15px_rgba(16,185,129,0.5)]">💎</span>
            </div>
            <h4 class="text-2xl sm:text-3xl font-serif font-bold text-white mb-3 sm:mb-4">Kristall-Kollaps 3D</h4>
            <p class="text-gray-400 text-sm sm:text-base leading-relaxed mb-6 sm:mb-8 flex-1">
                Kettenreaktionen, Nova-Sphären und Zeit-Kristalle. Wische oder Tausche strategisch klug auf dem 8x8 Feld, um schneller an Rabatte zu kommen!
            </p>
            <button type="button" class="w-full py-4 sm:py-5 bg-gray-950 text-emerald-500 border border-emerald-500/30 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest group-hover:bg-emerald-500 group-hover:text-gray-900 transition-all shadow-inner">
                Öffnen
            </button>
        </div>

        <div class="group relative bg-gray-900/50 rounded-[2rem] sm:rounded-[2.5rem] border border-gray-800 border-dashed p-6 sm:p-8 flex flex-col items-center justify-center text-center opacity-50 select-none">
            <span class="text-3xl sm:text-4xl mb-4 grayscale">🔒</span>
            <h4 class="text-lg sm:text-xl font-bold text-gray-500 mb-2">In Entwicklung</h4>
            <p class="text-gray-600 text-xs sm:text-sm">Ein neues Spiel wird in der Manufaktur geschmiedet...</p>
        </div>

    </div>

    {{-- AKTIVES SPIEL: KRISTALL-KOLLAPS --}}
    <div x-show="activeGame === 'kristall'" x-cloak x-transition:enter="transition ease-out duration-500 delay-200" x-transition:enter-start="opacity-0 translate-y-10" x-transition:enter-end="opacity-100 translate-y-0" class="w-full max-w-[1400px] mx-auto flex-1 flex flex-col">

        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 sm:gap-6 mb-6 sm:mb-8 bg-gray-900/80 p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-gray-800 shadow-lg">
            <div class="flex items-center gap-3 sm:gap-4 bg-gray-950 px-4 py-2.5 sm:px-5 sm:py-3 rounded-xl sm:rounded-2xl border border-gray-800 shadow-inner w-full sm:w-auto justify-center">
                <button type="button" @click="toggleMute()" class="text-gray-400 hover:text-emerald-400 transition-colors focus:outline-none shrink-0">
                    <svg x-show="isBgmPlaying" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5 12h4l4-4v16l-4-4H5a2 2 0 01-2-2v-4a2 2 0 012-2z" /></svg>
                    <svg x-show="!isBgmPlaying" style="display: none;" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                </button>
                <input type="range" min="0" max="1" step="0.05" x-model="bgmVolume" class="volume-slider w-full sm:w-32">
            </div>

            <button type="button" @click="quitGame()" class="w-full sm:w-auto justify-center px-6 py-3 bg-gray-800 text-gray-300 rounded-xl sm:rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-700 hover:text-white transition-all flex items-center gap-3 shadow-lg">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Spiel verlassen
            </button>
        </div>

        <div class="relative w-full flex-1 flex flex-col">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12 flex-1 pb-10 transition-all duration-500" :class="gameState === 'ready' ? 'opacity-20 blur-md pointer-events-none grayscale-[50%]' : 'opacity-100 blur-0'">

                <div class="lg:col-span-4 xl:col-span-4 flex flex-col gap-6 w-full">
                    <div class="bg-gray-900 border border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-[0_20px_40px_rgba(0,0,0,0.5)] relative overflow-hidden text-center flex flex-col items-center">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="relative z-10 w-full mb-4 sm:mb-6">
                            <p class="text-gray-500 text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2">Aktuelle Punkte</p>
                            <h4 class="text-5xl sm:text-6xl font-serif font-bold text-emerald-400 drop-shadow-[0_0_15px_rgba(16,185,129,0.4)]" x-text="score"></h4>
                            <div class="mt-3 inline-flex items-center gap-2 bg-gray-950 px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl border border-gray-800 shadow-inner">
                                <span class="text-sm sm:text-base">✨</span>
                                <span class="text-xs sm:text-sm font-bold text-primary">= <span x-text="Math.floor(score / 100)"></span> Funken</span>
                            </div>
                        </div>

                        <div class="w-full h-px bg-gray-800 relative z-10 my-2"></div>

                        <div class="relative z-10 w-full mt-4 sm:mt-6">
                            <p class="text-gray-500 text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2">Züge übrig</p>
                            <h4 class="text-5xl sm:text-6xl font-serif font-bold text-white transition-colors" :class="{'text-red-500 animate-bounce': moves <= 5}" x-text="moves"></h4>
                        </div>
                    </div>

                    {{-- VISUELLES HANDBUCH INGAME --}}
                    <div class="hidden lg:flex bg-blue-500/5 border border-blue-500/20 p-5 sm:p-6 rounded-[2rem] text-blue-300 text-xs sm:text-sm leading-relaxed flex-col gap-4 shadow-inner">
                        <strong class="text-blue-400 uppercase tracking-[0.2em] font-black text-[10px] border-b border-blue-500/20 pb-3 block w-full">Das Handbuch</strong>
                        <ul class="space-y-4">
                            <li class="flex gap-3 items-start">
                                <div class="w-5 h-5 mt-0.5 shrink-0 grid grid-cols-2 gap-0.5">
                                    <div class="bg-red-500 rounded-sm"></div><div class="bg-blue-500 rounded-sm"></div>
                                    <div class="bg-green-500 rounded-sm"></div><div class="bg-yellow-500 rounded-sm"></div>
                                </div>
                                <span><strong>Basis-Steine:</strong> Bilde 3er-Reihen. Kostet 1 Zug.</span>
                            </li>
                            <li class="flex gap-3 items-start">
                                <div class="w-5 h-5 mt-0.5 shrink-0 bg-white rotate-45 shadow-[0_0_10px_rgba(255,255,255,0.8)] border border-gray-300"></div>
                                <span><strong>Master-Diamant (Weiß):</strong> Zerstöre ihn in einer Reihe für Kreuz-Laser!</span>
                            </li>
                            <li class="flex gap-3 items-start">
                                <div class="w-5 h-5 mt-0.5 shrink-0 bg-black rounded-full shadow-[0_0_10px_rgba(168,85,247,0.8)] border border-purple-500"></div>
                                <span><strong>Nova-Sphäre (Schwarz/Lila):</strong> Zerstört alles im 3x3 Umkreis! Tausche sie beliebig.</span>
                            </li>
                            <li class="flex gap-3 items-start">
                                <div class="w-5 h-5 mt-0.5 shrink-0 border-[4px] border-orange-500 rounded-full shadow-[0_0_10px_rgba(249,115,22,0.8)]"></div>
                                <span><strong>Phantom-Stein (Orange Ring):</strong> Tausche ihn 3x straffrei ohne Zugverlust!</span>
                            </li>
                            <li class="flex gap-3 items-start">
                                <div class="w-5 h-5 mt-0.5 shrink-0 bg-blue-700 shadow-[0_0_10px_rgba(29,78,216,0.8)] rounded-sm rotate-12 border border-blue-400"></div>
                                <span><strong>Farb-Vortex (Tiefblau):</strong> Tausche ihn mit einer Farbe, um alle Steine dieser Farbe zu vernichten.</span>
                            </li>
                            <li class="flex gap-3 items-start">
                                <div class="w-5 h-5 mt-0.5 shrink-0 bg-yellow-300 shadow-[0_0_10px_rgba(253,224,71,0.8)] rounded-sm border border-yellow-100 rotate-45"></div>
                                <span><strong>Kettenblitz (Leuchtend Gelb):</strong> Zerstört wild 6 zufällige Steine auf dem Feld.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="lg:col-span-8 xl:col-span-8 w-full flex justify-center items-center relative z-10">
                    <div class="w-full max-w-[800px] aspect-square relative rounded-[2rem] sm:rounded-[3rem] bg-gray-900 border-2 border-gray-700 shadow-[0_20px_50px_rgba(0,0,0,0.6)] overflow-hidden flex items-center justify-center touch-none">
                        <div id="threejs-match3-container" x-ref="canvasContainer" wire:ignore class="absolute inset-0 z-10 touch-none"></div>
                        <div id="floating-scores-layer" class="absolute inset-0 pointer-events-none z-30 overflow-hidden"></div>

                        <div x-show="gameState === 'gameover'" x-cloak x-transition.opacity class="absolute inset-0 z-40 bg-gray-950/85 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center">
                            <h3 class="text-5xl sm:text-6xl font-serif font-bold text-emerald-400 mb-4 drop-shadow-[0_0_15px_rgba(16,185,129,0.5)]">Erfolg!</h3>
                            <div class="inline-flex items-center gap-3 bg-gray-900 px-6 py-3 rounded-2xl border border-gray-800 shadow-inner mb-8">
                                <span class="text-gray-400 uppercase font-black text-xs tracking-widest">Ausbeute:</span>
                                <span class="text-primary font-bold text-2xl sm:text-3xl" x-text="Math.floor(score / 100)"></span>
                                <span class="text-2xl">✨</span>
                            </div>

                            <button type="button" @click="attemptStartGame()" class="px-10 sm:px-14 py-5 sm:py-6 bg-emerald-500 text-gray-900 rounded-full font-black text-lg sm:text-xl uppercase tracking-[0.2em] hover:scale-105 transition-transform shadow-[0_0_60px_rgba(16,185,129,0.5)]">
                                Erneut Spielen
                            </button>
                            <p class="text-emerald-400 mt-5 font-black text-xs sm:text-sm uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                        </div>

                        <div x-show="energyWarning" x-cloak x-transition.opacity class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-32 bg-red-600/95 backdrop-blur-sm text-white px-6 py-3 rounded-2xl border border-red-400 shadow-[0_0_30px_rgba(220,38,38,0.8)] font-black uppercase tracking-widest text-xs sm:text-sm text-center flex items-center gap-3 w-max z-50">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            Nicht genug Energie!
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="gameState === 'ready'" x-cloak x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 z-50 flex items-center justify-center p-4 sm:p-6 pb-20">

                <div class="bg-gray-900/95 backdrop-blur-2xl border border-gray-700 shadow-[0_0_100px_rgba(0,0,0,0.8)] rounded-[2rem] sm:rounded-[3rem] w-full max-w-5xl p-6 sm:p-10 lg:p-12 relative flex flex-col lg:flex-row gap-8 lg:gap-12 items-center lg:items-stretch overflow-hidden">

                    <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/10 rounded-full blur-[80px] pointer-events-none"></div>

                    {{-- VISUELLES HANDBUCH START-MODAL --}}
                    <div class="flex-1 bg-gray-950/60 rounded-3xl p-6 sm:p-8 border border-gray-800 w-full flex flex-col justify-center shadow-inner relative z-10">
                        <div class="flex items-center justify-between border-b border-blue-500/20 pb-4 mb-6">
                            <h3 class="text-blue-400 font-black uppercase tracking-[0.2em] text-xs sm:text-sm flex items-center gap-3">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                Das Handbuch
                            </h3>
                        </div>
                        <ul class="space-y-4 sm:space-y-5 text-gray-300 text-xs sm:text-sm leading-relaxed">
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 shrink-0 grid grid-cols-2 gap-0.5">
                                    <div class="bg-red-500 rounded-sm"></div><div class="bg-blue-500 rounded-sm"></div>
                                    <div class="bg-green-500 rounded-sm"></div><div class="bg-yellow-500 rounded-sm"></div>
                                </div>
                                <span><strong>Basis-Steine:</strong> Bilde 3er-Reihen. Kostet 1 Zug.</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-5 h-5 mt-0.5 mx-0.5 shrink-0 bg-white rotate-45 shadow-[0_0_10px_rgba(255,255,255,0.8)] border border-gray-300"></div>
                                <span><strong>Master-Diamant (Weiß):</strong> Zerstöre ihn in einer Reihe für Kreuz-Laser!</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 shrink-0 bg-black rounded-full shadow-[0_0_10px_rgba(168,85,247,0.8)] border border-purple-500"></div>
                                <span><strong>Nova-Sphäre (Schwarz/Lila):</strong> Zerstört alles im 3x3 Umkreis!</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 shrink-0 border-[4px] border-orange-500 rounded-full shadow-[0_0_10px_rgba(249,115,22,0.8)]"></div>
                                <span><strong>Phantom-Stein (Orange Ring):</strong> Tausche ihn 3x straffrei ohne Zugverlust!</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 shrink-0 bg-blue-700 shadow-[0_0_10px_rgba(29,78,216,0.8)] rounded-sm rotate-12 border border-blue-400"></div>
                                <span><strong>Farb-Vortex (Tiefblau):</strong> Tausche ihn mit einer Farbe, um alle Steine dieser Farbe zu vernichten.</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-6 h-6 shrink-0 bg-yellow-300 shadow-[0_0_10px_rgba(253,224,71,0.8)] rounded-sm border border-yellow-100 rotate-45"></div>
                                <span><strong>Kettenblitz (Gelb):</strong> Zerstört wild 6 zufällige Steine.</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex-1 flex flex-col justify-center items-center w-full relative z-10">

                        <div class="flex gap-4 sm:gap-6 mb-8 w-full justify-center">
                            <div class="bg-gray-950 border border-gray-800 rounded-2xl p-4 sm:p-6 text-center flex-1 shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">Start-Züge</p>
                                <p class="text-3xl sm:text-4xl font-serif font-bold text-white">15</p>
                            </div>
                            <div class="bg-gray-950 border border-gray-800 rounded-2xl p-4 sm:p-6 text-center flex-1 shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">Letzte Punkte</p>
                                <p class="text-3xl sm:text-4xl font-serif font-bold text-emerald-400 drop-shadow-[0_0_10px_rgba(16,185,129,0.3)]" x-text="score">0</p>
                            </div>
                        </div>

                        <div class="relative w-full mt-2">
                            <button type="button" @click="attemptStartGame()" class="w-full py-5 sm:py-6 bg-emerald-500 text-gray-900 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] hover:scale-105 transition-transform shadow-[0_0_40px_rgba(16,185,129,0.5)]">
                                Spiel Starten
                            </button>
                            <div class="flex justify-between items-center mt-5 px-2">
                                <p class="text-emerald-500 font-black text-[10px] sm:text-xs uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                                <button type="button" @click="activeGame = null" class="text-gray-500 hover:text-white font-black text-[10px] sm:text-xs uppercase tracking-widest transition-colors">Abbrechen</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- STYLES --}}
    @include('livewire.customer.partials.games-component.styles')

    {{-- SCRIPTS --}}
    @include('livewire.customer.partials.games-component.scripts')
</div>
