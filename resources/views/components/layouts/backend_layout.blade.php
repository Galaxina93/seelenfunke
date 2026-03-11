<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>

    @livewireStyles

</head>
<body class="min-h-screen bg-gray-950 font-sans text-gray-300 antialiased relative overflow-x-hidden" x-data="universeLayout()" x-init="init()">

{{-- HINTERGRUND UNIVERSUM --}}
<canvas id="global-universe-canvas" class="fixed inset-0 z-0 pointer-events-none w-full h-full" wire:ignore></canvas>

@php
    $adminId = '';
    $hasUnreadTickets = false;

    if(auth()->guard('admin')->check()) {
        $adminId = (string)auth()->guard('admin')->id();

        if(class_exists(\App\Models\TicketMessage::class)) {
            // Prüfen ob es ungelesene Nachrichten von Kunden gibt
            $hasUnreadTickets = \App\Models\TicketMessage::where('sender_type', 'customer')
                ->where('is_read_by_admin', false)
                ->exists();
        }
    }
@endphp

{{-- GLOBALER ALPINE STATE FÜR NAVIGATION UND WEBSOCKETS (Als eigener Wrapper) --}}
<div x-data="{
        hasUnreadSupport: {{ $hasUnreadTickets ? 'true' : 'false' }},
        showToast: false,
        toastMessage: '',
        initAdminEcho() {
                    @if($adminId)
                    let attempts = 0;
                    const checkEcho = setInterval(() => {
                        attempts++;
                        if (typeof window.Echo !== 'undefined') {
                            clearInterval(checkEcho);

                            // Lauschen auf dem Admin-Channel
                            window.Echo.private('admin.{{$adminId}}').listen('.TicketMessageSent', (e) => {
                                if(e.message && e.message.sender_type === 'customer'){
                                    this.hasUnreadSupport = true;
                                    window.dispatchEvent(new CustomEvent('admin-ticket-badge-update')); // NEU: Event feuern

                                    if(!window.location.pathname.includes('/tickets')){
                                        let tNumber = e.message.ticket ? e.message.ticket.ticket_number : '';
                                        let cName = (e.message.ticket && e.message.ticket.customer) ? e.message.ticket.customer.first_name : 'Kunde';
                                        this.toastMessage = 'Neue Ticket Nachricht zum Ticket ' + tNumber + ' von ' + cName;
                                        this.showToast = true;
                                        setTimeout(() => { this.showToast = false; }, 5000);
                                    }
                                }
                            });

                            // Fallback-Channel:
                            window.Echo.private('admin.tickets').listen('.TicketMessageSent', (e) => {
                                if(e.message && e.message.sender_type === 'customer'){
                                    this.hasUnreadSupport = true;
                                    window.dispatchEvent(new CustomEvent('admin-ticket-badge-update')); // NEU: Event feuern

                                    if(!window.location.pathname.includes('/tickets')){
                                        let tNumber = e.message.ticket ? e.message.ticket.ticket_number : '';
                                        let cName = (e.message.ticket && e.message.ticket.customer) ? e.message.ticket.customer.first_name : 'Kunde';
                                        this.toastMessage = 'Neue Ticket Nachricht zum Ticket ' + tNumber + ' von ' + cName;
                                        this.showToast = true;
                                        setTimeout(() => { this.showToast = false; }, 5000);
                                    }
                                }
                            });
                        } else if (attempts > 30) {
                            clearInterval(checkEcho);
                        }
                    }, 300);
                    @endif
                }
    }"
     x-init="initAdminEcho()"
     @clear-admin-ticket-badge.window="hasUnreadSupport = false"
     class="relative w-full min-h-screen flex flex-col">

    @if(auth()->guard('admin')->check())
        <div x-cloak x-show="showToast" style="display: none;" class="fixed bottom-6 right-6 z-[100]"
             x-transition:enter="transition-all duration-500 ease-out"
             x-transition:enter-start="translate-y-10 opacity-0 pointer-events-none"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition-all duration-500 ease-out"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-10 opacity-0 pointer-events-none">
            <div class="bg-gray-900/95 backdrop-blur-md border border-gray-700 shadow-2xl rounded-2xl p-4 flex items-center gap-4 max-w-sm cursor-pointer" @click="window.location.href='/admin/tickets'">
                <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center shrink-0 border border-primary/30">
                    <span class="text-primary text-xl">💌</span>
                </div>
                <div>
                    <h4 class="text-white text-sm font-bold tracking-wide">Support Desk</h4>
                    <p class="text-gray-400 text-xs mt-0.5" x-text="toastMessage"></p>
                </div>
            </div>
        </div>
    @endif

    <div x-data="{ open: false }" class="relative z-10 flex-1 flex flex-col">
        @if($guard !== 'customer')
            <div x-show="open" class="relative z-50 lg:hidden" role="dialog" aria-modal="true" style="display: none;">
                <div x-show="open" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="open = false" aria-hidden="true"></div>

                <div class="fixed inset-0 flex">
                    <div x-show="open" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="relative mr-16 flex w-full max-w-xs flex-1">
                        <div x-show="open" x-transition:enter="ease-in-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in-out duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute left-full top-0 flex w-16 justify-center pt-5">
                            <button type="button" class="-m-2.5 p-2.5 transition-transform hover:rotate-90 duration-300" @click="open = false">
                                <span class="sr-only">Menü schließen</span>
                                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-gray-900/95 backdrop-blur-xl px-6 pb-4 border-r border-gray-800 shadow-[20px_0_50px_rgba(0,0,0,0.5)]">

                            {{-- MOBILE LOGO MIT LINK --}}
                            <div class="flex h-24 shrink-0 items-center justify-center border-b border-gray-800 mb-2">
                                <a href="{{ $guard === 'customer' ? url('/dashboard') : url('/' . $guard . '/dashboard') }}" class="block">
                                    <img class="h-16 w-auto transition-transform hover:scale-105 duration-500 " src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein-Seelenfunke">
                                </a>
                            </div>

                            <div class="flex-1 custom-scrollbar overflow-y-auto pr-2">
                                @livewire($guard . '.' . $guard . '-navigation')
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
                <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-gray-900/80 backdrop-blur-xl px-6 pb-4 border-r border-gray-800 shadow-[20px_0_50px_rgba(0,0,0,0.5)]">

                    {{-- DESKTOP LOGO MIT LINK --}}
                    <div class="flex h-24 shrink-0 items-center justify-center border-b border-gray-800 mb-2">
                        <a href="{{ $guard === 'customer' ? url('/dashboard') : url('/' . $guard . '/dashboard') }}" class="block">
                            <img class="h-16 w-auto transition-transform hover:scale-105 duration-500" src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein-Seelenfunke">
                        </a>
                    </div>

                    <div class="flex-1 custom-scrollbar overflow-y-auto pr-2">
                        @livewire($guard . '.' . $guard . '-navigation')
                    </div>
                </div>
            </div>
        @endif

        <div class="{{ $guard !== 'customer' ? 'lg:pl-72' : '' }} transition-all duration-500 flex-1 flex flex-col">
            @if($guard !== 'customer')
                <div class="sticky top-0 z-40 flex h-16 items-center gap-x-4 border-b border-gray-800 bg-gray-900/80 backdrop-blur-xl px-4 shadow-[0_10px_30px_rgba(0,0,0,0.5)] sm:px-6 lg:px-8 w-full max-w-[100vw]">

                    <button @click="open = true" type="button" class="shrink-0 -m-2.5 p-2.5 text-gray-400 lg:hidden hover:text-primary transition-colors">
                        <span class="sr-only">Menü öffnen</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <div class="shrink-0 h-6 w-px bg-gray-700 lg:hidden" aria-hidden="true"></div>

                    <div class="flex flex-1 items-center justify-end md:justify-between min-w-0">

                        <div class="hidden md:block shrink-0 pr-4">
                                    <span class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] drop-shadow-sm">
                                        Systemverwaltung <span class="text-primary mx-1">/</span> {{ ucfirst($guard) }}
                                    </span>
                        </div>

                        <div class="flex-1 flex justify-end min-w-0">
                            @livewire('global.profile.profile-dropdown')
                        </div>

                    </div>
                </div>
            @endif

            <main class="{{ $guard !== 'customer' ? 'py-8 flex-1' : 'flex-1' }}">
                @if($guard !== 'customer')
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="animate-fade-in-up">
                            @yield('content')
                        </div>
                    </div>
                @else
                    @yield('content')
                @endif
            </main>
        </div>
    </div>

    @if($guard !== 'customer')
        <livewire:global.funkira.funkira-chat />
        <livewire:global.funkira.funkira-widget />
    @endif

