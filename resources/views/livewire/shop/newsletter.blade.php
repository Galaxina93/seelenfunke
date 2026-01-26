<div>
    <div class="p-6 bg-white rounded-lg shadow-sm">
        {{-- HEADER --}}
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <h2 class="text-xl font-bold font-serif text-gray-800">Newsletter Abonnenten</h2>

            <div class="relative w-full md:w-64">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="E-Mail suchen..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
            </div>
        </div>

        {{-- LISTE --}}
        <div class="overflow-x-auto bg-white rounded-xl border border-gray-100 shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                <tr class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200 bg-gray-50/50">
                    <th class="px-6 py-4">E-Mail</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Angemeldet am</th>
                    <th class="px-6 py-4">Datenschutz</th>
                    <th class="px-6 py-4 text-right">Aktionen</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($subscribers as $sub)
                    <tr wire:key="sub-{{ $sub->id }}" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 font-bold text-gray-900">{{ $sub->email }}</td>
                        <td class="px-6 py-4">
                            @if($sub->is_verified)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Verifiziert</span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800">Ausstehend</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $sub->created_at->format('d.m.Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if($sub->privacy_accepted)
                                <span class="text-green-600">✓ Akzeptiert</span>
                            @else
                                <span class="text-red-500">Fehlt</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button wire:click="delete('{{ $sub->id }}')"
                                    wire:confirm="Abonnent wirklich löschen?"
                                    class="p-2 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors"
                                    title="Löschen">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">Keine Abonnenten gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $subscribers->links() }}
        </div>
    </div>
</div>
