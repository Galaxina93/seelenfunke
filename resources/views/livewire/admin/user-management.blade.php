<div class="space-y-6 pb-20">
    {{-- Tabs --}}
    <div class="flex items-center gap-2 bg-gray-100/50 p-1.5 rounded-2xl w-fit border border-gray-200 shadow-sm">
        <button wire:click="$set('activeTab', 'users')" @class(['px-6 py-2.5 rounded-xl text-sm font-bold transition-all', 'bg-white shadow-sm text-primary border border-gray-100' => $activeTab === 'users', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'users'])>
            <x-heroicon-o-users class="w-4 h-4 inline mr-2" /> Begleiter
        </button>
        <button wire:click="$set('activeTab', 'logs')" @class(['px-6 py-2.5 rounded-xl text-sm font-bold transition-all', 'bg-white shadow-sm text-primary border border-gray-100' => $activeTab === 'logs', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'logs'])>
            <x-heroicon-o-document-text class="w-4 h-4 inline mr-2" /> Protokoll
        </button>
    </div>

    @if($activeTab === 'users')
        {{-- Header --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-8 opacity-5"><x-heroicon-o-sparkles class="w-32 h-32 text-primary" /></div>
            <div class="relative z-10">
                <h1 class="text-3xl font-serif font-bold text-gray-900">
                    {{ $showArchive ? 'Archivierte Seelen' : 'Benutzer-Zentrale' }}
                </h1>
                <p class="text-gray-500 mt-1">Verwalten Sie Ihre Community mit Präzision und Übersicht.</p>
            </div>

            <div class="flex items-center gap-3 relative z-10">
                <button wire:click="toggleArchive" class="inline-flex items-center px-6 py-3 rounded-2xl text-sm font-bold transition-all {{ $showArchive ? 'bg-primary text-white shadow-lg shadow-primary/30' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    <x-heroicon-o-archive-box class="w-5 h-5 mr-2" />
                    {{ $showArchive ? 'Aktive Liste' : 'Archiv' }}
                </button>
            </div>
        </div>

        {{-- Filter & Search --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2 relative group">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Name, E-Mail oder Stadt suchen..."
                       class="w-full pl-12 pr-4 py-4 bg-white border border-gray-200 rounded-[1.5rem] focus:ring-4 focus:ring-primary/10 focus:border-primary shadow-sm transition-all text-gray-700">
                <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5 text-gray-300 group-focus-within:text-primary transition-colors" />
                </div>
            </div>

            <select wire:model.live="filterRole" class="bg-white border border-gray-200 rounded-[1.5rem] px-5 py-4 focus:ring-4 focus:ring-primary/10 shadow-sm font-bold text-gray-600 cursor-pointer">
                <option value="all">Alle Rollen</option>
                <option value="admin">Administratoren</option>
                <option value="employee">Mitarbeiter</option>
                <option value="customer">Kunden</option>
            </select>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-200/50 border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100 text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                        <th class="px-8 py-5">Identität</th>
                        <th class="px-8 py-5">Standort & Kontakt</th>
                        <th class="px-8 py-5">Letzter Login</th>
                        <th class="px-8 py-5">Rolle / Status</th>
                        <th class="px-8 py-5 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                    @forelse($users as $user)
                        @php $isEditing = ($editingId === $user['id'] && $editingType === $user['user_type']); @endphp
                        <tr @class(['transition-all duration-300', 'bg-primary/[0.03]' => $isEditing, 'hover:bg-gray-50/50' => !$isEditing])>
                            <td class="px-8 py-6">
                                @if($isEditing)
                                    <div class="space-y-3 w-64 animate-fade-in">
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-primary uppercase ml-1">Vorname & Nachname</label>
                                            <div class="flex gap-2">
                                                <input type="text" wire:model="formData.first_name" class="w-1/2 text-sm border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-primary/20 transition-all">
                                                <input type="text" wire:model="formData.last_name" class="w-1/2 text-sm border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-primary/20 transition-all">
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-primary uppercase ml-1">E-Mail</label>
                                            <input type="email" wire:model="formData.email" class="w-full text-sm border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-primary/20 transition-all">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-primary uppercase ml-1">Passwort (leer lassen = kein Reset)</label>
                                            <input type="password" wire:model="formData.password" class="w-full text-xs border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-primary/20 transition-all">
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center text-primary font-serif text-xl font-bold shadow-inner">
                                            {{ substr($user['first_name'], 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-gray-900 leading-tight">{{ $user['first_name'] }} {{ $user['last_name'] }}</div>
                                            <div class="text-xs text-primary font-bold opacity-60">{{ $user['email'] }}</div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-8 py-6">
                                @if($isEditing)
                                    <div class="space-y-3 w-64 animate-fade-in">
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-primary uppercase ml-1">Anschrift</label>
                                            <div class="flex gap-2">
                                                <input type="text" wire:model="formData.street" placeholder="Str." class="w-2/3 text-sm border-gray-200 rounded-xl bg-white">
                                                <input type="text" wire:model="formData.house_number" placeholder="Nr." class="w-1/3 text-sm border-gray-200 rounded-xl bg-white">
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <input type="text" wire:model="formData.postal" placeholder="PLZ" class="w-1/3 text-sm border-gray-200 rounded-xl bg-white">
                                            <input type="text" wire:model="formData.city" placeholder="Stadt" class="w-2/3 text-sm border-gray-200 rounded-xl bg-white">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] font-black text-primary uppercase ml-1">Telefon</label>
                                            <input type="text" wire:model="formData.phone_number" class="w-full text-sm border-gray-200 rounded-xl bg-white">
                                        </div>
                                    </div>
                                @else
                                    <div class="text-sm space-y-1">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <x-heroicon-m-map-pin class="w-4 h-4 text-gray-300" />
                                            <span class="font-medium">{{ $user['profile']['city'] ?? 'Ort unbekannt' }}</span>
                                        </div>
                                        <div class="text-[10px] text-gray-400 pl-6 leading-tight">
                                            {{ $user['profile']['street'] ?? '' }} {{ $user['profile']['house_number'] ?? '' }}<br>
                                            {{ $user['profile']['phone_number'] ?? '' }}
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td class="px-8 py-6">
                                @if($user['last_seen'])
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-gray-700">{{ \Carbon\Carbon::parse($user['last_seen'])->diffForHumans() }}</span>
                                        <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($user['last_seen'])->format('d.m.Y H:i') }}</span>
                                    </div>
                                @else
                                    <span class="text-[10px] text-gray-300 italic">Noch nie eingeloggt</span>
                                @endif
                            </td>
                            <td class="px-8 py-6">
                                <div class="space-y-2">
                                    <span @class(['px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border',
                                        'bg-purple-50 text-purple-600 border-purple-100' => $user['user_type'] === 'admin',
                                        'bg-indigo-50 text-indigo-600 border-indigo-100' => $user['user_type'] === 'employee',
                                        'bg-gray-50 text-gray-500 border-gray-200' => $user['user_type'] === 'customer'])>
                                        {{ $user['user_type'] }}
                                    </span>
                                    <div class="flex items-center text-[10px] font-bold uppercase tracking-wider">
                                        @if(isset($user['deleted_at']))
                                            <span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span>
                                            <span class="text-red-500">Archiviert</span>
                                        @else
                                            <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                                            <span class="text-green-600">Aktiv</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end items-center gap-2">
                                    @if($isEditing)
                                        <button wire:click="saveInline" class="flex items-center gap-2 bg-gray-900 text-white px-5 py-2.5 rounded-xl text-xs font-black hover:bg-black transition-all shadow-lg">
                                            <x-heroicon-m-check class="w-4 h-4" /> Sichern
                                        </button>
                                        <button wire:click="cancelEdit" class="p-2.5 bg-gray-100 text-gray-400 rounded-xl hover:bg-gray-200 transition-all">
                                            <x-heroicon-m-x-mark class="w-5 h-5" />
                                        </button>
                                    @else
                                        @if(!$showArchive)
                                            <button wire:click="startEdit('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 text-gray-400 hover:text-primary hover:bg-primary/5 rounded-2xl transition-all">
                                                <x-heroicon-m-pencil-square class="w-6 h-6" />
                                            </button>
                                            <button wire:click="archiveUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 text-gray-400 hover:text-orange-500 hover:bg-orange-50 rounded-2xl transition-all">
                                                <x-heroicon-m-archive-box class="w-6 h-6" />
                                            </button>
                                        @else
                                            <button wire:click="restoreUser('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 text-gray-400 hover:text-green-600 rounded-2xl transition-all">
                                                <x-heroicon-m-arrow-path class="w-6 h-6" />
                                            </button>
                                            <button wire:confirm="Permanent löschen?" wire:click="forceDelete('{{ $user['id'] }}', '{{ $user['user_type'] }}')" class="p-3 text-gray-400 hover:text-red-600 rounded-2xl transition-all">
                                                <x-heroicon-m-trash class="w-6 h-6" />
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-8 py-32 text-center text-gray-300 font-serif text-xl italic">Keine Begleiter gefunden...</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages()) <div class="px-8 py-6 bg-gray-50/30 border-t border-gray-100">{{ $users->links() }}</div> @endif
        </div>

    @else
        {{-- LOG BEREICH --}}
        <div class="bg-white rounded-[2.5rem] shadow-xl border border-gray-100 overflow-hidden animate-fade-in">
            <div class="p-8 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <div>
                    <h2 class="text-2xl font-serif font-bold text-gray-900">System-Historie</h2>
                    <p class="text-sm text-gray-500">Transparente Dokumentation aller Stammdaten-Änderungen.</p>
                </div>
            </div>
            <div class="divide-y divide-gray-50">
                @foreach($logs as $log)
                    <div class="p-6 hover:bg-gray-50/50 transition-colors">
                        <div class="flex items-start gap-6">
                            <div @class(['p-3 rounded-2xl shrink-0 shadow-sm',
                                'bg-blue-50 text-blue-500' => $log->action_id === 'user:update',
                                'bg-orange-50 text-orange-500' => $log->action_id === 'user:archive',
                                'bg-green-50 text-green-500' => $log->action_id === 'user:restore',
                                'bg-red-50 text-red-500' => $log->action_id === 'user:destroy'])>
                                <x-heroicon-o-finger-print class="w-6 h-6" />
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-bold text-gray-900">{{ $log->title }}</h4>
                                        <p class="text-sm text-gray-600">{{ $log->message }}</p>
                                    </div>
                                    <div class="text-right text-[10px] text-gray-400 font-bold uppercase">{{ $log->created_at->format('d.m.Y H:i') }}</div>
                                </div>
                                @if($log->payload && isset($log->payload['before']))
                                    <div x-data="{ open: false }" class="mt-4">
                                        <button @click="open = !open" class="text-[10px] font-black uppercase text-primary hover:underline flex items-center gap-1">
                                            Änderungs-Details <x-heroicon-m-chevron-down class="w-3 h-3 transition-transform" ::class="open ? 'rotate-180' : ''" />
                                        </button>
                                        <div x-show="open" x-collapse class="mt-3 grid grid-cols-2 gap-4">
                                            <div class="bg-red-50/50 p-4 rounded-2xl border border-red-100">
                                                <span class="text-[9px] font-black text-red-400 uppercase block mb-2">Vorheriger Stand</span>
                                                <pre class="text-[10px] text-red-700 font-mono whitespace-pre-wrap">{{ json_encode($log->payload['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                            <div class="bg-green-50/50 p-4 rounded-2xl border border-green-100">
                                                <span class="text-[9px] font-black text-green-400 uppercase block mb-2">Aktueller Stand</span>
                                                <pre class="text-[10px] text-green-700 font-mono whitespace-pre-wrap">{{ json_encode($log->payload['after'] ?? $log->payload['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="p-8 bg-gray-50/30">{{ $logs->links() }}</div>
        </div>
    @endif
</div>
