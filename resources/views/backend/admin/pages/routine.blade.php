<x-layouts.backend_layout guard="admin">

    @section('content')
        <div class="space-y-8 pb-20 animate-fade-in-up">

            {{-- CONTENT GRID --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

                {{-- Linke Seite: Die Routine Component --}}
                <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden min-h-[600px]">
                    @livewire('shop.management.management-routine')
                </div>

                {{-- Rechte Seite: Motivation & Status --}}
                <div class="bg-gradient-to-br from-gray-900 via-gray-950 to-black rounded-[2.5rem] p-10 border border-gray-800 flex flex-col justify-center items-center text-center shadow-2xl relative overflow-hidden group min-h-[600px]">

                    {{-- Kosmische Deko --}}
                    <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/10 rounded-full blur-[80px] opacity-40 group-hover:opacity-60 transition-opacity duration-700 pointer-events-none"></div>
                    <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-blue-500/10 rounded-full blur-[80px] opacity-20 pointer-events-none"></div>

                    <div class="relative z-10">
                        <div class="relative inline-block mb-10">
                            <div class="absolute inset-0 bg-primary/20 rounded-full blur-3xl animate-pulse"></div>
                            <img src="{{ asset('images/projekt/funki/funki_yoga.webp') }}"
                                 class="w-56 h-56 object-contain relative z-10 drop-shadow-[0_20px_30px_rgba(0,0,0,0.8)] transform group-hover:scale-105 group-hover:-rotate-2 transition-transform duration-700"
                                 alt="Funki Yoga">
                        </div>

                        <h3 class="text-3xl font-serif font-bold text-white tracking-tight mb-4 drop-shadow-md">
                            Dein Rhythmus, <span class="text-primary italic">dein Erfolg.</span>
                        </h3>

                        <div class="w-16 h-1 bg-gradient-to-r from-transparent via-primary/50 to-transparent mx-auto mb-6"></div>

                        <p class="text-gray-400 max-w-sm text-sm font-medium leading-relaxed italic">
                            "Ich achte darauf, dass du Pausen machst. Wer 24/7 arbeitet, brennt aus. Wer clever und nach Plan arbeitet, gewinnt langfristig."
                        </p>

                        <div class="mt-10 pt-8 border-t border-gray-800/50 flex justify-center gap-4">
                            <div class="flex flex-col items-center">
                                <span class="text-[9px] font-black text-gray-600 uppercase tracking-widest mb-1">Energielevel</span>
                                <div class="w-32 h-1.5 bg-gray-900 rounded-full overflow-hidden border border-gray-800">
                                    <div class="h-full bg-primary w-[85%] shadow-[0_0_10px_rgba(197,160,89,0.5)]"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    @endsection

</x-layouts.backend_layout>
