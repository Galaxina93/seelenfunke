<div x-show="activeProfileTab === 'delete_account'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">
    <div class="mb-10 border-b border-red-900/30 pb-6">
        <h3 class="text-2xl font-serif font-bold text-red-500 mb-2 tracking-tight">Account löschen</h3>
        <p class="text-gray-400 text-sm">Lösche dein Konto dauerhaft.</p>
    </div>
    
    <div class="text-sm text-gray-400 mb-8 leading-relaxed bg-red-500/5 p-6 rounded-2xl border border-red-500/10">
        <p class="mb-2"><strong class="text-red-400 font-bold">Wichtiger rechtlicher Hinweis:</strong></p>
        <p>Sobald dein Konto gelöscht wird, werden alle nicht-kaufmännischen Daten unwiderruflich entfernt. Gemäß den gesetzlichen Aufbewahrungsfristen müssen wir jedoch buchhalterisch und steuerrechtlich relevante Daten für bis zu 10 Jahre aufbewahren.</p>
    </div>
    
    <div x-data="{ confirming: false }" class="mt-4">
        <button x-show="!confirming" @click="confirming = true" type="button" class="bg-red-500/10 text-red-500 border border-red-500/50 px-10 py-4 rounded-2xl font-black uppercase tracking-widest text-sm hover:bg-red-500 hover:text-white transition-all shadow-[0_0_20px_rgba(239,68,68,0.2)]">Account löschen</button>
        
        <div x-show="confirming" style="display: none;" class="mt-6 bg-gray-950 border border-gray-800 p-8 rounded-3xl shadow-inner animate-fade-in-up">
            <p class="text-red-500 font-bold text-lg mb-6">Bist du sicher, dass du deinen Account löschen möchtest?</p>
            <form wire:submit.prevent="deleteAccount" class="flex flex-col sm:flex-row gap-4 items-center">
                <button type="submit" class="w-full sm:w-auto bg-red-500 text-white px-8 py-4 rounded-xl font-black uppercase tracking-widest text-xs hover:bg-red-600 transition-all shadow-[0_0_15px_rgba(239,68,68,0.4)]">Ja, löschen</button>
                <button type="button" @click="confirming = false" class="w-full sm:w-auto bg-gray-800 text-white border border-gray-700 px-8 py-4 rounded-xl font-black uppercase tracking-widest text-xs hover:bg-gray-700 transition-all">Abbrechen</button>
            </form>
        </div>
    </div>
</div>
