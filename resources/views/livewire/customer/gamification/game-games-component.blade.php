<div class="p-4 sm:p-6 lg:p-10 min-h-full flex flex-col relative z-10" x-data="kristallKollaps3D()">
    @if(!$hasOptedIn)
        <div x-data="optInScreen()" :class="isWarping ? 'opacity-0 scale-95 blur-sm' : 'opacity-100 scale-100 blur-0'" class="max-w-6xl mx-auto relative p-6 sm:p-10 lg:p-20 flex flex-col lg:flex-row items-center gap-10 lg:gap-16 transition-all duration-[1500ms] ease-in-out mt-8 lg:mt-12">
            <template x-teleport="body">
                <div x-show="isActivating" style="display: none;" class="fixed inset-0 z-[9000] pointer-events-none flex items-center justify-center overflow-hidden">
                    <div x-show="isActivating" x-transition:enter="transition ease-out duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0 bg-gray-950/95 backdrop-blur-xl"></div>
                    <div x-show="phase >= 1" x-transition:enter="transition ease-out duration-[1500ms]" x-transition:enter-start="scale-0 opacity-100" x-transition:enter-end="scale-[3] opacity-0" class="absolute w-[20rem] h-[20rem] md:w-[30rem] md:h-[30rem] border-[8px] border-primary rounded-full blur-[4px]"></div>
                    <div x-show="phase >= 1" x-transition:enter="transition ease-out duration-[1000ms] delay-100" x-transition:enter-start="scale-0 opacity-100" x-transition:enter-end="scale-[4] opacity-0" class="absolute w-[20rem] h-[20rem] md:w-[30rem] md:h-[30rem] border-[4px] border-white rounded-full"></div>
                    <div x-show="phase >= 1" class="relative z-10 w-32 h-32 md:w-48 md:h-48 rounded-full bg-primary/30 blur-2xl animate-pulse"></div>
                </div>
            </template>
            <div class="relative z-10 flex-1 text-center lg:text-left">
                @if(isset($profileSteps) && count($profileSteps) > 0)
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
                
                <div class="mb-6 flex flex-col gap-2 max-w-xl mx-auto lg:mx-0 text-left">
                    <label class="flex items-start gap-3 cursor-pointer group">
                        <div class="relative flex items-center justify-center shrink-0 mt-0.5">
                            <input type="checkbox" x-model="agreedToTerms" class="peer appearance-none w-5 h-5 border-2 border-gray-600 rounded bg-gray-900 checked:bg-primary checked:border-primary transition-all cursor-pointer">
                            <svg class="absolute w-3 h-3 text-gray-900 opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-xs sm:text-sm text-gray-400 group-hover:text-gray-300 transition-colors leading-relaxed">
                            Ich habe die <a href="{{ route('agb') }}#agb-gamification" target="_blank" @click.stop class="text-primary hover:underline hover:text-amber-400 relative z-20">Spielregeln & Teilnahmebedingungen</a> gelesen und stimme diesen zu. Zudem nehme ich die <a href="{{ route('datenschutz') }}#datenschutz-gamification" target="_blank" @click.stop class="text-primary hover:underline hover:text-amber-400 relative z-20">Datenschutzhinweise</a> bezüglich der Speicherung meines Spielfortschritts zur Kenntnis.
                        </span>
                    </label>
                </div>

                <button :disabled="!agreedToTerms" @click="if(agreedToTerms) triggerEpicStart()" class="w-full sm:w-auto group relative px-8 sm:px-12 py-4 sm:py-5 bg-gradient-to-r from-primary to-primary-dark text-gray-900 rounded-xl font-black uppercase tracking-widest text-xs sm:text-sm shadow-[0_0_40px_rgba(197,160,89,0.5)] transition-all flex items-center justify-center gap-4 overflow-hidden transform-gpu mx-auto lg:mx-0 disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed hover:scale-105 hover:shadow-[0_0_60px_rgba(197,160,89,0.8)] disabled:hover:scale-100 disabled:hover:shadow-[0_0_40px_rgba(197,160,89,0.5)]">
                    <div x-show="agreedToTerms" class="absolute inset-0 bg-white/30 transform -skew-x-12 -translate-x-[150%] group-hover:translate-x-[150%] transition-transform duration-1000 ease-in-out"></div>
                    <span>Magie aktivieren</span>
                    <svg class="w-5 h-5 group-hover:translate-x-2 group-hover:scale-110 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
            <div class="relative z-10 w-full lg:w-5/12 flex justify-center perspective-1000 mt-8 lg:mt-0 shrink-0">
                <div class="relative w-56 h-56 sm:w-72 sm:h-72 md:w-96 md:h-96 transform-gpu hover:rotate-y-12 hover:rotate-x-12 transition-transform duration-700 ease-out shrink-0">
                    <div class="absolute inset-0 bg-primary/20 rounded-full blur-[40px] md:blur-[50px] animate-pulse"></div>
                    <img src="{{asset('shop/customer/gamification/models/images/original/funki_lvl_5_apprentice.png')}}" draggable="false" class="relative w-full h-full object-contain drop-shadow-[0_20px_30px_rgba(0,0,0,0.8)] animate-[float_6s_ease-in-out_infinite] pointer-events-none select-none z-10">
                </div>
            </div>
        </div>

    <script>
        window.optInScreen = function() {
            return {
                agreedToTerms: false,
                isWarping: false,
                isActivating: false,
                phase: 0,
                triggerEpicStart() {
                    this.isWarping = true;
                    this.isActivating = true;
                    window.dispatchEvent(new CustomEvent('warp-started'));
                    setTimeout(() => { this.phase = 1; }, 50);
                    setTimeout(() => {
                        window.sessionStorage.setItem('funki_just_activated', 'true');
                        this.$wire.optIn();
                    }, 2500);
                }
            };
        };
        </script>

    @else


    {{-- AUDIO BGM --}}
    <audio x-ref="bgmAudio" src="{{ asset('shop/customer/gamification/music/cristall_kollaps_bg_music.mp3') }}" loop preload="auto"></audio>

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
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        0 Energie
                    </button>
                    <p class="text-[9px] sm:text-[10px] text-red-500/80 uppercase font-bold tracking-widest mt-2">
                        Aufladung in ca. {{ is_numeric($timeUntilNextEnergy) ? round($timeUntilNextEnergy) : $timeUntilNextEnergy }} Minuten
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
                        Aufladung in ca. {{ is_numeric($timeUntilNextEnergy) ? round($timeUntilNextEnergy) : $timeUntilNextEnergy }} Minuten
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
                            
                            {{-- FULLSCREEN TOGGLE --}}
                            <button x-show="isFullscreen" type="button" @click="toggleFullscreen()" class="absolute top-4 right-4 text-gray-400 hover:text-emerald-400 bg-gray-900 border border-gray-700 p-2 sm:px-4 sm:py-2 rounded-xl text-[10px] sm:text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2 transition-colors z-50 shadow-lg">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                <span class="hidden sm:block">Vollbild (V)</span>
                            </button>

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

                <div class="bg-gray-900/95 backdrop-blur-2xl border border-gray-700 shadow-[0_0_100px_rgba(0,0,0,0.8)] rounded-[2rem] sm:rounded-[3rem] w-full max-w-5xl p-6 sm:p-10 lg:p-12 relative flex flex-col lg:flex-row gap-6 lg:gap-12 items-center lg:items-stretch overflow-y-auto max-h-full">

                    <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/10 rounded-full blur-[80px] pointer-events-none"></div>

                    {{-- VISUELLES HANDBUCH START-MODAL --}}
                    <div class="flex-1 bg-gray-950/60 rounded-3xl p-6 sm:p-8 border border-gray-800 w-full flex flex-col justify-center shadow-inner relative z-10">
                        <div class="flex items-center justify-between border-b border-blue-500/20 pb-4 mb-6">
                            <h3 class="text-blue-400 font-black uppercase tracking-[0.2em] text-xs sm:text-sm flex items-center gap-3">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                Das Handbuch
                            </h3>
                        </div>
                        <ul class="space-y-4 sm:space-y-5 text-gray-300 text-xs sm:text-sm leading-relaxed max-h-[35vh] sm:max-h-none overflow-y-auto pr-2">
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
        <audio x-ref="ffBgmAudio" src="{{ asset('shop/customer/gamification/music/funkenflug.mp3') }}" loop preload="auto"></audio>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-6 mb-2 sm:mb-4 bg-gray-900/80 p-3 sm:p-6 rounded-2xl sm:rounded-3xl border border-gray-800 shadow-lg">
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

                        <div class="relative z-10 w-full mb-3 sm:mb-4">
                            <p class="text-gray-500 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mb-1">Distanz (Punkte)</p>
                            <h4 class="text-4xl sm:text-5xl font-serif font-bold text-amber-500 drop-shadow-[0_0_15px_rgba(245,158,11,0.4)]" x-text="distance"></h4>
                            
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
                        <ul class="space-y-3">
                            <li class="flex gap-2 items-start text-[11px] sm:text-xs">
                                <span><strong>Steuerung:</strong> Maus/Touch Bewegung. Klick=Schießen.</span>
                            </li>
                            <li class="flex gap-2 items-start text-[11px] sm:text-xs">
                                <div class="w-3 h-3 shrink-0 bg-red-500 mt-1 shadow-md"></div>
                                <span><strong>Normal:</strong> Rote Meteore (1 HP)</span>
                            </li>
                            <li class="flex gap-2 items-start text-[11px] sm:text-xs">
                                <div class="w-3 h-3 shrink-0 bg-purple-500 mt-1 shadow-md"></div>
                                <span><strong>Panzer:</strong> Lila Elite-Scherben (Viel HP)</span>
                            </li>
                            <li class="flex gap-2 items-start text-[11px] sm:text-xs">
                                <div class="w-3 h-3 rounded-full shrink-0 bg-yellow-400 mt-1 shadow-md"></div>
                                <span class="text-yellow-600"><strong>Gelb:</strong> Extrapunkte</span>
                                <div class="w-3 h-3 rounded-full shrink-0 bg-blue-400 mt-1 ml-2 shadow-md"></div>
                                <span class="text-blue-500"><strong>Blau:</strong> -5s Skill-CD</span>
                            </li>
                            <li class="flex gap-2 items-center text-[11px] sm:text-xs pt-1 border-t border-amber-500/10">
                                <span class="text-lg">🔥</span> <strong>[W]</strong> Multishoot (3x Hit)
                            </li>
                            <li class="flex gap-2 items-center text-[11px] sm:text-xs">
                                <span class="text-lg">⚡</span> <strong>[A]</strong> Teleport (Slow-Mo Touch)
                            </li>
                            <li class="flex gap-2 items-center text-[11px] sm:text-xs">
                                <span class="text-lg">🛡️</span> <strong>[S]</strong> Schild (Schutzfeld)
                            </li>
                            <li class="flex gap-2 items-center text-[11px] sm:text-xs">
                                <span class="text-lg">⭐</span> <strong>[D]</strong> Ultimate (Auto-Aim Drohnen)
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

                <div class="lg:col-span-8 xl:col-span-8 w-full flex justify-center items-center relative z-10 w-full h-full">
                    <div id="ff-main-wrapper" :class="{'h-[100dvh] sm:h-[100dvh] aspect-auto rounded-none border-0': isFullscreen, 'aspect-[3/4] sm:aspect-square rounded-[2rem] sm:rounded-[3rem]': !isFullscreen}" class="overflow-hidden w-full max-w-[800px] relative bg-gray-950 sm:bg-gray-900 border-2 border-gray-700 shadow-[0_20px_50px_rgba(0,0,0,0.6)] flex flex-col pointer-events-auto">
                        
                        {{-- SCREEN AREA (65% on mobile, 100% on desktop) --}}
                        <div class="relative w-full h-[65%] sm:h-full shrink-0 flex flex-col bg-gray-900 border-b-2 sm:border-b-0 border-gray-800 touch-none">

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
                            <button type="button" @click="toggleFullscreen()" class="text-gray-400 hover:text-amber-400 bg-gray-950/80 px-3 py-2 sm:px-4 sm:py-3 rounded-xl border border-gray-800 shadow-lg transition-colors flex items-center justify-center gap-2">
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
                                <div class="absolute bottom-4 left-4 sm:bottom-6 sm:left-6 z-40 hidden sm:flex flex-col items-center gap-2 origin-bottom-left scale-[0.7] sm:scale-[0.55] opacity-70 hover:opacity-100 transition-opacity pointer-events-auto" x-show="gameState === 'playing'">
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
                            <button type="button" @click="toggleFullscreen()" class="mt-6 text-gray-400 hover:text-amber-400 bg-gray-900 border border-gray-700 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2">
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
                            
                            {{-- FULLSCREEN TOGGLE --}}
                            <button x-show="isFullscreen" type="button" @click="toggleFullscreen()" class="absolute top-4 right-4 text-gray-400 hover:text-amber-400 bg-gray-900 border border-gray-700 p-2 sm:px-4 sm:py-2 rounded-xl text-[10px] sm:text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2 transition-colors z-50 shadow-lg">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                <span class="hidden sm:block">Vollbild (V)</span>
                            </button>                                

                            <h3 class="text-5xl sm:text-6xl font-serif font-bold text-amber-500 mb-4 drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">Zerstört!</h3>
                            <div class="inline-flex flex-col items-center gap-2 bg-gray-900 px-6 py-4 rounded-2xl border border-gray-800 shadow-inner mb-8 w-full max-w-sm">
                                <span class="text-gray-400 uppercase font-black text-xs tracking-widest border-b border-gray-800 pb-2 w-full text-center">Dein Run</span>
                                <div class="flex justify-between w-full mt-2">
                                    <span class="text-gray-400 uppercase font-bold text-xs">Distanz:</span>
                                    <span class="text-amber-500 font-bold" x-text="distance"></span>
                                </div>
                                <div class="flex justify-between w-full mt-1">
                                    <span class="text-gray-400 uppercase font-bold text-xs">Funken ergattert:</span>
                                    <span class="text-amber-500 font-bold" x-text="funkenCollected"></span>
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
                        
                        </div> <!-- End Screen Area Wrapper -->
                        
                        {{-- GAMEBOY CONTROLLER (MOBILE ONLY) --}}
                        <div class="w-full flex-1 bg-gray-950 flex sm:hidden flex-row items-center justify-between px-2 sm:px-4 pb-4 pt-2 relative z-50 shadow-[0_-10px_30px_rgba(0,0,0,0.5)] pointer-events-auto border-t-2 border-gray-800" x-show="gameState === 'playing'">
                            
                            {{-- LEFT SIDE: Skills Grid --}}
                            <div class="flex flex-wrap items-center gap-2 w-1/2 h-full justify-start pl-2 pt-2">
                                <button @click="useSkill(1)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-red-100 font-black relative overflow-hidden" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[0], 'bg-red-900 border-red-500': !skillFlash[0], 'opacity-40 grayscale pointer-events-none': (skillLevels[0] === 0 || skillCooldowns[0] > 0) && !skillFlash[0]}">
                                    <span class="text-xl z-10 drop-shadow-md">🔥</span>
                                    <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[0] / 30) * 100}%`"></div>
                                </button>
                                <button @click="useSkill(2)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-purple-100 font-black relative overflow-hidden" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[1], 'bg-purple-900 border-purple-500': !skillFlash[1], 'opacity-40 grayscale pointer-events-none': (skillLevels[1] === 0 || skillCooldowns[1] > 0) && !skillFlash[1]}">
                                    <span class="text-xl z-10 drop-shadow-md">⚡</span>
                                    <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[1] / 15) * 100}%`"></div>
                                </button>
                                <button @click="useSkill(3)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-blue-100 font-black relative overflow-hidden" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[2], 'bg-blue-900 border-blue-500': !skillFlash[2], 'opacity-40 grayscale pointer-events-none': (skillLevels[2] === 0 || skillCooldowns[2] > 0) && !skillFlash[2]}">
                                    <span class="text-xl z-10 drop-shadow-md">🛡️</span>
                                    <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[2] / 20) * 100}%`"></div>
                                </button>
                                <button @click="useSkill(4)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-yellow-100 font-black relative overflow-hidden" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[3], 'bg-yellow-900 border-yellow-500': !skillFlash[3], 'opacity-40 grayscale pointer-events-none': (skillLevels[3] === 0 || skillCooldowns[3] > 0) && !skillFlash[3]}">
                                    <span class="text-xl z-10 drop-shadow-md">⭐</span>
                                    <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[3] / 60) * 100}%`"></div>
                                </button>
                            </div>

                            {{-- RIGHT SIDE: Joystick Zone --}}
                            <div class="w-1/2 h-full flex items-center justify-end pr-4 sm:pr-6 pointer-events-auto">
                                <div id="ff-joystick-zone" class="w-[90px] h-[90px] rounded-full bg-gray-900 border-4 border-gray-800 shadow-[inset_0_10px_20px_rgba(0,0,0,1)] relative touch-none flex items-center justify-center overflow-hidden">
                                    <div id="ff-joystick-knob" class="w-12 h-12 rounded-full bg-gradient-to-tr from-amber-600 to-yellow-400 border-[3px] border-yellow-200 shadow-[0_5px_15px_rgba(245,158,11,0.6)] absolute pointer-events-none transform transition-transform duration-75"></div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div x-show="gameState === 'ready'" x-cloak x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 z-50 flex items-center justify-center p-4 sm:p-6 pb-20">

                <div class="bg-gray-900/95 backdrop-blur-2xl border border-gray-700 shadow-[0_0_100px_rgba(0,0,0,0.8)] rounded-[2rem] sm:rounded-[3rem] w-full max-w-5xl p-6 sm:p-10 lg:p-12 relative flex flex-col lg:flex-row gap-6 lg:gap-12 items-center lg:items-stretch overflow-y-auto max-h-full">

                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-[80px] pointer-events-none"></div>

                    {{-- VISUELLES HANDBUCH START-MODAL --}}
                    <div class="flex-1 bg-gray-950/60 rounded-3xl p-6 sm:p-8 border border-gray-800 w-full flex flex-col justify-center shadow-inner relative z-10">
                         <h3 class="text-amber-400 font-black uppercase tracking-[0.2em] text-xs sm:text-sm flex items-center gap-3 border-b border-amber-500/20 pb-4 mb-6">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            Mission Briefing
                        </h3>
                        <ul class="space-y-4 sm:space-y-5 text-gray-300 text-xs sm:text-sm leading-relaxed max-h-[35vh] sm:max-h-none overflow-y-auto pr-2">
                            <li class="flex gap-4 items-start">
                                <span><strong>Action:</strong> Weiche feindlichen Objekten aus oder zerschieße sie! Schild ist dein Leben.</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-5 h-5 shrink-0 bg-red-500 border border-gray-600 rounded-sm mt-0.5 shadow-md"></div>
                                <span><strong>Rote Meteore:</strong> Normale Hindernisse. Weiche ihnen aus oder zerstöre sie.</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-5 h-5 shrink-0 bg-purple-500 border border-gray-600 rounded-sm mt-0.5 shadow-[0_0_10px_rgba(168,85,247,0.4)]"></div>
                                <span><strong>Lila Elite-Scherben:</strong> Sehr gefährlich. Brauchen deutlich mehr Treffer zum Zerstören und fliegen Zick-Zack!</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-5 h-5 shrink-0 bg-yellow-400 border border-yellow-200 rounded-full mt-0.5 shadow-[0_0_10px_rgba(250,204,21,0.5)]"></div>
                                <span><strong>Gelbe Funken:</strong> Sammle sie ein für extra Distanz & Ranking-Punkte!</span>
                            </li>
                            <li class="flex gap-4 items-start">
                                <div class="w-5 h-5 shrink-0 bg-blue-400 border border-blue-200 rounded-full mt-0.5 shadow-[0_0_10px_rgba(96,165,250,0.5)]"></div>
                                <span><strong>Blaue Sphären:</strong> Reduziert die Cooldown-Zeit (Wartezeit) all deiner Skills sofort um 5 Sekunden.</span>
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

