<div class="flex flex-col sm:flex-row justify-between items-start group">
    <div class="flex-1 min-w-0 pr-4">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-1">
            @if(!$item->contract_file_path)
                <div class="text-red-400 animate-pulse drop-shadow-[0_0_8px_currentColor]" title="Kein Vertrag hinterlegt!">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            @endif
            <div class="font-bold text-white text-base tracking-wide truncate">{{ $item->name }}</div>
            @if($item->is_business)
                <span class="bg-blue-500/10 text-blue-400 text-[8px] px-2 py-0.5 rounded-md font-black uppercase tracking-widest border border-blue-500/20 shadow-inner">Gewerbe</span>
            @else
                <span class="bg-gray-800 text-gray-400 text-[8px] px-2 py-0.5 rounded-md font-black uppercase tracking-widest border border-gray-700 shadow-inner">Privat</span>
            @endif
        </div>

        <div class="text-[10px] font-medium text-gray-500 mt-2 flex flex-wrap gap-2 sm:gap-3 items-center">
            <span class="bg-gray-900 border border-gray-800 px-2.5 py-1 rounded-md text-gray-400 shadow-inner uppercase tracking-wider font-bold">{{ $item->first_payment_date->format('d.m.') }}</span>
            <span class="uppercase tracking-widest font-black text-gray-600">
                @switch($item->interval_months)
                    @case(1) Monatlich @break
                    @case(3) Quartalsweise @break
                    @case(6) Halbjährlich @break
                    @case(12) Jährlich @break
                    @case(24) Alle 2 Jahre @break
                @endswitch
            </span>

            @if($item->contract_file_path)
                <div class="w-px h-3 bg-gray-700"></div>
                <a href="{{ Storage::url($item->contract_file_path) }}" target="_blank" class="text-primary hover:text-white transition-colors flex items-center gap-1.5 font-bold uppercase tracking-widest">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg> Vertrag
                </a>
            @endif
        </div>

        @if($item->description)
            <div class="text-[11px] text-gray-400 mt-3 italic leading-relaxed border-l-2 border-gray-800 pl-3">{{ $item->description }}</div>
        @endif
    </div>

    <div class="text-left sm:text-right mt-3 sm:mt-0 w-full sm:w-auto">
        <div class="font-mono font-bold text-xl whitespace-nowrap {{ $item->amount > 0 ? 'text-emerald-400 drop-shadow-[0_0_8px_currentColor]' : ($item->amount < 0 ? 'text-red-400 drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500') }}">{{ number_format($item->amount, 2, ',', '.') }}€ </div>
        <div class="flex gap-4 justify-start sm:justify-end mt-3 opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <button wire:click="openItemForm('{{ $group->id }}', '{{ $item->id }}')" class="text-[9px] text-gray-500 hover:text-primary font-black uppercase tracking-widest border-b border-gray-500 hover:border-primary pb-0.5 transition-colors">Bearbeiten</button>
            <button wire:click.stop="deleteItem('{{ $item->id }}')" wire:confirm="Wirklich löschen?" class="text-[9px] text-gray-600 hover:text-red-400 font-black uppercase tracking-widest border-b border-gray-600 hover:border-red-400 pb-0.5 transition-colors">Löschen</button>
        </div>
    </div>
</div>

<div class="mt-4 pt-3 border-t border-gray-800/50 w-full">
    <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-2" style="scrollbar-width: thin;">
        <svg class="w-4 h-4 text-gray-600 shrink-0 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        </svg>

        @foreach($item->tags ?? [] as $tag)
            <span class="shrink-0 bg-blue-500/10 border border-blue-500/30 text-blue-400 shadow-[0_0_10px_rgba(59,130,246,0.3)] rounded-full pl-2.5 pr-1 py-1 text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 transition-all hover:bg-blue-500/20">
                {{ $tag }}
                <button wire:click.stop="removeTagFromItem('{{ $item->id }}', '{{ $tag }}')" class="text-blue-400/70 hover:text-red-400 hover:bg-red-500/20 transition-colors rounded-full p-0.5" title="Tag entfernen">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </span>
        @endforeach

        @if($addingTagToItemId === $item->id)
            <div class="shrink-0 flex items-center gap-1 bg-gray-950 border border-blue-500/50 shadow-inner rounded-full pl-3 pr-1 py-0.5" @click.stop>
                <input type="text" wire:model="newItemTag" wire:keydown.enter="saveNewItemTag" placeholder="Tag eingeben..." class="bg-transparent text-[9px] font-black uppercase tracking-widest text-white w-24 outline-none" autofocus>
                <button wire:click.stop="saveNewItemTag" class="text-emerald-400 hover:text-emerald-300 p-0.5 transition-colors" title="Speichern">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </button>
                <button wire:click.stop="$set('addingTagToItemId', null)" class="text-gray-500 hover:text-gray-400 p-0.5 transition-colors" title="Abbrechen">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @else
            <button wire:click.stop="startAddingTag('{{ $item->id }}')" class="shrink-0 bg-gray-900 border border-gray-700 hover:border-blue-500/50 text-gray-500 hover:text-blue-400 shadow-inner rounded-full p-1 transition-all" title="Tag hinzufügen">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </button>
        @endif
    </div>
</div>
