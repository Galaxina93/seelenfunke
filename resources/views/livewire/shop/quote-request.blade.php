<div>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold font-serif text-gray-800">Angebotsanfragen</h2>
        </div>

        {{-- Filter --}}
        <div class="bg-white p-4 rounded-t-xl border border-gray-200 flex gap-4">
            <input type="text" wire:model.live="search" placeholder="Nr, Name, Firma..." class="border-gray-300 rounded-lg text-sm w-full md:w-64">
            <select wire:model.live="filterStatus" class="border-gray-300 rounded-lg text-sm">
                <option value="">Alle Status</option>
                <option value="open">Offen</option>
                <option value="converted">Angenommen</option>
                <option value="rejected">Abgelehnt</option>
            </select>
        </div>

        {{-- Tabelle --}}
        <div class="bg-white border-x border-b border-gray-200 shadow-sm overflow-x-auto rounded-b-xl">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-500 font-bold uppercase text-xs">
                <tr>
                    <th class="px-6 py-4">Anfrage-Nr.</th>
                    <th class="px-6 py-4">Datum</th>
                    <th class="px-6 py-4">Kunde / Firma</th>
                    <th class="px-6 py-4">Typ</th>
                    <th class="px-6 py-4 text-right">Summe (Brutto)</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right"></th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($quotes as $quote)
                    <tr class="hover:bg-gray-50 cursor-pointer" wire:click="selectQuote('{{ $quote->id }}')">
                        <td class="px-6 py-4 font-mono font-bold text-gray-900">{{ $quote->quote_number }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $quote->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900">{{ $quote->first_name }} {{ $quote->last_name }}</div>
                            <div class="text-xs text-gray-500">{{ $quote->company ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($quote->is_guest)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Gast</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Kunde</span>
                            @endif
                            @if($quote->is_express)
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Express</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-gray-900">
                            {{ number_format($quote->gross_total / 100, 2, ',', '.') }} €
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($quote->status == 'open')
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-bold">Offen</span>
                            @elseif($quote->status == 'converted')
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-bold">Angenommen</span>
                            @elseif($quote->status == 'rejected')
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold">Abgelehnt</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-gray-500">Keine Anfragen gefunden.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $quotes->links() }}</div>
        </div>
    </div>
</div>
