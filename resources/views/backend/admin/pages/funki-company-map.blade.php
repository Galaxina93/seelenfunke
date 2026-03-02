<x-layouts.backend_layout guard="admin">
    @section('content')
        {{-- HEADER & TABS --}}
        <div class="bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-30 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-sparkles class="w-40 h-40 text-primary drop-shadow-[0_0_20px_rgba(197,160,89,1)]" />
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="flex flex-col md:flex-row justify-between items-center py-5 gap-4">
                    {{-- Logo & Titel --}}
                    <div class="flex items-center gap-5">
                        <div class="relative group">
                            <div class="absolute inset-0 bg-primary/20 rounded-2xl blur-xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <div class="h-14 w-14 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center p-2 shadow-inner relative z-10">
                                <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-500" alt="Funki">
                                <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-gray-900 rounded-full shadow-[0_0_10px_#10b981] animate-pulse"></div>
                            </div>
                        </div>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-serif font-bold text-white tracking-tight">Funkis Zentrale</h1>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mt-1">Dein Autopilot für deine Firmenarchitektur</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="h-[calc(100vh-140px)] min-h-[600px] mt-4 sm:mt-6 px-2 sm:px-6 lg:px-8 max-w-[1800px] mx-auto animate-fade-in-up">
            @livewire('shop.funki.funki-company-map')
        </div>
    @endsection
</x-layouts.backend_layout>
