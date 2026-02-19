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
                            <p class="text-xs text-gray-500">Dein Autopilot für deine Tagesroutine.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            @livewire('shop.funki.day-routine')

            {{-- Rechts: Motivation & Status --}}
            <div class="bg-gradient-to-br from-indigo-50 to-white rounded-[2.5rem] p-10 border border-indigo-100 flex flex-col justify-center items-center text-center shadow-sm relative overflow-hidden">
                {{-- Deko Hintergrund --}}
                <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-100/50 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>

                <img src="{{ asset('images/projekt/funki/funki_yoga.png') }}"
                     class="w-48 h-48 object-contain mb-8 drop-shadow-xl relative z-10 hover:scale-105 transition-transform duration-500"
                     alt="Funki Yoga">

                <h3 class="text-2xl font-serif font-bold text-indigo-900 relative z-10">Dein Rhythmus, dein Erfolg.</h3>
                <p class="text-indigo-600/80 mt-4 max-w-sm text-sm font-medium leading-relaxed relative z-10">
                    "Ich achte darauf, dass du Pausen machst. Wer 24/7 arbeitet, brennt aus. Wer clever und nach Plan arbeitet, gewinnt langfristig."
                </p>
            </div>

        </div>

    @endsection

</x-layouts.backend_layout>
