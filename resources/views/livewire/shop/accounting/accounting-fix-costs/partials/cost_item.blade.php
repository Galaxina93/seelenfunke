<div x-data="{ expanded: false, copied: false, copyName() { navigator.clipboard.writeText('{{ addslashes($item->name) }}'); this.copied = true; setTimeout(() => this.copied = false, 2000); } }" class="w-full">
<div class="flex flex-col sm:flex-row justify-between items-start group relative z-10">
    <div class="flex-1 min-w-0 pr-4">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-1">
            @if(!$item->contract_file_path)
                <div class="text-red-400 animate-pulse drop-shadow-[0_0_8px_currentColor]" title="Kein Vertrag hinterlegt!">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            @endif
            <div class="flex items-center gap-1.5 cursor-pointer group/copy" @click.stop="copyName()" title="Namen kopieren">
                <div class="font-bold text-gray-900 dark:text-white text-base tracking-wide truncate group-hover/copy:text-[var(--theme-color)] transition-colors">{{ $item->name }}</div>
                <svg x-show="!copied" class="w-4 h-4 text-gray-500 group-hover/copy:text-[var(--theme-color)] transition-colors opacity-0 group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <svg x-cloak x-show="copied" style="display: none;" class="w-4 h-4 text-emerald-400 drop-shadow-[0_0_5px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            @if($item->is_business)
                <span class="bg-[var(--theme-color-10)] text-[var(--theme-color)] text-[8px] px-2 py-0.5 rounded-md font-black uppercase tracking-widest border border-[var(--theme-color-20)] shadow-inner">Gewerbe</span>
            @else
                <span class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-[8px] px-2 py-0.5 rounded-md font-black uppercase tracking-widest border border-gray-200 dark:border-gray-700 shadow-inner">Privat</span>
            @endif
        </div>

        <div class="text-[10px] font-medium text-gray-500 mt-2 flex flex-wrap gap-2 sm:gap-3 items-center">
            <span class="bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-2.5 py-1 rounded-md text-gray-700 dark:text-gray-400 shadow-inner uppercase tracking-wider font-bold">{{ $item->first_payment_date->format('d.m.') }}</span>
            <span class="uppercase tracking-widest font-black text-gray-500 dark:text-gray-600">
                @switch($item->interval_months)
                    @case(1) Monatlich @break
                    @case(3) Quartalsweise @break
                    @case(6) Halbjährlich @break
                    @case(12) Jährlich @break
                    @case(24) Alle 2 Jahre @break
                @endswitch
            </span>

            @if($item->contract_file_path)
                <div class="w-px h-3 bg-gray-300 dark:bg-gray-700"></div>
                <button wire:click.prevent="downloadContract('{{ $item->id }}')" class="text-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white hover:underline transition-colors flex items-center gap-1.5 font-bold uppercase tracking-widest">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg> Vertrag
                </button>
            @endif
        </div>
    </div>

    <div class="text-left sm:text-right mt-3 sm:mt-0 w-full sm:w-auto">
        <div class="font-mono font-bold text-xl whitespace-nowrap {{ $item->amount == 0 ? 'text-gray-400 dark:text-gray-500' : ($group->type === 'income' ? 'text-emerald-600 dark:text-emerald-400 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : ($group->type === 'expense' ? 'text-red-600 dark:text-red-400 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : 'text-gray-400 dark:text-gray-500')) }}">{{ number_format($item->amount, 2, ',', '.') }}€ </div>
        <div class="flex gap-4 justify-start sm:justify-end mt-3 opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            @if(isset($item->transactions) && count($item->transactions) > 0)
                <button @click.stop="expanded = !expanded" class="text-[9px] text-[var(--theme-color)] hover:opacity-80 font-black uppercase tracking-widest border-b border-[var(--theme-color-50)] hover:border-[var(--theme-color)] pb-0.5 transition-colors flex items-center gap-1">
                    <span x-text="expanded ? 'Umsätze ausblenden' : '{{ count($item->transactions) }} Umsätze anzeigen'"></span>
                    <svg class="w-3 h-3 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            @endif
            @if(is_array($item->tags) && (in_array('Versicherung', $item->tags) || in_array('Vertrag', $item->tags)))
                <button wire:click.stop="openCancellationModal('{{ $item->id }}')" class="text-[9px] text-gray-500 hover:text-red-600 dark:hover:text-red-400 font-black uppercase tracking-widest border-b border-gray-300 dark:border-gray-500 hover:border-red-600 dark:hover:border-red-400 pb-0.5 transition-colors">Kündigen</button>
            @endif
            <button wire:click="openItemForm('{{ $group->id }}', '{{ $item->id }}')" class="text-[9px] text-gray-500 hover:text-[var(--theme-color)] font-black uppercase tracking-widest border-b border-gray-300 dark:border-gray-500 hover:border-[var(--theme-color)] pb-0.5 transition-colors">Bearbeiten</button>
            <button wire:click.stop="archiveItem('{{ $item->id }}')" wire:confirm="Kostenstelle '{{ $item->name }}' ins Archiv verschieben? (Vertrag bleibt gesichert und kann jederzeit wiederhergestellt werden)" class="text-[9px] text-gray-400 dark:text-gray-500 hover:text-amber-500 dark:hover:text-amber-400 font-black uppercase tracking-widest border-b border-gray-300 dark:border-gray-700 hover:border-amber-500 dark:hover:border-amber-400 pb-0.5 transition-colors">Archivieren</button>
            <button wire:click.stop="forceDeleteItem('{{ $item->id }}')" wire:confirm="Achtung: Möchtest du diese Kostenstelle samt hinterlegtem Vertrag wirklich UNWIDERRUFLICH und endgültig löschen?" class="text-[9px] text-gray-400 dark:text-gray-600 hover:text-red-600 dark:hover:text-red-400 font-black uppercase tracking-widest border-b border-gray-300 dark:border-gray-600 hover:border-red-600 dark:hover:border-red-400 pb-0.5 transition-colors">Endgültig löschen</button>
        </div>
    </div>
</div>

{{-- Accordion for Mapped Bank Transactions --}}
<div x-show="expanded" x-collapse class="mt-4 border-t border-gray-200 dark:border-gray-800/50 pt-4 cursor-default" @dragstart.prevent.stop>
    <div class="bg-gray-50 dark:bg-gray-950/80 rounded-2xl border border-gray-200 dark:border-gray-800/80 p-4 shadow-sm dark:shadow-inner">
        <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-3 ml-1 flex items-center gap-2">
            <svg class="w-3 h-3 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
            Verknüpfte Bankumsätze
        </h4>
        <div class="divide-y divide-gray-200 dark:divide-gray-800/50">
            @forelse($item->transactions ?? [] as $tx)
                <div class="py-2.5 flex items-center justify-between text-sm hover:bg-gray-100/60 dark:hover:bg-gray-900/40 px-2 rounded-lg transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full {{ $tx->amount > 0 ? 'bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.5)]' : 'bg-gray-600' }}"></div>
                        <div>
                            <div class="font-bold text-gray-700 dark:text-gray-300 text-xs">{{ $tx->counterpart_name ?? $tx->purpose ?? 'Kein Verwendungszweck' }}</div>
                            <div class="text-[10px] text-gray-500 font-mono">{{ $tx->transaction_date ? \Carbon\Carbon::parse($tx->transaction_date)->format('d.m.Y') : 'Unbekannt' }}</div>
                        </div>
                    </div>
                    <div class="font-mono font-bold text-xs whitespace-nowrap {{ $tx->amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-700 dark:text-gray-400' }}">
                        {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount, 2, ',', '.') }} €
                    </div>
                </div>
            @empty
                <div class="text-xs text-gray-600 italic py-2">Keine Umsätze verknüpft.</div>
            @endforelse
        </div>
    </div>
</div>
</div>
