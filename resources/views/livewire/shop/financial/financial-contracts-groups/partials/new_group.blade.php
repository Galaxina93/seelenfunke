<div x-data="{ open: false }" class="bg-gray-900/50 backdrop-blur-md border-2 border-dashed border-gray-800 rounded-[2rem] overflow-hidden shadow-inner hover:border-primary/50 transition-all duration-300 flex flex-col items-center justify-center min-h-[100px] group">

    <div x-show="!open" class="text-center w-full h-full flex flex-col items-center justify-center cursor-pointer p-6" @click="open = true">
        <div class="w-10 h-10 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center text-gray-500 group-hover:text-primary group-hover:bg-primary/10 transition-all mb-3 shadow-inner">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 group-hover:text-white transition-colors">Neue Gruppe erstellen</span>
    </div>

    <div x-show="open" class="w-full p-6 sm:p-8" x-transition>
        <h3 class="text-[10px] uppercase font-black text-primary tracking-[0.2em] mb-5 text-center flex items-center justify-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse shadow-[0_0_8px_currentColor]"></span>
            Gruppe anlegen
        </h3>
        <div class="space-y-4 max-w-sm mx-auto">
            <input type="text" wire:model="newGroupName" placeholder="Name der Gruppe" class="w-full text-sm rounded-xl border border-gray-800 bg-gray-950 text-white placeholder-gray-600 focus:ring-2 focus:ring-primary/30 focus:border-primary text-center p-3.5 outline-none shadow-inner transition-all">

            <select wire:model="newGroupType" class="w-full text-sm rounded-xl border border-gray-800 bg-gray-950 text-gray-400 focus:ring-2 focus:ring-primary/30 focus:border-primary text-center p-3.5 outline-none shadow-inner transition-all appearance-none cursor-pointer">
                <option value="expense" class="bg-gray-900 text-white">Ausgabe</option>
                <option value="income" class="bg-gray-900 text-white">Einnahme</option>
            </select>

            <div class="flex gap-3 justify-center pt-4 border-t border-gray-800">
                <button @click="open = false" class="px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white hover:bg-gray-800 rounded-xl transition-all border border-transparent">Abbrechen</button>
                <button wire:click="createGroup" @click="open = false" class="bg-primary text-gray-900 px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-primary-dark transition-all shadow-[0_0_15px_rgba(197,160,89,0.2)] hover:scale-[1.02]">Erstellen</button>
            </div>
        </div>
    </div>
</div>
