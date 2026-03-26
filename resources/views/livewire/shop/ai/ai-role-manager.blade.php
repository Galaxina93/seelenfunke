<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">

        @if($isCreating || $editingRoleId)
            <!-- Formular Modus (Inline Editing) -->
            <div class="mb-8 flex items-center gap-4">
                <button wire:click="cancel" class="text-gray-400 hover:text-white transition-colors bg-gray-900 border border-gray-800 rounded-full h-10 w-10 flex items-center justify-center shadow-[0_0_15px_rgba(0,0,0,0.5)]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </button>
                <div>
                    <h2 class="text-3xl font-black text-white mb-1 uppercase tracking-wider font-mono">{{ $isCreating ? 'Neue Rolle erstellen' : 'Rolle konfigurieren' }}</h2>
                    <p class="text-gray-400 font-mono text-sm">Verwalte Bezeichnung, Beschreibung und die zugewiesenen Fähigkeiten dieser Kontroll-Instanz.</p>
                </div>
            </div>

            <form wire:submit.prevent="save" class="space-y-10">
                <!-- Basisdaten -->
                <section class="bg-black/40 border border-gray-800/60 shadow-[0_0_20px_rgba(0,0,0,0.3)] rounded-3xl p-6 sm:p-8 backdrop-blur-md relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-gray-900/50 to-transparent pointer-events-none"></div>
                    
                    <div class="relative z-10 flex items-center justify-between border-b border-gray-800/80 pb-4 mb-8">
                        <div>
                            <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-3 font-mono uppercase tracking-widest">
                                <div class="p-2 rounded-lg bg-primary/20 text-primary border border-primary/30 shadow-[0_0_10px_rgba(197,160,89,0.2)]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" /></svg>
                                </div>
                                1. Basisinformationen
                            </h3>
                            <p class="text-xs text-gray-500">Allgemeine Definition für diese Agenten-Rolle.</p>
                        </div>
                    </div>

                    <div class="relative z-10 space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 flex items-center gap-2">Name der Rolle <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.defer="name" required placeholder="z.B. Marketing Experte" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-3 font-mono transition-all">
                            @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Kurzbeschreibung</label>
                            <textarea wire:model.defer="description" rows="4" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-primary focus:ring focus:ring-primary/20 text-emerald-400/90 p-4 font-mono text-sm leading-relaxed transition-all resize-y"></textarea>
                            @error('description') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </section>

                <!-- Tools & Capabilities -->
                <section class="bg-black/40 border border-gray-800/60 rounded-3xl p-6 sm:p-8 backdrop-blur-md relative overflow-hidden shadow-[0_0_20px_rgba(0,0,0,0.3)]">
                    <div class="absolute inset-0 bg-gradient-to-br from-cyan-900/10 to-transparent pointer-events-none"></div>

                    <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between border-b border-gray-800/80 pb-4 mb-8">
                        <div class="mb-4 md:mb-0">
                            <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-3 font-mono uppercase tracking-widest">
                                <div class="p-2 rounded-lg bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 shadow-[0_0_10px_rgba(6,182,212,0.2)]">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M11.828 2.25c-.916 0-1.699.663-1.85 1.567l-.091.549a3.375 3.375 0 01-2.423 2.423l-.549.091c-.904.15-1.567.933-1.567 1.85v.682c0 .916.663 1.699 1.567 1.85l.549.091a3.375 3.375 0 012.423 2.423l.091.549c.15.904.933 1.567 1.85 1.567h.682c.916 0 1.699-.663 1.85-1.567l.091-.549a3.375 3.375 0 012.423-2.423l.549-.091c.904-.15 1.567-.933 1.567-1.85v-.682c0-.916-.663-1.699-1.567-1.85l-.549-.091a3.375 3.375 0 01-2.423-2.423l-.091-.549c-.15-.904-.933-1.567-1.85-1.567h-.682zM6 15a.75.75 0 01-.75.75H4.5a.75.75 0 010-1.5h.75A.75.75 0 016 15zm13.5-.75a.75.75 0 000 1.5h.75a.75.75 0 000-1.5h-.75zM15 6a.75.75 0 01-.75.75H13.5a.75.75 0 010-1.5h.75A.75.75 0 0115 6zM5.25 6a.75.75 0 000 1.5h.75a.75.75 0 000-1.5H5.25z" clip-rule="evenodd" /></svg>
                                </div>
                                2. Fähigkeiten & Werkzeuge
                            </h3>
                            <p class="text-xs text-gray-500">Jeder Agent mit dieser Rolle erbt implizit alle hier markierten Tools.<br>Erlaube nur Werkzeuge, die für den Aufgabenbereich dieser Rolle sicher und nützlich sind.</p>
                        </div>
                        
                        <div class="w-full md:w-auto flex flex-col items-end gap-3 mt-4 md:mt-0">
                            <div class="relative w-full md:w-64">
                                <input type="text" wire:model.live.debounce.300ms="searchTool" placeholder="Werkzeuge filtern..." class="w-full bg-black/40 border border-cyan-900/50 rounded-lg focus:border-cyan-500 focus:ring focus:ring-cyan-500/20 text-white sm:text-sm p-2 pl-9 font-mono transition-all">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-4 w-4 text-cyan-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                </div>
                            </div>
                            <span class="text-xs font-mono text-cyan-400 font-bold bg-cyan-500/10 px-3 py-1.5 rounded border border-cyan-500/20 shadow-[0_0_10px_rgba(6,182,212,0.1)] inline-block text-right">
                                <span x-data x-text="$wire.selectedTools.filter(x => x).length"></span> von {{ $totalToolsCount }} {{ !empty($searchTool) ? 'Gefilterte' : 'Aktiviert' }}
                            </span>
                        </div>
                    </div>

                    <!-- Grouped Tools Rendering -->
                    <div class="space-y-12">
                        @foreach($groupedTools as $category => $data)
                            @if(count($data['tools']) > 0)
                                <div>
                                    <h4 class="text-lg font-black text-white mb-6 flex items-center gap-3 border-b border-gray-800/80 pb-3 uppercase tracking-widest font-mono">
                                        <span class="w-2 h-2 rounded-full bg-primary shadow-[0_0_10px_rgba(197,160,89,0.8)]"></span>
                                        Abteilung: {{ $category }}
                                        <span class="text-xs text-gray-500 ml-auto font-normal tracking-normal">{{ $data['active_count'] }} <span class="text-gray-700">/</span> {{ $data['total_count'] }} AKTIV</span>
                                    </h4>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                                        @foreach($data['tools'] as $tool)
                                            <div x-data="{ 
                                                    expanded: false, 
                                                    get isChecked() { return Array.isArray($wire.selectedTools) && $wire.selectedTools.includes('{{ $tool->id }}'); } 
                                                 }" 
                                                 class="rounded-2xl border transition-all duration-300 flex flex-col overflow-hidden shadow-inner cursor-pointer"
                                                 :class="isChecked ? 'bg-cyan-900/10 border-cyan-500/50 shadow-[0_0_15px_rgba(6,182,212,0.15)]' : 'bg-black/30 border-gray-800/80 hover:border-gray-600'">
                                                
                                                <!-- Header / Brief -->
                                                <div class="p-4 flex items-start gap-4">
                                                    <!-- Checkbox Replika -->
                                                    <button type="button" @click.stop="if(isChecked) { $wire.selectedTools = $wire.selectedTools.filter(t => t !== '{{ $tool->id }}'); } else { $wire.selectedTools.push('{{ $tool->id }}'); }"
                                                        class="shrink-0 w-6 h-6 rounded flex items-center justify-center transition-colors border mt-1"
                                                        :class="isChecked ? 'bg-cyan-500 text-white border-cyan-400 shadow-[0_0_10px_rgba(6,182,212,0.5)]' : 'bg-gray-900 border-gray-700 text-transparent'">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                    </button>
                                                    
                                                    <div class="flex-1 min-w-0" @click="expanded = !expanded">
                                                        <div class="flex justify-between items-center mb-1">
                                                            <h4 class="text-sm font-bold truncate transition-colors font-mono uppercase" :class="isChecked ? 'text-cyan-300' : 'text-gray-200'" title="{{ $tool->name }}">{{ $tool->name }}</h4>
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-gray-500 transition-transform" :class="expanded ? 'rotate-180' : ''"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                                                        </div>
                                                        <div class="text-[9px] font-mono tracking-widest lowercase mb-1 truncate" :class="isChecked ? 'text-cyan-600' : 'text-gray-600'">
                                                            ({{ $tool->identifier }})
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Expandable Details -->
                                                <div x-show="expanded" x-collapse style="display: none;">
                                                    <div class="px-4 pb-4 pt-0 border-t border-white/5 mt-2 bg-black/40">
                                                        <p class="text-[11px] text-gray-400 mt-3 leading-relaxed font-mono whitespace-pre-line">{{ $tool->description ?? 'Keine Dokumentation hinterlegt.' }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="checkbox" wire:model.defer="selectedTools" value="{{ $tool->id }}" class="hidden" id="hidden-tool-{{ $tool->id }}">
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>

                <div class="flex justify-between pt-6 border-t border-gray-800/80 items-center">
                    <button type="button" wire:click="cancel" class="text-gray-400 hover:text-white transition-colors font-mono text-sm uppercase tracking-widest px-4 py-2 rounded-lg hover:bg-gray-900">Zurück</button>
                    <button type="submit" class="bg-primary hover:bg-primary/80 text-gray-900 font-bold py-3.5 px-10 rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:shadow-[0_0_30px_rgba(197,160,89,0.5)] transition-all font-mono uppercase tracking-widest flex items-center gap-2">
                        <svg wire:loading.remove wire:target="save" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd" /></svg>
                        <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-5 w-5 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Rolle Speichern
                    </button>
                </div>
            </form>
        @else
            <!-- Standard Listen-Ansicht -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-3xl font-black text-white mb-1 uppercase tracking-wider font-mono">Agenten Rollen</h2>
                    <p class="text-gray-400 font-mono text-sm">Verwalte die grundlegenden KI-Rollen und Zuständigkeiten.</p>
                </div>
                <button wire:click="create" class="bg-primary hover:bg-primary/80 text-gray-900 font-bold py-2 px-6 rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.3)] transition-all font-mono uppercase tracking-widest flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i>
                    Rolle hinzufügen
                </button>
            </div>

            @if (session()->has('message'))
                <div class="bg-emerald-500/20 border border-emerald-500/50 text-emerald-300 px-4 py-3 rounded-lg mb-6 font-mono text-sm shadow-[0_0_15px_rgba(16,185,129,0.2)] flex items-center gap-3">
                    <i class="bi bi-check-circle-fill"></i>
                    {{ session('message') }}
                </div>
            @endif

            <div class="bg-black/40 border border-gray-800/60 rounded-3xl p-6 sm:p-8 backdrop-blur-md shadow-[0_0_20px_rgba(0,0,0,0.3)]">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-400 font-mono">
                        <thead class="text-xs uppercase bg-gray-900/50 text-gray-400 border-b border-gray-800/80">
                            <tr>
                                <th scope="col" class="px-6 py-4">Rolle</th>
                                <th scope="col" class="px-6 py-4">Beschreibung</th>
                                <th scope="col" class="px-6 py-4">Tools</th>
                                <th scope="col" class="px-6 py-4 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/80">
                            @forelse($roles as $role)
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="px-6 py-4 font-bold text-white">{{ $role->name }}</td>
                                    <td class="px-6 py-4 truncate max-w-xs">{{ $role->description }}</td>
                                    <td class="px-6 py-4 text-cyan-400 font-bold">{{ $role->tools->count() }} aktiv</td>
                                    <td class="px-6 py-4 text-right space-x-3">
                                        <button wire:click="edit('{{ $role->id }}')" class="font-bold text-cyan-500 hover:text-cyan-400 uppercase tracking-widest text-[10px]">Bearbeiten</button>
                                        <button wire:click="delete('{{ $role->id }}')" class="font-bold text-red-500 hover:text-red-400 uppercase tracking-widest text-[10px]" onclick="confirm('Bist du sicher, dass du diese Rolle löschen möchtest?') || event.stopImmediatePropagation()">Löschen</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-gray-500 italic">Keine Rollen gefunden.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
