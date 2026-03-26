<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 bg-transparent border-t border-gray-800 pt-6 mt-6 w-full">

    {{-- SITZUNGS-HISTORIE --}}
    <div class="bg-gray-900/80 backdrop-blur-md rounded-2xl shadow-2xl border border-gray-800 overflow-hidden flex flex-col h-full group w-full">

        {{-- Header inkl. Suchfeld --}}
        <div class="p-4 border-b border-gray-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-gray-900/50">
            <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                <h3 class="text-sm font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                    <i class="solar-user-check-bold-duotone text-primary text-lg"></i>
                    Sitzungs-Historie
                </h3>
                <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest bg-gray-950 px-2 py-1 rounded border border-gray-800 sm:hidden">Live</span>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:w-48">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                        <i class="bi bi-search text-gray-500 text-xs"></i>
                    </div>
                    <input type="text" wire:model.live="searchLogins" placeholder="Suchen..." class="bg-gray-950 border border-gray-800 text-gray-300 text-xs rounded-lg focus:ring-primary focus:border-primary block w-full pl-8 p-1.5 transition-colors shadow-inner">
                </div>
                <span class="hidden sm:inline-block text-[9px] font-bold text-gray-500 uppercase tracking-widest bg-gray-950 px-2 py-1 rounded border border-gray-800">Live</span>
            </div>
        </div>

        {{-- Fester Scrollbereich für die Tabelle --}}
        <div class="overflow-x-auto overflow-y-auto max-h-[350px] custom-scrollbar flex-1 w-full">
            <table class="w-full text-xs text-left border-separate border-spacing-0">
                <thead class="sticky top-0 z-10">
                <tr class="text-[8px] font-black text-gray-500 uppercase tracking-widest bg-gray-950">
                    <th class="px-4 py-2 border-b border-gray-800">Nutzer</th>
                    <th class="px-4 py-2 border-b border-gray-800">Rolle</th>
                    <th class="px-4 py-2 text-right border-b border-gray-800">Aktivität</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                @forelse($activeLogins as $login)
                    <tr class="hover:bg-gray-800/40 transition-colors group/row">
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-6 h-6 rounded-md bg-gray-950 flex items-center justify-center text-gray-400 font-bold text-[10px] border border-gray-800 shrink-0 group-hover/row:border-primary/50 transition-colors">{{ substr($login['first_name'] ?? 'U', 0, 1) }}</div>
                                <span class="font-bold text-gray-300 tracking-wide truncate max-w-[120px]">{{ $login['first_name'] }} {{ substr($login['last_name'] ?? '', 0, 1) }}.</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                                <span @class([
                                    'px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border whitespace-nowrap',
                                    'bg-purple-500/10 text-purple-400 border-purple-500/30' => $login['type'] === 'Admin',
                                    'bg-primary/10 text-primary border-primary/30' => $login['type'] === 'Customer',
                                    'bg-blue-500/10 text-blue-400 border-blue-500/30' => $login['type'] === 'Employee'
                                ])>{{ $login['type'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-[10px] font-bold text-gray-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($login['last_seen'])->diffForHumans(null, true, true) }}</span>
                                <span class="text-[8px] text-gray-600 font-black tracking-widest whitespace-nowrap">{{ \Carbon\Carbon::parse($login['last_seen'])->format('H:i') }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-gray-500 italic text-xs">Keine passenden Sitzungen gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    </div>
</div>
