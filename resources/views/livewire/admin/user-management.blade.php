<div class="space-y-6 md:space-y-8 pb-20 animate-fade-in-up font-sans antialiased text-gray-300">

    {{-- Tabs --}}
    <div class="flex items-center gap-2 bg-gray-950 p-2 rounded-2xl w-fit border border-gray-800 shadow-inner">
        <button wire:click="$set('activeTab', 'users')" @class(['px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all', 'bg-gray-800 shadow-lg text-primary border border-gray-700' => $activeTab === 'users', 'text-gray-500 hover:text-white' => $activeTab !== 'users'])>
            <x-heroicon-o-users class="w-4 h-4 inline mr-2" /> Begleiter
        </button>
        <button wire:click="$set('activeTab', 'logs')" @class(['px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all', 'bg-gray-800 shadow-lg text-primary border border-gray-700' => $activeTab === 'logs', 'text-gray-500 hover:text-white' => $activeTab !== 'logs'])>
            <x-heroicon-o-document-text class="w-4 h-4 inline mr-2" /> Protokoll
        </button>
    </div>

    @if($activeTab === 'users')
        {{-- Header --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none"><x-heroicon-o-sparkles class="w-40 h-40 text-primary drop-shadow-[0_0_20px_rgba(197,160,89,1)]" /></div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight">
                    {{ $showArchive ? 'Archivierte Seelen' : 'Benutzer-Zentrale' }}
                </h1>
                <p class="text-gray-400 mt-2 text-sm font-medium">Verwalten Sie Ihre Community mit Präzision und Übersicht.</p>
            </div>

            <div class="flex items-center gap-3 relative z-10">
                <button wire:click="toggleArchive" class="inline-flex items-center px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all {{ $showArchive ? 'bg-primary text-gray-900 shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-primary-dark hover:text-white hover:scale-[1.02]' : 'bg-gray-950 text-gray-400 hover:text-white border border-gray-800 shadow-inner' }}">
                    <x-heroicon-o-archive-box class="w-4 h-4 mr-2" />
                    {{ $showArchive ? 'Aktive Liste' : 'Archiv' }}
                </button>
            </div>
        </div>

        {{-- Filter & Search --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-6 bg-gray-900/80 backdrop-blur-md p-3 sm:p-4 rounded-[2rem] border border-gray-800 shadow-2xl items-center">
            <div class="md:col-span-3 relative group">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Name, E-Mail oder Stadt suchen..."
                       class="w-full pl-12 pr-4 py-4 bg-gray-950 border border-gray-800 rounded-[1.5rem] focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner transition-all text-white placeholder-gray-600 outline-none text-sm">
                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-600 group-focus-within:text-primary transition-colors" />
                </div>
            </div>

            <select wire:model.live="filterRole" class="md:col-span-1 bg-gray-950 border border-gray-800 rounded-[1.5rem] px-5 py-4 focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white transition-colors cursor-pointer outline-none appearance-none">
                <option value="all" class="bg-gray-900 text-white">Alle Rollen</option>
                <option value="admin" class="bg-gray-900 text-white">Administratoren</option>
                <option value="employee" class="bg-gray-900 text-white">Mitarbeiter</option>
                <option value="customer" class="bg-gray-900 text-white">Kunden</option>
            </select>
        </div>

        {{-- Table --}}
        <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
            <div class="overflow-x-auto w-full no-scrollbar">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                    <tr class="bg-gray-950/80 border-b border-gray-800 text-[10px] font-black text-gray-500 uppercase tracking-widest shadow-inner">
                        <th class="px-6 sm:px-8 py-6">Identität</th>
                        <th class="px-6 sm:px-8 py-6">Standort & Kontakt</th>
                        <th class="px-6 sm:px-8 py-6">Letzter Login</th>
                        <th class="px-6 sm:px-8 py-6">Rolle / Status</th>
                        <th class="px-6 sm:px-8 py-6 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                    @forelse($users as $user)
                        @php $isEditing = ($editingId === $user['id'] && $editingType === $user['user_type']); @endphp
                        <tr @class(['transition-all duration-300 group', 'bg-primary/5 shadow-inner' => $isEditing, 'hover:bg-gray-800/30' => !$isEditing])>
                            <td class="px-6 sm:px-8 py-6 align-top">
                                @if($isEditing)
                                    <div class="space-y-4 w-64 animate-fade-in">
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black text-primary uppercase tracking-widest ml-1 drop-shadow-[0_0_8px_currentColor]">Vorname & Nachname</label>
                                            <div class="flex gap-2">
                                                <input type="text" wire:model="formData.first_name" class="w-1/2 text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all p-3 outline-none">
                                                <input type="text" wire:model="formData.last_name" class="w-1/2 text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all p-3 outline-none">
                                            </div>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black text-primary uppercase tracking-widest ml-1 drop-shadow-[0_0_8px_currentColor]">E-Mail</label>
                                            <input type="email" wire:model="formData.email" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all p-3 outline-none">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black text-primary uppercase tracking-widest ml-1 drop-shadow-[0_0_8px_currentColor]">Passwort (Optional)</label>
                                            <input type="password" wire:model="formData.password" placeholder="Leer = Kein Reset" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white shadow-inner focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all p-3 outline-none placeholder-gray-600">
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-4">
                                        <div class="h-14 w-14 rounded-[1.2rem] bg-gray-950 border border-gray-800 flex items-center justify-center text-primary font-serif text-2xl font-bold shadow-[inset_0_-2px_10px_rgba(0,0,0,0.5),_0_0_15px_rgba(197,160,89,0.1)] group-hover:shadow-[inset_0_-2px_10px_rgba(0,0,0,0.5),_0_0_20px_rgba(197,160,89,0.3)] transition-shadow">
                                            {{ substr($user['first_name'], 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-white text-base tracking-wide">{{ $user['first_name'] }} {{ $user['last_name'] }}</div>
                                            <div class="text-[11px] text-gray-500 font-medium tracking-wide mt-0.5 group-hover:text-primary transition-colors">{{ $user['email'] }}</div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 sm:px-8 py-6 align-top">
                                @if($isEditing)
                                    <div class="space-y-4 w-64 animate-fade-in">
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black text-primary uppercase tracking-widest ml-1 drop-shadow-[0_0_8px_currentColor]">Anschrift</label>
                                            <div class="flex gap-2">
                                                <input type="text" wire:model="formData.street" placeholder="Str." class="w-2/3 text-sm border-gray-700 rounded-xl bg-gray-950 text-white p-3 outline-none focus:ring-2 focus:ring-primary/50 shadow-inner">
                                                <input type="text" wire:model="formData.house_number" placeholder="Nr." class="w-1/3 text-sm border-gray-700 rounded-xl bg-gray-950 text-white p-3 outline-none focus:ring-2 focus:ring-primary/50 shadow-inner">
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <input type="text" wire:model="formData.postal" placeholder="PLZ" class="w-1/3 text-sm border-gray-700 rounded-xl bg-gray-950 text-white p-3 outline-none focus:ring-2 focus:ring-primary/50 shadow-inner">
                                            <input type="text" wire:model="formData.city" placeholder="Stadt" class="w-2/3 text-sm border-gray-700 rounded-xl bg-gray-950 text-white p-3 outline-none focus:ring-2 focus:ring-primary/50 shadow-inner">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[9px] font-black text-primary uppercase tracking-widest ml-1 drop-shadow-[0_0_8px_currentColor]">Telefon</label>
                                            <input type="text" wire:model="formData.phone_number" class="w-full text-sm border-gray-700 rounded-xl bg-gray-950 text-white p-3 outline-none focus:ring-2 focus:ring-primary/50 shadow-inner">
                                        </div>
                                    </div>
                                @else
                                    <div class="text-sm space-y-1.5">
                                        <div class="flex items-center gap-2 text-gray-300">
                                            <x-heroicon-m-map-pin class="w-4 h-4 text-primary drop-shadow-[0_0_8px_currentColor]" />
                                            <span class="font-bold tracking-wide">{{ $user['profile']['city'] ?? 'Ort unbekannt' }}</span>
                                        </div>
                                        <div class="text-[11px] text-gray-500 pl-6 leading-relaxed font-medium">
                                            {{ $user['profile']['street'] ?? '' }} {{ $user['profile']['house_number'] ?? '' }}<br>
                                            {{ $user['profile']['phone_number'] ?? '' }}
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 sm:px-8 py-6 align-top">
                                @if($user['last_seen'])
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-white">{{ \Carbon\Carbon::parse($user['last_seen'])->diffForHumans() }}</span>
                                        <span class="text-[10px] text-gray-500 uppercase tracking-widest font-black mt-1">{{ \Carbon\Carbon::parse($user['last_seen'])->format('d.m.Y H:i') }}</span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-600 italic">Noch nie eingeloggt</span>
                                @endif
                            </td>
                            <td class="px-6 sm:px-8 py-6 align-top">
                                <div class="space-y-3">
                                    <span @class(['px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border shadow-inner inline-block',
                                        'bg-purple-500/10 text-purple-400 border-purple-500/30' => $user['user_type'] === 'admin',
                                        'bg-indigo-500/10 text-indigo-400 border-indigo-500/30' => $user['user_type'] === 'employee',
                                        'bg-gray-800 text-gray-400 border-gray-700' => $user['user_type'] === 'customer'])>
                                        {{ $user['user_type'] }}
                                    </span>
                                    <div class="flex items-center text-[9px] font-black uppercase tracking-widest">
                                        @if(isset($user['deleted_at']))
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-2 shadow-[0_0_8px_currentColor] animate-pulse"></span>
                                            <span class="text-red-400">Archiviert</span>
                                        @else
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2 shadow-[0_0_8px_currentColor]"></span>
                                            <span class="text-emerald-400">Aktiv</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 sm:px-8 py-6 text-right align-top">
                                <div class="flex justify-end items-center gap-3">
                                    @if($isEditing)
                                        <button wire:click="saveInline" class="flex items-center gap-2 bg-emerald-500 text-gray-900 px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-400 transition-all shadow-[0_0_15px_rgba(16,185,129,0.3)] hover:scale-[1.02]">
                                            <x-heroicon-m-check class="w-4 h-4" /> Speichern
                                        </button>
                                        <button wire:click="cancelEdit" class="p-3 bg-gray-900 border border-gray-700 text-gray-400 rounded-xl hover:text-white hover:bg-gray-800 transition-all shadow-inner">
                                            <x-heroicon-m-x-mark class="w-5 h-5" />
                                        </button>
                                    @else
                                        @if(!$showArchive)
                                            <div class="opacity-100 lg:opacity-0 group-hover:opacity-100 transition-opacity flex gap-2">
                                                <button wire:click="startEdit('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 bg-gray-950 border border-gray-800 text-gray-500 hover:text-primary hover:border-primary/30 rounded-xl transition-all shadow-inner" title="Bearbeiten">
                                                    <x-heroicon-m-pencil-square class="w-5 h-5" />
                                                </button>
                                                <button wire:click="archiveUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 bg-gray-950 border border-gray-800 text-gray-500 hover:text-orange-400 hover:border-orange-500/30 rounded-xl transition-all shadow-inner" title="Archivieren">
                                                    <x-heroicon-m-archive-box class="w-5 h-5" />
                                                </button>
                                            </div>
                                        @else
                                            <div class="flex gap-2">
                                                <button wire:click="restoreUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 bg-gray-950 border border-gray-800 text-gray-500 hover:text-emerald-400 hover:border-emerald-500/30 rounded-xl transition-all shadow-inner" title="Wiederherstellen">
                                                    <x-heroicon-m-arrow-path class="w-5 h-5" />
                                                </button>
                                                <button wire:confirm="Permanent löschen?" wire:click="forceDelete('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 bg-gray-950 border border-gray-800 text-gray-500 hover:text-red-400 hover:border-red-500/30 rounded-xl transition-all shadow-inner" title="Permanent löschen">
                                                    <x-heroicon-m-trash class="w-5 h-5" />
                                                </button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-8 py-32 text-center text-gray-500 font-serif text-xl italic">Keine Begleiter gefunden...</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages()) <div class="px-8 py-6 bg-gray-900/30 border-t border-gray-800">{{ $users->links() }}</div> @endif
        </div>

    @else
        {{-- LOG BEREICH --}}
        <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden animate-fade-in-up">
            <div class="p-8 sm:p-10 border-b border-gray-800 flex justify-between items-center bg-gray-950/50 shadow-inner">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold text-white tracking-wide">System-Historie</h2>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-2">Transparente Dokumentation aller Stammdaten-Änderungen.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-800/50">
                @forelse($logs as $log)
                    <div class="p-6 sm:p-8 hover:bg-gray-800/30 transition-colors">
                        <div class="flex items-start gap-6">
                            <div @class(['p-4 rounded-[1rem] shrink-0 border shadow-inner',
                                'bg-blue-500/10 text-blue-400 border-blue-500/20' => $log->action_id === 'user:update',
                                'bg-orange-500/10 text-orange-400 border-orange-500/20' => $log->action_id === 'user:archive',
                                'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' => $log->action_id === 'user:restore',
                                'bg-red-500/10 text-red-400 border-red-500/20' => $log->action_id === 'user:destroy'])>
                                <x-heroicon-o-finger-print class="w-6 h-6" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col sm:flex-row justify-between items-start gap-2">
                                    <div>
                                        <h4 class="font-bold text-white text-base">{{ $log->title }}</h4>
                                        <p class="text-xs text-gray-400 font-medium mt-1">{{ $log->message }}</p>
                                    </div>
                                    <div class="text-right text-[9px] text-gray-500 font-black uppercase tracking-widest shrink-0">{{ $log->created_at->format('d.m.Y H:i') }}</div>
                                </div>
                                @if($log->payload && isset($log->payload['before']))
                                    <div x-data="{ open: false }" class="mt-4 border-t border-gray-800/50 pt-4">
                                        <button @click="open = !open" class="text-[9px] font-black uppercase tracking-widest text-primary hover:text-white transition-colors flex items-center gap-2">
                                            Änderungs-Details <x-heroicon-m-chevron-down class="w-3.5 h-3.5 transition-transform" ::class="open ? 'rotate-180' : ''" />
                                        </button>
                                        <div x-show="open" x-collapse class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div class="bg-red-900/10 p-5 rounded-2xl border border-red-500/20 shadow-inner">
                                                <span class="text-[9px] font-black text-red-400 uppercase tracking-widest block mb-3 drop-shadow-[0_0_8px_currentColor]">Vorheriger Stand</span>
                                                <pre class="text-[10px] text-red-200/70 font-mono whitespace-pre-wrap leading-relaxed">{{ json_encode($log->payload['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                            <div class="bg-emerald-900/10 p-5 rounded-2xl border border-emerald-500/20 shadow-inner">
                                                <span class="text-[9px] font-black text-emerald-400 uppercase tracking-widest block mb-3 drop-shadow-[0_0_8px_currentColor]">Aktueller Stand</span>
                                                <pre class="text-[10px] text-emerald-200/70 font-mono whitespace-pre-wrap leading-relaxed">{{ json_encode($log->payload['after'] ?? $log->payload['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-16 text-center text-gray-500 font-serif italic text-lg">Keine Protokolleinträge vorhanden.</div>
                @endforelse
            </div>
            @if($logs->hasPages())
                <div class="p-6 sm:p-8 bg-gray-900/30 border-t border-gray-800">{{ $logs->links() }}</div>
            @endif
        </div>
    @endif
</div>
