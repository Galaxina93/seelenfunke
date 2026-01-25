<div>
    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open" type="button" class="-m-1.5 flex items-center p-1.5" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
            <span class="sr-only">Open user menu</span>
            @if($user && $user->profile && $user->profile->photo_path != null)
                <img class="h-10 w-10 rounded-full" src="{{ Storage::url($user->profile->photo_path) }}" alt="">
            @else
                <img class="h-10 w-10 rounded-full" src="{{ URL::to('/images/profile.webp') }}" alt="">
            @endif
            <span class="hidden lg:flex lg:items-center">
            <span class="ml-4" aria-hidden="true">
                <span class="text-sm font-semibold leading-6 text-gray-900">
                    {{ $user->firstName ?? '' }} {{ $user->lastName ?? '' }}
                </span>
            </span>

            <svg class="ml-2 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </span>
        </button>

        <div x-show="open" @click.away="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="transform opacity-100 scale-100"
             x-transition:leave-end="transform opacity-0 scale-95"
             class="absolute right-0 z-50 mt-2.5 w-52 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">

            <div class="block px-4 py-2 text-xs text-gray-400">
                Accountverwaltung
            </div>

            @if($user)
                <a href="{{ route($guard . '.dashboard') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 cursor-pointer" role="menuitem" tabindex="-1" id="user-menu-item-0">
                    Dashboard
                </a>
                <a href="{{ route($guard . '.profile') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 cursor-pointer" role="menuitem" tabindex="-1" id="user-menu-item-0">
                    Profil
                </a>
                <a wire:click="logout" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 cursor-pointer" role="menuitem" tabindex="-1" id="user-menu-item-0">
                    Logout
                </a>
            @else
                <a href="/login" class="block px-4 py-2 text-base font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-800 cursor-pointer" role="menuitem" tabindex="-1" id="user-menu-item-1">Login</a>
            @endif

        </div>
    </div>
</div>


