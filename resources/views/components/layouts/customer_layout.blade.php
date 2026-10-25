<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Manufaktur - Mein Seelenfunke</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 2 }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="{{ mix('js/app.js') }}" defer></script>
    <script src="{{ mix('js/admin-bundle.js') }}" defer></script>

    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        // Fallbacks für Alpine-Variablen, die bei Page-Transitions (wire:navigate) kurzzeitig ihre Scopes verlieren können
        window.showAllOperative = false;
        window.tooltip = false;

        // Theme Initialisierung: Standardmäßig Light Mode
        (function() {
            try {
                if (localStorage.theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                }
            } catch (e) {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            }
        })();

        document.addEventListener('livewire:navigated', function() {
            try {
                if (localStorage.theme === 'dark') {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                }
            } catch (e) {}
        });

        // Unterdrückt den harmlosen Alpine/Livewire Fehler bei abgebrochenen CSS-Animationen
        window.addEventListener('unhandledrejection', function(event) {
            if (event.reason && event.reason.isFromCancelledTransition === true) {
                event.preventDefault();
            }
        });
    </script>
    @include("components.layouts.partials.frontend-error-tracker")
</head>

@php
    $hasOptedIn = false;
    $hasUnreadTickets = false;
    $customerId = '';

    if(auth()->guard('customer')->check()) {
        $customerId = (string) auth()->guard('customer')->id();

        $profile = \App\Models\Customer\CustomerGamification::where('customer_id', $customerId)->first();
        if($profile && $profile->is_active) {
            $hasOptedIn = true;
        }

        // Kugelsichere Abfrage für den roten Punkt
        $hasUnreadTickets = \App\Models\Support\SupportTicketMessage::where('sender_type', 'admin')
            ->where('is_read_by_customer', false)
            ->whereIn('support_ticket_id', \App\Models\Support\SupportTicket::where('customer_id', $customerId)->pluck('id'))
            ->exists();
    }
@endphp

{{-- ALPINE.JS GLOBAL STATE (Ersetzt die Livewire-Komponente komplett!) --}}
<body x-data="{
        hasUnreadSupport: {{ $hasUnreadTickets ? 'true' : 'false' }},
        showToast: false,
        toastMessage: '',
        initGlobalEcho() {
            @if($customerId)
            let attempts = 0;
            const checkEcho = setInterval(() => {
                attempts++;
                if (typeof window.Echo !== 'undefined') {
                    clearInterval(checkEcho); // Echo ist da, wir können starten!

                    window.Echo.private('customer.{{ $customerId }}')
                        .listen('.TicketMessageSent', (e) => {
                            window.dispatchEvent(new CustomEvent('customer-ticket-message-received', { detail: e }));

                            if (e.message && e.message.sender_type === 'admin') {
                                // 1. Roten Punkt sofort anschalten
                                this.hasUnreadSupport = true;

                                // 2. Popup zeigen (wenn wir NICHT im Support-Chat sind)
                                if (!window.location.pathname.includes('/support')) {
                                    let tNumber = (e.message && e.message.ticket) ? e.message.ticket.ticket_number : 'dein Ticket';
                                    this.toastMessage = 'Der Support hat auf ' + tNumber + ' geantwortet.';
                                    this.showToast = true;
                                    setTimeout(() => { this.showToast = false; }, 12000); // 12 Sekunden statt 5
                                }
                            }
                        });
                } else if (attempts > 30) {
                    clearInterval(checkEcho); // Sicherheitsabbruch nach 10 Sekunden
                }
            }, 300);
            @endif
        }
      }"
      x-init="initGlobalEcho()"
      @clear-ticket-badge.window="hasUnreadSupport = false"
      class="bg-slate-50 dark:bg-gray-950 text-gray-900 dark:text-white flex flex-col h-screen overflow-hidden selection:bg-primary selection:text-gray-900 font-sans relative transition-colors duration-300">

