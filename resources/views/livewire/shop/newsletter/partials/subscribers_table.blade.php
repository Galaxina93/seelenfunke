<div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 p-8 animate-fade-in">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4 px-2">
        <div>
            <h2 class="text-2xl font-serif font-bold text-gray-900">Seelenfunke-Verteiler</h2>
            <p class="text-sm text-gray-500">Ihre treuesten Empfänger im Überblick.</p>
        </div>
        <div class="relative w-full sm:w-72">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Empfänger suchen..." class="w-full pl-10 pr-4 py-3 bg-gray-50 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all text-sm">
            <i class="bi bi-search absolute left-4 top-3.5 text-gray-400"></i>
        </div>
    </div>

    <div class="overflow-x-auto rounded-[2rem] border border-gray-100 shadow-inner">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] border-b border-gray-100 bg-gray-50/50">
                <th class="px-8 py-5">Status</th>
                <th class="px-8 py-5">E-Mail Adresse</th>
                <th class="px-8 py-5">Beitritt</th>
                <th class="px-8 py-5 text-right">Aktion</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            @forelse($subscribers as $sub)
                <tr class="hover:bg-blue-50/20 transition-colors group">
                    <td class="px-8 py-5">
                        @if($sub->is_verified)
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase text-green-600 bg-green-100 px-3 py-1 rounded-full border border-green-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Verifiziert
                                    </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase text-amber-600 bg-amber-100 px-3 py-1 rounded-full border border-amber-200">
                                        Wartend
                                    </span>
                        @endif
                    </td>
                    <td class="px-8 py-5 font-bold text-gray-700">{{ $sub->email }}</td>
                    <td class="px-8 py-5 text-xs text-gray-400">{{ $sub->created_at->format('d. M Y') }}</td>
                    <td class="px-8 py-5 text-right">
                        <button wire:click="deleteSubscriber('{{ $sub->id }}')" class="w-10 h-10 rounded-xl flex items-center justify-center text-gray-300 hover:bg-red-50 hover:text-red-500 transition-all opacity-0 group-hover:opacity-100">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-8 py-20 text-center text-gray-400 italic">Keine Empfänger gefunden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-8">{{ $subscribers->links() }}</div>
</div>
