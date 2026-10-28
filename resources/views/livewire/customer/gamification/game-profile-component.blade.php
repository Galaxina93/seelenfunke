<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 animate-fade-in-up min-h-full flex flex-col relative z-10" x-data="funkiHub('{{$modelPath}}', '{{$imagePath}}', {{$level}})" x-init="initFunki()" @funki-level-up.window="handleLevelUp($event.detail)">
    {{-- OPT-IN SCREEN REMOVED, WE REDIRECT IN MOUNT IF NOT OPTED IN --}}

    {{-- SEITEN-HEADER MIT SUB-TABS --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-6">
        <div>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Deine Evolution</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-xs sm:text-sm uppercase tracking-widest font-bold">Funki-Hub, Level & Titel verwalten</p>
        </div>

        {{-- SPIELE SUB-TABS --}}
        <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-fit shrink-0">
            <a href="{{ route('customer.games') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/50 dark:hover:bg-gray-800/50">
                <span>🧩</span>
                <span>Spiele</span>
            </a>
            <a href="{{ route('customer.gamification.profile') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all bg-primary text-gray-900 shadow-sm">
                <span>✨</span>
                <span>Funki-Profil</span>
            </a>
            <a href="{{ route('customer.ranking') }}" class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200/50 dark:hover:bg-gray-800/50">
                <span>🏆</span>
                <span>Rangliste</span>
            </a>
        </div>
    </div>


                <div class="relative mb-36 sm:mb-44 flex flex-col items-center mt-8 w-full max-w-[18rem] sm:max-w-md md:max-w-lg lg:max-w-xl mx-auto">
                    @php
                        $megaRank = $titlesData['mega_title']['rank'] ?? 0;
                        $megaShadow = match(true) {
                            $megaRank == 1 => 'shadow-md dark:shadow-[0_0_20px_rgba(255,255,255,0.05)]',
                            $megaRank == 2 || $megaRank == 3 => 'shadow-md dark:shadow-[0_0_40px_rgba(59,130,246,0.2)] border-blue-500/30',
                            $megaRank == 4 || $megaRank == 5 => 'shadow-md dark:shadow-[0_0_60px_rgba(250,204,21,0.4)] border-amber-500/50',
                            $megaRank == 6 => 'shadow-md dark:shadow-[0_0_80px_rgba(34,211,238,0.6)] border-cyan-400 ring-4 ring-cyan-400/30',
                            default => 'shadow-md dark:shadow-[0_0_30px_rgba(0,0,0,0.8)]'
                        };
                        $levelBorder = match(true) {
                            $level < 4 => 'border-2 border-gray-300 dark:border-gray-800',
                            $level < 7 => 'border-[3px] border-gray-400 dark:border-gray-600',
                            $level < 10 => 'border-[3px] border-primary',
                            $level >= 10 => 'border-[4px] border-amber-400',
                            default => 'border-2 border-gray-300 dark:border-gray-800'
                        };
                    @endphp

                    <button @click.stop="showTitlesModal=true" class="absolute -top-10 left-1/2 -translate-x-1/2 bg-white dark:bg-gray-900 border border-primary px-6 py-2.5 sm:px-10 sm:py-3 rounded-full font-black uppercase tracking-widest text-[10px] sm:text-xs text-gray-900 dark:text-white shadow-md dark:shadow-[0_0_25px_rgba(197,160,89,0.6)] flex flex-col items-center gap-1 hover:bg-primary hover:text-gray-900 transition-all z-40 hover:scale-110 group cursor-pointer whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            {{ $currentRankName ?: 'Kein Rang' }}
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 group-hover:rotate-90 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            </svg>
                        </div>
                    </button>

                    <div class="relative w-64 h-64 sm:w-80 sm:h-80 md:w-[26rem] md:h-[26rem] flex items-center justify-center shrink-0">
                        @if($megaRank >= 4)
                            <div class="absolute inset-[-4rem] bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-amber-400/20 via-transparent to-transparent blur-3xl pointer-events-none animate-pulse"></div>
                        @endif
                        @if($megaRank == 6)
                            <div class="absolute inset-[-6rem] bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-cyan-400/20 via-transparent to-transparent blur-3xl pointer-events-none animate-pulse duration-75"></div>
                        @endif
                        @if($level >= 4)
                            <div class="absolute inset-0 rounded-full border border-gray-400/40 dark:border-gray-600/50 animate-[spin_20s_linear_infinite] pointer-events-none"></div>
                        @endif
                        @if($level >= 7)
                            <div class="absolute inset-[-1rem] rounded-full border-[2px] border-primary/40 border-dashed animate-[spin_15s_linear_reverse_infinite] pointer-events-none"></div>
                        @endif
                        @if($level >= 10)
                            <div class="absolute inset-[-2rem] rounded-full border-[3px] border-amber-400/60 border-dotted animate-[spin_10s_linear_infinite] pointer-events-none"></div>
                        @endif

                        <svg class="absolute inset-0 w-full h-full -rotate-90 drop-shadow-none dark:drop-shadow-[0_0_20px_rgba(197,160,89,0.5)] pointer-events-none z-20" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#e2e8f0" class="dark:stroke-gray-800" stroke-width="1.5"></circle>
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#c5a059" stroke-width="3" stroke-linecap="round" stroke-dasharray="301.59" stroke-dashoffset="{{301.59 - ($progressPercentage / 100) * 301.59}}" class="transition-all duration-1000 ease-out"></circle>
                        </svg>

                        <button @click="open3DModal()" class="absolute inset-3 sm:inset-4 md:inset-5 rounded-full bg-white dark:bg-gray-900 {{$levelBorder}} {{$megaShadow}} flex items-center justify-center overflow-hidden z-10 group transition-all duration-700 hover:scale-[1.02]">
                            <div class="absolute inset-0 bg-primary/10 rounded-full blur-[60px] md:blur-[80px] group-hover:bg-primary/30 transition-colors duration-700 pointer-events-none"></div>
                            <img :src="currentImagePath ? currentImagePath : '{{$imagePath}}'" src="{{$imagePath}}" class="w-full h-full object-contain p-4 sm:p-8 animate-subtle-float">
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-sm z-20 pointer-events-none">
                                <span class="bg-primary text-gray-900 px-6 py-3 md:px-8 md:py-4 rounded-xl font-black text-[10px] sm:text-xs uppercase tracking-widest shadow-2xl whitespace-nowrap">3D Modell öffnen</span>
                            </div>
                        </button>

                        <div class="absolute top-6 -left-6 sm:top-12 sm:-left-10 md:top-16 md:-left-12 z-30 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border border-blue-500/30 px-3 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-sm dark:shadow-[0_0_20px_rgba(59,130,246,0.3)] flex items-center gap-2 sm:gap-3 hover:scale-105 transition-transform cursor-default">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-blue-500/20 flex items-center justify-center shrink-0">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-blue-500 dark:text-blue-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="text-left hidden sm:block">
                                <p class="text-[8px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest leading-none">Energie</p>
                                <p class="text-gray-900 dark:text-white font-bold text-xs sm:text-sm mt-1 leading-none">{{$energyBalance}}/{{$maxEnergy}}</p>
                            </div>
                            <div class="text-left sm:hidden">
                                <p class="text-gray-900 dark:text-white font-bold text-xs leading-none">{{$energyBalance}}/{{$maxEnergy}}</p>
                            </div>
                        </div>

                        <div class="absolute top-6 -right-6 sm:top-12 sm:-right-10 md:top-16 md:-right-12 z-30 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border border-primary/30 px-3 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-sm dark:shadow-[0_0_20px_rgba(197,160,89,0.3)] flex items-center gap-2 sm:gap-3 hover:scale-105 transition-transform cursor-default">
                            <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-primary/20 flex items-center justify-center shrink-0">
                                <span class="text-xs sm:text-sm">✨</span>
                            </div>
                            <div class="text-left hidden sm:block">
                                <p class="text-[8px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest leading-none">Guthaben</p>
                                <p class="text-gray-900 dark:text-white font-bold text-xs sm:text-sm mt-1 leading-none">{{$balance}} F.</p>
                            </div>
                            <div class="text-left sm:hidden">
                                <p class="text-gray-900 dark:text-white font-bold text-xs leading-none">{{$balance}} F.</p>
                            </div>
                        </div>

                        <div class="absolute -bottom-12 sm:-bottom-16 md:-bottom-20 left-1/2 -translate-x-1/2 z-30 flex flex-col items-center w-[130%] sm:w-[150%] md:w-max">
                            <div class="bg-gradient-to-r from-primary-light to-primary text-gray-900 border border-primary/40 px-8 py-2.5 sm:px-12 sm:py-3 rounded-full font-black text-[10px] sm:text-sm uppercase tracking-[0.2em] shadow-sm dark:shadow-[0_10px_20px_rgba(0,0,0,0.6)]">
                                Level {{$level}}
                            </div>
                            @if(!$isMaxLevel)
                                <div class="mt-3 sm:mt-4 w-full bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-200 dark:border-gray-800 rounded-xl p-3 sm:p-4 text-center shadow-sm dark:shadow-2xl">
                                    <p class="text-[9px] sm:text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        <span class="text-amber-700 dark:text-primary font-bold">{{$progressPercentage}}%</span> - Noch <span class="text-gray-900 dark:text-white font-bold">{{$missingSparks}}</span> Funken
                                    </p>
                                    @if($canUpgrade)
                                        <button wire:click="upgrade" wire:loading.attr="disabled" class="mt-3 sm:mt-4 w-full py-2.5 sm:py-3 bg-primary text-gray-900 rounded-lg text-[9px] sm:text-[10px] font-black uppercase tracking-widest hover:bg-white hover:text-gray-900 transition-colors shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.4)] animate-[pulse_2s_infinite]">
                                            <span wire:loading.remove wire:target="upgrade">Level Up durchführen!</span>
                                            <span wire:loading wire:target="upgrade">Wirkt Magie...</span>
                                        </button>
                                    @endif
                                </div>
                            @else
                                <div class="mt-3 sm:mt-4 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 px-6 py-3 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest shadow-sm">
                                    Maximales Prestige-Level (100)
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-center gap-3 sm:gap-4 relative z-50 w-full sm:w-auto mt-4 sm:mt-0">
                    <a href="{{route('customer.games')}}" class="w-full sm:w-auto px-6 py-4 sm:px-8 bg-emerald-500 text-gray-900 rounded-xl font-black text-xs sm:text-sm hover:bg-emerald-400 transition-all uppercase tracking-widest shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:-translate-y-1 flex items-center justify-center gap-2">
                        <span class="text-lg sm:text-xl">🎮</span> Jetzt spielen
                    </a>
                    <a href="{{route('customer.ranking')}}" class="w-full sm:w-auto px-6 py-4 sm:px-8 bg-white dark:bg-gray-800 border border-amber-500/30 text-amber-600 dark:text-amber-400 rounded-xl font-black text-xs sm:text-sm hover:bg-amber-400 hover:text-gray-900 transition-all uppercase tracking-widest shadow-sm dark:shadow-[0_0_20px_rgba(251,191,36,0.15)] hover:-translate-y-1 flex items-center justify-center gap-2">
                        <span class="text-lg sm:text-xl">🏆</span> Rangliste
                    </a>
                </div>

                {{-- ERKLÄRUNG GAMIFICATION --}}
                <div class="w-full max-w-4xl mx-auto mt-16 sm:mt-24 text-left animate-fade-in-up">
                    <div class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border border-primary/30 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-[0_0_30px_rgba(197,160,89,0.1)] relative overflow-hidden">
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent pointer-events-none"></div>
                        <div class="relative z-10">
                            <h3 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-3">
                                <span class="text-primary text-3xl">✨</span> Was passiert hier eigentlich?
                            </h3>
                            <div class="space-y-4 text-gray-600 dark:text-gray-400 text-sm sm:text-base leading-relaxed">
                                <p>
                                    Schön, dass du dabei bist! Dieser Bereich ist weit mehr als nur eine Kundenübersicht – es ist dein
                                    <strong class="text-gray-900 dark:text-white">persönliches Abenteuer</strong> in unserer Manufaktur der Magie. Wir glauben, dass Treue
                                    belohnt werden sollte, und zwar auf eine Art, die Spaß macht.
                                </p>
                                <p>
                                    Dein kleiner 3D-Gefährte (wir nennen ihn liebevoll "Funki") begleitet dich auf deiner Reise.
                                    Mit jeder Bestellung, jeder Interaktion und jedem gelösten Rätsel sammelst du <strong class="text-primary">Funken</strong>.
                                    Diese Funken lassen deinen Funki wachsen, neue Level erreichen und sich visuell weiterentwickeln!
                                </p>
                                <ul class="list-none space-y-3 mt-6 mb-6">
                                    <li class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-emerald-500 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span><strong class="text-gray-900 dark:text-white">Level-Ups & Belohnungen:</strong> Erreichst du bestimmte Meilensteine, schaltest du exklusive Gutscheine, echte Rabatte und Überraschungen frei.</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
                                        <span><strong class="text-gray-900 dark:text-white">Titel & Ränge:</strong> Verdiene prestigeträchtige Titel und präsentiere sie in der globalen Rangliste. Zeig allen, dass du ein Meister der Manufaktur bist!</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-blue-500 dark:text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span><strong class="text-gray-900 dark:text-white">Minispiele:</strong> Nutze deine gesammelte Energie, um in kleinen Spielen weitere Funken zu gewinnen oder einfach die Seele baumeln zu lassen.</span>
                                    </li>
                                </ul>
                                <p>
                                    Kurz gesagt: Jeder Einkauf bringt dich weiter. Schau regelmäßig vorbei, beobachte die Evolution deines Funkis und
                                    sichere dir die Schätze, die auf deinem Weg liegen. Viel Spaß beim Entdecken!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-full max-w-4xl mx-auto mt-16 sm:mt-24 text-left animate-fade-in-up">
                    <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4 gap-2">
                        <h3 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white flex items-center gap-3">
                            <span class="text-primary drop-shadow-[0_0_10px_rgba(197,160,89,0.6)]">🎁</span> Pfad zur Legende
                        </h3>
                        <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 font-black uppercase tracking-[0.2em]">Meilensteine & Belohnungen</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                        @foreach($milestonesConfig as $mLevel => $reward)
                            @php
                                $isUnlocked = $level >= $mLevel;
                                $couponData = $unlockedCoupons['lvl_' . $mLevel] ?? null;
                                $code = $couponData['code'] ?? null;
                                $isUsed = $couponData['is_used'] ?? false;
                            @endphp
                            <div class="relative border rounded-2xl p-5 flex flex-col items-center text-center transition-all duration-500 overflow-hidden group {{ $isUsed ? 'bg-gray-100 dark:bg-gray-950 border-red-200 dark:border-red-900/30 opacity-70 shadow-inner' : ($isUnlocked ? 'bg-white dark:bg-gray-900 border-primary/50 shadow-sm dark:shadow-[0_0_25px_rgba(197,160,89,0.15)] hover:-translate-y-1' : 'bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-800 opacity-60 grayscale') }}">
                                @if($isUnlocked && !$isUsed)
                                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--tw-gradient-stops))] from-primary/10 via-transparent to-transparent pointer-events-none"></div>
                                @endif
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4 font-black text-lg z-10 transition-colors {{ $isUsed ? 'bg-gray-200 dark:bg-gray-900 text-red-400 dark:text-red-500/50 border border-red-300 dark:border-red-900/30' : ($isUnlocked ? 'bg-primary text-gray-900 shadow-sm dark:shadow-[0_0_15px_rgba(197,160,89,0.5)]' : 'bg-gray-200 dark:bg-gray-800 text-gray-500 dark:text-gray-500 border border-gray-300 dark:border-gray-700') }}">
                                    {{$mLevel}}
                                </div>
                                <h4 class="font-bold text-sm mb-1 z-10 transition-colors {{ $isUsed ? 'text-gray-400 dark:text-gray-600 line-through' : 'text-gray-900 dark:text-white' }}">{{$reward['name']}}</h4>
                                <p class="text-[10px] font-medium mb-4 z-10 {{ $isUsed ? 'text-gray-400 dark:text-gray-600' : 'text-gray-500 dark:text-gray-400' }}">Erreiche Level {{$mLevel}}</p>

                                <div class="w-full mt-auto z-10">
                                    @if($isUnlocked)
                                        @if($isUsed)
                                            <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/50 rounded-lg p-2 text-center">
                                                <p class="text-red-500 dark:text-red-500/80 font-black text-[10px] uppercase tracking-[0.2em] flex items-center justify-center gap-2">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Eingelöst
                                                </p>
                                            </div>
                                        @elseif($code)
                                            <div x-data="{ copied: false }" class="bg-gray-50 dark:bg-gray-950 border border-primary/30 rounded-lg p-2 group-hover:border-primary transition-colors flex items-center justify-between gap-2 overflow-hidden shadow-inner">
                                                <div class="text-left flex-1 min-w-0">
                                                    <p class="text-[8px] text-gray-500 dark:text-gray-500 font-black uppercase tracking-widest mb-0.5 truncate">Code (1x gültig)</p>
                                                    <p class="text-primary font-mono font-bold text-xs sm:text-sm tracking-wider truncate">{{$code}}</p>
                                                </div>
                                                <button type="button" @click="navigator.clipboard.writeText('{{$code}}'); copied = true; setTimeout(() => copied = false, 2000)" class="shrink-0 p-2 bg-primary/10 text-primary rounded-md hover:bg-primary hover:text-gray-900 transition-colors focus:outline-none flex items-center justify-center h-full w-10" title="Code kopieren">
                                                    <svg x-show="!copied" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                    <svg x-show="copied" style="display: none;" class="w-4 h-4 text-emerald-500 drop-shadow-[0_0_5px_rgba(16,185,129,0.8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        @else
                                            <div class="bg-gray-50 dark:bg-gray-950 border border-primary/30 rounded-lg p-2">
                                                <p class="text-[8px] text-gray-500 font-black uppercase tracking-widest mb-1">Status</p>
                                                <p class="text-primary font-mono font-bold text-[10px] tracking-wider">WIRD GENERIERT...</p>
                                            </div>
                                        @endif
                                    @else
                                        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-3">
                                            <p class="text-gray-500 font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-2">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2-2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                Gesperrt
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- PRESTIGE-AUFSTIEG (LEVEL 11 - 100) --}}
                    <div class="mt-12 bg-white/90 dark:bg-gray-900/80 border border-amber-500/30 rounded-3xl p-6 sm:p-8 shadow-sm dark:shadow-[0_0_30px_rgba(245,158,11,0.1)]">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-gray-200 dark:border-gray-800">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">👑</span>
                                    <h4 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white">Prestige-Aufstieg (Level 11 bis 100)</h4>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Ab Level 10 hat Funki seine vollkommene 3D-Gottform erreicht. Dein Aufstieg geht jedoch weiter bis <strong>Level 100</strong> und schaltet exklusive Prestige-Embleme für die Halle der Legenden frei!
                                </p>
                            </div>
                            <span class="px-3.5 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-[10px] font-black uppercase tracking-widest shrink-0">
                                Dein Level: {{ $level }} / 100
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3.5">
                            @foreach(($prestigeMilestones ?? []) as $pLevel => $pInfo)
                                @php $pUnlocked = $level >= $pLevel; @endphp
                                <div class="rounded-2xl border p-4 flex flex-col items-center text-center transition-all {{ $pUnlocked ? 'bg-amber-500/10 border-amber-400/50 shadow-sm dark:shadow-[0_0_20px_rgba(245,158,11,0.15)]' : 'bg-gray-50 dark:bg-gray-950/60 border-gray-200 dark:border-gray-800 opacity-65' }}">
                                    <div class="text-2xl mb-2">{{ $pInfo['icon'] }}</div>
                                    <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full mb-1.5 {{ $pUnlocked ? 'bg-amber-500 text-gray-900' : 'bg-gray-200 dark:bg-gray-800 text-gray-500' }}">
                                        Level {{ $pLevel }}
                                    </span>
                                    <h5 class="font-black text-xs text-gray-900 dark:text-white mb-1">{{ $pInfo['name'] }}</h5>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-snug mb-3">{{ $pInfo['reward'] }}</p>
                                    <span class="mt-auto text-[9px] font-black uppercase tracking-widest {{ $pUnlocked ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                        {{ $pUnlocked ? '✓ Freigeschaltet' : '🔒 Noch ' . max(0, $pLevel - $level) . ' Lvl' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 3D-SEELEN-HANGAR, RUHMES-AUREN & HELDEN-MOTTO --}}
                <div class="w-full max-w-5xl mx-auto mt-16 sm:mt-24 text-left animate-fade-in-up">
                    <div class="bg-white/90 dark:bg-gray-900/90 backdrop-blur-xl border border-amber-500/30 rounded-3xl p-6 sm:p-10 shadow-sm dark:shadow-[0_0_40px_rgba(245,158,11,0.12)]">
                        @include('livewire.customer.partials.games-component.seelen-hangar-panel', ['showBackButton' => false])
                    </div>
                </div>

            <template x-teleport="body">
                <div x-show="show3DModal" style="display: none;" class="fixed inset-0 z-[6000] flex flex-col items-center justify-between p-3 sm:p-8">
                    <div class="absolute inset-0 bg-stone-900/45 backdrop-blur-md transition-opacity duration-200" @click="close3DModal()" x-transition.opacity></div>

                    <div class="relative w-full max-w-[90rem] flex-1 flex flex-col bg-gradient-to-b from-[#fffdf9] via-[#fdf7ea] to-[#f3e6ce] rounded-[2rem] sm:rounded-[3rem] shadow-[0_30px_90px_rgba(160,115,40,0.28)] border-2 border-amber-500/40 overflow-hidden cursor-default"
                         style="will-change: transform, opacity; transform: translateZ(0);"
                         x-transition:enter="transition ease-out duration-250"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95">

                        {{-- HELLER MANUFAKTUR-ATELIER LICHTSCHEIN & BODEN-REFLEXION --}}
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_42%,_rgba(251,191,36,0.28)_0%,_rgba(245,158,11,0.10)_42%,_rgba(255,255,255,0)_72%)] pointer-events-none"></div>
                        <div class="absolute inset-x-0 bottom-16 h-48 bg-[radial-gradient(ellipse_at_50%_100%,_rgba(197,160,89,0.22)_0%,_rgba(255,255,255,0)_70%)] pointer-events-none"></div>

                        {{-- 60 FPS GPU-PARTIKEL-, LICHTSTRAHL- & SCHOCKWELLEN-CANVAS --}}
                        <canvas id="funki-levelup-fx-canvas" class="absolute inset-0 w-full h-full z-[6045] pointer-events-none" wire:ignore></canvas>

                        {{-- AAA LEVEL-UP BANNER & BELOHNUNGS-EMBLEM (LÄSST FUNKI IN DER MITTE FREI SICHTBAR!) --}}
                        <div x-show="showConfetti"
                             x-transition:enter="transition ease-out duration-400"
                             x-transition:enter-start="opacity-0 scale-90 -translate-y-4"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-300"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute inset-x-0 top-20 sm:top-24 z-[6060] pointer-events-none flex flex-col items-center px-4"
                             style="display: none;">
                            <div class="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-amber-500/15 border border-amber-500/50 text-amber-800 text-[10px] sm:text-xs font-black uppercase tracking-[0.3em] mb-2 shadow-[0_8px_25px_rgba(245,158,11,0.25)]">
                                <span>⚡</span>
                                <span>Aufstieg Vollbracht</span>
                                <span>⚡</span>
                            </div>
                            <h2 class="text-4xl sm:text-6xl md:text-7xl font-serif font-black text-transparent bg-clip-text bg-gradient-to-b from-amber-500 via-amber-600 to-yellow-800 drop-shadow-[0_6px_25px_rgba(245,158,11,0.45)] tracking-tight text-center">
                                LEVEL <span x-text="activeModelLevel || {{$level}}"></span>
                            </h2>
                            <div x-show="rewardMessage" class="mt-3 px-6 py-2.5 rounded-2xl bg-white/95 border-2 border-amber-400 shadow-[0_12px_35px_rgba(245,158,11,0.3)] flex items-center gap-2.5">
                                <span class="text-xl">🎁</span>
                                <p class="text-amber-800 text-xs sm:text-base font-black uppercase tracking-widest text-center" x-text="rewardMessage"></p>
                            </div>
                        </div>

                        {{-- TOP LEFT: LEVEL & RANG BADGE (HELLMODUS) --}}
                        <div class="absolute top-4 left-4 sm:top-7 sm:left-7 z-[6050] flex items-center gap-3 sm:gap-4 bg-white/90 backdrop-blur-md border border-amber-500/40 rounded-[2rem] p-2 pr-4 sm:p-3.5 sm:pr-7 shadow-[0_10px_30px_rgba(197,160,89,0.2)] pointer-events-auto">
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-b from-amber-300 to-amber-500 border-2 border-amber-200 flex items-center justify-center shadow-[0_4px_15px_rgba(245,158,11,0.45)] shrink-0">
                                <span class="text-gray-950 font-black text-lg sm:text-xl" x-text="activeModelLevel || {{$level}}">{{$level}}</span>
                            </div>
                            <div>
                                <p class="text-[8px] sm:text-[9px] text-amber-700 font-black uppercase tracking-widest">Seelen-Evolution</p>
                                <h3 class="text-gray-900 font-black text-[10px] sm:text-xs uppercase tracking-[0.2em]">{{ $currentRankName ?: 'Kein Rang' }}</h3>
                            </div>
                        </div>

                        {{-- TOP RIGHT: AUFSTIEGS-MAGIE (NUR KLICKBAR WENN LEVEL-UP MÖGLICH) & SCHLIESSEN --}}
                        <div class="absolute top-4 right-4 sm:top-7 sm:right-7 z-[6050] flex items-center gap-2.5 pointer-events-auto">
                            @if($canUpgrade)
                                <button type="button"
                                        wire:click="upgrade"
                                        wire:loading.attr="disabled"
                                        title="Jetzt auf Level {{ $level + 1 }} aufsteigen!"
                                        class="px-4 py-2.5 sm:px-5 sm:py-3 rounded-full bg-gradient-to-r from-amber-400 via-primary to-amber-500 hover:from-amber-300 hover:to-amber-400 text-gray-950 border border-amber-300 font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all shadow-[0_8px_25px_rgba(245,158,11,0.45)] hover:scale-105 flex items-center gap-1.5 cursor-pointer animate-[pulse_2s_infinite]">
                                    <span>✨</span>
                                    <span wire:loading.remove wire:target="upgrade" class="hidden sm:inline">Aufstiegs-Magie</span>
                                    <span wire:loading.remove wire:target="upgrade" class="sm:hidden">Level Up!</span>
                                    <span wire:loading wire:target="upgrade">Wirkt Magie...</span>
                                </button>
                            @else
                                <button type="button"
                                        disabled
                                        title="{{ $isMaxLevel ? 'Maximales Prestige-Level (100) erreicht' : 'Noch ' . $missingSparks . ' Funken bis zum nächsten Level-Aufstieg' }}"
                                        class="px-3.5 py-2.5 sm:px-4 sm:py-3 rounded-full bg-white/80 text-gray-400 border border-gray-300/80 font-black text-[10px] sm:text-xs uppercase tracking-widest transition-all shadow-sm flex items-center gap-1.5 cursor-not-allowed opacity-75 select-none">
                                    <span>🔒</span>
                                    <span class="hidden sm:inline">Aufstiegs-Magie</span>
                                    @if(!$isMaxLevel)
                                        <span class="text-[9px] font-bold text-amber-800/75 bg-amber-500/10 px-2 py-0.5 rounded-full border border-amber-500/25">Noch {{ $missingSparks }} F.</span>
                                    @endif
                                </button>
                            @endif
                            <button @click="close3DModal()" class="p-2.5 sm:p-3 bg-white/90 border border-gray-200 rounded-full text-gray-600 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-md hover:scale-110 cursor-pointer">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="flex-1 w-full relative min-h-0 pt-20 pb-4 cursor-default">
                            <style>
                                #funki-3d-canvas-container,
                                #funki-3d-canvas-container canvas {
                                    cursor: grab !important;
                                }
                                #funki-3d-canvas-container:active canvas {
                                    cursor: grabbing !important;
                                }
                            </style>
                            <div id="funki-3d-canvas-container" class="absolute inset-0 w-full h-full z-10" wire:ignore></div>
                        </div>

                        <div class="w-full bg-white/90 backdrop-blur-xl border-t border-amber-500/25 relative z-[6050] pb-safe shadow-[0_-10px_35px_rgba(197,160,89,0.1)]">
                            <div class="flex flex-row items-center justify-start sm:justify-center gap-4 sm:gap-7 px-4 sm:px-10 py-4 sm:py-5 overflow-x-auto no-scrollbar scroll-smooth w-full">
                                @php
                                    $milestones = \App\Services\Gamification\GameConfig::getAppearanceMilestones();
                                @endphp
                                @foreach($milestones as $mLevel => $mName)
                                    <div wire:key="milestone-{{$mLevel}}" class="flex flex-col items-center gap-2 relative group shrink-0">
                                        <button type="button"
                                                @if($level >= $mLevel)
                                                    @click="selectEvolutionStage({{$mLevel}}, '{{asset('shop/customer/gamification/models/' . $mName . '.glb')}}', '{{asset('shop/customer/gamification/models/images/original/' . $mName . '.png')}}')"
                                                @endif
                                                :class="{
                                                    'border-[#C5A059] bg-amber-50 shadow-[0_8px_25px_rgba(197,160,89,0.45)] scale-110 ring-4 ring-[#C5A059]/40 z-10': (typeof $store !== 'undefined' && $store.funki ? $store.funki.activeLevel : (typeof activeModelLevel !== 'undefined' ? activeModelLevel : {{min(10, $level)}})) == {{$mLevel}},
                                                    'border-amber-500/35 bg-white opacity-85 hover:opacity-100 hover:scale-105 hover:border-[#C5A059] cursor-pointer shadow-sm': (typeof $store !== 'undefined' && $store.funki ? $store.funki.activeLevel : (typeof activeModelLevel !== 'undefined' ? activeModelLevel : {{min(10, $level)}})) != {{$mLevel}} && {{$level >= $mLevel ? 'true' : 'false'}},
                                                    'border-gray-200 bg-gray-100 cursor-not-allowed opacity-50': {{$level < $mLevel ? 'true' : 'false'}}
                                                }"
                                                class="w-14 h-14 sm:w-18 sm:h-18 shrink-0 rounded-full border-2 flex items-center justify-center transition-all duration-300 focus:outline-none">
                                            <img src="{{asset('shop/customer/gamification/models/images/original/' . $mName . '.png')}}" class="w-full h-full object-cover rounded-full {{ $level >= $mLevel ? '' : 'blur-[6px] opacity-30 grayscale' }}">
                                        </button>
                                        <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-widest" :class="(typeof $store !== 'undefined' && $store.funki ? $store.funki.activeLevel : (typeof activeModelLevel !== 'undefined' ? activeModelLevel : {{min(10, $level)}})) == {{$mLevel}} ? 'text-amber-700 font-bold' : 'text-gray-500'">Level {{$mLevel}}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template x-teleport="body">
                <div x-show="showTitlesModal" style="display: none;" class="fixed inset-0 z-[7000] flex items-center justify-center p-4 sm:p-6">
                    <div class="absolute inset-0 bg-black/60 dark:bg-black/90 backdrop-blur-sm dark:backdrop-blur-md" @click="showTitlesModal = false" x-transition.opacity></div>

                    <div class="relative w-full max-w-4xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-2xl dark:shadow-[0_0_100px_rgba(0,0,0,1)] flex flex-col max-h-[90vh] overflow-hidden"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 translate-y-10">

                        <div class="relative w-full bg-gray-50/90 dark:bg-gray-900 border-b border-gray-200 dark:border-amber-500/20 p-5 sm:p-8 text-center shrink-0 overflow-hidden shadow-sm dark:shadow-inner flex flex-col items-center">
                            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-amber-500/5 via-transparent to-transparent pointer-events-none"></div>

                            <button @click="showTitlesModal = false" class="absolute top-4 right-4 sm:top-6 sm:right-6 p-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 rounded-full hover:bg-red-500 hover:border-red-500 hover:text-white transition-colors cursor-pointer focus:outline-none z-20 shadow-sm">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>

                            <div class="relative z-10 w-full">
                                {{-- Ganz oben: Diamant-Titel Fortschritt --}}
                                <div class="max-w-md mx-auto bg-white dark:bg-gray-950 p-3.5 sm:p-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner mb-5">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Diamant-Titel</span>
                                        <span class="text-xs font-black text-amber-600 dark:text-amber-400">{{ $titlesData['diamonds_count'] ?? 0 }} <span class="opacity-50 text-gray-400 dark:text-gray-600">/ {{ $titlesData['total_titles'] ?? 15 }}</span></span>
                                    </div>
                                    <div class="w-full bg-gray-100 dark:bg-gray-900 rounded-full h-2.5 shadow-inner overflow-hidden border border-gray-200 dark:border-gray-800">
                                        <div class="h-full bg-gradient-to-r from-amber-500 to-yellow-400 rounded-full transition-all duration-1000 shadow-none dark:shadow-[0_0_10px_rgba(251,191,36,0.5)]" style="width: {{ min(100, (($titlesData['diamonds_count'] ?? 0) / max(1, ($titlesData['total_titles'] ?? 15))) * 100) }}%"></div>
                                    </div>
                                    @if(isset($titlesData['next_mega_title']))
                                        <p class="text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 font-medium mt-2.5 uppercase tracking-widest">Noch <span class="text-gray-900 dark:text-white font-bold">{{ max(0, $titlesData['next_mega_title']['req'] - ($titlesData['diamonds_count'] ?? 0)) }} Diamant-Titel</span> bis: <strong class="text-amber-700 dark:text-amber-400">{{ $titlesData['next_mega_title']['name'] }}</strong></p>
                                    @else
                                        <p class="text-[9px] sm:text-[10px] text-amber-700 dark:text-amber-400 font-black mt-2.5 uppercase tracking-widest drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Du hast die höchste Stufe erreicht!</p>
                                    @endif
                                </div>

                                {{-- Hauptüberschrift: Dein aktueller Rang --}}
                                <p class="text-[10px] sm:text-xs text-amber-700 dark:text-amber-500/80 font-black uppercase tracking-[0.3em] mb-1.5 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Dein aktueller Rang</p>
                                @if($currentRankName)
                                    <h2 class="text-2xl sm:text-3xl md:text-4xl font-serif font-black text-amber-600 dark:text-amber-400 drop-shadow-none dark:drop-shadow-[0_0_15px_rgba(251,191,36,0.3)] uppercase tracking-tight">{{ $currentRankName }}</h2>
                                @else
                                    <h2 class="text-xl sm:text-2xl md:text-3xl font-serif font-bold text-gray-400 dark:text-gray-500 uppercase tracking-tight">Kein Rang</h2>
                                @endif
                            </div>
                        </div>

                        <div class="p-4 sm:p-6 overflow-y-auto custom-scrollbar flex-1 bg-gray-50/60 dark:bg-gray-950">
                            <div class="flex flex-col gap-2.5">
                                @foreach($titlesData['titles'] ?? [] as $titleKey => $title)
                                    <div class="w-full bg-white dark:bg-gray-900 border rounded-xl px-3.5 py-2.5 sm:px-4 sm:py-3 relative overflow-hidden flex flex-col gap-2 group transition-all shadow-sm dark:shadow-none {{ $activeTitleKey === $titleKey ? 'border-amber-500 dark:border-amber-400/70 ring-1 ring-amber-500/30 bg-amber-50/30 dark:bg-amber-950/15' : ($title['tier'] === 'diamant' ? 'border-cyan-400/60 dark:border-cyan-500/30 bg-cyan-50/20 dark:bg-cyan-950/10' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700') }}">
                                        <div class="flex flex-wrap sm:flex-nowrap items-center justify-between gap-2 sm:gap-4">
                                            {{-- Links: Icon + Name + Beschreibung --}}
                                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg flex items-center justify-center shrink-0 shadow-inner
                                                    {{ $title['tier'] === 'grau' ? 'bg-gray-100 dark:bg-gray-950 text-gray-400 dark:text-gray-600 border border-gray-200 dark:border-gray-800' : '' }}
                                                    {{ $title['tier'] === 'silber' ? 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-300 dark:border-slate-600/50' : '' }}
                                                    {{ $title['tier'] === 'gold' ? 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-600 dark:text-yellow-500 border border-yellow-300 dark:border-yellow-600/50' : '' }}
                                                    {{ $title['tier'] === 'diamant' ? 'bg-cyan-50 dark:bg-cyan-900/20 text-cyan-600 dark:text-cyan-400 border border-cyan-300 dark:border-cyan-500/50 shadow-sm dark:shadow-[0_0_12px_rgba(34,211,238,0.25)]' : '' }}">
                                                    @if($title['tier'] === 'diamant')
                                                        <svg class="w-4 h-4 sm:w-4.5 sm:h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                                        </svg>
                                                    @else
                                                        <svg class="w-4 h-4 sm:w-4.5 sm:h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                        <h4 class="text-gray-900 dark:text-white font-bold text-xs sm:text-sm leading-tight truncate">{{ $title['name'] }}</h4>
                                                        <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded whitespace-nowrap
                                                            {{ $title['tier'] === 'grau' ? 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700' : '' }}
                                                            {{ $title['tier'] === 'silber' ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700' : '' }}
                                                            {{ $title['tier'] === 'gold' ? 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-700/50' : '' }}
                                                            {{ $title['tier'] === 'diamant' ? 'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-800 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-700/50' : '' }}">
                                                            {{ $title['tier_name'] }}
                                                        </span>
                                                    </div>
                                                    <p class="text-gray-500 dark:text-gray-400 text-[10px] sm:text-xs truncate mt-0.5">{{ $title['description'] }}</p>
                                                </div>
                                            </div>

                                            {{-- Rechts: Fortschrittszähler + Auswahl-Button --}}
                                            <div class="flex items-center gap-2.5 sm:gap-3 shrink-0 ml-auto">
                                                <span class="text-[10px] sm:text-xs text-gray-600 dark:text-gray-400 font-mono font-bold whitespace-nowrap">
                                                    {{ (int) round((float) ($title['current_value'] ?? 0)) }} / {{ (int) round((float) ($title['next_req'] ?? 0)) }}
                                                </span>

                                                @if($title['tier'] !== 'grau')
                                                    <button wire:click="selectTitle('{{ $titleKey }}')" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all whitespace-nowrap cursor-pointer {{ $activeTitleKey === $titleKey ? 'bg-amber-500 text-gray-900 shadow-sm dark:shadow-[0_0_12px_rgba(251,191,36,0.4)] font-black' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-amber-500 hover:text-gray-900 dark:hover:bg-amber-500 dark:hover:text-gray-900 border border-gray-200 dark:border-gray-700' }}">
                                                        {{ $activeTitleKey === $titleKey ? '✓ Aktiver Rang' : 'Auswählen' }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Volle Breite Fortschrittsbalken --}}
                                        <div class="w-full bg-gray-100 dark:bg-gray-950 rounded-full h-2 shadow-inner overflow-hidden border border-gray-200/80 dark:border-gray-800">
                                            <div class="h-full rounded-full transition-all duration-700
                                                {{ $title['tier'] === 'grau' ? 'bg-gray-400 dark:bg-gray-700' : '' }}
                                                {{ $title['tier'] === 'silber' ? 'bg-slate-500 dark:bg-slate-400 dark:shadow-[0_0_6px_rgba(148,163,184,0.5)]' : '' }}
                                                {{ $title['tier'] === 'gold' ? 'bg-gradient-to-r from-amber-500 to-yellow-400 dark:shadow-[0_0_8px_rgba(234,179,8,0.5)]' : '' }}
                                                {{ $title['tier'] === 'diamant' ? 'bg-gradient-to-r from-cyan-500 to-sky-400 dark:shadow-[0_0_10px_rgba(34,211,238,0.8)]' : '' }}"
                                                 style="width: {{ $title['percentage'] }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </template>
</div>