{{-- HINTERGRUND UNIVERSUM --}}
<div x-data="goldDust()" x-init="init()" class="fixed inset-0 z-0 pointer-events-none opacity-40 dark:opacity-60">
    <canvas id="gold-dust-canvas" class="w-full h-full"></canvas>
</div>

{{-- GLOBALER HEADER (Mit Top-Navigation auf Desktop) --}}
<header class="h-20 lg:h-24 w-full min-w-0 bg-white/80 dark:bg-gray-900/90 backdrop-blur-xl border-b border-gray-200 dark:border-gray-800 px-4 sm:px-6 lg:px-8 shadow-sm dark:shadow-lg shrink-0 flex items-center relative z-40 transition-colors duration-300">
    <livewire:customer.gamification.game-global-stats-header />
</header>

{{-- HAUPT-CONTENT BEREICH (Volle Breite) --}}
<main class="flex-1 overflow-x-hidden overflow-y-auto relative no-scrollbar pb-24 lg:pb-8 z-30 min-w-0">
    <div class="relative min-h-full w-full">
        {{ $slot }}

        @if(auth()->guard('customer')->check())
            <livewire:customer.gamification.game-spark-hunt-component />
        @endif
    </div>
</main>

{{-- MOBILE BOTTOM NAVIGATION --}}
<nav class="lg:hidden fixed bottom-0 left-0 w-full bg-white/95 dark:bg-gray-900/98 backdrop-blur-xl border-t border-gray-200 dark:border-gray-800 z-50 flex items-center justify-around px-2 py-2 pb-safe shadow-lg dark:shadow-[0_-10px_20px_rgba(0,0,0,0.5)]">
    <a href="{{ route('customer.dashboard') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.dashboard') ? 'text-primary' : 'text-gray-500 dark:text-gray-400' }}">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span class="text-[9px] font-black uppercase tracking-widest">Dashboard</span>
    </a>

    <a href="{{ route('customer.orders') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.orders') ? 'text-primary' : 'text-gray-500 dark:text-gray-400' }}">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <span class="text-[9px] font-black uppercase tracking-widest">Bestellungen</span>
    </a>

    <a href="{{ route('customer.invoices') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.invoices') ? 'text-primary' : 'text-gray-500 dark:text-gray-400' }}">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span class="text-[9px] font-black uppercase tracking-widest">Rechnungen</span>
    </a>

    {{-- NEU: MOBILE SUPPORT LINK --}}
    <a href="{{route('customer.support')}}" @click="hasUnreadSupport = false" class="flex flex-col items-center gap-1 flex-1 py-1 {{request()->routeIs('customer.support')? 'text-primary' : 'text-gray-500 dark:text-gray-400'}}">
        <div class="relative">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span x-show="hasUnreadSupport" style="display: none;" class="absolute -top-0.5 -right-1 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
            </span>
        </div>
        <span class="text-[9px] font-black uppercase tracking-widest">Hilfe</span>
    </a>

    @if($hasOptedIn)
    <a href="{{ route('customer.gamification.profile') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.games') || request()->routeIs('customer.gamification.profile') ? 'text-emerald-500' : 'text-gray-500 dark:text-gray-400' }}">
        <span class="text-xl leading-none h-6 flex items-center">🎮</span>
        <span class="text-[9px] font-black uppercase tracking-widest">Spiele</span>
    </a>
    @else
    <a href="{{ route('customer.games') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.games') ? 'text-emerald-500' : 'text-gray-500 dark:text-gray-400' }}">
        <span class="text-xl leading-none h-6 flex items-center">🎮</span>
        <span class="text-[9px] font-black uppercase tracking-widest">Spiele</span>
    </a>
    @endif
</nav>

