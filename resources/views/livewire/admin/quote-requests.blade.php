<div class="min-h-screen bg-gray-50 p-4 lg:p-6">

    {{-- STATS HEADER --}}
    @php
        $stats = [
            'total' => \App\Models\QuoteRequest::count(),
            'open' => \App\Models\QuoteRequest::where('status', 'open')->count(),
            'converted' => \App\Models\QuoteRequest::where('status', 'converted')->count(),
        ];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-blue-50 rounded-full text-blue-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Anfragen Gesamt</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-yellow-50 rounded-full text-yellow-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Offene Anfragen</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['open'] }}</p>
            </div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-green-50 rounded-full text-green-600">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Angenommen</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['converted'] }}</p>
            </div>
        </div>
    </div>

    {{-- TOOLBAR --}}
    <div class="bg-white rounded-t-xl border border-gray-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="relative w-full md:w-96">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche (Nr, Name, Firma)..." class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-primary focus:border-primary">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto">
            <select wire:model.live="filterStatus" class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-primary">
                <option value="">Alle Status</option>
                <option value="open">Offen</option>
                <option value="converted">Angenommen</option>
                <option value="rejected">Abgelehnt</option>
            </select>
        </div>
    </div>

    {{-- DESKTOP TABELLE --}}
    <div class="hidden md:block bg-white border-x border-b border-gray-200 shadow-sm overflow-x-auto rounded-b-xl">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="bg-gray-50/50 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                <th class="px-6 py-4">Anfrage-Nr.</th>
                <th class="px-6 py-4">Datum</th>
                <th class="px-6 py-4">Kunde</th>
                <th class="px-6 py-4 text-right">Summe</th>
                <th class="px-6 py-4 text-center">Status</th>
                <th class="px-6 py-4 text-right">Aktionen</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($quotes as $quote)
                <tr class="hover:bg-gray-50/50 transition-colors group text-sm cursor-pointer" wire:click="selectQuote('{{ $quote->id }}')">
                    <td class="px-6 py-4 font-mono font-bold text-gray-900 text-primary hover:underline">
                        {{ $quote->quote_number }}
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        {{ $quote->created_at->format('d.m.Y H:i') }}
                        @if($quote->is_express)
                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wide">Express</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">{{ $quote->first_name }} {{ $quote->last_name }}</div>
                        <div class="text-xs text-gray-400">{{ $quote->company ?? $quote->email }}</div>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-900">
                        {{ number_format($quote->gross_total / 100, 2, ',', '.') }} €
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($quote->status == 'open')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Offen</span>
                        @elseif($quote->status == 'converted')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">Angenommen</span>
                        @elseif($quote->status == 'rejected')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">Abgelehnt</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-blue-600 hover:underline text-xs font-bold">Öffnen</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">Keine Anfragen gefunden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE KACHELN (Karten-Layout) --}}
    <div class="md:hidden">
        @forelse($quotes as $quote)
            <div wire:click="selectQuote('{{ $quote->id }}')" class="bg-white p-4 border-x border-b border-gray-200 first:border-t first:rounded-t-none last:rounded-b-xl shadow-sm active:bg-gray-50 transition-colors cursor-pointer">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex flex-col">
                        <span class="font-mono font-bold text-primary text-sm">{{ $quote->quote_number }}</span>
                        <span class="text-[10px] text-gray-400 italic">{{ $quote->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div>
                        @if($quote->status == 'open')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-800 border border-yellow-200 uppercase">Offen</span>
                        @elseif($quote->status == 'converted')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800 border border-green-200 uppercase">Angenommen</span>
                        @elseif($quote->status == 'rejected')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 border border-red-200 uppercase">Abgelehnt</span>
                        @endif
                    </div>
                </div>

                <div class="flex justify-between items-end">
                    <div class="truncate pr-4">
                        <div class="font-bold text-gray-900 text-sm truncate">{{ $quote->first_name }} {{ $quote->last_name }}</div>
                        <div class="text-[11px] text-gray-500 truncate">{{ $quote->company ?? $quote->email }}</div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        @if($quote->is_express)
                            <div class="mb-1"><span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-red-100 text-red-800 uppercase tracking-tighter border border-red-200">Express</span></div>
                        @endif
                        <div class="font-bold text-gray-900 text-base">{{ number_format($quote->gross_total / 100, 2, ',', '.') }} €</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white p-12 text-center text-gray-500 border border-gray-200 rounded-xl">
                Keine Anfragen gefunden.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $quotes->links() }}
    </div>

</div>
