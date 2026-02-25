<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>

    @livewireStyles
</head>
<body class="min-h-screen bg-gray-950 font-sans text-gray-300 antialiased relative overflow-x-hidden" x-data="goldDustLayout()" x-init="init()">

{{-- HINTERGRUND FUNKEN --}}
<canvas id="global-gold-dust-canvas" class="fixed inset-0 z-0 pointer-events-none w-full h-full" wire:ignore></canvas>

<div x-data="{ open: false }" class="relative z-10">
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
                        <div class="flex h-24 shrink-0 items-center justify-center border-b border-gray-800 mb-2">
                            <img class="h-16 w-auto transition-transform hover:scale-105 duration-500 drop-shadow-[0_0_15px_rgba(197,160,89,0.5)]" src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein-Seelenfunke">
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
                <div class="flex h-24 shrink-0 items-center justify-center border-b border-gray-800 mb-2">
                    <img class="h-16 w-auto transition-transform hover:scale-105 duration-500 shadow-glow drop-shadow-[0_0_15px_rgba(197,160,89,0.5)]" src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein-Seelenfunke">
                </div>
                <div class="flex-1 custom-scrollbar overflow-y-auto pr-2">
                    @livewire($guard . '.' . $guard . '-navigation')
                </div>
            </div>
        </div>
    @endif

    <div class="{{ $guard !== 'customer' ? 'lg:pl-72' : '' }} transition-all duration-500">
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

        <main class="{{ $guard !== 'customer' ? 'py-8' : '' }}">
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
    @livewire('global.widgets.funki-chat')
@endif

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
    if (typeof window.goldDustLayout === 'undefined') {
        window.goldDustLayout = function() {
            return {
                maxMeteors: 60, // Auf 60 Meteoriten erhöht

                init() {
                    const canvas = document.getElementById('global-gold-dust-canvas');
                    if (!canvas) return;
                    const ctx = canvas.getContext('2d');
                    let meteors = [];

                    const resize = () => {
                        canvas.width = document.body.clientWidth;
                        canvas.height = window.innerHeight;
                    };

                    window.addEventListener('resize', resize);
                    resize();

                    // Funktion zum Erstellen eines Meteoriten
                    const spawnMeteor = (initialSpawn = false) => {
                        // 50/50 Chance: links nach rechts ODER rechts nach links
                        const goRight = Math.random() > 0.5;

                        // EXTREM langsam: 0.05 bis 0.15 Pixel pro Frame.
                        let vx = (Math.random() * 0.1 + 0.05);
                        if (!goRight) vx = -vx;

                        // Leichtes vertikales Absinken/Aufsteigen
                        let vy = (Math.random() - 0.5) * 0.05;

                        meteors.push({
                            // Beim Initialen Start verteilen wir sie zufällig im Bild,
                            // danach spawnen sie immer ganz außen am Rand.
                            x: initialSpawn ? (Math.random() * canvas.width) : (goRight ? -50 : canvas.width + 50),
                            y: Math.random() * canvas.height,
                            vx: vx,
                            vy: vy,
                            // Deutlich größer (zwischen 1.5 und 4.0 Radius)
                            size: Math.random() * 2.5 + 1.5,
                            // Transparenz (Distanzeffekt)
                            alpha: Math.random() * 0.3 + 0.1,
                            // Länge des Schweifs an die neue Größe angepasst
                            tailLength: Math.random() * 60 + 30
                        });
                    };

                    // 60 Meteoriten initial erschaffen
                    for (let i = 0; i < this.maxMeteors; i++) {
                        spawnMeteor(true);
                    }

                    const loop = () => {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);

                        for (let i = meteors.length - 1; i >= 0; i--) {
                            let m = meteors[i];

                            // Position aktualisieren
                            m.x += m.vx;
                            m.y += m.vy;

                            // Wenn der Meteorit den Bildschirm komplett verlassen hat -> löschen & neu spawnen
                            if ((m.vx > 0 && m.x > canvas.width + 100) ||
                                (m.vx < 0 && m.x < -100) ||
                                m.y > canvas.height + 100 ||
                                m.y < -100) {
                                meteors.splice(i, 1);
                                spawnMeteor(); // Neu am Rand spawnen
                                continue;
                            }

                            // 1. Den feinen Schweif zeichnen
                            ctx.beginPath();
                            ctx.moveTo(m.x, m.y);
                            // Der Schweif zeigt in die entgegengesetzte Flugrichtung
                            ctx.lineTo(m.x - (m.vx * m.tailLength), m.y - (m.vy * m.tailLength));
                            ctx.strokeStyle = `rgba(197, 160, 89, ${m.alpha * 0.4})`; // Primärfarbe Gold (sehr transparent)
                            ctx.lineWidth = m.size * 0.8;
                            ctx.stroke();

                            // 2. Den "Kopf" des Meteoriten zeichnen
                            ctx.beginPath();
                            ctx.arc(m.x, m.y, m.size, 0, Math.PI * 2);
                            ctx.fillStyle = `rgba(212, 175, 55, ${m.alpha + 0.2})`;
                            ctx.shadowBlur = m.size * 4;
                            ctx.shadowColor = `rgba(197, 160, 89, ${m.alpha})`;
                            ctx.fill();
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
