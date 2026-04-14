<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <!-- SCHRITT 1: Produktauswahl -->
    <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2rem] sm:rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up w-full mb-8">
        <h2 class="text-xl sm:text-2xl font-serif font-bold text-white tracking-wide mb-4 flex items-center gap-4">
            <span class="bg-[var(--theme-color)] text-gray-900 w-8 h-8 rounded-full flex items-center justify-center text-lg font-black shrink-0">1</span>
            Produkt für die Gewichtserfassung auswählen
        </h2>
        <p class="text-gray-400 text-sm sm:text-base mb-8 leading-relaxed">Wähle das Produkt aus, für das du das Versandmaterial hinterlegen möchtest. <br><em>Tipp: Ein mittelgroßer Versandkarton wiegt ca. 100g bis 150g. Etwas Klebeband wiegt ca. 5g. Lass es uns sicherheitshalber etwas großzügiger aufrunden.</em></p>

        <div class="relative max-w-2xl">
            <select wire:model.live="selectedProductId" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-5 py-4 text-base font-bold text-white focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color)] shadow-inner appearance-none pr-12 cursor-pointer transition-colors">
                <option value="">-- Klicke hier um ein Produkt auszuwählen --</option>
                @foreach($products as $prod)
                    <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                @endforeach
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-5 text-gray-500">
                <x-heroicon-m-chevron-down class="w-5 h-5 text-gray-500" />
            </div>
        </div>
    </div>

    <!-- SCHRITT 2: Material Editieren -->
    @if($this->product)
        <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2rem] sm:rounded-[2.5rem] shadow-2xl border border-emerald-500/20 animate-fade-in-up w-full">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 border-b border-gray-800 pb-6 gap-4">
                <h2 class="text-xl sm:text-2xl font-serif font-bold text-white tracking-wide flex items-center gap-4">
                    <span class="bg-[var(--theme-color)] text-gray-900 w-8 h-8 rounded-full flex items-center justify-center text-lg font-black shrink-0">2</span>
                    Materialien für: <span class="text-[var(--theme-color)] ml-1">{{ $this->product->name }}</span>
                </h2>
                
                @if($packagings->count() > 0)
                    <span class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest shadow-inner border bg-[var(--theme-color-10)] text-[var(--theme-color)] border-[var(--theme-color-30)]">
                        <x-heroicon-s-check-circle class="w-4 h-4 mr-2" /> {{ $packagings->count() }} Materialien gespeichert
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest shadow-inner border bg-red-500/10 text-red-400 border-red-500/30 animate-pulse">
                        <x-heroicon-s-x-circle class="w-4 h-4 mr-2" /> Noch keine Daten
                    </span>
                @endif
            </div>

            <div class="bg-gray-950/50 border border-gray-800 rounded-2xl p-6 sm:p-8 shadow-inner mb-10" x-data="{ selectedType: @entangle('newType') }">
                <h4 class="text-sm font-black uppercase tracking-wide text-gray-300 mb-6">Neues Material zum Paket hinzufügen</h4>
                
                <form wire:submit.prevent="addMaterial" class="flex flex-col md:flex-row items-start md:items-end gap-5 mb-5">
                    <div class="w-full md:flex-1">
                        <label class="block text-xs font-black tracking-widest text-gray-400 mb-2 ml-1 uppercase">Woraus besteht die Verpackung?</label>
                        <select wire:model.live="newType" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-5 py-4 text-sm font-bold text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner cursor-pointer transition-colors">
                            <option value="">-- Bitte Material wählen --</option>
                            @foreach($availableTypes as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('newType') <span class="text-xs text-red-500 font-bold block mt-2 ml-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="w-full md:w-48 shrink-0">
                        <label class="block text-xs font-black tracking-widest text-gray-400 mb-2 ml-1 uppercase">Gewicht (in Gramm)</label>
                        <div class="relative">
                            <input type="number" wire:model="newWeightGrams" min="1" class="w-full bg-gray-900 border border-gray-700 rounded-xl px-5 py-4 text-sm font-bold text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner pr-12" placeholder="z.B. 120">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-black text-gray-500 uppercase tracking-widest">g</span>
                        </div>
                        @error('newWeightGrams') <span class="text-xs text-red-500 font-bold block mt-2 ml-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="w-full md:w-auto shrink-0 mt-2 md:mt-0">
                        <button type="submit" class="w-full md:w-auto bg-[var(--theme-color)] text-gray-900 font-black text-xs md:text-sm uppercase tracking-widest px-8 py-4 rounded-xl hover:bg-white transition-all flex items-center justify-center gap-3 shadow-[0_0_15px_var(--theme-color-20)] hover:shadow-[0_0_25px_var(--theme-color-40)]">
                            <x-heroicon-o-plus class="w-5 h-5" /> Speichern
                        </button>
                    </div>
                </form>

                <!-- DYNAMISCHER HINT INFOBLOCK -->
                <div x-show="selectedType" x-cloak class="mt-4 p-4 bg-gray-900 border border-gray-800 rounded-xl text-sm text-gray-300 animate-fade-in-up">
                    <x-heroicon-s-information-circle class="w-5 h-5 text-blue-400 mr-2 inline-block align-text-bottom" />
                    <span x-show="selectedType === 'paper'"><strong>Papier, Pappe, Karton (PPK):</strong> Hierzu zählen z.B. Versandkartons, herkömmliches Packband aus Papier, Seidenpapier, Flyer, Rechnungen und Lieferscheine.</span>
                    <span x-show="selectedType === 'plastic'"><strong>Kunststoff:</strong> Hierzu zählen z.B. Luftpolsterfolie, Styropor-Chips, Plastik-Klebeband oder kleine Zip-Beutel.</span>
                    <span x-show="selectedType === 'composite'"><strong>Verbundmaterial:</strong> Hierzu zählen z.B. gängige Luftpolsterversandtaschen, da sie aus Papier (außen) und Folie (innen) fest verbunden bestehen. Bei Trennbarkeit sollten sie einzeln gewogen werden.</span>
                    <span x-show="selectedType === 'wood'"><strong>Holz:</strong> Hierzu zählen z.B. Holzwolle als Füllmaterial oder kleine Holzkisten.</span>
                    <span x-show="selectedType === 'glass'"><strong>Glas:</strong> Hierzu zählen z.B. Einwegverpackungen aus Glas.</span>
                    <span x-show="selectedType === 'tin'"><strong>Weißblech:</strong> Hierzu zählen z.B. kleine Blechdosen zur Versendung.</span>
                    <span x-show="selectedType === 'alu'"><strong>Aluminium:</strong> Hierzu zählen Schalen oder Umverpackungen aus Alu.</span>
                    <span x-show="selectedType === 'other'"><strong>Sonstiges:</strong> Alles, was sich nicht in die Hauptkategorien Papier, Kunststoff, Glas, Metall oder Holz einordnen lässt.</span>
                </div>
            </div>

            <div class="space-y-4">
                <h4 class="text-sm font-black uppercase tracking-wide text-gray-300 mb-4 px-2">Gespeicherte Verpackungsbestandteile an diesem Produkt:</h4>
                
                @forelse($packagings as $pkg)
                    @php
                        $iconComponent = match($pkg->material_type) {
                            'paper' => 'heroicon-o-document-text',
                            'plastic' => 'heroicon-o-archive-box',
                            'glass' => 'heroicon-o-beaker',
                            'wood' => 'heroicon-o-cube',
                            'tin' => 'heroicon-o-circle-stack',
                            'alu' => 'heroicon-o-rectangle-stack',
                            'composite' => 'heroicon-o-square-3-stack-3d',
                            default => 'heroicon-o-star',
                        };
                    @endphp
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border border-gray-700 bg-gray-900 p-4 sm:p-5 rounded-2xl group transition-all hover:border-gray-600 shadow-lg gap-4">
                        <div class="flex items-center gap-5 w-full sm:w-auto">
                            <div class="w-14 h-14 bg-gray-950 rounded-xl border border-gray-800 flex items-center justify-center shrink-0 shadow-inner p-3">
                                <x-dynamic-component :component="$iconComponent" class="w-7 h-7 text-[var(--theme-color)] opacity-80" />
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-bold text-white tracking-wide mb-1">{{ $availableTypes[$pkg->material_type] ?? $pkg->material_type }}</div>
                                
                                @if($editId === $pkg->id)
                                    <div class="mt-2 flex items-center gap-3 bg-gray-950 p-2 rounded-xl border border-gray-800">
                                        <input type="number" wire:model="editWeightGrams" class="w-24 bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm font-bold text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner" min="1">
                                        <span class="text-xs text-gray-500 font-black tracking-widest uppercase">Gramm</span>
                                        <div class="w-px h-6 bg-gray-800 mx-1"></div>
                                        <button wire:click="saveEdit" class="text-[var(--theme-color)] hover:opacity-80 p-1 rounded-lg hover:bg-[var(--theme-color-10)] transition-colors" title="Speichern"><x-heroicon-o-check class="w-5 h-5" /></button>
                                        <button wire:click="cancelEdit" class="text-gray-500 hover:text-gray-400 p-1 rounded-lg hover:bg-gray-800 transition-colors" title="Abbrechen"><x-heroicon-o-x-mark class="w-5 h-5" /></button>
                                    </div>
                                @else
                                    <div class="text-sm text-gray-400 font-bold mt-1 flex items-center gap-3 group/edit cursor-pointer hover:text-[var(--theme-color)] transition-colors" wire:click="startEdit({{ $pkg->id }}, {{ $pkg->weight_grams }})">
                                        <span class="text-[var(--theme-color)] text-base">{{ number_format($pkg->weight_grams, 0, ',', '.') }}</span> Gramm 
                                        <span class="bg-gray-800 px-2 py-1 rounded-lg text-[10px] uppercase opacity-0 group-hover/edit:opacity-100 transition-opacity flex items-center"><x-heroicon-s-pencil-square class="w-3 h-3 mx-1 inline-block" /> Bearbeiten</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="w-full sm:w-auto text-right sm:pr-2 border-t border-gray-800 sm:border-0 pt-3 sm:pt-0 mt-1 sm:mt-0">
                            <button wire:click="deleteMaterial({{ $pkg->id }})" wire:confirm="Sicher, dass du dieses Material entfernen willst?" class="text-gray-500 hover:text-red-500 transition-colors px-4 py-2 sm:p-2.5 rounded-xl hover:bg-red-500/10 border border-transparent hover:border-red-500/20 text-xs font-bold uppercase tracking-widest flex items-center gap-2 justify-center w-full sm:w-auto">
                                <x-heroicon-o-trash class="w-4 h-4" /> <span class="sm:hidden">Löschen</span>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center p-12 border-2 border-dashed border-gray-800 rounded-3xl bg-gray-950/30">
                        <x-heroicon-o-archive-box-x-mark class="w-16 h-16 text-gray-700 mx-auto mb-4 block" />
                        <h4 class="text-sm font-bold uppercase tracking-widest text-gray-500 mb-2">Die Liste ist noch leer</h4>
                        <p class="text-gray-500 text-sm">Bitte füge oben die Materialien hinzu, in der du dieses Produkt verschickst.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <div class="flex items-center justify-center p-12 text-center h-48 border border-dashed border-gray-800 rounded-3xl mt-8">
            <div class="text-gray-600">
                <x-heroicon-o-arrow-up-circle class="w-12 h-12 mb-3 mx-auto block opacity-20" />
                <h4 class="text-xs font-bold uppercase tracking-widest">Bitte wähle oben ein Produkt aus</h4>
            </div>
        </div>
    @endif

    <!-- INFOBEREICH: LUCID Erklärung unten im Accordion -->
    <div x-data="{ open: false }" class="bg-gray-950 border border-gray-800 rounded-[2rem] w-full mt-10 shadow-lg overflow-hidden group">
        <button @click="open = !open" type="button" class="w-full p-8 flex items-center justify-between text-left transition-colors hover:bg-gray-900">
            <h2 class="text-lg sm:text-xl font-bold text-gray-300 flex items-center gap-3 group-hover:text-white transition-colors">
                <x-heroicon-o-shield-check class="w-6 h-6 text-blue-500 inline-block align-text-bottom" /> Das Verpackungsgesetz (LUCID) einfach erklärt
            </h2>
            <x-heroicon-m-chevron-down class="w-6 h-6 text-gray-500 transition-transform duration-300" x-bind:class="open ? 'rotate-180' : ''" />
        </button>
        
        <div x-show="open" x-collapse style="display: none;" class="px-8 pb-8 pt-2">
            <div class="space-y-6 text-gray-400 text-sm sm:text-base leading-relaxed border-t border-gray-800 pt-6">
                <p>
                    In Deutschland gilt das strenge <strong>Verpackungsgesetz (VerpackG)</strong>. Die Regel ist simpel: Wer eine Verpackung (wie einen Karton, Luftpolsterfolie oder Klebeband) gewerblich an einen Endkunden verschickt, muss dafür sorgen, dass der spätere Recycling-Prozess finanziert wird (Systembeteiligung).
                </p>
                
                <div class="bg-blue-500/10 border border-blue-500/20 rounded-2xl p-6 sm:p-8 mt-4">
                    <h3 class="text-lg font-bold text-blue-400 mb-4">Die 3 einfachen Schritte zur legalen Verpackung:</h3>
                    <ol class="list-decimal list-inside space-y-4 font-medium text-gray-300">
                        <li class="pl-2">
                            <strong class="text-white">Gewichte hier im Shop eintragen</strong> 
                            Trage oben pro Artikel ein, wie viel Karton oder Plastik du verbrauchst. Unser Shop rechnet am Ende des Jahres vollautomatisch alles zusammen.
                        </li>
                        <li class="pl-2">
                            <strong class="text-white">Kostenlos bei LUCID registrieren</strong> 
                            Melde dich gratis bei der offiziellen Behörde (<a href="https://lucid.verpackungsregister.org/" target="_blank" class="text-[var(--theme-color)] hover:text-white transition-colors underline">LUCID</a>) an. Du erhältst dort eine <strong>LUCID-Registrierungsnummer</strong>, welche dringend in dein Impressum muss!
                        </li>
                        <li class="pl-2">
                            <strong class="text-white">Lizenz kaufen (ab ca. 59 € / Jahr)</strong> 
                            Gehe zu einem Anbieter wie <a href="https://www.lizenzero.de/" target="_blank" class="text-[var(--theme-color)] hover:text-white transition-colors underline">Lizenzero</a> oder <a href="https://www.zmart.de/" target="_blank" class="text-[var(--theme-color)] hover:text-white transition-colors underline">Zmart</a>, gib deine LUCID-Nummer ein und kaufe deine Mengen pauschal ein. <em>(Tipp: Für Kleingewerbe gibt es Tarife ab ~59 € pro Jahr).</em>
                        </li>
                    </ol>
                </div>

                <div class="flex items-start gap-3 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-200 mt-4">
                    <x-heroicon-s-exclamation-triangle class="w-6 h-6 mt-0.5 text-red-500 shrink-0" />
                    <p class="font-medium text-sm">Warnung: Fehlt deine gültige LUCID-Nummer im Impressum oder lizenzierst du deine Versandkartons nicht, drohen Bußgelder von bis zu 100.000 €.</p>
                </div>
            </div>
        </div>
    </div>
</div>
