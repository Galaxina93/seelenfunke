<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 bg-transparent border-t border-gray-800 pt-6 md:pt-8 mt-6 md:mt-8 w-full">

    <div class="lg:col-span-2 bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden flex flex-col h-full group w-full">
        <div class="p-5 md:p-8 border-b border-gray-800 flex justify-between items-center bg-gray-900/50">
            <div>
                <h3 class="text-lg md:text-xl font-serif font-bold text-white">Sitzungs-Historie</h3>
                <p class="text-[9px] md:text-[10px] font-black text-gray-500 mt-1 md:mt-2 uppercase tracking-widest">Echtzeit-Aktivitäten</p>
            </div>
            <div class="p-3 md:p-4 bg-primary/10 rounded-xl md:rounded-2xl text-primary shadow-[0_0_20px_rgba(197,160,89,0.2)] border border-primary/20 shrink-0">
                <i class="solar-user-check-bold-duotone text-xl md:text-2xl"></i>
            </div>
        </div>

        <div class="overflow-x-auto flex-1 w-full max-w-[100vw]">
            <table class="w-full text-xs md:text-sm text-left border-separate border-spacing-0 min-w-[350px]">
                <thead>
                <tr class="text-[8px] md:text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] md:tracking-[0.3em] bg-gray-950/50">
                    <th class="px-4 md:px-8 py-3 md:py-5 border-b border-gray-800">Begleiter</th>
                    <th class="px-4 md:px-8 py-3 md:py-5 border-b border-gray-800">Rolle</th>
                    <th class="px-4 md:px-8 py-3 md:py-5 text-right border-b border-gray-800">Zuletzt gesehen</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                @forelse($paginatedLogins as $login)
                    <tr class="hover:bg-gray-800/40 transition-colors">
                        <td class="px-4 md:px-8 py-4 md:py-6">
                            <div class="flex items-center gap-3 md:gap-4">
                                <div class="w-8 h-8 md:w-10 md:h-10 rounded-lg md:rounded-xl bg-gray-950 flex items-center justify-center text-gray-400 font-bold text-xs md:text-sm border border-gray-800 shadow-inner transition-colors shrink-0">{{ substr($login['first_name'] ?? 'U', 0, 1) }}</div>
                                <span class="font-bold text-white tracking-wide truncate max-w-[100px] md:max-w-none">{{ $login['first_name'] }} {{ $login['last_name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 md:px-8 py-4 md:py-6">
                            <span @class(['px-2 py-1 md:px-4 md:py-1.5 rounded-md md:rounded-lg text-[8px] md:text-[9px] font-black uppercase tracking-widest border shadow-sm whitespace-nowrap', 'bg-purple-500/10 text-purple-400 border-purple-500/30' => $login['type'] === 'Admin', 'bg-primary/10 text-primary border-primary/30' => $login['type'] === 'Customer', 'bg-blue-500/10 text-blue-400 border-blue-500/30' => $login['type'] === 'Employee'])>
                                {{ $login['type'] }}
                            </span>
                        </td>
                        <td class="px-4 md:px-8 py-4 md:py-6 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-[10px] md:text-xs font-bold text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($login['last_seen'])->diffForHumans() }}</span>
                                <span class="text-[8px] md:text-[10px] text-gray-600 font-black tracking-widest mt-1 whitespace-nowrap">{{ \Carbon\Carbon::parse($login['last_seen'])->format('H:i') }} Uhr</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 md:px-8 py-10 md:py-16 text-center text-gray-500 italic font-serif text-sm md:text-lg">Keine aktiven Sitzungen gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 md:p-6 bg-gray-950/50 border-t border-gray-800 mt-auto">{{ $paginatedLogins->links() }}</div>
    </div>

    <div class="lg:col-span-2 bg-gray-900/80 backdrop-blur-md rounded-[1.5rem] md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden flex flex-col h-full group w-full">
        <div class="p-5 md:p-8 border-b border-gray-800 flex justify-between items-center bg-gray-900/50">
            <div>
                <h3 class="text-lg md:text-xl font-serif font-bold text-white">Sicherheits-Log</h3>
                <p class="text-[9px] md:text-[10px] text-red-400 mt-1 md:mt-2 uppercase tracking-widest font-black drop-shadow-[0_0_8px_rgba(248,113,113,0.5)]">Abgewiesene Versuche</p>
            </div>
            <div @class(['p-3 md:p-4 rounded-xl md:rounded-2xl border transition-all shrink-0', 'bg-red-500/10 text-red-500 border-red-500/30 shadow-[0_0_20px_rgba(239,68,68,0.3)] animate-pulse' => $stats['failed_logins'] > 0, 'bg-gray-800/50 text-gray-500 border-gray-700' => $stats['failed_logins'] == 0])>
                <i class="solar-shield-warning-bold-duotone text-xl md:text-2xl"></i>
            </div>
        </div>

        <div class="overflow-x-auto flex-1 w-full max-w-[100vw]">
            <table class="w-full text-xs md:text-sm text-left border-separate border-spacing-0 min-w-[350px]">
                <thead>
                <tr class="text-[8px] md:text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] md:tracking-[0.3em] bg-gray-950/50">
                    <th class="px-4 md:px-8 py-3 md:py-5 border-b border-gray-800">IP-Adresse</th>
                    <th class="px-4 md:px-8 py-3 md:py-5 border-b border-gray-800">Email Versuch</th>
                    <th class="px-4 md:px-8 py-3 md:py-5 text-right border-b border-gray-800">Zeitpunkt</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                @forelse($paginatedFailedLogins as $fail)
                    <tr class="hover:bg-red-900/10 transition-colors">
                        <td class="px-4 md:px-8 py-4 md:py-6">
                            <div class="flex items-center gap-2 md:gap-3">
                                <span class="w-1.5 h-1.5 md:w-2 md:h-2 rounded-full bg-red-500 shadow-[0_0_8px_currentColor] shrink-0"></span>
                                <span class="font-mono text-[10px] md:text-xs text-gray-400 truncate">{{ $fail->ip_address }}</span>
                            </div>
                        </td>
                        <td class="px-4 md:px-8 py-4 md:py-6 font-bold text-gray-300 truncate max-w-[120px] md:max-w-none" title="{{ $fail->email }}">{{ $fail->email }}</td>
                        <td class="px-4 md:px-8 py-4 md:py-6 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-[10px] md:text-xs font-bold text-gray-300 whitespace-nowrap">{{ \Carbon\Carbon::parse($fail->attempted_at)->format('d.m.Y') }}</span>
                                <span class="text-[8px] md:text-[10px] text-red-400 font-black uppercase tracking-widest mt-1 whitespace-nowrap">{{ \Carbon\Carbon::parse($fail->attempted_at)->format('H:i:s') }} Uhr</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 md:px-8 py-10 md:py-16 text-center text-gray-500 italic font-serif text-sm md:text-lg">System ist sicher. Keine Fehlerversuche.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 md:p-6 bg-gray-950/50 border-t border-gray-800 mt-auto">{{ $paginatedFailedLogins->links() }}</div>
    </div>
</div>
