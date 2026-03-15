<div>
    <div class="animate-fade-in-up font-sans antialiased text-gray-300 pb-12 w-full">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 relative overflow-hidden mb-8">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-users class="w-40 h-40 text-primary drop-shadow-[0_0_20px_rgba(197,160,89,1)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight flex items-center gap-4">
                    <span class="text-primary"><x-heroicon-o-users class="w-10 h-10" /></span>
                    Kontakte
                </h1>
                <p class="text-gray-400 mt-2 text-sm font-medium">Das soziale Gedächtnis von Mein Seelenfunke. Freunde, Familie und ihre Eigenschaften.</p>
            </div>
            <div class="relative z-10 bg-gray-950 p-2 rounded-2xl border border-gray-800 shadow-inner flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(99,102,241,0.2)]">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse shadow-[0_0_8px_currentColor]"></span>
                    Memory Sync Active
                </div>
            </div>
        </div>

        @if (session()->has('success'))
            <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-sm flex items-center gap-3 shadow-sm">
                <x-heroicon-o-check-circle class="w-6 h-6" />
                {{ session('success') }}
            </div>
        @endif

        <!-- Split View -->
        <div class="flex flex-col lg:flex-row h-[calc(100vh-18rem)] min-h-[700px] bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">

            <!-- Sidebar List -->
            <div class="w-full lg:w-1/3 xl:w-1/4 bg-gray-950/50 border-b lg:border-b-0 lg:border-r border-gray-800 flex flex-col shrink-0 z-10 shadow-inner">
                <div class="p-6 border-b border-gray-800">
                    <div class="relative group">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Personen durchsuchen..." class="w-full pl-11 pr-4 py-3.5 bg-gray-900 border border-gray-800 rounded-xl text-sm text-white focus:bg-gray-950 focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-500">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-primary transition-colors" />
                    </div>
                </div>

                <div class="p-3 border-b border-gray-800 shrink-0 flex items-center justify-between gap-3">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-widest pl-2">
                        {{ $totalCount }} {{ $totalCount === 1 ? 'Kontakt' : 'Kontakte' }}
                    </span>
                    <button wire:click="createProfile" class="flex-1 py-2 bg-primary/10 text-primary border border-primary/20 rounded-xl text-xs font-bold uppercase tracking-widest hover:bg-primary/20 hover:border-primary/40 transition-all flex items-center justify-center gap-2">
                        <x-heroicon-o-user-plus class="w-4 h-4" /> Neu
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1">
                    @forelse($profiles as $profile)
                        <div wire:click="selectProfile({{ $profile->id }})" role="button" class="cursor-pointer w-full text-left p-4 rounded-2xl transition-all duration-200 border flex items-center gap-3 relative overflow-visible group/btn {{ $activeProfileId == $profile->id ? 'bg-gray-800/80 border-primary/30 shadow-[inset_4px_0_0_rgba(197,160,89,1)]' : 'bg-transparent border-transparent hover:bg-gray-900/50 hover:border-gray-800' }}">
                            <div class="relative shrink-0">
                                @if($profile->avatar_path)
                                    <img src="{{ Storage::url($profile->avatar_path) }}" class="w-10 h-10 rounded-full object-cover border border-primary/30">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-bold text-sm">
                                        {{ substr($profile->first_name, 0, 1) }}{{ $profile->last_name ? substr($profile->last_name, 0, 1) : '' }}
                                    </div>
                                @endif

                                <button wire:click.stop="toggleFavorite({{ $profile->id }})" class="absolute -bottom-1.5 -right-1.5 p-0.5 rounded-full bg-gray-900 border border-gray-800 transition-opacity outline-none {{ $profile->is_favorite ? 'opacity-100' : 'opacity-0 group-hover/btn:opacity-100' }} hover:scale-110 shadow-sm z-20">
                                    @if($profile->is_favorite)
                                        <x-heroicon-s-star class="w-4 h-4 text-amber-400 drop-shadow-[0_0_8px_rgba(251,191,36,0.6)]" />
                                    @else
                                        <x-heroicon-s-star class="w-4 h-4 text-gray-500 hover:text-amber-400 transition-colors" />
                                    @endif
                                </button>
                            </div>
                            <div class="overflow-hidden">
                                <h4 class="text-sm font-bold truncate {{ $activeProfileId == $profile->id ? 'text-white' : 'text-gray-300' }}">{{ $profile->full_name }}</h4>
                                <div class="text-[10px] text-gray-500 flex items-center gap-1.5 mt-1 truncate pr-2">
                                    @if($profile->relation_type)
                                        <span class="text-gray-400">{{ $profile->relation_type }}</span>
                                    @endif
                                    @if($profile->nickname)
                                        <span class="text-indigo-400/80">"{{ $profile->nickname }}"</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 px-4 text-gray-600">
                            <x-heroicon-o-users class="w-10 h-10 mx-auto mb-3 opacity-50" />
                            <p class="text-xs uppercase tracking-widest font-black">Keine Personen gefunden</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Detail / Edit Area -->
            <div class="flex-1 bg-transparent relative overflow-hidden flex flex-col">
                @if($isEditing)
                    <!-- Form Area -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-8 lg:p-12">
                        <div class="flex justify-between items-center mb-8 border-b border-gray-800 pb-4">
                            <h2 class="text-2xl font-serif text-white flex items-center gap-3">
                                <x-heroicon-o-user-circle class="w-8 h-8 text-primary" />
                                {{ $editForm['id'] ? 'Profil bearbeiten' : 'Neues Profil anlegen' }}
                            </h2>
                            <button wire:click="cancelEditing" class="text-gray-500 hover:text-white transition-colors">
                                <x-heroicon-o-x-mark class="w-6 h-6" />
                            </button>
                        </div>

                        <div class="space-y-8">
                            <!-- Profilbild -->
                            <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 flex flex-col items-center justify-center relative group overflow-hidden">
                                <h4 class="text-xs font-black text-primary mb-4 uppercase tracking-widest w-full text-left flex items-center gap-2">
                                    <x-heroicon-o-photo class="w-4 h-4" /> Profilbild
                                </h4>

                                <label for="avatar_upload" class="cursor-pointer flex flex-col items-center justify-center w-32 h-32 rounded-full border-2 border-dashed border-gray-700 bg-gray-950/80 hover:border-primary/50 hover:bg-primary/5 transition-all relative overflow-hidden">
                                    @if ($avatar_upload)
                                        <img src="{{ $avatar_upload->temporaryUrl() }}" class="w-full h-full object-cover">
                                    @elseif ($editForm['avatar_path'])
                                        <img src="{{ Storage::url($editForm['avatar_path']) }}" class="w-full h-full object-cover">
                                    @else
                                        <x-heroicon-o-cloud-arrow-up class="w-8 h-8 text-gray-500 mb-2 group-hover:text-primary transition-colors" />
                                        <span class="text-[10px] uppercase font-bold text-gray-500 group-hover:text-primary transition-colors">Drag & Drop</span>
                                    @endif
                                    <input type="file" wire:model="avatar_upload" id="avatar_upload" class="hidden" accept="image/*">
                                </label>
                                <p class="text-[10px] text-gray-500 mt-4 text-center">Auf den Kreis klicken oder Bild hineinziehen (Drag & Drop).</p>
                                <div wire:loading wire:target="avatar_upload" class="text-xs text-primary mt-2 flex items-center gap-2 font-bold bg-primary/10 px-3 py-1 rounded-full border border-primary/20">
                                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Lädt hoch...
                                </div>
                                @error('avatar_upload') <span class="text-red-500 text-xs mt-2">{{ $message }}</span> @enderror
                            </div>

                            <!-- Stammdaten -->
                            <div>
                                <h4 class="text-xs font-black text-primary mb-4 uppercase tracking-widest flex items-center gap-2">
                                    <x-heroicon-o-identification class="w-4 h-4" /> Stammdaten
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Vorname *</label>
                                        <input type="text" wire:model="editForm.first_name" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                        @error('editForm.first_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Nachname</label>
                                        <input type="text" wire:model="editForm.last_name" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Rufname / Alias</label>
                                        <input type="text" wire:model="editForm.nickname" placeholder="z.B. Mama, Schatz" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Beziehung</label>
                                        <input type="text" wire:model="editForm.relation_type" placeholder="z.B. Bruder, Freundin" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Geburtstag</label>
                                        <input type="date" wire:model="editForm.birthday" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-primary focus:border-primary outline-none transition-all [color-scheme:dark]">
                                    </div>
                                </div>
                            </div>

                            <!-- Kontaktdaten -->
                            <div>
                                <h4 class="text-xs font-black text-indigo-400 mb-4 uppercase tracking-widest flex items-center gap-2">
                                    <x-heroicon-o-at-symbol class="w-4 h-4" /> Kontakt
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">E-Mail Adresse</label>
                                        <input type="email" wire:model="editForm.email" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Telefon / Mobile</label>
                                        <input type="text" wire:model="editForm.phone" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all">
                                    </div>
                                </div>
                            </div>

                            <!-- Adresse -->
                            <div>
                                <h4 class="text-xs font-black text-amber-400 mb-4 uppercase tracking-widest flex items-center gap-2">
                                    <x-heroicon-o-map-pin class="w-4 h-4" /> Adresse
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="md:col-span-4">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Straße & Hausnr.</label>
                                        <input type="text" wire:model="editForm.street" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-amber-500 focus:border-amber-500 outline-none transition-all">
                                    </div>
                                    <div class="md:col-span-1">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">PLZ</label>
                                        <input type="text" wire:model="editForm.postal_code" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-amber-500 focus:border-amber-500 outline-none transition-all">
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Stadt</label>
                                        <input type="text" wire:model="editForm.city" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-amber-500 focus:border-amber-500 outline-none transition-all">
                                    </div>
                                    <div class="md:col-span-4">
                                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1">Land</label>
                                        <input type="text" wire:model="editForm.country" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-white px-4 py-2 focus:ring-amber-500 focus:border-amber-500 outline-none transition-all">
                                    </div>
                                </div>
                            </div>

                            <!-- Links & Socials -->
                            <div>
                                <h4 class="text-xs font-black text-pink-400 mb-4 uppercase tracking-widest flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2"><x-heroicon-o-link class="w-4 h-4" /> Verlinkungen (Socials, Websites)</div>
                                    <button wire:click="addLink" type="button" class="px-3 py-1 bg-pink-500/10 text-pink-400 rounded-lg text-[10px] font-bold uppercase tracking-widest hover:bg-pink-500/20 transition-colors border border-pink-500/20 shadow-sm">
                                        + Hinzufügen
                                    </button>
                                </h4>
                                <div class="space-y-3">
                                    @forelse($editForm['links'] as $index => $link)
                                        <div class="flex items-center gap-3 bg-gray-950 border border-gray-800 p-2 rounded-xl group relative">
                                            <div class="flex-1 grid grid-cols-2 gap-3">
                                                <input type="text" wire:model="editForm.links.{{ $index }}.name" placeholder="Name (z.B. Instagram)" class="w-full bg-gray-900 border border-gray-800 rounded-lg text-white px-3 py-1.5 focus:ring-pink-500 focus:border-pink-500 outline-none transition-all text-sm">
                                                <input type="url" wire:model="editForm.links.{{ $index }}.url" placeholder="URL (https://...)" class="w-full bg-gray-900 border border-gray-800 rounded-lg text-white px-3 py-1.5 focus:ring-pink-500 focus:border-pink-500 outline-none transition-all text-sm">
                                            </div>
                                            <button wire:click="removeLink({{ $index }})" type="button" class="p-2 text-gray-500 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors">
                                                <x-heroicon-o-trash class="w-5 h-5"/>
                                            </button>
                                        </div>
                                    @empty
                                        <div class="text-[10px] text-gray-500 italic p-4 text-center border border-dashed border-gray-800 rounded-xl bg-gray-950">
                                            Keine Links hinterlegt. Klicke auf "Hinzufügen", um Social Media oder Webseiten zu verlinken.
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <!-- KI-Gedächtnis -->
                            <div>
                                <h4 class="text-xs font-black text-emerald-400 mb-4 uppercase tracking-widest flex items-center gap-2">
                                    <x-heroicon-o-cpu-chip class="w-4 h-4" /> Funkira Langzeitgedächtnis
                                </h4>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-emerald-400 mb-1 flex justify-between">
                                            <span>System Instruktionen (Fakten)</span>
                                            <span class="text-gray-500">Nur Lesen für KI</span>
                                        </label>
                                        <p class="text-xs text-gray-500 mb-2">Harte Fakten oder Regeln (z.B. "Tim mag keinen Kaffee.")</p>
                                        <textarea wire:model="editForm.system_instructions" rows="4" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-gray-200 px-4 py-3 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all custom-scrollbar"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] uppercase tracking-widest text-sky-400 mb-1 flex justify-between">
                                            <span>Gelerntes Logbuch</span>
                                            <span class="text-gray-500">Lesen & Schreiben für KI</span>
                                        </label>
                                        <p class="text-xs text-gray-500 mb-2">Hier speichert Funkira selbstständig Vorlieben mit Datum ab.</p>
                                        <textarea wire:model="editForm.ai_learned_facts" rows="6" class="w-full bg-gray-900 border border-sky-900 rounded-lg text-sky-100 font-mono text-xs px-4 py-3 focus:ring-sky-500 focus:border-sky-500 outline-none transition-all custom-scrollbar leading-relaxed"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-4 pt-6 border-t border-gray-800">
                                <button wire:click="saveProfile" class="px-6 py-2.5 bg-primary text-gray-900 rounded-xl font-bold hover:bg-primary/90 hover:shadow-[0_0_15px_rgba(197,160,89,0.4)] transition-all flex items-center gap-2">
                                    <x-heroicon-o-check class="w-5 h-5"/> Speichern
                                </button>
                                <button wire:click="cancelEditing" class="px-6 py-2.5 bg-gray-800 text-white rounded-xl font-bold hover:bg-gray-700 transition-colors">Abbrechen</button>
                            </div>
                        </div>
                    </div>
                @elseif($activeProfile)
                    <!-- Detail View -->
                    <div class="p-8 lg:p-12 pb-6 border-b border-gray-800 bg-gray-900/30 shrink-0 relative bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC4wMykiLz48L3N2Zz4=')]">
                        <div class="absolute top-8 right-8 flex gap-2">
                            <button wire:click="editProfile({{ $activeProfile->id }})" class="p-2 bg-gray-800 border border-gray-700 hover:bg-primary/20 hover:text-primary hover:border-primary/50 text-gray-400 rounded-xl transition-all shadow-inner" title="Bearbeiten">
                                <x-heroicon-o-pencil class="w-5 h-5" />
                            </button>
                            <button wire:click="deleteProfile({{ $activeProfile->id }})" wire:confirm="Dieses Profil und das KI-Gedächtnis wirklich löschen?" class="p-2 bg-gray-800 border border-gray-700 hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/50 text-gray-400 rounded-xl transition-all shadow-inner" title="Löschen">
                                <x-heroicon-o-trash class="w-5 h-5" />
                            </button>
                        </div>

                        <div class="flex items-center gap-6 mb-6" x-data="{ showImage: false }">
                            @if($activeProfile->avatar_path)
                                <button @click="showImage = true" class="relative group outline-none shrink-0" title="Bild vergrößern">
                                    <img src="{{ Storage::url($activeProfile->avatar_path) }}" class="w-20 h-20 rounded-full object-cover border-2 border-primary/50 shadow-[0_0_20px_rgba(197,160,89,0.2)] group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                        <x-heroicon-o-magnifying-glass-plus class="w-6 h-6 text-white drop-shadow-md" />
                                    </div>
                                </button>

                                <!-- Fullscreen Image Modal (Alpine) -->
                                <div x-show="showImage" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-12">
                                    <!-- Backdrop -->
                                    <div x-show="showImage"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 backdrop-blur-none"
                                         x-transition:enter-end="opacity-100 backdrop-blur-md"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 backdrop-blur-md"
                                         x-transition:leave-end="opacity-0 backdrop-blur-none"
                                         @click="showImage = false"
                                         class="absolute inset-0 bg-gray-950/80 cursor-pointer">
                                    </div>
                                    <!-- Modal Image -->
                                    <div x-show="showImage"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                                         class="relative z-10 max-w-4xl max-h-full flex flex-col items-center">
                                        <img src="{{ Storage::url($activeProfile->avatar_path) }}" class="rounded-3xl border border-gray-700 shadow-2xl max-h-[85vh] object-contain bg-gray-900" @click.stop>
                                        <button @click="showImage = false" class="absolute -top-4 -right-4 md:-top-6 md:-right-6 w-10 h-10 md:w-12 md:h-12 bg-gray-800 hover:bg-red-500 text-white rounded-full flex items-center justify-center border-2 border-gray-700 hover:border-red-400 transition-colors shadow-xl">
                                            <x-heroicon-o-x-mark class="w-6 h-6" />
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="w-20 h-20 shrink-0 rounded-full bg-gradient-to-br from-indigo-500/30 to-purple-500/30 border border-indigo-500/40 flex items-center justify-center text-indigo-300 font-bold text-3xl shadow-[0_0_20px_rgba(99,102,241,0.2)]">
                                    {{ substr($activeProfile->first_name, 0, 1) }}{{ $activeProfile->last_name ? substr($activeProfile->last_name, 0, 1) : '' }}
                                </div>
                            @endif
                            <div>
                                <h2 class="text-3xl lg:text-4xl font-serif font-bold text-white tracking-tight leading-none mb-2">
                                    {{ $activeProfile->full_name }}
                                </h2>
                                <div class="flex items-center gap-3 text-sm">
                                    @if($activeProfile->relation_type)
                                        <span class="px-2 py-0.5 rounded-full bg-gray-800 border border-gray-700 text-gray-300 font-medium">
                                            {{ $activeProfile->relation_type }}
                                        </span>
                                    @endif
                                    @if($activeProfile->nickname)
                                        <span class="text-indigo-400 italic">"{{ $activeProfile->nickname }}"</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar p-8 lg:p-12 space-y-8 bg-gray-950/20">
                        <!-- Info Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @if($activeProfile->phone)
                            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-gray-800 text-gray-400 flex items-center justify-center"><x-heroicon-o-phone class="w-5 h-5" /></div>
                                <div><p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Telefon</p><p class="text-gray-300">{{ $activeProfile->phone }}</p></div>
                            </div>
                            @endif
                            @if($activeProfile->email)
                            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-gray-800 text-gray-400 flex items-center justify-center"><x-heroicon-o-envelope class="w-5 h-5" /></div>
                                <div><p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">E-Mail</p><p class="text-gray-300 truncate max-w-[150px]">{{ $activeProfile->email }}</p></div>
                            </div>
                            @endif
                            @if($activeProfile->birthday)
                            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-gray-800 text-gray-400 flex items-center justify-center"><x-heroicon-o-gift class="w-5 h-5" /></div>
                                <div><p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Geburtstag</p><p class="text-gray-300">{{ $activeProfile->birthday->format('d.m.Y') }}</p></div>
                            </div>
                            @endif

                            @if($activeProfile->street || $activeProfile->city)
                            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-4 flex items-center gap-4 lg:col-span-2">
                                <div class="w-10 h-10 rounded-full bg-gray-800 text-gray-400 flex items-center justify-center shrink-0"><x-heroicon-o-map-pin class="w-5 h-5" /></div>
                                <div>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-widest font-bold">Wohnort</p>
                                    <p class="text-gray-300 text-sm">
                                        {{ $activeProfile->street }}<br>
                                        {{ $activeProfile->postal_code }} {{ $activeProfile->city }}
                                        @if($activeProfile->country)<span class="text-gray-500">, {{ $activeProfile->country }}</span>@endif
                                    </p>
                                </div>
                            </div>
                            @endif
                        </div>

                        <!-- Links & Socials -->
                        @if(!empty($activeProfile->links) && count($activeProfile->links) > 0)
                        <div>
                            <h3 class="text-xs font-black text-pink-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <x-heroicon-o-link class="w-4 h-4" /> Verlinkungen
                            </h3>
                            <div class="flex flex-wrap gap-3">
                                @foreach($activeProfile->links as $link)
                                    @if(!empty($link['name']) && !empty($link['url']))
                                        <a href="{{ $link['url'] }}" target="_blank" class="px-4 py-2 bg-pink-500/10 border border-pink-500/20 text-pink-300 hover:bg-pink-500/20 hover:border-pink-500/40 hover:text-pink-200 hover:shadow-[0_0_15px_rgba(236,72,153,0.2)] transition-all rounded-full text-xs font-bold flex items-center gap-2">
                                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5"/> {{ $link['name'] }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Instruction Blocks -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div class="bg-gray-900/50 border border-emerald-900/30 rounded-3xl p-6 relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-500/5 rounded-full blur-3xl"></div>
                                <h3 class="text-xs font-black text-emerald-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <x-heroicon-o-shield-check class="w-5 h-5" /> Feste System-Regeln
                                </h3>
                                @if($activeProfile->system_instructions)
                                    <div class="text-gray-300 leading-relaxed text-sm whitespace-pre-wrap">{{ $activeProfile->system_instructions }}</div>
                                @else
                                    <p class="text-gray-600 italic text-sm">Keine speziellen Fest-Regeln definiert.</p>
                                @endif
                            </div>

                            <div class="bg-gray-950 border border-sky-900/40 rounded-3xl p-6 relative overflow-hidden">
                                <div class="absolute top-0 right-0 w-32 h-32 bg-sky-500/5 rounded-full blur-3xl"></div>
                                <h3 class="text-xs font-black text-sky-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <x-heroicon-o-circle-stack class="w-5 h-5" /> KI Logbuch & Erinnerungen
                                </h3>
                                @if($activeProfile->ai_learned_facts)
                                    <div class="text-sky-200/80 font-mono leading-relaxed text-sm whitespace-pre-wrap">{{ $activeProfile->ai_learned_facts }}</div>
                                @else
                                    <p class="text-gray-600 italic text-sm">Noch keine Fakten durch die KI aufgezeichnet.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-500 p-10 text-center">
                        <div class="w-24 h-24 bg-gray-950 border border-gray-800 rounded-full flex items-center justify-center shadow-inner mb-6 relative group">
                            <div class="absolute inset-0 bg-primary/20 rounded-full blur-xl opacity-0 group-hover:opacity-100 transition-opacity duration-1000"></div>
                            <x-heroicon-o-user-group class="w-10 h-10 text-gray-700 relative z-10 transition-colors duration-1000 group-hover:text-primary" />
                        </div>
                        <h3 class="text-xl font-serif font-bold text-white mb-2">Kontakte</h3>
                        <p class="text-sm font-medium">Wähle links eine Person aus oder füge eine neue hinzu,<br>damit Funkira lernt, wer dir wichtig ist.</p>
                    </div>
                @endif
            </div>
        </div>

        <style>
            .custom-scrollbar::-webkit-scrollbar { width: 6px; }
            .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #C5A059; }
        </style>
    </div>
</div>
