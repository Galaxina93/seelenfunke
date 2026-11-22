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
                 el.classList.remove('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-white', 'dark:ring-offset-black')
             );
         },
         dragOver(e, type) {
             if (this.draggedType === 'agent' && type === 'department') e.preventDefault();
         },
         dragEnter(e, type) {
             if (this.draggedType === 'agent' && type === 'department')
                 e.currentTarget.classList.add('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-white', 'dark:ring-offset-black');
         },
         dragLeave(e) {
             e.currentTarget.classList.remove('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-white', 'dark:ring-offset-black');
         },
         drop(e, targetType, targetId) {
             e.preventDefault();
             e.currentTarget.classList.remove('drag-over-active', 'ring-2', 'ring-[var(--theme-color)]', 'ring-offset-2', 'ring-offset-white', 'dark:ring-offset-black');

             if (this.draggedType === 'agent' && targetType === 'department') {
                 $wire.moveAgent(this.draggedId, targetId);
             }

             this.draggedType = null;
             this.draggedId = null;
         }
     }">

    {{-- MOBILE DRAG AND DROP POLYFILL --}}
    <link rel="stylesheet" href="{{ asset('vendor/mobile-drag-drop/default.min.css') }}"/>
    <script src="{{ asset('vendor/mobile-drag-drop/index.min.js') }}"></script>
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
             x-on:structure-updated.window="pulseLogo = true; setTimeout(() => pulseLogo = false, 800); $wire.loadStructure()">
        </div>

        <div class="text-center mb-10">
            <h1 class="text-3xl sm:text-4xl font-black text-[var(--theme-color)] tracking-widest uppercase">KI Organigramm</h1>
        </div>

        {{-- Tree Diagram Container --}}
        <div class="relative overflow-x-auto custom-scrollbar pb-20 pt-10">

            <div class="min-w-max flex flex-col items-center justify-center mx-auto">

                {{-- TOP LEVEL: ROOT & STAFF --}}
                <div class="flex items-center justify-center relative w-full">

                    {{-- ROOT NODE: LOGO --}}
                    <div class="flex flex-col items-center relative z-10 group cursor-default">
                        <div class="bg-white dark:bg-gray-950 p-6 sm:px-8 sm:py-6 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-lg flex flex-col items-center justify-center transition-all relative text-center"
                             :class="pulseLogo ? 'border-emerald-500 scale-[1.02]' : 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-50)]'">

                            <img src="/shop/projekt/logo/mein-seelenfunke-logo.svg" alt="Seelenfunke" class="h-24 sm:h-28 mx-auto mb-2 pointer-events-none">
                            <span class="text-[var(--theme-color)] tracking-widest uppercase font-black text-sm block">Headquarters</span>
                            <div class="absolute -bottom-16 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button wire:click="createDepartment" class="bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] rounded-lg px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest hover:bg-[var(--theme-color)] hover:text-white flex items-center gap-1 shadow-sm">
                                    <x-heroicon-m-plus class="w-3 h-3" /> Abteilung
                                </button>
                            </div>
                        </div>
                    </div>

                    </div>

                {{-- Vertical Line down from Root --}}
                @if($departments->count() > 0)
                    <div class="w-px h-12 bg-gray-300 dark:bg-gray-700"></div>
                @endif

                {{-- DEPARTMENTS ROW --}}
                <div class="flex items-start justify-center relative gap-2 sm:gap-3 lg:gap-4">

                    {{-- Horizontal Connecting Line --}}
                    @if($departments->count() > 1)
                        <div class="absolute top-0 h-px bg-gray-300 dark:bg-gray-700" style="width: calc(100% - {{ 100 / count($departments) }}%); left: {{ 50 / count($departments) }}%;"></div>
                    @endif

                    @foreach($departments as $dept)
                        <div class="flex flex-col items-center relative w-[120px] sm:w-32 md:w-36 lg:w-40 shrink-0 group/dept pt-8" wire:key="dept-{{ $dept->id }}">

                            {{-- Line up to horizontal bar --}}
                            @if($departments->count() > 1)
                                <div class="absolute top-0 left-1/2 w-px h-8 bg-gray-300 dark:bg-gray-700 -translate-x-1/2"></div>
                            @endif

                            {{-- Department Card --}}
                            <div class="w-full bg-white dark:bg-black/80 backdrop-blur-xl border border-gray-200 dark:border-gray-800/80 rounded-2xl p-5 cursor-pointer transition-all duration-300 z-10 relative shadow-sm dark:shadow-[0_5px_20px_rgba(0,0,0,0.5)]"
                                 :class="pulseDept === '{{ $dept->id }}' ? 'border-emerald-500 shadow-sm dark:shadow-[0_0_25px_rgba(16,185,129,0.8)] scale-105' : 'border-gray-200 dark:border-gray-800/80 hover:border-{{ $dept->color ?: 'emerald-500' }}/50'"
                                 @dragover="dragOver($event, 'department')"
                                 @dragenter="dragEnter($event, 'department')"
                                 @dragleave="dragLeave"
                                 @drop="drop($event, 'department', '{{ $dept->id }}')">

                                <div class="absolute top-2 right-2 flex items-center gap-1 z-20">
                                    @if(!empty($dept->description))
                                        <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-700 text-gray-400 dark:text-gray-500 text-[9px] font-bold flex items-center justify-center cursor-help" title="{{ $dept->description }}">!</span>
                                    @endif
                                    <button wire:click.stop="editDepartment('{{ $dept->id }}')" class="text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors p-1" title="Bearbeiten">
                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                    </button>
                                </div>

                                <div class="absolute -top-6 left-1/2 -translate-x-1/2 w-12 h-12 bg-white dark:bg-gray-950 border border-{{ $dept->color ?: 'emerald-500' }}/50 rounded-full flex items-center justify-center shadow-sm text-{{ $dept->color ?: 'emerald-500' }} z-20 hover:scale-110 transition-transform">
                                    <x-dynamic-component :component="'heroicon-o-' . ($dept->icon ?: 'building-office')" class="w-6 h-6" />
                                </div>

                                <div class="mt-4 text-center">
                                    <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-widest truncate">{{ $dept->name }}</h2>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest block mt-0.5">{{ $dept->agents->count() }} {{ $dept->agents->count() === 1 ? 'Agent' : 'Agenten' }}</span>
                                </div>
                            </div>

                            {{-- Vertical Line to Agents --}}
                            @if($dept->agents->count() > 0)
                                <div class="w-px h-16 bg-gray-300 dark:bg-gray-800 my-2"></div>
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
                                            <div class="w-px h-6 bg-gray-300 dark:bg-gray-800 absolute -top-6"></div>
                                        @endif

                                        @php
                                            $c = $agent->color ?: 'cyan-500';
                                            $borderClass = match($c) {
                                                'blue-500' => 'border-gray-200 dark:border-gray-800 hover:border-blue-500/50',
                                                'indigo-500' => 'border-gray-200 dark:border-gray-800 hover:border-indigo-500/50',
                                                'purple-500' => 'border-gray-200 dark:border-gray-800 hover:border-purple-500/50',
                                                'pink-500' => 'border-gray-200 dark:border-gray-800 hover:border-pink-500/50',
                                                'amber-500' => 'border-gray-200 dark:border-gray-800 hover:border-amber-500/50',
                                                'orange-500' => 'border-gray-200 dark:border-gray-800 hover:border-orange-500/50',
                                                'yellow-500' => 'border-gray-200 dark:border-gray-800 hover:border-yellow-500/50',
                                                'emerald-500' => 'border-gray-200 dark:border-gray-800 hover:border-emerald-500/50',
                                                'green-500' => 'border-gray-200 dark:border-gray-800 hover:border-green-500/50',
                                                'teal-500' => 'border-gray-200 dark:border-gray-800 hover:border-teal-500/50',
                                                'red-500' => 'border-gray-200 dark:border-gray-800 hover:border-red-500/50',
                                                'rose-500' => 'border-gray-200 dark:border-gray-800 hover:border-rose-500/50',
                                                'cyan-500' => 'border-gray-200 dark:border-gray-800 hover:border-cyan-500/50',
                                                'sky-500' => 'border-gray-200 dark:border-gray-800 hover:border-sky-500/50',
                                                'primary' => 'border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-50)]',
                                                default => 'border-gray-200 dark:border-gray-800 hover:border-cyan-500/50'
                                            };
                                            $iconStyleClass = match($c) {
                                                'blue-500' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
                                                'indigo-500' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
                                                'purple-500' => 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                                                'pink-500' => 'bg-pink-500/10 text-pink-600 dark:text-pink-400 border-pink-500/20',
                                                'amber-500' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                                'orange-500' => 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
                                                'yellow-500' => 'bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border-yellow-500/20',
                                                'emerald-500' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                                'green-500' => 'bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20',
                                                'teal-500' => 'bg-teal-500/10 text-teal-600 dark:text-teal-400 border-teal-500/20',
                                                'red-500' => 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
                                                'rose-500' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                                                'cyan-500' => 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border-cyan-500/20',
                                                'sky-500' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
                                                'primary' => 'bg-[var(--theme-color-10)] text-[var(--theme-color)] border-[var(--theme-color-20)]',
                                                default => 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border-cyan-500/20'
                                            };
                                        @endphp
                                        {{-- Agent Card --}}
                                        <div class="w-11/12 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-4 transition-all z-10 relative cursor-grab active:cursor-grabbing shadow-sm {{ $borderClass }}">

                                            <div class="absolute top-2 right-2 flex items-center">
                                                <button wire:click.stop="editAgent('{{ $agent->id }}')" class="text-gray-400 dark:text-gray-600 hover:text-gray-900 dark:hover:text-white transition-colors p-1" title="Bearbeiten">
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
                                                <h3 class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider truncate text-center w-full group-hover/agent:text-gray-900 dark:group-hover/agent:text-white transition-colors">{{ $agent->name }}</h3>
                                                @php
                                                    $rawModel = $agent->model ?? 'Standard';
                                                    $shortModel = str_replace(['gemini-', '-pro', '-flash'], ['Gemini ', ' Pro', ' Flash'], $rawModel);
                                                    $shortModel = ucwords(trim($shortModel));
                                                @endphp
                                                <div class="text-[8px] uppercase tracking-widest text-gray-500 dark:text-gray-400 truncate mb-2">{{ $shortModel }}</div>

                                                @if($agent->role)
                                                    <div class="text-[9px] bg-blue-500/10 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 px-2 py-1 rounded-md border border-blue-500/25 uppercase tracking-widest text-center truncate max-w-full">
                                                        {{ $agent->role->name }}
                                                    </div>
                                                @else
                                                    <div class="text-[9px] bg-red-500/10 text-red-600 dark:text-red-400 px-2 py-1 rounded-md border border-red-500/20 uppercase tracking-widest text-center">
                                                        Keine Rolle
                                                    </div>
                                                @endif

                                                @php
                                                    $waitingCnt = (int) ($agentWaitingTicketCounts[$agent->id] ?? 0);
                                                    $activeCnt = (int) ($agentActiveTicketCounts[$agent->id] ?? 0);
                                                    $isAgUnit = strtolower((string) $agent->name) === 'antigravity';
                                                @endphp
                                                <div class="mt-2 flex items-center justify-center gap-1.5">
                                                    @if($waitingCnt > 0)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                            {{ $waitingCnt }} Rückfrage
                                                        </span>
                                                    @elseif($activeCnt > 0)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                                            {{ $activeCnt }} aktiv
                                                        </span>
                                                    @elseif($isAgUnit)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold {{ $notebookOnline ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' : 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-500/20' }} border">
                                                            <span class="w-1.5 h-1.5 rounded-full {{ $notebookOnline ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                                            {{ $notebookOnline ? 'Notebook an' : 'Standby' }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            Bereit
                                                        </span>
                                                    @endif
                                                </div>
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
            <button wire:click="closeEditor" class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-full h-10 w-10 flex items-center justify-center shadow-sm">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </button>
            <div>
                <h2 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-wider font-mono">
                    @if($viewMode === 'edit-dept') {{ $editingId ? 'Abteilung bearbeiten' : 'Neue Abteilung erstellen' }} @endif
                </h2>
            </div>
        </div>
    @endif

    {{-- ==========================================
         EDIT: DEPARTMENT
         ========================================== --}}
    @if($viewMode === 'edit-dept')
        <div class="bg-white dark:bg-black/60 backdrop-blur-xl border border-gray-200 dark:border-gray-800 rounded-3xl p-6 sm:p-10 shadow-sm max-w-4xl mx-auto mt-6">
            <div class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-400 uppercase tracking-widest mb-2">Name der Abteilung <span class="text-[var(--theme-color)]">*</span></label>
                        <input type="text" wire:model.live.debounce.500ms="deptName" placeholder="z.B. Marketing" class="w-full bg-gray-50 dark:bg-black/40 border {{ $errors->has('deptName') ? 'border-red-500' : 'border-gray-300 dark:border-gray-700' }} rounded-xl focus:bg-white dark:focus:bg-black/40 focus:border-[var(--theme-color)] focus:ring focus:ring-[var(--theme-color)]/20 text-gray-900 dark:text-white p-3 transition-all">
                        @error('deptName') <span class="text-red-500 text-[10px] mt-1 block uppercase">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-400 uppercase tracking-widest mb-2">Beschreibung</label>
                        <input type="text" wire:model.live.debounce.500ms="deptDescription" placeholder="Kurzbeschreibung des Bereichs..." class="w-full bg-gray-50 dark:bg-black/40 border border-gray-300 dark:border-gray-700 rounded-xl focus:bg-white dark:focus:bg-black/40 focus:border-[var(--theme-color)] focus:ring focus:ring-[var(--theme-color)]/20 text-gray-800 dark:text-gray-300 p-3 transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-3">
                            Abteilungs-Icon Auswählen:
                            <div class="w-8 h-8 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg flex items-center justify-center text-[var(--theme-color)] shadow-sm">
                                <x-dynamic-component :component="'heroicon-o-' . ($deptIcon ?: 'building-office')" class="w-5 h-5" />
                            </div>
                        </label>
                        <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-12 lg:grid-cols-14 gap-2 max-h-48 overflow-y-auto custom-scrollbar p-3 bg-gray-50 dark:bg-black/40 border border-gray-200 dark:border-gray-800 rounded-xl">
                            @foreach($availableIcons as $icon)
                                <button type="button" wire:click="$set('deptIcon', '{{ $icon }}')" class="w-10 h-10 rounded-lg flex items-center justify-center transition-all {{ $deptIcon === $icon ? 'bg-[var(--theme-color)] text-white shadow-sm scale-110 z-10' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800 hover:border-gray-400 dark:hover:border-gray-600 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5" />
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-400 uppercase tracking-widest mb-3">Akzentfarbe</label>
                        <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 gap-2 p-3 bg-gray-50 dark:bg-black/40 border border-gray-200 dark:border-gray-800 rounded-xl">
                            @foreach($availableColors as $col)
                                <button type="button" wire:click="$set('deptColor', '{{ $col }}')"
                                    class="w-8 h-8 rounded-full border-2 transition-all flex items-center justify-center relative group
                                    {{ $deptColor === $col ? 'border-gray-900 dark:border-white scale-110 shadow-sm z-10' : 'border-transparent hover:scale-110 hover:border-gray-500' }}
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
            <div class="mt-10 flex flex-col-reverse sm:flex-row justify-between items-center border-t border-gray-200 dark:border-gray-800 pt-6 gap-4">
                @if($editingId)
                    <button wire:click="deleteDepartment('{{ $editingId }}')" wire:confirm="Bist du sicher? Die Abteilung wird unwiderruflich gelöscht. Alle zugewiesenen Agenten landen in der Stabsstelle." class="w-full sm:w-auto px-4 sm:px-6 py-3 bg-red-500/10 border border-red-500/50 text-red-600 dark:text-red-400 rounded-xl hover:bg-red-500 hover:text-white transition-colors text-xs font-bold uppercase tracking-widest flex justify-center items-center gap-2">
                        <x-heroicon-o-trash class="w-4 h-4" /> Löschen
                    </button>
                @else
                    <div class="hidden sm:block"></div>
                @endif
                <div class="flex w-full sm:w-auto gap-2 sm:gap-4">
                    <button wire:click="closeEditor" class="flex-1 sm:flex-none px-4 sm:px-6 py-3 bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 rounded-xl hover:text-gray-900 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors text-xs font-bold uppercase tracking-widest">
                        Abbrechen
                    </button>
                    <button wire:click="saveDepartment" class="flex-1 sm:flex-none px-5 sm:px-8 py-3 bg-[var(--theme-color)] text-white font-black uppercase tracking-widest text-xs rounded-xl shadow-sm hover:opacity-90 transition-all text-center">
                        {{ $editingId ? 'Speichern' : 'Erstellen' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

</div>
