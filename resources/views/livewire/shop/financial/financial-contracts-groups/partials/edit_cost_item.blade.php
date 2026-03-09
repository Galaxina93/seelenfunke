<div class="space-y-6 sm:space-y-8 bg-gray-900/80 backdrop-blur-md p-6 sm:p-8 rounded-[2rem] border border-gray-800 shadow-inner relative animate-fade-in-down">

    {{-- Label / Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-gray-800 pb-5 gap-4">
        <div class="flex items-center gap-3">
            <div class="h-2 w-2 bg-primary rounded-full shadow-[0_0_8px_rgba(197,160,89,0.8)] animate-pulse"></div>
            <span class="text-[10px] uppercase font-black text-gray-400 tracking-[0.2em]">Kostenstelle bearbeiten</span>
        </div>
        {{-- Gruppe wechseln Select --}}
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest whitespace-nowrap">Verschieben nach:</label>
            <select wire:model="targetGroupId" class="w-full sm:w-auto text-xs font-bold border border-gray-700 bg-gray-950 text-white rounded-xl py-2 px-3 focus:ring-2 focus:ring-primary/30 focus:border-primary cursor-pointer outline-none shadow-inner">
                @foreach($groups as $g)
                    <option value="{{ $g->id }}" class="bg-gray-900">{{ $g->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php
        $inputClass = "w-full text-sm p-4 rounded-xl border border-gray-700 bg-gray-950 text-white placeholder-gray-600 focus:bg-black focus:border-primary focus:ring-2 focus:ring-primary/30 transition-all duration-300 outline-none shadow-inner";
    @endphp

    {{-- Zeile 1: Name & Betrag --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8">
        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Bezeichnung</label>
            <input type="text" wire:model="itemName" placeholder="z.B. Miete" class="{{ $inputClass }}">
            @error('itemName') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Betrag</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-bold">€</span>
                <input type="number" step="0.01" wire:model="itemAmount" placeholder="0.00" class="{{ $inputClass }} pl-10 font-mono">
            </div>
            @error('itemAmount') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Steuersatz</label>
            <div class="relative">
                <select wire:model="itemTaxRate" class="{{ $inputClass }} appearance-none cursor-pointer">
                    <option value="0" class="bg-gray-950">0 %</option>
                    <option value="7" class="bg-gray-950">7 %</option>
                    <option value="19" class="bg-gray-950">19 %</option>
                </select>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Zeile 2: Intervall, Datum, Datei --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 xl:gap-8">
        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Intervall</label>
            <div class="relative">
                <select wire:model="itemInterval" class="{{ $inputClass }} appearance-none cursor-pointer">
                    <option value="1" class="bg-gray-900">Monatlich</option>
                    <option value="3" class="bg-gray-900">Quartalsweise</option>
                    <option value="6" class="bg-gray-900">Halbjährlich</option>
                    <option value="12" class="bg-gray-900">Jährlich</option>
                    <option value="24" class="bg-gray-900">Alle 2 Jahre</option>
                </select>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Erste Zahlung</label>
            <input type="date" wire:model="itemDate" class="{{ $inputClass }} cursor-pointer [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)]">
            @error('itemDate') <span class="text-[10px] text-red-400 mt-2 block font-bold tracking-widest uppercase ml-1 drop-shadow-[0_0_8px_currentColor]">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="text-[9px] font-black text-gray-500 ml-1 uppercase tracking-widest mb-1.5 block">Vertrag / Datei</label>

            @if($itemExistingFile)
                <div class="flex items-center justify-between p-3.5 rounded-xl border border-gray-800 bg-gray-950 shadow-inner">
                    <a href="{{ Storage::url($itemExistingFile) }}" target="_blank" class="text-[11px] font-bold text-blue-400 hover:text-white transition-colors truncate max-w-[80%] flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Datei öffnen
                    </a>
                    <button wire:click="removeFileFromItem('{{ $item->id }}')" class="text-gray-500 hover:text-red-400 bg-gray-900 p-1.5 rounded-lg border border-gray-800 transition-colors shadow-inner">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            @else
                <input type="file" wire:model="itemFile"
                       class="block w-full text-xs text-gray-500 file:mr-4 file:py-3.5 file:px-6 file:rounded-xl file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-gray-800 file:text-gray-300 hover:file:bg-gray-700 hover:file:text-white transition-all cursor-pointer bg-gray-950 border border-gray-800 rounded-xl p-1 shadow-inner">
            @endif
        </div>
    </div>

    {{-- Zeile 3: Checkbox & Textarea --}}
    <div class="pt-2">
        <label class="inline-flex items-center cursor-pointer select-none group mb-5">
            <input type="checkbox" wire:model="itemIsBusiness" class="sr-only peer">
            <div class="relative w-11 h-6 bg-gray-950 border border-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[1px] after:start-[1px] after:bg-gray-500 after:border-gray-500 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary shadow-inner peer-checked:after:bg-gray-900"></div>
            <span class="ms-3 text-[10px] font-black uppercase tracking-widest text-gray-500 group-hover:text-gray-300 transition-colors">Gewerblicher Eintrag</span>
        </label>

        <textarea wire:model="itemDescription" placeholder="Notizen, Vertragsnummer, Kundennummer..."
                  class="{{ $inputClass }} resize-none leading-relaxed" rows="3"></textarea>
    </div>

    {{-- Footer Actions --}}
    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 sm:gap-4 pt-6 border-t border-gray-800">
        <button wire:click="cancelItemEdit"
                class="px-6 py-3.5 text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-900 border border-gray-800 hover:text-white hover:bg-gray-800 rounded-xl transition-all shadow-inner w-full sm:w-auto text-center">
            Abbrechen
        </button>
        <button wire:click="saveItem" wire:loading.attr="disabled"
                class="bg-emerald-500 border border-emerald-400 text-gray-900 px-8 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:bg-emerald-400 hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2 w-full sm:w-auto">
            <span wire:loading.remove wire:target="saveItem">Speichern</span>
            <span wire:loading wire:target="saveItem">Speichert...</span>
            <svg wire:loading wire:target="saveItem" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </button>
    </div>
</div>