<div class="w-full max-w-5xl mx-auto h-px bg-gradient-to-r from-transparent via-gray-800 to-transparent mb-16 sm:mb-20"></div>

                <div class="w-full flex flex-col items-center text-center mb-8">
                    <h3 class="text-2xl sm:text-3xl font-serif font-bold text-white mb-2">Deine Gamification Evolution</h3>
                    <p class="text-gray-400 text-sm max-w-2xl mx-auto">Betrachte deine Evolution, sammle Funken und schalte neue Erfolge frei.</p>
                </div>


                <div class="relative mb-36 sm:mb-44 flex flex-col items-center mt-8 w-full max-w-[18rem] sm:max-w-md md:max-w-lg lg:max-w-xl mx-auto">
                    @php
                        $megaRank = $titlesData['mega_title']['rank'] ?? 0;
                        $megaShadow = match(true) {
                            $megaRank == 1 => 'shadow-[0_0_20px_rgba(255,255,255,0.05)]',
                            $megaRank == 2 || $megaRank == 3 => 'shadow-[0_0_40px_rgba(59,130,246,0.2)] border-blue-500/30',
                            $megaRank == 4 || $megaRank == 5 => 'shadow-[0_0_60px_rgba(250,204,21,0.4)] border-amber-500/50',
                            $megaRank == 6 => 'shadow-[0_0_80px_rgba(34,211,238,0.6)] border-cyan-400 ring-4 ring-cyan-400/30',
                            default => 'shadow-[0_0_30px_rgba(0,0,0,0.8)]'
                        };
                        $levelBorder = match(true) {
                            $level < 4 => 'border-2 border-gray-800',
                            $level < 7 => 'border-[3px] border-gray-600',
                            $level < 10 => 'border-[3px] border-primary',
                            $level == 10 => 'border-[4px] border-amber-400',
                            default => 'border-2 border-gray-800'
                        };
                    @endphp

                    <button @click.stop="showTitlesModal=true" class="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-900 border border-primary px-6 py-2.5 sm:px-10 sm:py-3 rounded-full font-black uppercase tracking-widest text-[10px] sm:text-xs text-white shadow-[0_0_25px_rgba(197,160,89,0.6)] flex flex-col items-center gap-1 hover:bg-primary hover:text-gray-900 transition-all z-40 hover:scale-110 group cursor-pointer whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            {{$currentRankName}}
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 group-hover:rotate-90 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            </svg>
                        </div>
                    </button>

                    <div class="relative w-64 h-64 sm:w-80 sm:h-80 md:w-[26rem] md:h-[26rem] flex items-center justify-center shrink-0">
                        @if($megaRank >= 4)
                            <div class="absolute inset-[-4rem] bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-amber-400/20 via-transparent to-transparent blur-3xl pointer-events-none animate-pulse"></div>
                        @endif
                        @if($megaRank == 6)
                            <div class="absolute inset-[-6rem] bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-cyan-400/20 via-transparent to-transparent blur-3xl pointer-events-none animate-pulse duration-75"></div>
                        @endif
                        @if($level >= 4)
                            <div class="absolute inset-0 rounded-full border border-gray-600/50 animate-[spin_20s_linear_infinite] pointer-events-none"></div>
                        @endif
                        @if($level >= 7)
                            <div class="absolute inset-[-1rem] rounded-full border-[2px] border-primary/40 border-dashed animate-[spin_15s_linear_reverse_infinite] pointer-events-none"></div>
                        @endif
                        @if($level == 10)
                            <div class="absolute inset-[-2rem] rounded-full border-[3px] border-amber-400/60 border-dotted animate-[spin_10s_linear_infinite] pointer-events-none"></div>
                        @endif

                        <svg class="absolute inset-0 w-full h-full -rotate-90 drop-shadow-[0_0_20px_rgba(197,160,89,0.5)] pointer-events-none z-20" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#1f2937" stroke-width="1.5"></circle>
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#c5a059" stroke-width="3" stroke-linecap="round" stroke-dasharray="301.59" stroke-dashoffset="{{301.59 - ($progressPercentage / 100) * 301.59}}" class="transition-all duration-1000 ease-out"></circle>
                        </svg>

                        <button @click="open3DModal()" class="absolute inset-3 sm:inset-4 md:inset-5 rounded-full bg-gray-900 {{$levelBorder}} {{$megaShadow}} flex items-center justify-center overflow-hidden z-10 group transition-all duration-700 hover:scale-[1.02]">
                            <div class="absolute inset-0 bg-primary/10 rounded-full blur-[60px] md:blur-[80px] group-hover:bg-primary/30 transition-colors duration-700 pointer-events-none"></div>
                            <img :src="currentImagePath ? currentImagePath : '{{$imagePath}}'" src="{{$imagePath}}" class="w-full h-full object-contain p-4 sm:p-8 animate-subtle-float">
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-sm z-20 pointer-events-none">
                                <span class="bg-primary text-gray-900 px-6 py-3 md:px-8 md:py-4 rounded-xl font-black text-[10px] sm:text-xs uppercase tracking-widest shadow-2xl whitespace-nowrap">3D Modell öffnen</span>
                            </div>
                        </button>

                        <div class="absolute top-6 -left-6 sm:top-12 sm:-left-10 md:top-16 md:-left-12 z-30 bg-gray-900/95 backdrop-blur-md border border-blue-500/30 px-3 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-[0_0_20px_rgba(59,130,246,0.3)] flex items-center gap-2 sm:gap-3 hover:scale-105 transition-transform cursor-default">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-blue-500/20 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="text-left hidden sm:block">
                                <p class="text-[8px] sm:text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Energie</p>
                                <p class="text-white font-bold text-xs sm:text-sm mt-1 leading-none">{{$energyBalance}}/{{$maxEnergy}}</p>
                            </div>
                            <div class="text-left sm:hidden">
                                <p class="text-white font-bold text-xs leading-none">{{$energyBalance}}/{{$maxEnergy}}</p>
                            </div>
                        </div>

                        <div class="absolute top-6 -right-6 sm:top-12 sm:-right-10 md:top-16 md:-right-12 z-30 bg-gray-900/95 backdrop-blur-md border border-primary/30 px-3 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-[0_0_20px_rgba(197,160,89,0.3)] flex items-center gap-2 sm:gap-3 hover:scale-105 transition-transform cursor-default">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                                <span class="text-xs sm:text-sm">✨</span>
                            </div>
                            <div class="text-left hidden sm:block">
                                <p class="text-[8px] sm:text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">Guthaben</p>
                                <p class="text-white font-bold text-xs sm:text-sm mt-1 leading-none">{{$balance}} F.</p>
                            </div>
                            <div class="text-left sm:hidden">
                                <p class="text-white font-bold text-xs leading-none">{{$balance}} F.</p>
                            </div>
                        </div>

                        <div class="absolute -bottom-12 sm:-bottom-16 md:-bottom-20 left-1/2 -translate-x-1/2 z-30 flex flex-col items-center w-[130%] sm:w-[150%] md:w-max">
                            <div class="bg-gradient-to-r from-primary to-primary-dark text-gray-900 px-8 py-2.5 sm:px-12 sm:py-3 rounded-full font-black text-[10px] sm:text-sm uppercase tracking-[0.2em] shadow-[0_10px_20px_rgba(0,0,0,0.6)]">
                                Level {{$level}}
                            </div>
                            @if(!$isMaxLevel)
                                <div class="mt-3 sm:mt-4 w-full bg-gray-900/95 backdrop-blur-xl border border-gray-800 rounded-xl p-3 sm:p-4 text-center shadow-2xl">
                                    <p class="text-[9px] sm:text-xs font-black uppercase tracking-widest text-gray-400 whitespace-nowrap">
                                        <span class="text-primary">{{$progressPercentage}}%</span> - Noch {{$missingSparks}} Funken
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
                    <a href="{{route('customer.games')}}" class="w-full sm:w-auto px-6 py-4 sm:px-8 bg-emerald-500 text-gray-900 rounded-xl font-black text-xs sm:text-sm hover:bg-emerald-400 transition-all uppercase tracking-widest shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:-translate-y-1 flex items-center justify-center gap-2">
                        <span class="text-lg sm:text-xl">🎮</span> Jetzt spielen
                    </a>
                    <a href="{{route('customer.ranking')}}" class="w-full sm:w-auto px-6 py-4 sm:px-8 bg-gray-800 border border-amber-500/30 text-amber-400 rounded-xl font-black text-xs sm:text-sm hover:bg-amber-400 hover:text-gray-900 transition-all uppercase tracking-widest shadow-[0_0_20px_rgba(251,191,36,0.15)] hover:-translate-y-1 flex items-center justify-center gap-2">
                        <span class="text-lg sm:text-xl">🏆</span> Rangliste
                    </a>
                </div>

                {{-- ERKLÄRUNG GAMIFICATION --}}
                <div class="w-full max-w-4xl mx-auto mt-16 sm:mt-24 text-left animate-fade-in-up">
                    <div class="bg-gray-900/80 backdrop-blur-xl border border-primary/30 rounded-3xl p-6 sm:p-10 shadow-[0_0_30px_rgba(197,160,89,0.1)] relative overflow-hidden">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent pointer-events-none"></div>
                        <div class="relative z-10">
                            <h3 class="text-2xl sm:text-3xl font-serif font-bold text-white mb-4 flex items-center gap-3">
                                <span class="text-primary text-3xl">✨</span> Was passiert hier eigentlich?
                            </h3>
                            <div class="space-y-4 text-gray-400 text-sm sm:text-base leading-relaxed">
                                <p>
                                    Schön, dass du dabei bist! Dieser Bereich ist weit mehr als nur eine Kundenübersicht – es ist dein
                                    <strong class="text-white">persönliches Abenteuer</strong> in unserer Manufaktur der Magie. Wir glauben, dass Treue
                                    belohnt werden sollte, und zwar auf eine Art, die Spaß macht.
                                </p>
                                <p>
                                    Dein kleiner 3D-Gefährte (wir nennen ihn liebevoll "Funki") begleitet dich auf deiner Reise.
                                    Mit jeder Bestellung, jeder Interaktion und jedem gelösten Rätsel sammelst du <strong class="text-primary">Funken</strong>.
                                    Diese Funken lassen deinen Funki wachsen, neue Level erreichen und sich visuell weiterentwickeln!
                                </p>
                                <ul class="list-none space-y-3 mt-6 mb-6">
                                    <li class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span><strong class="text-white">Level-Ups & Belohnungen:</strong> Erreichst du bestimmte Meilensteine, schaltest du exklusive Gutscheine, echte Rabatte und Überraschungen frei.</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                                        <span><strong class="text-white">Titel & Ränge:</strong> Verdiene prestigeträchtige Titel und präsentiere sie in der globalen Rangliste. Zeig allen, dass du ein Meister der Manufaktur bist!</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span><strong class="text-white">Minispiele:</strong> Nutze deine gesammelte Energie, um in kleinen Spielen weitere Funken zu gewinnen oder einfach die Seele baumeln zu lassen.</span>
                                    </li>
                                </ul>
                                <p>
                                    Kurz gesagt: Jeder Einkauf bringt dich weiter. Schau regelmäßig vorbei, beobachte die Evolution deines Funkis und
                                    sichere dir die Schätze, die auf deinem Weg liegen. Viel Spaß beim Entdecken!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-full max-w-4xl mx-auto mt-16 sm:mt-24 text-left animate-fade-in-up">
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 border-b border-gray-800 pb-4 gap-2">
                        <h3 class="text-2xl sm:text-3xl font-serif font-bold text-white flex items-center gap-3">
                            <span class="text-primary drop-shadow-[0_0_10px_rgba(197,160,89,0.6)]">🎁</span> Pfad zur Legende
                        </h3>
                        <p class="text-[10px] sm:text-xs text-gray-500 font-black uppercase tracking-[0.2em]">Meilensteine & Belohnungen</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        @foreach($milestonesConfig as $mLevel => $reward)
                            @php
                                $isUnlocked = $level >= $mLevel;
                                $couponData = $unlockedCoupons['lvl_' . $mLevel] ?? null;
                                $code = $couponData['code'] ?? null;
                                $isUsed = $couponData['is_used'] ?? false;
                            @endphp
                            <div class="relative border rounded-2xl p-5 flex flex-col items-center text-center transition-all duration-500 overflow-hidden group {{ $isUsed ? 'bg-gray-950 border-red-900/30 opacity-70 shadow-inner' : ($isUnlocked ? 'bg-gray-900 border-primary/50 shadow-[0_0_25px_rgba(197,160,89,0.15)] hover:-translate-y-1' : 'bg-gray-900 border-gray-800 opacity-50 grayscale') }}">
                                @if($isUnlocked && !$isUsed)
                                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent pointer-events-none"></div>
                                @endif
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4 font-black text-lg z-10 transition-colors {{ $isUsed ? 'bg-gray-900 text-red-500/50 border border-red-900/30' : ($isUnlocked ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.5)]' : 'bg-gray-800 text-gray-500 border border-gray-700') }}">
                                    {{$mLevel}}
                                </div>
                                <h4 class="font-bold text-sm mb-1 z-10 transition-colors {{ $isUsed ? 'text-gray-600 line-through' : 'text-white' }}">{{$reward['name']}}</h4>
                                <p class="text-[10px] font-medium mb-4 z-10 {{ $isUsed ? 'text-gray-600' : 'text-gray-400' }}">Erreiche Level {{$mLevel}}</p>

                                <div class="w-full mt-auto z-10">
                                    @if($isUnlocked)
                                        @if($isUsed)
                                            <div class="bg-red-950/20 border border-red-900/50 rounded-lg p-2 text-center">
                                                <p class="text-red-500/80 font-black text-[10px] uppercase tracking-[0.2em] flex items-center justify-center gap-2">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Eingelöst
                                                </p>
                                            </div>
                                        @elseif($code)
                                            <div x-data="{ copied: false }" class="bg-gray-950 border border-primary/30 rounded-lg p-2 group-hover:border-primary transition-colors flex items-center justify-between gap-2 overflow-hidden">
                                                <div class="text-left flex-1 min-w-0">
                                                    <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5 truncate">Code (1x gültig)</p>
                                                    <p class="text-primary font-mono font-bold text-xs sm:text-sm tracking-wider truncate">{{$code}}</p>
                                                </div>
                                                <button type="button" @click="navigator.clipboard.writeText('{{$code}}'); copied = true; setTimeout(() => copied = false, 2000)" class="shrink-0 p-2 bg-primary/10 text-primary rounded-md hover:bg-primary hover:text-gray-900 transition-colors focus:outline-none flex items-center justify-center h-full w-10" title="Code kopieren">
                                                    <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                    <svg x-show="copied" style="display: none;" class="w-4 h-4 text-emerald-500 drop-shadow-[0_0_5px_rgba(16,185,129,0.8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        @else
                                            <div class="bg-gray-950 border border-primary/30 rounded-lg p-2">
                                                <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-1">Status</p>
                                                <p class="text-primary font-mono font-bold text-[10px] tracking-wider">WIRD GENERIERT...</p>
                                            </div>
                                        @endif
                                    @else
                                        <div class="bg-gray-800 rounded-lg p-3">
                                            <p class="text-gray-500 font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                Gesperrt
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <template x-teleport="body">
                <div x-show="show3DModal" style="display: none;" class="fixed inset-0 z-[6000] flex flex-col items-center justify-between p-4 sm:p-10">
                    <div class="absolute inset-0 bg-black/98 backdrop-blur-3xl" @click="close3DModal()" x-transition.opacity></div>

                    <div class="relative w-full max-w-[90rem] flex-1 flex flex-col bg-gradient-to-b from-gray-900 to-black rounded-[2rem] sm:rounded-[3rem] shadow-[0_0_100px_rgba(0,0,0,1)] border border-gray-800 overflow-hidden"
                         x-transition:enter="transition ease-out duration-300 delay-100"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 translate-y-10">

                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent opacity-60 pointer-events-none"></div>

                        <div x-show="evolutionFlash" x-transition:enter="transition ease-in duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-out duration-1000" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute inset-0 z-[6050] flex items-center justify-center bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-white via-white/95 to-primary/50 pointer-events-none" style="display: none;"></div>

                        <div x-show="showConfetti" x-transition class="absolute inset-0 z-[6060] pointer-events-none flex flex-col items-center justify-center" style="display: none;">
                            <h2 class="text-5xl md:text-8xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-primary to-amber-300 drop-shadow-[0_0_30px_rgba(197,160,89,1)] animate-bounce mb-4 text-center">LEVEL UP!</h2>
                            <p class="text-white text-lg md:text-2xl font-black uppercase tracking-widest drop-shadow-lg text-center" x-text="rewardMessage"></p>
                        </div>

                        <div class="absolute top-4 left-4 sm:top-8 sm:left-8 z-[6020] flex items-center gap-3 sm:gap-4 bg-gray-900/90 backdrop-blur-md border border-gray-700 rounded-[2rem] p-2 pr-4 sm:p-4 sm:pr-8 shadow-2xl pointer-events-auto">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-b from-primary to-primary-dark border-2 border-gray-900 flex items-center justify-center shadow-[0_0_20px_rgba(197,160,89,0.6)] shrink-0">
                                <span class="text-gray-900 font-black text-lg sm:text-xl">{{$level}}</span>
                            </div>
                            <h3 class="text-white font-black text-[9px] sm:text-xs uppercase tracking-[0.2em]">{{$currentRankName}}</h3>
                        </div>

                        <button @click="close3DModal()" class="absolute top-4 right-4 sm:top-8 sm:right-8 z-[6050] p-2.5 sm:p-3 bg-gray-800 border-2 border-gray-700 rounded-full text-gray-400 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-[0_0_30px_rgba(0,0,0,0.8)] hover:scale-110 cursor-pointer">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>

                        <div class="flex-1 w-full relative min-h-0 pt-20 pb-4">
                            <div id="funki-3d-canvas-container" class="absolute inset-0 cursor-grab active:cursor-grabbing w-full h-full z-10" wire:ignore></div>
                        </div>

                        <div class="w-full bg-gray-950/98 backdrop-blur-3xl border-t border-gray-800 relative z-[6020] pb-safe">
                            <div class="flex flex-row items-center justify-start gap-4 sm:gap-8 px-4 sm:px-12 py-4 sm:py-6 overflow-x-auto no-scrollbar scroll-smooth w-full">
                                @php
                                    $milestones = \App\Services\Gamification\GameConfig::getAppearanceMilestones();
                                @endphp
                                @foreach($milestones as $mLevel => $mName)
                                    <div class="flex flex-col items-center gap-2 sm:gap-3 relative group shrink-0">
                                        <button type="button"
                                                @if($level >= $mLevel)
                                                    @click="currentPath = '{{asset('shop/customer/gamification/models/' . $mName . '.glb')}}'; currentImagePath = '{{asset('shop/customer/gamification/models/images/original/' . $mName . '.png')}}'; window._funki3DLoader(currentPath);"
                                                @endif
                                                class="w-14 h-14 sm:w-20 sm:h-20 shrink-0 rounded-full bg-black border-2 flex items-center justify-center transition-all duration-1000 focus:outline-none {{ $level == $mLevel ? 'border-primary shadow-[0_0_25px_rgba(197,160,89,0.6)] scale-110 ring-4 ring-primary/20 z-10' : ($level > $mLevel ? 'border-primary/40 opacity-70 hover:opacity-100 hover:scale-105 hover:border-primary cursor-pointer' : 'border-gray-800 cursor-not-allowed') }}">
                                            <img src="{{asset('shop/customer/gamification/models/images/original/' . $mName . '.png')}}" class="w-full h-full object-cover rounded-full transition-all duration-1000 {{ $level >= $mLevel ? '' : 'blur-[10px] opacity-10 grayscale' }}">
                                        </button>
                                        <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-widest {{ $level == $mLevel ? 'text-primary' : 'text-gray-600' }}">Level {{$mLevel}}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template x-teleport="body">
                <div x-show="showTitlesModal" style="display: none;" class="fixed inset-0 z-[7000] flex items-center justify-center p-4 sm:p-6">
                    <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="showTitlesModal = false" x-transition.opacity></div>

                    <div class="relative w-full max-w-4xl bg-gray-950 border border-gray-800 rounded-[2.5rem] shadow-[0_0_100px_rgba(0,0,0,1)] flex flex-col max-h-[90vh] overflow-hidden"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 translate-y-10">

                        <div class="relative w-full bg-gray-900 border-b border-amber-500/20 p-8 sm:p-10 text-center shrink-0 overflow-hidden shadow-inner flex flex-col items-center">
                            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-amber-500/5 via-transparent to-transparent pointer-events-none"></div>

                            <button @click="showTitlesModal = false" class="absolute top-6 right-6 p-2 bg-gray-800 text-gray-400 rounded-full hover:bg-red-500 hover:text-white transition-colors cursor-pointer focus:outline-none z-20 shadow-lg">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>

                            <div class="relative z-10 w-full">
                                <p class="text-[10px] sm:text-xs text-amber-500/70 font-black uppercase tracking-[0.3em] mb-2 drop-shadow-[0_0_8px_currentColor]">Dein aktueller Meta-Rang</p>
                                <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif font-black text-amber-400 drop-shadow-[0_0_15px_rgba(251,191,36,0.3)] mb-4 uppercase tracking-tight">{{$titlesData['mega_title']['name'] ?? 'Ein Funke im Wind'}}</h2>

                                <div class="mb-6">
                                    <button wire:click="selectTitle('mega_title')" class="px-6 py-2 rounded-full text-[9px] font-black uppercase tracking-widest transition-all shadow-md {{ $activeTitleKey === 'mega_title' ? 'bg-amber-500 text-gray-900 shadow-[0_0_15px_rgba(251,191,36,0.6)] cursor-default' : 'bg-gray-900 border border-amber-500/50 text-amber-400 hover:bg-amber-500/20 hover:text-white' }}">
                                        {{ $activeTitleKey === 'mega_title' ? 'Als Meta-Rang ausgerüstet' : 'Als Meta-Rang ausrüsten' }}
                                    </button>
                                </div>

                                <div class="max-w-md mx-auto bg-gray-950 p-4 rounded-2xl border border-gray-800 shadow-inner">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Diamant-Titel</span>
                                        <span class="text-xs font-black text-amber-400">{{$titlesData['diamonds_count'] ?? 0}} <span class="opacity-50 text-gray-600">/ 15</span></span>
                                    </div>
                                    <div class="w-full bg-gray-900 rounded-full h-2.5 shadow-inner overflow-hidden border border-gray-800">
                                        <div class="h-full bg-gradient-to-r from-amber-600 to-yellow-400 rounded-full transition-all duration-1000 shadow-[0_0_10px_rgba(251,191,36,0.5)]" style="width: {{ min(100, (($titlesData['diamonds_count'] ?? 0) / 15) * 100) }}%"></div>
                                    </div>
                                    @if(isset($titlesData['next_mega_title']))
                                        <p class="text-[9px] sm:text-[10px] text-gray-400 font-medium mt-3 uppercase tracking-widest">Noch <span class="text-white font-bold">{{ $titlesData['next_mega_title']['req'] - ($titlesData['diamonds_count'] ?? 0) }} Diamant-Titel</span> bis: <strong class="text-amber-400">{{$titlesData['next_mega_title']['name']}}</strong></p>
                                    @else
                                        <p class="text-[9px] sm:text-[10px] text-amber-400 font-black mt-3 uppercase tracking-widest drop-shadow-[0_0_8px_currentColor]">Du hast die höchste Stufe erreicht!</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="p-4 sm:p-8 overflow-y-auto custom-scrollbar flex-1 bg-gray-950">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($titlesData['titles'] ?? [] as $titleKey => $title)
                                    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-5 relative overflow-hidden flex flex-col gap-3 group transition-colors {{ $title['tier'] === 'diamant' ? 'border-amber-500/30 shadow-[inset_0_0_20px_rgba(251,191,36,0.05)] bg-amber-900/5' : 'hover:border-gray-600' }}">
                                        <div class="flex items-start gap-4">
                                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center shrink-0 shadow-inner
                                                {{ $title['tier'] === 'grau' ? 'bg-gray-950 text-gray-600 border border-gray-800' : '' }}
                                                {{ $title['tier'] === 'silber' ? 'bg-slate-800 text-slate-300 border border-slate-600/50' : '' }}
                                                {{ $title['tier'] === 'gold' ? 'bg-yellow-900/20 text-yellow-500 border border-yellow-600/50' : '' }}
                                                {{ $title['tier'] === 'diamant' ? 'bg-cyan-900/20 text-cyan-400 border border-cyan-500/50 shadow-[0_0_15px_rgba(34,211,238,0.3)]' : '' }}">
                                                @if($title['tier'] === 'diamant')
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                                    </svg>
                                                @else
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                                    </svg>
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0 pt-0.5">
                                                <h4 class="text-white font-bold text-sm sm:text-base leading-tight truncate">{{$title['name']}}</h4>
                                                <p class="text-gray-500 text-[10px] sm:text-xs mt-1 leading-snug">{{$title['description']}}</p>
                                            </div>
                                        </div>

                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md whitespace-nowrap
                                                {{ $title['tier'] === 'grau' ? 'bg-gray-800 text-gray-500' : '' }}
                                                {{ $title['tier'] === 'silber' ? 'bg-slate-800 text-slate-300' : '' }}
                                                {{ $title['tier'] === 'gold' ? 'bg-yellow-900/40 text-yellow-500' : '' }}
                                                {{ $title['tier'] === 'diamant' ? 'bg-cyan-900/40 text-cyan-400' : '' }}">
                                                {{$title['tier_name']}}
                                            </span>
                                            <p class="text-[9px] text-gray-600 font-mono font-bold">{{$title['current_value']}} / {{$title['next_req']}}</p>
                                        </div>
                                        <div class="w-full bg-gray-950 rounded-full h-1.5 shadow-inner overflow-hidden">
                                            <div class="h-1.5 rounded-full transition-all duration-1000
                                                {{ $title['tier'] === 'grau' ? 'bg-gray-700' : '' }}
                                                {{ $title['tier'] === 'silber' ? 'bg-slate-400 shadow-[0_0_5px_rgba(148,163,184,0.5)]' : '' }}
                                                {{ $title['tier'] === 'gold' ? 'bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.5)]' : '' }}
                                                {{ $title['tier'] === 'diamant' ? 'bg-cyan-400 shadow-[0_0_10px_rgba(34,211,238,0.8)]' : '' }}"
                                                 style="width: {{$title['percentage']}}%"></div>
                                        </div>

                                        @if($title['tier'] !== 'grau')
                                            <div class="mt-2 border-t border-gray-800 pt-3">
                                                <button wire:click="selectTitle('{{$titleKey}}')" class="w-full py-2 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all {{ $activeTitleKey === $titleKey ? 'bg-amber-500/20 text-amber-400 border border-amber-500/50 cursor-default' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white border border-transparent' }}">
                                                    {{ $activeTitleKey === $titleKey ? 'Ausgerüstet' : 'Als Titel anzeigen' }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    <script>


        window.funkiHub = function(initialModelPath, initialImagePath) {
            let scene, camera, renderer, controls, currentModel;
            let threeInitialized = false;
            let animationId = null;

            return {
                darkFade: false,
                evolutionFlash: false,
                showConfetti: false,
                rewardMessage: '',
                show3DModal: false,
                showTitlesModal: false,
                currentPath: initialModelPath || '',
                currentImagePath: initialImagePath || '',

                initFunki() {
                    if (window.sessionStorage.getItem('funki_just_activated')) {
                        this.darkFade = true;
                        window.sessionStorage.removeItem('funki_just_activated');
                        setTimeout(() => { this.darkFade = false; }, 100);
                    }
                },

                handleLevelUp(data) {
                    this.open3DModal();
                    setTimeout(() => {
                        this.evolutionFlash = true;
                        setTimeout(() => {
                            this.currentPath = data.newModelPath;
                            this.currentImagePath = data.newImagePath;
                            if (threeInitialized && window._funki3DLoader) {
                                window._funki3DLoader(this.currentPath, () => {
                                    this.evolutionFlash = false;
                                    this.rewardMessage = data.reward || 'Neue Form freigeschaltet!';
                                    this.showConfetti = true;
                                    setTimeout(() => { this.showConfetti = false; }, 4000);
                                });
                            }
                        }, 1000);
                    }, 500);
                },

                open3DModal() {
                    this.show3DModal = true;
                    setTimeout(() => {
                        if (!threeInitialized) {
                            this.initThreeJS();
                        } else {
                            this.resizeThreeJS();
                            if (!animationId && typeof window._funkiRestartAnimation === 'function') {
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
                        console.warn('ThreeJS or plugins not loaded properly.');
                        return;
                    }

                    // Remove existing canvases if Livewire/Alpine re-initializes while wire:ignore kept the DOM
                    while (container.firstChild) {
                        container.removeChild(container.firstChild);
                    }

                    scene = new window.THREE.Scene();
                    camera = new window.THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 0.1, 1000);
                    renderer = new window.THREE.WebGLRenderer({ antialias: true, alpha: true });
                    renderer.setSize(container.offsetWidth, container.offsetHeight);
                    renderer.setPixelRatio(window.devicePixelRatio);
                    container.appendChild(renderer.domElement);

                    scene.add(new window.THREE.AmbientLight(0xffffff, 2.5));
                    const dirLight = new window.THREE.DirectionalLight(0xffffff, 2.0);
                    dirLight.position.set(5, 5, 5);
                    scene.add(dirLight);

                    controls = new window.OrbitControls(camera, renderer.domElement);
                    controls.enableDamping = true;
                    controls.minDistance = 1;
                    controls.maxDistance = 5;

                    window._funki3DLoader = (path, cb) => {
                        if (!path || path.trim() === '') return;

                        // Set the latest requested path to prevent async race conditions
                        window._funkiLatestRequestedPath = path;

                        const loader = new window.GLTFLoader();
                        loader.load(path, (gltf) => {
                            // Abort if a different model was requested while this one was downloading
                            if (window._funkiLatestRequestedPath !== path) return;

                            if (currentModel) {
                                scene.remove(currentModel);
                                // Dispose geometries and materials to avoid memory leaks
                                currentModel.traverse((child) => {
                                    if (child.isMesh) {
                                        if (child.geometry) child.geometry.dispose();
                                        if (child.material) {
                                            if (Array.isArray(child.material)) {
                                                child.material.forEach(m => m.dispose());
                                            } else {
                                                child.material.dispose();
                                            }
                                        }
                                    }
                                });
                            }

                            currentModel = gltf.scene;
                            const box = new window.THREE.Box3().setFromObject(currentModel);
                            const center = box.getCenter(new window.THREE.Vector3());
                            currentModel.position.sub(center);
                            currentModel.rotation.y = Math.PI / -2;
                            scene.add(currentModel);
                            camera.position.set(0, 0.8, 2.5);
                            if (cb) cb();
                        });
                    };

                    window._funki3DLoader(this.currentPath);

                    const animate = () => {
                        if (!this.show3DModal) {
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
                    camera.aspect = container.offsetWidth / container.offsetHeight;
                    camera.updateProjectionMatrix();
                    renderer.setSize(container.offsetWidth, container.offsetHeight);
                }
            };
        };
    </script>

    @endif


    {{-- STYLES --}}
    @include('livewire.customer.partials.games-component.styles')

    {{-- SCRIPTS --}}
    @include('livewire.customer.partials.games-component.scripts')
</div>
