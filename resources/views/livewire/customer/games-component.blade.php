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

        <div @if($currentEnergy > 0) @click="activeGame = 'kristall'" @endif class="group relative bg-gray-900 rounded-[2rem] sm:rounded-[2.5rem] border border-gray-800 p-6 sm:p-8 hover:border-emerald-500/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(16,185,129,0.15)] flex flex-col {{ $currentEnergy > 0 ? 'cursor-pointer' : '' }} overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl sm:rounded-3xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-6 sm:mb-8 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                <span class="text-4xl sm:text-5xl drop-shadow-[0_0_15px_rgba(16,185,129,0.5)]">💎</span>
            </div>
            <h4 class="text-2xl sm:text-3xl font-serif font-bold text-white mb-3 sm:mb-4">Kristall-Kollaps 3D</h4>
            <p class="text-gray-400 text-sm sm:text-base leading-relaxed mb-6 sm:mb-8 flex-1">
                Kettenreaktionen, Nova-Sphären und Zeit-Kristalle. Wische oder Tausche strategisch klug auf dem 8x8 Feld, um schneller an Rabatte zu kommen!
            </p>
            @if($currentEnergy > 0)
                <button type="button" @click="activeGame = 'kristall'" class="w-full py-4 sm:py-5 bg-gray-950 text-emerald-500 border border-emerald-500/30 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest group-hover:bg-emerald-500 group-hover:text-gray-900 transition-all shadow-inner">
                    Öffnen
                </button>
            @else
                <div class="w-full flex flex-col items-center">
                    <button type="button" disabled class="w-full py-4 sm:py-5 bg-gray-950 text-gray-600 border border-gray-800 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest cursor-not-allowed flex justify-center items-center gap-2">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        0 Energie
                    </button>
                    <p class="text-[9px] sm:text-[10px] text-red-500/80 uppercase font-bold tracking-widest mt-2">
                        Aufladung {{ $timeUntilNextEnergy }}
                    </p>
                </div>
            @endif
        </div>

        <div @if($currentEnergy > 0) @click="activeGame = 'funkenflug'" @endif class="group relative bg-gray-900 rounded-[2rem] sm:rounded-[2.5rem] border border-gray-800 p-6 sm:p-8 hover:border-amber-500/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(245,158,11,0.15)] flex flex-col {{ $currentEnergy > 0 ? 'cursor-pointer' : '' }} overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl sm:rounded-3xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-6 sm:mb-8 group-hover:scale-110 transition-transform duration-500 shadow-inner">
                <span class="text-4xl sm:text-5xl drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">🚀</span>
            </div>
            <h4 class="text-2xl sm:text-3xl font-serif font-bold text-white mb-3 sm:mb-4">Funkenflug-Express</h4>
            <p class="text-gray-400 text-sm sm:text-base leading-relaxed mb-6 sm:mb-8 flex-1">
                Navigiere deine Funki-Rakete durchs All! Weiche Asteroiden aus, sammele Funken und erziele astronomische Distanzrekorde.
            </p>
            @if($currentEnergy > 0)
                <button type="button" @click="activeGame = 'funkenflug'" class="w-full py-4 sm:py-5 bg-gray-950 text-amber-500 border border-amber-500/30 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest group-hover:bg-amber-500 group-hover:text-gray-900 transition-all shadow-inner">
                    Öffnen
                </button>
            @else
                <div class="w-full flex flex-col items-center">
                    <button type="button" disabled class="w-full py-4 sm:py-5 bg-gray-950 text-gray-600 border border-gray-800 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest cursor-not-allowed flex justify-center items-center gap-2">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        0 Energie
                    </button>
                    <p class="text-[9px] sm:text-[10px] text-red-500/80 uppercase font-bold tracking-widest mt-2">
                        Aufladung {{ $timeUntilNextEnergy }}
                    </p>
                </div>
            @endif
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
                <input type="range" id="kristall-bgm-vol" name="bgmVolume" aria-label="Lautstärke" min="0" max="100" step="1" x-model="bgmVolumeUi" class="volume-slider w-full sm:w-32">
                <span class="text-xs text-gray-500 font-bold w-8" x-text="bgmVolumeUi + '%'"></span>
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
                            </div>
                            <button type="button" @click="attemptStartGame()" 
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
                                    :class="energyWarning ? 'bg-red-600 text-white shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-emerald-500 text-gray-900 shadow-[0_0_40px_rgba(16,185,129,0.5)] hover:scale-105'">
                                <span x-show="!energyWarning">Spiel Starten</span>
                                <span x-show="energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    0 Energie!
                                </span>
                            </button>
                            <p class="text-emerald-400 mt-5 font-black text-xs sm:text-sm uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
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
                            <button type="button" @click="attemptStartGame()" 
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
                                    :class="energyWarning ? 'bg-red-600 text-white shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-emerald-500 text-gray-900 shadow-[0_0_40px_rgba(16,185,129,0.5)] hover:scale-105'">
                                <span x-show="!energyWarning">Spiel Starten</span>
                                <span x-show="energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    0 Energie!
                                </span>
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

    {{-- AKTIVES SPIEL: FUNKENFLUG-EXPRESS --}}
    <div x-show="activeGame === 'funkenflug'" x-cloak x-transition:enter="transition ease-out duration-500 delay-200" x-transition:enter-start="opacity-0 translate-y-10" x-transition:enter-end="opacity-100 translate-y-0" class="w-full max-w-[1400px] mx-auto flex-1 flex flex-col" x-data="funkenflugExpress()">

        {{-- AUDIO BGM FUNKENFLUG --}}
        <audio x-ref="ffBgmAudio" src="{{ asset('storage/gamification/music/funkenflug.mp3') }}" loop preload="auto"></audio>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 sm:gap-6 mb-6 sm:mb-8 bg-gray-900/80 p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-gray-800 shadow-lg">
            <div class="flex items-center gap-3 sm:gap-4 bg-gray-950 px-4 py-2.5 sm:px-5 sm:py-3 rounded-xl sm:rounded-2xl border border-gray-800 shadow-inner w-full sm:w-auto justify-center">
                <button type="button" @click="toggleMute()" class="text-gray-400 hover:text-amber-400 transition-colors focus:outline-none shrink-0">
                    <svg x-show="isBgmPlaying" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5 12h4l4-4v16l-4-4H5a2 2 0 01-2-2v-4a2 2 0 012-2z" /></svg>
                    <svg x-show="!isBgmPlaying" style="display: none;" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                </button>
                <input type="range" id="ff-bgm-vol" name="bgmVolume" aria-label="Lautstärke" min="0" max="100" step="1" x-model="bgmVolumeUi" class="volume-slider w-full sm:w-32">
                <span class="text-xs text-amber-500/50 font-bold w-8" x-text="bgmVolumeUi + '%'"></span>
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
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-40 h-40 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="relative z-10 w-full mb-4 sm:mb-6">
                            <p class="text-gray-500 text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2">Distanz (Punkte)</p>
                            <h4 class="text-5xl sm:text-6xl font-serif font-bold text-amber-500 drop-shadow-[0_0_15px_rgba(245,158,11,0.4)]" x-text="distance"></h4>
                            
                            <div class="mt-4 flex flex-col gap-2">
                                <div class="inline-flex items-center gap-2 bg-gray-950 px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl border border-gray-800 shadow-inner justify-center">
                                    <span class="text-sm sm:text-base">✨</span>
                                    <span class="text-xs sm:text-sm font-bold text-primary">Gesammelt: <span x-text="funkenCollected"></span> Funken</span>
                                </div>
                            </div>
                        </div>

                        <div class="w-full h-px bg-gray-800 relative z-10 my-2"></div>

                        <div class="relative z-10 w-full mt-4 sm:mt-6">
                            <p class="text-gray-500 text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2">Schild Energie</p>
                            <div class="w-full bg-gray-800 rounded-full h-4 sm:h-6 overflow-hidden border border-gray-700 relative">
                                <div class="bg-gradient-to-r from-blue-600 to-cyan-400 h-full transition-all duration-300" :style="`width: ${shieldEnergy}%`"></div>
                                <div class="absolute inset-0 flex items-center justify-center text-[10px] sm:text-xs font-black text-white drop-shadow-md">
                                    <span x-text="shieldEnergy + '%'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- VISUELLES HANDBUCH INGAME --}}
                    <div class="hidden lg:flex bg-amber-500/5 border border-amber-500/20 p-5 sm:p-6 rounded-[2rem] text-amber-300 text-xs sm:text-sm leading-relaxed flex-col gap-4 shadow-inner">
                        <strong class="text-amber-400 uppercase tracking-[0.2em] font-black text-[10px] border-b border-amber-500/20 pb-3 block w-full">Mission Briefing</strong>
                        <ul class="space-y-4">
                            <li class="flex gap-3 items-start">
                                <span><strong>Steuerung:</strong> Maus/Touch für Bewegung. Mausklick=Schießen. Mobile: Touch/Drag, Auto-Fire.</span>
                            </li>
                            <li class="flex gap-3 items-start">
                                <span><strong>ESC / Pause:</strong> Spiel jederzeit pausieren.</span>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">🔥</span>
                                <div><strong>[W] Multishoot:</strong> Rakete rollt sich und schießt 3x schneller.</div>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">⚡</span>
                                <div><strong>[A] Teleport:</strong> Slow-Mo, tippe/klicke auf den Screen zum Ausweichen.</div>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">🛡️</span>
                                <div><strong>[S] Schild:</strong> Schutzfeld für 20s oder bis die Energie zerschossen wird.</div>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">⭐</span>
                                <div><strong>[D] Ultimate (Auto-Aim):</strong> 10s vernichtende Drohnen.</div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="lg:col-span-8 xl:col-span-8 w-full flex justify-center items-center relative z-10">
                    <div class="w-full max-w-[800px] aspect-[3/4] sm:aspect-square relative rounded-[2rem] sm:rounded-[3rem] bg-gray-900 border-2 border-gray-700 shadow-[0_20px_50px_rgba(0,0,0,0.6)] overflow-hidden flex flex-col touch-none">
                        
                        <div id="funkenflug-container" x-ref="ffContainer" wire:ignore class="absolute inset-0 z-10 touch-none"></div>
                        <div id="ff-floating-layer" class="absolute inset-0 pointer-events-none z-30 overflow-hidden"></div>

                        {{-- UI SAFE ZONE FOR FULLSCREEN --}}
                        <div class="absolute inset-0 pointer-events-none z-40 w-full h-full flex justify-center">
                            <div class="w-full h-full max-w-[800px] relative">
                                
                                {{-- TOP RIGHT CONTROLS --}}
                                <div class="absolute top-4 right-4 z-50 flex flex-col gap-2 pointer-events-auto">
                            {{-- PAUSE BUTTON --}}
                            <button type="button" @click="togglePause()" x-show="gameState === 'playing'" class="text-gray-400 hover:text-amber-400 bg-gray-950/80 px-3 py-2 sm:px-4 sm:py-3 rounded-xl border border-gray-800 shadow-lg transition-colors flex items-center justify-center gap-2">
                                <svg x-show="!isPaused" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <svg x-show="isPaused" style="display: none;" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest hidden sm:block">Pause (ESC)</span>
                            </button>
                            {{-- FULLSCREEN BUTTON --}}
                            <button type="button" @click="toggleFullscreen()" class="text-gray-400 hover:text-amber-400 bg-gray-950/80 px-3 py-2 sm:px-4 sm:py-3 rounded-xl border border-gray-800 shadow-lg transition-colors hidden lg:flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest hidden xl:block">Vollbild (V)</span>
                            </button>
                                </div>

                                {{-- TOP LEFT HIGHSCORES --}}
                                <div class="absolute top-4 left-4 sm:top-6 sm:left-6 z-40 flex flex-col items-start gap-1 text-[8px] sm:text-[10px] uppercase font-bold tracking-widest text-left pointer-events-auto" x-show="gameState === 'playing'">
                            <span class="text-amber-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Dein Highscore: {{ $personalHighscoreFF }}</span>
                            <span class="text-indigo-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Globaler Highscore: {{ $globalHighscoreFF }}</span>
                                </div>

                                {{-- SKILL BUTTONS (Mobile & Desktop Overlay) --}}
                                <div class="absolute bottom-4 left-4 sm:bottom-6 sm:left-6 z-40 flex flex-col items-center gap-2 origin-bottom-left scale-[0.45] sm:scale-[0.55] opacity-70 hover:opacity-100 transition-opacity pointer-events-auto" x-show="gameState === 'playing'">
                            <!-- W Button -->
                            <div class="flex justify-center w-full">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(1)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-red-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[0], 'bg-red-900/80 border-red-500': !skillFlash[0], 'opacity-50 grayscale pointer-events-none': (skillLevels[0] === 0 || skillCooldowns[0] > 0) && !skillFlash[0]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">🔥</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[0] / 30) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">W</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-red-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Multishoot</span>
                                </div>
                            </div>

                            <!-- ASD Buttons -->
                            <div class="flex justify-center gap-2 sm:gap-4 w-full">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(2)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-purple-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[1], 'bg-purple-900/80 border-purple-500': !skillFlash[1], 'opacity-50 grayscale pointer-events-none': (skillLevels[1] === 0 || skillCooldowns[1] > 0) && !skillFlash[1]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">⚡</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[1] / 15) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">A</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-purple-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Teleport</span>
                                </div>

                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(3)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-blue-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[2], 'bg-blue-900/80 border-blue-500': !skillFlash[2], 'opacity-50 grayscale pointer-events-none': (skillLevels[2] === 0 || skillCooldowns[2] > 0) && !skillFlash[2]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">🛡️</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[2] / 20) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">S</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-blue-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Schild</span>
                                </div>

                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(4)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-yellow-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[3], 'bg-yellow-900/80 border-yellow-500': !skillFlash[3], 'opacity-50 grayscale pointer-events-none': (skillLevels[3] === 0 || skillCooldowns[3] > 0) && !skillFlash[3]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">⭐</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[3] / 60) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">D</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-yellow-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Ultimate</span>
                                </div>
                            </div>
                        </div>

                        {{-- PAUSE OVERLAY --}}
                        <div x-show="isPaused && gameState === 'playing'" x-cloak x-transition.opacity class="absolute inset-0 z-50 bg-gray-950/80 backdrop-blur-sm flex flex-col items-center justify-center p-6 text-center pointer-events-auto">
                            <h3 class="text-5xl sm:text-6xl font-serif font-bold text-gray-300 mb-8 drop-shadow-md">Pausiert</h3>
                            <button type="button" @click="resumeGame()" class="w-full max-w-sm py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all bg-amber-500 text-gray-900 shadow-[0_0_40px_rgba(245,158,11,0.5)] hover:scale-105">
                                Weiterspielen (ESC)
                            </button>
                            <button type="button" @click="quitGame()" class="mt-4 text-gray-500 hover:text-red-400 font-bold text-xs uppercase tracking-widest transition-colors mb-4">
                                Spiel abbrechen
                            </button>
                            
                            {{-- FULLSCREEN TOGGLE IN PAUSE --}}
                            <button type="button" @click="toggleFullscreen()" class="mt-6 text-gray-400 hover:text-amber-400 bg-gray-900 border border-gray-700 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2 hidden lg:flex">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                Vollbild umschalten (V)
                            </button>
                        </div>
                        <!-- Close Safe Zone -->
                            </div>
                        </div>


                        <div x-show="gameState === 'gameover'" x-cloak x-transition.opacity class="absolute inset-0 z-40 bg-gray-950/85 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center pointer-events-auto">
                            <h3 class="text-5xl sm:text-6xl font-serif font-bold text-amber-500 mb-4 drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">Zerstört!</h3>
                            <div class="inline-flex flex-col items-center gap-2 bg-gray-900 px-6 py-4 rounded-2xl border border-gray-800 shadow-inner mb-8 w-full max-w-sm">
                                <span class="text-gray-400 uppercase font-black text-xs tracking-widest border-b border-gray-800 pb-2 w-full text-center">Dein Run</span>
                                <div class="flex justify-between w-full mt-2">
                                    <span class="text-gray-400 uppercase font-bold text-xs">Distanz:</span>
                                    <span class="text-amber-500 font-bold" x-text="distance"></span>
                                </div>
                                <div class="flex justify-between w-full mt-1">
                                    <span class="text-gray-400 uppercase font-bold text-xs">Funken ergattert:</span>
                                </div>
                            </div>
                            <button type="button" @click="attemptStartGame()" tabindex="0"
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
                                    :class="energyWarning ? 'bg-red-600 text-white shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-amber-500 text-gray-900 shadow-[0_0_40px_rgba(245,158,11,0.5)] hover:scale-105'">
                                <span x-show="!energyWarning">Erneut Fliegen</span>
                                <span x-show="energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    0 Energie!
                                </span>
                            </button>
                            <p class="text-amber-500 mt-5 font-black text-xs sm:text-sm uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="gameState === 'ready'" x-cloak x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 z-50 flex items-center justify-center p-4 sm:p-6 pb-20">

                <div class="bg-gray-900/95 backdrop-blur-2xl border border-gray-700 shadow-[0_0_100px_rgba(0,0,0,0.8)] rounded-[2rem] sm:rounded-[3rem] w-full max-w-5xl p-6 sm:p-10 lg:p-12 relative flex flex-col lg:flex-row gap-8 lg:gap-12 items-center lg:items-stretch overflow-hidden">

                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-[80px] pointer-events-none"></div>

                    {{-- VISUELLES HANDBUCH START-MODAL --}}
                    <div class="flex-1 bg-gray-950/60 rounded-3xl p-6 sm:p-8 border border-gray-800 w-full flex flex-col justify-center shadow-inner relative z-10">
                         <h3 class="text-amber-400 font-black uppercase tracking-[0.2em] text-xs sm:text-sm flex items-center gap-3 border-b border-amber-500/20 pb-4 mb-6">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            Mission Briefing
                        </h3>
                        <ul class="space-y-4 sm:space-y-5 text-gray-300 text-xs sm:text-sm leading-relaxed">
                            <li class="flex gap-4 items-start">
                                <span><strong>Bullet-Hell Action:</strong> Weiche aus und vernichte Asteroiden.</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <span><strong>Energie:</strong> Schild ist deine HP. Wenn es bei 0 ist, zerstört dich der nächste Treffer!</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <span><strong>Funken:</strong> Sammele leuchtende Orbs auf, sie füllen den Punktestand direkt auf!</span>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">🔥</span>
                                <div><strong>[W] Multishoot:</strong> Rakete rollt sich und schießt 3x schneller.</div>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">⚡</span>
                                <div><strong>[A] Teleport:</strong> Slow-Mo, tippe/klicke auf den Screen zum Ausweichen.</div>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">🛡️</span>
                                <div><strong>[S] Schild:</strong> Schutzfeld für 20s oder bis die Energie zerschossen wird.</div>
                            </li>
                            <li class="flex gap-3 items-center">
                                <span class="text-xl">⭐</span>
                                <div><strong>[D] Ultimate (Auto-Aim):</strong> 10s vernichtende Drohnen.</div>
                            </li>
                        </ul>
                    </div>

                    <div class="flex-1 flex flex-col justify-center items-center w-full relative z-10">

                        <div class="flex gap-4 sm:gap-6 mb-8 w-full justify-center">
                            <div class="bg-gray-950 border border-gray-800 rounded-2xl p-4 sm:p-6 text-center flex-1 shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">Schild</p>
                                <p class="text-3xl sm:text-4xl font-serif font-bold text-white">100%</p>
                            </div>
                            <div class="bg-gray-950 border border-gray-800 rounded-2xl p-4 sm:p-6 text-center flex-1 shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 font-black uppercase tracking-widest mb-2">Letzte Funken</p>
                                <p class="text-3xl sm:text-4xl font-serif font-bold text-amber-500 drop-shadow-[0_0_10px_rgba(245,158,11,0.3)]" x-text="funkenCollected">0</p>
                            </div>
                        </div>

                        <div class="relative w-full mt-2">
                            <button type="button" @click="attemptStartGame()" tabindex="0"
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
                                    :class="energyWarning ? 'bg-red-600 text-white shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-amber-500 text-gray-900 shadow-[0_0_40px_rgba(245,158,11,0.5)] hover:scale-105'">
                                <span x-show="!energyWarning">Flug Starten</span>
                                <span x-show="energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    0 Energie!
                                </span>
                            </button>
                            <div class="flex justify-between items-center mt-5 px-2">
                                <p class="text-amber-500 font-black text-[10px] sm:text-xs uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
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
