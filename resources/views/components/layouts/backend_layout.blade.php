<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    <script src="{{ asset('js/app.js') }}" defer></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @livewireStyles
</head>

<body class="min-h-screen bg-gray-50 dark:bg-gray-900 font-sans text-gray-900">

<div x-data="{ open: false }">

    <div x-show="open"
         class="relative z-50 lg:hidden"
         role="dialog"
         aria-modal="true"
         style="display: none;">

        <div x-show="open"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/80"
             @click="open = false"
             aria-hidden="true">
        </div>

        <div class="fixed inset-0 flex">
            <div x-show="open"
                 x-transition:enter="transition ease-in-out duration-300 transform"
                 x-transition:enter-start="-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in-out duration-300 transform"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="-translate-x-full"
                 class="relative mr-16 flex w-full max-w-xs flex-1">

                <div x-show="open"
                     x-transition:enter="ease-in-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in-out duration-300"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute left-full top-0 flex w-16 justify-center pt-5">
                    <button type="button" class="-m-2.5 p-2.5" @click="open = false">
                        <span class="sr-only">Menü schließen</span>
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-black px-6 pb-4 ring-1 ring-white/10">
                    <div class="flex h-16 shrink-0 items-center">
                        <img class="h-24 w-auto" src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein-Seelenfunke">
                    </div>

                    @livewire($guard . '.' . $guard . '-navigation')
                </div>
            </div>
        </div>
    </div>

    <div class="hidden lg:fixed lg:inset-y-0 lg:z-50 lg:flex lg:w-72 lg:flex-col">
        <div class="flex grow flex-col gap-y-5 overflow-y-auto bg-black px-6 pb-4">
            <div class="flex h-16 shrink-0 items-center">
                <img class="h-24 w-auto" src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein-Seelenfunke">
            </div>

            @livewire($guard . '.' . $guard . '-navigation')
        </div>
    </div>

    <div class="lg:pl-72">

        <div class="sticky top-0 z-40 flex h-16 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6 lg:px-8">

            <button @click="open = true" type="button" class="-m-2.5 p-2.5 text-gray-700 lg:hidden">
                <span class="sr-only">Menü öffnen</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <div class="h-6 w-px bg-gray-900/10 lg:hidden" aria-hidden="true"></div>

            <div class="flex flex-1 items-center justify-between">
                <div></div>

                <div class="flex items-center gap-x-4 lg:gap-x-6">
                    <a href="/" target="_blank" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Website ansehen</span>
                        <x-heroicon-o-globe-asia-australia class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
                    </a>

                    <button type="button" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Benachrichtigungen</span>
                        <x-heroicon-o-bell class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
                    </button>

                    <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-900/10" aria-hidden="true"></div>

                    @livewire('global.profile.profile-dropdown')
                </div>
            </div>
        </div>

        <main class="py-6">
            <x-sections.page-container>
                <x-sections.page-section>
                    @yield('content')
                </x-sections.page-section>
            </x-sections.page-container>
        </main>
    </div>

</div>

@livewireScripts
@stack('scripts')
</body>

</html>
