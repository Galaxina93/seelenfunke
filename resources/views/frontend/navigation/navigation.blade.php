<div class="mx-auto pt-6">
    {{--
        x-data: Hier lebt der Status des Menüs.
        @click.away: Schließt das Menü, wenn man daneben klickt.
    --}}
    <nav x-data="{ open: false }"
         @click.away="open = false"
         class="bg-primary-dark shadow-lg fixed w-full top-0 z-50 transition-all duration-300"
         id="navbar">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
            <div class="flex justify-between items-center h-16">

                {{-- Logo --}}
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 group">
                        <img src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}"
                             alt="Mein Seelenfunke Logo"
                             class="h-24 transition-transform duration-300 group-hover:scale-105">
                    </a>
                </div>

                {{-- Desktop Navigation --}}
                <div class="hidden md:block">
                    <div class="ml-10 flex items-center space-x-8">
                        <a href="{{ route('home') }}"
                           class="{{ Request::routeIs('home') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Startseite
                        </a>

                        <a href="{{ route('product.detail') }}"
                           class="{{ Request::routeIs('product.detail') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Seelen-Kristall
                        </a>

                        <a href="{{ route('manufacture') }}"
                           class="{{ Request::routeIs('manufacture') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Die Manufaktur
                        </a>

                        <a href="{{ route('shop') }}"
                           class="{{ Request::routeIs('shop') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Shop
                        </a>

                        <a href="{{ route('blog') }}"
                           class="{{ Request::routeIs('blog') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Blog
                        </a>

                        <a href="{{ route('contact') }}"
                           class="{{ Request::routeIs('contact') ? 'text-primary' : 'text-white' }} hover:text-primary transition-colors font-medium">
                            Kontakt
                        </a>

                        {{-- Button Angebot --}}
                        <a href="{{ route('calculator') }}" class="px-4 py-2 border border-primary text-primary hover:bg-primary hover:text-white rounded transition-all duration-300 font-semibold shadow-md hover:shadow-primary/30">
                            Angebot kalkulieren
                        </a>

                        {{-- NEU: Login Dropdown (Ersetzt das alte Icon) --}}
                        <livewire:global.auth.login-dropdown />

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

        {{-- Mobile Menu --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             x-cloak
             class="md:hidden bg-primary-dark border-t border-gray-700 shadow-xl absolute w-full left-0">

            <div class="px-4 pt-4 pb-6 space-y-2">
                <a href="{{ route('home') }}"
                   class="block px-3 py-2 text-lg {{ Request::routeIs('home') ? 'text-primary font-bold' : 'text-white hover:text-primary' }}">
                    Startseite
                </a>

                <a href="{{ route('product.detail') }}"
                   class="block px-3 py-2 text-lg {{ Request::routeIs('product.detail') ? 'text-primary font-bold' : 'text-white hover:text-primary' }}">
                    Der Seelen-Kristall
                </a>

                <a href="{{ route('manufacture') }}"
                   class="block px-3 py-2 text-lg {{ Request::routeIs('manufacture') ? 'text-primary font-bold' : 'text-white hover:text-primary' }}">
                    Die Manufaktur
                </a>

                <a href="{{ route('shop') }}"
                   class="block px-3 py-2 text-lg {{ Request::routeIs('shop') ? 'text-primary font-bold' : 'text-white hover:text-primary' }}">
                    Shop
                </a>

                <a href="{{ route('blog') }}"
                   class="block px-3 py-2 text-lg {{ Request::routeIs('blog') ? 'text-primary font-bold' : 'text-white hover:text-primary' }}">
                    Blog
                </a>

                <a href="{{ route('contact') }}"
                   class="block px-3 py-2 text-lg {{ Request::routeIs('contact') ? 'text-primary font-bold' : 'text-white hover:text-primary' }}">
                    Kontakt
                </a>

                <a href="{{ route('calculator') }}" class="block mt-4 px-3 py-3 text-center bg-primary text-white font-bold rounded-md hover:bg-primary-light">
                    Angebot kalkulieren
                </a>

                <a href="{{ route('login') }}" class="block mt-2 px-3 py-2 text-center text-white border border-gray-600 rounded-md hover:text-primary hover:border-primary transition-colors">
                    Login / Kundenbereich
                </a>
            </div>
        </div>
    </nav>
</div>
