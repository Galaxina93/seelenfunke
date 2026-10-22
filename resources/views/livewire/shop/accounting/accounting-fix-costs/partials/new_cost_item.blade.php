{{-- Toggle Button --}}
<div class="flex justify-center mt-8">
    @if($showAddItemFormForGroup !== $group->id)
        <button wire:click="toggleAddItemForm('{{ $group->id }}')" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-700 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white px-6 py-2.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-2 transition-all shadow-sm dark:shadow-inner hover:shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Kostenstelle hinzufügen
        </button>
    @endif
</div>

{{-- Formular --}}
@if($showAddItemFormForGroup === $group->id && !$editingItemId)
    <div draggable="false" @dragstart.stop.prevent class="mt-8 space-y-6 sm:space-y-8 bg-white dark:bg-gray-950/50 p-6 sm:p-8 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner relative animate-fade-in-down">

        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-5">
            <span class="text-[10px] uppercase font-black text-[var(--theme-color)] tracking-[0.2em] flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)] animate-pulse shadow-[0_0_8px_currentColor]"></span>
                Neue Kostenstelle anlegen
            </span>
            <button wire:click="toggleAddItemForm('{{ $group->id }}')" class="text-gray-400 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400 transition-colors bg-gray-100 dark:bg-gray-900 p-1.5 rounded-lg border border-gray-200 dark:border-gray-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        @php
            $inputClass = "w-full text-sm p-4 rounded-xl border border-gray-300 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:bg-white dark:focus:bg-gray-950 focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-30)] transition-all duration-300 outline-none shadow-sm dark:shadow-inner cursor-pointer";
        @endphp

        {{-- KI-Schnellimport & Dokumenten-Upload --}}
        <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-br from-purple-500/10 via-indigo-500/5 to-transparent border border-purple-500/20 shadow-sm relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-purple-500/20 text-purple-400 text-base shadow-[0_0_15px_rgba(168,85,247,0.35)] shrink-0">
                        ✨
                    </span>
                    <div>
                        <h4 class="text-xs font-black uppercase text-gray-900 dark:text-white tracking-widest flex items-center gap-2">
                            Vertragsdokument hochladen & KI-Auto-Fill
                            <span class="text-[9px] font-bold text-purple-600 dark:text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded-full border border-purple-500/20">1-Klick</span>
                        </h4>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Lade deine PDF oder ein Bild hoch – die KI füllt sofort jedes Formularfeld für dich aus.</p>
                    </div>
                </div>

                @if($itemFile)
                    <button type="button"
                            wire:click.prevent="analyzeContractWithAi"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white shadow-[0_0_20px_rgba(168,85,247,0.4)] hover:scale-[1.03] active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer shrink-0">
                        <span wire:loading.remove wire:target="analyzeContractWithAi">✨ Alle Felder per KI ausfüllen</span>
                        <span wire:loading wire:target="analyzeContractWithAi" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            KI analysiert Dokument...
                        </span>
                    </button>
                @endif
            </div>

            <div class="space-y-3">
                <input type="file" wire:model.live="itemFile" class="block w-full text-xs text-gray-500 file:mr-4 file:py-3.5 file:px-6 file:rounded-xl file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-purple-500/15 dark:file:bg-purple-500/20 file:text-purple-700 dark:file:text-purple-300 hover:file:bg-purple-500/25 dark:hover:file:bg-purple-500/30 transition-all cursor-pointer bg-white dark:bg-gray-900 border border-purple-200 dark:border-purple-500/30 rounded-xl p-1 shadow-sm">

                @if($itemFile)
                    <div class="flex items-center gap-2 text-[10px] text-gray-600 dark:text-gray-400 font-mono bg-white/60 dark:bg-gray-950/60 p-2.5 rounded-xl border border-gray-200 dark:border-gray-800">
                        <span class="text-xs">📁</span>
                        <span class="font-bold text-gray-700 dark:text-gray-300">Automatischer Zielpfad:</span>
                        <span class="text-[var(--theme-color)] font-bold truncate">{{ $this->predictedContractPath }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Zeile 1: Grunddaten --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8">
            <div>
                <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Bezeichnung</label>
                <input type="text" wire:model="itemName" placeholder="z.B. Autoversicherung" class="{{ $inputClass }}">
                @error('itemName') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Betrag</label>
                <div class="relative">
                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-bold">€</span>
                    <input type="text" wire:model="itemAmount" placeholder="0.00" class="{{ $inputClass }} pl-10 font-mono">
                </div>
                @error('itemAmount') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Steuersatz</label>
                <div class="relative">
                    <select wire:model="itemTaxRate" class="{{ $inputClass }} appearance-none cursor-pointer">
                        <option value="0" class="bg-gray-950">0 % (z.B. Versicherungen)</option>
                        <option value="7" class="bg-gray-950">7 %</option>
                        <option value="{{ shop_setting('default_tax_rate', 19) }}" class="bg-gray-950">{{ shop_setting('default_tax_rate', 19) }} %</option>
                    </select>
                    <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>
                </div>
            </div>
        </div>

        {{-- Zeile 2: Zahlungsrhythmus & Startdatum --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8">
            <div>
                <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Intervall</label>
                <div class="relative">
                    <select wire:model="itemInterval" class="{{ $inputClass }} appearance-none cursor-pointer">
                        <option value="1" class="bg-gray-950">Monatlich</option>
                        <option value="3" class="bg-gray-950">Quartalsweise</option>
                        <option value="6" class="bg-gray-950">Halbjährlich</option>
                        <option value="12" class="bg-gray-950">Jährlich</option>
                        <option value="24" class="bg-gray-950">Alle 2 Jahre</option>
                    </select>
                    <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>
                </div>
            </div>
            <div>
                <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Erste Zahlung / Startdatum</label>
                <input type="date" wire:model="itemDate" class="{{ $inputClass }} cursor-pointer dark:[&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)] text-gray-400">
                @error('itemDate') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Zeile 3: Checkboxen & Notizen --}}
        <div class="pt-2">
            <div class="flex flex-wrap items-center gap-6 mb-4">
                <label class="inline-flex items-center cursor-pointer select-none group">
                    <input type="checkbox" wire:model.live="itemIsBusiness" class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-200 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-white dark:after:bg-gray-500 after:border-gray-300 dark:after:border-gray-500 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[var(--theme-color)] shadow-inner peer-checked:after:bg-white dark:peer-checked:after:bg-gray-950"></div>
                    <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-900 dark:group-hover:text-gray-300 transition-colors">Gewerblicher Eintrag</span>
                </label>
                <label class="inline-flex items-center cursor-pointer select-none group">
                    <input type="checkbox" wire:model.live="itemRequiresContract" class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-200 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-white dark:after:bg-gray-500 after:border-gray-300 dark:after:border-gray-500 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[var(--theme-color)] shadow-inner peer-checked:after:bg-white dark:peer-checked:after:bg-gray-950"></div>
                    <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-900 dark:group-hover:text-gray-300 transition-colors">Vertrag/Abo benötigt</span>
                </label>
            </div>
            <textarea wire:model="itemDescription" placeholder="Notizen, Vertragsdetails, Kennzeichen, Konditionen..." class="{{ $inputClass }} resize-none leading-relaxed" rows="3"></textarea>
        </div>

        {{-- Zeile 4: Vertrags- und Stammdaten (Für Kündigungen & Dokumentation) --}}
        <div class="border-t border-gray-200 dark:border-gray-800 pt-6">
            <h4 class="text-[10px] font-black uppercase text-gray-400 tracking-[0.2em] mb-4 flex items-center justify-between">
                <span>Vertrags- & Stammdaten (Anbieter, Fristen & Adresse)</span>
                @if($itemRequiresContract)
                    <span class="text-[9px] font-bold text-gray-500 normal-case tracking-normal">Wird für Kündigungsvorlagen & Buchhaltung genutzt</span>
                @endif
            </h4>

            <div class="mb-6">
                <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Vertragspartner / Firma</label>
                <input type="text" wire:model="itemProviderCompany" placeholder="z.B. ADAC Autoversicherung AG" class="{{ $inputClass }}">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8 mb-6">
                <div>
                    <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Vertragsnummer / Police</label>
                    <input type="text" wire:model="itemContractNumber" placeholder="z.B. as-9185063127" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Kündigungsfrist</label>
                    <input type="text" wire:model="itemNoticePeriod" placeholder="z.B. 1 Monat zum Ablauf" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Vertragsende / Ablauf</label>
                    <input type="date" wire:model="itemContractEndDate" class="{{ $inputClass }} cursor-pointer dark:[&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
                </div>
            </div>

            <h5 class="text-[9px] font-black uppercase text-gray-500 tracking-widest mb-3">Anbieter Adresse & Kontakt</h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 xl:gap-8 mb-4">
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Straße</label>
                        <input type="text" wire:model="itemProviderStreet" placeholder="Straße" class="{{ $inputClass }}">
                    </div>
                    <div class="w-1/3">
                        <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Hausnr.</label>
                        <input type="text" wire:model="itemProviderHouseNumber" placeholder="Nr." class="{{ $inputClass }}">
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="w-1/3">
                        <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">PLZ</label>
                        <input type="text" wire:model="itemProviderZip" placeholder="PLZ" class="{{ $inputClass }}">
                    </div>
                    <div class="flex-1">
                        <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Ort</label>
                        <input type="text" wire:model="itemProviderCity" placeholder="Ort" class="{{ $inputClass }}">
                    </div>
                </div>
                <div>
                    <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">E-Mail</label>
                    <input type="email" wire:model="itemProviderEmail" placeholder="kontakt@anbieter.de" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Telefon / Webseite (Optional)</label>
                    <input type="text" wire:model="itemProviderPhone" class="{{ $inputClass }}" placeholder="Telefonnummer oder URL">
                </div>
            </div>
        </div>

        {{-- Tags Bereich --}}
        <div class="border-t border-gray-200 dark:border-gray-800 pt-6">
            <label class="flex items-center justify-between text-[10px] font-black uppercase text-gray-400 tracking-[0.2em] mb-3">
                <span class="flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-[var(--theme-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                    Tags / Schlagwörter
                </span>
                <span class="text-[9px] font-bold text-gray-500 normal-case tracking-normal">Durch KI gesetzt oder manuell anpassen</span>
            </label>
            <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl min-h-[48px]">
                @forelse($itemTags as $tag)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-wider bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] text-[var(--theme-color)] shadow-sm">
                        <span>{{ $tag }}</span>
                        <button type="button" wire:click.prevent="removeTagFromCurrentForm('{{ $tag }}')" class="hover:text-red-400 hover:bg-red-500/20 rounded-full p-0.5 transition-colors cursor-pointer" title="Tag entfernen">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </span>
                @empty
                    <span class="text-[10px] text-gray-400 italic">Noch keine Tags vergeben (werden bei KI-Import automatisch generiert).</span>
                @endforelse
                <div class="inline-flex items-center gap-1 ml-auto">
                    <input type="text" wire:model="itemNewTag" wire:keydown.enter.prevent="addTagToCurrentForm" placeholder="+ Neuer Tag..." class="bg-transparent text-[9px] font-black uppercase tracking-widest text-gray-900 dark:text-white px-2 py-1 outline-none border-b border-gray-300 dark:border-gray-700 focus:border-[var(--theme-color)] w-28">
                    <button type="button" wire:click.prevent="addTagToCurrentForm" class="text-xs font-bold text-gray-500 hover:text-[var(--theme-color)] px-1.5 py-0.5 rounded transition-colors cursor-pointer" title="Tag hinzufügen">+</button>
                </div>
            </div>
            @if(!empty($this->globalTags))
                <div class="flex flex-wrap items-center gap-1.5 mt-2.5">
                    <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mr-1">Vorschläge:</span>
                    @foreach(array_slice($this->globalTags, 0, 10) as $gTag)
                        @if(!in_array($gTag, $itemTags))
                            <button type="button" wire:click.prevent="addTagToCurrentForm('{{ $gTag }}')" class="text-[8px] font-black uppercase tracking-wider px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color-10)] text-gray-600 dark:text-gray-400 hover:text-[var(--theme-color)] border border-gray-200 dark:border-gray-700/50 transition-all cursor-pointer">+ {{ $gTag }}</button>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 sm:gap-4 pt-6 border-t border-gray-200 dark:border-gray-800">
            <button wire:click="toggleAddItemForm('{{ $group->id }}')" class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-800 rounded-xl transition-all shadow-sm dark:shadow-inner w-full sm:w-auto text-center">Abbrechen</button>

            <button wire:click="saveNewItem('{{ $group->id }}')" wire:loading.attr="disabled" class="bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-gray-900 px-8 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_var(--theme-color-20)] hover:bg-[var(--theme-color)]-dark hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 w-full sm:w-auto">
                <span wire:loading.remove wire:target="saveNewItem">Speichern</span>
                <span wire:loading wire:target="saveNewItem">Speichert...</span>
                <svg wire:loading wire:target="saveNewItem" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
        </div>
    </div>
@endif
