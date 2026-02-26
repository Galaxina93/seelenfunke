<div x-data="{ open: false }">
    <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up transition-colors hover:border-gray-700">

        {{-- Header (Klickbar zum Aufklappen) & Controls --}}
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-5 transition-all" :class="open ? 'mb-6 border-b border-gray-800 pb-5' : ''">

            {{-- Klickbarer Titelbereich --}}
            <div @click="open = !open" class="flex items-center gap-4 cursor-pointer group flex-1 w-full lg:w-auto">
                <div class="p-2 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 group-hover:text-primary group-hover:border-primary/30 transition-all shadow-inner shrink-0">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>

                <div class="flex items-center gap-2">
                    <h3 class="text-xl font-serif font-bold text-white tracking-wide group-hover:text-primary transition-colors">Staffelpreise</h3>

                    {{-- Tooltip (Click.stop, damit das Accordion nicht triggert) --}}
                    <div x-data="{ showInfo: false }" class="relative inline-block ml-1" @click.stop>
                        <button @mouseenter="showInfo = true" @mouseleave="showInfo = false" type="button" class="text-gray-500 hover:text-primary transition-colors flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                        </button>
                        <div x-show="showInfo" x-cloak x-transition.opacity class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 w-64 p-4 bg-gray-950 border border-gray-800 text-gray-300 text-xs font-medium leading-relaxed rounded-xl shadow-2xl z-50 text-center">
                            {{ $infoTexts['tier_pricing'] }}
                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Controls (Buttons rechts) --}}
            <div class="flex w-full lg:w-auto" @click.stop>
                <button type="button" wire:click="addTier"
                        @click="open = true"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gray-950 border border-gray-800 hover:border-primary hover:text-primary text-gray-400 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-inner shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Hinzufügen
                </button>
            </div>
        </div>

        {{-- ================================================= --}}
        {{-- EINGEKLAPPTER INHALT --}}
        {{-- ================================================= --}}
        <div x-show="open" x-collapse style="display: none;">
            <div class="pt-2">
                @if(empty($tiers))
                    <div class="flex flex-col items-center justify-center py-12 text-center bg-gray-950/50 rounded-[2rem] border-2 border-dashed border-gray-800 shadow-inner">
                        <div class="w-16 h-16 bg-gray-900 border border-gray-800 rounded-[1.5rem] flex items-center justify-center shadow-inner mb-4">
                            <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h4 class="text-white font-bold tracking-wide mb-1.5">Keine Mengenrabatte</h4>
                        <p class="text-[10px] text-gray-500 uppercase font-black tracking-widest">Füge Staffelpreise hinzu, um Verkäufe zu pushen.</p>
                    </div>
                @else
                    <div class="p-6 sm:p-8 bg-gray-950/50 rounded-[2rem] border border-gray-800 shadow-inner transition-all space-y-5">

                        {{-- Loop über Staffeln --}}
                        @foreach($tiers as $tierId => $tier)
                            {{-- Raster: 5/5/2 für sauberen Platz für den Löschen Button --}}
                            <div wire:key="tier-row-{{ $tierId }}" class="grid grid-cols-12 gap-4 sm:gap-5 items-end bg-gray-900 p-4 sm:p-5 rounded-[1.5rem] border border-gray-800 shadow-inner group transition-colors hover:border-gray-700">

                                {{-- Menge --}}
                                <div class="col-span-5">
                                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1">Ab Menge</label>
                                    <div class="relative">
                                        <input type="number"
                                               wire:model.blur="tiers.{{ $tierId }}.qty"
                                               wire:change="updateTier('{{ $tierId }}')"
                                               class="w-full px-4 py-3 rounded-xl border border-gray-800 bg-gray-950 text-white font-mono font-bold focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm pr-12 outline-none shadow-inner transition-all"
                                               placeholder="5">
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500">Stk.</span>
                                    </div>
                                </div>

                                {{-- Rabatt --}}
                                <div class="col-span-5">
                                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1">Rabatt</label>
                                    <div class="relative">
                                        <input type="number" step="0.01"
                                               wire:model.blur="tiers.{{ $tierId }}.percent"
                                               wire:change="updateTier('{{ $tierId }}')"
                                               class="w-full px-4 py-3 rounded-xl border border-gray-800 bg-gray-950 text-emerald-400 font-mono font-bold focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 text-sm pr-10 outline-none shadow-inner transition-all"
                                               placeholder="10">
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase text-emerald-500/50">%</span>
                                    </div>
                                </div>

                                {{-- Löschen --}}
                                <div class="col-span-2 flex justify-end pb-1 sm:pb-0.5">
                                    <button type="button" wire:click="removeTier('{{ $tierId }}')" class="p-3 text-gray-600 hover:text-red-400 bg-gray-950 hover:bg-red-500/10 border border-gray-800 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Entfernen">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach

                        {{-- Vorschau --}}
                        @php $firstTier = reset($tiers); @endphp
                        @if((float)$currentPrice > 0 && $firstTier && isset($firstTier['qty']) && $firstTier['qty'] > 0)
                            <div class="mt-5 pt-4 border-t border-gray-800 text-[10px] font-medium text-gray-500 flex flex-wrap gap-3 justify-between items-center bg-gray-950 px-5 py-4 rounded-xl shadow-inner">
                                <span class="flex items-center gap-2 uppercase font-black tracking-widest text-blue-400 drop-shadow-[0_0_5px_currentColor]">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Beispielrechnung:
                                </span>
                                <span class="tracking-wide">
                                    Bei <strong class="text-white">{{ $firstTier['qty'] }} Stück</strong> sinkt der Preis auf
                                    <strong class="text-emerald-400 font-mono text-xs bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-md shadow-inner inline-block mx-1 drop-shadow-[0_0_8px_currentColor]">{{ number_format(((float)$currentPrice * (1 - ((float)$firstTier['percent'] / 100))), 2, ',', '.') }} €</strong> / Stk.
                                </span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
