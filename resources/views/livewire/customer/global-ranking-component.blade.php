<div>
    <div class="p-4 sm:p-6 lg:p-10 min-h-[85vh] flex flex-col relative z-10 font-sans antialiased text-gray-300">

        @if(!$hasOptedIn)
            {{-- DATENSCHUTZ OPT-IN SCREEN --}}
            <div class="flex-1 flex items-center justify-center animate-fade-in-up">
                <div class="max-w-xl w-full bg-gray-900 border border-amber-500/30 rounded-[2.5rem] p-8 sm:p-12 text-center shadow-[0_0_80px_rgba(251,191,36,0.15)] relative overflow-hidden">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent pointer-events-none"></div>

                    <div class="w-20 h-20 mx-auto bg-gray-950 border border-amber-500/50 rounded-full flex items-center justify-center mb-6 shadow-inner relative z-10">
                        <span class="text-4xl drop-shadow-[0_0_15px_rgba(251,191,36,0.5)]">🏆</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl font-serif font-black text-white mb-4 relative z-10">Die Halle der Legenden</h1>
                    <p class="text-gray-400 text-sm sm:text-base leading-relaxed mb-8 relative z-10">
                        Messe dich mit anderen Käufern und Spielern der Manufaktur.
                        Mit dem Betreten stimmst du zu, dass dein <strong class="text-white">Vorname und der erste Buchstabe deines Nachnamens</strong>
                        sowie dein aktuelles Level und deine Punkte öffentlich in dieser Rangliste angezeigt werden.
                    </p>

                    <button wire:click="optIn" class="w-full py-4 sm:py-5 bg-amber-500 text-gray-900 rounded-2xl font-black text-xs sm:text-sm uppercase tracking-[0.2em] shadow-[0_0_30px_rgba(251,191,36,0.4)] hover:bg-amber-400 hover:scale-105 transition-all relative z-10 flex justify-center gap-3 items-center group">
                        Namen freigeben & Betreten
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>

                    <div class="mt-6 relative z-10">
                        <a href="{{ route('customer.dashboard') }}" class="text-[10px] sm:text-xs text-gray-500 font-bold uppercase tracking-widest hover:text-white transition-colors">Zurück zum Dashboard</a>
                    </div>
                </div>
            </div>
        @else
            {{-- RANGLISTE --}}
            <div class="max-w-6xl mx-auto w-full animate-fade-in">

                <div class="text-center mb-12 sm:mb-16">
                    <a href="{{ route('customer.dashboard') }}" class="inline-flex items-center gap-2 text-[10px] sm:text-xs text-gray-400 font-bold uppercase tracking-widest hover:text-white transition-colors mb-6">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Zurück
                    </a>
                    <h1 class="text-4xl sm:text-5xl md:text-6xl font-serif font-black text-white drop-shadow-lg tracking-tight">Halle der Legenden</h1>
                    <p class="text-amber-400/80 mt-3 text-xs sm:text-sm uppercase tracking-[0.3em] font-bold">Die glorreichen Top 50 der Manufaktur</p>

                    <div class="flex flex-wrap justify-center gap-4 mt-8">
                        <button wire:click="$set('activeTab', 'classic')" class="px-6 py-2 rounded-full font-bold uppercase tracking-widest text-xs transition-colors border {{ $activeTab === 'classic' ? 'bg-amber-500 text-gray-900 border-amber-500 shadow-[0_0_15px_rgba(245,158,11,0.5)]' : 'bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-500' }}">Klassisch</button>
                        <button wire:click="$set('activeTab', 'funkenflug')" class="px-6 py-2 rounded-full font-bold uppercase tracking-widest text-xs transition-colors border {{ $activeTab === 'funkenflug' ? 'bg-indigo-500 text-white border-indigo-500 shadow-[0_0_15px_rgba(99,102,241,0.5)]' : 'bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-500' }}">Funkenflug Express</button>
                    </div>
                </div>

                @php
                    $top3 = $rankings->take(3);
                    $rest = $rankings->skip(3);
                    $myId = auth('customer')->id();
                @endphp

                @if($top3->count() > 0)
                    {{-- PODIUM TOP 3 --}}
                    <div class="flex flex-col sm:flex-row items-end justify-center gap-4 sm:gap-6 mb-16 px-4">

                        {{-- PLATZ 2 (Silber) --}}
                        @if($top3->count() >= 2)
                            @php $p2 = $top3->get(1); @endphp
                            <div class="w-full sm:w-1/3 order-2 sm:order-1 bg-gray-900 border border-slate-400/30 rounded-t-[2rem] rounded-b-2xl p-6 text-center relative overflow-hidden shadow-[0_0_40px_rgba(148,163,184,0.1)] transform sm:translate-y-8 {{ $p2->customer_id === $myId ? 'ring-2 ring-slate-300' : '' }}">
                                <div class="absolute inset-0 bg-gradient-to-b from-slate-400/10 to-transparent pointer-events-none"></div>
                                <div class="w-16 h-16 mx-auto bg-slate-800 rounded-full flex items-center justify-center text-slate-300 font-serif font-black text-2xl mb-4 border-2 border-slate-500 shadow-inner">2</div>
                                <h3 class="text-white font-bold text-lg truncate">{{ $p2->customer->first_name }} {{ substr($p2->customer->last_name, 0, 1) }}.</h3>
                                <p class="text-slate-400 text-xs font-black uppercase tracking-widest mt-2">Level {{ $p2->level }}</p>
                                <div class="flex justify-center my-4">
                                    <img src="{{ $this->getAvatarForLevel($p2->level) }}" alt="Avatar" class="w-24 h-24 object-contain filter drop-shadow-[0_0_8px_rgba(255,255,255,0.1)]">
                                </div>
                                @if($activeTab === 'funkenflug')
                                    <p class="text-indigo-400/80 text-[10px] font-bold mt-1">{{ number_format($p2->funkenflug_highscore, 0, ',', '.') }} m Distanz</p>
                                @else
                                    <p class="text-slate-500 text-[10px] font-bold mt-1">{{ number_format($p2->funken_total_earned, 0, ',', '.') }} Funken</p>
                                @endif
                            </div>
                        @endif

                        {{-- PLATZ 1 (Gold) --}}
                        @php $p1 = $top3->get(0); @endphp
                        <div class="w-full sm:w-1/3 order-1 sm:order-2 bg-gradient-to-b from-gray-900 to-gray-950 border-2 border-amber-400/50 rounded-t-[3rem] rounded-b-2xl p-8 text-center relative overflow-hidden shadow-[0_0_60px_rgba(251,191,36,0.2)] z-10 {{ $p1->customer_id === $myId ? 'ring-4 ring-amber-400/50' : '' }}">
                            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/stardust.png')] opacity-20 pointer-events-none mix-blend-overlay"></div>
                            <div class="absolute inset-0 bg-gradient-to-b from-amber-500/20 to-transparent pointer-events-none"></div>
                            <div class="absolute top-2 left-1/2 -translate-x-1/2 text-4xl drop-shadow-[0_0_15px_rgba(251,191,36,0.8)] z-20">👑</div>
                            <div class="w-20 h-20 mx-auto bg-amber-900/50 rounded-full flex items-center justify-center text-amber-400 font-serif font-black text-4xl mb-4 mt-6 border-4 border-amber-400 shadow-[0_0_30px_rgba(251,191,36,0.5)] relative z-10">1</div>
                            <h3 class="text-white font-black text-xl md:text-2xl truncate drop-shadow-md relative z-10">{{ $p1->customer->first_name }} {{ substr($p1->customer->last_name, 0, 1) }}.</h3>
                            <p class="text-amber-400 text-sm font-black uppercase tracking-[0.2em] mt-2 drop-shadow-[0_0_8px_currentColor] relative z-10">Level {{ $p1->level }}</p>
                            <div class="flex justify-center my-6 relative z-10">
                                <img src="{{ $this->getAvatarForLevel($p1->level) }}" alt="Avatar" class="w-32 h-32 object-contain filter drop-shadow-[0_0_20px_rgba(251,191,36,0.6)]">
                            </div>
                            @if($activeTab === 'funkenflug')
                                <p class="text-indigo-400/90 text-xs font-bold mt-1 relative z-10">{{ number_format($p1->funkenflug_highscore, 0, ',', '.') }} m Distanz gesamt!</p>
                            @else
                                <p class="text-amber-500/70 text-xs font-bold mt-1 relative z-10">{{ number_format($p1->funken_total_earned, 0, ',', '.') }} Funken gesamt</p>
                            @endif
                        </div>

                        {{-- PLATZ 3 (Bronze) --}}
                        @if($top3->count() >= 3)
                            @php $p3 = $top3->get(2); @endphp
                            <div class="w-full sm:w-1/3 order-3 sm:order-3 bg-gray-900 border border-orange-700/30 rounded-t-[2rem] rounded-b-2xl p-6 text-center relative overflow-hidden shadow-[0_0_40px_rgba(194,65,12,0.1)] transform sm:translate-y-12 {{ $p3->customer_id === $myId ? 'ring-2 ring-orange-500' : '' }}">
                                <div class="absolute inset-0 bg-gradient-to-b from-orange-700/10 to-transparent pointer-events-none"></div>
                                <div class="w-16 h-16 mx-auto bg-orange-950 rounded-full flex items-center justify-center text-orange-400 font-serif font-black text-2xl mb-4 border-2 border-orange-700 shadow-inner">3</div>
                                <h3 class="text-white font-bold text-lg truncate">{{ $p3->customer->first_name }} {{ substr($p3->customer->last_name, 0, 1) }}.</h3>
                                <p class="text-orange-400 text-xs font-black uppercase tracking-widest mt-2">Level {{ $p3->level }}</p>
                                <div class="flex justify-center my-4">
                                    <img src="{{ $this->getAvatarForLevel($p3->level) }}" alt="Avatar" class="w-24 h-24 object-contain filter drop-shadow-[0_0_8px_rgba(255,255,255,0.1)]">
                                </div>
                                @if($activeTab === 'funkenflug')
                                    <p class="text-indigo-400/80 text-[10px] font-bold mt-1">{{ number_format($p3->funkenflug_highscore, 0, ',', '.') }} m Distanz</p>
                                @else
                                    <p class="text-orange-500/60 text-[10px] font-bold mt-1">{{ number_format($p3->funken_total_earned, 0, ',', '.') }} Funken</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                {{-- LISTE PLATZ 4 BIS 50 --}}
                @if($rest->count() > 0)
                    <div class="bg-gray-900/50 backdrop-blur-md rounded-[2rem] border border-gray-800 shadow-2xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                            <tr class="bg-gray-950/80 border-b border-gray-800 text-[10px] font-black text-gray-500 uppercase tracking-widest">
                                <th class="px-6 py-5 w-16 text-center">Rang</th>
                                <th class="px-6 py-5">Legende</th>
                                <th class="px-6 py-5 text-center">Level</th>
                                <th class="px-6 py-5 text-right">{{ $activeTab === 'funkenflug' ? 'Highscore (m)' : 'Verdiente Funken' }}</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/50">
                            @foreach($rest as $index => $r)
                                @php $actualRank = $index + 4; @endphp
                                <tr class="hover:bg-gray-800/30 transition-colors {{ $r->customer_id === $myId ? 'bg-primary/5' : '' }}">
                                    <td class="px-6 py-4 text-center">
                                        <span class="text-gray-500 font-mono font-bold text-sm">#{{ $actualRank }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-white text-sm sm:text-base flex items-center gap-2">
                                            {{ $r->customer->first_name }} {{ substr($r->customer->last_name, 0, 1) }}.
                                            @if($r->customer_id === $myId)
                                                <span class="bg-primary/20 text-primary border border-primary/30 px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest ml-2">Du</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-block bg-gray-800 border border-gray-700 px-3 py-1 rounded-lg text-xs font-black text-white shadow-inner">
                                            {{ $r->level }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-400 font-mono text-xs sm:text-sm">
                                        @if($activeTab === 'funkenflug')
                                            {{ number_format($r->funkenflug_highscore, 0, ',', '.') }} m 🚀
                                        @else
                                            {{ number_format($r->funken_total_earned, 0, ',', '.') }} ✨
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

            </div>
        @endif
    </div>
</div>
