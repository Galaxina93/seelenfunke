{{-- Gruppen-Archiv Ansicht --}}
<div class="space-y-6">
    {{-- Header des Gruppen-Archivs --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded-xl">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white tracking-wide">Archiv: {{ $group->name }}</h4>
                    <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                        {{ $group->archivedItems->count() }} {{ $group->archivedItems->count() === 1 ? 'Eintrag' : 'Einträge' }}
                    </span>
                </div>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                    Archivierte Verträge sind aus allen Summen und Statistiken ausgeschlossen. Verträge bleiben sicher gespeichert.
                </p>
            </div>
        </div>

        <button
            type="button"
            @click="showGroupArchive = false; $wire.toggleGroupArchive('{{ $group->id }}')"
            class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all shadow-sm shrink-0"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Zurück zur Übersicht
        </button>
    </div>

    {{-- Liste der archivierten Einträge --}}
    <div class="space-y-4">
        @forelse($group->archivedItems as $archivedItem)
            <div wire:key="archived-item-{{ $archivedItem->id }}" class="bg-white dark:bg-gray-900 border border-amber-500/20 hover:border-amber-500/40 rounded-2xl p-4 sm:p-6 shadow-sm dark:shadow-inner transition-all">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    {{-- Linker Bereich: Name & Infos --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="font-bold text-base text-gray-900 dark:text-white tracking-wide">{{ $archivedItem->name }}</span>
                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Archiviert</span>
                            @if($archivedItem->is_business)
                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-blue-500/10 text-blue-500 border border-blue-500/20">Gewerblich</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-gray-500/10 text-gray-400 border border-gray-500/20">Privat</span>
                            @endif
                        </div>

                        @if($archivedItem->provider_company)
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">{{ $archivedItem->provider_company }}</p>
                        @endif

                        @if($archivedItem->description)
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mb-3 line-clamp-2 italic">{{ $archivedItem->description }}</p>
                        @endif

                        {{-- Tags --}}
                        @if(!empty($archivedItem->tags) && is_array($archivedItem->tags))
                            <div class="flex flex-wrap items-center gap-1.5 mb-3">
                                @foreach($archivedItem->tags as $tag)
                                    <span class="text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700/50">
                                        {{ $tag }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Metadaten: Archivierungsdatum & Vertrag --}}
                        <div class="flex flex-wrap items-center gap-3 text-[9px] font-bold text-gray-400 dark:text-gray-500">
                            <span>Archiviert am: {{ $archivedItem->deleted_at?->format('d.m.Y H:i') }}</span>
                            @if($archivedItem->contract_file_path)
                                <span class="inline-flex items-center gap-1 text-[var(--theme-color)]">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                    Vertragsdokument gesichert
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Rechter Bereich: Betrag & Aktionen --}}
                    <div class="flex md:flex-col items-end justify-between md:justify-start gap-3 shrink-0">
                        <div class="text-right">
                            <span class="text-base font-mono font-bold text-gray-400 dark:text-gray-500 line-through">
                                {{ number_format($archivedItem->amount, 2, ',', '.') }} €
                            </span>
                            <span class="block text-[9px] font-black text-gray-400 uppercase tracking-wider">
                                alle {{ $archivedItem->interval_months }} {{ $archivedItem->interval_months === 1 ? 'Monat' : 'Monate' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            {{-- Wiederherstellen --}}
                            <button
                                type="button"
                                wire:click="restoreItem('{{ $archivedItem->id }}')"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/20 text-[9px] font-black uppercase tracking-widest transition-all shadow-sm cursor-pointer"
                                title="Kostenstelle wiederherstellen"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                Wiederherstellen
                            </button>

                            {{-- Endgültig löschen --}}
                            <button
                                type="button"
                                wire:click="forceDeleteItem('{{ $archivedItem->id }}')"
                                wire:confirm="Achtung: Möchtest du diese Kostenstelle samt hinterlegtem Vertrag wirklich UNWIDERRUFLICH und endgültig löschen?"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20 border border-red-500/20 text-[9px] font-black uppercase tracking-widest transition-all shadow-sm cursor-pointer"
                                title="Endgültig löschen"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                Endgültig löschen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-8 text-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl">
                <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3 text-gray-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </div>
                <h5 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-1">Keine archivierten Einträge</h5>
                <p class="text-[10px] text-gray-500 mb-4">In dieser Gruppe befinden sich derzeit keine archivierten Kostenstellen.</p>
                <button
                    type="button"
                    @click="showGroupArchive = false; $wire.toggleGroupArchive('{{ $group->id }}')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)] bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border border-[var(--theme-color-30)] transition-all"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Zurück zu aktiven Kostenstellen
                </button>
            </div>
        @endforelse
    </div>
</div>
