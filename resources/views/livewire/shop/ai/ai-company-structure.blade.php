<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="w-full mx-auto py-10 px-4 sm:px-6 lg:px-8 font-mono relative min-h-screen pb-32"
     x-data="{
         pulseLogo: false,
         pulseDept: null,
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
                 el.classList.remove('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-black')
             );
         },
         dragOver(e, type) {
             if (this.draggedType === 'role' && type === 'department') e.preventDefault();
             if (this.draggedType === 'agent' && (type === 'role' || type === 'department')) e.preventDefault();
         },
         dragEnter(e, type) {
             if (this.draggedType === 'role' && type === 'department')
                 e.currentTarget.classList.add('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-black');
             if (this.draggedType === 'agent' && type === 'role')
                 e.currentTarget.classList.add('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-black');
         },
         dragLeave(e) {
             e.currentTarget.classList.remove('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-black');
         },
         drop(e, targetType, targetId) {
             e.preventDefault();
             e.currentTarget.classList.remove('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-black');

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
        (function() {
            const init = () => {
                if (typeof MobileDragDrop !== 'undefined') {
                    MobileDragDrop.polyfill({
                        dragImageTranslateOverride: MobileDragDrop.scrollBehaviourDragImageTranslateOverride
                    });
                }
                if (!window.hasMobileDragDropTouchmove) {
                    window.hasMobileDragDropTouchmove = true;
                    window.addEventListener('touchmove', function() {}, {passive: false});
                }
            };

            if (window.Livewire) {
                init();
            } else {
                document.addEventListener('livewire:initialized', init, { once: true });
            }
        })();
    </script>

    @if($viewMode === 'tree')
        {{-- ==========================================
             TREE VIEW
             ========================================== --}}
             
        <div class="hidden" 
             x-on:department-saved.window="pulseDept = $event.detail.id; setTimeout(() => pulseDept = null, 2500)"
             x-on:structure-updated.window="pulseLogo = true; setTimeout(() => pulseLogo = false, 800)">
        </div>

        <div class="text-center mb-12">
            <h1 class="text-3xl sm:text-4xl font-black text-[var(--theme-color)] tracking-widest uppercase shadow-[0_0_15px_var(--theme-color)]/20 drop-shadow-md">KI Organigramm</h1>
            <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest">Die vollständige KI-Unternehmensstruktur im Überblick.</p>
        </div>

        {{-- Tree Diagram Container --}}
        <div class="relative overflow-x-auto custom-scrollbar pb-20 pt-10">

            <div class="min-w-max flex flex-col items-center justify-center mx-auto">

                {{-- TOP LEVEL: ROOT & STAFF --}}
                <div class="flex items-center justify-center relative w-full">

                    {{-- ROOT NODE: LOGO --}}
                    <div class="flex flex-col items-center relative z-10 group cursor-default">
                        <div class="bg-gray-950 p-6 sm:px-8 sm:py-6 rounded-3xl border shadow-[0_0_40px_rgba(0,0,0,0.8)] flex flex-col items-center justify-center transition-all relative text-center"
                             :class="pulseLogo ? 'border-emerald-500 shadow-[0_0_20px_rgba(16,185,129,0.3)] scale-[1.02]' : 'border-gray-800 hover:border-[var(--theme-color-50)]'">

                            <img src="/shop/projekt/logo/mein-seelenfunke-logo.svg" alt="Seelenfunke" class="h-24 sm:h-28 drop-shadow-[0_0_25px_var(--theme-color-40)] mx-auto mb-2 pointer-events-none">
                            <span class="text-[var(--theme-color)] tracking-widest uppercase font-black text-sm block drop-shadow-md">Headquarters</span>
                            <div class="absolute -bottom-16 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="createDepartment" class="bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] rounded-lg px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest hover:bg-[var(--theme-color)] hover:text-black flex items-center gap-1 shadow-[0_0_15px_var(--theme-color-20)]">
                                    <x-heroicon-m-plus class="w-3 h-3" /> Abteilung
                                </button>
                            </div>
                        </div>
                    </div>

                    </div>

                {{-- Vertical Line down from Root --}}
                @if($departments->count() > 0)
                    <div class="w-px h-12 bg-gray-700"></div>
                @endif

                {{-- DEPARTMENTS ROW --}}
                <div class="flex items-start justify-center relative gap-2 sm:gap-3 lg:gap-4">

                    {{-- Horizontal Connecting Line --}}
                    @if($departments->count() > 1)
                        <div class="absolute top-0 h-px bg-gray-700" style="width: calc(100% - {{ 100 / count($departments) }}%); left: {{ 50 / count($departments) }}%;"></div>
                    @endif

                    @foreach($departments as $dept)
                        <div class="flex flex-col items-center relative w-[120px] sm:w-32 md:w-36 lg:w-40 shrink-0 group/dept pt-8" wire:key="dept-{{ $dept->id }}">

                            {{-- Line up to horizontal bar --}}
                            @if($departments->count() > 1)
                                <div class="absolute top-0 left-1/2 w-px h-8 bg-gray-700 -translate-x-1/2"></div>
                            @endif

                            {{-- Department Card --}}
                            <div class="w-full bg-black/80 backdrop-blur-xl border rounded-2xl p-5 cursor-pointer transition-all duration-300 z-10 relative"
                                 :class="pulseDept === '{{ $dept->id }}' ? 'border-emerald-500 shadow-[0_0_25px_rgba(16,185,129,0.8)] scale-105' : 'border-gray-800/80 shadow-[0_5px_20px_rgba(0,0,0,0.5)] hover:border-{{ $dept->color ?: 'emerald-500' }}/50'"
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
                                         wire:key="agent-{{ $agent->id }}"
                                         draggable="true"
                                         @dragstart.stop="startDrag($event, 'agent', '{{ $agent->id }}')"
                                         @dragend="endDrag">

                                        {{-- Connecting Line --}}
                                        @if($index > 0)
                                            <div class="w-px h-6 bg-gray-800 absolute -top-6"></div>
                                        @endif

                                        @php
                                            $c = $agent->color ?: 'cyan-500';
                                            $borderClass = match($c) {
                                                'blue-500' => 'border-gray-800 hover:border-blue-500/50 hover:shadow-[0_0_15px_rgba(59,130,246,0.15)]',
                                                'purple-500' => 'border-gray-800 hover:border-purple-500/50 hover:shadow-[0_0_15px_rgba(168,85,247,0.15)]',
                                                'amber-500' => 'border-gray-800 hover:border-amber-500/50 hover:shadow-[0_0_15px_rgba(245,158,11,0.15)]',
                                                'emerald-500' => 'border-gray-800 hover:border-emerald-500/50 hover:shadow-[0_0_15px_rgba(16,185,129,0.15)]',
                                                'red-500' => 'border-gray-800 hover:border-red-500/50 hover:shadow-[0_0_15px_rgba(239,68,68,0.15)]',
                                                'rose-500' => 'border-gray-800 hover:border-rose-500/50 hover:shadow-[0_0_15px_rgba(244,63,94,0.15)]',
                                                'cyan-500' => 'border-gray-800 hover:border-cyan-500/50 hover:shadow-[0_0_15px_rgba(6,182,212,0.15)]',
                                                'sky-500' => 'border-gray-800 hover:border-sky-500/50 hover:shadow-[0_0_15px_rgba(14,165,233,0.15)]',
                                                'primary' => 'border-gray-800 hover:border-[var(--theme-color-50)] hover:shadow-[0_0_15px_var(--theme-color-15)]',
                                                default => 'border-gray-800 hover:border-cyan-500/50 hover:shadow-[0_0_15px_rgba(6,182,212,0.15)]'
                                            };
                                            $iconStyleClass = match($c) {
                                                'blue-500' => 'bg-blue-500/10 text-blue-500 border-blue-500/20 shadow-[0_0_15px_rgba(59,130,246,0.5)]',
                                                'purple-500' => 'bg-purple-500/10 text-purple-500 border-purple-500/20 shadow-[0_0_15px_rgba(168,85,247,0.5)]',
                                                'amber-500' => 'bg-amber-500/10 text-amber-500 border-amber-500/20 shadow-[0_0_15px_rgba(245,158,11,0.5)]',
                                                'emerald-500' => 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 shadow-[0_0_15px_rgba(16,185,129,0.5)]',
                                                'red-500' => 'bg-red-500/10 text-red-500 border-red-500/20 shadow-[0_0_15px_rgba(239,68,68,0.5)]',
                                                'rose-500' => 'bg-rose-500/10 text-rose-500 border-rose-500/20 shadow-[0_0_15px_rgba(244,63,94,0.5)]',
                                                'cyan-500' => 'bg-cyan-500/10 text-cyan-500 border-cyan-500/20 shadow-[0_0_15px_rgba(6,182,212,0.5)]',
                                                'sky-500' => 'bg-sky-500/10 text-sky-500 border-sky-500/20 shadow-[0_0_15px_rgba(14,165,233,0.5)]',
                                                'primary' => 'bg-[var(--theme-color-10)] text-[var(--theme-color)] border-[var(--theme-color-20)] shadow-[0_0_15px_var(--theme-color-50)]',
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
                                                <div class="w-16 h-16 rounded-xl flex items-center justify-center shrink-0 border mb-3 {{ $iconStyleClass }}">
                                                    @if($agent->profile_picture)
                                                        <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover rounded-xl pointer-events-none">
                                                    @else
                                                        @php
                                                            $agentIcon = $agent->department ? $agent->department->icon : ($agent->icon ?: 'sparkles');
                                                        @endphp
                                                        <x-dynamic-component :component="'heroicon-s-' . $agentIcon" class="w-8 h-8 pointer-events-none" />
                                                    @endif
                                                </div>
                                                <h3 class="text-xs font-bold text-gray-200 uppercase tracking-wider truncate text-center w-full group-hover/agent:text-white transition-colors">{{ $agent->name }}</h3>
                                                @php
                                                    $rawModel = $agent->model ?? 'Standard';
                                                    $shortModel = str_replace(['gemini-', '-pro', '-flash'], ['Gemini ', ' Pro', ' Flash'], $rawModel);
                                                    $shortModel = ucwords(trim($shortModel));
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
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Name der Abteilung <span class="text-[var(--theme-color)]">*</span></label>
                        <input type="text" wire:model.live.debounce.500ms="deptName" placeholder="z.B. Marketing" class="w-full bg-black/40 border {{ $errors->has('deptName') ? 'border-red-500' : 'border-gray-700' }} rounded-xl focus:border-[var(--theme-color)] focus:ring focus:ring-[var(--theme-color)]/20 text-white p-3 transition-all">
                        @error('deptName') <span class="text-red-500 text-[10px] mt-1 block uppercase">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Beschreibung</label>
                        <input type="text" wire:model.live.debounce.500ms="deptDescription" placeholder="Kurzbeschreibung des Bereichs..." class="w-full bg-black/40 border border-gray-700 rounded-xl focus:border-[var(--theme-color)] focus:ring focus:ring-[var(--theme-color)]/20 text-gray-300 p-3 transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-3">
                            Abteilungs-Icon Auswählen:
                            <div class="w-8 h-8 bg-gray-900 border border-gray-700 rounded-lg flex items-center justify-center text-[var(--theme-color)] shadow-[0_0_10px_var(--theme-color-20)]">
                                <x-dynamic-component :component="'heroicon-o-' . ($deptIcon ?: 'building-office')" class="w-5 h-5" />
                            </div>
                        </label>
                        <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 lg:grid-cols-14 gap-2 max-h-48 overflow-y-auto custom-scrollbar p-3 bg-black/40 border border-gray-800 rounded-xl">
                            @foreach($availableIcons as $icon)
                                <button type="button" wire:click="$set('deptIcon', '{{ $icon }}')" class="w-10 h-10 rounded-lg flex items-center justify-center transition-all {{ $deptIcon === $icon ? 'bg-[var(--theme-color)] text-black shadow-[0_0_15px_var(--theme-color-50)] scale-110 z-10' : 'bg-gray-900 text-gray-400 border border-gray-800 hover:border-gray-600 hover:text-white hover:bg-gray-800' }}">
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
            <div class="mt-10 flex flex-col-reverse sm:flex-row justify-between items-center border-t border-gray-800 pt-6 gap-4">
                @if($editingId)
                    <button wire:click="deleteDepartment('{{ $editingId }}')" wire:confirm="Bist du sicher? Die Abteilung wird unwiderruflich gelöscht. Alle zugewiesenen Agenten landen in der Stabsstelle." class="w-full sm:w-auto px-4 sm:px-6 py-3 bg-red-500/10 border border-red-500/50 text-red-500 rounded-xl hover:bg-red-500 hover:text-white transition-colors text-xs font-bold uppercase tracking-widest flex justify-center items-center gap-2">
                        <x-heroicon-o-trash class="w-4 h-4" /> Löschen
                    </button>
                @else
                    <div class="hidden sm:block"></div>
                @endif
                <div class="flex w-full sm:w-auto gap-2 sm:gap-4">
                    <button wire:click="closeEditor" class="flex-1 sm:flex-none px-4 sm:px-6 py-3 bg-gray-900 border border-gray-700 text-gray-400 rounded-xl hover:text-white hover:bg-gray-800 transition-colors text-xs font-bold uppercase tracking-widest">
                        Abbrechen
                    </button>
                    <button wire:click="saveDepartment" class="flex-1 sm:flex-none px-5 sm:px-8 py-3 bg-[var(--theme-color)] text-black font-black uppercase tracking-widest text-xs rounded-xl shadow-[0_0_20px_var(--theme-color-40)] hover:scale-105 transition-all text-center">
                        {{ $editingId ? 'Speichern' : 'Erstellen' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

</div>
