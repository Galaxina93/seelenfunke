<div class="min-h-screen bg-transparent pb-20 font-sans antialiased text-gray-300">
    <div class="bg-gray-900/90 backdrop-blur-xl border-b border-gray-800 sticky top-0 z-40 shadow-2xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-5 gap-4">
                <div class="flex items-center gap-4">
                    <div class="relative group">
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-md group-hover:bg-primary/30 transition-all"></div>
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="relative w-10 h-10 sm:w-12 sm:h-12 object-contain rounded-xl border border-white/10" alt="Funki">
                        <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-green-500 border-2 border-gray-900 rounded-full animate-pulse shadow-[0_0_10px_rgba(34,197,94,0.5)]"></div>
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-serif font-bold text-white leading-none tracking-tight">Funkis Zentrale</h1>
                        <p class="text-[10px] sm:text-xs text-gray-500 uppercase font-black tracking-widest mt-1">System-Autopilot Aktiv</p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-3 bg-black/40 px-4 py-2 rounded-full border border-gray-800 shadow-inner">
                    <span class="text-[9px] font-black text-gray-500 uppercase tracking-widest">Performance:</span>
                    <span class="text-[10px] font-mono font-bold text-primary">OPTIMAL</span>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 animate-fade-in-up">
        @php
            $command = $this->ultimateCommand;
            $flow = $command['flow'];
            $rec = $command['recommendation'];
            $alts = $command['alternatives'];
            $routines = $command['routines'];

            // LOGIK FÜR DIE 3-SCHRITT-TIMELINE
            $displayRoutines = [];
            $currentIndex = -1;
            foreach ($routines as $index => $r) {
                if ($r['status'] === 'current') {
                    $currentIndex = $index;
                    break;
                }
            }

            if ($currentIndex !== -1) {
                if (isset($routines[$currentIndex - 1])) $displayRoutines[] = $routines[$currentIndex - 1];
                $displayRoutines[] = $routines[$currentIndex];
                if (isset($routines[$currentIndex + 1])) $displayRoutines[] = $routines[$currentIndex + 1];
            } else {
                $past = array_filter($routines, fn($r) => $r['status'] === 'past');
                $future = array_filter($routines, fn($r) => $r['status'] === 'future');

                $lastPast = end($past);
                $firstFuture = reset($future);

                if ($lastPast) $displayRoutines[] = $lastPast;
                if ($firstFuture) {
                    $displayRoutines[] = $firstFuture;
                    $nextKeys = array_keys($future);
                    if (count($nextKeys) > 1 && !$lastPast) {
                        $displayRoutines[] = $future[$nextKeys[1]];
                    }
                }
            }
        @endphp

        <div class="max-w-5xl mx-auto mb-16 sm:mb-20">
            <div class="flex flex-col md:flex-row items-center md:items-start gap-8 lg:gap-12">
                <div class="shrink-0 relative mt-4">
                    <div class="absolute inset-0 bg-primary/20 rounded-full blur-[40px] animate-pulse"></div>
                    <div class="relative w-28 h-28 sm:w-36 sm:h-36 lg:w-48 lg:h-48 transform hover:scale-105 transition-transform duration-700">
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-contain drop-shadow-[0_20px_50px_rgba(0,0,0,0.5)]" alt="Funki">
                    </div>
                </div>

                <div class="relative flex-1 min-w-0 w-full">
                    <div class="hidden md:block absolute top-12 -left-3 w-6 h-6 bg-gray-900 border-l border-b border-gray-800 rotate-45 z-20 shadow-[-5px_5px_10px_rgba(0,0,0,0.3)]"></div>
                    <div class="block md:hidden absolute -top-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-gray-900 border-l border-t border-gray-800 rotate-45 z-20 shadow-[0_-5px_10px_rgba(0,0,0,0.3)]"></div>

                    <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] sm:rounded-[3.5rem] shadow-[0_30px_100px_rgba(0,0,0,0.6)] border border-gray-800 p-6 sm:p-10 lg:p-12 relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full -mr-20 -mt-20 blur-3xl pointer-events-none opacity-50"></div>
                        <div class="relative z-10 flex flex-col gap-10">

                            <div class="flex flex-col lg:flex-row gap-10">
                                <div class="flex-1 min-w-0">
                                    <div class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-black/40 border border-gray-800 mb-8 max-w-full shadow-inner">
                                        <x-dynamic-component :component="'heroicon-o-' . $flow['icon']" class="shrink-0 w-4 h-4 text-primary animate-pulse" />
                                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 truncate">
                                            Status: <span class="text-white">{{ $flow['title'] }}</span>
                                            <span class="text-primary/60 font-bold hidden sm:inline ml-1">[{{ $flow['step'] }}]</span>
                                        </span>
                                    </div>

                                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-serif font-bold text-white leading-tight mb-6 tracking-tight">
                                        <span class="inline-block hover:scale-125 transition-transform mr-2">{{ $rec['icon'] }}</span>
                                        {{ $rec['title'] }}
                                    </h2>

                                    <div class="bg-gray-950/60 rounded-3xl p-6 sm:p-8 border border-gray-800 mb-8 shadow-inner relative group">
                                        <div class="absolute top-4 right-6 text-5xl text-white/5 font-serif opacity-20 pointer-events-none group-hover:text-primary/10 transition-colors">”</div>
                                        <p class="text-base sm:text-xl text-gray-300 leading-relaxed italic font-medium relative z-10">
                                            "{{ $rec['message'] }}"
                                        </p>
                                    </div>

                                    <a href="{{ route($rec['action_route']) }}" class="group relative flex md:inline-flex items-center justify-center gap-4 bg-primary text-gray-900 px-8 py-4 sm:py-5 rounded-2xl font-black uppercase tracking-widest text-xs sm:text-sm shadow-[0_0_40px_rgba(197,160,89,0.3)] hover:bg-white hover:scale-[1.03] transition-all duration-300 w-full md:w-auto overflow-hidden">
                                        <div class="absolute inset-0 bg-white/20 transform -skew-x-12 -translate-x-[150%] group-hover:translate-x-[150%] transition-transform duration-1000"></div>
                                        <span class="relative z-10">Jetzt: {{ $rec['action_label'] }}</span>
                                        <svg class="w-5 h-5 relative z-10 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                        </svg>
                                    </a>
                                </div>

                                @if(count($alts) > 0)
                                    <div class="w-full lg:w-72 flex flex-col gap-4 border-t lg:border-t-0 lg:border-l border-gray-800 pt-8 lg:pt-0 lg:pl-10">
                                        <h3 class="text-[10px] font-black uppercase text-gray-500 tracking-[0.3em] mb-2 flex items-center gap-2">
                                            <span class="w-6 h-px bg-gray-800"></span> Optionen
                                        </h3>
                                        @foreach($alts as $alt)
                                            <a href="{{ route($alt['action_route']) }}" class="block p-4 rounded-2xl border border-gray-800 bg-gray-950/40 hover:border-primary/40 hover:bg-primary/5 transition-all duration-300 group shadow-inner">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <span class="text-lg group-hover:scale-110 transition-transform">{{ $alt['icon'] }}</span>
                                                    <span class="text-xs sm:text-sm font-bold text-gray-200 group-hover:text-white transition-colors line-clamp-1 tracking-wide">{{ $alt['title'] }}</span>
                                                </div>
                                                <p class="text-[10px] sm:text-xs text-gray-500 group-hover:text-gray-400 line-clamp-2 leading-relaxed font-medium">{{ $alt['message'] }}</p>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            @if(count($displayRoutines) > 0)
                                <div class="pt-8 border-t border-gray-800 w-full relative">
                                    <div class="flex items-center justify-between mb-6">
                                        <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-500">Zeitstrahl Fokus</h3>
                                        <span class="text-[9px] font-mono text-gray-700 uppercase tracking-widest">Realtime Sync</span>
                                    </div>

                                    <div class="flex sm:grid sm:grid-cols-3 gap-4 sm:gap-6 overflow-x-auto pb-4 sm:pb-0 snap-x snap-mandatory w-full no-scrollbar">
                                        @foreach($displayRoutines as $routine)
                                            @php
                                                $state = $routine['status'];
                                                $isCurrent = $state === 'current';
                                                $isPast = $state === 'past';
                                            @endphp
                                            <div class="shrink-0 w-[85%] sm:w-auto m-2 rounded-2xl border p-4 snap-center transition-all duration-500 {{ $isPast ? 'bg-gray-950/30 border-gray-800/50 opacity-40 grayscale shadow-inner' : '' }} {{ $isCurrent ? 'bg-blue-500/10 border-blue-500/40 shadow-[0_0_30px_rgba(59,130,246,0.15)] ring-1 ring-blue-400/20 scale-[1.02]' : '' }} {{ $state === 'future' ? 'bg-gray-900 border-gray-800 shadow-lg' : '' }}">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div class="p-2 rounded-lg {{ $isCurrent ? 'bg-blue-500 text-white shadow-[0_0_15px_rgba(59,130,246,0.5)]' : 'bg-gray-800 text-gray-500' }}">
                                                        <x-dynamic-component :component="'heroicon-o-' . $routine['icon']" class="w-4 h-4" />
                                                    </div>
                                                    <span class="text-[10px] font-mono font-black {{ $isCurrent ? 'text-blue-400' : 'text-gray-600' }} tracking-tighter">{{ $routine['time_formatted'] }}</span>
                                                </div>
                                                <h4 class="text-xs sm:text-sm font-black uppercase tracking-widest truncate {{ $isCurrent ? 'text-white' : 'text-gray-500' }}">{{ $routine['title'] }}</h4>
                                                @if($isCurrent)
                                                    <div class="mt-3 h-1 w-full bg-gray-800 rounded-full overflow-hidden">
                                                        <div class="h-full bg-blue-500 w-1/2 animate-pulse"></div>
                                                    </div>
                                                @endif
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

        <div class="max-w-6xl mx-auto">
            <div class="flex items-center gap-4 mb-10">
                <div class="h-px bg-gray-800 flex-1"></div>
                <h3 class="text-[10px] font-black uppercase text-gray-600 tracking-[0.5em] shrink-0">Funkis Prioritäten Logik</h3>
                <div class="h-px bg-gray-800 flex-1"></div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-4">
                @php
                    $prios = [
                        ['score' => '1000+', 'title' => 'Sicherheit', 'desc' => 'Kritisch', 'color' => 'text-red-400 bg-red-500/10 border-red-500/20 shadow-red-900/10', 'icon' => 'shield-exclamation'],
                        ['score' => '500', 'title' => 'Termine', 'desc' => 'Feste Zeit', 'color' => 'text-blue-400 bg-blue-500/10 border-blue-500/20 shadow-blue-900/10', 'icon' => 'calendar'],
                        ['score' => '300', 'title' => 'Routine', 'desc' => 'Bio-Fokus', 'color' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20 shadow-emerald-900/10', 'icon' => 'clock'],
                        ['score' => '200', 'title' => 'Business', 'desc' => 'Revenue', 'color' => 'text-amber-400 bg-amber-500/10 border-amber-500/20 shadow-amber-900/10', 'icon' => 'currency-euro'],
                        ['score' => '100', 'title' => 'Verwaltung', 'desc' => 'Struktur', 'color' => 'text-gray-400 bg-gray-800/40 border-gray-700 shadow-inner', 'icon' => 'document-text'],
                        ['score' => '10', 'title' => 'ToDos', 'desc' => 'Aufgaben', 'color' => 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20 shadow-cyan-900/10', 'icon' => 'check-circle'],
                        ['score' => '0', 'title' => 'Freizeit', 'desc' => 'Erholung', 'color' => 'text-indigo-400 bg-indigo-500/10 border-indigo-500/20 shadow-indigo-900/10', 'icon' => 'sun'],
                    ];
                @endphp
                @foreach($prios as $p)
                    <div class="bg-gray-900/60 border border-gray-800 rounded-3xl p-5 flex flex-col items-center text-center transition-all duration-300 hover:border-gray-600 hover:-translate-y-1 shadow-2xl group">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4 {{ $p['color'] }} border shadow-lg group-hover:scale-110 transition-transform">
                            <x-dynamic-component :component="'heroicon-o-' . $p['icon']" class="w-5 h-5" />
                        </div>
                        <span class="text-[9px] font-mono font-black text-gray-600 mb-2 uppercase tracking-widest">Score {{ $p['score'] }}</span>
                        <h4 class="text-xs font-black text-white mb-1 uppercase tracking-wider">{{ $p['title'] }}</h4>
                        <p class="text-[10px] font-bold text-gray-500 leading-tight uppercase tracking-tighter opacity-70">{{ $p['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .shadow-glow { box-shadow: 0 0 20px rgba(197, 160, 89, 0.2); }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
</div>
