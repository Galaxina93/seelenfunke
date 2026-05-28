<div class="space-y-6 md:space-y-8 pb-20 font-sans antialiased text-gray-300" style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">

    {{-- Table Area --}}
        {{-- Header --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 relative overflow-hidden animate-fade-in-up">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none"><x-heroicon-o-sparkles class="w-40 h-40 text-[var(--theme-color)] drop-shadow-[0_0_20px_var(--theme-color)1)]" /></div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight">
                    {{ $showArchive ? 'Archivierte Seelen' : 'Benutzer-Zentrale' }}
                </h1>
                <p class="text-gray-400 mt-2 text-sm font-medium">Verwalten Sie Ihre Community mit Präzision und Übersicht.</p>
            </div>

            <div class="flex items-center gap-3 relative z-10">
                @if(!$showArchive && !$isCreating && !$editingId)
                    <button wire:click="startCreate" class="inline-flex items-center px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-[var(--theme-color)] text-gray-900 shadow-[0_0_20px_var(--theme-color)0.3)] hover:bg-[var(--theme-color)]-dark hover:text-white hover:scale-[1.02]">
                        <x-heroicon-o-plus class="w-4 h-4 mr-2" /> Neuen Benutzer anlegen
                    </button>
                @endif
                @if(!$isCreating && !$editingId)
                    <button wire:click="toggleArchive" class="inline-flex items-center px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $showArchive ? 'bg-[var(--theme-color)] text-gray-900 shadow-[0_0_20px_var(--theme-color)0.3)] hover:bg-[var(--theme-color)]-dark hover:text-white hover:scale-[1.02]' : 'bg-gray-950 text-gray-400 hover:text-white border border-gray-800 shadow-inner' }}">
                        <x-heroicon-o-archive-box class="w-4 h-4 mr-2" />
                        {{ $showArchive ? 'Aktive Liste' : 'Archiv' }}
                    </button>
                @endif
            </div>
        </div>

        {{-- Success Message --}}
        @if (session()->has('message'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="mt-4 bg-emerald-500/10 border-l-4 border-emerald-500 p-4 text-emerald-400 shadow-inner rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
                <x-heroicon-s-check-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        @if($isCreating || $editingId)
            {{-- FULL PAGE INLINE EDITOR --}}
            <div class="bg-gray-900/80 backdrop-blur-md p-8 sm:p-10 rounded-[2.5rem] border border-gray-800 shadow-2xl relative overflow-hidden animate-fade-in mt-6">
                {{-- Form Header --}}
                <div class="flex justify-between items-center mb-8 border-b border-gray-800 pb-6">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-white tracking-wide">
                            {{ $isCreating ? 'Neues Seelenlicht erschaffen' : "Benutzerprofil bearbeiten" }}
                        </h2>
                        <p class="text-xs text-gray-500 font-medium mt-1">
                            {{ $isCreating ? 'Wähle die Rolle und fülle die grundlegenden Stammdaten aus.' : 'Passe die Identitätsdaten und Zugriffsrechte dieses Begleiters an.' }}
                        </p>
                    </div>
                    <button wire:click="cancelEdit" class="p-2.5 text-gray-500 hover:text-white bg-gray-950 border border-gray-800 rounded-xl transition-all shadow-inner hover:bg-red-500/20 hover:border-red-500/50 hover:text-red-400 group">
                        <x-heroicon-m-x-mark class="w-6 h-6 group-hover:scale-110 transition-transform" />
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                    {{-- Left Column: Account & Security --}}
                    <div class="space-y-6">
                        <div class="flex items-center gap-3 text-[var(--theme-color)] mb-4 pb-2 border-b border-gray-800/50">
                            <x-heroicon-m-shield-check class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                            <h3 class="text-[10px] font-black uppercase tracking-widest">Account & Sicherheit</h3>
                        </div>

                        @if($isCreating)
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">System-Rolle</label>
                                <select wire:model.live="createType" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner p-4 outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all appearance-none cursor-pointer">
                                    <option value="customer">Kunde</option>
                                    <option value="employee">Mitarbeiter</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        @else
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">System-Rolle</label>
                                <div class="w-full text-sm border-gray-800 rounded-xl bg-gray-950/50 text-gray-500 px-4 py-4 border inline-flex items-center gap-2 cursor-not-allowed">
                                    <x-heroicon-m-lock-closed class="w-4 h-4" />
                                    @if($editingType === 'admin') Administrator @elseif($editingType === 'employee') Mitarbeiter @else Kunde @endif
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Vorname *</label>
                                <input type="text" wire:model="formData.first_name" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Nachname *</label>
                                <input type="text" wire:model="formData.last_name" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">E-Mail Adresse *</label>
                            <input type="email" wire:model="formData.email" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Passwort {{ $isCreating ? '*' : '(Optional - Leer für kein Reset)' }}</label>
                            <div x-data="{ showPw: false }" class="relative">
                                <input :type="showPw ? 'text' : 'password'" wire:model="formData.password" placeholder="{{ $isCreating ? 'Mindestens 8 Zeichen' : 'Neues Passwort eingeben...' }}" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 pr-12 outline-none placeholder-gray-600">
                                <button type="button" @click="showPw = !showPw" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-[var(--theme-color)] transition-colors focus:outline-none">
                                    <x-heroicon-o-eye x-show="!showPw" class="w-5 h-5" />
                                    <x-heroicon-o-eye-slash x-show="showPw" class="w-5 h-5" style="display: none;" />
                                </button>
                            </div>
                        </div>

                        <label class="flex items-center gap-4 cursor-pointer group bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner mt-4 hover:border-emerald-500/50 transition-colors">
                            <div class="relative flex items-center shrink-0">
                                <input type="checkbox" wire:model="formData.is_verified" class="peer sr-only">
                                <div class="w-6 h-6 bg-black border-2 border-gray-700 rounded transition-all peer-checked:bg-emerald-500 peer-checked:border-emerald-500"></div>
                                <svg class="absolute w-4 h-4 left-1 top-1 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-gray-300 group-hover:text-white transition-colors">E-Mail verifiziert</span>
                                <span class="text-[10px] text-gray-500 font-medium">Gewährt direkten Zugriff auf das System</span>
                            </div>
                        </label>
                    </div>

                    {{-- Right Column: Address & Details --}}
                    <div class="space-y-6">
                        <div class="flex items-center gap-3 text-[var(--theme-color)] mb-4 pb-2 border-b border-gray-800/50">
                            <x-heroicon-m-map-pin class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                            <h3 class="text-[10px] font-black uppercase tracking-widest">Standort & Profil</h3>
                        </div>

                        <div class="grid grid-cols-2 gap-4 bg-gray-950 p-1.5 rounded-xl shadow-inner border border-gray-800">
                            <button wire:click="$set('formData.customer_type', 'private')" type="button" @class(['py-3.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all', 'bg-gray-800 text-[var(--theme-color)] shadow-[0_0_10px_rgba(0,0,0,0.5)] border border-gray-700' => $formData['customer_type'] === 'private', 'text-gray-500 hover:text-gray-300 hover:bg-gray-800/50' => $formData['customer_type'] !== 'private'])>
                                Privatperson
                            </button>
                            <button wire:click="$set('formData.customer_type', 'business')" type="button" @class(['py-3.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all', 'bg-gray-800 text-[var(--theme-color)] shadow-[0_0_10px_rgba(0,0,0,0.5)] border border-gray-700' => $formData['customer_type'] === 'business', 'text-gray-500 hover:text-gray-300 hover:bg-gray-800/50' => $formData['customer_type'] !== 'business'])>
                                Gewerblich (B2B)
                            </button>
                        </div>

                        @if($formData['customer_type'] === 'business')
                            <div class="grid grid-cols-2 gap-4 animate-fade-in-up">
                                <div class="space-y-2">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Firmenname</label>
                                    <input type="text" wire:model="formData.company_name" placeholder="GmbH, UG..." class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">USt-IdNr.</label>
                                    <input type="text" wire:model="formData.vat_id" placeholder="DE..." class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none font-mono uppercase">
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-4">
                            <div class="space-y-2 w-2/3">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Straße</label>
                                <input type="text" wire:model="formData.street" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                            <div class="space-y-2 w-1/3">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Nr.</label>
                                <input type="text" wire:model="formData.house_number" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="space-y-2 w-1/3">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">PLZ</label>
                                <input type="text" wire:model="formData.postal" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                            <div class="space-y-2 w-2/3">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Stadt</label>
                                <input type="text" wire:model="formData.city" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Telefon</label>
                            <input type="text" wire:model="formData.phone_number" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Interne Notiz (Nur für Admins)</label>
                            <textarea wire:model="formData.internal_note" rows="3" placeholder="Besonderheiten, VIP Status..." class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none resize-none"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Action Footer --}}
                <div class="mt-10 pt-8 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-6">
                    @if($errors->any())
                        <div class="text-red-400 text-[10px] font-bold bg-red-500/10 px-4 py-3 rounded-xl border border-red-500/20 w-full sm:w-auto flex items-center gap-3">
                            <x-heroicon-m-exclamation-triangle class="w-5 h-5 flex-shrink-0" />
                            <span>Bitte behebe die rot markierten Fehler, bevor du speicherst.</span>
                        </div>
                    @else
                        <div></div> {{-- Spacer --}}
                    @endif

                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <button wire:click="cancelEdit" class="flex-1 sm:flex-none px-8 py-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-gray-950 border border-gray-700 text-gray-400 hover:text-white hover:bg-gray-800 shadow-inner hover:-translate-x-1">
                            Zurück zur Liste
                        </button>
                        <button wire:click="{{ $isCreating ? 'saveNewUser' : 'saveInline' }}" class="flex-1 sm:flex-none px-8 py-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-emerald-500 text-gray-900 shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:bg-emerald-400 hover:scale-[1.02] flex items-center justify-center gap-2">
                            <x-heroicon-m-check class="w-5 h-5 drop-shadow-[0_0_4px_rgba(0,0,0,0.5)]" />
                            {{ $isCreating ? 'Benutzer erstellen' : 'Änderungen speichern' }}
                        </button>
                    </div>
                </div>
            </div>
        @else
            {{-- Filter & Search --}}
            <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 bg-gray-900/80 backdrop-blur-md p-3 sm:p-4 rounded-[2rem] border border-gray-800 shadow-2xl items-center animate-fade-in-up">
                <div class="md:col-span-3 relative group">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Kundennummer, Name, Firma, E-Mail oder Stadt suchen..."
                           class="w-full pl-12 pr-4 py-4 bg-gray-950 border border-gray-800 rounded-[1.5rem] focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-inner transition-all text-white placeholder-gray-600 outline-none text-sm">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                        <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-600 group-focus-within:text-[var(--theme-color)] transition-colors" />
                    </div>
                </div>

                <select wire:model.live="filterRole" class="md:col-span-1 bg-gray-950 border border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-colors cursor-pointer outline-none appearance-none">
                    <option value="all" class="bg-gray-900 text-white">Alle Rollen</option>
                    <option value="admin" class="bg-gray-900 text-white">Administratoren</option>
                    <option value="employee" class="bg-gray-900 text-white">Mitarbeiter</option>
                    <option value="customer" class="bg-gray-900 text-white">Kunden</option>
                </select>
            </div>

            {{-- Table --}}
            <div class="mt-6 bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full relative animate-fade-in-up">
                <div class="overflow-x-auto w-full custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                        <tr class="bg-gray-950/80 border-b border-gray-800 text-[10px] font-black text-gray-500 uppercase tracking-widest shadow-inner">
                            <th wire:click="sortBy('identity')" class="px-6 sm:px-8 py-6 w-[25%] cursor-pointer hover:text-white transition-colors select-none">
                                <div class="flex items-center gap-1">
                                    Identität
                                    @if($sortField === 'identity')
                                        @if($sortDirection === 'asc')
                                            <x-heroicon-m-chevron-up class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @else
                                            <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th wire:click="sortBy('status')" class="px-6 sm:px-8 py-6 w-[20%] cursor-pointer hover:text-white transition-colors select-none">
                                <div class="flex items-center gap-1">
                                    Status & Art
                                    @if($sortField === 'status')
                                        @if($sortDirection === 'asc')
                                            <x-heroicon-m-chevron-up class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @else
                                            <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th wire:click="sortBy('location')" class="px-6 sm:px-8 py-6 w-[20%] cursor-pointer hover:text-white transition-colors select-none">
                                <div class="flex items-center gap-1">
                                    Standort & Kontakt
                                    @if($sortField === 'location')
                                        @if($sortDirection === 'asc')
                                            <x-heroicon-m-chevron-up class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @else
                                            <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th wire:click="sortBy('ip')" class="px-6 sm:px-8 py-6 w-[10%] cursor-pointer hover:text-white transition-colors select-none">
                                <div class="flex items-center gap-1">
                                    IP-Adresse
                                    @if($sortField === 'ip')
                                        @if($sortDirection === 'asc')
                                            <x-heroicon-m-chevron-up class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @else
                                            <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th wire:click="sortBy('login')" class="px-6 sm:px-8 py-6 w-[15%] cursor-pointer hover:text-white transition-colors select-none">
                                <div class="flex items-center gap-1">
                                    Intern / Login
                                    @if($sortField === 'login')
                                        @if($sortDirection === 'asc')
                                            <x-heroicon-m-chevron-up class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @else
                                            <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                        @endif
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 sm:px-8 py-6 w-[10%] text-right">Aktionen</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                        @forelse($users as $user)
                            @php
                                $profile = $user['profile'] ?? [];
                            @endphp
                            <tr class="transition-all duration-300 group hover:bg-gray-800/40 cursor-default">
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-[1.2rem] shrink-0 bg-gray-950 border border-gray-800 flex items-center justify-center text-[var(--theme-color)] font-serif text-xl font-bold shadow-[inset_0_-2px_10px_rgba(0,0,0,0.5),_0_0_15px_var(--theme-color)0.1)] group-hover:shadow-[inset_0_-2px_10px_rgba(0,0,0,0.5),_0_0_20px_var(--theme-color)0.3)] transition-shadow">
                                            {{ substr($user['first_name'], 0, 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-white text-sm tracking-wide truncate">{{ $user['first_name'] }} {{ $user['last_name'] }}</div>
                                            <div class="text-[11px] text-gray-500 font-medium tracking-wide mt-0.5 group-hover:text-[var(--theme-color)] transition-colors truncate">{{ $user['email'] }}</div>
                                            @if($user['user_type'] === 'customer')
                                                <div class="mt-1 flex items-center gap-1.5">
                                                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-gray-900 px-1.5 py-0.5 rounded border border-gray-800 shadow-inner">KDN:</span>
                                                    <span class="text-[10px] text-[var(--theme-color)] font-mono font-black tracking-widest">{{ strtoupper(explode('-', $user['id'])[0]) }}</span>
                                                </div>
                                            @endif
                                            @if(($profile['is_business'] ?? false) && !empty($profile['company_name']))
                                                <div class="mt-1 flex flex-col gap-0.5">
                                                    <div class="flex items-center gap-1 text-[10px] text-amber-400 font-bold uppercase tracking-wider truncate">
                                                        <x-heroicon-o-building-office class="w-3.5 h-3.5 shrink-0" />
                                                        {{ $profile['company_name'] }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="space-y-3">
                                        <span @class(['px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border shadow-inner inline-block mt-1',
                                            'bg-purple-500/10 text-purple-400 border-purple-500/30' => $user['user_type'] === 'admin',
                                            'bg-indigo-500/10 text-indigo-400 border-indigo-500/30' => $user['user_type'] === 'employee',
                                            'bg-gray-800 text-gray-400 border-gray-700' => $user['user_type'] === 'customer'])>
                                            @if($user['user_type'] === 'admin') Administrator @elseif($user['user_type'] === 'employee') Mitarbeiter @else Kunde @endif
                                        </span>
                                        <div class="flex items-center text-[9px] font-black uppercase tracking-widest">
                                            @if(isset($user['deleted_at']))
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-2 shadow-[0_0_8px_currentColor] animate-pulse"></span>
                                                <span class="text-red-400">Archiviert</span>
                                            @elseif(!empty($profile['email_verified_at']))
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2 shadow-[0_0_8px_currentColor]"></span>
                                                <span class="text-emerald-400">Verifiziert</span>
                                            @else
                                                <div class="flex flex-col gap-1.5">
                                                    <div class="flex items-center">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-orange-500 mr-2 shadow-[0_0_8px_currentColor]"></span>
                                                        <span class="text-orange-400">Unverifiziert</span>
                                                    </div>
                                                    @if(isset($user['created_at']))
                                                        <div class="flex flex-col gap-1 w-full mt-1.5" x-data="{
                                                            createdTs: {{ \Carbon\Carbon::parse($user['created_at'])->timestamp * 1000 }},
                                                            total: 24 * 60 * 60 * 1000,
                                                            countdown: '',
                                                            progress: 0,
                                                            init() { setInterval(() => this.update(), 1000); this.update(); },
                                                            update() {
                                                                let deleteTs = this.createdTs + this.total;
                                                                let diff = Math.max(0, deleteTs - Date.now());
                                                                if (diff === 0) { this.countdown = 'Sofort'; this.progress = 100; return; }
                                                                
                                                                let h = Math.floor(diff / 3600000);
                                                                let m = Math.floor((diff % 3600000) / 60000);
                                                                let s = Math.floor((diff % 60000) / 1000);
                                                                let parts = [];
                                                                if (h > 0) parts.push(h + 'h');
                                                                if (m > 0 || h > 0) parts.push(m + 'm');
                                                                parts.push(s + 's');
                                                                this.countdown = parts.join(' ');
                                                                
                                                                let elapsed = Date.now() - this.createdTs;
                                                                this.progress = Math.min(100, Math.max(0, (elapsed / this.total) * 100));
                                                            }
                                                        }">
                                                            <span class="text-[9px] text-gray-500 font-bold normal-case tracking-normal">Wird gelöscht in: <span class="text-[color:var(--theme-color)] font-mono font-bold" x-text="countdown"></span></span>
                                                            <div class="w-full max-w-[120px] h-1 bg-gray-950/60 rounded-full overflow-hidden border border-gray-800/40 shadow-inner">
                                                                <div class="h-full bg-gradient-to-r from-[color:var(--theme-color)] to-[color:var(--theme-color-50)] transition-all duration-1000 ease-linear shadow-[0_0_10px_var(--theme-color-50)]" :style="`width: ${progress}%`"></div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="text-xs space-y-1.5 mt-1">
                                        <div class="flex items-center gap-2 text-gray-300">
                                            <x-heroicon-m-map-pin class="w-4 h-4 text-[var(--theme-color)] drop-shadow-[0_0_8px_currentColor] shrink-0" />
                                            <span class="font-bold tracking-wide truncate">{{ $profile['city'] ?? 'Ort unbekannt' }}</span>
                                        </div>
                                        <div class="text-[11px] text-gray-500 pl-6 leading-relaxed font-medium">
                                            {{ $profile['street'] ?? '-' }} {{ $profile['house_number'] ?? '' }}<br>
                                            {{ mb_strlen($profile['phone_number'] ?? '') > 0 ? $profile['phone_number'] : '- Keine Nummer -' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="text-xs font-mono font-bold text-gray-400 mt-1 select-all" title="Letzte bekannte IP">
                                        @if(!empty($profile['last_ip']))
                                            <div class="flex items-center gap-1.5 text-gray-300">
                                                <x-heroicon-o-computer-desktop class="w-4 h-4 text-gray-500 shrink-0" />
                                                <span>{{ $profile['last_ip'] }}</span>
                                            </div>
                                        @else
                                            <span class="text-gray-600 text-[10px] uppercase font-bold tracking-wide">-</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    @if($user['last_seen'])
                                        <div class="flex flex-col mb-3 mt-1">
                                            <span class="text-xs font-bold text-white">{{ \Carbon\Carbon::parse($user['last_seen'])->diffForHumans() }}</span>
                                            <span class="text-[9px] text-gray-500 uppercase tracking-widest font-black mt-1">{{ \Carbon\Carbon::parse($user['last_seen'])->format('d.m.Y H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="text-[10px] text-gray-600 block mb-3 mt-1 uppercase tracking-widest font-bold">Nicht eingeloggt</span>
                                    @endif

                                    @if(!empty($profile['internal_note']))
                                        <div class="inline-flex items-center gap-1.5 bg-[var(--theme-color)]/10 text-[var(--theme-color)] border border-[var(--theme-color)]/20 px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest" title="{{ $profile['internal_note'] }}">
                                            <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                                            Notiz
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 sm:px-8 py-4 text-right align-top">
                                    <div class="flex justify-end gap-2 mt-1">
                                        @if(!$showArchive)
                                            <div class="opacity-100 xl:opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                                <button wire:click="startEdit('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-950 border border-gray-800 text-gray-500 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)]/30 rounded-xl transition-all shadow-inner" title="Bearbeiten">
                                                    <x-heroicon-m-pencil-square class="w-4 h-4" />
                                                </button>
                                                <button wire:click="archiveUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-950 border border-gray-800 text-gray-500 hover:text-orange-400 hover:border-orange-500/30 rounded-xl transition-all shadow-inner" title="Archivieren">
                                                    <x-heroicon-m-archive-box class="w-4 h-4" />
                                                </button>
                                            </div>
                                        @else
                                            <button wire:click="restoreUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-950 border border-gray-800 text-gray-500 hover:text-emerald-400 hover:border-emerald-500/30 rounded-xl transition-all shadow-inner" title="Wiederherstellen">
                                                <x-heroicon-m-arrow-path class="w-4 h-4" />
                                            </button>
                                            <button wire:confirm="Sicher, dass dieser Benutzer unwiderruflich gelöscht werden soll?" wire:click="forceDelete('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-950 border border-gray-800 text-gray-500 hover:text-red-400 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Permanent löschen">
                                                <x-heroicon-m-trash class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-8 py-32 text-center text-gray-500 font-serif text-xl italic">Keine Begleiter gefunden...</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($users->hasPages()) <div class="px-8 py-6 bg-gray-900/30 border-t border-gray-800">{{ $users->links() }}</div> @endif
            </div>
        @endif
</div>
