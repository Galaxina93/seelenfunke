<div>
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 animate-fade-in-up min-h-[85vh] flex flex-col relative z-10 font-sans antialiased text-gray-700 dark:text-gray-300">

        @if(!$hasOptedIn)
            {{-- DATENSCHUTZ OPT-IN SCREEN --}}
            <div class="flex-1 flex items-center justify-center animate-fade-in-up">
                <div class="max-w-xl w-full bg-white dark:bg-gray-900 border border-amber-500/30 rounded-[2.5rem] p-8 sm:p-12 text-center shadow-lg dark:shadow-[0_0_80px_rgba(251,191,36,0.15)] relative overflow-hidden">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent pointer-events-none"></div>

                    <div class="w-20 h-20 mx-auto bg-amber-50 dark:bg-gray-950 border border-amber-500/50 rounded-full flex items-center justify-center mb-6 shadow-inner relative z-10">
                        <span class="text-4xl drop-shadow-[0_0_15px_rgba(251,191,36,0.5)]">🏆</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl font-serif font-black text-gray-900 dark:text-white mb-4 relative z-10">Die Halle der Legenden</h1>
                    <p class="text-gray-600 dark:text-gray-400 text-sm sm:text-base leading-relaxed mb-8 relative z-10">
                        Messe dich mit anderen Käufern und Spielern der Manufaktur.
                        Mit dem Betreten stimmst du zu, dass dein <strong class="text-gray-900 dark:text-white">Vorname und der erste Buchstabe deines Nachnamens</strong>
                        sowie dein aktuelles Level und deine Punkte öffentlich in dieser Rangliste angezeigt werden.
                    </p>

                    <button wire:click="optIn" class="w-full py-4 sm:py-5 bg-amber-500 text-gray-900 rounded-2xl font-black text-xs sm:text-sm uppercase tracking-[0.2em] shadow-md dark:shadow-[0_0_30px_rgba(251,191,36,0.4)] hover:bg-amber-400 hover:scale-105 transition-all relative z-10 flex justify-center gap-3 items-center group">
                        Namen freigeben & Betreten
                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>

                    <div class="mt-6 relative z-10">
                        <a href="{{ route('customer.dashboard') }}" class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest hover:text-gray-900 dark:hover:text-white transition-colors">Zurück zum Dashboard</a>
                    </div>
                </div>
            </div>
        @else
            {{-- RANGLISTE 2.0 MIT HELDEN-VISITENKARTE --}}
            <div class="max-w-6xl mx-auto w-full animate-fade-in" x-data="{ inspectedHero: null }">

                {{-- SEITEN-HEADER MIT SUB-TABS --}}
                <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-6">
                    <div>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Halle der Legenden</h1>
                        <p class="text-gray-500 dark:text-gray-400 mt-2 text-xs sm:text-sm uppercase tracking-widest font-bold">Die glorreichen Top 50 der Manufaktur • Klicke auf einen Helden für die Visitenkarte</p>
                    </div>

                    {{-- SPIELE SUB-TABS --}}
                    <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-fit shrink-0">
                        <a href="{{ route('customer.games') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/50 dark:hover:bg-gray-800/50">
                            <span>🧩</span>
                            <span>Spiele</span>
                        </a>
                        <a href="{{ route('customer.gamification.profile') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/50 dark:hover:bg-gray-800/50">
                            <span>✨</span>
                            <span>Funki-Profil</span>
                        </a>
                        <a href="{{ route('customer.ranking') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all bg-primary text-gray-900 shadow-sm">
                            <span>🏆</span>
                            <span>Rangliste</span>
                        </a>
                    </div>
                </div>

                <div class="text-center mb-8">
                    <div class="flex flex-wrap justify-center gap-4 mt-2">
                        <button wire:click="$set('activeTab', 'classic')" class="px-6 py-2 rounded-full font-bold uppercase tracking-widest text-xs transition-colors border {{ $activeTab === 'classic' ? 'bg-amber-500 text-gray-900 border-amber-500 shadow-md dark:shadow-[0_0_15px_rgba(245,158,11,0.5)]' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-gray-400' }}">Klassisch (Level & Funken)</button>
                        <button wire:click="$set('activeTab', 'funkenflug')" class="px-6 py-2 rounded-full font-bold uppercase tracking-widest text-xs transition-colors border {{ $activeTab === 'funkenflug' ? 'bg-indigo-500 text-white border-indigo-500 shadow-md dark:shadow-[0_0_15px_rgba(99,102,241,0.5)]' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-gray-400' }}">🚀 Funkenflug Express</button>
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
                            @php
                                $p2 = $top3->get(1);
                                $p2Ship = $this->getShipSkinForProfile($p2);
                                $p2Aura = $this->getHallAuraForProfile($p2);
                                $p2Prestige = $this->getPrestigeBadgeForLevel((int) $p2->level);
                                $p2Payload = $this->getInspectorPayload($p2, 2);
                            @endphp
                            <div @click="inspectedHero = @js($p2Payload)" class="cursor-pointer group w-full sm:w-1/3 order-2 sm:order-1 bg-white dark:bg-gray-900 border border-slate-300 dark:border-slate-400/30 rounded-t-[2rem] rounded-b-2xl p-6 text-center relative overflow-hidden shadow-sm dark:shadow-[0_0_40px_rgba(148,163,184,0.1)] transform sm:translate-y-8 hover:scale-[1.02] transition-all {{ $p2Aura['row_classes'] ?? '' }} {{ $p2->customer_id === $myId ? 'ring-2 ring-slate-400 dark:ring-slate-300' : '' }}">
                                <div class="absolute inset-0 bg-gradient-to-b from-slate-400/10 to-transparent pointer-events-none"></div>
                                <div class="w-16 h-16 mx-auto bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center text-slate-700 dark:text-slate-300 font-serif font-black text-2xl mb-3 border-2 border-slate-400 dark:border-slate-500 shadow-inner">2</div>

                                @if(!empty($p2Aura['badge']))
                                    <div class="mb-2">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-amber-500/20 text-amber-600 dark:text-amber-300 border border-amber-400/40">
                                            {{ $p2Aura['icon'] ?? '✨' }} {{ $p2Aura['badge'] }}
                                        </span>
                                    </div>
                                @endif

                                <h3 class="text-gray-900 dark:text-white font-bold text-lg truncate">{{ $p2->customer?->first_name ?? 'Anonym' }} {{ $p2->customer?->last_name ? mb_substr($p2->customer->last_name, 0, 1) . '.' : '' }}</h3>
                                <p class="text-slate-600 dark:text-slate-300 text-[10px] font-black uppercase tracking-[0.2em] mt-1.5 drop-shadow-sm truncate px-2">{{ $this->getActiveTitleName($p2) }}</p>
                                <div class="flex items-center justify-center gap-1.5 mt-1">
                                    <span class="text-slate-500 text-xs font-black uppercase tracking-widest">Level {{ $p2->level }}</span>
                                    @if($p2Prestige)
                                        <span class="text-[10px]" title="{{ $p2Prestige['name'] }}">{{ $p2Prestige['icon'] }}</span>
                                    @endif
                                </div>
                                <div class="flex justify-center my-4">
                                    <img src="{{ $this->getAvatarForLevel($p2->level) }}" alt="Avatar" class="w-24 h-24 object-contain filter drop-shadow-[0_0_8px_rgba(0,0,0,0.15)] dark:drop-shadow-[0_0_8px_rgba(255,255,255,0.1)] group-hover:scale-105 transition-transform">
                                </div>

                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-gray-100 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 text-[10px] font-bold text-gray-700 dark:text-gray-300 mb-2 max-w-full truncate">
                                    <span>{{ $p2Ship['icon'] ?? '🚀' }}</span>
                                    <span class="truncate">{{ $p2Ship['name'] ?? 'Standard-Gleiter' }}</span>
                                </div>

                                @if($activeTab === 'funkenflug')
                                    <p class="text-indigo-600 dark:text-indigo-400/90 text-xs font-black mt-1">{{ number_format($p2->funkenflug_highscore, 0, ',', '.') }} m Distanz</p>
                                @else
                                    <p class="text-slate-500 text-xs font-black mt-1">{{ number_format($p2->funken_total_earned, 0, ',', '.') }} Funken</p>
                                @endif
                                <p class="text-[9px] text-amber-600 dark:text-amber-400 font-bold uppercase tracking-widest mt-2 opacity-0 group-hover:opacity-100 transition-opacity">Visitenkarte öffnen →</p>
                            </div>
                        @endif

                        {{-- PLATZ 1 (Gold) --}}
                        @php
                            $p1 = $top3->get(0);
                            $p1Ship = $this->getShipSkinForProfile($p1);
                            $p1Aura = $this->getHallAuraForProfile($p1);
                            $p1Prestige = $this->getPrestigeBadgeForLevel((int) $p1->level);
                            $p1Payload = $this->getInspectorPayload($p1, 1);
                        @endphp
                        <div @click="inspectedHero = @js($p1Payload)" class="cursor-pointer group w-full sm:w-1/3 order-1 sm:order-2 bg-white dark:bg-gradient-to-b dark:from-gray-900 dark:to-gray-950 border-2 border-amber-400 rounded-t-[3rem] rounded-b-2xl p-8 text-center relative overflow-hidden shadow-lg dark:shadow-[0_0_60px_rgba(251,191,36,0.25)] z-10 hover:scale-[1.02] transition-all {{ $p1Aura['row_classes'] ?? '' }} {{ $p1->customer_id === $myId ? 'ring-4 ring-amber-400/50' : '' }}">
                            <div class="absolute inset-0 bg-gradient-to-b from-amber-500/10 dark:from-amber-500/20 to-transparent pointer-events-none"></div>
                            <div class="absolute top-2 left-1/2 -translate-x-1/2 text-4xl drop-shadow-[0_0_15px_rgba(251,191,36,0.8)] z-20">👑</div>
                            <div class="w-20 h-20 mx-auto bg-amber-100 dark:bg-amber-900/50 rounded-full flex items-center justify-center text-amber-600 dark:text-amber-400 font-serif font-black text-4xl mb-3 mt-6 border-4 border-amber-400 shadow-md dark:shadow-[0_0_30px_rgba(251,191,36,0.5)] relative z-10">1</div>

                            @if(!empty($p1Aura['badge']))
                                <div class="mb-2 relative z-10">
                                    <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-amber-500 text-gray-900 shadow-sm">
                                        {{ $p1Aura['icon'] ?? '👑' }} {{ $p1Aura['badge'] }}
                                    </span>
                                </div>
                            @endif

                            <h3 class="text-gray-900 dark:text-white font-black text-xl md:text-2xl truncate drop-shadow-sm relative z-10 px-2">{{ $p1->customer?->first_name ?? 'Anonym' }} {{ $p1->customer?->last_name ? mb_substr($p1->customer->last_name, 0, 1) . '.' : '' }}</h3>
                            <p class="text-amber-600 dark:text-amber-400 text-[11px] sm:text-xs font-black uppercase tracking-[0.2em] mt-1.5 drop-shadow-sm relative z-10 truncate px-2">{{ $this->getActiveTitleName($p1) }}</p>
                            <div class="flex items-center justify-center gap-1.5 mt-1 relative z-10">
                                <span class="text-amber-600 dark:text-amber-500/90 text-xs font-black uppercase tracking-widest">Level {{ $p1->level }}</span>
                                @if($p1Prestige)
                                    <span class="text-xs" title="{{ $p1Prestige['name'] }}">{{ $p1Prestige['icon'] }}</span>
                                @endif
                            </div>

                            <p class="text-[10px] italic text-gray-500 dark:text-gray-400 mt-1.5 relative z-10 line-clamp-1">„{{ $this->getHeroMottoForProfile($p1) }}“</p>

                            <div class="flex justify-center my-5 relative z-10">
                                <img src="{{ $this->getAvatarForLevel($p1->level) }}" alt="Avatar" class="w-32 h-32 object-contain filter drop-shadow-[0_0_20px_rgba(251,191,36,0.4)] group-hover:scale-105 transition-transform">
                            </div>

                            <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-[10px] sm:text-xs font-black text-amber-700 dark:text-amber-300 mb-2 relative z-10 max-w-full truncate">
                                <span>{{ $p1Ship['icon'] ?? '🚀' }}</span>
                                <span class="truncate">{{ $p1Ship['name'] ?? 'Standard-Gleiter' }}</span>
                            </div>

                            @if($activeTab === 'funkenflug')
                                <p class="text-indigo-600 dark:text-indigo-400/90 text-xs font-black mt-1 relative z-10">{{ number_format($p1->funkenflug_highscore, 0, ',', '.') }} m Distanz gesamt!</p>
                            @else
                                <p class="text-amber-600 dark:text-amber-500/80 text-xs font-black mt-1 relative z-10">{{ number_format($p1->funken_total_earned, 0, ',', '.') }} Funken gesamt</p>
                            @endif
                            <p class="text-[9px] text-amber-600 dark:text-amber-400 font-black uppercase tracking-widest mt-2 relative z-10">🔍 Visitenkarte ansehen</p>
                        </div>

                        {{-- PLATZ 3 (Bronze) --}}
                        @if($top3->count() >= 3)
                            @php
                                $p3 = $top3->get(2);
                                $p3Ship = $this->getShipSkinForProfile($p3);
                                $p3Aura = $this->getHallAuraForProfile($p3);
                                $p3Prestige = $this->getPrestigeBadgeForLevel((int) $p3->level);
                                $p3Payload = $this->getInspectorPayload($p3, 3);
                            @endphp
                            <div @click="inspectedHero = @js($p3Payload)" class="cursor-pointer group w-full sm:w-1/3 order-3 sm:order-3 bg-white dark:bg-gray-900 border border-orange-300 dark:border-orange-700/30 rounded-t-[2rem] rounded-b-2xl p-6 text-center relative overflow-hidden shadow-sm dark:shadow-[0_0_40px_rgba(194,65,12,0.1)] transform sm:translate-y-12 hover:scale-[1.02] transition-all {{ $p3Aura['row_classes'] ?? '' }} {{ $p3->customer_id === $myId ? 'ring-2 ring-orange-500' : '' }}">
                                <div class="absolute inset-0 bg-gradient-to-b from-orange-700/10 to-transparent pointer-events-none"></div>
                                <div class="w-16 h-16 mx-auto bg-orange-100 dark:bg-orange-950 rounded-full flex items-center justify-center text-orange-600 dark:text-orange-400 font-serif font-black text-2xl mb-3 border-2 border-orange-400 dark:border-orange-700 shadow-inner">3</div>

                                @if(!empty($p3Aura['badge']))
                                    <div class="mb-2">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-amber-500/20 text-amber-600 dark:text-amber-300 border border-amber-400/40">
                                            {{ $p3Aura['icon'] ?? '✨' }} {{ $p3Aura['badge'] }}
                                        </span>
                                    </div>
                                @endif

                                <h3 class="text-gray-900 dark:text-white font-bold text-lg truncate">{{ $p3->customer?->first_name ?? 'Anonym' }} {{ $p3->customer?->last_name ? mb_substr($p3->customer->last_name, 0, 1) . '.' : '' }}</h3>
                                <p class="text-orange-600 dark:text-orange-400 text-[10px] font-black uppercase tracking-[0.2em] mt-1.5 drop-shadow-sm truncate px-2">{{ $this->getActiveTitleName($p3) }}</p>
                                <div class="flex items-center justify-center gap-1.5 mt-1">
                                    <span class="text-orange-600 dark:text-orange-500/80 text-xs font-black uppercase tracking-widest">Level {{ $p3->level }}</span>
                                    @if($p3Prestige)
                                        <span class="text-[10px]" title="{{ $p3Prestige['name'] }}">{{ $p3Prestige['icon'] }}</span>
                                    @endif
                                </div>
                                <div class="flex justify-center my-4">
                                    <img src="{{ $this->getAvatarForLevel($p3->level) }}" alt="Avatar" class="w-24 h-24 object-contain filter drop-shadow-[0_0_8px_rgba(0,0,0,0.15)] dark:drop-shadow-[0_0_8px_rgba(255,255,255,0.1)] group-hover:scale-105 transition-transform">
                                </div>

                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-gray-100 dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700 text-[10px] font-bold text-gray-700 dark:text-gray-300 mb-2 max-w-full truncate">
                                    <span>{{ $p3Ship['icon'] ?? '🚀' }}</span>
                                    <span class="truncate">{{ $p3Ship['name'] ?? 'Standard-Gleiter' }}</span>
                                </div>

                                @if($activeTab === 'funkenflug')
                                    <p class="text-indigo-600 dark:text-indigo-400/90 text-xs font-black mt-1">{{ number_format($p3->funkenflug_highscore, 0, ',', '.') }} m Distanz</p>
                                @else
                                    <p class="text-orange-600 dark:text-orange-500/70 text-xs font-black mt-1">{{ number_format($p3->funken_total_earned, 0, ',', '.') }} Funken</p>
                                @endif
                                <p class="text-[9px] text-amber-600 dark:text-amber-400 font-bold uppercase tracking-widest mt-2 opacity-0 group-hover:opacity-100 transition-opacity">Visitenkarte öffnen →</p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- LISTE PLATZ 4 BIS 50 --}}
                @if($rest->count() > 0)
                    <div class="bg-white dark:bg-gray-900/50 backdrop-blur-md rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                            <tr class="bg-gray-50 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">
                                <th class="px-4 sm:px-6 py-5 w-16 text-center">Rang</th>
                                <th class="px-4 sm:px-6 py-5">Legende & Motto</th>
                                <th class="px-4 sm:px-6 py-5 hidden md:table-cell">Flugschiff</th>
                                <th class="px-4 sm:px-6 py-5 text-center">Level</th>
                                <th class="px-4 sm:px-6 py-5 text-right">{{ $activeTab === 'funkenflug' ? 'Highscore (m)' : 'Verdiente Funken' }}</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/50">
                            @foreach($rest as $index => $r)
                                @php
                                    $actualRank = $index + 4;
                                    $rShip = $this->getShipSkinForProfile($r);
                                    $rAura = $this->getHallAuraForProfile($r);
                                    $rPrestige = $this->getPrestigeBadgeForLevel((int) $r->level);
                                    $rPayload = $this->getInspectorPayload($r, $actualRank);
                                @endphp
                                <tr @click="inspectedHero = @js($rPayload)" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-all {{ $rAura['row_classes'] ?? '' }} {{ $r->customer_id === $myId ? 'bg-primary/10 dark:bg-primary/5' : '' }}">
                                    <td class="px-4 sm:px-6 py-4 text-center">
                                        <span class="text-gray-500 font-mono font-bold text-sm">#{{ $actualRank }}</span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $this->getAvatarForLevel($r->level) }}" alt="Avatar" class="w-10 h-10 rounded-full object-contain bg-gray-100 dark:bg-gray-800 p-1 border border-gray-200 dark:border-gray-700 shrink-0 hidden sm:block">
                                            <div class="flex flex-col min-w-0">
                                                <div class="font-bold text-gray-900 dark:text-white text-sm sm:text-base flex flex-wrap items-center gap-2">
                                                    <span>{{ $r->customer?->first_name ?? 'Anonym' }} {{ $r->customer?->last_name ? mb_substr($r->customer->last_name, 0, 1) . '.' : '' }}</span>
                                                    @if($r->customer_id === $myId)
                                                        <span class="bg-primary/20 text-primary border border-primary/30 px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest">Du</span>
                                                    @endif
                                                    @if(!empty($rAura['badge']))
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-widest bg-amber-500/20 text-amber-600 dark:text-amber-300 border border-amber-400/40">
                                                            {{ $rAura['icon'] ?? '✨' }} {{ $rAura['badge'] }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                                    <span class="text-amber-600 dark:text-amber-400 text-[9px] font-black uppercase tracking-widest">{{ $this->getActiveTitleName($r) }}</span>
                                                    <span class="text-gray-400 dark:text-gray-600 hidden sm:inline">•</span>
                                                    <span class="text-[10px] italic text-gray-500 dark:text-gray-400 truncate max-w-xs hidden sm:inline">„{{ $this->getHeroMottoForProfile($r) }}“</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[10px] font-bold text-gray-700 dark:text-gray-300">
                                            <span>{{ $rShip['icon'] ?? '🚀' }}</span>
                                            <span>{{ $rShip['name'] ?? 'Standard-Gleiter' }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-center">
                                        <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-1 rounded-lg text-xs font-black text-gray-800 dark:text-white shadow-inner">
                                            <span>{{ $r->level }}</span>
                                            @if($rPrestige)
                                                <span title="{{ $rPrestige['name'] }}">{{ $rPrestige['icon'] }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-right text-gray-700 dark:text-gray-300 font-mono font-bold text-xs sm:text-sm">
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

                {{-- HELDEN-VISITENKARTE (PLAYER INSPECTOR MODAL) --}}
                <template x-teleport="body">
                    <div x-show="inspectedHero !== null" x-cloak class="fixed inset-0 z-[7000] flex items-center justify-center p-4 sm:p-6">
                        <div class="absolute inset-0 bg-black/75 backdrop-blur-md" @click="inspectedHero = null" x-transition.opacity></div>

                        <div x-show="inspectedHero !== null"
                             x-transition:enter="transition ease-out duration-250"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             class="relative w-full max-w-xl bg-white dark:bg-gray-900 border-2 border-amber-500/40 rounded-[2.5rem] p-6 sm:p-8 shadow-2xl dark:shadow-[0_0_80px_rgba(245,158,11,0.25)] overflow-hidden z-10 text-gray-800 dark:text-gray-200">

                            <div class="absolute top-0 right-0 w-56 h-56 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                            <button type="button" @click="inspectedHero = null" class="absolute top-5 right-5 w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-red-500 hover:text-white text-gray-500 flex items-center justify-center transition-colors">
                                ✕
                            </button>

                            <template x-if="inspectedHero">
                                <div>
                                    {{-- KOPFBEREICH DER VISITENKARTE --}}
                                    <div class="flex flex-col sm:flex-row items-center gap-5 pb-6 border-b border-gray-200 dark:border-gray-800">
                                        <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-gray-100 dark:bg-gray-950 border-2 border-amber-400 p-2 flex items-center justify-center shrink-0 shadow-md">
                                            <img :src="inspectedHero.avatar_url" alt="Funki Avatar" class="w-full h-full object-contain">
                                            <span class="absolute -bottom-2 px-3 py-0.5 rounded-full bg-amber-500 text-gray-900 font-black text-[10px] uppercase tracking-widest shadow" x-text="'Platz #' + inspectedHero.rank"></span>
                                        </div>

                                        <div class="text-center sm:text-left min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1">
                                                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-[10px] font-black uppercase tracking-widest" x-text="'Level ' + inspectedHero.level"></span>
                                                <span class="px-2.5 py-0.5 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-600 dark:text-purple-300 text-[10px] font-black uppercase tracking-widest" x-text="inspectedHero.prestige_icon + ' ' + inspectedHero.prestige_name"></span>
                                                <template x-if="inspectedHero.aura_badge">
                                                    <span class="px-2.5 py-0.5 rounded-full bg-amber-500 text-gray-900 text-[10px] font-black uppercase tracking-widest" x-text="inspectedHero.aura_icon + ' ' + inspectedHero.aura_badge"></span>
                                                </template>
                                            </div>

                                            <h3 class="text-2xl font-serif font-black text-gray-900 dark:text-white truncate" x-text="inspectedHero.display_name"></h3>
                                            <p class="text-xs font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 mt-0.5" x-text="inspectedHero.active_title"></p>
                                            <p class="text-xs italic text-gray-500 dark:text-gray-400 mt-2" x-text="'„' + inspectedHero.motto + '“'"></p>
                                        </div>
                                    </div>

                                    {{-- AKTIVES RAUMSCHIFF & STATISTIKEN --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 my-5">
                                        <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/90 border border-amber-500/30">
                                            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Ausgerüstetes Raumschiff</p>
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-2xl" x-text="inspectedHero.ship_icon"></span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-black text-gray-900 dark:text-white truncate" x-text="inspectedHero.ship_name"></p>
                                                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold truncate" x-text="inspectedHero.ship_perk"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2.5">
                                            <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950/90 border border-gray-200 dark:border-gray-800 text-center flex flex-col justify-center">
                                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Gesamtfunken</p>
                                                <p class="text-sm sm:text-base font-black text-amber-600 dark:text-amber-400 mt-1" x-text="inspectedHero.funken_total + ' ✨'"></p>
                                            </div>
                                            <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950/90 border border-gray-200 dark:border-gray-800 text-center flex flex-col justify-center">
                                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Rekordflug</p>
                                                <p class="text-sm sm:text-base font-black text-indigo-600 dark:text-indigo-400 mt-1" x-text="inspectedHero.highscore + ' m'"></p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- ERRRUNGENE TROPHÄEN --}}
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2.5">Errungene Manufaktur-Trophäen</p>
                                        <template x-if="inspectedHero.trophies && inspectedHero.trophies.length > 0">
                                            <div class="flex flex-wrap gap-2">
                                                <template x-for="(t, idx) in inspectedHero.trophies" :key="idx">
                                                    <span class="px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider border flex items-center gap-1.5"
                                                          :class="t.tier === 'Diamant' ? 'bg-cyan-500/15 border-cyan-400/40 text-cyan-600 dark:text-cyan-300' : (t.tier === 'Gold' ? 'bg-amber-500/15 border-amber-400/40 text-amber-600 dark:text-amber-300' : 'bg-slate-500/15 border-slate-400/40 text-slate-600 dark:text-slate-300')">
                                                        <span x-text="t.tier === 'Diamant' ? '💎' : (t.tier === 'Gold' ? '🏆' : '🥈')"></span>
                                                        <span x-text="t.title_name + ' (' + t.tier + ')'"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="!inspectedHero.trophies || inspectedHero.trophies.length === 0">
                                            <p class="text-xs text-gray-400 italic">Noch keine Silber-, Gold- oder Diamant-Trophäen freigeschaltet.</p>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

            </div>
        @endif
    </div>
</div>