</div> {{-- ENDE DES GLOBALEN ALPINE STATES --}}

@livewireScripts
@stack('scripts')

<style>
    .shadow-glow { filter: drop-shadow(0 0 8px rgba(197, 160, 89, 0.3)); }
    .animate-fade-in-up { animation: fadeInUp 0.5s ease-out forwards; }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
</style>

<script>
    if (typeof window.universeLayout === 'undefined') {
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

                    // Einstellungen für das gechillte Universum
                    const config = {
                        starsCount: 250,
                        planetsCount: 3, // Sonnen/Monde
                        dustCount: 40,
                        meteorsCount: 12,
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

                    // Hilfsfunktion für Zufallszahlen
                    const random = (min, max) => Math.random() * (max - min) + min;

                    // 1. STERNE (Funkeln im Hintergrund)
                    for (let i = 0; i < config.starsCount; i++) {
                        stars.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(0.3, 1.2),
                            baseAlpha: random(0.1, 0.5),
                            angle: random(0, Math.PI * 2),
                            speed: random(0.005, 0.02),
                            color: Math.random() > 0.8 ? config.colors.gold : config.colors.white
                        });
                    }

                    // 2. MONDE / SONNEN (Tief im Hintergrund, riesig, fast unsichtbar)
                    for (let i = 0; i < config.planetsCount; i++) {
                        planets.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(150, 400),
                            vx: random(-0.02, 0.02), // Extrem langsam
                            vy: random(-0.02, 0.02),
                            color: [config.colors.gold, config.colors.blue, config.colors.copper][Math.floor(Math.random() * 3)],
                            maxAlpha: random(0.03, 0.08) // Geisterhaft transparent
                        });
                    }

                    // 3. STERNENSTAUB (Sanftes Schweben)
                    for (let i = 0; i < config.dustCount; i++) {
                        dust.push({
                            x: random(0, width),
                            y: random(0, height),
                            r: random(1, 3),
                            vx: random(-0.05, 0.05),
                            angle: random(0, Math.PI * 2),
                            floatSpeed: random(0.005, 0.015),
                            floatRange: random(10, 30),
                            baseY: random(0, height),
                            alpha: random(0.1, 0.3)
                        });
                    }

                    // 4. METEORITEN (Zufällige flache Winkel)
                    const spawnMeteor = (isInitial = false) => {
                        // Spawnt entweder links, rechts, oben oder unten
                        const edge = Math.floor(random(0, 4));
                        let x, y, vx, vy;

                        // Extrem langsame Geschwindigkeit für den "Chill" Faktor
                        const speed = random(0.1, 0.3);
                        const angle = random(0, Math.PI * 2);

                        if (isInitial) {
                            x = random(0, width);
                            y = random(0, height);
                        } else {
                            if (edge === 0) { x = -50; y = random(0, height); } // Links
                            if (edge === 1) { x = width + 50; y = random(0, height); } // Rechts
                            if (edge === 2) { x = random(0, width); y = -50; } // Oben
                            if (edge === 3) { x = random(0, width); y = height + 50; } // Unten
                        }

                        // Bewegung in Richtung Zentrum der Map (grob)
                        vx = Math.cos(angle) * speed;
                        vy = Math.sin(angle) * speed;

                        meteors.push({
                            x: x, y: y, vx: vx, vy: vy,
                            size: random(0.8, 2.5),
                            alpha: random(0.1, 0.6),
                            length: random(30, 100),
                            color: Math.random() > 0.5 ? config.colors.gold : config.colors.white
                        });
                    };

                    for (let i = 0; i < config.meteorsCount; i++) {
                        spawnMeteor(true);
                    }

                    // ==========================================
                    // RENDER LOOP
                    // ==========================================
                    const loop = () => {
                        ctx.clearRect(0, 0, width, height);

                        // 1. Monde & Sonnen zeichnen
                        planets.forEach(p => {
                            p.x += p.vx;
                            p.y += p.vy;

                            // Endlos-Loop an den Rändern
                            if (p.x - p.r > width) p.x = -p.r;
                            if (p.x + p.r < 0) p.x = width + p.r;
                            if (p.y - p.r > height) p.y = -p.r;
                            if (p.y + p.r < 0) p.y = height + p.r;

                            let grad = ctx.createRadialGradient(p.x - p.r * 0.2, p.y - p.r * 0.2, 0, p.x, p.y, p.r);
                            grad.addColorStop(0, `${p.color}${p.maxAlpha})`);
                            grad.addColorStop(1, 'rgba(0,0,0,0)');

                            ctx.beginPath();
                            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                            ctx.fillStyle = grad;
                            ctx.fill();
                        });

                        // 2. Sterne zeichnen (Funkeln)
                        stars.forEach(s => {
                            s.angle += s.speed;
                            // Pulsierender Alpha-Wert
                            let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.3;
                            if (currentAlpha < 0) currentAlpha = 0;

                            ctx.beginPath();
                            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                            ctx.fillStyle = `${s.color}${currentAlpha})`;
                            ctx.fill();
                        });

                        // 3. Sternenstaub (Schweben)
                        dust.forEach(d => {
                            d.x += d.vx;
                            d.angle += d.floatSpeed;
                            d.y = d.baseY + Math.sin(d.angle) * d.floatRange;

                            if (d.x > width + 10) d.x = -10;
                            if (d.x < -10) d.x = width + 10;

                            ctx.beginPath();
                            ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
                            ctx.fillStyle = `${config.colors.gold}${d.alpha})`;
                            ctx.shadowBlur = d.r * 3;
                            ctx.shadowColor = `${config.colors.gold}${d.alpha})`;
                            ctx.fill();
                            ctx.shadowBlur = 0; // Reset
                        });

                        // 4. Meteoriten zeichnen
                        for (let i = meteors.length - 1; i >= 0; i--) {
                            let m = meteors[i];

                            m.x += m.vx;
                            m.y += m.vy;

                            // Entfernen und Neu-Spawnen wenn außerhalb
                            if (m.x > width + 150 || m.x < -150 || m.y > height + 150 || m.y < -150) {
                                meteors.splice(i, 1);
                                spawnMeteor();
                                continue;
                            }

                            // Schweif
                            ctx.beginPath();
                            ctx.moveTo(m.x, m.y);
                            ctx.lineTo(m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                            let grad = ctx.createLinearGradient(m.x, m.y, m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                            grad.addColorStop(0, `${m.color}${m.alpha})`);
                            grad.addColorStop(1, 'rgba(0,0,0,0)');

                            ctx.strokeStyle = grad;
                            ctx.lineWidth = m.size * 0.6;
                            ctx.lineCap = 'round';
                            ctx.stroke();

                            // Leuchtender Kopf
                            ctx.beginPath();
                            ctx.arc(m.x, m.y, m.size, 0, Math.PI * 2);
                            ctx.fillStyle = `${m.color}${m.alpha + 0.3})`;
                            ctx.shadowBlur = m.size * 5;
                            ctx.shadowColor = `${m.color}${m.alpha})`;
                            ctx.fill();
                            ctx.shadowBlur = 0; // Reset
                        }

                        requestAnimationFrame(loop);
                    };
                    loop();
                }
            };
        }
    }
</script>

</body>
</html>
