<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 animate-fade-in-up min-h-full flex flex-col relative z-10" x-data="kristallKollaps3D()">
    @if(!$hasOptedIn)
        <div x-data="optInScreen()"
             x-init="initAmbientSparks()"
             :class="isWarping ? 'opacity-0 scale-[0.97] blur-sm' : 'opacity-100 scale-100 blur-0'"
             class="max-w-6xl mx-auto relative p-6 sm:p-10 lg:p-20 flex flex-col lg:flex-row items-center gap-10 lg:gap-16 transition-all duration-1000 ease-out mt-8 lg:mt-12 rounded-[2.5rem] bg-white/70 dark:bg-gray-900/60 backdrop-blur-xl border border-amber-500/20 dark:border-amber-400/15 shadow-[0_25px_70px_rgba(197,160,89,0.12)] dark:shadow-[0_25px_80px_rgba(0,0,0,0.65)] overflow-hidden">

            {{-- Subtile schwebende Goldene Funken im Karten-Hintergrund (Darkmode/Lightmode adaptiv) --}}
            <canvas x-ref="ambientSparksCanvas" class="absolute inset-0 w-full h-full pointer-events-none z-0 opacity-85"></canvas>
            <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-gradient-to-br from-amber-400/15 via-primary/10 to-transparent blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-gradient-to-tr from-amber-500/15 via-yellow-400/5 to-transparent blur-3xl pointer-events-none"></div>

            {{-- Vollbild "Magie aktivieren" Animation (Darkmode-Switch Eclipse + Goldene Funken) --}}
            <template x-teleport="body">
                <div x-show="isActivating"
                     style="display: none;"
                     class="fixed inset-0 z-[9500] pointer-events-none flex items-center justify-center overflow-hidden">

                    {{-- Darkmode-Switch orientierter Eclipse-Iris-Übergang (adaptiv für Light & Dark Mode) --}}
                    <div class="absolute inset-0 transition-[clip-path,opacity] duration-[950ms] ease-[cubic-bezier(0.22,1,0.36,1)] bg-gradient-to-br from-[#fffdf7]/95 via-[#fdf6e4]/95 to-[#f7e7c4]/95 dark:from-[#06080f]/95 dark:via-[#0b0f1c]/95 dark:to-[#17120a]/95 backdrop-blur-2xl"
                         :style="`clip-path: circle(${eclipseRadius}% at ${originX}px ${originY}px); opacity: ${phase === 3 ? 0 : 1};`">
                    </div>

                    {{-- Radiale Gold-Aura im Zentrum --}}
                    <div class="absolute inset-0 transition-opacity duration-700"
                         :class="phase >= 1 && phase < 3 ? 'opacity-100' : 'opacity-0'"
                         style="background: radial-gradient(circle at 50% 50%, rgba(245, 158, 11, 0.22) 0%, rgba(197, 160, 89, 0.08) 38%, transparent 70%);">
                    </div>

                    {{-- Interaktiver High-DPI Canvas für die Goldenen Funken (Wirbel -> Supernova) --}}
                    <canvas x-ref="magicSparksCanvas"
                            class="absolute inset-0 w-full h-full z-10 transition-opacity duration-500"
                            :class="phase === 3 ? 'opacity-0' : 'opacity-100'"></canvas>

                    {{-- Zentrales Manufaktur-Astrolabium & Emblem --}}
                    <div class="relative z-20 flex flex-col items-center justify-center text-center px-6 transition-all duration-700 transform-gpu"
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
                    }, 1050);

                    // Phase 3: Sanfter Seiden-Übergang & Livewire Opt-In
                    setTimeout(() => {
                        this.phase = 3;
                    }, 2250);

                    setTimeout(() => {
                        if (this._magicRaf) cancelAnimationFrame(this._magicRaf);
                        window.sessionStorage.setItem('funki_just_activated', 'true');
                        this.$wire.optIn();
                    }, 2550);
                },

                startGoldenSparksSequence() {
                    const canvas = this.$refs.magicSparksCanvas;
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    const dpr = Math.min(window.devicePixelRatio || 1, 2);
                    const w = window.innerWidth;
                    const h = window.innerHeight;
                    canvas.width = w * dpr;
                    canvas.height = h * dpr;
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                    const centerX = w / 2;
                    const centerY = h / 2;
                    const dark = this.isDark();

                    // Gold-Farbpaletten (abgestimmt auf Darkmode & Lightmode)
                    const palette = dark
                        ? ['#FEF08A', '#FDE047', '#FBBF24', '#F59E0B', '#C5A059', '#FFFBEB']
                        : ['#B45309', '#D97706', '#F59E0B', '#C5A059', '#92400E', '#FBBF24'];

                    const particles = [];
                    const totalSparks = 210;

                    for (let i = 0; i < totalSparks; i++) {
                        const fromButton = i < 90;
                        const angle = Math.random() * Math.PI * 2;
                        const dist = fromButton
                            ? (10 + Math.random() * 90)
                            : (120 + Math.random() * Math.max(w, h) * 0.55);
                        const startX = (fromButton ? this.originX : centerX) + Math.cos(angle) * dist;
                        const startY = (fromButton ? this.originY : centerY) + Math.sin(angle) * dist;

                        const burstAngle = (i / totalSparks) * Math.PI * 2 + (Math.random() - 0.5) * 0.35;
                        const burstSpeed = 3.5 + Math.random() * 14.5;

                        particles.push({
                            x: startX,
                            y: startY,
                            prevX: startX,
                            prevY: startY,
                            orbitAngle: Math.atan2(startY - centerY, startX - centerX),
                            orbitRadius: Math.hypot(startX - centerX, startY - centerY),
                            orbitSpeed: (0.045 + Math.random() * 0.055) * (i % 2 === 0 ? 1 : -1),
                            vx: Math.cos(burstAngle) * burstSpeed,
                            vy: Math.sin(burstAngle) * burstSpeed,
                            size: 1.4 + Math.random() * 3.4,
                            color: palette[i % palette.length],
                            alpha: 0.2 + Math.random() * 0.8,
                            twinkleSpeed: 0.08 + Math.random() * 0.14,
                            twinklePhase: Math.random() * Math.PI * 2,
                            isStar: i % 3 === 0,
                            exploded: false
                        });
                    }

                    const startTime = performance.now();

                    const drawSparkStar = (x, y, r, color, alpha, rot) => {
                        ctx.save();
                        ctx.translate(x, y);
                        ctx.rotate(rot);
                        ctx.globalAlpha = Math.max(0, Math.min(1, alpha));
                        ctx.fillStyle = color;
                        ctx.beginPath();
                        for (let j = 0; j < 4; j++) {
                            ctx.lineTo(Math.cos((j * Math.PI) / 2) * r * 2.6, Math.sin((j * Math.PI) / 2) * r * 2.6);
                            ctx.lineTo(Math.cos(((j + 0.5) * Math.PI) / 2) * r * 0.65, Math.sin(((j + 0.5) * Math.PI) / 2) * r * 0.65);
                        }
                        ctx.closePath();
                        ctx.fill();
                        ctx.restore();
                    };

                    const animate = (now) => {
                        const elapsed = now - startTime;
                        ctx.clearRect(0, 0, w, h);
                        ctx.save();
                        if (dark) {
                            ctx.globalCompositeOperation = 'lighter';
                        }

                        const isSupernova = elapsed >= 1050;

                        for (const p of particles) {
                            p.prevX = p.x;
                            p.prevY = p.y;
                            p.twinklePhase += p.twinkleSpeed;

                            if (!isSupernova) {
                                // Phase 1: Goldener Fibonacci-Wirbel zieht alle Funken ins Zentrum
                                p.orbitAngle += p.orbitSpeed;
                                p.orbitRadius = Math.max(6, p.orbitRadius * 0.945);
                                p.x += (centerX + Math.cos(p.orbitAngle) * p.orbitRadius - p.x) * 0.22;
                                p.y += (centerY + Math.sin(p.orbitAngle) * p.orbitRadius - p.y) * 0.22;
                            } else {
                                // Phase 2: Goldene Supernova-Eruption nach außen + sanfter Auftrieb
                                if (!p.exploded) {
                                    p.exploded = true;
                                    p.x = centerX + (Math.random() - 0.5) * 16;
                                    p.y = centerY + (Math.random() - 0.5) * 16;
                                    p.prevX = p.x;
                                    p.prevY = p.y;
                                }
                                p.x += p.vx;
                                p.y += p.vy;
                                p.vx *= 0.968;
                                p.vy = p.vy * 0.968 - 0.035; // leichter goldener Funken-Auftrieb
                            }

                            const twinkle = 0.55 + 0.45 * Math.sin(p.twinklePhase);
                            const currentAlpha = p.alpha * twinkle;

                            // Goldene Schweif-Spur (Motion Trail)
                            ctx.beginPath();
                            ctx.moveTo(p.prevX, p.prevY);
                            ctx.lineTo(p.x, p.y);
                            ctx.strokeStyle = p.color;
                            ctx.globalAlpha = Math.max(0, Math.min(1, currentAlpha * 0.65));
                            ctx.lineWidth = p.size * 0.85;
                            ctx.lineCap = 'round';
                            ctx.stroke();

                            if (p.isStar) {
                                drawSparkStar(p.x, p.y, p.size, p.color, currentAlpha, p.twinklePhase * 0.5);
                            } else {
                                ctx.globalAlpha = Math.max(0, Math.min(1, currentAlpha));
                                ctx.fillStyle = p.color;
                                ctx.beginPath();
                                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                                ctx.fill();
                            }
                        }

                        ctx.restore();

                        if (elapsed < 2600) {
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

    {{-- SEITEN-HEADER MIT SUB-TABS --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-6">
        <div>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Manufaktur Spiele</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-xs sm:text-sm uppercase tracking-widest font-bold">Verdiene Funken durch Geschick & Taktik</p>
        </div>

        {{-- SPIELE SUB-TABS --}}
        <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-fit shrink-0">
            <a href="{{ route('customer.games') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all bg-primary text-gray-900 shadow-sm">
                <span>🧩</span>
                <span>Spiele</span>
            </a>
            <a href="{{ route('customer.gamification.profile') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/50 dark:hover:bg-gray-800/50">
                <span>✨</span>
                <span>Funki-Profil</span>
            </a>
            <a href="{{ route('customer.ranking') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/50 dark:hover:bg-gray-800/50">
                <span>🏆</span>
                <span>Rangliste</span>
            </a>
        </div>
    </div>

    {{-- SPIELAUSWAHL (Menü) --}}
    <div x-show="!activeGame" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-10" x-transition:enter-end="opacity-100 translate-y-0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 sm:gap-8 pb-32 md:pb-0">

        <div @if($currentEnergy > 0) @click="activeGame = 'kristall'" @endif class="group relative bg-white dark:bg-gray-900 rounded-[2rem] sm:rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 hover:border-emerald-500/50 transition-all duration-500 hover:-translate-y-2 shadow-sm dark:shadow-2xl hover:shadow-lg dark:hover:shadow-[0_20px_40px_rgba(16,185,129,0.15)] flex flex-col {{ $currentEnergy > 0 ? 'cursor-pointer' : '' }} overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl sm:rounded-3xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center mb-6 sm:mb-8 group-hover:scale-110 transition-transform duration-500 shadow-sm dark:shadow-inner">
                <span class="text-4xl sm:text-5xl drop-shadow-[0_0_15px_rgba(16,185,129,0.5)]">💎</span>
            </div>
            <h4 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mb-3 sm:mb-4">Kristall-Kollaps 3D</h4>
            <p class="text-gray-600 dark:text-gray-400 text-sm sm:text-base leading-relaxed mb-6 sm:mb-8 flex-1">
                Kettenreaktionen, Nova-Sphären und Zeit-Kristalle. Wische oder Tausche strategisch klug auf dem 8x8 Feld, um schneller an Rabatte zu kommen!
            </p>
            @if($currentEnergy > 0)
                <button type="button" @click="activeGame = 'kristall'" class="mt-auto w-full py-4 sm:py-5 bg-emerald-50 dark:bg-gray-950 text-emerald-600 dark:text-emerald-500 border border-emerald-200 dark:border-emerald-500/30 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest group-hover:bg-emerald-500 group-hover:text-white dark:group-hover:text-gray-900 transition-all shadow-sm">
                    Öffnen
                </button>
            @else
                <div class="mt-auto w-full flex flex-col items-center">
                    <button type="button" disabled class="w-full py-4 sm:py-5 bg-gray-100 dark:bg-gray-950 text-gray-400 dark:text-gray-600 border border-gray-200 dark:border-gray-800 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest cursor-not-allowed flex justify-center items-center gap-2">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        0 Energie
                    </button>
                    <p class="text-[9px] sm:text-[10px] text-red-500/80 uppercase font-bold tracking-widest mt-2 text-center">
                        Aufladung in ca. {{ is_numeric($timeUntilNextEnergy) ? round($timeUntilNextEnergy) : $timeUntilNextEnergy }} Minuten
                    </p>
                </div>
            @endif
        </div>

        <div @if($currentEnergy > 0) @click="activeGame = 'funkenflug'" @endif class="group relative bg-white dark:bg-gray-900 rounded-[2rem] sm:rounded-[2.5rem] border border-gray-200 dark:border-gray-800 p-6 sm:p-8 hover:border-amber-500/50 transition-all duration-500 hover:-translate-y-2 shadow-sm dark:shadow-2xl hover:shadow-lg dark:hover:shadow-[0_20px_40px_rgba(245,158,11,0.15)] flex flex-col {{ $currentEnergy > 0 ? 'cursor-pointer' : '' }} overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl sm:rounded-3xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center mb-6 sm:mb-8 group-hover:scale-110 transition-transform duration-500 shadow-sm dark:shadow-inner">
                <span class="text-4xl sm:text-5xl drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]">🚀</span>
            </div>
            <h4 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mb-3 sm:mb-4">Funkenflug-Express</h4>
            <p class="text-gray-600 dark:text-gray-400 text-sm sm:text-base leading-relaxed mb-6 sm:mb-8 flex-1">
                Navigiere deine Funki-Rakete durchs All! Weiche Asteroiden aus, sammele Funken und erziele astronomische Distanzrekorde.
            </p>
            @if($currentEnergy > 0)
                <button type="button" @click="activeGame = 'funkenflug'" class="mt-auto w-full py-4 sm:py-5 bg-amber-50 dark:bg-gray-950 text-amber-600 dark:text-amber-500 border border-amber-200 dark:border-amber-500/30 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest group-hover:bg-amber-500 group-hover:text-white dark:group-hover:text-gray-900 transition-all shadow-sm">
                    Öffnen
                </button>
            @else
                <div class="mt-auto w-full flex flex-col items-center">
                    <button type="button" disabled class="w-full py-4 sm:py-5 bg-gray-100 dark:bg-gray-950 text-gray-400 dark:text-gray-600 border border-gray-200 dark:border-gray-800 rounded-xl sm:rounded-2xl font-black text-[10px] sm:text-xs uppercase tracking-widest cursor-not-allowed flex justify-center items-center gap-2">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                        0 Energie
                    </button>
                    <p class="text-[9px] sm:text-[10px] text-red-500/80 uppercase font-bold tracking-widest mt-2 text-center">
                        Aufladung in ca. {{ is_numeric($timeUntilNextEnergy) ? round($timeUntilNextEnergy) : $timeUntilNextEnergy }} Minuten
                    </p>
                </div>
            @endif
        </div>

        <div class="group relative bg-gray-50 dark:bg-gray-900/50 rounded-[2rem] sm:rounded-[2.5rem] border border-gray-200 dark:border-gray-800 border-dashed p-6 sm:p-8 flex flex-col items-center justify-center text-center opacity-70 dark:opacity-50 select-none">
            <span class="text-3xl sm:text-4xl mb-4 grayscale">🔒</span>
            <h4 class="text-lg sm:text-xl font-bold text-gray-500 dark:text-gray-500 mb-2">In Entwicklung</h4>
            <p class="text-gray-400 dark:text-gray-600 text-xs sm:text-sm flex-1">Ein neues Spiel wird in der Manufaktur geschmiedet...</p>
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

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12 flex-1 pb-10 transition-all duration-500" :class="gameState === 'ready' ? 'opacity-20 blur-md pointer-events-none grayscale-[50%]' : 'opacity-100 blur-0'">

                <div class="lg:col-span-4 xl:col-span-4 flex flex-col gap-6 w-full">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2rem] p-6 sm:p-8 shadow-sm dark:shadow-[0_20px_40px_rgba(0,0,0,0.5)] relative overflow-hidden text-center flex flex-col items-center">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="relative z-10 w-full mb-4 sm:mb-6">
                            <p class="text-gray-500 dark:text-gray-400 text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2">Aktuelle Punkte</p>
                            <h4 class="text-5xl sm:text-6xl font-serif font-bold text-emerald-600 dark:text-emerald-400 drop-shadow-none dark:drop-shadow-[0_0_15px_rgba(16,185,129,0.4)]" x-text="score"></h4>
                            <div class="mt-3 inline-flex items-center gap-2 bg-gray-50 dark:bg-gray-950 px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                                <span class="text-sm sm:text-base">✨</span>
                                <span class="text-xs sm:text-sm font-bold text-amber-700 dark:text-primary">= <span x-text="Math.floor(score / 100)"></span> Funken</span>
                            </div>
                        </div>

                        <div class="w-full h-px bg-gray-200 dark:bg-gray-800 relative z-10 my-2"></div>

                        <div class="relative z-10 w-full mt-4 sm:mt-6">
                            <p class="text-gray-500 dark:text-gray-400 text-[10px] sm:text-xs font-black uppercase tracking-widest mb-2">Züge übrig</p>
                            <h4 class="text-5xl sm:text-6xl font-serif font-bold text-gray-900 dark:text-white transition-colors" :class="{'text-red-500 animate-bounce': moves <= 5}" x-text="moves"></h4>
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

            <div x-show="gameState === 'ready'" x-cloak x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 z-50 flex items-center justify-center p-4 sm:p-6 pb-20">

                <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-2xl border border-gray-200 dark:border-gray-700 shadow-2xl dark:shadow-[0_0_100px_rgba(0,0,0,0.8)] rounded-[2rem] sm:rounded-[3rem] w-full max-w-5xl p-6 sm:p-10 lg:p-12 relative flex flex-col lg:flex-row gap-6 lg:gap-12 items-center lg:items-stretch overflow-y-auto max-h-full">

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
                    <div id="ff-main-wrapper" :class="{'h-full w-full max-w-[1200px] aspect-auto rounded-none border-0 shadow-[0_0_100px_rgba(245,158,11,0.1)]': isFullscreen, 'aspect-[3/4] sm:aspect-square w-full max-w-[800px] rounded-[2rem] sm:rounded-[3rem] border-2 border-gray-200 dark:border-gray-700 shadow-xl dark:shadow-[0_20px_50px_rgba(0,0,0,0.6)]': !isFullscreen}" class="overflow-hidden relative bg-gray-950 sm:bg-gray-900 flex flex-col pointer-events-auto transition-all duration-300">
                        
                        {{-- SCREEN AREA (100% on mobile and desktop) --}}
                        <div class="relative w-full h-full shrink-0 flex flex-col bg-gray-950">

                            <div id="funkenflug-container" x-ref="ffContainer" wire:ignore class="absolute inset-0 z-10" :class="{'touch-none': gameState === 'playing'}"></div>
                            <div id="ff-floating-layer" class="absolute inset-0 pointer-events-none z-30 overflow-hidden"></div>

                        {{-- UI SAFE ZONE FOR FULLSCREEN --}}
                        <div class="absolute inset-0 pointer-events-none z-40 w-full h-full flex justify-center">
                            <div class="w-full h-full max-w-[800px] relative">
                                
                                {{-- TOP RIGHT CONTROLS --}}
                                <div class="absolute top-4 right-4 z-50 hidden sm:flex flex-col gap-2 pointer-events-auto">
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

                                {{-- TOP LEFT: GESAMMELTE FUNKEN --}}
                                <div class="absolute top-3 left-3 sm:top-6 sm:left-6 z-40 flex flex-col items-start gap-1.5 pointer-events-auto" style="margin-top: env(safe-area-inset-top, 0px);" x-show="gameState === 'playing'">
                                    <div class="bg-gray-950/90 px-3 py-1.5 rounded-xl border border-gray-800 shadow-inner flex items-center gap-2">
                                        <span class="text-sm">✨</span>
                                        <span class="text-xs sm:text-sm font-bold text-amber-500" x-text="funkenCollected">0</span>
                                    </div>
                                </div>

                                {{-- TOP CENTER: DISTANZ / REKORD & SCHILD-ANZEIGE --}}
                                <div class="absolute top-3 sm:top-6 left-1/2 -translate-x-1/2 z-40 flex flex-col items-center gap-1 pointer-events-auto" style="margin-top: env(safe-area-inset-top, 0px);" x-show="gameState === 'playing'">
                                    <p class="text-[8px] sm:text-[10px] text-gray-300 font-black uppercase tracking-widest bg-gray-950/90 border border-gray-800/80 px-3 py-0.5 rounded-full shadow-md">Distanz / Rekord</p>
                                    <div class="text-xl sm:text-2xl md:text-3xl font-serif font-bold text-white drop-shadow-md flex items-baseline gap-1 leading-none">
                                        <span id="ff-hud-distance" x-text="distance" class="text-amber-400 drop-shadow-[0_0_10px_rgba(245,158,11,0.5)]">0</span>
                                        <span class="text-gray-400 text-sm sm:text-base">/ {{ $personalHighscoreFF }}</span>
                                    </div>
                                    {{-- KOMPAKTE SCHILD-ANZEIGE IM SICHTFELD (MOBIL & DESKTOP) --}}
                                    <div class="mt-1 w-36 sm:w-44 h-4 sm:h-5 rounded-full bg-gray-950/90 border overflow-hidden relative flex items-center shadow-lg transition-all duration-300"
                                         :class="shieldEnergy > 0 ? 'border-cyan-400/80 shadow-[0_0_14px_rgba(34,211,238,0.45)]' : 'border-blue-500/35'">
                                        <div class="absolute left-0 top-0 bottom-0 bg-gradient-to-r from-blue-600 via-cyan-500 to-cyan-300 transition-all duration-300"
                                             :style="`width: ${shieldEnergy}%`"></div>
                                        <div class="relative z-10 w-full flex items-center justify-center gap-1 text-[8px] sm:text-[9px] font-black uppercase tracking-wider text-white drop-shadow">
                                            <span>🛡️ Schild</span>
                                            <span :class="shieldEnergy > 0 ? 'text-cyan-200' : 'text-gray-400'" x-text="shieldEnergy + '%'">0%</span>
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

                                {{-- SKILL BUTTONS (Mobile & Desktop Overlay) --}}
                                <div class="absolute bottom-4 left-4 sm:bottom-6 sm:left-6 z-40 hidden sm:flex flex-col items-center gap-2 origin-bottom-left scale-[0.95] sm:scale-[0.85] opacity-70 hover:opacity-100 transition-opacity pointer-events-auto" x-show="gameState === 'playing'">
                            <!-- W Button -->
                            <div class="flex justify-center w-full">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="relative">
                                        <button @click="useSkill(1)" class="w-12 h-12 sm:w-16 sm:h-16 rounded-full border-2 flex items-center justify-center text-red-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-125 shadow-[0_0_30px_#22c55e] z-50': skillFlash[0], 'bg-red-900/80 border-red-500': !skillFlash[0], 'opacity-50 grayscale pointer-events-none': (skillLevels[0] === 0 || skillCooldowns[0] > 0) && !skillFlash[0]}">
                                            <span class="text-xl sm:text-2xl z-10 drop-shadow-md">🔥</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[0] / 30) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">1</div>
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
                                        <div class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 w-4 h-4 sm:w-5 sm:h-5 bg-red-600 text-white rounded text-[8px] sm:text-[10px] flex items-center justify-center font-bold z-20 shadow">4</div>
                                    </div>
                                    <span class="text-[9px] sm:text-[10px] uppercase font-bold tracking-widest text-yellow-400 drop-shadow-md bg-gray-900/50 px-2 py-0.5 rounded-full">Ultimate</span>
                                </div>
                            </div>
                                </div>

                                {{-- SKILL BUTTONS (Mobile Overlay - Vertical) --}}
                                <div class="absolute bottom-24 z-40 sm:hidden flex flex-col items-center gap-4 transition-all duration-300 pointer-events-auto" :class="leftHandedMode ? 'right-2' : 'left-2'" x-show="gameState === 'playing'">
                                    <!-- Skill 1 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(1)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-red-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[0], 'bg-red-900 border-red-500': !skillFlash[0], 'opacity-40 grayscale pointer-events-none': (skillLevels[0] === 0 || skillCooldowns[0] > 0) && !skillFlash[0]}">
                                            <span class="text-xl z-10 drop-shadow-md">🔥</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[0] / 30) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">1</div>
                                    </div>
                                    <!-- Skill 2 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(2)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-purple-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[1], 'bg-purple-900 border-purple-500': !skillFlash[1], 'opacity-40 grayscale pointer-events-none': (skillLevels[1] === 0 || skillCooldowns[1] > 0) && !skillFlash[1]}">
                                            <span class="text-xl z-10 drop-shadow-md">⚡</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[1] / 15) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">2</div>
                                    </div>
                                    <!-- Skill 3 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(3)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-blue-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[2], 'bg-blue-900 border-blue-500': !skillFlash[2], 'opacity-40 grayscale pointer-events-none': (skillLevels[2] === 0 || skillCooldowns[2] > 0) && !skillFlash[2]}">
                                            <span class="text-xl z-10 drop-shadow-md">🛡️</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[2] / 20) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">3</div>
                                    </div>
                                    <!-- Skill 4 -->
                                    <div class="relative">
                                        <button @pointerdown.stop @touchstart.stop @click="useSkill(4)" class="w-12 h-12 rounded-full border-2 flex items-center justify-center text-yellow-100 font-black overflow-hidden active:scale-95 transition-all duration-150" :class="{'bg-green-500/90 border-green-400 scale-110 shadow-[0_0_15px_#22c55e] z-50': skillFlash[3], 'bg-yellow-900 border-yellow-500': !skillFlash[3], 'opacity-40 grayscale pointer-events-none': (skillLevels[3] === 0 || skillCooldowns[3] > 0) && !skillFlash[3]}">
                                            <span class="text-xl z-10 drop-shadow-md">⭐</span>
                                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 transition-all duration-100" :style="`height: ${(skillCooldowns[3] / 60) * 100}%`"></div>
                                        </button>
                                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white rounded text-[9px] flex items-center justify-center font-bold z-20 shadow">4</div>
                                    </div>

                                    {{-- MOBILE CONTROLS (UNDER SKILLS) --}}
                                    <div class="flex flex-col items-center gap-3 mt-1 pt-3 border-t border-gray-700 w-full">
                                        <button type="button" @click="togglePause()" class="w-10 h-10 rounded-full bg-gray-900/80 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-amber-400 shadow-md">
                                            <svg x-show="!isPaused" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <svg x-show="isPaused" style="display: none;" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                        <button type="button" @click="toggleFullscreen()" class="w-10 h-10 rounded-full bg-gray-900/80 border border-gray-700 flex items-center justify-center text-gray-400 hover:text-amber-400 shadow-md">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                            </svg>
                                        </button>
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

            <div x-show="gameState === 'ready'" x-cloak x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute inset-0 z-50 flex items-center justify-center p-4 sm:p-6">

                <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-2xl border border-gray-200 dark:border-gray-700 shadow-2xl dark:shadow-[0_0_100px_rgba(0,0,0,0.8)] rounded-[2rem] sm:rounded-[3rem] w-full max-w-5xl p-6 sm:p-10 lg:p-12 relative flex flex-col gap-6 lg:gap-12 items-center overflow-y-auto max-h-full scrollbar-hide">

                    <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-[80px] pointer-events-none"></div>

                    <div class="flex-1 flex flex-col justify-center items-center w-full relative z-10 max-w-lg mx-auto" x-show="!showMissionBriefing" x-transition>

                        <div class="flex gap-4 sm:gap-6 mb-8 w-full justify-center">
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-center items-center flex-1 shadow-sm dark:shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-3">Lautstärke</p>
                                <div class="flex items-center gap-3 w-full max-w-[150px]">
                                    <button type="button" @click="toggleMute()" class="text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 transition-colors focus:outline-none shrink-0">
                                        <svg x-show="isBgmPlaying" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5 12h4l4-4v16l-4-4H5a2 2 0 01-2-2v-4a2 2 0 012-2z" /></svg>
                                        <svg x-show="!isBgmPlaying" style="display: none;" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" clip-rule="evenodd" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" /></svg>
                                    </button>
                                    <input type="range" name="bgmVolumeStart" aria-label="Lautstärke" min="0" max="100" step="1" x-model="bgmVolumeUi" class="volume-slider w-full">
                                </div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-6 text-center flex-1 shadow-sm dark:shadow-inner">
                                <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-2">Letzte Funken</p>
                                <p class="text-3xl sm:text-4xl font-serif font-bold text-amber-600 dark:text-amber-500 drop-shadow-none dark:drop-shadow-[0_0_10px_rgba(245,158,11,0.3)]" x-text="funkenCollected">0</p>
                            </div>
                        </div>

                        <div class="relative w-full mt-2 flex flex-col gap-3">
                            <button type="button" @click="attemptStartGame()" tabindex="0"
                                    class="w-full py-5 sm:py-6 rounded-2xl font-black text-lg sm:text-xl uppercase tracking-[0.2em] transition-all"
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
                            
                            <button type="button" @click="showMissionBriefing = true" class="w-full py-3 sm:py-4 rounded-xl font-bold text-xs sm:text-sm uppercase tracking-widest transition-all bg-gray-100 dark:bg-gray-800 text-amber-700 dark:text-amber-400 hover:bg-gray-200 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 shadow-sm">
                                Mission Briefing ansehen
                            </button>

                            <div class="flex justify-between items-center mt-3 px-2">
                                <p class="text-amber-600 dark:text-amber-500 font-black text-[10px] sm:text-xs uppercase tracking-[0.3em] opacity-80">- Kostet 1 Energie -</p>
                                <button type="button" @click="activeGame = null" class="text-gray-500 hover:text-gray-900 dark:hover:text-white font-black text-[10px] sm:text-xs uppercase tracking-widest transition-colors">Abbrechen</button>
                            </div>
                        </div>
                    </div>

                    {{-- INLINE MISSION BRIEFING --}}
                    <div class="w-full relative z-10" x-show="showMissionBriefing" x-cloak x-transition>
                        <div class="flex flex-col sm:flex-row items-center justify-between border-b border-amber-500/20 pb-4 gap-4 mb-6">
                            <strong class="text-amber-700 dark:text-amber-400 uppercase tracking-[0.2em] font-black text-sm sm:text-base">Mission Briefing</strong>
                            <button type="button" @click="showMissionBriefing = false" class="text-gray-700 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors bg-gray-100 dark:bg-gray-800 px-4 py-2 rounded-xl text-xs uppercase font-bold tracking-widest border border-gray-200 dark:border-gray-700 shadow-sm">
                                Zurück
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6 text-gray-700 dark:text-amber-300 text-xs sm:text-sm [&_strong]:text-gray-900 dark:[&_strong]:text-white">
                            <ul class="space-y-4">
                                <li class="flex gap-3 items-start text-xs sm:text-sm">
                                    <span><strong>Steuerung:</strong> Maus/Touch Bewegung. Klick=Schießen.</span>
                                </li>
                                <li class="flex gap-3 items-start text-xs sm:text-sm">
                                    <div class="w-4 h-4 shrink-0 bg-red-500 mt-0.5 shadow-md border border-gray-600 rounded-sm"></div>
                                    <span><strong>Normal:</strong> Rote Meteore (1 HP)</span>
                                </li>
                                <li class="flex gap-3 items-start text-xs sm:text-sm">
                                    <div class="w-4 h-4 shrink-0 bg-purple-500 mt-0.5 shadow-md border border-gray-600 rounded-sm"></div>
                                    <span><strong>Panzer:</strong> Lila Elite-Scherben (Viel HP)</span>
                                </li>
                                <li class="flex gap-3 items-start text-xs sm:text-sm">
                                    <div class="w-4 h-4 rounded-full shrink-0 bg-yellow-400 mt-0.5 shadow-md border border-yellow-200"></div>
                                    <span class="text-yellow-600 dark:text-yellow-500"><strong>Gelb:</strong> Sammeln für Extrapunkte & Distanz</span>
                                </li>
                                <li class="flex gap-3 items-start text-xs sm:text-sm">
                                    <div class="w-4 h-4 rounded-full shrink-0 bg-blue-400 mt-0.5 shadow-md border border-blue-200"></div>
                                    <span class="text-blue-600 dark:text-blue-400"><strong>Blau:</strong> Reduziert Skill-Cooldowns um 5s</span>
                                </li>
                            </ul>
                            <ul class="space-y-4 border-t border-gray-200 dark:border-amber-500/10 pt-4 md:pt-0 md:border-t-0 md:border-l md:pl-8">
                                <li class="flex gap-3 items-start">
                                    <span class="text-xl">🔥</span>
                                    <div><strong class="text-gray-900 dark:text-white">[W] Multishoot:</strong> Rakete schießt in einer Fassrolle 3x schneller für massiven Schaden.</div>
                                </li>
                                <li class="flex gap-3 items-start">
                                    <span class="text-xl">⚡</span>
                                    <div><strong class="text-gray-900 dark:text-white">[A] Teleport:</strong> Zeit verlangsamt sich. Klicke/Tippe auf den Bildschirm, um sofort dorthin auszuweichen.</div>
                                </li>
                                <li class="flex gap-3 items-start">
                                    <span class="text-xl">🛡️</span>
                                    <div><strong class="text-gray-900 dark:text-white">[S] Schild:</strong> Aktiviert ein Schutzfeld für 20s oder bis es durch Treffer zerstört wird.</div>
                                </li>
                                <li class="flex gap-3 items-start">
                                    <span class="text-xl">⭐</span>
                                    <div><strong class="text-gray-900 dark:text-white">[D] Ultimate (Auto-Aim):</strong> Beschwört 10s lang Drohnen, die selbstständig zielen und vernichten.</div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    @endif


    {{-- STYLES --}}
    @include('livewire.customer.partials.games-component.styles')

    {{-- SCRIPTS --}}
    @include('livewire.customer.partials.games-component.scripts')
</div>
