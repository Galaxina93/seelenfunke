<div class="flex justify-between items-start group">
    <div class="flex-1">
        <div class="flex items-center gap-2">

            {{-- Warnung wenn keine Datei --}}
            @if(!$item->contract_file_path)
                <div class="text-red-500 animate-pulse" title="Kein Vertrag hinterlegt!">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            @endif

            <div class="font-semibold text-gray-800">{{ $item->name }}</div>

            @if($item->is_business)
                <span class="bg-blue-100 text-blue-600 text-[10px] px-1.5 py-0.5 rounded font-medium border border-blue-200">Gewerbe</span>
            @else
                <span class="bg-gray-100 text-gray-600 text-[10px] px-1.5 py-0.5 rounded font-medium border border-gray-200">Privat</span>
            @endif
        </div>
        <div class="text-xs text-gray-500 mt-1 flex flex-wrap gap-2 items-center">
            <span class="bg-gray-100 px-2 py-0.5 rounded text-gray-600">{{ $item->first_payment_date->format('d.m.') }}</span>
            <span>
                @switch($item->interval_months)
                    @case(1) Monatlich @break
                    @case(3) Quartalsweise @break
                    @case(6) Halbjährlich @break
                    @case(12) Jährlich @break
                    @case(24) Alle 2 Jahre @break
                @endswitch
            </span>
            @if($item->contract_file_path)
                <a href="{{ Storage::url($item->contract_file_path) }}" target="_blank" class="text-primary hover:underline flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                    Vertrag
                </a>
            @endif
        </div>
        @if($item->description)
            <div class="text-xs text-gray-400 mt-1 italic">{{ $item->description }}</div>
        @endif
    </div>
    <div class="text-right pl-4">
        <div class="font-bold whitespace-nowrap {{ $item->amount > 0 ? 'text-emerald-600' : ($item->amount < 0 ? 'text-rose-500' : 'text-gray-400') }}">
            {{ number_format($item->amount, 2, ',', '.') }} €
        </div>
        <div class="flex gap-3 justify-end mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <button wire:click="openItemForm('{{ $group->id }}', '{{ $item->id }}')" class="text-xs text-primary hover:underline font-medium">Bearbeiten</button>
            <button wire:click.stop="deleteItem('{{ $item->id }}')" wire:confirm="Wirklich löschen?" class="text-xs text-rose-400 hover:underline">Löschen</button>
        </div>
    </div>
</div>
