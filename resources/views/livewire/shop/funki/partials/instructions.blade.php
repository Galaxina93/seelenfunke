@php $wi = $this->workInstructions; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

    @php $command = $this->ultimateCommand; @endphp

    <div class="max-w-4xl mx-auto mt-10 animate-fade-in">
        {{-- FUNKI AVATAR & SPRECHBLASE --}}
        <div class="flex flex-col md:flex-row items-center md:items-start gap-8">

            {{-- Funki Portrait --}}
            <div class="shrink-0 relative">
                <div class="absolute inset-0 bg-primary/20 rounded-full blur-2xl animate-pulse"></div>
                <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                     class="relative w-32 h-32 md:w-48 md:h-48 object-contain drop-shadow-2xl"
                     alt="Funki">
            </div>

            {{-- Die Sprechblase --}}
            <div class="relative flex-1">
                {{-- Pfeil der Sprechblase (Desktop) --}}
                <div class="hidden md:block absolute top-10 -left-4 w-8 h-8 bg-white border-l border-b border-slate-100 rotate-45"></div>

                <div class="bg-white rounded-[3rem] shadow-2xl border border-slate-100 p-8 md:p-12 relative overflow-hidden">
                    {{-- Background Light Effect --}}
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16 blur-3xl"></div>

                    <div class="relative z-10">
                    <span class="text-primary font-black uppercase tracking-[0.3em] text-xs mb-4 block">
                        {{ $command['instruction'] }}
                    </span>

                        <h2 class="text-3xl md:text-5xl font-serif font-bold text-slate-900 leading-tight mb-6">
                            {{ $command['icon'] }} {{ $command['title'] }}
                        </h2>

                        <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100 mb-8">
                            <p class="text-lg md:text-xl text-slate-600 leading-relaxed italic">
                                "{{ $command['message'] }}"
                            </p>
                        </div>

                        <a href="{{ route($command['action_route']) }}"
                           class="inline-flex items-center gap-4 bg-slate-900 text-white px-10 py-5 rounded-2xl font-black uppercase tracking-widest text-sm hover:bg-primary hover:text-black transition-all transform hover:-translate-y-1 shadow-xl shadow-slate-200">
                            <span>{{ $command['action_label'] }}</span>
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
