<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-white flex items-center gap-3">
                <span class="text-primary"><i class="bi bi-people"></i></span>
                Personen & Familie
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                Verwalte Freunde, Familie und Bekannte für das Langzeitgedächtnis der KI.
            </p>
        </div>

        <div class="flex items-center gap-4 w-full sm:w-auto">
            <div class="relative w-full sm:w-64">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input wire:model.live.debounce.300ms="search" type="text" class="w-full pl-10 pr-4 py-2 bg-gray-900 border border-gray-700 rounded-xl text-sm focus:ring-primary focus:border-primary text-gray-200 placeholder-gray-500" placeholder="Suchen...">
            </div>
            
            <button wire:click="createProfile" class="flex items-center gap-2 px-4 py-2 bg-primary/20 hover:bg-primary/30 text-primary border border-primary/50 rounded-xl font-medium transition-colors shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Neu
            </button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 text-sm flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    <!-- Profiles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($profiles as $profile)
            <div class="bg-gray-900/50 backdrop-blur-sm border border-gray-800 rounded-2xl p-5 hover:border-gray-700 transition-colors group flex flex-col h-full">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500/20 to-purple-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-bold text-lg">
                            {{ substr($profile->first_name, 0, 1) }}{{ $profile->last_name ? substr($profile->last_name, 0, 1) : '' }}
                        </div>
                        <div>
                            <h3 class="text-white font-medium text-lg">{{ $profile->full_name }}</h3>
                            <div class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                @if($profile->nickname)
                                    <span class="text-indigo-400/80">"{{ $profile->nickname }}"</span>
                                @endif
                                @if($profile->relation_type)
                                    <span class="px-2 py-0.5 rounded-full bg-gray-800 text-gray-400">{{ $profile->relation_type }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 mb-6 flex-1 text-sm">
                    @if($profile->email)
                        <div class="flex items-center gap-2 text-gray-400">
                            <i class="bi bi-envelope"></i> {{ $profile->email }}
                        </div>
                    @endif
                    @if($profile->phone)
                        <div class="flex items-center gap-2 text-gray-400">
                            <i class="bi bi-telephone"></i> {{ $profile->phone }}
                        </div>
                    @endif
                    @if($profile->birthday)
                        <div class="flex items-center gap-2 text-gray-400">
                            <i class="bi bi-gift"></i> {{ $profile->birthday->format('d.m.Y') }}
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-gray-800/50 mt-auto">
                    <button wire:click="editProfile({{ $profile->id }})" class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors" title="Bearbeiten">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button wire:click="deleteProfile({{ $profile->id }})" wire:confirm="Dieses Profil und das KI-Gedächtnis wirklich löschen?" class="p-2 text-red-400/70 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors" title="Löschen">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-gray-500 bg-gray-900/30 rounded-2xl border border-gray-800 border-dashed">
                <i class="bi bi-people text-4xl mb-3 block text-gray-600"></i>
                <p>Noch keine Personen angelegt.</p>
                <button wire:click="createProfile" class="mt-4 text-primary hover:text-primary/80 transition-colors">
                    Erstes Profil erstellen
                </button>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $profiles->links() }}
    </div>

    <!-- Edit/Create Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-[100] flex items-center justify-center pointer-events-auto" x-data="{}" x-init="document.body.style.overflow = 'hidden'" @destroyed="document.body.style.overflow = 'auto'">
            <div class="fixed inset-0 bg-gray-950/80 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            
            <div class="relative bg-gray-900 border border-gray-700/50 shadow-2xl rounded-2xl max-w-4xl w-full m-4 max-h-[90vh] flex flex-col z-10 overflow-hidden">
                
                <div class="px-6 py-4 border-b border-gray-800 bg-gray-800/20 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white">
                        {{ $isEditing ? 'Profil bearbeiten' : 'Neues Profil anlegen' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-white">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                    <form wire:submit="saveProfile" class="space-y-8">
                        
                        <!-- Stammdaten -->
                        <div>
                            <h4 class="text-sm font-bold text-primary mb-4 uppercase tracking-wider flex items-center gap-2">
                                <i class="bi bi-person-badge"></i> Stammdaten
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Vorname *</label>
                                    <input type="text" wire:model="first_name" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary text-white" required>
                                    @error('first_name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Nachname</label>
                                    <input type="text" wire:model="last_name" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Rufname / Alias (z.B. "Mama", "Schatz")</label>
                                    <input type="text" wire:model="nickname" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Beziehung (z.B. Bruder, Freundin)</label>
                                    <input type="text" wire:model="relation_type" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Geburtstag</label>
                                    <input type="date" wire:model="birthday" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary text-white [color-scheme:dark]">
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-800">

                        <!-- Kontaktdaten -->
                        <div>
                            <h4 class="text-sm font-bold text-indigo-400 mb-4 uppercase tracking-wider flex items-center gap-2">
                                <i class="bi bi-at"></i> Automatisierungen / Kontakt
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">E-Mail Adresse</label>
                                    <input type="email" wire:model="email" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary text-white placeholder-gray-600" placeholder="Für KI-gesteuerten Mailversand">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">Telefon / Mobile</label>
                                    <input type="text" wire:model="phone" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-2 focus:ring-primary focus:border-primary text-white placeholder-gray-600" placeholder="Für SMS/WhatsApp Agenten">
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-800">

                        <!-- KI-Gedächtnis -->
                        <div>
                            <h4 class="text-sm font-bold text-emerald-400 mb-4 uppercase tracking-wider flex items-center gap-2">
                                <i class="bi bi-robot"></i> Funkira Langzeitgedächtnis
                            </h4>
                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-emerald-400 mb-1 flex justify-between">
                                        <span>System Instruktionen (von dir)</span>
                                        <span class="text-xs text-gray-500">Nur Lesen für KI</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mb-2">Harte Fakten oder Regeln (z.B. "Tim mag keinen Kaffee, biete ihm nur Tee an. Sprich ihn niemals auf Fußball an.")</p>
                                    <textarea wire:model="system_instructions" rows="4" class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-3 focus:ring-emerald-500 focus:border-emerald-500 text-gray-200"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-sky-400 mb-1 flex justify-between">
                                        <span>Gelerntes KI-Logbuch (Fakten)</span>
                                        <span class="text-xs text-gray-500">Lesen & Schreiben für KI</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mb-2">Hier speichert Funkira selbstständig Vorlieben und Fakten mit Datum ab, die sie in Chats aufschnappt.</p>
                                    <textarea wire:model="ai_learned_facts" rows="6" class="w-full bg-gray-900 border border-sky-900 rounded-lg px-4 py-3 focus:ring-sky-500 focus:border-sky-500 text-gray-300 font-mono text-sm leading-relaxed"></textarea>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-800 bg-gray-800/20 flex items-center justify-end gap-3">
                    <button wire:click="$set('showModal', false)" type="button" class="px-4 py-2 text-gray-400 hover:text-white transition-colors">
                        Abbrechen
                    </button>
                    <button wire:click="saveProfile" type="button" class="px-6 py-2 bg-primary hover:bg-primary/90 text-gray-900 font-bold rounded-lg transition-colors">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
