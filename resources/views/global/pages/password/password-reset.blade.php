<x-layouts.guest>
    <section class="min-h-screen bg-[#fdf8f6] flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">

        {{-- Subtile Hintergrund-Deko (optional, für den "Seelenfunken"-Vibe) --}}
        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-[#C5A059]/30 to-transparent"></div>
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-[#C5A059]/5 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-[#e29578]/5 rounded-full blur-3xl"></div>

        <div class="max-w-md w-full space-y-8 relative z-10">

            {{-- LOGO SEKTION --}}
            <div class="flex flex-col items-center">
                <a href="/" class="transition-transform hover:scale-105 duration-500 ease-in-out">
                    <img class="h-24 md:h-28 w-auto drop-shadow-sm"
                         src="{{ URL::to('/images/projekt/logo/mein-seelenfunke-logo.png') }}"
                         alt="Mein-Seelenfunken">
                </a>
            </div>

            {{-- LIVEWIRE COMPONENT WRAPPER --}}
            {{-- Die Card-Stylings kommen hier drumherum, damit die Livewire-Komponente sauber eingebettet ist --}}
            <div class="bg-white rounded-[2.5rem] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.07)] border border-slate-100 overflow-hidden transform transition-all">

                {{-- Goldener Akzent-Strich oben --}}
                <div class="h-2 w-full bg-[#C5A059]"></div>

                <div class="p-2 sm:p-4">
                    @livewire('global.password.password-reset', ['token' => $token])
                </div>

            </div>

            {{-- FOOTER LINKS --}}
            <div class="text-center">
                <p class="text-slate-400 text-[10px] tracking-[0.3em] uppercase">
                    &copy; {{ date('Y') }} Mein-Seelenfunken &bull; Magische Momente
                </p>
            </div>

        </div>
    </section>
</x-layouts.guest>