@if(auth()->guard('customer')->check())
    {{-- NATIVES TOAST POPUP OHNE LIVEWIRE --}}
    <div x-cloak x-show="showToast" style="display: none;" class="fixed bottom-6 right-6 z-[100]"
         x-transition:enter="transition-all duration-500 ease-out"
         x-transition:enter-start="translate-y-10 opacity-0 pointer-events-none"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition-all duration-500 ease-out"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="translate-y-10 opacity-0 pointer-events-none">
        <a href="{{ route('customer.support') }}" class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border border-gray-200 dark:border-gray-700 hover:border-primary/50 shadow-2xl rounded-2xl p-4 flex items-center gap-4 max-w-sm cursor-pointer transition-all group block">
            <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center shrink-0 border border-primary/30 group-hover:scale-110 transition-transform">
                <span class="text-primary text-xl">💌</span>
            </div>
            <div>
                <h4 class="text-gray-900 dark:text-white text-sm font-bold tracking-wide group-hover:text-primary transition-colors">Support Desk</h4>
                <p class="text-gray-600 dark:text-gray-400 text-xs mt-0.5" x-text="toastMessage"></p>
                <div class="text-[10px] text-primary mt-1.5 font-bold uppercase tracking-widest opacity-80 group-hover:opacity-100 transition-opacity">Zum Ticket springen &rarr;</div>
            </div>
        </a>
    </div>
@endif

@livewireScripts

{{-- Gold Dust Skript --}}
<script>
    function goldDust() {
        return {
            init() {
                const canvas = document.getElementById('gold-dust-canvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                let width, height;
                let stars = [];
                const config = { starsCount: 150, color: '197, 160, 89' };

                const resize = () => { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; };
                window.addEventListener('resize', resize); resize();
                const random = (min, max) => Math.random() * (max - min) + min;

                for (let i = 0; i < config.starsCount; i++) {
                    stars.push({
                        x: random(0, width), y: random(0, height), r: random(0.2, 1.2),
                        baseAlpha: random(0.1, 0.6), angle: random(0, Math.PI * 2), speed: random(0.005, 0.02)
                    });
                }

                const loop = () => {
                    if (!window.__PauseBackgroundCanvas && !document.hidden) {
                        ctx.clearRect(0, 0, width, height);
                        stars.forEach(s => {
                            s.angle += s.speed;
                            let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.3;
                            if (currentAlpha < 0) currentAlpha = 0;
                            s.x -= 0.1;
                            if (s.x < 0) s.x = width;
                            ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                            ctx.fillStyle = `rgba(${config.color}, ${currentAlpha})`; ctx.fill();
                        });
                    }
                    requestAnimationFrame(loop);
                }; loop();
            }
        };
    }

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
                const payload = Array.isArray(data) ? (data[0] || {}) : (data || {});
                this.open3DModal();
                setTimeout(() => {
                    this.evolutionFlash = true;
                    setTimeout(() => {
                        if (payload.newModelPath) this.currentPath = payload.newModelPath;
                        if (payload.newImagePath) this.currentImagePath = payload.newImagePath;

                        let flashFinished = false;
                        const finishFlash = () => {
                            if (flashFinished) return;
                            flashFinished = true;
                            this.evolutionFlash = false;
                            this.rewardMessage = payload.reward || 'Neue Form freigeschaltet!';
                            this.showConfetti = true;
                            setTimeout(() => { this.showConfetti = false; }, 4000);
                        };

                        // FAIL-SAFE: Guarantees the flash ends even if ThreeJS crashes or loader hangs
                        setTimeout(finishFlash, 2500);

                        if (threeInitialized && window._funki3DLoader) {
                            window._funki3DLoader(this.currentPath, finishFlash);
                        } else {
                            finishFlash();
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
                renderer.domElement.style.cursor = 'default';
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
                    loader.load(
                        path,
                        (gltf) => {
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
                        },
                        undefined,
                        (error) => {
                            console.error('Failed to load Funki model:', error);
                            // Even if it fails, trigger the callback so we don't get stuck with a white screen flash
                            if (window._funkiLatestRequestedPath === path && cb) {
                                cb();
                            }
                        }
                    );
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
</body>
</html>
