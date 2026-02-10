<div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">

    {{-- TRIGGER BUTTON --}}
    <button @click="open = !open" type="button" class="-m-1.5 flex items-center p-1.5 text-white hover:text-primary transition-colors duration-300 focus:outline-none" id="shop-menu-button" aria-expanded="false" aria-haspopup="true">
        <span class="sr-only">Benutzermenü öffnen</span>

        @if($user)
            {{-- Eingeloggt: Profilbild oder Initiale --}}
            <div class="h-8 w-8 rounded-full overflow-hidden border-2 border-primary/50 bg-gray-100 flex items-center justify-center">
                {{-- Hinweis: Prüfen ob profile relationship existiert, analog zu deinem ProfileDropdown --}}
                @if(!empty($user->profile_photo_path))
                    <img src="{{ Storage::url($user->profile_photo_path) }}" class="h-full w-full object-cover" alt="{{ $user->firstname }}">
                @elseif(isset($user->profile) && !empty($user->profile->photo_path))
                    <img src="{{ Storage::url($user->profile->photo_path) }}" class="h-full w-full object-cover" alt="">
                @else
                    <span class="text-xs font-bold text-gray-700 leading-none">
                        {{ substr($user->firstname ?? $user->name ?? 'K', 0, 1) }}
                    </span>
                @endif
            </div>
        @else
            {{-- Ausgeloggt: Standard Icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 transform group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        @endif
    </button>

    {{-- DROPDOWN MENU --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute right-0 z-50 mt-2.5 w-64 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-none"
         role="menu"
         aria-orientation="vertical"
         aria-labelledby="shop-menu-button"
         tabindex="-1"
         style="display: none;">

        @if($user)
            {{-- STATUS: EINGELOGGT --}}
            <div class="px-4 py-3 border-b border-gray-100 mb-1">
                <p class="text-sm text-gray-900 font-bold">Hallo, {{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}!</p>
                <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
            </div>

            {{-- Dynamische Route basierend auf Guard --}}
            <a href="{{ route($guard . '.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-primary cursor-pointer" role="menuitem" tabindex="-1">
                Mein Dashboard
            </a>

            {{-- Optional: Profil Route --}}
            <a href="{{ route($guard . '.profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-primary cursor-pointer" role="menuitem" tabindex="-1">
                Profil
            </a>

            <div class="border-t border-gray-100 mt-1">
                <button wire:click="logout" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 cursor-pointer font-medium" role="menuitem" tabindex="-1">
                    Ausloggen
                </button>
            </div>

        @else
            {{-- STATUS: AUSGELOGGT --}}
            <div class="px-4 py-3 text-center">
                <p class="text-sm font-medium text-gray-900 mb-1">Willkommen!</p>
                <p class="text-xs text-gray-500 mb-3">Melde dich an, um auf dein Konto zuzugreifen.</p>

                <a href="{{ route('login') }}" class="block w-full bg-primary text-white py-2 rounded text-xs font-bold hover:bg-primary-dark transition text-center mb-2">
                    Anmelden
                </a>

                <div class="text-xs text-gray-500">
                    Neu? <a href="{{ route('register') }}" class="text-primary hover:underline font-bold">Registrieren</a>
                </div>
            </div>
        @endif

    </div>
</div>
