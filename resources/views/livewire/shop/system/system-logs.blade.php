<div class="space-y-6 md:space-y-8 pb-20 font-sans antialiased text-gray-300" style="--theme-color: {{ $this->themeColorHex }};">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-hidden animate-fade-in-up">
        <div class="hidden dark:block absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
            <x-heroicon-o-server-stack class="w-40 h-40 text-[var(--theme-color)] drop-shadow-[0_0_20px_var(--theme-color)1)]" />
        </div>
        <div class="relative z-10">
            <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Log</h1>
            <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm font-medium">Systemübergreifende Fehler-, Ereignis- und Aktivitätenverfolgung.</p>
        </div>

        <div class="flex items-center gap-3 relative z-10">
            <button wire:click="clearFilters" class="inline-flex items-center px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner hover:bg-gray-100 dark:hover:bg-gray-800">
                <x-heroicon-o-x-mark class="w-4 h-4 mr-2" /> Filter zurücksetzen
            </button>
        </div>
    </div>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-hidden group hover:border-gray-300 dark:hover:border-gray-700 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-server-stack class="w-6 h-6"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Gesamte Logs</p>
                <div class="text-3xl font-serif text-gray-900 dark:text-white">{{ number_format($totalLogs, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-hidden group hover:border-gray-300 dark:hover:border-gray-700 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-emerald-600 dark:text-emerald-500 shadow-sm dark:shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-chart-bar class="w-6 h-6 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Logs Heute</p>
                <div class="text-3xl font-serif text-gray-900 dark:text-white">+{{ number_format($logsToday, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-sm dark:shadow-2xl border border-red-200 dark:border-red-900/30 relative overflow-hidden group hover:border-red-300 dark:hover:border-red-500/50 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 flex items-center justify-center text-red-600 dark:text-red-500 shadow-sm dark:shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-red-500 font-black uppercase tracking-widest mb-1">Errors / Warnungen</p>
                <div class="text-3xl font-serif text-gray-900 dark:text-white">
                    {{ number_format($totalErrors, 0, ',', '.') }}
                    @if(($totalWarnings ?? 0) > 0)
                        <span class="text-sm font-sans font-bold text-amber-500 ml-2">({{ number_format($totalWarnings, 0, ',', '.') }} Warn.)</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-hidden group hover:border-gray-300 dark:hover:border-gray-700 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-sky-600 dark:text-sky-500 shadow-sm dark:shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-cpu-chip class="w-6 h-6 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Aktive AI Agenten</p>
                <div class="text-3xl font-serif text-gray-900 dark:text-white">{{ count($agents) }}</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 sm:gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-md p-3 sm:p-4 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl items-center animate-fade-in-up">
        <div class="md:col-span-1 relative group">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Suche in Nachricht..."
                   class="w-full pl-12 pr-4 py-4 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner transition-all text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 outline-none text-[10px] font-black tracking-widest uppercase">
            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-600 group-focus-within:text-[var(--theme-color)] transition-colors" />
            </div>
        </div>
        <select wire:model.live="domainFilter" class="md:col-span-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer outline-none appearance-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Alle Sub-Systeme</option>
            @foreach($uniqueDomains as $domain)
                <option value="{{ $domain }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">System: {{ strtoupper($domain) }}</option>
            @endforeach
        </select>
        <select wire:model.live="agentFilter" class="md:col-span-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer outline-none appearance-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Alle Agenten / Bereiche</option>
            <option value="system" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">System (Kein Agent)</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ $agent->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="typeFilter" class="md:col-span-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer outline-none appearance-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Alle Typen</option>
            @foreach($uniqueTypes as $type)
                <option value="{{ $type }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ $type }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="md:col-span-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer outline-none appearance-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Alle Status</option>
            <option value="success" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">SUCCESS</option>
            <option value="running" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">RUNNING</option>
            <option value="error" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">ERROR</option>
            <option value="info" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">INFO</option>
            <option value="warning" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">WARNING</option>
        </select>
    </div>

    {{-- Log Table --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden w-full relative animate-fade-in-up">
        <div class="overflow-x-auto w-full custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black text-gray-600 dark:text-gray-500 uppercase tracking-widest shadow-sm dark:shadow-inner">
                        <th class="px-6 sm:px-8 py-6 w-[15%]">Zeitstempel</th>
                        <th class="px-6 sm:px-8 py-6 w-[20%]">Agent / Bereich</th>
                        <th class="px-6 sm:px-8 py-6 w-[15%]">Typ & Aktion</th>
                        <th class="px-6 sm:px-8 py-6 w-[40%]">Nachricht</th>
                        <th class="px-6 sm:px-8 py-6 w-[10%] text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/50">
                    @forelse($logs as $log)
                        <tbody x-data="{ expanded: false }" class="transition-colors group hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <tr @click="expanded = !expanded" class="cursor-pointer">
                                <td class="px-6 sm:px-8 py-4 align-top border-l-[3px] {{ $log->status === 'error' ? 'border-red-500' : ($log->status === 'warning' ? 'border-amber-500' : ($log->status === 'success' ? 'border-emerald-500' : 'border-gray-700')) }}">
                                    <div class="flex flex-col mt-0.5">
                                        <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $log->created_at->format('H:i:s') }}</span>
                                        <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black mt-1">{{ $log->created_at->format('d.m.Y') }}</span>
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    @if($log->agent)
                                        @php 
                                            // Lade die Abteilungsfarbe, falls verfügbar, sonst die Basis-Farbe des Agenten
                                            $agentColor = $log->agent->department ? $log->agent->department->color : $log->agent->color; 
                                        @endphp
                                        <div class="flex items-center gap-3 mt-1">
                                            <div class="w-6 h-6 rounded bg-{{ $agentColor }}-500/10 flex items-center justify-center border border-{{ $agentColor }}-500/30 text-{{ $agentColor }}-500">
                                                <i class="{{ $log->agent->icon }} text-xs"></i>
                                            </div>
                                            <span class="text-{{ $agentColor }}-400 font-bold text-sm">{{ $log->agent->name }}</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-3 mt-1">
                                            @if(in_array($log->action_id, ['user:profile_updated_frontend', 'user:security_update']))
                                                <div class="w-6 h-6 rounded bg-fuchsia-500/10 flex items-center justify-center border border-fuchsia-500/30 text-fuchsia-400">
                                                    @if($log->action_id === 'user:security_update')
                                                        <x-heroicon-o-shield-check class="w-3.5 h-3.5" />
                                                    @else
                                                        <x-heroicon-o-finger-print class="w-3.5 h-3.5" />
                                                    @endif
                                                </div>
                                            @else
                                                <div class="w-6 h-6 rounded bg-gray-800 flex items-center justify-center border border-gray-700 text-gray-400">
                                                    <x-heroicon-o-cpu-chip class="w-3.5 h-3.5"/>
                                                </div>
                                            @endif
                                            <span class="text-gray-800 dark:text-gray-300 font-bold text-sm">System <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 ml-1">({{ $log->action_id }})</span></span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="flex flex-col mt-1">
                                        <span class="text-xs font-bold text-[var(--theme-color)]">{{ $log->type }}</span>
                                        <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black mt-1">{{ $log->action_id }}</span>
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top max-w-xs">
                                    @if(!empty($log->title))
                                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">{{ $log->title }}</div>
                                    @endif
                                    <div class="text-xs text-gray-700 dark:text-gray-300 font-medium truncate mt-0.5 group-hover:text-gray-900 dark:group-hover:text-white transition-colors" title="{{ $log->message }}">
                                        {{ $log->message }}
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 text-right align-top">
                                    <div class="flex justify-end items-center gap-3 mt-1">
                                        @if($log->status === 'success')
                                            <button wire:click.stop="toggleStatus({{ $log->id }})" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.1)] hover:bg-emerald-500/20 transition-colors">GELÖST</button>
                                        @elseif($log->status === 'running')
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-sky-500/30 bg-sky-500/10 text-sky-400 flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-ping"></span> EXECUTING
                                            </span>
                                        @elseif($log->status === 'error')
                                            <button wire:click.stop="toggleStatus({{ $log->id }})" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-red-500/30 bg-red-500/10 text-red-400 shadow-[0_0_10px_rgba(239,68,68,0.1)] flex items-center gap-1.5 hover:bg-red-500/20 transition-colors">
                                                FEHLER
                                                @if($log->error_count > 1)
                                                    <span class="bg-red-500 text-white rounded px-1.5 py-0.5 text-[8px] ml-1">{{ $log->error_count }}x</span>
                                                @endif
                                            </button>
                                        @elseif($log->status === 'warning')
                                            <button wire:click.stop="toggleStatus({{ $log->id }})" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-amber-500/30 bg-amber-500/10 text-amber-400 shadow-[0_0_10px_rgba(245,158,11,0.1)] flex items-center gap-1.5 hover:bg-amber-500/20 transition-colors">
                                                WARNUNG
                                                @if($log->error_count > 1)
                                                    <span class="bg-amber-500 text-black rounded px-1.5 py-0.5 text-[8px] ml-1">{{ $log->error_count }}x</span>
                                                @endif
                                            </button>
                                        @else
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-gray-700 bg-gray-800 text-gray-400">{{ strtoupper($log->status) }}</span>
                                        @endif
                                        <button wire:click.stop="deleteLog({{ $log->id }})" wire:confirm="Bist du sicher, dass du diesen Log endgültig löschen möchtest?" class="p-1 rounded-lg text-gray-600 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Löschen">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                        <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-600 transition-transform group-hover:text-gray-400" x-bind:class="expanded ? 'rotate-180 text-[var(--theme-color)]' : ''" />
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="expanded" x-transition x-cloak>
                                <td colspan="5" class="px-8 py-6 bg-gray-50/80 dark:bg-gray-950/80 border-t border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                                    @php
                                        $cPayload = is_array($log->payload) ? $log->payload : (json_decode($log->payload, true) ?? []);
                                        $diag = $cPayload['diagnosis'] ?? null;
                                        $tel = $cPayload['session_telemetry'] ?? null;
                                        $execCtx = $cPayload['execution_context'] ?? null;
                                        $traceList = $cPayload['trace_excerpt'] ?? null;
                                        $rawStack = $cPayload['stack'] ?? null;
                                        $recentEvents = $cPayload['recent_events'] ?? null;
                                    @endphp
                                    <div class="space-y-4">
                                        <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                                            <div class="flex-1 w-full">
                                                <div class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-2 flex items-center gap-2">
                                                    <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                                                    {{ $log->title ? $log->title . ' — ' : '' }}Nachrichten-Trace
                                                </div>
                                                <div class="text-xs text-gray-800 dark:text-gray-300 whitespace-pre-wrap break-all p-4 bg-white dark:bg-black rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner font-mono leading-relaxed">{{ $log->message }}</div>
                                            </div>
                                        </div>

                                        {{-- Automatische Ursachen-Diagnose --}}
                                        @if(!empty($diag))
                                            <div class="p-4 rounded-xl border border-amber-500/30 bg-amber-500/5 dark:bg-amber-950/20 space-y-1.5">
                                                <div class="text-[10px] font-black uppercase tracking-widest text-amber-500 flex items-center gap-2">
                                                    <x-heroicon-o-light-bulb class="w-4 h-4" /> Automatische Ursachen-Diagnose
                                                    @if(is_array($diag) && !empty($diag['category']))
                                                        <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono text-[9px]">{{ $diag['category'] }}</span>
                                                    @endif
                                                </div>
                                                @if(is_array($diag))
                                                    <p class="text-xs text-gray-800 dark:text-gray-200 font-medium"><strong>Ursache:</strong> {{ $diag['probable_cause'] ?? '-' }}</p>
                                                    @if(!empty($diag['recommended_action']))
                                                        <p class="text-xs text-emerald-600 dark:text-emerald-400"><strong>Empfehlung:</strong> {{ $diag['recommended_action'] }}</p>
                                                    @endif
                                                @else
                                                    <p class="text-xs text-gray-800 dark:text-gray-200 font-medium">{{ $diag }}</p>
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Fehler-Ursprung (Datei, Zeile, Exception, Request / WebSocket-Session) --}}
                                        @if(!empty($cPayload['file']) || !empty($cPayload['exception_class']) || !empty($tel) || !empty($execCtx))
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[11px] font-mono">
                                                @if(!empty($cPayload['file']) || !empty($cPayload['exception_class']))
                                                    <div class="p-3.5 rounded-xl bg-white dark:bg-black border border-gray-200 dark:border-gray-800 space-y-1">
                                                        <div class="text-[9px] font-sans font-black uppercase tracking-widest text-red-400">Code-Ursprung</div>
                                                        @if(!empty($cPayload['exception_class']))
                                                            <div class="text-gray-900 dark:text-white font-bold break-all">{{ $cPayload['exception_class'] }}</div>
                                                        @endif
                                                        @if(!empty($cPayload['file']))
                                                            <div class="text-gray-600 dark:text-gray-400 break-all">{{ $cPayload['file'] }}:{{ $cPayload['line'] ?? '?' }}</div>
                                                        @endif
                                                    </div>
                                                @endif
                                                @if(!empty($tel) && is_array($tel))
                                                    <div class="p-3.5 rounded-xl bg-white dark:bg-black border border-gray-200 dark:border-gray-800 space-y-1">
                                                        <div class="text-[9px] font-sans font-black uppercase tracking-widest text-sky-400">Live-Session & Client</div>
                                                        <div class="text-gray-800 dark:text-gray-300">Client: <strong>{{ $tel['client_type'] ?? 'Unbekannt' }}</strong> (IP: {{ $tel['client_ip'] ?? '-' }})</div>
                                                        <div class="text-gray-600 dark:text-gray-400">Modell: {{ $tel['requested_model'] ?? '-' }} &rarr; {{ $tel['effective_model'] ?? '-' }} | Dauer: {{ $tel['session_duration_sec'] ?? 0 }}s</div>
                                                        <div class="text-gray-600 dark:text-gray-400">Audio-Pakete: Client={{ $tel['client_audio_packets_sent'] ?? 0 }}, Google={{ $tel['google_audio_chunks_received'] ?? 0 }}</div>
                                                    </div>
                                                @elseif(!empty($execCtx))
                                                    <div class="p-3.5 rounded-xl bg-white dark:bg-black border border-gray-200 dark:border-gray-800 space-y-1">
                                                        <div class="text-[9px] font-sans font-black uppercase tracking-widest text-sky-400">Aufruf-Kontext</div>
                                                        @if(is_array($execCtx))
                                                            <div class="text-gray-800 dark:text-gray-300 break-all">{{ strtoupper($execCtx['mode'] ?? 'HTTP') }}: {{ $execCtx['method'] ?? '' }} {{ $execCtx['url'] ?? ($execCtx['command'] ?? '') }}</div>
                                                            @if(!empty($execCtx['ip']))
                                                                <div class="text-gray-600 dark:text-gray-400">IP: {{ $execCtx['ip'] }} | Route: {{ $execCtx['route'] ?? '-' }}</div>
                                                            @endif
                                                        @else
                                                            <div class="text-gray-800 dark:text-gray-300 break-all">{{ $execCtx }}</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Chronologie der letzten WebSocket-Ereignisse --}}
                                        @if(!empty($recentEvents) && is_array($recentEvents))
                                            <div>
                                                <div class="text-[10px] font-black uppercase tracking-widest text-sky-400 mb-2 flex items-center gap-2">
                                                    <x-heroicon-o-clock class="w-3.5 h-3.5" /> Letzte Protokoll-Ereignisse vor dem Ausfall
                                                </div>
                                                <div class="p-3.5 bg-white dark:bg-black rounded-xl border border-gray-200 dark:border-gray-800 font-mono text-[10px] space-y-1 text-gray-700 dark:text-gray-300">
                                                    @foreach($recentEvents as $ev)
                                                        <div><span class="text-sky-400">[+{{ $ev['t_ms'] ?? 0 }}ms]</span> <strong>{{ $ev['event'] ?? 'event' }}</strong> {{ !empty($ev['detail']) ? '— ' . $ev['detail'] : '' }}</div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Stack-Trace Auszug --}}
                                        @if((!empty($traceList) && is_array($traceList)) || !empty($rawStack))
                                            <div>
                                                <div class="text-[10px] font-black uppercase tracking-widest text-red-400 mb-2 flex items-center gap-2">
                                                    <x-heroicon-o-bug-ant class="w-3.5 h-3.5" /> Stack-Trace
                                                </div>
                                                <div class="p-4 bg-white dark:bg-black rounded-xl border border-red-500/20 font-mono text-[10px] text-red-700 dark:text-red-300/90 whitespace-pre-wrap break-all leading-relaxed">@if(!empty($traceList) && is_array($traceList)){{ implode("\n", $traceList) }}@else{{ $rawStack }}@endif</div>
                                            </div>
                                        @endif

                                        @if($log->payload)
                                            @if(isset($log->payload['changes']))
                                                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div class="bg-red-50 dark:bg-red-900/10 p-5 rounded-2xl border border-red-200 dark:border-red-500/20 shadow-sm dark:shadow-inner overflow-hidden">
                                                        <span class="text-[9px] font-black text-red-400 uppercase tracking-widest block mb-3 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Vorheriger Stand</span>
                                                        <div class="text-[10px] text-red-900 dark:text-red-200/70 font-mono leading-relaxed space-y-1.5 w-full break-all">
                                                            @foreach($log->payload['changes'] as $key => $change)
                                                                <div class="flex justify-between gap-4 py-1 border-b border-red-500/10 last:border-0">
                                                                    <span class="font-bold text-red-300">{{ $key }}:</span>
                                                                    <span class="text-right">
                                                                        @if(is_bool($change['old'])) {{ $change['old'] ? 'true' : 'false' }} 
                                                                        @elseif(is_null($change['old']) || $change['old'] === '') <em>null</em> 
                                                                        @else {{ Str::limit((string)$change['old'], 100) }} @endif
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="bg-emerald-50 dark:bg-emerald-900/10 p-5 rounded-2xl border border-emerald-200 dark:border-emerald-500/20 shadow-sm dark:shadow-inner overflow-hidden">
                                                        <span class="text-[9px] font-black text-emerald-400 uppercase tracking-widest block mb-3 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Aktueller Stand</span>
                                                        <div class="text-[10px] text-emerald-900 dark:text-emerald-200/70 font-mono leading-relaxed space-y-1.5 w-full break-all">
                                                            @foreach($log->payload['changes'] as $key => $change)
                                                                <div class="flex justify-between gap-4 py-1 border-b border-emerald-500/10 last:border-0">
                                                                    <span class="font-bold text-emerald-300">{{ $key }}:</span>
                                                                    <span class="text-right">
                                                                        @if(is_bool($change['new'])) {{ $change['new'] ? 'true' : 'false' }} 
                                                                        @elseif(is_null($change['new']) || $change['new'] === '') <em>null</em> 
                                                                        @else {{ Str::limit((string)$change['new'], 100) }} @endif
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif(isset($log->payload['before']))
                                                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div class="bg-red-50 dark:bg-red-900/10 p-5 rounded-2xl border border-red-200 dark:border-red-500/20 shadow-sm dark:shadow-inner overflow-x-auto custom-scrollbar">
                                                        <span class="text-[9px] font-black text-red-400 uppercase tracking-widest block mb-3 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Vorheriger Stand</span>
                                                        <pre class="text-[10px] text-red-200/70 font-mono leading-relaxed">{{ json_encode($log->payload['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                    <div class="bg-emerald-50 dark:bg-emerald-900/10 p-5 rounded-2xl border border-emerald-200 dark:border-emerald-500/20 shadow-sm dark:shadow-inner overflow-x-auto custom-scrollbar">
                                                        <span class="text-[9px] font-black text-emerald-400 uppercase tracking-widest block mb-3 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]">Aktueller Stand</span>
                                                        <pre class="text-[10px] text-emerald-200/70 font-mono leading-relaxed">{{ json_encode($log->payload['after'] ?? $log->payload['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                </div>
                                            @endif
                                            
                                            @php
                                                $clonedPayload = collect($cPayload)
                                                    ->except(['changes', 'before', 'after'])
                                                    ->toArray();
                                            @endphp
                                            @if(!empty($clonedPayload))
                                                <div>
                                                    <div class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-2 mt-6 flex items-center gap-2"><x-heroicon-o-code-bracket class="w-3.5 h-3.5" /> {{ isset($log->payload['changes']) || isset($log->payload['before']) ? 'Zusätzliche Metadaten' : 'System Payload Dump (JSON)' }}</div>
                                                    <div class="text-[10px] text-emerald-700 dark:text-green-500 whitespace-pre-wrap break-all p-4 bg-white dark:bg-black rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner font-mono leading-relaxed">{{ json_encode($clonedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <tbody>
                            <tr>
                                <td colspan="5" class="px-8 py-32 text-center text-gray-500 font-serif text-xl italic">Keine Logs gefunden...</td>
                            </tr>
                        </tbody>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="px-8 py-6 bg-gray-50/50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
