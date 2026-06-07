<div>
    <style>
        .animate-fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <script>
        window.universeLayout = function() {
            return {
                init() {
                    const canvas = document.getElementById('global-universe-canvas');
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');

                    let width, height;
                    let stars = [];
                    let planets = [];
                    let meteors = [];
                    let dust = [];

                    const config = {
                        starsCount: 200,
                        planetsCount: 2,
                        dustCount: 30,
                        meteorsCount: 8,
                        colors: {
                            gold: 'rgba(197, 160, 89,',
                            white: 'rgba(255, 255, 255,',
                            blue: 'rgba(100, 150, 255,',
                            copper: 'rgba(217, 119, 83,'
                        }
                    };

                    const resize = () => {
                        width = canvas.width = window.innerWidth;
                        height = canvas.height = window.innerHeight;
                    };
                    window.addEventListener('resize', resize);
                    resize();

                    const random = (min, max) => Math.random() * (max - min) + min;

                    // Sterne
                    for (let i = 0; i < config.starsCount; i++) {
                        stars.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(0.2, 1.0),
                            baseAlpha: random(0.1, 0.4),
                            angle: random(0, Math.PI * 2),
                            speed: random(0.002, 0.015),
                            color: Math.random() > 0.8 ? config.colors.gold : config.colors.white
                        });
                    }

                    // Monde / Nebel
                    for (let i = 0; i < config.planetsCount; i++) {
                        planets.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(200, 500),
                            vx: random(-0.01, 0.01),
                            vy: random(-0.01, 0.01),
                            color: [config.colors.gold, config.colors.blue][Math.floor(Math.random() * 2)],
                            maxAlpha: random(0.02, 0.05)
                        });
                    }

                    // Dust
                    for (let i = 0; i < config.dustCount; i++) {
                        dust.push({
                            x: random(0, width), y: random(0, height), r: random(0.5, 2),
                            vx: random(-0.03, 0.03), angle: random(0, Math.PI * 2),
                            floatSpeed: random(0.002, 0.01), floatRange: random(10, 40),
                            baseY: random(0, height), alpha: random(0.05, 0.2)
                        });
                    }

                    const spawnMeteor = (isInitial = false) => {
                        const edge = Math.floor(random(0, 4));
                        let x, y, vx, vy;
                        const speed = random(0.05, 0.2);
                        const angle = random(0, Math.PI * 2);

                        if (isInitial) {
                            x = random(0, width); y = random(0, height);
                        } else {
                            if (edge === 0) { x = -50; y = random(0, height); }
                            if (edge === 1) { x = width + 50; y = random(0, height); }
                            if (edge === 2) { x = random(0, width); y = -50; }
                            if (edge === 3) { x = random(0, width); y = height + 50; }
                        }

                        vx = Math.cos(angle) * speed;
                        vy = Math.sin(angle) * speed;

                        meteors.push({
                            x: x, y: y, vx: vx, vy: vy,
                            size: random(0.5, 1.5), alpha: random(0.1, 0.4), length: random(20, 80),
                            color: Math.random() > 0.5 ? config.colors.gold : config.colors.white
                        });
                    };

                    for (let i = 0; i < config.meteorsCount; i++) { spawnMeteor(true); }

                    const loop = () => {
                        ctx.clearRect(0, 0, width, height);

                        planets.forEach(p => {
                            p.x += p.vx; p.y += p.vy;
                            if (p.x - p.r > width) p.x = -p.r; if (p.x + p.r < 0) p.x = width + p.r;
                            if (p.y - p.r > height) p.y = -p.r; if (p.y + p.r < 0) p.y = height + p.r;

                            let grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
                            grad.addColorStop(0, `${p.color}${p.maxAlpha})`);
                            grad.addColorStop(1, 'rgba(0,0,0,0)');
                            ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2); ctx.fillStyle = grad; ctx.fill();
                        });

                        stars.forEach(s => {
                            s.angle += s.speed;
                            let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.2;
                            if (currentAlpha < 0) currentAlpha = 0;
                            ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2); ctx.fillStyle = `${s.color}${currentAlpha})`; ctx.fill();
                        });

                        dust.forEach(d => {
                            d.x += d.vx; d.angle += d.floatSpeed; d.y = d.baseY + Math.sin(d.angle) * d.floatRange;
                            if (d.x > width + 10) d.x = -10; if (d.x < -10) d.x = width + 10;
                            ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2); ctx.fillStyle = `${config.colors.gold}${d.alpha})`; ctx.fill();
                        });

                        for (let i = meteors.length - 1; i >= 0; i--) {
                            let m = meteors[i];
                            m.x += m.vx; m.y += m.vy;
                            if (m.x > width + 100 || m.x < -100 || m.y > height + 100 || m.y < -100) { meteors.splice(i, 1); spawnMeteor(); continue; }

                            ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                            let grad = ctx.createLinearGradient(m.x, m.y, m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                            grad.addColorStop(0, `${m.color}${m.alpha})`); grad.addColorStop(1, 'rgba(0,0,0,0)');
                            ctx.strokeStyle = grad; ctx.lineWidth = m.size; ctx.lineCap = 'round'; ctx.stroke();

                            ctx.beginPath(); ctx.arc(m.x, m.y, m.size, 0, Math.PI * 2); ctx.fillStyle = `${m.color}${m.alpha + 0.2})`; ctx.fill();
                        }
                        requestAnimationFrame(loop);
                    };
                    loop();
                }
            };
        };

        if (window.Alpine) {
            Alpine.data('universeLayout', window.universeLayout);
        } else {
            document.addEventListener('alpine:init', () => Alpine.data('universeLayout', window.universeLayout));
        }
    </script>

    <div class="min-h-[100dvh] bg-gray-950 font-sans text-gray-300 antialiased relative overflow-hidden flex items-center justify-center" x-data="universeLayout()" x-init="init()">

        {{-- HINTERGRUND UNIVERSUM --}}
        <canvas id="global-universe-canvas" class="fixed inset-0 z-0 pointer-events-none w-full h-full" wire:ignore></canvas>

        {{-- LOGIN CONTAINER (Schwebt im Raum) --}}
        <div class="relative z-10 w-full max-w-md px-4 sm:px-0 animate-fade-in-up">

            {{-- Card: Dark Glassmorphism, abgerundet, mit edlem Glow --}}
            <div class="bg-gray-900/80 backdrop-blur-xl py-10 px-6 sm:px-10 rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.8)] border border-gray-800 relative overflow-hidden">
            
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary/10 rounded-full blur-[50px] pointer-events-none"></div>

                {{-- Error Messages --}}
                @if ($errors->any())
                    <div class="mb-8 bg-red-500/10 border border-red-500/30 text-red-400 px-5 py-4 rounded-xl text-sm shadow-inner">
                        <div class="flex items-center mb-3 text-[10px] font-black uppercase tracking-widest drop-shadow-[0_0_8px_currentColor]">
                            <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Bitte überprüfen:
                        </div>
                        <ul class="list-disc pl-9 space-y-1.5 text-xs font-medium text-red-300">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="relative z-10 text-center mb-10">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gray-950 border border-gray-800 shadow-inner mb-6">
                        <svg class="h-8 w-8 text-primary drop-shadow-[0_0_8px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                    </div>
                    <h1 class="text-2xl font-serif font-bold text-white tracking-wide">Passwort festlegen</h1>
                    <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-500 px-4 leading-relaxed">
                        Da du als Gast eingekauft hast, legen wir Wert auf die Sicherheit deiner digitalen Downloads. Bitte erstelle ein sicheres Passwort, um dein Kundenkonto freizuschalten.
                    </p>
                </div>

                <form wire:submit.prevent="submit" novalidate class="space-y-6 relative z-10">
                
                    {{-- Neues Passwort --}}
                    <div>
                        <label for="password" class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Dein Passwort</label>
                        <div class="relative" x-data="{ show: false }">
                            <input
                                :type="show ? 'text' : 'password'"
                                id="password"
                                wire:model.defer="password"
                                required
                                class="block w-full bg-gray-950 border border-gray-800 rounded-xl py-3.5 px-4 pr-12 text-white text-sm focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-600 @error('password') border-red-500/50 @enderror"
                                placeholder="••••••••"
                            >
                            <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-gray-500 hover:text-primary transition-colors">
                                <span x-show="!show"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></span>
                                <span x-show="show" style="display: none;"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg></span>
                            </button>
                        </div>
                    </div>

                    {{-- Bestätigung --}}
                    <div>
                        <label for="passwordConfirm" class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Passwort bestätigen</label>
                        <div class="relative" x-data="{ show: false }">
                            <input
                                :type="show ? 'text' : 'password'"
                                id="passwordConfirm"
                                wire:model.defer="passwordConfirm"
                                required
                                class="block w-full bg-gray-950 border border-gray-800 rounded-xl py-3.5 px-4 pr-12 text-white text-sm focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-600 @error('passwordConfirm') border-red-500/50 @enderror"
                                placeholder="••••••••"
                            >
                            <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-gray-500 hover:text-primary transition-colors">
                                <span x-show="!show"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></span>
                                <span x-show="show" style="display: none;"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg></span>
                            </button>
                        </div>
                    </div>

                    {{-- Button --}}
                    <div class="pt-4">
                        <button type="submit" wire:loading.attr="disabled"
                                class="w-full flex justify-center items-center gap-3 py-3.5 px-4 border border-primary/50 rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.2)] text-[10px] uppercase tracking-widest font-black text-gray-900 bg-primary hover:bg-primary-dark hover:text-white hover:scale-[1.02] focus:outline-none transition-all duration-300 disabled:opacity-70 disabled:cursor-wait">
                            <svg wire:loading class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span>Passwort speichern & fortfahren</span>
                        </button>
                    </div>
                    
                    {{-- Abmelden --}}
                    <div class="mt-6 text-center">
                        <a href="#" wire:click.prevent="logout" class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-primary transition-colors flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            Konto verlassen (Abmelden)
                        </a>
                    </div>

                </form>
            </div>

            {{-- Footer --}}
            <div class="mt-8 text-center text-[9px] font-black uppercase tracking-widest text-gray-600 z-10">
                &copy; {{ date('Y') }} mein-seelenfunke. Alle Rechte vorbehalten.
            </div>

        </div>

    </div>
</div>
