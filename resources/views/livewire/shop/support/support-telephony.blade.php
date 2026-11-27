<div style="--theme-color: {{ $this->themeColorHex }};" class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div class="flex items-center gap-2.5">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                <span class="text-[var(--theme-color)]"><i class="bi bi-telephone"></i></span>
                <span>Support Telefonie</span>
            </h1>
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400 cursor-help" title="KI-Anrufannahme, Live-Protokolle, Ticket-Routing und Steuerung für Web & Funkenwerk App">!</span>
        </div>
        <div>
            <div class="flex space-x-2 bg-gray-100 dark:bg-gray-800/50 p-1 rounded-xl backdrop-blur-md border border-gray-200 dark:border-white/10">
                <button wire:click="$set('currentTab', 'calls')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 {{ $currentTab === 'calls' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-white/5' }}">
                    Dashboard
                </button>
                <button wire:click="$set('currentTab', 'settings')" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 {{ $currentTab === 'settings' ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-white/5' }}">
                    Einstellungen
                </button>
            </div>
        </div>
    </div>

    @if($actionFeedback)
        <div class="flex items-center justify-between px-4 py-3 rounded-xl bg-white dark:bg-gray-900/70 border border-[var(--theme-color)]/40 text-sm text-gray-900 dark:text-white">
            <span class="font-medium">{{ $actionFeedback }}</span>
            <button type="button" wire:click="$set('actionFeedback', null)" class="text-xs font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white">Schließen</button>
        </div>
    @endif

    @if($currentTab === 'calls')
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white/90 dark:bg-gray-900/40 border border-gray-200/80 dark:border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Anrufe Heute</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color-15)] flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-telephone"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['total_calls_today'] }}</div>
            </div>

            <div class="bg-white/90 dark:bg-gray-900/40 border border-gray-200/80 dark:border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Kundenservice Heute</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color-15)] flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-headset"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['customer_calls_today'] }}</div>
            </div>

            <div class="bg-white/90 dark:bg-gray-900/40 border border-gray-200/80 dark:border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Erfolgsquote</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color-15)] flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['success_rate'] }}%</div>
            </div>

            <div class="bg-white/90 dark:bg-gray-900/40 border border-gray-200/80 dark:border-white/5 p-6 rounded-2xl backdrop-blur-md hover:border-[var(--theme-color)]/30 transition-colors shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Ø Dauer</h3>
                    <div class="h-8 w-8 rounded-full bg-[var(--theme-color-15)] flex items-center justify-center text-[var(--theme-color)]">
                        <i class="bi bi-stopwatch"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['avg_duration'] }}</div>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Live Calls Section -->
            <div class="bg-white/90 dark:bg-gray-800/60 border border-gray-200/80 dark:border-[var(--theme-color)]/20 rounded-2xl p-6 relative overflow-hidden shadow-sm transition-colors duration-300">
                <div class="absolute top-0 right-0 p-4">
                    <span class="flex h-3 w-3 relative">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--theme-color)] opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-[var(--theme-color)]"></span>
                    </span>
                </div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Aktive Anrufe</h2>
                
                @if($activeCalls->isEmpty())
                    <div class="text-gray-500 dark:text-gray-400 text-center py-8">
                        Aktuell telefoniert kein Agent.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($activeCalls as $call)
                            <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 p-4 rounded-xl flex flex-col space-y-3">
                                <div class="flex items-center space-x-4">
                                    <div class="h-12 w-12 rounded-full bg-[var(--theme-color)] flex items-center justify-center text-gray-900 font-bold text-lg dark:shadow-[0_0_10px_var(--theme-color)]">
                                        KI
                                    </div>
                                    <div>
                                        <div class="text-gray-900 dark:text-white font-medium">Sprach-KI Agent</div>
                                        <div class="text-sm text-[var(--theme-color)] flex items-center space-x-1">
                                            <i class="bi bi-telephone-outbound"></i>
                                            <span>{{ $call->contact_name ?? $call->phone }}</span>
                                        </div>
                                        <div class="text-xs text-emerald-600 dark:text-emerald-500 mt-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Läuft aktuell</div>
                                    </div>
                                </div>
                                @if($call->objective)
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-1">
                                        <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1.5">Aufgabenplan:</div>
                                        <div class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed italic border-l-2 border-[var(--theme-color)] pl-3">{{ $call->objective }}</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Call History -->
            <div class="bg-white/90 dark:bg-gray-900/40 border border-gray-200/80 dark:border-white/5 rounded-2xl overflow-hidden backdrop-blur-md shadow-sm transition-colors duration-300">
                <div class="p-6 border-b border-gray-200/80 dark:border-white/5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Anruf-Historie</h2>
                        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800/60 p-1 rounded-xl border border-gray-200 dark:border-white/10">
                            @foreach(['all' => 'Alle', 'customer_service' => 'Kundenservice', 'secretary' => 'Privat (VIP)', 'spam' => 'Spam'] as $mKey => $mLabel)
                                <button type="button" wire:click="setFilterMode('{{ $mKey }}')" class="px-3 py-1 rounded-lg text-xs font-semibold transition-colors {{ $filterMode === $mKey ? 'bg-[var(--theme-color)] text-gray-900' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                    {{ $mLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="w-full sm:w-64">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suchen (Name, Nummer, Ticket)..." class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl text-sm text-gray-900 dark:text-white px-3 py-2 outline-none">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-800/30 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider border-b border-gray-200/80 dark:border-white/5">
                                <th class="p-4 font-medium">Datum</th>
                                <th class="p-4 font-medium">Typ</th>
                                <th class="p-4 font-medium">Kontakt</th>
                                <th class="p-4 font-medium">Dauer</th>
                                <th class="p-4 font-medium">Status</th>
                                <th class="p-4 font-medium text-right">Aktion</th>
                            </tr>
                        </thead>
                        @forelse($historyCalls as $call)
                            @php
                                $rowMeta = is_string($call->next_steps) ? (json_decode($call->next_steps, true) ?: []) : (is_array($call->next_steps) ? $call->next_steps : []);
                                $isCustomerRow = ($rowMeta['call_mode'] ?? ($rowMeta['mode'] ?? '')) === 'inbound_customer_service' || str_starts_with((string) $call->objective, 'Funkira Kundenservice:');
                                $isResolvedRow = !empty($rowMeta['resolved']);
                                $rowUrgency = strtolower((string) ($rowMeta['urgency'] ?? 'mittel'));
                            @endphp
                            <tbody wire:key="call-row-{{ $call->id }}" x-data="{ expanded: false }" class="border-b border-gray-100 dark:border-white/5 last:border-0">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/20 transition-colors cursor-pointer group" @click="expanded = !expanded">
                                    <td class="p-4 text-sm text-gray-700 dark:text-gray-300 tabular-nums">{{ $call->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="p-4 text-sm font-semibold">
                                        @if($call->status === 'spam_filtered')
                                            <span class="text-rose-600 dark:text-rose-400">Spam</span>
                                        @elseif($isCustomerRow)
                                            <span class="text-[var(--theme-color)]">Kundenservice</span>
                                            @if(!empty($rowMeta['support_ticket_number']))
                                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $rowMeta['support_ticket_number'] }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-900 dark:text-white">Privat / VIP</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $call->contact_name ?? 'Unbekannt' }}</span><br>
                                        <span class="text-xs text-gray-500">{{ $call->phone }}</span>
                                    </td>
                                    <td class="p-4 text-sm text-gray-700 dark:text-gray-300 tabular-nums">{{ gmdate("i:s", $call->duration_seconds ?? 0) }}</td>
                                    <td class="p-4 text-sm font-semibold">
                                        @if($isResolvedRow)
                                            <span class="text-emerald-700 dark:text-emerald-400">Erledigt</span>
                                        @elseif($call->status === 'spam_filtered')
                                            <span class="text-gray-500 dark:text-gray-400">Gefiltert</span>
                                        @elseif($call->status === 'completed')
                                            <span class="{{ $rowUrgency === 'hoch' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                                {{ $rowUrgency === 'hoch' ? 'Dringend' : 'Aufgenommen' }}
                                            </span>
                                        @elseif($call->status === 'planned')
                                            <span class="text-amber-600 dark:text-amber-400">Geplant</span>
                                        @else
                                            <span class="text-rose-600 dark:text-rose-400">{{ ucfirst($call->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="p-4 text-sm text-right">
                                        <button class="text-[var(--theme-color)] group-hover:text-gray-900 dark:group-hover:text-white transition-colors text-xs font-medium mr-2 flex items-center justify-end gap-1 ml-auto">
                                            <span x-text="expanded ? 'Schließen' : '{{ $call->status === 'planned' ? 'Plan ansehen' : 'Fazit ansehen' }}'"></span>
                                            <i class="bi transition-transform" :class="expanded ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                        </button>
                                    </td>
                                </tr>
                                <!-- EXPANDED INLINE CONTENT -->
                                <tr x-show="expanded" style="display: none;" class="bg-gray-50/80 dark:bg-gray-900/60 dark:shadow-inner">
                                    <td colspan="6" class="p-0 border-t border-gray-200/80 dark:border-white/5">
                                        <div x-show="expanded" x-collapse>
                                            <div class="p-8">
                                                @if($call->status === 'planned')
                                                    <div class="flex items-start gap-4">
                                                        <div class="w-10 h-10 rounded-full bg-[var(--theme-color-15)] flex items-center justify-center flex-shrink-0 text-[var(--theme-color)]">
                                                            <i class="bi bi-calendar-event text-lg"></i>
                                                        </div>
                                                        <div>
                                                            <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2">Geplanter Aufgabenplan</h4>
                                                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed max-w-3xl">{{ $call->objective ?? 'Kein Plan hinterlegt.' }}</p>
                                                        </div>
                                                    </div>
                                                @else
                                                    @php
                                                        $transcriptArray = json_decode($call->transcript ?? '[]', true);
                                                        $stepsAndGoals = $rowMeta;
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
                                                        <div class="xl:col-span-6 bg-white dark:bg-gray-950/50 rounded-2xl border border-gray-200 dark:border-white/5 p-6 h-[450px] flex flex-col shadow-sm">
                                                            <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-4 border-b border-gray-200 dark:border-white/5 pb-3 flex items-center gap-2">
                                                                <i class="bi bi-chat-text"></i> Gesprächsprotokoll
                                                            </h4>
                                                            <div class="overflow-y-auto pr-2 space-y-4 flex-1 custom-scrollbar">
                                                                @if(is_array($transcriptArray) && count($transcriptArray) > 0)
                                                                    @foreach($transcriptArray as $line)
                                                                        @if(str_starts_with($line, 'Anrufer:'))
                                                                            <div class="flex justify-end w-full">
                                                                                <div class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-4 py-2.5 rounded-2xl rounded-tr-sm text-sm max-w-[85%] shadow-sm border border-gray-200 dark:border-white/5">
                                                                                    {{ trim(substr($line, 8)) }}
                                                                                </div>
                                                                            </div>
                                                                        @elseif(str_starts_with($line, 'Funkira:') || str_starts_with($line, 'KI:'))
                                                                            <div class="flex justify-start w-full">
                                                                                <div class="bg-[var(--theme-color-15)] text-gray-900 dark:text-white px-4 py-2.5 rounded-2xl rounded-tl-sm text-sm max-w-[85%] shadow-sm border border-[var(--theme-color)]/30">
                                                                                    {{ trim(str_starts_with($line, 'Funkira:') ? substr($line, 8) : substr($line, 3)) }}
                                                                                </div>
                                                                            </div>
                                                                        @else
                                                                            <div class="flex justify-start w-full">
                                                                                <div class="bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-xl text-xs max-w-[90%] border border-gray-200 dark:border-white/5">
                                                                                    {{ $line }}
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

                                                        <!-- Right Column: Summary, Ticket Links, Reply & Resolve -->
                                                        <div class="xl:col-span-6 space-y-5">
                                                            <!-- Summary & Links -->
                                                            <div class="bg-white dark:bg-gray-900/40 rounded-2xl border border-gray-200 dark:border-white/5 p-5 shadow-sm">
                                                                <div class="flex items-center justify-between gap-3 mb-3">
                                                                    <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                                                        <i class="bi bi-card-text"></i> Fazit & Verknüpfung
                                                                    </h4>
                                                                    @if(!empty($call->phone) && $call->phone !== 'Unterdrückt')
                                                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $call->phone) }}" class="text-xs font-bold text-[var(--theme-color)] hover:underline flex items-center gap-1">
                                                                            <i class="bi bi-telephone-outbound"></i> Zurückrufen
                                                                        </a>
                                                                    @endif
                                                                </div>
                                                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $call->summary ?? 'Kein Fazit verfügbar.' }}</p>

                                                                @if(!empty($stepsAndGoals['support_ticket_number']) || !empty($stepsAndGoals['ticket_number']) || !empty($stepsAndGoals['caller_email']) || !empty($stepsAndGoals['order_number']))
                                                                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-white/5 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold">
                                                                        @if(!empty($stepsAndGoals['support_ticket_number']))
                                                                            <a href="{{ ($stepsAndGoals['support_ticket_type'] ?? '') === 'support_ticket' ? route('admin.support-tickets') : route('admin.support-contact-form') }}" class="text-[var(--theme-color)] hover:underline">
                                                                                Support: {{ $stepsAndGoals['support_ticket_number'] }}
                                                                            </a>
                                                                        @endif
                                                                        @if(!empty($stepsAndGoals['ticket_number']))
                                                                            <a href="{{ url('/admin/tickets') }}" class="text-gray-700 dark:text-gray-300 hover:underline">
                                                                                Vorgang: {{ $stepsAndGoals['ticket_number'] }}
                                                                            </a>
                                                                        @endif
                                                                        @if(!empty($stepsAndGoals['order_number']))
                                                                            <span class="text-gray-600 dark:text-gray-400">Bestellung: {{ $stepsAndGoals['order_number'] }}</span>
                                                                        @endif
                                                                        @if(!empty($stepsAndGoals['caller_email']))
                                                                            <span class="text-gray-600 dark:text-gray-400">E-Mail: {{ $stepsAndGoals['caller_email'] }}</span>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            @if(!empty($stepsAndGoals['rahmenbedingungen']) && is_array($stepsAndGoals['rahmenbedingungen']))
                                                                <div class="bg-white dark:bg-gray-900/40 rounded-2xl border border-gray-200 dark:border-white/5 p-5 shadow-sm">
                                                                    <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                                        <i class="bi bi-check2-square"></i> Rahmenbedingungen
                                                                    </h4>
                                                                    <ul class="space-y-1.5">
                                                                        @foreach($stepsAndGoals['rahmenbedingungen'] as $rb)
                                                                            <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                                                <span class="text-[#C5A059] font-bold">✓</span>
                                                                                <span>{{ $rb }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                            @endif

                                                            <!-- Next Steps -->
                                                            <div class="bg-white dark:bg-gray-900/40 rounded-2xl border border-gray-200 dark:border-white/5 p-5 shadow-sm">
                                                                <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                                                    <i class="bi bi-arrow-right-circle"></i> Nächste Schritte
                                                                </h4>
                                                                @if(count($steps) > 0)
                                                                    <ul class="space-y-2">
                                                                        @foreach($steps as $step)
                                                                            <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                                                <i class="bi bi-arrow-right-short text-[var(--theme-color)] mt-0.5"></i>
                                                                                <span>{{ $step }}</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @else
                                                                    <p class="text-sm text-gray-500 italic">Keine nächsten Schritte definiert.</p>
                                                                @endif
                                                            </div>

                                                            <!-- Direct Reply & Resolve Action -->
                                                            @if($call->status !== 'spam_filtered')
                                                                <div class="bg-white dark:bg-gray-900/40 rounded-2xl border border-gray-200 dark:border-white/5 p-5 shadow-sm space-y-3">
                                                                    <div class="flex items-center justify-between">
                                                                        <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                                                            <i class="bi bi-reply"></i> Direkt-Antwort & Abschluss
                                                                        </h4>
                                                                        @if(!$isResolvedRow)
                                                                            <button type="button" wire:click="resolveCall({{ $call->id }})" class="text-xs font-bold text-emerald-700 dark:text-emerald-400 hover:underline flex items-center gap-1 cursor-pointer">
                                                                                <i class="bi bi-check2-circle"></i> Als erledigt markieren
                                                                            </button>
                                                                        @else
                                                                            <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">✓ Erledigt</span>
                                                                        @endif
                                                                    </div>
                                                                    @if(!empty($stepsAndGoals['last_reply']))
                                                                        <div class="text-xs text-gray-600 dark:text-gray-400 border-l-2 border-[var(--theme-color)] pl-3">
                                                                            <span class="font-semibold text-gray-800 dark:text-gray-200">Letzte Antwort:</span> {{ $stepsAndGoals['last_reply'] }}
                                                                        </div>
                                                                    @endif
                                                                    <textarea wire:model="replyDrafts.{{ $call->id }}" rows="3" placeholder="Antwort auf Ticket / Anliegen schreiben..." class="w-full bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-700 focus:border-[var(--theme-color)] rounded-xl p-3 text-sm text-gray-900 dark:text-white outline-none"></textarea>
                                                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                                                        @if(!empty($stepsAndGoals['caller_email']))
                                                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400 cursor-pointer">
                                                                                <input type="checkbox" wire:model="replySendEmail.{{ $call->id }}" class="rounded border-gray-300 dark:border-gray-600 text-[var(--theme-color)]">
                                                                                <span>Zusätzlich per E-Mail senden ({{ $stepsAndGoals['caller_email'] }})</span>
                                                                            </label>
                                                                        @else
                                                                            <span></span>
                                                                        @endif
                                                                        <button type="button" wire:click="sendCallReply({{ $call->id }})" class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-900 font-bold text-xs hover:opacity-90 transition cursor-pointer">
                                                                            Antwort senden
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            @endif
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
                                        Noch keine Anrufe in dieser Ansicht.
                                    </td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>
                <div class="p-4 border-t border-gray-200/80 dark:border-white/5">
                    {{ $historyCalls->links() }}
                </div>
            </div>
        </div>
    @elseif($currentTab === 'settings')
        <div class="bg-white/90 dark:bg-gray-900/40 border border-gray-200/80 dark:border-white/5 rounded-2xl p-6 backdrop-blur-md shadow-sm transition-colors duration-300">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">KI-Telefonie Steuerung</h2>

            <div class="space-y-4">
                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-xl flex justify-between items-center bg-gray-50 dark:bg-gray-800/30">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-900 dark:text-white font-medium">KI-Anrufannahme</span>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400 cursor-help" title="Hauptschalter für eingehende Live-Anrufe durch Funkira (synchronisiert mit der Funkenwerk App)">!</span>
                    </div>
                    <button type="button" wire:click="toggleConciergeEnabled" aria-label="KI-Anrufannahme umschalten" class="w-12 h-6 {{ $conciergeEnabled ? 'bg-[var(--theme-color)]' : 'bg-gray-300 dark:bg-gray-700' }} rounded-full relative cursor-pointer transition-colors">
                        <div class="absolute top-1 w-4 h-4 bg-white dark:bg-gray-900 rounded-full shadow-sm transition-all {{ $conciergeEnabled ? 'right-1' : 'left-1' }}"></div>
                    </button>
                </div>

                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-xl flex justify-between items-center bg-gray-50 dark:bg-gray-800/30">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-900 dark:text-white font-medium">Vollständiger Kundenservice</span>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400 cursor-help" title="Übernimmt zusätzlich zur privaten Whitelist alle Kunden- und Geschäftsanrufe, erkennt Spam automatisch und legt strukturierte Tickets für die App an">!</span>
                    </div>
                    <button type="button" wire:click="toggleCustomerServiceEnabled" aria-label="Kundenservice-Anrufannahme umschalten" class="w-12 h-6 {{ $customerServiceEnabled ? 'bg-[var(--theme-color)]' : 'bg-gray-300 dark:bg-gray-700' }} rounded-full relative cursor-pointer transition-colors">
                        <div class="absolute top-1 w-4 h-4 bg-white dark:bg-gray-900 rounded-full shadow-sm transition-all {{ $customerServiceEnabled ? 'right-1' : 'left-1' }}"></div>
                    </button>
                </div>

                <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-xl flex flex-wrap justify-between items-center gap-4 bg-gray-50 dark:bg-gray-800/30">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-900 dark:text-white font-medium">Klingelzeit</span>
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-gray-300 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400 cursor-help" title="Wartezeit in Sekunden bis zur Übernahme durch Funkira (0 = Sofort, synchronisiert mit den Telefon-Einstellungen der Funkenwerk App)">!</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach([0 => 'Sofort', 8 => '8 Sek.', 12 => '12 Sek.', 18 => '18 Sek.'] as $sec => $secLabel)
                            <button type="button" wire:click="setQuickRingDelay({{ $sec }})" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition cursor-pointer {{ $ringDelaySeconds === $sec ? 'bg-[var(--theme-color)] text-gray-900 border-[var(--theme-color)]' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[var(--theme-color)]' }}">
                                {{ $secLabel }}
                            </button>
                        @endforeach
                        <input type="number" min="0" max="30" wire:model="ringDelaySeconds" wire:change="saveRingDelay" class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 focus:border-[var(--theme-color)] rounded-lg text-gray-900 dark:text-white w-20 text-center px-2 py-1 text-sm outline-none shadow-sm">
                        <span class="text-gray-500 dark:text-gray-400 text-xs">Sek.</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- TIPPS, TRICKS & HINWEISE WURDEN IN DAS MD FILE AUSGELAGERT --}}
</div>
