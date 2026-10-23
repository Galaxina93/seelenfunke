<div class="w-full flex items-center justify-between gap-3 lg:gap-6 h-full min-w-0">

    {{-- LOGO (Desktop + Mobile) --}}
    <div class="shrink-0 flex items-center h-full mr-3 lg:mr-6">
        <a href="{{ route('customer.dashboard') }}" class="flex items-center hover:opacity-90 transition-opacity py-1">
            <img src="{{ URL::to('/shop/projekt/logo/mein-seelenfunke-logo.svg') }}" class="h-12 sm:h-14 md:h-16 lg:h-20 max-h-[76px] w-auto object-contain drop-shadow-[0_0_12px_rgba(197,160,89,0.35)]" alt="Logo">
        </a>
    </div>

    {{-- DESKTOP TOP NAVIGATION --}}
    <nav class="hidden lg:flex items-center gap-1.5 xl:gap-2 shrink-0">
        <a href="{{ route('customer.dashboard') }}"
           class="flex items-center gap-2 px-3.5 py-2.5 rounded-2xl text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.dashboard') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>

        <a href="{{ route('customer.orders') }}"
           class="flex items-center gap-2 px-3.5 py-2.5 rounded-2xl text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.orders') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            Bestellungen
        </a>

        <a href="{{ route('customer.invoices') }}"
           class="flex items-center gap-2 px-3.5 py-2.5 rounded-2xl text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.invoices') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Rechnungen
        </a>

        <a href="{{ route('customer.support') }}" @click="hasUnreadSupport = false"
           class="flex items-center gap-2 px-3.5 py-2.5 rounded-2xl text-xs font-bold tracking-wide transition-all relative {{ request()->routeIs('customer.support') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <div class="relative">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span x-show="hasUnreadSupport" style="display: none;" class="absolute -top-1 -right-1 flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.8)]"></span>
                </span>
            </div>
            Hilfe & Support
        </a>

        {{-- SPIELE DROPDOWN MIT UNTERSEITEN (Mit Hover-Brücke & Grace-Timer) --}}
        <div x-data="{
                open: false,
                timer: null,
                show() {
                    clearTimeout(this.timer);
                    this.open = true;
                },
                hide() {
                    this.timer = setTimeout(() => { this.open = false; }, 250);
                }
             }"
             @mouseenter="show()"
             @mouseleave="hide()"
             @click.outside="open = false"
             class="relative">
            <button @click="open = !open"
                    type="button"
                    class="flex items-center gap-1.5 xl:gap-2 px-3.5 py-2.5 rounded-2xl text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.games*') || request()->routeIs('customer.gamification*') || request()->routeIs('customer.ranking*') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
                <span>🎮</span>
                Spiele
                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {{-- Dropdown Container mit nahtloser Hover-Brücke --}}
            <div x-cloak x-show="open"
                 @mouseenter="show()"
                 @mouseleave="hide()"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                 class="absolute top-full left-0 pt-2 w-64 z-50">
                <div class="bg-white dark:bg-gray-900 backdrop-blur-xl border border-gray-200 dark:border-gray-800 rounded-2xl p-2 shadow-2xl">
                    <a href="{{ route('customer.games') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors {{ request()->routeIs('customer.games') ? 'bg-primary/15 text-primary font-bold' : 'text-gray-700 dark:text-gray-300' }}">
                        <span class="text-lg">🧩</span>
                        <div class="text-left">
                            <div class="text-xs font-bold text-gray-900 dark:text-white">Manufaktur Spiele</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">Minispiele & Funkenjagd</div>
                        </div>
                    </a>

                    <a href="{{ route('customer.gamification.profile') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors {{ request()->routeIs('customer.gamification.profile') ? 'bg-primary/15 text-primary font-bold' : 'text-gray-700 dark:text-gray-300' }}">
                        <span class="text-lg">✨</span>
                        <div class="text-left">
                            <div class="text-xs font-bold text-gray-900 dark:text-white">Funki-Evolution</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">3D-Gefährte, Level & Titel</div>
                        </div>
                    </a>

                    <a href="{{ route('customer.ranking') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors {{ request()->routeIs('customer.ranking') ? 'bg-primary/15 text-primary font-bold' : 'text-gray-700 dark:text-gray-300' }}">
                        <span class="text-lg">🏆</span>
                        <div class="text-left">
                            <div class="text-xs font-bold text-gray-900 dark:text-white">Halle der Legenden</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">Globale Rangliste</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    {{-- GUTHABEN & ENERGIE (Wird nur im Shop/Spiele im Header angezeigt) --}}
    @if(!request()->routeIs('customer.dashboard') && $hasOptedIn)
        <div class="hidden md:flex items-center gap-3 sm:gap-6 shrink-0 h-full">
            <div class="text-left flex flex-col justify-center">
                <div class="hidden sm:block text-[9px] sm:text-[10px] text-gray-500 font-black uppercase tracking-[0.2em] mb-0.5 leading-none">Guthaben</div>
                <div class="text-sm sm:text-2xl font-serif font-bold text-primary tracking-tight leading-none">{{ $balance }} <span class="hidden sm:inline text-xs font-sans">Funken</span><span class="sm:hidden text-[9px] font-sans ml-0.5">F.</span></div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-2 py-1 sm:px-3 sm:py-1.5 rounded-lg sm:rounded-xl shadow-sm dark:shadow-inner relative group cursor-help shrink-0">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                <div class="flex flex-col">
                    <span class="hidden sm:block text-[8px] text-gray-500 font-black uppercase tracking-widest leading-none">Energie</span>
                    <span class="text-gray-900 dark:text-white font-bold leading-none sm:mt-0.5 text-[10px] sm:text-xs">{{ $energyBalance }} / {{ $maxEnergy }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- RECHTER BEREICH: PROFIL-DOCK --}}
    <div class="flex-1 flex items-center justify-end h-full min-w-0">
        <div class="flex items-center justify-end h-full overflow-x-auto no-scrollbar scroll-smooth">
            @livewire('auth.auth-profile-dropdown')
        </div>
    </div>
</div>
