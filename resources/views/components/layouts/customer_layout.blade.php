<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manufaktur der Magie - Seelenfunke</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="{{ asset('js/app.js') }}" defer></script>

    @livewireStyles

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
        $hasUnreadTickets = \App\Models\TicketMessage::where('sender_type', 'admin')
            ->where('is_read_by_customer', false)
            ->whereIn('ticket_id', \App\Models\Ticket::where('customer_id', $customerId)->pluck('id'))
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
                            if (e.message && e.message.sender_type === 'admin') {
                                // 1. Roten Punkt sofort anschalten
                                this.hasUnreadSupport = true;

                                // 2. Popup zeigen (wenn wir NICHT im Support-Chat sind)
                                if (!window.location.pathname.includes('/support')) {
                                    this.toastMessage = 'Der Support hat auf dein Ticket geantwortet.';
                                    this.showToast = true;
                                    setTimeout(() => { this.showToast = false; }, 5000);
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
      class="bg-gray-950 text-white flex h-screen overflow-hidden selection:bg-primary selection:text-gray-900 font-sans relative">

{{-- HINTERGRUND UNIVERSUM --}}
<div x-data="goldDust()" x-init="init()" class="fixed inset-0 z-0 pointer-events-none">
    <canvas id="gold-dust-canvas" class="w-full h-full opacity-60"></canvas>
</div>

{{-- DESKTOP SIDEBAR --}}
<aside class="hidden lg:flex flex-col w-72 bg-gray-900/80 backdrop-blur-xl border-r border-gray-800 relative z-40 shadow-2xl h-full shrink-0">
    <div class="p-8 border-b border-gray-800 flex justify-center shrink-0">
        <a href="{{ route('customer.dashboard') }}" class="block hover:scale-105 transition-transform">
            <img src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" class="h-16 w-auto" alt="Logo">
        </a>
    </div>

    <nav class="flex-1 p-6 space-y-3 overflow-y-auto custom-scrollbar">
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-4 px-4">Navigation</div>

        <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl font-bold tracking-wide transition-all {{ request()->routeIs('customer.dashboard') ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Zentrale
        </a>

        <a href="{{ route('customer.orders') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl font-bold tracking-wide transition-all {{ request()->routeIs('customer.orders') ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            Bestellungen
        </a>

        {{-- NEU: SUPPORT DESK LINK --}}
        <a href="{{route('customer.support')}}" @click="hasUnreadSupport = false" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl font-bold tracking-wide transition-all {{request()->routeIs('customer.support')? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-400 hover:text-white hover:bg-gray-800'}}">
            <div class="relative">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span x-show="hasUnreadSupport" style="display: none;" class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
                </span>
            </div>
            Support Desk
        </a>

        @if($hasOptedIn)
            <a href="{{ route('customer.games') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl font-bold tracking-wide transition-all {{ request()->routeIs('customer.games') ? 'bg-emerald-500 text-gray-900 shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'text-gray-400 hover:text-emerald-400 hover:bg-gray-800' }}">
                <span class="text-xl">🎮</span>
                Spiele
            </a>
        @endif
    </nav>

    <div class="p-6 border-t border-gray-800 shrink-0">
        <a href="{{ route('shop') }}" target="_blank" class="w-full flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white border border-gray-700 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all">
            Zum Shop
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </a>
    </div>
</aside>

{{-- HAUPT-CONTENT BEREICH --}}
<main class="flex-1 flex flex-col h-full relative z-30 min-w-0">

    {{-- GLOBALER HEADER --}}
    <header class="h-20 lg:h-24 w-full min-w-0 bg-gray-900/90 backdrop-blur-xl border-b border-gray-800 px-3 lg:px-8 shadow-lg shrink-0 flex items-center">
        <livewire:customer.global-stats-header />
    </header>

    {{-- SEITEN-INHALT --}}
    <div class="flex-1 overflow-x-hidden overflow-y-auto relative no-scrollbar pb-24 lg:pb-0">
        {{ $slot }}
    </div>

</main>

{{-- MOBILE BOTTOM NAVIGATION --}}
<nav class="lg:hidden fixed bottom-0 left-0 w-full bg-gray-900/98 backdrop-blur-xl border-t border-gray-800 z-50 flex items-center justify-around px-2 py-2 pb-safe shadow-[0_-10px_20px_rgba(0,0,0,0.5)]">
    <a href="{{ route('customer.dashboard') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.dashboard') ? 'text-primary' : 'text-gray-500' }}">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span class="text-[9px] font-black uppercase tracking-widest">Home</span>
    </a>
    <a href="{{ route('customer.orders') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.orders') ? 'text-primary' : 'text-gray-500' }}">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <span class="text-[9px] font-black uppercase tracking-widest">Orders</span>
    </a>

    {{-- NEU: MOBILE SUPPORT LINK --}}
    <a href="{{route('customer.support')}}" @click="hasUnreadSupport = false" class="flex flex-col items-center gap-1 flex-1 py-1 {{request()->routeIs('customer.support')? 'text-primary' : 'text-gray-500'}}">
        <div class="relative">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span x-show="hasUnreadSupport" style="display: none;" class="absolute -top-0.5 -right-1 flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
            </span>
        </div>
        <span class="text-[9px] font-black uppercase tracking-widest">Support</span>
    </a>

    @if($hasOptedIn)
        <a href="{{ route('customer.games') }}" class="flex flex-col items-center gap-1 flex-1 py-1 {{ request()->routeIs('customer.games') ? 'text-emerald-500' : 'text-gray-500' }}">
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
        <div class="bg-gray-900/95 backdrop-blur-md border border-gray-700 shadow-2xl rounded-2xl p-4 flex items-center gap-4 max-w-sm">
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
                    requestAnimationFrame(loop);
                }; loop();
            }
        };
    }
</script>
</body>
</html>
