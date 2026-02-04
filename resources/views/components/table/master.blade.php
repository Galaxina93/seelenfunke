@props([
    'headers' => [],        // Kopfzeilen-Definition
    'rows' => [],           // Die Daten (Paginator/Collection)
    'sortField' => null,    // Aktuell sortiertes Feld
    'sortDirection' => 'asc' // Aktuelle Richtung (Standard: asc)
])

<div class="bg-white border-x border-b border-gray-200 shadow-sm rounded-b-xl overflow-hidden">
    {{-- DESKTOP TABELLE --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
            <tr class="bg-gray-50/50 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                @foreach($headers as $key => $header)
                    <th @class([
                            'px-6 py-4',
                            'text-center' => ($header['align'] ?? 'left') === 'center',
                            'text-right' => ($header['align'] ?? 'left') === 'right',
                            'cursor-pointer hover:bg-gray-100' => $header['sortable'] ?? false
                        ])
                        @if($header['sortable'] ?? false) wire:click="sortBy('{{ $key }}')" @endif>
                        <div @class([
                                'flex items-center gap-1',
                                'justify-center' => ($header['align'] ?? 'left') === 'center',
                                'justify-end' => ($header['align'] ?? 'left') === 'right',
                            ])>
                            {{ $header['label'] }}

                            {{-- Sortier-Icons (nur anzeigen wenn sortierbar und aktives Feld) --}}
                            @if(($header['sortable'] ?? false) && $sortField === $key)
                                <svg class="w-3 h-3 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                          d="{{ $sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" />
                                </svg>
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            {{ $slot }}
            </tbody>
        </table>
    </div>

    {{-- MOBILE ANSICHT --}}
    <div class="md:hidden divide-y divide-gray-100">
        {{ $mobileSlot }}
    </div>
</div>

@if(method_exists($rows, 'links'))
    <div class="p-4 bg-gray-50 border-t border-gray-100">
        {{ $rows->links() }}
    </div>
@endif
