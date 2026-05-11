<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-xl text-[var(--theme-color)] leading-tight flex items-center gap-2">
                <x-heroicon-o-chat-bubble-left-ellipsis class="w-6 h-6" />
                Live Support Analytics
            </h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-9xl w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            {{-- 1. Main Summary Cards (Hauptkacheln) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 my-4">
                {{-- Card 1: Offene Klärungen & Eskalationen --}}
                <div class="bg-gray-900 border border-gray-700/60 rounded-3xl p-5 sm:p-6 shadow-2xl flex flex-col justify-between relative overflow-hidden group">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-[var(--theme-color)]/20 blur-[50px] rounded-full pointer-events-none transition-all duration-500 group-hover:bg-[var(--theme-color)]/30"></div>
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center border border-[var(--theme-color)]/50">
                                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-[var(--theme-color)]" />
                            </div>
                            <div>
                                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Operationell</h3>
                                <p class="text-sm font-bold text-white">Eskalationen & Offen</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-end justify-between">
                        <div>
                            <span class="text-4xl font-black text-white">{{ $openCount + $needsEmployeeCount }}</span>
                            <span class="text-xs text-gray-400 block mt-1 font-bold">Tickets in Bearbeitung</span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-bold text-[var(--theme-color)]">
                                {{ $needsEmployeeCount }} akut
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Card 2: KI-Lösungsquote --}}
                <div class="bg-gray-900 border border-gray-700/60 rounded-3xl p-5 sm:p-6 shadow-2xl flex flex-col justify-between relative overflow-hidden group">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-emerald-500/20 blur-[50px] rounded-full pointer-events-none transition-all duration-500 group-hover:bg-emerald-500/30"></div>
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center border border-emerald-500/50">
                                <x-heroicon-s-bolt class="w-5 h-5 text-emerald-400" />
                            </div>
                            <div>
                                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Effizienz</h3>
                                <p class="text-sm font-bold text-white">KI-Lösungsquote</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-end justify-between">
                        <div>
                            @php
                                $autoRate = $totalChatsAll > 0 ? round((($resolvedCount + $resolvedAutoCount) / $totalChatsAll) * 100) : 0;
                            @endphp
                            <span class="text-4xl font-black text-white">{{ $autoRate }}%</span>
                            <span class="text-xs text-gray-400 block mt-1 font-bold">Ohne Mensch gelöst</span>
                        </div>
                        <div class="text-right text-xs font-bold text-emerald-400">
                            {{ $resolvedCount + $resolvedAutoCount }} Tickets
                        </div>
                    </div>
                </div>

                {{-- Card 3: Kundenzufriedenheit --}}
                <div class="bg-gray-900 border border-gray-700/60 rounded-3xl p-5 sm:p-6 shadow-2xl flex flex-col justify-between relative overflow-hidden group">
                    <div class="absolute -top-10 -right-10 w-40 h-40 bg-amber-500/20 blur-[50px] rounded-full pointer-events-none transition-all duration-500 group-hover:bg-amber-500/30"></div>
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center border border-amber-500/50">
                                <x-heroicon-s-star class="w-5 h-5 text-amber-400" />
                            </div>
                            <div>
                                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Bewertung</h3>
                                <p class="text-sm font-bold text-white">Kundenzufriedenheit</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-end justify-between">
                        <div>
                            <span class="text-4xl font-black text-white">{{ number_format($avgRating, 1, ',', '.') }}</span>
                            <span class="text-xs text-gray-400 block mt-1 font-bold">Durchschnitt Ø</span>
                        </div>
                        <div class="flex items-center gap-1 mb-1">
                            @for($i=1; $i<=5; $i++)
                                <x-heroicon-s-star class="w-4 h-4 {{ $i <= round($avgRating) ? 'text-amber-400' : 'text-gray-700' }}" />
                            @endfor
                        </div>
                    </div>
                </div>
            </div>

            {{-- Details Toggle Button --}}
            <div class="flex justify-center mt-4 mb-8">
                <button wire:click="$toggle('showDetails')" class="flex items-center gap-2 px-6 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-full text-xs font-bold text-gray-300 transition-colors">
                    @if($showDetails)
                        <x-heroicon-s-chevron-up class="w-4 h-4" />
                        Details ausblenden
                    @else
                        <x-heroicon-s-chevron-down class="w-4 h-4" />
                        Auswertungen im Detail anzeigen
                    @endif
                </button>
            </div>

            @if($showDetails)
            <div x-data="kpiDashboard">
                {{-- Unified Animated KPI Grid with Alpine & Anime.js --}}
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-12">
                    {{-- Group 1: Operationeller Status --}}
                    <div class="kpi-group bg-gray-900 border border-gray-700/60 rounded-3xl p-5 sm:p-6 shadow-2xl relative overflow-hidden ring-1 ring-white/5 opacity-0">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-[var(--theme-color)]/20 blur-[50px] rounded-full pointer-events-none"></div>
                        
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 flex items-center gap-2">
                            <x-heroicon-s-server-stack class="w-4 h-4" /> Ticket-Operationen
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between items-center group">
                                <span class="text-sm font-medium text-gray-400 group-hover:text-gray-200 transition-colors">Aktive KI-Chats</span>
                                <span class="text-xl font-black text-[var(--theme-color)] anime-num" data-val="{{ $openCount }}">0</span>
                            </div>
                            <div class="flex justify-between items-center group">
                                <span class="text-sm font-medium text-gray-400 group-hover:text-gray-200 transition-colors">Durch KI gelöst</span>
                                <span class="text-xl font-black text-[var(--theme-color)] anime-num" data-val="{{ $resolvedCount }}">0</span>
                            </div>
                            <div class="flex justify-between items-center group">
                                <span class="text-sm font-medium text-gray-400 group-hover:text-gray-200 transition-colors">Admin-Schließungen</span>
                                <span class="text-xl font-black text-[var(--theme-color-80)] anime-num" data-val="{{ $resolvedAdminCount }}">0</span>
                            </div>
                            <div class="flex justify-between items-center group pt-4 mt-2 border-t border-gray-800">
                                <span class="text-sm font-medium text-[var(--theme-color)] flex items-center gap-1.5"><x-heroicon-s-exclamation-triangle class="w-5 h-5 animate-pulse" /> Akute Eskalationen</span>
                                <span class="text-2xl font-black text-[var(--theme-color)] anime-num" data-val="{{ $needsEmployeeCount }}">0</span>
                            </div>
                        </div>
                    </div>

                    {{-- Group 2: System Telemetrie --}}
                    <div class="kpi-group bg-gray-900 border border-gray-700/60 rounded-3xl p-5 sm:p-6 shadow-2xl relative overflow-hidden ring-1 ring-white/5 opacity-0">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-[var(--theme-color-20)] blur-[50px] rounded-full pointer-events-none"></div>
                        
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 flex items-center gap-2">
                            <x-heroicon-s-cpu-chip class="w-4 h-4" /> System-Leistung
                        </h3>
                        
                        <div class="space-y-6">
                            <div>
                                <div class="text-[11px] font-medium text-gray-500 mb-1 uppercase tracking-wider">Eindeutige KI-Nutzer</div>
                                <div class="text-3xl font-black text-white flex items-baseline gap-1.5">
                                    <span class="anime-num" data-val="{{ $uniqueAiCustomers }}">0</span>
                                    <span class="text-xs text-gray-500 font-bold uppercase">Nutzer</span>
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] font-medium text-gray-500 mb-1 uppercase tracking-wider">Ø KI-Antwortzeit (LLM)</div>
                                <div class="text-3xl font-black text-white flex items-baseline gap-1.5">
                                    <span class="anime-num" data-val="{{ $avgResponseTime }}">0</span>
                                    <span class="text-xs text-gray-500 font-bold uppercase">ms</span>
                                </div>
                            </div>
                            <div class="pt-2">
                                <div class="flex justify-between text-[11px] font-medium text-gray-400 mb-2 uppercase tracking-wider">
                                    <span>Agenten-Sicherheit</span>
                                    <span class="text-[var(--theme-color)] font-bold"><span class="anime-num" data-val="{{ $avgConfidence }}">0</span>%</span>
                                </div>
                                <div class="w-full bg-gray-800 rounded-full h-1.5 overflow-hidden ring-1 ring-inset ring-gray-900 relative">
                                    <div class="bg-[var(--theme-color)] h-1.5 rounded-full z-10 relative" style="width: {{ $avgConfidence }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Group 3: Message / Content Insights --}}
                    <div class="kpi-group bg-gray-900 border border-gray-700/60 rounded-3xl p-5 sm:p-6 shadow-2xl relative overflow-hidden ring-1 ring-white/5 opacity-0">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-[var(--theme-color-20)] blur-[50px] rounded-full pointer-events-none"></div>
                        
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 flex items-center gap-2">
                            <x-heroicon-s-chat-bubble-left-right class="w-4 h-4" /> Verhalten & Text
                        </h3>
                        
                        <div class="grid grid-cols-2 gap-3 h-full pb-4">
                            <div class="bg-gray-800/60 rounded-2xl p-4 border border-gray-700/50 flex flex-col justify-center items-center text-center">
                                <div class="text-[10px] text-gray-400 uppercase tracking-widest font-bold mb-1">Nachrichten</div>
                                <div class="text-2xl font-black text-white"><span class="anime-num" data-val="{{ $avgMessagesPerChat }}" data-format="float">0</span></div>
                                <div class="text-[9px] text-gray-500 font-medium">Ø pro Chat</div>
                            </div>
                            <div class="bg-gray-800/60 rounded-2xl p-4 border border-gray-700/50 flex flex-col justify-center items-center text-center">
                                <div class="text-[10px] text-gray-400 uppercase tracking-widest font-bold mb-1">Textlänge</div>
                                <div class="text-2xl font-black text-white"><span class="anime-num" data-val="{{ $avgCustomerLength }}">0</span></div>
                                <div class="text-[9px] text-gray-500 font-medium">Ø Zeichen/Nachricht</div>
                            </div>
                            <div class="bg-[var(--theme-color-5)] rounded-2xl p-4 border border-[var(--theme-color)]/20 flex flex-col justify-center items-center text-center group hover:bg-[var(--theme-color-10)] transition-colors">
                                <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-bold mb-1">Eskalations-Rate</div>
                                <div class="text-2xl font-black text-[var(--theme-color)]"><span class="anime-num" data-val="{{ $escalationRate }}">0</span>%</div>
                                <div class="text-[9px] text-gray-500 font-medium">Endet beim Mitarbeiter</div>
                            </div>
                            <div class="bg-[var(--theme-color-5)] rounded-2xl p-4 border border-[var(--theme-color)]/20 flex flex-col justify-center items-center text-center group hover:bg-[var(--theme-color-10)] transition-colors">
                                <div class="text-[10px] text-[var(--theme-color)] uppercase tracking-widest font-bold mb-1">Troll Quote</div>
                                <div class="text-2xl font-black text-[var(--theme-color)]"><span class="anime-num" data-val="{{ $trollRate }}">0</span>%</div>
                                <div class="text-[9px] text-gray-500 font-medium">Automatischer Ban</div>
                            </div>
                        </div>
                    </div>

                    {{-- Group 4: Kundenbewertungen --}}
                    <div class="kpi-group bg-gray-900 border border-gray-700/60 rounded-3xl p-5 sm:p-6 shadow-2xl relative overflow-hidden ring-1 ring-white/5 opacity-0">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-[var(--theme-color-20)] blur-[50px] rounded-full pointer-events-none"></div>
                        
                        <h3 class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-4 flex items-center gap-2">
                            <x-heroicon-s-star class="w-4 h-4" /> Kundenbewertungen
                        </h3>
                        
                        <div class="flex items-baseline gap-2 mb-4">
                            <span class="text-4xl font-black text-white anime-num" data-val="{{ $avgRating }}" data-format="float">0</span>
                            <span class="text-[9px] text-gray-500 uppercase tracking-widest font-bold">von 5 (aus <span class="anime-num" data-val="{{ $totalRatings }}">0</span>)</span>
                        </div>

                        <div class="space-y-2 mt-4">
                            @foreach([5, 4, 3, 2, 1] as $star)
                                <div class="flex items-center gap-2 text-[10px]">
                                    <span class="text-gray-400 w-6 font-bold">{{ $star }} <x-heroicon-s-star class="w-2.5 h-2.5 inline-block -mt-0.5 text-[var(--theme-color)]" /></span>
                                    <div class="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-[var(--theme-color-80)] rounded-full" style="width: {{ $ratingBreakdown[$star]['percent'] }}%;"></div>
                                    </div>
                                    <span class="text-gray-500 w-6 text-right font-medium">{{ $ratingBreakdown[$star]['percent'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 2. Top Topics & Products & Insights --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                    <div class="kpi-group opacity-0 bg-gray-800/80 rounded-2xl shadow-lg border border-gray-700/50 overflow-hidden backdrop-blur-sm">
                        <div class="px-6 py-4 border-b border-gray-700/50 bg-gray-900/50">
                            <h3 class="font-bold text-gray-100 flex items-center gap-2">
                                <x-heroicon-o-hashtag class="w-5 h-5 text-[var(--theme-color)]" />
                                Top 5 Cloud Themen
                            </h3>
                        </div>
                        <div class="p-6">
                            @if($topTopics->isEmpty())
                                <p class="text-gray-400 text-sm">Noch keine analytischen Daten verfügbar.</p>
                            @else
                                <ul class="space-y-4">
                                    @foreach($topTopics as $topic)
                                        <li class="flex items-center justify-between">
                                            <span class="text-gray-300 font-medium text-sm truncate pr-2" title="{{ $topic->top_topic }}">{{ $topic->top_topic }}</span>
                                            <span class="bg-gray-700 text-gray-300 py-1 px-3 rounded-full text-xs font-bold">{{ $topic->count }}x</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div class="kpi-group opacity-0 bg-gray-800/80 rounded-2xl shadow-lg border border-gray-700/50 overflow-hidden backdrop-blur-sm">
                        <div class="px-6 py-4 border-b border-gray-700/50 bg-gray-900/50">
                            <h3 class="font-bold text-gray-100 flex items-center gap-2">
                                <x-heroicon-o-shopping-bag class="w-5 h-5 text-[var(--theme-color)]" />
                                Top 5 Erwähnte Produkte
                            </h3>
                        </div>
                        <div class="p-6">
                            @if($topProducts->isEmpty())
                                <p class="text-gray-400 text-sm">Noch keine Produkt-Analysen verfügbar.</p>
                            @else
                                <ul class="space-y-4">
                                    @foreach($topProducts as $prod)
                                        <li class="flex items-center justify-between">
                                            <span class="text-gray-300 font-medium text-sm truncate pr-2" title="{{ $prod->mentioned_product }}">{{ $prod->mentioned_product }}</span>
                                            <span class="bg-[var(--theme-color)]/20 text-[var(--theme-color)] py-1 px-3 rounded-full text-xs font-bold ring-1 ring-[var(--theme-color)]/50">{{ $prod->count }}x</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <!-- Recent Summaries -->
                    <div class="kpi-group opacity-0 bg-gray-800/80 rounded-2xl shadow-lg border border-gray-700/50 overflow-hidden backdrop-blur-sm">
                        <div class="px-6 py-4 border-b border-gray-700/50 bg-gray-900/50">
                            <h3 class="font-bold text-gray-100 flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-5 h-5 text-[var(--theme-color)]" />
                                Live KI-Insights
                            </h3>
                        </div>
                        <div class="p-5">
                            @if($recentSummaries->isEmpty())
                                <p class="text-gray-400 text-sm">Keine Zusammenfassungen vorhanden.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($recentSummaries as $summary)
                                        <div class="bg-gray-900/50 rounded-xl p-3 border border-gray-700/30 shadow-inner group hover:bg-gray-700/30 transition-colors">
                                            <div class="text-[10px] text-gray-500 mb-1 flex justify-between uppercase font-black tracking-wider">
                                                <span class="truncate max-w-[120px] text-[var(--theme-color-80)]">{{ $summary->top_topic ?? 'Generell' }}</span>
                                                <span class="shrink-0 ml-2 opacity-50">{{ $summary->updated_at->diffForHumans() }}</span>
                                            </div>
                                            <p class="text-xs text-gray-300 line-clamp-2 leading-relaxed">
                                                "{{ $summary->ai_summary }}"
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif


            {{-- 3. Data Table --}}
            <div class="bg-gray-800 my-12 rounded-2xl shadow-lg border border-gray-700/50 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700/50 flex flex-col sm:flex-row justify-between items-center bg-gray-800/50 gap-4">
                    <h3 class="font-bold text-gray-100 uppercase tracking-widest text-xs">Chat Protokolle</h3>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <select wire:model.live="statusFilter" class="bg-gray-900 border border-gray-700 text-white rounded-xl text-sm w-full sm:w-auto">
                            <option value="">Alle Status</option>
                            <option value="needs_employee">Eskalation (Mitarbeiter)</option>
                            <option value="open">Offen</option>
                            <option value="resolved">Erledigt (KI)</option>
                            <option value="resolved_admin">Erledigt (Admin)</option>
                            <option value="resolved_auto">Timeout (Auto)</option>
                        </select>
                        <select wire:model.live="ratingFilter" class="bg-gray-900 border border-gray-700 text-white rounded-xl text-sm w-full sm:w-auto">
                            <option value="">Alle Bewertungen</option>
                            <option value="5">5 Sterne</option>
                            <option value="4">4 Sterne</option>
                            <option value="3">3 Sterne</option>
                            <option value="2">2 Sterne</option>
                            <option value="1">1 Stern</option>
                        </select>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche in Thematik..." class="w-full sm:w-64 bg-gray-900 border border-gray-700 text-white placeholder-gray-500 text-sm rounded-xl px-4 py-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                    </div>
                </div>

                {{-- Desktop Table View --}}
                <div class="overflow-x-auto hidden lg:block">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-900/50 border-b border-gray-700/50">
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Thema / Fokusprodukt</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Nachrichten</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-center">Sterne</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Analytische Zusammenfassung</th>
                                <th class="p-4 text-xs font-bold text-gray-400 uppercase tracking-wider text-right">Aktion</th>
                            </tr>
                        </thead>
                        @forelse($chats as $chat)
                            <tbody x-data="{ expanded: false }" wire:key="chat-desktop-{{ $chat->id }}">
                                <tr @click="expanded = !expanded" class="hover:bg-gray-700/20 transition-colors cursor-pointer border-b border-gray-700/50 {{ $chat->status === 'needs_employee' ? 'bg-red-500/5' : '' }}">
                                        <td class="p-4">
                                        @if($chat->status === 'needs_employee')
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-red-500/20 text-red-400 border border-red-500/30">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                                Mitarbeiter benötigt
                                            </span>
                                        @elseif($chat->status === 'open')
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[var(--theme-color-20)] text-[var(--theme-color)] border border-[var(--theme-color)]/30">
                                                Offen
                                            </span>
                                        @elseif($chat->status === 'resolved_admin')
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-500/20 text-purple-400 border border-purple-500/30">
                                                Admin Erledigt
                                            </span>
                                        @elseif($chat->status === 'resolved_auto')
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-500/20 text-gray-400 border border-gray-500/30">
                                                Auto Erledigt
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-500/20 text-green-400 border border-green-500/30">
                                                KI Erledigt
                                            </span>
                                        @endif
                                        <div class="text-[10px] text-gray-500 mt-2 ml-1" title="Erstellt am: {{ $chat->created_at->format('d.m.Y H:i') }}">{{ $chat->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="text-sm text-gray-200 font-semibold truncate max-w-[200px]" title="{{ $chat->top_topic }}">{{ $chat->top_topic ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 truncate mt-1">Prod: {{ $chat->mentioned_product ?? '-' }}</div>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <x-heroicon-o-chat-bubble-bottom-center-text class="w-5 h-5 text-gray-400" />
                                            <span class="text-sm text-gray-300 font-bold">{{ $chat->messages->count() }}</span>
                                        </div>
                                        @php
                                            $custMsgs = $chat->messages->where('sender', 'customer');
                                            $baseWeight = $custMsgs->count() * 10;
                                            $severityWeight = $custMsgs->sum('severity');
                                            $weight = $baseWeight + $severityWeight;
                                            $percent = min(100, $weight);
                                        @endphp
                                        <div class="mt-2 w-full max-w-[120px]" title="{{ $custMsgs->count() }} Kunden-Nachrichten ({{ $baseWeight }} Pkt) + {{ $severityWeight }} Pkt Strafen. Ab 40 Punkten macht die KI Druck, bei 100 ist Schluss.">
                                            <div class="flex justify-between text-[9px] text-gray-500 font-bold uppercase tracking-widest mb-1">
                                                <span>Ausdauer</span>
                                                <span class="{{ $percent >= 100 ? 'text-red-500' : ($percent >= 40 ? 'text-amber-500' : 'text-emerald-500') }}">{{ $weight }}/100</span>
                                            </div>
                                            <div class="w-full h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $percent >= 100 ? 'bg-red-500' : ($percent >= 40 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $percent }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-center">
                                        @if($chat->rating)
                                            <div class="flex justify-center items-center text-[var(--theme-color)] font-bold gap-1 text-sm">
                                                {{ $chat->rating }} <x-heroicon-s-star class="w-4 h-4" />
                                            </div>
                                        @else
                                            <span class="text-gray-600 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="p-4">
                                        <p class="text-xs text-gray-400 line-clamp-2 max-w-xs">{{ $chat->ai_summary ?? 'Keine KI-Zusammenfassung vorhanden.' }}</p>
                                    </td>
                                    <td class="p-4 text-right align-middle">
                                        <div class="flex flex-col items-end gap-2 pr-2">
                                            @if(!in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto']))
                                                <button wire:click.stop="markAsResolved('{{ $chat->id }}')" class="px-4 py-2 bg-gray-700 hover:bg-green-600 focus:ring-4 focus:outline-none focus:ring-green-900 font-medium rounded-xl text-[11px] text-white transition-colors shadow-sm whitespace-nowrap">
                                                    Chat Schließen
                                                </button>
                                                
                                                @php
                                                    $minutesPassed = now()->diffInMinutes($chat->updated_at);
                                                    $percent = min(100, max(0, ($minutesPassed / (12 * 60)) * 100));
                                                    $remainingMinutes = max(0, (12 * 60) - $minutesPassed);
                                                    $remH = floor($remainingMinutes / 60);
                                                    $remM = $remainingMinutes % 60;
                                                    $timeStr = $remH > 0 ? $remH . 'h ' . $remM . 'm' : $remM . 'm';
                                                @endphp
                                                
                                                <div class="w-full max-w-[130px] flex flex-col gap-1.5 mt-1" title="Auto-Close bei Inaktivität">
                                                    <div class="flex justify-between w-full text-[9px] text-gray-400 font-black uppercase tracking-widest">
                                                        <span>Auto-Close</span>
                                                        <span class="text-gray-300">{{ $timeStr }}</span>
                                                    </div>
                                                    <div class="w-full h-1.5 bg-gray-900 rounded-full overflow-hidden shadow-inner border border-gray-700/50">
                                                        <div class="h-full bg-gradient-to-r from-cyan-600 to-[var(--theme-color)] rounded-full" style="width: {{ $percent }}%"></div>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-gray-500 text-xs italic">
                                                    @if($chat->status === 'resolved_admin')
                                                        Durch Admin erledigt
                                                    @elseif($chat->status === 'resolved_auto')
                                                        Auto Timeout (12h)
                                                    @else
                                                        Verwaltet von {{ $agentName }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr x-show="expanded" x-transition.opacity class="bg-gray-900/40">
                                    <td colspan="6" class="p-6">
                                        <div class="space-y-6">
                                            @if($chat->rating)
                                                <div class="flex items-start gap-4 bg-gray-800 p-4 rounded-xl border border-gray-700">
                                                    <div>
                                                        <div class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Kundenbewertung</div>
                                                        <div class="flex items-center gap-1 text-yellow-500">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                @if($i <= $chat->rating)
                                                                    <x-heroicon-s-star class="w-5 h-5" />
                                                                @else
                                                                    <x-heroicon-o-star class="w-5 h-5 text-gray-600" />
                                                                @endif
                                                            @endfor
                                                        </div>
                                                    </div>
                                                    @if($chat->feedback_text)
                                                        <div class="flex-1 ml-4 border-l border-gray-700 pl-4">
                                                            <div class="text-xs text-gray-400 uppercase tracking-wider font-bold mb-1">Kundenfeedback</div>
                                                            <p class="text-sm text-gray-300 italic">"{{ $chat->feedback_text }}"</p>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden shadow-inner">
                                                <div class="px-4 py-3 bg-gray-900/80 border-b border-gray-700 flex justify-between items-center">
                                                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Chat Verlauf ({{ $chat->messages->count() }} Nachrichten)</span>
                                                </div>
                                                <div class="p-4 space-y-4 max-h-[400px] overflow-y-auto">
                                                    @foreach($chat->messages as $msg)
                                                        <div class="flex {{ $msg->sender === 'customer' ? 'justify-end' : 'justify-start' }} items-end gap-2">
                                                            @if($msg->sender !== 'customer' && $msg->sender !== 'system')
                                                                <img src="{{ $agentImage }}" class="w-7 h-7 rounded-full border border-gray-600/50 shadow-sm shrink-0 mb-1">
                                                            @endif
                                                            <div class="max-w-[75%] px-4 py-3 rounded-2xl shadow-sm {{ $msg->sender === 'customer' ? 'bg-[var(--theme-color-20)] text-white rounded-br-sm border border-[var(--theme-color)]/30' : ($msg->sender === 'system' ? 'bg-gray-700/50 text-gray-400 rounded-xl border border-gray-600/30 w-full text-center text-xs' : 'bg-gray-700/80 text-gray-200 rounded-bl-sm border border-gray-600/50') }}">
                                                                @if($msg->sender !== 'system')
                                                                    <div class="text-[10px] opacity-70 mb-1 {{ $msg->sender === 'customer' ? 'text-right text-[var(--theme-color-80)]' : 'text-left text-gray-400' }}">
                                                                        {{ $msg->sender === 'customer' ? 'Kunde' : $agentName }} • {{ $msg->created_at->format('H:i:s') }}
                                                                        @if($msg->sender === 'customer' && $msg->severity > 0)
                                                                            <span class="ml-1 bg-red-500/20 text-red-400 border border-red-500/30 px-1.5 py-0.5 rounded text-[8px] font-black tracking-widest" title="KI Strafe: {{ $msg->tag }}">STRAFE: {{ $msg->severity }}</span>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                                <div class="text-sm whitespace-pre-wrap leading-relaxed [&_a]:text-[var(--theme-color)] [&_a]:font-bold [&_a]:underline [&_a]:hover:text-[var(--theme-color-80)] transition-all">{!! \Illuminate\Support\Str::markdown($msg->message) !!}</div>
                                                            </div>
                                                            @if($msg->sender === 'customer')
                                                                <div class="w-7 h-7 rounded-full bg-[var(--theme-color-30)] border border-[var(--theme-color)]/30 flex items-center justify-center shrink-0 mb-1">
                                                                    <x-heroicon-s-user class="w-4 h-4 text-[var(--theme-color)]" />
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            @empty
                                <tbody>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-gray-500 text-sm border-b border-gray-700/50">
                                            Es wurden derzeit noch keine Support-Chats durch die KI verarbeitet.
                                        </td>
                                    </tr>
                                </tbody>
                            @endforelse
                    </table>
                </div>

                {{-- Mobile Card View --}}
                <div class="lg:hidden divide-y divide-gray-700/50">
                    @forelse($chats as $chat)
                        <div x-data="{ expanded: false }" wire:key="chat-mobile-{{ $chat->id }}" class="p-4 {{ $chat->status === 'needs_employee' ? 'bg-red-500/5' : '' }}">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    @if($chat->status === 'needs_employee')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500/20 text-red-400 border border-red-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                            Mitarbeiter benötigt
                                        </span>
                                    @elseif($chat->status === 'open')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[var(--theme-color-20)] text-[var(--theme-color)] border border-[var(--theme-color)]/30">
                                            Offen
                                        </span>
                                    @elseif($chat->status === 'resolved_admin')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-400 border border-purple-500/30">
                                            Admin Erledigt
                                        </span>
                                    @elseif($chat->status === 'resolved_auto')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-500/20 text-gray-400 border border-gray-500/30">
                                            Auto Erledigt
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-500/20 text-green-400 border border-green-500/30">
                                            KI Erledigt
                                        </span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-gray-500" title="Erstellt am: {{ $chat->created_at->format('d.m.Y H:i') }}">{{ $chat->created_at->diffForHumans() }}</div>
                            </div>
                            
                            <h4 class="text-sm font-bold text-gray-200 leading-tight mb-1">{{ $chat->top_topic ?? 'Generelle Anfrage' }}</h4>
                            <div class="text-xs text-gray-500 mb-3">Produkt: {{ $chat->mentioned_product ?? '-' }}</div>
                            
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center gap-4 w-full">
                                    <div class="flex items-center gap-1.5 text-xs text-gray-400">
                                        <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4" />
                                        <span>{{ $chat->messages->count() }} Msg</span>
                                    </div>
                                    
                                    @php
                                        $custMsgsM = $chat->messages->where('sender', 'customer');
                                        $baseWeightM = $custMsgsM->count() * 10;
                                        $severityWeightM = $custMsgsM->sum('severity');
                                        $weightM = $baseWeightM + $severityWeightM;
                                        $percentM = min(100, $weightM);
                                    @endphp
                                    <div class="flex flex-col gap-0.5 w-24" title="{{ $custMsgsM->count() }} Kunden-Nachrichten ({{ $baseWeightM }} Pkt) + {{ $severityWeightM }} Pkt Strafen.">
                                        <div class="flex justify-between text-[8px] font-bold uppercase tracking-widest">
                                            <span class="text-gray-500">Ausdauer</span>
                                            <span class="{{ $percentM >= 100 ? 'text-red-500' : ($percentM >= 40 ? 'text-amber-500' : 'text-emerald-500') }}">{{ $weightM }}/100</span>
                                        </div>
                                        <div class="w-full h-1 bg-gray-800 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $percentM >= 100 ? 'bg-red-500' : ($percentM >= 40 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ $percentM }}%"></div>
                                        </div>
                                    </div>
                                    
                                    @if($chat->rating)
                                        <div class="flex items-center gap-1 text-[var(--theme-color)] font-bold text-xs ml-auto">
                                            {{ $chat->rating }} <x-heroicon-s-star class="w-3.5 h-3.5" />
                                        </div>
                                    @else
                                        <div class="ml-auto"></div>
                                    @endif
                                </div>
                                
                                <button @click="expanded = !expanded" class="text-xs font-bold text-gray-300 flex items-center gap-1 bg-gray-700/50 px-3 py-1.5 rounded-lg border border-gray-600/50">
                                    <span x-text="expanded ? 'Zuklappen' : 'Verlauf ansehen'"></span>
                                    <x-heroicon-s-chevron-down class="w-3 h-3 transition-transform duration-200" x-bind:class="expanded ? 'rotate-180' : ''" />
                                </button>
                            </div>
                            
                            {{-- Mobile Actions --}}
                            <div class="mt-4 pt-3 border-t border-gray-700/50 flex flex-col gap-2">
                                @if(!in_array($chat->status, ['resolved', 'resolved_admin', 'resolved_auto']))
                                    <button wire:click="markAsResolved('{{ $chat->id }}')" class="w-full py-2 bg-gray-700 hover:bg-green-600 font-medium rounded-xl text-xs text-white transition-colors">
                                        Chat Schließen (Erledigt)
                                    </button>
                                    
                                    @php
                                        $minutesPassed = now()->diffInMinutes($chat->updated_at);
                                        $percent = min(100, max(0, ($minutesPassed / (12 * 60)) * 100));
                                        $remainingMinutes = max(0, (12 * 60) - $minutesPassed);
                                        $remH = floor($remainingMinutes / 60);
                                        $remM = $remainingMinutes % 60;
                                        $timeStr = $remH > 0 ? $remH . 'h ' . $remM . 'm' : $remM . 'm';
                                    @endphp
                                    <div class="w-full flex items-center justify-between text-[10px] text-gray-400 uppercase mt-1">
                                        <span>Auto-Close in {{ $timeStr }}</span>
                                        <div class="w-20 h-1.5 bg-gray-900 rounded-full overflow-hidden shadow-inner border border-gray-700/50">
                                            <div class="h-full bg-gradient-to-r from-cyan-600 to-[var(--theme-color)] rounded-full" style="width: {{ $percent }}%"></div>
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center w-full">
                                        <span class="text-gray-500 text-xs italic">
                                            @if($chat->status === 'resolved_admin')
                                                Durch Admin erledigt
                                            @elseif($chat->status === 'resolved_auto')
                                                Auto Timeout (12h)
                                            @else
                                                Verwaltet von {{ $agentName }}
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Mobile Expanded Content --}}
                            <div x-show="expanded" x-transition.opacity class="mt-4 pt-4 border-t border-gray-700 space-y-4">
                                <div class="bg-gray-800/50 p-3 rounded-xl border border-gray-700/50">
                                    <div class="text-[10px] font-bold text-gray-400 uppercase mb-1 tracking-wider">KI-Zusammenfassung</div>
                                    <p class="text-xs text-gray-300">{{ $chat->ai_summary ?? 'Keine Zusammenfassung.' }}</p>
                                </div>
                                
                                @if($chat->rating && $chat->feedback_text)
                                    <div class="bg-gray-800/50 p-3 rounded-xl border border-gray-700/50">
                                        <div class="text-[10px] font-bold text-gray-400 uppercase mb-1 tracking-wider">Kundenfeedback</div>
                                        <p class="text-xs text-gray-300 italic">"{{ $chat->feedback_text }}"</p>
                                    </div>
                                @endif
                                
                                <div class="bg-gray-900 rounded-xl border border-gray-700 overflow-hidden shadow-inner">
                                    <div class="px-3 py-2 bg-gray-800/80 border-b border-gray-700">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Chat Verlauf</span>
                                    </div>
                                    <div class="p-3 space-y-3 max-h-[300px] overflow-y-auto">
                                        @foreach($chat->messages as $msg)
                                            <div class="flex {{ $msg->sender === 'customer' ? 'justify-end' : 'justify-start' }} items-end gap-1.5">
                                                @if($msg->sender !== 'customer' && $msg->sender !== 'system')
                                                    <img src="{{ $agentImage }}" class="w-5 h-5 rounded-full border border-gray-600/50 shrink-0 mb-1">
                                                @endif
                                                <div class="max-w-[85%] px-3 py-2 rounded-xl {{ $msg->sender === 'customer' ? 'bg-[var(--theme-color-20)] text-white rounded-br-sm border border-[var(--theme-color)]/30' : ($msg->sender === 'system' ? 'bg-gray-800 text-gray-400 rounded-lg w-full text-center text-[10px]' : 'bg-gray-800 text-gray-200 rounded-bl-sm border border-gray-700') }}">
                                                    @if($msg->sender !== 'system')
                                                        <div class="text-[9px] opacity-70 mb-0.5 {{ $msg->sender === 'customer' ? 'text-right text-[var(--theme-color-80)]' : 'text-left text-gray-400' }}">
                                                            {{ $msg->sender === 'customer' ? 'Kunde' : $agentName }} • {{ $msg->created_at->format('H:i') }}
                                                            @if($msg->sender === 'customer' && $msg->severity > 0)
                                                                <span class="ml-1 bg-red-500/20 text-red-400 border border-red-500/30 px-1 py-0.5 rounded text-[7px] font-black tracking-widest" title="KI Strafe: {{ $msg->tag }}">STRAFE: {{ $msg->severity }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div class="text-xs whitespace-pre-wrap leading-relaxed [&_a]:text-[var(--theme-color)] [&_a]:underline">{!! \Illuminate\Support\Str::markdown($msg->message) !!}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500 text-sm">
                            Keine Chats gefunden.
                        </div>
                    @endforelse
                </div>
                @if($chats->hasPages())
                    <div class="p-4 border-t border-gray-700/50 bg-gray-800/30">
                        {{ $chats->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kpiDashboard', () => ({
            init() {
                const attemptAnime = () => {
                    if (window.anime) {
                        this.animate();
                    } else {
                        setTimeout(attemptAnime, 50);
                    }
                };
                attemptAnime();
            },
            animate() {
                // Staggered Opacity/Slide-Up of Grid Cards
                anime({
                    targets: this.$el.querySelectorAll('.kpi-group'),
                    translateY: [40, 0],
                    opacity: [0, 1],
                    delay: anime.stagger(150),
                    easing: 'easeOutExpo',
                    duration: 1200
                });

                // Animate the Numbers ticking up slowly
                this.$el.querySelectorAll('.anime-num').forEach(el => {
                    let endValue = parseFloat(el.getAttribute('data-val'));
                    let isFloat = el.getAttribute('data-format') === 'float';
                    if (isNaN(endValue)) endValue = 0;
                    
                    let targetObj = { val: 0 };
                    anime({
                        targets: targetObj,
                        val: endValue,
                        easing: 'easeOutQuart',
                        duration: 3000,
                        delay: anime.random(200, 500),
                        update: function() {
                            el.innerHTML = isFloat ? (targetObj.val).toFixed(1) : Math.round(targetObj.val);
                        }
                    });
                });
            }
        }));
    });
</script>
@endpush

</div>
