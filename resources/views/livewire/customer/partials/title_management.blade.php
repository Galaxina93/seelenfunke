<div x-show="showTitlesModal" style="display: none;" class="fixed inset-0 z-[2000] flex items-start justify-center pt-10 sm:pt-16 p-4 sm:px-10">
    <div class="absolute inset-0 bg-black/98 backdrop-blur-3xl" @click="showTitlesModal = false" x-transition.opacity></div>

    <div class="relative w-full max-w-[100rem] max-h-[85vh] overflow-y-auto bg-gradient-to-b from-gray-900 to-black rounded-[4rem] shadow-[0_0_100px_rgba(0,0,0,1)] border border-gray-800 p-10 md:p-20 no-scrollbar"
         x-transition:enter="transition ease-out duration-300 delay-100"
         x-transition:enter-start="opacity-0 scale-95 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-10">

        <button @click="showTitlesModal = false" class="absolute top-10 right-10 z-[2010] p-4 bg-gray-800 border-2 border-gray-700 rounded-full text-gray-400 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-[0_0_30px_rgba(0,0,0,0.8)] hover:scale-110">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <div class="mb-16 relative z-10">
            <h3 class="text-5xl md:text-7xl font-serif font-bold text-white mb-6 tracking-tight drop-shadow-2xl">Meilensteine & Titel</h3>
            <p class="text-gray-400 text-xl md:text-2xl max-w-4xl leading-relaxed">Dein Fortschritt in der Manufaktur der Seelenfunken. Jede deiner Entscheidungen bringt dich näher zum Status eines echten Seelengottes.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-10 relative z-10">
            @foreach($titlesData as $key => $title)
                @php
                    $colors = match($title['tier']) {
                        'diamant' => 'bg-cyan-500/10 border-cyan-500/30 text-cyan-400 hover:shadow-[0_0_30px_rgba(6,182,212,0.3)]',
                        'gold'    => 'bg-amber-500/10 border-amber-500/30 text-amber-400 hover:shadow-[0_0_30px_rgba(245,158,11,0.3)]',
                        'silber'  => 'bg-gray-400/10 border-gray-400/30 text-gray-300 hover:shadow-[0_0_30px_rgba(156,163,175,0.3)]',
                        default   => 'bg-gray-900 border-gray-800 text-gray-500 hover:border-gray-700',
                    };
                    $progressColor = match($title['tier']) {
                        'diamant' => 'bg-cyan-400 shadow-[0_0_15px_rgba(6,182,212,0.8)]',
                        'gold'    => 'bg-amber-400 shadow-[0_0_15px_rgba(245,158,11,0.8)]',
                        'silber'  => 'bg-gray-300 shadow-[0_0_15px_rgba(156,163,175,0.8)]',
                        default   => 'bg-gray-700',
                    };
                @endphp
                <div class="rounded-[3rem] border-2 p-10 transition-all duration-700 group hover:-translate-y-2 relative overflow-hidden {{ $colors }}">
                    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-white/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>

                    <div class="flex items-center gap-6 mb-8 relative z-10">
                        <div class="w-16 h-16 rounded-[1.5rem] bg-gray-950 shadow-inner flex items-center justify-center shrink-0 border border-inherit group-hover:scale-110 transition-transform duration-500">
                            <x-dynamic-component :component="'heroicon-s-'.$title['icon']" class="w-8 h-8 opacity-80 group-hover:opacity-100 transition-opacity" />
                        </div>
                        <div>
                            <h4 class="font-bold text-white text-xl tracking-tight">{{ $title['name'] }}</h4>
                            <p class="text-[10px] uppercase font-black tracking-[0.2em] mt-1.5 opacity-80">{{ $title['tier_name'] }}</p>
                        </div>
                    </div>
                    <p class="text-sm opacity-70 mb-8 leading-relaxed relative z-10">{{ $title['description'] }}</p>
                    <div class="relative z-10">
                        <div class="w-full h-3 bg-gray-950 rounded-full overflow-hidden border border-gray-800 mb-3 shadow-inner">
                            <div class="h-full {{ $progressColor }} transition-all duration-1000" style="width: {{ $title['percentage'] }}%"></div>
                        </div>
                        <div class="flex justify-between text-[10px] font-black uppercase tracking-widest opacity-60">
                            <span>Fortschritt</span>
                            <span>{{ $title['current_value'] }} / {{ $title['next_req'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
