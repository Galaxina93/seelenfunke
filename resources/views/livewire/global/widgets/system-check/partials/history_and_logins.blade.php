{{-- Container für die 50/50 Aufteilung der Seite --}}
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 bg-slate-50 border-t border-slate-200 pt-8 mt-8">

    {{-- BEREICH 1 (Links): SITZUNGS-HISTORIE --}}
    <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col h-full">
        <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-white">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-800">Sitzungs-Historie</h3>
                <p class="text-xs text-slate-400 mt-1 uppercase tracking-widest font-semibold">Echtzeit-Aktivitäten</p>
            </div>
            <div class="p-3 bg-indigo-50 rounded-2xl text-indigo-600 shadow-sm">
                <i class="solar-user-check-bold-duotone text-xl"></i>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-separate border-spacing-0">
                <thead>
                <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                    <th class="px-8 py-4 font-black">Begleiter</th>
                    <th class="px-8 py-4 font-black">Rolle</th>
                    <th class="px-8 py-4 text-right font-black">Zuletzt gesehen</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                @forelse($paginatedLogins as $login)
                    <tr class="hover:bg-slate-50/80 transition-colors group">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 font-bold text-xs border border-white shadow-sm group-hover:border-primary/30 transition-colors">
                                    {{ substr($login['first_name'] ?? 'U', 0, 1) }}
                                </div>
                                <span class="font-bold text-slate-700">{{ $login['first_name'] }} {{ $login['last_name'] }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                                <span @class([
                                    'px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border',
                                    'bg-purple-50 text-purple-600 border-purple-100' => $login['type'] === 'Admin',
                                    'bg-blue-50 text-blue-600 border-blue-100' => $login['type'] === 'Customer',
                                    'bg-teal-50 text-teal-600 border-teal-100' => $login['type'] === 'Employee'
                                ])>
                                    {{ $login['type'] }}
                                </span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-xs font-bold text-slate-600">{{ \Carbon\Carbon::parse($login['last_seen'])->diffForHumans() }}</span>
                                <span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($login['last_seen'])->format('H:i') }} Uhr</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-8 py-12 text-center text-slate-400 italic font-serif">Keine aktiven Sitzungen gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6 bg-slate-50/30 border-t border-slate-50 mt-auto">
            {{ $paginatedLogins->links() }}
        </div>
    </div>

    {{-- BEREICH 2 (Rechts): SICHERHEITS-LOG --}}
    <div class="lg:col-span-2 bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden flex flex-col h-full">
        <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-white">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-800">Sicherheits-Log</h3>
                <p class="text-xs text-rose-400 mt-1 uppercase tracking-widest font-semibold">Abgewiesene Versuche</p>
            </div>
            <div @class([
                        'p-3 rounded-2xl shadow-sm transition-all',
                        'bg-rose-50 text-rose-500 shadow-rose-100' => $stats['failed_logins'] > 0,
                        'bg-slate-50 text-slate-300' => $stats['failed_logins'] == 0
                    ])>
                <i class="solar-shield-warning-bold-duotone text-xl"></i>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-separate border-spacing-0">
                <thead>
                <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                    <th class="px-8 py-4 font-black">IP-Adresse</th>
                    <th class="px-8 py-4 font-black">Email Versuch</th>
                    <th class="px-8 py-4 text-right font-black">Zeitpunkt</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                @forelse($paginatedFailedLogins as $fail)
                    <tr class="hover:bg-rose-50/30 transition-colors group">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400 opacity-50"></span>
                                <span class="font-mono text-xs text-slate-500">{{ $fail->ip_address }}</span>
                            </div>
                        </td>
                        <td class="px-8 py-5 font-bold text-slate-700">
                            {{ $fail->email }}
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex flex-col items-end">
                                <span class="text-xs font-bold text-slate-600">{{ \Carbon\Carbon::parse($fail->attempted_at)->format('d.m.Y') }}</span>
                                <span class="text-[10px] text-rose-400 font-bold uppercase">{{ \Carbon\Carbon::parse($fail->attempted_at)->format('H:i:s') }} Uhr</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-8 py-12 text-center text-slate-300 italic font-serif">System ist sicher. Keine Fehlerversuche.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6 bg-slate-50/30 border-t border-slate-50 mt-auto">
            {{ $paginatedFailedLogins->links() }}
        </div>
    </div>

</div>
