<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-3xl font-black text-emerald-500 mb-1 font-mono tracking-widest shadow-emerald-500/20 drop-shadow-md">
                KI System Logs
            </h2>
            <p class="text-emerald-700 font-mono text-xs uppercase tracking-widest font-bold">Multi-Agenten System Aktivitäts-Tracker</p>
        </div>
        <button wire:click="clearFilters" class="px-4 py-2 bg-black border border-emerald-900/50 hover:border-emerald-500 hover:bg-emerald-500/10 text-[10px] font-black uppercase tracking-widest font-mono text-emerald-600 hover:text-emerald-400 rounded-lg transition-all shadow-inner flex items-center gap-2">
            <x-heroicon-o-x-mark class="w-4 h-4" /> Filter zurücksetzen
        </button>
    </div>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-black border border-emerald-900/40 rounded-xl p-4 flex items-center justify-between shadow-[inset_0_0_20px_rgba(16,185,129,0.02)] relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
            <div class="relative z-10">
                <p class="text-[10px] text-emerald-700 font-black uppercase tracking-widest mb-1">Gesamte Logs</p>
                <div class="text-2xl font-mono text-emerald-400 font-black">{{ number_format($totalLogs, 0, ',', '.') }}</div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 relative z-10 group-hover:scale-110 transition-transform">
                <x-heroicon-o-server-stack class="w-5 h-5"/>
            </div>
        </div>
        
        <div class="bg-black border border-emerald-900/40 rounded-xl p-4 flex items-center justify-between shadow-[inset_0_0_20px_rgba(16,185,129,0.02)] relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
            <div class="relative z-10">
                <p class="text-[10px] text-emerald-700 font-black uppercase tracking-widest mb-1">Logs Heute</p>
                <div class="text-2xl font-mono text-emerald-400 font-black">+{{ number_format($logsToday, 0, ',', '.') }}</div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 relative z-10 group-hover:scale-110 transition-transform">
                <x-heroicon-o-chart-bar class="w-5 h-5"/>
            </div>
        </div>
        
        <div class="bg-black border border-red-900/40 rounded-xl p-4 flex items-center justify-between shadow-[inset_0_0_20px_rgba(239,68,68,0.05)] relative overflow-hidden group hover:border-red-500/50 transition-colors">
            <div class="relative z-10">
                <p class="text-[10px] text-red-700 font-black uppercase tracking-widest mb-1">Fehlerrate / Errors</p>
                <div class="text-2xl font-mono text-red-500 font-black">{{ number_format($totalErrors, 0, ',', '.') }}</div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-500 relative z-10 group-hover:scale-110 transition-transform">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5"/>
            </div>
        </div>
        
        <div class="bg-black border border-emerald-900/40 rounded-xl p-4 flex items-center justify-between shadow-[inset_0_0_20px_rgba(16,185,129,0.02)] relative overflow-hidden group hover:border-emerald-500/50 transition-colors">
            <div class="relative z-10">
                <p class="text-[10px] text-emerald-700 font-black uppercase tracking-widest mb-1">Aktive Agenten</p>
                <div class="text-2xl font-mono text-emerald-400 font-black">{{ count($agents) }}</div>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 relative z-10 group-hover:scale-110 transition-transform">
                <x-heroicon-o-cpu-chip class="w-5 h-5"/>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-black border border-emerald-900/50 rounded-xl p-3 mb-6 flex flex-wrap items-center gap-3 shadow-[0_0_30px_rgba(16,185,129,0.02)] font-mono">
        <div class="flex-1 min-w-[200px] relative tracking-widest text-[10px]">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 text-emerald-700 absolute left-3 top-1/2 -translate-y-1/2" />
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche in Nachricht, Aktion, Typ..." class="w-full bg-gray-950 border border-emerald-900/50 rounded-lg py-2.5 pl-9 pr-3 text-emerald-400 focus:bg-black focus:ring-1 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition-all placeholder-emerald-900 shadow-inner font-bold uppercase">
        </div>
        <select wire:model.live="agentFilter" class="bg-gray-950 border border-emerald-900/50 rounded-lg text-[10px] font-bold uppercase tracking-widest py-2.5 px-3 text-emerald-500 focus:bg-black focus:ring-1 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none shadow-inner w-full sm:w-auto">
            <option value="">Alle Agenten</option>
            <option value="system">Kein Agent (System)</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="typeFilter" class="bg-gray-950 border border-emerald-900/50 rounded-lg text-[10px] font-bold uppercase tracking-widest py-2.5 px-3 text-emerald-500 focus:bg-black focus:ring-1 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none shadow-inner w-full sm:w-auto">
            <option value="">Alle Typen</option>
            @foreach($uniqueTypes as $type)
                <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="bg-gray-950 border border-emerald-900/50 rounded-lg text-[10px] font-bold uppercase tracking-widest py-2.5 px-3 text-emerald-500 focus:bg-black focus:ring-1 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none shadow-inner w-full sm:w-auto">
            <option value="">Alle Status</option>
            <option value="success">SUCCESS</option>
            <option value="running">RUNNING</option>
            <option value="error">ERROR</option>
            <option value="info">INFO</option>
            <option value="warning">WARNING</option>
        </select>
    </div>

    <div class="bg-black border border-emerald-900/50 rounded-xl backdrop-blur-xl shadow-[0_0_40px_rgba(16,185,129,0.05)] overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-950/80 border-b border-emerald-900/50 text-[10px] uppercase tracking-[0.2em] text-emerald-600 font-black font-mono">
                        <th class="p-4 px-6">Zeitstempel</th>
                        <th class="p-4">Agent</th>
                        <th class="p-4">Typ</th>
                        <th class="p-4">Aktion</th>
                        <th class="p-4">Nachricht</th>
                        <th class="p-4 text-right">Status</th>
                    </tr>
                </thead>
                @forelse($logs as $log)
                    <tbody x-data="{ expanded: false }" class="font-mono text-xs border-b border-emerald-900/30 hover:bg-emerald-900/10 transition-colors">
                        <tr @click="expanded = !expanded" class="cursor-pointer group">
                            <td class="p-4 px-6 text-emerald-600 font-bold whitespace-nowrap relative">
                                <div class="absolute inset-y-0 left-0 w-1 bg-{{ $log->status === 'error' ? 'red-600' : ($log->status === 'success' ? 'emerald-500' : 'emerald-900') }}"></div>
                                {{ $log->created_at->format('d.m.y H:i:s') }}
                            </td>
                            <td class="p-4">
                                @if($log->agent)
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded shadow-inner bg-{{ $log->agent->color }}-500/10 flex items-center justify-center border border-{{ $log->agent->color }}-500/30 text-{{ $log->agent->color }}-500">
                                            <i class="{{ $log->agent->icon }} text-[10px]"></i>
                                        </div>
                                        <span class="text-{{ $log->agent->color }}-400 font-black uppercase tracking-wider text-[10px]">{{ $log->agent->name }}</span>
                                    </div>
                                @else
                                    <span class="flex items-center gap-2 text-emerald-700 font-black uppercase tracking-wider text-[10px]">
                                        <div class="w-5 h-5 rounded shadow-inner bg-emerald-900/30 flex items-center justify-center border border-emerald-800/50 text-emerald-600">
                                            <x-heroicon-o-cpu-chip class="w-3 h-3"/>
                                        </div>
                                        SYSTEM
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-emerald-500 font-bold uppercase tracking-widest text-[10px]">{{ $log->type }}</td>
                            <td class="p-4 text-emerald-300 font-bold">{{ $log->action_id }}</td>
                            <td class="p-4 text-emerald-600/80 max-w-xs truncate font-bold group-hover:text-emerald-400 transition-colors" title="{{ $log->message }}">{{ $log->message }}</td>
                            <td class="p-4 flex items-center gap-3 justify-end">
                                @if($log->status === 'success')
                                    <span class="text-emerald-400 text-[9px] font-black tracking-widest border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 rounded shadow-[0_0_10px_rgba(16,185,129,0.1)]">SUCCESS</span>
                                @elseif($log->status === 'running')
                                    <span class="text-emerald-400 text-[9px] font-black tracking-widest border border-emerald-500/50 bg-emerald-500/20 px-2.5 py-1 rounded shadow-[0_0_15px_rgba(16,185,129,0.3)] animate-pulse flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span> EXECUTING
                                    </span>
                                @elseif($log->status === 'error')
                                    <span class="text-red-500 text-[9px] font-black tracking-widest border border-red-500/30 bg-red-500/10 px-2.5 py-1 rounded flex items-center gap-1.5 shadow-[0_0_10px_rgba(239,68,68,0.1)]">
                                        FEHLER
                                        @if($log->error_count > 1)
                                            <span class="bg-red-500 text-black rounded px-1.5 py-0.5 text-[8px]">{{ $log->error_count }}x</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-gray-500 text-[9px] font-black tracking-widest border border-gray-700 bg-gray-900 px-2.5 py-1 rounded">{{ strtoupper($log->status) }}</span>
                                @endif
                                <x-heroicon-o-chevron-down class="w-4 h-4 text-emerald-800 transition-transform group-hover:text-emerald-500" x-bind:class="expanded ? 'rotate-180 text-emerald-400' : ''" />
                            </td>
                        </tr>
                        <tr x-show="expanded" x-transition style="display: none;">
                            <td colspan="6" class="p-6 bg-gray-950/80 border-t border-emerald-900/40 shadow-inner">
                                <div class="space-y-4">
                                    <div class="text-[10px] font-black uppercase tracking-widest text-emerald-600 mb-1">Nachrichten-Trace</div>
                                    <div class="text-xs text-emerald-300 whitespace-pre-wrap break-all p-4 bg-black rounded-lg border border-emerald-900/30 shadow-inner font-mono leading-relaxed">{{ $log->message }}</div>
                                    
                                    @if($log->payload)
                                        <div class="text-[10px] font-black uppercase tracking-widest text-emerald-600 mb-1 mt-4">System Payload Dump</div>
                                        <div class="text-xs text-emerald-500 whitespace-pre-wrap break-all p-4 bg-black rounded-lg border border-emerald-900/30 shadow-inner font-mono leading-relaxed">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="6" class="p-12 text-center text-emerald-800/50 text-[10px] font-black uppercase tracking-widest">
                                <x-heroicon-o-cube-transparent class="w-10 h-10 mx-auto mb-3 opacity-50" />
                                Keine Logs gefunden
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>
        <div class="p-5 border-t border-emerald-900/50 bg-black shadow-inner">
            {{ $logs->links('vendor.livewire.matrix-pagination') }}
        </div>
    </div>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(5, 150, 105, 0.4); border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.8); }
        ::selection { background: rgba(16, 185, 129, 0.3); color: #6ee7b7; }
    </style>
</div>
