@props([
    'headers' => [],        // Kopfzeilen-Definition
    'rows' => [],           // Die Daten (Paginator/Collection)
    'sortField' => null,    // Aktuell sortiertes Feld
    'sortDirection' => 'asc' // Aktuelle Richtung (Standard: asc)
])

<div class="w-full overflow-hidden flex flex-col bg-transparent">
    {{-- DESKTOP TABELLE --}}
    <div class="hidden md:block overflow-x-auto w-full">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="bg-gray-950/50 text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-800">
                @foreach($headers as $key => $header)
                    <th @class([
                            'px-6 py-5',
                            'text-center' => ($header['align'] ?? 'left') === 'center',
                            'text-right' => ($header['align'] ?? 'left') === 'right',
                            'cursor-pointer hover:text-gray-300 hover:bg-gray-800/30 transition-colors' => $header['sortable'] ?? false
                        ])
                        @if($header['sortable'] ?? false) wire:click="sortBy('{{ $key }}')" @endif>
                        <div @class([
                                'flex items-center gap-2',
                                'justify-center' => ($header['align'] ?? 'left') === 'center',
                                'justify-end' => ($header['align'] ?? 'left') === 'right',
                            ])>
                            {{ $header['label'] }}

                            {{-- Sortier-Icons (nur anzeigen wenn sortierbar und aktives Feld) --}}
                            @if(($header['sortable'] ?? false) && $sortField === $key)
                                <svg class="w-3.5 h-3.5 text-primary drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                          d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                </svg>
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/50 bg-transparent">
            {{ $slot }}
            </tbody>
        </table>
    </div>

    {{-- MOBILE ANSICHT --}}
    <div class="md:hidden divide-y divide-gray-800/50 bg-transparent">
        {{ $mobileSlot }}
    </div>
</div>

@if(method_exists($rows, 'links'))
    <div class="p-5 md:p-6 bg-gray-950/50 border-t border-gray-800 mt-auto">
        {{ $rows->links() }}
    </div>
@endif
