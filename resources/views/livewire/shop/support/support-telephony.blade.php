<div style="--theme-color: {{ $this->themeColorHex }};" class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">
                <span class="text-[var(--theme-color)] drop-shadow-[0_0_15px_var(--theme-color)0.5)]"><i class="bi bi-telephone"></i></span> 
                Support Telefonie
            </h1>
            <p class="text-gray-400">Verwalte Anrufe, überwache KPIs und greife auf das Telefonbuch der KI zu.</p>
        </div>
        <div>
            <div class="flex space-x-2 bg-gray-800/50 p-1 rounded-xl backdrop-blur-md border border-white/10">
                <button wire:click="$set('currentTab', 'calls')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 {{ $currentTab === 'calls' ? 'bg-[var(--theme-color)] text-gray-900 shadow' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Dashboard
                </button>
                <button wire:click="$set('currentTab', 'settings')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 {{ $currentTab === 'settings' ? 'bg-[var(--theme-color)] text-gray-900 shadow' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    Einstellungen
                </button>
            </div>
        </div>
    </div>

    @if($currentTab === 'calls')
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Anrufe Heute</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-telephone"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['total_calls_today'] }}</div>
            </div>
            
            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Gesprächsminuten (Heute)</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['total_minutes_today'] }} <span class="text-sm font-normal text-gray-500">Min.</span></div>
            </div>

            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Erfolgsquote</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['success_rate'] }}%</div>
            </div>

            <div class="bg-gray-900/40 border border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-400 text-sm font-medium">Ø Dauer</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-stopwatch"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-white">{{ $kpi['avg_duration'] }}</div>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Live Calls Section -->
            <div class="bg-gray-800/60 border border-[var(--theme-color)]/20 rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4">
                    <span class="flex h-3 w-3 relative">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--theme-color)] opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-[var(--theme-color)]"></span>
                    </span>
                </div>
                <h2 class="text-xl font-semibold text-white mb-4">Aktive Anrufe</h2>
                
                @if($activeCalls->isEmpty())
                    <div class="text-gray-400 text-center py-8">
                        Aktuell telefoniert kein Agent.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($activeCalls as $call)
                            <div class="bg-gray-900/50 border border-gray-700 p-4 rounded-xl flex flex-col space-y-3">
                                <div class="flex items-center space-x-4">
                                    <div class="h-12 w-12 rounded-full bg-[var(--theme-color)] flex items-center justify-center text-gray-900 font-bold text-lg shadow-[0_0_10px_var(--theme-color)]">
                                        KI
                                    </div>
                                    <div>
                                        <div class="text-white font-medium">Sprach-KI Agent</div>
                                        <div class="text-sm text-[var(--theme-color)] flex items-center space-x-1">
                                            <i class="bi bi-telephone-outbound"></i>
                                            <span>{{ $call->contact_name ?? $call->phone }}</span>
                                        </div>
                                        <div class="text-xs text-emerald-500 mt-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Läuft aktuell</div>
                                    </div>
                                </div>
                                @if($call->objective)
                                    <div class="border-t border-gray-700 pt-3 mt-1">
                                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Aufgabenplan:</div>
                                        <div class="text-xs text-gray-300 leading-relaxed italic border-l-2 border-[var(--theme-color)] pl-3">{{ $call->objective }}</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Call History -->
            <div class="bg-gray-900/40 border border-white/5 rounded-2xl overflow-hidden backdrop-blur-md">
                <div class="p-6 border-b border-white/5">
                    <h2 class="text-xl font-semibold text-white">Anruf-Historie</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-800/30 text-gray-400 text-xs uppercase tracking-wider">
                                <th class="p-4 font-medium">Datum</th>
                                <th class="p-4 font-medium">Agent</th>
                                <th class="p-4 font-medium">Kontakt</th>
                                <th class="p-4 font-medium">Dauer</th>
                                <th class="p-4 font-medium">Status</th>
                                <th class="p-4 font-medium text-right">Aktion</th>
                            </tr>
                        </thead>
                        @forelse($historyCalls as $call)
                            <tbody x-data="{ expanded: false }" class="border-b border-white/5 last:border-0">
                                <tr class="hover:bg-gray-800/20 transition-colors cursor-pointer group" @click="expanded = !expanded">
                                    <td class="p-4 text-sm text-gray-300">{{ $call->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="p-4 text-sm text-white font-medium">KI Agent</td>
                                    <td class="p-4 text-sm text-gray-300">
                                        {{ $call->contact_name ?? 'Unbekannt' }}<br>
                                        <span class="text-xs text-gray-500">{{ $call->phone }}</span>
                                    </td>
                                    <td class="p-4 text-sm text-gray-300">{{ gmdate("i:s", $call->duration_seconds ?? 0) }}</td>
                                    <td class="p-4 text-sm">
                                        @if($call->status === 'completed')
                                            <span class="px-2 py-1 bg-green-500/10 text-green-400 rounded-md text-xs border border-green-500/20">Beendet</span>
                                        @elseif($call->status === 'planned')
                                            <span class="px-2 py-1 bg-amber-500/10 text-amber-400 rounded-md text-xs border border-amber-500/20">Geplant (Wartet auf Freigabe)</span>
                                        @else
                                            <span class="px-2 py-1 bg-red-500/10 text-red-400 rounded-md text-xs border border-red-500/20">{{ ucfirst($call->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-sm text-right">
                                        <button class="text-[var(--theme-color)] group-hover:text-white transition-colors text-xs font-medium mr-2 flex items-center justify-end gap-1 ml-auto">
                                            <span x-text="expanded ? 'Schließen' : '{{ $call->status === 'planned' ? 'Plan ansehen' : 'Fazit ansehen' }}'"></span>
                                            <i class="bi transition-transform" :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        </button>
                                    </td>
                                </tr>
                                <!-- EXPANDED INLINE CONTENT -->
                                <tr x-show="expanded" style="display: none;" class="bg-gray-900/60 shadow-inner">
                                    <td colspan="6" class="p-0 border-t border-white/5">
                                        <div x-show="expanded" x-collapse>
                                            <div class="p-8">
                                                @if($call->status === 'planned')
                                                    <div class="flex items-start gap-4">
                                                        <div class="w-10 h-10 rounded-full bg-[var(--theme-color)]/20 flex items-center justify-center flex-shrink-0 text-[var(--theme-color)]">
                                                            <i class="bi bi-calendar-event text-lg"></i>
                                                        </div>
                                                        <div>
                                                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Geplanter Aufgabenplan</h4>
                                                            <p class="text-sm text-gray-300 leading-relaxed max-w-3xl">{{ $call->objective ?? 'Kein Plan hinterlegt.' }}</p>
                                                        </div>
                                                    </div>
                                                @else
                                                    @php
                                                        $transcriptArray = json_decode($call->transcript ?? '[]', true);
                                                        $stepsAndGoals = json_decode($call->next_steps ?? '[]', true);
                                                        // Fallback for old data where next_steps was an array of strings
                                                        if(isset($stepsAndGoals[0]) && is_string($stepsAndGoals[0])) {
                                                            $steps = $stepsAndGoals;
                                                            $goals = [];
                                                        } else {
                                                            $steps = $stepsAndGoals['steps'] ?? [];
                                                            $goals = $stepsAndGoals['goals'] ?? [];
                                                        }
                                                    @endphp

                                                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                                                        <!-- Left Column: Transcript (Chat Bubbles) -->
                                                        <div class="xl:col-span-7 bg-gray-950/50 rounded-2xl border border-white/5 p-6 h-[450px] flex flex-col">
                                                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 border-b border-white/5 pb-3 flex items-center gap-2">
                                                                <i class="bi bi-chat-text"></i> Gesprächsprotokoll
                                                            </h4>
                                                            <div class="overflow-y-auto pr-2 space-y-4 flex-1 custom-scrollbar">
                                                                @if(is_array($transcriptArray) && count($transcriptArray) > 0)
                                                                    @foreach($transcriptArray as $line)
                                                                        @if(str_starts_with($line, 'Anrufer:'))
                                                                            <div class="flex justify-end w-full">
                                                                                <div class="bg-gray-800 text-gray-200 px-4 py-2.5 rounded-2xl rounded-tr-sm text-sm max-w-[85%] shadow-md border border-white/5">
                                                                                    {{ trim(substr($line, 8)) }}
                                                                                </div>
                                                                            </div>
                                                                        @elseif(str_starts_with($line, 'KI:'))
                                                                            <div class="flex justify-start w-full">
                                                                                <div class="bg-[var(--theme-color)]/20 text-white px-4 py-2.5 rounded-2xl rounded-tl-sm text-sm max-w-[85%] shadow-md border border-[var(--theme-color)]/30">
                                                                                    {{ trim(substr($line, 3)) }}
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                @else
                                                                    <div class="flex h-full items-center justify-center text-gray-500 italic text-sm">
                                                                        Kein Protokoll verfügbar.
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <!-- Right Column: Goals, Summary, Next Steps -->
                                                        <div class="xl:col-span-5 space-y-6">
                                                            
                                                            <!-- Goals Evaluation -->
                                                            <div class="bg-gray-900/40 rounded-2xl border border-white/5 p-5">
                                                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                                                    <i class="bi bi-list-check"></i> Zielerreichung
                                                                </h4>
                                                                @if(count($goals) > 0)
                                                                    <ul class="space-y-3">
                                                                        @foreach($goals as $goal)
                                                                            <li class="flex items-start gap-3">
                                                                                @if($goal['achieved'] ?? false)
                                                                                    <i class="bi bi-check-circle-fill text-green-500 text-lg mt-0.5 shadow-sm rounded-full bg-green-500/10"></i>
                                                                                @else
                                                                                    <i class="bi bi-x-circle-fill text-red-500 text-lg mt-0.5 shadow-sm rounded-full bg-red-500/10"></i>
                                                                                @endif
                                                                                <span class="text-sm text-gray-300">{{ $goal['task'] ?? 'Unbekanntes Ziel' }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @else
                                                                    <p class="text-sm text-gray-500 italic border-l-2 border-gray-700 pl-3">
                                                                        Ursprünglicher Plan:<br>
                                                                        <span class="text-gray-400 mt-1 block">{{ $call->objective ?? 'Kein spezifischer Plan definiert.' }}</span>
                                                                    </p>
                                                                @endif
                                                            </div>

                                                            <!-- Summary -->
                                                            <div class="bg-gray-900/40 rounded-2xl border border-white/5 p-5">
                                                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                                    <i class="bi bi-card-text"></i> Fazit
                                                                </h4>
                                                                <p class="text-sm text-gray-300 leading-relaxed">{{ $call->summary ?? 'Kein Fazit verfügbar.' }}</p>
                                                            </div>

                                                            <!-- Next Steps -->
                                                            <div class="bg-gray-900/40 rounded-2xl border border-white/5 p-5">
                                                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                                    <i class="bi bi-arrow-right-circle"></i> Nächste Schritte
                                                                </h4>
                                                                @if(count($steps) > 0)
                                                                    <ul class="space-y-2">
                                                                        @foreach($steps as $step)
                                                                            <li class="flex items-start gap-2 text-sm text-gray-300">
                                                                                <i class="bi bi-arrow-right-short text-[var(--theme-color)] mt-0.5"></i>
                                                                                <span>{{ $step }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @else
                                                                    <p class="text-sm text-gray-500 italic">Keine nächsten Schritte definiert.</p>
                                                                @endif
                                                            </div>

                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-500">
                                        Noch keine Anrufe in der Historie.
                                    </td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>
                <div class="p-4 border-t border-white/5">
                    {{ $historyCalls->links() }}
                </div>
            </div>
        </div>
    @elseif($currentTab === 'settings')
        <div class="bg-gray-900/40 border border-white/5 rounded-2xl p-6 backdrop-blur-md">
            <h2 class="text-xl font-semibold text-white mb-4">Regeln & Limits</h2>
            <p class="text-gray-400 text-sm mb-6">Definiere, wann und wie viel die Agenten telefonieren dürfen.</p>
            
            <div class="space-y-4">
                <div class="p-4 border border-gray-700 rounded-xl flex justify-between items-center bg-gray-800/30">
                    <div>
                        <div class="text-white font-medium">Nachtruhe (Outbound)</div>
                        <div class="text-sm text-gray-400">Verbietet ausgehende Anrufe zwischen 20:00 und 08:00 Uhr.</div>
                    </div>
                    <div class="w-12 h-6 bg-[var(--theme-color)] rounded-full relative cursor-pointer opacity-80 hover:opacity-100 transition-opacity">
                        <div class="absolute right-1 top-1 w-4 h-4 bg-gray-900 rounded-full"></div>
                    </div>
                </div>
                <div class="p-4 border border-gray-700 rounded-xl flex justify-between items-center bg-gray-800/30">
                    <div>
                        <div class="text-white font-medium">Kosten-Limit pro Tag</div>
                        <div class="text-sm text-gray-400">Maximale Gesprächsminuten pro Agent und Tag.</div>
                    </div>
                    <div>
                        <input type="number" value="120" class="bg-gray-900 border border-gray-600 focus:border-[var(--theme-color)] rounded-lg text-white w-24 text-center px-2 py-1 outline-none"> <span class="text-gray-400 ml-1">Min.</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- TIPPS, TRICKS & HINWEISE WURDEN IN DAS MD FILE AUSGELAGERT --}}
</div>
