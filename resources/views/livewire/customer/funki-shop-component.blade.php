{{--
    HAUPTCONTAINER FÜR DAS DASHBOARD
    Wir nutzen hier x-data="funkiHub(...)" ganz außen, damit der Scope für alle Modals verfügbar ist.
--}}
<div class="w-full min-h-screen bg-gray-950 relative overflow-x-hidden" x-data="funkiHub('{{ $modelPath }}', '{{ $imagePath }}')" x-init="initShop()">

    {{-- SCRIPTS EINBINDEN (Livewire, Alpine, Three.js etc.) --}}
    @include('livewire.customer.partials.scripts')

    {{--
        ==========================================
        NEUES SCRIPT: KRISTALL-KOLLAPS ENGINE
        ==========================================
        Dies ist die komplette Logik für das erste Match-3 Spiel.
    --}}
    @include('livewire.customer.partials.cristall_game')

    {{-- HINTERGRUND UNIVERSUM (Isoliert in goldDust) --}}
    <div x-data="goldDust()">
        <canvas id="gold-dust-canvas" class="fixed inset-0 z-[10] pointer-events-none transform-gpu" style="will-change: transform; transform: translateZ(0);" wire:ignore></canvas>
    </div>

    {{-- GLOBALE STYLES --}}
    <style>
        .shadow-glow { box-shadow: 0 0 35px rgba(197, 160, 89, 0.4); }
        .perspective-1000 { perspective: 1000px; }
        @keyframes float { 0%, 100% { transform: translate3d(0px, 0px, 0px) rotate(0deg); } 50% { transform: translate3d(0px, -30px, 0px) rotate(2deg); } }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .animate-fade-in-up { animation: fadeInUp 0.8s ease-out forwards; will-change: transform, opacity; }
        @keyframes fadeInUp { from { opacity: 0; transform: translate3d(0, 40px, 0); } to { opacity: 1; transform: translate3d(0, 0, 0); } }

        /* STYLES FÜR KRISTALL-KOLLAPS */
        .game-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 0.25rem; padding: 0.5rem; background: rgba(0,0,0,0.3); border-radius: 1rem; }
        .crystal { width: 100%; aspect-ratio: 1; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s ease-in-out; position: relative; border: 2px solid transparent; }
        .crystal:hover { transform: scale(1.05); z-index: 10; }
        .crystal.selected { border-color: theme('colors.primary.DEFAULT'); box-shadow: 0 0 15px theme('colors.primary.DEFAULT'); transform: scale(1.1); z-index: 20; }
        .crystal.exploded { opacity: 0; transform: scale(0.5); transition: all 0.3s ease-in; }
        /* Die 6 Kristall-Typen - Leuchtende Fragmente */
        .c-type-1 { background: radial-gradient(circle at 30% 30%, #ef4444, #991b1b); box-shadow: inset 0 0 10px #fca5a5, 0 0 5px #b91c1c; } /* Rubin */
        .c-type-2 { background: radial-gradient(circle at 30% 30%, #3b82f6, #1e40af); box-shadow: inset 0 0 10px #93c5fd, 0 0 5px #1d4ed8; } /* Saphir */
        .c-type-3 { background: radial-gradient(circle at 30% 30%, #10b981, #065f46); box-shadow: inset 0 0 10px #6ee7b7, 0 0 5px #047857; } /* Smaragd */
        .c-type-4 { background: radial-gradient(circle at 30% 30%, #eab308, #854d0e); box-shadow: inset 0 0 10px #fde047, 0 0 5px #a16207; } /* Topas */
        .c-type-5 { background: radial-gradient(circle at 30% 30%, #a855f7, #6b21a8); box-shadow: inset 0 0 10px #d8b4fe, 0 0 5px #7e22ce; } /* Amethyst */
        .c-type-6 { background: radial-gradient(circle at 30% 30%, #06b6d4, #164e63); box-shadow: inset 0 0 10px #67e8f9, 0 0 5px #0e7490; } /* Diamant */
    </style>

    @if(!$hasOptedIn)
        {{-- ========================================== --}}
        {{-- 1. OPT-IN SCREEN (LANDING PAGE VOR DEM WARP) --}}
        {{-- ========================================== --}}
        <div class="w-full min-h-screen bg-transparent pb-24 relative z-20 overflow-x-hidden">
            {{-- HEADER --}}
            <div x-data="{ isWarping: false }" @warp-started.window="isWarping = true" :class="isWarping ? 'opacity-0 -translate-y-full' : 'opacity-100'" class="sticky top-0 w-full bg-gray-950/80 backdrop-blur-xl flex flex-col xl:flex-row items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-800 z-[250] gap-4 md:gap-6 shadow-lg transform-gpu transition-all duration-1000 ease-in-out">
                <div class="flex items-center justify-between w-full xl:w-auto xl:pr-6 xl:border-r border-gray-800 shrink-0">
                    <a href="/" target="_blank" class="shrink-0">
                        <img src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" class="h-16 md:h-20 w-auto hover:scale-105 transition-transform duration-300" alt="Logo">
                    </a>
                </div>
                <div class="w-full xl:w-auto flex justify-center xl:justify-end mt-1 xl:mt-0 relative z-30">
                    @livewire('global.profile.profile-dropdown')
                </div>
            </div>

            {{-- OPT-IN INHALT --}}
            <div x-data="optInScreen()" @mousemove="handleMouse($event)" @mouseleave="resetMouse()"
                 :class="isWarping ? 'opacity-0 scale-95 blur-sm' : 'opacity-100 scale-100 blur-0'"
                 class="max-w-[1600px] mx-auto bg-gray-900 shadow-[0_20px_50px_rgba(0,0,0,0.5)] border-y border-gray-800 relative p-8 md:p-16 lg:p-20 flex flex-col lg:flex-row items-center gap-12 lg:gap-16 transition-all duration-[1500ms] ease-in-out mt-12 transform-gpu">
                <div class="absolute inset-0 pointer-events-none transition-opacity duration-300 transform-gpu" style="will-change: opacity, background;" :style="`background: radial-gradient(circle 600px at ${mouseX}px ${mouseY}px, rgba(197, 160, 89, 0.12), transparent 40%); opacity: ${isHovering ? 1 : 0};`"></div>
                <div class="relative z-10 flex-1 transform-gpu">
                    @if(count($profileSteps) > 0)
                        <div class="mb-10 flex flex-wrap gap-3 p-5 bg-gray-950 rounded-2xl border border-gray-800 shadow-inner">
                            <span class="w-full block text-[10px] text-gray-500 uppercase tracking-[0.2em] font-black mb-1">Profil vervollständigen:</span>
                            @foreach($profileSteps as $step)
                                <button @click="{!! $step['action'] !!}" class="px-4 py-2 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full text-[9px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse">{{ $step['label'] }}</button>
                            @endforeach
                        </div>
                    @endif
                    <span class="inline-block px-5 py-2 bg-primary/10 text-primary font-black uppercase tracking-widest rounded-xl mb-6 border border-primary/30 shadow-[0_0_15px_rgba(197,160,89,0.2)] animate-pulse">Dein neues Shopping-Erlebnis</span>
                    <h2 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-serif font-bold text-white mb-6 leading-[1.1] drop-shadow-md tracking-tight">Einkaufen,<br>weit weg vom <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-amber-300">Standard.</span></h2>
                    <p class="text-gray-400 text-base md:text-lg mb-10 leading-relaxed max-w-2xl font-medium">Willkommen in der Manufaktur! Dein Dashboard ist kein einfaches Kundenkonto mehr – es ist <strong class="text-white">spielerisch, interaktiv und lebendig</strong>. Begleite deinen persönlichen 3D-Gefährten auf seiner Reise.</p>
                    <button @click="triggerEpicStart()" class="w-full sm:w-auto group relative px-12 py-5 bg-gradient-to-r from-primary to-primary-dark text-gray-900 rounded-xl font-black uppercase tracking-widest text-sm shadow-[0_0_40px_rgba(197,160,89,0.5)] hover:scale-105 hover:shadow-[0_0_60px_rgba(197,160,89,0.8)] transition-all flex items-center justify-center gap-4 overflow-hidden transform-gpu">
                        <div class="absolute inset-0 bg-white/30 transform -skew-x-12 -translate-x-[150%] group-hover:translate-x-[150%] transition-transform duration-1000 ease-in-out"></div>
                        <span>Magie jetzt aktivieren</span>
                        <svg class="w-5 h-5 group-hover:translate-x-2 group-hover:scale-110 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                </div>
                <div class="relative z-10 w-full lg:w-5/12 flex justify-center perspective-1000 transform-gpu mt-12 lg:mt-0">
                    <div class="relative w-64 h-64 sm:w-80 sm:h-80 md:w-96 md:h-96 transform-gpu hover:rotate-y-12 hover:rotate-x-12 transition-transform duration-700 ease-out">
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-[50px] animate-pulse transform-gpu"></div>
                        <img src="{{ asset('storage/funki/models/images/funki_lvl_5_apprentice.png') }}" draggable="false" class="relative w-full h-full object-contain drop-shadow-[0_20px_30px_rgba(0,0,0,0.8)] animate-[float_6s_ease-in-out_infinite] pointer-events-none select-none transform-gpu z-10">
                    </div>
                </div>
            </div>
            {{-- BESTELLUNGEN --}}
            <div x-data="{ isWarping: false }" @warp-started.window="isWarping = true" :class="isWarping ? 'opacity-0 translate-y-20' : 'opacity-100'" class="max-w-[1600px] mx-auto px-6 md:px-16 mt-24 relative z-10 animate-fade-in-up delay-300 transform-gpu transition-all duration-[1500ms] ease-in-out">
                <div class="flex items-center gap-6 sm:gap-8 mb-10">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-950 rounded-2xl flex items-center justify-center text-primary shadow-inner border border-gray-800">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 drop-shadow-[0_0_8px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                    </div>
                    <div>
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-white mb-2 tracking-tight">Deine Bestellungen</h2>
                        <p class="text-gray-500 font-black uppercase tracking-widest text-[9px] sm:text-[10px]">Historie deiner Schätze</p>
                    </div>
                </div>
                <div class="bg-transparent">
                    @include('livewire.customer.partials.orders_section')
                </div>
            </div>
        </div>
    @else
        {{-- ========================================== --}}
        {{-- 2. FUNKI HUB (DAS DASHBOARD NACH DEM WARP) --}}
        {{-- ========================================== --}}
        <div class="bg-gray-900 shadow-2xl border-t border-gray-800 relative animate-fade-in-up min-h-screen z-20" @funki-level-up.window="handleLevelUp($event.detail[0])">
            <div x-show="darkFade" x-transition:leave="transition ease-in-out duration-[2000ms]" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-[9999] bg-gray-950 pointer-events-none" style="display: none;"></div>
            @include('livewire.customer.partials.ep_bar')

            {{-- HEADER BEREICH --}}
            <div id="shop-header-container" class="relative min-h-[85vh] py-20 lg:py-24 flex items-center justify-center overflow-hidden border-b border-gray-800">
                <canvas id="funki-sparks-bg" class="absolute inset-0 z-0 opacity-80 w-full h-full pointer-events-none transform-gpu" style="will-change: transform; transform: translateZ(0);" wire:ignore></canvas>
                <div class="absolute inset-0 bg-gradient-to-b from-transparent via-gray-950/40 to-gray-950 z-1 pointer-events-none"></div>
                <div class="relative z-10 text-center flex flex-col items-center w-full max-w-5xl px-4 sm:px-6">
                    <style> @keyframes subtleFloat { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } } .animate-subtle-float { animation: subtleFloat 6s ease-in-out infinite; } </style>
                    {{-- AVATAR & BUTTONS --}}
                    <div class="relative mt-12 mb-20 flex flex-col items-center">
                        <button @click.stop="showTitlesModal=true" class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 border border-primary px-8 py-3 rounded-full font-black uppercase tracking-widest text-xs text-white shadow-[0_0_25px_rgba(197,160,89,0.6)] flex items-center gap-3 hover:bg-primary hover:text-gray-900 transition-all z-40 hover:scale-110 group cursor-pointer">
                            {{ $currentRankName }} <x-heroicon-s-cog-6-tooth class="w-5 h-5 group-hover:rotate-90 transition-transform duration-500" />
                        </button>
                        <button @click="open3DModal()" class="group relative flex flex-col items-center justify-center mt-6 transition-transform duration-700 hover:scale-105">
                            <div class="absolute inset-0 bg-primary/20 rounded-full blur-[80px] group-hover:bg-primary/30 transition-colors duration-700 pointer-events-none"></div>
                            <div class="relative w-72 h-72 sm:w-96 sm:h-96 md:w-[28rem] md:h-[28rem] lg:w-[32rem] lg:h-[32rem] rounded-full bg-gradient-to-br from-gray-800 to-gray-950 border-2 border-gray-800 shadow-[0_30px_60px_rgba(0,0,0,0.8)] flex items-center justify-center overflow-hidden z-10 group-hover:border-primary transition-all duration-700">
                                <img :src="currentImagePath" src="{{ $imagePath }}" class="w-full h-full object-contain p-6 animate-subtle-float">
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-sm z-20 pointer-events-none">
                                    <span class="bg-primary text-gray-900 px-8 py-4 rounded-xl font-black text-sm uppercase tracking-widest shadow-2xl whitespace-nowrap">3D Modell öffnen</span>
                                </div>
                            </div>
                            <div class="relative z-40 -mt-6 bg-gradient-to-r from-primary to-primary-dark text-gray-900 px-12 py-4 rounded-full font-black text-sm uppercase tracking-[0.2em] shadow-[0_10px_20px_rgba(0,0,0,0.6)]">Level {{ $level }}</div>
                        </button>
                        {{-- SPIELEN BUTTON --}}
                        <div class="relative z-50 mt-10">
                            <button @click.stop="showGameModal = true" class="group relative px-10 py-4 bg-gray-900 border border-primary/50 text-white rounded-2xl font-black uppercase tracking-widest text-sm shadow-[0_0_30px_rgba(197,160,89,0.3)] hover:scale-110 hover:shadow-[0_0_50px_rgba(197,160,89,0.6)] hover:border-primary transition-all duration-300 flex items-center gap-3 overflow-hidden">
                                <div class="absolute inset-0 bg-white/20 transform -skew-x-12 -translate-x-[150%] group-hover:translate-x-[150%] transition-transform duration-700 ease-in-out"></div>
                                <svg class="w-6 h-6 text-primary group-hover:animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Jetzt Spielen</span>
                            </button>
                        </div>
                    </div>
                    {{-- TEXT & AKTIONEN --}}
                    <h2 class="text-5xl sm:text-6xl md:text-8xl font-serif font-bold mb-6 tracking-tight text-white drop-shadow-2xl">Willkommen, {{ auth()->user()->first_name }}</h2>
                    @if(count($profileSteps) > 0)
                        <div class="mb-10 flex flex-wrap justify-center gap-3 bg-gray-900/50 backdrop-blur-md border border-gray-800 p-5 rounded-2xl shadow-inner max-w-4xl">
                            <span class="w-full block text-[10px] text-gray-500 uppercase tracking-[0.2em] font-black mb-2">Profil vervollständigen:</span>
                            @foreach($profileSteps as $step)
                                <button @click="{!! $step['action'] !!}" class="px-5 py-2.5 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse hover:scale-105">{{ $step['label'] }}</button>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-400 text-lg sm:text-xl md:text-2xl mb-12 max-w-3xl leading-relaxed drop-shadow-md">Deine persönliche Manufaktur ist bereit. Verwalte deine Schätze oder rüste Funki für neue Abenteuer auf.</p>
                    @endif
                    <div class="flex flex-wrap justify-center gap-4 sm:gap-8 relative z-50">
                        <a href="{{ route('shop') }}" target="_blank" class="px-8 sm:px-12 py-5 sm:py-6 bg-white text-gray-900 rounded-2xl font-black text-sm sm:text-base hover:bg-primary transition-all uppercase tracking-widest shadow-[0_0_20px_rgba(255,255,255,0.2)] hover:shadow-[0_0_30px_rgba(197,160,89,0.5)] hover:-translate-y-1">Neues Unikat gestalten</a>
                        <button @click="document.getElementById('orders-section').scrollIntoView({behavior: 'smooth'})" class="px-8 sm:px-12 py-5 sm:py-6 bg-gray-900/80 backdrop-blur-md text-white rounded-2xl font-black text-sm sm:text-base hover:bg-gray-800 border border-gray-700 transition-all uppercase tracking-widest shadow-xl hover:-translate-y-1">Bestellungen ansehen</button>
                    </div>
                </div>
            </div>

            @include('livewire.customer.partials.header_filter')
            @include('livewire.customer.partials.shop-grid')

            <section id="orders-section" class="bg-gray-950 text-white py-24 sm:py-32 px-6 md:px-12 border-t border-gray-800 relative z-20 shadow-inner">
                <div class="max-w-[1600px] mx-auto">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-6 sm:gap-8 mb-12 sm:mb-16">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-900 rounded-2xl flex items-center justify-center text-primary shadow-[0_0_20px_rgba(197,160,89,0.1)] border border-gray-800">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        </div>
                        <div>
                            <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold mb-2 tracking-tight text-white">Deine Bestellungen</h2>
                            <p class="text-gray-500 font-black uppercase tracking-widest text-[9px] sm:text-[10px]">Die Historie deiner persönlichen Schätze</p>
                        </div>
                    </div>
                    <div class="bg-transparent">
                        @include('livewire.customer.partials.orders_section')
                    </div>
                </div>
            </section>

            @include('livewire.customer.partials.evolution_timeline')
            @include('livewire.customer.partials.title_management')

            {{-- ========================================== --}}
            {{-- MODAL: DIE SPIELE-ARCADE                   --}}
            {{-- ========================================== --}}
            {{-- FEHLERBEHEBUNG: classes 'items-start pt-4 sm:pt-10' sorgen dafür, dass das Modal oben beginnt --}}
            <div x-show="showGameModal" style="display: none;" class="fixed inset-0 z-[5000] flex items-start justify-center pt-4 sm:pt-10 p-4 sm:p-6 overflow-hidden">
                <div x-show="showGameModal" x-transition.opacity.duration.500ms class="absolute inset-0 bg-gray-950/90 backdrop-blur-xl" @click="showGameModal = false; activeGame = null"></div>

                <div x-show="showGameModal" x-transition:enter="transition ease-out duration-500 delay-100" x-transition:enter-start="opacity-0 scale-95 translate-y-8" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-8" class="relative w-full max-w-7xl bg-gray-900 border border-gray-800 rounded-[2.5rem] shadow-[0_0_80px_rgba(0,0,0,0.8)] overflow-hidden flex flex-col max-h-[90vh]">

                    {{-- MODAL HEADER --}}
                    <div class="px-6 sm:px-8 py-4 sm:py-6 border-b border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4 sm:gap-6 bg-gray-950/50 shrink-0">
                        <div>
                            {{-- Dynamischer Titel je nach Ansicht --}}
                            <h3 class="text-2xl sm:text-3xl font-serif font-bold text-white tracking-tight" x-text="activeGame ? 'Kristall-Kollaps' : 'Die Funken-Schmiede'"></h3>
                            <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mt-1" x-text="activeGame ? 'Kombiniere die Fragmente' : 'Wähle dein Schicksal und sammle Schätze'"></p>
                        </div>

                        <div class="flex items-center gap-4 sm:gap-6">
                            {{-- Zurück-Button im Spiel --}}
                            <button x-show="activeGame" @click="activeGame = null" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-700 transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg> Zurück
                            </button>

                            <div class="flex items-center gap-3 bg-gray-900 border border-gray-800 px-5 py-2.5 rounded-2xl shadow-inner">
                                <svg class="w-5 h-5 text-blue-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-gray-500 font-black uppercase tracking-widest leading-none">Seelen-Energie</span>
                                    <span class="text-white font-bold leading-none mt-1">5 / 5</span>
                                </div>
                            </div>
                            <button @click="showGameModal = false; activeGame = null" class="p-3 rounded-full bg-gray-800 text-gray-400 hover:text-white hover:bg-red-500 transition-colors">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </div>

                    {{-- MODAL CONTENT AREA --}}
                    <div class="p-6 sm:p-8 overflow-y-auto no-scrollbar flex-1 relative">

                        {{-- ANSICHT 1: SPIELE-AUSWAHL MENÜ --}}
                        <div x-show="!activeGame" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-x-10" x-transition:enter-end="opacity-100 translate-x-0" class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
                            {{-- SPIEL 1: KRISTALL-KOLLAPS --}}
                            <div @click="activeGame = 'kristall'" class="group relative bg-gray-950 rounded-3xl border border-gray-800 p-6 hover:border-emerald-500/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(16,185,129,0.15)] flex flex-col cursor-pointer">
                                <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-3xl pointer-events-none"></div>
                                <div class="w-16 h-16 rounded-2xl bg-gray-900 border border-gray-800 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform shadow-inner"><span class="text-3xl">💎</span></div>
                                <h4 class="text-2xl font-serif font-bold text-white mb-2">Kristall-Kollaps</h4>
                                <p class="text-gray-400 text-sm mb-6 flex-1">Kombiniere leuchtende Fragmente in Kettenreaktionen. Ein rasantes Match-3 Erlebnis für Taktiker.</p>
                                <button class="w-full py-4 bg-gray-900 text-emerald-500 border border-emerald-500/30 rounded-xl font-black text-[10px] uppercase tracking-widest group-hover:bg-emerald-500 group-hover:text-white transition-all">Spielen (1 Energie)</button>
                            </div>
                            {{-- Platzhalter für Spiel 2 & 3 ... --}}
                            <div class="group relative bg-gray-950 rounded-3xl border border-gray-800 p-6 hover:border-primary/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(197,160,89,0.15)] flex flex-col cursor-pointer opacity-50 grayscale">
                                <div class="w-16 h-16 rounded-2xl bg-gray-900 border border-gray-800 flex items-center justify-center mb-6 shadow-inner"><span class="text-3xl">🏺</span></div>
                                <h4 class="text-2xl font-serif font-bold text-white mb-2">Seelen-Schmiede</h4>
                                <p class="text-gray-400 text-sm mb-6 flex-1">Verschmelze Elemente zu epischen Artefakten. (Bald verfügbar)</p>
                            </div>
                            <div class="group relative bg-gray-950 rounded-3xl border border-gray-800 p-6 hover:border-purple-500/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(168,85,247,0.15)] flex flex-col cursor-pointer opacity-50 grayscale">
                                <div class="w-16 h-16 rounded-2xl bg-gray-900 border border-gray-800 flex items-center justify-center mb-6 shadow-inner"><span class="text-3xl">🚀</span></div>
                                <h4 class="text-2xl font-serif font-bold text-white mb-2">Sternen-Drift</h4>
                                <p class="text-gray-400 text-sm mb-6 flex-1">Weiche Asteroiden aus und sammle Schweife. (Bald verfügbar)</p>
                            </div>
                        </div>

                        {{-- ANSICHT 2: DAS KRISTALL-KOLLAPS SPIEL --}}
                        <div x-show="activeGame === 'kristall'" x-data="kristallKollapsGame()" x-init="init()" x-transition:enter="transition ease-out duration-300 delay-200" x-transition:enter-start="opacity-0 translate-x-10" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 p-6 sm:p-8 flex flex-col items-center md:flex-row gap-8 md:gap-12 overflow-y-auto">

                            {{-- Sidebar: Stats & Info --}}
                            <div class="w-full md:w-1/3 flex flex-col gap-6 shrink-0">
                                <div class="bg-gray-950 border border-gray-800 rounded-3xl p-6 flex flex-col items-center gap-4 shadow-inner">
                                    <div class="text-center">
                                        <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Punkte</p>
                                        <h4 class="text-5xl font-serif font-bold text-emerald-400 animate-pulse" x-text="score"></h4>
                                    </div>
                                    <div class="w-full h-px bg-gray-800"></div>
                                    <div class="text-center">
                                        <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-1">Züge übrig</p>
                                        <h4 class="text-4xl font-serif font-bold text-white" :class="{'text-red-500': moves <= 5}" x-text="moves"></h4>
                                    </div>
                                </div>
                                <div class="bg-blue-500/10 border border-blue-500/30 p-4 rounded-2xl text-blue-300 text-xs leading-relaxed flex gap-3 items-start">
                                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <p>Tippe auf einen Kristall und dann auf einen benachbarten, um sie zu tauschen. Bilde Reihen aus 3 oder mehr gleichen Kristallen.</p>
                                </div>
                                <div x-show="!active && moves <= 0" class="bg-red-500/10 border border-red-500/30 p-4 rounded-2xl text-red-300 text-center font-bold text-xl animate-bounce">
                                    Spiel vorbei!
                                </div>
                            </div>

                            {{-- Das Spielfeld --}}
                            <div class="w-full md:w-2/3 max-w-xl aspect-square shrink-0 relative">
                                {{-- Lade-Overlay während Animationen --}}
                                <div x-show="isProcessing" class="absolute inset-0 bg-gray-950/50 z-30 rounded-2xl backdrop-blur-sm flex items-center justify-center transition-opacity duration-200">
                                    <svg class="w-10 h-10 text-primary animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </div>

                                <div class="game-grid w-full h-full shadow-[0_0_50px_rgba(0,0,0,0.5)]">
                                    <template x-for="(row, r) in board" :key="r">
                                        <template x-for="(cell, c) in row" :key="cell.id">
                                            <div class="crystal"
                                                 :class="[
                                                    'c-type-' + cell.type,
                                                    selected && selected.r === r && selected.c === c ? 'selected' : '',
                                                    cell.type === 0 ? 'exploded' : ''
                                                 ]"
                                                 @click="handleClick(r, c)">
                                            </div>
                                        </template>
                                    </template>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    @endif
</div>
