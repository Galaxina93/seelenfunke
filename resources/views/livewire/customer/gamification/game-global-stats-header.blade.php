<div class="w-full flex items-center justify-between gap-3 lg:gap-6 h-full min-w-0">

    {{-- LOGO (Desktop + Mobile) --}}
    <div class="shrink-0 flex items-center h-full mr-2 sm:mr-3 lg:mr-4 xl:mr-6">
        <a href="{{ route('customer.dashboard') }}" class="flex items-center hover:opacity-90 transition-opacity py-1">
            <img src="{{ URL::to('/shop/projekt/logo/mein-seelenfunke-logo.svg') }}" class="h-12 sm:h-14 md:h-16 lg:h-14 xl:h-16 2xl:h-20 max-h-[58px] lg:max-h-[58px] xl:max-h-[76px] w-auto object-contain drop-shadow-[0_0_12px_rgba(197,160,89,0.35)]" alt="Logo">
        </a>
    </div>

    {{-- DESKTOP TOP NAVIGATION --}}
    <nav class="hidden lg:flex items-center gap-1 xl:gap-2 shrink-0">
        <a href="{{ route('customer.dashboard') }}"
           class="flex items-center gap-1.5 xl:gap-2 px-2.5 xl:px-3.5 py-2 xl:py-2.5 rounded-2xl text-[11px] xl:text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.dashboard') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>

        <a href="{{ route('customer.orders') }}"
           class="flex items-center gap-1.5 xl:gap-2 px-2.5 xl:px-3.5 py-2 xl:py-2.5 rounded-2xl text-[11px] xl:text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.orders') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            Bestellungen
        </a>

        <a href="{{ route('customer.invoices') }}"
           class="flex items-center gap-1.5 xl:gap-2 px-2.5 xl:px-3.5 py-2 xl:py-2.5 rounded-2xl text-[11px] xl:text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.invoices') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Rechnungen
        </a>

        <a href="{{ route('customer.support') }}" @click="hasUnreadSupport = false"
           class="flex items-center gap-1.5 xl:gap-2 px-2.5 xl:px-3.5 py-2 xl:py-2.5 rounded-2xl text-[11px] xl:text-xs font-bold tracking-wide transition-all relative {{ request()->routeIs('customer.support') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <div class="relative">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span x-show="hasUnreadSupport" style="display: none;" class="absolute -top-1 -right-1 flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500 shadow-[0_0_6px_rgba(239,68,68,0.8)]"></span>
                </span>
            </div>
            Hilfe & Support
        </a>

        {{-- SPIELE NAVIGATION (Eine zentrale Ansicht unter /games: Spieler-Cockpit) --}}
        <a href="{{ route('customer.games') }}"
           class="flex items-center gap-1.5 xl:gap-2 px-2.5 xl:px-3.5 py-2 xl:py-2.5 rounded-2xl text-[11px] xl:text-xs font-bold tracking-wide transition-all {{ request()->routeIs('customer.games*') || request()->routeIs('customer.gamification*') || request()->routeIs('customer.ranking*') ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800/80' }}">
            <span>🎮</span>
            <span>Spiele</span>
        </a>
    </nav>

    {{-- RECHTER BEREICH: PROFIL-DOCK --}}
    <div class="flex-1 flex items-center justify-end h-full min-w-0">
        @livewire('auth.auth-profile-dropdown')
    </div>
</div>
