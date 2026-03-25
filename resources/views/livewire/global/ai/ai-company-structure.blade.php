<div class="max-w-[1400px] mx-auto py-10 px-4 sm:px-6 lg:px-8 font-mono relative min-h-screen pb-32"
     x-data="{
         pulseLogo: @entangle('showSuccessBanner'),
         draggedType: null,
         draggedId: null,
         startDrag(e, type, id) {
             this.draggedType = type;
             this.draggedId = id;
             e.dataTransfer.effectAllowed = 'move';
             e.dataTransfer.setData('text/plain', id);
             setTimeout(() => e.target.classList.add('opacity-40', 'scale-95', 'z-50'), 0);
         },
         endDrag(e) {
             this.draggedType = null;
             this.draggedId = null;
             e.target.classList.remove('opacity-40', 'scale-95', 'z-50');
             document.querySelectorAll('.drag-over-active').forEach(el => 
                 el.classList.remove('drag-over-active', 'ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-black')
             );
         },
         dragOver(e, type) {
             if (this.draggedType === 'role' && type === 'department') e.preventDefault();
             if (this.draggedType === 'agent' && (type === 'role' || type === 'department')) e.preventDefault();
         },
         dragEnter(e, type) {
             if (this.draggedType === 'role' && type === 'department') 
                 e.currentTarget.classList.add('drag-over-active', 'ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-black');
             if (this.draggedType === 'agent' && type === 'role') 
                 e.currentTarget.classList.add('drag-over-active', 'ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-black');
         },
         dragLeave(e) {
             e.currentTarget.classList.remove('drag-over-active', 'ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-black');
         },
         drop(e, targetType, targetId) {
             e.preventDefault();
             e.currentTarget.classList.remove('drag-over-active', 'ring-2', 'ring-primary', 'ring-offset-2', 'ring-offset-black');
             
             if (this.draggedType === 'role' && targetType === 'department') {
                 $wire.moveRole(this.draggedId, targetId);
             } else if (this.draggedType === 'agent' && targetType === 'department') {
                 $wire.moveAgent(this.draggedId, targetId);
             }
             
             this.draggedType = null;
             this.draggedId = null;
         }
     }">

    {{-- MOBILE DRAG AND DROP POLYFILL --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/default.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/index.min.js"></script>
    <script>
        document.addEventListener('livewire:initialized', () => {
            MobileDragDrop.polyfill({
                dragImageTranslateOverride: MobileDragDrop.scrollBehaviourDragImageTranslateOverride
            });
            window.addEventListener('touchmove', function() {}, {passive: false});
        });
    </script>

    @if($viewMode === 'tree')
        {{-- ==========================================
             TREE VIEW 
             ========================================== --}}
        
        <div class="text-center mb-12">
            <h1 class="text-3xl sm:text-4xl font-black text-primary tracking-widest uppercase shadow-primary/20 drop-shadow-md">KI Organigramm</h1>
            <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest">Die vollständige KI-Unternehmensstruktur im Überblick.</p>
        </div>

        {{-- Tree Diagram Container --}}
        <div class="relative overflow-x-auto custom-scrollbar pb-20 pt-10 px-10">
            
            <div class="min-w-max flex flex-col items-center justify-center mx-auto">
                
                {{-- ROOT NODE: LOGO --}}
                <div class="flex flex-col items-center relative z-10 group cursor-default" x-init="$watch('pulseLogo', value => { if(value) setTimeout(() => pulseLogo = false, 1500) })">
                    <div class="bg-gray-950 p-6 sm:px-8 sm:py-6 rounded-3xl border shadow-[0_0_40px_rgba(0,0,0,0.8)] flex flex-col items-center justify-center transition-all relative text-center"
                         :class="pulseLogo ? 'border-emerald-500 shadow-[0_0_50px_rgba(16,185,129,0.8)] scale-105' : 'border-gray-800 hover:border-primary/50'">
                        <img src="/images/projekt/logo/mein-seelenfunke-logo.svg" alt="Seelenfunke" class="h-24 sm:h-28 drop-shadow-[0_0_25px_rgba(197,160,89,0.4)] mx-auto mb-2 pointer-events-none">
                        <span class="text-primary tracking-widest uppercase font-black text-sm block drop-shadow-md">Headquarters</span>
                        <div class="absolute -bottom-16 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click="createDepartment" class="bg-primary/10 text-primary border border-primary/30 rounded-lg px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest hover:bg-primary hover:text-black flex items-center gap-1 shadow-[0_0_15px_rgba(197,160,89,0.2)]">
                                <x-heroicon-m-plus class="w-3 h-3" /> Abteilung
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Vertical Line down from Root --}}
                @if($departments->count() > 0)
                    <div class="w-px h-12 bg-gray-700"></div>
                @endif

                {{-- DEPARTMENTS ROW --}}
                <div class="flex items-start justify-center relative gap-2 sm:gap-4 lg:gap-6">
                    
                    {{-- Horizontal Connecting Line --}}
                    @if($departments->count() > 1)
                        <div class="absolute top-0 h-px bg-gray-700" style="width: calc(100% - {{ 100 / count($departments) }}%); left: {{ 50 / count($departments) }}%;"></div>
                    @endif

                    @foreach($departments as $dept)
                        <div class="flex flex-col items-center relative w-40 md:w-44 lg:w-48 shrink-0 group/dept pt-8">
                            
                            {{-- Line up to horizontal bar --}}
                            @if($departments->count() > 1)
                                <div class="absolute top-0 left-1/2 w-px h-8 bg-gray-700 -translate-x-1/2"></div>
                            @endif

                            {{-- Department Card --}}
                            <div class="w-full bg-black/80 backdrop-blur-xl border border-gray-800/80 rounded-2xl p-5 shadow-[0_5px_20px_rgba(0,0,0,0.5)] cursor-pointer hover:border-{{ $dept->color ?: 'emerald-500' }}/50 transition-all z-10 relative"
                                 @dragover="dragOver($event, 'department')"
                                 @dragenter="dragEnter($event, 'department')"
                                 @dragleave="dragLeave"
                                 @drop="drop($event, 'department', '{{ $dept->id }}')">
                                 
                                <div class="absolute top-2 right-2 flex items-center z-20">
                                    <button wire:click.stop="editDepartment('{{ $dept->id }}')" class="text-gray-500 hover:text-white transition-colors p-1" title="Bearbeiten">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </button>
                                </div>

                                <div class="absolute -top-6 left-1/2 -translate-x-1/2 w-12 h-12 bg-gray-950 border border-{{ $dept->color ?: 'emerald-500' }}/50 rounded-full flex items-center justify-center shadow-[0_0_15px_currentColor] text-{{ $dept->color ?: 'emerald-500' }} z-20 hover:scale-110 transition-transform">
                                    <x-dynamic-component :component="'heroicon-o-' . ($dept->icon ?: 'building-office')" class="w-6 h-6" />
                                </div>

                                <div class="mt-4 text-center pb-2">
                                    <h2 class="text-sm font-bold text-white uppercase tracking-widest truncate">{{ $dept->name }}</h2>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest block">{{ $dept->agents->count() }} Agenten</span>
                                </div>
                                <p class="text-[10px] text-gray-400 leading-relaxed line-clamp-2 h-7 w-full">{{ $dept->description ?: 'Keine Beschreibung' }}</p>
                            </div>

                            {{-- Vertical Line to Agents --}}
                            @if($dept->agents->count() > 0)
                                <div class="w-px h-16 bg-gray-800 my-2"></div>
                            @endif

                            {{-- AGENTS COLUMN --}}
                            <div class="flex flex-col gap-6 w-full items-center relative drop-zone"
                                 @dragover="dragOver($event, 'department')"
                                 @dragenter="dragEnter($event, 'department')"
                                 @dragleave="dragLeave"
                                 @drop="drop($event, 'department', '{{ $dept->id }}')">
                                 
                                @foreach($dept->agents as $index => $agent)
                                    <div class="flex flex-col items-center relative w-full group/agent z-10"
                                         draggable="true"
                                         @dragstart.stop="startDrag($event, 'agent', '{{ $agent->id }}')" 
                                         @dragend="endDrag">
                                        
                                        {{-- Connecting Line --}}
                                        @if($index > 0)
                                            <div class="w-px h-6 bg-gray-800 absolute -top-6"></div>
                                        @endif

                                        @php
                                            $c = $agent->department ? $agent->department->color : 'cyan-500';
                                            $borderClass = match($c) {
                                                'blue-500' => 'border-gray-800 hover:border-blue-500/50 hover:shadow-[0_0_15px_rgba(59,130,246,0.15)]',
                                                'purple-500' => 'border-gray-800 hover:border-purple-500/50 hover:shadow-[0_0_15px_rgba(168,85,247,0.15)]',
                                                'amber-500' => 'border-gray-800 hover:border-amber-500/50 hover:shadow-[0_0_15px_rgba(245,158,11,0.15)]',
                                                'emerald-500' => 'border-gray-800 hover:border-emerald-500/50 hover:shadow-[0_0_15px_rgba(16,185,129,0.15)]',
                                                'rose-500' => 'border-gray-800 hover:border-rose-500/50 hover:shadow-[0_0_15px_rgba(244,63,94,0.15)]',
                                                'cyan-500' => 'border-gray-800 hover:border-cyan-500/50 hover:shadow-[0_0_15px_rgba(6,182,212,0.15)]',
                                                'sky-500' => 'border-gray-800 hover:border-sky-500/50 hover:shadow-[0_0_15px_rgba(14,165,233,0.15)]',
                                                'primary' => 'border-gray-800 hover:border-primary/50 hover:shadow-[0_0_15px_rgba(197,160,89,0.15)]',
                                                default => 'border-gray-800 hover:border-cyan-500/50 hover:shadow-[0_0_15px_rgba(6,182,212,0.15)]'
                                            };
                                            $iconStyleClass = match($c) {
                                                'blue-500' => 'bg-blue-500/10 text-blue-500 border-blue-500/20 shadow-[0_0_15px_rgba(59,130,246,0.5)]',
                                                'purple-500' => 'bg-purple-500/10 text-purple-500 border-purple-500/20 shadow-[0_0_15px_rgba(168,85,247,0.5)]',
                                                'amber-500' => 'bg-amber-500/10 text-amber-500 border-amber-500/20 shadow-[0_0_15px_rgba(245,158,11,0.5)]',
                                                'emerald-500' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 shadow-[0_0_15px_rgba(16,185,129,0.5)]',
                                                'rose-500' => 'bg-rose-500/10 text-rose-500 border-rose-500/20 shadow-[0_0_15px_rgba(244,63,94,0.5)]',
                                                'cyan-500' => 'bg-cyan-500/10 text-cyan-500 border-cyan-500/20 shadow-[0_0_15px_rgba(6,182,212,0.5)]',
                                                'sky-500' => 'bg-sky-500/10 text-sky-500 border-sky-500/20 shadow-[0_0_15px_rgba(14,165,233,0.5)]',
                                                'primary' => 'bg-primary/10 text-primary border-primary/20 shadow-[0_0_15px_rgba(197,160,89,0.5)]',
                                                default => 'bg-cyan-500/10 text-cyan-500 border-cyan-500/20 shadow-[0_0_15px_rgba(6,182,212,0.5)]'
                                            };
                                        @endphp
                                        {{-- Agent Card --}}
                                        <div class="w-11/12 bg-gray-950 border rounded-xl p-4 transition-all z-10 relative cursor-grab active:cursor-grabbing {{ $borderClass }}">
                                            
                                            <div class="absolute top-2 right-2 flex items-center">
                                                <button wire:click.stop="editAgent('{{ $agent->id }}')" class="text-gray-600 hover:text-white transition-colors p-1" title="Bearbeiten">
                                                    <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                                </button>
                                            </div>

                                            <div class="flex flex-col items-center justify-center p-2 cursor-pointer" wire:click.stop="editAgent('{{ $agent->id }}')">
                                                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 border mb-3 {{ $iconStyleClass }}">
                                                    @if($agent->profile_picture)
                                                        <img src="{{ Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover rounded-xl pointer-events-none">
                                                    @else
                                                        @php
                                                            $agentIcon = $agent->department ? $agent->department->icon : ($agent->icon ?: 'sparkles');
                                                        @endphp
                                                        <x-dynamic-component :component="'heroicon-s-' . $agentIcon" class="w-6 h-6 pointer-events-none" />
                                                    @endif
                                                </div>
                                                <h3 class="text-xs font-bold text-gray-200 uppercase tracking-wider truncate text-center w-full group-hover/agent:text-white transition-colors">{{ $agent->name }}</h3>
                                                @php
                                                    $rawModel = $agent->model ?? 'Standard';
                                                    if(str_contains($rawModel, 'Ministral')) $shortModel = 'Ministral';
                                                    elseif(str_contains($rawModel, 'Devstral')) $shortModel = 'Devstral';
                                                    elseif(str_contains($rawModel, 'GPT-OSS')) $shortModel = 'GPT-OSS';
                                                    else $shortModel = explode('-', explode(' ', $rawModel)[0])[0];
                                                @endphp
                                                <div class="text-[8px] uppercase tracking-widest text-gray-500 truncate mb-2">{{ $shortModel }}</div>
                                                
                                                @if($agent->role)
                                                    <div class="text-[9px] bg-blue-500/20 text-blue-400/90 px-2 py-1 rounded-md border border-blue-500/30 uppercase tracking-widest text-center truncate max-w-full">
                                                        {{ $agent->role->name }}
                                                    </div>
                                                @else
                                                    <div class="text-[9px] bg-red-500/10 text-red-500/80 px-2 py-1 rounded-md border border-red-500/20 uppercase tracking-widest text-center">
                                                        Keine Rolle
                                                    </div>
                                                @endif
                                            </div>

                                        </div>
                                    </div>
                                @endforeach

                                {{-- Add Agent Dropdown --}}
                                <div class="w-11/12 mt-2" x-data="{ open: false }">
                                    <button @click="open = !open" class="w-full border border-dashed border-gray-700 hover:border-cyan-500 hover:text-cyan-400 text-gray-600 rounded-lg p-3 text-[10px] uppercase tracking-widest transition-all font-bold flex items-center justify-between group">
                                        <span class="flex items-center gap-2"><x-heroicon-m-plus class="w-3 h-3 group-hover:scale-110 transition-transform" /> Agent Zuweisen</span>
                                        <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition.opacity class="mt-2 bg-black/90 backdrop-blur-md border border-gray-800 rounded-xl shadow-2xl overflow-hidden z-30 absolute left-0 right-0 w-11/12 mx-auto origin-top text-left">
                                        <div class="max-h-48 overflow-y-auto custom-scrollbar">
                                            @forelse($freeAgents as $freeAgent)
                                                <button wire:click.stop="moveAgent('{{ $freeAgent->id }}', '{{ $dept->id }}')" @click="open = false" class="w-full text-left p-3 text-[10px] text-gray-300 hover:bg-gray-800 hover:text-white border-b border-gray-800/50 flex flex-col transition-colors group">
                                                    <span class="font-bold text-cyan-500 group-hover:text-cyan-400 flex items-center justify-between">
                                                        {{ $freeAgent->name }}
                                                        <x-heroicon-o-arrow-right-circle class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                                                    </span>
                                                    @if($freeAgent->role)
                                                        <span class="text-[9px] text-gray-500 truncate mt-1">{{ $freeAgent->role->name }}</span>
                                                    @endif
                                                </button>
                                            @empty
                                                <div class="p-4 text-[10px] text-gray-500 italic text-center">Keine freien Agenten verfügbar.</div>
                                            @endforelse
                                        </div>
                                        <div class="p-2 border-t border-gray-800">
                                            <button wire:click.stop="moveAgent('{{ $freeAgent->id ?? null }}', 'unassigned')" class="hidden"></button> {{-- For Drop Handler --}}
                                            <a href="{{ route('admin.ai-agents') }}" class="block w-full text-center p-2 text-[9px] font-bold uppercase tracking-widest text-gray-400 hover:bg-cyan-500/10 hover:text-cyan-500 rounded transition-colors">Im Manager Erschaffen</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    @endif


    {{-- ==========================================
         INLINE EDITORS HEADER
         ========================================== --}}
    @if($viewMode !== 'tree')
        <div class="mb-8 flex items-center gap-4">
            <button wire:click="closeEditor" class="text-gray-400 hover:text-white transition-colors bg-gray-900 border border-gray-800 rounded-full h-10 w-10 flex items-center justify-center shadow-[0_0_15px_rgba(0,0,0,0.5)]">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </button>
            <div>
                <h2 class="text-3xl font-black text-white mb-1 uppercase tracking-wider font-mono">
                    @if($viewMode === 'edit-dept') {{ $editingId ? 'Abteilung bearbeiten' : 'Neue Abteilung erstellen' }} @endif
                </h2>
                <p class="text-gray-400 font-mono text-sm">Organigramm Struktur verwalten.</p>
            </div>
        </div>
    @endif

    {{-- ==========================================
         EDIT: DEPARTMENT 
         ========================================== --}}
    @if($viewMode === 'edit-dept')
        <div class="bg-black/60 backdrop-blur-xl border border-gray-800 rounded-3xl p-6 sm:p-10 shadow-[0_0_30px_rgba(0,0,0,0.5)] max-w-4xl mx-auto mt-6">
            <div class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Name der Abteilung <span class="text-primary">*</span></label>
                        <input type="text" wire:model.live.debounce.500ms="deptName" placeholder="z.B. Marketing" class="w-full bg-black/40 border {{ $errors->has('deptName') ? 'border-red-500' : 'border-gray-700' }} rounded-xl focus:border-primary focus:ring focus:ring-primary/20 text-white p-3 transition-all">
                        @error('deptName') <span class="text-red-500 text-[10px] mt-1 block uppercase">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Beschreibung</label>
                        <input type="text" wire:model.live.debounce.500ms="deptDescription" placeholder="Kurzbeschreibung des Bereichs..." class="w-full bg-black/40 border border-gray-700 rounded-xl focus:border-primary focus:ring focus:ring-primary/20 text-gray-300 p-3 transition-all">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 gap-8">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-3">
                            Abteilungs-Icon Auswählen: 
                            <div class="w-8 h-8 bg-gray-900 border border-gray-700 rounded-lg flex items-center justify-center text-primary shadow-[0_0_10px_rgba(197,160,89,0.2)]">
                                <x-dynamic-component :component="'heroicon-o-' . ($deptIcon ?: 'building-office')" class="w-5 h-5" />
                            </div>
                        </label>
                        <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 lg:grid-cols-14 gap-2 max-h-48 overflow-y-auto custom-scrollbar p-3 bg-black/40 border border-gray-800 rounded-xl">
                            @foreach($availableIcons as $icon)
                                <button type="button" wire:click="$set('deptIcon', '{{ $icon }}')" class="w-10 h-10 rounded-lg flex items-center justify-center transition-all {{ $deptIcon === $icon ? 'bg-primary text-black shadow-[0_0_15px_rgba(197,160,89,0.5)] scale-110 z-10' : 'bg-gray-900 text-gray-400 border border-gray-800 hover:border-gray-600 hover:text-white hover:bg-gray-800' }}">
                                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5" />
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Akzentfarbe</label>
                        <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 gap-2 p-3 bg-black/40 border border-gray-800 rounded-xl">
                            @foreach($availableColors as $col)
                                <button type="button" wire:click="$set('deptColor', '{{ $col }}')" 
                                    class="w-8 h-8 rounded-full border-2 transition-all flex items-center justify-center relative group
                                    {{ $deptColor === $col ? 'border-white scale-110 shadow-[0_0_15px_currentColor] z-10' : 'border-transparent hover:scale-110 hover:border-gray-500' }}
                                    bg-{{ $col }} text-{{ $col }}"
                                    title="{{ $col }}">
                                    @if($deptColor === $col)
                                        <div class="w-3 h-3 bg-white rounded-full"></div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        @error('deptColor') <span class="text-red-500 text-[10px] mt-1 block uppercase">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="mt-10 flex justify-end gap-4 border-t border-gray-800 pt-6">
                <button wire:click="closeEditor" class="px-6 py-3 bg-gray-900 border border-gray-700 text-gray-400 rounded-xl hover:text-white hover:bg-gray-800 transition-colors text-xs font-bold uppercase tracking-widest">
                    Abbrechen
                </button>
                <button wire:click="saveDepartment" class="px-8 py-3 bg-primary text-black font-black uppercase tracking-widest text-xs rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.4)] hover:scale-105 transition-all">
                    {{ $editingId ? 'Änderungen Speichern' : 'Abteilung Erstellen' }}
                </button>
            </div>
        </div>
    @endif
</div>
