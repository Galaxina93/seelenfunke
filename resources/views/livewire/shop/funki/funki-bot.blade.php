<div class="min-h-screen bg-gray-50 pb-20">

    {{-- HEADER --}}
    <div class="bg-white border-b border-gray-100 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4 gap-4">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                             class="w-10 h-10 sm:w-12 sm:h-12 object-contain"
                             alt="Funki">
                        <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 border-2 border-white rounded-full animate-pulse"></div>
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-serif font-bold text-gray-900 leading-tight">Funkis Zentrale</h1>
                        <p class="text-[10px] sm:text-xs text-gray-500">Dein Autopilot für den Tag.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 animate-fade-in">

        @php
            $command = $this->ultimateCommand;
            $flow = $command['flow'];
            $rec = $command['recommendation'];
            $alts = $command['alternatives'];
            $routines = $command['routines'];

            // LOGIK FÜR DIE 3-SCHRITT-TIMELINE
            $displayRoutines = [];
            $currentIndex = -1;

            // Finde den Index der aktuellen Routine
            foreach ($routines as $index => $r) {
                if ($r['status'] === 'current') {
                    $currentIndex = $index;
                    break;
                }
            }

            if ($currentIndex !== -1) {
                // 1 davor, die aktuelle, 1 danach
                if (isset($routines[$currentIndex - 1])) $displayRoutines[] = $routines[$currentIndex - 1];
                $displayRoutines[] = $routines[$currentIndex];
                if (isset($routines[$currentIndex + 1])) $displayRoutines[] = $routines[$currentIndex + 1];
            } else {
                // Falls gerade "Freizeit" ist und keine aktiv ist: Letzte vergangene und nächste kommende zeigen
                $past = array_filter($routines, fn($r) => $r['status'] === 'past');
                $future = array_filter($routines, fn($r) => $r['status'] === 'future');

                $lastPast = end($past);
                $firstFuture = reset($future);

                if ($lastPast) $displayRoutines[] = $lastPast;
                if ($firstFuture) {
                    $displayRoutines[] = $firstFuture;

                    // Noch eine weitere aus der Zukunft holen, um auf 3 zu kommen (falls gewünscht und vorhanden)
                    $nextKeys = array_keys($future);
                    if (count($nextKeys) > 1 && !$lastPast) {
                        $displayRoutines[] = $future[$nextKeys[1]];
                    }
                }
            }
        @endphp

        <div class="max-w-5xl mx-auto mb-12 sm:mb-16">
            <div class="w-full">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-6 lg:gap-10">

                    {{-- Funki Portrait --}}
                    <div class="shrink-0 relative mt-2 md:mt-4">
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-xl md:blur-2xl animate-pulse"></div>
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                             class="relative w-24 h-24 sm:w-32 sm:h-32 lg:w-48 lg:h-48 object-contain drop-shadow-2xl hover:scale-105 transition-transform duration-500"
                             alt="Funki">
                    </div>

                    {{-- Die Sprechblase --}}
                    <div class="relative flex-1 min-w-0 w-full">

                        {{-- Pfeil Desktop (Links) --}}
                        <div class="hidden md:block absolute top-12 -left-3 w-6 h-6 bg-white border-l border-b border-slate-100 rotate-45 z-20"></div>

                        {{-- Pfeil Mobile (Oben) --}}
                        <div class="block md:hidden absolute -top-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-white border-l border-t border-slate-100 rotate-45 z-20"></div>

                        <div class="bg-white rounded-3xl sm:rounded-[3rem] shadow-xl shadow-slate-200/50 border border-slate-100 p-6 sm:p-8 lg:p-10 relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-48 h-48 sm:w-64 sm:h-64 bg-primary/5 rounded-full -mr-10 -mt-10 sm:-mr-20 sm:-mt-20 blur-2xl sm:blur-3xl pointer-events-none"></div>

                            <div class="relative z-10 flex flex-col gap-8 sm:gap-10">

                                {{-- OBERER BEREICH: Ansage & Alternativen --}}
                                <div class="flex flex-col lg:flex-row gap-8">
                                    {{-- Linke Spalte: Aktueller Flow & Empfehlung --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="inline-flex items-center gap-2 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full bg-slate-100 border border-slate-200 mb-4 sm:mb-6 max-w-full">
                                            <x-dynamic-component :component="'heroicon-o-' . $flow['icon']" class="shrink-0 w-4 h-4 text-slate-500" />
                                            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-700 truncate">
                                                Dein Flow: <span class="text-primary">{{ $flow['title'] }}</span>
                                                <span class="text-slate-400 font-normal hidden sm:inline">({{ $flow['step'] }})</span>
                                            </span>
                                        </div>

                                        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-serif font-bold text-slate-900 leading-tight mb-4 break-words">
                                            <span class="mr-2">{{ $rec['icon'] }}</span> {{ $rec['title'] }}
                                        </h2>

                                        <div class="bg-slate-50 rounded-2xl p-4 sm:p-5 border border-slate-100 mb-6">
                                            <p class="text-base sm:text-lg text-slate-600 leading-relaxed italic font-medium">
                                                "{{ $rec['message'] }}"
                                            </p>
                                        </div>

                                        <a href="{{ route($rec['action_route']) }}"
                                           class="flex md:inline-flex items-center justify-center gap-3 bg-slate-900 text-white px-6 sm:px-8 py-3.5 sm:py-4 rounded-xl sm:rounded-2xl font-bold uppercase tracking-widest text-[10px] sm:text-xs hover:bg-primary hover:text-white transition-all transform hover:-translate-y-1 shadow-lg shadow-slate-200 w-full md:w-auto text-center">
                                            <span>Jetzt: {{ $rec['action_label'] }}</span>
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                            </svg>
                                        </a>
                                    </div>

                                    {{-- Rechte Spalte: Alternativen --}}
                                    @if(count($alts) > 0)
                                        <div class="w-full lg:w-64 flex flex-col gap-3 border-t lg:border-t-0 lg:border-l border-slate-100 pt-6 lg:pt-0 lg:pl-8">
                                            <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-[0.2em] mb-1 sm:mb-2">Alternativ sinnvoll:</h3>
                                            @foreach($alts as $alt)
                                                <a href="{{ route($alt['action_route']) }}" class="block p-3 sm:p-4 rounded-xl sm:rounded-2xl border border-slate-100 hover:border-primary/30 hover:bg-primary/5 transition-colors group bg-white lg:bg-transparent">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span>{{ $alt['icon'] }}</span>
                                                        <span class="text-xs sm:text-sm font-bold text-slate-800 line-clamp-1">{{ $alt['title'] }}</span>
                                                    </div>
                                                    <p class="text-[10px] sm:text-xs text-slate-500 line-clamp-2">{{ $alt['message'] }}</p>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- UNTERER BEREICH: Die 3-Stationen Timeline --}}
                                @if(count($displayRoutines) > 0)
                                    <div class="pt-6 sm:pt-8 border-t border-slate-100 w-full">

                                        <div class="mb-4">
                                            <h3 class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-400">Dein Tages-Fokus</h3>
                                        </div>

                                        {{-- Desktop: 3-Spalten Grid. Mobile: Horizontaler Scroll mit 85% Breite pro Item --}}
                                        <div class="flex sm:grid sm:grid-cols-3 gap-3 sm:gap-4 overflow-x-auto pb-2 sm:pb-0 snap-x snap-mandatory w-full" style="scrollbar-width: none;">
                                            <style> .snap-x::-webkit-scrollbar { display: none; } </style>

                                            @foreach($displayRoutines as $routine)
                                                <div class="shrink-0 w-[80%] sm:w-auto p-3 m-2 sm:p-4 rounded-xl sm:rounded-2xl border snap-center transition-all
                                                    {{ $routine['status'] === 'past' ? 'bg-slate-50 border-slate-100 text-slate-400 opacity-60 grayscale' : '' }}
                                                    {{ $routine['status'] === 'current' ? 'bg-blue-50/60 border-blue-200 shadow-sm ring-1 ring-blue-500/20 scale-100 sm:scale-105 origin-center' : '' }}
                                                    {{ $routine['status'] === 'future' ? 'bg-white border-slate-100/60 text-slate-400' : '' }}
                                                ">
                                                    <div class="flex items-center justify-between mb-1.5 sm:mb-2">
                                                        <x-dynamic-component :component="'heroicon-o-' . $routine['icon']"
                                                                             class="w-4 h-4 sm:w-5 sm:h-5 {{ $routine['status'] === 'current' ? 'text-blue-500' : 'text-slate-400' }}" />
                                                        <span class="text-[9px] sm:text-[10px] font-bold tracking-wider
                                                            {{ $routine['status'] === 'current' ? 'text-blue-600' : 'text-slate-400' }}">
                                                            {{ $routine['time_formatted'] }}
                                                        </span>
                                                    </div>
                                                    <h4 class="text-xs sm:text-sm font-bold mt-1 truncate {{ $routine['status'] === 'current' ? 'text-slate-900' : 'text-slate-500' }}">
                                                        {{ $routine['title'] }}
                                                    </h4>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- BEREICH 2: PRIORITÄTEN LEGENDE --}}
        <div class="max-w-6xl mx-auto mb-16 sm:mb-24">
            <div class="text-center mb-6 sm:mb-8">
                <h3 class="text-[10px] sm:text-xs font-black uppercase text-slate-300 tracking-[0.2em]">Funkis Logik</h3>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3 sm:gap-4">
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
                    <div class="bg-white rounded-xl sm:rounded-2xl p-3 sm:p-4 border border-slate-100 shadow-sm flex flex-col items-center text-center transition-all hover:-translate-y-1 hover:shadow-md group">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center mb-2 sm:mb-3 {{ $p['color'] }} border">
                            <x-dynamic-component :component="'heroicon-o-' . $p['icon']" class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        </div>
                        <span class="text-[8px] sm:text-[9px] font-black uppercase text-slate-300 mb-1">Score {{ $p['score'] }}</span>
                        <h4 class="text-[11px] sm:text-xs font-bold text-slate-900 mb-0.5 sm:mb-1">{{ $p['title'] }}</h4>
                        <p class="text-[9px] sm:text-[10px] font-medium text-slate-500 leading-tight">{{ $p['desc'] }}</p>
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
