<div class="space-y-6 md:space-y-8 pb-20 font-sans antialiased text-gray-300" style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">

    {{-- Table Area --}}
        {{-- Header --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-hidden animate-fade-in-up">
            <div class="hidden dark:block absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none"><x-heroicon-o-sparkles class="w-40 h-40 text-[var(--theme-color)] drop-shadow-[0_0_20px_var(--theme-color)1)]" /></div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                    {{ $showArchive ? 'Archivierte Seelen' : 'Benutzer-Zentrale' }}
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm font-medium">Verwalten Sie Ihre Community mit Präzision und Übersicht.</p>
            </div>

            <div class="flex items-center gap-3 relative z-10">
                @if(!$showArchive && !$isCreating && !$editingId)
                    <button wire:click="startCreate" class="inline-flex items-center px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-[var(--theme-color)] text-gray-900 shadow-[0_0_20px_var(--theme-color)0.3)] hover:bg-[var(--theme-color)]-dark hover:text-white hover:scale-[1.02]">
                        <x-heroicon-o-plus class="w-4 h-4 mr-2" /> Neuen Benutzer anlegen
                    </button>
                @endif
                @if(!$isCreating && !$editingId)
                    <button wire:click="toggleArchive" class="inline-flex items-center px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $showArchive ? 'bg-[var(--theme-color)] text-gray-900 shadow-sm dark:shadow-[0_0_20px_var(--theme-color)0.3)] hover:bg-[var(--theme-color)]-dark hover:text-white hover:scale-[1.02]' : 'bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner' }}">
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

        {{-- Error Message --}}
        @if (session()->has('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mt-4 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-600 dark:text-red-400 shadow-inner rounded-r-xl flex items-center gap-3 animate-fade-in text-sm font-bold">
                <x-heroicon-s-exclamation-circle class="w-5 h-5 drop-shadow-[0_0_8px_currentColor]" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($isCreating || $editingId)
            @php
                $customerMedia = (!$isCreating && $editingType === 'customer')
                    ? $this->editingCustomerMedia
                    : ['orders' => collect(), 'invoices' => collect(), 'adminDocs' => [], 'orderDocs' => [], 'snapshots' => [], 'totalCount' => 0];
            @endphp
            {{-- FULL PAGE INLINE EDITOR --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md p-8 sm:p-10 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl relative overflow-hidden animate-fade-in mt-6">
                {{-- Form Header --}}
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6 border-b border-gray-100 dark:border-gray-800 pb-6">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">
                            {{ $isCreating ? 'Neues Seelenlicht erschaffen' : 'Benutzerprofil bearbeiten' }}
                        </h2>
                        <p class="text-xs text-gray-500 font-medium mt-1">
                            {{ $formData['first_name'] || $formData['last_name'] ? trim($formData['first_name'] . ' ' . $formData['last_name']) . ' (' . $formData['email'] . ')' : 'Stammdaten, Standort und hinterlegte Dokumente verwalten.' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if(!$isCreating && $editingType === 'customer')
                            <a href="/admin/support-tickets?createForCustomer={{ $editingId }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-[var(--theme-color)] text-gray-900 shadow-sm hover:opacity-90">
                                <x-heroicon-m-chat-bubble-left-right class="w-4 h-4" />
                                <span>Nachricht senden</span>
                            </a>
                        @endif
                        <button wire:click="cancelEdit" class="p-2.5 text-gray-500 hover:text-gray-900 dark:hover:text-white bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl transition-all shadow-sm dark:shadow-inner hover:bg-red-500/20 hover:border-red-500/50 hover:text-red-500 dark:hover:text-red-400 group">
                            <x-heroicon-m-x-mark class="w-6 h-6 group-hover:scale-110 transition-transform" />
                        </button>
                    </div>
                </div>

                {{-- Interactive Tabs --}}
                <div class="flex flex-wrap items-center gap-2 mb-8 p-1.5 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800 w-fit">
                    <button type="button"
                            wire:click="setEditTab('account')"
                            @class([
                                'inline-flex items-center gap-2.5 px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all',
                                'bg-white dark:bg-gray-900 text-[var(--theme-color)] shadow-sm border border-gray-200 dark:border-gray-700' => $activeEditTab === 'account',
                                'text-gray-500 hover:text-gray-900 dark:hover:text-white' => $activeEditTab !== 'account',
                            ])>
                        <x-heroicon-m-shield-check class="w-4 h-4" />
                        <span>Account &amp; Sicherheit</span>
                    </button>

                    <button type="button"
                            wire:click="setEditTab('profile')"
                            @class([
                                'inline-flex items-center gap-2.5 px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all',
                                'bg-white dark:bg-gray-900 text-[var(--theme-color)] shadow-sm border border-gray-200 dark:border-gray-700' => $activeEditTab === 'profile',
                                'text-gray-500 hover:text-gray-900 dark:hover:text-white' => $activeEditTab !== 'profile',
                            ])>
                        <x-heroicon-m-map-pin class="w-4 h-4" />
                        <span>Standort &amp; Profil</span>
                    </button>

                    @if(!$isCreating && $editingType === 'customer')
                        <button type="button"
                                wire:click="setEditTab('media')"
                                @class([
                                    'inline-flex items-center gap-2.5 px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all',
                                    'bg-white dark:bg-gray-900 text-[var(--theme-color)] shadow-sm border border-gray-200 dark:border-gray-700' => $activeEditTab === 'media',
                                    'text-gray-500 hover:text-gray-900 dark:hover:text-white' => $activeEditTab !== 'media',
                                ])>
                            <x-heroicon-m-folder-open class="w-4 h-4" />
                            <span>Medien &amp; Dokumente</span>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                {{ $customerMedia['totalCount'] }}
                            </span>
                        </button>
                    @endif
                </div>

                @if($activeEditTab === 'account')
                    {{-- TAB 1: ACCOUNT & SICHERHEIT --}}
                    <div class="space-y-6 animate-fade-in">
                        @if($isCreating)
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">System-Rolle</label>
                                <select wire:model.live="createType" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner p-4 outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all appearance-none cursor-pointer">
                                    <option value="customer">Kunde</option>
                                    <option value="employee">Mitarbeiter</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        @else
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">System-Rolle</label>
                                <div class="w-full text-sm border-gray-200 dark:border-gray-800 rounded-xl bg-gray-50 dark:bg-gray-950/50 text-gray-500 px-4 py-4 border inline-flex items-center gap-2 cursor-not-allowed">
                                    <x-heroicon-m-lock-closed class="w-4 h-4" />
                                    @if($editingType === 'admin') Administrator @elseif($editingType === 'employee') Mitarbeiter @else Kunde @endif
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Vorname *</label>
                                <input type="text" wire:model="formData.first_name" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Nachname *</label>
                                <input type="text" wire:model="formData.last_name" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">E-Mail Adresse *</label>
                                <input type="email" wire:model="formData.email" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Passwort {{ $isCreating ? '*' : '(Optional)' }}</label>
                                <div x-data="{ showPw: false }" class="relative">
                                    <input :type="showPw ? 'text' : 'password'" wire:model="formData.password" placeholder="{{ $isCreating ? 'Mindestens 8 Zeichen' : 'Neues Passwort eingeben...' }}" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 pr-12 outline-none placeholder-gray-400 dark:placeholder-gray-600">
                                    <button type="button" @click="showPw = !showPw" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-[var(--theme-color)] transition-colors focus:outline-none">
                                        <x-heroicon-o-eye x-show="!showPw" class="w-5 h-5" />
                                        <x-heroicon-o-eye-slash x-show="showPw" class="w-5 h-5" style="display: none;" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <label class="flex items-center gap-4 cursor-pointer group bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner hover:border-emerald-500/50 transition-colors">
                                <div class="relative flex items-center shrink-0">
                                    <input type="checkbox" wire:model="formData.is_verified" class="peer sr-only">
                                    <div class="w-6 h-6 bg-black border-2 border-gray-700 rounded transition-all peer-checked:bg-emerald-500 peer-checked:border-emerald-500"></div>
                                    <svg class="absolute w-4 h-4 left-1 top-1 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <span class="text-sm font-bold text-gray-800 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">E-Mail verifiziert</span>
                            </label>

                            @if(!$isCreating)
                                <div class="bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div @class([
                                            'p-2.5 rounded-xl border shrink-0',
                                            'bg-emerald-500/10 border-emerald-500/30 text-emerald-500 dark:text-emerald-400' => $editing2faActive,
                                            'bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-400' => !$editing2faActive,
                                        ])>
                                            @if($editing2faActive)
                                                <x-heroicon-m-shield-check class="w-5 h-5" />
                                            @else
                                                <x-heroicon-m-shield-exclamation class="w-5 h-5" />
                                            @endif
                                        </div>
                                        <div class="text-sm font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                            <span>2-Faktor-Authentifizierung</span>
                                            @if($editing2faActive)
                                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-500 border border-emerald-500/30">Aktiv</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-gray-200 dark:bg-gray-800 text-gray-500 border border-gray-300 dark:border-gray-700">Inaktiv</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($editing2faActive)
                                        <button type="button" wire:confirm="Möchtest du die 2-Faktor-Authentifizierung für diesen Benutzer wirklich deaktivieren?" wire:click="disable2Fa" class="px-3.5 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-500 hover:text-amber-400 border border-amber-500/30 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center justify-center gap-2 shadow-sm">
                                            <x-heroicon-s-key class="w-4 h-4" />
                                            2FA deaktivieren
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if(!$isCreating)
                            {{-- PROFIL-, ADRESS-, GEBURTSTAGS- & DSGVO-ÄNDERUNGSPROTOKOLL (LOG) --}}
                            <div class="p-4 bg-gray-50 dark:bg-gray-950 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-gray-200 dark:border-gray-800">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                                            <x-heroicon-m-clock class="w-4 h-4" />
                                        </div>
                                        <div class="text-xs font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                            <span>Profil- &amp; DSGVO-Änderungsprotokoll</span>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-700">
                                                {{ count($editingUserLogs) }} Einträge
                                            </span>
                                        </div>
                                    </div>

                                    @if($editingType === 'customer')
                                        <span @class([
                                            'px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider border shrink-0',
                                            'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' => $editingUserBirthdayCorrections === 0,
                                            'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/40' => $editingUserBirthdayCorrections >= 1,
                                        ])>
                                            🎂 Geburtstags-Korrekturen: {{ $editingUserBirthdayCorrections }} / 1
                                        </span>
                                    @endif
                                </div>

                                @if(empty($editingUserLogs))
                                    <div class="py-5 px-4 text-center rounded-xl bg-white dark:bg-gray-900 border border-dashed border-gray-200 dark:border-gray-800">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Keine protokollierten Änderungen vorhanden.</p>
                                    </div>
                                @else
                                    <div class="max-h-72 overflow-y-auto pr-1 space-y-2.5 custom-scrollbar">
                                        @foreach($editingUserLogs as $logEntry)
                                            <div class="p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xs space-y-2">
                                                <div class="flex flex-wrap items-center justify-between gap-2 text-[10px]">
                                                    <div class="flex items-center gap-1.5">
                                                        <span @class([
                                                            'px-2 py-0.5 rounded font-black uppercase tracking-wider border text-[9px]',
                                                            'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/30' => !empty($logEntry['is_customer_actor']),
                                                            'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/30' => empty($logEntry['is_customer_actor']),
                                                        ])>
                                                            {{ !empty($logEntry['is_customer_actor']) ? '👤 Kunde' : '🛡️ Admin' }}
                                                        </span>
                                                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $logEntry['actor'] }}</span>
                                                    </div>
                                                    <span class="font-mono text-gray-500 dark:text-gray-400">
                                                        {{ $logEntry['created_at'] }}
                                                        @if(!empty($logEntry['created_at_human']))
                                                            <span class="opacity-75">({{ $logEntry['created_at_human'] }})</span>
                                                        @endif
                                                    </span>
                                                </div>

                                                @if(!empty($logEntry['changes']))
                                                    <div class="space-y-1 pt-1 border-t border-gray-100 dark:border-gray-800/80">
                                                        @foreach($logEntry['changes'] as $change)
                                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-[11px] bg-gray-50 dark:bg-gray-950/70 px-2.5 py-1.5 rounded-lg border border-gray-100 dark:border-gray-800/60">
                                                                <span class="font-bold text-gray-700 dark:text-gray-300 shrink-0">{{ $change['label'] }}:</span>
                                                                <div class="flex flex-wrap items-center gap-1.5 font-mono text-[10px] min-w-0">
                                                                    <span class="text-red-600 dark:text-red-400 line-through truncate max-w-[160px]" title="{{ $change['old'] }}">{{ $change['old'] }}</span>
                                                                    <span class="text-gray-400">&rarr;</span>
                                                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold truncate max-w-[180px]" title="{{ $change['new'] }}">{{ $change['new'] }}</span>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="text-[11px] text-gray-600 dark:text-gray-400">{{ $logEntry['title'] }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @elseif($activeEditTab === 'profile')
                    {{-- TAB 2: STANDORT & PROFIL --}}
                    <div class="space-y-6 animate-fade-in">
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 dark:bg-gray-950 p-1.5 rounded-xl shadow-sm dark:shadow-inner border border-gray-200 dark:border-gray-800 max-w-md">
                            <button wire:click="$set('formData.customer_type', 'private')" type="button" @class(['py-3 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all', 'bg-white dark:bg-gray-800 text-[var(--theme-color)] shadow-sm border border-gray-200 dark:border-gray-700' => $formData['customer_type'] === 'private', 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-300' => $formData['customer_type'] !== 'private'])>
                                Privatperson
                            </button>
                            <button wire:click="$set('formData.customer_type', 'business')" type="button" @class(['py-3 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all', 'bg-white dark:bg-gray-800 text-[var(--theme-color)] shadow-sm border border-gray-200 dark:border-gray-700' => $formData['customer_type'] === 'business', 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-300' => $formData['customer_type'] !== 'business'])>
                                Gewerblich (B2B)
                            </button>
                        </div>

                        @if($formData['customer_type'] === 'business')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 animate-fade-in-up">
                                <div class="space-y-2">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Firmenname</label>
                                    <input type="text" wire:model="formData.company_name" placeholder="GmbH, UG..." class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">USt-IdNr.</label>
                                    <input type="text" wire:model="formData.vat_id" placeholder="DE..." class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none font-mono uppercase">
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex gap-4">
                                <div class="space-y-2 w-2/3">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Straße</label>
                                    <input type="text" wire:model="formData.street" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                                </div>
                                <div class="space-y-2 w-1/3">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Nr.</label>
                                    <input type="text" wire:model="formData.house_number" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div class="space-y-2 w-1/3">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">PLZ</label>
                                    <input type="text" wire:model="formData.postal" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                                </div>
                                <div class="space-y-2 w-2/3">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Stadt</label>
                                    <input type="text" wire:model="formData.city" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Telefon</label>
                                <input type="text" wire:model="formData.phone_number" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Geburtsdatum</label>
                                <input type="date" wire:model="formData.birthday" class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none">
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Interne Notiz (Nur für Admins)</label>
                            <textarea wire:model="formData.internal_note" rows="3" placeholder="Besonderheiten, VIP Status..." class="w-full text-sm border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-950 text-gray-900 dark:text-white shadow-sm dark:shadow-inner focus:ring-2 focus:ring-[var(--theme-color)]/50 focus:border-[var(--theme-color)] transition-all p-4 outline-none resize-none"></textarea>
                        </div>

                        @if(!$isCreating && $editingType === 'customer')
                            <div class="pt-4 border-t border-gray-100 dark:border-gray-800/60 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-amber-500">
                                        <x-heroicon-s-sparkles class="w-5 h-5" />
                                        <h3 class="text-[10px] font-black uppercase tracking-widest">Seelenfunken, Funki &amp; DSGVO-Freigaben</h3>
                                    </div>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="formData.gamification_active" class="w-4 h-4 rounded border-gray-700 text-amber-500 focus:ring-amber-500">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Magie aktiv</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Einlösbare Funken</label>
                                        <input type="number" min="0" wire:model="formData.funken_balance" class="w-full text-sm font-mono font-bold border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-amber-500 p-2.5 outline-none focus:ring-2 focus:ring-amber-500/40">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Lebenszeit-Funken</label>
                                        <input type="number" min="0" wire:model="formData.funken_total_earned" class="w-full text-sm font-mono font-bold border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white p-2.5 outline-none focus:ring-2 focus:ring-amber-500/40">
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Funki-Level (1–10)</label>
                                        <select wire:model="formData.funki_level" class="w-full text-xs font-bold border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white p-2.5 outline-none focus:ring-2 focus:ring-amber-500/40">
                                            @for($lvl = 1; $lvl <= 10; $lvl++)
                                                <option value="{{ $lvl }}">Level {{ $lvl }} ({{ \App\Services\Gamification\GameConfig::getLevels()[$lvl]['title'] ?? '' }})</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-500">DSGVO &amp; Einwilligungen</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        <label class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 cursor-pointer hover:border-amber-500/40 transition">
                                            <input type="checkbox" wire:model="formData.gamification_active" class="w-4 h-4 rounded border-gray-700 text-amber-500 focus:ring-amber-500 shrink-0">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">Seelenfunken-Treueprogramm aktiv</span>
                                        </label>

                                        <label class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 cursor-pointer hover:border-amber-500/40 transition">
                                            <input type="checkbox" wire:model="formData.ranking_opt_in" class="w-4 h-4 rounded border-gray-700 text-amber-500 focus:ring-amber-500 shrink-0">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">Sichtbarkeit in der Halle der Helden</span>
                                        </label>

                                        <label class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 cursor-pointer hover:border-amber-500/40 transition">
                                            <input type="checkbox" wire:model="formData.use_real_name" class="w-4 h-4 rounded border-gray-700 text-amber-500 focus:ring-amber-500 shrink-0">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">Echtnamen öffentlich anzeigen</span>
                                        </label>

                                        <label class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 cursor-pointer hover:border-amber-500/40 transition">
                                            <input type="checkbox" wire:model="formData.ai_data_consent" class="w-4 h-4 rounded border-gray-700 text-amber-500 focus:ring-amber-500 shrink-0">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">DSGVO KI-Support-Freigabe</span>
                                        </label>
                                    </div>

                                    <div class="pt-1">
                                        <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">Spielername</label>
                                        <input type="text" maxlength="40" wire:model="formData.player_name" placeholder="z. B. Sternenwanderer..." class="w-full text-xs font-bold border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white p-2.5 outline-none focus:ring-2 focus:ring-amber-500/40">
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @elseif($activeEditTab === 'media' && !$isCreating && $editingType === 'customer')
                    {{-- TAB 3: MEDIEN & DOKUMENTE (HINTERLEGTE DATEIEN) --}}
                    <div class="space-y-8 animate-fade-in">
                        {{-- 1. Neue Datei aktiv für Kunden hinterlegen --}}
                        <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <x-heroicon-m-cloud-arrow-up class="w-5 h-5 text-[var(--theme-color)]" />
                                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Neue Datei für Kunden hinterlegen</h3>
                                    <div x-data="{ tip: false }" class="relative inline-flex items-center">
                                        <button type="button" @mouseenter="tip = true" @mouseleave="tip = false" @click="tip = !tip" class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-[10px] font-black flex items-center justify-center hover:bg-[var(--theme-color)] hover:text-gray-900 transition-colors">!</button>
                                        <div x-show="tip" x-cloak class="absolute left-6 top-0 z-30 w-72 p-3 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">
                                            Hochgeladene Dateien sind sofort gespeichert und erscheinen beim Kunden direkt im Kundenportal unter „Bestellungen &rarr; Dokumente“.
                                        </div>
                                    </div>
                                </div>

                                @if($customerMediaSavedText)
                                    <span x-data="{ visible: true }"
                                          x-init="setTimeout(() => visible = false, 4000)"
                                          x-show="visible"
                                          x-transition.opacity
                                          wire:key="cust-doc-saved-{{ md5($customerMediaSavedText . microtime()) }}"
                                          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        {{ $customerMediaSavedText }}
                                    </span>
                                @endif
                            </div>

                            {{-- Schnellwahl-Buttons --}}
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach(['Echtheitszertifikat', 'Pflegehinweise', 'Manufaktur-Zertifikat', 'Kulanz-Beleg'] as $preset)
                                    <button type="button"
                                            wire:click="setCustomerUploadPresetTitle('{{ $preset }}')"
                                            @class([
                                                'px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all',
                                                'bg-[var(--theme-color)] text-gray-900 border-[var(--theme-color)] shadow-2xs' => $customerUploadTitle === $preset,
                                                'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)]' => $customerUploadTitle !== $preset,
                                            ])>
                                        + {{ $preset }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Dokumenten-Titel</label>
                                    <input type="text"
                                           wire:model="customerUploadTitle"
                                           placeholder="z. B. Echtheitszertifikat (leer = Dateiname)"
                                           class="w-full text-xs font-bold border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white p-3.5 outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50">
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Bestell-Zuordnung</label>
                                    <select wire:model="customerUploadOrderId"
                                            class="w-full text-xs font-bold border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white p-3.5 outline-none focus:ring-2 focus:ring-[var(--theme-color)]/50">
                                        <option value="">Kundenkonto allgemein (Alle Bestellungen)</option>
                                        @foreach($customerMedia['orders'] as $ord)
                                            <option value="{{ $ord->id }}">Bestellung #{{ $ord->order_number }} ({{ $ord->created_at?->format('d.m.Y') }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Datei auswählen (Sofort-Upload)</label>
                                    <label class="flex items-center justify-center gap-2 w-full p-3.5 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 hover:border-[var(--theme-color)] bg-white dark:bg-gray-900 text-xs font-bold text-gray-600 dark:text-gray-300 cursor-pointer transition-all">
                                        <x-heroicon-m-arrow-up-tray class="w-4 h-4 text-[var(--theme-color)]" />
                                        <span wire:loading.remove wire:target="customerUploadFiles">Datei hochladen (PDF, ZIP, Bild)</span>
                                        <span wire:loading wire:target="customerUploadFiles" class="text-[var(--theme-color)]">Wird gespeichert...</span>
                                        <input type="file" wire:model="customerUploadFiles" multiple accept=".pdf,.zip,.rar,.jpg,.jpeg,.png,.webp" class="hidden">
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- 2. Hinterlegte Dateien & Dokumente des Kunden --}}
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {{-- Spalte A: Individuell hinterlegte Dokumente & Produkt-Zertifikate --}}
                            <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-4">
                                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-m-document-check class="w-5 h-5 text-[var(--theme-color)]" />
                                        <h4 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Hinterlegte Dokumente &amp; Zertifikate</h4>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                                        {{ count($customerMedia['adminDocs']) + count($customerMedia['orderDocs']) }}
                                    </span>
                                </div>

                                @if(empty($customerMedia['adminDocs']) && empty($customerMedia['orderDocs']))
                                    <div class="py-8 text-center rounded-xl bg-white dark:bg-gray-900 border border-dashed border-gray-200 dark:border-gray-800">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Noch keine individuellen Dokumente oder Produkt-Zertifikate hinterlegt.</p>
                                    </div>
                                @else
                                    <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1 custom-scrollbar">
                                        @foreach($customerMedia['adminDocs'] as $aDoc)
                                            <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                                            {{ !empty($aDoc['order_number']) ? 'Bestellung #' . $aDoc['order_number'] : 'Kundenkonto' }}
                                                        </span>
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $aDoc['title'] }}</span>
                                                    </div>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 truncate">
                                                        {{ strtoupper($aDoc['extension'] ?? 'PDF') }} • {{ $aDoc['filename'] ?? '' }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-1.5 shrink-0">
                                                    <a href="{{ route('customer-document.download', ['customer' => $editingId, 'doc' => $aDoc['id']]) }}"
                                                       target="_blank"
                                                       class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all"
                                                       title="Herunterladen">
                                                        <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                                    </a>
                                                    <button type="button"
                                                            wire:click="removeCustomerUploadedDocument('{{ $aDoc['id'] }}')"
                                                            class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-red-500 hover:border-red-500/40 transition-all"
                                                            title="Entfernen">
                                                        <x-heroicon-m-trash class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach

                                        @foreach($customerMedia['orderDocs'] as $oDoc)
                                            <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                            #{{ $oDoc['order_number'] }}
                                                        </span>
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $oDoc['title'] }}</span>
                                                    </div>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1 truncate">
                                                        {{ $oDoc['product_name'] }} ({{ $oDoc['extension'] }}) • {{ $oDoc['order_date'] }}
                                                    </p>
                                                </div>
                                                <a href="{{ $oDoc['download_url'] }}"
                                                   target="_blank"
                                                   class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all shrink-0"
                                                   title="Herunterladen">
                                                    <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Spalte B: Rechnungen, Gutschriften & Gravur-Snapshots --}}
                            <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-4">
                                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-3">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-m-document-text class="w-5 h-5 text-[var(--theme-color)]" />
                                        <h4 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white">Rechnungen &amp; Bestell-Belege</h4>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                                        {{ $customerMedia['invoices']->count() + count($customerMedia['snapshots']) }}
                                    </span>
                                </div>

                                @if($customerMedia['invoices']->isEmpty() && empty($customerMedia['snapshots']))
                                    <div class="py-8 text-center rounded-xl bg-white dark:bg-gray-900 border border-dashed border-gray-200 dark:border-gray-800">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Noch keine Rechnungen oder Bestell-Belege vorhanden.</p>
                                    </div>
                                @else
                                    <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1 custom-scrollbar">
                                        @foreach($customerMedia['invoices'] as $inv)
                                            <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span @class([
                                                            'px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border',
                                                            'bg-red-500/10 text-red-500 border-red-500/30' => method_exists($inv, 'isCreditNote') && $inv->isCreditNote(),
                                                            'bg-emerald-500/10 text-emerald-500 border-emerald-500/30' => !(method_exists($inv, 'isCreditNote') && $inv->isCreditNote()),
                                                        ])>
                                                            {{ (method_exists($inv, 'isCreditNote') && $inv->isCreditNote()) ? 'Gutschrift' : 'Rechnung' }}
                                                        </span>
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $inv->invoice_number }}</span>
                                                    </div>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">
                                                        {{ $inv->created_at?->format('d.m.Y') }} • {{ number_format(((int) ($inv->total ?? 0)) / 100, 2, ',', '.') }} €
                                                    </p>
                                                </div>
                                                <a href="{{ route('invoice.download', $inv->id) }}"
                                                   target="_blank"
                                                   class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all shrink-0"
                                                   title="Rechnung herunterladen">
                                                    <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                                </a>
                                            </div>
                                        @endforeach

                                        @foreach($customerMedia['snapshots'] as $snap)
                                            <div class="flex items-center justify-between gap-3 p-3.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                            #{{ $snap['order_number'] }}
                                                        </span>
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $snap['label'] }}</span>
                                                    </div>
                                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1">{{ $snap['date'] }}</p>
                                                </div>
                                                <a href="{{ $snap['url'] }}"
                                                   target="_blank"
                                                   class="p-2 rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-all shrink-0"
                                                   title="Vorschau öffnen">
                                                    <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Action Footer --}}
                <div class="mt-10 pt-8 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-6">
                    @if($errors->any())
                        <div class="text-red-400 text-[10px] font-bold bg-red-500/10 px-4 py-3 rounded-xl border border-red-500/20 w-full sm:w-auto flex items-center gap-3">
                            <x-heroicon-m-exclamation-triangle class="w-5 h-5 flex-shrink-0" />
                            <span>Bitte behebe die rot markierten Fehler, bevor du speicherst.</span>
                        </div>
                    @else
                        <div></div> {{-- Spacer --}}
                    @endif

                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <button wire:click="cancelEdit" class="flex-1 sm:flex-none px-8 py-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 shadow-sm dark:shadow-inner hover:-translate-x-1">
                            Zurück zur Liste
                        </button>
                        <button wire:click="{{ $isCreating ? 'saveNewUser' : 'saveInline' }}" class="flex-1 sm:flex-none px-8 py-4 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-emerald-500 text-gray-900 shadow-sm dark:shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:bg-emerald-400 hover:scale-[1.02] flex items-center justify-center gap-2">
                            <x-heroicon-m-check class="w-5 h-5 drop-shadow-[0_0_4px_rgba(0,0,0,0.5)]" />
                            {{ $isCreating ? 'Benutzer erstellen' : 'Änderungen speichern' }}
                        </button>
                    </div>
                </div>
            </div>
        @else
            {{-- Filter & Search --}}
            <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-md p-3 sm:p-4 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl items-center animate-fade-in-up">
                <div class="md:col-span-3 relative group">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Kundennummer, Name, Firma, E-Mail oder Stadt suchen..."
                           class="w-full pl-12 pr-4 py-4 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner transition-all text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 outline-none text-sm">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                        <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-600 group-focus-within:text-[var(--theme-color)] transition-colors" />
                    </div>
                </div>

                <select wire:model.live="filterRole" class="md:col-span-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] shadow-sm dark:shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer outline-none appearance-none">
                    <option value="all" class="bg-gray-900 text-white">Alle Rollen</option>
                    <option value="admin" class="bg-gray-900 text-white">Administratoren</option>
                    <option value="employee" class="bg-gray-900 text-white">Mitarbeiter</option>
                    <option value="customer" class="bg-gray-900 text-white">Kunden</option>
                </select>
            </div>

            {{-- Table --}}
            <div class="mt-6 bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden w-full relative animate-fade-in-up">
                <div class="overflow-x-auto w-full custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                        <tr class="bg-gray-50/80 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black text-gray-600 dark:text-gray-500 uppercase tracking-widest shadow-sm dark:shadow-inner">
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
                            <tr class="transition-all duration-300 group hover:bg-gray-50 dark:hover:bg-gray-800/40 cursor-default">
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-[1.2rem] shrink-0 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[var(--theme-color)] font-serif text-xl font-bold shadow-sm dark:shadow-[inset_0_-2px_10px_rgba(0,0,0,0.5),_0_0_15px_var(--theme-color)0.1)] group-hover:shadow-md dark:group-hover:shadow-[inset_0_-2px_10px_rgba(0,0,0,0.5),_0_0_20px_var(--theme-color)0.3)] transition-shadow">
                                            {{ substr($user['first_name'], 0, 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900 dark:text-white text-sm tracking-wide truncate">{{ $user['first_name'] }} {{ $user['last_name'] }}</div>
                                            <div class="text-[11px] text-gray-500 font-medium tracking-wide mt-0.5 group-hover:text-[var(--theme-color)] transition-colors truncate">{{ $user['email'] }}</div>
                                            @if($user['user_type'] === 'customer')
                                                <div class="mt-1 flex items-center gap-1.5">
                                                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500 bg-gray-100 dark:bg-gray-900 px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">KDN:</span>
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
                                                            init() {
                                                                let timer = setInterval(() => this.update(), 1000);
                                                                this.$cleanup(() => clearInterval(timer));
                                                                this.update();
                                                            },
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

                                        @if(!empty($profile['two_factor_is_active']))
                                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[9px] font-black uppercase tracking-widest" title="Zwei-Faktor-Authentifizierung aktiv">
                                                <x-heroicon-m-shield-check class="w-3.5 h-3.5 shrink-0" />
                                                <span>2FA Aktiv</span>
                                            </div>
                                        @endif

                                        @if($user['user_type'] === 'customer' && !empty($user['gamification']))
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[9px] font-black uppercase tracking-widest">
                                                <span>✨ Funki Lvl. {{ max(1, (int) ($user['gamification']['funki_level'] ?? 1)) }}</span>
                                                <span class="opacity-60">•</span>
                                                <span>{{ (int) ($user['gamification']['funken_balance'] ?? 0) }} Funken</span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 sm:px-8 py-4 align-top">
                                    <div class="text-xs space-y-1.5 mt-1">
                                        <div class="flex items-center gap-2 text-gray-800 dark:text-gray-300">
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
                                            <div class="flex items-center gap-1.5 text-gray-800 dark:text-gray-300">
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
                                                @if(($user['user_type'] ?? '') === 'customer')
                                                    <a href="/admin/support-tickets?createForCustomer={{ $user['id'] }}" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)]/30 rounded-xl transition-all shadow-sm dark:shadow-inner" title="Direktnachricht / Support-Ticket an Kunden senden">
                                                        <x-heroicon-m-chat-bubble-left-right class="w-4 h-4" />
                                                    </a>
                                                    <button type="button" wire:click="startEdit('{{ $user['id'] }}', 'customer', 'media')" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)]/30 rounded-xl transition-all shadow-sm dark:shadow-inner" title="Hinterlegte Dateien & Dokumente">
                                                        <x-heroicon-m-folder-open class="w-4 h-4" />
                                                    </button>
                                                @endif
                                                @if(($user['user_type'] ?? '') === 'employee' && !in_array($user['role'] ?? 'internal', ['influencer', 'tax_advisor'], true))
                                                    <a href="/admin/employees" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-indigo-500/30 text-indigo-400 hover:text-indigo-300 hover:border-indigo-500/60 rounded-xl transition-all shadow-sm dark:shadow-inner" title="Zur Personalakte & Lohnabrechnung (Leitung -> Mitarbeiter)">
                                                        <x-heroicon-m-identification class="w-4 h-4" />
                                                    </a>
                                                @endif
                                                @if(!empty($profile['two_factor_is_active']))
                                                    <button type="button" wire:confirm="Möchtest du die 2-Faktor-Authentifizierung für '{{ $user['first_name'] }} {{ $user['last_name'] }}' ({{ $user['email'] }}) wirklich deaktivieren?" wire:click="disable2FaForUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-amber-500/30 text-amber-500 hover:text-amber-400 hover:border-amber-500/60 rounded-xl transition-all shadow-sm dark:shadow-inner" title="2FA deaktivieren (Support-Reset)">
                                                        <x-heroicon-s-key class="w-4 h-4" />
                                                    </button>
                                                @endif
                                                <button wire:click="startEdit('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)]/30 rounded-xl transition-all shadow-sm dark:shadow-inner" title="Bearbeiten">
                                                    <x-heroicon-m-pencil-square class="w-4 h-4" />
                                                </button>
                                                <button wire:click="archiveUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-orange-500 dark:hover:text-orange-400 hover:border-orange-500/30 rounded-xl transition-all shadow-sm dark:shadow-inner" title="Archivieren">
                                                    <x-heroicon-m-archive-box class="w-4 h-4" />
                                                </button>
                                            </div>
                                        @else
                                            <button wire:click="restoreUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:border-emerald-500/30 rounded-xl transition-all shadow-sm dark:shadow-inner" title="Wiederherstellen">
                                                <x-heroicon-m-arrow-path class="w-4 h-4" />
                                            </button>
                                            <button wire:confirm="Sicher, dass dieser Benutzer unwiderruflich gelöscht werden soll?" wire:click="forceDelete('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-red-500 dark:hover:text-red-400 hover:border-red-500/30 rounded-xl transition-all shadow-sm dark:shadow-inner" title="Permanent löschen">
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
                @if($users->hasPages()) <div class="px-8 py-6 bg-gray-50/50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-800">{{ $users->links() }}</div> @endif
            </div>
        @endif
</div>
