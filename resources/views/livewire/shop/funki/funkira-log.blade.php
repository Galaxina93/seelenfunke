<div class="p-4 md:p-6 bg-transparent min-h-screen space-y-6 relative z-10 w-full max-w-7xl mx-auto">
    
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-gray-900/80 backdrop-blur-md p-6 rounded-2xl border border-gray-800 shadow-2xl">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-3">
                <i class="solar-document-text-bold-duotone text-cyan-400"></i>
                Funkira Log
            </h1>
            <p class="text-sm text-gray-400 mt-1">Zentrales Fehler- und Eventprotokoll der KI und des Systems.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            @if($stats['errors'] > 0 || $stats['warnings'] > 0)
                <button wire:click="fixSystem" class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/30 rounded-xl font-bold text-xs uppercase tracking-widest transition-all flex items-center gap-2 animate-pulse shadow-[0_0_15px_rgba(239,68,68,0.2)]">
                    <i class="solar-magic-stick-3-bold-duotone"></i>
                    System Healing
                </button>
            @else
                <button wire:click="fixSystem" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 border border-gray-700 rounded-xl font-bold text-xs uppercase tracking-widest transition-all flex items-center gap-2">
                    <i class="solar-restart-bold-duotone"></i>
                    Caches leeren
                </button>
            @endif
            
            <button wire:click="clearLogs" wire:confirm="Bist du sicher, dass du das gesamte KI-Logbuch unwiderruflich löschen möchtest?" class="px-4 py-2 bg-red-900/30 hover:bg-red-900/60 text-red-400 border border-red-900/50 rounded-xl font-bold text-xs uppercase tracking-widest transition-all flex items-center gap-2">
                <i class="solar-trash-bin-trash-bold-duotone"></i>
                Logs löschen
            </button>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gray-900/80 backdrop-blur-md p-5 rounded-2xl border border-gray-800 flex flex-col items-center justify-center text-center">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Health Score</span>
            <div class="text-3xl font-black {{ $stats['health_score'] > 80 ? 'text-emerald-400' : ($stats['health_score'] > 50 ? 'text-amber-400' : 'text-red-500') }}">
                {{ $stats['health_score'] }}%
            </div>
        </div>
        <div class="bg-gray-900/80 backdrop-blur-md p-5 rounded-2xl border border-gray-800 flex flex-col items-center justify-center text-center">
            <span class="text-[10px] font-black uppercase tracking-widest text-red-500 mb-1">Errors</span>
            <div class="text-3xl font-black text-gray-200">{{ $stats['errors'] }}</div>
        </div>
        <div class="bg-gray-900/80 backdrop-blur-md p-5 rounded-2xl border border-gray-800 flex flex-col items-center justify-center text-center">
            <span class="text-[10px] font-black uppercase tracking-widest text-amber-500 mb-1">Warnings</span>
            <div class="text-3xl font-black text-gray-200">{{ $stats['warnings'] }}</div>
        </div>
        <div class="bg-gray-900/80 backdrop-blur-md p-5 rounded-2xl border border-gray-800 flex flex-col items-center justify-center text-center">
            <span class="text-[10px] font-black uppercase tracking-widest text-emerald-500 mb-1">Erfolgreich</span>
            <div class="text-3xl font-black text-gray-200">{{ $stats['success'] }}</div>
        </div>
    </div>

    {{-- Log Table Box --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl shadow-2xl border border-gray-800 overflow-hidden flex flex-col">
        {{-- Toolbar --}}
        <div class="p-4 border-b border-gray-800 flex flex-col sm:flex-row justify-between items-center bg-gray-900/50 gap-4">
            <div class="relative w-full sm:w-64">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="solar-magnifer-linear text-gray-500 text-sm"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Logs durchsuchen..." class="bg-gray-950 border border-gray-800 text-gray-300 text-sm rounded-xl focus:ring-cyan-500 focus:border-cyan-500 block w-full pl-10 p-2.5 transition-colors shadow-inner">
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto overflow-x-auto pb-2 sm:pb-0 custom-scrollbar">
                <button wire:click="$set('typeFilter', '')" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-colors {{ empty($typeFilter) ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'bg-gray-800 text-gray-500 border border-transparent hover:text-white' }}">Alle Logs</button>
                <button wire:click="$set('typeFilter', 'system')" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-colors {{ $typeFilter === 'system' ? 'bg-gray-700 text-white border border-gray-500' : 'bg-gray-800 text-gray-500 border border-transparent hover:text-white' }}">System</button>
                <button wire:click="$set('typeFilter', 'ai')" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-colors {{ $typeFilter === 'ai' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'bg-gray-800 text-gray-500 border border-transparent hover:text-white' }}">Funkira</button>
                <button wire:click="$set('typeFilter', 'automation')" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-colors {{ $typeFilter === 'automation' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-gray-800 text-gray-500 border border-transparent hover:text-white' }}">Automationen</button>
                <button wire:click="$set('typeFilter', 'security')" class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-colors {{ $typeFilter === 'security' ? 'bg-amber-500/20 text-amber-500 border border-amber-500/30' : 'bg-gray-800 text-gray-500 border border-transparent hover:text-white' }}">Sicherheit</button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto custom-scrollbar w-full flex-1">
            <table class="w-full text-sm text-left border-separate border-spacing-0">
                <thead class="sticky top-0 z-10 bg-gray-950/90 backdrop-blur-md">
                <tr class="text-[9px] font-black text-gray-500 uppercase tracking-widest">
                    <th class="px-6 py-4 border-b border-gray-800 whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 border-b border-gray-800 w-1/4">Ereignis</th>
                    <th class="px-6 py-4 border-b border-gray-800 w-1/2">Details</th>
                    <th class="px-6 py-4 text-right border-b border-gray-800 whitespace-nowrap">Zeitpunkt</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                @forelse($logs as $log)
                    @php
                        $isError = $log['status'] === 'error';
                        $isWarning = $log['status'] === 'warning';
                        $isRunning = $log['status'] === 'running';
                        $isSuccess = $log['status'] === 'success';

                        $icon = match($log['type']) {
                            'security' => 'solar-shield-warning-bold-duotone',
                            'automation' => 'solar-bolt-circle-bold-duotone',
                            'ai' => 'solar-magic-stick-3-bold-duotone',
                            'marketing' => 'solar-megaphone-bold-duotone',
                            default => 'solar-server-square-update-bold-duotone'
                        };

                        $iconBg = match(true) {
                            $isError => 'bg-red-500/10 text-red-500 border-red-500/30 shadow-[0_0_10px_rgba(239,68,68,0.2)]',
                            $isWarning => 'bg-amber-500/10 text-amber-500 border-amber-500/30',
                            $isRunning => 'bg-blue-500/10 text-blue-400 border-blue-500/30 animate-pulse',
                            $isSuccess => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                            default => 'bg-gray-800 text-gray-400 border-gray-700'
                        };
                    @endphp
                    <tr class="hover:bg-gray-800/60 transition-colors group">
                        <td class="px-6 py-4 align-top w-16">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center border {{ $iconBg }}">
                                <i class="{{ $icon }} text-xl"></i>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex flex-col">
                                <span class="font-bold {{ $isError ? 'text-red-400' : ($isWarning ? 'text-amber-400' : 'text-gray-200') }}">{{ $log['title'] }}</span>
                                <span class="text-[9px] uppercase tracking-widest text-gray-500 mt-1 font-bold">{{ $log['type'] }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <p class="text-xs text-gray-400 leading-relaxed max-w-2xl break-words">{{ $log['message'] ?? '-' }}</p>
                        </td>
                        <td class="px-6 py-4 text-right align-top">
                            <div class="flex flex-col items-end">
                                <span class="text-xs font-bold text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($log['timestamp'])->format('d.m.Y') }}</span>
                                <span class="text-[10px] {{ $isError ? 'text-red-400' : 'text-gray-500' }} font-black tracking-widest whitespace-nowrap mt-1">{{ \Carbon\Carbon::parse($log['timestamp'])->format('H:i:s') }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <i class="solar-folder-with-files-bold-duotone text-gray-700 text-5xl mb-3 block"></i>
                            <span class="text-gray-400 font-bold block text-lg">Keine Log-Einträge gefunden</span>
                            <span class="text-gray-500 text-sm mt-1 block">Es wurden keine Aufzeichnungen für diese Filterung gefunden.</span>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
