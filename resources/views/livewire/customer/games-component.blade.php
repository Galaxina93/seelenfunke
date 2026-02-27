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
                Kettenreaktionen, Nova-Sphären und Zeit-Kristalle. Wische oder Tausche strategisch klug auf dem 9x9 Feld, um schneller an Rabatte zu kommen!
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
                            {{-- BOUNCE & RED WENN ZÜGE <= 5 --}}
                            <h4 class="text-5xl sm:text-6xl font-serif font-bold text-white transition-colors" :class="{'text-red-500 animate-bounce': moves <= 5}" x-text="moves"></h4>
                        </div>
                    </div>

                    <div class="hidden lg:flex bg-blue-500/5 border border-blue-500/20 p-5 sm:p-6 rounded-[2rem] text-blue-300 text-xs sm:text-sm leading-relaxed flex-col gap-4 shadow-inner">
                        <strong class="text-blue-400 uppercase tracking-[0.2em] font-black text-[10px] border-b border-blue-500/20 pb-3">Das Handbuch</strong>
                        <ul class="space-y-3 sm:space-y-4">
                            <li class="flex gap-3 items-start"><span class="text-lg sm:text-xl leading-none">🔄</span> <span>Tippe oder Wische 2 Steine an, um sie zu tauschen. Kostet 1 Zug.</span></li>
                            <li class="flex gap-3 items-start"><span class="text-lg sm:text-xl leading-none">💎</span> <span><strong>Master-Diamant:</strong> Zerstöre ihn für riesige Kreuz-Laser!</span></li>
                            <li class="flex gap-3 items-start"><span class="text-lg sm:text-xl leading-none drop-shadow-[0_0_5px_rgba(255,255,255,0.8)]">🕳️</span> <span><strong>Nova-Sphäre:</strong> Tausche sie mit <span class="underline">jedem</span> Stein (3x3 Explosion).</span></li>
                            <li class="flex gap-3 items-start"><span class="text-lg sm:text-xl leading-none">⏳</span> <span><strong>Zeit-Kristall:</strong> Bringt dir sofort <strong>+2 Züge</strong>.</span></li>
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

                    <div class="flex-1 bg-gray-950/60 rounded-3xl p-6 sm:p-8 border border-gray-800 w-full flex flex-col justify-center shadow-inner relative z-10">
                        <div class="flex items-center justify-between border-b border-blue-500/20 pb-4 mb-6">
                            <h3 class="text-blue-400 font-black uppercase tracking-[0.2em] text-xs sm:text-sm flex items-center gap-3">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                Das Handbuch
                            </h3>
                        </div>
                        <ul class="space-y-4 sm:space-y-5 text-gray-300 text-xs sm:text-sm leading-relaxed">
                            <li class="flex gap-4 items-start"><span class="text-xl sm:text-2xl leading-none">🔄</span> <span>Tippe oder Wische 2 Steine an, um sie zu tauschen. Kostet 1 Zug.</span></li>
                            <li class="flex gap-4 items-start"><span class="text-xl sm:text-2xl leading-none">💎</span> <span><strong>Master-Diamant:</strong> Zerstöre ihn für riesige Kreuz-Laser!</span></li>
                            <li class="flex gap-4 items-start"><span class="text-xl sm:text-2xl leading-none drop-shadow-[0_0_5px_rgba(255,255,255,0.8)]">🕳️</span> <span><strong>Nova-Sphäre:</strong> Tausche sie mit jedem Stein (3x3 Explosion).</span></li>
                            <li class="flex gap-4 items-start"><span class="text-xl sm:text-2xl leading-none">⏳</span> <span><strong>Zeit-Kristall:</strong> Bringt dir sofort <strong>+2 Züge</strong>.</span></li>
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
    <style>
        #threejs-match3-container canvas {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;
            display: block; width: 100% !important; height: 100% !important; outline: none; touch-action: none;
        }
        .floating-score {
            position: absolute; font-family: ui-sans-serif, system-ui, sans-serif; font-weight: 900; font-size: 1.5rem;
            pointer-events: none; z-index: 100; transform: translate(-50%, -50%);
            animation: floatUp 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; text-shadow: 0px 4px 10px rgba(0,0,0,0.9), 0px 0px 8px currentColor;
        }
        .floating-bonus {
            font-size: 2rem; color: #f472b6; animation: floatUpBonus 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @media (min-width: 640px) {
            .floating-score { font-size: 2.5rem; }
            .floating-bonus { font-size: 3.5rem; }
        }
        @keyframes floatUp { 0% { transform: translate(-50%, -50%) scale(0.5); opacity: 1; } 20% { transform: translate(-50%, -100%) scale(1.5); opacity: 1; } 100% { transform: translate(-50%, -200%) scale(1); opacity: 0; } }
        @keyframes floatUpBonus { 0% { transform: translate(-50%, -50%) scale(0.5) rotate(-10deg); opacity: 1; } 30% { transform: translate(-50%, -150%) scale(1.5) rotate(5deg); opacity: 1; } 100% { transform: translate(-50%, -300%) scale(1.2) rotate(0deg); opacity: 0; } }

        input[type=range].volume-slider { -webkit-appearance: none; width: 100%; background: transparent; }
        input[type=range].volume-slider::-webkit-slider-thumb { -webkit-appearance: none; height: 16px; width: 16px; border-radius: 50%; background: #10b981; cursor: pointer; margin-top: -6px; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
        input[type=range].volume-slider::-webkit-slider-runnable-track { width: 100%; height: 4px; cursor: pointer; background: #374151; border-radius: 2px; }
    </style>

    {{-- SCRIPTS --}}
    <script>
        window.ArcadeAudio = class ArcadeAudio {
            constructor() { this.ctx = new (window.AudioContext || window.webkitAudioContext)(); }
            playTone(freq, type, duration, vol = 0.1) {
                if(this.ctx.state === 'suspended') this.ctx.resume();
                const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
                osc.type = type; osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
                gain.gain.setValueAtTime(vol, this.ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + duration);
                osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + duration);
            }
            playSwap() { this.playTone(300, 'sine', 0.1, 0.05); setTimeout(() => this.playTone(400, 'sine', 0.15, 0.05), 100); }
            playMatch() { this.playTone(523.25, 'sine', 0.3, 0.1); this.playTone(659.25, 'sine', 0.3, 0.1); this.playTone(783.99, 'sine', 0.4, 0.1); }
            playLaser() {
                if(this.ctx.state === 'suspended') this.ctx.resume();
                const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
                osc.type = 'sawtooth'; osc.frequency.setValueAtTime(800, this.ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(100, this.ctx.currentTime + 0.3);
                gain.gain.setValueAtTime(0.1, this.ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.3);
                osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + 0.3);
            }
        };

        window.Match3DEngine = class Match3DEngine {
            constructor(container, callbacks) {
                this.container = container; this.callbacks = callbacks; this.audio = new window.ArcadeAudio();
                this.rows = 9; this.cols = 9; this.cellSize = 1.2;
                this.board = []; this.crystals = []; this.particles = []; this.tweens = []; this.lasers = [];
                this.isProcessing = false; this.selectedMesh = null; this.shakeTime = 0;
                this.initScene(); this.loadAssets(); this.setupInteractions(); this.animate();
            }

            resize() {
                if (!this.container || !this.camera || !this.renderer) return;
                const width = this.container.offsetWidth || 800; const height = this.container.offsetHeight || 800;
                const aspect = width / height;
                this.camera.aspect = aspect; this.camera.updateProjectionMatrix();
                this.renderer.setSize(width, height);
                const gridWidth = this.cols * this.cellSize; const gridHeight = this.rows * this.cellSize;
                let cameraZ = Math.max(gridWidth, gridHeight) + 4;
                if (aspect < 1) cameraZ = (gridWidth / aspect) * 0.9;
                this.camera.position.set(0, 0, cameraZ);
                this.camera.lookAt(0, 0, 0);
            }

            initScene() {
                this.scene = new THREE.Scene();
                const width = this.container.offsetWidth || 800; const height = this.container.offsetHeight || 800;
                this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
                this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
                this.renderer.setSize(width, height);
                this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
                this.renderer.setClearColor(0x000000, 0);
                this.renderer.domElement.style.width = '100%'; this.renderer.domElement.style.height = '100%';
                this.renderer.domElement.style.position = 'absolute'; this.renderer.domElement.style.top = '0'; this.renderer.domElement.style.left = '0';
                this.container.appendChild(this.renderer.domElement);
                this.scene.add(new THREE.AmbientLight(0xffffff, 1.5));
                const dirLight = new THREE.DirectionalLight(0xffffff, 2.0); dirLight.position.set(5, 10, 10); this.scene.add(dirLight);
                this.scene.add(new THREE.PointLight(0xffffff, 0, 10));
                const resizeObserver = new ResizeObserver(() => this.resize());
                resizeObserver.observe(this.container);
                this.resize();
            }

            loadAssets() {
                this.geometries = [
                    null, new THREE.OctahedronGeometry(0.45, 0), new THREE.IcosahedronGeometry(0.45, 0),
                    new THREE.DodecahedronGeometry(0.35, 0), new THREE.TetrahedronGeometry(0.45, 0),
                    new THREE.CylinderGeometry(0.3, 0.3, 0.7, 6), new THREE.TorusGeometry(0.3, 0.15, 8, 16),
                    new THREE.OctahedronGeometry(0.55, 1), new THREE.SphereGeometry(0.4, 32, 32),
                    new THREE.TorusKnotGeometry(0.25, 0.08, 64, 8)
                ];
                this.colorsHex = [null, 0xef4444, 0x3b82f6, 0x10b981, 0xeab308, 0xa855f7, 0x06b6d4, 0xffffff, 0x111111, 0xf472b6];
                this.materials = this.colorsHex.map((c, index) => {
                    if (index === 0) return null;
                    if (index === 7) return new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 0.5, roughness: 0.1, emissive: 0xffffff, emissiveIntensity: 0.6 });
                    if (index === 8) return new THREE.MeshStandardMaterial({ color: 0x111111, metalness: 0.8, roughness: 0.2, emissive: 0x6b21a8, emissiveIntensity: 0.8 });
                    if (index === 9) return new THREE.MeshStandardMaterial({ color: 0xf472b6, metalness: 0.3, roughness: 0.1, emissive: 0xf472b6, emissiveIntensity: 0.5 });
                    return new THREE.MeshStandardMaterial({ color: c, metalness: 0.2, roughness: 0.1, emissive: c, emissiveIntensity: 0.4 });
                });
                this.highlightMat = new THREE.MeshBasicMaterial({ color: 0xffffff, wireframe: true, transparent: true, opacity: 0.8 });
            }

            start() {
                this.clearBoard(); this.fillBoard();
                while(this.findMatches().length > 0) { this.clearBoard(); this.fillBoard(); }
                this.isProcessing = false;
            }

            clearBoard() {
                this.crystals.forEach(m => this.scene.remove(m)); this.crystals = [];
                this.board = Array.from({ length: this.rows }, () => Array(this.cols).fill(0));
            }

            getPos(r, c) { return { x: (c - this.cols / 2 + 0.5) * this.cellSize, y: -(r - this.rows / 2 + 0.5) * this.cellSize, z: 0 }; }

            fillBoard() {
                for (let r = 0; r < this.rows; r++) { for (let c = 0; c < this.cols; c++) { if (this.board[r][c] === 0) this.spawnCrystal(r, c); } }
            }

            spawnCrystal(r, c, dropFromTop = false) {
                const rand = Math.random(); let typeIndex = Math.floor(Math.random() * 6) + 1;
                if (rand < 0.015) typeIndex = 8; else if (rand < 0.04) typeIndex = 9; else if (rand < 0.07) typeIndex = 7;
                const mat = (typeIndex >= 7) ? this.materials[typeIndex].clone() : this.materials[typeIndex];
                const mesh = new THREE.Mesh(this.geometries[typeIndex], mat);
                mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0); mesh.userData = { r, c, type: typeIndex };
                const targetPos = this.getPos(r, c);
                if (dropFromTop) { mesh.position.set(targetPos.x, targetPos.y + 12, targetPos.z); this.tween(mesh.position, targetPos, 400, 'easeOutBounce'); }
                else { mesh.position.set(targetPos.x, targetPos.y, targetPos.z); }
                this.scene.add(mesh); this.crystals.push(mesh); this.board[r][c] = mesh;
            }

            // ==========================================
            // LOGIK: CLICK + DRAG & DROP (SWIPE)
            // ==========================================
            setupInteractions() {
                this.raycaster = new THREE.Raycaster();
                this.mouse = new THREE.Vector2();
                let startX = 0;
                let startY = 0;
                let isDragging = false;

                const getEventCoords = (e) => {
                    if (e.changedTouches && e.changedTouches.length > 0) {
                        return { x: e.changedTouches[0].clientX, y: e.changedTouches[0].clientY };
                    }
                    return { x: e.clientX, y: e.clientY };
                };

                const onPointerDown = (event) => {
                    if (this.isProcessing || this.callbacks.getMoves() <= 0 || event.target !== this.renderer.domElement) return;

                    const rect = this.renderer.domElement.getBoundingClientRect();
                    const coords = getEventCoords(event);

                    this.mouse.x = ((coords.x - rect.left) / rect.width) * 2 - 1;
                    this.mouse.y = -((coords.y - rect.top) / rect.height) * 2 + 1;

                    this.raycaster.setFromCamera(this.mouse, this.camera);
                    const intersects = this.raycaster.intersectObjects(this.crystals);

                    if (intersects.length > 0) {
                        const clickedMesh = intersects[0].object;

                        if (!this.selectedMesh) {
                            // ERSTER KLICK (Stein markieren)
                            this.selectMesh(clickedMesh);
                            this.audio.playSwap();
                            startX = coords.x;
                            startY = coords.y;
                            isDragging = true;
                        } else {
                            // ZWEITER KLICK
                            if (this.selectedMesh === clickedMesh) {
                                this.deselectMesh();
                                isDragging = false;
                                return;
                            }
                            const r1 = this.selectedMesh.userData.r, c1 = this.selectedMesh.userData.c;
                            const r2 = clickedMesh.userData.r, c2 = clickedMesh.userData.c;

                            const isAdj = (Math.abs(r1 - r2) + Math.abs(c1 - c2)) === 1;
                            const isTeleport = (this.selectedMesh.userData.type === 8 || clickedMesh.userData.type === 8);

                            if (isAdj || isTeleport) {
                                this.executeSwap(this.selectedMesh, clickedMesh, isTeleport);
                                isDragging = false;
                            } else {
                                // Falscher Stein -> neuen markieren
                                this.deselectMesh();
                                this.selectMesh(clickedMesh);
                                this.audio.playSwap();
                                startX = coords.x;
                                startY = coords.y;
                                isDragging = true;
                            }
                        }
                    } else {
                        this.deselectMesh();
                        isDragging = false;
                    }
                };

                const onPointerMove = (event) => {
                    if (!isDragging || !this.selectedMesh || this.isProcessing) return;

                    const coords = getEventCoords(event);
                    const diffX = coords.x - startX;
                    const diffY = coords.y - startY;
                    const threshold = 30; // Pixel Distanz ab der ein Wischen (Drag) erkannt wird

                    if (Math.abs(diffX) > threshold || Math.abs(diffY) > threshold) {
                        isDragging = false; // Wir haben gewischt!

                        let targetR = this.selectedMesh.userData.r;
                        let targetC = this.selectedMesh.userData.c;

                        // Richtung bestimmen
                        if (Math.abs(diffX) > Math.abs(diffY)) {
                            targetC += diffX > 0 ? 1 : -1;
                        } else {
                            targetR += diffY > 0 ? 1 : -1; // Y geht nach unten ins Positive!
                        }

                        if (targetR >= 0 && targetR < this.rows && targetC >= 0 && targetC < this.cols) {
                            const targetMesh = this.board[targetR][targetC];
                            if (targetMesh && targetMesh !== 0) {
                                const isTeleport = (this.selectedMesh.userData.type === 8 || targetMesh.userData.type === 8);
                                this.executeSwap(this.selectedMesh, targetMesh, isTeleport);
                            } else {
                                this.deselectMesh();
                            }
                        } else {
                            this.deselectMesh();
                        }
                    }
                };

                const onPointerUp = () => { isDragging = false; };

                // Mouse & Touch registrieren
                this.container.addEventListener('mousedown', onPointerDown);
                this.container.addEventListener('mousemove', onPointerMove);
                this.container.addEventListener('mouseleave', onPointerUp);
                window.addEventListener('mouseup', onPointerUp);
                this.container.addEventListener('touchstart', onPointerDown, {passive: true});
                this.container.addEventListener('touchmove', onPointerMove, {passive: true});
                window.addEventListener('touchend', onPointerUp);
            }

            selectMesh(mesh) {
                this.selectedMesh = mesh; this.tween(mesh.scale, {x: 1.3, y: 1.3, z: 1.3}, 150);
                const outline = new THREE.Mesh(mesh.geometry, this.highlightMat); outline.scale.set(1.1, 1.1, 1.1);
                mesh.add(outline); mesh.userData.outline = outline;
            }

            deselectMesh() {
                if(this.selectedMesh) {
                    this.tween(this.selectedMesh.scale, {x: 1, y: 1, z: 1}, 150);
                    if(this.selectedMesh.userData.outline) this.selectedMesh.remove(this.selectedMesh.userData.outline);
                    this.selectedMesh = null;
                }
            }

            async executeSwap(mesh1, mesh2, isTeleport = false) {
                this.isProcessing = true; this.deselectMesh(); this.audio.playSwap();
                const r1 = mesh1.userData.r, c1 = mesh1.userData.c; const r2 = mesh2.userData.r, c2 = mesh2.userData.c;
                mesh1.userData.r = r2; mesh1.userData.c = c2; mesh2.userData.r = r1; mesh2.userData.c = c1;
                this.board[r1][c1] = mesh2; this.board[r2][c2] = mesh1;
                if (isTeleport) {
                    this.tween(mesh1.position, this.getPos(r2, c2), 400, 'easeOutBounce'); this.tween(mesh2.position, this.getPos(r1, c1), 400, 'easeOutBounce');
                    await this.sleep(400);
                } else {
                    this.tween(mesh1.position, this.getPos(r2, c2), 250); this.tween(mesh2.position, this.getPos(r1, c1), 250);
                    await this.sleep(250);
                }
                this.callbacks.onMoveUsed();
                if (isTeleport) {
                    if(mesh1.userData.type === 8) await this.triggerNovaExplosion(mesh1.userData.r, mesh1.userData.c);
                    if(mesh2.userData.type === 8) await this.triggerNovaExplosion(mesh2.userData.r, mesh2.userData.c);
                    await this.sleep(200); this.applyGravity(); await this.sleep(400);
                }
                await this.processMatches(); this.isProcessing = false;
            }

            async triggerNovaExplosion(r, c) {
                this.audio.playLaser(); this.shakeTime = 40; let destroyedCount = 0;
                for (let rr = r - 1; rr <= r + 1; rr++) {
                    for (let cc = c - 1; cc <= c + 1; cc++) {
                        if (rr >= 0 && rr < this.rows && cc >= 0 && cc < this.cols) {
                            let mesh = this.board[rr][cc];
                            if (mesh && mesh !== 0) {
                                this.createExplosion(mesh.position, mesh.material.color); this.scene.remove(mesh);
                                this.board[rr][cc] = 0; this.crystals = this.crystals.filter(cr => cr !== mesh); destroyedCount++;
                            }
                        }
                    }
                }
                this.callbacks.onScore(destroyedCount, 3, new THREE.Vector3(this.getPos(r, c).x, this.getPos(r, c).y, 0), '#a855f7');
            }

            findMatches() {
                let matched = new Set();
                for (let r = 0; r < this.rows; r++) {
                    for (let c = 0; c < this.cols - 2; c++) {
                        let m1 = this.board[r][c], m2 = this.board[r][c+1], m3 = this.board[r][c+2];
                        if (m1 && m2 && m3 && m1.userData.type !== 8 && m1.userData.type === m2.userData.type && m1.userData.type === m3.userData.type) { matched.add(m1); matched.add(m2); matched.add(m3); }
                    }
                }
                for (let c = 0; c < this.cols; c++) {
                    for (let r = 0; r < this.rows - 2; r++) {
                        let m1 = this.board[r][c], m2 = this.board[r+1][c], m3 = this.board[r+2][c];
                        if (m1 && m2 && m3 && m1.userData.type !== 8 && m1.userData.type === m2.userData.type && m1.userData.type === m3.userData.type) { matched.add(m1); matched.add(m2); matched.add(m3); }
                    }
                }
                return Array.from(matched);
            }

            async processMatches(combo = 1) {
                let matches = this.findMatches(); if (matches.length === 0) return;
                this.audio.playMatch();
                let masterDiamonds = matches.filter(m => m.userData.type === 7);
                if(masterDiamonds.length > 0) await this.triggerMasterDiamond(masterDiamonds, matches);
                let timeCrystals = matches.filter(m => m.userData.type === 9);
                if(timeCrystals.length > 0) this.callbacks.onAddMoves(timeCrystals.length * 2, matches[1].position.clone());
                let colorHex = matches[0].userData.type === 7 ? "ffffff" : matches[0].material.color.getHexString();
                this.callbacks.onScore(matches.length, combo, matches[1].position.clone(), '#' + colorHex);
                matches.forEach(mesh => {
                    if (this.board[mesh.userData.r][mesh.userData.c] !== 0) {
                        this.createExplosion(mesh.position, mesh.material.color); this.board[mesh.userData.r][mesh.userData.c] = 0;
                        this.scene.remove(mesh); this.crystals = this.crystals.filter(c => c !== mesh);
                    }
                });
                await this.sleep(150); this.applyGravity(); await this.sleep(400); await this.processMatches(combo + 1);
            }

            async triggerMasterDiamond(masterDiamonds, currentMatches) {
                this.audio.playLaser(); this.shakeTime = 30;
                masterDiamonds.forEach(md => {
                    const r = md.userData.r, c = md.userData.c;
                    for(let i = 0; i < this.cols; i++) {
                        if(this.board[r][i] && this.board[r][i] !== 0) { currentMatches.push(this.board[r][i]); this.createLaser(md.position, this.board[r][i].position, 0xffffff); }
                    }
                    for(let i = 0; i < this.rows; i++) {
                        if(this.board[i][c] && this.board[i][c] !== 0) { currentMatches.push(this.board[i][c]); this.createLaser(md.position, this.board[i][c].position, 0xffffff); }
                    }
                });
                await this.sleep(200);
            }

            createLaser(startPos, endPos, colorHex) {
                const material = new THREE.LineBasicMaterial({ color: colorHex, transparent: true, opacity: 0.8, linewidth: 2 });
                const points = [startPos.clone(), endPos.clone()];
                const geometry = new THREE.BufferGeometry().setFromPoints(points);
                this.scene.add(new THREE.Line(geometry, material)); this.lasers.push({ mesh: new THREE.Line(geometry, material), life: 1.0 });
            }

            createExplosion(position, color) {
                const mat = new THREE.MeshBasicMaterial({ color: color });
                for (let i = 0; i < 10; i++) {
                    const p = new THREE.Mesh(new THREE.TetrahedronGeometry(0.2), mat); p.position.copy(position);
                    const v = new THREE.Vector3((Math.random() - 0.5) * 0.4, (Math.random() - 0.5) * 0.4, (Math.random() - 0.5) * 0.4 + 0.1);
                    this.scene.add(p); this.particles.push({ mesh: p, velocity: v, life: 1.0 });
                }
            }

            applyGravity() {
                for (let c = 0; c < this.cols; c++) {
                    let writePos = this.rows - 1, missing = 0;
                    for (let r = this.rows - 1; r >= 0; r--) {
                        let mesh = this.board[r][c];
                        if (mesh !== 0) {
                            if (writePos !== r) {
                                this.board[writePos][c] = mesh; this.board[r][c] = 0; mesh.userData.r = writePos;
                                this.tween(mesh.position, this.getPos(writePos, c), 300, 'easeOutBounce');
                            }
                            writePos--;
                        } else { missing++; }
                    }
                    for (let i = 0; i < missing; i++) { this.spawnCrystal(writePos, c, true); writePos--; }
                }
            }

            showFloatingText(pos3D, text, colorHex, layerElement, isBonus = false) {
                if (!layerElement) return;
                const vector = pos3D.clone(); vector.project(this.camera);
                const x = (vector.x * .5 + .5) * this.container.offsetWidth; const y = (vector.y * -.5 + .5) * this.container.offsetHeight;
                const el = document.createElement('div');
                el.className = isBonus ? 'floating-score floating-bonus' : 'floating-score'; el.innerText = text; el.style.left = `${x}px`; el.style.top = `${y}px`; if(!isBonus) el.style.color = colorHex;
                layerElement.appendChild(el);
                setTimeout(() => { if (el.parentNode === layerElement) layerElement.removeChild(el); }, 1500);
            }

            tween(obj, target, duration, easing = 'linear') { this.tweens.push({ obj, start: { x: obj.x, y: obj.y, z: obj.z }, target, duration, startTime: Date.now(), easing }); }

            updateTweens() {
                const now = Date.now();
                for (let i = this.tweens.length - 1; i >= 0; i--) {
                    const t = this.tweens[i]; let progress = (now - t.startTime) / t.duration;
                    if (progress >= 1) {
                        if (t.target.x !== undefined) t.obj.x = t.target.x; if (t.target.y !== undefined) t.obj.y = t.target.y; if (t.target.z !== undefined) t.obj.z = t.target.z;
                        this.tweens.splice(i, 1);
                    } else {
                        let e = progress;
                        if(t.easing === 'easeOutBounce') {
                            const n1 = 7.5625, d1 = 2.75;
                            if (progress < 1 / d1) { e = n1 * progress * progress; } else if (progress < 2 / d1) { e = n1 * (progress -= 1.5 / d1) * progress + 0.75; } else if (progress < 2.5 / d1) { e = n1 * (progress -= 2.25 / d1) * progress + 0.9375; } else { e = n1 * (progress -= 2.625 / d1) * progress + 0.984375; }
                        }
                        if (t.target.x !== undefined) t.obj.x = t.start.x + (t.target.x - t.start.x) * e; if (t.target.y !== undefined) t.obj.y = t.start.y + (t.target.y - t.start.y) * e; if (t.target.z !== undefined) t.obj.z = t.start.z + (t.target.z - t.start.z) * e;
                    }
                }
            }

            updateParticles() {
                for (let i = this.particles.length - 1; i >= 0; i--) {
                    let p = this.particles[i]; p.mesh.position.add(p.velocity); p.mesh.rotation.x += 0.1; p.mesh.rotation.y += 0.1; p.velocity.y -= 0.01; p.life -= 0.03; p.mesh.scale.setScalar(Math.max(0, p.life));
                    if (p.life <= 0) { this.scene.remove(p.mesh); this.particles.splice(i, 1); }
                }
                for (let i = this.lasers.length - 1; i >= 0; i--) {
                    let l = this.lasers[i]; l.life -= 0.1; l.mesh.material.opacity = Math.max(0, l.life);
                    if (l.life <= 0) { this.scene.remove(l.mesh); this.lasers.splice(i, 1); }
                }
            }

            animate() {
                requestAnimationFrame(() => this.animate());
                this.crystals.forEach(c => {
                    c.rotation.x += 0.005; c.rotation.y += 0.01;
                    if(c.userData.type === 8 && c.material.emissiveIntensity !== undefined) c.material.emissiveIntensity = 0.5 + Math.sin(Date.now() * 0.008) * 0.4;
                    if(c.userData.type === 7 && c.material.emissiveIntensity !== undefined) c.material.emissiveIntensity = 0.2 + Math.sin(Date.now() * 0.005) * 0.2;
                });
                this.updateTweens(); this.updateParticles();
                if (this.shakeTime > 0) { this.camera.position.x += (Math.random() - 0.5) * 0.4; this.camera.position.y += (Math.random() - 0.5) * 0.4; this.shakeTime--; }
                else { this.camera.position.x *= 0.9; this.camera.position.y *= 0.9; }
                if(this.renderer && this.scene && this.camera) this.renderer.render(this.scene, this.camera);
            }

            sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }
        };

        window.kristallKollaps3D = function() {
            let engine = null;

            return {
                activeGame: null,
                score: 0,
                moves: 15,
                gameState: 'ready',
                bgmVolume: 0.3,
                isBgmPlaying: false,
                energyWarning: false,

                init() {
                    this.$watch('bgmVolume', val => {
                        if (this.$refs.bgmAudio) this.$refs.bgmAudio.volume = val;
                    });

                    this.$watch('activeGame', value => {
                        if (value === 'kristall') {
                            setTimeout(() => {
                                if (!engine) { this.init3DEngine(); } else { engine.resize(); }
                            }, 300);
                        } else {
                            this.quitGame();
                        }
                    });
                },

                toggleMute() {
                    let audio = this.$refs.bgmAudio;
                    if(!audio) return;
                    if (this.isBgmPlaying) { audio.pause(); this.isBgmPlaying = false; }
                    else { audio.play().catch(e => console.log(e)); this.isBgmPlaying = true; }
                },

                quitGame() {
                    if (this.$refs.bgmAudio) {
                        this.$refs.bgmAudio.pause();
                        this.$refs.bgmAudio.currentTime = 0;
                        this.isBgmPlaying = false;
                    }
                    if (engine) engine.clearBoard();
                    this.gameState = 'ready';
                    this.activeGame = null;
                },

                async attemptStartGame() {
                    if (typeof window.THREE === 'undefined') {
                        alert("Three.js lädt noch. Bitte kurz warten...");
                        return;
                    }

                    if (engine && engine.audio) engine.audio.ctx.resume();

                    try {
                        let hasEnergy = await this.$wire.consumeEnergy();
                        if (hasEnergy) {
                            this.startGame();
                        } else {
                            this.energyWarning = true;
                            setTimeout(() => { this.energyWarning = false; }, 3000);
                        }
                    } catch (e) {
                        console.warn("Backend-Check übersprungen. Starte Spiel im Testmodus.");
                        this.startGame();
                    }
                },

                init3DEngine() {
                    if (typeof window.THREE === 'undefined') return;

                    const container = document.getElementById('threejs-match3-container');
                    const scoreLayer = document.getElementById('floating-scores-layer');

                    const callbacks = {
                        onScore: (points, combo, meshPos, colorHex) => {
                            let total = points * combo * 5; if(points > 3) total += 30;
                            this.score += total;
                            if(meshPos && engine) engine.showFloatingText(meshPos, `+${total}`, colorHex, scoreLayer);
                        },
                        onAddMoves: (extraMoves, meshPos) => {
                            this.moves += extraMoves;
                            if(meshPos && engine) engine.showFloatingText(meshPos, `+${extraMoves} Züge!`, '#f472b6', scoreLayer, true);
                        },
                        onMoveUsed: () => {
                            if (this.moves > 0) this.moves--;
                            if(this.moves <= 0 && this.gameState !== 'gameover') {
                                this.gameState = 'gameover';
                                let earnedFunken = Math.floor(this.score / 100);
                                if (earnedFunken > 0) { try { this.$wire.rewardGameSparks(earnedFunken); } catch(e) {} }
                            }
                        },
                        getMoves: () => { return this.moves; }
                    };

                    engine = new window.Match3DEngine(container, callbacks);
                },

                startGame() {
                    this.score = 0;
                    this.moves = 15;
                    this.gameState = 'playing';

                    if (this.$refs.bgmAudio) {
                        this.$refs.bgmAudio.volume = this.bgmVolume;
                        this.$refs.bgmAudio.play().then(() => { this.isBgmPlaying = true; }).catch(e => console.warn(e));
                    }
                    if(engine) engine.start();
                }
            };
        };
    </script>
</div>
