<div x-data="{ open: false }" class="bg-white border-2 border-dashed border-gray-300 rounded-xl overflow-hidden shadow-sm hover:shadow-md hover:border-blue-400 transition-all duration-300 flex flex-col items-center justify-center min-h-[60px]">
    <div x-show="!open" class="text-center w-full h-full flex flex-col items-center justify-center cursor-pointer" @click="open = true">
        <span class="text-sm font-bold text-gray-500 group-hover:text-blue-600">Neue Gruppe erstellen</span>
    </div>

    <div x-show="open" class="w-full p-4" x-transition>
        <h3 class="text-xs uppercase font-bold text-gray-400 tracking-wider mb-4 text-center">Gruppe anlegen</h3>
        <div class="space-y-3">
            <input type="text" wire:model="newGroupName" placeholder="Name der Gruppe" class="w-full text-sm rounded-lg border-gray-300 focus:ring-primary text-center">
            <select wire:model="newGroupType" class="w-full text-sm rounded-lg border-gray-300 focus:ring-primary text-center">
                <option value="expense">Ausgabe</option>
                <option value="income">Einnahme</option>
            </select>
            <div class="flex gap-2 justify-center pt-2">
                <button @click="open = false" class="px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-100 rounded-lg">Abbrechen</button>
                <button wire:click="createGroup" @click="open = false" class="bg-primary text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-primary-dark transition">Erstellen</button>
            </div>
        </div>
    </div>
</div>
