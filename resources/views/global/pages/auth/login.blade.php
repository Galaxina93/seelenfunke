<x-layouts.guest>
    <section class="dark:bg-gray-900 min-h-screen flex flex-col items-center justify-center px-4 py-8">

        {{-- Logo --}}
        <a href="/" class="flex justify-center mb-8 hover:opacity-90 transition-opacity">
            <img class="h-56 object-contain" src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="mein-seelenfunke">
        </a>

        {{-- Login Component --}}
        {{--
            Wir binden die Komponente nur einmal ein.
            Die Komponente selbst bringt das Design (weiße Box, Schatten) mit.
            Der Parameter 'guard' ist hier nicht mehr zwingend nötig, da der Controller
            das dynamisch macht, aber wir können 'customer' als Default lassen.
        --}}
        <div class="w-full max-w-md">
            @livewire('global.auth.login')
        </div>

    </section>
</x-layouts.guest>
