<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-emerald-500 mb-1 font-mono tracking-tight shadow-emerald-500/20 drop-shadow-md">
                > SYSTEM_LOGS :: AI_MAS
            </h2>
            <p class="text-emerald-700/80 font-mono text-xs uppercase tracking-widest">Multi-Agent System Activity Tracker</p>
        </div>
        <button wire:click="clearFilters" class="px-3 py-1.5 bg-gray-900 border border-gray-700 hover:border-gray-500 text-xs font-mono text-gray-400 hover:text-white rounded transition-colors flex items-center gap-2">
            <i class="bi bi-x-lg"></i> Filter Zurücksetzen
        </button>
    </div>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 flex items-center justify-between shadow-inner">
            <div>
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1">Gesamte Logs</p>
                <div class="text-2xl font-mono text-white font-black">{{ number_format($totalLogs, 0, ',', '.') }}</div>
            </div>
            <div class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-gray-500"><i class="bi bi-database"></i></div>
        </div>
        <div class="bg-gray-900 border border-emerald-900/30 rounded-xl p-4 flex items-center justify-between shadow-inner">
            <div>
                <p class="text-[10px] text-emerald-600/80 font-bold uppercase tracking-widest mb-1">Logs Heute</p>
                <div class="text-2xl font-mono text-emerald-500 font-black">+{{ number_format($logsToday, 0, ',', '.') }}</div>
            </div>
            <div class="w-10 h-10 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-500 border border-emerald-500/20"><i class="bi bi-bar-chart-line"></i></div>
        </div>
        <div class="bg-gray-900 border border-red-900/30 rounded-xl p-4 flex items-center justify-between shadow-inner">
            <div>
                <p class="text-[10px] text-red-600/80 font-bold uppercase tracking-widest mb-1">Fehlerrate / Errors</p>
                <div class="text-2xl font-mono text-red-500 font-black">{{ number_format($totalErrors, 0, ',', '.') }}</div>
            </div>
            <div class="w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
        <div class="bg-gray-900 border border-purple-900/30 rounded-xl p-4 flex items-center justify-between shadow-inner">
            <div>
                <p class="text-[10px] text-purple-600/80 font-bold uppercase tracking-widest mb-1">Aktive Agenten</p>
                <div class="text-2xl font-mono text-purple-500 font-black">{{ count($agents) }}</div>
            </div>
            <div class="w-10 h-10 rounded-full bg-purple-500/10 flex items-center justify-center text-purple-500 border border-purple-500/20"><i class="bi bi-robot"></i></div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-3 mb-6 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[200px] relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche in Nachricht, Aktion, Typ..." class="w-full bg-gray-950 border border-gray-800 rounded-lg text-xs py-2 pl-8 pr-3 text-gray-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all placeholder-gray-600">
        </div>
        <select wire:model.live="agentFilter" class="bg-gray-950 border border-gray-800 rounded-lg text-xs py-2 px-3 text-gray-300 focus:ring-1 focus:ring-emerald-500 outline-none w-full sm:w-auto">
            <option value="">Alle Agenten</option>
            <option value="system">Kein Agent (System)</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="typeFilter" class="bg-gray-950 border border-gray-800 rounded-lg text-xs py-2 px-3 text-gray-300 focus:ring-1 focus:ring-emerald-500 outline-none w-full sm:w-auto">
            <option value="">Alle Typen</option>
            @foreach($uniqueTypes as $type)
                <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="bg-gray-950 border border-gray-800 rounded-lg text-xs py-2 px-3 text-gray-300 focus:ring-1 focus:ring-emerald-500 outline-none w-full sm:w-auto">
            <option value="">Alle Status</option>
            <option value="success">Success</option>
            <option value="running">Running</option>
            <option value="error">Error</option>
            <option value="info">Info</option>
            <option value="warning">Warning</option>
        </select>
    </div>

    <div class="bg-black/80 border border-emerald-900/50 rounded-xl backdrop-blur-xl shadow-[0_0_30px_rgba(16,185,129,0.05)] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-emerald-950/30 border-b border-emerald-900/50 text-[10px] uppercase tracking-widest text-emerald-600/80 font-mono">
                        <th class="p-4 font-normal">Zeitstempel</th>
                        <th class="p-4 font-normal">Agent</th>
                        <th class="p-4 font-normal">Typ</th>
                        <th class="p-4 font-normal">Aktion</th>
                        <th class="p-4 font-normal">Nachricht</th>
                        <th class="p-4 font-normal">Status</th>
                    </tr>
                </thead>
                @forelse($logs as $log)
                    <tbody x-data="{ expanded: false }" class="font-mono text-sm border-b border-emerald-900/20 hover:bg-emerald-900/10 transition-colors">
                        <tr @click="expanded = !expanded" class="cursor-pointer">
                            <td class="p-4 text-emerald-700/70 text-xs text-nowrap">
                                {{ $log->created_at->format('d.m.Y H:i:s') }}
                            </td>
                            <td class="p-4">
                                @if($log->agent)
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded bg-{{ $log->agent->color }}-500/20 flex items-center justify-center border border-{{ $log->agent->color }}-500/30 text-{{ $log->agent->color }}-500">
                                            <i class="{{ $log->agent->icon }} text-xs"></i>
                                        </div>
                                        <span class="text-{{ $log->agent->color }}-500 font-bold text-xs">{{ $log->agent->name }}</span>
                                    </div>
                                @else
                                    <span class="text-gray-500 text-xs text-nowrap">
                                        <i class="bi bi-cpu text-gray-700 mr-1"></i> SYSTEM
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-emerald-500/80 text-xs">{{ $log->type }}</td>
                            <td class="p-4 text-emerald-400 text-xs">{{ $log->action_id }}</td>
                            <td class="p-4 text-gray-300 text-xs max-w-xs truncate" title="{{ $log->message }}">{{ $log->message }}</td>
                            <td class="p-4 flex items-center gap-2 justify-end">
                                @if($log->status === 'success')
                                    <span class="text-emerald-500 text-xs border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 rounded">SUCCESS</span>
                                @elseif($log->status === 'running')
                                    <span class="text-amber-500 text-xs border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 rounded animate-pulse">RUNNING</span>
                                @elseif($log->status === 'error')
                                    <span class="text-red-500 text-xs border border-red-500/30 bg-red-500/10 px-2 py-0.5 rounded flex items-center gap-1">
                                        ERROR
                                        @if($log->error_count > 1)
                                            <span class="bg-red-500 text-white rounded-full px-1.5 py-0.5 text-[9px] ml-1">{{ $log->error_count }}x</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs border border-gray-600/30 bg-gray-600/10 px-2 py-0.5 rounded">{{ strtoupper($log->status) }}</span>
                                @endif
                                <i class="bi bi-chevron-down text-gray-600 transition-transform ml-2" :class="expanded ? 'rotate-180' : ''"></i>
                            </td>
                        </tr>
                        <!-- Expanded Details Row -->
                        <tr x-show="expanded" x-transition style="display: none;">
                            <td colspan="6" class="p-4 bg-black/60 border-t border-emerald-900/30">
                                <div class="text-xs text-gray-300 whitespace-pre-wrap break-all p-4 bg-gray-950/50 rounded border border-gray-800 font-mono">{{ $log->message }}</div>
                                @if($log->payload)
                                    <div class="mt-2 text-xs text-emerald-600/80 whitespace-pre-wrap break-all p-4 bg-gray-950/50 rounded border border-gray-800 font-mono">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-emerald-700/50 text-xs font-mono">_ NO_LOGS_FOUND</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-emerald-900/50 bg-black/40">
    <div class="mt-6">
        {{ $logs->links('vendor.livewire.matrix-pagination') }}
    </div>
    </div>
</div>
