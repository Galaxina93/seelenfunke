<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-rose-50/30">
        <h3 class="font-bold text-rose-700 flex items-center gap-2">
            @if($stats['failed_logins'] > 0)
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
            @endif
            Fehlgeschlagene Logins
        </h3>
        <div class="flex gap-4 items-center">
            <span class="text-xs font-bold bg-rose-100 text-rose-600 px-2 py-0.5 rounded">{{ $stats['failed_logins'] }} Total</span>
            <button @click="showFailed = !showFailed" class="text-xs text-rose-500 hover:text-rose-700 font-medium">
                <span x-text="showFailed ? 'Einklappen' : 'Anzeigen'"></span>
            </button>
        </div>
    </div>

    <div x-show="showFailed" x-collapse>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-rose-800 uppercase bg-rose-50">
                <tr>
                    <th class="px-4 py-3">IP Address</th>
                    <th class="px-4 py-3">Email Versuch</th>
                    <th class="px-4 py-3 text-right">Zeitpunkt</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-rose-100">
                @forelse($paginatedFailedLogins as $fail)
                    <tr class="hover:bg-rose-50/50 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $fail->ip_address }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $fail->email }}</td>
                        <td class="px-4 py-3 text-right text-slate-500 text-xs">
                            {{ \Carbon\Carbon::parse($fail->attempted_at)->format('d.m.Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-4 text-center text-slate-400">Keine Einträge</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t border-slate-100">
            {{ $paginatedFailedLogins->links() }}
        </div>
    </div>
</div>
