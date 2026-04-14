@props(['vouchers'])

<div class="w-full relative rounded-2xl overflow-hidden border border-gray-800 bg-gray-950 shadow-2xl">
    <table class="w-full text-left whitespace-nowrap">
        <thead class="bg-gray-900/80 border-b border-gray-800">
            <tr>
                <th class="px-6 py-4 text-sm font-medium text-gray-400">Code</th>
                <th class="px-6 py-4 text-sm font-medium text-gray-400">Wert</th>
                <th class="px-6 py-4 text-sm font-medium text-gray-400">Nutzung</th>
                <th class="px-6 py-4 text-sm font-medium text-gray-400">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-800/50">
            @foreach($vouchers as $v)
                <tr class="hover:bg-gray-800/30 transition-colors group">
                    <!-- Code -->
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-[color:var(--theme-color-10)] border border-[color:var(--theme-color-30)] flex items-center justify-center transition-colors">
                                <i class="bi bi-tag-fill text-[color:var(--theme-color)]"></i>
                            </div>
                            <span class="font-mono text-white text-sm">{{ $v['code'] ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <!-- Wert -->
                    <td class="px-6 py-4">
                        <span class="text-sm text-gray-300 font-bold bg-gray-900 px-3 py-1 rounded-md border border-gray-800">{{ $v['value'] ?? '-' }}</span>
                    </td>
                    <!-- Nutzung -->
                    <td class="px-6 py-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-medium {{ ($v['used_count'] ?? 0) > 0 ? 'text-[color:var(--theme-color)]' : 'text-gray-400' }}">
                                {{ $v['used_count'] ?? 0 }} Einlösungen
                            </span>
                            @if(isset($v['usage_limit']) && $v['usage_limit'])
                                <span class="text-xs text-gray-500">Max {{ $v['usage_limit'] }}</span>
                            @endif
                        </div>
                    </td>
                    <!-- Status -->
                    <td class="px-6 py-4">
                         @if(($v['is_active'] ?? true))
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-[color:var(--theme-color-10)] text-[color:var(--theme-color)] border border-[color:var(--theme-color-30)]">
                                Aktiv
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-800 text-gray-500 border border-gray-700">
                                Inaktiv
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
