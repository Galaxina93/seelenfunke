<div class="min-h-screen bg-gray-50 pb-20">

    {{-- HEADER --}}
    <div class="bg-white border-b border-gray-100 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center py-4 gap-4">
                {{-- Logo & Titel --}}
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                             class="w-12 h-12 object-contain"
                             alt="Funki">
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

    {{-- MAIN CONTENT --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 animate-fade-in">

        {{-- BEREICH 1: DIE KLARE ANSAGE (Ultimate Command) --}}
        <div class="max-w-5xl mx-auto mb-16">

            @php
                $wi = $this->workInstructions;
                $command = $this->ultimateCommand;
            @endphp

            <div class="w-full">
                {{-- FUNKI AVATAR & SPRECHBLASE --}}
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-10">

                    {{-- Funki Portrait --}}
                    <div class="shrink-0 relative mt-4">
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-2xl animate-pulse"></div>
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                             class="relative w-32 h-32 md:w-48 md:h-48 object-contain drop-shadow-2xl hover:scale-105 transition-transform duration-500"
                             alt="Funki">
                    </div>

                    {{-- Die Sprechblase --}}
                    <div class="relative flex-1 w-full">
                        {{-- Pfeil der Sprechblase (Desktop) --}}
                        <div class="hidden md:block absolute top-12 -left-3 w-6 h-6 bg-white border-l border-b border-slate-100 rotate-45 z-20"></div>

                        <div class="bg-white rounded-[3rem] shadow-xl shadow-slate-200/50 border border-slate-100 p-8 md:p-12 relative overflow-hidden group hover:shadow-2xl transition-all duration-500">

                            {{-- Background Light Effect --}}
                            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full -mr-20 -mt-20 blur-3xl pointer-events-none"></div>

                            <div class="relative z-10">
                                <span class="inline-block px-3 py-1 rounded-full bg-primary/10 text-primary font-black uppercase tracking-[0.2em] text-[10px] mb-6">
                                    {{ $command['instruction'] }}
                                </span>

                                <h2 class="text-3xl md:text-5xl font-serif font-bold text-slate-900 leading-tight mb-6">
                                    <span class="mr-2">{{ $command['icon'] }}</span> {{ $command['title'] }}
                                </h2>

                                <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100 mb-10">
                                    <p class="text-lg md:text-xl text-slate-600 leading-relaxed italic font-medium">
                                        "{{ $command['message'] }}"
                                    </p>
                                </div>

                                <a href="{{ route($command['action_route']) }}"
                                   class="inline-flex items-center gap-3 bg-slate-900 text-white px-8 py-4 rounded-2xl font-bold uppercase tracking-widest text-xs hover:bg-primary hover:text-white transition-all transform hover:-translate-y-1 shadow-lg shadow-slate-200">
                                    <span>{{ $command['action_label'] }}</span>
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- BEREICH 2: PRIORITÄTEN LEGENDE --}}
        <div class="max-w-6xl mx-auto mb-24">
            <div class="text-center mb-8">
                <h3 class="text-xs font-black uppercase text-slate-300 tracking-[0.2em]">Funkis Logik</h3>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                @php
                    $prios = [
                        ['score' => '1000+', 'title' => 'Sicherheit', 'desc' => 'System brennt', 'color' => 'bg-red-50 text-red-600 border-red-100', 'icon' => 'shield-exclamation'],
                        ['score' => '500', 'title' => 'Termine', 'desc' => 'Feste Pflicht', 'color' => 'bg-blue-50 text-blue-600 border-blue-100', 'icon' => 'calendar'],
                        ['score' => '300', 'title' => 'Routine', 'desc' => 'Bio-Rhythmus', 'color' => 'bg-emerald-50 text-emerald-600 border-emerald-100', 'icon' => 'clock'],
                        ['score' => '200', 'title' => 'Business', 'desc' => 'Geld verdienen', 'color' => 'bg-amber-50 text-amber-600 border-amber-100', 'icon' => 'currency-euro'],
                        ['score' => '100', 'title' => 'Verwaltung', 'desc' => 'Ordnung halten', 'color' => 'bg-slate-50 text-slate-600 border-slate-200', 'icon' => 'document-text'],
                        ['score' => '10', 'title' => 'ToDos', 'desc' => 'Lückenfüller', 'color' => 'bg-cyan-50 text-cyan-600 border-cyan-100', 'icon' => 'check-circle'],
                        ['score' => '0', 'title' => 'Freizeit', 'desc' => 'Erholung', 'color' => 'bg-indigo-50 text-indigo-600 border-indigo-100', 'icon' => 'sun'],
                    ];
                @endphp

                @foreach($prios as $p)
                    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col items-center text-center transition-all hover:-translate-y-1 hover:shadow-md group">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mb-3 {{ $p['color'] }} border">
                            <x-dynamic-component :component="'heroicon-o-' . $p['icon']" class="w-4 h-4" />
                        </div>
                        <span class="text-[9px] font-black uppercase text-slate-300 mb-1">Score {{ $p['score'] }}</span>
                        <h4 class="text-xs font-bold text-slate-900 mb-1">{{ $p['title'] }}</h4>
                        <p class="text-[10px] font-medium text-slate-500 leading-tight">{{ $p['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- BEREICH 3: AUTOMATISIERUNG --}}
        <div class="max-w-7xl mx-auto">
            @include('livewire.shop.funki.partials.automation')
        </div>

    </div>
</div>
