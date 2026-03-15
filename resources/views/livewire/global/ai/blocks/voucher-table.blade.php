@props(['vouchers'])

<div class="w-full relative rounded-2xl overflow-hidden border border-gray-800 bg-gray-950 shadow-2xl">
    <table class="w-full text-left whitespace-nowrap">
        <thead class="bg-gray-900/80 border-b border-gray-800">
            <tr>
                <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-gray-500">Code</th>
                <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-gray-500">Wert</th>
                <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-gray-500">Nutzung</th>
                <th class="px-6 py-4 text-xs font-black uppercase tracking-widest text-gray-500">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800/50">
            @foreach($vouchers as $v)
                <tr class="hover:bg-gray-800/30 transition-colors group">
                    <!-- Code -->
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-900 border border-gray-700 flex items-center justify-center group-hover:border-emerald-500/50 transition-colors">
                                <i class="bi bi-tag-fill text-emerald-500"></i>
                            </div>
                            <span class="font-mono text-white text-sm tracking-widest">{{ $v['code'] ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <!-- Wert -->
                    <td class="px-6 py-4">
                        <span class="text-sm text-gray-300 font-bold bg-gray-900 px-3 py-1 rounded-md border border-gray-800">{{ $v['value'] ?? '-' }}</span>
                    </td>
                    <!-- Nutzung -->
                    <td class="px-6 py-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold {{ ($v['used_count'] ?? 0) > 0 ? 'text-emerald-400' : 'text-gray-400' }}">
                                {{ $v['used_count'] ?? 0 }} Einlösungen
                            </span>
                            @if(isset($v['usage_limit']) && $v['usage_limit'])
                                <span class="text-[10px] text-gray-500 uppercase tracking-widest">Max {{ $v['usage_limit'] }}</span>
                            @endif
                        </div>
                    </td>
                    <!-- Status -->
                    <td class="px-6 py-4">
                         @if(($v['is_active'] ?? true))
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-[9px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest">
                                Aktiv
                            </span>
                        @else
                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-md text-[9px] font-black bg-gray-800 text-gray-500 border border-gray-700 uppercase tracking-widest">
                                Inaktiv
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
