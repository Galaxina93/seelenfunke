<x-layouts.backend_layout guard="admin">

    @section('content')

        {{-- HEADER & TABS --}}
        <div class="bg-white border-b border-gray-100 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row justify-between items-center py-4 gap-4">
                    {{-- Logo & Titel --}}
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-12 h-12 object-contain" alt="Funki">
                            <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 border-2 border-white rounded-full animate-pulse"></div>
                        </div>
                        <div>
                            <h1 class="text-xl font-serif font-bold text-gray-900">Funkis Zentrale</h1>
                            <p class="text-xs text-gray-500">Dein Autopilot für den Tag.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="h-[calc(100vh-200px)]">
            @livewire('shop.funki.funki-kalender')
        </div>

    @endsection

</x-layouts.backend_layout>
