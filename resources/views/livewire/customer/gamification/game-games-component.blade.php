<div class="w-full max-w-7xl mx-auto px-3.5 sm:px-6 lg:px-8 py-4 sm:py-8 lg:py-10 animate-fade-in-up min-h-full flex flex-col relative z-10"
     @if($hasOptedIn)
         x-data="kristallKollaps3D('{{$modelPath}}', '{{$imagePath}}', {{$level}})"
         @funki-level-up.window="handleLevelUp($event.detail)"
     @endif>
    @if(!$hasOptedIn)
        <div x-data="optInScreen()"
             x-init="initAmbientSparks()"
             :class="isWarping ? 'opacity-0 scale-[0.98] pointer-events-none' : 'opacity-100 scale-100'"
             class="max-w-6xl mx-auto relative p-6 sm:p-10 lg:p-20 flex flex-col lg:flex-row items-center gap-10 lg:gap-16 transition-[opacity,transform] duration-500 ease-out will-change-[opacity,transform] mt-8 lg:mt-12 rounded-[2.5rem] bg-white/70 dark:bg-gray-900/60 backdrop-blur-xl border border-amber-500/20 dark:border-amber-400/15 shadow-[0_25px_70px_rgba(197,160,89,0.12)] dark:shadow-[0_25px_80px_rgba(0,0,0,0.65)] overflow-hidden">

            {{-- Subtile schwebende Goldene Funken im Karten-Hintergrund (Darkmode/Lightmode adaptiv) --}}
            <canvas x-ref="ambientSparksCanvas" class="absolute inset-0 w-full h-full pointer-events-none z-0 opacity-85"></canvas>
            <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-gradient-to-br from-amber-400/15 via-primary/10 to-transparent blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-gradient-to-tr from-amber-500/15 via-yellow-400/5 to-transparent blur-3xl pointer-events-none"></div>

            {{-- Vollbild "Magie aktivieren" Animation (Reine GPU-Transform-Iris + Pre-Baked Sprite-Canvas für 60/120 FPS ohne Ruckler) --}}
            <template x-teleport="body">
                <div x-show="isActivating"
                     style="display: none;"
                     class="fixed inset-0 z-[9500] pointer-events-none flex items-center justify-center overflow-hidden">

                    {{-- GPU-beschleunigter Iris-Kreis (transform: scale statt teurem clip-path + backdrop-blur!) --}}
                    <div class="fixed rounded-full pointer-events-none will-change-transform transition-transform duration-[750ms] ease-[cubic-bezier(0.22,1,0.36,1)] bg-gradient-to-br from-[#fffdf7] via-[#fdf6e4] to-[#f7e7c4] dark:from-[#06080f] dark:via-[#0b0f1c] dark:to-[#17120a]"
                         :style="`left: ${originX}px; top: ${originY}px; width: 120px; height: 120px; transform: translate3d(-50%, -50%, 0) scale(${eclipseRadius > 0 ? 38 : 0});`">
                    </div>

                    {{-- Solider Hintergrund-Layer nach Iris-Öffnung verhindert jegliches Layout-Flackern beim Livewire-Wechsel --}}
                    <div class="absolute inset-0 transition-opacity duration-300 bg-[#fdf8ed] dark:bg-[#070a14]"
                         :class="phase >= 1 ? 'opacity-100' : 'opacity-0'">
                    </div>

                    {{-- Radiale Gold-Aura im Zentrum --}}
                    <div class="absolute inset-0 transition-opacity duration-500 will-change-[opacity]"
                         :class="phase >= 1 ? 'opacity-100' : 'opacity-0'"
                         style="background: radial-gradient(circle at 50% 50%, rgba(245, 158, 11, 0.22) 0%, rgba(197, 160, 89, 0.08) 38%, transparent 70%);">
                    </div>

                    {{-- Hardware-beschleunigter Sprite-Canvas für die Goldenen Funken (Wirbel -> Supernova) --}}
                    <canvas x-ref="magicSparksCanvas"
                            class="absolute inset-0 w-full h-full z-10 transition-opacity duration-400 will-change-[opacity]"
                            :class="phase === 3 ? 'opacity-0' : 'opacity-100'"></canvas>

                    {{-- Zentrales Manufaktur-Astrolabium & Emblem --}}
                    <div class="relative z-20 flex flex-col items-center justify-center text-center px-6 transition-[opacity,transform] duration-500 transform-gpu will-change-[opacity,transform]"
                         :class="phase === 0 ? 'scale-75 opacity-0' : (phase === 1 ? 'scale-100 opacity-100' : (phase === 2 ? 'scale-105 opacity-100' : 'scale-110 opacity-0'))">

                        {{-- Heilige Geometrie / Goldene Ringe --}}
                        <div class="relative w-36 h-36 sm:w-44 sm:h-44 flex items-center justify-center mb-6">
                            {{-- Äußere Schockwelle bei Supernova (Phase 2) --}}
                            <div class="absolute inset-0 rounded-full border-2 border-amber-400/80 dark:border-amber-300/80 transition-all duration-1000 ease-out"
                                 :class="phase >= 2 ? 'scale-[2.6] opacity-0' : 'scale-75 opacity-0'"></div>
                            <div class="absolute inset-0 rounded-full border border-primary/70 transition-all duration-1000 delay-100 ease-out"
                                 :class="phase >= 2 ? 'scale-[3.4] opacity-0' : 'scale-75 opacity-0'"></div>

                            {{-- Äußerer rotierender Filigran-Ring --}}
                            <svg class="absolute inset-0 w-full h-full text-amber-600/40 dark:text-amber-400/40 animate-[spin_12s_linear_infinite]" viewBox="0 0 200 200">
                                <circle cx="100" cy="100" r="92" fill="none" stroke="currentColor" stroke-width="1" stroke-dasharray="4 8" />
                                <circle cx="100" cy="100" r="82" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="24 12 4 12" />
                                <polygon points="100,6 104,16 96,16" fill="currentColor" />
                                <polygon points="100,194 104,184 96,184" fill="currentColor" />
                                <polygon points="6,100 16,104 16,96" fill="currentColor" />
                                <polygon points="194,100 184,104 184,96" fill="currentColor" />
                            </svg>

                            {{-- Gegenläufiger innerer Runen-Ring --}}
                            <svg class="absolute inset-3 w-[calc(100%-1.5rem)] h-[calc(100%-1.5rem)] text-amber-500/60 dark:text-amber-300/60 animate-[spin_8s_linear_infinite_reverse]" viewBox="0 0 160 160">
                                <circle cx="80" cy="80" r="70" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="10 14" />
                                <rect x="29" y="29" width="102" height="102" fill="none" stroke="currentColor" stroke-width="0.75" opacity="0.45" transform="rotate(45 80 80)" />
                            </svg>

                            {{-- Zentraler Gold-Kern (Darkmode-Switch Sonne/Funken-Kristall) --}}
                            <div class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full flex items-center justify-center transition-all duration-500 shadow-[0_0_50px_rgba(245,158,11,0.55)] dark:shadow-[0_0_65px_rgba(251,191,36,0.75)] bg-gradient-to-br from-amber-300 via-amber-500 to-yellow-600 dark:from-amber-200 dark:via-amber-400 dark:to-amber-600 border-2 border-white/80 dark:border-amber-100"
                                 :class="phase >= 2 ? 'scale-110 shadow-[0_0_90px_rgba(251,191,36,0.95)]' : 'scale-95'">
                                <svg class="w-10 h-10 sm:w-12 sm:h-12 text-gray-950 drop-shadow-sm transition-transform duration-700"
                                     :class="phase >= 2 ? 'rotate-180 scale-110' : 'rotate-0 scale-100'"
                                     viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2L14.6 9.4L22 12L14.6 14.6L12 22L9.4 14.6L2 12L9.4 9.4L12 2Z" />
                                </svg>
                            </div>
                        </div>

                        {{-- Typografie-Enthüllung --}}
                        <div class="transition-all duration-500"
                             :class="phase >= 1 ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
                            <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-amber-500/15 dark:bg-amber-400/15 border border-amber-500/40 dark:border-amber-400/40 text-amber-800 dark:text-amber-300 text-[10px] sm:text-xs font-black uppercase tracking-[0.3em] mb-3 shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-300 animate-ping"></span>
                                <span x-text="phase >= 2 ? 'Seelenfunken entfesselt' : 'Seelenfunken sammeln sich...'"></span>
                            </span>
                            <h3 class="text-3xl sm:text-5xl font-serif font-black tracking-tight text-gray-900 dark:text-white drop-shadow-sm dark:drop-shadow-[0_0_25px_rgba(251,191,36,0.35)]">
                                Die Magie <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-700 dark:from-amber-200 dark:via-amber-400 dark:to-yellow-200">erwacht</span>
                            </h3>
                            <p class="mt-2 text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-300 tracking-wide transition-opacity duration-500"
                               :class="phase >= 2 ? 'opacity-100' : 'opacity-60'">
                                Dein persönlicher Gefährte & die Spiele der Manufaktur werden aktiviert…
                            </p>
                        </div>
                    </div>
                </div>
            </template>

            <div class="relative z-10 flex-1 text-center lg:text-left">
                @if(isset($profileSteps) && count($profileSteps) > 0)
                    <div class="mb-8 flex flex-col sm:flex-row flex-wrap items-center lg:items-start gap-3 p-4 sm:p-5 bg-gray-100 dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                        <span class="w-full block text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-[0.2em] font-black mb-1">Profil vervollständigen:</span>
                        @foreach($profileSteps as $step)
                            <button @click="{!! $step['action'] !!}" class="px-4 py-2 bg-red-500/10 border border-red-500/30 text-red-500 dark:text-red-400 rounded-full text-[9px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-sm dark:shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse">{{ $step['label'] }}</button>
                        @endforeach
                    </div>
                @endif
                <span class="inline-flex items-center gap-2 px-4 py-1.5 sm:px-5 sm:py-2 bg-amber-500/10 dark:bg-primary/15 text-amber-700 dark:text-primary font-black uppercase tracking-widest rounded-xl mb-4 sm:mb-6 border border-amber-500/30 dark:border-primary/30 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.2)] text-[10px] sm:text-xs">
                    <span class="text-amber-500 dark:text-amber-300">✦</span>
                    <span>Dein neues Erlebnis</span>
                </span>
                <h2 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-serif font-bold text-gray-900 dark:text-white mb-4 sm:mb-6 leading-[1.1] drop-shadow-none dark:drop-shadow-md tracking-tight">Einkaufen,<br>weit weg vom <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-600 dark:from-primary dark:via-amber-200 dark:to-amber-400">Standard.</span></h2>
                <p class="text-gray-600 dark:text-gray-400 text-sm sm:text-base md:text-lg mb-8 sm:mb-10 leading-relaxed max-w-2xl mx-auto lg:mx-0 font-medium">Willkommen in der Manufaktur! Dein Dashboard ist kein einfaches Kundenkonto mehr – es ist <strong class="text-gray-900 dark:text-white">spielerisch, interaktiv und lebendig</strong>. Begleite deinen persönlichen 3D-Gefährten auf seiner Reise.</p>
                
                <div class="mb-6 flex flex-col gap-2 max-w-xl mx-auto lg:mx-0 text-left">
                    <label class="flex items-start gap-3 cursor-pointer group">
                        <div class="relative flex items-center justify-center shrink-0 mt-0.5">
                            <input type="checkbox" x-model="agreedToTerms" class="peer appearance-none w-5 h-5 border-2 border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-900 checked:bg-primary checked:border-primary transition-all cursor-pointer">
                            <svg class="absolute w-3 h-3 text-gray-900 opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-gray-300 transition-colors leading-relaxed">
                            Ich habe die <a href="{{ route('agb') }}#agb-gamification" target="_blank" @click.stop class="text-amber-700 dark:text-primary hover:underline hover:text-amber-800 dark:hover:text-amber-400 relative z-20 font-semibold">Spielregeln & Teilnahmebedingungen</a> gelesen und stimme diesen zu. Zudem nehme ich die <a href="{{ route('datenschutz') }}#datenschutz-gamification" target="_blank" @click.stop class="text-amber-700 dark:text-primary hover:underline hover:text-amber-800 dark:hover:text-amber-400 relative z-20 font-semibold">Datenschutzhinweise</a> bezüglich der Speicherung meines Spielfortschritts zur Kenntnis.
                        </span>
                    </label>
                </div>

                <button :disabled="!agreedToTerms || isActivating"
                        @click="if(agreedToTerms && !isActivating) triggerEpicStart($event)"
                        class="w-full sm:w-auto group relative px-8 sm:px-12 py-4 sm:py-5 bg-gradient-to-r from-amber-400 via-primary to-amber-500 hover:from-amber-300 hover:via-primary-light hover:to-amber-400 text-gray-950 border border-amber-300/80 disabled:from-gray-200 disabled:via-gray-200 disabled:to-gray-200 dark:disabled:from-gray-800 dark:disabled:via-gray-800 dark:disabled:to-gray-800 disabled:border-transparent disabled:text-gray-400 dark:disabled:text-gray-500 rounded-2xl font-black uppercase tracking-widest text-xs sm:text-sm shadow-[0_10px_30px_rgba(245,158,11,0.35)] dark:shadow-[0_0_35px_rgba(251,191,36,0.45)] disabled:shadow-none transition-all duration-300 flex items-center justify-center gap-3.5 overflow-hidden transform-gpu mx-auto lg:mx-0 disabled:cursor-not-allowed hover:scale-[1.03] hover:shadow-[0_15px_40px_rgba(245,158,11,0.5)] dark:hover:shadow-[0_0_50px_rgba(251,191,36,0.7)] disabled:hover:scale-100 cursor-pointer">
                    <div x-show="agreedToTerms" class="absolute inset-0 bg-gradient-to-r from-transparent via-white/50 to-transparent transform -skew-x-12 -translate-x-[150%] group-hover:translate-x-[150%] transition-transform duration-1000 ease-in-out"></div>
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-950 disabled:text-gray-400 transition-transform duration-500 group-hover:rotate-90 group-hover:scale-125" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L14.4 9.6L22 12L14.4 14.4L12 22L9.6 14.4L2 12L9.6 9.6L12 2Z" />
                    </svg>
                    <span class="relative z-10">Magie aktivieren</span>
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 relative z-10 group-hover:translate-x-1.5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.8" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
            <div class="relative z-10 w-full lg:w-5/12 flex justify-center perspective-1000 mt-8 lg:mt-0 shrink-0">
                <div class="relative w-56 h-56 sm:w-72 sm:h-72 md:w-96 md:h-96 transform-gpu hover:rotate-y-12 hover:rotate-x-12 transition-transform duration-700 ease-out shrink-0">
                    <div class="absolute inset-0 bg-gradient-to-tr from-amber-500/25 via-primary/20 to-yellow-300/20 rounded-full blur-[40px] md:blur-[55px] animate-pulse"></div>
                    <img src="{{asset('shop/customer/gamification/models/images/original/funki_lvl_5_apprentice.png')}}" draggable="false" class="relative w-full h-full object-contain drop-shadow-[0_20px_35px_rgba(197,160,89,0.35)] dark:drop-shadow-[0_20px_35px_rgba(0,0,0,0.85)] animate-[float_6s_ease-in-out_infinite] pointer-events-none select-none z-10">
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
                originX: window.innerWidth / 2,
                originY: window.innerHeight / 2,
                eclipseRadius: 0,
                _ambientRaf: null,
                _magicRaf: null,

                isDark() {
                    return document.documentElement.classList.contains('dark');
                },

                initAmbientSparks() {
                    const canvas = this.$refs.ambientSparksCanvas;
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    let w = 0, h = 0;
                    const dpr = Math.min(window.devicePixelRatio || 1, 2);

                    const resize = () => {
                        const rect = canvas.parentElement.getBoundingClientRect();
                        w = Math.max(300, rect.width);
                        h = Math.max(300, rect.height);
                        canvas.width = w * dpr;
                        canvas.height = h * dpr;
                        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                    };
                    resize();
                    window.addEventListener('resize', resize);

                    const sparks = Array.from({ length: 38 }, () => ({
                        x: Math.random() * w,
                        y: Math.random() * h,
                        r: 1 + Math.random() * 2.4,
                        vx: (Math.random() - 0.5) * 0.35,
                        vy: -(0.25 + Math.random() * 0.65),
                        phase: Math.random() * Math.PI * 2,
                        speed: 0.02 + Math.random() * 0.03,
                        isStar: Math.random() > 0.55
                    }));

                    const drawStar = (cx, cy, radius, alpha, dark) => {
                        ctx.save();
                        ctx.translate(cx, cy);
                        ctx.fillStyle = dark
                            ? `rgba(253, 224, 71, ${alpha})`
                            : `rgba(217, 119, 6, ${alpha})`;
                        ctx.beginPath();
                        for (let i = 0; i < 4; i++) {
                            ctx.lineTo(Math.cos((i * Math.PI) / 2) * radius * 2.2, Math.sin((i * Math.PI) / 2) * radius * 2.2);
                            ctx.lineTo(Math.cos(((i + 0.5) * Math.PI) / 2) * radius * 0.55, Math.sin(((i + 0.5) * Math.PI) / 2) * radius * 0.55);
                        }
                        ctx.closePath();
                        ctx.fill();
                        ctx.restore();
                    };

                    const renderAmbient = () => {
                        if (this.isActivating) return;
                        ctx.clearRect(0, 0, w, h);
                        const dark = this.isDark();

                        for (const s of sparks) {
                            s.x += s.vx + Math.sin(s.phase) * 0.15;
                            s.y += s.vy;
                            s.phase += s.speed;

                            if (s.y < -10) { s.y = h + 10; s.x = Math.random() * w; }
                            if (s.x < -10) s.x = w + 10;
                            if (s.x > w + 10) s.x = -10;

                            const alpha = (0.25 + 0.55 * (0.5 + 0.5 * Math.sin(s.phase))) * (this.agreedToTerms ? 1.0 : 0.65);

                            if (s.isStar) {
                                drawStar(s.x, s.y, s.r, alpha, dark);
                            } else {
                                const grad = ctx.createRadialGradient(s.x, s.y, 0, s.x, s.y, s.r * 3);
                                if (dark) {
                                    grad.addColorStop(0, `rgba(254, 240, 138, ${alpha})`);
                                    grad.addColorStop(0.45, `rgba(245, 158, 11, ${alpha * 0.6})`);
                                    grad.addColorStop(1, 'rgba(245, 158, 11, 0)');
                                } else {
                                    grad.addColorStop(0, `rgba(217, 119, 6, ${alpha})`);
                                    grad.addColorStop(0.5, `rgba(197, 160, 89, ${alpha * 0.55})`);
                                    grad.addColorStop(1, 'rgba(197, 160, 89, 0)');
                                }
                                ctx.fillStyle = grad;
                                ctx.beginPath();
                                ctx.arc(s.x, s.y, s.r * 3, 0, Math.PI * 2);
                                ctx.fill();
                            }
                        }
                        this._ambientRaf = requestAnimationFrame(renderAmbient);
                    };
                    this._ambientRaf = requestAnimationFrame(renderAmbient);
                },

                triggerEpicStart(event) {
                    if (event && event.currentTarget) {
                        const rect = event.currentTarget.getBoundingClientRect();
                        this.originX = rect.left + rect.width / 2;
                        this.originY = rect.top + rect.height / 2;
                    } else {
                        this.originX = window.innerWidth / 2;
                        this.originY = window.innerHeight / 2;
                    }

                    // Sofort alle Hintergrund-Canvases pausieren für 100% flüssige 60/120 FPS
                    window.__PauseBackgroundCanvas = true;
                    if (this._ambientRaf) {
                        cancelAnimationFrame(this._ambientRaf);
                        this._ambientRaf = null;
                    }

                    this.isWarping = true;
                    this.isActivating = true;
                    this.phase = 0;
                    this.eclipseRadius = 0;

                    window.dispatchEvent(new CustomEvent('warp-started'));

                    this.$nextTick(() => {
                        this.startGoldenSparksSequence();
                        requestAnimationFrame(() => {
                            this.eclipseRadius = 150;
                            this.phase = 1;
                        });
                    });

                    // Phase 2: Goldene Supernova-Eruption im Zentrum
                    setTimeout(() => {
                        this.phase = 2;
                    }, 950);

                    // Phase 3: Sanfter Seiden-Übergang & Livewire Opt-In
                    setTimeout(() => {
                        this.phase = 3;
                    }, 1950);

                    setTimeout(() => {
                        if (this._magicRaf) {
                            cancelAnimationFrame(this._magicRaf);
                            this._magicRaf = null;
                        }
                        window.__PauseBackgroundCanvas = false;
                        window.sessionStorage.setItem('funki_just_activated', 'true');
                        this.$wire.optIn();
                    }, 2200);
                },

                startGoldenSparksSequence() {
                    const canvas = this.$refs.magicSparksCanvas;
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d', { alpha: true });
                    const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
                    const w = window.innerWidth;
                    const h = window.innerHeight;
                    canvas.width = w * dpr;
                    canvas.height = h * dpr;
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                    const centerX = w / 2;
                    const centerY = h / 2;
                    const dark = this.isDark();

                    const palette = dark
                        ? ['#FEF08A', '#FDE047', '#FBBF24', '#F59E0B', '#C5A059', '#FFFBEB']
                        : ['#B45309', '#D97706', '#F59E0B', '#C5A059', '#92400E', '#FBBF24'];

                    // Pre-bake Star & Orb Sprites einmalig auf Offscreen-Canvas (verhindert 200+ Pfad-Berechnungen pro Frame!)
                    const buildSprite = (color, isStar) => {
                        const sc = document.createElement('canvas');
                        sc.width = 48;
                        sc.height = 48;
                        const sctx = sc.getContext('2d');
                        const grad = sctx.createRadialGradient(24, 24, 0, 24, 24, 22);
                        grad.addColorStop(0, '#FFFFFF');
                        grad.addColorStop(0.28, color);
                        grad.addColorStop(1, 'rgba(0,0,0,0)');
                        sctx.fillStyle = grad;
                        sctx.beginPath();
                        sctx.arc(24, 24, 22, 0, Math.PI * 2);
                        sctx.fill();

                        if (isStar) {
                            sctx.fillStyle = '#FFFFFF';
                            sctx.beginPath();
                            for (let j = 0; j < 4; j++) {
                                sctx.lineTo(24 + Math.cos((j * Math.PI) / 2) * 18, 24 + Math.sin((j * Math.PI) / 2) * 18);
                                sctx.lineTo(24 + Math.cos(((j + 0.5) * Math.PI) / 2) * 4, 24 + Math.sin(((j + 0.5) * Math.PI) / 2) * 4);
                            }
                            sctx.closePath();
                            sctx.fill();
                        }
                        return sc;
                    };

                    const starSprites = palette.map(c => buildSprite(c, true));
                    const orbSprites = palette.map(c => buildSprite(c, false));

                    const particles = [];
                    const totalSparks = 96;

                    for (let i = 0; i < totalSparks; i++) {
                        const fromButton = i < 42;
                        const angle = Math.random() * Math.PI * 2;
                        const dist = fromButton
                            ? (10 + Math.random() * 85)
                            : (100 + Math.random() * Math.max(w, h) * 0.5);
                        const startX = (fromButton ? this.originX : centerX) + Math.cos(angle) * dist;
                        const startY = (fromButton ? this.originY : centerY) + Math.sin(angle) * dist;

                        const burstAngle = (i / totalSparks) * Math.PI * 2 + (Math.random() - 0.5) * 0.35;
                        const burstSpeed = 4.5 + Math.random() * 14.5;
                        const colorIdx = i % palette.length;
                        const isStar = i % 3 === 0;

                        particles.push({
                            x: startX,
                            y: startY,
                            prevX: startX,
                            prevY: startY,
                            orbitAngle: Math.atan2(startY - centerY, startX - centerX),
                            orbitRadius: Math.hypot(startX - centerX, startY - centerY),
                            orbitSpeed: (0.048 + Math.random() * 0.055) * (i % 2 === 0 ? 1 : -1),
                            vx: Math.cos(burstAngle) * burstSpeed,
                            vy: Math.sin(burstAngle) * burstSpeed,
                            drawSize: (isStar ? 18 : 14) + Math.random() * 16,
                            sprite: isStar ? starSprites[colorIdx] : orbSprites[colorIdx],
                            color: palette[colorIdx],
                            alpha: 0.35 + Math.random() * 0.65,
                            twinkleSpeed: 0.09 + Math.random() * 0.12,
                            twinklePhase: Math.random() * Math.PI * 2,
                            exploded: false
                        });
                    }

                    const startTime = performance.now();
                    let lastNow = startTime;

                    const animate = (now) => {
                        const elapsed = now - startTime;
                        const dt = Math.min(2.2, Math.max(0.5, (now - lastNow) / 16.667));
                        lastNow = now;

                        ctx.clearRect(0, 0, w, h);
                        ctx.save();
                        if (dark) {
                            ctx.globalCompositeOperation = 'lighter';
                        }

                        const isSupernova = elapsed >= 950;

                        // Gebündelter Motion-Trail-Pfad für minimale GPU-Draw-Calls
                        ctx.beginPath();
                        ctx.strokeStyle = dark ? 'rgba(253, 224, 71, 0.38)' : 'rgba(217, 119, 6, 0.32)';
                        ctx.lineWidth = 1.6;
                        ctx.lineCap = 'round';

                        for (let i = 0; i < totalSparks; i++) {
                            const p = particles[i];
                            p.prevX = p.x;
                            p.prevY = p.y;
                            p.twinklePhase += p.twinkleSpeed * dt;

                            if (!isSupernova) {
                                p.orbitAngle += p.orbitSpeed * dt;
                                p.orbitRadius = Math.max(6, p.orbitRadius * Math.pow(0.942, dt));
                                const pull = 1 - Math.pow(0.76, dt);
                                p.x += (centerX + Math.cos(p.orbitAngle) * p.orbitRadius - p.x) * pull;
                                p.y += (centerY + Math.sin(p.orbitAngle) * p.orbitRadius - p.y) * pull;
                            } else {
                                if (!p.exploded) {
                                    p.exploded = true;
                                    p.x = centerX + (Math.random() - 0.5) * 14;
                                    p.y = centerY + (Math.random() - 0.5) * 14;
                                    p.prevX = p.x;
                                    p.prevY = p.y;
                                }
                                p.x += p.vx * dt;
                                p.y += p.vy * dt;
                                const drag = Math.pow(0.965, dt);
                                p.vx *= drag;
                                p.vy = p.vy * drag - 0.035 * dt;
                            }

                            ctx.moveTo(p.prevX, p.prevY);
                            ctx.lineTo(p.x, p.y);
                        }
                        ctx.stroke();

                        // Schnelles Hardware-Blitting der vorgerenderten Funken-Sprites
                        for (let i = 0; i < totalSparks; i++) {
                            const p = particles[i];
                            const twinkle = 0.6 + 0.4 * Math.sin(p.twinklePhase);
                            ctx.globalAlpha = p.alpha * twinkle;
                            const half = p.drawSize * 0.5;
                            ctx.drawImage(p.sprite, p.x - half, p.y - half, p.drawSize, p.drawSize);
                        }

                        ctx.restore();

                        if (elapsed < 2250) {
                            this._magicRaf = requestAnimationFrame(animate);
                        }
                    };

                    this._magicRaf = requestAnimationFrame(animate);
                }
            };
        };
    </script>

    @else


    {{-- AUDIO BGM --}}
    <audio x-ref="bgmAudio" src="{{ asset('shop/customer/gamification/music/cristall_kollaps_bg_music.mp3') }}" loop preload="auto"></audio>

    {{-- 1. 60 FPS COCKPIT-LADEANIMATION (VERHINDERT GRAFIK-FLACKERN & LAYOUT-SHIFTS) --}}
    <div x-show="!cockpitReady"
         x-transition:leave="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-400"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-[0.98]"
         class="relative w-full min-h-[68vh] flex flex-col items-center justify-center py-16 px-4 z-50 select-none">

        {{-- Goldene Top-Progress-Bar --}}
        <div class="fixed top-0 left-0 right-0 h-1 bg-gray-200/40 dark:bg-gray-900/60 z-[9990] overflow-hidden">
            <div class="h-full bg-gradient-to-r from-amber-500 via-[#C5A059] to-yellow-300 transition-[width] duration-150 ease-out shadow-[0_0_15px_rgba(197,160,89,0.85)]"
                 :style="`width: ${cockpitLoadProgress}%`"></div>
        </div>

        <div class="relative w-full max-w-md rounded-[2.25rem] bg-white/90 dark:bg-[#11131a]/95 backdrop-blur-2xl border border-gray-200/90 dark:border-[#C5A059]/30 p-8 sm:p-10 shadow-xl dark:shadow-[0_25px_70px_rgba(0,0,0,0.75)] flex flex-col items-center text-center">
            <div class="relative w-24 h-24 flex items-center justify-center mb-6">
                <svg class="absolute inset-0 w-full h-full text-[#C5A059]/45 animate-[spin_9s_linear_infinite]" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="54" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="6 8" />
                    <circle cx="60" cy="60" r="44" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="18 10" />
                </svg>
                <svg class="absolute inset-0 w-full h-full -rotate-90" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="50" fill="none" stroke="#C5A059" stroke-width="3" stroke-linecap="round"
                            stroke-dasharray="314.16"
                            :stroke-dashoffset="314.16 - (cockpitLoadProgress / 100) * 314.16"
                            class="transition-all duration-150 ease-out" />
                </svg>
                <span class="text-lg font-serif font-black text-gray-900 dark:text-[#f5dca0]" x-text="cockpitLoadProgress + '%'">12%</span>
            </div>

            <span class="text-[10px] font-black uppercase tracking-[0.28em] text-[#C5A059] mb-1.5">Deine Evolution</span>
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white">Spieler-Cockpit lädt…</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">3D-Gefährte, Telemetrie & Grafik-Systeme werden synchronisiert</p>

            <div class="w-full h-1.5 bg-gray-100 dark:bg-gray-900 rounded-full overflow-hidden mt-6 border border-gray-200/70 dark:border-gray-800">
                <div class="h-full bg-gradient-to-r from-amber-500 via-[#C5A059] to-yellow-300 transition-[width] duration-150 ease-out rounded-full"
                     :style="`width: ${cockpitLoadProgress}%`"></div>
            </div>
        </div>
    </div>

    {{-- 2. HAUPT-STAGE DES SPIELER-COCKPITS (MATT, MODERN, CLEAN, 60 FPS) --}}
    <div :class="cockpitReady ? 'sf-cockpit-stage-ready' : 'sf-cockpit-stage-hidden'" class="w-full flex-1 flex flex-col">

        {{-- SEITEN-HEADER: DEINE EVOLUTION -> SPIELER-COCKPIT --}}
        <div class="mb-4 sm:mb-8 flex flex-row items-center sm:items-end justify-between gap-3 border-b border-gray-200/80 dark:border-gray-800/80 pb-3.5 sm:pb-5">
            <div class="min-w-0">
                <div class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-0.5 sm:py-1 rounded-full bg-[#C5A059]/12 border border-[#C5A059]/35 text-amber-800 dark:text-[#f5dca0] text-[9px] sm:text-[10px] font-black uppercase tracking-[0.24em] mb-1.5 sm:mb-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#C5A059] animate-pulse"></span>
                    <span>Deine Evolution</span>
                </div>
                <h1 class="text-2xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 dark:text-white tracking-tight leading-none">
                    Spieler-Cockpit
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1.5 sm:mt-2 text-[11px] sm:text-sm font-medium leading-snug">
                    Klicke auf eine Smart-Kachel am Helden oder starte direkt in die Manufaktur-Spiele
                </p>
            </div>

            {{-- COCKPIT HEADER-STEUERUNG (UI-SOUNDS & SCHNELL-ZURÜCK) --}}
            <div class="flex flex-wrap items-center justify-end gap-2 shrink-0">
                <button type="button"
                        @click="toggleCockpitSound()"
                        :title="cockpitSoundMuted ? 'Cockpit-Sounds aktivieren' : 'Cockpit-Sounds stummschalten'"
                        class="px-3 py-2 sm:px-3.5 sm:py-2.5 rounded-2xl bg-white dark:bg-[#13151c] border border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/60 text-gray-700 dark:text-gray-300 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all flex items-center gap-1.5 sm:gap-2 cursor-pointer shadow-sm active:scale-95">
                    <svg x-show="!cockpitSoundMuted" class="w-4 h-4 text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5 12h4l4-4v16l-4-4H5a2 2 0 01-2-2v-4a2 2 0 012-2z" />
                    </svg>
                    <svg x-show="cockpitSoundMuted" style="display: none;" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                    </svg>
                    <span class="hidden xs:inline sm:inline" x-text="cockpitSoundMuted ? 'Ton Aus' : 'UI-Ton'">UI-Ton</span>
                </button>

                <button type="button"
                        x-show="activeCockpitPanel !== null || activeGame !== null"
                        x-cloak
                        @click="if (activeGame !== null) { closeGameArena(); } else { switchCockpitPanel(null); }"
                        class="px-3.5 py-2 sm:px-4 sm:py-2.5 rounded-2xl bg-[#C5A059] hover:bg-amber-400 text-gray-950 font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all flex items-center gap-1.5 sm:gap-2 cursor-pointer shadow-sm active:scale-95">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span class="hidden sm:inline">Cockpit-Übersicht</span>
                    <span class="sm:hidden">Zurück</span>
                </button>
            </div>
        </div>

        {{-- =====================================================================
             A. 60 FPS ORBITALER HELDEN-KERN MIT SMART-KACHELN (ADMIN FOCUS-MODE)
             ===================================================================== --}}
        @php
            $megaRank = $titlesData['mega_title']['rank'] ?? 0;
            $megaShadow = match(true) {
                $megaRank == 1 => 'shadow-md dark:shadow-[0_0_20px_rgba(255,255,255,0.05)]',
                $megaRank == 2 || $megaRank == 3 => 'shadow-md dark:shadow-[0_0_40px_rgba(59,130,246,0.2)] border-blue-500/30',
                $megaRank == 4 || $megaRank == 5 => 'shadow-md dark:shadow-[0_0_60px_rgba(250,204,21,0.35)] border-amber-500/50',
                $megaRank == 6 => 'shadow-md dark:shadow-[0_0_80px_rgba(34,211,238,0.5)] border-cyan-400 ring-4 ring-cyan-400/30',
                default => 'shadow-md dark:shadow-[0_0_30px_rgba(0,0,0,0.65)]'
            };
            $levelBorder = match(true) {
                $level < 4 => 'border-2 border-gray-300 dark:border-gray-800',
                $level < 7 => 'border-[3px] border-gray-400 dark:border-gray-600',
                $level < 10 => 'border-[3px] border-primary',
                $level >= 10 => 'border-[4px] border-amber-400',
                default => 'border-2 border-gray-300 dark:border-gray-800'
            };
            $unlockedCouponsCount = count($unlockedCoupons ?? []);
            $totalMilestonesCount = max(1, count($milestonesConfig ?? []));
            $legendePct = min(100, max(12, round(($unlockedCouponsCount / $totalMilestonesCount) * 100)));
            $prestigePct = min(100, max(10, $level));
            $unlockedSkinsCount = count($unlockedSkins ?? ['default']);
            $totalSkinsCount = max(1, count($shipSkins ?? ['default' => []]));
            $hangarPct = min(100, max(18, round(($unlockedSkinsCount / $totalSkinsCount) * 100)));
            $energyPct = $maxEnergy > 0 ? min(100, max(10, round(($energyBalance / $maxEnergy) * 100))) : 100;
            $rCirc = 2 * pi() * 34;

            // Linke Orbit-Satelliten am Helden: ENERGIE + INFO + LEGENDE (Jeweils 1 prägnantes Wort + 1 Satz Hover-Erklärung!)
            $leftHeroNodes = [
                [
                    'key' => 'energie',
                    'is_status' => true,
                    'word' => 'ENERGIE',
                    'value' => $energyBalance . '/' . $maxEnergy,
                    'unit' => 'LADUNG',
                    'ring_pct' => $energyPct,
                    'color' => '#3b82f6',
                    'metric' => 'SEELEN-ENERGIE',
                    'status' => $energyBalance > 0 ? '● EINSATZBEREIT' : 'AUFLADUNG LÄUFT',
                    'desc' => 'Deine Seelen-Energie lädt sich automatisch wieder auf und wird benötigt, um Minispiele im Cockpit zu starten.',
                    'y' => 190,
                    'path' => 'M 500 500 C 400 500, 345 190, 250 190',
                    'dur' => '2.6s',
                ],
                [
                    'key' => 'info',
                    'is_status' => false,
                    'word' => 'GUIDE',
                    'value' => 'INFO',
                    'unit' => 'SYSTEM',
                    'ring_pct' => 100,
                    'color' => '#C5A059',
                    'metric' => 'WAS PASSIERT HIER?',
                    'status' => 'ERKLÄRUNG ÖFFNEN ↘',
                    'desc' => 'Hier erfährst du kompakt, wie du mit Funki aufsteigst, Funken sammelst und echte Manufaktur-Rabatte freischaltest.',
                    'y' => 500,
                    'path' => 'M 500 500 C 400 500, 345 500, 250 500',
                    'dur' => '3.1s',
                ],
                [
                    'key' => 'legende',
                    'is_status' => false,
                    'word' => 'LEGENDE',
                    'value' => $unlockedCouponsCount . '/' . $totalMilestonesCount,
                    'unit' => 'SCHÄTZE',
                    'ring_pct' => $legendePct,
                    'color' => '#C5A059',
                    'metric' => 'PFAD ZUR LEGENDE',
                    'status' => 'GUTSCHEINE & CODES ↘',
                    'desc' => 'Auf dem Pfad zur Legende schaltest du von Level 1 bis 10 exklusive Gutscheincodes und echte Shop-Vorteile frei.',
                    'y' => 810,
                    'path' => 'M 500 500 C 400 500, 345 810, 250 810',
                    'dur' => '2.9s',
                ],
            ];

            // Rechte Orbit-Satelliten am Helden: GUTHABEN + PRESTIGE + HANGAR (Jeweils 1 prägnantes Wort + 1 Satz Hover-Erklärung!)
            $rightHeroNodes = [
                [
                    'key' => 'guthaben',
                    'is_status' => true,
                    'word' => 'GUTHABEN',
                    'value' => number_format($balance, 0, ',', '.'),
                    'unit' => 'FUNKEN',
                    'ring_pct' => min(100, max(18, $progressPercentage)),
                    'color' => '#C5A059',
                    'metric' => 'SEELENFUNKEN-KONTO',
                    'status' => $canUpgrade ? '★ LEVEL-UP BEREIT!' : ('NOCH ' . $missingSparks . ' F. BIS UPGRADE'),
                    'desc' => 'Dein aktuelles Seelenfunken-Konto für Level-Aufstiege, 3D-Raumschiffe, Schlachtschiffe und Ruhmes-Auren.',
                    'y' => 190,
                    'path' => 'M 500 500 C 600 500, 655 190, 750 190',
                    'dur' => '2.7s',
                ],
                [
                    'key' => 'prestige',
                    'is_status' => false,
                    'word' => 'PRESTIGE',
                    'value' => $level . '/100',
                    'unit' => 'STUFE',
                    'ring_pct' => $prestigePct,
                    'color' => '#f59e0b',
                    'metric' => 'AUFSTIEG LVL 11–100',
                    'status' => 'PRESTIGE-EMBLEME ↙',
                    'desc' => 'Ab Level 11 steigst du bis Level 100 im Prestige-Rang auf und erringst seltene Ehren-Embleme für die Ruhmeshalle.',
                    'y' => 500,
                    'path' => 'M 500 500 C 600 500, 655 500, 750 500',
                    'dur' => '3.2s',
                ],
                [
                    'key' => 'hangar',
                    'is_status' => false,
                    'word' => 'HANGAR',
                    'value' => $unlockedSkinsCount . '/' . $totalSkinsCount,
                    'unit' => 'FLOTTE',
                    'ring_pct' => $hangarPct,
                    'color' => '#C5A059',
                    'metric' => 'FLOTTE, AUREN & MOTTO',
                    'status' => '3D-SKINS & INPUTS ↙',
                    'desc' => 'Im Seelen-Hangar rüstest du 3D-Jäger, epische Schlachtschiffe, Ruhmes-Auren und deinen aktiven Piloten-Kampfruf aus.',
                    'y' => 810,
                    'path' => 'M 500 500 C 600 500, 655 810, 750 810',
                    'dur' => '2.8s',
                ],
            ];
        @endphp

        <div x-show="!activeGame"
             x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-500"
             x-transition:enter-start="opacity-0 scale-[0.97]"
             x-transition:enter-end="opacity-100 scale-100"
             class="w-full mb-10">

            {{-- 1. DESKTOP ORBITAL-STAGE (>= 1024px) WIE AUF DEM ADMIN DASHBOARD (FOKUS-MODUS) --}}
            <div class="hidden lg:flex relative w-full max-w-[1260px] mx-auto min-h-[590px] items-center justify-center select-none py-6">

                {{-- SVG ENERGIESTRÖME VOM HELDEN-KERN (500, 500) ZU DEN 6 SMART-SATELLITEN --}}
                <svg class="absolute inset-0 w-full h-full pointer-events-none z-0 overflow-visible"
                     viewBox="0 0 1000 1000" preserveAspectRatio="none">
                    @foreach(array_merge($leftHeroNodes, $rightHeroNodes) as $idx => $node)
                        <g>
                            <path d="{{ $node['path'] }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="1.4" stroke-opacity="0.22" />
                            <path d="{{ $node['path'] }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="2.6" stroke-linecap="round"
                                  class="sf-energy-stream"
                                  style="animation-delay: -{{ round($idx * 0.42, 2) }}s; filter: drop-shadow(0 0 6px {{ $node['color'] }});" />
                            <circle r="3.4" fill="{{ $node['color'] }}" style="filter: drop-shadow(0 0 8px {{ $node['color'] }});">
                                <animateMotion dur="{{ $node['dur'] }}" repeatCount="indefinite" path="{{ $node['path'] }}" />
                            </circle>
                        </g>
                    @endforeach
                </svg>

                {{-- LINKE 3 ORBIT-KACHELN (ENERGIE, GUIDE, LEGENDE) --}}
                @foreach($leftHeroNodes as $idx => $node)
                    @php
                        $rOffset = $rCirc - (($node['ring_pct'] / 100) * $rCirc);
                        $yPct = round($node['y'] / 10, 2);
                    @endphp
                    <div style="left: 25%; top: {{ $yPct }}%; transform: translate(-100%, -50%);"
                         @mouseenter="window.sfCockpitSound && window.sfCockpitSound('hover'); hoveredNodeTitle = '{{ $node['word'] }}'; hoveredNodeDesc = '{{ $node['desc'] }}'"
                         @mouseleave="hoveredNodeTitle = null; hoveredNodeDesc = null"
                         @if($node['is_status'])
                             @click="window.sfCockpitSound && window.sfCockpitSound('click'); document.getElementById('sf-cockpit-focus-stage')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })"
                         @else
                             @click="switchCockpitPanel('{{ $node['key'] }}')"
                         @endif
                         class="absolute z-20 hover:z-50 flex items-center flex-row-reverse group cursor-pointer transition-transform duration-300 hover:scale-[1.04]">

                        <div class="sf-floating-satellite relative flex items-center flex-row-reverse px-3.5 py-2.5 rounded-3xl transition-all duration-300 border"
                             :class="activeCockpitPanel === '{{ $node['key'] }}'
                                 ? 'bg-white dark:bg-[#161821] border-[#C5A059] ring-2 ring-[#C5A059]/40 shadow-[0_10px_30px_rgba(197,160,89,0.28)] scale-[1.03]'
                                 : 'bg-white/90 dark:bg-[#12141c]/90 backdrop-blur-md border-gray-200/90 dark:border-gray-800/90 hover:border-[#C5A059]/60 shadow-sm dark:shadow-[0_10px_30px_rgba(0,0,0,0.45)]'"
                             style="animation-delay: -{{ round($idx * 0.7, 2) }}s;">

                            {{-- Mini-Progress-Ring mit Wert --}}
                            <div class="relative w-18 h-18 flex items-center justify-center shrink-0 rounded-full bg-gray-50 dark:bg-[#0c0e14]">
                                <svg class="w-18 h-18 overflow-visible" viewBox="0 0 84 84">
                                    <circle cx="42" cy="42" r="34" fill="none" stroke="currentColor" stroke-width="4.5" class="text-gray-200 dark:text-gray-800" />
                                    <circle cx="42" cy="42" r="34" fill="none"
                                            stroke="{{ $node['color'] }}"
                                            stroke-width="4.5"
                                            stroke-dasharray="{{ $rCirc }}"
                                            stroke-dashoffset="{{ $rOffset }}"
                                            stroke-linecap="round"
                                            transform="rotate(-90 42 42)" />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-1">
                                    <span class="text-xs font-black text-gray-900 dark:text-white tracking-tight leading-none">{{ $node['value'] }}</span>
                                    <span class="text-[7px] font-black uppercase tracking-widest text-gray-400 mt-1">{{ $node['unit'] }}</span>
                                </div>
                            </div>

                            <div class="w-5 h-0.5 shrink-0 rounded-full mx-2"
                                 style="background: linear-gradient(90deg, transparent, {{ $node['color'] }});"></div>

                            {{-- 1-WORT HAUPTTITEL & STATUS --}}
                            <div class="text-right pr-2 w-40 xl:w-44">
                                <div class="text-xs xl:text-sm font-black uppercase tracking-[0.2em] text-gray-900 dark:text-white group-hover:text-[#C5A059] transition-colors truncate">
                                    {{ $node['word'] }}
                                </div>
                                <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    {{ $node['metric'] }}
                                </div>
                                <div class="text-[9px] font-black uppercase tracking-widest mt-1 truncate"
                                     style="color: {{ $node['color'] }};"
                                     x-text="activeCockpitPanel === '{{ $node['key'] }}' ? '✓ AKTIV GEÖFFNET' : '{{ $node['status'] }}'">
                                    {{ $node['status'] }}
                                </div>
                            </div>

                            {{-- HOVER-INFO-TOOLTIP (1 SATZ ERKLÄRUNG DIREKT AN DER KACHEL) --}}
                            <div class="pointer-events-none opacity-0 group-hover:opacity-100 translate-y-1 group-hover:translate-y-0 transition-all duration-200 absolute right-0 {{ $node['y'] > 700 ? 'bottom-full mb-2.5' : 'top-full mt-2.5' }} w-72 p-3 rounded-2xl bg-gray-950/95 text-white border border-[#C5A059]/55 shadow-[0_15px_35px_rgba(0,0,0,0.65)] text-left z-50">
                                <div class="text-[9px] font-black uppercase tracking-widest text-[#C5A059] mb-1 flex items-center gap-1.5">
                                    <span>ℹ️</span>
                                    <span>{{ $node['word'] }} • {{ $node['metric'] }}</span>
                                </div>
                                <p class="text-[11px] text-gray-200 leading-relaxed font-medium">{{ $node['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- RECHTE 3 ORBIT-KACHELN (GUTHABEN, PRESTIGE, HANGAR) --}}
                @foreach($rightHeroNodes as $idx => $node)
                    @php
                        $rOffset = $rCirc - (($node['ring_pct'] / 100) * $rCirc);
                        $yPct = round($node['y'] / 10, 2);
                    @endphp
                    <div style="left: 75%; top: {{ $yPct }}%; transform: translate(0%, -50%);"
                         @mouseenter="window.sfCockpitSound && window.sfCockpitSound('hover'); hoveredNodeTitle = '{{ $node['word'] }}'; hoveredNodeDesc = '{{ $node['desc'] }}'"
                         @mouseleave="hoveredNodeTitle = null; hoveredNodeDesc = null"
                         @if($node['is_status'])
                             @click="window.sfCockpitSound && window.sfCockpitSound('click'); open3DModal()"
                         @else
                             @click="switchCockpitPanel('{{ $node['key'] }}')"
                         @endif
                         class="absolute z-20 hover:z-50 flex items-center group cursor-pointer transition-transform duration-300 hover:scale-[1.04]">

                        <div class="sf-floating-satellite relative flex items-center px-3.5 py-2.5 rounded-3xl transition-all duration-300 border"
                             :class="activeCockpitPanel === '{{ $node['key'] }}'
                                 ? 'bg-white dark:bg-[#161821] border-[#C5A059] ring-2 ring-[#C5A059]/40 shadow-[0_10px_30px_rgba(197,160,89,0.28)] scale-[1.03]'
                                 : 'bg-white/90 dark:bg-[#12141c]/90 backdrop-blur-md border-gray-200/90 dark:border-gray-800/90 hover:border-[#C5A059]/60 shadow-sm dark:shadow-[0_10px_30px_rgba(0,0,0,0.45)]'"
                             style="animation-delay: -{{ round(($idx + 2) * 0.65, 2) }}s;">

                            {{-- Mini-Progress-Ring mit Wert --}}
                            <div class="relative w-18 h-18 flex items-center justify-center shrink-0 rounded-full bg-gray-50 dark:bg-[#0c0e14]">
                                <svg class="w-18 h-18 overflow-visible" viewBox="0 0 84 84">
                                    <circle cx="42" cy="42" r="34" fill="none" stroke="currentColor" stroke-width="4.5" class="text-gray-200 dark:text-gray-800" />
                                    <circle cx="42" cy="42" r="34" fill="none"
                                            stroke="{{ $node['color'] }}"
                                            stroke-width="4.5"
                                            stroke-dasharray="{{ $rCirc }}"
                                            stroke-dashoffset="{{ $rOffset }}"
                                            stroke-linecap="round"
                                            transform="rotate(-90 42 42)" />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-1">
                                    <span class="text-xs font-black text-gray-900 dark:text-white tracking-tight leading-none">{{ $node['value'] }}</span>
                                    <span class="text-[7px] font-black uppercase tracking-widest text-gray-400 mt-1">{{ $node['unit'] }}</span>
                                </div>
                            </div>

                            <div class="w-5 h-0.5 shrink-0 rounded-full mx-2"
                                 style="background: linear-gradient(90deg, {{ $node['color'] }}, transparent);"></div>

                            {{-- 1-WORT HAUPTTITEL & STATUS --}}
                            <div class="text-left pl-2 w-40 xl:w-44">
                                <div class="text-xs xl:text-sm font-black uppercase tracking-[0.2em] text-gray-900 dark:text-white group-hover:text-[#C5A059] transition-colors truncate">
                                    {{ $node['word'] }}
                                </div>
                                <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                    {{ $node['metric'] }}
                                </div>
                                <div class="text-[9px] font-black uppercase tracking-widest mt-1 truncate"
                                     style="color: {{ $node['color'] }};"
                                     x-text="activeCockpitPanel === '{{ $node['key'] }}' ? '✓ AKTIV GEÖFFNET' : '{{ $node['status'] }}'">
                                    {{ $node['status'] }}
                                </div>
                            </div>

                            {{-- HOVER-INFO-TOOLTIP (1 SATZ ERKLÄRUNG DIREKT AN DER KACHEL) --}}
                            <div class="pointer-events-none opacity-0 group-hover:opacity-100 translate-y-1 group-hover:translate-y-0 transition-all duration-200 absolute left-0 {{ $node['y'] > 700 ? 'bottom-full mb-2.5' : 'top-full mt-2.5' }} w-72 p-3 rounded-2xl bg-gray-950/95 text-white border border-[#C5A059]/55 shadow-[0_15px_35px_rgba(0,0,0,0.65)] text-left z-50">
                                <div class="text-[9px] font-black uppercase tracking-widest text-[#C5A059] mb-1 flex items-center gap-1.5">
                                    <span>ℹ️</span>
                                    <span>{{ $node['word'] }} • {{ $node['metric'] }}</span>
                                </div>
                                <p class="text-[11px] text-gray-200 leading-relaxed font-medium">{{ $node['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- ZENTRALER HELDEN-KERN AUF (50%, 50%) --}}
                <div style="left: 50%; top: 50%; transform: translate(-50%, -50%);"
                     class="absolute z-30 flex flex-col items-center justify-center">

                    {{-- Äußere dezente Radar-Ringe --}}
                    <div class="absolute w-[340px] h-[340px] rounded-full border border-dashed border-[#C5A059]/25 pointer-events-none"></div>
                    <div class="absolute w-[390px] h-[390px] rounded-full border border-[#C5A059]/10 pointer-events-none"></div>

                    {{-- OBEN AM KREIS: AKTIVER RANG / TITEL BUTTON --}}
                    <button type="button"
                            @click.stop="window.sfCockpitSound && window.sfCockpitSound('click'); showTitlesModal = true"
                            class="mb-2.5 bg-white dark:bg-[#14161f] border border-[#C5A059] px-6 py-2 rounded-full font-black uppercase tracking-widest text-[10px] sm:text-xs text-gray-900 dark:text-white shadow-md hover:bg-[#C5A059] hover:text-gray-950 transition-all z-40 hover:scale-105 group cursor-pointer whitespace-nowrap flex items-center gap-2">
                        <span>{{ $currentRankName ?: 'Kein Rang' }}</span>
                        <svg class="w-3.5 h-3.5 group-hover:rotate-90 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                        </svg>
                    </button>

                    {{-- HAUPTKREIS DES HELDEN (FUNKI) --}}
                    <div class="relative w-[265px] h-[265px] xl:w-[285px] xl:h-[285px] flex items-center justify-center shrink-0">
                        @if($level >= 4)
                            <div class="absolute inset-0 rounded-full border border-gray-400/40 dark:border-gray-600/50 animate-[spin_20s_linear_infinite] pointer-events-none"></div>
                        @endif
                        @if($level >= 7)
                            <div class="absolute inset-[-0.75rem] rounded-full border-[2px] border-primary/40 border-dashed animate-[spin_15s_linear_reverse_infinite] pointer-events-none"></div>
                        @endif
                        @if($level >= 10)
                            <div class="absolute inset-[-1.35rem] rounded-full border-[3px] border-amber-400/60 border-dotted animate-[spin_10s_linear_infinite] pointer-events-none"></div>
                        @endif

                        {{-- XP FORTSCHRITTS-RING --}}
                        <svg class="absolute inset-0 w-full h-full -rotate-90 pointer-events-none z-20" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#e2e8f0" class="dark:stroke-gray-800" stroke-width="1.8"></circle>
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#c5a059" stroke-width="3.2" stroke-linecap="round"
                                    stroke-dasharray="301.59"
                                    stroke-dashoffset="{{ 301.59 - ($progressPercentage / 100) * 301.59 }}"
                                    class="transition-all duration-1000 ease-out"></circle>
                        </svg>

                        {{-- KLICK AUF FUNKI ÖFFNET DAS 3D-CHARAKTER-ATELIER IM HELLMODUS --}}
                        <button type="button"
                                @click="window.sfCockpitSound && window.sfCockpitSound('switch'); open3DModal()"
                                class="absolute inset-3.5 rounded-full bg-white dark:bg-[#13151e] {{ $levelBorder }} {{ $megaShadow }} flex items-center justify-center overflow-hidden z-10 group transition-all duration-500 hover:scale-[1.02] cursor-pointer">
                            <div class="absolute inset-0 bg-primary/10 rounded-full blur-[50px] group-hover:bg-primary/25 transition-colors duration-500 pointer-events-none"></div>
                            <img :src="currentImagePath ? currentImagePath : '{{$imagePath}}'" src="{{$imagePath}}" alt="Funki" class="w-full h-full object-contain p-5 animate-subtle-float">
                            <div class="absolute inset-0 bg-black/55 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-xs z-20 pointer-events-none">
                                <span class="bg-[#C5A059] text-gray-950 px-5 py-2.5 rounded-xl font-black text-[10px] uppercase tracking-widest shadow-2xl">3D-Atelier öffnen</span>
                            </div>
                        </button>
                    </div>

                    {{-- UNTEN AM KREIS: LEVEL-ANZEIGE + GROSSER PRÄSENTER "JETZT SPIELEN" BUTTON + HELDEN-MOTTO --}}
                    <div class="mt-2.5 flex flex-col items-center z-40">
                        <div class="flex items-center gap-2">
                            <div class="bg-gradient-to-r from-amber-300 via-[#C5A059] to-amber-500 text-gray-950 border border-amber-300/60 px-6 py-1.5 rounded-full font-black text-xs uppercase tracking-[0.22em] shadow-md">
                                Level {{ $level }}
                            </div>
                            @if(!$isMaxLevel)
                                <span class="px-3 py-1.5 rounded-full bg-white/95 dark:bg-[#13151e]/95 border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-300 shadow-xs">
                                    <span class="text-amber-600 dark:text-[#C5A059]">{{ $progressPercentage }}%</span> • Noch {{ $missingSparks }} F.
                                </span>
                            @endif
                        </div>

                        @if($canUpgrade && !$isMaxLevel)
                            <button type="button"
                                    wire:click="upgrade"
                                    wire:loading.attr="disabled"
                                    @click="window.sfCockpitSound && window.sfCockpitSound('switch')"
                                    class="mt-2 px-5 py-2 bg-[#C5A059] hover:bg-amber-400 text-gray-950 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md animate-pulse cursor-pointer">
                                <span wire:loading.remove wire:target="upgrade">✨ Jetzt Level Up durchführen!</span>
                                <span wire:loading wire:target="upgrade">Magie wirkt…</span>
                            </button>
                        @endif

                        {{-- GROSSER, PRÄSENTER "JETZT SPIELEN" BUTTON DIREKT UNTER DER LEVEL-ANZEIGE --}}
                        <button type="button"
                                @click="openGameArena('funkenflug')"
                                @mouseenter="window.sfCockpitSound && window.sfCockpitSound('hover')"
                                class="mt-2.5 px-8 py-3.5 rounded-2xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-yellow-400 hover:from-amber-300 hover:via-yellow-400 hover:to-amber-300 text-gray-950 font-black text-xs xl:text-sm uppercase tracking-[0.22em] shadow-[0_12px_35px_rgba(245,158,11,0.45)] hover:shadow-[0_16px_45px_rgba(245,158,11,0.65)] border-2 border-amber-200/90 transition-all duration-300 hover:scale-105 active:scale-95 flex items-center gap-2.5 cursor-pointer group">
                            <span class="text-base xl:text-lg group-hover:translate-x-0.5 transition-transform">🚀</span>
                            <span>Jetzt Spielen</span>
                            <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>

                        {{-- AKTIVES HELDEN-MOTTO & MORAL-BUFF PILL UNTER DEM HELDEN --}}
                        <button type="button"
                                @click="switchCockpitPanel('hangar')"
                                title="Klicken, um dein Helden-Motto & deinen Moral-Buff im Hangar anzupassen"
                                class="mt-2 px-3.5 py-1 rounded-full bg-white/90 dark:bg-[#12141c]/90 border border-amber-500/30 hover:border-[#C5A059] text-[9px] font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5 shadow-xs transition-all cursor-pointer max-w-[330px] truncate">
                            <span>{{ $activeMottoDetail['icon'] ?? '💬' }}</span>
                            <span class="italic truncate">„{{ $activeMottoDetail['text'] ?? 'Funken sammeln, Legende werden!' }}“</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-black uppercase tracking-wider shrink-0">• {{ $activeMottoDetail['buff_label'] ?? '+10% Magnet' }}</span>
                        </button>
                    </div>
                </div>

                {{-- VERTIKALER ENERGIESTRAHL NACH UNTEN IN DIE GEÖFFNETE SEKTION --}}
                <div x-show="activeCockpitPanel !== null"
                     x-cloak
                     x-transition.opacity.duration.350ms
                     class="absolute left-1/2 bottom-0 -translate-x-1/2 w-0.5 h-12 bg-gradient-to-b from-[#C5A059] via-[#C5A059]/60 to-transparent shadow-[0_0_12px_#C5A059] pointer-events-none z-10"></div>
            </div>

            {{-- 2. MOBILE & TABLET ORBITAL-STAGE (< 1024px) --}}
            <div class="lg:hidden flex flex-col items-center w-full pt-1">
                <button type="button"
                        @click.stop="window.sfCockpitSound && window.sfCockpitSound('click'); showTitlesModal = true"
                        class="mb-3 bg-white dark:bg-[#14161f] border border-[#C5A059] px-4 py-1.5 rounded-full font-black uppercase tracking-widest text-[10px] text-gray-900 dark:text-white shadow-sm flex items-center gap-1.5 active:scale-95 transition-transform">
                    <span>{{ $currentRankName ?: 'Kein Rang' }}</span>
                    <svg class="w-3.5 h-3.5 text-[#C5A059]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" /></svg>
                </button>

                <div class="relative w-52 h-52 sm:w-68 sm:h-68 flex items-center justify-center">
                    <svg class="absolute inset-0 w-full h-full -rotate-90 pointer-events-none z-20" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="48" fill="none" stroke="#e2e8f0" class="dark:stroke-gray-800" stroke-width="1.8"></circle>
                        <circle cx="50" cy="50" r="48" fill="none" stroke="#c5a059" stroke-width="3.2" stroke-linecap="round"
                                stroke-dasharray="301.59"
                                stroke-dashoffset="{{ 301.59 - ($progressPercentage / 100) * 301.59 }}"></circle>
                    </svg>

                    <button type="button"
                            @click="window.sfCockpitSound && window.sfCockpitSound('switch'); open3DModal()"
                            class="absolute inset-3 rounded-full bg-white dark:bg-[#13151e] {{ $levelBorder }} {{ $megaShadow }} flex items-center justify-center overflow-hidden z-10 active:scale-95 transition-transform">
                        <img :src="currentImagePath ? currentImagePath : '{{$imagePath}}'" src="{{$imagePath}}" alt="Funki" class="w-full h-full object-contain p-4 animate-subtle-float">
                        <span class="absolute bottom-2.5 px-2.5 py-0.5 rounded-full bg-gray-900/75 dark:bg-black/75 backdrop-blur-xs text-[#f5dca0] border border-[#C5A059]/40 text-[8px] font-black uppercase tracking-widest shadow-sm">
                            3D-Atelier ↗
                        </span>
                    </button>

                    {{-- INTERAKTIVE ENERGIE & GUTHABEN SATELLITEN AM HELDEN (MOBILE MIT INFO-TRIGGER) --}}
                    <button type="button"
                            @click="window.sfCockpitSound && window.sfCockpitSound('click'); mobileNodeInfoText = '⚡ ENERGIE: Deine Seelen-Energie lädt sich automatisch wieder auf und wird benötigt, um Minispiele im Cockpit zu starten.'"
                            class="absolute top-2 -left-4 sm:-left-6 z-30 bg-white/95 dark:bg-[#13151e]/95 border border-blue-500/45 px-2.5 py-1.5 rounded-2xl shadow-md flex items-center gap-1.5 active:scale-95 transition-transform">
                        <span class="text-xs">⚡</span>
                        <div class="text-left leading-none">
                            <span class="block text-[7px] font-black uppercase tracking-widest text-blue-500">ENERGIE ⓘ</span>
                            <span class="text-[11px] font-black text-gray-900 dark:text-white">{{ $energyBalance }}/{{ $maxEnergy }}</span>
                        </div>
                    </button>
                    <button type="button"
                            @click="window.sfCockpitSound && window.sfCockpitSound('click'); mobileNodeInfoText = '✨ GUTHABEN: Dein aktuelles Seelenfunken-Konto für Level-Aufstiege, 3D-Raumschiffe, Schlachtschiffe und Ruhmes-Auren.'"
                            class="absolute top-2 -right-4 sm:-right-6 z-30 bg-white/95 dark:bg-[#13151e]/95 border border-[#C5A059]/55 px-2.5 py-1.5 rounded-2xl shadow-md flex items-center gap-1.5 active:scale-95 transition-transform">
                        <span class="text-xs">✨</span>
                        <div class="text-left leading-none">
                            <span class="block text-[7px] font-black uppercase tracking-widest text-[#C5A059]">GUTHABEN ⓘ</span>
                            <span class="text-[11px] font-black text-gray-900 dark:text-white">{{ number_format($balance, 0, ',', '.') }} F.</span>
                        </div>
                    </button>
                </div>

                <div class="mt-2.5 flex flex-col items-center w-full max-w-md px-2">
                    <div class="bg-gradient-to-r from-amber-300 via-[#C5A059] to-amber-500 text-gray-950 px-5 py-1 rounded-full font-black text-[11px] uppercase tracking-[0.2em] shadow-sm">
                        Level {{ $level }} • {{ $progressPercentage }}%
                    </div>
                    @if($canUpgrade)
                        <button type="button" wire:click="upgrade" wire:loading.attr="disabled"
                                class="mt-2 px-5 py-2 bg-[#C5A059] text-gray-950 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-md animate-pulse active:scale-95">
                            ✨ Level Up durchführen!
                        </button>
                    @endif

                    {{-- GROSSER, PRÄSENTER "JETZT SPIELEN" BUTTON UNTER DER LEVEL-ANZEIGE (MOBIL) --}}
                    <button type="button"
                            @click="openGameArena('funkenflug')"
                            class="mt-3 w-full max-w-xs py-3.5 px-6 rounded-2xl bg-gradient-to-r from-amber-400 via-[#C5A059] to-yellow-400 text-gray-950 font-black text-xs uppercase tracking-[0.22em] shadow-[0_10px_28px_rgba(245,158,11,0.42)] border-2 border-amber-200/90 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <span class="text-base">🚀</span>
                        <span>Jetzt Spielen</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </button>

                    {{-- AKTIVES HELDEN-MOTTO & MORAL-BUFF (MOBIL) --}}
                    <button type="button"
                            @click="switchCockpitPanel('hangar')"
                            class="mt-2 px-3 py-1 rounded-full bg-white/90 dark:bg-[#12141c] border border-amber-500/30 text-[9px] font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5 max-w-full truncate">
                        <span>{{ $activeMottoDetail['icon'] ?? '💬' }}</span>
                        <span class="italic truncate">„{{ $activeMottoDetail['text'] ?? 'Funken sammeln, Legende werden!' }}“</span>
                        <span class="text-emerald-600 dark:text-emerald-400 font-black shrink-0">• {{ $activeMottoDetail['buff_label'] ?? '+10% Magnet' }}</span>
                    </button>
                </div>

                {{-- INTELLIGENTE MOBILE SMART-INFO-LEISTE (ZEIGT 1-SATZ-ERKLÄRUNG FÜR JEDE KACHEL) --}}
                <div x-show="mobileNodeInfoText !== null"
                     x-transition.opacity.duration.200ms
                     style="display: none;"
                     class="mt-2.5 w-full max-w-md rounded-2xl bg-gray-950/95 text-white border border-[#C5A059]/55 px-3.5 py-2.5 shadow-lg flex items-start justify-between gap-2 text-left">
                    <p class="text-[11px] text-amber-100 leading-snug font-medium" x-text="mobileNodeInfoText"></p>
                    <button type="button" @click="mobileNodeInfoText = null" class="text-gray-400 hover:text-white text-xs font-black shrink-0 px-1">✕</button>
                </div>

                {{-- 4 SMART-KACHELN IM ERGONOMISCHEN 2x2 TOUCH-GRID UNTER DEM HELDEN (MOBILE INKL. 1-SATZ-INFO) --}}
                <div class="w-0.5 h-3.5 bg-gradient-to-b from-[#C5A059] to-transparent my-1.5"></div>
                <div class="grid grid-cols-2 gap-2.5 w-full max-w-md">
                    @foreach([
                        ['key' => 'info', 'word' => 'GUIDE', 'sub' => 'Erklärung', 'icon' => '✨', 'desc' => 'Hier erfährst du kompakt, wie du mit Funki aufsteigst, Funken sammelst und echte Manufaktur-Rabatte freischaltest.'],
                        ['key' => 'legende', 'word' => 'LEGENDE', 'sub' => $unlockedCouponsCount . '/' . $totalMilestonesCount . ' Codes', 'icon' => '🎁', 'desc' => 'Auf dem Pfad zur Legende schaltest du von Level 1 bis 10 exklusive Gutscheincodes und echte Shop-Vorteile frei.'],
                        ['key' => 'prestige', 'word' => 'PRESTIGE', 'sub' => 'Lvl ' . $level . '/100', 'icon' => '👑', 'desc' => 'Ab Level 11 steigst du bis Level 100 im Prestige-Rang auf und erringst seltene Ehren-Embleme für die Ruhmeshalle.'],
                        ['key' => 'hangar', 'word' => 'HANGAR', 'sub' => $unlockedSkinsCount . '/' . $totalSkinsCount . ' Skins', 'icon' => '🛸', 'desc' => 'Im Seelen-Hangar rüstest du 3D-Jäger, epische Schlachtschiffe, Ruhmes-Auren und deinen aktiven Piloten-Kampfruf aus.'],
                    ] as $mNode)
                        <div :class="activeCockpitPanel === '{{ $mNode['key'] }}'
                                 ? 'bg-[#C5A059]/15 border-[#C5A059] ring-1 ring-[#C5A059] shadow-sm'
                                 : 'bg-white dark:bg-[#12141c] border-gray-200 dark:border-gray-800'"
                             class="p-2.5 sm:p-3 rounded-2xl border flex flex-col justify-between gap-1.5 text-left transition-all shadow-xs">
                            <div class="flex items-center justify-between gap-1.5">
                                <button type="button"
                                        @click="mobileNodeInfoText = '{{ $mNode['word'] }}: {{ $mNode['desc'] }}'; switchCockpitPanel('{{ $mNode['key'] }}')"
                                        class="flex items-center gap-2 min-w-0 flex-1 text-left active:scale-[0.98]">
                                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gray-50 dark:bg-[#0b0d13] border border-gray-200/70 dark:border-gray-800 flex items-center justify-center text-sm sm:text-base shrink-0">
                                        {{ $mNode['icon'] }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-[11px] sm:text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white truncate">{{ $mNode['word'] }}</div>
                                        <div class="text-[10px] font-bold text-[#C5A059] truncate">{{ $mNode['sub'] }}</div>
                                    </div>
                                </button>
                                <button type="button"
                                        @click.stop="window.sfCockpitSound && window.sfCockpitSound('click'); mobileNodeInfoText = (mobileNodeInfoText === '{{ $mNode['word'] }}: {{ $mNode['desc'] }}' ? null : '{{ $mNode['word'] }}: {{ $mNode['desc'] }}')"
                                        title="Kurz-Erklärung anzeigen"
                                        class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300 hover:bg-[#C5A059] hover:text-gray-950 text-[10px] font-black flex items-center justify-center shrink-0">
                                    ⓘ
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- =====================================================================
                 B. 60 FPS FOKUS-MODUS TRANSFORMATIONS-BÜHNE (EINGEKLAPPTE SEKTIONEN & SPIELE)
                 ===================================================================== --}}
            <div id="sf-cockpit-focus-stage" class="relative w-full mt-2 scroll-mt-24">

                {{-- CHOREOGRAPHIERTES 60 FPS TRANSFORM-WRAPPER --}}
                <div class="w-full transform-gpu"
                     :class="{
                         'transition-[opacity,transform,filter] duration-175 ease-in opacity-0 scale-[0.975] blur-[2px] -translate-y-2': cockpitMorphPhase === 'leaving' && cockpitMorphDir === 'forward',
                         'transition-[opacity,transform,filter] duration-175 ease-in opacity-0 scale-[0.975] blur-[2px] translate-y-2': cockpitMorphPhase === 'leaving' && cockpitMorphDir === 'back',
                         'transition-none opacity-0 scale-[0.98] blur-[2px] translate-y-3': cockpitMorphPhase === 'entering' && cockpitMorphDir === 'forward',
                         'transition-none opacity-0 scale-[0.98] blur-[2px] -translate-y-3': cockpitMorphPhase === 'entering' && cockpitMorphDir === 'back',
                         'transition-[opacity,transform,filter] duration-350 ease-[cubic-bezier(0.16,1,0.3,1)] opacity-100 scale-100 blur-0 translate-y-0': cockpitMorphPhase === 'idle'
                     }">

                    {{-- ZUSTAND 1: EINE DER SMART-KACHELN IST GEÖFFNET (INFO, LEGENDE, PRESTIGE, HANGAR, RANKING) --}}
                    <div x-show="activeCockpitPanel !== null" x-cloak class="w-full max-w-6xl mx-auto">

                        {{-- MATTE KOMMANDO-LEISTE ZUM SCHNELLWECHSEL ODER EINKLAPPEN (MOBIL HORIZONTAL SWIPEBAR) --}}
                        <div class="mb-5 sm:mb-6 p-2 sm:p-3 rounded-2xl bg-white/95 dark:bg-[#12141c]/95 backdrop-blur-xl border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2 shadow-sm">
                            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5 pr-1">
                                @foreach([
                                    ['key' => 'info', 'label' => 'Guide', 'icon' => '✨'],
                                    ['key' => 'legende', 'label' => 'Legende', 'icon' => '🎁'],
                                    ['key' => 'prestige', 'label' => 'Prestige', 'icon' => '👑'],
                                    ['key' => 'hangar', 'label' => 'Hangar', 'icon' => '🛸'],
                                    ['key' => 'ranking', 'label' => 'Ruhmeshalle', 'icon' => '🏆'],
                                ] as $pill)
                                    <button type="button"
                                            @click="switchCockpitPanel('{{ $pill['key'] }}')"
                                            :class="activeCockpitPanel === '{{ $pill['key'] }}'
                                                ? 'bg-[#C5A059] text-gray-950 font-black shadow-xs'
                                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/70 font-bold'"
                                            class="px-3 sm:px-3.5 py-2 rounded-xl text-[10px] sm:text-[11px] uppercase tracking-wider transition-all flex items-center gap-1.5 cursor-pointer shrink-0 active:scale-95">
                                        <span>{{ $pill['icon'] }}</span>
                                        <span>{{ $pill['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>

                            <button type="button"
                                    @click="switchCockpitPanel(null)"
                                    class="px-3 sm:px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800/90 hover:bg-red-500/15 hover:text-red-500 text-gray-700 dark:text-gray-300 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all flex items-center gap-1.5 cursor-pointer shrink-0 active:scale-95">
                                <span>✕</span>
                                <span class="hidden sm:inline">Einklappen</span>
                            </button>
                        </div>

                        {{-- SMART-PANEL 1: GUIDE ("Was passiert hier eigentlich?") --}}
                        <div x-show="activeCockpitPanel === 'info'" x-cloak>
                            <div class="bg-white/95 dark:bg-[#12141c]/95 backdrop-blur-xl border border-gray-200 dark:border-[#C5A059]/30 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-2xl relative overflow-hidden text-left">
                                <div class="relative z-10">
                                    <h3 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                                        <span class="text-[#C5A059] text-3xl">✨</span> Was passiert hier eigentlich?
                                    </h3>
                                    <div class="space-y-4 text-gray-600 dark:text-gray-300 text-sm sm:text-base leading-relaxed">
                                        <p>
                                            Schön, dass du dabei bist! Das <strong class="text-gray-900 dark:text-white">Spieler-Cockpit</strong> ist dein persönliches Kontrollzentrum in unserer Manufaktur. Wir glauben, dass Treue belohnt werden sollte – und zwar auf eine Art, die wirklich Freude macht.
                                        </p>
                                        <p>
                                            Dein kleiner 3D-Gefährte <strong class="text-gray-900 dark:text-white">„Funki“</strong> begleitet dich auf deiner Reise. Mit jeder Bestellung, jeder Interaktion und jedem Einsatz in den Minispielen sammelst du <strong class="text-[#C5A059]">Funken</strong>. Diese Funken lassen deinen Funki wachsen, neue Level erreichen und sich visuell weiterentwickeln!
                                        </p>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 my-6">
                                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-[#0c0e14] border border-gray-200/80 dark:border-gray-800">
                                                <div class="text-emerald-500 font-black text-xs uppercase tracking-widest mb-1.5">🎁 Level-Ups & Rabatte</div>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">Erreichst du neue Meilensteine, schaltest du unter <strong>Legende</strong> exklusive Gutscheine und echte Shop-Rabatte frei.</p>
                                            </div>
                                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-[#0c0e14] border border-gray-200/80 dark:border-gray-800">
                                                <div class="text-amber-500 font-black text-xs uppercase tracking-widest mb-1.5">🛸 3D-Hangar & Prestige</div>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">Steige bis <strong>Level 100</strong> auf, rüste im <strong>Hangar</strong> 3D-Raumschiffe mit Spezial-Vorteilen aus und wähle dein Helden-Motto.</p>
                                            </div>
                                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-[#0c0e14] border border-gray-200/80 dark:border-gray-800">
                                                <div class="text-blue-500 font-black text-xs uppercase tracking-widest mb-1.5">🎮 Minispiele & Ruhm</div>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">Nutze deine Seelen-Energie für <strong>Kristall-Kollaps 3D</strong> & <strong>Funkenflug-Express</strong> und verewige dich in der <strong>Ruhmeshalle</strong>!</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- SMART-PANEL 2: LEGENDE ("Pfad zur Legende") --}}
                        <div x-show="activeCockpitPanel === 'legende'" x-cloak>
                            <div class="bg-white/95 dark:bg-[#12141c]/95 backdrop-blur-xl border border-gray-200 dark:border-[#C5A059]/30 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-2xl text-left">
                                <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4 gap-2">
                                    <h3 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-3">
                                        <span class="text-[#C5A059]">🎁</span> Pfad zur Legende
                                    </h3>
                                    <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 font-black uppercase tracking-[0.2em]">Meilensteine & Gutscheincodes (Level 1 bis 10)</p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                                    @foreach($milestonesConfig as $mLevel => $reward)
                                        @php
                                            $isUnlocked = $level >= $mLevel;
                                            $couponData = $unlockedCoupons['lvl_' . $mLevel] ?? null;
                                            $code = $couponData['code'] ?? null;
                                            $isUsed = $couponData['is_used'] ?? false;
                                        @endphp
                                        <div class="relative border rounded-2xl p-5 flex flex-col items-center text-center transition-all duration-300 overflow-hidden group {{ $isUsed ? 'bg-gray-100 dark:bg-gray-950 border-red-200 dark:border-red-900/30 opacity-70' : ($isUnlocked ? 'bg-gray-50/70 dark:bg-[#0d0f17] border-[#C5A059]/50 shadow-xs hover:-translate-y-1' : 'bg-gray-50 dark:bg-[#0b0d13] border-gray-200 dark:border-gray-800 opacity-60 grayscale') }}">
                                            <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4 font-black text-lg z-10 {{ $isUsed ? 'bg-gray-200 dark:bg-gray-900 text-red-400 border border-red-300' : ($isUnlocked ? 'bg-[#C5A059] text-gray-950 shadow-sm' : 'bg-gray-200 dark:bg-gray-800 text-gray-500 border border-gray-300 dark:border-gray-700') }}">
                                                {{ $mLevel }}
                                            </div>
                                            <h4 class="font-bold text-sm mb-1 z-10 {{ $isUsed ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-white' }}">{{ $reward['name'] }}</h4>
                                            <p class="text-[10px] font-medium mb-4 z-10 text-gray-500 dark:text-gray-400">Erreiche Level {{ $mLevel }}</p>

                                            <div class="w-full mt-auto z-10">
                                                @if($isUnlocked)
                                                    @if($isUsed)
                                                        <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 rounded-lg p-2 text-center">
                                                            <p class="text-red-500 font-black text-[10px] uppercase tracking-[0.2em]">✓ Eingelöst</p>
                                                        </div>
                                                    @elseif($code)
                                                        <div x-data="{ copied: false }" class="bg-white dark:bg-gray-950 border border-[#C5A059]/40 rounded-lg p-2 flex items-center justify-between gap-2 overflow-hidden">
                                                            <div class="text-left flex-1 min-w-0">
                                                                <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-0.5 truncate">Code (1x gültig)</p>
                                                                <p class="text-[#C5A059] font-mono font-bold text-xs sm:text-sm tracking-wider truncate">{{ $code }}</p>
                                                            </div>
                                                            <button type="button"
                                                                    @click="window.sfCockpitSound && window.sfCockpitSound('click'); navigator.clipboard.writeText('{{$code}}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                                    class="shrink-0 p-2 bg-[#C5A059]/15 text-[#C5A059] rounded-md hover:bg-[#C5A059] hover:text-gray-950 transition-colors cursor-pointer"
                                                                    title="Code kopieren">
                                                                <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                                                <svg x-show="copied" style="display: none;" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                                            </button>
                                                        </div>
                                                    @else
                                                        <div class="bg-white dark:bg-gray-950 border border-[#C5A059]/30 rounded-lg p-2">
                                                            <p class="text-[#C5A059] font-mono font-bold text-[10px] tracking-wider">WIRD GENERIERT...</p>
                                                        </div>
                                                    @endif
                                                @else
                                                    <div class="bg-gray-100 dark:bg-gray-800/80 rounded-lg p-2.5">
                                                        <p class="text-gray-500 font-black text-[10px] uppercase tracking-widest">🔒 Gesperrt</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- SMART-PANEL 3: PRESTIGE ("Prestige-Aufstieg Level 11 bis 100") --}}
                        <div x-show="activeCockpitPanel === 'prestige'" x-cloak>
                            <div class="bg-white/95 dark:bg-[#12141c]/95 backdrop-blur-xl border border-amber-500/30 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-2xl text-left">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
                                    <div>
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-2xl">👑</span>
                                            <h3 class="text-xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white">Prestige-Aufstieg (Level 11 bis 100)</h3>
                                        </div>
                                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1.5">
                                            Ab Level 10 hat Funki seine vollkommene 3D-Gottform erreicht. Dein Aufstieg geht jedoch weiter bis <strong>Level 100</strong> und schaltet exklusive Prestige-Embleme für die Ruhmeshalle frei!
                                        </p>
                                    </div>
                                    <span class="px-4 py-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs font-black uppercase tracking-widest shrink-0">
                                        Dein Level: {{ $level }} / 100
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3.5">
                                    @foreach(($prestigeMilestones ?? []) as $pLevel => $pInfo)
                                        @php $pUnlocked = $level >= $pLevel; @endphp
                                        <div class="rounded-2xl border p-4 flex flex-col items-center text-center transition-all {{ $pUnlocked ? 'bg-amber-500/10 border-amber-400/50 shadow-xs' : 'bg-gray-50 dark:bg-[#0b0d13] border-gray-200 dark:border-gray-800 opacity-65' }}">
                                            <div class="text-2xl mb-2">{{ $pInfo['icon'] }}</div>
                                            <span class="text-[9px] font-black uppercase tracking-widest px-2.5 py-0.5 rounded-full mb-1.5 {{ $pUnlocked ? 'bg-amber-500 text-gray-950' : 'bg-gray-200 dark:bg-gray-800 text-gray-500' }}">
                                                Level {{ $pLevel }}
                                            </span>
                                            <h5 class="font-black text-xs text-gray-900 dark:text-white mb-1">{{ $pInfo['name'] }}</h5>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-snug mb-3">{{ $pInfo['reward'] }}</p>
                                            <span class="mt-auto text-[9px] font-black uppercase tracking-widest {{ $pUnlocked ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $pUnlocked ? '✓ Freigeschaltet' : '🔒 Noch ' . max(0, $pLevel - $level) . ' Lvl' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- SMART-PANEL 4: HANGAR ("Flotte, Prestige-Auren & Motto") --}}
                        <div x-show="activeCockpitPanel === 'hangar'" x-cloak>
                            <div class="bg-white/95 dark:bg-[#12141c]/95 backdrop-blur-xl border border-amber-500/30 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-2xl text-left">
                                @include('livewire.customer.partials.games-component.seelen-hangar-panel', ['showBackButton' => false])
                            </div>
                        </div>

                        {{-- SMART-PANEL 5: RUHMESHALLE ("Halle der Legenden") --}}
                        <div x-show="activeCockpitPanel === 'ranking'" x-cloak>
                            <div class="bg-white/95 dark:bg-[#12141c]/95 backdrop-blur-xl border border-gray-200 dark:border-[#C5A059]/30 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-2xl text-left">
                                @if(!$rankingOptIn)
                                    <div class="max-w-xl mx-auto py-6 text-center">
                                        <div class="w-18 h-18 mx-auto bg-amber-500/10 border border-amber-500/40 rounded-full flex items-center justify-center mb-5 text-3xl">🏆</div>
                                        <h3 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mb-3">Die Halle der Legenden</h3>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed mb-6">
                                            Messe dich mit anderen Spielern der Manufaktur. Mit dem Betreten stimmst du zu, dass dein <strong>Vorname und der erste Buchstabe deines Nachnamens</strong> sowie dein Level, Flugschiff und Punkte in der Rangliste angezeigt werden.
                                        </p>
                                        <button type="button"
                                                wire:click="optInRanking"
                                                @click="window.sfCockpitSound && window.sfCockpitSound('switch')"
                                                class="px-8 py-4 bg-[#C5A059] hover:bg-amber-400 text-gray-950 rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all cursor-pointer shadow-md">
                                            Namen freigeben & Ruhmeshalle betreten →
                                        </button>
                                    </div>
                                @else
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-4 border-b border-gray-200 dark:border-gray-800">
                                        <div>
                                            <h3 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                                                <span>🏆</span> Halle der Legenden
                                            </h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Klicke auf einen Helden, um seine vollständige Visitenkarte & Trophäen zu inspizieren</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                    wire:click="setRankingTab('classic')"
                                                    @click="window.sfCockpitSound && window.sfCockpitSound('click')"
                                                    class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider border transition-all cursor-pointer {{ $rankingTab === 'classic' ? 'bg-[#C5A059] text-gray-950 border-[#C5A059]' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                                ✨ Level & Funken
                                            </button>
                                            <button type="button"
                                                    wire:click="setRankingTab('funkenflug')"
                                                    @click="window.sfCockpitSound && window.sfCockpitSound('click')"
                                                    class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider border transition-all cursor-pointer {{ $rankingTab === 'funkenflug' ? 'bg-indigo-500 text-white border-indigo-500' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}">
                                                🚀 Funkenflug-Rekorde
                                            </button>
                                        </div>
                                    </div>

                                    @php
                                        $top3 = ($rankings ?? collect())->take(3);
                                        $rest = ($rankings ?? collect())->skip(3);
                                        $myId = auth('customer')->id();
                                    @endphp

                                    @if($top3->count() > 0)
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                                            @foreach($top3 as $tIdx => $pHero)
                                                @php
                                                    $rankNum = $tIdx + 1;
                                                    $pShip = $this->getShipSkinForProfile($pHero);
                                                    $pAura = $this->getHallAuraForProfile($pHero);
                                                    $pMotto = $this->getHeroMottoDetailForProfile($pHero);
                                                    $pPrestige = $this->getPrestigeBadgeForLevel((int) $pHero->level);
                                                    $pPayload = $this->getInspectorPayload($pHero, $rankNum);
                                                    $hasCustomAura = !empty($pAura['row_class']);
                                                @endphp
                                                <div @click="openHeroInspector(@js($pPayload))"
                                                     class="cursor-pointer group rounded-3xl border p-6 text-center relative overflow-hidden transition-all hover:-translate-y-1 {{ $hasCustomAura ? $pAura['row_class'] : ($rankNum === 1 ? 'border-amber-400 bg-amber-500/5 dark:bg-amber-500/10' : 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-[#0d0f17]') }}">
                                                    <div class="w-12 h-12 mx-auto rounded-full flex items-center justify-center font-serif font-black text-xl mb-3 {{ $rankNum === 1 ? 'bg-amber-400 text-gray-950' : ($rankNum === 2 ? 'bg-slate-300 text-gray-900' : 'bg-orange-400/80 text-gray-950') }}">
                                                        #{{ $rankNum }}
                                                    </div>
                                                    @if(($pHero->active_hall_aura ?? 'none') !== 'none')
                                                        <div class="mb-1.5">
                                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border {{ $pAura['badge_class'] ?? '' }}">
                                                                {{ $pAura['icon'] ?? '🌟' }} {{ $pAura['name'] ?? '' }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                    <h4 class="font-black text-base text-gray-900 dark:text-white truncate">
                                                        {{ $pHero->customer?->first_name ?? 'Anonym' }} {{ $pHero->customer?->last_name ? mb_substr($pHero->customer->last_name, 0, 1) . '.' : '' }}
                                                    </h4>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-[#C5A059] mt-0.5 truncate">{{ $this->getActiveTitleName($pHero) }}</p>
                                                    <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 mt-1">Level {{ $pHero->level }} {{ $pPrestige['icon'] ?? '' }}</p>
                                                    <div class="my-3 flex justify-center">
                                                        <img src="{{ $this->getAvatarForLevel($pHero->level) }}" alt="Avatar" class="w-20 h-20 object-contain group-hover:scale-105 transition-transform">
                                                    </div>
                                                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[10px] font-bold text-gray-700 dark:text-gray-300 mb-2 max-w-full truncate">
                                                        <span>{{ $pShip['icon'] ?? '🚀' }}</span>
                                                        <span class="truncate">{{ $pShip['name'] ?? 'Standard-Gleiter' }}</span>
                                                    </div>
                                                    <p class="text-[10px] italic text-gray-500 dark:text-gray-400 line-clamp-1 mb-1">„{{ $pMotto['text'] ?? '' }}“</p>
                                                    <div class="text-xs font-black text-amber-600 dark:text-[#C5A059] mt-1">
                                                        @if($rankingTab === 'funkenflug')
                                                            {{ number_format($pHero->funkenflug_highscore, 0, ',', '.') }} m
                                                        @else
                                                            {{ number_format($pHero->funken_total_earned, 0, ',', '.') }} ✨
                                                        @endif
                                                    </div>
                                                    <div class="mt-3 pt-2.5 border-t border-gray-200/60 dark:border-gray-800/70 flex items-center justify-center">
                                                        <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-[#C5A059] group-hover:underline">
                                                            🔍 Visitenkarte & Trophäen
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($rest->count() > 0)
                                        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 overflow-x-auto no-scrollbar">
                                            <table class="w-full text-left border-collapse min-w-[300px]">
                                                <thead>
                                                    <tr class="bg-gray-50 dark:bg-[#0b0d13] border-b border-gray-200 dark:border-gray-800 text-[9px] sm:text-[10px] font-black text-gray-500 uppercase tracking-widest">
                                                        <th class="px-2.5 sm:px-4 py-3 sm:py-3.5 w-11 sm:w-14 text-center">Rang</th>
                                                        <th class="px-2.5 sm:px-4 py-3 sm:py-3.5">Legende</th>
                                                        <th class="px-4 py-3.5 hidden md:table-cell">Flugschiff</th>
                                                        <th class="px-2.5 sm:px-4 py-3 sm:py-3.5 text-center">Level</th>
                                                        <th class="px-2.5 sm:px-4 py-3 sm:py-3.5 text-right">{{ $rankingTab === 'funkenflug' ? 'Distanz' : 'Funken' }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                                                    @foreach($rest as $index => $r)
                                                        @php
                                                            $actualRank = $index + 4;
                                                            $rShip = $this->getShipSkinForProfile($r);
                                                            $rAura = $this->getHallAuraForProfile($r);
                                                            $rPrestige = $this->getPrestigeBadgeForLevel((int) $r->level);
                                                            $rPayload = $this->getInspectorPayload($r, $actualRank);
                                                        @endphp
                                                        <tr @click="openHeroInspector(@js($rPayload))"
                                                            class="cursor-pointer group hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors {{ $rAura['row_class'] ?? '' }} {{ $r->customer_id === $myId ? 'bg-[#C5A059]/10' : '' }}">
                                                            <td class="px-2.5 sm:px-4 py-2.5 sm:py-3 text-center font-mono font-bold text-xs text-gray-500">#{{ $actualRank }}</td>
                                                            <td class="px-2.5 sm:px-4 py-2.5 sm:py-3 max-w-[160px] sm:max-w-none">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="font-bold text-xs sm:text-sm text-gray-900 dark:text-white truncate">
                                                                        {{ $r->customer?->first_name ?? 'Anonym' }} {{ $r->customer?->last_name ? mb_substr($r->customer->last_name, 0, 1) . '.' : '' }}
                                                                    </span>
                                                                    @if(($r->active_hall_aura ?? 'none') !== 'none')
                                                                        <span class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider border {{ $rAura['badge_class'] ?? '' }}">
                                                                            {{ $rAura['icon'] ?? '🌟' }}
                                                                        </span>
                                                                    @endif
                                                                    <span class="text-[9px] text-amber-600 dark:text-[#C5A059] opacity-75 group-hover:opacity-100 font-black uppercase tracking-wider ml-auto sm:ml-1">🔍</span>
                                                                </div>
                                                                <div class="text-[9px] sm:text-[10px] text-[#C5A059] font-black uppercase tracking-wider truncate">{{ $this->getActiveTitleName($r) }}</div>
                                                            </td>
                                                            <td class="px-4 py-3 hidden md:table-cell text-xs text-gray-600 dark:text-gray-300">
                                                                {{ $rShip['icon'] ?? '🚀' }} {{ $rShip['name'] ?? 'Standard-Gleiter' }}
                                                            </td>
                                                            <td class="px-2.5 sm:px-4 py-2.5 sm:py-3 text-center font-black text-xs text-gray-800 dark:text-white whitespace-nowrap">
                                                                {{ $r->level }} {{ $rPrestige['icon'] ?? '' }}
                                                            </td>
                                                            <td class="px-2.5 sm:px-4 py-2.5 sm:py-3 text-right font-mono font-bold text-xs text-gray-800 dark:text-gray-200 whitespace-nowrap">
                                                                @if($rankingTab === 'funkenflug')
                                                                    {{ number_format($r->funkenflug_highscore, 0, ',', '.') }} m
                                                                @else
                                                                    {{ number_format($r->funken_total_earned, 0, ',', '.') }} ✨
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ZUSTAND 2: STANDARD-COCKPIT-DOCK (ALLE 4 PROFIL-BEREICHE SIND EINGEKLAPPT / UNSICHTBAR) --}}
                    <div x-show="activeCockpitPanel === null" class="grid grid-cols-1 md:grid-cols-3 gap-5 sm:gap-6 pb-20 md:pb-8">

                        {{-- KACHEL 1: KRISTALL-KOLLAPS 3D --}}
                        <div @if($currentEnergy > 0) @click="openGameArena('kristall')" @endif
                             @mouseenter="window.sfCockpitSound && window.sfCockpitSound('hover')"
                             class="group relative bg-white dark:bg-[#12141c] rounded-[2rem] border border-gray-200 dark:border-gray-800/90 p-6 sm:p-7 hover:border-emerald-500/60 transition-all duration-300 hover:-translate-y-1 shadow-sm dark:shadow-xl flex flex-col {{ $currentEnergy > 0 ? 'cursor-pointer' : '' }}">
                            <div class="flex items-center justify-between mb-5">
                                <div class="w-14 h-14 rounded-2xl bg-gray-50 dark:bg-[#0b0d13] border border-gray-200 dark:border-gray-800 flex items-center justify-center text-3xl group-hover:scale-105 transition-transform">
                                    💎
                                </div>
                                <span class="px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/25 text-emerald-600 dark:text-emerald-400 text-[9px] font-black uppercase tracking-widest">
                                    Match-3 Taktik
                                </span>
                            </div>
                            <h4 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white mb-2">Kristall-Kollaps 3D</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-xs sm:text-sm leading-relaxed mb-6 flex-1">
                                Kettenreaktionen, Nova-Sphären und Zeit-Kristalle auf dem 8×8 Feld. Kombiniere klug und sammle wertvolle Seelenfunken!
                            </p>
                            @if($currentEnergy > 0)
                                <button type="button" @click.stop="openGameArena('kristall')" class="mt-auto w-full py-3.5 bg-emerald-500/10 hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-gray-950 border border-emerald-500/30 rounded-xl font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all cursor-pointer">
                                    Mission starten →
                                </button>
                            @else
                                <div class="mt-auto w-full text-center">
                                    <button type="button" disabled class="w-full py-3.5 bg-gray-100 dark:bg-gray-900 text-gray-400 border border-gray-200 dark:border-gray-800 rounded-xl font-black text-[10px] uppercase tracking-widest cursor-not-allowed">
                                        🔒 0 Energie ({{ is_numeric($timeUntilNextEnergy) ? round($timeUntilNextEnergy) : $timeUntilNextEnergy }} Min.)
                                    </button>
                                </div>
                            @endif
                        </div>

                        {{-- KACHEL 2: FUNKENFLUG-EXPRESS --}}
                        <div @if($currentEnergy > 0) @click="openGameArena('funkenflug')" @endif
                             @mouseenter="window.sfCockpitSound && window.sfCockpitSound('hover')"
                             class="group relative bg-white dark:bg-[#12141c] rounded-[2rem] border border-gray-200 dark:border-gray-800/90 p-6 sm:p-7 hover:border-[#C5A059]/70 transition-all duration-300 hover:-translate-y-1 shadow-sm dark:shadow-xl flex flex-col {{ $currentEnergy > 0 ? 'cursor-pointer' : '' }}">
                            <div class="flex items-center justify-between mb-5">
                                <div class="w-14 h-14 rounded-2xl bg-gray-50 dark:bg-[#0b0d13] border border-gray-200 dark:border-gray-800 flex items-center justify-center text-3xl group-hover:scale-105 transition-transform">
                                    🚀
                                </div>
                                <span class="px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-[#C5A059] text-[9px] font-black uppercase tracking-widest">
                                    Rekord: {{ number_format($personalHighscoreFF, 0, ',', '.') }}m
                                </span>
                            </div>
                            <h4 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white mb-2">Funkenflug-Express</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-xs sm:text-sm leading-relaxed mb-6 flex-1">
                                Steuere deine Rakete durchs All, lass Meteoriten am Plasma-Schild abprallen und besiege die Sektor-Bosse von <em>Lord Lieblos</em>!
                            </p>
                            @if($currentEnergy > 0)
                                <button type="button" @click.stop="openGameArena('funkenflug')" class="mt-auto w-full py-3.5 bg-[#C5A059]/15 hover:bg-[#C5A059] text-amber-700 dark:text-[#f5dca0] hover:text-gray-950 border border-[#C5A059]/40 rounded-xl font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all cursor-pointer">
                                    Flug starten →
                                </button>
                            @else
                                <div class="mt-auto w-full text-center">
                                    <button type="button" disabled class="w-full py-3.5 bg-gray-100 dark:bg-gray-900 text-gray-400 border border-gray-200 dark:border-gray-800 rounded-xl font-black text-[10px] uppercase tracking-widest cursor-not-allowed">
                                        🔒 0 Energie ({{ is_numeric($timeUntilNextEnergy) ? round($timeUntilNextEnergy) : $timeUntilNextEnergy }} Min.)
                                    </button>
                                </div>
                            @endif
                        </div>

                        {{-- KACHEL 3: HALLE DER LEGENDEN (DIREKT IM COCKPIT) --}}
                        <div @click="switchCockpitPanel('ranking')"
                             @mouseenter="window.sfCockpitSound && window.sfCockpitSound('hover')"
                             class="group relative bg-white dark:bg-[#12141c] rounded-[2rem] border border-gray-200 dark:border-gray-800/90 p-6 sm:p-7 hover:border-[#C5A059]/70 transition-all duration-300 hover:-translate-y-1 shadow-sm dark:shadow-xl flex flex-col cursor-pointer">
                            <div class="flex items-center justify-between mb-5">
                                <div class="w-14 h-14 rounded-2xl bg-gray-50 dark:bg-[#0b0d13] border border-gray-200 dark:border-gray-800 flex items-center justify-center text-3xl group-hover:scale-105 transition-transform">
                                    🏆
                                </div>
                                <span class="px-3 py-1 rounded-full bg-[#C5A059]/12 border border-[#C5A059]/30 text-amber-700 dark:text-[#f5dca0] text-[9px] font-black uppercase tracking-widest">
                                    Top 50 Global
                                </span>
                            </div>
                            <h4 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white mb-2">Halle der Legenden</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-xs sm:text-sm leading-relaxed mb-6 flex-1">
                                Vergleiche dein Prestige-Level, deine Flotte und deinen Funkenflug-Rekord mit den besten Helden der Manufaktur!
                            </p>
                            <button type="button" @click.stop="switchCockpitPanel('ranking')" class="mt-auto w-full py-3.5 bg-gray-100 dark:bg-[#0b0d13] group-hover:bg-[#C5A059] text-gray-800 dark:text-gray-200 group-hover:text-gray-950 border border-gray-200 dark:border-gray-800 group-hover:border-[#C5A059] rounded-xl font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all cursor-pointer">
                                Ruhmeshalle öffnen →
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    {{-- AKTIVES SPIEL: KRISTALL-KOLLAPS --}}
    <div x-show="activeGame === 'kristall'" x-cloak x-transition:enter="transition ease-out duration-500 delay-200" x-transition:enter-start="opacity-0 translate-y-10" x-transition:enter-end="opacity-100 translate-y-0" class="w-full max-w-[1400px] mx-auto flex-1 flex flex-col">

        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 sm:gap-6 mb-6 sm:mb-8 bg-white dark:bg-gray-900/80 p-4 sm:p-6 rounded-2xl sm:rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-lg">
            <div class="flex items-center gap-3 sm:gap-4 bg-gray-50 dark:bg-gray-950 px-4 py-2.5 sm:px-5 sm:py-3 rounded-xl sm:rounded-2xl border border-gray-200 dark:border-gray-800 shadow-inner w-full sm:w-auto justify-center">
                <button type="button" @click="toggleMute()" class="text-gray-400 hover:text-emerald-500 transition-colors focus:outline-none shrink-0">
                    <svg x-show="isBgmPlaying" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5 12h4l4-4v16l-4-4H5a2 2 0 01-2-2v-4a2 2 0 012-2z" /></svg>
                    <svg x-show="!isBgmPlaying" style="display: none;" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                </button>
                <input type="range" id="kristall-bgm-vol" name="bgmVolume" aria-label="Lautstärke" min="0" max="100" step="1" x-model="bgmVolumeUi" class="volume-slider w-full sm:w-32">
                <span class="text-xs text-gray-500 font-bold w-8" x-text="bgmVolumeUi + '%'"></span>
            </div>

            <button type="button" @click="quitGame()" class="w-full sm:w-auto justify-center px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl sm:rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-all flex items-center gap-3 shadow-sm">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Spiel verlassen
            </button>
        </div>

        <div class="relative w-full flex-1 flex flex-col">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6 lg:gap-12 flex-1 pb-10 transition-all duration-500" :class="gameState === 'ready' ? 'opacity-20 blur-md pointer-events-none grayscale-[50%]' : 'opacity-100 blur-0'">

                <div class="lg:col-span-4 xl:col-span-4 flex flex-col gap-6 w-full">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl sm:rounded-[2rem] p-4 sm:p-8 shadow-sm dark:shadow-[0_20px_40px_rgba(0,0,0,0.5)] relative overflow-hidden text-center flex flex-row lg:flex-col items-center justify-around gap-3">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="relative z-10 flex-1 lg:w-full lg:mb-6">
                            <p class="text-gray-500 dark:text-gray-400 text-[9px] sm:text-xs font-black uppercase tracking-widest mb-1 sm:mb-2">Aktuelle Punkte</p>
                            <h4 class="text-3xl sm:text-6xl font-serif font-bold text-emerald-600 dark:text-emerald-400 drop-shadow-none dark:drop-shadow-[0_0_15px_rgba(16,185,129,0.4)] leading-none" x-text="score"></h4>
                            <div class="mt-1.5 sm:mt-3 inline-flex items-center gap-1.5 sm:gap-2 bg-gray-50 dark:bg-gray-950 px-2.5 py-1 sm:px-4 sm:py-2 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                                <span class="text-xs sm:text-base">✨</span>
                                <span class="text-[10px] sm:text-sm font-bold text-amber-700 dark:text-primary">= <span x-text="Math.floor(score / 100)"></span> Funken</span>
                            </div>
                        </div>

                        <div class="w-px h-14 lg:w-full lg:h-px bg-gray-200 dark:bg-gray-800 relative z-10 lg:my-2 shrink-0"></div>

                        <div class="relative z-10 flex-1 lg:w-full lg:mt-6">
                            <p class="text-gray-500 dark:text-gray-400 text-[9px] sm:text-xs font-black uppercase tracking-widest mb-1 sm:mb-2">Züge übrig</p>
                            <h4 class="text-3xl sm:text-6xl font-serif font-bold text-gray-900 dark:text-white transition-colors leading-none" :class="{'text-red-500 animate-bounce': moves <= 5}" x-text="moves"></h4>
                        </div>
                    </div>

                    {{-- VISUELLES HANDBUCH INGAME --}}
                    <div class="hidden lg:flex bg-blue-50/70 dark:bg-blue-500/5 border border-blue-200 dark:border-blue-500/20 p-5 sm:p-6 rounded-[2rem] text-blue-900 dark:text-blue-300 text-xs sm:text-sm leading-relaxed flex-col gap-4 shadow-sm dark:shadow-inner [&_strong]:text-blue-950 dark:[&_strong]:text-blue-200">
                        <strong class="text-blue-700 dark:text-blue-400 uppercase tracking-[0.2em] font-black text-[10px] border-b border-blue-200 dark:border-blue-500/20 pb-3 block w-full">Das Handbuch</strong>
                        <ul class="space-y-4">
                            @include('livewire.customer.partials.games-component.kristall-kollaps-handbuch-items')
                        </ul>
                    </div>
                </div>

                <div class="lg:col-span-8 xl:col-span-8 w-full flex justify-center items-center relative z-10">
                    <div class="w-full max-w-[800px] aspect-square relative rounded-[2rem] sm:rounded-[3rem] bg-gray-100 dark:bg-gray-900 border-2 border-gray-200 dark:border-gray-700 shadow-md dark:shadow-[0_20px_50px_rgba(0,0,0,0.6)] overflow-hidden flex items-center justify-center touch-none">
                        <div id="threejs-match3-container" x-ref="canvasContainer" wire:ignore class="absolute inset-0 z-10 touch-none"></div>
                        <div id="floating-scores-layer" class="absolute inset-0 pointer-events-none z-30 overflow-hidden"></div>

                        {{-- INGAME OVERLAY MENU (FULLSCREEN & QUIT) --}}
                        <div class="absolute top-4 right-4 z-50 flex items-center gap-2 pointer-events-auto">
                            <button type="button" @click="toggleFullscreen()" class="w-10 h-10 rounded-xl bg-white/90 dark:bg-gray-900/80 backdrop-blur border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:border-emerald-500/50 flex items-center justify-center transition-all shadow-md dark:shadow-lg" title="Vollbild umschalten">
                                <svg x-show="!isFullscreen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                <svg x-show="isFullscreen" style="display: none;" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14h4v4M4 14l5 5M14 4h4v4M14 4l5-5M4 10h4V6M4 10l5-5M20 14h-4v4M20 14l-5 5" /></svg>
                            </button>
                            <button x-show="isFullscreen" style="display: none;" type="button" @click="quitGame()" class="w-10 h-10 rounded-xl bg-red-500/20 backdrop-blur border border-red-500/50 text-red-500 dark:text-red-400 hover:bg-red-500 hover:text-white flex items-center justify-center transition-all shadow-md dark:shadow-lg" title="Spiel verlassen">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        {{-- INGAME FULLSCREEN HUD --}}
                        <div x-show="isFullscreen && gameState === 'playing'" style="display: none;" class="absolute top-4 left-4 z-50 flex gap-3 pointer-events-none">
                            <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 shadow-md dark:shadow-lg flex items-center gap-3">
                                <span class="text-2xl drop-shadow-[0_0_10px_rgba(16,185,129,0.5)]">✨</span>
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest leading-none">Ausbeute</span>
                                    <span class="text-lg font-bold text-amber-700 dark:text-primary leading-none mt-1" x-text="Math.floor(score / 100)"></span>
                                </div>
                            </div>
                            <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 shadow-md dark:shadow-lg flex items-center gap-3">
                                <span class="text-2xl drop-shadow-[0_0_10px_rgba(255,255,255,0.5)]">🎯</span>
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest leading-none">Züge übrig</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-white leading-none mt-1 transition-colors" :class="{'text-red-500 animate-bounce': moves <= 5}" x-text="moves"></span>
                                </div>
                            </div>
                        </div>

                        <div x-show="gameState === 'gameover'" x-cloak x-transition.opacity class="absolute inset-0 z-40 bg-white/95 dark:bg-gray-950/85 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center">
                            

                            <h3 class="text-5xl sm:text-6xl font-serif font-bold text-emerald-600 dark:text-emerald-400 mb-4 drop-shadow-none dark:drop-shadow-[0_0_15px_rgba(16,185,129,0.5)]">Erfolg!</h3>
                            <div class="inline-flex items-center gap-3 bg-gray-50 dark:bg-gray-900 px-6 py-3 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner mb-8">
                                <span class="text-gray-500 dark:text-gray-400 uppercase font-black text-xs tracking-widest">Ausbeute:</span>
                                <span class="text-amber-700 dark:text-primary font-bold text-2xl sm:text-3xl" x-text="Math.floor(score / 100)"></span>
                            </div>
                            <button type="button" @click="attemptStartGame()" 
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
                                    :class="energyWarning ? 'bg-red-600 text-white shadow-md dark:shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-emerald-500 hover:bg-emerald-400 text-gray-900 shadow-md dark:shadow-[0_0_40px_rgba(16,185,129,0.5)] hover:scale-105'">
                                <span x-show="!energyWarning">Spiel Starten</span>
                                <span x-show="energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    0 Energie!
                                </span>
                            </button>
                            <p class="text-emerald-600 dark:text-emerald-400 mt-5 font-black text-xs sm:text-sm uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                            <button type="button" @click="quitGame()" class="mt-4 text-gray-500 hover:text-gray-900 dark:hover:text-red-400 font-bold text-xs uppercase tracking-widest transition-colors">
                                Abbrechen / Schließen
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="gameState === 'ready'" x-cloak x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 z-50 flex items-center justify-center p-2.5 sm:p-6">

                <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-2xl border border-gray-200 dark:border-gray-700 shadow-2xl dark:shadow-[0_0_100px_rgba(0,0,0,0.8)] rounded-[2rem] sm:rounded-[3rem] w-full max-w-5xl p-5 sm:p-10 lg:p-12 relative flex flex-col lg:flex-row gap-5 lg:gap-12 items-center lg:items-stretch overflow-y-auto max-h-full">

                    <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/10 rounded-full blur-[80px] pointer-events-none"></div>

                    {{-- VISUELLES HANDBUCH START-MODAL --}}
                    <div class="flex-1 bg-gray-50/90 dark:bg-gray-950/60 rounded-3xl p-6 sm:p-8 border border-gray-200 dark:border-gray-800 w-full flex flex-col justify-center shadow-sm dark:shadow-inner relative z-10">
                        <div class="flex items-center justify-between border-b border-blue-500/20 pb-4 mb-6">
                            <h3 class="text-blue-700 dark:text-blue-400 font-black uppercase tracking-[0.2em] text-xs sm:text-sm flex items-center gap-3">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                Das Handbuch
                            </h3>
                        </div>
                        <ul class="space-y-4 sm:space-y-5 text-gray-700 dark:text-gray-300 [&_strong]:text-gray-900 dark:[&_strong]:text-white text-xs sm:text-sm leading-relaxed max-h-[35vh] sm:max-h-none overflow-y-auto pr-2">
                            @include('livewire.customer.partials.games-component.kristall-kollaps-handbuch-items')
                        </ul>
                    </div>

                    <div class="flex-1 flex flex-col justify-center items-center w-full relative z-10">

                        <div class="flex gap-4 sm:gap-6 mb-8 w-full justify-center">
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-6 text-center flex-1 shadow-sm dark:shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-2">Start-Züge</p>
                                <p class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">15</p>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-6 text-center flex-1 shadow-sm dark:shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-2">Letzte Punkte</p>
                                <p class="text-3xl sm:text-4xl font-serif font-bold text-emerald-600 dark:text-emerald-400 drop-shadow-none dark:drop-shadow-[0_0_10px_rgba(16,185,129,0.3)]" x-text="score">0</p>
                            </div>
                        </div>

                        <div class="relative w-full mt-2">
                            <button type="button" @click="attemptStartGame()" 
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
                                    :class="energyWarning ? 'bg-red-600 text-white shadow-md dark:shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-emerald-500 hover:bg-emerald-400 text-gray-900 shadow-md dark:shadow-[0_0_40px_rgba(16,185,129,0.5)] hover:scale-105'">
                                <span x-show="!energyWarning">Spiel Starten</span>
                                <span x-show="energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    0 Energie!
                                </span>
                            </button>
                            <div class="flex justify-between items-center mt-5 px-2">
                                <p class="text-emerald-600 dark:text-emerald-500 font-black text-[10px] sm:text-xs uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                                <button type="button" @click="activeGame = null" class="text-gray-500 hover:text-gray-900 dark:hover:text-white font-black text-[10px] sm:text-xs uppercase tracking-widest transition-colors">Abbrechen</button>
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
        <audio x-ref="ffBgmAudio" src="{{ asset('shop/customer/gamification/music/funkenflug.mp3') }}?v=1" loop preload="auto"></audio>

        <div class="relative w-full flex-1 flex flex-col">

            <div class="flex flex-col gap-6 lg:gap-8 flex-1 pb-10 transition-opacity duration-500" :class="gameState === 'ready' ? 'opacity-20 blur-md pointer-events-none grayscale-[50%]' : 'opacity-100'">


                {{-- MIDDLE: SPIELFELD --}}
                <div id="ff-fullscreen-container" class="w-full flex justify-center items-center relative z-10 h-full" :class="{'fixed inset-0 z-[9999] w-screen h-[100dvh] bg-black': isFullscreen}">
                    <div id="ff-main-wrapper" :class="{'h-full w-full max-w-[1200px] aspect-auto rounded-none border-0 shadow-[0_0_100px_rgba(245,158,11,0.1)]': isFullscreen, 'min-h-[72dvh] sm:min-h-[640px] aspect-[3/4] sm:aspect-square w-full max-w-[800px] rounded-[1.75rem] sm:rounded-[3rem] border-2 border-gray-200 dark:border-gray-700 shadow-xl dark:shadow-[0_20px_50px_rgba(0,0,0,0.6)]': !isFullscreen, 'ff-playing-cursor-none': gameState === 'playing' && !isPaused && !isDying}" class="overflow-hidden relative bg-gray-950 sm:bg-gray-900 flex flex-col pointer-events-auto transition-all duration-300">
                        
                        {{-- SCREEN AREA (100% on mobile and desktop) --}}
                        <div class="relative w-full h-full shrink-0 flex flex-col bg-gray-950">

                            <div id="funkenflug-container" x-ref="ffContainer" wire:ignore class="absolute inset-0 z-10" :class="{'touch-none': gameState === 'playing', 'ff-playing-cursor-none': gameState === 'playing' && !isPaused && !isDying}"></div>
                            <div id="ff-floating-layer" class="absolute inset-0 pointer-events-none z-30 overflow-hidden"></div>

                        {{-- UI SAFE ZONE FOR FULLSCREEN --}}
                        <div class="absolute inset-0 pointer-events-none z-40 w-full h-full flex justify-center">
                            <div class="w-full h-full max-w-[800px] relative">
                                
                                {{-- TOP RIGHT CONTROLS (MOBILE & DESKTOP) --}}
                                <div class="absolute top-2.5 right-2.5 sm:top-4 sm:right-4 z-50 flex flex-row sm:flex-col gap-1.5 sm:gap-2 pointer-events-auto" style="margin-top: env(safe-area-inset-top, 0px);">
                            {{-- PAUSE BUTTON --}}
                            <button type="button" @click="togglePause()" x-show="gameState === 'playing'" class="text-gray-300 hover:text-amber-400 bg-gray-950/85 p-2 sm:px-4 sm:py-3 rounded-xl border border-gray-800 shadow-lg transition-colors flex items-center justify-center gap-2 active:scale-95">
                                <svg x-show="!isPaused" class="w-4 h-4 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <svg x-show="isPaused" style="display: none;" class="w-4 h-4 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest hidden sm:block">Pause (ESC)</span>
                            </button>
                            {{-- FULLSCREEN BUTTON --}}
                            <button type="button" @click="toggleFullscreen()" class="text-gray-300 hover:text-amber-400 bg-gray-950/85 p-2 sm:px-4 sm:py-3 rounded-xl border border-gray-800 shadow-lg transition-colors flex items-center justify-center gap-2 active:scale-95">
                                <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                <span class="text-[10px] sm:text-xs font-bold uppercase tracking-widest hidden xl:block">Vollbild (V)</span>
                            </button>
                                </div>

                                {{-- TOP LEFT: GESAMMELTE FUNKEN & WAFFEN-KOMBO --}}
                                <div class="absolute top-2.5 left-2.5 sm:top-6 sm:left-6 z-40 flex flex-col items-start gap-1 pointer-events-auto" style="margin-top: env(safe-area-inset-top, 0px);" x-show="gameState === 'playing'">
                                    <div class="bg-gray-950/90 px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-xl border border-gray-800 shadow-inner flex items-center gap-1.5 sm:gap-2">
                                        <span class="text-xs sm:text-sm">✨</span>
                                        <span class="text-xs sm:text-sm font-bold text-amber-500" x-text="funkenCollected">0</span>
                                    </div>
                                    {{-- KOMBO & WAFFEN-STUFE --}}
                                    <div class="bg-gray-950/90 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-xl border transition-all duration-300 flex items-center gap-1 sm:gap-1.5 shadow-md"
                                         :class="weaponTier === 3 ? 'border-amber-400/80 shadow-[0_0_12px_rgba(251,191,36,0.35)]' : (weaponTier === 2 ? 'border-cyan-400/70 shadow-[0_0_10px_rgba(34,211,238,0.25)]' : 'border-gray-800')">
                                        <span class="text-[9px] sm:text-xs font-black uppercase tracking-wider"
                                              :class="weaponTier === 3 ? 'text-amber-400' : (weaponTier === 2 ? 'text-cyan-400' : 'text-gray-400')"
                                              x-text="weaponTier === 3 ? '🔥 LASER III' : (weaponTier === 2 ? '⚡ LASER II' : '🔹 LASER I')"></span>
                                        <span x-show="comboCount > 0" class="text-[9px] sm:text-[10px] font-mono font-bold text-white bg-gray-800/90 px-1 py-0.5 rounded" x-text="'x' + comboCount"></span>
                                    </div>
                                </div>

                                {{-- TOP CENTER: DISTANZ / REKORD, SCHILD & SEKTOR-BOSS ANZEIGE --}}
                                <div class="absolute top-2.5 sm:top-6 left-1/2 -translate-x-1/2 z-40 flex flex-col items-center gap-0.5 sm:gap-1 pointer-events-auto" style="margin-top: env(safe-area-inset-top, 0px);" x-show="gameState === 'playing'">
                                    <p class="text-[7px] sm:text-[10px] text-gray-300 font-black uppercase tracking-widest bg-gray-950/90 border border-gray-800/80 px-2.5 py-0.5 rounded-full shadow-md">Distanz / Rekord</p>
                                    <div class="text-lg sm:text-2xl md:text-3xl font-serif font-bold text-white drop-shadow-md flex items-baseline gap-1 leading-none">
                                        <span id="ff-hud-distance" x-text="distance" class="text-amber-400 drop-shadow-[0_0_10px_rgba(245,158,11,0.5)]">0</span>
                                        <span class="text-gray-400 text-xs sm:text-base">/ {{ $personalHighscoreFF }}</span>
                                    </div>
                                    {{-- KOMPAKTE RUMPF- & SCHILD-ANZEIGE IM SICHTFELD (MOBIL & DESKTOP) --}}
                                    <div class="mt-0.5 sm:mt-1 flex items-center gap-1 sm:gap-1.5">
                                        {{-- RUMPF-INTEGRITÄT (3 HERZEN FÜR JÄGER, 5-7 HERZEN FÜR SCHLACHTSCHIFFE) --}}
                                        <div class="h-4 sm:h-5 px-2 sm:px-2.5 rounded-full bg-gray-950/90 border flex items-center gap-1 shadow-lg transition-all duration-300"
                                             :class="hullHp === 1 ? 'border-red-500 bg-red-950/80 shadow-[0_0_16px_rgba(239,68,68,0.65)] animate-pulse' : (hullHp < (maxHullHp || 3) ? 'border-orange-400/90 bg-orange-950/60 shadow-[0_0_12px_rgba(249,115,22,0.45)]' : 'border-emerald-500/40')">
                                            <div class="flex items-center gap-0.5 text-[9px] sm:text-[10px]">
                                                <template x-for="i in (maxHullHp || 3)" :key="i">
                                                    <span x-text="i <= hullHp ? (hullHp < (maxHullHp || 3) ? '🔥' : '❤️') : '🖤'" class="leading-none"></span>
                                                </template>
                                            </div>
                                            <span x-show="hullHp < (maxHullHp || 3)" class="text-[8px] sm:text-[9px] font-black uppercase tracking-wider ml-0.5"
                                                  :class="hullHp === 1 ? 'text-red-300' : 'text-orange-300'"
                                                  x-text="hullHp === 1 ? 'KRITISCH!' : 'BRENNT!'"></span>
                                        </div>

                                        {{-- ENERGIESCHILD --}}
                                        <div class="w-20 sm:w-36 h-4 sm:h-5 rounded-full bg-gray-950/90 border overflow-hidden relative flex items-center shadow-lg transition-all duration-300"
                                             :class="shieldEnergy > 0 ? 'border-cyan-400/80 shadow-[0_0_14px_rgba(34,211,238,0.45)]' : 'border-blue-500/35'">
                                            <div class="absolute left-0 top-0 bottom-0 bg-gradient-to-r from-blue-600 via-cyan-500 to-cyan-300 transition-all duration-300"
                                                 :style="`width: ${shieldEnergy}%`"></div>
                                            <div class="relative z-10 w-full flex items-center justify-center gap-1 text-[8px] sm:text-[9px] font-black uppercase tracking-wider text-white drop-shadow">
                                                <span>🛡️</span>
                                                <span :class="shieldEnergy > 0 ? 'text-cyan-200' : 'text-gray-400'" x-text="shieldEnergy + '%'">0%</span>
                                            </div>
                                        </div>
                                    </div>
                                    {{-- SEKTOR-BOSS LEBENSBALKEN (ERSCHEINT ALLE 1.500m) --}}
                                    <div x-show="bossActive" x-transition.opacity class="mt-1 w-48 sm:w-72 bg-gray-950/95 border border-rose-500/80 rounded-xl p-1 sm:p-1.5 shadow-[0_0_20px_rgba(244,63,94,0.4)]">
                                        <div class="flex items-center justify-between text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-rose-300 px-1">
                                            <span class="truncate">👾 <span x-text="bossName">Lord Lieblos</span></span>
                                            <span class="shrink-0 ml-2" x-text="bossHpPercent + '%'">100%</span>
                                        </div>
                                        <div class="text-[7px] sm:text-[8px] text-amber-300/85 font-bold uppercase tracking-wider px-1 mb-1 truncate" x-text="bossSubtitle">Der Massenware-Monolith</div>
                                        <div class="w-full h-2 sm:h-2.5 bg-gray-900 rounded-full overflow-hidden border border-rose-900/60">
                                            <div class="h-full bg-gradient-to-r from-rose-600 via-red-500 to-amber-400 transition-all duration-150"
                                                 :style="`width: ${bossHpPercent}%`"></div>
                                        </div>
                                    </div>
                                </div>

                                {{-- BOTTOM: SCHILD ENERGIE BAR (MIT SAFE-AREA ABSTAND FÜR SMARTPHONES) --}}
                                <div class="absolute bottom-2 sm:bottom-0 left-3 right-3 sm:left-0 sm:right-0 sm:w-full z-40 pointer-events-auto" style="margin-bottom: env(safe-area-inset-bottom, 0px);" x-show="gameState === 'playing'">
                                    <div class="relative w-full h-5 sm:h-6 bg-gray-950/90 rounded-full sm:rounded-none border border-cyan-500/40 sm:border-0 sm:border-t sm:border-gray-800 flex items-center overflow-hidden shadow-[0_0_15px_rgba(6,182,212,0.2)]">
                                        <div class="absolute left-0 top-0 bottom-0 bg-gradient-to-r from-blue-600 to-cyan-400 transition-all duration-300 shadow-[0_0_15px_rgba(37,99,235,0.8)]" :style="`width: ${shieldEnergy}%`"></div>
                                        <div class="absolute inset-0 flex items-center justify-center text-[9px] sm:text-[10px] font-black text-white drop-shadow-md tracking-widest uppercase">
                                            🛡️ Schild Energie <span class="ml-2" x-text="shieldEnergy + '%'"></span>
                                        </div>
                                    </div>
                                </div>

                                {{-- SKILL BUTTONS (Desktop Overlay mit 1-4 & W,A,S,D) --}}
                                <div class="absolute bottom-4 left-4 sm:bottom-6 sm:left-6 z-40 hidden sm:flex flex-col items-center gap-2 origin-bottom-left scale-[0.95] sm:scale-[0.85] opacity-70 hover:opacity-100 transition-opacity pointer-events-auto" x-show="gameState === 'playing'">
                            <!-- W / 1 Button -->
                            <div class="flex justify-center w-full">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(1)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-red-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[0], 'bg-red-900/80 border-red-500': !skillFlash[0], 'opacity-50 grayscale pointer-events-none': (skillLevels[0] === 0 || skillCooldowns[0] > 0) && !skillFlash[0]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">🔥</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[0] / 30) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -left-1 sm:-top-2 sm:-left-2 w-4 h-4 sm:w-5 sm:h-5 bg-gray-900 border border-amber-500/70 text-amber-400 rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">W</div>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">1</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-red-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Multishoot</span>
                                </div>
                            </div>

                            <!-- A, S, D / 2, 3, 4 Buttons -->
                            <div class="flex justify-center gap-2 sm:gap-4 w-full">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(2)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-purple-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[1], 'bg-purple-900/80 border-purple-500': !skillFlash[1], 'opacity-50 grayscale pointer-events-none': (skillLevels[1] === 0 || skillCooldowns[1] > 0) && !skillFlash[1]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">⚡</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[1] / 15) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -left-1 sm:-top-2 sm:-left-2 w-4 h-4 sm:w-5 sm:h-5 bg-gray-900 border border-amber-500/70 text-amber-400 rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">A</div>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">2</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-purple-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Teleport</span>
                                </div>

                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(3)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-blue-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[2], 'bg-blue-900/80 border-blue-500': !skillFlash[2], 'opacity-50 grayscale pointer-events-none': (skillLevels[2] === 0 || skillCooldowns[2] > 0) && !skillFlash[2]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">🛡️</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[2] / 20) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -left-1 sm:-top-2 sm:-left-2 w-4 h-4 sm:w-5 sm:h-5 bg-gray-900 border border-amber-500/70 text-amber-400 rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">S</div>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">3</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-blue-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Schild</span>
                                </div>

                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(4)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-yellow-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[3], 'bg-yellow-900/80 border-yellow-500': !skillFlash[3], 'opacity-50 grayscale pointer-events-none': (skillLevels[3] === 0 || skillCooldowns[3] > 0) && !skillFlash[3]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">⭐</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[3] / 60) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -left-1 sm:-top-2 sm:-left-2 w-4 h-4 sm:w-5 sm:h-5 bg-gray-900 border border-amber-500/70 text-amber-400 rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">D</div>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">4</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-yellow-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Ultimate</span>
                                </div>
                            </div>
                                </div>

                                {{-- SKILL BUTTONS (Mobile Overlay - Kompakt & Daumen-Ergonomisch) --}}
                                <div class="absolute bottom-10 z-40 sm:hidden flex flex-col items-center gap-2.5 transition-all duration-300 pointer-events-auto" :class="leftHandedMode ? 'right-2.5' : 'left-2.5'" x-show="gameState === 'playing'">
                                    <!-- Skill 1 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(1)" class="w-11 h-11 rounded-full border-2 flex items-center justify-center text-red-100 font-black overflow-hidden active:scale-95 transition-all duration-150 shadow-lg" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[0], 'bg-red-900/90 border-red-500': !skillFlash[0], 'opacity-40 grayscale pointer-events-none': (skillLevels[0] === 0 || skillCooldowns[0] > 0) && !skillFlash[0]}">
                                            <span class="text-lg z-10 drop-shadow-md">🔥</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[0] / 30) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">1</div>
                                    </div>
                                    <!-- Skill 2 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(2)" class="w-11 h-11 rounded-full border-2 flex items-center justify-center text-purple-100 font-black overflow-hidden active:scale-95 transition-all duration-150 shadow-lg" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[1], 'bg-purple-900/90 border-purple-500': !skillFlash[1], 'opacity-40 grayscale pointer-events-none': (skillLevels[1] === 0 || skillCooldowns[1] > 0) && !skillFlash[1]}">
                                            <span class="text-lg z-10 drop-shadow-md">⚡</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[1] / 15) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">2</div>
                                    </div>
                                    <!-- Skill 3 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(3)" class="w-11 h-11 rounded-full border-2 flex items-center justify-center text-blue-100 font-black overflow-hidden active:scale-95 transition-all duration-150 shadow-lg" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[2], 'bg-blue-900/90 border-blue-500': !skillFlash[2], 'opacity-40 grayscale pointer-events-none': (skillLevels[2] === 0 || skillCooldowns[2] > 0) && !skillFlash[2]}">
                                            <span class="text-lg z-10 drop-shadow-md">🛡️</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[2] / 20) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">3</div>
                                    </div>
                                    <!-- Skill 4 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(4)" class="w-11 h-11 rounded-full border-2 flex items-center justify-center text-yellow-100 font-black overflow-hidden active:scale-95 transition-all duration-150 shadow-lg" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[3], 'bg-yellow-900/90 border-yellow-500': !skillFlash[3], 'opacity-40 grayscale pointer-events-none': (skillLevels[3] === 0 || skillCooldowns[3] > 0) && !skillFlash[3]}">
                                            <span class="text-lg z-10 drop-shadow-md">⭐</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[3] / 60) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">4</div>
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

                            <!-- SOUND CONTROL IN PAUSE -->
                            <div class="mt-6 bg-gray-950 border border-gray-800 shadow-inner px-4 py-3 rounded-xl flex items-center gap-4 w-full max-w-[250px]">
                                <button type="button" @click="toggleMute()" class="text-gray-400 hover:text-amber-400 transition-colors focus:outline-none shrink-0">
                                    <svg x-show="isBgmPlaying" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5 12h4l4-4v16l-4-4H5a2 2 0 01-2-2v-4a2 2 0 012-2z" /></svg>
                                    <svg x-show="!isBgmPlaying" style="display: none;" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                                </button>
                                <input type="range" name="bgmVolumePause" aria-label="Lautstärke" min="0" max="100" step="1" x-model="bgmVolumeUi" class="volume-slider w-full">
                            </div>

                            <!-- Handedness Switch -->
                            <label class="relative inline-flex items-center cursor-pointer mt-6">
                                <input type="checkbox" x-model="leftHandedMode" class="sr-only peer">
                                <div class="w-10 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                                <span class="ml-2 text-xs font-bold text-amber-300 uppercase tracking-widest" x-text="leftHandedMode ? 'Linkshänder' : 'Rechtshänder'"></span>
                            </label>
                        </div>
                        <!-- Close Safe Zone -->
                            </div>
                        </div>


                        <div x-show="gameState === 'gameover'" x-cloak x-transition.opacity class="absolute inset-0 z-40 bg-white/95 dark:bg-gray-950/85 backdrop-blur-md flex flex-col items-center justify-center p-6 text-center pointer-events-auto">
                            
                            {{-- FULLSCREEN TOGGLE --}}
                            <button x-show="isFullscreen" type="button" @click="toggleFullscreen()" class="absolute top-4 right-4 text-gray-700 dark:text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-2 sm:px-4 sm:py-2 rounded-xl text-[10px] sm:text-xs font-bold uppercase tracking-widest flex items-center justify-center gap-2 transition-colors z-50 shadow-sm dark:shadow-lg">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                </svg>
                                <span class="hidden sm:block">Vollbild (V)</span>
                            </button>                                

                            <h3 class="text-5xl sm:text-6xl font-serif font-bold text-amber-600 dark:text-amber-500 mb-4 drop-shadow-none dark:drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">Zerstört!</h3>
                            <div class="inline-flex flex-col items-center gap-2 bg-gray-50 dark:bg-gray-900 px-6 py-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner mb-8 w-full max-w-sm">
                                <span class="text-gray-500 dark:text-gray-400 uppercase font-black text-xs tracking-widest border-b border-gray-200 dark:border-gray-800 pb-2 w-full text-center">Dein Run</span>
                                <div class="flex justify-between w-full mt-2">
                                    <span class="text-gray-600 dark:text-gray-400 uppercase font-bold text-xs">Distanz:</span>
                                    <span class="text-amber-600 dark:text-amber-500 font-bold" x-text="distance"></span>
                                </div>
                                <div class="flex justify-between w-full mt-1">
                                    <span class="text-gray-600 dark:text-gray-400 uppercase font-bold text-xs">Funken ergattert:</span>
                                    <span class="text-amber-600 dark:text-amber-500 font-bold" x-text="funkenCollected"></span>
                                </div>
                            </div>
                            <button type="button" @click="attemptStartGame()" tabindex="0"
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
                                    :disabled="assetsLoading"
                                    :class="assetsLoading ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 border border-gray-200 dark:border-gray-700 pointer-events-none' : (energyWarning ? 'bg-red-600 text-white shadow-md dark:shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-amber-500 hover:bg-amber-400 text-gray-900 shadow-md dark:shadow-[0_0_40px_rgba(245,158,11,0.5)] hover:scale-105')">
                                
                                <span x-show="assetsLoading" class="flex flex-col items-center justify-center gap-2">
                                    <span class="text-xs uppercase tracking-widest text-amber-600 dark:text-amber-500/80 mb-1">Spieldaten laden...</span>
                                    <div class="w-1/2 h-1.5 bg-gray-200 dark:bg-gray-900 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 transition-all duration-300" :style="`width: ${loadingProgress}%`"></div>
                                    </div>
                                </span>

                                <span x-show="!assetsLoading && !energyWarning">Erneut Fliegen</span>
                                <span x-show="!assetsLoading && energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    0 Energie!
                                </span>
                            </button>
                            <div class="flex justify-between items-center w-full mt-3 px-2 max-w-sm">
                                <p class="text-amber-600 dark:text-amber-500 font-black text-[10px] sm:text-xs uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                                <button type="button" @click="activeGame = null" class="text-gray-500 hover:text-gray-900 dark:hover:text-white font-black text-[10px] sm:text-xs uppercase tracking-widest transition-colors">Abbrechen</button>
                            </div>                        </div>
                        
                        </div> <!-- End Screen Area Wrapper -->
                        
                    </div>
                </div>

            </div> <!-- CLOSE BLUR WRAPPER -->

            <div x-show="gameState === 'ready'" x-cloak x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 z-50 flex items-center justify-center p-3 sm:p-6">

                <div id="ff-ready-modal-card"
                     :class="readyView === 'lobby' ? 'max-w-xl p-6 sm:p-9' : 'max-w-5xl p-5 sm:p-9 lg:p-10'"
                     class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-2xl border border-gray-200 dark:border-gray-700/90 shadow-2xl dark:shadow-[0_0_100px_rgba(0,0,0,0.85)] rounded-[2rem] sm:rounded-[2.75rem] w-full relative flex flex-col items-center overflow-y-auto max-h-full scrollbar-hide transition-[max-width,padding,border-color,box-shadow] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]">

                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-[80px] pointer-events-none"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-cyan-500/5 rounded-full blur-[80px] pointer-events-none"></div>

                    {{-- HOLODECK LIGHT-SWEEP BEIM ANSICHTSWECHSEL --}}
                    <div class="absolute inset-0 pointer-events-none overflow-hidden rounded-[2rem] sm:rounded-[2.75rem] z-30">
                        <div class="absolute inset-y-0 w-1/2 bg-gradient-to-r from-transparent via-amber-400/20 to-transparent -skew-x-12 transition-all duration-500 ease-out"
                             :class="viewTransitionPhase !== 'idle' ? 'translate-x-[220%] opacity-100' : '-translate-x-[150%] opacity-0'"></div>
                    </div>

                    {{-- CHOREOGRAPHIERTES VIEW-STAGE-WRAPPER (KEIN DOPPEL-STACKING IM DOM -> 0% ZUCKEN!) --}}
                    <div class="w-full relative z-10 transform-gpu"
                         :class="{
                             'transition-[opacity,transform,filter] duration-180 ease-in opacity-0 scale-[0.97] blur-[2px] -translate-y-2': viewTransitionPhase === 'leaving' && viewTransitionDir === 'forward',
                             'transition-[opacity,transform,filter] duration-180 ease-in opacity-0 scale-[0.97] blur-[2px] translate-y-2': viewTransitionPhase === 'leaving' && viewTransitionDir === 'back',
                             'transition-none opacity-0 scale-[0.975] blur-[2px] translate-y-3': viewTransitionPhase === 'entering' && viewTransitionDir === 'forward',
                             'transition-none opacity-0 scale-[0.975] blur-[2px] -translate-y-3': viewTransitionPhase === 'entering' && viewTransitionDir === 'back',
                             'transition-[opacity,transform,filter] duration-350 ease-[cubic-bezier(0.16,1,0.3,1)] opacity-100 scale-100 blur-0 translate-y-0': viewTransitionPhase === 'idle'
                         }">

                        {{-- ANSICHT 1: START-LOBBY --}}
                        <div class="flex-1 flex flex-col justify-center items-center w-full relative z-10 max-w-lg mx-auto"
                             x-show="readyView === 'lobby'">

                            <div class="flex gap-4 sm:gap-6 mb-4 w-full justify-center">
                                <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 flex flex-col justify-center items-center flex-1 shadow-sm dark:shadow-inner">
                                    <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-3">Lautstärke</p>
                                    <div class="flex items-center gap-3 w-full max-w-[150px]">
                                        <button type="button" @click="toggleMute()" class="text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition-colors focus:outline-none shrink-0">
                                            <svg x-show="isBgmPlaying" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5 12h4l4-4v16l-4-4H5a2 2 0 01-2-2v-4a2 2 0 012-2z" /></svg>
                                            <svg x-show="!isBgmPlaying" style="display: none;" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                                        </button>
                                        <input type="range" name="bgmVolumeStart" aria-label="Lautstärke" min="0" max="100" step="1" x-model="bgmVolumeUi" class="volume-slider w-full">
                                    </div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 text-center flex-1 shadow-sm dark:shadow-inner">
                                    <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-2">Letzte Funken</p>
                                    <p class="text-3xl sm:text-4xl font-serif font-bold text-amber-600 dark:text-amber-500 drop-shadow-none dark:drop-shadow-[0_0_10px_rgba(245,158,11,0.3)]" x-text="funkenCollected">0</p>
                                </div>
                            </div>

                            {{-- AKTIVES SCHIFF & HANGAR SCHNELLWAHL --}}
                            <div class="w-full bg-gray-50 dark:bg-gray-950/90 border {{ ($activeShipSkin['category'] ?? 'fighter') === 'battleship' ? 'border-rose-500/40' : 'border-amber-500/30' }} rounded-2xl p-3.5 sm:p-4 mb-3 flex items-center justify-between gap-3 shadow-sm">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 rounded-xl {{ ($activeShipSkin['category'] ?? 'fighter') === 'battleship' ? 'bg-rose-500/15 border-rose-500/30' : 'bg-amber-500/15 border-amber-500/30' }} border flex items-center justify-center text-xl shrink-0">
                                        {{ $activeShipSkin['icon'] ?? '🚀' }}
                                    </div>
                                    <div class="min-w-0 text-left">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="text-[9px] uppercase font-black tracking-widest {{ ($activeShipSkin['category'] ?? 'fighter') === 'battleship' ? 'text-rose-500' : 'text-amber-600 dark:text-amber-400' }}">
                                                {{ $activeShipSkin['category_label'] ?? 'Gleiter & Jäger' }}
                                            </span>
                                            <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">{{ $activeShipSkin['rarity'] ?? 'Klassisch' }}</span>
                                        </div>
                                        <p class="text-xs sm:text-sm font-black text-gray-900 dark:text-white truncate">{{ $activeShipSkin['name'] ?? 'Seelenfunke Standard-Gleiter' }}</p>
                                        <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold truncate">{{ $activeShipSkin['perk_summary'] ?? 'Standard-Konfiguration' }}</p>
                                    </div>
                                </div>
                                <button type="button" @click="switchReadyView('hangar')" class="shrink-0 px-3.5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-gray-900 font-black text-[10px] sm:text-xs uppercase tracking-widest shadow-sm transition-all hover:scale-105 cursor-pointer">
                                    🚀 Hangar
                                </button>
                            </div>

                            {{-- AKTIVES HELDEN-MOTTO & MORAL-BUFF ANZEIGE --}}
                            <div class="w-full bg-emerald-500/5 dark:bg-emerald-950/20 border border-emerald-500/25 rounded-xl px-3.5 py-2 mb-4 flex items-center justify-between gap-2 text-left">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-sm shrink-0">{{ $activeMottoDetail['icon'] ?? '💬' }}</span>
                                    <p class="text-[10px] italic text-gray-700 dark:text-gray-300 truncate">„{{ $activeMottoDetail['text'] ?? '' }}“</p>
                                </div>
                                <span class="shrink-0 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-[9px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                    {{ $activeMottoDetail['buff_label'] ?? '' }}
                                </span>
                            </div>

                            <div class="relative w-full mt-1 flex flex-col gap-3">
                                <button type="button" @click="attemptStartGame()" tabindex="0"
                                        class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all cursor-pointer"
                                        :disabled="assetsLoading"
                                        :class="assetsLoading ? 'bg-gray-100 dark:bg-gray-800 text-gray-400 border border-gray-200 dark:border-gray-700 pointer-events-none' : (energyWarning ? 'bg-red-600 text-white shadow-md dark:shadow-[0_0_30px_rgba(220,38,38,0.8)] scale-95 pointer-events-none' : 'bg-amber-500 hover:bg-amber-400 text-gray-900 shadow-md dark:shadow-[0_0_40px_rgba(245,158,11,0.5)] hover:scale-105')">
                                    
                                    <span x-show="assetsLoading" class="flex flex-col items-center justify-center gap-2">
                                        <span class="text-xs uppercase tracking-widest text-amber-600 dark:text-amber-500/80">Spieldaten laden...</span>
                                        <div class="w-3/4 max-w-[200px] h-1.5 bg-gray-200 dark:bg-gray-900 rounded-full overflow-hidden mt-1">
                                            <div class="h-full bg-amber-500 transition-all duration-300" :style="`width: ${loadingProgress}%`"></div>
                                        </div>
                                    </span>

                                    <span x-show="!assetsLoading && !energyWarning">Flug Starten</span>
                                    <span x-show="!assetsLoading && energyWarning" x-cloak class="flex items-center justify-center gap-2">
                                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                        0 Energie!
                                    </span>
                                </button>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                    <button type="button" @click="switchReadyView('hangar')" class="w-full py-3 sm:py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30 shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                                        <span>🛸</span> 3D-Hangar & Skins
                                    </button>
                                    <button type="button" @click="switchReadyView('briefing')" class="w-full py-3 sm:py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-center gap-2 cursor-pointer">
                                        <span>🛰️</span> Mission Briefing
                                    </button>
                                </div>

                                <div class="flex justify-between items-center mt-2 px-2">
                                    <p class="text-amber-600 dark:text-amber-500 font-black text-[10px] sm:text-xs uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                                    <button type="button" @click="activeGame = null" class="text-gray-500 hover:text-gray-900 dark:hover:text-white font-black text-[10px] sm:text-xs uppercase tracking-widest transition-colors cursor-pointer">Abbrechen</button>
                                </div>
                            </div>
                        </div>

                        {{-- ANSICHT 2: 3D-SEELEN-HANGAR & RUHMESHALLE --}}
                        <div class="w-full relative z-10" x-show="readyView === 'hangar'" x-cloak>
                            @include('livewire.customer.partials.games-component.seelen-hangar-panel', ['showBackButton' => true])
                        </div>

                        {{-- ANSICHT 3: NEXT-LEVEL MISSION BRIEFING (TAKTSCHES HOLO-DOSSIER) --}}
                        <div class="w-full relative z-10 text-left" x-show="readyView === 'briefing'" x-cloak>

                            {{-- BRIEFING HEADER BANNER --}}
                            <div class="relative rounded-2xl sm:rounded-3xl bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-500/15 dark:via-gray-950/80 dark:to-gray-950 border border-amber-500/30 p-4 sm:p-6 mb-6 overflow-hidden">
                                <div class="absolute -right-10 -top-10 w-44 h-44 bg-amber-400/15 rounded-full blur-2xl pointer-events-none"></div>
                                <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-start sm:items-center gap-3.5">
                                        <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-br from-amber-400 via-amber-500 to-yellow-600 p-0.5 shadow-[0_0_25px_rgba(245,158,11,0.35)] shrink-0">
                                            <div class="w-full h-full bg-white dark:bg-gray-950 rounded-[14px] flex items-center justify-center text-amber-500 dark:text-amber-400">
                                                <svg class="w-6 h-6 sm:w-7 sm:h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                    <circle cx="12" cy="12" r="9" stroke-dasharray="4 2" />
                                                    <circle cx="12" cy="12" r="5" />
                                                    <path stroke-linecap="round" d="M12 3v3m0 12v3M3 12h3m12 0h3" />
                                                    <circle cx="12" cy="12" r="1.5" fill="currentColor" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/35 text-amber-700 dark:text-amber-300 text-[9px] font-black uppercase tracking-[0.22em] mb-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                                <span>Taktisches Holo-Dossier • Sektor-Aufklärung</span>
                                            </div>
                                            <h3 class="text-lg sm:text-2xl font-serif font-black text-gray-900 dark:text-white tracking-tight">
                                                Mission Briefing: <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-yellow-500 to-amber-600 dark:from-amber-300 dark:via-yellow-400 dark:to-amber-500">Funkis Kampf für das Einzigartige</span>
                                            </h3>
                                            <p class="text-[11px] sm:text-xs text-gray-600 dark:text-gray-400 mt-0.5 leading-relaxed">
                                                Verteidige das goldene Licht der Manufaktur gegen graue 08/15-Massenware, Splitter-Kristalle und die Flotte von <em>Lord Lieblos</em>!
                                            </p>
                                        </div>
                                    </div>

                                    <button type="button"
                                            @click="switchReadyView('lobby')"
                                            class="group self-start sm:self-center text-gray-700 dark:text-gray-200 hover:text-amber-700 dark:hover:text-amber-300 transition-all duration-300 bg-white/90 dark:bg-gray-900/90 hover:bg-amber-500/15 dark:hover:bg-amber-500/20 px-4 py-2.5 rounded-xl text-xs uppercase font-black tracking-widest border border-gray-200 dark:border-gray-700 hover:border-amber-500/50 shadow-sm shrink-0 flex items-center gap-2 cursor-pointer">
                                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                        </svg>
                                        <span>Zurück</span>
                                    </button>
                                </div>
                            </div>

                            {{-- HAUPT-GRID: LINKS FEIND-RADAR & BEUTE | RECHTS COCKPIT-SYSTEME & 4 AKTIVE SKILLS --}}
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6">

                                {{-- LINKE SPALTE (7 COLS): GEGNER-RADAR & BEUTE-OBJEKTE --}}
                                <div class="lg:col-span-7 flex flex-col gap-5">

                                    {{-- SEKTION 1: BEDROHUNGEN IM SEKTOR --}}
                                    <div class="rounded-2xl bg-gray-50/90 dark:bg-gray-950/70 border border-gray-200/90 dark:border-gray-800 p-4 sm:p-5">
                                        <div class="flex items-center justify-between mb-3.5 pb-2 border-b border-gray-200 dark:border-gray-800/80">
                                            <span class="text-[10px] font-black uppercase tracking-[0.22em] text-rose-600 dark:text-rose-400 flex items-center gap-2">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                                </svg>
                                                Feind-Aufklärung & Hindernisse
                                            </span>
                                            <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Gefahren-Index</span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            {{-- 1. Grauschleier-Brocken --}}
                                            <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-gray-200/80 dark:border-gray-800 p-3 flex items-start gap-3 hover:border-red-500/40 transition-colors">
                                                <div class="w-10 h-10 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-500 dark:text-red-400 shrink-0 shadow-inner">
                                                    {{-- Facettierter 3D-Meteorit SVG --}}
                                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <polygon points="12 2 19 5 22 12 18 20 10 22 4 17 3 9 8 3" fill="currentColor" fill-opacity="0.18" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l2 6 8 4M14 8l-6 5-5-4M8 13l2 9M14 8l4 12" />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <h4 class="text-xs font-black text-gray-900 dark:text-white">Grauschleier-Brocken</h4>
                                                        <span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">Gestein</span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-snug">
                                                        Verkrustete Alltags-Meteoriten. Zerschieße sie für Kombo-Aufladung oder lass sie an deinem Schild abprallen!
                                                    </p>
                                                </div>
                                            </div>

                                            {{-- 2. Trübglas-Splitter & Sucher --}}
                                            <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-gray-200/80 dark:border-gray-800 p-3 flex items-start gap-3 hover:border-purple-500/40 transition-colors">
                                                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-500 dark:text-purple-400 shrink-0 shadow-inner">
                                                    {{-- Kristall-Splitter SVG --}}
                                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <polygon points="12 2 18 9 12 22 6 9" fill="currentColor" fill-opacity="0.18" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20M6 9h12M19 4l3 3-3 3M5 14l-3 3 3 3" />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <h4 class="text-xs font-black text-gray-900 dark:text-white">Trübglas & Sucher</h4>
                                                        <span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-purple-500/10 text-purple-600 dark:text-purple-300 border border-purple-500/20">2× Split</span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-snug">
                                                        Unreine Kristalle spalten sich beim Abschuss diagonal in <strong>2 schnelle Fragmente</strong>. Sucher-Kometen lenken auf dein Schiff zu!
                                                    </p>
                                                </div>
                                            </div>

                                            {{-- 3. Fließband-Barrieren --}}
                                            <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-gray-200/80 dark:border-gray-800 p-3 flex items-start gap-3 hover:border-orange-500/40 transition-colors">
                                                <div class="w-10 h-10 rounded-xl bg-orange-500/10 border border-orange-500/30 flex items-center justify-center text-orange-500 dark:text-orange-400 shrink-0 shadow-inner">
                                                    {{-- Laser-Sperrriegel SVG --}}
                                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <rect x="2" y="8" width="4" height="8" rx="1" fill="currentColor" fill-opacity="0.25" />
                                                        <rect x="18" y="8" width="4" height="8" rx="1" fill="currentColor" fill-opacity="0.25" />
                                                        <path stroke-linecap="round" d="M6 10h12M6 14h12M9 10l-2 4m6-4l-2 4m6-4l-2 4" />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <h4 class="text-xs font-black text-gray-900 dark:text-white">Fließband-Barrieren</h4>
                                                        <span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-600 dark:text-orange-300 border border-orange-500/20">Ab 320m</span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-snug">
                                                        Breite 08/15-Sperrschranken. Umfliege sie geschickt oder springe per <strong>Goldstaub-Teleport</strong> direkt hindurch!
                                                    </p>
                                                </div>
                                            </div>

                                            {{-- 4. Sektor-Bosse & Zwillings-Spaltung --}}
                                            <div class="rounded-xl bg-gradient-to-br from-rose-500/5 via-amber-500/5 to-transparent dark:from-rose-950/35 dark:to-gray-900/90 border border-rose-500/35 p-3 flex items-start gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-rose-500/15 border border-rose-500/40 flex items-center justify-center text-rose-500 dark:text-rose-400 shrink-0 shadow-[0_0_15px_rgba(244,63,94,0.2)]">
                                                    {{-- Dreadnought Boss Core SVG --}}
                                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <polygon points="12 2 21 7 21 17 12 22 3 17 3 7" fill="currentColor" fill-opacity="0.18" />
                                                        <circle cx="9" cy="11" r="1.4" fill="currentColor" />
                                                        <circle cx="15" cy="11" r="1.4" fill="currentColor" />
                                                        <path stroke-linecap="round" d="M8 16c1.3-1 2.7-1.5 4-1.5s2.7.5 4 1.5M12 2v4m0 12v4" />
                                                    </svg>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <h4 class="text-xs font-black text-gray-900 dark:text-white">Sektor-Bosse</h4>
                                                        <span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-rose-500/15 text-rose-600 dark:text-rose-300 border border-rose-500/30">Alle 1.500m</span>
                                                    </div>
                                                    <p class="text-[11px] text-gray-600 dark:text-gray-300 mt-1 leading-snug">
                                                        <em>Lord Lieblos</em> & Co. erscheinen aus einer Licht-Nova. Bricht ihr Hauptkern, teilen sie sich in <strong>2 schnelle Zwillings-Bosse</strong>!
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- SEKTION 2: BEUTE, HEILUNG & RUMPF-SYSTEM --}}
                                    <div class="rounded-2xl bg-gray-50/90 dark:bg-gray-950/70 border border-gray-200/90 dark:border-gray-800 p-4 sm:p-5">
                                        <div class="flex items-center justify-between mb-3.5 pb-2 border-b border-gray-200 dark:border-gray-800/80">
                                            <span class="text-[10px] font-black uppercase tracking-[0.22em] text-amber-600 dark:text-amber-400 flex items-center gap-2">
                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M12 2L14.5 9.5L22 12L14.5 14.5L12 22L9.5 14.5L2 12L9.5 9.5L12 2Z" />
                                                </svg>
                                                Beute-Orbs, Rumpf-Herzen & Waffen-Stufen
                                            </span>
                                            <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Drops & Schiff</span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            {{-- Goldene Seelenfunken --}}
                                            <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-amber-500/25 p-3 flex flex-col gap-1.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-8 h-8 rounded-lg bg-amber-500/15 border border-amber-500/35 flex items-center justify-center text-amber-500 dark:text-amber-300 shrink-0">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 2L14.6 9.4L22 12L14.6 14.6L12 22L9.4 14.6L2 12L9.4 9.4L12 2Z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h5 class="text-[11px] font-black text-gray-900 dark:text-white leading-tight">Seelenfunken</h5>
                                                        <span class="text-[9px] font-bold text-amber-600 dark:text-amber-400">+25m & Währung</span>
                                                    </div>
                                                </div>
                                                <p class="text-[10px] text-gray-600 dark:text-gray-400 leading-snug">
                                                    Bringen sofort +25m Distanz-Bonus und füllen dein Funken-Konto für Level-Ups & Skins.
                                                </p>
                                            </div>

                                            {{-- Blaue Cooldown-Orbs --}}
                                            <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-cyan-500/25 p-3 flex flex-col gap-1.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-8 h-8 rounded-lg bg-cyan-500/15 border border-cyan-500/35 flex items-center justify-center text-cyan-500 dark:text-cyan-300 shrink-0">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="12" r="8" fill="currentColor" fill-opacity="0.2" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h5 class="text-[11px] font-black text-gray-900 dark:text-white leading-tight">Äther-Kühlkern</h5>
                                                        <span class="text-[9px] font-bold text-cyan-600 dark:text-cyan-400">-5s Cooldown</span>
                                                    </div>
                                                </div>
                                                <p class="text-[10px] text-gray-600 dark:text-gray-400 leading-snug">
                                                    Verkürzt sofort die Abklingzeit aller 4 aktiven Schiffsfähigkeiten um 5 Sekunden.
                                                </p>
                                            </div>

                                            {{-- Seltene Rote Herzen --}}
                                            <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-rose-500/30 p-3 flex flex-col gap-1.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-8 h-8 rounded-lg bg-rose-500/15 border border-rose-500/35 flex items-center justify-center text-rose-500 dark:text-rose-400 shrink-0">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h5 class="text-[11px] font-black text-gray-900 dark:text-white leading-tight">Rotes Herz</h5>
                                                        <span class="text-[9px] font-bold text-rose-600 dark:text-rose-400">+1 ❤️ Reparatur</span>
                                                    </div>
                                                </div>
                                                <p class="text-[10px] text-gray-600 dark:text-gray-400 leading-snug">
                                                    Sehr seltener Drop: Löscht Rumpf-Brände (+1 ❤️) oder lädt bei 3/3 Herzen +35% Schild auf!
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- RECHTE SPALTE (5 COLS): 4 TAKTISCHE COCKPIT-FÄHIGKEITEN --}}
                                <div class="lg:col-span-5 flex flex-col">
                                    <div class="rounded-2xl bg-gray-50/90 dark:bg-gray-950/70 border border-gray-200/90 dark:border-gray-800 p-4 sm:p-5 h-full flex flex-col justify-between">
                                        <div>
                                            <div class="flex items-center justify-between mb-3.5 pb-2 border-b border-gray-200 dark:border-gray-800/80">
                                                <span class="text-[10px] font-black uppercase tracking-[0.22em] text-cyan-600 dark:text-cyan-400 flex items-center gap-2">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                    Cockpit-Arsenal & Aktive Skills
                                                </span>
                                                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Tasten 1–4 / WASD</span>
                                            </div>

                                            <div class="space-y-3">
                                                {{-- Skill 1: Multishoot --}}
                                                <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-red-500/25 p-3 flex items-start gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500/20 to-orange-500/10 border border-red-500/40 flex items-center justify-center text-red-500 dark:text-red-400 shrink-0">
                                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M7 6l-3 12M17 6l3 12M12 3l-2.5 3.5M12 3l2.5 3.5" />
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <h4 class="text-xs font-black text-gray-900 dark:text-white">Plasma-Multishoot</h4>
                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <span class="px-1.5 py-0.5 rounded bg-gray-900 text-amber-400 border border-amber-500/50 text-[9px] font-black">W</span>
                                                                <span class="px-1.5 py-0.5 rounded bg-red-600 text-white text-[9px] font-black">1</span>
                                                            </div>
                                                        </div>
                                                        <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-snug">
                                                            Entfesselt sofort maximales 3-fach-Schnellfeuer. Tipp: Auch reguläre Treffer-Serien rüsten deinen Laser bis <strong>Stufe III</strong> auf!
                                                        </p>
                                                    </div>
                                                </div>

                                                {{-- Skill 2: Goldstaub-Teleport --}}
                                                <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-purple-500/25 p-3 flex items-start gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500/20 to-fuchsia-500/10 border border-purple-500/40 flex items-center justify-center text-purple-500 dark:text-purple-300 shrink-0">
                                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="12" r="3" fill="currentColor" />
                                                            <path stroke-linecap="round" d="M12 2v4m0 12v4M2 12h4m12 0h4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M19.07 4.93l-2.83 2.83M7.76 16.24l-2.83 2.83" />
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <h4 class="text-xs font-black text-gray-900 dark:text-white">Goldstaub-Teleport</h4>
                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <span class="px-1.5 py-0.5 rounded bg-gray-900 text-amber-400 border border-amber-500/50 text-[9px] font-black">A</span>
                                                                <span class="px-1.5 py-0.5 rounded bg-red-600 text-white text-[9px] font-black">2</span>
                                                            </div>
                                                        </div>
                                                        <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-snug">
                                                            Aktiviert <strong>Bullet-Time</strong> (max. 7s) mit goldenem Sternenstaub-Leitstrahl. Klicke auf deine Wunschposition für den Dimensions-Sprung!
                                                        </p>
                                                    </div>
                                                </div>

                                                {{-- Skill 3: Fresnel-Plasma-Schild --}}
                                                <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-cyan-500/25 p-3 flex items-start gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500/20 to-blue-500/10 border border-cyan-500/40 flex items-center justify-center text-cyan-500 dark:text-cyan-300 shrink-0">
                                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" fill="currentColor" fill-opacity="0.18" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4" />
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <h4 class="text-xs font-black text-gray-900 dark:text-white">Fresnel-Plasma-Schild</h4>
                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <span class="px-1.5 py-0.5 rounded bg-gray-900 text-amber-400 border border-amber-500/50 text-[9px] font-black">S</span>
                                                                <span class="px-1.5 py-0.5 rounded bg-red-600 text-white text-[9px] font-black">3</span>
                                                            </div>
                                                        </div>
                                                        <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-snug">
                                                            Erzeugt 20s lang eine sphärische Energiekuppel: <strong>Meteoriten prallen physisch daran ab</strong>, während das Schild die Treffer-Energie absorbiert!
                                                        </p>
                                                    </div>
                                                </div>

                                                {{-- Skill 4: Ultimate (Funken-Drohnen) --}}
                                                <div class="rounded-xl bg-white dark:bg-gray-900/90 border border-amber-500/30 p-3 flex items-start gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400/20 to-yellow-500/10 border border-amber-500/45 flex items-center justify-center text-amber-500 dark:text-amber-300 shrink-0">
                                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="currentColor" fill-opacity="0.2" />
                                                        </svg>
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <h4 class="text-xs font-black text-gray-900 dark:text-white">Ultimate: Funken-Drohnen</h4>
                                                            <div class="flex items-center gap-1 shrink-0">
                                                                <span class="px-1.5 py-0.5 rounded bg-gray-900 text-amber-400 border border-amber-500/50 text-[9px] font-black">D</span>
                                                                <span class="px-1.5 py-0.5 rounded bg-red-600 text-white text-[9px] font-black">4</span>
                                                            </div>
                                                        </div>
                                                        <p class="text-[11px] text-gray-600 dark:text-gray-400 mt-1 leading-snug">
                                                            Beschwört 12s lang <strong>4 goldene Begleit-Drohnen</strong>, die dein Schiff umkreisen und autonom auf alle Feinde & Sektor-Bosse feuern!
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- KOMPAKTE RUMPF-INFO FOOTER --}}
                                        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-800/80 flex items-center justify-between gap-2 text-[10px] text-gray-500 dark:text-gray-400">
                                            <span class="flex items-center gap-1.5 font-bold">
                                                <span class="text-rose-500">❤️❤️❤️</span>
                                                <span>3-Treffer-Rumpf (brennt bei Schaden)</span>
                                            </span>
                                            <button type="button" @click="switchReadyView('lobby')" class="text-amber-600 dark:text-amber-400 hover:underline font-black uppercase tracking-wider cursor-pointer">
                                                Bereit zum Abflug →
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- =====================================================================
             C. TELEPORTED MODALS FÜR DAS SPIELER-COCKPIT (3D-ATELIER, RÄNGE, VISITENKARTE)
             ===================================================================== --}}

        {{-- 1. 3D FUNKI CHARAKTER-ATELIER IM HELLMODUS (INKL. 60 FPS LEVEL-UP MAGIE) --}}
        <template x-teleport="body">
            <div x-show="show3DModal" style="display: none;" class="fixed inset-0 z-[6000] flex flex-col items-center justify-between p-3 sm:p-8">
                <div class="absolute inset-0 bg-stone-900/45 backdrop-blur-md transition-opacity duration-200" @click="window.sfCockpitSound && window.sfCockpitSound('back'); close3DModal()" x-transition.opacity></div>

                <div class="relative w-full max-w-[90rem] flex-1 flex flex-col bg-gradient-to-b from-[#fffdf9] via-[#fdf7ea] to-[#f3e6ce] rounded-[2rem] sm:rounded-[3rem] shadow-[0_30px_90px_rgba(160,115,40,0.28)] border-2 border-amber-500/40 overflow-hidden cursor-default"
                     style="will-change: transform, opacity; transform: translateZ(0);"
                     x-transition:enter="transition ease-out duration-250"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95">

                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_42%,_rgba(251,191,36,0.28)_0%,_rgba(245,158,11,0.10)_42%,_rgba(255,255,255,0)_72%)] pointer-events-none"></div>
                    <div class="absolute inset-x-0 bottom-16 h-48 bg-[radial-gradient(ellipse_at_50%_100%,_rgba(197,160,89,0.22)_0%,_rgba(255,255,255,0)_70%)] pointer-events-none"></div>

                    <canvas id="funki-levelup-fx-canvas" class="absolute inset-0 w-full h-full z-[6045] pointer-events-none" wire:ignore></canvas>

                    <div x-show="showConfetti"
                         x-transition:enter="transition ease-out duration-400"
                         x-transition:enter-start="opacity-0 scale-90 -translate-y-4"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute inset-x-0 top-20 sm:top-24 z-[6060] pointer-events-none flex flex-col items-center px-4"
                         style="display: none;">
                        <div class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-amber-500/15 border border-amber-500/50 text-amber-800 text-[10px] sm:text-xs font-black uppercase tracking-[0.3em] mb-2 shadow-[0_8px_25px_rgba(245,158,11,0.25)]">
                            <span>⚡</span>
                            <span>Aufstieg Vollbracht</span>
                            <span>⚡</span>
                        </div>
                        <h2 class="text-4xl sm:text-6xl md:text-7xl font-serif font-black text-transparent bg-clip-text bg-gradient-to-b from-amber-500 via-amber-600 to-yellow-800 drop-shadow-[0_6px_25px_rgba(245,158,11,0.45)] tracking-tight text-center">
                            LEVEL <span x-text="activeModelLevel || {{$level}}"></span>
                        </h2>
                        <div x-show="rewardMessage" class="mt-3 px-6 py-2.5 rounded-2xl bg-white/95 border-2 border-amber-400 shadow-[0_12px_35px_rgba(245,158,11,0.3)] flex items-center gap-2.5">
                            <span class="text-xl">🎁</span>
                            <p class="text-amber-800 text-xs sm:text-base font-black uppercase tracking-widest text-center" x-text="rewardMessage"></p>
                        </div>
                    </div>

                    <div class="absolute top-3.5 left-3.5 sm:top-7 sm:left-7 z-[6050] flex items-center gap-2.5 sm:gap-4 bg-white/90 backdrop-blur-md border border-amber-500/40 rounded-[2rem] p-1.5 pr-3.5 sm:p-3.5 sm:pr-7 shadow-[0_10px_30px_rgba(197,160,89,0.2)] pointer-events-auto max-w-[54%] sm:max-w-none">
                        <div class="w-9 h-9 sm:w-12 sm:h-12 rounded-full bg-gradient-to-b from-amber-300 to-amber-500 border-2 border-amber-200 flex items-center justify-center shadow-[0_4px_15px_rgba(245,158,11,0.45)] shrink-0">
                            <span class="text-gray-950 font-black text-base sm:text-xl" x-text="activeModelLevel || {{$level}}">{{$level}}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[8px] sm:text-[9px] text-amber-700 font-black uppercase tracking-widest truncate">Seelen-Evolution</p>
                            <h3 class="text-gray-900 font-black text-[10px] sm:text-xs uppercase tracking-[0.16em] sm:tracking-[0.2em] truncate">{{ $currentRankName ?: 'Kein Rang' }}</h3>
                        </div>
                    </div>

                    <div class="absolute top-4 right-4 sm:top-7 sm:right-7 z-[6050] flex items-center gap-2.5 pointer-events-auto">
                        @if($canUpgrade)
                            <button type="button"
                                    wire:click="upgrade"
                                    wire:loading.attr="disabled"
                                    title="Jetzt auf Level {{ $level + 1 }} aufsteigen!"
                                    class="px-4 py-2.5 sm:px-5 sm:py-3 rounded-full bg-gradient-to-r from-amber-400 via-primary to-amber-500 hover:from-amber-300 hover:to-amber-400 text-gray-950 border border-amber-300 font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all shadow-[0_8px_25px_rgba(245,158,11,0.45)] hover:scale-105 flex items-center gap-1.5 cursor-pointer animate-[pulse_2s_infinite]">
                                <span>✨</span>
                                <span wire:loading.remove wire:target="upgrade" class="hidden sm:inline">Aufstiegs-Magie</span>
                                <span wire:loading.remove wire:target="upgrade" class="sm:hidden">Level Up!</span>
                                <span wire:loading wire:target="upgrade">Wirkt Magie...</span>
                            </button>
                        @else
                            <button type="button"
                                    disabled
                                    title="{{ $isMaxLevel ? 'Maximales Prestige-Level (100) erreicht' : 'Noch ' . $missingSparks . ' Funken bis zum nächsten Level-Aufstieg' }}"
                                    class="px-3.5 py-2.5 sm:px-4 sm:py-3 rounded-full bg-white/80 text-gray-400 border border-gray-300/80 font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-1.5 cursor-not-allowed opacity-75 select-none">
                                <span>🔒</span>
                                <span class="hidden sm:inline">Aufstiegs-Magie</span>
                                @if(!$isMaxLevel)
                                    <span class="text-[9px] font-bold text-amber-800/75 bg-amber-500/10 px-2 py-0.5 rounded-full border border-amber-500/25">Noch {{ $missingSparks }} F.</span>
                                @endif
                            </button>
                        @endif
                        <button type="button" @click="window.sfCockpitSound && window.sfCockpitSound('back'); close3DModal()" class="p-2.5 sm:p-3 bg-white/90 border border-gray-200 rounded-full text-gray-600 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-md hover:scale-110 cursor-pointer">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 w-full relative min-h-0 pt-20 pb-4 cursor-default">
                        <style>
                            #funki-3d-canvas-container,
                            #funki-3d-canvas-container canvas {
                                cursor: grab !important;
                            }
                            #funki-3d-canvas-container:active canvas {
                                cursor: grabbing !important;
                            }
                        </style>
                        <div id="funki-3d-canvas-container" class="absolute inset-0 w-full h-full z-10" wire:ignore></div>
                    </div>

                    <div class="w-full bg-white/90 backdrop-blur-xl border-t border-amber-500/25 relative z-[6050] pb-safe shadow-[0_-10px_35px_rgba(197,160,89,0.1)]">
                        <div class="flex flex-row items-center justify-start sm:justify-center gap-4 sm:gap-7 px-4 sm:px-10 py-4 sm:py-5 overflow-x-auto no-scrollbar scroll-smooth w-full">
                            @php
                                $milestones = \App\Services\Gamification\GameConfig::getAppearanceMilestones();
                            @endphp
                            @foreach($milestones as $mLevel => $mName)
                                <div wire:key="milestone-{{$mLevel}}" class="flex flex-col items-center gap-2 relative group shrink-0">
                                    <button type="button"
                                            @if($level >= $mLevel)
                                                @click="window.sfCockpitSound && window.sfCockpitSound('click'); selectEvolutionStage({{$mLevel}}, '{{asset('shop/customer/gamification/models/' . $mName . '.glb')}}', '{{asset('shop/customer/gamification/models/images/original/' . $mName . '.png')}}')"
                                            @endif
                                            :class="{
                                                'border-[#C5A059] bg-amber-50 shadow-[0_8px_25px_rgba(197,160,89,0.45)] scale-110 ring-4 ring-[#C5A059]/40 z-10': (typeof $store !== 'undefined' && $store.funki ? $store.funki.activeLevel : (typeof activeModelLevel !== 'undefined' ? activeModelLevel : {{min(10, $level)}})) == {{$mLevel}},
                                                'border-amber-500/35 bg-white opacity-85 hover:opacity-100 hover:scale-105 hover:border-[#C5A059] cursor-pointer shadow-sm': (typeof $store !== 'undefined' && $store.funki ? $store.funki.activeLevel : (typeof activeModelLevel !== 'undefined' ? activeModelLevel : {{min(10, $level)}})) != {{$mLevel}} && {{$level >= $mLevel ? 'true' : 'false'}},
                                                'border-gray-200 bg-gray-100 cursor-not-allowed opacity-50': {{$level < $mLevel ? 'true' : 'false'}}
                                            }"
                                            class="w-14 h-14 sm:w-18 sm:h-18 shrink-0 rounded-full border-2 flex items-center justify-center transition-all duration-300 focus:outline-none">
                                        <img src="{{asset('shop/customer/gamification/models/images/original/' . $mName . '.png')}}" class="w-full h-full object-cover rounded-full {{ $level >= $mLevel ? '' : 'blur-[6px] opacity-30 grayscale' }}">
                                    </button>
                                    <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-widest" :class="(typeof $store !== 'undefined' && $store.funki ? $store.funki.activeLevel : (typeof activeModelLevel !== 'undefined' ? activeModelLevel : {{min(10, $level)}})) == {{$mLevel}} ? 'text-amber-700 font-bold' : 'text-gray-500'">Level {{$mLevel}}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- 2. TITEL & RÄNGE MODAL --}}
        <template x-teleport="body">
            <div x-show="showTitlesModal" style="display: none;" class="fixed inset-0 z-[7000] flex items-center justify-center p-4 sm:p-6">
                <div class="absolute inset-0 bg-black/65 backdrop-blur-sm" @click="window.sfCockpitSound && window.sfCockpitSound('back'); showTitlesModal = false" x-transition.opacity></div>

                <div class="relative w-full max-w-4xl bg-white dark:bg-[#101219] border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-2xl flex flex-col max-h-[90vh] overflow-hidden"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-6"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 translate-y-6">

                    <div class="relative w-full bg-gray-50/90 dark:bg-[#141720] border-b border-gray-200 dark:border-[#C5A059]/25 p-5 sm:p-8 text-center shrink-0 flex flex-col items-center">
                        <button type="button" @click="window.sfCockpitSound && window.sfCockpitSound('back'); showTitlesModal = false" class="absolute top-4 right-4 sm:top-6 sm:right-6 p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 rounded-full hover:bg-red-500 hover:border-red-500 hover:text-white transition-colors cursor-pointer z-20">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>

                        <div class="relative z-10 w-full">
                            <div class="max-w-md mx-auto bg-white dark:bg-[#0b0d13] p-3.5 sm:p-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs mb-5">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Diamant-Titel</span>
                                    <span class="text-xs font-black text-amber-600 dark:text-[#C5A059]">{{ $titlesData['diamonds_count'] ?? 0 }} <span class="opacity-50 text-gray-400">/ {{ $titlesData['total_titles'] ?? 15 }}</span></span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-900 rounded-full h-2.5 overflow-hidden border border-gray-200 dark:border-gray-800">
                                    <div class="h-full bg-gradient-to-r from-amber-500 to-yellow-400 rounded-full transition-all duration-1000" style="width: {{ min(100, (($titlesData['diamonds_count'] ?? 0) / max(1, ($titlesData['total_titles'] ?? 15))) * 100) }}%"></div>
                                </div>
                                @if(isset($titlesData['next_mega_title']))
                                    <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-2.5 uppercase tracking-widest">Noch <span class="text-gray-900 dark:text-white font-bold">{{ max(0, $titlesData['next_mega_title']['req'] - ($titlesData['diamonds_count'] ?? 0)) }} Diamant-Titel</span> bis: <strong class="text-amber-700 dark:text-[#C5A059]">{{ $titlesData['next_mega_title']['name'] }}</strong></p>
                                @else
                                    <p class="text-[9px] sm:text-[10px] text-amber-700 dark:text-[#C5A059] font-black mt-2.5 uppercase tracking-widest">Du hast die höchste Stufe erreicht!</p>
                                @endif
                            </div>

                            <p class="text-[10px] sm:text-xs text-amber-700 dark:text-[#C5A059] font-black uppercase tracking-[0.3em] mb-1">Dein aktueller Rang</p>
                            <h2 class="text-2xl sm:text-3xl font-serif font-black text-gray-900 dark:text-white uppercase tracking-tight">{{ $currentRankName ?: 'Kein Rang' }}</h2>
                        </div>
                    </div>

                    <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar flex-1 bg-gray-50/60 dark:bg-[#0d0f16]">
                        <div class="flex flex-col gap-2.5">
                            @foreach($titlesData['titles'] ?? [] as $titleKey => $title)
                                <div class="w-full bg-white dark:bg-[#141720] border rounded-xl px-3.5 py-2.5 sm:px-4 sm:py-3 flex flex-col gap-2 transition-all {{ $activeTitleKey === $titleKey ? 'border-[#C5A059] ring-1 ring-[#C5A059]/30' : 'border-gray-200 dark:border-gray-800' }}">
                                    <div class="flex flex-wrap sm:flex-nowrap items-center justify-between gap-2 sm:gap-4">
                                        <div class="min-w-0 flex-1 text-left">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <h4 class="text-gray-900 dark:text-white font-bold text-xs sm:text-sm leading-tight truncate">{{ $title['name'] }}</h4>
                                                <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                    {{ $title['tier_name'] }}
                                                </span>
                                            </div>
                                            <p class="text-gray-500 dark:text-gray-400 text-[10px] sm:text-xs truncate mt-0.5">{{ $title['description'] }}</p>
                                        </div>

                                        <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 ml-auto">
                                            <span class="text-[10px] sm:text-xs text-gray-600 dark:text-gray-400 font-mono font-bold whitespace-nowrap">
                                                {{ (int) round((float) ($title['current_value'] ?? 0)) }} / {{ (int) round((float) ($title['next_req'] ?? 0)) }}
                                            </span>

                                            @if($title['tier'] !== 'grau')
                                                <button type="button"
                                                        wire:click="selectTitle('{{ $titleKey }}')"
                                                        @click="window.sfCockpitSound && window.sfCockpitSound('click')"
                                                        class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all whitespace-nowrap cursor-pointer {{ $activeTitleKey === $titleKey ? 'bg-[#C5A059] text-gray-950 font-black' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-[#C5A059] hover:text-gray-950 border border-gray-200 dark:border-gray-700' }}">
                                                    {{ $activeTitleKey === $titleKey ? '✓ Aktiver Rang' : 'Auswählen' }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="w-full bg-gray-100 dark:bg-gray-950 rounded-full h-2 overflow-hidden border border-gray-200/80 dark:border-gray-800">
                                        <div class="h-full rounded-full transition-all duration-700 bg-gradient-to-r from-amber-500 to-yellow-400"
                                             style="width: {{ $title['percentage'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- 3. HELDEN-VISITENKARTE (PLAYER INSPECTOR MODAL FÜR DIE RUHMESHALLE) --}}
        <template x-teleport="body">
            <div x-show="inspectedHero !== null" style="display: none;" class="fixed inset-0 z-[7000] flex items-center justify-center p-4 sm:p-6">
                <div class="absolute inset-0 bg-black/75 backdrop-blur-md" @click="window.sfCockpitSound && window.sfCockpitSound('back'); inspectedHero = null" x-transition.opacity></div>

                <div x-show="inspectedHero !== null"
                     x-transition:enter="transition ease-out duration-250"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     :class="inspectedHero?.aura_row_class || 'border-[#C5A059]/45'"
                     class="relative w-full max-w-xl bg-white dark:bg-[#12141c] border-2 rounded-[2.5rem] p-6 sm:p-8 shadow-2xl overflow-hidden z-10 text-gray-800 dark:text-gray-200">

                    <button type="button" @click="window.sfCockpitSound && window.sfCockpitSound('back'); inspectedHero = null" class="absolute top-5 right-5 w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-red-500 hover:text-white text-gray-500 flex items-center justify-center transition-colors cursor-pointer z-20">
                        ✕
                    </button>

                    <template x-if="inspectedHero">
                        <div>
                            <div class="flex flex-col sm:flex-row items-center gap-5 pb-6 border-b border-gray-200 dark:border-gray-800">
                                <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-gray-100 dark:bg-[#0b0d13] border-2 border-amber-400 p-2 flex items-center justify-center shrink-0 shadow-md">
                                    <img :src="inspectedHero.avatar_url" alt="Funki Avatar" class="w-full h-full object-contain">
                                    <span class="absolute -bottom-2 px-3 py-0.5 rounded-full bg-amber-500 text-gray-900 font-black text-[10px] uppercase tracking-widest shadow" x-text="'Platz #' + inspectedHero.rank"></span>
                                </div>

                                <div class="text-center sm:text-left min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1.5">
                                        <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-[10px] font-black uppercase tracking-widest" x-text="'Level ' + inspectedHero.level"></span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-600 dark:text-purple-300 text-[10px] font-black uppercase tracking-widest" x-text="inspectedHero.prestige_icon + ' ' + inspectedHero.prestige_name"></span>
                                        <span x-show="inspectedHero.aura_name && inspectedHero.aura_name !== 'Klassischer Rahmen'"
                                              :class="inspectedHero.aura_badge_class || 'bg-amber-500/15 text-amber-500 border-amber-500/30'"
                                              class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest border"
                                              x-text="(inspectedHero.aura_icon || '🌟') + ' ' + inspectedHero.aura_name"></span>
                                    </div>

                                    <h3 class="text-2xl font-serif font-black text-gray-900 dark:text-white truncate" x-text="inspectedHero.display_name"></h3>
                                    <p class="text-xs font-black uppercase tracking-widest text-amber-600 dark:text-[#C5A059] mt-0.5" x-text="inspectedHero.active_title"></p>
                                    <div class="mt-2.5 p-2.5 rounded-xl bg-gray-50 dark:bg-[#0b0d13] border border-gray-200 dark:border-gray-800">
                                        <p class="text-xs italic text-gray-700 dark:text-gray-200 font-medium" x-text="'„' + inspectedHero.motto + '“'"></p>
                                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mt-1">
                                            <span class="text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-[#C5A059]" x-text="inspectedHero.motto_title || 'Helden-Motto'"></span>
                                            <span x-show="inspectedHero.motto_buff" class="px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-[9px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400" x-text="inspectedHero.motto_buff"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 my-5">
                                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-[#0b0d13] border border-amber-500/30 text-left">
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Ausgerüstetes Raumschiff</p>
                                        <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20" x-text="inspectedHero.ship_category_label || 'Gleiter & Jäger'"></span>
                                    </div>
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-2xl" x-text="inspectedHero.ship_icon"></span>
                                        <div class="min-w-0">
                                            <p class="text-xs font-black text-gray-900 dark:text-white truncate" x-text="inspectedHero.ship_name"></p>
                                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold truncate" x-text="inspectedHero.ship_perk"></p>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2.5">
                                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-[#0b0d13] border border-gray-200 dark:border-gray-800 text-center flex flex-col justify-center">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Gesamtfunken</p>
                                        <p class="text-sm sm:text-base font-black text-amber-600 dark:text-[#C5A059] mt-1" x-text="inspectedHero.funken_total + ' ✨'"></p>
                                    </div>
                                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-[#0b0d13] border border-gray-200 dark:border-gray-800 text-center flex flex-col justify-center">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Rekordflug</p>
                                        <p class="text-sm sm:text-base font-black text-indigo-600 dark:text-indigo-400 mt-1" x-text="inspectedHero.highscore + ' m'"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="text-left">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2.5">Errungene Manufaktur-Trophäen</p>
                                <template x-if="inspectedHero.trophies && inspectedHero.trophies.length > 0">
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="(t, idx) in inspectedHero.trophies" :key="idx">
                                            <span class="px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider border flex items-center gap-1.5"
                                                  :class="t.tier === 'Diamant' ? 'bg-cyan-500/15 border-cyan-400/40 text-cyan-600 dark:text-cyan-300' : (t.tier === 'Gold' ? 'bg-amber-500/15 border-amber-400/40 text-amber-600 dark:text-amber-300' : 'bg-slate-500/15 border-slate-400/40 text-slate-600 dark:text-slate-300')">
                                                <span x-text="t.tier === 'Diamant' ? '💎' : (t.tier === 'Gold' ? '🏆' : '🥈')"></span>
                                                <span x-text="t.title_name + ' (' + t.tier + ')'"></span>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!inspectedHero.trophies || inspectedHero.trophies.length === 0">
                                    <p class="text-xs text-gray-400 italic">Noch keine Silber-, Gold- oder Diamant-Trophäen freigeschaltet.</p>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

    </div> {{-- Ende .sf-cockpit-stage-ready --}}

    {{-- SCRIPTS (STRIKT NUR BEI AKTIVIERTEM SPIELMODUS GELADEN) --}}
    @include('livewire.customer.partials.games-component.scripts')

    @endif

    {{-- STYLES --}}
    @include('livewire.customer.partials.games-component.styles')
</div>
