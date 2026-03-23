<div class="space-y-6 md:space-y-8 pb-20 font-sans antialiased text-gray-300">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 relative overflow-hidden animate-fade-in-up">
        <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
            <x-heroicon-o-server-stack class="w-40 h-40 text-primary drop-shadow-[0_0_20px_rgba(197,160,89,1)]" />
        </div>
        <div class="relative z-10">
            <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight">Log</h1>
            <p class="text-gray-400 mt-2 text-sm font-medium">Systemübergreifende Fehler-, Ereignis- und Aktivitätenverfolgung.</p>
        </div>

        <div class="flex items-center gap-3 relative z-10">
            <button wire:click="clearFilters" class="inline-flex items-center px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-950 text-gray-400 hover:text-white border border-gray-800 shadow-inner hover:bg-gray-800">
                <x-heroicon-o-x-mark class="w-4 h-4 mr-2" /> Filter zurücksetzen
            </button>
        </div>
    </div>

    <!-- Analytics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-2xl border border-gray-800 relative overflow-hidden group hover:border-gray-700 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-gray-950 border border-gray-800 flex items-center justify-center text-primary shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-server-stack class="w-6 h-6"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Gesamte Logs</p>
                <div class="text-3xl font-serif text-white">{{ number_format($totalLogs, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-2xl border border-gray-800 relative overflow-hidden group hover:border-gray-700 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-gray-950 border border-gray-800 flex items-center justify-center text-emerald-500 shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-chart-bar class="w-6 h-6 drop-shadow-[0_0_8px_currentColor]"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Logs Heute</p>
                <div class="text-3xl font-serif text-white">+{{ number_format($logsToday, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-2xl border border-red-900/30 relative overflow-hidden group hover:border-red-500/50 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center text-red-500 shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 drop-shadow-[0_0_8px_currentColor]"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-red-500 font-black uppercase tracking-widest mb-1">Fehlerrate / Errors</p>
                <div class="text-3xl font-serif text-white">{{ number_format($totalErrors, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-2xl border border-gray-800 relative overflow-hidden group hover:border-gray-700 transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-12 h-12 rounded-xl bg-gray-950 border border-gray-800 flex items-center justify-center text-sky-500 shadow-inner group-hover:scale-110 transition-transform">
                    <x-heroicon-o-cpu-chip class="w-6 h-6 drop-shadow-[0_0_8px_currentColor]"/>
                </div>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mb-1">Aktive AI Agenten</p>
                <div class="text-3xl font-serif text-white">{{ count($agents) }}</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 bg-gray-900/80 backdrop-blur-md p-3 sm:p-4 rounded-[2rem] border border-gray-800 shadow-2xl items-center animate-fade-in-up">
        <div class="md:col-span-1 relative group">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Suche in Nachricht..."
                   class="w-full pl-12 pr-4 py-4 bg-gray-950 border border-gray-800 rounded-[1.5rem] focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner transition-all text-white placeholder-gray-600 outline-none text-sm">
            <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-600 group-focus-within:text-primary transition-colors" />
            </div>
        </div>
        <select wire:model.live="agentFilter" class="md:col-span-1 bg-gray-950 border border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-colors cursor-pointer outline-none appearance-none">
            <option value="" class="bg-gray-900 text-white">Alle Agenten / Bereiche</option>
            <option value="system" class="bg-gray-900 text-white">System (Kein Agent)</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" class="bg-gray-900 text-white">{{ $agent->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="typeFilter" class="md:col-span-1 bg-gray-950 border border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-colors cursor-pointer outline-none appearance-none">
            <option value="" class="bg-gray-900 text-white">Alle Typen</option>
            @foreach($uniqueTypes as $type)
                <option value="{{ $type }}" class="bg-gray-900 text-white">{{ $type }}</option>
            @endforeach
        </select>
        <select wire:model.live="statusFilter" class="md:col-span-1 bg-gray-950 border border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-colors cursor-pointer outline-none appearance-none">
            <option value="" class="bg-gray-900 text-white">Alle Status</option>
            <option value="success" class="bg-gray-900 text-white">SUCCESS</option>
            <option value="running" class="bg-gray-900 text-white">RUNNING</option>
            <option value="error" class="bg-gray-900 text-white">ERROR</option>
            <option value="info" class="bg-gray-900 text-white">INFO</option>
            <option value="warning" class="bg-gray-900 text-white">WARNING</option>
        </select>
    </div>

    {{-- Log Table --}}
    <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full relative animate-fade-in-up">
        <div class="overflow-x-auto w-full custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-gray-950/80 border-b border-gray-800 text-[10px] font-black text-gray-500 uppercase tracking-widest shadow-inner">
                        <th class="px-6 sm:px-8 py-6 w-[15%]">Zeitstempel</th>
                        <th class="px-6 sm:px-8 py-6 w-[20%]">Agent / Bereich</th>
                        <th class="px-6 sm:px-8 py-6 w-[15%]">Typ & Aktion</th>
                        <th class="px-6 sm:px-8 py-6 w-[40%]">Nachricht</th>
                        <th class="px-6 sm:px-8 py-6 w-[10%] text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @forelse($logs as $log)
                        <tbody x-data="{ expanded: false }" class="transition-colors group hover:bg-gray-800/40">
                            <tr @click="expanded = !expanded" class="cursor-pointer">
                                <td class="px-6 sm:px-8 py-4 align-top border-l-[3px] {{ $log->status === 'error' ? 'border-red-500' : ($log->status === 'success' ? 'border-emerald-500' : 'border-gray-700') }}">
                                    <div class="flex flex-col mt-0.5">
                                        <span class="text-xs font-bold text-white">{{ $log->created_at->format('H:i:s') }}</span>
                                        <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black mt-1">{{ $log->created_at->format('d.m.Y') }}</span>
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    @if($log->agent)
                                        <div class="flex items-center gap-3 mt-1">
                                            <div class="w-6 h-6 rounded bg-{{ $log->agent->color }}-500/10 flex items-center justify-center border border-{{ $log->agent->color }}-500/30 text-{{ $log->agent->color }}-500">
                                                <i class="{{ $log->agent->icon }} text-xs"></i>
                                            </div>
                                            <span class="text-{{ $log->agent->color }}-400 font-bold text-sm">{{ $log->agent->name }}</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-3 mt-1">
                                            <div class="w-6 h-6 rounded bg-gray-800 flex items-center justify-center border border-gray-700 text-gray-400">
                                                <x-heroicon-o-cpu-chip class="w-3.5 h-3.5"/>
                                            </div>
                                            <span class="text-gray-300 font-bold text-sm">System <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 ml-1">({{ $log->action_id }})</span></span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="flex flex-col mt-1">
                                        <span class="text-xs font-bold text-primary">{{ $log->type }}</span>
                                        <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black mt-1">{{ $log->action_id }}</span>
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top max-w-xs">
                                    <div class="text-xs text-gray-300 font-medium truncate mt-1 group-hover:text-white transition-colors" title="{{ $log->message }}">
                                        {{ $log->message }}
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 text-right align-top">
                                    <div class="flex justify-end items-center gap-3 mt-1">
                                        @if($log->status === 'success')
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-emerald-500/30 bg-emerald-500/10 text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.1)]">SUCCESS</span>
                                        @elseif($log->status === 'running')
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-sky-500/30 bg-sky-500/10 text-sky-400 flex items-center gap-2">
                                                <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-ping"></span> EXECUTING
                                            </span>
                                        @elseif($log->status === 'error')
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-red-500/30 bg-red-500/10 text-red-400 shadow-[0_0_10px_rgba(239,68,68,0.1)] flex items-center gap-1.5">
                                                FEHLER
                                                @if($log->error_count > 1)
                                                    <span class="bg-red-500 text-white rounded px-1.5 py-0.5 text-[8px] ml-1">{{ $log->error_count }}x</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-gray-700 bg-gray-800 text-gray-400">{{ strtoupper($log->status) }}</span>
                                        @endif
                                        <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-600 transition-transform group-hover:text-gray-400" x-bind:class="expanded ? 'rotate-180 text-primary' : ''" />
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="expanded" x-transition x-cloak>
                                <td colspan="5" class="px-8 py-6 bg-gray-950/80 border-t border-gray-800 shadow-inner">
                                    <div class="space-y-4">
                                        <div>
                                            <div class="text-[10px] font-black uppercase tracking-widest text-primary mb-2 flex items-center gap-2"><x-heroicon-o-document-text class="w-3.5 h-3.5" /> Nachrichten-Trace</div>
                                            <div class="text-xs text-gray-300 whitespace-pre-wrap break-all p-4 bg-black rounded-xl border border-gray-800 shadow-inner font-mono leading-relaxed">{{ $log->message }}</div>
                                        </div>

                                        @if($log->payload)
                                            <div>
                                                <div class="text-[10px] font-black uppercase tracking-widest text-primary mb-2 mt-6 flex items-center gap-2"><x-heroicon-o-code-bracket class="w-3.5 h-3.5" /> System Payload Dump</div>
                                                <div class="text-xs text-green-500 whitespace-pre-wrap break-all p-4 bg-black rounded-xl border border-gray-800 shadow-inner font-mono leading-relaxed">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
                                            </div>
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
            <div class="px-8 py-6 bg-gray-900/30 border-t border-gray-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
