<div class="min-h-[100dvh] bg-gray-950 font-sans text-gray-300 antialiased relative overflow-hidden flex items-center justify-center" x-data="universeLayout()" x-init="init()">

    {{-- HINTERGRUND UNIVERSUM (wire:ignore verhindert, dass Livewire das Canvas beim Neuladen stört) --}}
    <canvas id="global-universe-canvas" class="fixed inset-0 z-0 pointer-events-none w-full h-full" wire:ignore></canvas>

    {{-- LOGIN CONTAINER (Schwebt im Raum) --}}
    <div class="relative z-10 w-full max-w-md px-4 sm:px-0 animate-fade-in-up">

        {{-- Card: Dark Glassmorphism, abgerundet, mit edlem Glow --}}
        <div class="bg-gray-900/80 backdrop-blur-xl py-10 px-6 sm:px-10 rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.8)] border border-gray-800 relative overflow-hidden">

            {{-- Feiner Glanz-Effekt im Hintergrund der Karte --}}
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary/10 rounded-full blur-[50px] pointer-events-none"></div>

            {{-- Status / Errors --}}
            @if (session('status'))
                <div class="mb-8 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-5 py-4 rounded-xl text-[10px] font-black uppercase tracking-widest flex items-center shadow-inner drop-shadow-[0_0_8px_currentColor]">
                    <svg class="w-5 h-5 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ session('status') }}
                </div>
            @endif

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

            {{-- ========================================== --}}
            {{-- VIEW: LOGIN --}}
            {{-- ========================================== --}}
            @if ($activeView === 'login')
                <div class="text-center mb-10 relative z-10">
                    {{-- LOGO --}}
                    <a href="/" class="inline-block mb-8 hover:scale-105 transition-transform duration-500 drop-shadow-[0_0_15px_rgba(197,160,89,0.3)]">
                        <img class="h-28 mx-auto object-contain" src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="mein-seelenfunke">
                    </a>

                    <h1 class="text-3xl font-serif font-bold text-white tracking-wide">
                        Willkommen zurück
                    </h1>
                    <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-500">
                        Melde dich an, um fortzufahren.
                    </p>
                </div>

                <form wire:submit.prevent="login" novalidate class="space-y-6 relative z-10">

                    {{-- E-Mail --}}
                    <div>
                        <label for="email" class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">E-Mail-Adresse</label>
                        <div class="relative">
                            <input
                                id="email"
                                type="email"
                                wire:model.defer="email"
                                autocomplete="email"
                                required
                                class="block w-full bg-gray-950 border border-gray-800 rounded-xl py-3.5 px-4 text-white text-sm focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-600 @error('email') border-red-500/50 focus:border-red-500 focus:ring-red-500/30 @enderror"
                                placeholder="name@beispiel.de"
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            >
                        </div>
                    </div>

                    {{-- Passwort --}}
                    <div>
                        <label for="password" class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Passwort</label>
                        <div class="relative" x-data="{ show: false }">
                            <input
                                :type="show ? 'text' : 'password'"
                                id="password"
                                wire:model.defer="password"
                                autocomplete="current-password"
                                required
                                class="block w-full bg-gray-950 border border-gray-800 rounded-xl py-3.5 px-4 pr-12 text-white text-sm focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-600 @error('password') border-red-500/50 focus:border-red-500 focus:ring-red-500/30 @enderror"
                                placeholder="••••••••"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            >
                            <button type="button" x-on:click="show = !show"
                                    class="absolute inset-y-0 right-0 pr-4 flex items-center cursor-pointer text-gray-500 hover:text-primary transition-colors"
                                    :aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'">
                                <span x-show="!show">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </span>
                                <span x-show="show" style="display: none;">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    {{-- Remember + Passwort vergessen --}}
                    <div class="flex items-center justify-between pt-2">
                        <label class="flex items-center cursor-pointer group">
                            <div class="relative flex items-center h-5">
                                <input id="remember" type="checkbox" wire:model.defer="remember" class="peer sr-only">
                                <div class="w-5 h-5 bg-gray-950 border border-gray-700 rounded transition-all peer-checked:bg-primary peer-checked:border-primary shadow-inner"></div>
                                <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="ml-3 text-xs font-bold text-gray-400 group-hover:text-white transition-colors">Angemeldet bleiben</span>
                        </label>

                        <button type="button" wire:click="setPasswordResetView" class="text-xs font-bold text-gray-400 hover:text-primary transition-colors">
                            Passwort vergessen?
                        </button>
                    </div>

                    {{-- Submit --}}
                    <div class="pt-4">
                        <button type="submit" wire:loading.attr="disabled"
                                class="w-full flex justify-center items-center gap-3 py-3.5 px-4 border border-primary/50 rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.2)] text-[10px] uppercase tracking-widest font-black text-gray-900 bg-primary hover:bg-primary-dark hover:text-white hover:scale-[1.02] focus:outline-none transition-all duration-300 disabled:opacity-70 disabled:cursor-wait">
                            <svg wire:loading class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span>Anmelden</span>
                        </button>
                    </div>
                </form>

                <div class="mt-8 relative z-10">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-800"></div>
                        </div>
                        <div class="relative flex justify-center text-[9px] font-black uppercase tracking-widest">
                            <span class="px-4 bg-gray-900 text-gray-600">Oder weiter mit</span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('auth.google', ['guard' => 'customer']) }}"
                           class="w-full flex justify-center items-center py-3.5 px-4 border border-gray-800 rounded-xl shadow-inner bg-gray-950 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white hover:border-gray-600 transition-all duration-300 group">
                            <svg class="w-5 h-5 mr-3 group-hover:scale-110 transition-transform duration-300" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                            </svg>
                            <span>Google Login</span>
                        </a>
                    </div>

                    {{-- REGISTRIERUNG (KORRIGIERTER PFAD) --}}
                    <div class="mt-8 text-center bg-gray-950/50 rounded-xl p-4 border border-gray-800/50">
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1.5">Noch kein Teil unserer Reise?</p>
                        <a href="{{ route('livewire.global.auth.register') }}" class="text-xs font-bold text-primary hover:text-white transition-colors block">
                            Entfache deinen Seelenfunken ✨
                        </a>
                    </div>
                </div>
            @endif

            {{-- ========================================== --}}
            {{-- VIEW: TWO FACTOR AUTH --}}
            {{-- ========================================== --}}
            @if ($activeView === 'twoFactor')
                <div class="relative z-10">
                    <form wire:submit.prevent="twoFactorVerify" novalidate class="space-y-8">
                        <div class="text-center mb-8">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gray-950 border border-gray-800 shadow-inner mb-6">
                                <svg class="h-8 w-8 text-primary drop-shadow-[0_0_8px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </div>
                            <h2 class="text-2xl font-serif font-bold text-white tracking-wide">Authentifizierung</h2>
                            <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-500">
                                Code aus der Authenticator App eingeben.
                            </p>
                        </div>

                        <div>
                            <div class="mt-2">
                                <input
                                    id="code"
                                    type="text"
                                    wire:model.defer="code"
                                    required
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    class="block w-full text-center text-3xl tracking-[0.5em] font-mono text-white bg-gray-950 rounded-xl border border-gray-800 py-4 shadow-inner focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition-all @error('code') border-red-500/50 @enderror"
                                    placeholder="••••••"
                                >
                            </div>
                            @error('code')
                            <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-center text-red-400">{{ $message }}</p>
                            @enderror
                            @if (session()->has('error'))
                                <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-center text-red-400">{{ session('error') }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col gap-4">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="w-full flex justify-center items-center py-4 px-4 border border-primary/50 rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.2)] text-[10px] uppercase tracking-widest font-black text-gray-900 bg-primary hover:bg-primary-dark hover:text-white hover:scale-[1.02] focus:outline-none transition-all duration-300 disabled:opacity-70 disabled:cursor-wait">
                                <span wire:loading.remove>Bestätigen</span>
                                <span wire:loading>Prüfe Code...</span>
                            </button>

                            <button type="button" wire:click="setLoginView" class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition-colors text-center mt-2 p-2">
                                Zurück zur Anmeldung
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- ========================================== --}}
            {{-- VIEW: PASSWORT RESET --}}
            {{-- ========================================== --}}
            @if ($activeView === 'passwordReset')
                <div class="relative z-10">
                    <form wire:submit.prevent="sendLink" novalidate class="space-y-8">
                        <div class="text-center mb-8">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gray-950 border border-gray-800 shadow-inner mb-6">
                                <svg class="h-8 w-8 text-blue-400 drop-shadow-[0_0_8px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            </div>
                            <h2 class="text-2xl font-serif font-bold text-white tracking-wide">Passwort vergessen?</h2>
                            <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-gray-500 leading-relaxed px-4">
                                Gib deine E-Mail-Adresse ein und wir senden dir einen Link zum Zurücksetzen.
                            </p>
                        </div>

                        <div>
                            <label for="email_reset" class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">E-Mail-Adresse</label>
                            <div class="mt-1">
                                <input
                                    id="email_reset"
                                    type="email"
                                    wire:model.defer="email"
                                    autocomplete="email"
                                    required
                                    class="block w-full bg-gray-950 border border-gray-800 rounded-xl py-3.5 px-4 text-white text-sm focus:bg-black focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 shadow-inner outline-none transition-all placeholder-gray-600 @error('email') border-red-500/50 @enderror"
                                    placeholder="name@beispiel.de"
                                >
                            </div>
                            @error('email')
                            <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-red-400 ml-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-4 pt-2">
                            <button type="submit" wire:loading.attr="disabled"
                                    class="w-full flex justify-center items-center py-4 px-4 border border-blue-500/30 rounded-xl shadow-[0_0_20px_rgba(59,130,246,0.15)] text-[10px] uppercase tracking-widest font-black text-blue-400 bg-blue-500/10 hover:bg-blue-500/20 hover:text-white hover:scale-[1.02] focus:outline-none transition-all duration-300 disabled:opacity-70 disabled:cursor-wait">
                                <span wire:loading.remove>Link anfordern</span>
                                <span wire:loading>Sende E-Mail...</span>
                            </button>

                            <button type="button" wire:click="setLoginView"
                                    class="w-full flex justify-center py-4 px-4 border border-gray-800 rounded-xl shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-950 hover:bg-black hover:text-white transition-all">
                                Zurück zum Login
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>

        {{-- Footer --}}
        <div class="mt-8 text-center text-[9px] font-black uppercase tracking-widest text-gray-600 z-10">
            &copy; {{ date('Y') }} mein-seelenfunke. Alle Rechte vorbehalten.
        </div>

    </div>

    {{-- ========================================================= --}}
    {{-- CSS & JS FÜR UNIVERSUM UND ANIMATIONEN                    --}}
    {{-- ========================================================= --}}
    <style>
        .animate-fade-in-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('universeLayout', () => ({
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
            }));
        });
    </script>
</div>
