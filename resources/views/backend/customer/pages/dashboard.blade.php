<x-layouts.backend_layout guard="customer">

    @section('content')
        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-10">

            {{-- 1. PREMIUM GREETING CARD MIT FUNKI --}}
            <div class="relative bg-white rounded-[2.5rem] p-8 md:p-12 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 overflow-hidden flex flex-col md:flex-row items-center gap-8 md:gap-12 animate-fade-in-down">

                {{-- Deko-Hintergrund für einen leichten Premium-Glow --}}
                <div class="absolute -top-24 -right-24 w-96 h-96 bg-primary/10 rounded-full blur-[80px] pointer-events-none"></div>

                {{-- Funki Avatar --}}
                <div class="relative shrink-0 z-10 group flex justify-center">
                    {{-- Sanfter Glow direkt hinter Funki --}}
                    <div class="absolute inset-0 bg-primary/20 rounded-full blur-2xl group-hover:bg-primary/30 transition-colors duration-500"></div>
                    <img src="{{ asset('images/projekt/funki/funki.png') }}"
                         alt="Funki"
                         class="relative w-32 md:w-48 h-auto object-contain drop-shadow-2xl transform group-hover:scale-105 transition-transform duration-500 origin-bottom">
                </div>

                {{-- Sprechblase --}}
                <div class="relative z-10 flex-1 w-full">
                    <div class="relative bg-gray-50 rounded-3xl p-6 md:p-8 border border-gray-200 shadow-sm">

                        {{-- Der Pfeil der Sprechblase (Responsive) --}}
                        {{-- Auf Mobile zeigt er nach Oben (Top), auf Desktop nach Links (Left) --}}
                        <div class="absolute w-6 h-6 bg-gray-50 border-gray-200 transform rotate-45
                                    border-l border-t -top-3 left-1/2 -translate-x-1/2
                                    md:border-t-0 md:border-b md:-left-3 md:top-12 md:-translate-y-1/2 md:translate-x-0">
                        </div>

                        <div class="relative z-20 text-center md:text-left">
                            <h1 class="text-2xl md:text-3xl font-serif font-bold text-gray-900 mb-3 leading-tight">
                                Halli Hallo, {{ auth()->user()->first_name }}! ✨
                            </h1>
                            <p class="text-gray-600 text-base md:text-lg leading-relaxed">
                                Schön, dass du da bist! Ich wünsche dir einen wunderschönen Tag voller glänzender Momente.
                            </p>

                            {{-- Kleine Überleitung zu den Bestellungen --}}
                            <div class="mt-5 inline-flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-100 shadow-sm text-xs font-bold text-primary uppercase tracking-widest">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                Deine Schätze & Bestellungen
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. BESTELL-ÜBERSICHT (Livewire) --}}
            <div class="relative z-10">
                @livewire('customer.orders')
            </div>

        </div>
    @endsection

</x-layouts.backend_layout>
