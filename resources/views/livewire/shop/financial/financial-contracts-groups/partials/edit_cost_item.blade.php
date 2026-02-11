<div class="space-y-8 bg-white p-8 rounded-3xl border border-orange-100 shadow-xl shadow-orange-50/20 relative">

    {{-- Label / Header --}}
    <div class="flex items-center justify-between border-b border-orange-50 pb-4">
        <div class="flex items-center gap-3">
            <div class="h-2 w-2 bg-orange-400 rounded-full shadow-sm shadow-orange-200"></div>
            <span class="text-xs uppercase font-extrabold text-gray-400 tracking-widest">Kostenstelle bearbeiten</span>
        </div>
        {{-- Gruppe wechseln Select --}}
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-gray-400 uppercase">Verschieben nach:</label>
            <select wire:model="targetGroupId" class="text-xs border-gray-200 bg-gray-50 rounded-lg py-1 px-2 focus:ring-orange-200 focus:border-orange-300 cursor-pointer">
                @foreach($groups as $g)
                    <option value="{{ $g->id }}">{{ $g->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Zeile 1: Name & Betrag --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-2">
            <label class="text-xs font-bold text-gray-400 ml-1 uppercase tracking-wider">Bezeichnung</label>
            <input type="text" wire:model="itemName" placeholder="z.B. Miete"
                   class="w-full text-base p-4 rounded-2xl border-gray-200 bg-gray-50 text-gray-800 placeholder-gray-400 focus:bg-white focus:border-orange-300 focus:ring-4 focus:ring-orange-100/50 transition-all duration-200 outline-none shadow-sm">
            @error('itemName') <span class="text-xs text-red-500 mt-1 block font-medium ml-1">{{ $message }}</span> @enderror
        </div>

        <div class="space-y-2">
            <label class="text-xs font-bold text-gray-400 ml-1 uppercase tracking-wider">Betrag</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">€</span>
                <input type="number" step="0.01" wire:model="itemAmount" placeholder="0.00"
                       class="w-full text-base p-4 pl-10 rounded-2xl border-gray-200 bg-gray-50 text-gray-800 font-mono placeholder-gray-400 focus:bg-white focus:border-orange-300 focus:ring-4 focus:ring-orange-100/50 transition-all duration-200 outline-none shadow-sm">
            </div>
            @error('itemAmount') <span class="text-xs text-red-500 mt-1 block font-medium ml-1">{{ $message }}</span> @enderror
        </div>
    </div>

    {{-- Zeile 2: Intervall, Datum, Datei --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="space-y-2">
            <label class="text-xs font-bold text-gray-400 ml-1 uppercase tracking-wider">Intervall</label>
            <div class="relative">
                <select wire:model="itemInterval"
                        class="w-full text-base p-4 rounded-2xl border-gray-200 bg-gray-50 text-gray-800 focus:bg-white focus:border-orange-300 focus:ring-4 focus:ring-orange-100/50 transition-all duration-200 outline-none appearance-none cursor-pointer shadow-sm">
                    <option value="1">Monatlich</option>
                    <option value="3">Quartalsweise</option>
                    <option value="6">Halbjährlich</option>
                    <option value="12">Jährlich</option>
                    <option value="24">Alle 2 Jahre</option>
                </select>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <label class="text-xs font-bold text-gray-400 ml-1 uppercase tracking-wider">Erste Zahlung</label>
            <input type="date" wire:model="itemDate"
                   class="w-full text-base p-4 rounded-2xl border-gray-200 bg-gray-50 text-gray-800 focus:bg-white focus:border-orange-300 focus:ring-4 focus:ring-orange-100/50 transition-all duration-200 outline-none cursor-pointer shadow-sm">
            @error('itemDate') <span class="text-xs text-red-500 mt-1 block font-medium ml-1">{{ $message }}</span> @enderror
        </div>

        <div class="space-y-2">
            <label class="text-xs font-bold text-gray-400 ml-1 uppercase tracking-wider">Vertrag / Datei</label>

            @if($itemExistingFile)
                <div class="flex items-center justify-between p-3 rounded-2xl border border-gray-200 bg-gray-50">
                    <a href="{{ Storage::url($itemExistingFile) }}" target="_blank" class="text-sm font-bold text-blue-500 hover:underline truncate max-w-[80%] flex items-center gap-1">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Datei öffnen
                    </a>
                    <button wire:click="removeFileFromItem('{{ $item->id }}')" class="text-red-400 hover:text-red-600 transition p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            @else
                <input type="file" wire:model="itemFile"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-2xl file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 hover:file:bg-orange-100 transition cursor-pointer">
            @endif
        </div>
    </div>

    {{-- Zeile 3: Checkbox & Textarea --}}
    <div class="pt-2">
        <label class="inline-flex items-center cursor-pointer select-none group mb-4">
            <input type="checkbox" wire:model="itemIsBusiness" class="sr-only peer">
            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600 shadow-inner"></div>
            <span class="ms-3 text-sm font-bold text-gray-600 group-hover:text-gray-800 transition">Gewerblicher Eintrag</span>
        </label>

        <textarea wire:model="itemDescription" placeholder="Notizen, Vertragsnummer, Kundennummer..."
                  class="w-full text-base p-4 rounded-2xl border-gray-200 bg-gray-50 text-gray-800 placeholder-gray-400 focus:bg-white focus:border-orange-300 focus:ring-4 focus:ring-orange-100/50 transition-all duration-200 outline-none resize-none shadow-sm" rows="3"></textarea>
    </div>

    {{-- Footer Actions --}}
    <div class="flex justify-end gap-4 pt-6 border-t border-gray-100">
        <button wire:click="cancelItemEdit"
                class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-2xl transition-all">
            Abbrechen
        </button>
        <button wire:click="saveItem" wire:loading.attr="disabled"
                class="bg-gray-900 text-white px-8 py-3 rounded-2xl text-sm font-bold shadow-xl shadow-gray-200 hover:bg-black hover:scale-[1.02] active:scale-95 transition-all transform flex items-center gap-2 flex-row-reverse">
            <span wire:loading.remove wire:target="saveItem">Speichern</span>
            <span wire:loading wire:target="saveItem">Speichert...</span>
            <svg wire:loading wire:target="saveItem" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </button>
    </div>
</div>
