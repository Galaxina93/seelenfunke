<div class="bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] shadow-2xl border border-gray-800 p-5 md:p-8 flex flex-col w-full overflow-hidden relative group">
    <div class="absolute top-0 right-0 w-full h-1 bg-gradient-to-l from-fuchsia-500 via-pink-400 to-transparent opacity-50"></div>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-xl md:text-2xl font-serif font-bold text-white flex items-center gap-3">
                <i class="solar-users-group-rounded-bold-duotone text-fuchsia-400 drop-shadow-[0_0_10px_rgba(217,70,239,0.5)]"></i>
                Kundengewinnung & Wachstum
            </h2>
            <p class="text-[10px] md:text-xs font-black uppercase tracking-widest text-gray-500 mt-2">Gewonnene Neu-Kunden im Zeitraum</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <div class="bg-gray-950 p-3 md:p-4 rounded-xl md:rounded-2xl border border-gray-800 shadow-inner flex flex-col items-center min-w-[150px]">
                <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Verifizierte User</span>
                <span class="text-xl md:text-2xl font-black text-fuchsia-400 drop-shadow-[0_0_10px_rgba(217,70,239,0.4)]">{{ number_format($stats['total_new_customers_period'] ?? 0, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <div class="w-full mb-4">
        <div class="flex items-center gap-4 mb-4 justify-end">
            <span class="flex items-center gap-2 text-[9px] font-black text-white uppercase tracking-widest bg-gray-950 px-3 py-1.5 rounded-full border border-gray-800 cursor-help" title="Zeigt alle neuen, E-Mail-verifizierten Kunden an.">
                <span class="w-2.5 h-2.5 rounded-full bg-fuchsia-500 shadow-[0_0_8px_currentColor]"></span>
                Neue Kunden (Verifiziert)
            </span>
        </div>
        <div class="relative w-full h-56 md:h-80" wire:ignore>
            <canvas id="acquisitionChart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 pt-6 border-t border-gray-800">
        {{-- ZULETZT REGISTRIERT --}}
        <div class="bg-gray-950/50 rounded-2xl p-5 border border-gray-800 shadow-inner">
            <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                Zuletzt Registriert (Verifiziert)
            </h4>
            <div class="space-y-3 max-h-[350px] overflow-y-auto custom-scrollbar pr-2">
                @if(isset($stats['latest_customers']) && count($stats['latest_customers']) > 0)
                    @foreach($stats['latest_customers'] as $cust)
                        <div class="flex items-center justify-between p-2 rounded-xl hover:bg-gray-800/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-900 flex items-center justify-center text-[10px] font-bold text-gray-500 uppercase border border-gray-800 shadow-[inset_0_0_10px_rgba(0,0,0,0.5)]">
                                    {{ substr($cust['first_name'], 0, 1) }}{{ substr($cust['last_name'], 0, 1) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-bold text-gray-300">{{ $cust['first_name'] }} {{ $cust['last_name'] }}</span>
                                    <span class="text-[10px] text-gray-500">{{ \Carbon\Carbon::parse($cust['created_at'])->diffForHumans() }}</span>
                                </div>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-fuchsia-400 bg-fuchsia-500/10 px-2 py-1 rounded-md border border-fuchsia-500/20">Neu</span>
                        </div>
                    @endforeach
                @else
                    <p class="text-xs text-gray-600 italic">Keine Neukunden im gewählten Zeitraum.</p>
                @endif
            </div>
        </div>
        
        {{-- SITZUNGS-HISTORIE --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-2xl border border-gray-800 overflow-hidden flex flex-col h-full group w-full">
            <div class="p-5 border-b border-gray-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-gray-900/50">
                <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                    <h3 class="text-[10px] sm:text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="solar-user-check-bold-duotone text-fuchsia-400 opacity-80 text-base"></i>
                        Sitzungs-Historie
                    </h3>
                    <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest bg-gray-950 px-2 py-1 rounded border border-gray-800 sm:hidden">Live</span>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <div class="relative w-full sm:w-40">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <i class="bi bi-search text-gray-500 text-[10px]"></i>
                        </div>
                        <input type="text" wire:model.live="searchLogins" placeholder="Suchen..." class="bg-gray-950 border border-gray-800 text-gray-300 text-[10px] rounded-lg focus:ring-fuchsia-500 focus:border-fuchsia-500 block w-full pl-8 p-1.5 transition-colors shadow-inner">
                    </div>
                    <span class="hidden sm:inline-block text-[8px] font-bold text-gray-500 uppercase tracking-widest bg-gray-950 px-2 py-1 rounded border border-gray-800">Live</span>
                </div>
            </div>

            <div class="overflow-x-auto overflow-y-auto max-h-[350px] custom-scrollbar flex-1 w-full">
                <table class="w-full text-[10px] text-left border-separate border-spacing-0">
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
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-lg bg-gray-950 flex items-center justify-center text-gray-400 font-bold text-[9px] border border-gray-800 shrink-0 group-hover/row:border-fuchsia-500/50 transition-colors">{{ substr($login['first_name'] ?? 'U', 0, 1) }}</div>
                                    <span class="font-bold text-gray-300 tracking-wide truncate max-w-[100px]">{{ $login['first_name'] }} {{ substr($login['last_name'] ?? '', 0, 1) }}.</span>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                @php
                                    $roleName = match($login['type']) {
                                        'Admin' => 'Admin',
                                        'Customer' => 'Kunde',
                                        'Employee' => 'Mitarbeiter',
                                        default => $login['type'] ?? 'Unbekannt'
                                    };
                                @endphp
                                <span @class([
                                    'px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border whitespace-nowrap',
                                    'bg-purple-500/10 text-purple-400 border-purple-500/30' => $login['type'] === 'Admin',
                                    'bg-fuchsia-500/10 text-fuchsia-400 border-fuchsia-500/30' => $login['type'] === 'Customer',
                                    'bg-blue-500/10 text-blue-400 border-blue-500/30' => $login['type'] === 'Employee'
                                ])>{{ $roleName }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex flex-col items-end">
                                    <span class="text-[9px] font-bold text-gray-400 whitespace-nowrap">{{ \Carbon\Carbon::parse($login['last_seen'])->diffForHumans(null, true, true) }}</span>
                                    <span class="text-[8px] text-gray-600 font-black tracking-widest whitespace-nowrap mt-0.5">{{ \Carbon\Carbon::parse($login['last_seen'])->format('H:i') }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500 italic text-[10px]">Keine passenden Sitzungen gefunden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
