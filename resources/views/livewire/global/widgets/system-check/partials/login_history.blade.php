<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
        <h3 class="font-bold text-slate-700">Letzte Aktivitäten</h3>
        <button @click="showLogins = !showLogins" class="text-xs text-indigo-500 hover:text-indigo-700 font-medium">
            <span x-text="showLogins ? 'Einklappen' : 'Anzeigen'"></span>
        </button>
    </div>

    <div x-show="showLogins" x-collapse>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Rolle</th>
                    <th class="px-4 py-3 text-right">Zuletzt gesehen</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($paginatedLogins as $login)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-700">
                            {{ $login['first_name'] }} {{ $login['last_name'] }}
                        </td>
                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 rounded-md text-[10px] font-bold
                                                {{ $login['type'] === 'Admin' ? 'bg-purple-100 text-purple-700' : '' }}
                                                {{ $login['type'] === 'Customer' ? 'bg-blue-100 text-blue-700' : '' }}
                                                {{ $login['type'] === 'Employee' ? 'bg-teal-100 text-teal-700' : '' }}">
                                                {{ $login['type'] }}
                                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-500">
                            {{ \Carbon\Carbon::parse($login['last_seen'])->diffForHumans() }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-4 text-center text-slate-400">Keine Daten</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t border-slate-100">
            {{ $paginatedLogins->links() }}
        </div>
    </div>
</div>
