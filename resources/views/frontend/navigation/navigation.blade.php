<div class="mx-auto pt-20">
    <style>
        @keyframes gold-pulse {
            0%, 100% { text-shadow: 0 0 2px rgba(251, 191, 36, 0.4); opacity: 0.95; }
            50% { text-shadow: 0 0 8px rgba(251, 191, 36, 0.8), 0 0 15px rgba(251, 191, 36, 0.3); opacity: 1; color: #fbbf24; }
        }
        .animate-gold-pulse {
            animation: gold-pulse 2.5s infinite ease-in-out;
        }
    </style>
    {{--
        x-data: Hier lebt der Status des Menüs.
        @click.away: Schließt das Menü, wenn man daneben klickt.
    --}}
    <nav x-data="{ open: false }"
         @click.away="open = false"
         class="bg-primary-dark shadow-lg fixed w-full top-0 transition-all duration-300"
         style="z-index: 99999;"
         id="navbar">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
            <div class="flex justify-between items-center h-16">

                {{-- Logo --}}
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 group">
                        <img src="{{ URL::to('/shop/projekt/logo/mein-seelenfunke-logo.svg') }}"
                             alt="Mein Seelenfunke Logo"
                             width="600" height="229"
                             class="h-24 w-auto transition-transform duration-300 group-hover:scale-105">
                    </a>
                </div>

                {{-- Desktop Navigation --}}
                <div class="hidden md:block">
                    <div class="ml-10 flex items-center space-x-8">
                        <a href="{{ route('home') }}"
                           class="{{ Request::routeIs('home') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Startseite
                        </a>

                        <a href="{{ route('manufacture') }}"
                           class="{{ Request::routeIs('manufacture') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Die Manufaktur
                        </a>

                        <a href="{{ route('shop') }}"
                           class="{{ Request::routeIs('shop') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Shop
                        </a>

                        <a href="{{ route('marketing/marketing/blog') }}"
                           class="{{ Request::routeIs('marketing/marketing/blog') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Blog
                        </a>

                        <a href="{{ route('contact') }}"
                           class="{{ Request::routeIs('contact') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Kontakt
                        </a>

                        <a href="{{ route('product.show', 'geschenkgutschein') }}"
                           class="{{ request()->is('produkt/geschenkgutschein') ? 'text-amber-400 font-bold' : 'text-amber-400/90' }} hover:text-amber-300 transition-all font-bold tracking-wider relative group whitespace-nowrap animate-gold-pulse">
                            Gutscheine
                            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-amber-400 transition-all duration-300 group-hover:w-full"></span>
                        </a>

                        {{-- Button Angebot --}}
                        <a href="{{ route('calculator') }}" class="px-4 py-2 border border-primary text-primary hover:bg-primary hover:text-gray-900 rounded transition-all duration-300 font-semibold shadow-md hover:shadow-primary/30">
                            Angebot kalkulieren
                        </a>

                        {{-- NEU: Login Dropdown (Ersetzt das alte Icon) --}}
                        <livewire:auth.auth-login-dropdown />

                        {{-- Warenkorb Icon (Desktop) --}}
                        <livewire:shop.cart.cart-icon />

                    </div>
                </div>

                {{-- Mobile Hamburger Button & cart --}}
                <div class="md:hidden flex items-center gap-4">

                    {{-- Warenkorb Icon (Mobil) - Identisch zur Desktop Komponente für Badge-Sync --}}
                    <livewire:shop.cart.cart-icon />

                    <button @click="open = !open"
                            type="button"
                            aria-label="Menü öffnen"
                            class="text-white hover:text-primary focus:outline-none transition-colors">

                        {{-- Icon: Hamburger (angezeigt wenn geschlossen) --}}
                        <svg x-show="!open" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>

                        {{-- Icon: X (angezeigt wenn offen) --}}
                        <svg x-show="open" x-cloak class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile Menu (Modern White Design) --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             x-cloak
             class="md:hidden bg-white text-gray-900 border-t border-gray-200/80 shadow-[0_20px_50px_rgba(0,0,0,0.14)] rounded-b-2xl absolute w-full left-0 overflow-hidden">

            <div class="px-4 pt-4 pb-6 space-y-1.5">
                <a href="{{ route('home') }}"
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-base transition-colors {{ Request::routeIs('home') ? 'bg-amber-50/80 text-primary font-bold border border-primary/20' : 'text-gray-800 font-medium hover:bg-gray-50 hover:text-primary' }}">
                    <span>Startseite</span>
                    @if(Request::routeIs('home')) <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> @endif
                </a>

                <a href="{{ route('manufacture') }}"
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-base transition-colors {{ Request::routeIs('manufacture') ? 'bg-amber-50/80 text-primary font-bold border border-primary/20' : 'text-gray-800 font-medium hover:bg-gray-50 hover:text-primary' }}">
                    <span>Manufaktur</span>
                    @if(Request::routeIs('manufacture')) <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> @endif
                </a>

                <a href="{{ route('shop') }}"
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-base transition-colors {{ Request::routeIs('shop') ? 'bg-amber-50/80 text-primary font-bold border border-primary/20' : 'text-gray-800 font-medium hover:bg-gray-50 hover:text-primary' }}">
                    <span>Shop</span>
                    @if(Request::routeIs('shop')) <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> @endif
                </a>

                <a href="{{ route('marketing/marketing/blog') }}"
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-base transition-colors {{ Request::routeIs('marketing/marketing/blog') ? 'bg-amber-50/80 text-primary font-bold border border-primary/20' : 'text-gray-800 font-medium hover:bg-gray-50 hover:text-primary' }}">
                    <span>Blog</span>
                    @if(Request::routeIs('marketing/marketing/blog')) <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> @endif
                </a>

                <a href="{{ route('contact') }}"
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-base transition-colors {{ Request::routeIs('contact') ? 'bg-amber-50/80 text-primary font-bold border border-primary/20' : 'text-gray-800 font-medium hover:bg-gray-50 hover:text-primary' }}">
                    <span>Kontakt</span>
                    @if(Request::routeIs('contact')) <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> @endif
                </a>

                <a href="{{ route('product.show', 'geschenkgutschein') }}"
                   class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-base font-bold transition-colors {{ request()->is('produkt/geschenkgutschein') ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'text-amber-600 hover:bg-amber-50/60 hover:text-amber-700' }}">
                    <span class="flex items-center gap-2">
                        <i class="bi bi-gift-fill text-primary"></i>
                        <span>Gutscheine kaufen</span>
                    </span>
                </a>

                <div class="pt-2">
                    <a href="{{ route('calculator') }}" class="block px-4 py-3 text-center bg-primary text-gray-900 font-bold rounded-xl shadow-sm hover:bg-primary-dark hover:text-white transition-all">
                        Angebot kalkulieren
                    </a>
                </div>

                <div class="mt-4 border-t border-gray-100 pt-3 pb-1">
                    <livewire:auth.auth-login-dropdown :isMobile="true" />
                </div>
            </div>
        </div>
    </nav>
</div>
