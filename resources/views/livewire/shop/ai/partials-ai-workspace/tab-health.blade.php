        <!-- HEALTH TAB CONTENT -->
        <div wire:key="tab-health" :class="{'hidden': activeTab !== 'health'}" class="flex-1 shrink-0 rounded-2xl border border-[var(--theme-color-50)] bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-[0_0_30px_var(--theme-color-20)] h-full w-full p-6">
            
            <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 grid grid-cols-1 xl:grid-cols-2 gap-8">
                
                <!-- MEDICATIONS -->
                <div class="flex flex-col gap-4">
                    <div class="flex justify-between items-center border-b border-gray-800 pb-2">
                        <h2 class="text-xl font-black text-[var(--theme-color)] uppercase tracking-widest flex items-center gap-2">
                            <x-heroicon-o-beaker class="w-6 h-6" /> Aktive Medikamente
                        </h2>
                        <button wire:click="editMedication()" class="bg-[var(--theme-color)] hover:brightness-110 text-black px-3 py-1.5 rounded text-xs font-bold transition-all shadow-lg">
                            <x-heroicon-o-plus class="w-4 h-4 inline-block -mt-0.5" /> Neu
                        </button>
                    </div>

                    @if(count($this->activeMedications) > 0)
                        <div class="space-y-3">
                            @foreach($this->activeMedications as $med)
                                <div class="bg-black/40 border border-gray-800 rounded-xl p-4 hover:border-[var(--theme-color-50)] transition-colors flex justify-between items-start group">
                                    <div>
                                        <h3 class="font-bold text-gray-200 text-lg flex items-center gap-2">
                                            {{ $med->name }}
                                            @if($med->is_long_term)
                                                <span class="bg-[var(--theme-color-20)] text-[var(--theme-color)] opacity-90 text-[10px] px-2 py-0.5 rounded-full border border-[var(--theme-color-30)] uppercase tracking-widest">Dauermedikation</span>
                                            @endif
                                        </h3>
                                        <p class="text-sm text-gray-400 mt-1">{{ $med->active_ingredients ?: 'Keine Wirkstoffangabe' }}</p>
                                        <div class="flex gap-4 mt-3 text-xs text-gray-500 font-mono">
                                            @if($med->dosage)<span class="bg-gray-800 px-2 py-1 rounded border border-gray-700">Dosis: <span class="text-gray-300">{{ $med->dosage }}</span></span>@endif
                                            @if($med->frequency)<span class="bg-gray-800 px-2 py-1 rounded border border-gray-700">Frequenz: <span class="text-gray-300">{{ $med->frequency }}</span></span>@endif
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button wire:click="editMedication({{ $med->id }})" class="p-1.5 bg-gray-800 hover:bg-indigo-600 text-gray-400 hover:text-white rounded transition-colors" title="Bearbeiten"><x-heroicon-s-pencil class="w-4 h-4" /></button>
                                        <button wire:click="deleteMedication({{ $med->id }})" wire:confirm="Dieses Medikament wirklich löschen?" class="p-1.5 bg-gray-800 hover:bg-red-600 text-gray-400 hover:text-white rounded transition-colors" title="Löschen"><x-heroicon-s-trash class="w-4 h-4" /></button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex-1 flex flex-col items-center justify-center text-gray-600 py-12 border-2 border-dashed border-gray-800 rounded-xl">
                            <x-heroicon-o-beaker class="w-12 h-12 mb-3 opacity-50" />
                            <p class="font-mono text-sm">Keine aktiven Medikamente eingetragen.</p>
                        </div>
                    @endif
                </div>

                <!-- DOCTORS -->
                <div class="flex flex-col gap-4">
                    <div class="flex justify-between items-center border-b border-gray-800 pb-2">
                        <h2 class="text-xl font-black text-[var(--theme-color)] uppercase tracking-widest flex items-center gap-2">
                            <x-heroicon-o-identification class="w-6 h-6" /> Ärzte & Praxen
                        </h2>
                        <a href="/admin/contacts" target="_blank" class="bg-gray-800 hover:bg-gray-700 text-gray-300 px-3 py-1.5 rounded text-xs font-bold transition-colors shadow-lg border border-gray-700">
                            Adressbuch öffnen <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3 inline-block ml-1" />
                        </a>
                    </div>

                    @if(count($this->doctors) > 0)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($this->doctors as $doc)
                                <div class="bg-black/40 border border-gray-800 rounded-xl p-3 hover:border-gray-600 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-[var(--theme-color-20)] border border-[var(--theme-color-50)] flex items-center justify-center shrink-0">
                                            <span class="text-[var(--theme-color)] font-black">{{ substr($doc->first_name ?? $doc->last_name, 0, 1) }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="font-bold text-gray-200 text-sm truncate">{{ trim(($doc->title ?? '') . ' ' . $doc->first_name . ' ' . $doc->last_name) }}</h3>
                                            <p class="text-xs text-[var(--theme-color)] opacity-80 font-mono truncate">{{ $doc->relation_type ?: 'Arzt' }}</p>
                                        </div>
                                    </div>
                                    @if($doc->phone || $doc->email)
                                        <div class="mt-3 text-xs text-gray-400 space-y-1 bg-gray-900 rounded p-2">
                                            @if($doc->phone)<div class="flex items-center gap-2 truncate"><x-heroicon-s-phone class="w-3 h-3 text-gray-500" /> {{ $doc->phone }}</div>@endif
                                            @if($doc->email)<div class="flex items-center gap-2 truncate"><x-heroicon-s-envelope class="w-3 h-3 text-gray-500" /> {{ $doc->email }}</div>@endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex-1 flex flex-col items-center justify-center text-gray-600 py-12 border-2 border-dashed border-gray-800 rounded-xl">
                            <x-heroicon-o-identification class="w-12 h-12 mb-3 opacity-50" />
                            <p class="font-mono text-sm text-center px-4">Keine Ärzte im Adressbuch gefunden.<br>Lege Kontakte mit der Relation 'Arzt' oder 'Praxis' an.</p>
                        </div>
                    @endif
                </div>

            </div>
        </div>

        <!-- MEDICATION MODAL -->
        <div x-data="{ show: @entangle('showMedicationModal') }" 
             x-show="show" 
             style="display: none;" 
             class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            
            <div x-show="show" 
                 x-transition.opacity 
                 class="absolute inset-0 bg-black/80 backdrop-blur-sm" 
                 @click="show = false"></div>
                 
            <div x-show="show" 
                 x-transition.scale.95 
                 class="relative bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl p-6 max-w-lg w-full flex flex-col gap-4">
                 
                <h3 class="text-xl font-black text-[var(--theme-color)] uppercase tracking-widest border-b border-gray-800 pb-3">Medikament {{ $medicationForm['id'] ? 'bearbeiten' : 'hinzufügen' }}</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-400 uppercase tracking-widest mb-1">Name / Präparat <span class="text-[var(--theme-color)]">*</span></label>
                        <input type="text" wire:model="medicationForm.name" class="w-full bg-black border border-gray-800 rounded px-3 py-2 text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none">
                        @error('medicationForm.name') <span class="text-[var(--theme-color)] text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-xs text-gray-400 uppercase tracking-widest mb-1">Wirkstoffe</label>
                        <input type="text" wire:model="medicationForm.active_ingredients" class="w-full bg-black border border-gray-800 rounded px-3 py-2 text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-400 uppercase tracking-widest mb-1">Dosis (z.B. 50mg)</label>
                            <input type="text" wire:model="medicationForm.dosage" class="w-full bg-black border border-gray-800 rounded px-3 py-2 text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 uppercase tracking-widest mb-1">Frequenz (z.B. 1-0-1)</label>
                            <input type="text" wire:model="medicationForm.frequency" class="w-full bg-black border border-gray-800 rounded px-3 py-2 text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs text-gray-400 uppercase tracking-widest mb-1">Beschreibung / Grund</label>
                        <textarea wire:model="medicationForm.description" rows="2" class="w-full bg-black border border-gray-800 rounded px-3 py-2 text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none"></textarea>
                    </div>
                    
                    <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-gray-800 hover:border-[var(--theme-color-50)] bg-black/50 transition-colors">
                        <input type="checkbox" wire:model="medicationForm.is_long_term" class="w-5 h-5 bg-black border-gray-600 rounded text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                        <span class="text-sm text-gray-300 font-bold">Dauermedikation</span>
                    </label>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-800 mt-2">
                    <button @click="show = false" class="px-4 py-2 rounded text-gray-400 hover:bg-gray-800 hover:text-white font-bold transition-colors">Abbrechen</button>
                    <button wire:click="saveMedication" class="px-6 py-2 rounded bg-[var(--theme-color)] hover:brightness-110 text-black font-black tracking-widest transition-all shadow-lg shadow-[var(--theme-color-20)]">Speichern</button>
                </div>
            </div>
        </div>
