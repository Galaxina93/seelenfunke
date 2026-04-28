<div class="w-full relative min-h-[100dvh] flex flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-slate-50 to-slate-200" id="maintenance-wrapper">

    {{-- UI Container --}}
    <div class="relative z-10 text-center w-full max-w-2xl mx-auto bg-white/60 backdrop-blur-xl p-8 md:p-12 rounded-[3rem] shadow-[0_20px_60px_rgba(0,0,0,0.08)] border border-white my-12 flex flex-col items-center">

        <div class="mb-6 flex justify-center">
            <img src="{{ asset('shop/projekt/logo/mein-seelenfunke-logo.svg') }}" alt="Mein Seelenfunke" class="h-20 w-auto drop-shadow-xl">
        </div>

        <h1 class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-4 tracking-tight">
            Kreative Pause.
        </h1>

        <p class="text-gray-600 mb-10 text-base md:text-lg leading-relaxed font-medium max-w-lg mx-auto">
            Wir optimieren gerade den Shop im Hintergrund, um dir bald ein noch schöneres Erlebnis zu bieten. Wir sind bald wieder da!
        </p>

        {{-- Blog Call-to-Action Card --}}
        <div class="w-full bg-slate-900 text-white p-8 rounded-3xl shadow-2xl border border-slate-700 relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-tr from-[#C5A059]/30 to-purple-500/10 opacity-50 group-hover:opacity-100 transition-opacity duration-500"></div>
            
            <div class="relative z-10 flex flex-col items-center">
                <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center mb-4 border border-white/20">
                    <x-heroicon-s-book-open class="w-6 h-6 text-[#C5A059]" />
                </div>
                
                <h2 class="text-xl font-bold font-serif mb-2 text-white">In der Zwischenzeit...</h2>
                <p class="text-gray-400 text-sm mb-6 max-w-sm text-center">
                    Entdecke unseren Seelenfunke Blog! Lass dich von unseren neuesten Geschichten, Tipps und spirituellen Impulsen inspirieren.
                </p>
                
                <a href="{{ route('marketing/marketing/blog') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#C5A059] text-white font-black uppercase tracking-widest text-[11px] rounded-xl hover:bg-[#b08d4b] hover:scale-105 transition-all shadow-[0_0_20px_rgba(197,160,89,0.4)]">
                    Zum Blog
                    <x-heroicon-m-arrow-right class="w-4 h-4" />
                </a>
            </div>
        </div>

        <div class="inline-flex items-center gap-3 px-5 py-2.5 bg-white/90 border border-amber-200 rounded-full text-[10px] text-amber-700 uppercase tracking-widest font-black mt-8 shadow-sm">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
            </span>
            Wartungsmodus aktiv
        </div>
    </div>
</div>
