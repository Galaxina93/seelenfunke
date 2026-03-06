<div x-data="{ open: false }" class="w-full min-w-0">
    <div class="bg-gray-900/80 backdrop-blur-xl p-5 sm:p-8 rounded-[2rem] sm:rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up transition-colors hover:border-gray-700 w-full min-w-0">

        <div class="flex flex-wrap items-center justify-between gap-4 transition-all" :class="open ? 'mb-6 border-b border-gray-800 pb-5' : ''">
            <div @click="open = !open" class="flex items-center gap-3 sm:gap-4 cursor-pointer group flex-1 min-w-[200px]">
                <div class="p-2 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 group-hover:text-primary group-hover:border-primary/30 transition-all shadow-inner shrink-0">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </div>
                <div class="flex items-center gap-2 min-w-0">
                    <h3 class="text-lg sm:text-xl font-serif font-bold text-white tracking-wide group-hover:text-primary transition-colors truncate">Produktvarianten (Matrix)</h3>
                    <div x-data="{ showInfo: false }" class="relative inline-block ml-1 shrink-0" @click.stop>
                        <button @mouseenter="showInfo = true" @mouseleave="showInfo = false" type="button" class="text-gray-500 hover:text-primary transition-colors flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                        </button>
                        <div x-show="showInfo" x-cloak x-transition.opacity class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 w-64 sm:w-72 p-4 bg-gray-950 border border-gray-800 text-gray-300 text-xs font-medium leading-relaxed rounded-xl shadow-2xl z-50 text-center">
                            Hier werden aus den Eigenschaften kaufbare Endprodukte generiert. Jede Variante hat eigene Preise/SKUs.
                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-start sm:justify-end w-full sm:w-auto gap-3" @click.stop>
                <span class="text-[9px] font-black uppercase tracking-widest text-primary bg-primary/10 border border-primary/20 px-3 py-1.5 rounded-lg shadow-inner">
                    {{ count($variants) }} Varianten
                </span>

                <button wire:click="generateMatrix"
                        @if(count($variants) > 0)
                            wire:confirm="Achtung: Es existiert bereits eine Matrix! Preise und Bestände für weiterhin gültige Kombinationen bleiben erhalten. Varianten, deren Eigenschaften du oben gelöscht hast, werden jedoch unwiderruflich entfernt. Willst du die Matrix wirklich neu berechnen?"
                        @endif
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto text-[10px] font-black uppercase tracking-widest px-5 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shrink-0 bg-primary text-gray-900 hover:bg-primary-dark hover:scale-[1.02] shadow-[0_0_15px_rgba(197,160,89,0.3)] disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg wire:loading wire:target="generateMatrix" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <svg wire:loading.remove wire:target="generateMatrix" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Matrix generieren
                </button>
            </div>
        </div>

        <div x-show="open" x-collapse style="display: none;">
            @if(session()->has('variants_success'))
                <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-5 py-3 rounded-xl flex items-center gap-3 text-[10px] font-black uppercase tracking-widest shadow-inner">
                    <svg class="w-5 h-5 drop-shadow-[0_0_5px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ session('variants_success') }}
                </div>
            @endif

            @if(count($variants) === 0)
                <div class="flex flex-col items-center justify-center py-12 text-center bg-gray-950/50 rounded-[2rem] border-2 border-dashed border-gray-800 shadow-inner">
                    <div class="w-16 h-16 bg-gray-900 border border-gray-800 rounded-[1.5rem] flex items-center justify-center shadow-inner mb-4">
                        <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    </div>
                    <h4 class="text-white font-bold tracking-wide mb-1.5 text-base sm:text-lg">Keine Varianten vorhanden</h4>
                    <p class="text-[10px] text-gray-500 uppercase font-black tracking-widest max-w-md mx-auto leading-relaxed">
                        Definiere zuerst Eigenschaften im Reiter darüber (z.B. Farbe: Rot, Blau). Klicke dann auf "Matrix generieren", um die Kombinationen zu erzeugen.
                    </p>
                </div>
            @else

                <div class="hidden lg:grid grid-cols-12 gap-6 text-[10px] font-black uppercase tracking-widest text-gray-500 border-b border-gray-800 pb-4 px-2 mb-2">
                    <div class="col-span-4">Variante</div>
                    <div class="col-span-3">Eigene SKU</div>
                    <div class="col-span-2">Preis (€)</div>
                    <div class="col-span-2 text-center">Bestand</div>
                    <div class="col-span-1 text-right">Aktiv</div>
                </div>

                <div class="space-y-4 lg:space-y-3 mt-4">
                    @foreach($variants as $index => $variant)
                        <div class="bg-gray-950 p-5 rounded-[1.5rem] border border-gray-800 shadow-inner flex flex-col lg:grid lg:grid-cols-12 lg:items-center gap-4 lg:gap-6 group hover:border-gray-700 transition-colors relative" wire:key="variant-{{ $index }}">

                            <div class="lg:col-span-4 flex justify-between items-start lg:block pr-12 lg:pr-0 min-w-0">
                                <div class="min-w-0">
                                    <div class="font-bold text-white text-base lg:text-sm truncate">{{ $variant['name'] }}</div>
                                    <div class="flex flex-wrap gap-1.5 mt-2 lg:mt-1.5">
                                        @foreach($variant['attributes'] as $key => $val)
                                            <span class="bg-gray-900 px-2 py-1 rounded-md border border-gray-800 shadow-inner text-[9px] uppercase tracking-widest font-black text-gray-500 truncate max-w-[120px]">{{ $key }}: <span class="text-primary drop-shadow-[0_0_5px_currentColor]">{{ $val }}</span></span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-3">
                                <label class="lg:hidden text-[9px] font-black uppercase tracking-widest text-gray-500 block mb-1.5 ml-1">Eigene SKU</label>
                                <input type="text" wire:model.blur="variants.{{ $index }}.sku" wire:change="saveVariants" class="w-full px-3 py-2.5 rounded-xl border border-gray-800 bg-gray-900 text-white font-mono text-xs focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-inner outline-none placeholder-gray-600" placeholder="{{ $product->sku ?? 'SKU' }}-{{ $index + 1 }}">
                            </div>

                            <div class="lg:col-span-2">
                                <label class="lg:hidden text-[9px] font-black uppercase tracking-widest text-gray-500 block mb-1.5 ml-1">Preis (Überschreiben)</label>
                                <div class="relative">
                                    <input type="number" step="0.01" min="0" wire:model.blur="variants.{{ $index }}.price" wire:change="saveVariants" class="w-full px-3 py-2.5 rounded-xl border border-gray-800 bg-gray-900 text-white font-mono text-xs focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-inner outline-none placeholder-gray-600 pr-8" placeholder="Standard">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold text-xs">€</span>
                                </div>
                            </div>

                            <div class="lg:col-span-2">
                                <label class="lg:hidden text-[9px] font-black uppercase tracking-widest text-gray-500 block mb-1.5 ml-1">Lagerbestand</label>
                                <div class="relative w-full lg:w-3/4 lg:mx-auto">
                                    <input type="number" min="0" wire:model.blur="variants.{{ $index }}.stock" wire:change="saveVariants" class="w-full px-3 py-2.5 rounded-xl border border-gray-800 bg-gray-900 text-white font-mono text-xs lg:text-center focus:border-primary focus:ring-1 focus:ring-primary transition-all shadow-inner outline-none placeholder-gray-600 lg:pr-4" placeholder="∞">
                                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500 lg:hidden">Stk.</span>
                                </div>
                            </div>

                            <div class="lg:col-span-1 flex items-center justify-between lg:justify-end border-t border-gray-800 lg:border-0 pt-4 lg:pt-0 mt-2 lg:mt-0">
                                <div class="flex items-center gap-3">
                                    <label class="lg:hidden text-[10px] font-black uppercase tracking-widest text-gray-500">Ist Aktiv</label>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" wire:model.live="variants.{{ $index }}.is_active" wire:change="saveVariants" class="sr-only peer">
                                        <div class="w-9 h-5 bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-gray-400 after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary peer-checked:after:bg-gray-900 border border-gray-700 shadow-inner"></div>
                                    </label>
                                </div>

                                <button wire:click="removeVariant({{ $index }})" class="absolute top-5 right-5 lg:static p-2 text-gray-600 hover:text-red-400 bg-gray-900 hover:bg-red-500/10 border border-gray-800 hover:border-red-500/30 rounded-lg transition-all shadow-inner" title="Variante löschen">
                                    <svg class="w-5 h-5 lg:w-4 lg:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                        </div>
                    @endforeach
                </div>

                    <div class="mt-6 pt-5 border-t border-gray-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-5"
                         x-data="{ saved: false }"
                         @variants-saved.window="saved = true; setTimeout(() => saved = false, 3000)">

                        <div class="flex items-start gap-3 text-[10px] font-black uppercase tracking-widest text-gray-500">
                            <svg class="w-4 h-4 text-blue-400 shrink-0 mt-0.5 drop-shadow-[0_0_5px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <p class="leading-relaxed">Leere Preis-Felder erben den Standard-Preis.<br>Leere Bestands-Felder bedeuten unbegrenzten Vorrat.</p>
                        </div>

                        <div class="flex items-center gap-4 w-full md:w-auto justify-end">
                        <span x-show="saved" x-transition.opacity style="display: none;" class="text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 drop-shadow-[0_0_8px_currentColor]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            Gespeichert
                        </span>

                            <button wire:click="saveVariants" class="w-full md:w-auto px-5 py-2.5 bg-gray-950 border border-gray-800 hover:border-gray-600 text-gray-300 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors shadow-inner flex items-center justify-center gap-2 shrink-0">
                                <svg wire:loading wire:target="saveVariants" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                <span wire:loading.remove wire:target="saveVariants">Daten Speichern</span>
                                <span wire:loading wire:target="saveVariants">Speichert...</span>
                            </button>
                        </div>
                    </div>
            @endif
        </div>
    </div>
</div>
