<x-layouts.backend_layout>
    <x-slot:title>
        Händler & Lieferanten | {{ config('app.name') }}
    </x-slot:title>

    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <!-- Header -->
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-200 font-bold">Händler & Lieferanten ✨</h1>
                <p class="text-gray-400 mt-1">Verwalte deine Lieferketten, Kontaktpersonen und dynamische Anlaufstellen hier zentral für das Analytics-Tool.</p>
            </div>
        </div>

        <!-- Livewire Component -->
        @livewire('shop.product.product-suppliers')

    </div>
</x-layouts.backend_layout>
